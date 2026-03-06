<?php
/**
 * Taxonomy sitemap provider.
 *
 * @package Mantis_SEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Mantis_SEO_Sitemap_Terms
 */
class Mantis_SEO_Sitemap_Terms implements Mantis_SEO_Sitemap_Provider {

	/**
	 * Taxonomy.
	 *
	 * @var string
	 */
	private $taxonomy;

	/**
	 * Constructor.
	 *
	 * @param string $taxonomy Taxonomy name.
	 */
	public function __construct( $taxonomy = 'category' ) {
		$this->taxonomy = $taxonomy;
	}

	/**
	 * Check if provider handles type.
	 *
	 * @param string $type Type.
	 * @return bool
	 */
	public function handles_type( $type ) {
		$expected = $this->taxonomy === 'category' ? 'categories' : ( $this->taxonomy === 'post_tag' ? 'tags' : $this->taxonomy );
		return $type === $expected || $type === $this->taxonomy;
	}

	/**
	 * Get index links.
	 *
	 * @param int $max_entries Max entries per sitemap.
	 * @return array
	 */
	public function get_index_links( $max_entries ) {
		$total = wp_count_terms( array( 'taxonomy' => $this->taxonomy, 'hide_empty' => true ) );
		if ( is_wp_error( $total ) ) {
			$total = 0;
		}
		$total = (int) $total;
		if ( $total <= 0 ) {
			return array();
		}

		$links = array();
		$pages = (int) ceil( $total / $max_entries );
		$slug  = $this->taxonomy === 'category' ? 'categories' : ( $this->taxonomy === 'post_tag' ? 'tags' : $this->taxonomy );

		for ( $i = 1; $i <= $pages; $i++ ) {
			$url = $pages > 1
				? home_url( '/sitemap_' . $slug . '_' . $i . '.xml' )
				: home_url( '/sitemap_' . $slug . '.xml' );
			$links[] = array(
				'loc'     => $url,
				'lastmod' => current_time( 'c' ),
			);
		}

		return $links;
	}

	/**
	 * Get sitemap links.
	 *
	 * @param string $type        Type.
	 * @param int    $max_entries Max entries.
	 * @param int    $current_page Current page.
	 * @return array
	 */
	public function get_sitemap_links( $type, $max_entries, $current_page ) {
		$links = array();

		$terms = get_terms(
			array(
				'taxonomy'   => $this->taxonomy,
				'hide_empty' => true,
				'number'     => $max_entries,
				'offset'     => ( $current_page - 1 ) * $max_entries,
				'orderby'    => 'count',
				'order'      => 'DESC',
			)
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return $links;
		}

		foreach ( $terms as $term ) {
			$url = get_term_link( $term );
			if ( is_wp_error( $url ) ) {
				continue;
			}
			$links[] = array(
				'loc'        => $url,
				'lastmod'    => current_time( 'c' ),
				'changefreq' => 'weekly',
				'priority'   => '0.4',
			);
		}

		return $links;
	}
}
