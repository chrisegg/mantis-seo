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
		backdrop: '.mantis-seo-slideout-backdrop',
		closeBtn: '.mantis-seo-slideout-close',
		pinToggle: '.mantis-seo-pin-toggle',
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
		const backdrop = slideout.querySelector(SELECTORS.backdrop);
		const closeBtn = slideout.querySelector(SELECTORS.closeBtn);
		const pinToggle = slideout.querySelector(SELECTORS.pinToggle);

		if (trigger) {
			trigger.addEventListener('click', openPanel);
		}

		if (backdrop) {
			backdrop.addEventListener('click', handleBackdropClick);
		}

		if (closeBtn) {
			closeBtn.addEventListener('click', closePanel);
		}

		if (pinToggle) {
			pinToggle.addEventListener('click', togglePin);
			// Restore pinned state from sessionStorage.
			const wasPinned = sessionStorage.getItem(STORAGE_KEY) === '1';
			if (wasPinned) {
				slideout.classList.add(CLASSES.pinned);
				pinToggle.setAttribute('aria-pressed', 'true');
				pinToggle.title = 'Unpin panel (click outside to close)';
				slideout.classList.add(CLASSES.open);
			}
		}

		// Close on Escape key.
		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape' && slideout.classList.contains(CLASSES.open)) {
				closePanel();
			}
		});
	}

	function openPanel() {
		const slideout = document.querySelector(SELECTORS.slideout);
		if (slideout) {
			slideout.classList.add(CLASSES.open);
			slideout.setAttribute('aria-hidden', 'false');
			// Focus first focusable in panel for accessibility.
			const firstInput = slideout.querySelector('input, textarea, button');
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

	function handleBackdropClick() {
		const slideout = document.querySelector(SELECTORS.slideout);
		if (!slideout || !slideout.classList.contains(CLASSES.open)) return;
		// Don't close if pinned.
		if (slideout.classList.contains(CLASSES.pinned)) return;
		closePanel();
	}

	function togglePin() {
		const slideout = document.querySelector(SELECTORS.slideout);
		const pinToggle = slideout && slideout.querySelector(SELECTORS.pinToggle);
		if (!slideout || !pinToggle) return;

		const isPinned = slideout.classList.toggle(CLASSES.pinned);
		pinToggle.setAttribute('aria-pressed', isPinned ? 'true' : 'false');
		pinToggle.title = isPinned
			? 'Unpin panel (click outside to close)'
			: 'Pin panel open';
		// Toggle lock/unlock icon.
		const icon = pinToggle.querySelector('.dashicons');
		if (icon) {
			icon.classList.remove('dashicons-lock', 'dashicons-unlock');
			icon.classList.add(isPinned ? 'dashicons-unlock' : 'dashicons-lock');
		}
		sessionStorage.setItem(STORAGE_KEY, isPinned ? '1' : '0');
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
