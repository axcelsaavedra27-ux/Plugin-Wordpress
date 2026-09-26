<?php
/**
 * Generador mínimo de archivos .xlsx sin dependencias (no requiere ZipArchive).
 * Empaqueta SpreadsheetML en un ZIP sin compresión.
 *
 * @package CalculadoraCielorrasoPVC
 */

defined( 'ABSPATH' ) || exit;

class CCR_Xlsx {

	/**
	 * Genera el binario .xlsx.
	 *
	 * @param array  $rows   Filas; cada celda es string|int|float. La primera fila se muestra en negrita.
	 * @param string $sheet  Nombre de la hoja.
	 * @param array  $widths Anchos de columna opcionales.
	 * @return string
	 */
	public static function build( array $rows, $sheet = 'Hoja1', array $widths = array() ) {
		$xml_rows = '';
		foreach ( array_values( $rows ) as $ri => $row ) {
			$cells = '';
			foreach ( array_values( (array) $row ) as $ci => $value ) {
				if ( null === $value || '' === $value ) {
					continue;
				}
				$ref   = self::col( $ci ) . ( $ri + 1 );
				$style = 0 === $ri ? ' s="1"' : '';
				if ( ( is_int( $value ) || is_float( $value ) ) && is_finite( (float) $value ) ) {
					$cells .= '<c r="' . $ref . '"' . $style . '><v>' . $value . '</v></c>';
				} else {
					$cells .= '<c r="' . $ref . '" t="inlineStr"' . $style . '><is><t xml:space="preserve">' . self::esc( $value ) . '</t></is></c>';
				}
			}
			$xml_rows .= '<row r="' . ( $ri + 1 ) . '">' . $cells . '</row>';
		}

		$cols = '';
		foreach ( $widths as $i => $w ) {
			$cols .= '<col min="' . ( $i + 1 ) . '" max="' . ( $i + 1 ) . '" width="' . (float) $w . '" customWidth="1"/>';
		}

		$head  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
		$files = array(
			'[Content_Types].xml'        => $head . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>',
			'_rels/.rels'                => $head . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
			'xl/workbook.xml'            => $head . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="' . self::esc( substr( $sheet, 0, 31 ) ) . '" sheetId="1" r:id="rId1"/></sheets></workbook>',
			'xl/_rels/workbook.xml.rels' => $head . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
			'xl/styles.xml'              => $head . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFEEF1F5"/></patternFill></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/></cellXfs></styleSheet>',
			'xl/worksheets/sheet1.xml'   => $head . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' . ( $cols ? '<cols>' . $cols . '</cols>' : '' ) . '<sheetData>' . $xml_rows . '</sheetData></worksheet>',
		);

		return self::zip( $files );
	}

	private static function col( $i ) {
		$s = '';
		$i++;
		while ( $i > 0 ) {
			$m = ( $i - 1 ) % 26;
			$s = chr( 65 + $m ) . $s;
			$i = (int) floor( ( $i - 1 ) / 26 );
		}
		return $s;
	}

	private static function esc( $v ) {
		$v = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', (string) $v );
		return htmlspecialchars( $v, ENT_QUOTES | ENT_XML1, 'UTF-8' );
	}

	/**
	 * ZIP "store" (sin compresión).
	 *
	 * @param array $files nombre => contenido.
	 * @return string
	 */
	private static function zip( array $files ) {
		$data    = '';
		$central = '';
		$offset  = 0;
		foreach ( $files as $name => $content ) {
			$crc  = crc32( $content );
			$size = strlen( $content );
			$len  = strlen( $name );

			$local = pack( 'VvvvvvVVVvv', 0x04034b50, 20, 0x0800, 0, 0, 0x21, $crc, $size, $size, $len, 0 );
			$data .= $local . $name . $content;

			$central .= pack( 'VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0x0800, 0, 0, 0x21, $crc, $size, $size, $len, 0, 0, 0, 0, 0, $offset ) . $name;
			$offset  += strlen( $local ) + $len + $size;
		}
		$end = pack( 'VvvvvVVv', 0x06054b50, 0, 0, count( $files ), count( $files ), strlen( $central ), $offset, 0 );
		return $data . $central . $end;
	}
}
