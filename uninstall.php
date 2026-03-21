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
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- uninstall: table name from $wpdb->prefix only.
$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );

$like = $wpdb->esc_like( '_mantis_' ) . '%';
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $wpdb->postmeta is a known table name.
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s", $like ) );
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $wpdb->termmeta is a known table name.
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->termmeta} WHERE meta_key LIKE %s", $like ) );

delete_option( 'mantis_settings' );
delete_option( 'mantis_migrated_from_yoast' );
delete_option( 'mantis_redirect_notice' );
