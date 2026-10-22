<?php
/**
 * Shortcodes. Everything a visitor or a crawler needs (answer, results, visual,
 * chart, FAQ) is in the HTML; JavaScript only updates it while typing.
 *
 * @package DV_Converters
 */

defined( 'ABSPATH' ) || exit;

class DV_Render {

	private static $uid = 0;

	public static function register() {
		add_shortcode( 'dv_converter', array( __CLASS__, 'sc_converter' ) );
		add_shortcode( 'dv_chart', array( __CLASS__, 'sc_chart' ) );
		add_shortcode( 'dv_scale', array( 'DV_Scale', 'sc_scale' ) );
		add_shortcode( 'dv_faq', array( __CLASS__, 'sc_faq' ) );
		add_shortcode( 'dv_q', array( __CLASS__, 'sc_q' ) );
		add_shortcode( 'dv_facts', array( __CLASS__, 'sc_facts' ) );
		add_shortcode( 'dv_breadcrumbs', array( __CLASS__, 'breadcrumbs' ) );
	}

	/* ------------------------------------------------------------------ */
	/* Shared text builders                                                */
	/* ------------------------------------------------------------------ */

	public static function converter_atts( $atts ) {
		$a = shortcode_atts(
			array(
				'from'      => 'mm',
				'to'        => 'in',
				'value'     => '',      // Empty = main tool page (definition answer).
				'default'   => '10',    // Starting input on a main tool page.
				'dp'        => '4',
				'fractions' => '16,32,64',
				'visual'    => 'yes',
				'label'     => '',      // Breadcrumb label for value pages, e.g. "8 mm".
			),
			$atts,
			'dv_converter'
		);
		$a['from']      = sanitize_key( $a['from'] );
		$a['to']        = sanitize_key( $a['to'] );
		$a['dp']        = max( 0, min( 6, (int) $a['dp'] ) );
		$a['fractions'] = array_values( array_filter( array_map( 'intval', explode( ',', $a['fractions'] ) ), function ( $d ) {
			return in_array( $d, array( 2, 4, 8, 16, 32, 64, 128 ), true );
		} ) );
		$a['pair'] = dv_converters_pair( $a['from'], $a['to'] );
		return $a;
	}

	/** Show an input value the way a person would write it. */
	public static function value_text( $r, $unit ) {
		if ( 'in' === $unit && 0 === ( 64 * $r[0] ) % $r[1] && 0 !== $r[0] % $r[1] ) {
			return DV_Math::fraction_text( intdiv( 64 * $r[0], $r[1] ), 64 );
		}
		$ex = DV_Math::exact_decimal( $r );
		$t  = null === $ex ? DV_Math::decimal( $r, 4 )['text'] : $ex;
		return $t . ( 'in' === $unit ? '″' : ' ' . DV_Math::unit( $unit )['short'] );
	}

	/** Decimal with the right sign: "= 25.4 mm" or "≈ 0.315 in". */
	private static function dec_with_sign( $r, $dp, $short ) {
		$d = DV_Math::decimal( $r, $dp );
		if ( '0' === $d['text'] && $r[0] > 0 ) {
			// Too small for $dp places; widen precision instead of showing 0.
			for ( $p = $dp + 1; $p <= DV_Math::MAX_DP && '0' === $d['text']; $p++ ) {
				$d = DV_Math::decimal( $r, $p );
			}
		}
		return array( ( $d['exact'] ? '=' : '≈' ), $d['text'] . ' ' . $short );
	}

