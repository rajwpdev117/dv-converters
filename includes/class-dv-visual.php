<?php
/**
 * Scaled ruler visual (SVG). The same layout rules are mirrored in
 * assets/dv-converters.js so the picture redraws while the visitor types.
 *
 * @package DV_Converters
 */

defined( 'ABSPATH' ) || exit;

class DV_Visual {

	const W    = 640;
	const PAD  = 24;
	const BODY_Y = 46;
	const BODY_H = 100;

	/** Smallest "nice" range (1, 1.5, 2, 2.5, 3, 4, 5, 6, 8 × 10^k) that leaves headroom. */
	public static function range_mm( $mm ) {
		$target = max( 5, $mm / 0.75 );
		$steps  = array( 1, 1.5, 2, 2.5, 3, 4, 5, 6, 8 );
		for ( $p = 0; $p < 9; $p++ ) {
			foreach ( $steps as $s ) {
				$v = $s * pow( 10, $p );
				if ( $v >= $target ) {
					return $v;
				}
			}
		}
		return $target;
	}

	private static function first_fit( $list, $scale, $min_px ) {
		foreach ( $list as $v ) {
			if ( $v * $scale >= $min_px ) {
				return $v;
			}
		}
		return end( $list );
	}

	private static function n( $v ) {
		return rtrim( rtrim( number_format( $v, 2, '.', '' ), '0' ), '.' );
	}

