<div class="kanban-toolbar" style="margin-bottom: 20px;">
    <button type="button" data-control="popup" data-handler="onLoadTaskForm"
        data-request-data="project_id: <?= $formModel->id ?>" class="btn btn-primary oc-icon-plus">
        New Task
    </button>
</div>

<div class="kanban-board-integrated" id="project-kanban-board-container">
    <?= $this->makePartial('kanban_board') ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
    $(document).ready(initProjectKanban);
    $(document).on('ajaxUpdate', initProjectKanban);

    function initProjectKanban() {
        const columns = document.querySelectorAll('.kanban-tasks');

        columns.forEach(column => {
            if (column.classList.contains('sortable-initialized')) return;
            column.classList.add('sortable-initialized');

            new Sortable(column, {
                group: 'project-kanban',
                animation: 150,
                ghostClass: 'ghost',
                onEnd: function (evt) {
                    const taskId = evt.item.getAttribute('data-id');
                    const newStatus = evt.to.getAttribute('data-status');
                    const order = Array.from(evt.to.children).map(el => el.getAttribute('data-id'));

                    $.request('onUpdateTaskStatus', {
                        data: {
                            task_id: taskId,
                            status: newStatus,
                            order: order
                        },
                        flash: true
                    });
                }
            });
        });
    }
</script>

<style>
    .kanban-board-integrated {
        display: flex;
        overflow-x: auto;
        padding: 0;
        background: transparent;
        min-height: 500px;
    }

    .kanban-row {
        display: flex;
        gap: 15px;
        width: 100%;
    }

    .kanban-column {
        flex: 1;
        min-width: 250px;
        background: #f1f3f5;
        border-radius: 6px;
        padding: 12px;
        display: flex;
        flex-direction: column;
    }

    .kanban-column h3 {
        margin: 0 0 12px 0;
        font-size: 14px;
        font-weight: 600;
        color: #666;
        text-transform: uppercase;
        border-bottom: 2px solid #ddd;
        padding-bottom: 5px;
    }

    .kanban-tasks {
        flex: 1;
        min-height: 50px;
    }

    .kanban-task {
        background: #fff;
        border-radius: 4px;
        padding: 10px;
        margin-bottom: 10px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        position: relative;
        cursor: pointer;
        border-left: 3px solid #ddd;
    }

    .kanban-task:hover {
        box-shadow: 0 3px 6px rgba(0, 0, 0, 0.15);
    }

    .ghost {
        opacity: 0.5;
        background: #c8ebfb;
    }

    .task-priority.priority-low {
        border-left-color: #5bc0de;
    }

    .task-priority.priority-medium {
        border-left-color: #f0ad4e;
    }

    .task-priority.priority-high {
        border-left-color: #d9534f;
    }

    .task-priority.priority-urgent {
        border-left-color: #000;
    }

    .kanban-task h4 {
        margin: 0 0 5px 0;
        font-size: 13px;
        font-weight: 600;
        color: #333;
    }

    .task-footer {
        margin-top: 8px;
        display: flex;
        justify-content: space-between;
        font-size: 10px;
        color: #888;
    }

    .task-edit {
        position: absolute;
        top: 8px;
        right: 8px;
        font-size: 10px;
        color: #3498db;
        opacity: 0;
        transition: opacity 0.2s;
    }

    .kanban-task:hover .task-edit {
        opacity: 1;
    }
</style>