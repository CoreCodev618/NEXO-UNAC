<?php
/**
 * Layout de autenticación.
 *
 * @var string $prefix
 * @var bool   $isPreview
 */
$security = config('Security');
$authHome = ! empty($isPreview) ? base_url('preview/dashboard') : base_url();
?>
<!doctype html>
<html lang="es-PE">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="oklch(38% 0.12 15)">
    <meta name="description" content="Entra a NEXO UNAC y ordena tus apuntes, tareas y material del salón.">
    <meta name="csrf-token" content="<?= esc(csrf_hash(), 'attr') ?>">
    <meta name="csrf-header" content="<?= esc(csrf_header(), 'attr') ?>">
    <meta name="csrf-cookie" content="<?= esc($security->cookieName, 'attr') ?>">

    <title><?= $this->renderSection('title') ?: 'NEXO UNAC' ?> · NEXO UNAC</title>

    <link rel="icon" href="<?= base_url('favicon.svg') ?>" type="image/svg+xml">
    <link rel="preload" href="<?= base_url('fonts/newsreader-latin.woff2') ?>" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="<?= base_url('fonts/public-sans-latin.woff2') ?>" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?= base_url('vendor/bootstrap/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= base_url('css/tokens.css') ?>">
    <link rel="stylesheet" href="<?= base_url('css/base.css') ?>">
    <link rel="stylesheet" href="<?= base_url('css/components.css') ?>">
    <link rel="stylesheet" href="<?= base_url('css/layout.css') ?>">
    <link rel="stylesheet" href="<?= base_url('css/auth.css') ?>">
</head>
<body
    class="nx-auth-body"
    data-base="<?= esc(base_url(), 'attr') ?>"
    data-api="<?= esc(rtrim(base_url('api/v1'), '/'), 'attr') ?>"
    data-prefix="<?= esc($prefix ?? '', 'attr') ?>"
    data-preview="<?= ! empty($isPreview) ? '1' : '0' ?>"
>
    <a class="nx-skip" href="#nx-auth-main">Saltar al formulario</a>

    <main class="nx-auth" id="nx-auth-main">
        <aside class="nx-auth__brand-panel" aria-label="NEXO UNAC">
            <a class="nx-auth__brand" href="<?= esc($authHome, 'attr') ?>" aria-label="NEXO UNAC, inicio">
                <span class="nx-brand__mark" aria-hidden="true">N</span>
                <span class="nx-brand__name">NEXO</span>
                <span class="nx-brand__sub">UNAC</span>
            </a>

            <h1 class="nx-auth__statement">
                El salón, en orden. <span class="nx-auth__highlight">Incluso cuando la semana no lo está.</span>
            </h1>
            <p class="nx-auth__sub">
                Apuntes compartidos, tareas con fecha y un tutor que responde con el material de tus cursos. Sin saltar entre cinco herramientas.
            </p>

            <div class="nx-auth__cards" aria-hidden="true">
                <div class="nx-auth__card">
                    <div class="nx-auth__card-top">
                        <span class="nx-auth__card-tag">EP Sistemas</span>
                        <?= nexo_icon('book-open', 'nx-icon nx-icon--sm') ?>
                    </div>
                    <div class="nx-auth__card-title">Semana 6 — Programación entera</div>
                    <div class="nx-auth__card-lines">
                        <span class="nx-auth__card-line"></span>
                        <span class="nx-auth__card-line"></span>
                        <span class="nx-auth__card-line"></span>
                    </div>
                </div>
                <div class="nx-auth__card">
                    <div class="nx-auth__card-top">
                        <span class="nx-auth__card-tag">Club IA · FIIS</span>
                        <?= nexo_icon('sparkles', 'nx-icon nx-icon--sm') ?>
                    </div>
                    <div class="nx-auth__card-title">Ruta de aprendizaje</div>
                    <div class="nx-auth__card-lines">
                        <span class="nx-auth__card-line"></span>
                        <span class="nx-auth__card-line"></span>
                        <span class="nx-auth__card-line"></span>
                    </div>
                </div>
                <div class="nx-auth__card">
                    <div class="nx-auth__card-top">
                        <span class="nx-auth__card-tag">Proyecto final</span>
                        <?= nexo_icon('compass', 'nx-icon nx-icon--sm') ?>
                    </div>
                    <div class="nx-auth__card-title">NEXO: conocimiento del salón</div>
                    <div class="nx-auth__card-lines">
                        <span class="nx-auth__card-line"></span>
                        <span class="nx-auth__card-line"></span>
                        <span class="nx-auth__card-line"></span>
                    </div>
                </div>
            </div>

            <div class="nx-auth__brand-foot">
                <span><?= nexo_icon('graduation-cap') ?> Universidad Nacional del Callao</span>
                <span>Hecho para estudiar mejor</span>
            </div>
        </aside>

        <section class="nx-auth__panel" aria-label="Acceso a NEXO">
            <a class="nx-brand nx-auth__mobile-brand" href="<?= esc($authHome, 'attr') ?>" aria-label="NEXO UNAC, inicio">
                <span class="nx-brand__mark" aria-hidden="true">N</span>
                <span class="nx-brand__name">NEXO</span>
                <span class="nx-brand__sub">UNAC</span>
            </a>

            <?= $this->renderSection('main') ?>
        </section>
    </main>

    <div class="nx-toasts" id="nx-toasts" aria-live="polite" aria-atomic="false" data-toast-region></div>

    <script type="module" src="<?= base_url('js/auth.js') ?>"></script>
    <?= $this->renderSection('scripts') ?>
</body>
</html>
