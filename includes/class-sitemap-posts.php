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
	 * Post meta: when set to "1", exclude from sitemap (matches front-end noindex).
	 */
	const NOINDEX_META_KEY = '_mantis_robots_noindex';

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
	 * Meta query: include posts that are not explicitly noindex.
	 *
	 * @return array
	 */
	private function noindex_meta_query() {
		return array(
			'relation' => 'OR',
			array(
				'key'     => self::NOINDEX_META_KEY,
				'compare' => 'NOT EXISTS',
			),
			array(
				'key'     => self::NOINDEX_META_KEY,
				'value'   => '1',
				'compare' => '!=',
			),
		);
	}

	/**
	 * Count published posts eligible for the sitemap.
	 *
	 * @return int
	 */
	private function count_indexable_posts() {
		$q = new WP_Query(
			array(
				'post_type'              => $this->post_type,
				'post_status'            => 'publish',
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'no_found_rows'          => false,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_query'             => $this->noindex_meta_query(),
			)
		);
		return (int) $q->found_posts;
	}

	/**
	 * Latest GMT modified date among indexable posts (for sitemap index lastmod).
	 *
	 * @return string ISO 8601 or empty.
	 */
	private function get_latest_modified_iso_for_indexable() {
		global $wpdb;
		$key = self::NOINDEX_META_KEY;
		$max = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MAX(p.post_modified_gmt) FROM {$wpdb->posts} p
				WHERE p.post_type = %s AND p.post_status = 'publish'
				AND NOT EXISTS (
					SELECT 1 FROM {$wpdb->postmeta} pm
					WHERE pm.post_id = p.ID AND pm.meta_key = %s AND pm.meta_value = '1'
				)",
				$this->post_type,
				$key
			)
		);
		if ( ! $max || '0000-00-00 00:00:00' === $max ) {
			return '';
		}
		return get_date_from_gmt( $max, 'c' );
	}

	/**
	 * Get index links.
	 *
	 * @param int $max_entries Max entries per sitemap.
	 * @return array
	 */
	public function get_index_links( $max_entries ) {
		$total = $this->count_indexable_posts();
		if ( $total <= 0 ) {
			return array();
		}

		$links   = array();
		$pages   = (int) ceil( $total / $max_entries );
		$lastmod = $this->get_latest_modified_iso_for_indexable();
		if ( '' === $lastmod ) {
			$lastmod = current_time( 'c' );
		}

		for ( $i = 1; $i <= $pages; $i++ ) {
			$url = $pages > 1
				? home_url( '/sitemap_' . $this->post_type . 's_' . $i . '.xml' )
				: home_url( '/sitemap_' . $this->post_type . 's.xml' );
			$links[] = array(
				'loc'     => $url,
				'lastmod' => $lastmod,
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
			'meta_query'     => $this->noindex_meta_query(),
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
