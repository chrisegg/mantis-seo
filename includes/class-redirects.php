<?php
/**
 * Redirect storage, matching, and execution.
 *
 * @package Mantis_SEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Mantis_SEO_Redirects
 */
class Mantis_SEO_Redirects {

	/**
	 * Table name.
	 *
	 * @var string
	 */
	const TABLE_NAME = 'mantis_redirects';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'template_redirect', array( $this, 'maybe_redirect' ), 1 );
	}

	/**
	 * Create the redirects table.
	 */
	public static function create_table() {
		global $wpdb;
		$table_name     = $wpdb->prefix . self::TABLE_NAME;
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			origin varchar(500) NOT NULL,
			target varchar(500) NOT NULL,
			type smallint(3) NOT NULL DEFAULT 301,
			format varchar(10) NOT NULL DEFAULT 'plain',
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY origin (origin(191))
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Get table name.
	 *
	 * @return string
	 */
	public static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_NAME;
	}

	/**
	 * Get all redirects.
	 *
	 * @return array
	 */
	public static function get_all() {
		global $wpdb;
		$table = self::get_table_name();
		$rows  = $wpdb->get_results( "SELECT * FROM $table ORDER BY format ASC, origin ASC", ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Get redirect by ID.
	 *
	 * @param int $id Redirect ID.
	 * @return array|null
	 */
	public static function get_by_id( $id ) {
		global $wpdb;
		$table = self::get_table_name();
		$id    = (int) $id;
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ), ARRAY_A );
	}

	/**
	 * Add redirect.
	 *
	 * @param string $origin  Origin path.
	 * @param string $target  Target URL.
	 * @param int    $type    Redirect type (301, 302, etc).
	 * @param string $format  Format: plain or regex.
	 * @return int|false Insert ID or false.
	 */
	public static function add( $origin, $target, $type = 301, $format = 'plain' ) {
		global $wpdb;
		$table = self::get_table_name();

		$origin = self::normalize_origin( $origin );
		$target = ( 0 === strpos( $target, 'http' ) || 0 === strpos( $target, '//' ) ) ? esc_url_raw( $target ) : sanitize_text_field( $target );
		$type   = (int) $type;
		$format = 'regex' === $format ? 'regex' : 'plain';

		if ( empty( $origin ) || empty( $target ) ) {
			return false;
		}

		$result = $wpdb->insert(
			$table,
			array(
				'origin' => $origin,
				'target' => $target,
				'type'   => $type,
				'format' => $format,
			),
			array( '%s', '%s', '%d', '%s' )
		);

		return $result ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Update redirect.
	 *
	 * @param int    $id     Redirect ID.
	 * @param string $origin Origin path.
	 * @param string $target Target URL.
	 * @param int    $type   Redirect type.
	 * @param string $format Format.
	 * @return bool
	 */
	public static function update( $id, $origin, $target, $type = 301, $format = 'plain' ) {
		global $wpdb;
		$table = self::get_table_name();
		$id    = (int) $id;

		$origin = self::normalize_origin( $origin );
		$target = ( 0 === strpos( $target, 'http' ) || 0 === strpos( $target, '//' ) ) ? esc_url_raw( $target ) : sanitize_text_field( $target );
		$type   = (int) $type;
		$format = 'regex' === $format ? 'regex' : 'plain';

		if ( empty( $origin ) || empty( $target ) ) {
			return false;
		}

		return (bool) $wpdb->update(
			$table,
			array(
				'origin' => $origin,
				'target' => $target,
				'type'   => $type,
				'format' => $format,
			),
			array( 'id' => $id ),
			array( '%s', '%s', '%d', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Delete redirect.
	 *
	 * @param int $id Redirect ID.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;
		$table = self::get_table_name();
		$id    = (int) $id;
		return (bool) $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );
	}

	/**
	 * Find matching redirect for request path.
	 *
	 * @param string $path Request path (e.g. /old-page/).
	 * @return array|null Redirect row or null.
	 */
	public static function get_match( $path ) {
		global $wpdb;
		$table = self::get_table_name();
		$path  = self::normalize_origin( $path );

		// Plain redirects first (exact match).
		$plain = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE format = 'plain' AND origin = %s LIMIT 1",
				$path
			),
			ARRAY_A
		);

		if ( $plain ) {
			return $plain;
		}

		// Regex redirects.
		$regex_redirects = $wpdb->get_results( "SELECT * FROM $table WHERE format = 'regex' ORDER BY id ASC", ARRAY_A );
		if ( ! empty( $regex_redirects ) ) {
			foreach ( $regex_redirects as $redirect ) {
				if ( @preg_match( '#' . $redirect['origin'] . '#', $path ) ) {
					$target = preg_replace( '#' . $redirect['origin'] . '#', $redirect['target'], $path );
					if ( $target !== $path ) {
						$redirect['target'] = $target;
					}
					return $redirect;
				}
			}
		}

		return null;
	}

	/**
	 * Normalize origin path.
	 *
	 * @param string $path Path.
	 * @return string
	 */
	public static function normalize_origin( $path ) {
		$path = trim( $path );
		$home = home_url( '/' );
		$path = str_replace( $home, '/', $path );
		if ( 0 !== strpos( $path, '/' ) ) {
			$path = '/' . $path;
		}
		return $path;
	}

	/**
	 * Maybe perform redirect.
	 */
	public function maybe_redirect() {
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$path        = wp_parse_url( $request_uri, PHP_URL_PATH );
		if ( empty( $path ) ) {
			$path = '/';
		}

		$redirect = self::get_match( $path );
		if ( ! $redirect ) {
			return;
		}

		$target = $redirect['target'];
		$type   = (int) $redirect['type'];

		// Ensure target is absolute.
		if ( 0 !== strpos( $target, 'http' ) ) {
			$target = home_url( $target );
		}

		if ( in_array( $type, array( 410, 451 ), true ) ) {
			status_header( $type );
			nocache_headers();
			exit;
		}

		wp_redirect( $target, $type, 'Mantis SEO' );
		exit;
	}
}