	/**
	 * Answer box text.
	 *
	 * @return array{main:string,note:string} Escaped HTML.
	 */
	public static function answer( $a ) {
		$from = DV_Math::unit( $a['from'] );
		$to   = DV_Math::unit( $a['to'] );
		$r    = '' === $a['value'] ? null : DV_Math::parse( $a['value'] );

		if ( null === $r ) {
			// Main tool page: state the exact definition first.
			$one_to   = DV_Math::convert( array( 1, 1 ), $a['to'], $a['from'] );
			$one_from = DV_Math::convert( array( 1, 1 ), $a['from'], $a['to'] );
			$ex_to    = DV_Math::exact_decimal( $one_to );
			if ( null !== $ex_to ) {
				list( $s, $t ) = self::dec_with_sign( $one_from, 5, $to['short'] );
				$main = sprintf( '1 %s = %s %s', $to['one'], $ex_to, $from['short'] );
				$note = sprintf(
					/* translators: 1: from unit, 2: sign, 3: value, 4: to name, 5: from name, 6: factor */
					__( 'Exactly, by definition · 1 %1$s %2$s %3$s · %4$s = %5$s ÷ %6$s', 'dv-converters' ),
					$from['short'], $s, $t, $to['name'], $from['name'], $ex_to
				);
			} else {
				$ex_from        = DV_Math::exact_decimal( $one_from );
				list( $s, $t )  = self::dec_with_sign( $one_to, 5, $from['short'] );
				$main = sprintf( '1 %s = %s %s', $from['one'], $ex_from, $to['short'] );
				$note = sprintf(
					/* translators: 1: to unit, 2: sign, 3: value, 4: to name, 5: from name, 6: factor */
					__( 'Exactly, by definition · 1 %1$s %2$s %3$s · %4$s = %5$s × %6$s', 'dv-converters' ),
					$to['short'], $s, $t, $to['name'], $from['name'], $ex_from
				);
			}
			return array( 'main' => esc_html( $main ), 'note' => esc_html( $note ) );
		}

		$in_text = self::value_text( $r, $a['from'] );
		$res     = DV_Math::convert( $r, $a['from'], $a['to'] );

		if ( 'in' === $a['to'] ) {
			$dens          = $a['fractions'] ? $a['fractions'] : array( 16, 32, 64 );
			$a['fractions'] = $dens;
			$best          = DV_Math::best( $res, $dens );
			list( $s, $t ) = self::dec_with_sign( $res, 3, 'in' );
			if ( 0 === $best['k'] ) {
				$main = sprintf( '%s %s %s', $in_text, $s, $t );
				$note = sprintf( /* translators: %s: fraction */ __( 'Less than half of %s', 'dv-converters' ), DV_Math::fraction_text( 1, end( $a['fractions'] ) ) );
			} elseif ( 0 === $best['cmp'] ) {
				$main = sprintf( '%s = <b>%s</b>', esc_html( $in_text ), esc_html( $best['text'] ) );
				$note = esc_html__( 'Exact, because 1 inch is defined as exactly 25.4 mm', 'dv-converters' );
				return array( 'main' => $main, 'note' => $note );
			} else {
				$main = sprintf( '%s ≈ <b>%s</b>', esc_html( $in_text ), esc_html( $best['text'] ) );
				$note = esc_html(
					sprintf(
						/* translators: 1: "slightly more than", 2: fraction, 3: difference, 4: sign, 5: decimal */
						__( '%1$s %2$s (by %3$s) · %4$s %5$s', 'dv-converters' ),
						ucfirst( DV_Math::cmp_words( $best['cmp'] ) ),
						$best['text'],
						DV_Math::diff_mm( $res, $best['value'] ),
						$s,
						$t
					)
				);
				return array( 'main' => $main, 'note' => $note );
			}
			return array( 'main' => esc_html( $main ), 'note' => esc_html( $note ) );
		}

		list( $s, $t ) = self::dec_with_sign( $res, $a['dp'], $to['short'] );
		$ex   = DV_Math::exact_decimal( $res );
		$main = sprintf( '%s %s <b>%s</b>', esc_html( $in_text ), $s, esc_html( null !== $ex ? $ex . ' ' . $to['short'] : $t ) );
		$is_ft = 'ft' === $a['from'] || 'ft' === $a['to'];
		if ( null !== $ex && $is_ft ) {
			$note = esc_html__( 'Exact, because 1 foot is defined as exactly 0.3048 m', 'dv-converters' );
		} elseif ( null !== $ex && ( 'in' === $a['from'] || 'in' === $a['to'] ) ) {
			$note = esc_html__( 'Exact, because 1 inch is defined as exactly 25.4 mm', 'dv-converters' );
		} elseif ( null !== $ex ) {
			$note = esc_html__( 'Exact', 'dv-converters' );
		} else {
			$note = esc_html( sprintf( /* translators: %d: decimals */ __( 'Rounded to %d decimal places', 'dv-converters' ), $a['dp'] ) );
		}
		return array( 'main' => $main, 'note' => $note );
	}

