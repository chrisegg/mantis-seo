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
	 * Term meta: when set to "1", exclude from sitemap.
	 */
	const NOINDEX_META_KEY = '_mantis_robots_noindex';

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
	 * Meta query: include terms that are not explicitly noindex.
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
	 * Count non-empty terms eligible for the sitemap.
	 *
	 * @return int
	 */
	private function count_indexable_terms() {
		$count = wp_count_terms(
			array(
				'taxonomy'   => $this->taxonomy,
				'hide_empty' => true,
				'meta_query' => $this->noindex_meta_query(),
			)
		);
		if ( is_wp_error( $count ) ) {
			return 0;
		}
		return (int) $count;
	}

	/**
	 * Latest GMT modified date among posts in this taxonomy (proxy for term sitemap freshness).
	 *
	 * @return string ISO 8601 or empty.
	 */
	private function get_taxonomy_latest_post_modified_iso() {
		global $wpdb;
		$max = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MAX(p.post_modified_gmt) FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
				INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
				WHERE tt.taxonomy = %s AND p.post_status = 'publish'",
				$this->taxonomy
			)
		);
		if ( ! $max || '0000-00-00 00:00:00' === $max ) {
			return '';
		}
		return get_date_from_gmt( $max, 'c' );
	}

	/**
	 * Latest GMT modified for posts assigned to a term (term URL lastmod).
	 *
	 * @param WP_Term $term Term.
	 * @return string ISO 8601 or empty.
	 */
	private function get_term_archive_lastmod_iso( $term ) {
		global $wpdb;
		$max = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MAX(p.post_modified_gmt) FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
				WHERE tr.term_taxonomy_id = %d AND p.post_status = 'publish'",
				$term->term_taxonomy_id
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
		$total = $this->count_indexable_terms();
		if ( $total <= 0 ) {
			return array();
		}

		$links = array();
		$pages = (int) ceil( $total / $max_entries );
		$slug  = $this->taxonomy === 'category' ? 'categories' : ( $this->taxonomy === 'post_tag' ? 'tags' : $this->taxonomy );

		$lastmod = $this->get_taxonomy_latest_post_modified_iso();
		if ( '' === $lastmod ) {
			$lastmod = current_time( 'c' );
		}

		for ( $i = 1; $i <= $pages; $i++ ) {
			$url = $pages > 1
				? home_url( '/sitemap_' . $slug . '_' . $i . '.xml' )
				: home_url( '/sitemap_' . $slug . '.xml' );
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

		$terms = get_terms(
			array(
				'taxonomy'   => $this->taxonomy,
				'hide_empty' => true,
				'number'     => $max_entries,
				'offset'     => ( $current_page - 1 ) * $max_entries,
				'orderby'    => 'count',
				'order'      => 'DESC',
				'meta_query' => $this->noindex_meta_query(),
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
			$lastmod = $this->get_term_archive_lastmod_iso( $term );
			if ( '' === $lastmod ) {
				$lastmod = current_time( 'c' );
			}
			$links[] = array(
				'loc'        => $url,
				'lastmod'    => $lastmod,
				'changefreq' => 'weekly',
				'priority'   => '0.4',
			);
		}

		return $links;
	}
}
