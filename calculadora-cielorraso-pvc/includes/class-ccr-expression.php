<?php
/**
 * Motor de fórmulas seguro.
 *
 * Analizador sintáctico descendente recursivo + evaluador de AST.
 * NO usa eval(): solo acepta números, variables, operadores y una lista blanca de funciones.
 *
 * Operadores (de menor a mayor precedencia):
 *   c ? a : b    ||    &&    == != < <= > >=    + -    * / %    unario - + !    ^ (potencia)
 *
 * @package CalculadoraCielorrasoPVC
 */

defined( 'ABSPATH' ) || exit;

class CCR_Expression {

	const MAX_LENGTH = 4000;
	const MAX_DEPTH  = 80;

	/** @var array Caché de AST por fórmula. */
	private static $ast_cache = array();

	/** @var array[] Tokens de la fórmula actual. */
	private $tokens = array();

	/** @var int */
	private $pos = 0;

	/** @var int */
	private $depth = 0;

	/**
	 * Evalúa una fórmula con un contexto de variables.
	 *
	 * @param string $formula Fórmula.
	 * @param array  $vars    nombre => número.
	 * @return float
	 * @throws CCR_Expression_Exception Si hay un error.
	 */
	public static function evaluate( $formula, array $vars ) {
		$ast   = self::parse( $formula );
		$value = self::eval_node( $ast, $vars );
		if ( ! is_finite( $value ) ) {
			throw new CCR_Expression_Exception( __( 'El resultado no es un número finito.', 'calculadora-cielorraso-pvc' ) );
		}
		return $value;
	}

	/**
	 * Valida la sintaxis. Devuelve true o el mensaje de error.
	 *
	 * @param string $formula Fórmula.
	 * @return true|string
	 */
	public static function validate( $formula ) {
		try {
			self::parse( $formula );
			return true;
		} catch ( CCR_Expression_Exception $e ) {
			return $e->getMessage();
		}
	}

	/**
	 * Lista de variables usadas en una fórmula.
	 *
	 * @param string $formula Fórmula.
	 * @return string[]
	 */
	public static function variables( $formula ) {
		try {
			$ast = self::parse( $formula );
		} catch ( CCR_Expression_Exception $e ) {
			return array();
		}
		$out = array();
		self::collect_vars( $ast, $out );
		return array_values( array_unique( $out ) );
	}

	/**
	 * Funciones disponibles: nombre => array( min args, max args (-1 = ilimitado), descripción ).
	 */
	public static function functions() {
		return array(
			'si'                 => array( 3, 3, 'si(condición, valor_si, valor_no)' ),
			'if'                 => array( 3, 3, 'Igual que si()' ),
			'min'                => array( 1, -1, 'min(a, b, ...)' ),
			'max'                => array( 1, -1, 'max(a, b, ...)' ),
			'abs'                => array( 1, 1, 'Valor absoluto' ),
			'ceil'               => array( 1, 1, 'Redondeo hacia arriba (tolerante a errores de coma flotante)' ),
			'floor'              => array( 1, 1, 'Redondeo hacia abajo (tolerante a errores de coma flotante)' ),
			'round'              => array( 1, 2, 'round(x, decimales)' ),
			'sqrt'               => array( 1, 1, 'Raíz cuadrada' ),
			'pow'                => array( 2, 2, 'pow(base, exponente)' ),
			'multiplo_superior'  => array( 2, 2, 'multiplo_superior(x, m): redondea x hacia arriba al múltiplo de m' ),
			'multiplo_inferior'  => array( 2, 2, 'multiplo_inferior(x, m): redondea x hacia abajo al múltiplo de m' ),
			'lineas'             => array( 2, 3, 'lineas(longitud, separación, [tolerancia]): cantidad de líneas de estructura' ),
			'piezas_lineales'    => array( 3, 4, 'piezas_lineales(longitud_línea, largo_pieza, cantidad_líneas, [solape]): piezas para cubrir N líneas reaprovechando recortes' ),
			'piezas_perimetro'   => array( 3, 3, 'piezas_perimetro(lado_a, lado_b, largo_pieza): piezas para el perímetro reaprovechando recortes' ),
			'piezas_superficie'  => array( 4, 4, 'piezas_superficie(lado_paralelo, lado_perpendicular, largo_pieza, ancho_pieza): láminas para cubrir la superficie reaprovechando recortes' ),
		);
	}

	/* --------------------------------------------------------------------
	 * Análisis sintáctico
	 * ------------------------------------------------------------------ */

