/**
 * CMS Booking – Admin JavaScript (CSP-konform, ohne Inline-Handler)
 *
 *   data-booking-autosubmit            Filter-Select sendet das Formular bei Änderung
 *   data-booking-color-preview="id"    Farbwert in ein Textfeld spiegeln
 *   data-booking-bulk-form             Sammelaktionen: Alle-auswählen, Zähler, Button-Sperre
 *
 * Bestätigungen laufen über das Core-Attribut data-cms-confirm (cms-inline-actions.js).
 *
 * @package CMS_Booking
 */
(function () {
    'use strict';

    function init() {
        document.querySelectorAll('[data-booking-autosubmit]').forEach(function (field) {
            field.addEventListener('change', function () {
                if (field.form) {
                    if (typeof field.form.requestSubmit === 'function') {
                        field.form.requestSubmit();
                    } else {
                        field.form.submit();
                    }
                }
            });
        });

        document.querySelectorAll('[data-booking-color-preview]').forEach(function (input) {
            var preview = document.getElementById(input.getAttribute('data-booking-color-preview') || '');
            if (!preview) {
                return;
            }
            input.addEventListener('input', function () {
                preview.value = input.value;
            });
        });

        document.querySelectorAll('form[data-booking-bulk-form]').forEach(initBulkForm);
    }

    function initBulkForm(form) {
        var all = form.querySelector('[data-booking-check-all]');
        var boxes = Array.prototype.slice.call(form.querySelectorAll('[data-booking-check]'));
        var counter = form.querySelector('[data-booking-selected-count]');
        var submit = form.querySelector('[data-booking-bulk-submit]');
        var select = form.querySelector('select[name="bulk_action"]');

        function update() {
            var selected = boxes.filter(function (box) { return box.checked; }).length;
            if (counter) {
                counter.textContent = selected > 0 ? selected + ' ausgewählt' : '';
            }
            if (submit) {
                submit.disabled = selected === 0 || !select || select.value === '';
            }
            if (all) {
                all.checked = selected > 0 && selected === boxes.length;
                all.indeterminate = selected > 0 && selected < boxes.length;
            }
        }

        if (all) {
            all.addEventListener('change', function () {
                boxes.forEach(function (box) { box.checked = all.checked; });
                update();
            });
        }
        boxes.forEach(function (box) { box.addEventListener('change', update); });
        if (select) {
            select.addEventListener('change', update);
        }
        update();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
