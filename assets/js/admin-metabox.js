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
		resizeTab: '.mantis-seo-resize-tab',
		tab: '.mantis-seo-tab',
		tabPanel: '.mantis-seo-tab-panel',
	};

	const CLASSES = {
		open: 'mantis-seo-slideout-open',
		pinned: 'mantis-seo-slideout-pinned',
		wide: 'mantis-seo-slideout-wide',
	};

	const STORAGE_KEYS = {
		pinned: 'mantis_seo_panel_pinned',
		wide: 'mantis_seo_panel_wide',
	};

	function updatePreview() {
		const titleEl = document.getElementById('_mantis_title');
		const descEl = document.getElementById('_mantis_description');
		const postTitleEl = document.getElementById('title');
		const snippetTitle = document.getElementById('mantis-snippet-title');
		const snippetDesc = document.getElementById('mantis-snippet-description');
		const titleCount = document.getElementById('mantis-title-count');
		const descCount = document.getElementById('mantis-desc-count');

		if (!snippetTitle) return;

		let title = (titleEl && titleEl.value) ? titleEl.value.trim() : '';
		if (!title && postTitleEl) {
			title = postTitleEl.value.trim();
		}
		snippetTitle.textContent = title || '(No title)';

		const desc = (descEl && descEl.value) ? descEl.value.trim() : '';
		snippetDesc.textContent = desc || '';

		if (titleCount) titleCount.textContent = (titleEl ? titleEl.value.length : 0);
		if (descCount) descCount.textContent = (descEl ? descEl.value.length : 0);
	}

	function bindPreviewEvents() {
		const titleEl = document.getElementById('_mantis_title');
		const descEl = document.getElementById('_mantis_description');
		const postTitleEl = document.getElementById('title');
		const events = ['input', 'change', 'paste'];

		function bind(el, fn) {
			if (!el) return;
			events.forEach(function (ev) {
				el.addEventListener(ev, fn);
			});
		}

		bind(titleEl, updatePreview);
		bind(descEl, updatePreview);
		bind(postTitleEl, updatePreview);
	}

	function init() {
		const slideout = document.querySelector(SELECTORS.slideout);
		if (!slideout) return;

		updatePreview();
		bindPreviewEvents();

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
			const wasPinned = sessionStorage.getItem(STORAGE_KEYS.pinned) === '1';
			if (wasPinned) {
				slideout.classList.add(CLASSES.pinned);
				pinToggle.setAttribute('aria-pressed', 'true');
				pinToggle.title = 'Unpin panel';
				slideout.classList.add(CLASSES.open);
				document.body.classList.add('mantis-seo-panel-open');
			}
		}

		const resizeTab = slideout.querySelector(SELECTORS.resizeTab);
		const panelWrap = slideout.querySelector('.mantis-seo-slideout-panel-wrap');
		if (resizeTab && panelWrap) {
			resizeTab.addEventListener('click', toggleWide);
			// Restore wide state from sessionStorage.
			const wasWide = sessionStorage.getItem(STORAGE_KEYS.wide) === '1';
			if (wasWide) {
				panelWrap.classList.add(CLASSES.wide);
				updateResizeTab(resizeTab, true);
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
			document.body.classList.add('mantis-seo-panel-open');
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
			document.body.classList.remove('mantis-seo-panel-open');
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
		// Toggle tack icon state (pinned = pushed in).
		const icon = pinToggle.querySelector('.mantis-seo-icon-tack');
		if (icon) {
			icon.classList.toggle('mantis-seo-tack-pinned', isPinned);
		}
		sessionStorage.setItem(STORAGE_KEYS.pinned, isPinned ? '1' : '0');
	}

	function toggleWide() {
		const slideout = document.querySelector(SELECTORS.slideout);
		const panelWrap = slideout && slideout.querySelector('.mantis-seo-slideout-panel-wrap');
		const resizeTab = slideout && slideout.querySelector(SELECTORS.resizeTab);
		if (!panelWrap || !resizeTab) return;

		const isWide = panelWrap.classList.toggle(CLASSES.wide);
		updateResizeTab(resizeTab, isWide);
		sessionStorage.setItem(STORAGE_KEYS.wide, isWide ? '1' : '0');
	}

	function updateResizeTab(resizeTab, isWide) {
		const caretLeft = '<span class="mantis-seo-resize-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="m3.86 8.753 5.482 4.796c.646.566 1.658.106 1.658-.753V3.204a1 1 0 0 0-1.659-.753l-5.48 4.796a1 1 0 0 0 0 1.506z"/></svg></span>';
		const caretRight = '<span class="mantis-seo-resize-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="m12.14 8.753-5.482 4.796c-.646.566-1.658.106-1.658-.753V3.204a1 1 0 0 1 1.659-.753l5.48 4.796a1 1 0 0 1 0 1.506z"/></svg></span>';
		resizeTab.innerHTML = isWide ? caretRight : caretLeft;
		resizeTab.setAttribute('title', isWide ? 'Shrink panel' : 'Expand panel');
		resizeTab.setAttribute('aria-label', isWide ? 'Shrink panel' : 'Expand panel');
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
