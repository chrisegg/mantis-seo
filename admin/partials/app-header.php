<?php
/**
 * App header partial – shared by settings and redirects pages.
 *
 * @package Mantis_SEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Inline markup avoids a second HTTP request to wp-content/plugins/…, which many hosts block (403).
$mantis_seo_logo_svg = '';
$logo_svg_path       = MANTIS_SEO_PATH . 'assets/images/mseo-logo.svg';
$logo_png_path       = MANTIS_SEO_PATH . 'assets/images/mantiseo-logo.png';

if ( is_readable( $logo_svg_path ) ) {
	$mantis_seo_logo_svg = (string) file_get_contents( $logo_svg_path );
	$stripped            = preg_replace( '/^\xEF\xBB\xBF/', '', $mantis_seo_logo_svg );
	$mantis_seo_logo_svg = is_string( $stripped ) ? $stripped : $mantis_seo_logo_svg;
	$with_class          = preg_replace( '/<svg\b/', '<svg class="mantis-seo-logo-img" role="img" aria-labelledby="mantis-seo-logo-title" ', $mantis_seo_logo_svg, 1 );
	$mantis_seo_logo_svg = is_string( $with_class ) ? $with_class : $mantis_seo_logo_svg;
	$logo_title          = '<title id="mantis-seo-logo-title">' . esc_html__( 'Mantis SEO', 'mantis-seo' ) . '</title>';
	$with_title          = preg_replace( '/^(<svg\b[^>]*>)/u', '$1' . $logo_title, $mantis_seo_logo_svg, 1 );
	$mantis_seo_logo_svg = is_string( $with_title ) ? $with_title : $mantis_seo_logo_svg;
}

if ( '' === $mantis_seo_logo_svg && is_readable( $logo_png_path ) ) {
	$mantis_seo_logo_svg = '<img class="mantis-seo-logo-img" src="' . esc_attr( 'data:image/png;base64,' . base64_encode( (string) file_get_contents( $logo_png_path ) ) ) . '" alt="' . esc_attr__( 'Mantis SEO', 'mantis-seo' ) . '" width="96" height="32" decoding="async" />';
}
?>
<header class="mantis-seo-app-header">
	<div class="mantis-seo-app-header-left">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=mantis-seo' ) ); ?>" class="mantis-seo-logo">
			<?php
			if ( '' !== $mantis_seo_logo_svg ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Trusted asset from plugin directory.
				echo $mantis_seo_logo_svg;
			} else {
				?>
				<img
					class="mantis-seo-logo-img"
					src="<?php echo esc_url( plugins_url( 'assets/images/mantiseo-logo.png', MANTIS_SEO_FILE ) ); ?>"
					alt="<?php echo esc_attr__( 'Mantis SEO', 'mantis-seo' ); ?>"
					width="96"
					height="32"
					decoding="async"
				/>
				<?php
			}
			?>
		</a>
	</div>
	<div class="mantis-seo-app-header-right">
		<button type="button" class="mantis-seo-theme-toggle" aria-pressed="false" aria-label="<?php esc_attr_e( 'Toggle dark mode', 'mantis-seo' ); ?>" title="<?php esc_attr_e( 'Dark / Light mode', 'mantis-seo' ); ?>">
			<span class="mantis-seo-theme-icon mantis-seo-theme-icon-light" aria-hidden="true">
				<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 16 16"><path d="M8 11a3 3 0 1 1 0-6 3 3 0 0 1 0 6zm0 1a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM8 0a.5.5 0 0 1 .5.5v2a.5.5 0 0 1-1 0v-2A.5.5 0 0 1 8 0zm0 13a.5.5 0 0 1 .5.5v2a.5.5 0 0 1-1 0v-2A.5.5 0 0 1 8 13zm8-5a.5.5 0 0 1-.5.5h-2a.5.5 0 0 1 0-1h2a.5.5 0 0 1 .5.5zM3 8a.5.5 0 0 1-.5.5h-2a.5.5 0 0 1 0-1h2A.5.5 0 0 1 3 8zm10.657-5.657a.5.5 0 0 1 0 .707l-1.414 1.415a.5.5 0 1 1-.707-.708l1.414-1.414a.5.5 0 0 1 .707 0zm-9.193 9.193a.5.5 0 0 1 0 .707L3.05 13.657a.5.5 0 0 1-.707-.707l1.414-1.414a.5.5 0 0 1 .707 0zm9.193 2.121a.5.5 0 0 1-.707 0l-1.414-1.414a.5.5 0 0 1 .707-.707l1.414 1.414a.5.5 0 0 1 0 .707zM4.464 4.465a.5.5 0 0 1-.707 0L2.343 3.05a.5.5 0 1 1 .707-.707l1.414 1.414a.5.5 0 0 1 0 .708z"/></svg>
			</span>
			<span class="mantis-seo-theme-icon mantis-seo-theme-icon-dark" aria-hidden="true">
				<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 16 16"><path d="M6 .278a.768.768 0 0 1 .08.858 7.208 7.208 0 0 0-.878 3.46c0 4.021 3.278 7.277 7.318 7.277.527 0 1.04-.055 1.533-.16a.787.787 0 0 1 .81.316.733.733 0 0 1-.031.893A8.349 8.349 0 0 1 8.344 16C3.734 16 0 12.286 0 7.71 0 4.266 2.114 1.312 5.124.06A.752.752 0 0 1 6 .278z"/></svg>
			</span>
		</button>
		<a href="https://github.com/chrisegg/mantis-seo#readme" target="_blank" rel="noopener noreferrer" class="mantis-seo-help-button" aria-label="<?php esc_attr_e( 'Help & documentation', 'mantis-seo' ); ?>">
			<?php esc_html_e( 'Help', 'mantis-seo' ); ?>
		</a>
	</div>
</header>