	private static function parse( $formula ) {
		$formula = trim( (string) $formula );
		if ( '' === $formula ) {
			throw new CCR_Expression_Exception( __( 'La fórmula está vacía.', 'calculadora-cielorraso-pvc' ) );
		}
		if ( strlen( $formula ) > self::MAX_LENGTH ) {
			throw new CCR_Expression_Exception( __( 'La fórmula es demasiado larga.', 'calculadora-cielorraso-pvc' ) );
		}
		$key = md5( $formula );
		if ( isset( self::$ast_cache[ $key ] ) ) {
			return self::$ast_cache[ $key ];
		}

		$parser         = new self();
		$parser->tokens = self::tokenize( $formula );
		$ast            = $parser->parse_expression();
		if ( $parser->peek() ) {
			$tok = $parser->peek();
			/* translators: %s: token */
			throw new CCR_Expression_Exception( sprintf( __( 'Símbolo inesperado "%s".', 'calculadora-cielorraso-pvc' ), $tok[1] ) );
		}
		self::$ast_cache[ $key ] = $ast;
		return $ast;
	}

	private static function tokenize( $s ) {
		$tokens = array();
		$len    = strlen( $s );
		$i      = 0;
		$ops2   = array( '<=', '>=', '==', '!=', '&&', '||' );
		$ops1   = array( '+', '-', '*', '/', '%', '^', '(', ')', ',', '<', '>', '!', '?', ':' );

		while ( $i < $len ) {
			$c = $s[ $i ];
			if ( ctype_space( $c ) ) {
				$i++;
				continue;
			}
			if ( ctype_digit( $c ) || ( '.' === $c && $i + 1 < $len && ctype_digit( $s[ $i + 1 ] ) ) ) {
				if ( ! preg_match( '/\G\d*\.?\d+(?:[eE][-+]?\d+)?|\G\d+\.?/', $s, $m, 0, $i ) ) {
					throw new CCR_Expression_Exception( __( 'Número mal formado.', 'calculadora-cielorraso-pvc' ) );
				}
				$tokens[] = array( 'num', $m[0] );
				$i       += strlen( $m[0] );
				continue;
			}
			if ( ctype_alpha( $c ) || '_' === $c ) {
				preg_match( '/\G[A-Za-z_][A-Za-z0-9_]*/', $s, $m, 0, $i );
				$tokens[] = array( 'id', strtolower( $m[0] ) );
				$i       += strlen( $m[0] );
				continue;
			}
			$two = substr( $s, $i, 2 );
			if ( in_array( $two, $ops2, true ) ) {
				$tokens[] = array( 'op', $two );
				$i       += 2;
				continue;
			}
			if ( in_array( $c, $ops1, true ) ) {
				$tokens[] = array( 'op', $c );
				$i++;
				continue;
			}
			/* translators: %s: character */
			throw new CCR_Expression_Exception( sprintf( __( 'Carácter no permitido "%s".', 'calculadora-cielorraso-pvc' ), $c ) );
		}
		return $tokens;
	}

	private function peek() {
		return isset( $this->tokens[ $this->pos ] ) ? $this->tokens[ $this->pos ] : null;
	}

	private function accept_op( $op ) {
		$t = $this->peek();
		if ( $t && 'op' === $t[0] && $t[1] === $op ) {
			$this->pos++;
			return true;
		}
		return false;
	}

	private function expect_op( $op ) {
		if ( ! $this->accept_op( $op ) ) {
			/* translators: %s: expected symbol */
			throw new CCR_Expression_Exception( sprintf( __( 'Se esperaba "%s".', 'calculadora-cielorraso-pvc' ), $op ) );
		}
	}

	private function enter() {
		if ( ++$this->depth > self::MAX_DEPTH ) {
			throw new CCR_Expression_Exception( __( 'La fórmula está demasiado anidada.', 'calculadora-cielorraso-pvc' ) );
		}
	}

	private function parse_expression() {
		$this->enter();
		$cond = $this->parse_binary( 0 );
		if ( $this->accept_op( '?' ) ) {
			$a = $this->parse_expression();
			$this->expect_op( ':' );
			$b    = $this->parse_expression();
			$cond = array( 't', $cond, $a, $b );
		}
		$this->depth--;
		return $cond;
	}

	/**
	 * Niveles binarios por precedencia.
	 */
	private static $levels = array(
		array( '||' ),
		array( '&&' ),
		array( '==', '!=', '<', '<=', '>', '>=' ),
		array( '+', '-' ),
		array( '*', '/', '%' ),
	);