	/** Result rows for the live converter (also rendered by JS). */
	public static function results( $r, $from, $to, $dp, $dens ) {
		if ( null === $r ) {
			return '<p class="dv-r__err">' . esc_html__( 'Enter a number such as 8, 8.5 or 5/16.', 'dv-converters' ) . '</p>';
		}
		$res  = DV_Math::convert( $r, $from, $to );
		$u    = DV_Math::unit( $to );
		$name = self::value_text( $r, $from );
		list( $s, $t ) = self::dec_with_sign( $res, $dp, $u['short'] );
		$rows = array( array( esc_html( ucfirst( $u['name'] ) ), '<b>' . esc_html( $s . ' ' . $t ) . '</b>' ) );
		if ( 'in' === $to ) {
			foreach ( $dens as $den ) {
				$f = DV_Math::nearest( $res, $den );
				if ( 0 === $f['cmp'] ) {
					$diff = __( 'exact', 'dv-converters' );
				} else {
					$diff = sprintf(
						/* translators: 1: input, 2: difference, 3: longer/shorter */
						__( '%1$s is %2$s %3$s', 'dv-converters' ),
						$name,
						DV_Math::diff_mm( $res, $f['value'] ),
						$f['cmp'] > 0 ? __( 'longer', 'dv-converters' ) : __( 'shorter', 'dv-converters' )
					);
				}
				$rows[] = array(
					/* translators: %s: fraction such as 1/16″ */
					esc_html( sprintf( __( 'Nearest %s', 'dv-converters' ), '1/' . $den . '″' ) ),
					'<b>' . esc_html( $f['text'] ) . '</b> <span class="dv-r__diff">' . esc_html( $diff ) . '</span>',
				);
			}
		}
		$o = '<dl class="dv-r">';
		foreach ( $rows as $row ) {
			$o .= '<div><dt>' . $row[0] . '</dt><dd>' . $row[1] . '</dd></div>';
		}
		return $o . '</dl>';
	}

	/* ------------------------------------------------------------------ */
	/* [dv_converter]                                                      */
	/* ------------------------------------------------------------------ */