	/**
	 * @param float  $mm      Value to show, in millimetres.
	 * @param string $top     Label above the dimension line (e.g. "8 mm").
	 * @param string $bottom  Label under the ruler (e.g. "≈ 5/16″").
	 * @return string SVG markup.
	 */
	public static function svg( $mm, $top, $bottom, $top_unit = 'mm' ) {
		$div    = 'cm' === $top_unit ? 10 : 1;
		$mm     = max( 0, (float) $mm );
		$range  = self::range_mm( $mm );
		$usable = self::W - 2 * self::PAD;
		$s      = $usable / $range;             // viewBox px per mm.
		$x0     = self::PAD;
		$y_top  = self::BODY_Y;
		$y_bot  = self::BODY_Y + self::BODY_H;
		$o      = array();

		// Ruler body + measured band.
		$xv  = $x0 + min( $mm, $range ) * $s;
		$o[] = '<rect class="dv-v-body" x="' . self::n( $x0 - 8 ) . '" y="' . $y_top . '" width="' . self::n( $usable + 16 ) . '" height="' . self::BODY_H . '" rx="6"/>';
		if ( $mm > 0 ) {
			$o[] = '<rect class="dv-v-band" x="' . $x0 . '" y="' . $y_top . '" width="' . self::n( $xv - $x0 ) . '" height="' . self::BODY_H . '"/>';
		}

		// Millimetre scale (top edge, ticks hang down).
		$mm_minor = self::first_fit( array( 0.1, 0.2, 0.5, 1, 2, 5, 10, 20, 50, 100, 200, 500, 1000, 2000, 5000, 10000 ), $s, 5 );
		$mm_label = self::first_fit( array( 1, 2, 5, 10, 20, 50, 100, 200, 500, 1000, 2000, 5000, 10000, 20000, 50000 ), $s, 46 );
		$count    = (int) round( $range / $mm_minor );
		$ticks    = '';
		$labels   = '';
		for ( $i = 0; $i <= $count; $i++ ) {
			$v   = $i * $mm_minor;
			$x   = self::n( $x0 + $v * $s );
			$is_label = abs( fmod( $v + 1e-9, $mm_label ) ) < 1e-6;
			$is_half  = abs( fmod( $v + 1e-9, $mm_label / 2 ) ) < 1e-6;
			$h   = $is_label ? 18 : ( $is_half ? 12 : 7 );
			$ticks .= 'M' . $x . ' ' . $y_top . 'v' . $h;
			if ( $is_label && $v * $s < $usable - 40 ) { // Keep clear of the unit label.
				$labels .= '<text x="' . $x . '" y="' . ( $y_top + 32 ) . '">' . self::n( $v / $div ) . '</text>';
			}
		}
		$o[] = '<path class="dv-v-tick" d="' . $ticks . '"/>';
		$o[] = '<g class="dv-v-lab">' . $labels . '</g>';
		$o[] = '<text class="dv-v-unit" x="' . self::n( $x0 + $usable + 2 ) . '" y="' . ( $y_top + 32 ) . '" text-anchor="end">' . ( 'cm' === $top_unit ? 'cm' : 'mm' ) . '</text>';

		// Inch scale (bottom edge, ticks rise up).
		$in_s     = 25.4 * $s;                 // viewBox px per inch.
		$in_range = $range / 25.4;
		$in_minor = self::first_fit( array( 1 / 64, 1 / 32, 1 / 16, 1 / 8, 1 / 4, 1 / 2, 1, 2, 3, 6, 12, 24, 60, 120, 360 ), $in_s, 5 );
		$in_label = self::first_fit( array( 1 / 32, 1 / 16, 1 / 8, 1 / 4, 1 / 2, 1, 2, 3, 6, 12, 24, 60, 120, 360, 1200 ), $in_s, 46 );
		$count    = (int) floor( $in_range / $in_minor + 1e-9 );
		$ticks    = '';
		$labels   = '';
		for ( $i = 0; $i <= $count; $i++ ) {
			$v  = $i * $in_minor;
			$x  = self::n( $x0 + $v * $in_s );
			$h  = 6;
			foreach ( array( array( 1, 18 ), array( 0.5, 14 ), array( 0.25, 11 ), array( 0.125, 8 ) ) as $lv ) {
				if ( abs( $v / $lv[0] - round( $v / $lv[0] ) ) < 1e-6 ) {
					$h = $lv[1];
					break;
				}
			}
			$ticks .= 'M' . $x . ' ' . $y_bot . 'v-' . $h;
			if ( abs( $v / $in_label - round( $v / $in_label ) ) < 1e-6 && $v * $in_s < $usable - 40 ) {
				$k       = (int) round( $v * 64 );
				$labels .= '<text x="' . $x . '" y="' . ( $y_bot - 24 ) . '">' . esc_html( DV_Math::fraction_text( $k, 64, '' ) ) . '</text>';
			}
		}
		$o[] = '<path class="dv-v-tick" d="' . $ticks . '"/>';
		$o[] = '<g class="dv-v-lab">' . $labels . '</g>';
		$o[] = '<text class="dv-v-unit" x="' . self::n( $x0 + $usable + 2 ) . '" y="' . ( $y_bot - 24 ) . '" text-anchor="end">in</text>';

		// Measured edge + dimension line above the ruler.
		if ( $mm > 0 ) {
			$o[]  = '<path class="dv-v-edge" d="M' . self::n( $xv ) . ' ' . ( $y_top - 2 ) . 'V' . ( $y_bot + 2 ) . '"/>';
			$dy   = 24;
			$o[]  = '<path class="dv-v-dim" d="M' . $x0 . ' ' . ( $dy - 6 ) . 'v12M' . $x0 . ' ' . $dy . 'H' . self::n( $xv ) . 'M' . self::n( $xv ) . ' ' . ( $dy - 6 ) . 'v12"/>';
			$wide = ( $xv - $x0 ) > 90;
			$tx   = $wide ? self::n( ( $x0 + $xv ) / 2 ) : self::n( $xv + 8 );
			$o[]  = '<text class="dv-v-top" x="' . $tx . '" y="' . ( $dy - 10 ) . '"' . ( $wide ? '' : ' text-anchor="start"' ) . '>' . esc_html( $top ) . '</text>';
			$o[]  = '<text class="dv-v-bottom" x="' . ( $wide ? $tx : self::n( $xv + 8 ) ) . '" y="' . ( $y_bot + 22 ) . '"' . ( $wide ? '' : ' text-anchor="start"' ) . '>' . esc_html( $bottom ) . '</text>';
		}

		$label = trim( $top . ' ' . $bottom );
		return '<svg class="dv-v" viewBox="0 0 ' . self::W . ' 176" role="img" aria-label="' . esc_attr( sprintf( /* translators: %s: measurement */ __( 'Ruler showing %s', 'dv-converters' ), $label ) ) . '">' . implode( '', $o ) . '</svg>';
	}

	/**
	 * Honest caption: the picture is never claimed as actual size, because
	 * screen pixel density varies. 3.78 CSS px ≈ 1 mm at the CSS reference.
	 */
	public static function caption( $mm, $top_unit = 'mm' ) {
		$s = ( self::W - 2 * self::PAD ) / self::range_mm( max( 0, (float) $mm ) );
		$top = 'cm' === $top_unit ? __( 'centimeters', 'dv-converters' ) : __( 'millimeters', 'dv-converters' );
		return $s > 3.78
			/* translators: %s: unit name of the top scale */
			? sprintf( __( 'Enlarged view, not actual size. Top scale: %s. Bottom scale: inches.', 'dv-converters' ), $top )
			/* translators: %s: unit name of the top scale */
			: sprintf( __( 'Scaled-down view, not actual size. Top scale: %s. Bottom scale: inches.', 'dv-converters' ), $top );
	}
}
