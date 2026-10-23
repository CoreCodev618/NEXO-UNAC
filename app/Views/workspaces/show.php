<?php
/**
 * Detalle de un espacio.
 *
 * @var array  $workspace
 * @var array  $flatPages
 * @var array  $tasks
 * @var array  $task_counts
 * @var array  $members
 * @var array  $taskCounts
 * @var string $prefix
 * @var bool   $isPreview
 */
$workspaceId = (int) $workspace['id'];
$newPageUrl  = nexo_url($prefix, 'pages/nueva') . '?workspace=' . $workspaceId;
?>
<div class="nx-page">
    <header class="nx-workspace-head">
        <div class="nx-workspace-head__main">
            <span class="nx-icon-tile nx-icon-tile--lg nx-icon-tile--<?= esc($workspace['tone'], 'attr') ?>" aria-hidden="true">
                <?= nexo_icon($workspace['icon']) ?>
            </span>
            <div>
                <div class="nx-row nx-row--wrap" style="gap: 8px;">
                    <span class="nx-chip nx-chip--wine"><?= esc($workspace['type_label']) ?></span>
                    <span class="nx-avatar-stack" aria-label="Miembros del espacio">
                        <?php foreach (array_slice($workspace['members'], 0, 3) as $member): ?>
                            <span class="nx-avatar nx-avatar--sm nx-avatar--<?= esc($member['tone'], 'attr') ?>" title="<?= esc($member['name'], 'attr') ?>"><?= esc($member['initials']) ?></span>
                        <?php endforeach ?>
                    </span>
                    <span class="nx-muted" style="font-size: 10px;"><?= count($workspace['members']) ?> miembros</span>
                </div>
                <h1 class="nx-workspace-head__title"><?= esc($workspace['name']) ?></h1>
                <p class="nx-workspace-head__desc"><?= esc($workspace['summary'] ?? $workspace['type_full']) ?></p>
                <div class="nx-workspace-head__meta">
                    <span><?= nexo_icon('graduation-cap') ?><?= esc($workspace['faculty']) ?></span>
                    <span><?= nexo_icon('file-text') ?><?= count($flatPages) ?> páginas</span>
                    <span><?= nexo_icon('check-square') ?><?= (int) ($taskCounts[$workspaceId] ?? 0) ?> tareas abiertas</span>
                    <span><?= nexo_icon('clock') ?>Actualizado <?= esc(nexo_datetime($workspace['updated_at'])) ?></span>
                </div>
            </div>
        </div>

        <div class="nx-page__actions">
            <button class="nx-btn nx-btn--secondary" type="button" data-task-new>
                <?= nexo_icon('plus') ?>
                Nueva tarea
            </button>
            <a class="nx-btn nx-btn--primary" href="<?= esc($newPageUrl, 'attr') ?>">
                <?= nexo_icon('plus') ?>
                Nueva página
            </a>
        </div>
    </header>

    <nav class="nx-tabs nx-workspace-tabs" aria-label="Vistas del espacio">
        <a class="nx-tab" href="<?= nexo_url($prefix, 'workspaces/' . $workspaceId) ?>" aria-current="page">
            <?= nexo_icon('file-text') ?>
            Páginas
        </a>
        <a class="nx-tab" href="<?= nexo_url($prefix, 'workspaces/' . $workspaceId . '/kanban') ?>">
            <?= nexo_icon('kanban') ?>
            Tablero
        </a>
        <a class="nx-tab" href="<?= nexo_url($prefix, 'workspaces/' . $workspaceId . '/calendar') ?>">
            <?= nexo_icon('calendar') ?>
            Calendario
        </a>
    </nav>

    <div class="nx-workspace-layout">
        <div>
            <div class="nx-section__head">
                <div>
                    <h2 class="nx-section__title">Páginas del espacio</h2>
                    <p class="nx-section__desc">Documentos, apuntes y material compartido por el equipo.</p>
                </div>
                <span class="nx-chip"><?= count($flatPages) ?> en total</span>
            </div>

            <?php if ($flatPages !== []): ?>
                <div class="nx-page-list">
                    <?php foreach ($flatPages as $page): ?>
                        <?php
                        $depth = (int) ($page['depth'] ?? 0);
                        $padding = $depth > 0 ? 'padding-left: calc(var(--space-2) + ' . ($depth * 18) . 'px);' : '';
                        ?>
                        <a
                            class="nx-page-list__row"
                            href="<?= nexo_url($prefix, 'pages/' . $page['id']) ?>"
                            <?= $padding !== '' ? 'style="' . esc($padding, 'attr') . '"' : '' ?>
                        >
                            <span class="nx-icon-tile nx-icon-tile--sm" aria-hidden="true">
                                <?= nexo_icon(nexo_page_icon($page['icon'] ?? 'file-text')) ?>
                            </span>
                            <span class="nx-page-list__main">
                                <span class="nx-page-list__title"><?= esc($page['title']) ?></span>
                                <span class="nx-page-list__meta">
                                    <span><?= nexo_icon('clock') ?><?= esc(nexo_datetime($page['updated_at'] ?? null)) ?></span>
                                    <span><?= nexo_icon('user') ?><?= esc($page['author'] ?? 'Sin autor') ?></span>
                                    <?php foreach (array_slice($page['tags'] ?? [], 0, 2) as $tag): ?>
                                        <span><?= nexo_icon('tag') ?><?= esc($tag) ?></span>
                                    <?php endforeach ?>
                                    <?php if (! empty($page['has_children'])): ?>
                                        <span><?= nexo_icon('layers') ?>Con subpáginas</span>
                                    <?php endif ?>
                                </span>
                            </span>
                            <span class="nx-page-list__arrow" aria-hidden="true"><?= nexo_icon('arrow-right') ?></span>
                        </a>
                    <?php endforeach ?>
                </div>

                <a class="nx-editor__add" href="<?= esc($newPageUrl, 'attr') ?>" style="margin-top: 12px;">
                    <?= nexo_icon('plus') ?>
                    Añadir otra página al espacio
                </a>
            <?php else: ?>
                <div class="nx-radar-empty">
                    <div class="nx-empty">
                        <?= nexo_illustration('book') ?>
                        <p class="nx-empty__title">Este espacio aún no tiene páginas</p>
                        <p class="nx-empty__text">Empieza con una página para los apuntes de la semana o el acta de la primera reunión.</p>
                        <a class="nx-btn nx-btn--primary" href="<?= esc($newPageUrl, 'attr') ?>">
                            <?= nexo_icon('plus') ?> Crear la primera página
                        </a>
                    </div>
                </div>
            <?php endif ?>
        </div>

        <aside aria-label="Información del espacio">
            <section class="nx-aside-section">
                <div class="nx-aside-title">
                    <span>Miembros</span>
                    <button class="nx-link-arrow" type="button" data-coming-soon="Las invitaciones se activarán cuando el backend de miembros esté disponible.">Invitar</button>
                </div>
                <div class="nx-member-list">
                    <?php foreach ($workspace['members'] as $member): ?>
                        <div class="nx-member-row">
                            <span class="nx-avatar nx-avatar--sm nx-avatar--<?= esc($member['tone'], 'attr') ?>" aria-hidden="true"><?= esc($member['initials']) ?></span>
                            <span class="nx-member-row__main">
                                <span class="nx-member-row__name"><?= esc($member['name']) ?></span>
                                <span class="nx-member-row__role"><?= esc(nexo_role_label($member['role'])) ?></span>
                            </span>
                            <?php if ($member['id'] === ($user['id'] ?? 0)): ?>
                                <span class="nx-badge nx-badge--outline">Tú</span>
                            <?php endif ?>
                        </div>
                    <?php endforeach ?>
                </div>
            </section>

            <section class="nx-aside-section">
                <div class="nx-aside-title">
                    <span>Tareas</span>
                    <a class="nx-link-arrow" href="<?= nexo_url($prefix, 'workspaces/' . $workspaceId . '/kanban') ?>">Abrir tablero <?= nexo_icon('arrow-right') ?></a>
                </div>

                <div class="nx-card" style="padding: 14px;">
                    <div class="nx-row nx-row--between nx-row--wrap" style="gap: 6px;">
                        <span class="nx-status"><span class="nx-dot nx-dot--gold"></span><?= (int) $task_counts['todo'] ?> por hacer</span>
                        <span class="nx-status"><span class="nx-dot nx-dot--wine"></span><?= (int) $task_counts['doing'] ?> en curso</span>
                        <span class="nx-status"><span class="nx-dot nx-dot--success"></span><?= (int) $task_counts['done'] ?> hechas</span>
                    </div>

                    <?php if ($tasks !== []): ?>
                        <div class="nx-task-list" style="margin-top: 10px;">
                            <?php foreach (array_slice($tasks, 0, 3) as $task): ?>
                                <div class="nx-task-row">
                                    <button class="nx-task-row__check" type="button" data-task-toggle="<?= (int) $task['id'] ?>" data-task-status="<?= esc($task['status'], 'attr') ?>" aria-label="Marcar «<?= esc($task['title'], 'attr') ?>» como hecha" aria-pressed="false"><?= nexo_icon('check') ?></button>
                                    <span class="nx-task-row__main">
                                        <span class="nx-task-row__title"><?= esc($task['title']) ?></span>
                                        <span class="nx-task-row__meta">
                                            <span class="<?= ! empty($task['is_overdue']) ? 'is-overdue' : '' ?>"><?= nexo_icon('clock') ?><?= esc($task['due_label']) ?></span>
                                            <span class="nx-priority nx-priority--<?= esc($task['priority'], 'attr') ?>"><?= esc(nexo_priority_label($task['priority'])) ?></span>
                                        </span>
                                    </span>
                                </div>
                            <?php endforeach ?>
                        </div>
                    <?php else: ?>
                        <p class="nx-hint" style="margin-top: 10px;">No hay tareas abiertas en este espacio.</p>
                    <?php endif ?>

                    <button class="nx-btn nx-btn--ghost nx-btn--sm nx-btn--block" type="button" data-task-new style="margin-top: 10px; justify-content: flex-start;">
                        <?= nexo_icon('plus') ?> Añadir tarea
                    </button>
                </div>
            </section>

            <section class="nx-aside-section">
                <div class="nx-aside-title">Sobre el espacio</div>
                <p class="nx-hint" style="line-height: 1.6;">
                    <?= esc($workspace['type_full']) ?>. Pertenece a la Facultad de Ingeniería Industrial y de Sistemas.
                    Los permisos de edición los administra quien creó el espacio.
                </p>
                <div class="nx-page__meta" style="margin-top: 12px;">
                    <span><?= nexo_icon('clock') ?>Creado <?= esc(nexo_date($workspace['updated_at'], 'long')) ?></span>
                </div>
            </section>
        </aside>
    </div>
</div>

<?= $this->include('partials/task-modal') ?>
