<?php
/**
 * Dashboard: la mesa de trabajo.
 *
 * @var array  $dashboard
 * @var array  $user
 * @var array  $taskCounts
 * @var string $prefix
 * @var bool   $isPreview
 */
$featured = $dashboard['featured_page'] ?? null;
$recent   = $dashboard['recent_pages'] ?? [];
$others   = array_values(array_filter($recent, static fn (array $page): bool => $featured === null || (int) $page['id'] !== (int) $featured['id']));
$tasks    = $dashboard['tasks'] ?? [];
$workspaces = $dashboard['workspaces'] ?? [];
$newPageUrl = nexo_url($prefix, 'pages/nueva') . '?workspace=' . (int) (($sidebarWorkspace['id'] ?? 1));

$now       = new DateTimeImmutable('now', new DateTimeZone('America/Lima'));
$dayNames  = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
$dateLabel = ucfirst($dayNames[(int) $now->format('w')]) . ', ' . (int) $now->format('j') . ' de ' . nexo_month_name((int) $now->format('n')) . ' de ' . $now->format('Y');
$firstName = explode(' ', trim((string) $user['name']))[0] ?? 'estudiante';
?>
<div class="nx-page">
    <header class="nx-dash-head">
        <div>
            <p class="nx-eyebrow">Mi mesa</p>
            <h1 class="nx-dash-greeting">Hola, <?= esc($firstName) ?>.</h1>
            <p class="nx-dash-sub">
                Tienes <?= (int) $dashboard['counts']['open_tasks'] ?> tareas abiertas en <?= (int) $dashboard['counts']['workspaces'] ?> espacios.
                Empieza por donde lo dejaste o revisa lo que viene.
            </p>
        </div>
        <div class="nx-dash-date" aria-label="Fecha de hoy"><?= esc($dateLabel) ?></div>
    </header>

    <div class="nx-grid nx-grid--dashboard">
        <div class="nx-dash-main">
            <div class="nx-section__head" style="margin-bottom: 16px;">
                <div>
                    <h2 class="nx-section__title">Continuar donde lo dejaste</h2>
                    <p class="nx-section__desc">Las páginas que se movieron últimamente en tus espacios.</p>
                </div>
                <a class="nx-section__link" href="<?= nexo_url($prefix, 'workspaces') ?>">
                    Todos los espacios <?= nexo_icon('arrow-right') ?>
                </a>
            </div>

            <?php if ($featured !== null): ?>
                <a class="nx-continue-card" href="<?= nexo_url($prefix, 'pages/' . $featured['id']) ?>">
                    <span class="nx-continue-card__cover nx-cover <?= esc(nexo_cover_class($featured['cover'] ?? 'rose'), 'attr') ?>" aria-hidden="true"></span>
                    <span class="nx-continue-card__body">
                        <span class="nx-continue-card__eyebrow">
                            <?= nexo_icon('clock') ?>
                            Última edición · <?= esc(nexo_datetime($featured['updated_at'] ?? null)) ?>
                        </span>
                        <span class="nx-continue-card__title"><?= esc($featured['title']) ?></span>
                        <span class="nx-continue-card__meta">
                            <span><?= nexo_icon($featured['workspace_icon'] ?? 'folder') ?><?= esc($featured['workspace_name'] ?? 'Espacio') ?></span>
                            <?php if (! empty($featured['tags'])): ?>
                                <span><?= nexo_icon('tag') ?><?= esc(implode(' · ', $featured['tags'])) ?></span>
                            <?php endif ?>
                        </span>
                    </span>
                    <span class="nx-continue-card__arrow" aria-hidden="true"><?= nexo_icon('arrow-right') ?></span>
                </a>
            <?php else: ?>
                <div class="nx-radar-empty">
                    <div class="nx-empty">
                        <?= nexo_illustration('book') ?>
                        <p class="nx-empty__title">Tu mesa está en blanco</p>
                        <p class="nx-empty__text">Crea tu primera página para empezar a ordenar apuntes y tareas.</p>
                        <a class="nx-btn nx-btn--primary" href="<?= esc($newPageUrl, 'attr') ?>">
                            <?= nexo_icon('plus') ?> Nueva página
                        </a>
                    </div>
                </div>
            <?php endif ?>

            <?php if ($others !== []): ?>
                <div class="nx-page-rows">
                    <?php foreach ($others as $page): ?>
                        <a class="nx-page-row" href="<?= nexo_url($prefix, 'pages/' . $page['id']) ?>">
                            <span class="nx-icon-tile nx-icon-tile--sm nx-icon-tile--<?= esc($page['workspace_tone'] ?? 'paper', 'attr') ?>" aria-hidden="true">
                                <?= nexo_icon(nexo_page_icon($page['icon'] ?? 'file-text')) ?>
                            </span>
                            <span class="nx-page-row__main">
                                <span class="nx-page-row__title"><?= esc($page['title']) ?></span>
                                <span class="nx-page-row__meta">
                                    <span><?= nexo_icon($page['workspace_icon'] ?? 'folder') ?><?= esc($page['workspace_name'] ?? 'Espacio') ?></span>
                                    <span><?= nexo_icon('clock') ?><?= esc(nexo_datetime($page['updated_at'] ?? null)) ?></span>
                                    <?php if (! empty($page['tags'])): ?>
                                        <span><?= nexo_icon('tag') ?><?= esc(implode(' · ', $page['tags'])) ?></span>
                                    <?php endif ?>
                                </span>
                            </span>
                            <span class="nx-page-row__arrow" aria-hidden="true"><?= nexo_icon('arrow-right') ?></span>
                        </a>
                    <?php endforeach ?>
                </div>
            <?php endif ?>

            <section class="nx-section">
                <div class="nx-section__head">
                    <div>
                        <h2 class="nx-section__title">Tus espacios</h2>
                        <p class="nx-section__desc">Cada escuela, club o proyecto tiene su propio ritmo.</p>
                    </div>
                    <a class="nx-section__link" href="<?= nexo_url($prefix, 'workspaces') ?>">
                        Ver todos <?= nexo_icon('arrow-right') ?>
                    </a>
                </div>

                <div class="nx-workspace-list">
                    <?php foreach ($workspaces as $workspace): ?>
                        <a class="nx-workspace-row" href="<?= nexo_url($prefix, 'workspaces/' . $workspace['id']) ?>">
                            <span class="nx-icon-tile nx-icon-tile--<?= esc($workspace['tone'], 'attr') ?>" aria-hidden="true">
                                <?= nexo_icon($workspace['icon']) ?>
                            </span>
                            <span class="nx-workspace-row__main">
                                <span class="nx-workspace-row__name"><?= esc($workspace['name']) ?></span>
                                <span class="nx-workspace-row__meta">
                                    <span><?= nexo_icon('file-text') ?><?= (int) $workspace['pages_count'] ?> páginas</span>
                                    <span><?= nexo_icon('check-square') ?><?= (int) ($taskCounts[$workspace['id']] ?? 0) ?> tareas abiertas</span>
                                    <span><?= nexo_icon($workspace['icon']) ?><?= esc($workspace['type_full']) ?></span>
                                </span>
                            </span>
                            <span class="nx-workspace-row__side">
                                <span class="nx-avatar-stack" aria-label="Miembros del espacio">
                                    <?php foreach (array_slice($workspace['members'], 0, 3) as $member): ?>
                                        <span class="nx-avatar nx-avatar--sm nx-avatar--<?= esc($member['tone'], 'attr') ?>" title="<?= esc($member['name'], 'attr') ?>"><?= esc($member['initials']) ?></span>
                                    <?php endforeach ?>
                                    <?php if (count($workspace['members']) > 3): ?>
                                        <span class="nx-avatar-stack__more">+<?= count($workspace['members']) - 3 ?></span>
                                    <?php endif ?>
                                </span>
                                <span class="nx-workspace-row__arrow" aria-hidden="true"><?= nexo_icon('arrow-right') ?></span>
                            </span>
                        </a>
                    <?php endforeach ?>
                </div>
            </section>
        </div>

        <aside class="nx-dash-aside" aria-label="Resumen del día">
            <section class="nx-card nx-task-card">
                <div class="nx-task-card__head">
                    <h2 class="nx-task-card__title">Lo que viene</h2>
                    <a class="nx-link-arrow" href="<?= nexo_url($prefix, 'workspaces/' . ($workspaces[0]['id'] ?? 1) . '/kanban') ?>">
                        Tablero <?= nexo_icon('arrow-right') ?>
                    </a>
                </div>

                <?php if ($tasks !== []): ?>
                    <div class="nx-task-list">
                        <?php foreach ($tasks as $task): ?>
                            <div class="nx-task-row" data-task-row="<?= (int) $task['id'] ?>">
                                <button
                                    class="nx-task-row__check"
                                    type="button"
                                    data-task-toggle="<?= (int) $task['id'] ?>"
                                    data-task-status="<?= esc($task['status'], 'attr') ?>"
                                    aria-label="Marcar «<?= esc($task['title'], 'attr') ?>» como hecha"
                                    aria-pressed="false"
                                ><?= nexo_icon('check') ?></button>
                                <div class="nx-task-row__main">
                                    <a class="nx-task-row__title" href="<?= $task['page_id'] !== null ? nexo_url($prefix, 'pages/' . $task['page_id']) : nexo_url($prefix, 'workspaces/' . $task['workspace_id'] . '/kanban') ?>">
                                        <?= esc($task['title']) ?>
                                    </a>
                                    <span class="nx-task-row__meta">
                                        <span><?= nexo_icon('folder') ?><?= esc($task['workspace_name'] ?? 'Espacio') ?></span>
                                        <span class="<?= ! empty($task['is_overdue']) ? 'is-overdue' : '' ?>"><?= nexo_icon('clock') ?><?= esc($task['due_label']) ?></span>
                                        <span class="nx-priority nx-priority--<?= esc($task['priority'], 'attr') ?>"><?= esc(nexo_priority_label($task['priority'])) ?></span>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach ?>
                    </div>
                <?php else: ?>
                    <div class="nx-empty nx-empty--compact">
                        <?= nexo_illustration('board') ?>
                        <p class="nx-empty__title">Nada pendiente</p>
                        <p class="nx-empty__text">Cuando agregues tareas, aparecerán aquí por fecha.</p>
                    </div>
                <?php endif ?>
            </section>

            <section class="nx-tutor-card">
                <span class="nx-tutor-card__icon" aria-hidden="true"><?= nexo_icon('sparkles') ?></span>
                <h2 class="nx-tutor-card__title">Pregunta a tu material</h2>
                <p class="nx-tutor-card__text">
                    El tutor busca en tus páginas y documentos del espacio antes de responder. Si no tiene contexto, te lo dice.
                </p>
                <div class="nx-tutor-card__actions">
                    <button class="nx-btn nx-btn--tutor nx-btn--sm" type="button" data-chat-toggle aria-controls="nx-chat" aria-expanded="false">
                        <?= nexo_icon('sparkles') ?>
                        Abrir tutor
                    </button>
                    <?php if ($featured !== null): ?>
                        <a class="nx-btn nx-btn--ghost nx-btn--sm" href="<?= nexo_url($prefix, 'pages/' . $featured['id']) ?>" style="color: var(--paper);">
                            Ver contexto
                        </a>
                    <?php endif ?>
                </div>
            </section>

            <section class="nx-radar-empty">
                <div class="nx-empty">
                    <?= nexo_illustration('compass') ?>
                    <p class="nx-empty__title">Radar, en preparación</p>
                    <p class="nx-empty__text">
                        Los eventos y convocatorias de la FIIS aparecerán aquí. Todavía no hay datos del backend, así que no mostramos eventos inventados.
                    </p>
                    <?php if ($eventsUrl !== null): ?>
                        <a class="nx-link-arrow" href="<?= esc($eventsUrl, 'attr') ?>">Ver el estado <?= nexo_icon('arrow-right') ?></a>
                    <?php endif ?>
                </div>
            </section>
        </aside>
    </div>
</div>
