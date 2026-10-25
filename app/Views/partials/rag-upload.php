<?php
/**
 * Material del curso para el tutor RAG.
 *
 * @var array $page
 * @var bool  $isPreview
 */
$pageId   = (int) ($page['id'] ?? 0);
$disabled = $isPreview || $pageId <= 0;
$maxMb    = max(1, min(100, (int) env('RAG_MAX_UPLOAD_MB', 20)));
?>
<section
    class="nx-rag"
    data-rag-upload
    data-rag-page="<?= $pageId ?>"
    data-rag-preview="<?= $isPreview ? '1' : '0' ?>"
    data-rag-max-mb="<?= $maxMb ?>"
>
    <header class="nx-rag__head">
        <span class="nx-rag__icon" aria-hidden="true"><?= nexo_icon('sparkles') ?></span>
        <div class="nx-rag__heading">
            <h2 class="nx-rag__title">Material para el tutor</h2>
            <p class="nx-rag__subtitle">
                Sube el PDF, DOCX o PPTX de la clase. Se indexa y el tutor lo cita con su ubicación.
            </p>
        </div>
        <span class="nx-badge nx-badge--outline" data-rag-count>0</span>
    </header>

    <?php if ($isPreview): ?>
        <p class="nx-rag__note">
            <?= nexo_icon('info') ?>
            Vista demo: el material no se indexa. Inicia sesión para subir archivos reales.
        </p>
    <?php elseif ($pageId <= 0): ?>
        <p class="nx-rag__note">
            <?= nexo_icon('info') ?>
            Guarda la página primero para asociar el material a este apunte.
        </p>
    <?php endif ?>

    <form class="nx-rag__form" data-rag-form>
        <label class="nx-rag__dropzone" data-rag-dropzone>
            <span class="nx-rag__dropicon" aria-hidden="true"><?= nexo_icon('upload') ?></span>
            <strong data-rag-file-label>Arrastra un archivo o haz clic para elegirlo</strong>
            <span>PDF, DOCX, PPTX, TXT, MD, JPG, PNG o WEBP · máx. <?= $maxMb ?> MB</span>
            <input
                class="nx-sr-only"
                type="file"
                accept=".pdf,.docx,.pptx,.txt,.md,.jpg,.jpeg,.png,.webp"
                data-rag-input
                <?= $disabled ? 'disabled' : '' ?>
            >
        </label>

        <div class="nx-rag__fields">
            <label class="nx-rag__field">
                <span>Título</span>
                <input
                    class="nx-input"
                    type="text"
                    maxlength="255"
                    placeholder="Por defecto, el nombre del archivo"
                    data-rag-title
                    <?= $disabled ? 'disabled' : '' ?>
                >
            </label>
            <label class="nx-rag__field">
                <span>Tema</span>
                <input
                    class="nx-input"
                    type="text"
                    maxlength="255"
                    placeholder="Ej. Semana 6 · Programación entera"
                    data-rag-topic
                    <?= $disabled ? 'disabled' : '' ?>
                >
            </label>
            <label class="nx-rag__field">
                <span>Curso</span>
                <select class="nx-select" data-rag-curso <?= $disabled ? 'disabled' : '' ?>>
                    <option value="1">General</option>
                    <option value="2">Programación Web II</option>
                    <option value="3">Investigación de Operaciones</option>
                    <option value="4">Sistemas Digitales</option>
                </select>
            </label>
            <label class="nx-rag__field">
                <span>Fecha de clase</span>
                <input class="nx-input" type="date" data-rag-date <?= $disabled ? 'disabled' : '' ?>>
            </label>
        </div>

        <div class="nx-rag__actions">
            <span class="nx-rag__status" data-rag-status role="status">Sin archivo seleccionado.</span>
            <button class="nx-btn nx-btn--primary nx-btn--sm" type="submit" data-rag-submit <?= $disabled ? 'disabled' : '' ?>>
                <?= nexo_icon('upload') ?>
                Subir e indexar
            </button>
        </div>

        <div class="nx-rag__progress" data-rag-progress hidden>
            <span data-rag-progress-bar></span>
        </div>
    </form>

    <div class="nx-rag__list" data-rag-list aria-live="polite">
        <?php if (! $disabled): ?>
            <p class="nx-rag__empty">Cargando material…</p>
        <?php endif ?>
    </div>
</section>