	public static function sc_converter( $atts ) {
		$a    = self::converter_atts( $atts );
		$from = DV_Math::unit( $a['from'] );
		$to   = DV_Math::unit( $a['to'] );
		if ( ! $from || ! $to ) {
			return current_user_can( 'edit_posts' ) ? '<p><strong>[dv_converter]</strong> unknown unit.</p>' : '';
		}
		$id    = 'dv-conv-' . ( ++self::$uid );
		$ans   = self::answer( $a );
		$start = '' !== $a['value'] ? $a['value'] : $a['default'];
		$r     = DV_Math::parse( $start );
		$dens  = $a['fractions'] ? $a['fractions'] : array( 16, 32, 64 );

		$o  = '<section class="dv-tool" id="' . esc_attr( $id ) . '" data-from="' . esc_attr( $a['from'] ) . '" data-to="' . esc_attr( $a['to'] ) . '" data-dp="' . esc_attr( $a['dp'] ) . '" data-fractions="' . esc_attr( implode( ',', $dens ) ) . '">';
		$o .= '<div class="dv-answer"><p class="dv-answer__main">' . $ans['main'] . '</p><p class="dv-answer__note">' . $ans['note'] . '</p></div>';

		$o .= '<div class="dv-conv">';
		$o .= '<label class="dv-conv__label" for="' . esc_attr( $id ) . '-in">' . esc_html( sprintf( '%s (%s)', ucfirst( $from['name'] ), $from['short'] ) ) . '</label>';
		$o .= '<div class="dv-conv__row">';
		$o .= '<input class="dv-conv__input" id="' . esc_attr( $id ) . '-in" type="text" inputmode="decimal" autocomplete="off" spellcheck="false" value="' . esc_attr( $start ) . '" aria-describedby="' . esc_attr( $id ) . '-out">';
		$o .= '<span class="dv-conv__unit">' . esc_html( $from['short'] ) . '</span>';
		$o .= '<button type="button" class="dv-conv__swap" hidden aria-label="' . esc_attr__( 'Swap units', 'dv-converters' ) . '">⇄ <span>' . esc_html( sprintf( '%s to %s', $to['short'], $from['short'] ) ) . '</span></button>';
		$o .= '</div>';
		$o .= '<div class="dv-conv__out" id="' . esc_attr( $id ) . '-out" aria-live="polite">' . self::results( $r, $a['from'], $a['to'], $a['dp'], $dens ) . '</div>';
		$o .= '</div>';

		if ( 'yes' === $a['visual'] && null !== $r && in_array( 'in', array( $a['from'], $a['to'] ), true ) ) {
			$mm       = DV_Math::to_float( DV_Math::convert( $r, $a['from'], 'mm' ) );
			$r_in     = DV_Math::convert( $r, $a['from'], 'in' );
			$top_unit = 'cm' === $a['from'] || 'cm' === $a['to'] ? 'cm' : 'mm';
			$top      = self::value_text( DV_Math::convert( $r, $a['from'], $top_unit ), $top_unit );
			$best     = DV_Math::best( $r_in );
			$bottom   = ( 0 === $best['cmp'] ? '= ' : '≈ ' ) . ( $best['k'] > 0 ? $best['text'] : DV_Math::decimal( $r_in, 3 )['text'] . ' in' );
			$o       .= '<figure class="dv-visual" data-top="' . esc_attr( $top_unit ) . '">' . DV_Visual::svg( $mm, $top, $bottom, $top_unit ) . '<figcaption>' . esc_html( DV_Visual::caption( $mm, $top_unit ) ) . '</figcaption></figure>';
		}

		$o .= '</section>';
		return $o;
	}

	/* ------------------------------------------------------------------ */
	/* [dv_chart]                                                          */
	/* ------------------------------------------------------------------ */

