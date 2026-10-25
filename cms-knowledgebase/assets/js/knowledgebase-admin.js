(function () {
    'use strict';

    function normalizeHex(value) {
        var normalized = String(value || '').trim();
        if (!normalized.startsWith('#')) {
            normalized = '#' + normalized;
        }

        if (!/^#[0-9a-fA-F]{6}$/.test(normalized)) {
            return null;
        }

        return normalized.toUpperCase();
    }

    function initColorSync() {
        document.querySelectorAll('[data-color-sync]').forEach(function (wrapper) {
            var colorInput = wrapper.querySelector('input[type="color"]');
            var textInput = wrapper.querySelector('input[type="text"]');

            if (!colorInput || !textInput) {
                return;
            }

            var syncTextFromColor = function () {
                var normalized = normalizeHex(colorInput.value);
                if (normalized !== null) {
                    textInput.value = normalized;
                }
            };

            var syncColorFromText = function () {
                var normalized = normalizeHex(textInput.value);
                if (normalized !== null) {
                    textInput.value = normalized;
                    colorInput.value = normalized;
                }
            };

            colorInput.addEventListener('input', syncTextFromColor);
            colorInput.addEventListener('change', syncTextFromColor);
            textInput.addEventListener('input', syncColorFromText);
            textInput.addEventListener('change', syncColorFromText);

            syncTextFromColor();
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        initColorSync();
    });
})();
