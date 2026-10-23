<?php
/**
 * Shell principal de NEXO.
 *
 * @var string      $pageTitle
 * @var string      $prefix
 * @var bool        $isPreview
 * @var string      $currentPath
 * @var array|null  $currentPage
 * @var array|null  $currentWorkspace
 * @var array|null  $sidebarWorkspace
 * @var array       $user
 */
$isDoc      = ! empty($currentPage);
$isBoard    = str_contains($currentPath, '/kanban');
$isCalendar = str_contains($currentPath, '/calendar');
$needsSort  = $isDoc || $isBoard;
$pageId     = $isDoc ? (int) $currentPage['id'] : null;
$workspaceId = $currentWorkspace['id'] ?? ($sidebarWorkspace['id'] ?? null);
$security   = config('Security');
?>
<!doctype html>
<html lang="es-PE">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="oklch(38% 0.12 15)">
    <meta name="description" content="NEXO UNAC: apuntes, tareas y material del salón en un solo lugar.">
    <meta name="csrf-token" content="<?= esc(csrf_hash(), 'attr') ?>">
    <meta name="csrf-header" content="<?= esc(csrf_header(), 'attr') ?>">
    <meta name="csrf-cookie" content="<?= esc($security->cookieName, 'attr') ?>">

    <title><?= esc($pageTitle ?? 'NEXO') ?> · NEXO UNAC</title>

    <link rel="icon" href="<?= base_url('favicon.svg') ?>" type="image/svg+xml">
    <link rel="preload" href="<?= base_url('fonts/newsreader-latin.woff2') ?>" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="<?= base_url('fonts/public-sans-latin.woff2') ?>" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="<?= base_url('fonts/jetbrains-mono-latin.woff2') ?>" as="font" type="font/woff2" crossorigin>

    <link rel="stylesheet" href="<?= base_url('vendor/bootstrap/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= base_url('css/tokens.css') ?>">
    <link rel="stylesheet" href="<?= base_url('css/base.css') ?>">
    <link rel="stylesheet" href="<?= base_url('css/components.css') ?>">
    <link rel="stylesheet" href="<?= base_url('css/layout.css') ?>">
    <link rel="stylesheet" href="<?= base_url('css/pages.css') ?>">
    <link rel="stylesheet" href="<?= base_url('css/palette.css') ?>">
    <link rel="stylesheet" href="<?= base_url('css/chat.css') ?>">
    <?php if ($isDoc): ?>
        <link rel="stylesheet" href="<?= base_url('css/editor.css') ?>">
    <?php endif ?>
    <?php if ($isBoard || $isCalendar): ?>
        <link rel="stylesheet" href="<?= base_url('css/kanban.css') ?>">
    <?php endif ?>
    <?php if ($isCalendar): ?>
        <link rel="stylesheet" href="<?= base_url('css/calendar.css') ?>">
    <?php endif ?>
</head>
<body
    class="nx-app"
    data-base="<?= esc(base_url(), 'attr') ?>"
    data-api="<?= esc(rtrim(base_url('api/v1'), '/'), 'attr') ?>"
    data-prefix="<?= esc($prefix, 'attr') ?>"
    data-preview="<?= $isPreview ? '1' : '0' ?>"
    data-page-id="<?= $pageId !== null ? (string) $pageId : '' ?>"
    data-workspace-id="<?= $workspaceId !== null ? (string) $workspaceId : '' ?>"
    data-page-title="<?= esc($currentPage['title'] ?? '', 'attr') ?>"
    data-user-name="<?= esc($user['name'] ?? 'Usuario', 'attr') ?>"
>
    <a class="nx-skip" href="#nx-main">Saltar al contenido</a>

    <noscript>
        <div class="nx-alert nx-alert--warning" style="margin: 12px; border-radius: 8px;">
            <?= nexo_icon('alert-triangle') ?>
            <div class="nx-alert__body">
                <span class="nx-alert__title">JavaScript está desactivado</span>
                Puedes leer el contenido, pero el editor, el tablero y el tutor necesitan JavaScript.
            </div>
        </div>
    </noscript>

    <?= $this->include('partials/sidebar') ?>

    <div class="nx-mobile-backdrop" data-sidebar-backdrop></div>

    <div class="nx-shell">
        <?= $this->include('partials/topbar') ?>

        <main class="nx-main nx-page-enter" id="nx-main" tabindex="-1">
            <?= $content ?? $this->renderSection('content') ?>
        </main>
    </div>

    <?= $this->include('partials/command-palette') ?>
    <?= $this->include('partials/chat-panel') ?>
    <?= $this->include('partials/toast-region') ?>

    <script src="<?= base_url('vendor/bootstrap/bootstrap.bundle.min.js') ?>" defer></script>
    <?php if ($needsSort): ?>
        <script src="<?= base_url('vendor/sortable/Sortable.min.js') ?>" defer></script>
    <?php endif ?>
    <script type="module" src="<?= base_url('js/app.js') ?>"></script>
    <?= $this->renderSection('scripts') ?>
</body>
</html>
