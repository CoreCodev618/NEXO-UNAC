<?php
/**
 * Índice de espacios.
 *
 * @var array   $workspaces
 * @var array   $taskCounts
 * @var string  $prefix
 * @var bool    $isPreview
 */
$totalPages = array_sum(array_map(static fn (array $workspace): int => (int) $workspace['pages_count'], $workspaces));
$totalTasks = array_sum(array_map(static fn (array $workspace): int => (int) ($taskCounts[$workspace['id']] ?? 0), $workspaces));
?>
<div class="nx-page">
    <header class="nx-page__head">
        <div class="nx-page__head-main">
            <p class="nx-eyebrow">Espacios</p>
            <h1 class="nx-page__title">El salón, por dentro.</h1>
            <p class="nx-page__sub">
                Cada espacio reúne las páginas, los archivos y las tareas de una escuela, un club o un proyecto.
                Entra al que necesitas y trabaja con tu equipo.
            </p>
            <div class="nx-page__meta">
                <span><?= nexo_icon('layers') ?><?= count($workspaces) ?> espacios</span>
                <span><?= nexo_icon('file-text') ?><?= (int) $totalPages ?> páginas</span>
                <span><?= nexo_icon('check-square') ?><?= (int) $totalTasks ?> tareas abiertas</span>
            </div>
        </div>
        <div class="nx-page__actions">
            <button class="nx-btn nx-btn--secondary" type="button" data-coming-soon="Unirse con un código llegará con la invitación de espacios del backend.">
                <?= nexo_icon('users') ?>
                Unirme con código
            </button>
            <button class="nx-btn nx-btn--primary" type="button" data-coming-soon="La creación de espacios se habilitará cuando el endpoint de workspaces esté disponible.">
                <?= nexo_icon('plus') ?>
                Nuevo espacio
            </button>
        </div>
    </header>

    <?php if ($workspaces === []): ?>
        <div class="nx-radar-empty">
            <div class="nx-empty">
                <?= nexo_illustration('folder') ?>
                <p class="nx-empty__title">Todavía no perteneces a un espacio</p>
                <p class="nx-empty__text">Cuando te inviten a un salón, club o proyecto, aparecerá aquí con sus páginas y tareas.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="nx-space-list" aria-label="Tus espacios">
            <?php foreach ($workspaces as $workspace): ?>
                <a class="nx-space-row" href="<?= nexo_url($prefix, 'workspaces/' . $workspace['id']) ?>">
                    <span class="nx-icon-tile nx-icon-tile--lg nx-icon-tile--<?= esc($workspace['tone'], 'attr') ?>" aria-hidden="true">
                        <?= nexo_icon($workspace['icon']) ?>
                    </span>

                    <span class="nx-space-row__main">
                        <span class="nx-space-row__name"><?= esc($workspace['name']) ?></span>
                        <span class="nx-space-row__desc"><?= esc($workspace['summary'] ?? $workspace['type_full']) ?></span>
                        <span class="nx-space-row__meta">
                            <span><?= nexo_icon('file-text') ?><?= (int) $workspace['pages_count'] ?> páginas</span>
                            <span><?= nexo_icon('check-square') ?><?= (int) ($taskCounts[$workspace['id']] ?? 0) ?> tareas abiertas</span>
                            <span><?= nexo_icon($workspace['icon']) ?><?= esc($workspace['type_full']) ?></span>
                        </span>
                    </span>

                    <span class="nx-space-row__side">
                        <span class="nx-avatar-stack" aria-label="Miembros del espacio">
                            <?php foreach (array_slice($workspace['members'], 0, 4) as $member): ?>
                                <span class="nx-avatar nx-avatar--sm nx-avatar--<?= esc($member['tone'], 'attr') ?>" title="<?= esc($member['name'], 'attr') ?>"><?= esc($member['initials']) ?></span>
                            <?php endforeach ?>
                            <?php if (count($workspace['members']) > 4): ?>
                                <span class="nx-avatar-stack__more">+<?= count($workspace['members']) - 4 ?></span>
                            <?php endif ?>
                        </span>
                        <span class="nx-space-row__arrow" aria-hidden="true"><?= nexo_icon('arrow-right') ?></span>
                    </span>
                </a>
            <?php endforeach ?>
        </div>

        <p class="nx-hint" style="margin-top: 20px;">
            Pertenecer a un espacio te da acceso a sus páginas. Los permisos de edición los define quien lo creó.
        </p>
    <?php endif ?>
</div>
