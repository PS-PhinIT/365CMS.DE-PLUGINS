(() => {
  const root = document;
  const fields = Array.from(root.querySelectorAll('input[data-excomp-media-field]'));
  if (!fields.length) return;

  const $ = (selector, context = document) => context.querySelector(selector);
  const esc = (value) => String(value ?? '').replace(/[&<>'"]/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' }[char]));
  const text = (value) => String(value ?? '');
  const config = (() => {
    const element = $('#cms-excomp-media-config');
    if (!element) return {};
    try {
      const parsed = JSON.parse(element.textContent || '{}');
      return parsed && typeof parsed === 'object' ? parsed : {};
    } catch (_) {
      return {};
    }
  })();

  let activeInput = null;
  let modal = null;
  const mediaState = { loaded: false, items: [], query: '' };

  const normalizeMediaUrl = (value) => {
    const raw = text(value).trim();
    if (!raw) return '';
    if (/^https?:\/\//i.test(raw)) {
      try {
        const url = new URL(raw, window.location.origin);
        if (/^(uploads|media|media-file)$/i.test(url.hostname)) return `/${url.hostname}${url.pathname}${url.search}${url.hash}`;
        if (url.origin === window.location.origin) return `${url.pathname}${url.search}${url.hash}`;
      } catch (_) {
        return '';
      }
    }
    if (/^(uploads|media|media-file)(\/|\?|$)/i.test(raw)) return `/${raw.replace(/^\/+/, '')}`;
    try {
      const url = new URL(raw, window.location.href);
      if (url.protocol !== 'http:' && url.protocol !== 'https:') return '';
    } catch (_) {
      return '';
    }
    return raw;
  };

  const showMessage = (type, message) => {
    if (typeof window.cmsAlert === 'function') {
      window.cmsAlert(type === 'danger' ? 'danger' : 'success', message);
      return;
    }
    console[type === 'danger' ? 'error' : 'log'](message);
  };

  const fetchJson = (url, options = {}) => fetch(url, options).then((response) => response.json().catch(() => ({})).then((payload) => {
    if (!response.ok || payload?.success === 0 || payload?.success === false) {
      throw new Error(payload?.message || payload?.error || 'Media-Anfrage fehlgeschlagen.');
    }
    return payload;
  }));

  const updatePreview = (input, preview) => {
    const value = normalizeMediaUrl(input.value);
    input.value = value;
    preview.innerHTML = '';
    if (!value) {
      preview.hidden = true;
      return;
    }
    const image = document.createElement('img');
    image.src = value;
    image.alt = 'Bildvorschau';
    image.loading = 'lazy';
    preview.append(image);
    preview.hidden = false;
  };

  const setInputValue = (input, value) => {
    input.value = normalizeMediaUrl(value);
    input.dispatchEvent(new Event('input', { bubbles: true }));
  };

  const closeModal = () => {
    if (modal) modal.classList.remove('is-open');
    activeInput = null;
  };

  const ensureModal = () => {
    if (modal) return modal;
    modal = document.createElement('div');
    modal.className = 'cms-excomp-media-modal';
    modal.setAttribute('role', 'dialog');
    modal.setAttribute('aria-modal', 'true');
    modal.innerHTML = `
      <div class="cms-excomp-media-modal__panel">
        <div class="cms-excomp-media-modal__head">
          <div><h2>Bild aus der Mediathek auswählen</h2><p>Ein Klick übernimmt das Bild direkt in das aktive Feld.</p></div>
          <button type="button" class="cms-excomp-media-modal__close" data-excomp-media-close aria-label="Schließen">×</button>
        </div>
        <div class="cms-excomp-media-modal__toolbar">
          <input type="search" placeholder="Mediathek durchsuchen …" data-excomp-media-search>
          <div class="cms-excomp-media-modal__status" data-excomp-media-status>Lade Medien …</div>
        </div>
        <div class="cms-excomp-media-modal__body"><div class="cms-excomp-media-grid" data-excomp-media-grid></div></div>
      </div>`;
    document.body.append(modal);
    $('[data-excomp-media-close]', modal)?.addEventListener('click', closeModal);
    modal.addEventListener('click', (event) => { if (event.target === modal) closeModal(); });
    $('[data-excomp-media-search]', modal)?.addEventListener('input', (event) => {
      mediaState.query = text(event.target.value).trim().toLowerCase();
      renderMediaItems();
    });
    $('[data-excomp-media-grid]', modal)?.addEventListener('click', (event) => {
      const button = event.target instanceof Element ? event.target.closest('[data-excomp-media-url]') : null;
      if (!button || !activeInput) return;
      setInputValue(activeInput, button.dataset.excompMediaUrl || '');
      closeModal();
    });
    return modal;
  };

  const renderMediaItems = () => {
    const dialog = ensureModal();
    const grid = $('[data-excomp-media-grid]', dialog);
    const status = $('[data-excomp-media-status]', dialog);
    if (!grid) return;
    const query = mediaState.query;
    const items = query ? mediaState.items.filter((item) => `${item.name || ''} ${item.path || ''}`.toLowerCase().includes(query)) : mediaState.items;
    grid.innerHTML = '';
    if (status) status.textContent = query ? `${items.length} Treffer` : `${items.length} Medien verfügbar`;
    if (!items.length) {
      grid.innerHTML = '<div class="cms-excomp-media-empty">Keine passenden Bilder gefunden.</div>';
      return;
    }
    items.forEach((item) => {
      const url = normalizeMediaUrl(item.url || '');
      if (!url) return;
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'cms-excomp-media-item';
      button.dataset.excompMediaUrl = url;
      button.innerHTML = `<span class="cms-excomp-media-item__image"><img src="${esc(url)}" alt="${esc(item.name || 'Bild')}" loading="lazy"></span><span class="cms-excomp-media-item__meta"><span class="cms-excomp-media-item__name">${esc(item.name || 'Bild')}</span><span class="cms-excomp-media-item__path">${esc(item.path || url)}</span></span>`;
      grid.append(button);
    });
  };

  const loadMediaItems = () => {
    if (mediaState.loaded) {
      renderMediaItems();
      return Promise.resolve();
    }
    const dialog = ensureModal();
    const status = $('[data-excomp-media-status]', dialog);
    if (status) status.textContent = 'Lade Medien …';
    const libraryUrl = config.libraryUrl || '/api/media';
    const csrfToken = config.csrfToken || '';
    return fetchJson(`${libraryUrl}?action=list_images`, {
      method: 'GET',
      headers: csrfToken ? { 'X-CSRF-Token': csrfToken } : {},
      credentials: 'same-origin'
    }).then((payload) => {
      mediaState.items = Array.isArray(payload.items) ? payload.items : [];
      mediaState.loaded = true;
      renderMediaItems();
    }).catch((error) => {
      if (status) status.textContent = 'Laden fehlgeschlagen';
      const grid = $('[data-excomp-media-grid]', dialog);
      if (grid) grid.innerHTML = `<div class="cms-excomp-media-empty">${esc(error.message || 'Medien konnten nicht geladen werden.')}</div>`;
    });
  };

  const openMediaPicker = (input) => {
    activeInput = input;
    const dialog = ensureModal();
    dialog.classList.add('is-open');
    loadMediaItems();
    window.setTimeout(() => $('[data-excomp-media-search]', dialog)?.focus(), 50);
  };

  const uploadMediaFile = (file, input, button) => {
    if (!file) return;
    const maxSizeMb = Number(config.maxSizeMb || 10);
    const maxSize = maxSizeMb * 1024 * 1024;
    const extension = text(file.name).split('.').pop().toLowerCase();
    const allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'ico', 'svg'];
    if (file.type && !file.type.startsWith('image/') && !allowedExtensions.includes(extension)) {
      showMessage('danger', 'Bitte eine Bilddatei auswählen.');
      return;
    }
    if (file.size > maxSize) {
      showMessage('danger', `Das Bild ist größer als ${maxSizeMb} MB.`);
      return;
    }

    const uploadUrl = config.uploadUrl || '/api/experts-companie/media-upload';
    const csrfToken = config.csrfToken || '';
    const formData = new FormData();
    formData.append('file', file, file.name);
    formData.append('csrf_token', csrfToken);
    button.disabled = true;
    button.textContent = 'Upload …';

    fetchJson(uploadUrl, {
      method: 'POST',
      headers: csrfToken ? { 'X-CSRF-Token': csrfToken } : {},
      body: formData,
      credentials: 'same-origin'
    }).then((payload) => {
      const url = payload?.file?.url || payload?.url || '';
      setInputValue(input, url);
      mediaState.loaded = false;
      showMessage('success', `Bild wurde in ${config.uploadFolder || '/uploads/experts-companie/'} hochgeladen.`);
    }).catch((error) => {
      showMessage('danger', error.message || 'Upload fehlgeschlagen.');
    }).finally(() => {
      button.disabled = false;
      button.textContent = 'Upload';
    });
  };

  fields.forEach((input, index) => {
    if (input.dataset.excompMediaEnhanced === '1') return;
    input.dataset.excompMediaEnhanced = '1';
    input.id ||= `cms-excomp-media-field-${index + 1}`;
    const label = input.closest('label');
    if (label) label.classList.add('cms-excomp-media-label');

    const tools = document.createElement('div');
    tools.className = 'cms-excomp-media-tools';
    tools.innerHTML = '<button type="button" class="cms-excomp-media-btn" data-excomp-media-upload>Upload</button><button type="button" class="cms-excomp-media-btn" data-excomp-media-library>Mediathek</button><button type="button" class="cms-excomp-media-btn is-danger" data-excomp-media-clear>Leeren</button><input type="file" accept="image/*" hidden data-excomp-media-file><small class="cms-excomp-media-note">Uploads werden im Ordner /uploads/experts-companie gespeichert.</small>';
    const preview = document.createElement('div');
    preview.className = 'cms-excomp-media-preview';
    preview.hidden = true;

    input.insertAdjacentElement('afterend', tools);
    tools.insertAdjacentElement('afterend', preview);

    const fileInput = $('[data-excomp-media-file]', tools);
    const uploadButton = $('[data-excomp-media-upload]', tools);
    uploadButton?.addEventListener('click', () => fileInput?.click());
    fileInput?.addEventListener('change', () => uploadMediaFile(fileInput.files?.[0] || null, input, uploadButton));
    $('[data-excomp-media-library]', tools)?.addEventListener('click', () => openMediaPicker(input));
    $('[data-excomp-media-clear]', tools)?.addEventListener('click', () => setInputValue(input, ''));
    input.addEventListener('input', () => updatePreview(input, preview));
    updatePreview(input, preview);
  });
})();
