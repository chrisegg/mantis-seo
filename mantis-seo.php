<?php
/**
 * Plugin Name: Mantis SEO
 * Plugin URI: https://mantiseo.com
 * Description: Lightweight SEO for WordPress — titles & meta, redirects, JSON-LD schema, XML sitemaps, social tags, and AI/GEO discovery (llms.txt) in one plugin. No upsells, no feature gating.
 * Version: 1.0.3
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: WP Mantis
 * Author URI: https://mantiseo.com
 * License: GPL v3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: mantis-seo
 * Domain Path: /languages
 * Update URI: https://github.com/chrisegg/mantis-seo
 *
 * @package Mantis_SEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MANTIS_SEO_VERSION', '1.0.3' );
define( 'MANTIS_SEO_FILE', __FILE__ );
define( 'MANTIS_SEO_PATH', plugin_dir_path( __FILE__ ) );
define( 'MANTIS_SEO_URL', plugin_dir_url( __FILE__ ) );
define( 'MANTIS_SEO_BASENAME', plugin_basename( __FILE__ ) );
define( 'MANTIS_SEO_GITHUB_REPO', 'chrisegg/mantis-seo' );
define( 'MANTIS_SEO_RELEASE_ZIP', 'mantis-seo.zip' );

/**
 * Load text domain.
 */
function mantis_seo_load_textdomain() {
	load_plugin_textdomain( 'mantis-seo', false, dirname( MANTIS_SEO_BASENAME ) . '/languages' );
}
add_action( 'init', 'mantis_seo_load_textdomain' );

require_once MANTIS_SEO_PATH . 'includes/class-mantis-seo-updater.php';
add_action( 'plugins_loaded', array( 'Mantis_SEO_Updater', 'init' ), 5 );

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
