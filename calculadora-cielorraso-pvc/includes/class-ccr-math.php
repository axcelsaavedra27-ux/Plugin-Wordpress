<?php
/**
 * Funciones de cálculo de obra disponibles dentro de las fórmulas.
 *
 * Los algoritmos de piezas_* reproducen la lógica de optimización de recortes usada por
 * la calculadora de referencia (CPR): primero se cuentan piezas enteras y luego cuántos
 * faltantes se pueden obtener de cada pieza adicional.
 *
 * @package CalculadoraCielorrasoPVC
 */

defined( 'ABSPATH' ) || exit;

class CCR_Math {

	/** Tolerancia para errores de coma flotante (p. ej. 3.6 / 0.2 = 17.999999...). */
	const EPS = 1e-9;

	/** Tolerancia para considerar que un sobrante de longitud es cero (1 micrón). */
	const ZERO_LEN = 1e-6;

	public static function floor( $x ) {
		return floor( $x + self::EPS );
	}

	public static function ceil( $x ) {
		return ceil( $x - self::EPS );
	}

	/**
	 * Redondea al múltiplo indicado.
	 *
	 * @param float  $x        Valor.
	 * @param float  $multiple Múltiplo (<= 0 equivale a 1).
	 * @param string $mode     ceil|floor|round.
	 * @return float
	 */
	public static function to_multiple( $x, $multiple, $mode = 'ceil' ) {
		$multiple = $multiple > 0 ? $multiple : 1;
		$q        = $x / $multiple;
		switch ( $mode ) {
			case 'floor':
				$q = self::floor( $q );
				break;
			case 'round':
				$q = round( $q );
				break;
			default:
				$q = self::ceil( $q );
		}
		return $q * $multiple;
	}

	/**
	 * Cantidad de líneas de estructura en una longitud: floor((longitud - tolerancia) / separación).
	 */
	public static function lines( $length, $spacing, $tolerance = 0 ) {
		if ( $spacing <= 0 ) {
			throw new CCR_Expression_Exception( __( 'lineas(): la separación debe ser mayor que cero.', 'calculadora-cielorraso-pvc' ) );
		}
		return max( 0, self::floor( ( $length - $tolerance ) / $spacing ) );
	}

	/**
	 * Piezas de largo $piece necesarias para cubrir $count líneas de longitud $length,
	 * con empalmes solapados $overlap y reaprovechando los recortes de la última pieza.
	 */
	public static function linear_pieces( $length, $piece, $count, $overlap = 0 ) {
		$count = self::floor( $count );
		if ( $count <= 0 || $length <= self::ZERO_LEN ) {
			return 0.0;
		}
		if ( $piece <= 0 ) {
			throw new CCR_Expression_Exception( __( 'piezas_lineales(): el largo de pieza debe ser mayor que cero.', 'calculadora-cielorraso-pvc' ) );
		}

		if ( $piece > $length + self::ZERO_LEN ) {
			$per_line  = 0;
			$remainder = $length;
		} else {
			$step = $piece - $overlap;
			if ( $step <= 0 ) {
				throw new CCR_Expression_Exception( __( 'piezas_lineales(): el solape debe ser menor que el largo de la pieza.', 'calculadora-cielorraso-pvc' ) );
			}
			$per_line  = self::floor( ( $length - $overlap ) / $step );
			$remainder = $length - $per_line * $step;
			if ( $remainder <= $overlap + self::ZERO_LEN ) {
				$remainder = 0;
			}
		}

		if ( $remainder > 0 ) {
			$from_one_piece = max( 1, self::floor( $piece / $remainder ) );
			return $per_line * $count + self::ceil( $count / $from_one_piece );
		}
		return (float) ( $per_line * $count );
	}

	/**
	 * Piezas de largo $piece para recorrer el perímetro de un rectángulo $a x $b.
	 */
	public static function perimeter_pieces( $a, $b, $piece ) {
		if ( $piece <= 0 ) {
			throw new CCR_Expression_Exception( __( 'piezas_perimetro(): el largo de pieza debe ser mayor que cero.', 'calculadora-cielorraso-pvc' ) );
		}
		if ( $a <= 0 || $b <= 0 ) {
			return 0.0;
		}
		$ea    = self::floor( $a / $piece );
		$eb    = self::floor( $b / $piece );
		$whole = 2 * $ea + 2 * $eb;
		$fa    = $a - $ea * $piece;
		$fb    = $b - $eb * $piece;
		$fa    = $fa < self::ZERO_LEN ? 0 : $fa;
		$fb    = $fb < self::ZERO_LEN ? 0 : $fb;
		$tol   = self::ZERO_LEN;

		if ( 0 == $fa && 0 == $fb ) { // phpcs:ignore Universal.Operators.StrictComparisons
			$extra = 0;
		} elseif ( ( $fa + $fb ) * 2 <= $piece + $tol ) {
			$extra = 1;
		} elseif ( ( $fa * 2 <= $piece + $tol && $fb * 2 <= $piece + $tol ) || ( $fa + $fb <= $piece + $tol ) ) {
			$extra = 2;
		} elseif ( $fa * 2 <= $piece + $tol || $fb * 2 <= $piece + $tol ) {
			$extra = 3;
		} else {
			$extra = 4;
		}
		return (float) ( $whole + $extra );
	}

	/**
	 * Láminas de $piece_len x $piece_w para cubrir una superficie, con las láminas
	 * orientadas a lo largo de $parallel y apiladas a lo largo de $perpendicular.
	 */
	public static function surface_pieces( $parallel, $perpendicular, $piece_len, $piece_w ) {
		if ( $piece_len <= 0 || $piece_w <= 0 ) {
			throw new CCR_Expression_Exception( __( 'piezas_superficie(): largo y ancho de pieza deben ser mayores que cero.', 'calculadora-cielorraso-pvc' ) );
		}
		if ( $parallel <= 0 || $perpendicular <= 0 ) {
			return 0.0;
		}

		$rows  = self::floor( $perpendicular / $piece_w );    // Filas de láminas enteras (a lo ancho de la lámina).
		$cols  = self::floor( $parallel / $piece_len );       // Láminas enteras por fila.
		$total = $rows * $cols;

		// Sobrante al final de cada fila: se obtiene de láminas adicionales cortadas.
		$end_remainder = $parallel - $cols * $piece_len;
		$leftover_left = false;
		if ( $end_remainder > self::ZERO_LEN ) {
			$per_piece     = max( 1, self::floor( $piece_len / $end_remainder ) );
			$extra         = self::ceil( $rows / $per_piece );
			$leftover_left = $extra > ( $rows / $per_piece ) + self::EPS;
			$total        += $extra;
		} else {
			$end_remainder = 0;
		}

		// Última fila cortada a lo largo (ancho parcial).
		$side_remainder = $perpendicular - $rows * $piece_w;
		if ( $side_remainder > self::ZERO_LEN ) {
			$total += $cols + ( ( $end_remainder > 0 && ! $leftover_left ) ? 1 : 0 );
		}

		return (float) $total;
	}
}
