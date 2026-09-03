/**
 * Mantis SEO – settings page tab panels and import form placement.
 *
 * @package Mantis_SEO
 */

(function () {
	'use strict';

	var cfg = window.mantisSeoSettings || {};
	var allowedTabs = cfg.allowedTabs || [];

	var navItems = document.querySelectorAll('.mantis-settings-nav-item');
	var panels = document.querySelectorAll('.mantis-settings-body .mantis-tab-panel');
	var importForm = document.getElementById('mantis-import-form');
	var importCell = document.getElementById('mantis-import-cell');
	var activeTabInput = document.getElementById('mantis-settings-active-tab');

	function showPanel(targetId) {
		navItems.forEach(function (n) {
			n.classList.remove('mantis-settings-nav-active');
		});
		panels.forEach(function (p) {
			p.style.display = p.id === targetId ? 'block' : 'none';
		});
		var active = document.querySelector('.mantis-settings-nav-item[data-panel="' + targetId + '"]');
		if (active) {
			active.classList.add('mantis-settings-nav-active');
		}
		if (activeTabInput) {
			activeTabInput.value = targetId;
		}
		if (targetId === 'mantis-tools' && importForm && importCell) {
			importCell.appendChild(importForm);
			importForm.style.display = 'block';
		} else if (importForm) {
			importForm.style.display = 'none';
		}
	}

	navItems.forEach(function (item) {
		item.addEventListener('click', function (e) {
			e.preventDefault();
			var targetId = item.getAttribute('data-panel') || item.getAttribute('href').slice(1);
			showPanel(targetId);
		});
	});

	if (window.location.search.indexOf('mantis_import_error') !== -1) {
		showPanel('mantis-tools');
	} else {
		var params = new URLSearchParams(window.location.search);
		var tabParam = params.get('mantis_tab');
		if (tabParam && allowedTabs.indexOf(tabParam) !== -1 && document.getElementById(tabParam)) {
			showPanel(tabParam);
		} else if (activeTabInput && activeTabInput.value && activeTabInput.value !== 'mantis-titles') {
			showPanel(activeTabInput.value);
		}
	}

	// MCP tool group select / deselect all.
	document.querySelectorAll('[data-mantis-mcp-select]').forEach(function (btn) {
		btn.addEventListener('click', function (e) {
			e.preventDefault();
			var group = btn.getAttribute('data-mantis-mcp-select');
			var on = btn.getAttribute('data-state') === '1';
			document.querySelectorAll('input[data-mantis-mcp-group="' + group + '"]').forEach(function (input) {
				input.checked = on;
			});
		});
	});
})();
