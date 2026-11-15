/*!
 * CMS Beratung – Formular-Anfragen: „Alle auswählen“ für die Sammelaktion.
 * Die Checkboxen gehören per form-Attribut zum Formular #beratung-bulk; Bestätigung über data-cms-confirm (Core).
 */
(function () {
    'use strict';

    var selectAll = document.querySelector('[data-beratung-select-all]');
    if (!selectAll) {
        return;
    }

    var items = function () { return Array.prototype.slice.call(document.querySelectorAll('[data-beratung-select-item]')); };
    var sync = function () {
        var all = items();
        var checked = all.filter(function (item) { return item.checked; }).length;
        selectAll.checked = all.length > 0 && checked === all.length;
        selectAll.indeterminate = checked > 0 && checked < all.length;
    };

    selectAll.addEventListener('change', function () {
        items().forEach(function (item) { item.checked = selectAll.checked; });
        sync();
    });
    document.addEventListener('change', function (event) {
        if (event.target && event.target.matches && event.target.matches('[data-beratung-select-item]')) {
            sync();
        }
    });
})();
