<?php
/**
 * Schema generation and JSON-LD output.
 *
 * @package Mantis_SEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Mantis_SEO_Schema
 */
class Mantis_SEO_Schema {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp_head', array( $this, 'output_schema' ), 2 );
	}

	/**
	 * Output schema JSON-LD.
	 */
	public function output_schema() {
		$graph = $this->generate_graph();
		if ( empty( $graph ) ) {
			return;
		}

		$json = wp_json_encode(
			array(
				'@context' => 'https://schema.org',
				'@graph'   => $graph,
			),
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
		);

		if ( $json ) {
			echo "\n<!-- Mantis SEO Schema -->\n";
			echo '<script type="application/ld+json">' . $json . '</script>' . "\n";
		}
	}

	/**
	 * Generate schema graph.
	 *
	 * @return array
	 */
	public function generate_graph() {
		$graph   = array();
		$settings = Mantis_SEO_Main::get_settings();

		// Organization (if configured).
		if ( ! empty( $settings['schema_organization_name'] ) ) {
			$org = array(
				'@type' => 'Organization',
				'name'  => $settings['schema_organization_name'],
			);
			if ( ! empty( $settings['schema_organization_url'] ) ) {
				$org['url'] = $settings['schema_organization_url'];
			}
			if ( ! empty( $settings['schema_organization_logo'] ) ) {
				$org['logo'] = $settings['schema_organization_logo'];
			}
			if ( ! empty( $settings['schema_same_as'] ) && is_array( $settings['schema_same_as'] ) ) {
				$org['sameAs'] = array_values( array_filter( $settings['schema_same_as'] ) );
			}
			$graph[] = $org;
		}

		// WebSite.
		$website = array(
			'@type'          => 'WebSite',
			'name'           => get_bloginfo( 'name' ),
			'url'            => home_url( '/' ),
			'potentialAction' => array(
				'@type'       => 'SearchAction',
				'target'      => array(
					'@type'       => 'EntryPoint',
					'urlTemplate' => home_url( '/?s={search_term_string}' ),
				),
				'query-input' => 'required name=search_term_string',
			),
		);
		$graph[] = $website;

		if ( is_singular() ) {
			$page_schema = $this->get_singular_schema();
			if ( ! empty( $page_schema ) ) {
				$graph = array_merge( $graph, $page_schema );
			}
		}

		return $graph;
	}

	/**
	 * Get schema for singular post/page.
	 *
	 * @return array
	 */
	private function get_singular_schema() {
		$schema = array();
		$post   = get_post( get_queried_object_id() );

		if ( ! $post ) {
			return $schema;
		}

		$url = get_permalink( $post->ID );

		if ( 'post' === $post->post_type ) {
			$article = array(
				'@type'            => 'Article',
				'headline'         => get_the_title( $post ),
				'url'              => $url,
				'datePublished'    => get_the_date( 'c', $post ),
				'dateModified'     => get_the_modified_date( 'c', $post ),
				'mainEntityOfPage' => array(
					'@type' => 'WebPage',
					'@id'   => $url,
				),
			);

			$author_id = (int) $post->post_author;
			if ( $author_id ) {
				$article['author'] = array(
					'@type' => 'Person',
					'name'  => get_the_author_meta( 'display_name', $author_id ),
				);
			}

			$thumb = get_the_post_thumbnail_url( $post->ID, 'full' );
			if ( $thumb ) {
				$article['image'] = $thumb;
			}

			$schema[] = $article;
		} else {
			$schema[] = array(
				'@type' => 'WebPage',
				'name'  => get_the_title( $post ),
				'url'   => $url,
			);
		}

		// Breadcrumb.
		$breadcrumb = $this->get_breadcrumb_schema( $post );
		if ( ! empty( $breadcrumb ) ) {
			$schema[] = $breadcrumb;
		}

		return $schema;
	}

	/**
	 * Get breadcrumb schema.
	 *
	 * @param WP_Post $post Post object.
	 * @return array|null
	 */
	private function get_breadcrumb_schema( $post ) {
		$items = array(
			array(
				'@type'    => 'ListItem',
				'position' => 1,
				'name'     => get_bloginfo( 'name' ),
				'item'     => home_url( '/' ),
			),
		);

		$position = 2;
		if ( 'page' === $post->post_type && $post->post_parent ) {
			$ancestors = get_post_ancestors( $post->ID );
			$ancestors = array_reverse( $ancestors );
			foreach ( $ancestors as $ancestor_id ) {
				$items[] = array(
					'@type'    => 'ListItem',
					'position' => $position++,
					'name'     => get_the_title( $ancestor_id ),
					'item'     => get_permalink( $ancestor_id ),
				);
			}
		}

		$items[] = array(
			'@type'    => 'ListItem',
			'position' => $position,
			'name'     => get_the_title( $post ),
			'item'     => get_permalink( $post->ID ),
		);

		return array(
			'@type'           => 'BreadcrumbList',
			'itemListElement' => $items,
		);
	}
}
