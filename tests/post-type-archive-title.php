<?php
/**
 * Post type archive titles fall back when the setting is empty.
 *
 * Run: php tests/post-type-archive-title.php
 *
 * @package Mantis_SEO
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );

/**
 * Stand-in for WordPress translation. The real function is not loaded here.
 *
 * @param string $text   Text.
 * @param string $domain Unused.
 * @return string
 */
function __( $text, $domain = 'default' ) {
	unset( $domain );
	return $text;
}

require dirname( __DIR__ ) . '/includes/class-meta.php';

/**
 * @param mixed  $expected Expected value.
 * @param mixed  $actual   Actual value.
 * @param string $message  Label.
 */
function mantis_assert_same( $expected, $actual, $message ) {
	if ( $expected !== $actual ) {
		fwrite( STDERR, "FAIL {$message}\n expected: " . var_export( $expected, true ) . "\n actual:   " . var_export( $actual, true ) . "\n" );
		exit( 1 );
	}
	fwrite( STDOUT, "ok {$message}\n" );
}

$default = '%archive_title% %separator% %site_name%';
mantis_assert_same( $default, Mantis_SEO_Meta::default_archive_title_template(), 'default archive title is archive name, separator, site name' );
mantis_assert_same( $default, Mantis_SEO_Meta::archive_title_template( '' ), 'blank title uses the default' );
mantis_assert_same( $default, Mantis_SEO_Meta::archive_title_template( '   ' ), 'whitespace title uses the default' );
mantis_assert_same(
	'%pt_plural% %separator% %site_name%',
	Mantis_SEO_Meta::archive_title_template( '%pt_plural% %separator% %site_name%' ),
	'saved title is kept'
);

$labels = Mantis_SEO_Meta::get_archive_variable_dropdown_options();
mantis_assert_same( 'Archive Title', $labels['%archive_title%'], 'archive dropdown offers Archive Title' );
mantis_assert_same( 'Plural Label', $labels['%pt_plural%'], 'archive dropdown offers Plural Label' );
mantis_assert_same( true, isset( $labels['%site_name%'] ), 'archive dropdown still offers the shared variables' );

$shared = Mantis_SEO_Meta::get_variable_dropdown_options();
mantis_assert_same( false, isset( $shared['%archive_title%'] ), 'post and page dropdowns omit archive variables' );

fwrite( STDOUT, "all passed\n" );
