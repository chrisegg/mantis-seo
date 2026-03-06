/**
 * Mantis SEO – Slide-out panel for post meta settings.
 *
 * @package Mantis_SEO
 */

(function () {
	'use strict';

	const SELECTORS = {
		trigger: '.mantis-seo-open-panel',
		slideout: '#mantis-seo-slideout',
		closeBtn: '.mantis-seo-slideout-close',
		pinToggle: '.mantis-seo-pin-toggle',
		tab: '.mantis-seo-tab',
		tabPanel: '.mantis-seo-tab-panel',
	};

	const CLASSES = {
		open: 'mantis-seo-slideout-open',
		pinned: 'mantis-seo-slideout-pinned',
	};

	const STORAGE_KEY = 'mantis_seo_panel_pinned';

	function init() {
		const slideout = document.querySelector(SELECTORS.slideout);
		if (!slideout) return;

		const trigger = document.querySelector(SELECTORS.trigger);
		const closeBtn = slideout.querySelector(SELECTORS.closeBtn);
		const pinToggle = slideout.querySelector(SELECTORS.pinToggle);

		if (trigger) {
			trigger.addEventListener('click', openPanel);
		}

		if (closeBtn) {
			closeBtn.addEventListener('click', closePanel);
		}

		slideout.querySelectorAll(SELECTORS.tab).forEach(function (tabEl) {
			tabEl.addEventListener('click', function () {
				switchTab(slideout, tabEl.getAttribute('data-tab'));
			});
		});

		if (pinToggle) {
			pinToggle.addEventListener('click', togglePin);
			// Restore pinned state from sessionStorage.
			const wasPinned = sessionStorage.getItem(STORAGE_KEY) === '1';
			if (wasPinned) {
				slideout.classList.add(CLASSES.pinned);
				pinToggle.setAttribute('aria-pressed', 'true');
				pinToggle.title = 'Unpin panel';
				slideout.classList.add(CLASSES.open);
			}
		}

		// Close on Escape key (when not pinned).
		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape' && slideout.classList.contains(CLASSES.open) && !slideout.classList.contains(CLASSES.pinned)) {
				closePanel();
			}
		});
	}

	function switchTab(slideout, tabId) {
		slideout.querySelectorAll(SELECTORS.tab).forEach(function (t) {
			t.classList.toggle('mantis-seo-tab-active', t.getAttribute('data-tab') === tabId);
			t.setAttribute('aria-selected', t.getAttribute('data-tab') === tabId ? 'true' : 'false');
		});
		slideout.querySelectorAll(SELECTORS.tabPanel).forEach(function (p) {
			const isActive = p.id === 'mantis-seo-tab-' + tabId;
			p.classList.toggle('mantis-seo-tab-panel-active', isActive);
			p.setAttribute('aria-hidden', isActive ? 'false' : 'true');
		});
	}

	function openPanel() {
		const slideout = document.querySelector(SELECTORS.slideout);
		if (slideout) {
			slideout.classList.add(CLASSES.open);
			slideout.setAttribute('aria-hidden', 'false');
			// Focus first focusable in active tab for accessibility.
			const activePanel = slideout.querySelector(SELECTORS.tabPanel + '.mantis-seo-tab-panel-active');
			const firstInput = activePanel ? activePanel.querySelector('input:not([type="hidden"]), textarea') : null;
			if (firstInput) {
				setTimeout(function () {
					firstInput.focus();
				}, 100);
			}
		}
	}

	function closePanel() {
		const slideout = document.querySelector(SELECTORS.slideout);
		if (slideout) {
			slideout.classList.remove(CLASSES.open);
			slideout.setAttribute('aria-hidden', 'true');
		}
	}

	function togglePin() {
		const slideout = document.querySelector(SELECTORS.slideout);
		const pinToggle = slideout && slideout.querySelector(SELECTORS.pinToggle);
		if (!slideout || !pinToggle) return;

		const isPinned = slideout.classList.toggle(CLASSES.pinned);
		pinToggle.setAttribute('aria-pressed', isPinned ? 'true' : 'false');
		pinToggle.title = isPinned
			? 'Unpin panel'
			: 'Pin panel open';
		// Toggle tack icon (pinned = filled, unpinned = outline).
		const icon = pinToggle.querySelector('.dashicons');
		if (icon) {
			icon.classList.remove('dashicons-location', 'dashicons-location-alt');
			icon.classList.add(isPinned ? 'dashicons-location-alt' : 'dashicons-location');
		}
		sessionStorage.setItem(STORAGE_KEY, isPinned ? '1' : '0');
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