	public static function sc_chart( $atts ) {
		$a = shortcode_atts(
			array(
				'from'      => 'mm',
				'to'        => 'in',
				'start'     => '1',
				'end'       => '100',
				'step'      => '1',
				'highlight' => '',
				'dp'        => '3',
				'fractions' => '16,32',
				'link'      => 'auto',
				'caption'   => '',
				'pdf'       => '',
				'print'     => 'yes',
			),
			$atts,
			'dv_chart'
		);
		$from  = DV_Math::unit( sanitize_key( $a['from'] ) );
		$to    = DV_Math::unit( sanitize_key( $a['to'] ) );
		$start = DV_Math::parse( $a['start'] );
		$end   = DV_Math::parse( $a['end'] );
		$step  = DV_Math::parse( $a['step'] );
		if ( ! $from || ! $to || ! $start || ! $end || ! $step || 0 === $step[0] ) {
			return current_user_can( 'edit_posts' ) ? '<p><strong>[dv_chart]</strong> check from, to, start, end and step.</p>' : '';
		}
		$fk   = sanitize_key( $a['from'] );
		$tk   = sanitize_key( $a['to'] );
		$dp   = max( 0, min( 6, (int) $a['dp'] ) );
		$dens = array_values( array_filter( array_map( 'intval', explode( ',', $a['fractions'] ) ) ) );
		$hl   = '' !== $a['highlight'] ? DV_Math::parse( $a['highlight'] ) : null;
		$pair = dv_converters_pair( $fk, $tk );

		// Rows.
		$values = array();
		for ( $i = 0; $i < 600; $i++ ) {
			$v = DV_Math::reduce( $start[0] * $step[1] * 1 + $i * $step[0] * $start[1], $start[1] * $step[1] );
			if ( $v[0] * $end[1] > $end[0] * $v[1] ) {
				break;
			}
			$values[] = $v;
		}

		// Value pages that exist, for row links (one query).
		$links = array();
		if ( 'auto' === $a['link'] && $pair && ! empty( $pair['value_slug'] ) ) {
			$slugs = array();
			foreach ( $values as $v ) {
				$ex = DV_Math::exact_decimal( $v );
				if ( null !== $ex && 'in' !== $fk ) {
					$slugs[ sprintf( $pair['value_slug'], str_replace( array( '.', ',' ), array( '-', '' ), $ex ) ) ] = $ex;
				}
			}
			if ( $slugs ) {
				$found = get_posts(
					array(
						'post_type'      => 'page',
						'post_status'    => 'publish',
						'post_name__in'  => array_keys( $slugs ),
						'posts_per_page' => count( $slugs ),
						'no_found_rows'  => true,
					)
				);
				foreach ( $found as $p ) {
					$links[ $slugs[ $p->post_name ] ] = get_permalink( $p );
				}
			}
		}

		$frac_cols = 'in' === $tk ? $dens : array();
		$head      = array( esc_html( sprintf( '%s (%s)', ucfirst( $from['name'] ), $from['short'] ) ) );
		if ( 'in' === $fk ) {
			$head[] = esc_html__( 'Inches (decimal)', 'dv-converters' );
		}
		$head[] = esc_html( sprintf( '%s (%s)', ucfirst( $to['name'] ), 'in' === $tk ? __( 'decimal', 'dv-converters' ) : $to['short'] ) );
		foreach ( $frac_cols as $den ) {
			/* translators: %s: 1/16″ */
			$head[] = esc_html( sprintf( __( 'Nearest %s', 'dv-converters' ), '1/' . $den . '″' ) );
		}

		$caption = '' !== $a['caption'] ? $a['caption'] : sprintf(
			/* translators: 1: from, 2: to, 3: start, 4: end */
			__( '%1$s to %2$s chart, %3$s to %4$s', 'dv-converters' ),
			$from['short'],
			$to['name'],
			self::value_text( $start, $fk ),
			self::value_text( $end, $fk )
		);

		$o  = '<div class="dv-chart">';
		$o .= '<div class="dv-chart__bar">';
		if ( 'yes' === $a['print'] ) {
			$o .= '<button type="button" class="dv-btn dv-chart__print" hidden>' . esc_html__( 'Print chart', 'dv-converters' ) . '</button>';
		}
		if ( '' !== $a['pdf'] ) {
			$o .= '<a class="dv-btn" href="' . esc_url( $a['pdf'] ) . '" download>' . esc_html__( 'Download PDF', 'dv-converters' ) . '</a>';
		}
		$o .= '</div>';
		if ( $frac_cols ) {
			$o .= '<p class="dv-chart__key">' . esc_html(
				sprintf(
					/* translators: %d: decimal places */
					__( 'Decimals are rounded to %d places. In the fraction columns, + means the value is slightly longer than the fraction, − slightly shorter, and = exact.', 'dv-converters' ),
					$dp
				)
			) . '</p>';
		}
		$o .= '<div class="dv-chart__scroll" tabindex="0" role="region" aria-label="' . esc_attr( $caption ) . '"><table class="dv-table"><caption>' . esc_html( $caption ) . '</caption><thead><tr>';
		foreach ( $head as $h ) {
			$o .= '<th scope="col">' . $h . '</th>';
		}
		$o .= '</tr></thead><tbody>';

		foreach ( $values as $v ) {
			$res   = DV_Math::convert( $v, $fk, $tk );
			$label = self::value_text( $v, $fk );
			$key   = DV_Math::exact_decimal( $v );
			$is_hl = $hl && $hl[0] === $v[0] && $hl[1] === $v[1];
			$cell  = isset( $links[ $key ] ) ? '<a href="' . esc_url( $links[ $key ] ) . '">' . esc_html( $label ) . '</a>' : esc_html( $label );
			$o    .= '<tr' . ( $is_hl ? ' class="dv-hl" id="dv-row-' . esc_attr( str_replace( '.', '-', $key ) ) . '"' : '' ) . '><th scope="row">' . $cell . '</th>';
			if ( 'in' === $fk ) {
				$o .= '<td>' . esc_html( DV_Math::exact_decimal( $v ) ) . '</td>';
			}
			// Fractional inches convert to terminating decimals: show them exactly.
			$ex = 'in' === $fk ? DV_Math::exact_decimal( $res ) : null;
			$o .= '<td>' . esc_html( null !== $ex ? $ex : DV_Math::decimal( $res, $dp )['text'] ) . '</td>';
			foreach ( $frac_cols as $den ) {
				$f    = DV_Math::nearest( $res, $den );
				$mark = 0 === $f['cmp'] ? '=' : ( $f['cmp'] > 0 ? '+' : '−' );
				$tip  = 0 === $f['cmp'] ? __( 'exact', 'dv-converters' ) : ( $f['cmp'] > 0 ? __( 'slightly longer', 'dv-converters' ) : __( 'slightly shorter', 'dv-converters' ) );
				$o   .= '<td>' . esc_html( $f['text'] ) . ' <abbr class="dv-m" title="' . esc_attr( $tip ) . '">' . $mark . '</abbr></td>';
			}
			$o .= '</tr>';
		}
		$o .= '</tbody></table></div></div>';
		return $o;
	}

