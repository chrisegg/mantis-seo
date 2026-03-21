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

// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name uses trusted $wpdb->prefix.
$table_name = $wpdb->prefix . 'mantis_redirects';
$wpdb->query( "DROP TABLE IF EXISTS $table_name" );

delete_option( 'mantis_settings' );
delete_option( 'mantis_migrated_from_yoast' );
delete_option( 'mantis_redirect_notice' );

// Clean up post and term meta.
// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- LIKE pattern is safe (hardcoded prefix).
$wpdb->query( "DELETE FROM $wpdb->postmeta WHERE meta_key LIKE '_mantis\_%'" );
// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- LIKE pattern is safe (hardcoded prefix).
$wpdb->query( "DELETE FROM $wpdb->termmeta WHERE meta_key LIKE '_mantis\_%'" );

// Clean up transients.
delete_transient( 'mantis_seo_github_release' );
delete_transient( 'mantis_import_error' );
delete_transient( 'mantis_migrate_result' );
