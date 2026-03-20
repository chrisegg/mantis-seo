/**
 * SEO title template editor: variables as pills; stores %tokens% in a hidden input.
 *
 * @package Mantis_SEO
 */
(function (window, document) {
	'use strict';

	const CHUNK_CLASS = 'mantis-template-chunk';
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

	function createChunk(text) {
		const span = document.createElement('span');
		span.className = CHUNK_CLASS;
		span.setAttribute('contenteditable', 'true');
		span.textContent = text;
		return span;
	}

	function createPill(token, mode) {
		const span = document.createElement('span');
		span.className = PILL_CLASS;
		span.setAttribute('contenteditable', 'false');
		span.setAttribute('data-var', token);
		span.setAttribute('title', getVarLabels()[token] || token);
		const inner = document.createElement('span');
		inner.className = 'mantis-var-pill-inner';
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
			syncToHidden(surface);
		});
		surface.addEventListener('blur', function () {
			syncToHidden(surface);
		}, true);
	}

	function render(surface, templateStr, mode) {
		surface.innerHTML = '';
		const tokens = parseTemplate(templateStr);
		if (tokens.length === 0) {
			surface.appendChild(createChunk(''));
		} else {
			tokens.forEach(function (tok) {
				if (tok.type === 'var') {
					surface.appendChild(createPill(tok.token, mode));
				} else {
					surface.appendChild(createChunk(tok.value));
				}
			});
		}
		bindSurface(surface);
	}

	function serialize(surface) {
		let out = '';
		Array.prototype.forEach.call(surface.children, function (node) {
			if (node.classList.contains(PILL_CLASS)) {
				out += node.getAttribute('data-var') || '';
			} else if (node.classList.contains(CHUNK_CLASS)) {
				out += node.textContent;
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

	function init(root) {
		(root || document).querySelectorAll(SURFACE_SELECTOR).forEach(initOne);
		bindSettingsVarToolbars();
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
		const sel = window.getSelection();
		let chunk = null;
		if (sel.rangeCount > 0) {
			let node = sel.anchorNode;
			if (node.nodeType === 3) {
				node = node.parentElement;
			}
			chunk = node && node.closest('.' + CHUNK_CLASS);
			if (chunk && !surface.contains(chunk)) {
				chunk = null;
			}
		}
		if (!chunk) {
			const last = surface.lastElementChild;
			if (last && last.classList.contains(CHUNK_CLASS)) {
				chunk = last;
			}
		}
		if (!chunk) {
			chunk = createChunk('');
			surface.appendChild(chunk);
		}
		const pill = createPill(varToken, mode);
		const range = sel.rangeCount ? sel.getRangeAt(0) : null;
		if (range && chunk.contains(range.commonAncestorContainer)) {
			try {
				range.deleteContents();
				range.insertNode(pill);
				range.setStartAfter(pill);
				range.collapse(true);
				sel.removeAllRanges();
				sel.addRange(range);
			} catch (e) {
				surface.appendChild(pill);
			}
		} else {
			surface.appendChild(pill);
		}
		syncToHidden(surface);
		chunk.focus();
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