	/* ------------------------------------------------------------------ */
	/* [dv_faq][dv_q q="..."]Answer[/dv_q][/dv_faq]                         */
	/* ------------------------------------------------------------------ */

	public static function sc_faq( $atts, $content = '' ) {
		$a = shortcode_atts( array( 'title' => __( 'Frequently asked questions', 'dv-converters' ) ), $atts, 'dv_faq' );
		$o = '<section class="dv-faq">';
		if ( '' !== $a['title'] ) {
			$o .= '<h2>' . esc_html( $a['title'] ) . '</h2>';
		}
		return $o . self::clean( do_shortcode( shortcode_unautop( $content ) ) ) . '</section>';
	}

	public static function sc_q( $atts, $content = '' ) {
		$a = shortcode_atts( array( 'q' => '' ), $atts, 'dv_q' );
		if ( '' === $a['q'] ) {
			return '';
		}
		$body = self::clean( do_shortcode( $content ) );
		if ( false === strpos( $body, '<p' ) ) {
			$body = wpautop( $body );
		}
		return '<div class="dv-faq__item"><h3>' . esc_html( $a['q'] ) . '</h3><div class="dv-faq__a">' . wp_kses_post( $body ) . '</div></div>';
	}

	/** Remove stray paragraph tags wpautop leaves around nested shortcodes. */
	private static function clean( $html ) {
		$html = trim( $html );
		$html = preg_replace( '#^</p>\s*#', '', $html );
		$html = preg_replace( '#\s*<p>$#', '', $html );
		return preg_replace( '#<p>\s*</p>#', '', $html );
	}

	/* ------------------------------------------------------------------ */
	/* [dv_facts updated="2026-10-21"]                                     */
	/* ------------------------------------------------------------------ */

