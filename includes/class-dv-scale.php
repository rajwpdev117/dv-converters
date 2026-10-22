<?php
/**
 * [dv_scale]: a to-scale comparison of one length against familiar reference
 * lengths (SVG, server-rendered). Reference lengths are official or nominal
 * specifications, listed with their source, and are labelled as such.
 *
 * @package DV_Converters
 */

defined( 'ABSPATH' ) || exit;

class DV_Scale {

	const W     = 640;
	const ROW_H = 44;

	/**
	 * Reference lengths in exact millimetres as [numerator, denominator].
	 * 'kind' = "official" (a published rule or standard) or "nominal" (typical or rated size).
	 *
	 * @return array<string,array>
	 */
	public static function refs() {
		$refs = array(
			'dollar'      => array( 'label' => 'US dollar bill', 'mm' => array( 155956, 1000 ), 'kind' => 'official' ), // 6.14 in.
			'a4'          => array( 'label' => 'A4 sheet (long side)', 'mm' => array( 297, 1 ), 'kind' => 'official' ), // ISO 216.
			'door'        => array( 'label' => 'Standard interior door height', 'mm' => array( 2032, 1 ), 'kind' => 'nominal' ), // 80 in.
			'hoop'        => array( 'label' => 'Basketball rim height', 'mm' => array( 3048, 1 ), 'kind' => 'official' ), // 10 ft.
			'container20' => array( 'label' => '20 ft shipping container (outer length)', 'mm' => array( 6058, 1 ), 'kind' => 'official' ), // ISO 668.
			'container40' => array( 'label' => '40 ft shipping container', 'mm' => array( 12192, 1 ), 'kind' => 'official' ), // ISO 668.
			'trailer'     => array( 'label' => 'Semi-trailer, 53 ft', 'mm' => array( 161544, 10 ), 'kind' => 'nominal' ),
			'bowling'     => array( 'label' => 'Bowling lane, foul line to head pin', 'mm' => array( 18288, 1 ), 'kind' => 'official' ), // 60 ft.
			'pitch'       => array( 'label' => 'Baseball pitching distance', 'mm' => array( 184404, 10 ), 'kind' => 'official' ), // 60 ft 6 in.
			'cricket'     => array( 'label' => 'Cricket pitch', 'mm' => array( 201168, 10 ), 'kind' => 'official' ), // 22 yd.
			'tennis'      => array( 'label' => 'Tennis court length', 'mm' => array( 237744, 10 ), 'kind' => 'official' ), // ITF 78 ft.
			'nba'         => array( 'label' => 'NBA basketball court length', 'mm' => array( 286512, 10 ), 'kind' => 'official' ), // 94 ft.
			'pool'        => array( 'label' => 'Olympic swimming pool', 'mm' => array( 50000, 1 ), 'kind' => 'official' ),
			'football'    => array( 'label' => 'American football field (goal line to goal line)', 'mm' => array( 91440, 1 ), 'kind' => 'official' ), // 100 yd.
		);
		return apply_filters( 'dv_converters_scale_refs', $refs );
	}

	/** "18.29 m" or "= 18.288 m": exact when the decimal terminates within 3 places. */
	private static function fmt( $mm, $unit ) {
		$u = DV_Math::unit( $unit );
		$r = DV_Math::convert( $mm, 'mm', $unit );
		$d = DV_Math::decimal( $r, 2 );
		$x = DV_Math::exact_decimal( $r );
		if ( null !== $x ) {
			$pos = strpos( $x, '.' );
			if ( false === $pos || strlen( $x ) - $pos - 1 <= 3 ) {
				return $x . ' ' . $u['short'];
			}
		}
		return '≈ ' . $d['text'] . ' ' . $u['short'];
	}

	private static function n( $v ) {
		return rtrim( rtrim( number_format( $v, 2, '.', '' ), '0' ), '.' );
	}

