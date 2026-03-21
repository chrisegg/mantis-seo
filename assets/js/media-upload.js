/**
 * Mantis SEO – Media library upload for image fields.
 *
 * @package Mantis_SEO
 */

(function () {
	'use strict';

	function openMediaFrame(targetInput, previewEl) {
		if (typeof wp === 'undefined' || !wp.media) {
			return;
		}

		var frame = wp.media({
			title: wp.media.view.l10n.insertMediaTitle || 'Select or Upload Image',
			button: { text: wp.media.view.l10n.insertIntoPost || 'Use this image' },
			library: { type: 'image' },
			multiple: false,
		});

		frame.on('select', function () {
			var attachment = frame.state().get('selection').first().toJSON();
			var url = attachment.url;
			if (attachment.sizes && attachment.sizes.full) {
				url = attachment.sizes.full.url;
			}
			targetInput.value = url;
			targetInput.dispatchEvent(new Event('input', { bubbles: true }));
			updatePreview(previewEl, url);
		});

		frame.open();
	}

	function updatePreview(previewEl, url) {
		if (!previewEl) return;
		previewEl.replaceChildren();
		if (url) {
			var img = document.createElement('img');
			img.alt = '';
			img.src = url;
			previewEl.appendChild(img);
		}
	}

	function syncPreviews() {
		document.querySelectorAll('.mantis-image-preview').forEach(function (el) {
			var forId = el.getAttribute('data-for');
			if (!forId) return;
			var input = document.querySelector(forId);
			if (input) {
				updatePreview(el, input.value);
			}
		});
	}

	function init() {
		document.addEventListener('click', function (e) {
			var btn = e.target.closest('.mantis-media-upload');
			if (!btn) return;

			e.preventDefault();
			var targetSel = btn.getAttribute('data-target');
			if (!targetSel) return;

			var targetInput = document.querySelector(targetSel);
			if (!targetInput) return;

			var previewSel = btn.parentElement && btn.parentElement.querySelector('.mantis-image-preview[data-for="' + targetSel + '"]');
			var previewEl = previewSel || (btn.parentElement ? btn.parentElement.querySelector('.mantis-image-preview') : null);

			openMediaFrame(targetInput, previewEl);
		});

		// Update previews when input values change (paste, manual edit).
		document.addEventListener('input', function (e) {
			if (!e.target.classList.contains('mantis-image-input')) return;
			var forSelector = e.target.id ? '#' + e.target.id : null;
			if (!forSelector) return;
			var preview = document.querySelector('.mantis-image-preview[data-for="' + forSelector + '"]');
			if (!preview) {
				preview = e.target.closest('.mantis-image-field-wrap')?.querySelector('.mantis-image-preview');
			}
			if (preview) {
				updatePreview(preview, e.target.value);
			}
		});

		// Initial sync on DOMContentLoaded (for settings page).
		syncPreviews();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
