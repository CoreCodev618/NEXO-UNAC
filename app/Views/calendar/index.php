<?php
/**
 * Calendario mensual y agenda de un espacio.
 *
 * @var array  $workspace
 * @var array  $days
 * @var array  $agenda
 * @var array  $upcoming
 * @var array  $tasks
 * @var array  $members
 * @var array  $weekdays
 * @var string $monthTitle
 * @var string $view
 * @var string $monthUrl
 * @var string $agendaUrl
 * @var string $prevUrl
 * @var string $nextUrl
 * @var string $todayUrl
 * @var string $agendaUrl
 * @var string $prefix
 * @var string $eventsUrl
 * @var bool   $isPreview
 */
$workspaceId = (int) $workspace['id'];
$monthTaskCount = count(array_filter($tasks, static fn (array $task): bool => $task['due_date'] !== null && str_starts_with((string) $task['due_date'], (string) $month)));
?>
<div class="nx-page nx-page--wide">
    <header class="nx-page__head">
        <div class="nx-page__head-main">
            <p class="nx-eyebrow">Calendario · <?= esc($workspace['name']) ?></p>
            <h1 class="nx-page__title">El mes, sin sorpresas.</h1>
            <p class="nx-page__sub">
                Entregas, tareas y fechas del espacio en una sola vista. Cambia al modo agenda para leer el mes como una lista.
            </p>
            <div class="nx-page__meta">
                <span><?= nexo_icon('calendar') ?><?= $monthTaskCount ?> tareas con fecha este mes</span>
                <span><?= nexo_icon('clock') ?><?= count($upcoming) ?> próximas</span>
            </div>
        </div>
        <div class="nx-page__actions">
            <button class="nx-btn nx-btn--secondary" type="button" data-task-new>
                <?= nexo_icon('plus') ?>
                Nueva tarea
            </button>
            <a class="nx-btn nx-btn--primary" href="<?= nexo_url($prefix, 'workspaces/' . $workspaceId . '/kanban') ?>">
                <?= nexo_icon('kanban') ?>
                Abrir tablero
            </a>
        </div>
    </header>

    <nav class="nx-tabs nx-workspace-tabs" aria-label="Vistas del espacio">
        <a class="nx-tab" href="<?= nexo_url($prefix, 'workspaces/' . $workspaceId) ?>"><?= nexo_icon('file-text') ?>Páginas</a>
        <a class="nx-tab" href="<?= nexo_url($prefix, 'workspaces/' . $workspaceId . '/kanban') ?>"><?= nexo_icon('kanban') ?>Tablero</a>
        <a class="nx-tab" href="<?= nexo_url($prefix, 'workspaces/' . $workspaceId . '/calendar') ?>" aria-current="page"><?= nexo_icon('calendar') ?>Calendario</a>
    </nav>

    <div class="nx-calendar-toolbar">
        <div class="nx-calendar-toolbar__left">
            <div class="nx-calendar__nav" aria-label="Navegación del calendario">
                <a class="nx-icon-btn" href="<?= esc($prevUrl, 'attr') ?>" aria-label="Mes anterior" data-tip="Mes anterior"><?= nexo_icon('chevron-left') ?></a>
                <a class="nx-btn nx-btn--secondary nx-btn--sm" href="<?= esc($todayUrl, 'attr') ?>">Hoy</a>
                <a class="nx-icon-btn" href="<?= esc($nextUrl, 'attr') ?>" aria-label="Mes siguiente" data-tip="Mes siguiente"><?= nexo_icon('chevron-right') ?></a>
            </div>
            <h2 class="nx-calendar__month"><?= esc($monthTitle) ?></h2>
        </div>
        <div class="nx-calendar-toolbar__right">
            <div class="nx-segmented" role="group" aria-label="Vista del calendario">
                <a class="nx-segmented__item<?= $view === 'month' ? ' is-active' : '' ?>" href="<?= esc($monthUrl, 'attr') ?>" <?= $view === 'month' ? 'aria-current="page"' : '' ?>>
                    <?= nexo_icon('calendar') ?> Mes
                </a>
                <a class="nx-segmented__item<?= $view === 'agenda' ? ' is-active' : '' ?>" href="<?= esc($agendaUrl, 'attr') ?>" <?= $view === 'agenda' ? 'aria-current="page"' : '' ?>>
                    <?= nexo_icon('list') ?> Agenda
                </a>
            </div>
        </div>
    </div>

    <div class="nx-calendar-layout">
        <div>
            <?php if ($view === 'agenda'): ?>
                <div class="nx-agenda">
                    <?php if ($agenda === []): ?>
                        <div class="nx-empty" style="padding: var(--space-12) var(--space-4);">
                            <?= nexo_illustration('calendar') ?>
                            <p class="nx-empty__title">Este mes está libre</p>
                            <p class="nx-empty__text">No hay tareas con fecha en <?= esc($monthTitle) ?>. Puedes crear una desde el botón “Nueva tarea”.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($agenda as $group): ?>
                            <section class="nx-agenda__group" id="day-<?= esc($group['date'], 'attr') ?>">
                                <div class="nx-agenda__date">
                                    <strong><?= esc($group['label']) ?></strong>
                                    <?= esc($group['weekday']) ?>
                                </div>
                                <div class="nx-agenda__items">
                                    <?php foreach ($group['tasks'] as $task): ?>
                                        <?php $taskUrl = $task['page_id'] !== null ? nexo_url($prefix, 'pages/' . $task['page_id']) : nexo_url($prefix, 'workspaces/' . $workspaceId . '/kanban'); ?>
                                        <a class="nx-agenda__item" href="<?= $taskUrl ?>">
                                            <span class="nx-priority nx-priority--<?= esc($task['priority'], 'attr') ?>"><?= esc(nexo_priority_label($task['priority'])) ?></span>
                                            <span class="nx-agenda__item__main">
                                                <span class="nx-agenda__title"><?= esc($task['title']) ?></span>
                                                <span class="nx-agenda__meta">
                                                    <span><?= nexo_icon('user') ?><?= esc($task['assignee_name']) ?></span>
                                                    <?php if ($task['page_title'] !== null): ?>
                                                        <span><?= nexo_icon('file-text') ?><?= esc($task['page_title']) ?></span>
                                                    <?php endif ?>
                                                    <span><?= nexo_icon('check-square') ?><?= esc(nexo_status_label($task['status'])) ?></span>
                                                </span>
                                            </span>
                                        </a>
                                    <?php endforeach ?>
                                </div>
                            </section>
                        <?php endforeach ?>
                    <?php endif ?>
                </div>
            <?php else: ?>
                <div class="nx-calendar-scroll" tabindex="0" role="region" aria-label="Calendario mensual. En pantallas pequeñas puedes desplazarte horizontalmente.">
                    <div class="nx-calendar">
                        <div class="nx-calendar__weekdays" aria-hidden="true">
                            <?php foreach ($weekdays as $index => $weekday): ?>
                                <div class="nx-calendar__weekday<?= $index >= 5 ? ' nx-calendar__weekday--weekend' : '' ?>"><?= esc($weekday) ?></div>
                            <?php endforeach ?>
                        </div>

                        <div class="nx-calendar__grid" data-calendar-grid>
                            <?php foreach ($days as $day): ?>
                                <div
                                    class="nx-calendar__day<?= ! $day['in_month'] ? ' is-outside' : '' ?><?= $day['is_today'] ? ' is-today' : '' ?><?= $day['is_weekend'] ? ' is-weekend' : '' ?>"
                                    data-date="<?= esc($day['date'], 'attr') ?>"
                                >
                                    <div class="nx-calendar__day-head">
                                        <span class="nx-calendar__date" aria-label="<?= esc($day['date'], 'attr') ?>"><?= (int) $day['day'] ?></span>
                                        <button class="nx-calendar__add" type="button" data-task-new data-task-date="<?= esc($day['date'], 'attr') ?>" aria-label="Crear tarea el <?= esc($day['date'], 'attr') ?>">
                                            <?= nexo_icon('plus') ?>
                                        </button>
                                    </div>

                                    <div class="nx-calendar__tasks">
                                        <?php foreach ($day['tasks'] as $task): ?>
                                            <?php $taskUrl = $task['page_id'] !== null ? nexo_url($prefix, 'pages/' . $task['page_id']) : nexo_url($prefix, 'workspaces/' . $workspaceId . '/kanban'); ?>
                                            <a
                                                class="nx-calendar__task nx-calendar__task--<?= esc($task['priority'], 'attr') ?><?= $task['status'] === 'done' ? ' is-done' : '' ?>"
                                                href="<?= $taskUrl ?>"
                                                title="<?= esc($task['title'], 'attr') ?>"
                                            >
                                                <?= nexo_icon($task['status'] === 'done' ? 'check' : 'dot') ?>
                                                <span><?= esc($task['title']) ?></span>
                                            </a>
                                        <?php endforeach ?>

                                        <?php if ($day['task_count'] > count($day['tasks'])): ?>
                                            <a class="nx-calendar__more" href="<?= esc($agendaUrl, 'attr') ?>#day-<?= esc($day['date'], 'attr') ?>">
                                                +<?= $day['task_count'] - count($day['tasks']) ?> más
                                            </a>
                                        <?php endif ?>
                                    </div>

                                    <?php if ($day['is_today']): ?>
                                        <span class="nx-calendar__note"><?= nexo_icon('clock') ?>Hoy</span>
                                    <?php endif ?>
                                </div>
                            <?php endforeach ?>
                        </div>
                    </div>
                </div>
                <p class="nx-hint" style="margin-top: 12px;">
                    <?= nexo_icon('info') ?>
                    Pasa el cursor por un día para crear una tarea. En pantallas pequeñas, desliza el calendario para ver la semana completa.
                </p>
            <?php endif ?>
        </div>

        <aside class="nx-calendar-aside" aria-label="Próximas fechas">
            <section class="nx-card">
                <div class="nx-row nx-row--between" style="margin-bottom: 8px;">
                    <h2 class="nx-section__title" style="font-size: var(--text-lg);">Lo que viene</h2>
                    <a class="nx-link-arrow" href="<?= nexo_url($prefix, 'workspaces/' . $workspaceId . '/kanban') ?>">Ver todo <?= nexo_icon('arrow-right') ?></a>
                </div>

                <?php if ($upcoming !== []): ?>
                    <div class="nx-upcoming">
                        <?php foreach ($upcoming as $task): ?>
                            <?php
                            $dateObject = new DateTimeImmutable((string) $task['due_date']);
                            $taskUrl = $task['page_id'] !== null ? nexo_url($prefix, 'pages/' . $task['page_id']) : nexo_url($prefix, 'workspaces/' . $workspaceId . '/kanban');
                            ?>
                            <a class="nx-upcoming__item" href="<?= $taskUrl ?>">
                                <span class="nx-upcoming__date" aria-hidden="true">
                                    <?= esc(nexo_month_name((int) $dateObject->format('n'), true)) ?>
                                    <strong><?= (int) $dateObject->format('j') ?></strong>
                                </span>
                                <span class="nx-upcoming__main">
                                    <span class="nx-upcoming__title"><?= esc($task['title']) ?></span>
                                    <span class="nx-upcoming__meta"><?= esc(nexo_priority_label($task['priority'])) ?> · <?= esc($task['assignee_name']) ?></span>
                                </span>
                            </a>
                        <?php endforeach ?>
                    </div>
                <?php else: ?>
                    <p class="nx-hint">No hay tareas abiertas con fecha en este espacio.</p>
                <?php endif ?>
            </section>

            <section class="nx-card">
                <h2 class="nx-section__title" style="font-size: var(--text-lg);">Cómo leer el color</h2>
                <div class="nx-calendar-legend" style="margin-top: 12px;">
                    <div class="nx-calendar-legend__row"><span class="nx-calendar-legend__swatch nx-calendar-legend__swatch--alta"></span>Prioridad alta</div>
                    <div class="nx-calendar-legend__row"><span class="nx-calendar-legend__swatch nx-calendar-legend__swatch--media"></span>Prioridad media</div>
                    <div class="nx-calendar-legend__row"><span class="nx-calendar-legend__swatch"></span>Prioridad baja</div>
                    <div class="nx-calendar-legend__row"><span class="nx-calendar-legend__swatch nx-calendar-legend__swatch--event"></span>Evento del Radar (pendiente)</div>
                </div>
                <p class="nx-hint" style="margin-top: 12px;">
                    El calendario no inventa eventos de la FIIS. Cuando el backend de Radar esté listo, aparecerán aquí con el mismo lenguaje visual.
                </p>
                <?php if ($eventsUrl !== null): ?>
                    <a class="nx-link-arrow" href="<?= esc($eventsUrl, 'attr') ?>" style="margin-top: 10px;">Ver el estado del Radar <?= nexo_icon('arrow-right') ?></a>
                <?php endif ?>
            </section>
        </aside>
    </div>
</div>

<?= $this->include('partials/task-modal') ?>
