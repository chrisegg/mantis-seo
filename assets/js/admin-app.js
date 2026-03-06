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
		const wrap = document.getElementById('mantis-redirect-form-wrap');
		const addTrigger = document.querySelector('.mantis-redirect-add-trigger');
		const formEl = document.querySelector('.mantis-redirect-form');
		const cancelBtn = document.querySelector('.mantis-redirect-form-cancel');

		if (addTrigger && formEl) {
			addTrigger.addEventListener('click', function () {
				if (wrap) wrap.classList.add('mantis-redirect-form-expanded');
				formEl.classList.remove('mantis-redirect-form-collapsed');
				addTrigger.style.display = 'none';
			});
		}

		if (cancelBtn && formEl) {
			cancelBtn.addEventListener('click', function () {
				if (wrap) wrap.classList.remove('mantis-redirect-form-expanded');
				formEl.classList.add('mantis-redirect-form-collapsed');
				const trigger = document.querySelector('.mantis-redirect-add-trigger');
				if (trigger) trigger.style.display = '';
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