	private function parse_binary( $level ) {
		if ( $level >= count( self::$levels ) ) {
			return $this->parse_unary();
		}
		$left = $this->parse_binary( $level + 1 );
		while ( true ) {
			$t = $this->peek();
			if ( $t && 'op' === $t[0] && in_array( $t[1], self::$levels[ $level ], true ) ) {
				$this->pos++;
				$right = $this->parse_binary( $level + 1 );
				$left  = array( 'b', $t[1], $left, $right );
			} else {
				return $left;
			}
		}
	}

	private function parse_unary() {
		$this->enter();
		foreach ( array( '-', '+', '!' ) as $op ) {
			if ( $this->accept_op( $op ) ) {
				$node = array( 'u', $op, $this->parse_unary() );
				$this->depth--;
				return $node;
			}
		}
		$node = $this->parse_power();
		$this->depth--;
		return $node;
	}

	private function parse_power() {
		$base = $this->parse_primary();
		if ( $this->accept_op( '^' ) ) {
			return array( 'b', '^', $base, $this->parse_unary() );
		}
		return $base;
	}

	private function parse_primary() {
		$t = $this->peek();
		if ( ! $t ) {
			throw new CCR_Expression_Exception( __( 'La fórmula termina de forma inesperada.', 'calculadora-cielorraso-pvc' ) );
		}
		if ( 'num' === $t[0] ) {
			$this->pos++;
			return array( 'n', (float) $t[1] );
		}
		if ( 'id' === $t[0] ) {
			$this->pos++;
			if ( $this->accept_op( '(' ) ) {
				$name  = $t[1];
				$funcs = self::functions();
				if ( ! isset( $funcs[ $name ] ) ) {
					/* translators: %s: function name */
					throw new CCR_Expression_Exception( sprintf( __( 'Función desconocida "%s".', 'calculadora-cielorraso-pvc' ), $name ) );
				}
				$args = array();
				if ( ! $this->accept_op( ')' ) ) {
					do {
						$args[] = $this->parse_expression();
					} while ( $this->accept_op( ',' ) );
					$this->expect_op( ')' );
				}
				list( $min, $max ) = $funcs[ $name ];
				if ( count( $args ) < $min || ( -1 !== $max && count( $args ) > $max ) ) {
					/* translators: %s: function name */
					throw new CCR_Expression_Exception( sprintf( __( 'Cantidad de argumentos incorrecta en "%s()".', 'calculadora-cielorraso-pvc' ), $name ) );
				}
				return array( 'f', $name, $args );
			}
			return array( 'v', $t[1] );
		}
		if ( $this->accept_op( '(' ) ) {
			$node = $this->parse_expression();
			$this->expect_op( ')' );
			return $node;
		}
		/* translators: %s: token */
		throw new CCR_Expression_Exception( sprintf( __( 'Símbolo inesperado "%s".', 'calculadora-cielorraso-pvc' ), $t[1] ) );
	}

	private static function collect_vars( $node, array &$out ) {
		switch ( $node[0] ) {
			case 'v':
				$out[] = $node[1];
				break;
			case 'u':
				self::collect_vars( $node[2], $out );
				break;
			case 'b':
				self::collect_vars( $node[2], $out );
				self::collect_vars( $node[3], $out );
				break;
			case 't':
				self::collect_vars( $node[1], $out );
				self::collect_vars( $node[2], $out );
				self::collect_vars( $node[3], $out );
				break;
			case 'f':
				foreach ( $node[2] as $arg ) {
					self::collect_vars( $arg, $out );
				}
				break;
		}
	}

	/* --------------------------------------------------------------------
	 * Evaluación
	 * ------------------------------------------------------------------ */

	private static function eval_node( $node, array $vars ) {
		switch ( $node[0] ) {
			case 'n':
				return $node[1];

			case 'v':
				if ( ! array_key_exists( $node[1], $vars ) ) {
					/* translators: %s: variable name */
					throw new CCR_Expression_Exception( sprintf( __( 'Variable desconocida "%s".', 'calculadora-cielorraso-pvc' ), $node[1] ) );
				}
				return (float) $vars[ $node[1] ];

			case 'u':
				$v = self::eval_node( $node[2], $vars );
				if ( '-' === $node[1] ) {
					return -$v;
				}
				if ( '!' === $node[1] ) {
					return self::truthy( $v ) ? 0.0 : 1.0;
				}
				return $v;

			case 't':
				return self::truthy( self::eval_node( $node[1], $vars ) )
					? self::eval_node( $node[2], $vars )
					: self::eval_node( $node[3], $vars );

			case 'b':
				$op = $node[1];
				// Cortocircuito lógico.
				if ( '&&' === $op ) {
					return ( self::truthy( self::eval_node( $node[2], $vars ) ) && self::truthy( self::eval_node( $node[3], $vars ) ) ) ? 1.0 : 0.0;
				}
				if ( '||' === $op ) {
					return ( self::truthy( self::eval_node( $node[2], $vars ) ) || self::truthy( self::eval_node( $node[3], $vars ) ) ) ? 1.0 : 0.0;
				}
				$a = self::eval_node( $node[2], $vars );
				$b = self::eval_node( $node[3], $vars );
				return self::binary( $op, $a, $b );

			case 'f':
				return self::call( $node[1], $node[2], $vars );
		}
		throw new CCR_Expression_Exception( 'Nodo inválido.' );
	}

