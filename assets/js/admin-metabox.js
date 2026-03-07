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

	function replaceVarsInString(str, replaceVars) {
		if (!replaceVars || typeof str !== 'string') return str;
		let out = str;
		Object.keys(replaceVars).forEach(function (key) {
			out = out.split(key).join(replaceVars[key]);
		});
		return out;
	}

	function updatePreview(slideout) {
		const root = slideout || document;
		const titleEl = root.querySelector('[name="_mantis_title"]') || document.getElementById('_mantis_title');
		const descEl = root.querySelector('[name="_mantis_description"]') || document.getElementById('_mantis_description');
		const postTitleEl = document.getElementById('title');
		const snippetTitle = root.querySelector('#mantis-snippet-title') || document.getElementById('mantis-snippet-title');
		const snippetDesc = root.querySelector('#mantis-snippet-description') || document.getElementById('mantis-snippet-description');
		const titleCount = root.querySelector('#mantis-title-count') || document.getElementById('mantis-title-count');
		const descCount = root.querySelector('#mantis-desc-count') || document.getElementById('mantis-desc-count');

		if (!snippetTitle) return;

		let titleRaw = (titleEl && titleEl.value) ? titleEl.value.trim() : '';
		if (!titleRaw && postTitleEl) {
			titleRaw = postTitleEl.value.trim();
		}
		const replaceVars = (typeof mantisSeoMetabox !== 'undefined' && mantisSeoMetabox.replaceVars) ? mantisSeoMetabox.replaceVars : {};
		const titleResolved = replaceVarsInString(titleRaw || '', replaceVars);
		snippetTitle.textContent = titleResolved || '(No title)';

		const descRaw = (descEl && descEl.value) ? descEl.value.trim() : '';
		const descResolved = replaceVarsInString(descRaw, replaceVars);
		snippetDesc.textContent = descResolved || '';

		const titleLen = titleEl ? titleEl.value.length : 0;
		const descLen = descEl ? descEl.value.length : 0;
		if (titleCount) titleCount.textContent = titleLen;
		if (descCount) descCount.textContent = descLen;
	}

	function bindPreviewEvents(slideout) {
		const root = slideout || document;
		const titleEl = root.querySelector('[name="_mantis_title"]') || document.getElementById('_mantis_title');
		const descEl = root.querySelector('[name="_mantis_description"]') || document.getElementById('_mantis_description');
		const postTitleEl = document.getElementById('title');
		const events = ['input', 'change', 'paste'];

		function bind(el, fn) {
			if (!el) return;
			events.forEach(function (ev) {
				el.addEventListener(ev, fn);
			});
		}

		bind(titleEl, function () { updatePreview(slideout); });
		bind(descEl, function () { updatePreview(slideout); });
		bind(postTitleEl, function () { updatePreview(slideout); });
	}

	function init() {
		const slideout = document.querySelector(SELECTORS.slideout);
		if (!slideout) return;

		// Move slideout to body so it escapes block editor sidebar stacking context
		// (fixes Layout/Reset Ratings from other plugins showing inside the panel).
		if (slideout.parentNode !== document.body) {
			document.body.appendChild(slideout);
		}

		updatePreview(slideout);
		bindPreviewEvents(slideout);

		const trigger = document.querySelector(SELECTORS.trigger);
		const closeBtn = slideout.querySelector(SELECTORS.closeBtn);
		const pinToggle = slideout.querySelector(SELECTORS.pinToggle);

		if (trigger) {
			trigger.addEventListener('click', openPanel);
		}

		if (closeBtn) {
			closeBtn.addEventListener('click', closePanel);
		}

		const backdrop = slideout.querySelector('.mantis-seo-slideout-backdrop');
		if (backdrop) {
			backdrop.addEventListener('click', function () {
				if (!slideout.classList.contains(CLASSES.pinned)) {
					closePanel();
				}
			});
		}

		slideout.querySelectorAll(SELECTORS.tab).forEach(function (tabEl) {
			tabEl.addEventListener('click', function () {
				switchTab(slideout, tabEl.getAttribute('data-tab'));
			});
		});

		if (pinToggle) {
			pinToggle.addEventListener('click', function (e) {
				e.preventDefault();
				e.stopPropagation();
				togglePin();
			});
			// Pin is off by default; do not restore from sessionStorage.
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

		// Variable insert dropdown.
		bindVariableInsert(slideout);
	}

	function bindVariableInsert(slideout) {
		slideout.querySelectorAll('.mantis-var-insert-btn').forEach(function (btn) {
			btn.addEventListener('click', function (e) {
				e.preventDefault();
				e.stopPropagation();
				const wrap = btn.closest('.mantis-var-field-wrap');
				if (!wrap) return;
				const dropdown = wrap.querySelector('.mantis-var-dropdown');
				const field = wrap.querySelector('.mantis-var-field');
				if (!dropdown || !field) return;
				const isOpen = dropdown.getAttribute('aria-hidden') !== 'true';
				slideout.querySelectorAll('.mantis-var-dropdown').forEach(function (d) {
					d.setAttribute('aria-hidden', 'true');
				});
				slideout.querySelectorAll('.mantis-var-insert-btn').forEach(function (b) {
					b.setAttribute('aria-expanded', 'false');
				});
				if (!isOpen) return;
				dropdown.setAttribute('aria-hidden', 'false');
				btn.setAttribute('aria-expanded', 'true');
			});
		});
		slideout.querySelectorAll('.mantis-var-option').forEach(function (opt) {
			opt.addEventListener('click', function (e) {
				e.preventDefault();
				e.stopPropagation();
				const wrap = opt.closest('.mantis-var-field-wrap');
				if (!wrap) return;
				const field = wrap.querySelector('.mantis-var-field');
				const dropdown = wrap.querySelector('.mantis-var-dropdown');
				const btn = wrap.querySelector('.mantis-var-insert-btn');
				const v = opt.getAttribute('data-var');
				if (!field || !v) return;
				if (field.tagName === 'TEXTAREA') {
					const start = field.selectionStart;
					const end = field.selectionEnd;
					const val = field.value;
					field.value = val.slice(0, start) + v + val.slice(end);
					field.selectionStart = field.selectionEnd = start + v.length;
				} else {
					const start = field.selectionStart;
					const end = field.selectionEnd;
					const val = field.value;
					field.value = val.slice(0, start) + v + val.slice(end);
					field.selectionStart = field.selectionEnd = start + v.length;
				}
				field.dispatchEvent(new Event('input', { bubbles: true }));
				if (dropdown) dropdown.setAttribute('aria-hidden', 'true');
				if (btn) btn.setAttribute('aria-expanded', 'false');
			});
		});
		// Defer so the same click that opened the dropdown doesn't immediately close it.
		document.addEventListener('click', function closeDropdownHandler(e) {
			if (slideout.contains(e.target) && e.target.closest('.mantis-var-insert-wrap')) return;
			slideout.querySelectorAll('.mantis-var-dropdown').forEach(function (d) {
				d.setAttribute('aria-hidden', 'true');
			});
			slideout.querySelectorAll('.mantis-var-insert-btn').forEach(function (b) {
				b.setAttribute('aria-expanded', 'false');
			});
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
			updatePreview(slideout);
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
			slideout.classList.remove(CLASSES.open, CLASSES.pinned);
			slideout.setAttribute('aria-hidden', 'true');
			document.body.classList.remove('mantis-seo-panel-open');
			const pinToggle = slideout.querySelector(SELECTORS.pinToggle);
			if (pinToggle) {
				pinToggle.setAttribute('aria-pressed', 'false');
				pinToggle.title = 'Pin panel open';
				const icon = pinToggle.querySelector('.mantis-seo-icon-tack');
				if (icon) icon.classList.remove('mantis-seo-tack-pinned');
			}
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

	function tryInit() {
		const slideout = document.querySelector(SELECTORS.slideout);
		if (slideout) {
			init();
			return;
		}
		// Block editor may load metabox late; retry a few times.
		let attempts = 0;
		const interval = setInterval(function () {
			attempts++;
			if (document.querySelector(SELECTORS.slideout)) {
				clearInterval(interval);
				init();
			} else if (attempts >= 20) {
				clearInterval(interval);
			}
		}, 250);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', tryInit);
	} else {
		tryInit();
	}
})();
