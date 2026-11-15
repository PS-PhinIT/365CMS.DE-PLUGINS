/**
 * CMS Booking – Public JavaScript
 * Kalender-Widget, Slot-Laden, Formular-Stepper
 *
 * @package CMS_Booking
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', init);

    function init() {
        initCalendar();
    }

    /* =================================================================== */
    /*  Kalender                                                            */
    /* =================================================================== */

    function initCalendar() {
        const el = document.getElementById('bookingCalendar');
        if (!el) return;

        const providerId    = parseInt(el.dataset.providerId, 10);
        const serviceId     = parseInt(el.dataset.serviceId, 10);
        const apiUrl        = el.dataset.apiUrl || '/api/booking/slots';
        let i18n = {};
        try {
            i18n = JSON.parse(el.dataset.i18n || '{}');
        } catch (error) {
            i18n = {};
        }
        const monthNames = Array.isArray(i18n.monthNames) && i18n.monthNames.length === 12
            ? i18n.monthNames
            : [
                'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni',
                'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'
            ];
        const dayLabels = Array.isArray(i18n.dayLabels) && i18n.dayLabels.length === 7
            ? i18n.dayLabels
            : ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];
        const clockLabel = i18n.clockLabel || 'Uhr';
        const loadingSlotsText = i18n.loadingSlots || 'Zeitfenster werden geladen…';
        const noSlotsText = i18n.noSlots || 'Keine freien Zeiten an diesem Tag.';
        const slotLoadErrorText = i18n.slotLoadError || 'Fehler beim Laden der Zeitfenster.';
        const summaryAtText = i18n.summaryAt || 'um';
        let availableDates = [];
        try {
            availableDates = JSON.parse(el.dataset.availableDates || '[]');
        } catch (error) {
            availableDates = [];
        }
        const availSet       = new Set(availableDates);

        let currentYear  = new Date().getFullYear();
        let currentMonth = new Date().getMonth();
        let selectedDate = null;

        render();

        function createNode(tagName, className, text) {
            const node = document.createElement(tagName);
            if (className) {
                node.className = className;
            }
            if (text !== undefined) {
                node.textContent = String(text);
            }

            return node;
        }

        function render() {
            const firstDay  = new Date(currentYear, currentMonth, 1);
            const lastDay   = new Date(currentYear, currentMonth + 1, 0);
            const startDow  = (firstDay.getDay() + 6) % 7; // Mo=0
            const daysInMonth = lastDay.getDate();
            const today = new Date();
            today.setHours(0, 0, 0, 0);

            const header = createNode('div', 'booking-cal-header');
            const prevButton = createNode('button', '', '‹');
            prevButton.type = 'button';
            prevButton.dataset.calPrev = '';
            const title = createNode('span', '', monthNames[currentMonth] + ' ' + currentYear);
            const nextButton = createNode('button', '', '›');
            nextButton.type = 'button';
            nextButton.dataset.calNext = '';
            header.append(prevButton, title, nextButton);

            const grid = createNode('div', 'booking-cal-grid');
            dayLabels.forEach(function (d) {
                grid.appendChild(createNode('div', 'cal-day-label', d));
            });

            // Leere Zellen vor dem 1.
            for (let i = 0; i < startDow; i++) {
                grid.appendChild(createNode('div', 'cal-day empty'));
            }

            for (let d = 1; d <= daysInMonth; d++) {
                const dateStr = currentYear + '-' +
                    String(currentMonth + 1).padStart(2, '0') + '-' +
                    String(d).padStart(2, '0');
                const dateObj = new Date(currentYear, currentMonth, d);
                const isToday = dateObj.getTime() === today.getTime();
                const isAvail = availSet.has(dateStr);
                const isSel   = selectedDate === dateStr;

                let cls = 'cal-day';
                if (isToday) cls += ' today';
                if (isAvail) cls += ' available';
                if (isSel) cls += ' selected';

                const day = createNode('button', cls, d);
                day.type = 'button';
                day.dataset.date = dateStr;
                day.disabled = !isAvail;
                grid.appendChild(day);
            }

            el.replaceChildren(header, grid);

            // Events
            prevButton.addEventListener('click', function () {
                currentMonth--;
                if (currentMonth < 0) { currentMonth = 11; currentYear--; }
                render();
            });
            nextButton.addEventListener('click', function () {
                currentMonth++;
                if (currentMonth > 11) { currentMonth = 0; currentYear++; }
                render();
            });

            el.querySelectorAll('.cal-day.available').forEach(function (cell) {
                cell.addEventListener('click', function () {
                    selectedDate = this.dataset.date;
                    document.getElementById('bookingDate').value = selectedDate;
                    render();
                    loadSlots(providerId, serviceId, selectedDate, apiUrl);
                    showStep('step-time');
                });
            });
        }
    }

    /* =================================================================== */
    /*  Slots laden                                                         */
    /* =================================================================== */

    function loadSlots(providerId, serviceId, date, apiUrl) {
        const container = document.getElementById('bookingSlots');
        if (!container) return;

        renderMessage(container, loadingSlotsText);

        const url = apiUrl + '/' + encodeURIComponent(providerId) + '/' + encodeURIComponent(date)
            + '?service_id=' + encodeURIComponent(serviceId);

        fetch(url)
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (!data.success || !data.slots || data.slots.length === 0) {
                    renderMessage(container, noSlotsText);
                    return;
                }

                container.replaceChildren();
                data.slots.forEach(function (slot) {
                    var btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'booking-slot-btn';
                    btn.dataset.time = String(slot);
                    btn.textContent = String(slot) + ' ' + clockLabel;
                    container.appendChild(btn);
                });

                container.querySelectorAll('.booking-slot-btn').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        container.querySelectorAll('.booking-slot-btn').forEach(function (b) {
                            b.classList.remove('selected');
                        });
                        this.classList.add('selected');
                        document.getElementById('startTime').value = this.dataset.time;
                        showStep('step-contact');
                        showStep('step-submit');
                        updateSummary();
                    });
                });
            })
            .catch(function () {
                renderMessage(container, slotLoadErrorText);
            });
    }

    function renderMessage(container, message) {
        var note = document.createElement('p');
        note.className = 'text-muted';
        note.textContent = message;
        container.replaceChildren(note);
    }

    /* =================================================================== */
    /*  Step-Steuerung                                                      */
    /* =================================================================== */

    function showStep(stepId) {
        var step = document.getElementById(stepId);
        if (step) step.hidden = false;
    }

    /* =================================================================== */
    /*  Zusammenfassung                                                     */
    /* =================================================================== */

    function updateSummary() {
        var summary = document.getElementById('bookingSummary');
        if (!summary) return;

        var date = document.getElementById('bookingDate').value;
        var time = document.getElementById('startTime').value;

        if (date && time) {
            var parts = date.split('-');
            var formatted = parts[2] + '.' + parts[1] + '.' + parts[0];
            summary.textContent = formatted + ' ' + summaryAtText + ' ' + time + ' ' + clockLabel;
        }
    }

})();
