/**
 * CMS Projects – Admin-Interaktionen (CSP-konform: externe Datei, nur DOM-APIs, keine Inline-Handler).
 *
 * - Ticket-Drag-and-drop in der Board-Ansicht (sendet das versteckte, CSRF-geschützte Formular)
 * - Projektauswahl sendet das Filterformular ab ([data-cp-autosubmit])
 * - „Alle auswählen“ in Tabellen mit Sammelaktionen ([data-cp-check-all])
 * - Ticketformular: Spaltenauswahl auf das gewählte Board einschränken
 */
(function () {
    'use strict';

    var initAutosubmit = function () {
        document.querySelectorAll('select[data-cp-autosubmit]').forEach(function (select) {
            select.addEventListener('change', function () {
                if (select.form) {
                    select.form.submit();
                }
            });
        });
    };

    var initCheckAll = function () {
        document.querySelectorAll('input[data-cp-check-all]').forEach(function (master) {
            var table = master.closest('table');
            if (!table) {
                return;
            }

            master.addEventListener('change', function () {
                table.querySelectorAll('tbody input[type="checkbox"][name="ids[]"]').forEach(function (box) {
                    box.checked = master.checked;
                });
            });
        });
    };

    var initColumnFilter = function () {
        document.querySelectorAll('form[data-cp-task-form]').forEach(function (form) {
            var boardSelect = form.querySelector('[data-cp-board-select]');
            var columnSelect = form.querySelector('[data-cp-column-select]');
            if (!boardSelect || !columnSelect) {
                return;
            }

            var sync = function () {
                var boardId = boardSelect.value;
                var selectedStillVisible = false;

                columnSelect.querySelectorAll('optgroup[data-board-id]').forEach(function (group) {
                    var visible = boardId === '' || group.getAttribute('data-board-id') === boardId;
                    group.hidden = !visible;
                    group.disabled = !visible;
                    group.querySelectorAll('option').forEach(function (option) {
                        if (visible && option.selected) {
                            selectedStillVisible = true;
                        }
                    });
                });

                if (!selectedStillVisible) {
                    var firstVisible = columnSelect.querySelector('optgroup:not([disabled]) option');
                    columnSelect.value = firstVisible ? firstVisible.value : '';
                }
            };

            boardSelect.addEventListener('change', sync);
            sync();
        });
    };

    var initBoardDragDrop = function () {
        var moveForm = document.querySelector('[data-cp-task-move-form]');
        if (!moveForm) {
            return;
        }

        var taskInput = moveForm.querySelector('input[name="task_id"]');
        var boardInput = moveForm.querySelector('input[name="target_board_id"]');
        var columnInput = moveForm.querySelector('input[name="target_column_key"]');
        var orderedInput = moveForm.querySelector('input[name="ordered_task_ids"]');
        var columnSelector = '.cp-board-column--dropzone[data-drop-board-id][data-drop-column-key]';
        var cardSelector = '.cp-ticket-card--draggable[data-task-id]';
        var state = { taskId: '', boardId: '', columnKey: '', card: null, order: '' };

        if (!taskInput || !boardInput || !columnInput || !orderedInput) {
            return;
        }

        var getStackOrder = function (stack) {
            return Array.prototype.map.call(stack.querySelectorAll(cardSelector), function (card) {
                return card.getAttribute('data-task-id') || '';
            }).filter(function (taskId) {
                return taskId !== '';
            });
        };

        var syncDropHints = function () {
            document.querySelectorAll(columnSelector).forEach(function (column) {
                var stack = column.querySelector('[data-ticket-stack]');
                var hint = column.querySelector('.cp-ticket-dropzone-hint');
                if (stack && hint) {
                    hint.classList.toggle('cp-ticket-dropzone-hint--hidden', stack.querySelector(cardSelector) !== null);
                }
            });
        };

        var clearTargets = function () {
            document.querySelectorAll('.cp-board-column--drop-target').forEach(function (element) {
                element.classList.remove('cp-board-column--drop-target');
            });
        };

        var getDragAfterElement = function (stack, clientY) {
            var cards = Array.prototype.slice.call(stack.querySelectorAll(cardSelector + ':not(.cp-ticket-card--dragging)'));

            return cards.reduce(function (closest, card) {
                var box = card.getBoundingClientRect();
                var offset = clientY - box.top - (box.height / 2);
                return offset < 0 && offset > closest.offset ? { offset: offset, element: card } : closest;
            }, { offset: Number.NEGATIVE_INFINITY, element: null }).element;
        };

        document.querySelectorAll(cardSelector).forEach(function (card) {
            card.addEventListener('dragstart', function (event) {
                var column = card.closest(columnSelector);
                var stack = card.closest('[data-ticket-stack]');
                state.taskId = card.getAttribute('data-task-id') || '';
                state.boardId = column ? (column.getAttribute('data-drop-board-id') || '') : '';
                state.columnKey = column ? (column.getAttribute('data-drop-column-key') || '') : '';
                state.card = card;
                state.order = stack ? getStackOrder(stack).join(',') : '';
                card.classList.add('cp-ticket-card--dragging');

                if (event.dataTransfer) {
                    event.dataTransfer.effectAllowed = 'move';
                    event.dataTransfer.setData('text/plain', state.taskId);
                }
            });

            card.addEventListener('dragend', function () {
                state = { taskId: '', boardId: '', columnKey: '', card: null, order: '' };
                card.classList.remove('cp-ticket-card--dragging');
                clearTargets();
                syncDropHints();
            });
        });

        document.querySelectorAll(columnSelector).forEach(function (column) {
            var stack = column.querySelector('[data-ticket-stack]');
            if (!stack) {
                return;
            }

            column.addEventListener('dragover', function (event) {
                if (!state.taskId) {
                    return;
                }

                event.preventDefault();
                if (state.card) {
                    var afterElement = getDragAfterElement(stack, event.clientY);
                    if (afterElement === null) {
                        stack.appendChild(state.card);
                    } else if (afterElement !== state.card) {
                        stack.insertBefore(state.card, afterElement);
                    }
                }

                clearTargets();
                column.classList.add('cp-board-column--drop-target');
                syncDropHints();
            });

            column.addEventListener('drop', function (event) {
                if (!state.taskId) {
                    return;
                }

                event.preventDefault();
                var targetBoardId = column.getAttribute('data-drop-board-id') || '';
                var targetColumnKey = column.getAttribute('data-drop-column-key') || '';
                var orderedTaskIds = getStackOrder(stack);

                clearTargets();
                syncDropHints();

                if (!targetBoardId || !targetColumnKey || orderedTaskIds.length === 0) {
                    return;
                }

                if (state.boardId === targetBoardId && state.columnKey === targetColumnKey && orderedTaskIds.join(',') === state.order) {
                    return;
                }

                taskInput.value = state.taskId;
                boardInput.value = targetBoardId;
                columnInput.value = targetColumnKey;
                orderedInput.value = orderedTaskIds.join(',');
                moveForm.submit();
            });
        });

        syncDropHints();
    };

    var init = function () {
        initAutosubmit();
        initCheckAll();
        initColumnFilter();
        initBoardDragDrop();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}());
