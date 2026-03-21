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
	 * Transient key for cached regex redirect rows.
	 *
	 * @var string
	 */
	const TRANSIENT_REGEX_ROWS = 'mantis_seo_regex_redirects';

	/**
	 * PCRE delimiter for regex redirects (literal `~` in a pattern must be written as \~).
	 *
	 * @var string
	 */
	private static $regex_delimiter = '~';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'template_redirect', array( $this, 'maybe_redirect' ), 1 );
	}

	/**
	 * Bust cached regex redirect list.
	 */
	public static function bust_regex_cache() {
		delete_transient( self::TRANSIENT_REGEX_ROWS );
	}

	/**
	 * Wrap stored regex origin for preg_* (delimiter-safe).
	 *
	 * @param string $pattern Pattern from DB.
	 * @return string
	 */
	private static function regex_pcre( $pattern ) {
		$d = self::$regex_delimiter;
		return $d . str_replace( $d, '\\' . $d, $pattern ) . $d;
	}

	/**
	 * Whether a regex pattern compiles without PCRE errors.
	 *
	 * @param string $pattern Normalized origin (regex body).
	 * @return bool
	 */
	public static function is_valid_regex_pattern( $pattern ) {
		$pattern = (string) $pattern;
		if ( '' === $pattern ) {
			return false;
		}
		$wrapped = self::regex_pcre( $pattern );
		$result  = @preg_match( $wrapped, '/' );
		if ( false === $result ) {
			return false;
		}
		return PREG_NO_ERROR === preg_last_error();
	}

	/**
	 * Sanitize redirect / gone HTTP status.
	 *
	 * @param int $type Raw type.
	 * @return int
	 */
	public static function sanitize_redirect_type( $type ) {
		$type = (int) $type;
		$ok   = array( 301, 302, 303, 307, 308, 410, 451 );
		return in_array( $type, $ok, true ) ? $type : 301;
	}

	/**
	 * Regex redirect rows (transient-cached).
	 *
	 * @return array
	 */
	private static function get_regex_redirect_rows() {
		$cached = get_transient( self::TRANSIENT_REGEX_ROWS );
		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}
		global $wpdb;
		$table = self::get_table_name();
		$rows  = $wpdb->get_results( "SELECT * FROM $table WHERE format = 'regex' ORDER BY id ASC", ARRAY_A );
		$rows  = is_array( $rows ) ? $rows : array();
		set_transient( self::TRANSIENT_REGEX_ROWS, $rows, HOUR_IN_SECONDS );
		return $rows;
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
	 * Get redirects (optionally paginated).
	 *
	 * @param int|null $limit  Max rows, or null for all.
	 * @param int      $offset Offset for LIMIT.
	 * @return array
	 */
	public static function get_all( $limit = null, $offset = 0 ) {
		global $wpdb;
		$table = self::get_table_name();
		$sql   = "SELECT * FROM $table ORDER BY format ASC, origin ASC";
		if ( null !== $limit ) {
			$sql .= $wpdb->prepare( ' LIMIT %d OFFSET %d', max( 1, (int) $limit ), max( 0, (int) $offset ) );
		}
		$rows = $wpdb->get_results( $sql, ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Total redirect count.
	 *
	 * @return int
	 */
	public static function count_all() {
		global $wpdb;
		$table = self::get_table_name();
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table" );
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
		$type   = self::sanitize_redirect_type( $type );
		$format = 'regex' === $format ? 'regex' : 'plain';

		if ( empty( $origin ) || empty( $target ) ) {
			return false;
		}

		if ( 'regex' === $format && ! self::is_valid_regex_pattern( $origin ) ) {
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

		if ( $result ) {
			self::bust_regex_cache();
			return (int) $wpdb->insert_id;
		}
		return false;
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
		$type   = self::sanitize_redirect_type( $type );
		$format = 'regex' === $format ? 'regex' : 'plain';

		if ( empty( $origin ) || empty( $target ) ) {
			return false;
		}

		if ( 'regex' === $format && ! self::is_valid_regex_pattern( $origin ) ) {
			return false;
		}

		$ok = (bool) $wpdb->update(
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
		if ( $ok ) {
			self::bust_regex_cache();
		}
		return $ok;
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
		$ok    = (bool) $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );
		if ( $ok ) {
			self::bust_regex_cache();
		}
		return $ok;
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

		// Regex redirects (cached).
		$regex_redirects = self::get_regex_redirect_rows();
		if ( ! empty( $regex_redirects ) ) {
			foreach ( $regex_redirects as $redirect ) {
				$wrapped = self::regex_pcre( $redirect['origin'] );
				$matched = preg_match( $wrapped, $path );
				if ( false === $matched || PREG_NO_ERROR !== preg_last_error() ) {
					continue;
				}
				if ( 1 !== (int) $matched ) {
					continue;
				}
				$target = preg_replace( $wrapped, $redirect['target'], $path );
				if ( null === $target || false === $target || PREG_NO_ERROR !== preg_last_error() ) {
					continue;
				}
				if ( $target !== $path ) {
					$redirect['target'] = $target;
				}
				return $redirect;
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
		static $home = null;
		if ( null === $home ) {
			$home = home_url( '/' );
		}
		$path = trim( $path );
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
		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}

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
		$type   = self::sanitize_redirect_type( (int) $redirect['type'] );

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
