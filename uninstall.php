<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package Mantis_SEO
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$table_name = $wpdb->prefix . 'mantis_redirects';
$wpdb->query( "DROP TABLE IF EXISTS $table_name" );

delete_option( 'mantis_settings' );
delete_option( 'mantis_migrated_from_yoast' );
delete_option( 'mantis_redirect_notice' );
