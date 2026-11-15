(() => {
  // Leere/ungültige Selektoren (z. B. '#' ohne Ziel-ID) liefern null statt das gesamte Skript abzubrechen.
  const $ = (selector, scope = document) => {
    try {
      return scope.querySelector(selector);
    } catch (_) {
      return null;
    }
  };
  const $$ = (selector, scope = document) => [...scope.querySelectorAll(selector)];
  const text = (value) => String(value ?? '');
  /**
   * Einzige HTML-Senke des Builders: Templates escapen alle Werte mit esc(); zusätzlich wird das Markup
   * über DOMPurify als TrustedHTML erzeugt (CSP `require-trusted-types-for 'script'`). Ohne DOMPurify
   * greift die Default-Policy aus cms-csp-runtime.js (ebenfalls DOMPurify, fail closed).
   */
  const setHtml = (element, html) => {
    if (!element) return;
    const markup = String(html ?? '');
    if (markup === '') {
      element.replaceChildren();
      return;
    }
    const purifier = window.DOMPurify;
    element.innerHTML = purifier && typeof purifier.sanitize === 'function'
      ? purifier.sanitize(markup, { RETURN_TRUSTED_TYPE: true, ADD_ATTR: ['target'] })
      : markup;
  };
  const builderOpenSectionIndexes = new Set();
  let builderSectionOpenStateReady = false;

  const SECTION_TYPES = {
    partner_band: 'Partnerband',
    proof: 'Belegbare Grundlagen',
    booking: 'Terminbuchung',
    text: 'Textbereich',
    card_grid: 'Card Grid',
    image_cards: 'Bildkarten Bereich',
    services: 'Meine Leistungen',
    offers: 'Angebotsbereich',
    comparison: 'Vergleich / Infografik',
    steps: 'Ablauf Modul',
    cta: 'CTA Band',
    trust: 'Trust Bereich',
    technology: 'Technologie Bereich',
    contact: 'Kontaktbereich',
    divider: 'Trenner / Separator',
    html: 'Freier HTML Bereich',
    infographic: 'Infografik Bereich'
  };

  const MODULE_PRESETS = {
    partner_band: 'Partnerband',
    proof: 'Belegbare Grundlagen',
    booking: 'Terminbuchung',
    collaboration: 'Zusammenarbeit Band',
    services: 'Meine Leistungen',
    steps: 'So läuft die Zusammenarbeit ab',
    comparison: 'Vergleichsmodul',
    trust: 'Trust Bereich',
    technology: 'Technologie Bereich',
    cta: 'CTA Band',
    divider: 'Trenner',
    html: 'Freier HTML Bereich'
  };

  const TARGET_SECTION_ORDER = ['proof', 'partner_band', 'comparison', 'services', 'steps', 'collaboration', 'trust', 'technology', 'html', 'booking', 'divider', 'cta'];
  const TARGET_SECTION_NAMES = {
    proof: 'Grundlagen',
    partner_band: 'Partnerband',
    comparison: 'Vergleich GenAI Agentic AI',
    services: 'Meine Leistungen',
    steps: 'Beratungsablauf',
    collaboration: 'Zusammenarbeit',
    trust: 'Trust Bereich',
    technology: 'Technologie Bereich',
    html: 'Freier HTML Bereich',
    booking: 'Terminbuchung',
    divider: 'Zitat Trenner',
    cta: 'CTA Band Copilot Readiness'
  };

  const CARD_THEMES = {
    default: 'Standard / neutral',
    experience: 'Erfahrung',
    consulting: 'Beratung',
    exchange: 'Austausch',
    iamcp: 'IAMCP Mitglied',
    projects: 'Projekte',
    security: 'Security',
    governance: 'Governance',
    microsoft: 'Microsoft Cloud',
    network: 'Netzwerk'
  };

  const SINGLETON_SECTION_TYPES = ['partner_band', 'proof', 'booking', 'collaboration', 'comparison', 'services', 'steps', 'trust', 'technology', 'html', 'divider', 'cta'];

  const LANDINGPAGE_PROPOSALS = {
    copilot_readiness: 'Copilot Readiness Landingpage',
    security_review: 'Microsoft 365 Security Review',
    governance_workshop: 'SharePoint Governance Workshop',
    admin_automation: 'Admin Workshop & PowerShell Automatisierung'
  };

  const CARD_TYPES = {
    text: 'Klassische Text Card',
    image_label: 'Bild Card mit Label',
    offer_icon_tab: 'Angebots Card mit Icon Reiter',
    step: 'Step Card',
    problem_solution: 'Problem Lösung Card'
  };

  const TARGET_TYPES = {
    internal: 'Interne URL',
    external: 'Externe URL',
    anchor: 'Anker auf der Seite',
    contact: 'Kontaktformular',
    email: 'E-Mail Link',
    phone: 'Telefon Link',
    bookings: 'Microsoft Bookings Link',
    download: 'Download Link'
  };

  const DEFAULT_HERO_TRUST_BADGES = ['Ex-Microsoft MVP', '20+ Jahre', 'LPIC 1 & 2', 'Microsoft zertifiziert'];

  let mediaFieldCounter = 0;
  let mediaPickerModal = null;
  let activeMediaInput = null;
  const mediaPickerState = { items: [], loaded: false, query: '' };

  const mediaConfig = (() => {
    const element = $('#beratung-media-config');
    if (!element) return {};
    try {
      const parsed = JSON.parse(element.textContent || '{}');
      return parsed && typeof parsed === 'object' ? parsed : {};
    } catch (_) {
      return {};
    }
  })();

  const expertOptions = (() => {
    const element = $('#beratung-expert-options');
    if (!element) return [];
    try {
      const parsed = JSON.parse(element.textContent || '{}');
      return Array.isArray(parsed?.experts) ? parsed.experts : [];
    } catch (_) {
      return [];
    }
  })();

  const normalizeIds = (value) => {
    let raw = value;
    if (typeof raw === 'string') {
      try {
        const parsed = JSON.parse(raw);
        raw = Array.isArray(parsed) ? parsed : raw.split(/[\s,;]+/);
      } catch (_) {
        raw = raw.split(/[\s,;]+/);
      }
    } else if (typeof raw === 'number') {
      raw = [raw];
    }
    if (!Array.isArray(raw)) return [];
    return [...new Set(raw.map((entry) => Number(typeof entry === 'object' && entry !== null ? (entry.id || entry.value || 0) : entry)).filter(Boolean))].slice(0, 24);
  };

  const expertOptionHtml = (selectedIds = []) => {
    const selected = new Set(normalizeIds(selectedIds));
    if (!expertOptions.length) return '<option value="" disabled>Keine Experts aus CMS-Expertsandcompanie gefunden</option>';
    return expertOptions.map((expert) => `<option value="${Number(expert.id || 0)}"${selected.has(Number(expert.id || 0)) ? ' selected' : ''}>${esc(expert.label || expert.name || `Expert #${expert.id}`)}${expert.is_mvp ? ' · MVP' : ''}</option>`).join('');
  };

  const parseJson = (textarea, fallback) => {
    try {
      const data = JSON.parse(textarea.value || '');
      return data && typeof data === 'object' ? data : fallback;
    } catch (_) {
      return fallback;
    }
  };

  const syncJson = (textarea, value) => { textarea.value = JSON.stringify(value, null, 2); };
  const optionHtml = (options, selected) => Object.entries(options).map(([value, label]) => `<option value="${value}"${String(value) === String(selected) ? ' selected' : ''}>${label}</option>`).join('');

  const sectionOrderRank = (section, fallbackIndex = 0) => {
    const stored = Number(section?.sort_order || 0);
    if (Number.isFinite(stored) && stored > 0) return stored;
    const targetIndex = TARGET_SECTION_ORDER.indexOf(section?.type || '');
    return targetIndex >= 0 ? targetIndex + 1 : 1000 + fallbackIndex;
  };

  const updateSectionSortOrders = (sections) => {
    sections.forEach((section, index) => { if (section && typeof section === 'object') section.sort_order = index + 1; });
  };

  const sortSectionsByOrder = (sections) => {
    const originalIndexes = new Map(sections.map((section, index) => [section, index]));
    sections.sort((a, b) => sectionOrderRank(a, originalIndexes.get(a) || 0) - sectionOrderRank(b, originalIndexes.get(b) || 0));
    updateSectionSortOrders(sections);
    return sections;
  };

  const normalizeMediaUrl = (value) => {
    const raw = text(value).trim();
    if (!raw) return '';
    if (/^https?:\/\//i.test(raw)) {
      try {
        const url = new URL(raw, window.location.origin);
        if (url.origin === window.location.origin) return `${url.pathname}${url.search}${url.hash}`;
      } catch (_) {
        return raw;
      }
    }
    if (/^(uploads|media|media-file)(\/|\?|$)/i.test(raw)) return `/${raw.replace(/^\/+/, '')}`;
    return raw;
  };

  const showMediaMessage = (type, message) => {
    if (typeof window.cmsAlert === 'function') {
      window.cmsAlert(type === 'danger' ? 'danger' : 'success', message);
      return;
    }
    console[type === 'danger' ? 'error' : 'log'](message);
  };

  const fetchMediaJson = (url, options = {}) => fetch(url, options).then((response) => response.json().catch(() => ({})).then((payload) => {
    if (!response.ok || payload?.success === 0 || payload?.success === false) {
      throw new Error(payload?.message || payload?.error || 'Media-Anfrage fehlgeschlagen.');
    }
    return payload;
  }));

  const updateMediaPreview = (input, preview) => {
    if (!preview) return;
    const value = normalizeMediaUrl(input.value);
    preview.replaceChildren();
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

  const setInputMediaValue = (input, value) => {
    input.value = normalizeMediaUrl(value);
    input.dispatchEvent(new Event('input', { bubbles: true }));
  };

  const closeMediaPicker = () => {
    if (mediaPickerModal) mediaPickerModal.classList.remove('is-open');
    activeMediaInput = null;
  };

  const ensureMediaPickerModal = () => {
    if (mediaPickerModal) return mediaPickerModal;
    const modal = document.createElement('div');
    modal.className = 'beratung-media-modal';
    modal.setAttribute('role', 'dialog');
    modal.setAttribute('aria-modal', 'true');
    setHtml(modal, `
      <div class="beratung-media-modal__panel">
        <div class="beratung-media-modal__head">
          <div><h2>Bild aus der Mediathek auswählen</h2><p>Ein Klick übernimmt das Bild direkt in das aktive Feld.</p></div>
          <button type="button" class="beratung-media-modal__close" data-media-close aria-label="Schließen">×</button>
        </div>
        <div class="beratung-media-modal__toolbar">
          <input type="search" placeholder="Mediathek durchsuchen …" data-media-search>
          <div class="beratung-media-modal__status" data-media-status>Lade Medien …</div>
        </div>
        <div class="beratung-media-modal__body"><div class="beratung-media-grid" data-media-grid></div></div>
      </div>`);
    document.body.append(modal);
    $('[data-media-close]', modal)?.addEventListener('click', closeMediaPicker);
    modal.addEventListener('click', (event) => { if (event.target === modal) closeMediaPicker(); });
    $('[data-media-search]', modal)?.addEventListener('input', (event) => {
      mediaPickerState.query = text(event.target.value).trim().toLowerCase();
      renderMediaItems();
    });
    $('[data-media-grid]', modal)?.addEventListener('click', (event) => {
      const button = event.target.closest('[data-media-url]');
      if (!button || !activeMediaInput) return;
      setInputMediaValue(activeMediaInput, button.dataset.mediaUrl || '');
      closeMediaPicker();
    });
    mediaPickerModal = modal;
    return modal;
  };

  const renderMediaItems = () => {
    const modal = ensureMediaPickerModal();
    const grid = $('[data-media-grid]', modal);
    const status = $('[data-media-status]', modal);
    if (!grid) return;
    const query = mediaPickerState.query;
    const items = query ? mediaPickerState.items.filter((item) => `${item.name || ''} ${item.path || ''}`.toLowerCase().includes(query)) : mediaPickerState.items;
    grid.replaceChildren();
    if (status) status.textContent = query ? `${items.length} Treffer` : `${items.length} Medien verfügbar`;
    if (!items.length) {
      setHtml(grid, '<div class="beratung-media-empty">Keine passenden Bilder gefunden.</div>');
      return;
    }
    items.forEach((item) => {
      const url = normalizeMediaUrl(item.url || '');
      if (!url) return;
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'beratung-media-item';
      button.dataset.mediaUrl = url;
      setHtml(button, `<span class="beratung-media-item__image"><img src="${esc(url)}" alt="${esc(item.name || 'Bild')}" loading="lazy"></span><span class="beratung-media-item__meta"><span class="beratung-media-item__name">${esc(item.name || 'Bild')}</span><span class="beratung-media-item__path">${esc(item.path || url)}</span></span>`);
      grid.append(button);
    });
  };

  const loadMediaItems = () => {
    if (mediaPickerState.loaded) {
      renderMediaItems();
      return Promise.resolve();
    }
    const modal = ensureMediaPickerModal();
    const status = $('[data-media-status]', modal);
    const libraryUrl = mediaConfig.libraryUrl || '/api/media';
    const csrfToken = mediaConfig.csrfToken || '';
    if (status) status.textContent = 'Lade Medien …';
    return fetchMediaJson(`${libraryUrl}?action=list_images`, {
      method: 'GET',
      headers: csrfToken ? { 'X-CSRF-Token': csrfToken } : {},
      credentials: 'same-origin'
    }).then((payload) => {
      mediaPickerState.items = Array.isArray(payload.items) ? payload.items : [];
      mediaPickerState.loaded = true;
      renderMediaItems();
    }).catch((error) => {
      if (status) status.textContent = 'Laden fehlgeschlagen';
      const grid = $('[data-media-grid]', modal);
      if (grid) setHtml(grid, `<div class="beratung-media-empty">${esc(error.message || 'Medien konnten nicht geladen werden.')}</div>`);
    });
  };

  const openMediaPicker = (input) => {
    activeMediaInput = input;
    const modal = ensureMediaPickerModal();
    modal.classList.add('is-open');
    loadMediaItems();
    window.setTimeout(() => $('[data-media-search]', modal)?.focus(), 50);
  };

  const uploadMediaFile = (file, input, button) => {
    if (!file) return;
    const uploadUrl = mediaConfig.uploadUrl || '/api/cms-beratung/media-upload';
    const csrfToken = mediaConfig.csrfToken || '';
    const maxSizeMb = Number(mediaConfig.maxSizeMb || 10);
    const maxSize = maxSizeMb * 1024 * 1024;
    const extension = text(file.name).split('.').pop().toLowerCase();
    const allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'ico'];
    if (file.type && !file.type.startsWith('image/') && !allowedExtensions.includes(extension)) {
      showMediaMessage('danger', 'Bitte eine Bilddatei auswählen.');
      return;
    }
    if (file.size > maxSize) {
      showMediaMessage('danger', `Das Bild ist größer als ${maxSizeMb} MB.`);
      return;
    }
    const formData = new FormData();
    formData.append('file', file, file.name);
    formData.append('csrf_token', csrfToken);
    button.disabled = true;
    button.textContent = 'Upload …';
    fetchMediaJson(uploadUrl, {
      method: 'POST',
      headers: csrfToken ? { 'X-CSRF-Token': csrfToken } : {},
      body: formData,
      credentials: 'same-origin'
    }).then((payload) => {
      const url = payload?.file?.url || payload?.url || '';
      setInputMediaValue(input, url);
      mediaPickerState.loaded = false;
      showMediaMessage('success', `Bild wurde in ${mediaConfig.uploadFolder || '/uploads/beratung/'} hochgeladen.`);
    }).catch((error) => {
      showMediaMessage('danger', error.message || 'Upload fehlgeschlagen.');
    }).finally(() => {
      button.disabled = false;
      button.textContent = 'Upload';
    });
  };

  const enhanceMediaFields = (root) => {
    $$('input[data-field="image_url"], input[data-field="trust_image_url"], input[data-field="background_image_url"], input[data-field="divider_image_url"], input[data-card-field="image_url"], input[data-card-field="logo_url"], input[data-media-field]', root).forEach((input) => {
      if (input.dataset.beratungMediaEnhanced === '1') return;
      input.dataset.beratungMediaEnhanced = '1';
      input.id ||= `beratung-media-field-${++mediaFieldCounter}`;
      input.placeholder ||= '/uploads/beratung/... oder https://...';
      const label = input.closest('label');
      if (label) label.classList.add('beratung-media-label');
      const tools = document.createElement('div');
      tools.className = 'beratung-media-tools';
      setHtml(tools, '<button type="button" class="beratung-media-btn" data-media-upload>Upload</button><button type="button" class="beratung-media-btn" data-media-library>Mediathek</button><button type="button" class="beratung-media-btn is-danger" data-media-clear>Leeren</button><input type="file" accept="image/*" hidden data-media-file><small class="beratung-media-note">Uploads werden im Ordner /uploads/beratung gespeichert.</small>');
      const preview = document.createElement('div');
      preview.className = 'beratung-media-preview';
      preview.hidden = true;
      input.insertAdjacentElement('afterend', tools);
      tools.insertAdjacentElement('afterend', preview);
      const fileInput = $('[data-media-file]', tools);
      const uploadButton = $('[data-media-upload]', tools);
      uploadButton?.addEventListener('click', () => fileInput?.click());
      fileInput?.addEventListener('change', () => uploadMediaFile(fileInput.files?.[0] || null, input, uploadButton));
      $('[data-media-library]', tools)?.addEventListener('click', () => openMediaPicker(input));
      $('[data-media-clear]', tools)?.addEventListener('click', () => setInputMediaValue(input, ''));
      input.addEventListener('input', () => updateMediaPreview(input, preview));
      updateMediaPreview(input, preview);
    });
  };

  const bindInputs = (root, model, sync, callback = null) => {
    $$('[data-field]', root).forEach((input) => {
      const field = input.dataset.field;
      if (input.type === 'checkbox') input.checked = Boolean(model[field]);
      else input.value = text(model[field]);
      input.addEventListener('input', () => {
        if (input.type === 'checkbox') model[field] = input.checked;
        else if (input.type === 'number') model[field] = Number(input.value || 0);
        else model[field] = input.value;
        if (callback) callback(field);
        sync();
      });
      input.addEventListener('change', () => input.dispatchEvent(new Event('input')));
    });
  };

  const buttonDefaults = (textValue = '', target = '', style = 'primary') => ({ text: textValue, target, target_type: 'internal', style });

  const proofDefaultCards = () => [
    newCard('text', { badge: 'Erfahrung', title: '20+ Jahre Microsoft-Infrastruktur', text: 'Senior IT-Admin mit Schwerpunkt Microsoft 365, Azure, Exchange, PowerShell, IT-Security und Datenschutz/Compliance.', card_layout: 'classic', card_theme: 'experience' }),
    newCard('text', { badge: 'Prüfung', title: 'IHK-Prüfer', text: 'Prüfungsperspektive aus Ausbildung und Praxis. Das hilft bei klaren Standards, verständlicher Übergabe und sauberer Dokumentation.', card_layout: 'media-left', card_theme: 'consulting' }),
    newCard('text', { badge: 'Zertifizierung', title: 'Mehrfach zertifiziert', text: 'LPIC 1 & 2 sowie Microsoft-Zertifizierungen ergänzen die praktische Erfahrung aus Microsoft-Infrastruktur, Security und Automatisierung.', card_layout: 'spotlight', card_theme: 'microsoft' })
  ];

  const setCollapsibleState = (section, content, toggle, open) => {
    section.classList.toggle('is-open', open);
    section.classList.toggle('is-collapsed', !open);
    if (content) content.hidden = !open;
    if (toggle) {
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      toggle.textContent = open ? 'Einklappen' : 'Ausklappen';
    }
  };

  const enhanceEditorCollapsibleSections = () => {
    const editor = $('.beratung-editor');
    if (!editor || editor.dataset.collapsibleReady === '1') return;
    editor.dataset.collapsibleReady = '1';
    const sections = $$('.beratung-card', editor).filter((section) => section.querySelector(':scope > h1'));
    sections.forEach((section, index) => {
      if (section.dataset.collapsibleReady === '1') return;
      section.dataset.collapsibleReady = '1';
      section.classList.add('beratung-editor-section');
      const title = section.querySelector(':scope > h1');
      if (!title) return;
      const content = document.createElement('div');
      content.className = 'beratung-editor-section__body';
      while (title.nextSibling) content.append(title.nextSibling);
      const header = document.createElement('div');
      header.className = 'beratung-editor-section__head';
      const toggle = document.createElement('button');
      toggle.type = 'button';
      toggle.className = 'beratung-editor-section__toggle';
      const titleId = title.id || `beratung-editor-section-title-${index + 1}`;
      const contentId = `beratung-editor-section-body-${index + 1}`;
      title.id = titleId;
      content.id = contentId;
      toggle.setAttribute('aria-controls', contentId);
      toggle.setAttribute('aria-labelledby', titleId);
      header.append(title);
      if (section.dataset.alwaysOpen !== '1') header.append(toggle);
      section.append(header, content);
      if (section.dataset.alwaysOpen === '1') {
        section.classList.add('is-always-open');
        setCollapsibleState(section, content, null, true);
        return;
      }
      const open = section.dataset.defaultOpen === '1';
      setCollapsibleState(section, content, toggle, open);
      toggle.addEventListener('click', () => setCollapsibleState(section, content, toggle, content.hidden));
    });
  };

  const enhanceBuilderSectionCollapsible = (section, open, index) => {
    if (!section || section.dataset.builderCollapsibleReady === '1') return;
    section.dataset.builderCollapsibleReady = '1';
    const head = section.querySelector(':scope > .beratung-builder__section-head');
    if (!head) return;
    const content = document.createElement('div');
    content.className = 'beratung-builder__section-body';
    while (head.nextSibling) content.append(head.nextSibling);
    const toggle = document.createElement('button');
    toggle.type = 'button';
    toggle.className = 'beratung-builder__section-toggle';
    const contentId = `beratung-builder-section-body-${section.dataset.index || '0'}`;
    content.id = contentId;
    toggle.setAttribute('aria-controls', contentId);
    head.append(toggle);
    section.append(content);
    setCollapsibleState(section, content, toggle, open);
    if (open) builderOpenSectionIndexes.add(index);
    toggle.addEventListener('click', () => {
      const nextOpen = content.hidden;
      setCollapsibleState(section, content, toggle, nextOpen);
      if (nextOpen) builderOpenSectionIndexes.add(index);
      else builderOpenSectionIndexes.delete(index);
    });
  };

  const renderHeroBuilder = () => {
    const mount = $('#beratung-hero-builder');
    const target = $('#' + (mount?.dataset?.target || ''));
    if (!mount || !target) return;

    const hero = parseJson(target, {});
    hero.enabled ??= true;
    hero.image_position ??= 'left';
    hero.image_flush ??= true;
    hero.image_height ??= 520;
    hero.image_width ??= 46;
    hero.image_fit ??= 'cover';
    hero.badge_show ??= true;
    hero.vertical_align ??= 'center';
    hero.mobile_order ??= 'image-first';
    hero.button_1 ??= { text: 'Beratung anfragen', target: '#kontakt', target_type: 'contact', style: 'primary' };
    hero.button_2 ??= { text: 'Leistungen ansehen', target: '#leistungen', target_type: 'anchor', style: 'ghost' };
    hero.button_3 ??= buttonDefaults('', '', 'secondary');
    hero.partner_band_enabled ??= false;
    hero.partner_band_text ??= 'Zugehörig zum copilotberater.de Netzwerk';
    hero.partner_band_website_label ??= 'copilotberater.de';
    hero.partner_band_website_url ??= 'https://copilotberater.de';
    hero.partner_band_map_label ??= 'Copilotberater Deutschland Karte';
    hero.partner_band_map_url ??= 'https://copilotberater.de/copilotberater-deutschland-karte/';
    hero.anchor_nav_layout ??= 'pills';
    hero.toc_right_display ??= 'card';
    hero.toc_right_layout ??= 'card';
    hero.trust_image_url ??= '';
    hero.trust_image_enabled ??= true;
    hero.trust_image_alt ??= 'Portrait eines Microsoft 365 Beraters';
    if (!Array.isArray(hero.trust_badges)) hero.trust_badges = DEFAULT_HERO_TRUST_BADGES.slice();
    [0, 1, 2, 3].forEach((index) => { hero[`trust_badge_${index + 1}`] = text(hero.trust_badges[index]); });

    const sync = () => {
      hero.trust_badges = [hero.trust_badge_1, hero.trust_badge_2, hero.trust_badge_3, hero.trust_badge_4].map((value) => text(value).trim()).filter(Boolean);
      syncJson(target, hero);
    };
    mount.className = 'beratung-builder beratung-hero-builder';
    setHtml(mount, `
      <div class="beratung-builder__section is-hero">
        <div class="beratung-builder__section-head"><strong>Hero Bereich</strong><span>Content Header</span></div>
        <div class="beratung-builder__grid">
          <label><input data-field="enabled" type="checkbox"> Hero aktivieren</label>
          <label><input data-field="badge_show" type="checkbox"> Badge anzeigen</label>
          <label><input data-field="image_flush" type="checkbox"> Bild nahtlos am Rand anzeigen</label>
          <label>Bildposition <select data-field="image_position"><option value="left">links</option><option value="right">rechts</option></select></label>
          <label>Mobile Reihenfolge <select data-field="mobile_order"><option value="image-first">Bild zuerst</option><option value="text-first">Text zuerst</option></select></label>
          <label>Vertikale Ausrichtung <select data-field="vertical_align"><option value="start">oben</option><option value="center">mittig</option><option value="end">unten</option></select></label>
          <label>Bild URL / Mediathek-Pfad <input data-field="image_url" placeholder="/uploads/... oder https://..."></label>
          <label>Bild Alt Text <input data-field="image_alt"></label>
          <label>Bildhöhe <input data-field="image_height" type="number" min="180" max="900"></label>
          <label>Bildbreite in % <input data-field="image_width" type="number" min="25" max="70"></label>
          <label>Bild Zuschnitt <select data-field="image_fit"><option value="cover">Cover</option><option value="contain">Contain</option></select></label>
          <label>Badge Text <input data-field="badge_text"></label>
          <label>Trust Badge 1 <input data-field="trust_badge_1" placeholder="z. B. Ex-Microsoft MVP"></label>
          <label>Trust Badge 2 <input data-field="trust_badge_2" placeholder="z. B. 20+ Jahre"></label>
          <label>Trust Badge 3 <input data-field="trust_badge_3" placeholder="z. B. LPIC 1 & 2"></label>
          <label>Trust Badge 4 <input data-field="trust_badge_4" placeholder="z. B. Microsoft zertifiziert"></label>
          <label>Haupttitel <input data-field="title"></label>
          <label>Untertitel <input data-field="subtitle"></label>
          <label>Beschreibungstext <textarea data-field="description" rows="3"></textarea></label>
          <label>Trust Hinweis <input data-field="trust_text"></label>
          <label><input data-field="trust_image_enabled" type="checkbox"> Trust Hinweis Bild / SVG anzeigen</label>
          <label>Trust Hinweis Bild / SVG <input data-field="trust_image_url" placeholder="/uploads/beratung/... oder https://..."></label>
          <label>Trust Hinweis Bild Alt Text <input data-field="trust_image_alt"></label>
          <label>Hintergrundfarbe <input data-field="background_color" type="color"></label>
          <label>Textfarbe <input data-field="text_color" type="color"></label>
        </div>
        <div class="beratung-builder__cards is-buttons"></div>
      </div>`);
    bindInputs(mount, hero, sync);
    enhanceMediaFields(mount);
    renderButtons($('.is-buttons', mount), hero, sync, ['button_1', 'button_2', 'button_3']);
    sync();
  };

  const renderAnchorNavigationBuilder = () => {
    const mount = $('#beratung-anchor-nav-builder');
    const target = $('#' + (mount?.dataset?.target || ''));
    if (!mount || !target) return;
    const hero = parseJson(target, {});
    hero.anchor_nav_layout ??= 'pills';
    hero.toc_right_display ??= 'card';
    hero.toc_right_layout ??= 'card';
    const sync = () => syncJson(target, hero);
    mount.className = 'beratung-builder beratung-anchor-nav-builder';
    setHtml(mount, `
      <div class="beratung-builder__section is-anchor-nav">
        <div class="beratung-builder__section-head"><strong>Anker Navigation / rechtes TOC</strong><span>Public Navigation</span></div>
        <div class="beratung-builder__grid">
          <label>Layout unter dem Content Header <select data-field="anchor_nav_layout"><option value="pills">Pills mit Glas-Effekt</option><option value="cards">Karten Navigation</option><option value="goldbar">Goldene Netzwerk-Leiste</option><option value="minimal">Minimal Tabs</option><option value="threegrid">3er Breitenraster</option></select></label>
          <label>TOC rechts vom Content <select data-field="toc_right_display"><option value="card">Als rechte Card anzeigen</option><option value="off">Nicht rechts anzeigen</option></select></label>
          <label>Rechte TOC Card Layout <select data-field="toc_right_layout"><option value="card">Card mit Schatten</option><option value="compact">Kompakt</option><option value="outline">Outline / Minimal</option></select></label>
        </div>
        <p class="beratung-admin-hint">Die Linkziele entstehen automatisch aus den Anker IDs der aktivierten Inhaltsbereiche. Der globale Schalter „Inhaltsverzeichnis anzeigen“ aktiviert die rechte TOC Card; hier steuerst du Darstellung und Layout.</p>
      </div>`);
    bindInputs(mount, hero, sync);
    sync();
  };

  const renderContactBuilder = () => {
    const mount = $('#beratung-contact-builder');
    const target = $('#' + (mount?.dataset?.target || ''));
    if (!mount || !target) return;
    const contact = Object.assign({
      enabled: true,
      mode: 'form',
      layout: 'split-form',
      eyebrow: 'Kontakt',
      title: 'Beratungsanfrage senden',
      description: 'Beschreiben Sie kurz Ihr Anliegen.',
      image_url: '',
      image_width: 38,
      background_color: '#ffffff',
      text_color: '#111827',
      button_text: 'Kontakt aufnehmen',
      button_target: '#kontakt',
      button_target_type: 'contact',
      button_style: 'primary',
      button_2_text: '',
      button_2_target: '',
      button_2_target_type: 'internal',
      button_2_style: 'ghost',
      note_text: 'Hinweis: Ich melde mich in der Regel innerhalb von 1 bis 2 Werktagen mit einer ersten Einschätzung zurück.',
      anchor_id: 'kontakt',
      privacy_text: 'Ich stimme der Verarbeitung meiner Angaben zur Bearbeitung der Anfrage zu.',
      captcha_enabled: false
    }, parseJson(target, {}));
    const sync = () => syncJson(target, contact);
    mount.className = 'beratung-builder beratung-contact-builder';
    setHtml(mount, `
      <div class="beratung-builder__section is-contact">
        <div class="beratung-builder__section-head"><strong>Kontakt direkt bearbeiten</strong><span>Keine JSON-Bearbeitung notwendig</span></div>
        <div class="beratung-builder__grid">
          <label><input data-field="enabled" type="checkbox"> Kontaktmodul aktiv</label>
          <label><input data-field="captcha_enabled" type="checkbox"> Captcha aktivieren</label>
          <label>Modus <select data-field="mode"><option value="form">Formular anzeigen</option><option value="button">Nur Button anzeigen</option></select></label>
          <label>Kontakt Layout <select data-field="layout"><option value="split-form">Split: Text links, Formular rechts</option><option value="form-left">Formular links, Text rechts</option><option value="centered-card">Zentrierte Kontakt-Card</option><option value="compact-band">Kompaktes Kontaktband</option><option value="image-left-flush">Bild links nahtlos, Formular rechts</option></select></label>
          <label>Anker ID <input data-field="anchor_id" placeholder="kontakt"></label>
          <label>Oberzeile <input data-field="eyebrow"></label>
          <label>Überschrift <input data-field="title"></label>
          <label>Bild URL / Mediathek <input data-field="image_url" placeholder="/uploads/beratung/... oder https://..."></label>
          <label>Bildbreite in % <input data-field="image_width" type="number" min="25" max="60"></label>
          <label>Hintergrundfarbe <input data-field="background_color" type="color"></label>
          <label>Textfarbe <input data-field="text_color" type="color"></label>
          <label>Button Text <input data-field="button_text"></label>
          <label>Button Zieltyp <select data-field="button_target_type">${optionHtml(TARGET_TYPES, contact.button_target_type || 'contact')}</select></label>
          <label>Button Ziel <input data-field="button_target" placeholder="#kontakt, mail@domain.de, https://..."></label>
          <label>Button Stil <select data-field="button_style"><option value="primary">Primär</option><option value="secondary">Sekundär</option><option value="ghost">Ghost</option><option value="link">Link</option></select></label>
          <label>Button 2 Text <input data-field="button_2_text"></label>
          <label>Button 2 Zieltyp <select data-field="button_2_target_type">${optionHtml(TARGET_TYPES, contact.button_2_target_type || 'internal')}</select></label>
          <label>Button 2 Ziel <input data-field="button_2_target"></label>
          <label>Button 2 Stil <select data-field="button_2_style"><option value="primary">Primär</option><option value="secondary">Sekundär</option><option value="ghost">Ghost</option><option value="link">Link</option></select></label>
          <label class="beratung-builder__wide">Beschreibung <textarea data-field="description" rows="3"></textarea></label>
          <label class="beratung-builder__wide">Hinweistext <textarea data-field="note_text" rows="2"></textarea></label>
          <label class="beratung-builder__wide">Datenschutztext <textarea data-field="privacy_text" rows="3"></textarea></label>
        </div>
      </div>`);
    bindInputs(mount, contact, sync);
    enhanceMediaFields(mount);
    sync();
  };

  const renderDesignEditor = () => {
    const mount = $('#beratung-design-builder');
    const target = $('#' + (mount?.dataset?.target || ''));
    if (!mount || !target) return;
    const labels = {
      primary_color: 'Primärfarbe',
      secondary_color: 'Sekundärfarbe',
      accent_color: 'Akzentfarbe',
      background_color: 'Hintergrundfarbe',
      text_color: 'Textfarbe',
      heading_color: 'Überschriftenfarbe',
      button_color: 'Buttonfarbe',
      button_text_color: 'Button Textfarbe',
      card_background_color: 'Card Hintergrund',
      card_border_color: 'Card Rahmenfarbe'
    };
    const defaults = {
      primary_color: '#1e3a8a',
      secondary_color: '#0f172a',
      accent_color: '#d4af37',
      background_color: '#ffffff',
      text_color: '#111827',
      heading_color: '#0f172a',
      button_color: '#1e3a8a',
      button_text_color: '#ffffff',
      card_background_color: '#ffffff',
      card_border_color: '#e5e7eb',
      font_size_base: 16,
      font_size_hero: 52,
      font_size_section_title: 32,
      font_size_card_title: 21,
      header_spacing: 48,
      footer_spacing: 48,
      card_spacing: 24,
      section_content_spacing: 24
    };
    const spacingLabels = {
      font_size_base: 'Basis-Schriftgröße (px)',
      font_size_hero: 'Content Header Titelgröße (px)',
      font_size_section_title: 'Bereichstitel Größe (px)',
      font_size_card_title: 'Card-Titel Größe (px)',
      header_spacing: 'Abstand oben Landingpage (px)',
      footer_spacing: 'Abstand unten / Bereiche (px)',
      card_spacing: 'Card Innenabstand / Grid-Abstand (px)',
      section_content_spacing: 'Abstand Bereichstitel zu Inhalt (px)'
    };
    let design = {};
    const render = () => {
      design = Object.assign({}, defaults, parseJson(target, {}));
      mount.className = 'beratung-builder beratung-design-builder';
      setHtml(mount, `<div class="beratung-builder__section is-design"><div class="beratung-builder__section-head"><strong>Design direkt bearbeiten</strong><span>Farben, Schriftgrößen und Abstände ohne JSON anpassen</span></div><div class="beratung-builder__grid">${Object.entries(labels).map(([field, label]) => `<label>${label} <input data-field="${field}" type="color"></label>`).join('')}${Object.entries(spacingLabels).map(([field, label]) => `<label>${label} <input data-field="${field}" type="number" min="0" max="160" step="1"></label>`).join('')}<div class="beratung-builder__wide"><button type="button" class="beratung-btn beratung-btn--secondary" data-design-reset>Design-Felder leeren / globale Werte nutzen</button></div></div></div>`);
      bindInputs(mount, design, () => syncJson(target, design));
      $('[data-design-reset]', mount)?.addEventListener('click', () => {
        design = {};
        syncJson(target, design);
        render();
      });
    };
    render();
    window.addEventListener('beratung:design-updated', render);
  };

  const renderButtons = (wrap, model, sync, keys) => {
    keys.forEach((key, index) => {
      model[key] ??= buttonDefaults();
      const button = model[key];
      const box = document.createElement('div');
      box.className = 'beratung-builder__card is-button';
      setHtml(box, `
        <strong>Button ${index + 1}</strong>
        <label>Text <input data-button-field="text"></label>
        <label>Zieltyp <select data-button-field="target_type">${optionHtml(TARGET_TYPES, button.target_type || 'internal')}</select></label>
        <label>Ziel <input data-button-field="target"></label>
        <label>Stil <select data-button-field="style"><option value="primary">Primär</option><option value="secondary">Sekundär</option><option value="ghost">Ghost</option><option value="link">Link</option></select></label>`);
      $$('[data-button-field]', box).forEach((input) => {
        const field = input.dataset.buttonField;
        input.value = text(button[field]);
        input.addEventListener('input', () => { button[field] = input.value; sync(); });
        input.addEventListener('change', () => input.dispatchEvent(new Event('input')));
      });
      wrap.append(box);
    });
  };

  const baseSection = (index, type = 'card_grid') => ({
    id: `${type}-${index + 1}`,
    anchor_id: `${type}-${index + 1}`,
    sort_order: index + 1,
    enabled: true,
    type,
    internal_name: MODULE_PRESETS[type] || 'Neuer Bereich',
    eyebrow: '',
    title: MODULE_PRESETS[type] || 'Neuer Bereich',
    intro: '',
    background_color: type === 'cta' ? '#112347' : '#ffffff',
    background_image_url: '',
    text_color: type === 'cta' ? '#ffffff' : '#111827',
    padding_top: 56,
    padding_bottom: 56,
    max_width: 1200,
    text_align: 'left',
    columns: type === 'technology' ? 4 : 3,
    card_type: type === 'steps' ? 'step' : type === 'comparison' ? 'problem_solution' : 'text',
    card_design: 'standard',
    equal_height: true,
    categories_enabled: type === 'services',
    note_text: '',
    display_style: type === 'technology' ? 'icon_grid' : 'cards',
    expert_ids: [],
    mvp_note_enabled: type === 'collaboration',
    mvp_note_text: 'Darunter auch Microsoft MVPs aus dem 365 Network.',
    auto_number: true,
    connector: true,
    title_band_enabled: type === 'comparison',
    title_band_text: '',
    title_band_background_color: '#1e3a8a',
    title_band_text_color: '#ffffff',
    comparison_variant: 'two_columns',
    border_enabled: true,
    shadow_enabled: true,
    mobile_stack: true,
    faq_allow_multiple: false,
    faq_icon_style: 'plus',
    faq_open_behavior: 'none',
    faq_question_background_color: '#ffffff',
    faq_question_text_color: '#111827',
    faq_answer_background_color: '#f8fafc',
    faq_schema_enabled: true,
    button_1_text: '',
    button_1_target: '',
    button_1_target_type: 'internal',
    button_1_style: 'primary',
    button_2_text: '',
    button_2_target: '',
    button_2_target_type: 'internal',
    button_2_style: 'ghost',
    divider_type: 'line',
    divider_title: '',
    divider_subtitle: '',
    divider_icon: '',
    divider_image_url: '',
    divider_image_alt: '',
    divider_layout: 'centered',
    divider_image_width: 32,
    divider_line_color: '#dbeafe',
    divider_width: 100,
    divider_padding_left: 20,
    divider_padding_right: 20,
    divider_mobile_behavior: 'stack',
    booking_url: '',
    booking_display: 'embed',
    booking_button_text: 'Termin buchen',
    html: '',
    cards: []
  });

  const normalizeFlatSections = (sections) => {
    const legacyDefaultSectionIds = new Set(['intro', 'herausforderungen']);
    const list = (Array.isArray(sections) ? sections : []).filter((section) => section && typeof section === 'object' && section.type !== 'faq' && !legacyDefaultSectionIds.has(text(section.id)));
    const legacyHeroTarget = $('#hero_json');
    const legacyHero = legacyHeroTarget ? parseJson(legacyHeroTarget, {}) : {};
    const ensure = (type, position, defaults = {}) => {
      let section = list.find((item) => item?.type === type);
      if (!section) {
        section = Object.assign(baseSection(list.length, type), defaults, { type });
        list.splice(Math.min(position, list.length), 0, section);
      }
      section.type = type;
      section.enabled = section.enabled !== false;
      section.sort_order = Number(section.sort_order || position + 1);
      Object.entries(defaults).forEach(([key, value]) => { section[key] ??= value; });
      if (TARGET_SECTION_NAMES[type]) section.internal_name = TARGET_SECTION_NAMES[type];
      if (['partner_band', 'booking', 'collaboration', 'cta', 'divider', 'html'].includes(type)) {
        section.cards = [];
      } else {
        section.cards = Array.isArray(section.cards) ? section.cards : [];
      }
    };
    ensure('proof', 0, {
      id: 'belegbare-grundlagen',
      anchor_id: 'belegbare-grundlagen',
      internal_name: 'Grundlagen',
      eyebrow: 'Belegbare Grundlagen',
      title: 'Was nach Beratung greifbar wird',
      intro: 'Keine erfundenen Kundenzitate. Hier stehen nur nachvollziehbare Erfahrung, klare Projektbelege und später echte freigegebene Referenzen.',
      cards: proofDefaultCards()
    });
    ensure('partner_band', 1, {
      id: 'partnerband',
      anchor_id: 'partnerband',
      enabled: Boolean(legacyHero.partner_band_enabled),
      internal_name: 'Partnerband',
      eyebrow: 'Netzwerk',
      title: text(legacyHero.partner_band_text || 'Zugehörig zum copilotberater.de Netzwerk'),
      intro: text(legacyHero.partner_band_description || legacyHero.partner_band_intro || 'Einordnung, Netzwerkbezug und weiterführende Links zum Partnerangebot.'),
      partner_layout: 'network-card',
      button_1_text: text(legacyHero.partner_band_website_label || 'copilotberater.de'),
      button_1_target: text(legacyHero.partner_band_website_url || 'https://copilotberater.de'),
      button_1_target_type: 'external',
      button_1_style: 'primary',
      button_2_text: text(legacyHero.partner_band_map_label || 'Copilotberater Deutschland Karte'),
      button_2_target: text(legacyHero.partner_band_map_url || 'https://copilotberater.de/copilotberater-deutschland-karte/'),
      button_2_target_type: 'external',
      button_2_style: 'ghost'
    });
    const proofSection = list.find((item) => item?.type === 'proof');
    if (proofSection) {
      proofSection.card_type ??= 'text';
      proofSection.card_design ??= 'accent';
      proofSection.columns = Math.max(1, Math.min(4, Number(proofSection.columns || 3)));
      proofSection.cards = Array.isArray(proofSection.cards) && proofSection.cards.length > 0 ? proofSection.cards : proofDefaultCards();
    }
    ensure('comparison', 2, {
      id: 'vergleich-genai-agentic-ai',
      anchor_id: 'vergleich',
      internal_name: 'Vergleich GenAI Agentic AI',
      eyebrow: 'Einordnung',
      title: 'Vergleich GenAI Agentic AI',
      intro: 'GenAI und Agentic AI verständlich gegenüberstellen und für Microsoft 365 Szenarien einordnen.',
      columns: 2,
      card_type: 'problem_solution',
      comparison_variant: 'genai_agentic',
      title_band_enabled: true,
      title_band_text: 'GenAI vs. Agentic AI: Die wichtigsten Unterschiede',
      cards: [
        newCard('text', { icon: '✨', title: 'Generative KI', text: 'Erstellt Inhalte wie Texte, Bilder oder Code auf Basis großer Sprachmodelle.', extra_text: 'Typischer Einsatz: Content Erstellung, Ideenfindung, Automatisierung einfacher Aufgaben' }),
        newCard('text', { icon: '🧠', title: 'Agentic AI', text: 'Handelt zielorientierter, integriert Systeme und führt Aufgaben je nach Kontext teilautonom aus.', extra_text: 'Typischer Einsatz: Prozessautomatisierung, intelligente Assistenz, datenbasierte Entscheidungsunterstützung' })
      ]
    });
    ensure('services', 3, {
      id: 'leistungen',
      anchor_id: 'leistungen',
      internal_name: 'Meine Leistungen',
      eyebrow: 'Meine Leistungen',
      title: 'Microsoft 365 und Copilot Beratung aus der Praxis',
      intro: 'Frei sortierbare Leistungen für Tenant, Security, Compliance, Governance, Automatisierung und Workshops.'
    });
    ensure('steps', 4, {
      id: 'ablauf',
      anchor_id: 'ablauf',
      internal_name: 'Beratungsablauf',
      eyebrow: 'Vorgehen',
      title: 'So läuft die Zusammenarbeit ab',
      intro: 'Ein klarer Ablauf macht Beratung planbar, nachvollziehbar und technisch belastbar.',
      card_type: 'step',
      display_style: 'horizontal'
    });
    ensure('collaboration', 5, {
      id: 'zusammenarbeit',
      anchor_id: 'zusammenarbeit',
      internal_name: 'Zusammenarbeit',
      eyebrow: 'Zusammenarbeit',
      title: 'Expertinnen und Experten, mit denen ich bei dieser Dienstleistung zusammenarbeite',
      intro: 'Für spezialisierte Microsoft 365, Copilot, Security und Governance Themen arbeite ich mit ausgewählten Experts aus dem Netzwerk zusammen.',
      columns: 3,
      mvp_note_enabled: true,
      mvp_note_text: 'Darunter auch Microsoft MVPs aus dem 365 Network.'
    });
    ensure('trust', 6, {
      id: 'trust',
      anchor_id: 'vertrauen',
      internal_name: 'Trust Bereich',
      eyebrow: 'Vertrauen',
      title: 'Technische Beratung statt reines Folien Consulting',
      intro: 'Praxis, Betrieb und Sicherheit stehen im Mittelpunkt.'
    });
    ensure('technology', 7, {
      id: 'technologien',
      anchor_id: 'technologien',
      internal_name: 'Technologie Bereich',
      eyebrow: 'Technologien',
      title: 'Relevante Microsoft Technologien',
      intro: 'Beratung entlang der Plattformen, die im Microsoft 365 Betrieb wirklich zusammenspielen.',
      columns: 4,
      display_style: 'icon_grid'
    });
    ensure('html', 8, {
      id: 'html-hinweis',
      anchor_id: 'html-hinweis',
      internal_name: 'Freier HTML Bereich',
      eyebrow: 'Optional',
      title: 'Freier HTML Bereich',
      intro: 'Nur für berechtigte Benutzer. Unsichere Skripte werden gefiltert.'
    });
    ensure('booking', 9, {
      id: 'termin-buchen',
      anchor_id: 'termin-buchen',
      internal_name: 'Terminbuchung',
      eyebrow: 'Termin',
      title: 'Direkt einen Termin buchen',
      intro: 'Wähle einen passenden Slot für ein erstes Gespräch zu Microsoft 365, Copilot oder Security. Danach klären wir Ziel, Ausgangslage und den nächsten sinnvollen Schritt.',
      booking_url: '',
      booking_display: 'embed',
      booking_button_text: 'Termin buchen'
    });
    ensure('divider', 10, {
      id: 'trenner',
      anchor_id: 'trenner',
      internal_name: 'Zitat Trenner',
      divider_type: 'quote',
      divider_title: 'Gute Copilot Einführung beginnt nicht beim Prompt, sondern bei Daten, Identitäten und Governance.',
      divider_icon: '💬',
      divider_layout: 'centered',
      divider_image_width: 32
    });
    ensure('cta', 11, {
      id: 'cta-copilot-readiness',
      anchor_id: 'copilot-readiness',
      internal_name: 'CTA Band Copilot Readiness',
      eyebrow: 'Copilot Readiness',
      title: 'Du möchtest wissen, ob dein Microsoft 365 Tenant bereit für Copilot ist?',
      intro: 'Dann lass uns gemeinsam prüfen, wo Berechtigungen, Datenstruktur, Governance und Compliance wirklich stehen.',
      display_style: 'compact',
      background_color: '#112347',
      text_color: '#ffffff',
      button_1_text: 'Beratung anfragen',
      button_1_target: '#kontakt',
      button_1_target_type: 'contact',
      button_1_style: 'primary'
    });
    return sortSectionsByOrder(list);
  };

  const newSection = (index, type = 'card_grid') => {
    const section = baseSection(index, type);
    if (type === 'services') {
      section.anchor_id = 'leistungen';
      section.eyebrow = 'Meine Leistungen';
      section.title = 'Microsoft 365 und Copilot Beratung aus der Praxis';
      section.intro = 'Frei sortierbare Leistungen für Tenant, Security, Compliance, Governance, Automatisierung und Workshops.';
      section.button_1_text = 'Beratung anfragen';
      section.button_1_target = '#kontakt';
      section.button_1_target_type = 'contact';
      section.note_text = 'Alle Leistungen können als Einzeltermin, Workshop oder Projektbegleitung kombiniert werden.';
      section.cards = ['Microsoft 365 Tenant Check', 'Copilot Readiness Check', 'Entra ID Security Review'].map((title) => newCard('text', { title, category: 'Microsoft 365', icon: '✓' }));
    }
    if (type === 'faq') {
      section.anchor_id = 'faq';
      section.eyebrow = 'FAQ';
      section.title = 'Häufige Fragen zur Microsoft 365 Beratung';
      section.cards = [newCard('text', { question: 'Was ist Microsoft 365 Copilot?', answer: 'Microsoft 365 Copilot verbindet KI-Funktionen mit Microsoft 365 Apps und Unternehmensdaten.', default_open: false })];
    }
    if (type === 'divider') {
      section.title = '';
      section.intro = '';
      section.divider_title = 'Nächster Abschnitt';
    }
    if (type === 'collaboration') {
      section.anchor_id = 'zusammenarbeit';
      section.eyebrow = 'Zusammenarbeit';
      section.title = 'Expertinnen und Experten, mit denen ich bei dieser Dienstleistung zusammenarbeite';
      section.intro = 'Für spezialisierte Microsoft 365, Copilot, Security und Governance Themen arbeite ich mit ausgewählten Experts aus dem Netzwerk zusammen.';
      section.columns = 3;
      section.card_design = 'accent';
      section.equal_height = true;
      section.background_color = '#f8fafc';
      section.mvp_note_enabled = true;
      section.mvp_note_text = 'Darunter auch Microsoft MVPs aus dem 365 Network.';
    }
    if (type === 'partner_band') {
      section.id = 'partnerband';
      section.anchor_id = 'partnerband';
      section.internal_name = 'Partnerband';
      section.eyebrow = 'Netzwerk';
      section.title = 'Zugehörig zum copilotberater.de Netzwerk';
      section.intro = 'Einordnung, Netzwerkbezug und weiterführende Links zum Partnerangebot.';
      section.button_1_text = 'copilotberater.de';
      section.button_1_target = 'https://copilotberater.de';
      section.button_1_target_type = 'external';
      section.button_1_style = 'primary';
      section.button_2_text = 'Copilotberater Deutschland Karte';
      section.button_2_target = 'https://copilotberater.de/copilotberater-deutschland-karte/';
      section.button_2_target_type = 'external';
      section.button_2_style = 'ghost';
    }
    if (type === 'proof') {
      section.id = 'belegbare-grundlagen';
      section.anchor_id = 'belegbare-grundlagen';
      section.internal_name = 'Belegbare Grundlagen';
      section.eyebrow = 'Belegbare Grundlagen';
      section.title = 'Was nach Beratung greifbar wird';
      section.intro = 'Keine erfundenen Kundenzitate. Hier stehen nur nachvollziehbare Erfahrung, klare Projektbelege und später echte freigegebene Referenzen.';
      section.columns = 3;
      section.card_type = 'text';
      section.card_design = 'accent';
      section.cards = proofDefaultCards();
    }
    if (type === 'booking') {
      section.id = 'termin-buchen';
      section.anchor_id = 'termin-buchen';
      section.internal_name = 'Terminbuchung';
      section.eyebrow = 'Termin';
      section.title = 'Direkt einen Termin buchen';
      section.intro = 'Wähle einen passenden Slot für ein erstes Gespräch zu Microsoft 365, Copilot oder Security. Danach klären wir Ziel, Ausgangslage und den nächsten sinnvollen Schritt.';
      section.booking_url ||= '';
      section.booking_display ||= 'embed';
      section.booking_button_text ||= 'Termin buchen';
    }
    return section;
  };

  const newCard = (type = 'text', overrides = {}) => ({
    enabled: true,
    card_type: type,
    card_layout: 'classic',
    card_theme: 'default',
    title: 'Neue Card',
    text: '',
    extra_text: '',
    icon: '💡',
    category: '',
    metric: '',
    name: '',
    logo_url: '',
    question: '',
    answer: '',
    default_open: false,
    image_url: '',
    image_alt: '',
    image_height: 230,
    image_fit: 'cover',
    label: '',
    label_position: 'left',
    label_style: 'filled',
    button_label: '',
    button_url: '',
    badge: '',
    featured: false,
    background_color: '#ffffff',
    text_color: '#111827',
    border_enabled: true,
    border_color: '#dbeafe',
    shadow_enabled: true,
    hover_enabled: true,
    tab_enabled: true,
    icon_background: '#eff6ff',
    icon_color: '#2563eb',
    icon_text_layout: 'below',
    card_size: 'medium',
    step_number: '',
    auto_number: true,
    connector: true,
    problem_title: '',
    problem_text: '',
    solution_title: '',
    solution_text: '',
    risk_level: 'medium',
    recommendation: '',
    ...overrides
  });

  const proposalHero = (key) => ({
    enabled: true,
    image_url: '',
    image_position: 'left',
    image_flush: true,
    image_height: 520,
    image_width: 46,
    image_fit: 'cover',
    image_alt: LANDINGPAGE_PROPOSALS[key] || 'Microsoft 365 Beratung',
    badge_text: key === 'security_review' ? 'Security · Entra ID · Defender' : key === 'governance_workshop' ? 'SharePoint · OneDrive · Governance' : key === 'admin_automation' ? 'Admin Workshop · PowerShell · Betrieb' : 'Copilot · Readiness · Governance',
    badge_show: true,
    title: LANDINGPAGE_PROPOSALS[key] || 'Microsoft 365 Beratung',
    subtitle: 'Strukturierter Vorschlag mit Platzhaltertexten für eine hochwertige Beratungs-Landingpage.',
    description: 'Nutze diesen Vorschlag als Startpunkt und passe Inhalte, Cards und CTA direkt im Builder an. FAQs werden zentral im Menüpunkt M365 FAQs gepflegt.',
    button_1: { text: 'Beratung anfragen', target: '#kontakt', target_type: 'contact', style: 'primary' },
    button_2: { text: 'Leistungen ansehen', target: '#leistungen', target_type: 'anchor', style: 'ghost' },
    button_3: { text: 'Ablauf ansehen', target: '#ablauf', target_type: 'anchor', style: 'secondary' },
    trust_badges: key === 'security_review' ? ['Security Review', 'Entra ID', 'Defender', 'Conditional Access'] : key === 'governance_workshop' ? ['Governance', 'SharePoint', 'OneDrive', 'Teams'] : key === 'admin_automation' ? ['Admin Workshop', 'PowerShell', 'Dokumentation', 'Betrieb'] : DEFAULT_HERO_TRUST_BADGES.slice(),
    partner_band_enabled: false,
    partner_band_text: 'Zugehörig zum copilotberater.de Netzwerk',
    partner_band_website_label: 'copilotberater.de',
    partner_band_website_url: 'https://copilotberater.de',
    partner_band_map_label: 'Copilotberater Deutschland Karte',
    partner_band_map_url: 'https://copilotberater.de/copilotberater-deutschland-karte/',
    anchor_nav_layout: 'pills',
    trust_text: 'Platzhalter: Praxisnahe Beratung mit Fokus auf Sicherheit, Governance und Betrieb.',
    background_color: '#f8fafc',
    text_color: '#111827',
    vertical_align: 'center',
    mobile_order: 'image-first'
  });

  const proposalSections = (key) => {
    const services = newSection(0, 'services');
    const steps = newSection(1, 'steps');
    const comparison = newSection(2, 'comparison');
    const trust = newSection(4, 'trust');
    const cta = newSection(5, 'cta');

    services.cards = [];
    const serviceTitles = key === 'security_review'
      ? ['Entra ID Security Review', 'Conditional Access Bewertung', 'Microsoft Defender Review', 'Secure Score Einordnung', 'Admin Rollen Check', 'Dokumentation und Maßnahmenplan']
      : key === 'governance_workshop'
        ? ['SharePoint Governance Check', 'OneDrive Freigaben Review', 'Site Lifecycle Konzept', 'Berechtigungsmodell', 'Vorlagen und Namenskonzept', 'Admin Workshop']
        : key === 'admin_automation'
          ? ['Admin Workshop', 'PowerShell Automatisierung', 'Tenant Dokumentation', 'Exchange Online Analyse', 'Betriebsübergabe', 'Projektbegleitung']
          : ['Microsoft 365 Tenant Check', 'Copilot Readiness Check', 'SharePoint Governance', 'Microsoft Purview Beratung', 'Entra ID Security Review', 'Admin Workshop'];
    services.cards = serviceTitles.map((title, index) => newCard('text', {
      icon: ['🏢', '🤖', '🔐', '🧭', '🛡️', '⚙️'][index % 6],
      category: index < 2 ? 'Analyse' : index < 4 ? 'Governance' : 'Umsetzung',
      title,
      text: 'Platzhaltertext: Beschreibe hier Nutzen, Vorgehen und Ergebnis dieser Leistung.'
    }));

    steps.cards = ['Erstgespräch', 'Zielklärung', 'Technische Analyse', 'Bewertung', 'Maßnahmenplan', 'Umsetzung'].map((title, index) => newCard('step', {
      title,
      text: 'Platzhaltertext: Kurze Beschreibung dieses Schrittes.',
      auto_number: true,
      connector: index < 5
    }));

    comparison.title_band_text = key === 'copilot_readiness' ? 'Copilot bereit vs. Copilot riskant eingeführt' : key === 'security_review' ? 'Ohne Security Review vs. mit klarer Maßnahmenliste' : 'Ist-Zustand vs. Zielbild';
    comparison.cards = [
      newCard('text', { icon: '⚠️', title: 'Ist Zustand', text: 'Platzhalter: Unklare Berechtigungen, gewachsene Strukturen und fehlende Governance.', extra_text: 'Typische Folge: Risiken bleiben unsichtbar.' }),
      newCard('text', { icon: '✅', title: 'Zielbild', text: 'Platzhalter: Transparente Struktur, priorisierte Maßnahmen und klare Verantwortlichkeiten.', extra_text: 'Typische Folge: sicherer Betrieb und bessere Entscheidungsgrundlagen.' })
    ];

    trust.cards = ['Praxis aus echten Admin Umgebungen', 'Fokus auf Sicherheit und Betrieb', 'Verständliche Dokumentation'].map((title, index) => newCard('text', {
      icon: ['🏢', '🛡️', '📘'][index],
      metric: index === 0 ? '20+ Jahre' : '',
      title,
      text: 'Platzhaltertext: Erläutere hier den Vertrauensfaktor.'
    }));

    cta.eyebrow = 'Nächster Schritt';
    cta.title = 'Bereit für den nächsten Schritt?';
    cta.intro = 'Platzhaltertext: Lade Besucher zu einem unverbindlichen Gespräch oder einer Buchung ein.';
    cta.button_1_text = 'Beratung anfragen';
    cta.button_1_target = '#kontakt';
    cta.button_1_target_type = 'contact';
    cta.button_2_text = 'Leistungen ansehen';
    cta.button_2_target = '#leistungen';
    cta.button_2_target_type = 'anchor';
    cta.background_color = '#1e3a8a';
    cta.text_color = '#ffffff';

    return [services, steps, comparison, trust, cta];
  };

  const renderSectionBuilder = () => {
    const mount = $('#beratung-builder');
    const target = $('#' + (mount?.dataset?.target || ''));
    if (!mount || !target) return;

    let sections = normalizeFlatSections(parseJson(target, []));

    const sync = () => syncJson(target, sections);
    const rerender = () => { sync(); renderSectionBuilder(); };

    mount.className = 'beratung-builder beratung-root-builder';
    mount.replaceChildren();
    const toolbar = document.createElement('div');
    toolbar.className = 'beratung-builder__toolbar beratung-card beratung-root-builder__toolbar';
    setHtml(toolbar, `<div><button type="button" class="beratung-btn" data-add-basic>Bereich hinzufügen</button></div><label>Landingpage Vorschlag laden <select data-landingpage-proposal><option value="">Bitte wählen</option>${optionHtml(LANDINGPAGE_PROPOSALS, '')}</select></label><label>Root-Bereich hinzufügen <select data-add-module><option value="">Bitte wählen</option>${optionHtml(MODULE_PRESETS, '')}</select></label><span>Alles nach dem Content Header liegt flach in sections[] und kann per Hoch/Runter oder Drag and Drop sortiert werden.</span>`);
    $('[data-add-basic]', toolbar).addEventListener('click', () => { sections.push(newSection(sections.length)); rerender(); });
    $('[data-landingpage-proposal]', toolbar).addEventListener('change', (event) => {
      const value = event.target.value;
      if (!value) return;
      sections = normalizeFlatSections(proposalSections(value));
      const heroTarget = $('#hero_json');
      if (heroTarget) syncJson(heroTarget, proposalHero(value));
      rerender();
      renderHeroBuilder();
      renderAnchorNavigationBuilder();
    });
    $('[data-add-module]', toolbar).addEventListener('change', (event) => {
      const value = event.target.value;
      if (!value) return;
      if (SINGLETON_SECTION_TYPES.includes(value) && sections.some((section) => section?.type === value)) {
        event.target.value = '';
        return;
      }
      sections.push(newSection(sections.length, value));
      rerender();
    });
    mount.append(toolbar);

    sections.forEach((section, visibleIndex) => {
      const index = sections.indexOf(section);
      section.cards = Array.isArray(section.cards) ? section.cards : [];
      section.type ??= 'card_grid';
      section.card_type ??= 'text';
      section.sort_order = index + 1;
      section.anchor_id ??= section.id ?? `bereich-${index + 1}`;
      const box = document.createElement('section');
      box.className = 'beratung-builder__section beratung-card beratung-root-builder__section';
      box.draggable = true;
      box.dataset.index = String(index);
      setHtml(box, sectionTemplate(section, index));

      const updatePreview = () => { const preview = $('.beratung-live-preview', box); if (preview) setHtml(preview, sectionPreview(section)); };
      const updateVisibility = () => { updateSectionVisibility(box, section); updatePreview(); };
      bindInputs(box, section, sync, (field) => {
        if (field === 'type') {
          if (section.type === 'steps') section.card_type = 'step';
          if (section.type === 'comparison') section.card_type = 'problem_solution';
          if (section.type === 'technology') section.display_style ||= 'icon_grid';
          if (section.type === 'collaboration') {
            section.anchor_id ||= 'zusammenarbeit';
            section.eyebrow ||= 'Zusammenarbeit';
            section.title ||= 'Expertinnen und Experten, mit denen ich bei dieser Dienstleistung zusammenarbeite';
            section.intro ||= 'Für spezialisierte Microsoft 365, Copilot, Security und Governance Themen arbeite ich mit ausgewählten Experts aus dem Netzwerk zusammen.';
            section.columns = Math.min(4, Math.max(2, Number(section.columns || 3)));
            section.expert_ids = Array.isArray(section.expert_ids) ? section.expert_ids : [];
            section.mvp_note_enabled ??= true;
            section.mvp_note_text ||= 'Darunter auch Microsoft MVPs aus dem 365 Network.';
          }
        }
        if (field === 'columns' && section.type === 'collaboration') {
          section.columns = Math.min(4, Math.max(2, Number(section.columns || 3)));
        }
        updateVisibility();
      });
      const expertSelect = $('[data-expert-ids]', box);
      if (expertSelect) {
        section.expert_ids = normalizeIds(section.expert_ids);
        expertSelect.addEventListener('change', () => {
          section.expert_ids = normalizeIds([...expertSelect.selectedOptions].map((option) => option.value));
          sync();
          updatePreview();
        });
      }
      enhanceMediaFields(box);

      const cardsWrap = $('.beratung-builder__cards', box);
      section.cards.forEach((card, cardIndex) => cardsWrap.append(renderCard(card, section, cardIndex, sync, rerender)));

      $$('[data-action]', box).forEach((button) => button.addEventListener('click', () => {
        const action = button.dataset.action;
        if (action === 'delete') sections.splice(index, 1);
        if (action === 'duplicate') sections.splice(index + 1, 0, JSON.parse(JSON.stringify(section)));
        if (action === 'move-up' && index > 0) [sections[index - 1], sections[index]] = [sections[index], sections[index - 1]];
        if (action === 'move-down' && index < sections.length - 1) [sections[index + 1], sections[index]] = [sections[index], sections[index + 1]];
        if (action === 'add-card') section.cards.push(newCard(section.card_type));
        updateSectionSortOrders(sections);
        rerender();
      }));

      box.addEventListener('dragstart', (event) => event.dataTransfer?.setData('text/plain', `section:${index}`));
      box.addEventListener('dragover', (event) => event.preventDefault());
      box.addEventListener('drop', (event) => {
        event.preventDefault();
        const raw = event.dataTransfer?.getData('text/plain') || '';
        if (!raw.startsWith('section:')) return;
        const from = Number(raw.split(':')[1]);
        if (Number.isNaN(from) || from === index) return;
        const [moved] = sections.splice(from, 1);
        sections.splice(index, 0, moved);
        updateSectionSortOrders(sections);
        rerender();
      });

      updateVisibility();
      enhanceBuilderSectionCollapsible(box, builderSectionOpenStateReady ? builderOpenSectionIndexes.has(visibleIndex) : false, visibleIndex);
      mount.append(box);
    });
    builderSectionOpenStateReady = true;
    sync();
  };

  const esc = (value) => text(value).replace(/[&<>"]/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[char]));

  const cardPreview = (card, index, sectionType) => {
    if (card.enabled === false) return '';
    if (sectionType === 'faq') return `<div class="beratung-preview-faq"><button type="button">${esc(card.question || card.title || 'FAQ Frage')}<span>+</span></button><p>${esc(card.answer || card.text || 'Antwort Platzhalter')}</p></div>`;
    if (sectionType === 'steps') return `<article class="beratung-preview-card is-step"><b>${card.auto_number !== false ? index + 1 : esc(card.step_number || index + 1)}</b><h4>${esc(card.title || 'Schritt')}</h4><p>${esc(card.text || 'Platzhaltertext für diesen Schritt.')}</p></article>`;
    const layoutClass = card.icon_text_layout === 'right' ? ' is-icon-right' : '';
    const themeClass = card.card_theme && card.card_theme !== 'default' ? ` is-theme-${esc(card.card_theme)}` : '';
    const visual = card.image_url ? `<img class="beratung-preview-card__logo" src="${esc(normalizeMediaUrl(card.image_url))}" alt="${esc(card.image_alt || card.title || '')}" loading="lazy">` : `<i>${esc(card.icon || '')}</i>`;
    if (sectionType === 'technology') return `<article class="beratung-preview-card is-tech${layoutClass}">${card.logo_url ? `<img class="beratung-preview-card__logo" src="${esc(normalizeMediaUrl(card.logo_url))}" alt="${esc(card.name || card.title || 'Technologie Logo')}" loading="lazy">` : `<i>${esc(card.icon || '☁️')}</i>`}<h4>${esc(card.name || card.title || 'Technologie')}</h4><p>${esc(card.text || '')}</p></article>`;
    if (sectionType === 'trust') return `<article class="beratung-preview-card is-trust${layoutClass}${themeClass}">${card.metric ? `<strong>${esc(card.metric)}</strong>` : ''}${visual}<h4>${esc(card.title || 'Trust Element')}</h4><p>${esc(card.text || 'Platzhaltertext')}</p></article>`;
    return `<article class="beratung-preview-card${layoutClass}${themeClass}"><span>${esc(card.category || card.badge || '')}</span>${visual}<h4>${esc(card.title || 'Card Titel')}</h4><p>${esc(card.text || card.extra_text || 'Platzhaltertext für diese Card.')}</p></article>`;
  };

  const sectionPreview = (section) => {
    const cards = Array.isArray(section.cards) ? section.cards : [];
    if (section.type === 'partner_band') return `<div class="beratung-preview-section"><small>${esc(section.eyebrow || 'Netzwerk')}</small><h3>${esc(section.title || 'Partnerband')}</h3><p>${esc(section.intro || 'Beschreibungstext unter Partnerband Text und Buttons.')}</p><em>Layout: ${esc(section.partner_layout || 'network-card')}</em><div><button>${esc(section.button_1_text || 'Website')}</button>${section.button_2_text ? `<button class="ghost">${esc(section.button_2_text)}</button>` : ''}</div></div>`;
    if (section.type === 'proof') return `<div class="beratung-preview-section"><small>${esc(section.eyebrow || 'Belegbare Grundlagen')}</small><h3>${esc(section.title || 'Was nach Beratung greifbar wird')}</h3><p>${esc(section.intro || '')}</p><div class="beratung-preview-grid cols-${Math.min(4, Math.max(1, Number(section.columns || 3)))}">${cards.slice(0, 8).map((card, index) => cardPreview(card, index, 'proof')).join('')}</div></div>`;
    if (section.type === 'booking') {
      const bookingDisplayLabel = section.booking_display === 'button' ? 'nur als Button ohne Booking-Bereich' : section.booking_display === 'link' ? 'als Link/Card' : 'als Embed';
      return `<div class="beratung-preview-section"><small>${esc(section.eyebrow || 'Termin')}</small><h3>${esc(section.title || 'Direkt einen Termin buchen')}</h3><p>${esc(section.intro || '')}</p><em>${section.booking_url ? `Microsoft Bookings ${bookingDisplayLabel}: ${esc(section.booking_url)}` : 'Noch kein Microsoft Bookings Link hinterlegt.'}</em><div><button>${esc(section.booking_button_text || 'Termin buchen')}</button></div></div>`;
    }
    if (section.type === 'cta') return `<div class="beratung-preview-section is-cta"><small>${esc(section.eyebrow || 'CTA')}</small><h3>${esc(section.title || 'CTA Titel')}</h3><p>${esc(section.intro || 'Beschreibung für den CTA Bereich.')}</p><div><button>${esc(section.button_1_text || 'Button 1')}</button>${section.button_2_text ? `<button class="ghost">${esc(section.button_2_text)}</button>` : ''}</div></div>`;
    if (section.type === 'divider') return `<div class="beratung-preview-divider is-${esc(section.divider_layout || 'centered')}">${section.divider_image_url ? `<img class="beratung-preview-card__logo" src="${esc(normalizeMediaUrl(section.divider_image_url))}" alt="${esc(section.divider_image_alt || section.divider_title || 'Trenner Bild')}" loading="lazy">` : `<span>${esc(section.divider_icon || '—')}</span>`}<strong>${esc(section.divider_title || 'Trenner')}</strong><p>${esc(section.divider_subtitle || '')}</p></div>`;
    if (section.type === 'html') return `<div class="beratung-preview-section"><small>Freier HTML Bereich</small><h3>${esc(section.title || 'HTML Bereich')}</h3><p>HTML wird sicher gefiltert. Vorschau zeigt bewusst nur eine neutrale Darstellung.</p></div>`;
    if (section.type === 'comparison') return `<div class="beratung-preview-section"><small>${esc(section.eyebrow || 'Vergleich')}</small><h3>${esc(section.title || 'Vergleich')}</h3>${section.title_band_text ? `<div class="beratung-preview-band">${esc(section.title_band_text)}</div>` : ''}<div class="beratung-preview-grid cols-${Math.min(3, Math.max(1, Number(section.columns || 2)))}">${cards.map((card, index) => cardPreview(card, index, 'card_grid')).join('')}</div></div>`;
    if (section.type === 'collaboration') {
      const selected = new Set(normalizeIds(section.expert_ids));
      const experts = expertOptions.filter((expert) => selected.has(Number(expert.id))).slice(0, 8);
      const expertCards = experts.map((expert) => `<article class="beratung-preview-card is-trust"><i>${expert.is_mvp ? '★' : '👤'}</i><h4>${esc(expert.name || 'Expert')}</h4><p>${esc([expert.position, expert.company].filter(Boolean).join(' · '))}</p></article>`).join('');
      return `<div class="beratung-preview-section"><small>${esc(section.eyebrow || 'Zusammenarbeit')}</small><h3>${esc(section.title || 'Zusammenarbeit')}</h3><p>${esc(section.intro || '')}</p><div class="beratung-preview-grid cols-${Math.min(4, Math.max(2, Number(section.columns || 3)))}">${expertCards || '<article class="beratung-preview-card"><h4>Noch keine Experts ausgewählt</h4><p>Wähle unten Experts aus dem CMS-Expertsandcompanie Plugin.</p></article>'}</div>${section.mvp_note_enabled && section.mvp_note_text ? `<em>${esc(section.mvp_note_text)}</em>` : ''}</div>`;
    }
    return `<div class="beratung-preview-section"><small>${esc(section.eyebrow || SECTION_TYPES[section.type] || 'Bereich')}</small><h3>${esc(section.title || section.internal_name || 'Bereichstitel')}</h3><p>${esc(section.intro || 'Platzhalter Beschreibung für diesen Bereich.')}</p><div class="beratung-preview-grid cols-${Math.min(4, Math.max(1, Number(section.columns || 3)))}">${cards.slice(0, 8).map((card, index) => cardPreview(card, index, section.type)).join('')}</div>${section.note_text ? `<em>${esc(section.note_text)}</em>` : ''}</div>`;
  };

  const sectionTemplate = (section, index) => `
    <div class="beratung-builder__section-head">
      <strong>☰ Bereich ${index + 1}: ${esc(section.internal_name || section.title || SECTION_TYPES[section.type] || 'Ohne Titel')}</strong>
      <div class="beratung-builder__actions"><button type="button" data-action="move-up" aria-label="Bereich nach oben verschieben">↑ Hoch</button><button type="button" data-action="move-down" aria-label="Bereich nach unten verschieben">↓ Runter</button><button type="button" data-action="duplicate">Duplizieren</button><button type="button" data-action="delete">Löschen</button></div>
    </div>
    <div class="beratung-builder__grid">
      <label><input data-field="enabled" type="checkbox"> Modul aktivieren</label>
      <label><input data-field="equal_height" type="checkbox"> Gleiche Card Höhe</label>
      <label class="field-services"><input data-field="categories_enabled" type="checkbox"> Leistungskategorien aktivieren</label>
      <label class="field-faq"><input data-field="faq_allow_multiple" type="checkbox"> Mehrere FAQ Einträge gleichzeitig geöffnet</label>
      <label class="field-faq"><input data-field="faq_schema_enabled" type="checkbox"> FAQ Schema aktivieren</label>
      <label class="field-steps"><input data-field="auto_number" type="checkbox"> Nummerierung automatisch</label>
      <label class="field-steps"><input data-field="connector" type="checkbox"> Verbindungslinie anzeigen</label>
      <label class="field-comparison"><input data-field="title_band_enabled" type="checkbox"> Titelband aktivieren</label>
      <label class="field-comparison"><input data-field="border_enabled" type="checkbox"> Rahmen aktivieren</label>
      <label class="field-comparison"><input data-field="shadow_enabled" type="checkbox"> Schatten aktivieren</label>
      <label class="field-comparison"><input data-field="mobile_stack" type="checkbox"> Mobile Darstellung untereinander</label>
      <label class="field-collaboration"><input data-field="mvp_note_enabled" type="checkbox"> MVP Hinweis unter dem Band anzeigen</label>
      <label>Bereichstyp <select data-field="type">${optionHtml(SECTION_TYPES, section.type)}</select></label>
      <label>Card Typ <select data-field="card_type">${optionHtml(CARD_TYPES, section.card_type)}</select></label>
      <label>Interner Name <input data-field="internal_name"></label>
      <label>Oberzeile <input data-field="eyebrow"></label>
      <label>Öffentliche Überschrift / Titel <input data-field="title"></label>
      <label>Anker ID <input data-field="anchor_id"></label>
      <label>Beschreibung <textarea data-field="intro" rows="2"></textarea></label>
      <label class="field-partner_band">Partnerband Layout <select data-field="partner_layout"><option value="network-card">Netzwerk Card</option><option value="split-panel">Split Panel</option><option value="centered-badge">Zentriertes Badge</option><option value="compact-strip">Kompakte Leiste</option></select></label>
      <label>Spaltenanzahl <select data-field="columns"><option>1</option><option>2</option><option>3</option><option>4</option></select></label>
      <label>Card Design <select data-field="card_design"><option value="standard">Standard</option><option value="compact">Kompakt</option><option value="bordered">Rahmen</option><option value="filled">Gefüllt</option><option value="minimal">Minimal</option><option value="accent">Akzent</option></select></label>
      <label class="field-steps field-technology field-cta">Darstellung <select data-field="display_style"><option value="cards">Cards</option><option value="horizontal">Horizontal</option><option value="vertical">Vertikal</option><option value="icon_grid">Icon Grid</option><option value="logo_strip">Logo Leiste</option><option value="compact">Kompakt</option><option value="large">Groß</option></select></label>
      <label class="field-comparison">Vergleichsvariante <select data-field="comparison_variant"><option value="two_columns">Zwei Spalten Vergleich</option><option value="three_columns">Drei Spalten Vergleich</option><option value="current_target">Ist Zustand gegen Zielbild</option><option value="problem_solution">Problem gegen Lösung</option><option value="genai_agentic">GenAI gegen Agentic AI</option><option value="copilot_search">Copilot gegen klassische Suche</option><option value="governance">Ohne Governance gegen mit Governance</option></select></label>
      <label class="field-comparison">Titelband Text <input data-field="title_band_text"></label>
      <label class="field-comparison">Titelband Hintergrund <input data-field="title_band_background_color" type="color"></label>
      <label class="field-comparison">Titelband Textfarbe <input data-field="title_band_text_color" type="color"></label>
      <label>Hintergrundfarbe <input data-field="background_color" type="color"></label>
      <label>Textfarbe <input data-field="text_color" type="color"></label>
      <label>Hintergrundbild <input data-field="background_image_url"></label>
      <label>Innenabstand oben <input data-field="padding_top" type="number" min="0" max="180"></label>
      <label>Innenabstand unten <input data-field="padding_bottom" type="number" min="0" max="180"></label>
      <label>Maximale Inhaltsbreite <input data-field="max_width" type="number" min="720" max="1800"></label>
      <label>Textausrichtung <select data-field="text_align"><option value="left">links</option><option value="center">zentriert</option><option value="right">rechts</option></select></label>
      <label class="field-services field-trust">Optionaler Hinweistext <textarea data-field="note_text" rows="2"></textarea></label>
      <label class="field-collaboration beratung-expert-select-label">Experts auswählen <select data-expert-ids multiple size="8">${expertOptionHtml(section.expert_ids)}</select><small>Mehrfachauswahl mit Strg/⌘ oder Shift. Quelle: CMS-Expertsandcompanie.</small></label>
      <label class="field-collaboration">MVP Hinweistext <textarea data-field="mvp_note_text" rows="2"></textarea></label>
      <label class="field-faq">FAQ Icon Stil <select data-field="faq_icon_style"><option value="plus">Plus</option><option value="chevron">Chevron</option><option value="question">Fragezeichen</option></select></label>
      <label class="field-faq">Standard Öffnung <select data-field="faq_open_behavior"><option value="none">Kein Eintrag offen</option><option value="first">Erster Eintrag offen</option><option value="custom">Individuell pro Eintrag</option></select></label>
      <label class="field-faq">Frage Hintergrund <input data-field="faq_question_background_color" type="color"></label>
      <label class="field-faq">Frage Textfarbe <input data-field="faq_question_text_color" type="color"></label>
      <label class="field-faq">Antwort Hintergrund <input data-field="faq_answer_background_color" type="color"></label>
      <label class="field-cta field-services field-steps field-trust field-partner_band">Button 1 Text <input data-field="button_1_text"></label>
      <label class="field-cta field-services field-steps field-trust field-partner_band">Button 1 Zieltyp <select data-field="button_1_target_type">${optionHtml(TARGET_TYPES, section.button_1_target_type || 'internal')}</select></label>
      <label class="field-cta field-services field-steps field-trust field-partner_band">Button 1 Ziel <input data-field="button_1_target"></label>
      <label class="field-cta field-services field-steps field-trust field-partner_band">Button 1 Stil <select data-field="button_1_style"><option value="primary">Primär</option><option value="secondary">Sekundär</option><option value="ghost">Ghost</option><option value="link">Link</option></select></label>
      <label class="field-cta field-partner_band">Button 2 Text <input data-field="button_2_text"></label>
      <label class="field-cta field-partner_band">Button 2 Zieltyp <select data-field="button_2_target_type">${optionHtml(TARGET_TYPES, section.button_2_target_type || 'internal')}</select></label>
      <label class="field-cta field-partner_band">Button 2 Ziel <input data-field="button_2_target"></label>
      <label class="field-cta field-partner_band">Button 2 Stil <select data-field="button_2_style"><option value="primary">Primär</option><option value="secondary">Sekundär</option><option value="ghost">Ghost</option><option value="link">Link</option></select></label>
      <label class="field-divider">Trenner Typ <select data-field="divider_type"><option value="line">Einfacher Strich</option><option value="title_band">Titelband</option><option value="icon">Icon Trenner</option><option value="color_block">Farbblock</option><option value="wave">Wellenform</option><option value="quote">Zitatband</option><option value="cta">CTA Trenner</option><option value="numbered">Nummerierter Abschnittstrenner</option><option value="spacer">Abstand ohne sichtbaren Trenner</option></select></label>
      <label class="field-divider">Trenner Titel <input data-field="divider_title"></label>
      <label class="field-divider">Trenner Untertitel <textarea data-field="divider_subtitle" rows="2"></textarea></label>
      <label class="field-divider">Trenner Icon <input data-field="divider_icon"></label>
      <label class="field-divider">Trenner Bild / Mediathek <input data-field="divider_image_url" placeholder="Bild hochladen oder aus der Mediathek wählen"></label>
      <label class="field-divider">Trenner Bild Alt Text <input data-field="divider_image_alt"></label>
      <label class="field-divider">Trenner Layout <select data-field="divider_layout"><option value="centered">Zentriert: Bild/Icon über Text</option><option value="image-left">Bild links, Text rechts</option><option value="image-right">Text links, Bild rechts</option></select></label>
      <label class="field-divider">Bildbreite in % <input data-field="divider_image_width" type="number" min="18" max="45"></label>
      <label class="field-divider">Linienfarbe <input data-field="divider_line_color" type="color"></label>
      <label class="field-divider">Breite in % <input data-field="divider_width" type="number" min="20" max="100"></label>
      <label class="field-divider">Innenabstand links <input data-field="divider_padding_left" type="number" min="0" max="180"></label>
      <label class="field-divider">Innenabstand rechts <input data-field="divider_padding_right" type="number" min="0" max="180"></label>
      <label class="field-divider">Mobile Verhalten <select data-field="divider_mobile_behavior"><option value="stack">Stapeln</option><option value="compact">Kompakt</option><option value="hide_visual">Visuelles Element ausblenden</option></select></label>
      <label class="field-booking">Microsoft Bookings Link / Embed URL <input data-field="booking_url" placeholder="https://outlook.office.com/book/... oder https://.../bookings/..."></label>
      <label class="field-booking">Booking Darstellung <select data-field="booking_display"><option value="embed">Eingebettet als Kalender</option><option value="link">Als Booking Card mit Button</option><option value="button">Nur Button ohne Booking-Bereich</option></select></label>
      <label class="field-booking">Booking Button Text <input data-field="booking_button_text" placeholder="Termin buchen"></label>
      <label class="field-html is-html">Freier HTML Bereich <textarea data-field="html" rows="6"></textarea><small>Nur Admin-Benutzer. Skripte werden serverseitig gefiltert.</small></label>
    </div>
    <div class="beratung-preview-label">Live-nahe Vorschau</div>
    <div class="beratung-live-preview">${sectionPreview(section)}</div>
    <div class="beratung-builder__cards"></div>
    <button type="button" data-action="add-card">Eintrag / Card hinzufügen</button>`;

  const updateSectionVisibility = (box, section) => {
    const groups = ['partner_band', 'proof', 'booking', 'services', 'steps', 'comparison', 'faq', 'trust', 'technology', 'collaboration', 'cta', 'divider', 'html'];
    groups.forEach((group) => $$(`.field-${group}`, box).forEach((field) => { field.hidden = section.type !== group; }));
    $('.beratung-builder__cards', box).hidden = ['partner_band', 'booking', 'cta', 'divider', 'html', 'contact', 'collaboration'].includes(section.type);
    $('[data-action="add-card"]', box).hidden = ['partner_band', 'booking', 'cta', 'divider', 'html', 'contact', 'collaboration'].includes(section.type);
  };

  const renderCard = (card, section, cardIndex, sync, rerender) => {
    card.card_type ??= section.card_type || 'text';
    if (section.type === 'faq') card.card_type = 'text';
    const box = document.createElement('div');
    box.className = `beratung-builder__card type-${card.card_type}`;
    box.draggable = true;
    setHtml(box, `
      <div class="beratung-builder__section-head">
        <strong>☰ ${section.type === 'faq' ? 'FAQ' : 'Card'} ${cardIndex + 1}: ${CARD_TYPES[card.card_type] || 'Card'}</strong>
        <div class="beratung-builder__actions"><button type="button" data-card-action="duplicate">Duplizieren</button><button type="button" data-card-action="delete">Löschen</button></div>
      </div>
      <div class="beratung-builder__grid">
        <label><input data-card-field="enabled" type="checkbox"> Eintrag aktivieren</label>
        <label><input data-card-field="featured" type="checkbox"> Hervorgehoben</label>
        <label><input data-card-field="border_enabled" type="checkbox"> Rahmen aktivieren</label>
        <label><input data-card-field="shadow_enabled" type="checkbox"> Schatten aktivieren</label>
        <label><input data-card-field="hover_enabled" type="checkbox"> Hover Effekt</label>
        <label class="card-general">Card Typ <select data-card-field="card_type">${optionHtml(CARD_TYPES, card.card_type)}</select></label>
        <label class="card-general">Card Layout <select data-card-field="card_layout"><option value="classic">Klassisch</option><option value="media-left">Icon/Text links-rechts</option><option value="compact">Kompakt</option><option value="spotlight">Spotlight</option></select></label>
        <label class="card-general card-service card-trust">Card Theme / Farbstil <select data-card-field="card_theme">${optionHtml(CARD_THEMES, card.card_theme || 'default')}</select><small>Dezente Farbwelt je Card, passend untereinander kombinierbar.</small></label>
        <label class="card-general card-service card-trust card-comparison">Icon <input data-card-field="icon"></label>
        <label class="card-tech">Fallback Icon <input data-card-field="icon"><small>Nur sichtbar, wenn kein Logo aus der Mediathek hinterlegt ist.</small></label>
        <label class="card-general card-service card-tech card-trust field-offer">Icon/Text Layout <select data-card-field="icon_text_layout"><option value="below">Text unter dem Icon</option><option value="right">Text rechts vom Icon</option></select></label>
        <label class="card-service">Kategorie <input data-card-field="category"></label>
        <label class="card-general card-service card-tech card-trust card-step">Titel / Name <input data-card-field="title"></label>
        <label class="card-tech">Technologie Name <input data-card-field="name"></label>
        <label class="card-general card-service card-tech card-trust card-step">Text / Kurzbeschreibung <textarea data-card-field="text" rows="2"></textarea></label>
        <label class="card-service card-comparison">Zusatztext / Typischer Einsatz <textarea data-card-field="extra_text" rows="2"></textarea></label>
        <label class="card-trust">Kennzahl optional <input data-card-field="metric"></label>
        <label class="card-tech">Logo aus Mediathek / Upload <input data-card-field="logo_url" placeholder="Logo hochladen oder aus der Mediathek wählen"><small>Empfohlen für Microsoft-Technologien: SVG/PNG/WebP mit transparentem Hintergrund.</small></label>
        <label class="card-faq">Frage <input data-card-field="question"></label>
        <label class="card-faq">Antwort <textarea data-card-field="answer" rows="4"></textarea></label>
        <label class="card-faq"><input data-card-field="default_open" type="checkbox"> Standardmäßig geöffnet</label>
        <label class="field-offer">Icon Hintergrund <input data-card-field="icon_background" type="color"></label>
        <label class="field-offer">Icon Farbe <input data-card-field="icon_color" type="color"></label>
        <label class="field-image card-general card-service card-trust">Bild / Logo aus Mediathek oder Upload <input data-card-field="image_url" placeholder="Bild hochladen oder aus der Mediathek wählen"></label>
        <label class="field-image card-general card-service card-trust">Bild Alt Text <input data-card-field="image_alt"></label>
        <label class="field-image card-general card-service card-trust">Bildhöhe <input data-card-field="image_height" type="number" min="120" max="520"></label>
        <label class="field-image card-general card-service card-trust">Bild Zuschnitt <select data-card-field="image_fit"><option value="cover">Cover</option><option value="contain">Contain</option></select></label>
        <label class="field-image field-text">Badge / Label <input data-card-field="label"></label>
        <label class="field-image">Label Position <select data-card-field="label_position"><option value="left">links</option><option value="center">mittig</option><option value="right">rechts</option></select></label>
        <label class="field-image">Label Stil <select data-card-field="label_style"><option value="border">Rahmen</option><option value="filled">gefüllt</option><option value="transparent">transparent</option></select></label>
        <label class="field-text">Badge <input data-card-field="badge"></label>
        <label class="field-text field-image field-offer field-step field-problem card-service card-trust">Button Text <input data-card-field="button_label"></label>
        <label class="field-text field-image field-offer field-step field-problem card-service card-trust">Button Ziel <input data-card-field="button_url"></label>
        <label>Hintergrundfarbe <input data-card-field="background_color" type="color"></label>
        <label>Textfarbe <input data-card-field="text_color" type="color"></label>
        <label>Rahmenfarbe <input data-card-field="border_color" type="color"></label>
        <label class="field-offer"><input data-card-field="tab_enabled" type="checkbox"> Reiter aktivieren</label>
        <label class="field-offer">Card Größe <select data-card-field="card_size"><option value="small">klein</option><option value="medium">mittel</option><option value="large">groß</option></select></label>
        <label class="field-step card-step">Schritt Nummer <input data-card-field="step_number"></label>
        <label class="field-step card-step"><input data-card-field="auto_number" type="checkbox"> Automatische Nummerierung</label>
        <label class="field-step card-step"><input data-card-field="connector" type="checkbox"> Verbindungslinie anzeigen</label>
        <label class="field-problem card-comparison">Spalte 1 / Problem Titel <input data-card-field="problem_title"></label>
        <label class="field-problem card-comparison">Spalte 1 / Problem Text <textarea data-card-field="problem_text" rows="2"></textarea></label>
        <label class="field-problem card-comparison">Spalte 2 / Lösung Titel <input data-card-field="solution_title"></label>
        <label class="field-problem card-comparison">Spalte 2 / Lösung Text <textarea data-card-field="solution_text" rows="2"></textarea></label>
        <label class="field-problem">Risiko Level <select data-card-field="risk_level"><option value="low">niedrig</option><option value="medium">mittel</option><option value="high">hoch</option></select></label>
        <label class="field-problem">Empfohlene Maßnahme <textarea data-card-field="recommendation" rows="2"></textarea></label>
      </div>`);

    const updateVisibility = () => {
      box.dataset.cardType = card.card_type;
      ['field-text', 'field-image', 'field-offer', 'field-step', 'field-problem', 'card-service', 'card-tech', 'card-trust', 'card-faq', 'card-comparison', 'card-step'].forEach((cls) => $$(`.${cls}`, box).forEach((field) => { field.hidden = true; }));
      $$('.card-general', box).forEach((field) => { field.hidden = false; });
      if (section.type === 'services') $$('.card-service', box).forEach((field) => { field.hidden = false; });
      if (section.type === 'proof') $$('.field-text', box).forEach((field) => { field.hidden = false; });
      if (section.type === 'technology') $$('.card-tech', box).forEach((field) => { field.hidden = false; });
      if (section.type === 'trust') $$('.card-trust', box).forEach((field) => { field.hidden = false; });
      if (section.type === 'faq') $$('.card-faq', box).forEach((field) => { field.hidden = false; });
      if (section.type === 'comparison') $$('.card-comparison', box).forEach((field) => { field.hidden = false; });
      if (section.type === 'steps') $$('.card-step', box).forEach((field) => { field.hidden = false; });
      const map = { text: '.field-text', image_label: '.field-image', offer_icon_tab: '.field-offer', step: '.field-step', problem_solution: '.field-problem' };
      if (!['services', 'proof', 'technology', 'trust', 'faq', 'comparison', 'steps'].includes(section.type)) $$(map[card.card_type] || '.field-text', box).forEach((field) => { field.hidden = false; });
    };

    $$('[data-card-field]', box).forEach((input) => {
      const field = input.dataset.cardField;
      if (input.type === 'checkbox') input.checked = Boolean(card[field]);
      else input.value = text(card[field]);
      input.addEventListener('input', () => {
        if (input.type === 'checkbox') card[field] = input.checked;
        else if (input.type === 'number') card[field] = Number(input.value || 0);
        else card[field] = input.value;
        updateVisibility();
        sync();
      });
      input.addEventListener('change', () => input.dispatchEvent(new Event('input')));
    });
    enhanceMediaFields(box);

    $$('[data-card-action]', box).forEach((button) => button.addEventListener('click', () => {
      if (button.dataset.cardAction === 'delete') section.cards.splice(cardIndex, 1);
      if (button.dataset.cardAction === 'duplicate') section.cards.splice(cardIndex + 1, 0, JSON.parse(JSON.stringify(card)));
      rerender();
    }));

    box.addEventListener('dragstart', (event) => event.dataTransfer?.setData('text/plain', `card:${cardIndex}`));
    box.addEventListener('dragover', (event) => event.preventDefault());
    box.addEventListener('drop', (event) => {
      event.preventDefault();
      const raw = event.dataTransfer?.getData('text/plain') || '';
      if (!raw.startsWith('card:')) return;
      const from = Number(raw.split(':')[1]);
      if (Number.isNaN(from) || from === cardIndex) return;
      const [moved] = section.cards.splice(from, 1);
      section.cards.splice(cardIndex, 0, moved);
      rerender();
    });

    updateVisibility();
    return box;
  };

  const renderDesignPresetSelector = () => {
    const select = $('#beratung-design-preset');
    const target = $('#design_json');
    if (!select || !target) return;
    select.addEventListener('change', () => {
      const option = select.selectedOptions?.[0];
      const raw = option?.dataset?.design || '';
      if (!raw) return;
      try {
        const design = JSON.parse(raw);
        if (design && typeof design === 'object') {
          target.value = JSON.stringify(design, null, 2);
            window.dispatchEvent(new CustomEvent('beratung:design-updated'));
          const customToggle = $('input[name="custom_design_enabled"]');
          const globalToggle = $('input[name="use_global_settings"]');
          if (customToggle) customToggle.checked = true;
          if (globalToggle) globalToggle.checked = false;
        }
      } catch (_) {
        // Preset-Auswahl darf den Editor nie blockieren.
      }
    });
  };

  renderHeroBuilder();
  renderAnchorNavigationBuilder();
  renderContactBuilder();
  renderSectionBuilder();
  enhanceEditorCollapsibleSections();
  renderDesignEditor();
  renderDesignPresetSelector();
})();
