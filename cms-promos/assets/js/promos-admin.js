/**
 * CMS Promos – Vorschau der Ziel-URL mit UTM-Parametern im Promo-Formular.
 * CSP-konform: externe Datei, keine Inline-Handler, Ausgabe nur über textContent.
 */
(function () {
    'use strict';

    var targetInput = document.getElementById('promo-target-url');
    var buildButton = document.getElementById('promo-build-utm');
    var copyButton = document.getElementById('promo-copy-utm');
    var output = document.getElementById('promo-utm-output');
    if (!targetInput || !buildButton || !output) {
        return;
    }

    var utmFields = ['source', 'medium', 'campaign', 'term', 'content'];

    function normalize(value) {
        return value.trim().toLowerCase().replace(/\s+/g, '-').replace(/[^a-z0-9._~-]+/g, '-').replace(/^-+|-+$/g, '');
    }

    function buildUrl() {
        var rawUrl = (targetInput.value || '').trim();
        if (!rawUrl) {
            return { error: 'Bitte zuerst eine Ziel-URL eingeben.' };
        }

        var parsed;
        try {
            parsed = new URL(rawUrl);
        } catch (e) {
            return { error: 'Die Ziel-URL ist ungültig.' };
        }
        if (parsed.protocol !== 'https:' && parsed.protocol !== 'http:') {
            return { error: 'Nur http(s)-Adressen sind erlaubt.' };
        }

        utmFields.forEach(function (field) {
            var input = document.getElementById('promo-utm-' + field);
            var value = input ? normalize(input.value || '') : '';
            var key = 'utm_' + field;
            if (value && !parsed.searchParams.get(key)) {
                parsed.searchParams.set(key, value);
            }
        });

        return { url: parsed.toString() };
    }

    buildButton.addEventListener('click', function () {
        var result = buildUrl();
        output.textContent = result.error || result.url;
        output.classList.toggle('is-error', Boolean(result.error));
        if (copyButton) {
            copyButton.hidden = Boolean(result.error) || !navigator.clipboard;
        }
    });

    if (copyButton) {
        copyButton.addEventListener('click', function () {
            var text = output.textContent || '';
            if (!text || !navigator.clipboard) {
                return;
            }
            navigator.clipboard.writeText(text).then(function () {
                copyButton.textContent = 'Kopiert';
            });
        });
    }
})();
