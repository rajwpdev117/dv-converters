<?php
/**
 * Exact conversion math for DimensionVista converters.
 *
 * Every value is held as an exact rational [numerator, denominator] built from
 * integers, so "=" is only ever shown when a result is truly exact, and "≈"
 * everywhere else. Floats are used only for drawing positions in the visual.
 *
 * @package DV_Converters
 */

defined( 'ABSPATH' ) || exit;

class DV_Math {

	/** Largest value accepted from an input (keeps integer math inside 64 bits). */
	const MAX_VALUE = 100000;

	/** Most decimal places accepted from an input. */
	const MAX_DP = 6;

	/**
	 * Units, each defined by its exact length in millimetres as a rational.
	 * The inch is exactly 25.4 mm = 127/5 (NIST SP 811, Appendix B.9).
	 */
	public static function units() {
		$units = array(
			'mm' => array( 'mm' => array( 1, 1 ),     'short' => 'mm', 'name' => 'millimeters', 'one' => 'millimeter' ),
			'cm' => array( 'mm' => array( 10, 1 ),    'short' => 'cm', 'name' => 'centimeters', 'one' => 'centimeter' ),
			'm'  => array( 'mm' => array( 1000, 1 ),  'short' => 'm',  'name' => 'meters',      'one' => 'meter' ),
			'in' => array( 'mm' => array( 127, 5 ),   'short' => 'in', 'name' => 'inches',      'one' => 'inch' ),
			'ft' => array( 'mm' => array( 1524, 5 ),  'short' => 'ft', 'name' => 'feet',        'one' => 'foot' ),
		);
		return apply_filters( 'dv_converters_units', $units );
	}

	public static function unit( $key ) {
		$u = self::units();
		return isset( $u[ $key ] ) ? $u[ $key ] : null;
	}

	/* ------------------------------------------------------------------ */
	/* Rational helpers                                                    */
	/* ------------------------------------------------------------------ */

	public static function gcd( $a, $b ) {
		$a = abs( $a );
		$b = abs( $b );
		while ( 0 !== $b ) {
			$t = $a % $b;
			$a = $b;
			$b = $t;
		}
		return 0 === $a ? 1 : $a;
	}

	public static function reduce( $n, $d ) {
		if ( $d < 0 ) {
			$n = -$n;
			$d = -$d;
		}
		$g = self::gcd( $n, $d );
		return array( intdiv( $n, $g ), intdiv( $d, $g ) );
	}

	public static function mul( $a, $b ) {
		// Cross-reduce first to keep numbers small.
		$g1 = self::gcd( $a[0], $b[1] );
		$g2 = self::gcd( $b[0], $a[1] );
		return self::reduce(
			intdiv( $a[0], $g1 ) * intdiv( $b[0], $g2 ),
			intdiv( $a[1], $g2 ) * intdiv( $b[1], $g1 )
		);
	}

	public static function div( $a, $b ) {
		return self::mul( $a, array( $b[1], $b[0] ) );
	}

	public static function to_float( $r ) {
		return $r[0] / $r[1];
	}

	/**
	 * Parse user input into a rational. Accepts 8, 8.5, 8,5, 5/16, 1 1/4, 1-1/4,
	 * with an optional trailing unit or inch mark. Returns null when invalid.
	 */
	public static function parse( $raw ) {
		$s = strtolower( trim( (string) $raw ) );
		$s = str_replace( array( '⁄', '″', '"', '”', 'inches', 'inch', 'in', 'mm', 'cm' ), array( '/', '', '', '', '', '', '', '', '' ), $s );
		$s = trim( $s );
		if ( '' === $s ) {
			return null;
		}
		if ( preg_match( '/^(\d{1,6})[\s-]+(\d{1,4})\/(\d{1,4})$/', $s, $m ) ) {
			if ( 0 === (int) $m[3] ) {
				return null;
			}
			$r = self::reduce( (int) $m[1] * (int) $m[3] + (int) $m[2], (int) $m[3] );
		} elseif ( preg_match( '/^(\d{1,6})\/(\d{1,4})$/', $s, $m ) ) {
			if ( 0 === (int) $m[2] ) {
				return null;
			}
			$r = self::reduce( (int) $m[1], (int) $m[2] );
		} elseif ( preg_match( '/^(\d{0,6})(?:[.,](\d{1,' . self::MAX_DP . '}))?$/', $s, $m ) && ( '' !== $m[1] || isset( $m[2] ) ) ) {
			$frac = isset( $m[2] ) ? $m[2] : '';
			$den  = (int) ( '1' . str_repeat( '0', strlen( $frac ) ) );
			$r    = self::reduce( (int) ( ( '' === $m[1] ? '0' : $m[1] ) . $frac ), $den );
		} else {
			return null;
		}
		if ( $r[0] > self::MAX_VALUE * $r[1] ) {
			return null;
		}
		return $r;
	}

