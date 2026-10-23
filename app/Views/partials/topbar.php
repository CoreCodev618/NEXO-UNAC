<?php
/**
 * Barra superior del shell.
 *
 * @var array|null $currentPage
 * @var array|null $currentWorkspace
 * @var array|null $sidebarWorkspace
 * @var array      $user
 * @var string     $prefix
 * @var bool       $isPreview
 * @var string     $topbarTitle
 * @var string     $activeNav
 */
$page       = $currentPage ?? null;
$workspace  = $currentWorkspace ?? $sidebarWorkspace ?? null;
$isDoc      = $page !== null;
$inWorkspace = ($activeNav ?? '') === 'workspaces';
$backUrl    = $page !== null
    ? nexo_url($prefix, 'workspaces/' . $page['workspace_id'])
    : ($workspace !== null ? nexo_url($prefix, 'workspaces/' . $workspace['id']) : nexo_url($prefix, 'workspaces'));
?>
<header class="nx-topbar" role="banner">
    <div class="nx-topbar__left">
        <button class="nx-icon-btn nx-mobile-menu" type="button" data-sidebar-open aria-label="Abrir menú" aria-controls="nx-sidebar" aria-expanded="false">
            <?= nexo_icon('menu') ?>
        </button>

        <nav class="nx-breadcrumb" aria-label="Migas de pan">
            <?php if ($page !== null): ?>
                <a href="<?= esc($backUrl, 'attr') ?>"><?= esc($page['workspace']['name'] ?? 'Espacio') ?></a>
                <?= nexo_icon('chevron-right') ?>
                <span class="nx-breadcrumb__current" aria-current="page"><?= esc($page['title']) ?></span>
            <?php elseif ($workspace !== null && $inWorkspace): ?>
                <a href="<?= nexo_url($prefix, 'workspaces') ?>">Espacios</a>
                <?= nexo_icon('chevron-right') ?>
                <span class="nx-breadcrumb__current" aria-current="page"><?= esc($workspace['name']) ?></span>
            <?php else: ?>
                <span class="nx-breadcrumb__current" aria-current="page"><?= esc($topbarTitle) ?></span>
            <?php endif ?>
        </nav>

        <?php if ($isDoc): ?>
            <span class="nx-topbar__divider" aria-hidden="true"></span>
            <span class="nx-save-state" id="nx-save-state" data-state="saved" aria-live="polite">
                <?= nexo_icon('check-circle') ?>
                <span class="nx-save-state__text">Guardado</span>
            </span>
        <?php endif ?>
    </div>

    <div class="nx-topbar__right">
        <button class="nx-topbar__search" type="button" data-palette-open aria-label="Buscar o ejecutar un comando">
            <?= nexo_icon('search') ?>
            <span>Buscar o ejecutar…</span>
            <span class="nx-kbd">⌘ K</span>
        </button>

        <button class="nx-btn nx-btn--tutor nx-btn--sm" type="button" data-chat-toggle aria-controls="nx-chat" aria-expanded="false">
            <?= nexo_icon('sparkles') ?>
            <span class="nx-topbar__tutor-label">Tutor</span>
        </button>

        <?php if ($isDoc): ?>
            <button class="nx-icon-btn" type="button" data-context-toggle aria-controls="nx-context" aria-expanded="false" aria-label="Abrir contexto de la página" data-tip="Contexto">
                <?= nexo_icon('panel-right') ?>
            </button>
        <?php endif ?>

        <span class="nx-topbar__divider" aria-hidden="true"></span>

        <div class="nx-dropdown">
            <button class="nx-icon-btn" type="button" data-menu-trigger="nx-user-menu" aria-expanded="false" aria-controls="nx-user-menu" aria-label="Menú de <?= esc($user['name'], 'attr') ?>">
                <span class="nx-avatar nx-avatar--sm nx-avatar--<?= esc($user['tone'], 'attr') ?>" aria-hidden="true"><?= esc($user['initials']) ?></span>
            </button>

            <div class="nx-menu" id="nx-user-menu" hidden>
                <div class="nx-menu__label"><?= esc($user['name']) ?></div>
                <div class="px-3 pb-2">
                    <div class="nx-muted" style="font-size: 10px; line-height: 1.4;"><?= esc($user['email']) ?></div>
                    <?php if ($isPreview): ?>
                        <span class="nx-badge nx-badge--gold mt-1">Vista demo</span>
                    <?php endif ?>
                </div>
                <div class="nx-menu__separator"></div>
                <a class="nx-menu__item" href="<?= nexo_url($prefix, 'settings') ?>">
                    <?= nexo_icon('user') ?>
                    <span>Mi perfil</span>
                </a>
                <a class="nx-menu__item" href="<?= esc(nexo_url($prefix, 'settings') . '?tab=workspace', 'attr') ?>">
                    <?= nexo_icon('settings') ?>
                    <span>Preferencias</span>
                </a>
                <?php if ($isPreview): ?>
                    <a class="nx-menu__item" href="<?= base_url('preview/ui-kit') ?>">
                        <?= nexo_icon('layers') ?>
                        <span>UI kit</span>
                        <span class="nx-menu__shortcut">dev</span>
                    </a>
                <?php endif ?>
                <div class="nx-menu__separator"></div>
                <?php if ($isPreview): ?>
                    <a class="nx-menu__item nx-menu__item--danger" href="<?= base_url('preview/login') ?>">
                        <?= nexo_icon('log-out') ?>
                        <span>Salir de la demo</span>
                    </a>
                <?php else: ?>
                    <form action="<?= url_to('logout') ?>" method="post">
                        <?= csrf_field() ?>
                        <button class="nx-menu__item nx-menu__item--danger" type="submit">
                            <?= nexo_icon('log-out') ?>
                            <span>Cerrar sesión</span>
                        </button>
                    </form>
                <?php endif ?>
            </div>
        </div>
    </div>
</header>
