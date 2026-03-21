<?php
/**
 * Apply saved dark theme before deferred admin JS runs (avoids flash).
 *
 * @package Mantis_SEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<script>!function(){var e=document.getElementById("mantis-seo-app");e&&"dark"===localStorage.getItem("mantis_seo_theme")&&e.classList.add("mantis-seo-dark")}();</script>
