/**
 * Mantis SEO – App header: dark/light mode toggle.
 *
 * @package Mantis_SEO
 */

(function () {
	'use strict';

	const STORAGE_KEY = 'mantis_seo_theme';
	const DARK_CLASS = 'mantis-seo-dark';

	function init() {
		const toggle = document.querySelector('.mantis-seo-theme-toggle');
		const app = document.getElementById('mantis-seo-app') || document.querySelector('.mantis-seo-app');
		if (!toggle || !app) return;

		const saved = localStorage.getItem(STORAGE_KEY);
		if (saved === 'dark') {
			app.classList.add(DARK_CLASS);
			toggle.setAttribute('aria-pressed', 'true');
		}

		toggle.addEventListener('click', function () {
			const isDark = app.classList.toggle(DARK_CLASS);
			toggle.setAttribute('aria-pressed', isDark ? 'true' : 'false');
			localStorage.setItem(STORAGE_KEY, isDark ? 'dark' : 'light');
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
