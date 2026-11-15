(() => {
  const roots = document.querySelectorAll('.cms-beratung');
  if (!roots.length) return;

  roots.forEach((root) => root.addEventListener('click', (event) => {
    try {
      const link = event.target instanceof Element ? event.target.closest('a[href^="#"]') : null;
      if (!link) return;
      const href = link.getAttribute('href') || '';
      if (href.length < 2) return;
      const targetId = href.startsWith('#') ? decodeURIComponent(href.slice(1)) : '';
      const target = targetId ? document.getElementById(targetId) : null;
      if (!target) return;
      event.preventDefault();
      target.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' });

      if (window.cmsTracking && link.classList.contains('cms-beratung-card__link')) {
        window.cmsTracking.track?.('cms_beratung_button_click', { href });
      }
    } catch (_) {
      // Frontend-Enhancement darf die Seite nie blockieren.
    }
  }));

  roots.forEach((root) => root.addEventListener('click', (event) => {
    try {
      const button = event.target instanceof Element ? event.target.closest('.cms-beratung-faq button[aria-controls]') : null;
      if (!button) return;
      const faq = button.closest('.cms-beratung-faq');
      const panelId = button.getAttribute('aria-controls') || '';
      const panel = panelId ? document.getElementById(panelId) : null;
      if (!faq || !panel) return;
      event.preventDefault();
      const allowMultiple = faq.dataset.allowMultiple === '1';
      const willOpen = button.getAttribute('aria-expanded') !== 'true';
      if (!allowMultiple) {
        faq.querySelectorAll('button[aria-controls]').forEach((other) => {
          const otherPanelId = other.getAttribute('aria-controls') || '';
          const otherPanel = otherPanelId ? document.getElementById(otherPanelId) : null;
          other.setAttribute('aria-expanded', 'false');
          other.closest('.cms-beratung-faq__item')?.classList.remove('is-open');
          if (otherPanel) otherPanel.hidden = true;
        });
      }
      button.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
      button.closest('.cms-beratung-faq__item')?.classList.toggle('is-open', willOpen);
      panel.hidden = !willOpen;
    } catch (_) {
      // FAQ bleibt als HTML lesbar, auch wenn Enhancement fehlschlägt.
    }
  }));

  roots.forEach((root) => {
    try {
      const tocRail = root.querySelector('.cms-beratung__toc-rail');
      if (!tocRail) return;
      const toggle = tocRail.querySelector('.cms-beratung__toc-toggle');
      const links = Array.from(tocRail.querySelectorAll('.cms-beratung__toc-link'));
      const isLockedOpen = tocRail.dataset.tocLock === 'expanded';
      let expanded = isLockedOpen || root.classList.contains('is-toc-expanded') || tocRail.dataset.tocState === 'expanded';

      const setExpanded = (next) => {
        expanded = Boolean(next);
        root.classList.toggle('is-toc-expanded', expanded);
        tocRail.dataset.tocState = expanded ? 'expanded' : 'collapsed';
        if (toggle) {
          toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
          toggle.setAttribute('aria-label', expanded ? 'Inhaltsverzeichnis schließen' : 'Inhaltsverzeichnis öffnen');
        }
      };

      setExpanded(expanded);

      if (toggle) {
        toggle.addEventListener('click', (event) => {
          event.preventDefault();
          if (isLockedOpen) return;
          setExpanded(!expanded);
        });
      }

      const sections = links.map((link) => {
        const href = link.getAttribute('href') || '';
        const id = href.startsWith('#') ? decodeURIComponent(href.slice(1)) : '';
        const target = id ? document.getElementById(id) : null;
        return target ? { link, target } : null;
      }).filter(Boolean);

      const setActive = (id) => {
        links.forEach((link) => {
          const href = link.getAttribute('href') || '';
          link.classList.toggle('is-active', href === `#${id}`);
        });
      };

      if ('IntersectionObserver' in window && sections.length) {
        const observer = new IntersectionObserver((entries) => {
          const visible = entries
            .filter((entry) => entry.isIntersecting)
            .sort((a, b) => Math.abs(a.boundingClientRect.top) - Math.abs(b.boundingClientRect.top));
          if (visible[0]?.target?.id) setActive(visible[0].target.id);
        }, { rootMargin: '-25% 0px -60% 0px', threshold: [0, 0.1, 0.35] });
        sections.forEach(({ target }) => observer.observe(target));
      } else if (sections.length) {
        const updateActive = () => {
          const current = sections.reduce((best, item) => {
            const top = Math.abs(item.target.getBoundingClientRect().top - 120);
            return !best || top < best.top ? { id: item.target.id, top } : best;
          }, null);
          if (current?.id) setActive(current.id);
        };
        window.addEventListener('scroll', updateActive, { passive: true });
        updateActive();
      }
    } catch (_) {
      // TOC ist reine Verbesserung; die Seite bleibt ohne JS vollständig nutzbar.
    }
  });

  const alignTrustTitleRows = () => {
    roots.forEach((root) => {
      try {
        const grids = root.querySelectorAll('.cms-beratung__cards, .cms-beratung-proof__grid, .cms-beratung-comparison');
        grids.forEach((grid) => {
          const cards = Array.from(grid.children).filter((child) => child instanceof Element && child.matches('.cms-beratung-card--module-trust'));
          if (!cards.length) return;

          cards.forEach((card) => {
            card.classList.remove('has-row-multiline-title');
            card.style.removeProperty('--beratung-trust-title-height');
          });

          if (window.matchMedia('(max-width: 767px)').matches) return;

          const rows = new Map();
          cards.forEach((card) => {
            const top = Math.round(card.getBoundingClientRect().top);
            if (!rows.has(top)) rows.set(top, []);
            rows.get(top).push(card);
          });

          rows.forEach((rowCards) => {
            const titleItems = rowCards.map((card) => {
              const title = card.querySelector('h3');
              return title instanceof HTMLElement ? { card, title } : null;
            }).filter(Boolean);
            if (!titleItems.length) return;

            const hasMultilineTitle = titleItems.some(({ title }) => {
              const styles = window.getComputedStyle(title);
              const fontSize = parseFloat(styles.fontSize) || 16;
              const lineHeight = parseFloat(styles.lineHeight) || (fontSize * 1.28);
              return title.getBoundingClientRect().height > (lineHeight * 1.35);
            });

            if (!hasMultilineTitle) return;

            const maxTitleHeight = Math.ceil(Math.max(...titleItems.map(({ title }) => title.getBoundingClientRect().height)));
            titleItems.forEach(({ card }) => {
              card.classList.add('has-row-multiline-title');
              card.style.setProperty('--beratung-trust-title-height', `${maxTitleHeight}px`);
            });
          });
        });
      } catch (_) {
        // Trust-Alignment ist ein Layout-Enhancement und darf die Seite nie blockieren.
      }
    });
  };

  alignTrustTitleRows();
  window.addEventListener('load', alignTrustTitleRows, { once: true });
  if (document.fonts?.ready) document.fonts.ready.then(alignTrustTitleRows).catch(() => {});
  let trustTitleResizeTimer = 0;
  window.addEventListener('resize', () => {
    window.clearTimeout(trustTitleResizeTimer);
    trustTitleResizeTimer = window.setTimeout(alignTrustTitleRows, 120);
  }, { passive: true });
})();
