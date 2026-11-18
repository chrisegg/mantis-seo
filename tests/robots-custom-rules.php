<?php
/**
 * Custom robots.txt rules: keep valid directives and place them in the right groups.
 *
 * Run: php tests/robots-custom-rules.php
 *
 * @package Mantis_SEO
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

$dir = sys_get_temp_dir() . '/mantis-seo-robots-' . getmypid();
if ( ! is_dir( $dir ) && ! mkdir( $dir ) ) {
	fwrite( STDERR, "FAIL could not create temp dir\n" );
	exit( 1 );
}
define( 'ABSPATH', $dir . '/' );

require dirname( __DIR__ ) . '/includes/class-robots.php';

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

$raw = implode(
	"\n",
	array(
		'# keep crawlers out of affiliate redirects',
		'Disallow: /click/',
		'User-agent: Googlebot',
		'Sitemap: https://example.com/sitemap.xml',
		'Allow:/public/',
		'Crawl-delay: 10',
		'Crawl-delay: nope',
		'Disallow: /click/ /also/',
		'Allow: ',
		'Disallow:',
		'disallow: /click/',
		'',
	)
);

mantis_assert_same(
	"Disallow: /click/\nAllow: /public/\nCrawl-delay: 10\nDisallow:",
	Mantis_SEO_Robots::sanitize_rules( $raw ),
	'only Allow, Disallow, and Crawl-delay lines are kept'
);

$core = "User-agent: *\nDisallow: /wp-admin/\nAllow: /wp-admin/admin-ajax.php\n";
mantis_assert_same( $core, Mantis_SEO_Robots::insert_into_star_group( $core, '' ), 'empty rules leave robots.txt unchanged' );
mantis_assert_same( $core, Mantis_SEO_Robots::insert_into_star_group( $core, "User-agent: *\nSitemap: https://example.com/x\n" ), 'rejected lines leave robots.txt unchanged' );

$with_rules = Mantis_SEO_Robots::insert_into_star_group( $core, "Disallow: /click/\n" );
$with_sitemap = rtrim( $with_rules ) . "\n\nSitemap: https://example.com/sitemap_index.xml\n\n# Mantis SEO — AI crawler policies\nUser-agent: OAI-SearchBot\nAllow: /\n";

$click = strpos( $with_sitemap, 'Disallow: /click/' );
$sitemap = strpos( $with_sitemap, 'Sitemap:' );
$agent = strpos( $with_sitemap, 'User-agent: OAI-SearchBot' );
mantis_assert_same( true, false !== $click && false !== $sitemap && $click < $sitemap, 'custom rule sits in the * group before the sitemap line' );
mantis_assert_same( true, false !== $agent && $sitemap < $agent, 'sitemap line stays ahead of AI crawler groups' );

$already = "User-agent: *\nDisallow: /wp-admin/\n\nSitemap: https://example.com/sitemap_index.xml\n";
$inserted = Mantis_SEO_Robots::insert_into_star_group( $already, 'Disallow: /click/' );
mantis_assert_same(
	"User-agent: *\nDisallow: /wp-admin/\nDisallow: /click/\n\nSitemap: https://example.com/sitemap_index.xml\n",
	$inserted,
	'rules are inserted before an existing sitemap line'
);

$group = Mantis_SEO_Robots::merge_group_lines( array( 'Allow: /' ), Mantis_SEO_Robots::lines( "Disallow: /click/\nAllow: /" ) );
mantis_assert_same(
	array( 'Allow: /', 'Disallow: /click/' ),
	$group,
	'AI groups repeat custom paths and do not duplicate Allow: /'
);

mantis_assert_same( false, Mantis_SEO_Robots::has_physical_file(), 'missing robots.txt is not treated as a physical file' );
file_put_contents( ABSPATH . 'robots.txt', "User-agent: *\n" );
mantis_assert_same( true, Mantis_SEO_Robots::has_physical_file(), 'a robots.txt in the WordPress directory is detected' );
unlink( ABSPATH . 'robots.txt' );
rmdir( $dir );

fwrite( STDOUT, "all passed\n" );
