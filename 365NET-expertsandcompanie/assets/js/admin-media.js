/*!
 * 365CMS | Experts & Companies – Bildfelder (Upload, Mediathek, Vorschau).
 * Konfiguration aus <div id="cms-excomp-media-config" data-config="{…}">; DOM ausschließlich über
 * createElement/textContent/replaceChildren (keine HTML-Strings, CSP/Trusted-Types-konform).
 */
(() => {
  'use strict';

  const fields = Array.from(document.querySelectorAll('input[data-excomp-media-field]'));
  if (!fields.length) return;

  const $ = (selector, context = document) => context.querySelector(selector);
  const text = (value) => String(value ?? '');
  const config = (() => {
    const element = $('#cms-excomp-media-config');
    if (!element) return {};
    try {
      const parsed = JSON.parse(element.dataset.config || '{}');
      return parsed && typeof parsed === 'object' ? parsed : {};
    } catch (_) {
      return {};
    }
  })();

  const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg'];
  let activeInput = null;
  let modal = null;
  const mediaState = { loaded: false, items: [], query: '' };

  /** Element mit Klasse, Attributen und Kindern (Strings werden Textknoten). */
  const el = (tag, attrs = {}, children = []) => {
    const node = document.createElement(tag);
    Object.entries(attrs).forEach(([key, value]) => {
      if (value === false || value === null || value === undefined) return;
      if (key === 'className') node.className = value;
      else if (key === 'text') node.textContent = value;
      else if (key === 'hidden') node.hidden = Boolean(value);
      else node.setAttribute(key, value === true ? '' : String(value));
    });
    (Array.isArray(children) ? children : [children]).forEach((child) => {
      if (child === null || child === undefined) return;
      node.append(child instanceof Node ? child : document.createTextNode(String(child)));
    });
    return node;
  };

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
      return raw;
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
    if (type === 'danger') window.alert(message);
  };

  const fetchJson = (url, options = {}) => fetch(url, options).then((response) => response.json().catch(() => ({})).then((payload) => {
    if (!response.ok || payload?.success === 0 || payload?.success === false) {
      throw new Error(payload?.message || payload?.error || 'Media-Anfrage fehlgeschlagen.');
    }
    return payload;
  }));

  const updatePreview = (input, preview) => {
    // Nur die Vorschau normalisieren; den Feldwert beim Tippen nicht umschreiben (Server prüft beim Speichern).
    const value = normalizeMediaUrl(input.value);
    if (!value) {
      preview.replaceChildren();
      preview.hidden = true;
      return;
    }
    preview.replaceChildren(el('img', { src: value, alt: 'Bildvorschau', loading: 'lazy' }));
    preview.hidden = false;
  };

  const setInputValue = (input, value) => {
    input.value = normalizeMediaUrl(value);
    input.dispatchEvent(new Event('input', { bubbles: true }));
  };

  const closeModal = () => {
    if (modal) modal.classList.remove('is-open');
    if (activeInput) activeInput.focus();
    activeInput = null;
  };

  const emptyNotice = (message) => el('div', { className: 'cms-excomp-media-empty', text: message });

  const ensureModal = () => {
    if (modal) return modal;

    const search = el('input', { type: 'search', className: 'form-control', placeholder: 'Mediathek durchsuchen …', 'aria-label': 'Mediathek durchsuchen', 'data-excomp-media-search': true });
    const status = el('div', { className: 'cms-excomp-media-modal__status', 'data-excomp-media-status': true, 'aria-live': 'polite', text: 'Lade Medien …' });
    const grid = el('div', { className: 'cms-excomp-media-grid', 'data-excomp-media-grid': true });
    const close = el('button', { type: 'button', className: 'btn btn-outline-secondary btn-sm cms-excomp-media-modal__close', 'aria-label': 'Schließen' }, [
      el('i', { className: 'ti ti-x', 'aria-hidden': 'true' }),
    ]);

    modal = el('div', { className: 'cms-excomp-media-modal', role: 'dialog', 'aria-modal': 'true', 'aria-labelledby': 'cms-excomp-media-title' }, [
      el('div', { className: 'cms-excomp-media-modal__panel' }, [
        el('div', { className: 'cms-excomp-media-modal__head' }, [
          el('div', {}, [
            el('h2', { id: 'cms-excomp-media-title', text: 'Bild aus der Mediathek auswählen' }),
            el('p', { text: 'Ein Klick übernimmt das Bild direkt in das aktive Feld.' }),
          ]),
          close,
        ]),
        el('div', { className: 'cms-excomp-media-modal__toolbar' }, [search, status]),
        el('div', { className: 'cms-excomp-media-modal__body' }, [grid]),
      ]),
    ]);
    document.body.append(modal);

    close.addEventListener('click', closeModal);
    modal.addEventListener('click', (event) => { if (event.target === modal) closeModal(); });
    modal.addEventListener('keydown', (event) => { if (event.key === 'Escape') closeModal(); });
    search.addEventListener('input', () => {
      mediaState.query = text(search.value).trim().toLowerCase();
      renderMediaItems();
    });
    grid.addEventListener('click', (event) => {
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
    if (status) status.textContent = query ? `${items.length} Treffer` : `${items.length} Medien verfügbar`;

    const buttons = [];
    items.forEach((item) => {
      const url = normalizeMediaUrl(item.url || '');
      if (!url) return;
      const name = text(item.name || 'Bild');
      const button = el('button', { type: 'button', className: 'cms-excomp-media-item', 'data-excomp-media-url': url, title: name }, [
        el('span', { className: 'cms-excomp-media-item__image' }, [el('img', { src: url, alt: name, loading: 'lazy' })]),
        el('span', { className: 'cms-excomp-media-item__meta' }, [
          el('span', { className: 'cms-excomp-media-item__name', text: name }),
          el('span', { className: 'cms-excomp-media-item__path', text: text(item.path || url) }),
        ]),
      ]);
      buttons.push(button);
    });

    grid.replaceChildren(...(buttons.length ? buttons : [emptyNotice('Keine passenden Bilder gefunden.')]));
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
      headers: csrfToken ? { 'X-CSRF-Token': csrfToken, 'X-Requested-With': 'XMLHttpRequest' } : { 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    }).then((payload) => {
      mediaState.items = Array.isArray(payload.items) ? payload.items : [];
      mediaState.loaded = true;
      renderMediaItems();
    }).catch((error) => {
      if (status) status.textContent = 'Laden fehlgeschlagen';
      const grid = $('[data-excomp-media-grid]', dialog);
      if (grid) grid.replaceChildren(emptyNotice(error.message || 'Medien konnten nicht geladen werden.'));
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
    const extension = text(file.name).split('.').pop().toLowerCase();
    if (!ALLOWED_EXTENSIONS.includes(extension)) {
      showMessage('danger', 'Erlaubt sind JPG, PNG, GIF, WebP, AVIF und SVG.');
      return;
    }
    if (file.size > maxSizeMb * 1024 * 1024) {
      showMessage('danger', `Das Bild ist größer als ${maxSizeMb} MB.`);
      return;
    }

    const uploadUrl = config.uploadUrl || '/api/experts-companie/media-upload';
    const csrfToken = config.csrfToken || '';
    const formData = new FormData();
    formData.append('file', file, file.name);
    formData.append('csrf_token', csrfToken);
    const label = button.textContent;
    button.disabled = true;
    button.textContent = 'Upload …';

    fetchJson(uploadUrl, {
      method: 'POST',
      headers: csrfToken ? { 'X-CSRF-Token': csrfToken } : {},
      body: formData,
      credentials: 'same-origin',
    }).then((payload) => {
      setInputValue(input, payload?.file?.url || payload?.url || '');
      mediaState.loaded = false;
      showMessage('success', `Bild wurde in ${config.uploadFolder || '/uploads/experts-companie/'} hochgeladen.`);
    }).catch((error) => {
      showMessage('danger', error.message || 'Upload fehlgeschlagen.');
    }).finally(() => {
      button.disabled = false;
      button.textContent = label;
    });
  };

  fields.forEach((input, index) => {
    if (input.dataset.excompMediaEnhanced === '1') return;
    input.dataset.excompMediaEnhanced = '1';
    input.id ||= `cms-excomp-media-field-${index + 1}`;

    const fileInput = el('input', { type: 'file', accept: ALLOWED_EXTENSIONS.map((ext) => `.${ext}`).join(','), hidden: true, 'aria-hidden': 'true', tabindex: '-1' });
    const uploadButton = el('button', { type: 'button', className: 'btn btn-sm btn-outline-secondary' }, 'Upload');
    const libraryButton = el('button', { type: 'button', className: 'btn btn-sm btn-outline-secondary' }, 'Mediathek');
    const clearButton = el('button', { type: 'button', className: 'btn btn-sm btn-outline-danger' }, 'Leeren');
    const tools = el('div', { className: 'cms-excomp-media-tools' }, [uploadButton, libraryButton, clearButton, fileInput]);
    const preview = el('div', { className: 'cms-excomp-media-preview', hidden: true });

    input.after(tools, preview);

    uploadButton.addEventListener('click', () => fileInput.click());
    fileInput.addEventListener('change', () => {
      uploadMediaFile(fileInput.files?.[0] || null, input, uploadButton);
      fileInput.value = '';
    });
    libraryButton.addEventListener('click', () => openMediaPicker(input));
    clearButton.addEventListener('click', () => setInputValue(input, ''));
    input.addEventListener('input', () => updatePreview(input, preview));
    input.addEventListener('change', () => {
      input.value = normalizeMediaUrl(input.value);
      updatePreview(input, preview);
    });
    updatePreview(input, preview);
  });
})();
