<?php
/**
 * Sidebar de navegación.
 *
 * @var array       $workspaces
 * @var array|null  $sidebarWorkspace
 * @var array       $sidebarPages
 * @var array|null  $currentPage
 * @var array       $taskCounts
 * @var array       $user
 * @var string      $prefix
 * @var bool        $isPreview
 * @var string      $activeNav
 * @var string|null $eventsUrl
 */
$activeNav = $activeNav ?? '';
$currentWorkspaceUrl = $sidebarWorkspace !== null
    ? nexo_url($prefix, 'workspaces/' . $sidebarWorkspace['id'])
    : nexo_url($prefix, 'workspaces');
?>
<aside class="nx-sidebar" id="nx-sidebar" aria-label="Navegación principal">
    <div class="nx-sidebar__head">
        <a class="nx-brand" href="<?= nexo_url($prefix, 'dashboard') ?>" aria-label="NEXO UNAC, ir a mi mesa">
            <span class="nx-brand__mark" aria-hidden="true">N</span>
            <span class="nx-brand__name">NEXO</span>
            <span class="nx-brand__sub">UNAC</span>
        </a>
        <button class="nx-icon-btn nx-sidebar__close" type="button" data-sidebar-close aria-label="Cerrar menú">
            <?= nexo_icon('x') ?>
        </button>
    </div>

    <div class="nx-sidebar__scroll nx-scroll">
        <?php if ($sidebarWorkspace !== null): ?>
            <div class="nx-workspace-switch nx-dropdown">
                <button
                    class="nx-workspace-switch__btn"
                    type="button"
                    data-menu-trigger="nx-workspace-menu"
                    aria-expanded="false"
                    aria-controls="nx-workspace-menu"
                >
                    <span class="nx-workspace-switch__icon">
                        <?= nexo_icon($sidebarWorkspace['icon']) ?>
                    </span>
                    <span class="nx-workspace-switch__text">
                        <span class="nx-workspace-switch__name"><?= esc($sidebarWorkspace['name']) ?></span>
                        <span class="nx-workspace-switch__type"><?= esc($sidebarWorkspace['type_label']) ?></span>
                    </span>
                    <?= nexo_icon('chevron-down') ?>
                </button>

                <div class="nx-menu nx-menu--left" id="nx-workspace-menu" hidden>
                    <div class="nx-menu__label">Cambiar de espacio</div>
                    <?php foreach ($workspaces as $workspace): ?>
                        <a
                            class="nx-menu__item"
                            href="<?= nexo_url($prefix, 'workspaces/' . $workspace['id']) ?>"
                            <?= (int) $workspace['id'] === (int) $sidebarWorkspace['id'] ? 'aria-current="page"' : '' ?>
                        >
                            <?= nexo_icon($workspace['icon']) ?>
                            <span><?= esc($workspace['name']) ?></span>
                            <?php if ((int) $workspace['id'] === (int) $sidebarWorkspace['id']): ?>
                                <?= nexo_icon('check', 'nx-icon nx-icon--sm') ?>
                            <?php endif ?>
                        </a>
                    <?php endforeach ?>
                    <div class="nx-menu__separator"></div>
                    <a class="nx-menu__item" href="<?= nexo_url($prefix, 'workspaces') ?>">
                        <?= nexo_icon('layers') ?>
                        <span>Ver todos los espacios</span>
                    </a>
                </div>
            </div>
        <?php endif ?>

        <nav class="nx-nav" aria-label="Secciones">
            <a class="nx-nav__item<?= $activeNav === 'dashboard' ? ' is-active' : '' ?>" href="<?= nexo_url($prefix, 'dashboard') ?>" <?= $activeNav === 'dashboard' ? 'aria-current="page"' : '' ?>>
                <?= nexo_icon('layout-dashboard') ?>
                <span>Mi mesa</span>
            </a>
            <a class="nx-nav__item<?= $activeNav === 'workspaces' ? ' is-active' : '' ?>" href="<?= nexo_url($prefix, 'workspaces') ?>" <?= $activeNav === 'workspaces' ? 'aria-current="page"' : '' ?>>
                <?= nexo_icon('folder') ?>
                <span>Espacios</span>
            </a>
            <?php if ($eventsUrl !== null): ?>
                <a class="nx-nav__item<?= $activeNav === 'events' ? ' is-active' : '' ?>" href="<?= esc($eventsUrl, 'attr') ?>" <?= $activeNav === 'events' ? 'aria-current="page"' : '' ?>>
                    <?= nexo_icon('compass') ?>
                    <span>Radar</span>
                    <?php if ($isPreview): ?>
                        <span class="nx-badge nx-badge--outline">demo</span>
                    <?php endif ?>
                </a>
            <?php endif ?>
        </nav>

        <div class="nx-sidebar__section">
            <div class="nx-sidebar__label">
                <span>Páginas</span>
                <?php if ($sidebarWorkspace !== null): ?>
                    <a href="<?= $currentWorkspaceUrl ?>">Ver todas</a>
                <?php endif ?>
            </div>

            <?php if ($sidebarPages !== []): ?>
                <div class="nx-tree">
                    <?= nexo_page_tree($sidebarPages, isset($currentPage['id']) ? (int) $currentPage['id'] : null, $prefix) ?>
                </div>
            <?php else: ?>
                <p class="nx-tree__more">Aún no hay páginas en este espacio.</p>
            <?php endif ?>

            <?php if ($sidebarWorkspace !== null && ! empty($sidebarWorkspace['can_edit'])): ?>
                <a class="nx-nav__new" href="<?= esc(nexo_url($prefix, 'pages/nueva') . '?workspace=' . (int) $sidebarWorkspace['id'], 'attr') ?>">
                    <?= nexo_icon('plus') ?>
                    <span>Nueva página</span>
                </a>
            <?php endif ?>
        </div>

        <?php if ($sidebarWorkspace !== null): ?>
            <div class="nx-sidebar__section">
                <div class="nx-sidebar__label">
                    <span>Accesos rápidos</span>
                </div>
                <a class="nx-nav__item" href="<?= nexo_url($prefix, 'workspaces/' . $sidebarWorkspace['id'] . '/kanban') ?>">
                    <?= nexo_icon('kanban') ?>
                    <span>Tablero</span>
                    <?php if (! empty($taskCounts[$sidebarWorkspace['id']])): ?>
                        <span class="nx-badge"><?= (int) $taskCounts[$sidebarWorkspace['id']] ?></span>
                    <?php endif ?>
                </a>
                <a class="nx-nav__item" href="<?= nexo_url($prefix, 'workspaces/' . $sidebarWorkspace['id'] . '/calendar') ?>">
                    <?= nexo_icon('calendar') ?>
                    <span>Calendario</span>
                </a>
            </div>
        <?php endif ?>
    </div>

    <div class="nx-sidebar__foot">
        <div class="nx-user-card">
            <span class="nx-avatar nx-avatar--<?= esc($user['tone'], 'attr') ?>" aria-hidden="true"><?= esc($user['initials']) ?></span>
            <span class="nx-user-card__text">
                <span class="nx-user-card__name"><?= esc($user['name']) ?></span>
                <span class="nx-user-card__role">
                    <?= esc($user['role_label']) ?>
                    <?php if ($isPreview): ?> · demo<?php endif ?>
                </span>
            </span>
        </div>
        <div class="nx-sidebar__foot-actions">
            <a class="nx-icon-btn" href="<?= nexo_url($prefix, 'settings') ?>" aria-label="Ajustes" data-tip="Ajustes">
                <?= nexo_icon('settings') ?>
            </a>
            <?php if ($isPreview): ?>
                <a class="nx-icon-btn" href="<?= base_url('preview/login') ?>" aria-label="Salir de la vista demo" data-tip="Salir de la demo">
                    <?= nexo_icon('log-out') ?>
                </a>
            <?php else: ?>
                <form action="<?= url_to('logout') ?>" method="post" class="d-inline">
                    <?= csrf_field() ?>
                    <button class="nx-icon-btn" type="submit" aria-label="Cerrar sesión" data-tip="Cerrar sesión">
                        <?= nexo_icon('log-out') ?>
                    </button>
                </form>
            <?php endif ?>
        </div>
    </div>
</aside>
