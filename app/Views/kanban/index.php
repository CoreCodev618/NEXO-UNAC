<?php
/**
 * Tablero kanban de un espacio.
 *
 * @var array  $workspace
 * @var array  $tasks
 * @var array  $columns
 * @var array  $members
 * @var string $prefix
 * @var bool   $isPreview
 */
$workspaceId = (int) $workspace['id'];
$grouped = ['todo' => [], 'doing' => [], 'done' => []];

foreach ($tasks as $task) {
    if (isset($grouped[$task['status']])) {
        $grouped[$task['status']][] = $task;
    }
}

$openCount = count($grouped['todo']) + count($grouped['doing']);
$doneCount = count($grouped['done']);
?>
<div class="nx-page nx-page--wide">
    <header class="nx-page__head">
        <div class="nx-page__head-main">
            <p class="nx-eyebrow">Tablero</p>
            <h1 class="nx-page__title"><?= esc($workspace['name']) ?>, en movimiento.</h1>
            <p class="nx-page__sub">
                Arrastra una tarjeta para cambiar su estado. Las tareas con fecha también aparecen en el calendario.
            </p>
            <div class="nx-page__meta">
                <span><?= nexo_icon('check-square') ?><?= $openCount ?> abiertas</span>
                <span><?= nexo_icon('check-circle') ?><?= $doneCount ?> hechas</span>
                <span><?= nexo_icon('alert-triangle') ?><?= count(array_filter($tasks, static fn (array $task): bool => $task['priority'] === 'alta')) ?> de prioridad alta</span>
            </div>
        </div>
        <div class="nx-page__actions">
            <a class="nx-btn nx-btn--secondary" href="<?= nexo_url($prefix, 'workspaces/' . $workspaceId . '/calendar') ?>">
                <?= nexo_icon('calendar') ?>
                Calendario
            </a>
            <button class="nx-btn nx-btn--primary" type="button" data-task-new>
                <?= nexo_icon('plus') ?>
                Nueva tarea
            </button>
        </div>
    </header>

    <nav class="nx-tabs nx-workspace-tabs" aria-label="Vistas del espacio">
        <a class="nx-tab" href="<?= nexo_url($prefix, 'workspaces/' . $workspaceId) ?>">
            <?= nexo_icon('file-text') ?>
            Páginas
        </a>
        <a class="nx-tab" href="<?= nexo_url($prefix, 'workspaces/' . $workspaceId . '/kanban') ?>" aria-current="page">
            <?= nexo_icon('kanban') ?>
            Tablero
        </a>
        <a class="nx-tab" href="<?= nexo_url($prefix, 'workspaces/' . $workspaceId . '/calendar') ?>">
            <?= nexo_icon('calendar') ?>
            Calendario
        </a>
    </nav>

    <div class="nx-board-toolbar">
        <div class="nx-board-toolbar__left">
            <div class="nx-board-summary">
                <span><?= nexo_icon('circle-help') ?><?= count($tasks) ?> tareas en total</span>
                <?php if ($isPreview): ?>
                    <span><?= nexo_icon('info') ?> Los cambios se quedan en esta vista demo</span>
                <?php endif ?>
            </div>
        </div>
        <div class="nx-board-toolbar__right">
            <label class="nx-filter">
                <?= nexo_icon('filter') ?>
                <span class="nx-sr-only">Filtrar por prioridad</span>
                <select data-board-filter-priority>
                    <option value="">Toda prioridad</option>
                    <option value="alta">Alta</option>
                    <option value="media">Media</option>
                    <option value="baja">Baja</option>
                </select>
            </label>
            <label class="nx-filter">
                <?= nexo_icon('user') ?>
                <span class="nx-sr-only">Filtrar por responsable</span>
                <select data-board-filter-assignee>
                    <option value="">Todo el equipo</option>
                    <?php foreach ($members as $member): ?>
                        <option value="<?= (int) $member['id'] ?>"><?= esc($member['name']) ?></option>
                    <?php endforeach ?>
                </select>
            </label>
            <a class="nx-btn nx-btn--secondary nx-btn--sm" href="<?= nexo_url($prefix, 'workspaces/' . $workspaceId . '/calendar') ?>">
                <?= nexo_icon('calendar') ?>
                Ver fechas
            </a>
        </div>
    </div>

    <div class="nx-board" data-board data-workspace-id="<?= $workspaceId ?>">
        <?php foreach ($columns as $status => $label): ?>
            <?php $columnTasks = $grouped[$status]; ?>
            <section class="nx-board__col" data-status="<?= esc($status, 'attr') ?>" aria-labelledby="nx-board-<?= esc($status, 'attr') ?>">
                <header class="nx-board__head">
                    <span class="nx-board__dot" aria-hidden="true"></span>
                    <h2 class="nx-board__title" id="nx-board-<?= esc($status, 'attr') ?>"><?= esc($label) ?></h2>
                    <span class="nx-board__count" data-board-count><?= count($columnTasks) ?></span>
                    <div class="nx-board__head-actions">
                        <button class="nx-icon-btn nx-icon-btn--sm" type="button" data-task-new data-task-status="<?= esc($status, 'attr') ?>" aria-label="Nueva tarea en <?= esc($label, 'attr') ?>" data-tip="Nueva tarea">
                            <?= nexo_icon('plus') ?>
                        </button>
                    </div>
                </header>

                <div class="nx-board__list" data-board-list data-status="<?= esc($status, 'attr') ?>">
                    <?php foreach ($columnTasks as $task): ?>
                        <article
                            class="nx-task"
                            data-task-card
                            data-task-id="<?= (int) $task['id'] ?>"
                            data-task-status="<?= esc($task['status'], 'attr') ?>"
                            data-task-priority="<?= esc($task['priority'], 'attr') ?>"
                            data-task-assignee="<?= (int) $task['assigned_to'] ?>"
                            data-task-title="<?= esc($task['title'], 'attr') ?>"
                            data-task-due="<?= esc($task['due_date'] ?? '', 'attr') ?>"
                            data-task-page="<?= $task['page_id'] !== null ? (int) $task['page_id'] : '' ?>"
                            data-task-page-title="<?= esc($task['page_title'] ?? '', 'attr') ?>"
                            data-task-assignee-name="<?= esc($task['assignee_name'], 'attr') ?>"
                            data-task-initials="<?= esc($task['assignee_initials'], 'attr') ?>"
                            data-task-tone="<?= esc($task['assignee_tone'], 'attr') ?>"
                            aria-label="<?= esc($task['title'], 'attr') ?>"
                        >
                            <div class="nx-task__head">
                                <button class="nx-task__title" type="button" data-task-edit="<?= (int) $task['id'] ?>" style="border:0; background:none; padding:0; text-align:left; font:inherit; color:inherit;">
                                    <?= esc($task['title']) ?>
                                </button>
                                <div class="nx-dropdown">
                                    <button class="nx-icon-btn nx-task__menu-btn" type="button" data-menu-trigger="task-menu-<?= (int) $task['id'] ?>" aria-expanded="false" aria-label="Opciones de la tarea">
                                        <?= nexo_icon('more-horizontal') ?>
                                    </button>
                                    <div class="nx-menu nx-menu--right" id="task-menu-<?= (int) $task['id'] ?>" hidden>
                                        <button class="nx-menu__item" type="button" data-task-edit="<?= (int) $task['id'] ?>">
                                            <?= nexo_icon('pencil') ?>
                                            <span>Editar tarea</span>
                                        </button>
                                        <?php if ($task['page_id'] !== null): ?>
                                            <a class="nx-menu__item" href="<?= nexo_url($prefix, 'pages/' . $task['page_id']) ?>">
                                                <?= nexo_icon('file-text') ?>
                                                <span>Abrir página</span>
                                            </a>
                                        <?php endif ?>
                                        <div class="nx-menu__separator"></div>
                                        <button class="nx-menu__item nx-menu__item--danger" type="button" data-task-delete="<?= (int) $task['id'] ?>">
                                            <?= nexo_icon('trash') ?>
                                            <span>Eliminar</span>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="nx-task__meta">
                                <span class="<?= ! empty($task['is_overdue']) ? 'is-overdue' : '' ?>">
                                    <?= nexo_icon('clock') ?>
                                    <?= esc($task['due_label']) ?>
                                </span>
                                <span class="nx-priority nx-priority--<?= esc($task['priority'], 'attr') ?>">
                                    <?= esc(nexo_priority_label($task['priority'])) ?>
                                </span>
                            </div>

                            <div class="nx-task__foot">
                                <?php if ($task['page_title'] !== null): ?>
                                    <a class="nx-task__page" href="<?= nexo_url($prefix, 'pages/' . $task['page_id']) ?>">
                                        <?= nexo_icon('file-text') ?>
                                        <span><?= esc($task['page_title']) ?></span>
                                    </a>
                                <?php else: ?>
                                    <span class="nx-task__page nx-muted"><?= nexo_icon('file-text') ?><span>Sin página</span></span>
                                <?php endif ?>

                                <span class="nx-task__assignee">
                                    <span class="nx-avatar nx-avatar--sm nx-avatar--<?= esc($task['assignee_tone'], 'attr') ?>" title="<?= esc($task['assignee_name'], 'attr') ?>" aria-label="<?= esc($task['assignee_name'], 'attr') ?>">
                                        <?= esc($task['assignee_initials']) ?>
                                    </span>
                                </span>
                            </div>
                        </article>
                    <?php endforeach ?>

                    <div class="nx-board__empty" data-board-empty <?= $columnTasks !== [] ? 'hidden' : '' ?>>
                        <?= $status === 'done' ? 'Aún no hay tareas cerradas.' : 'Arrastra una tarjeta aquí o crea una nueva.' ?>
                    </div>
                </div>

                <div class="nx-board__foot">
                    <button class="nx-board__add" type="button" data-task-new data-task-status="<?= esc($status, 'attr') ?>">
                        <?= nexo_icon('plus') ?>
                        Añadir tarea
                    </button>
                </div>
            </section>
        <?php endforeach ?>
    </div>

    <p class="nx-hint" style="margin-top: 18px;">
        <?= nexo_icon('grip-vertical') ?>
        Arrastra las tarjetas entre columnas. El estado se guarda al soltar.
    </p>
</div>

<?= $this->include('partials/task-modal') ?>
