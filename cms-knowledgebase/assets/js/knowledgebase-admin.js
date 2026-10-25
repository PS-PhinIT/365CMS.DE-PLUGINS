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

    /*
     * Der Core-Editor (editor-init.js) registriert alle Blöcke mit inlineToolbar: false,
     * dadurch erscheint beim Markieren von Text keine Formatierungsleiste.
     * Für die KB-Inhaltsbereiche wird die Inline-Toolbar beim Erzeugen der
     * EditorJS-Instanz für Textblöcke wieder aktiviert.
     */
    var KB_INLINE_TEXT_BLOCKS = ['paragraph', 'header', 'list', 'quote', 'table', 'warning', 'alert', 'image', 'mediaText', 'accordion'];
    // Bewusst ohne 'hyperlink' (setzt unter EditorJS 2.31 keinen Link und hinterlässt Markierungs-Spans)
    // und ohne 'textColor' (Custom Element bricht beim Klonen des Inline-Popovers ab); stattdessen das eingebaute 'link'.
    var KB_INLINE_TOOL_ORDER = ['bold', 'italic', 'link', 'underline', 'marker', 'strikethrough', 'inlineCode'];
    var KB_BUILTIN_INLINE_TOOLS = ['bold', 'italic', 'link'];

    function resolveInlineTools(tools) {
        return KB_INLINE_TOOL_ORDER.filter(function (name) {
            return KB_BUILTIN_INLINE_TOOLS.indexOf(name) !== -1 || !!tools[name];
        });
    }

    function enableInlineToolbar(config) {
        if (!config || typeof config !== 'object' || !config.tools || typeof config.tools !== 'object') {
            return config;
        }

        var inlineTools = resolveInlineTools(config.tools);
        KB_INLINE_TEXT_BLOCKS.forEach(function (blockName) {
            var definition = config.tools[blockName];
            if (definition && typeof definition === 'object' && definition.inlineToolbar === false) {
                definition.inlineToolbar = inlineTools.slice();
            }
        });

        return config;
    }

    function wrapEditorJs(OriginalEditorJs) {
        if (typeof OriginalEditorJs !== 'function' || OriginalEditorJs.__kbInlineToolbarPatched) {
            return OriginalEditorJs;
        }

        var PatchedEditorJs = function (config) {
            return new OriginalEditorJs(enableInlineToolbar(config));
        };
        Object.setPrototypeOf(PatchedEditorJs, OriginalEditorJs);
        PatchedEditorJs.prototype = OriginalEditorJs.prototype;
        PatchedEditorJs.__kbInlineToolbarPatched = true;

        return PatchedEditorJs;
    }

    (function installEditorJsHook() {
        var descriptor = Object.getOwnPropertyDescriptor(window, 'EditorJS');
        if (descriptor && !descriptor.configurable) {
            window.EditorJS = wrapEditorJs(window.EditorJS);
            return;
        }

        var current = wrapEditorJs(window.EditorJS);
        Object.defineProperty(window, 'EditorJS', {
            configurable: true,
            enumerable: true,
            get: function () {
                return current;
            },
            set: function (value) {
                current = wrapEditorJs(value);
            }
        });
    })();
})();
