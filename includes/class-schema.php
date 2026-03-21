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

		// Avoid JSON_UNESCAPED_SLASHES: unescaped </script> in a string breaks out of the script tag.
		$json = wp_json_encode(
			array(
				'@context' => 'https://schema.org',
				'@graph'   => $graph,
			),
			JSON_UNESCAPED_UNICODE
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
		$graph     = array();
		$settings  = Mantis_SEO_Main::get_settings();
		$lang      = $this->get_in_language();
		$home_url  = trailingslashit( home_url( '/' ) );
		$website_id = $home_url . '#website';

		$organization_id = null;
		if ( ! empty( $settings['schema_organization_name'] ) ) {
			$organization_id = $this->get_organization_node_id( $settings );
			$graph[]         = $this->build_organization( $settings, $organization_id );
		}

		$graph[] = $this->build_website( $website_id, $home_url, $lang, $organization_id );

		if ( is_singular() ) {
			$post = get_post( get_queried_object_id() );
			if ( $post ) {
				$graph = array_merge( $graph, $this->build_singular_nodes( $post, $website_id, $lang, $organization_id ) );
			}
		}

		return $graph;
	}

	/**
	 * BCP 47 style language tag for schema.
	 *
	 * @return string
	 */
	private function get_in_language() {
		$lang = get_bloginfo( 'language' );
		if ( is_string( $lang ) && '' !== $lang ) {
			return $lang;
		}
		return str_replace( '_', '-', get_locale() );
	}

	/**
	 * Stable @id for Organization node.
	 *
	 * @param array $settings Plugin settings.
	 * @return string
	 */
	private function get_organization_node_id( $settings ) {
		$base = ! empty( $settings['schema_organization_url'] )
			? $settings['schema_organization_url']
			: home_url( '/' );
		return untrailingslashit( $base ) . '#organization';
	}

	/**
	 * Organization entity.
	 *
	 * @param array  $settings Plugin settings.
	 * @param string $node_id  @id value.
	 * @return array
	 */
	private function build_organization( $settings, $node_id ) {
		$org = array(
			'@type' => 'Organization',
			'@id'   => $node_id,
			'name'  => $settings['schema_organization_name'],
		);
		if ( ! empty( $settings['schema_organization_url'] ) ) {
			$org['url'] = $settings['schema_organization_url'];
		}
		if ( ! empty( $settings['schema_organization_logo'] ) ) {
			$org['logo'] = $settings['schema_organization_logo'];
		}
		$same_as = array_values(
			array_filter(
				array(
					$settings['schema_same_as_facebook'] ?? '',
					$settings['schema_same_as_instagram'] ?? '',
					$settings['schema_same_as_x'] ?? '',
					$settings['schema_same_as_linkedin'] ?? '',
				)
			)
		);
		if ( ! empty( $same_as ) ) {
			$org['sameAs'] = $same_as;
		}
		return $org;
	}

	/**
	 * WebSite entity with SearchAction (schema.org-recommended query-input shape).
	 *
	 * @param string      $website_id      @id for this site.
	 * @param string      $home_url        Site front URL (trailing slash).
	 * @param string      $in_language     BCP 47 tag.
	 * @param string|null $organization_id Optional Organization @id for publisher.
	 * @return array
	 */
	private function build_website( $website_id, $home_url, $in_language, $organization_id ) {
		$website = array(
			'@type'           => 'WebSite',
			'@id'             => $website_id,
			'url'             => $home_url,
			'name'            => get_bloginfo( 'name' ),
			'inLanguage'      => $in_language,
			'potentialAction' => array(
				array(
					'@type'       => 'SearchAction',
					'target'      => array(
						'@type'       => 'EntryPoint',
						'urlTemplate' => home_url( '/?s={search_term_string}' ),
					),
					'query-input' => array(
						'@type'         => 'PropertyValueSpecification',
						'valueRequired' => true,
						'valueName'     => 'search_term_string',
					),
				),
			),
		);

		$desc = get_bloginfo( 'description', 'display' );
		if ( is_string( $desc ) && '' !== trim( wp_strip_all_tags( $desc ) ) ) {
			$website['description'] = wp_strip_all_tags( $desc );
		}

		if ( $organization_id ) {
			$website['publisher'] = array( '@id' => $organization_id );
		}

		return $website;
	}

	/**
	 * WebPage (+ Article for posts) and BreadcrumbList with cross-references.
	 *
	 * @param WP_Post     $post            Queried post.
	 * @param string      $website_id      WebSite @id.
	 * @param string      $in_language   BCP 47 tag.
	 * @param string|null $organization_id Optional Organization @id.
	 * @return array List of graph nodes.
	 */
	private function build_singular_nodes( WP_Post $post, $website_id, $in_language, $organization_id ) {
		$url           = get_permalink( $post->ID );
		$breadcrumb_id = $url . '#breadcrumb';
		$page_id       = $url;

		$description = Mantis_SEO_Meta::resolve_post_meta_description( $post );
		$page_name   = Mantis_SEO_Meta::resolve_document_title_for_post( $post );

		$webpage = array(
			'@type'            => 'WebPage',
			'@id'              => $page_id,
			'url'              => $url,
			'name'             => $page_name,
			'isPartOf'         => array( '@id' => $website_id ),
			'datePublished'    => get_the_date( 'c', $post ),
			'dateModified'     => get_the_modified_date( 'c', $post ),
			'breadcrumb'       => array( '@id' => $breadcrumb_id ),
			'inLanguage'       => $in_language,
			'potentialAction'  => array(
				array(
					'@type'  => 'ReadAction',
					'target' => array( $url ),
				),
			),
		);

		if ( '' !== $description ) {
			$webpage['description'] = $description;
		}

		$nodes = array( $webpage );

		if ( 'post' === $post->post_type ) {
			$article_id = $url . '#article';
			$article    = array(
				'@type'            => 'Article',
				'@id'              => $article_id,
				'headline'         => get_the_title( $post ),
				'url'              => $url,
				'datePublished'    => get_the_date( 'c', $post ),
				'dateModified'     => get_the_modified_date( 'c', $post ),
				'mainEntityOfPage' => array( '@id' => $page_id ),
			);

			if ( '' !== $description ) {
				$article['description'] = $description;
			}

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

			if ( $organization_id ) {
				$article['publisher'] = array( '@id' => $organization_id );
			}

			$nodes[] = $article;
		}

		$nodes[] = $this->build_breadcrumb_list( $post, $breadcrumb_id );

		return $nodes;
	}

	/**
	 * BreadcrumbList with @id for WebPage.breadcrumb references.
	 *
	 * @param WP_Post $post          Post object.
	 * @param string  $breadcrumb_id Node @id.
	 * @return array
	 */
	private function build_breadcrumb_list( WP_Post $post, $breadcrumb_id ) {
		$items = array(
			array(
				'@type'    => 'ListItem',
				'position' => 1,
				'name'     => __( 'Home', 'mantis-seo' ),
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
			'@id'             => $breadcrumb_id,
			'itemListElement' => $items,
		);
	}
}
