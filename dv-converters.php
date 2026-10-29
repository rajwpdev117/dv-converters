<?php
/**
 * Plugin Name:       DimensionVista Converters
 * Plugin URI:        https://dimensionvista.com/
 * Description:       Server-rendered unit converters for DimensionVista: answer box with fractions, live converter, scaled ruler visual, charts, FAQ and schema. Shortcodes: [dv_converter], [dv_chart], [dv_scale], [dv_faq], [dv_facts], [dv_breadcrumbs]. Length (mm, cm, m, in, ft) and weight (g, kg, lb, oz) units.
 * Version:           1.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Rajesh Kumar
 * Author URI:        https://dimensionvista.com/author/rajeshkumar-dv/
 * License:           GPL-2.0-or-later
 * Text Domain:       dv-converters
 *
 * @package DV_Converters
 */

defined( 'ABSPATH' ) || exit;

define( 'DV_CONVERTERS_VERSION', '1.1.0' );
define( 'DV_CONVERTERS_URL', plugin_dir_url( __FILE__ ) );
define( 'DV_CONVERTERS_DIR', plugin_dir_path( __FILE__ ) );

require_once DV_CONVERTERS_DIR . 'includes/class-dv-math.php';
require_once DV_CONVERTERS_DIR . 'includes/class-dv-visual.php';
require_once DV_CONVERTERS_DIR . 'includes/class-dv-render.php';
require_once DV_CONVERTERS_DIR . 'includes/class-dv-scale.php';
require_once DV_CONVERTERS_DIR . 'includes/class-dv-schema.php';

/**
 * Conversion pairs. Each pair knows its main tool page, hub and the slug
 * pattern of its value pages, which drives breadcrumbs and chart links.
 * Add pairs with the `dv_converters_pairs` filter.
 */
function dv_converters_pairs() {
	$pairs = array(
		'mm-in' => array(
			'from'       => 'mm',
			'to'         => 'in',
			'tool_path'  => '/mm-to-inches/',
			'tool_label' => 'mm to inches',
			'hub_path'   => '/length/',
			'hub_label'  => 'Length',
			'value_slug' => '%s-mm-to-inches',
			'app_name'   => 'mm to inches converter',
		),
		'cm-in' => array(
			'from'       => 'cm',
			'to'         => 'in',
			'tool_path'  => '/cm-to-inches/',
			'tool_label' => 'cm to inches',
			'hub_path'   => '/length/',
			'hub_label'  => 'Length',
			'value_slug' => '%s-cm-to-inches',
			'app_name'   => 'cm to inches converter',
		),
		'in-mm' => array(
			'from'       => 'in',
			'to'         => 'mm',
			'tool_path'  => '/inches-to-mm/',
			'tool_label' => 'inches to mm',
			'hub_path'   => '/length/',
			'hub_label'  => 'Length',
			'value_slug' => '%s-inches-to-mm',
			'app_name'   => 'inches to mm converter',
		),
		'ft-m'  => array(
			'from'       => 'ft',
			'to'         => 'm',
			'tool_path'  => '/feet-to-meters/',
			'tool_label' => 'feet to meters',
			'hub_path'   => '/length/',
			'hub_label'  => 'Length',
			'value_slug' => '%s-feet-to-meters',
			'app_name'   => 'feet to meters converter',
		),
		'm-ft'  => array(
			'from'       => 'm',
			'to'         => 'ft',
			'tool_path'  => '/meters-to-feet/',
			'tool_label' => 'meters to feet',
			'hub_path'   => '/length/',
			'hub_label'  => 'Length',
			'value_slug' => '%s-meters-to-feet',
			'app_name'   => 'meters to feet converter',
		),
		'kg-lb' => array(
			'from'       => 'kg',
			'to'         => 'lb',
			'tool_path'  => '/kg-to-lbs/',
			'tool_label' => 'kg to lbs',
			'hub_path'   => '/weight/',
			'hub_label'  => 'Weight',
			'value_slug' => '%s-kg-to-lbs',
			'app_name'   => 'kg to lbs converter',
			'defn'       => '1 pound is defined as exactly 0.45359237 kg',
		),
		'lb-kg' => array(
			'from'       => 'lb',
			'to'         => 'kg',
			'tool_path'  => '/lbs-to-kg/',
			'tool_label' => 'lbs to kg',
			'hub_path'   => '/weight/',
			'hub_label'  => 'Weight',
			'value_slug' => '%s-lbs-to-kg',
			'app_name'   => 'lbs to kg converter',
			'defn'       => '1 pound is defined as exactly 0.45359237 kg',
		),
		'g-oz'  => array(
			'from'       => 'g',
			'to'         => 'oz',
			'tool_path'  => '/grams-to-ounces/',
			'tool_label' => 'grams to ounces',
			'hub_path'   => '/weight/',
			'hub_label'  => 'Weight',
			'value_slug' => '%s-grams-to-ounces',
			'app_name'   => 'grams to ounces converter',
			'defn'       => '1 ounce is defined as exactly 28.349523125 g',
		),
		'oz-g'  => array(
			'from'       => 'oz',
			'to'         => 'g',
			'tool_path'  => '/ounces-to-grams/',
			'tool_label' => 'ounces to grams',
			'hub_path'   => '/weight/',
			'hub_label'  => 'Weight',
			'value_slug' => '%s-ounces-to-grams',
			'app_name'   => 'ounces to grams converter',
			'defn'       => '1 ounce is defined as exactly 28.349523125 g',
		),
	);
	return apply_filters( 'dv_converters_pairs', $pairs );
}

