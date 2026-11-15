/**
 * CMS Promos – Admin-Helfer (CSP-konform: externe Datei, keine Inline-Handler, ohne HTML-Strings im DOM).
 *
 * - UTM-Builder im Promo-Formular (#promo-build-utm)
 * - „Alle markieren“ und Auswahlprüfung für Sammelaktionen ([data-pr-bulk])
 */
(function () {
    'use strict';

    function initUtmBuilder() {
        var targetInput = document.getElementById('promo-target-url');
        var buildButton = document.getElementById('promo-build-utm');
        if (!targetInput || !buildButton) {
            return;
        }

        buildButton.addEventListener('click', function () {
            var rawUrl = (targetInput.value || '').trim();
            if (!rawUrl) {
                targetInput.focus();
                return;
            }

            var parsed;
            try {
                parsed = new URL(rawUrl);
            } catch (e) {
                targetInput.focus();
                return;
            }

            ['source', 'medium', 'campaign', 'term', 'content'].forEach(function (field) {
                var input = document.getElementById('promo-utm-' + field);
                if (!input) {
                    return;
                }
                var value = (input.value || '').trim().toLowerCase().replace(/\s+/g, '-');
                var key = 'utm_' + field;
                if (value && !parsed.searchParams.get(key)) {
                    parsed.searchParams.set(key, value);
                }
            });

            targetInput.value = parsed.toString();
        });
    }

    function initBulkForms() {
        var forms = document.querySelectorAll('form[data-pr-bulk]');
        Array.prototype.forEach.call(forms, function (form) {
            var toggle = form.querySelector('[data-pr-select-all]');
            var boxes = function () {
                return form.querySelectorAll('input[data-pr-select]');
            };

            if (toggle) {
                toggle.addEventListener('change', function () {
                    Array.prototype.forEach.call(boxes(), function (box) {
                        box.checked = toggle.checked;
                    });
                });
                form.addEventListener('change', function (event) {
                    if (!event.target || !event.target.hasAttribute || !event.target.hasAttribute('data-pr-select')) {
                        return;
                    }
                    var all = boxes();
                    var checked = Array.prototype.filter.call(all, function (box) { return box.checked; }).length;
                    toggle.checked = checked > 0 && checked === all.length;
                    toggle.indeterminate = checked > 0 && checked < all.length;
                });
            }
        });

        // Capture-Phase: vor der Core-Bestätigung (data-cms-confirm) prüfen, ob Aktion und Auswahl gesetzt sind.
        document.addEventListener('click', function (event) {
            var target = event.target instanceof Element ? event.target.closest('[data-pr-bulk-apply]') : null;
            if (!target) {
                return;
            }
            var form = target.form || target.closest('form');
            if (!form) {
                return;
            }
            var select = form.querySelector('select[name="bulk_action"]');
            var selected = form.querySelectorAll('input[data-pr-select]:checked').length;
            if (!select || select.value === '' || selected === 0) {
                event.preventDefault();
                event.stopImmediatePropagation();
                if (select && select.value === '') {
                    select.focus();
                }
            }
        }, true);
    }

    function init() {
        initUtmBuilder();
        initBulkForms();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