	public static function sc_facts( $atts ) {
		$a = shortcode_atts(
			array(
				'updated'   => '',
				'basis'     => 'inch',
				'precision' => __( 'Decimals are rounded to 4 places in the converter and 3 in the chart; fractions go to the nearest 1/64″.', 'dv-converters' ),
			),
			$atts,
			'dv_facts'
		);
		$nist = apply_filters( 'dv_converters_nist_url', 'https://www.nist.gov/pml/special-publication-811/nist-guide-si-appendix-b-conversion-factors/nist-guide-si-appendix-b9' );
		$o    = '<aside class="dv-facts"><p>';
		$msg  = 'foot' === $a['basis']
			/* translators: %s: link to NIST */
			? __( 'How the numbers work: 1 foot is defined as exactly 0.3048 m, which is 12 inches of 25.4 mm each (%s), so every result here is calculated from that factor with exact arithmetic.', 'dv-converters' )
			/* translators: %s: link to NIST */
			: __( 'How the numbers work: 1 inch is defined as exactly 25.4 mm (%s), so every result here is calculated from that factor with exact arithmetic.', 'dv-converters' );
		$o   .= wp_kses(
			sprintf(
				$msg,
				'<a href="' . esc_url( $nist ) . '" rel="noopener" target="_blank">NIST SP 811</a>'
			),
			array( 'a' => array( 'href' => true, 'rel' => true, 'target' => true ) )
		);
		$o .= ' ' . esc_html( $a['precision'] );
		$o .= ' ' . esc_html__( 'Free, with no sign-up. Conversions run in your browser; nothing is stored.', 'dv-converters' );
		if ( '' !== $a['updated'] && strtotime( $a['updated'] ) ) {
			$o .= ' <span class="dv-facts__date">' . esc_html__( 'Last updated', 'dv-converters' ) . ' <time datetime="' . esc_attr( gmdate( 'Y-m-d', strtotime( $a['updated'] ) ) ) . '">' . esc_html( date_i18n( get_option( 'date_format' ), strtotime( $a['updated'] ) ) ) . '</time>.</span>';
		}
		return $o . '</p></aside>';
	}

	/* ------------------------------------------------------------------ */
	/* Breadcrumbs: Home › Length › mm to inches › 8 mm                    */
	/* ------------------------------------------------------------------ */

	/** @return array<int,array{0:string,1:string}> [name, url] items. */
	public static function trail() {
		$c = DV_Context::$converter;
		if ( ! $c || ! $c['pair'] ) {
			return array();
		}
		$p     = $c['pair'];
		$items = array( array( __( 'Home', 'dv-converters' ), home_url( '/' ) ) );
		if ( ! empty( $p['hub_path'] ) && get_page_by_path( trim( $p['hub_path'], '/' ) ) ) {
			$items[] = array( $p['hub_label'], home_url( $p['hub_path'] ) );
		}
		$here = get_permalink();
		if ( '' === $c['value'] ) {
			$items[] = array( $p['tool_label'], $here );
		} else {
			if ( get_page_by_path( trim( $p['tool_path'], '/' ) ) ) {
				$items[] = array( $p['tool_label'], home_url( $p['tool_path'] ) );
			}
			$r       = DV_Math::parse( $c['value'] );
			$items[] = array( '' !== $c['label'] ? $c['label'] : ( $r ? self::value_text( $r, $c['from'] ) : $c['value'] ), $here );
		}
		return $items;
	}

	public static function breadcrumbs() {
		$items = self::trail();
		if ( ! $items ) {
			return '';
		}
		$o    = '<nav class="dv-crumbs" aria-label="' . esc_attr__( 'Breadcrumb', 'dv-converters' ) . '"><ol>';
		$last = count( $items ) - 1;
		foreach ( $items as $i => $it ) {
			$o .= $i === $last
				? '<li aria-current="page">' . esc_html( $it[0] ) . '</li>'
				: '<li><a href="' . esc_url( $it[1] ) . '">' . esc_html( $it[0] ) . '</a></li>';
		}
		return $o . '</ol></nav>';
	}
}
