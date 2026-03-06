<?php
/**
 * Plugin Name: Mantis SEO
 * Plugin URI: https://github.com/mantis-seo/mantis-seo
 * Description: A lightweight, powerful SEO plugin for WordPress. Metadata, redirects, schema, and sitemaps—all in one, no freemium.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Mantis SEO
 * Author URI: https://github.com/mantis-seo
 * License: GPL v3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: mantis-seo
 * Domain Path: /languages
 *
 * @package Mantis_SEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MANTIS_SEO_VERSION', '1.0.0' );
define( 'MANTIS_SEO_FILE', __FILE__ );
define( 'MANTIS_SEO_PATH', plugin_dir_path( __FILE__ ) );
define( 'MANTIS_SEO_URL', plugin_dir_url( __FILE__ ) );
define( 'MANTIS_SEO_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Load text domain.
 */
function mantis_seo_load_textdomain() {
	load_plugin_textdomain( 'mantis-seo', false, dirname( MANTIS_SEO_BASENAME ) . '/languages' );
}
add_action( 'init', 'mantis_seo_load_textdomain' );

/**
 * Main plugin bootstrap.
 */
function mantis_seo_load() {
	require_once MANTIS_SEO_PATH . 'includes/class-mantis-seo.php';
	Mantis_SEO_Main::instance();
}
add_action( 'plugins_loaded', 'mantis_seo_load' );

/**
 * Activation hook.
 */
function mantis_seo_activate() {
	require_once MANTIS_SEO_PATH . 'includes/class-mantis-seo.php';
	Mantis_SEO_Main::activate();
}
register_activation_hook( __FILE__, 'mantis_seo_activate' );

/**
 * Deactivation hook.
 */
function mantis_seo_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'mantis_seo_deactivate' );
