/**
 * CMS Projects – Ticket-Drag-and-Drop im Admin-Board.
 * Ausgelagert aus dem Admin-Template (CSP-konform, keine Inline-Skripte).
 */
(function () {
    var moveForm = document.querySelector('[data-cp-task-move-form]');
    if (!moveForm) {
        return;
    }

    var taskInput = moveForm.querySelector('input[name="task_id"]');
    var boardInput = moveForm.querySelector('input[name="target_board_id"]');
    var columnInput = moveForm.querySelector('input[name="target_column_key"]');
    var orderedInput = moveForm.querySelector('input[name="ordered_task_ids"]');
    var draggedTaskId = '';
    var draggedBoardId = '';
    var draggedColumnKey = '';
    var draggedCard = null;
    var sourceStack = null;
    var sourceOrder = '';

    var getStackOrder = function (stack) {
        return Array.prototype.map.call(stack.querySelectorAll('.cp-ticket-card--draggable[data-task-id]'), function (card) {
            return card.getAttribute('data-task-id') || '';
        }).filter(function (taskId) {
            return taskId !== '';
        });
    };

    var syncDropHints = function () {
        document.querySelectorAll('.cp-board-preview-column').forEach(function (column) {
            var stack = column.querySelector('[data-ticket-stack]');
            var hint = column.querySelector('.cp-ticket-dropzone-hint');
            if (!stack || !hint) {
                return;
            }

            hint.classList.toggle('cp-ticket-dropzone-hint--hidden', stack.querySelector('.cp-ticket-card--draggable[data-task-id]') !== null);
        });
    };

    var getDragAfterElement = function (stack, clientY) {
        var cards = Array.prototype.slice.call(stack.querySelectorAll('.cp-ticket-card--draggable[data-task-id]:not(.cp-ticket-card--dragging)'));

        return cards.reduce(function (closest, card) {
            var box = card.getBoundingClientRect();
            var offset = clientY - box.top - (box.height / 2);

            if (offset < 0 && offset > closest.offset) {
                return { offset: offset, element: card };
            }

            return closest;
        }, { offset: Number.NEGATIVE_INFINITY, element: null }).element;
    };

    var clearTargets = function () {
        document.querySelectorAll('.cp-board-preview-column--drop-target').forEach(function (element) {
            element.classList.remove('cp-board-preview-column--drop-target');
        });
    };

    document.querySelectorAll('.cp-ticket-card--draggable[data-task-id]').forEach(function (ticketCard) {
        ticketCard.addEventListener('dragstart', function (event) {
            var currentColumn = ticketCard.closest('.cp-board-preview-column[data-drop-board-id][data-drop-column-key]');
            var currentStack = ticketCard.closest('[data-ticket-stack]');
            draggedTaskId = ticketCard.getAttribute('data-task-id') || '';
            draggedBoardId = currentColumn ? (currentColumn.getAttribute('data-drop-board-id') || '') : '';
            draggedColumnKey = currentColumn ? (currentColumn.getAttribute('data-drop-column-key') || '') : '';
            draggedCard = ticketCard;
            sourceStack = currentStack;
            sourceOrder = currentStack ? getStackOrder(currentStack).join(',') : '';
            ticketCard.classList.add('cp-ticket-card--dragging');

            if (event.dataTransfer) {
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('text/plain', draggedTaskId);
            }
        });

        ticketCard.addEventListener('dragend', function () {
            draggedTaskId = '';
            draggedBoardId = '';
            draggedColumnKey = '';
            draggedCard = null;
            sourceStack = null;
            sourceOrder = '';
            ticketCard.classList.remove('cp-ticket-card--dragging');
            clearTargets();
            syncDropHints();
        });
    });

    document.querySelectorAll('.cp-board-preview-column[data-drop-board-id][data-drop-column-key]').forEach(function (column) {
        var stack = column.querySelector('[data-ticket-stack]');

        if (!stack) {
            return;
        }

        stack.addEventListener('dragover', function (event) {
            if (!draggedTaskId) {
                return;
            }

            event.preventDefault();
            if (draggedCard) {
                var afterElement = getDragAfterElement(stack, event.clientY);
                if (afterElement === null) {
                    stack.appendChild(draggedCard);
                } else if (afterElement !== draggedCard) {
                    stack.insertBefore(draggedCard, afterElement);
                }
            }

            clearTargets();
            column.classList.add('cp-board-preview-column--drop-target');
            syncDropHints();
        });

        stack.addEventListener('drop', function (event) {
            var targetBoardId;
            var targetColumnKey;
            var taskId;
            var orderedTaskIds;

            if (!draggedTaskId) {
                return;
            }

            event.preventDefault();
            targetBoardId = column.getAttribute('data-drop-board-id') || '';
            targetColumnKey = column.getAttribute('data-drop-column-key') || '';
            taskId = draggedTaskId;
            orderedTaskIds = getStackOrder(stack);

            clearTargets();
            syncDropHints();

            if (!taskId || !targetBoardId || !targetColumnKey) {
                return;
            }

            if (orderedTaskIds.length === 0) {
                return;
            }

            if (draggedBoardId === targetBoardId && draggedColumnKey === targetColumnKey && orderedTaskIds.join(',') === sourceOrder) {
                return;
            }

            taskInput.value = taskId;
            boardInput.value = targetBoardId;
            columnInput.value = targetColumnKey;
            orderedInput.value = orderedTaskIds.join(',');
            moveForm.submit();
        });

        column.addEventListener('dragover', function (event) {
            if (!draggedTaskId) {
                return;
            }

            event.preventDefault();
            clearTargets();
            column.classList.add('cp-board-preview-column--drop-target');
        });
    });

    syncDropHints();
}());
