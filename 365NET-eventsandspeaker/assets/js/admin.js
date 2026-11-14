(function () {
	'use strict';

	var activeMediaTarget = null;
	var activeMediaIsGallery = false;

	function byId(id) {
		return id ? document.getElementById(id) : null;
	}

	function isSafeImageUrl(value) {
		try {
			var url = new URL(value, window.location.href);
			return url.protocol === 'http:' || url.protocol === 'https:';
		} catch (_) {
			return false;
		}
	}

	function updateImagePreview(input) {
		var preview = null;
		var value = input ? String(input.value || '').trim() : '';
		var image;

		if (!input) {
			return;
		}

		preview = input.id ? document.querySelector('[data-cms365-image-preview][data-input-id="' + input.id + '"]') : null;
		if (!preview) {
			var field = input.closest('.cms365-image-field, .cms365-media-field');
			preview = field ? field.querySelector('[data-cms365-image-preview]') : null;
		}
		if (!preview) {
			return;
		}

		preview.innerHTML = '';
		if (!value) {
			var empty = document.createElement('span');
			empty.textContent = 'Keine Vorschau';
			preview.appendChild(empty);
			return;
		}
		if (!isSafeImageUrl(value)) {
			return;
		}

		image = document.createElement('img');
		image.src = value;
		image.alt = 'Bildvorschau';
		image.loading = 'lazy';
		image.addEventListener('error', function () {
			preview.innerHTML = '<span>Vorschau nicht verfügbar</span>';
		});
		preview.appendChild(image);
	}

	function galleryUrls(input) {
		return String(input && input.value ? input.value : '')
			.split(/[\n,]+/)
			.map(function (item) { return item.trim(); })
			.filter(Boolean);
	}

	function updateGalleryPreview(input) {
		var preview = input && input.id ? document.querySelector('[data-cms365-gallery-preview][data-input-id="' + input.id + '"]') : null;
		var urls = input ? galleryUrls(input) : [];

		if (!preview) {
			return;
		}

		preview.innerHTML = '';
		if (!urls.length) {
			var empty = document.createElement('span');
			empty.textContent = 'Noch keine Galeriebilder ausgewählt.';
			preview.appendChild(empty);
			return;
		}

		urls.slice(0, 12).forEach(function (url) {
			if (!isSafeImageUrl(url)) {
				return;
			}
			var item = document.createElement('span');
			var img = document.createElement('img');
			img.src = url;
			img.alt = 'Galeriebild';
			img.loading = 'lazy';
			item.appendChild(img);
			preview.appendChild(item);
		});
	}

	function appendGalleryUrl(input, url) {
		var urls = galleryUrls(input);
		if (urls.indexOf(url) === -1) {
			urls.push(url);
		}
		input.value = urls.join('\n');
		input.dispatchEvent(new Event('input', { bubbles: true }));
		input.dispatchEvent(new Event('change', { bubbles: true }));
	}

	function selectMediaUrl(url) {
		url = normalizeMediaUrl(url);
		if (!activeMediaTarget || !url) {
			return;
		}

		if (activeMediaIsGallery && activeMediaTarget.tagName === 'TEXTAREA') {
			appendGalleryUrl(activeMediaTarget, url);
			return;
		}

		activeMediaTarget.value = url;
		activeMediaTarget.dispatchEvent(new Event('input', { bubbles: true }));
		activeMediaTarget.dispatchEvent(new Event('change', { bubbles: true }));
		updateImagePreview(activeMediaTarget);
	}

	function itemUrl(item) {
		var directUrl = String((item && (item.url || item.fileUrl || item.file_url)) || '').trim();
		if (directUrl) {
			return normalizeMediaUrl(directUrl);
		}

		var path = String((item && item.path) || '').trim().replace(/^\/+/, '');
		if (!path) {
			return '';
		}

		return normalizeMediaUrl('/uploads/' + path);
	}

	function normalizeMediaUrl(url) {
		var normalized = String(url || '').trim();
		if (!normalized) {
			return '';
		}

		if (/^media-file\?/i.test(normalized) || /^uploads\//i.test(normalized)) {
			normalized = '/' + normalized.replace(/^\/+/, '');
		}

		if (!/^https?:\/\//i.test(normalized) && normalized.charAt(0) !== '/') {
			normalized = '/' + normalized.replace(/^\/+/, '');
		}

		return normalized;
	}

	function normalizeMediaInputsBeforeSubmit(form) {
		if (!form) {
			return;
		}

		form.querySelectorAll('[data-cms365-image-input]').forEach(function (input) {
			var normalized = normalizeMediaUrl(input.value || '');
			if (normalized !== String(input.value || '')) {
				input.value = normalized;
			}
		});
	}

	function renderMediaGrid(modal, items) {
		var grid = modal.querySelector('[data-media-picker-grid]');
		var status = modal.querySelector('[data-media-picker-status]');
		if (!grid) {
			return;
		}
		grid.innerHTML = '';
		if (!items.length) {
			if (status) {
				status.textContent = 'Keine Bilder gefunden.';
			}
			return;
		}
		if (status) {
			status.textContent = items.length + ' Bilder verfügbar';
		}
		items.forEach(function (item) {
			var url = itemUrl(item);
			var name = String((item && (item.name || item.title || item.path)) || 'Bild');
			if (!url || !isSafeImageUrl(url)) {
				return;
			}
			var button = document.createElement('button');
			var image;
			var label;
			button.type = 'button';
			button.className = 'cms365-media-picker-item';
			button.setAttribute('data-media-picker-select', '1');
			button.setAttribute('data-media-url', url);
			image = document.createElement('img');
			image.src = url;
			image.alt = '';
			image.loading = 'lazy';
			label = document.createElement('span');
			label.textContent = name;
			button.appendChild(image);
			button.appendChild(label);
			grid.appendChild(button);
		});
	}

	function loadMediaItems(modal) {
		var picker = modal.querySelector('[data-media-picker-modal]');
		var search = modal.querySelector('[data-media-picker-search]');
		var status = modal.querySelector('[data-media-picker-status]');
		var apiUrl = picker ? picker.getAttribute('data-api-url') || '/api/media' : '/api/media';
		var token = picker ? picker.getAttribute('data-csrf-token') || '' : '';
		var pathPrefix = picker ? String(picker.getAttribute('data-path-prefix') || 'events').trim() : 'events';
		var prefix = search ? String(search.value || '').trim() : '';
		var url = apiUrl
			+ '?action=list_images'
			+ (pathPrefix ? '&path_prefix=' + encodeURIComponent(pathPrefix) : '')
			+ (prefix ? '&filename_prefix=' + encodeURIComponent(prefix) : '');
		var headers = token ? { 'X-CSRF-Token': token } : {};

		if (status) {
			status.textContent = 'Lade Medien …';
		}

		fetch(url, { credentials: 'same-origin', headers: headers })
			.then(function (response) { return response.json(); })
			.then(function (payload) {
				if (payload && Number(payload.success) === 0) {
					if (status) {
						status.textContent = payload.message || 'Mediathek konnte nicht geladen werden.';
					}
					renderMediaGrid(modal, []);
					return;
				}
				renderMediaGrid(modal, Array.isArray(payload && payload.items) ? payload.items : []);
			})
			.catch(function () {
				if (status) {
					status.textContent = 'Mediathek konnte nicht geladen werden.';
				}
			});
	}

	function uploadMediaItem(modal, file) {
		var picker = modal.querySelector('[data-media-picker-modal]');
		var status = modal.querySelector('[data-media-picker-status]');
		var apiUrl = picker ? picker.getAttribute('data-api-url') || '/api/media' : '/api/media';
		var token = picker ? picker.getAttribute('data-csrf-token') || '' : '';
		var slugInput = document.querySelector('input[name="slug"]');
		var titleInput = document.querySelector('input[name="title"], input[name="display_name"]');
		var formData = new FormData();

		formData.append('action', 'upload_image');
		formData.append('image', file);
		formData.append('content_type', 'events');
		if (slugInput && slugInput.value) {
			formData.append('content_slug', String(slugInput.value));
		} else if (titleInput && titleInput.value) {
			formData.append('content_title', String(titleInput.value));
		}

		if (status) {
			status.textContent = 'Lade Bild hoch …';
		}

		fetch(apiUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: token ? { 'X-CSRF-Token': token } : {},
			body: formData
		})
			.then(function (response) { return response.json(); })
			.then(function (payload) {
				if (!payload || Number(payload.success) !== 1) {
					if (status) {
						status.textContent = payload && payload.message ? payload.message : 'Upload fehlgeschlagen.';
					}
					return;
				}
				if (status) {
					status.textContent = 'Upload erfolgreich.';
				}
				loadMediaItems(modal);
			})
			.catch(function () {
				if (status) {
					status.textContent = 'Upload fehlgeschlagen.';
				}
			});
	}

	function initMediaPicker() {
		var modal = byId('settingsMediaPickerModal');
		var instance = null;
		var searchTimer = null;
		var picker = null;
		var uploadButton = null;
		var uploadInput = null;

		if (!modal) {
			return;
		}

		picker = modal.querySelector('[data-media-picker-modal]');
		uploadButton = modal.querySelector('[data-media-picker-upload-trigger]');
		uploadInput = modal.querySelector('[data-media-picker-upload-input]');

		if (!uploadInput && picker) {
			uploadInput = document.createElement('input');
			uploadInput.type = 'file';
			uploadInput.accept = 'image/*';
			uploadInput.hidden = true;
			uploadInput.setAttribute('data-media-picker-upload-input', '1');
			picker.appendChild(uploadInput);
		}

		if (!uploadButton && picker) {
			uploadButton = document.createElement('button');
			uploadButton.type = 'button';
			uploadButton.className = 'btn btn-secondary btn-sm';
			uploadButton.textContent = 'Bild hochladen';
			uploadButton.setAttribute('data-media-picker-upload-trigger', '1');
			var searchWrap = modal.querySelector('.cms365-media-picker-search');
			if (searchWrap) {
				searchWrap.appendChild(uploadButton);
			} else {
				picker.insertBefore(uploadButton, picker.firstChild);
			}
		}

		function showModal() {
			if (window.bootstrap && window.bootstrap.Modal && typeof window.bootstrap.Modal.getOrCreateInstance === 'function') {
				instance = window.bootstrap.Modal.getOrCreateInstance(modal);
				instance.show();
			} else {
				modal.hidden = false;
				modal.classList.add('show');
				modal.style.display = 'block';
				modal.removeAttribute('aria-hidden');
			}
			loadMediaItems(modal);
		}

		function hideModal() {
			if (instance && typeof instance.hide === 'function') {
				instance.hide();
			} else {
				modal.classList.remove('show');
				modal.style.display = 'none';
				modal.setAttribute('aria-hidden', 'true');
			}
		}

		document.querySelectorAll('[data-open-media-picker]').forEach(function (button) {
			button.addEventListener('click', function () {
				activeMediaTarget = byId(button.getAttribute('data-target-input') || '');
				activeMediaIsGallery = button.getAttribute('data-gallery-target') === '1';
				var title = modal.querySelector('[data-media-picker-title]');
				if (title) {
					title.textContent = button.getAttribute('data-picker-title') || 'Bild auswählen';
				}
				showModal();
			});
		});

		modal.addEventListener('click', function (event) {
			var close = event.target.closest('[data-bs-dismiss="modal"], .btn-close');
			var select = event.target.closest('[data-media-picker-select="1"]');
			if (close) {
				hideModal();
				return;
			}
			if (select) {
				selectMediaUrl(select.getAttribute('data-media-url') || '');
				if (!activeMediaIsGallery) {
					hideModal();
				}
			}
		});

		var search = modal.querySelector('[data-media-picker-search]');
		if (search) {
			search.addEventListener('input', function () {
				window.clearTimeout(searchTimer);
				searchTimer = window.setTimeout(function () { loadMediaItems(modal); }, 250);
			});
		}

		if (uploadButton && uploadInput) {
			uploadButton.addEventListener('click', function () {
				uploadInput.click();
			});

			uploadInput.addEventListener('change', function () {
				var file = uploadInput.files && uploadInput.files[0] ? uploadInput.files[0] : null;
				if (!file) {
					return;
				}
				uploadMediaItem(modal, file);
				uploadInput.value = '';
			});
		}
	}

	document.addEventListener('DOMContentLoaded', function () {
		document.querySelectorAll('form').forEach(function (form) {
			if (!form.querySelector('[data-cms365-image-input]')) {
				return;
			}
			form.addEventListener('submit', function () {
				normalizeMediaInputsBeforeSubmit(form);
			});
		});

		document.querySelectorAll('form[data-confirm]').forEach(function (form) {
			form.addEventListener('submit', function (event) {
				var message = form.getAttribute('data-confirm') || 'Diese Aktion ausführen?';
				if (!window.confirm(message)) {
					event.preventDefault();
				}
			});
		});

		document.querySelectorAll('.cms365-multiselect').forEach(function (select) {
			select.addEventListener('dblclick', function (event) {
				if (event.target && event.target.tagName === 'OPTION') {
					event.target.selected = !event.target.selected;
				}
			});
		});

		document.querySelectorAll('[data-cms365-image-input]').forEach(function (input) {
			input.addEventListener('input', function () { updateImagePreview(input); });
			input.addEventListener('change', function () { updateImagePreview(input); });
			updateImagePreview(input);
		});

		document.querySelectorAll('[data-cms365-gallery-input]').forEach(function (input) {
			input.addEventListener('input', function () { updateGalleryPreview(input); });
			input.addEventListener('change', function () { updateGalleryPreview(input); });
			updateGalleryPreview(input);
		});

		document.querySelectorAll('[data-clear-media-input]').forEach(function (button) {
			button.addEventListener('click', function () {
				var input = byId(button.getAttribute('data-target-input') || '');
				if (input) {
					input.value = '';
					input.dispatchEvent(new Event('input', { bubbles: true }));
				}
			});
		});

		document.querySelectorAll('[data-clear-gallery-input]').forEach(function (button) {
			button.addEventListener('click', function () {
				var input = byId(button.getAttribute('data-target-input') || '');
				if (input) {
					input.value = '';
					input.dispatchEvent(new Event('input', { bubbles: true }));
				}
			});
		});

		document.querySelectorAll('input[name="tags"],input[name="categories"],input[name="specializations"],input[name="languages"]').forEach(function (input) {
			input.addEventListener('blur', function () {
				var seen = [];
				input.value = (input.value || '').split(/[,;\n]+/).map(function (item) {
					return item.trim();
				}).filter(function (item) {
					var key = item.toLowerCase();
					if (!item || seen.indexOf(key) !== -1) {
						return false;
					}
					seen.push(key);
					return true;
				}).join(', ');
			});
		});

		initMediaPicker();
	});
})();