	private static function binary( $op, $a, $b ) {
		$eps = CCR_Math::EPS;
		switch ( $op ) {
			case '+':
				return $a + $b;
			case '-':
				return $a - $b;
			case '*':
				return $a * $b;
			case '/':
				if ( 0.0 === (float) $b ) {
					throw new CCR_Expression_Exception( __( 'División por cero.', 'calculadora-cielorraso-pvc' ) );
				}
				return $a / $b;
			case '%':
				if ( 0.0 === (float) $b ) {
					throw new CCR_Expression_Exception( __( 'División por cero.', 'calculadora-cielorraso-pvc' ) );
				}
				return fmod( $a, $b );
			case '^':
				return pow( $a, $b );
			case '==':
				return abs( $a - $b ) < $eps ? 1.0 : 0.0;
			case '!=':
				return abs( $a - $b ) >= $eps ? 1.0 : 0.0;
			case '<':
				return $a < $b - $eps ? 1.0 : 0.0;
			case '<=':
				return $a <= $b + $eps ? 1.0 : 0.0;
			case '>':
				return $a > $b + $eps ? 1.0 : 0.0;
			case '>=':
				return $a >= $b - $eps ? 1.0 : 0.0;
		}
		throw new CCR_Expression_Exception( 'Operador inválido.' );
	}

	private static function call( $name, array $arg_nodes, array $vars ) {
		// si()/if() se evalúan de forma perezosa: solo la rama elegida.
		if ( 'si' === $name || 'if' === $name ) {
			return self::truthy( self::eval_node( $arg_nodes[0], $vars ) )
				? self::eval_node( $arg_nodes[1], $vars )
				: self::eval_node( $arg_nodes[2], $vars );
		}

		$args = array();
		foreach ( $arg_nodes as $n ) {
			$args[] = self::eval_node( $n, $vars );
		}

		switch ( $name ) {
			case 'min':
				return (float) min( $args );
			case 'max':
				return (float) max( $args );
			case 'abs':
				return abs( $args[0] );
			case 'ceil':
				return CCR_Math::ceil( $args[0] );
			case 'floor':
				return CCR_Math::floor( $args[0] );
			case 'round':
				return round( $args[0], isset( $args[1] ) ? (int) $args[1] : 0 );
			case 'sqrt':
				if ( $args[0] < 0 ) {
					throw new CCR_Expression_Exception( __( 'Raíz de un número negativo.', 'calculadora-cielorraso-pvc' ) );
				}
				return sqrt( $args[0] );
			case 'pow':
				return pow( $args[0], $args[1] );
			case 'multiplo_superior':
				return CCR_Math::to_multiple( $args[0], $args[1], 'ceil' );
			case 'multiplo_inferior':
				return CCR_Math::to_multiple( $args[0], $args[1], 'floor' );
			case 'lineas':
				return CCR_Math::lines( $args[0], $args[1], isset( $args[2] ) ? $args[2] : 0 );
			case 'piezas_lineales':
				return CCR_Math::linear_pieces( $args[0], $args[1], $args[2], isset( $args[3] ) ? $args[3] : 0 );
			case 'piezas_perimetro':
				return CCR_Math::perimeter_pieces( $args[0], $args[1], $args[2] );
			case 'piezas_superficie':
				return CCR_Math::surface_pieces( $args[0], $args[1], $args[2], $args[3] );
		}
		/* translators: %s: function name */
		throw new CCR_Expression_Exception( sprintf( __( 'Función desconocida "%s".', 'calculadora-cielorraso-pvc' ), $name ) );
	}

	private static function truthy( $v ) {
		return abs( (float) $v ) >= CCR_Math::EPS;
	}
}
