/**
 * Mantis SEO – App header: dark/light mode toggle, redirect form collapse.
 *
 * @package Mantis_SEO
 */

(function () {
	'use strict';

	const STORAGE_KEY = 'mantis_seo_theme';
	const DARK_CLASS = 'mantis-seo-dark';

	function initThemeToggle() {
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

	function initRedirectsPage() {
		const accordion = document.getElementById('mantis-redirect-accordion');
		const header = document.querySelector('.mantis-redirect-accordion-header');
		const closeBtn = document.querySelector('.mantis-redirect-accordion-close');

		if (accordion && header) {
			header.addEventListener('click', function () {
				const expanded = accordion.classList.toggle('mantis-redirect-accordion-expanded');
				header.setAttribute('aria-expanded', expanded ? 'true' : 'false');
			});
		}

		if (closeBtn && accordion && header) {
			closeBtn.addEventListener('click', function () {
				accordion.classList.remove('mantis-redirect-accordion-expanded');
				header.setAttribute('aria-expanded', 'false');
			});
		}
	}

	function init() {
		initThemeToggle();
		initRedirectsPage();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
