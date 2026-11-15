/*!
 * 365CMS | Experts & Companies – Admin-Listen: „Alle auswählen“ für Sammelaktionen.
 * Bestätigungen laufen über das Core-Attribut data-cms-confirm.
 */
(() => {
  'use strict';

  document.querySelectorAll('[data-excomp-select-all]').forEach((toggle) => {
    const scope = toggle.closest('form') || document;
    const items = () => Array.from(scope.querySelectorAll('[data-excomp-select-item]'));
    const sync = () => {
      const all = items();
      const checked = all.filter((item) => item.checked).length;
      toggle.checked = all.length > 0 && checked === all.length;
      toggle.indeterminate = checked > 0 && checked < all.length;
    };

    toggle.addEventListener('change', () => {
      items().forEach((item) => { item.checked = toggle.checked; });
      sync();
    });
    scope.addEventListener('change', (event) => {
      if (event.target instanceof Element && event.target.matches('[data-excomp-select-item]')) sync();
    });
    sync();
  });
})();