function dv_converters_pair( $from, $to ) {
	$pairs = dv_converters_pairs();
	$key   = $from . '-' . $to;
	return isset( $pairs[ $key ] ) ? $pairs[ $key ] : null;
}

/**
 * Per-request context, read from the post content before the page renders so
 * the schema (printed in <head>) and assets know what the page contains.
 */
class DV_Context {

	public static $converter = null; // First [dv_converter] attributes.
	public static $faq       = array(); // [ [q, a], ... ].
	public static $active    = false;

	public static function collect() {
		if ( is_admin() || ! is_singular() ) {
			return;
		}
		$post = get_queried_object();
		if ( ! $post instanceof WP_Post ) {
			return;
		}
		$content = $post->post_content;
		$tags    = array( 'dv_converter', 'dv_chart', 'dv_scale', 'dv_faq', 'dv_facts', 'dv_breadcrumbs' );
		foreach ( $tags as $tag ) {
			if ( has_shortcode( $content, $tag ) ) {
				self::$active = true;
			}
		}
		if ( ! self::$active ) {
			return;
		}

		preg_match_all( '/' . get_shortcode_regex( array( 'dv_converter', 'dv_q' ) ) . '/s', $content, $m, PREG_SET_ORDER );
		foreach ( $m as $sc ) {
			$atts = shortcode_parse_atts( $sc[3] );
			$atts = is_array( $atts ) ? $atts : array();
			if ( 'dv_converter' === $sc[2] && null === self::$converter ) {
				self::$converter = DV_Render::converter_atts( $atts );
			} elseif ( 'dv_q' === $sc[2] && ! empty( $atts['q'] ) ) {
				self::$faq[] = array( wp_strip_all_tags( $atts['q'] ), trim( wp_strip_all_tags( do_shortcode( $sc[5] ) ) ) );
			}
		}
	}
}
add_action( 'wp', array( 'DV_Context', 'collect' ) );

add_action(
	'init',
	function () {
		DV_Render::register();
	}
);

add_action(
	'wp_enqueue_scripts',
	function () {
		wp_register_style( 'dv-converters', DV_CONVERTERS_URL . 'assets/dv-converters.css', array(), DV_CONVERTERS_VERSION );
		wp_register_script( 'dv-converters', DV_CONVERTERS_URL . 'assets/dv-converters.js', array(), DV_CONVERTERS_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
		if ( DV_Context::$active ) {
			wp_enqueue_style( 'dv-converters' );
			wp_enqueue_script( 'dv-converters' );
		}
	}
);

// Visible breadcrumbs at the top of the content on any page that holds a converter
// (works in any theme; skipped when the page already has [dv_breadcrumbs]).
add_filter(
	'the_content',
	function ( $content ) {
		static $done = false;
		if ( $done || ! DV_Context::$converter || ! in_the_loop() || ! is_main_query() || has_shortcode( $content, 'dv_breadcrumbs' ) ) {
			return $content;
		}
		if ( ! apply_filters( 'dv_converters_auto_breadcrumbs', true ) ) {
			return $content;
		}
		$done = true;
		return DV_Render::breadcrumbs() . $content;
	},
	5
);

// Tool pages are clean: no sidebar (GeneratePress) and no auto table of contents (Easy TOC).
add_filter(
	'generate_sidebar_layout',
	function ( $layout ) {
		return DV_Context::$active && apply_filters( 'dv_converters_no_sidebar', true ) ? 'no-sidebar' : $layout;
	},
	99
);
add_filter(
	'ez_toc_maybe_apply_the_content_filter',
	function ( $apply ) {
		return DV_Context::$active && apply_filters( 'dv_converters_no_toc', true ) ? false : $apply;
	},
	99
);

// Marker class so the stylesheet can tidy tool pages (e.g. hide Easy TOC).
add_filter(
	'body_class',
	function ( $classes ) {
		if ( DV_Context::$active ) {
			$classes[] = 'dv-page';
		}
		return $classes;
	}
);

// Updates from the private GitHub repository (Releases). Add to wp-config.php:
// define( 'DV_CONVERTERS_GITHUB_TOKEN', 'github_pat_...' ); (read-only, Contents: read).
$dv_puc = DV_CONVERTERS_DIR . 'lib/plugin-update-checker/plugin-update-checker.php';
if ( file_exists( $dv_puc ) ) {
	require_once $dv_puc;
	$dv_update_checker = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
		'https://github.com/rajwpdev117/dv-converters/',
		__FILE__,
		'dv-converters'
	);
	if ( defined( 'DV_CONVERTERS_GITHUB_TOKEN' ) && DV_CONVERTERS_GITHUB_TOKEN ) {
		$dv_update_checker->setAuthentication( DV_CONVERTERS_GITHUB_TOKEN );
	}
}

DV_Schema::init();
