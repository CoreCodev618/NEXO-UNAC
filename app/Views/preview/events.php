<?php
/**
 * Vista de desarrollo para el Radar de eventos.
 *
 * La vista real de /events pertenece al backend. Esta versión no inventa
 * eventos: solo documenta el estado de la integración.
 *
 * @var string $prefix
 * @var string $eventsUrl
 * @var bool   $isPreview
 */
?>
<div class="nx-page">
    <header class="nx-page__head">
        <div class="nx-page__head-main">
            <p class="nx-eyebrow">Tech Radar · FIIS</p>
            <h1 class="nx-page__title">Lo que viene en la facultad.</h1>
            <p class="nx-page__sub">
                Charlas, convocatorias y ferias de la FIIS, con fecha y lugar. El Radar se alimenta del backend de eventos;
                esta vista de desarrollo no muestra datos de ejemplo.
            </p>
        </div>
        <div class="nx-page__actions">
            <a class="nx-btn nx-btn--secondary" href="<?= nexo_url($prefix, 'workspaces/1/calendar') ?>">
                <?= nexo_icon('calendar') ?>
                Ver el calendario
            </a>
            <a class="nx-btn nx-btn--primary" href="<?= nexo_url($prefix, 'dashboard') ?>">
                <?= nexo_icon('arrow-left') ?>
                Volver a mi mesa
            </a>
        </div>
    </header>

    <div class="nx-alert nx-alert--warning" style="margin-bottom: 24px;">
        <?= nexo_icon('info') ?>
        <div class="nx-alert__body">
            <span class="nx-alert__title">Vista de desarrollo · Radar pendiente</span>
            La vista real <code class="nx-mono">/events</code> pertenece al backend y todavía no está en esta rama.
            Para no presentar eventos inventados, aquí solo mostramos el estado de la integración.
        </div>
    </div>

    <div class="nx-radar-empty" style="margin-top: 0;">
        <div class="nx-empty" style="padding: var(--space-12) var(--space-6);">
            <?= nexo_illustration('compass') ?>
            <p class="nx-empty__title">El Radar todavía no tiene datos</p>
            <p class="nx-empty__text" style="max-width: 54ch;">
                Cuando el equipo de backend publique el endpoint de eventos, las tarjetas de charlas y convocatorias
                aparecerán aquí y también en el calendario del espacio. Mientras tanto, preferimos un vacío honesto
                antes que una lista de eventos ficticios.
            </p>
            <div class="nx-row nx-row--wrap" style="justify-content: center; margin-top: var(--space-5);">
                <a class="nx-btn nx-btn--secondary" href="<?= nexo_url($prefix, 'workspaces/1/kanban') ?>">
                    <?= nexo_icon('kanban') ?>
                    Ir al tablero
                </a>
                <a class="nx-btn nx-btn--ghost" href="<?= nexo_url($prefix, 'ui-kit') ?>">
                    <?= nexo_icon('layers') ?>
                    Ver el UI kit
                </a>
            </div>
        </div>
    </div>

    <section class="nx-section" style="margin-top: var(--space-10);">
        <div class="nx-section__head">
            <div>
                <h2 class="nx-section__title">Estado de la integración</h2>
                <p class="nx-section__desc">Lo que ya está del lado del frontend y lo que depende del backend.</p>
            </div>
        </div>

        <div class="nx-page-list">
            <div class="nx-page-list__row" style="cursor: default;">
                <span class="nx-icon-tile nx-icon-tile--sm" aria-hidden="true"><?= nexo_icon('layout-dashboard') ?></span>
                <span class="nx-page-list__main">
                    <span class="nx-page-list__title">Navegación y estado vacío</span>
                    <span class="nx-page-list__meta"><span>Listo en el frontend</span></span>
                </span>
                <span class="nx-badge nx-badge--success">Listo</span>
            </div>
            <div class="nx-page-list__row" style="cursor: default;">
                <span class="nx-icon-tile nx-icon-tile--sm" aria-hidden="true"><?= nexo_icon('calendar') ?></span>
                <span class="nx-page-list__main">
                    <span class="nx-page-list__title">Integración con el calendario</span>
                    <span class="nx-page-list__meta"><span>Las tareas ya se muestran por fecha; los eventos se suman cuando exista el endpoint.</span></span>
                </span>
                <span class="nx-badge nx-badge--gold">Pendiente</span>
            </div>
            <div class="nx-page-list__row" style="cursor: default;">
                <span class="nx-icon-tile nx-icon-tile--sm" aria-hidden="true"><?= nexo_icon('compass') ?></span>
                <span class="nx-page-list__main">
                    <span class="nx-page-list__title">Vista real <code class="nx-mono">/events</code></span>
                    <span class="nx-page-list__meta"><span>Pertenece al equipo de backend; este repositorio no la sobrescribe.</span></span>
                </span>
                <span class="nx-badge nx-badge--outline">Backend</span>
            </div>
        </div>
    </section>
</div>
