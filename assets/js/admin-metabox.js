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

	let blockEditorTitleSyncAttached = false;

	function replaceVarsInString(str, replaceVars) {
		if (!replaceVars || typeof str !== 'string') return str;
		let out = str;
		Object.keys(replaceVars).forEach(function (key) {
			out = out.split(key).join(replaceVars[key]);
		});
		return out;
	}

	function getSeoTitleTemplateRaw(root) {
		const r = root || document;
		const hidden = r.querySelector('input.mantis-template-hidden[name="_mantis_title"]')
			|| r.querySelector('input[type="hidden"][name="_mantis_title"]');
		return hidden && hidden.value ? hidden.value.trim() : '';
	}

	function updatePreview(slideout) {
		const root = slideout || document;
		const descEl = root.querySelector('[name="_mantis_description"]') || document.getElementById('_mantis_description');
		const postTitleEl = document.getElementById('title');
		const snippetTitle = root.querySelector('#mantis-snippet-title') || document.getElementById('mantis-snippet-title');
		const snippetDesc = root.querySelector('#mantis-snippet-description') || document.getElementById('mantis-snippet-description');
		const titleCount = root.querySelector('#mantis-title-count') || document.getElementById('mantis-title-count');
		const descCount = root.querySelector('#mantis-desc-count') || document.getElementById('mantis-desc-count');

		if (!snippetTitle) return;

		let titleRaw = getSeoTitleTemplateRaw(root);
		if (!titleRaw && typeof mantisSeoMetabox !== 'undefined' && mantisSeoMetabox.defaultTitleTemplate) {
			titleRaw = String(mantisSeoMetabox.defaultTitleTemplate).trim();
		}
		if (!titleRaw && postTitleEl) {
			titleRaw = postTitleEl.value.trim();
		}
		const replaceVars = (typeof mantisSeoMetabox !== 'undefined' && mantisSeoMetabox.replaceVars) ? mantisSeoMetabox.replaceVars : {};
		const titleResolved = replaceVarsInString(titleRaw || '', replaceVars);
		snippetTitle.textContent = titleResolved || '(No title)';

		const descRaw = (descEl && descEl.value) ? descEl.value.trim() : '';
		const descResolved = replaceVarsInString(descRaw, replaceVars);
		snippetDesc.textContent = descResolved || '';

		const titleLen = titleResolved ? titleResolved.length : 0;
		const descLen = descEl ? descEl.value.length : 0;
		if (titleCount) titleCount.textContent = titleLen;
		if (descCount) descCount.textContent = descLen;
	}

	function bindPreviewEvents(slideout) {
		const root = slideout || document;
		const titleHidden = root.querySelector('input.mantis-template-hidden[name="_mantis_title"]')
			|| root.querySelector('input[type="hidden"][name="_mantis_title"]');
		const descEl = root.querySelector('[name="_mantis_description"]') || document.getElementById('_mantis_description');
		const postTitleEl = document.getElementById('title');
		const titleSurface = root.querySelector('[data-mantis-template-editor]');
		const events = ['input', 'change', 'paste'];

		function bind(el, fn) {
			if (!el) return;
			events.forEach(function (ev) {
				el.addEventListener(ev, fn);
			});
		}

		bind(titleHidden, function () { updatePreview(slideout); });
		bind(titleSurface, function () { updatePreview(slideout); });
		bind(descEl, function () { updatePreview(slideout); });
		bind(postTitleEl, function () {
			if (window.MantisTemplateEditor) {
				MantisTemplateEditor.refreshPills(slideout);
			}
			updatePreview(slideout);
		});
	}

	function init() {
		const slideout = document.querySelector(SELECTORS.slideout);
		if (!slideout) return;

		// Move slideout to body so it escapes block editor sidebar stacking context
		// (fixes Layout/Reset Ratings from other plugins showing inside the panel).
		if (slideout.parentNode !== document.body) {
			document.body.appendChild(slideout);
		}

		if (window.MantisTemplateEditor) {
			MantisTemplateEditor.init(slideout);
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

		// Save SEO button (AJAX) – works in both Classic and Block editor.
		bindSaveSeoButton(slideout);

		// Sync slideout values into form on submit (slideout lives on body, outside form).
		bindSubmitSync();

		bindBlockEditorPostTitleSync(slideout);
	}

	/**
	 * Block editor: no #title field — sync edited post title into replaceVars for pills + preview.
	 *
	 * @param {Element} slideout Slideout root.
	 */
	function bindBlockEditorPostTitleSync(slideout) {
		function attach() {
			if (blockEditorTitleSyncAttached) return;
			if (!window.wp || !wp.data || typeof wp.data.subscribe !== 'function' || typeof wp.data.select !== 'function') {
				return;
			}
			blockEditorTitleSyncAttached = true;
			var last = null;
			wp.data.subscribe(function () {
				var s = slideout || document.querySelector(SELECTORS.slideout);
				if (!s) return;
				try {
					var ed = wp.data.select('core/editor');
					if (!ed || typeof ed.getEditedPostAttribute !== 'function') return;
					var title = ed.getEditedPostAttribute('title');
					if (typeof title !== 'string') return;
					if (title === last) return;
					last = title;
					var disp = title.trim();
					if (!disp) {
						disp = (typeof mantisSeoMetabox !== 'undefined' && mantisSeoMetabox.noTitlePlaceholder)
							? mantisSeoMetabox.noTitlePlaceholder
							: 'No title';
					}
					if (typeof mantisSeoMetabox !== 'undefined' && mantisSeoMetabox.replaceVars) {
						mantisSeoMetabox.replaceVars['%post_title%'] = disp;
						mantisSeoMetabox.replaceVars['%title%'] = disp;
					}
					if (window.MantisTemplateEditor) {
						MantisTemplateEditor.refreshPills(s);
					}
					updatePreview(s);
				} catch (err) {
					/* Classic editor or editor not mounted */
				}
			});
		}
		if (window.wp && wp.data) {
			attach();
		} else {
			setTimeout(attach, 500);
		}
	}

	/**
	 * Bind Save SEO button – saves meta via AJAX.
	 */
	function bindSaveSeoButton(slideout) {
		const btn = slideout.querySelector('.mantis-seo-save-btn');
		const status = slideout.querySelector('.mantis-seo-save-status');
		if (!btn || !status) return;

		btn.addEventListener('click', function () {
			let postId = slideout.getAttribute('data-post-id');
			if (!postId || postId === '0') {
				const m = window.location.search.match(/[?&]post=(\d+)/);
				if (m) postId = m[1];
			}
			if (!postId || postId === '0') {
				showSaveStatus(status, 'error', 'Save or publish the post first.');
				return;
			}

			const ajaxNonce = (typeof mantisSeoMetabox !== 'undefined' && mantisSeoMetabox.ajaxNonce) ? mantisSeoMetabox.ajaxNonce : '';
			if (!ajaxNonce) {
				showSaveStatus(status, 'error', 'Security check failed.');
				return;
			}

			const formData = new FormData();
			formData.append('action', 'mantis_seo_save_meta');
			formData.append('mantis_seo_ajax_nonce', ajaxNonce);
			formData.append('post_id', postId);

			// Text inputs and textareas.
			slideout.querySelectorAll('input[name^="_mantis_"]:not([type="checkbox"]), textarea[name^="_mantis_"]').forEach(function (el) {
				formData.append(el.name, el.value);
			});

			// Checkboxes: only send when checked (PHP isset = checked).
			['_mantis_robots_noindex', '_mantis_robots_nofollow'].forEach(function (name) {
				const cb = slideout.querySelector('input[name="' + name + '"]');
				if (cb && cb.checked) {
					formData.append(name, '1');
				}
			});

			btn.disabled = true;
			showSaveStatus(status, 'saving', 'Saving…');

			const ajaxUrl = (typeof mantisSeoMetabox !== 'undefined' && mantisSeoMetabox.ajaxUrl) ? mantisSeoMetabox.ajaxUrl : '';
			if (!ajaxUrl) {
				showSaveStatus(status, 'error', 'Configuration error.');
				btn.disabled = false;
				return;
			}

			fetch(ajaxUrl, {
				method: 'POST',
				body: formData,
				credentials: 'same-origin'
			})
				.then(function (res) { return res.json(); })
				.then(function (data) {
					if (data && data.success) {
						showSaveStatus(status, 'success', 'Saved.');
						setTimeout(function () { showSaveStatus(status, '', ''); }, 2000);
					} else {
						showSaveStatus(status, 'error', (data && data.data && data.data.message) ? data.data.message : 'Save failed.');
					}
				})
				.catch(function () {
					showSaveStatus(status, 'error', 'Save failed.');
				})
				.finally(function () {
					btn.disabled = false;
				});
		});
	}

	function showSaveStatus(el, type, msg) {
		if (!el) return;
		el.textContent = msg;
		el.className = 'mantis-seo-save-status mantis-seo-save-' + type;
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
				const tplSurface = wrap.querySelector('[data-mantis-template-editor]');
				if (!dropdown || (!field && !tplSurface)) return;
				const wasOpen = dropdown.getAttribute('aria-hidden') === 'false';
				slideout.querySelectorAll('.mantis-var-dropdown').forEach(function (d) {
					d.setAttribute('aria-hidden', 'true');
				});
				slideout.querySelectorAll('.mantis-var-insert-btn').forEach(function (b) {
					b.setAttribute('aria-expanded', 'false');
				});
				if (wasOpen) {
					return;
				}
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
				if (!v) return;
				if (wrap.querySelector('[data-mantis-template-editor]') && window.MantisTemplateEditor) {
					MantisTemplateEditor.insertVar(wrap, v);
					updatePreview(slideout);
					if (dropdown) dropdown.setAttribute('aria-hidden', 'true');
					if (btn) btn.setAttribute('aria-expanded', 'false');
					return;
				}
				if (!field) return;
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
			if (window.MantisTemplateEditor) {
				MantisTemplateEditor.refreshPills(slideout);
			}
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

	/**
	 * Sync slideout field values into the post form before submit.
	 * The slideout is moved to body (outside the form), so its inputs are not submitted.
	 * We copy values into hidden inputs inside the form so they are included in POST.
	 */
	function syncSlideoutToForm() {
		const slideout = document.querySelector(SELECTORS.slideout);
		if (!slideout) return;

		const trigger = document.querySelector(SELECTORS.trigger);
		const form = trigger && trigger.closest('form') ||
			document.getElementById('post') ||
			document.querySelector('form.metabox-location-side') ||
			document.querySelector('form[action*="post.php"]');
		if (!form) return;

		// Remove previous synced inputs.
		const container = form.querySelector('#mantis-seo-sync-container');
		if (container) {
			container.remove();
		}

		const wrap = document.createElement('div');
		wrap.id = 'mantis-seo-sync-container';
		wrap.style.display = 'none';

		function addHidden(name, value) {
			const input = document.createElement('input');
			input.type = 'hidden';
			input.name = name;
			input.value = value || '';
			wrap.appendChild(input);
		}

		// Nonce (required for PHP verification).
		const nonceEl = slideout.querySelector('[name="mantis_seo_metabox_nonce"]');
		if (nonceEl) {
			addHidden('mantis_seo_metabox_nonce', nonceEl.value);
		}

		// Text inputs and textareas.
		slideout.querySelectorAll('input[name^="_mantis_"]:not([type="checkbox"]), textarea[name^="_mantis_"]').forEach(function (el) {
			addHidden(el.name, el.value);
		});

		// Checkboxes: only add when checked (PHP uses isset() - absent = unchecked = '0').
		['_mantis_robots_noindex', '_mantis_robots_nofollow'].forEach(function (name) {
			const cb = slideout.querySelector('input[name="' + name + '"]');
			if (cb && cb.checked) {
				addHidden(name, '1');
			}
		});

		form.appendChild(wrap);
	}

	/**
	 * Bind submit handler to sync slideout values into form before submission.
	 */
	function bindSubmitSync() {
		const trigger = document.querySelector(SELECTORS.trigger);
		const form = trigger && trigger.closest('form') ||
			document.getElementById('post') ||
			document.querySelector('form.metabox-location-side') ||
			document.querySelector('form[action*="post.php"]');
		if (!form) return;

		form.addEventListener('submit', function syncHandler() {
			syncSlideoutToForm();
		}, true);
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