	/** Convert a rational value between two unit keys. */
	public static function convert( $r, $from, $to ) {
		$f = self::unit( $from );
		$t = self::unit( $to );
		if ( ! $f || ! $t ) {
			return null;
		}
		return self::div( self::mul( $r, $f['mm'] ), $t['mm'] );
	}

	/* ------------------------------------------------------------------ */
	/* Decimal output                                                      */
	/* ------------------------------------------------------------------ */

	/**
	 * Round to $dp places (half up) and report whether that is exact.
	 *
	 * @return array{text:string,exact:bool}
	 */
	public static function decimal( $r, $dp ) {
		$pow = (int) ( '1' . str_repeat( '0', $dp ) );
		while ( $dp > 0 && abs( $r[0] ) > intdiv( PHP_INT_MAX, 4 * $pow ) ) {
			// Guard against 64-bit overflow on very large inputs: drop precision.
			$dp--;
			$pow = intdiv( $pow, 10 );
		}
		$q     = $r[0] * $pow;
		$exact = 0 === $q % $r[1];
		$int   = intdiv( 2 * $q + $r[1], 2 * $r[1] );
		$whole = intdiv( $int, $pow );
		$rest  = $int % $pow;
		$text  = number_format( $whole, 0, '.', ',' );
		if ( $dp > 0 && $rest > 0 ) {
			$text .= '.' . rtrim( str_pad( (string) $rest, $dp, '0', STR_PAD_LEFT ), '0' );
		}
		return array( 'text' => $text, 'exact' => $exact );
	}

	/** Shortest exact decimal if one exists within MAX_DP places, else null. */
	public static function exact_decimal( $r ) {
		for ( $dp = 0; $dp <= self::MAX_DP; $dp++ ) {
			$d = self::decimal( $r, $dp );
			if ( $d['exact'] ) {
				return $d['text'];
			}
		}
		return null;
	}

	/* ------------------------------------------------------------------ */
	/* Fractions of an inch                                                */
	/* ------------------------------------------------------------------ */

	/**
	 * Nearest fraction with denominator $den (half rounds up).
	 *
	 * @return array{k:int,den:int,cmp:int,text:string,value:array}
	 *         cmp: 1 = the value is more than the fraction, -1 = less, 0 = exact.
	 */
	public static function nearest( $r, $den ) {
		$k    = intdiv( 2 * $r[0] * $den + $r[1], 2 * $r[1] );
		$diff = $r[0] * $den - $k * $r[1];
		return array(
			'k'     => $k,
			'den'   => $den,
			'cmp'   => $diff <=> 0,
			'text'  => self::fraction_text( $k, $den ),
			'value' => self::reduce( $k, $den ),
		);
	}

	/**
	 * Coarsest fraction from $dens that is within 1/128 in of the value, which is
	 * what someone reading a tape or ruler would use. Falls back to the finest.
	 */
	public static function best( $r, $dens = array( 16, 32, 64 ) ) {
		$pick = null;
		foreach ( $dens as $den ) {
			$f = self::nearest( $r, $den );
			// |r - k/den| <= 1/128  <=>  128 * |n*den - k*d| <= den * d
			if ( 128 * abs( $r[0] * $den - $f['k'] * $r[1] ) <= $den * $r[1] ) {
				return $f;
			}
			$pick = $f;
		}
		return $pick;
	}

	/** "1 3/16″", "5/16″", "2″" from k/den. */
	public static function fraction_text( $k, $den, $mark = '″' ) {
		list( $k, $den ) = self::reduce( $k, $den );
		$whole = intdiv( $k, $den );
		$rest  = $k % $den;
		if ( 0 === $rest ) {
			return $whole . $mark;
		}
		return ( $whole ? $whole . ' ' : '' ) . $rest . '/' . $den . $mark;
	}

	/** Difference between a value (inches) and a fraction, in mm, as text. */
	public static function diff_mm( $r_in, $frac_value ) {
		$d = array( $r_in[0] * $frac_value[1] - $frac_value[0] * $r_in[1], $r_in[1] * $frac_value[1] );
		$d = self::reduce( abs( $d[0] ), $d[1] );
		$d = self::mul( $d, array( 127, 5 ) );
		$t = self::decimal( $d, 2 );
		return ( '0' === $t['text'] ? '<0.01' : $t['text'] ) . ' mm';
	}

	/** Plain words for a comparison result. */
	public static function cmp_words( $cmp ) {
		if ( 0 === $cmp ) {
			return __( 'exactly', 'dv-converters' );
		}
		return $cmp > 0 ? __( 'slightly more than', 'dv-converters' ) : __( 'slightly less than', 'dv-converters' );
	}
}
