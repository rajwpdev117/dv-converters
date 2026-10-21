<?php
/**
 * Plugin Name:       DimensionVista Converters
 * Plugin URI:        https://dimensionvista.com/
 * Description:       Server-rendered unit converters for DimensionVista: answer box with fractions, live converter, scaled ruler visual, charts, FAQ and schema. Shortcodes: [dv_converter], [dv_chart], [dv_faq], [dv_facts], [dv_breadcrumbs].
 * Version:           1.0.2
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

define( 'DV_CONVERTERS_VERSION', '1.0.2' );
define( 'DV_CONVERTERS_URL', plugin_dir_url( __FILE__ ) );
define( 'DV_CONVERTERS_DIR', plugin_dir_path( __FILE__ ) );

require_once DV_CONVERTERS_DIR . 'includes/class-dv-math.php';
require_once DV_CONVERTERS_DIR . 'includes/class-dv-visual.php';
require_once DV_CONVERTERS_DIR . 'includes/class-dv-render.php';
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
		$tags    = array( 'dv_converter', 'dv_chart', 'dv_faq', 'dv_facts', 'dv_breadcrumbs' );
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

// Tool pages are clean: no sidebar (GeneratePress). Easy TOC: turn off auto-insert for Pages in its settings.
add_filter(
	'generate_sidebar_layout',
	function ( $layout ) {
		return DV_Context::$active && apply_filters( 'dv_converters_no_sidebar', true ) ? 'no-sidebar' : $layout;
	},
	99
);

DV_Schema::init();

/**
 * Updates from the private GitHub repo (Dashboard > Updates).
 * Add this line to wp-config.php, with a fine-grained read-only token for this repo:
 *   define( 'DV_CONVERTERS_GITHUB_TOKEN', 'github_pat_...' );
 */
if ( is_admin() && file_exists( DV_CONVERTERS_DIR . 'lib/plugin-update-checker/plugin-update-checker.php' ) ) {
	require_once DV_CONVERTERS_DIR . 'lib/plugin-update-checker/plugin-update-checker.php';
	$dv_updater = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
		'https://github.com/rajwpdev117/dv-converters/',
		__FILE__,
		'dv-converters'
	);
	if ( defined( 'DV_CONVERTERS_GITHUB_TOKEN' ) && DV_CONVERTERS_GITHUB_TOKEN ) {
		$dv_updater->setAuthentication( DV_CONVERTERS_GITHUB_TOKEN );
	}
}
