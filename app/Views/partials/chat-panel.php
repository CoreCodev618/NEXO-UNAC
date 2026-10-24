<?php
/**
 * Panel del tutor.
 *
 * @var array|null $currentPage
 * @var bool       $isPreview
 */
$page      = $currentPage ?? null;
$pageTitle = $page['title'] ?? null;
$suggestions = $pageTitle !== null
    ? [
        ['icon' => 'file-text', 'text' => 'Resume esta página en cinco puntos'],
        ['icon' => 'code', 'text' => 'Explícame el bloque de código con un ejemplo'],
        ['icon' => 'check-square', 'text' => 'Dame tres preguntas para repasar'],
    ]
    : [
        ['icon' => 'compass', 'text' => '¿Cómo puedo organizar mi semana en NEXO?'],
        ['icon' => 'kanban', 'text' => '¿Qué tareas tengo pendientes?'],
        ['icon' => 'sparkles', 'text' => '¿Para qué sirve el tutor?'],
    ];
?>
<div class="nx-chat__backdrop" data-chat-backdrop></div>

<aside
    class="nx-chat"
    id="nx-chat"
    aria-labelledby="nx-chat-title"
    aria-hidden="true"
    inert
>
    <div class="nx-chat__head">
        <span class="nx-chat__head-icon" aria-hidden="true"><?= nexo_icon('sparkles') ?></span>
        <span class="nx-chat__head-text">
            <span class="nx-chat__title" id="nx-chat-title">Tutor NEXO</span>
            <span class="nx-chat__status<?= $isPreview ? ' is-preview' : '' ?>">
                <span class="nx-dot" aria-hidden="true"></span>
                <?php if ($isPreview): ?>
                    Vista demo · backend pendiente
                <?php elseif ($pageTitle !== null): ?>
                    Contexto: <?= esc($pageTitle) ?>
                <?php else: ?>
                    Contexto del material
                <?php endif ?>
            </span>
        </span>
        <span class="nx-chat__head-actions">
            <button class="nx-icon-btn" type="button" data-chat-new aria-label="Nueva conversación" data-tip="Nueva conversación">
                <?= nexo_icon('refresh') ?>
            </button>
            <button class="nx-icon-btn" type="button" data-chat-close aria-label="Cerrar tutor" data-tip="Cerrar">
                <?= nexo_icon('x') ?>
            </button>
        </span>
    </div>

    <div class="nx-chat__body nx-scroll" data-chat-body>
        <div class="nx-chat__intro" data-chat-intro>
            <?= nexo_illustration('spark', 'nx-chat__intro-art') ?>
            <h2 class="nx-chat__intro-title">Pregunta con el material a la mano</h2>
            <p class="nx-chat__intro-text">
                El tutor responde con tus páginas y documentos del espacio.
                Si aún no hay conexión con el backend, te lo dirá sin inventar fuentes.
            </p>

            <div class="nx-chat__suggestions">
                <?php foreach ($suggestions as $suggestion): ?>
                    <button class="nx-chat__suggestion" type="button" data-chat-suggestion="<?= esc($suggestion['text'], 'attr') ?>">
                        <?= nexo_icon($suggestion['icon']) ?>
                        <span><?= esc($suggestion['text']) ?></span>
                    </button>
                <?php endforeach ?>
            </div>
        </div>

        <div class="nx-chat__messages" data-chat-messages role="log" aria-live="polite" aria-relevant="additions"></div>
    </div>

    <div class="nx-chat__composer">
        <?php if ($pageTitle !== null): ?>
            <span class="nx-chat__context">
                <?= nexo_icon('file-text') ?>
                <span>Contexto:</span>
                <strong><?= esc($pageTitle) ?></strong>
            </span>
        <?php endif ?>

        <form class="nx-chat__form" data-chat-form>
            <label class="nx-sr-only" for="nx-chat-input">Escribe tu pregunta</label>
            <textarea
                class="nx-chat__input"
                id="nx-chat-input"
                rows="1"
                maxlength="1200"
                placeholder="Escribe una pregunta…"
                data-chat-input
            ></textarea>
            <button class="nx-chat__send" type="submit" data-chat-send aria-label="Enviar pregunta">
                <?= nexo_icon('send') ?>
            </button>
        </form>

        <p class="nx-chat__foot">
            <?= nexo_icon('info') ?>
            <span>El tutor puede equivocarse. Verifica siempre las fuentes y tus apuntes.</span>
        </p>
    </div>
</aside>