	/**
	 * @param array  $mm     Target length in mm as a rational.
	 * @param string $label  Label for the target bar, e.g. "60 ft".
	 * @param array  $ids    Reference ids to show.
	 * @param array  $units  Two unit keys to print values in, e.g. array( 'ft', 'm' ).
	 * @return string Escaped SVG.
	 */
	public static function svg( $mm, $label, $ids, $units ) {
		$all  = self::refs();
		$rows = array( array( $label, $mm, true, 'target' ) );
		foreach ( $ids as $id ) {
			if ( isset( $all[ $id ] ) ) {
				$rows[] = array( $all[ $id ]['label'], $all[ $id ]['mm'], false, $all[ $id ]['kind'] );
			}
		}
		usort( $rows, function ( $a, $b ) {
			return DV_Math::to_float( $b[1] ) <=> DV_Math::to_float( $a[1] );
		} );
		$max = 0;
		foreach ( $rows as $r ) {
			$max = max( $max, DV_Math::to_float( $r[1] ) );
		}
		if ( $max <= 0 ) {
			return '';
		}
		$pad    = 8;
		$usable = self::W - 2 * $pad;
		$o      = array();
		$y      = 4;
		$aria   = array();
		foreach ( $rows as $r ) {
			$w     = max( 3, $usable * DV_Math::to_float( $r[1] ) / $max );
			$vals  = self::fmt( $r[1], $units[0] ) . ' · ' . self::fmt( $r[1], $units[1] );
			$aria[] = $r[0] . ' ' . $vals;
			$o[]   = '<text class="dv-s-label' . ( $r[2] ? ' dv-s-label--target' : '' ) . '" x="' . $pad . '" y="' . ( $y + 14 ) . '">' . esc_html( $r[0] ) . '</text>';
			$o[]   = '<text class="dv-s-val" x="' . ( self::W - $pad ) . '" y="' . ( $y + 14 ) . '" text-anchor="end">' . esc_html( $vals ) . '</text>';
			$o[]   = '<rect class="' . ( $r[2] ? 'dv-s-bar dv-s-bar--target' : 'dv-s-bar' ) . '" x="' . $pad . '" y="' . ( $y + 20 ) . '" width="' . self::n( $w ) . '" height="14" rx="2"/>';
			$y    += self::ROW_H;
		}
		return '<svg class="dv-sc" viewBox="0 0 ' . self::W . ' ' . ( $y + 2 ) . '" role="img" aria-label="' . esc_attr( 'Length comparison, drawn to the same scale: ' . implode( '; ', $aria ) ) . '">' . implode( '', $o ) . '</svg>';
	}

	/** Pick references closest to the target on a log scale (up to 2 shorter, 2 longer). */
	public static function auto( $mm_f ) {
		$all   = self::refs();
		$short = array();
		$long  = array();
		foreach ( $all as $id => $ref ) {
			$v = DV_Math::to_float( $ref['mm'] );
			if ( $v < $mm_f * 0.97 ) {
				$short[ $id ] = $mm_f / $v;
			} elseif ( $v > $mm_f * 1.03 ) {
				$long[ $id ] = $v / $mm_f;
			} else {
				$short[ $id ] = 1.0; // Near match: shown first.
			}
		}
		asort( $short );
		asort( $long );
		return array_merge( array_slice( array_keys( $short ), 0, 2 ), array_slice( array_keys( $long ), 0, 2 ) );
	}

	public static function sc_scale( $atts ) {
		$a = shortcode_atts(
			array(
				'value' => '',
				'unit'  => 'ft',
				'refs'  => '',
				'show'  => '',
			),
			$atts,
			'dv_scale'
		);
		$unit = sanitize_key( $a['unit'] );
		$u    = DV_Math::unit( $unit );
		$r    = DV_Math::parse( $a['value'] );
		if ( ! $u || ! $r || 0 === $r[0] ) {
			return current_user_can( 'edit_posts' ) ? '<p><strong>[dv_scale]</strong> check value and unit.</p>' : '';
		}
		$mm = DV_Math::convert( $r, $unit, 'mm' );
		$ids = '' !== $a['refs'] ? array_map( 'sanitize_key', explode( ',', $a['refs'] ) ) : self::auto( DV_Math::to_float( $mm ) );
		$mm_f = DV_Math::to_float( $mm );
		$units = $mm_f < 1000 ? array( 'cm', 'in' ) : array( 'ft', 'm' );
		if ( '' !== $a['show'] ) {
			$p = array_map( 'sanitize_key', explode( ',', $a['show'] ) );
			if ( 2 === count( $p ) && DV_Math::unit( $p[0] ) && DV_Math::unit( $p[1] ) ) {
				$units = $p;
			}
		}
		$label = DV_Render::value_text( $r, $unit );
		$svg   = self::svg( $mm, $label, $ids, $units );
		if ( '' === $svg ) {
			return '';
		}
		return '<figure class="dv-scale">' . $svg . '<figcaption>' . esc_html__( 'All bars are drawn to the same scale. Reference lengths are official or nominal sizes; real objects vary.', 'dv-converters' ) . '</figcaption></figure>';
	}
}
