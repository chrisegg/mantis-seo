<?php
/**
 * Sitemap provider interface.
 *
 * @package Mantis_SEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface Mantis_SEO_Sitemap_Provider
 */
interface Mantis_SEO_Sitemap_Provider {

	/**
	 * Check if provider supports given item type.
	 *
	 * @param string $type Type string.
	 * @return bool
	 */
	public function handles_type( $type );

	/**
	 * Get set of sitemap index link data.
	 *
	 * @param int $max_entries Entries per sitemap.
	 * @return array
	 */
	public function get_index_links( $max_entries );

	/**
	 * Get set of sitemap link data.
	 *
	 * @param string $type        Sitemap type.
	 * @param int    $max_entries Entries per sitemap.
	 * @param int    $current_page Current page.
	 * @return array
	 */
	public function get_sitemap_links( $type, $max_entries, $current_page );
}
