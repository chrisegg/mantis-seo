/**
 * SEO title template editor: one contenteditable + atomic pills; hidden input holds %tokens%.
 *
 * @package Mantis_SEO
 */
(function (window, document) {
	'use strict';

	const PILL_CLASS = 'mantis-var-pill';
	const SURFACE_SELECTOR = '[data-mantis-template-editor]';

	function getVarLabels() {
		return (window.mantisSeoTemplateVars && window.mantisSeoTemplateVars.varLabels) || {};
	}

	function knownVarsSorted() {
		return Object.keys(getVarLabels()).sort(function (a, b) {
			return b.length - a.length;
		});
	}

	function parseTemplate(str) {
		str = str || '';
		const vars = knownVarsSorted();
		const tokens = [];
		let i = 0;
		while (i < str.length) {
			if (str[i] === '%') {
				let matched = '';
				for (let v = 0; v < vars.length; v++) {
					const key = vars[v];
					if (str.slice(i, i + key.length) === key) {
						matched = key;
						break;
					}
				}
				if (matched) {
					tokens.push({ type: 'var', token: matched });
					i += matched.length;
				} else {
					tokens.push({ type: 'text', value: str[i] });
					i++;
				}
			} else {
				let j = i;
				while (j < str.length && str[j] !== '%') {
					j++;
				}
				tokens.push({ type: 'text', value: str.slice(i, j) });
				i = j;
			}
		}
		return mergeAdjacentText(tokens);
	}

	function mergeAdjacentText(tokens) {
		const out = [];
		tokens.forEach(function (t) {
			if (t.type === 'text' && t.value === '') {
				return;
			}
			if (out.length && t.type === 'text' && out[out.length - 1].type === 'text') {
				out[out.length - 1].value += t.value;
			} else {
				out.push(t);
			}
		});
		return out;
	}

	function getReplaceVars() {
		return (typeof mantisSeoMetabox !== 'undefined' && mantisSeoMetabox.replaceVars)
			? mantisSeoMetabox.replaceVars
			: {};
	}

	function pillDisplayText(token, mode) {
		const labels = getVarLabels();
		if (mode === 'resolve') {
			const rv = getReplaceVars();
			if (Object.prototype.hasOwnProperty.call(rv, token)) {
				return String(rv[token]);
			}
		}
		return labels[token] || token;
	}

	function stripZwsp(str) {
		return (str || '').replace(/\u200b/g, '').replace(/\ufeff/g, '');
	}

	function placeCaretInTextNode(textNode, offset) {
		if (!textNode || textNode.nodeType !== 3) return;
		const sel = window.getSelection();
		const range = document.createRange();
		const max = textNode.textContent.length;
		range.setStart(textNode, Math.min(Math.max(0, offset), max));
		range.collapse(true);
		sel.removeAllRanges();
		sel.addRange(range);
	}

	function ensureTextAfterPill(pill) {
		if (!pill.nextSibling || pill.nextSibling.nodeType !== 3) {
			pill.parentNode.insertBefore(document.createTextNode('\u200b'), pill.nextSibling);
		}
	}

	/**
	 * Insert ZWSP text nodes so there is always editable space around pills.
	 */
	function ensureTextGaps(surface) {
		let n = surface.firstChild;
		while (n) {
			const next = n.nextSibling;
			if (n.nodeType === 1 && n.classList.contains(PILL_CLASS)) {
				if (next && next.nodeType === 1 && next.classList.contains(PILL_CLASS)) {
					surface.insertBefore(document.createTextNode('\u200b'), next);
					continue;
				}
			}
			n = next;
		}
		if (surface.firstChild && surface.firstChild.nodeType === 1 && surface.firstChild.classList.contains(PILL_CLASS)) {
			surface.insertBefore(document.createTextNode('\u200b'), surface.firstChild);
		}
		const last = surface.lastChild;
		if (last && last.nodeType === 1 && last.classList.contains(PILL_CLASS)) {
			surface.appendChild(document.createTextNode('\u200b'));
		}
		if (!surface.firstChild) {
			surface.appendChild(document.createTextNode('\u200b'));
		}
	}

	function normalizeEmptyTextNodes(surface) {
		Array.prototype.forEach.call(surface.childNodes, function (node) {
			if (node.nodeType === 3 && node.textContent === '') {
				node.textContent = '\u200b';
			}
		});
	}

	function createPill(token, mode) {
		const span = document.createElement('span');
		span.className = PILL_CLASS;
		span.setAttribute('contenteditable', 'false');
		span.setAttribute('data-var', token);
		span.setAttribute('title', getVarLabels()[token] || token);
		const inner = document.createElement('span');
		inner.className = 'mantis-var-pill-inner';
		inner.setAttribute('contenteditable', 'false');
		inner.textContent = pillDisplayText(token, mode);
		span.appendChild(inner);
		return span;
	}

	function bindSurface(surface) {
		if (surface.dataset.mantisTeBound) {
			return;
		}
		surface.dataset.mantisTeBound = '1';

		surface.addEventListener('input', function () {
			normalizeEmptyTextNodes(surface);
			ensureTextGaps(surface);
			syncToHidden(surface);
		});

		surface.addEventListener('blur', function () {
			syncToHidden(surface);
		}, true);

		/* Click pill: caret after pill (ready to type or insert next variable). */
		surface.addEventListener('click', function (e) {
			const pill = e.target.closest && e.target.closest('.' + PILL_CLASS);
			if (!pill || !surface.contains(pill)) {
				return;
			}
			e.preventDefault();
			e.stopPropagation();
			ensureTextAfterPill(pill);
			const after = pill.nextSibling;
			if (after && after.nodeType === 3) {
				placeCaretInTextNode(after, after.textContent.length);
			}
		});

		surface.addEventListener('keydown', function (e) {
			if (e.key !== 'Backspace' && e.key !== 'Delete') {
				return;
			}
			const sel = window.getSelection();
			if (!sel.rangeCount || !surface.contains(sel.anchorNode)) {
				return;
			}
			if (!sel.isCollapsed) {
				return;
			}
			const node = sel.anchorNode;
			if (node.nodeType !== 3) {
				return;
			}
			const offset = sel.anchorOffset;
			const text = node.textContent;

			if (e.key === 'Backspace' && offset === 0) {
				const prev = node.previousSibling;
				if (prev && prev.nodeType === 1 && prev.classList.contains(PILL_CLASS)) {
					e.preventDefault();
					prev.remove();
					const before = node.previousSibling;
					if (before && before.nodeType === 3) {
						const merged = before.textContent + node.textContent;
						before.textContent = merged === '' ? '\u200b' : merged;
						surface.removeChild(node);
						placeCaretInTextNode(before, before.textContent.length);
					} else {
						placeCaretInTextNode(node, 0);
					}
					ensureTextGaps(surface);
					syncToHidden(surface);
				}
			} else if (e.key === 'Delete' && offset === text.length) {
				const next = node.nextSibling;
				if (next && next.nodeType === 1 && next.classList.contains(PILL_CLASS)) {
					e.preventDefault();
					next.remove();
					const after = node.nextSibling;
					if (after && after.nodeType === 3) {
						const merged = node.textContent + after.textContent;
						node.textContent = merged === '' ? '\u200b' : merged;
						surface.removeChild(after);
					}
					placeCaretInTextNode(node, node.textContent.length);
					ensureTextGaps(surface);
					syncToHidden(surface);
				}
			}
		});
	}

	function render(surface, templateStr, mode) {
		surface.innerHTML = '';
		surface.setAttribute('contenteditable', 'true');
		surface.setAttribute('spellcheck', 'false');
		const tokens = parseTemplate(templateStr);
		if (tokens.length === 0) {
			surface.appendChild(document.createTextNode('\u200b'));
		} else {
			tokens.forEach(function (tok) {
				if (tok.type === 'var') {
					surface.appendChild(createPill(tok.token, mode));
				} else {
					surface.appendChild(document.createTextNode(tok.value === '' ? '\u200b' : tok.value));
				}
			});
		}
		ensureTextGaps(surface);
		bindSurface(surface);
	}

	function serialize(surface) {
		let out = '';
		Array.prototype.forEach.call(surface.childNodes, function (node) {
			if (node.nodeType === 3) {
				out += stripZwsp(node.textContent);
			} else if (node.nodeType === 1 && node.classList.contains(PILL_CLASS)) {
				out += node.getAttribute('data-var') || '';
			}
		});
		return out;
	}

	function syncToHidden(surface) {
		const wrap = surface.closest('.mantis-template-editor-wrap');
		if (!wrap) {
			return;
		}
		const hidden = wrap.querySelector('.mantis-template-hidden');
		if (!hidden) {
			return;
		}
		hidden.value = serialize(surface);
		hidden.dispatchEvent(new Event('input', { bubbles: true }));
		hidden.dispatchEvent(new CustomEvent('mantis-template-serialized', { bubbles: true }));
	}

	function refreshPills(surface) {
		const mode = surface.getAttribute('data-pill-mode') || 'label';
		if (mode !== 'resolve') {
			return;
		}
		Array.prototype.forEach.call(surface.querySelectorAll('.' + PILL_CLASS), function (pill) {
			const token = pill.getAttribute('data-var');
			const inner = pill.querySelector('.mantis-var-pill-inner');
			if (inner && token) {
				inner.textContent = pillDisplayText(token, mode);
			}
		});
	}

	function initOne(surface) {
		if (surface.dataset.mantisTemplateInit) {
			return;
		}
		surface.dataset.mantisTemplateInit = '1';
		const wrap = surface.closest('.mantis-template-editor-wrap');
		if (!wrap) {
			return;
		}
		const hidden = wrap.querySelector('.mantis-template-hidden');
		if (!hidden) {
			return;
		}
		const mode = surface.getAttribute('data-pill-mode') || 'label';
		render(surface, hidden.value, mode);
		syncToHidden(surface);
	}

	let settingsToolbarDocClickBound = false;

	function bindSettingsVarToolbars() {
		document.querySelectorAll('.mantis-template-editor-toolbar .mantis-settings-var-btn').forEach(function (btn) {
			if (btn.dataset.mantisSettingsVarBound) {
				return;
			}
			btn.dataset.mantisSettingsVarBound = '1';
			const toolbar = btn.closest('.mantis-template-editor-toolbar');
			const wrap = btn.closest('.mantis-template-editor-wrap');
			const dd = toolbar && toolbar.querySelector('.mantis-settings-var-dropdown');
			if (!toolbar || !wrap || !dd) {
				return;
			}
			btn.addEventListener('click', function (e) {
				e.preventDefault();
				e.stopPropagation();
				const open = dd.getAttribute('aria-hidden') === 'false';
				document.querySelectorAll('.mantis-settings-var-dropdown').forEach(function (d) {
					d.setAttribute('aria-hidden', 'true');
				});
				document.querySelectorAll('.mantis-settings-var-btn').forEach(function (b) {
					b.setAttribute('aria-expanded', 'false');
				});
				if (!open) {
					dd.setAttribute('aria-hidden', 'false');
					btn.setAttribute('aria-expanded', 'true');
				}
			});
			dd.querySelectorAll('.mantis-settings-var-option').forEach(function (opt) {
				opt.addEventListener('click', function (e) {
					e.preventDefault();
					e.stopPropagation();
					const v = opt.getAttribute('data-var');
					if (v) {
						insertVar(wrap, v);
					}
					dd.setAttribute('aria-hidden', 'true');
					btn.setAttribute('aria-expanded', 'false');
				});
			});
		});
		if (!settingsToolbarDocClickBound) {
			settingsToolbarDocClickBound = true;
			document.addEventListener('click', function (e) {
				if (e.target.closest('.mantis-template-editor-toolbar')) {
					return;
				}
				document.querySelectorAll('.mantis-settings-var-dropdown').forEach(function (d) {
					d.setAttribute('aria-hidden', 'true');
				});
				document.querySelectorAll('.mantis-settings-var-btn').forEach(function (b) {
					b.setAttribute('aria-expanded', 'false');
				});
			});
		}
	}

	function collapseRangeToEndOfSurface(surface) {
		const range = document.createRange();
		const last = surface.lastChild;
		if (!last) {
			surface.appendChild(document.createTextNode('\u200b'));
			range.setStart(surface.lastChild, 1);
			range.collapse(true);
			return range;
		}
		if (last.nodeType === 3) {
			range.setStart(last, last.textContent.length);
			range.collapse(true);
			return range;
		}
		if (last.classList && last.classList.contains(PILL_CLASS)) {
			ensureTextAfterPill(last);
			const after = last.nextSibling;
			if (after && after.nodeType === 3) {
				range.setStart(after, after.textContent.length);
				range.collapse(true);
				return range;
			}
		}
		range.selectNodeContents(surface);
		range.collapse(false);
		return range;
	}

	/**
	 * @param {Element} scope .mantis-var-field-wrap or surface element
	 * @param {string} varToken e.g. %post_title%
	 */
	function insertVar(scope, varToken) {
		const surface = scope.matches && scope.matches(SURFACE_SELECTOR)
			? scope
			: (scope.querySelector ? scope.querySelector(SURFACE_SELECTOR) : null);
		if (!surface) {
			return;
		}
		const mode = surface.getAttribute('data-pill-mode') || 'label';
		const pill = createPill(varToken, mode);
		const sel = window.getSelection();

		surface.focus();

		let range = null;
		if (sel.rangeCount > 0 && surface.contains(sel.anchorNode)) {
			range = sel.getRangeAt(0).cloneRange();
		}
		if (!range) {
			range = collapseRangeToEndOfSurface(surface);
		}

		range.deleteContents();
		try {
			range.insertNode(pill);
		} catch (err) {
			surface.appendChild(pill);
		}

		ensureTextAfterPill(pill);
		const after = pill.nextSibling;
		const r2 = document.createRange();
		if (after && after.nodeType === 3) {
			r2.setStart(after, after.textContent.length);
			r2.collapse(true);
		} else {
			ensureTextAfterPill(pill);
			const a2 = pill.nextSibling;
			if (a2 && a2.nodeType === 3) {
				r2.setStart(a2, a2.textContent.length);
				r2.collapse(true);
			} else {
				r2.setStartAfter(pill);
				r2.collapse(true);
			}
		}
		sel.removeAllRanges();
		sel.addRange(r2);

		ensureTextGaps(surface);
		syncToHidden(surface);
	}

	function init(root) {
		(root || document).querySelectorAll(SURFACE_SELECTOR).forEach(initOne);
		bindSettingsVarToolbars();
	}

	window.MantisTemplateEditor = {
		init: init,
		refreshPills: function (root) {
			(root || document).querySelectorAll(SURFACE_SELECTOR).forEach(refreshPills);
		},
		insertVar: insertVar,
		serialize: serialize
	};

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function () {
			init(document);
		});
	} else {
		init(document);
	}
})(window, document);
