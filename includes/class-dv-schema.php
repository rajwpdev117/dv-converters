<?php
/**
 * Schema: WebApplication (free), FAQPage and BreadcrumbList for converter pages.
 * With Rank Math active the nodes join Rank Math's own @graph (so Organization,
 * WebSite and WebPage stay as Rank Math outputs them); otherwise the plugin
 * prints its own JSON-LD.
 *
 * @package DV_Converters
 */

defined( 'ABSPATH' ) || exit;

class DV_Schema {

	public static function init() {
		add_filter( 'rank_math/json_ld', array( __CLASS__, 'rank_math' ), 99, 2 );
		add_action( 'wp_head', array( __CLASS__, 'fallback' ), 30 );
	}

	/** @return array<int,array> Schema nodes for the current page. */
	public static function nodes() {
		if ( ! DV_Context::$active ) {
			return array();
		}
		$url   = get_permalink();
		$nodes = array();
		$c     = DV_Context::$converter;

		if ( $c && $c['pair'] ) {
			$ans  = DV_Render::answer( $c );
			$desc = trim( html_entity_decode( wp_strip_all_tags( $ans['main'] . '. ' . $ans['note'] ), ENT_QUOTES, 'UTF-8' ) );
			$app  = array(
				'@type'               => 'WebApplication',
				'@id'                 => $url . '#app',
				'name'                => $c['pair']['app_name'],
				'url'                 => $url,
				'description'         => $desc,
				'applicationCategory' => 'UtilitiesApplication',
				'operatingSystem'     => 'Any',
				'browserRequirements' => 'Requires JavaScript for live conversion',
				'isAccessibleForFree' => true,
				'offers'              => array(
					'@type'         => 'Offer',
					'price'         => '0',
					'priceCurrency' => 'USD',
				),
				'publisher'           => array( '@id' => home_url( '/#organization' ) ),
			);
			$author_id = (int) get_post_field( 'post_author', get_queried_object_id() );
			if ( $author_id ) {
				$app['creator'] = array(
					'@type' => 'Person',
					'name'  => get_the_author_meta( 'display_name', $author_id ),
					'url'   => get_author_posts_url( $author_id ),
				);
			}
			$nodes['dvWebApplication'] = $app;

			$trail = DV_Render::trail();
			if ( $trail ) {
				$list = array();
				foreach ( $trail as $i => $it ) {
					$list[] = array(
						'@type'    => 'ListItem',
						'position' => $i + 1,
						'name'     => $it[0],
						'item'     => $it[1],
					);
				}
				$nodes['BreadcrumbList'] = array(
					'@type'           => 'BreadcrumbList',
					'@id'             => $url . '#breadcrumb',
					'itemListElement' => $list,
				);
			}
		}

		if ( DV_Context::$faq ) {
			$q = array();
			foreach ( DV_Context::$faq as $item ) {
				$q[] = array(
					'@type'          => 'Question',
					'name'           => $item[0],
					'acceptedAnswer' => array(
						'@type' => 'Answer',
						'text'  => $item[1],
					),
				);
			}
			$nodes['dvFAQPage'] = array(
				'@type'      => 'FAQPage',
				'@id'        => $url . '#faq',
				'mainEntity' => $q,
			);
		}
		return apply_filters( 'dv_converters_schema_nodes', $nodes );
	}

	/** Merge into Rank Math's graph, replacing its breadcrumb node. */
	public static function rank_math( $data, $jsonld = null ) {
		$nodes = self::nodes();
		if ( ! $nodes ) {
			return $data;
		}
		// Tool pages are not articles: drop Rank Math's default Article node.
		if ( apply_filters( 'dv_converters_drop_article_schema', true ) ) {
			foreach ( $data as $k => $node ) {
				if ( is_array( $node ) && isset( $node['@type'] ) && 'Article' === $node['@type'] ) {
					unset( $data[ $k ] );
				}
			}
		}
		if ( isset( $nodes['BreadcrumbList'] ) ) {
			foreach ( $data as $k => $node ) {
				if ( is_array( $node ) && isset( $node['@type'] ) && 'BreadcrumbList' === $node['@type'] ) {
					unset( $data[ $k ] );
				}
			}
		}
		foreach ( $nodes as $k => $node ) {
			$data[ $k ] = $node;
		}
		return $data;
	}

	public static function fallback() {
		if ( defined( 'RANK_MATH_VERSION' ) ) {
			return;
		}
		$nodes = self::nodes();
		if ( ! $nodes ) {
			return;
		}
		$graph = array(
			'@context' => 'https://schema.org',
			'@graph'   => array_values( $nodes ),
		);
		echo '<script type="application/ld+json">' . wp_json_encode( $graph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
	}
}
