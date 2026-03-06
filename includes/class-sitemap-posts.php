<?php
/**
 * Post type sitemap provider.
 *
 * @package Mantis_SEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Mantis_SEO_Sitemap_Posts
 */
class Mantis_SEO_Sitemap_Posts implements Mantis_SEO_Sitemap_Provider {

	/**
	 * Post type.
	 *
	 * @var string
	 */
	private $post_type;

	/**
	 * Constructor.
	 *
	 * @param string $post_type Post type.
	 */
	public function __construct( $post_type = 'post' ) {
		$this->post_type = $post_type;
	}

	/**
	 * Check if provider handles type.
	 *
	 * @param string $type Type.
	 * @return bool
	 */
	public function handles_type( $type ) {
		return $type === $this->post_type . 's' || $type === $this->post_type;
	}

	/**
	 * Get index links.
	 *
	 * @param int $max_entries Max entries per sitemap.
	 * @return array
	 */
	public function get_index_links( $max_entries ) {
		$count = wp_count_posts( $this->post_type );
		$total = isset( $count->publish ) ? (int) $count->publish : 0;
		if ( $total <= 0 ) {
			return array();
		}

		$links   = array();
		$pages   = (int) ceil( $total / $max_entries );

		for ( $i = 1; $i <= $pages; $i++ ) {
			$url = $pages > 1
				? home_url( '/sitemap_' . $this->post_type . 's_' . $i . '.xml' )
				: home_url( '/sitemap_' . $this->post_type . 's.xml' );
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

		$args = array(
			'post_type'      => $this->post_type,
			'post_status'    => 'publish',
			'posts_per_page' => $max_entries,
			'paged'          => $current_page,
			'orderby'        => 'modified',
			'order'          => 'DESC',
			'no_found_rows'  => true,
		);

		$query = new WP_Query( $args );

		foreach ( $query->posts as $post ) {
			$links[] = array(
				'loc'        => get_permalink( $post->ID ),
				'lastmod'    => get_the_modified_date( 'c', $post ),
				'changefreq' => 'weekly',
				'priority'   => 'page' === $this->post_type ? '0.8' : '0.6',
			);
		}

		return $links;
	}
}
