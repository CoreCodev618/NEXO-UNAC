<?php
/**
 * UI kit de desarrollo: componentes, estados y tokens.
 *
 * @var string $prefix
 */
$colors = [
    ['Papel 0', '--paper-0', 'Fondo de superficies'],
    ['Papel', '--paper', 'Fondo de la aplicación'],
    ['Tinta', '--ink', 'Texto principal'],
    ['Vino', '--wine', 'Acción principal'],
    ['Oro viejo', '--gold', 'Detalle y tutor'],
    ['Verde', '--success', 'Confirmación'],
    ['Rojo', '--danger', 'Error y acciones destructivas'],
    ['Regla', '--rule', 'Bordes y separadores'],
];
$icons = ['home', 'layout-dashboard', 'file-text', 'folder', 'book-open', 'graduation-cap', 'code', 'table', 'check-square', 'sparkles', 'compass', 'flask', 'kanban', 'calendar', 'message-square', 'send', 'search', 'settings', 'user', 'users', 'bell', 'link', 'tag', 'upload', 'download', 'trash', 'filter', 'grip-vertical', 'chevron-right', 'arrow-right'];
?>
<div class="nx-page nx-page--wide">
    <header class="nx-page__head">
        <div class="nx-page__head-main">
            <p class="nx-eyebrow">Solo desarrollo</p>
            <h1 class="nx-page__title">El sistema, a la vista.</h1>
            <p class="nx-page__sub">
                Tokens, componentes y estados de NEXO. Esta página no se registra en producción y sirve para revisar
                que la interfaz siga hablando el mismo idioma.
            </p>
        </div>
        <div class="nx-page__actions">
            <span class="nx-badge nx-badge--outline"><?= nexo_icon('code') ?> /preview/ui-kit</span>
        </div>
    </header>

    <section class="nx-kit-section" aria-labelledby="kit-color">
        <div class="nx-kit-section__head">
            <div>
                <h2 class="nx-kit-section__title" id="kit-color">Color</h2>
                <p class="nx-kit-section__desc">Papel cálido, tinta café, vino para la acción y oro solo como detalle.</p>
            </div>
            <code class="nx-mono nx-muted" style="font-size:10px;">tokens.css</code>
        </div>
        <div class="nx-kit-grid">
            <?php foreach ($colors as [$name, $token, $use]): ?>
                <div class="nx-swatch">
                    <div class="nx-swatch__color" style="background: var(<?= esc($token, 'attr') ?>);"></div>
                    <div class="nx-swatch__body">
                        <span class="nx-swatch__name"><?= esc($name) ?><br><span class="nx-muted" style="font-weight:400;"><?= esc($use) ?></span></span>
                        <span class="nx-swatch__value"><?= esc($token) ?></span>
                    </div>
                </div>
            <?php endforeach ?>
        </div>
    </section>

    <section class="nx-kit-section" aria-labelledby="kit-type">
        <div class="nx-kit-section__head">
            <div>
                <h2 class="nx-kit-section__title" id="kit-type">Tipografía</h2>
                <p class="nx-kit-section__desc">Dos voces para leer y una para el código. Sin itálicas decorativas en títulos.</p>
            </div>
        </div>
        <div class="nx-type-specimen">
            <span class="nx-type-specimen__label">Newsreader · display</span>
            <div class="nx-type-specimen__sample nx-type-specimen__sample--display">El salón, en orden.</div>
        </div>
        <div class="nx-type-specimen">
            <span class="nx-type-specimen__label">Public Sans · UI</span>
            <div class="nx-type-specimen__sample nx-type-specimen__sample--body">
                Apuntes compartidos, tareas con fecha y un tutor que responde con el material del curso.
                El cuerpo de texto usa una sans de trabajo, no una display.
            </div>
        </div>
        <div class="nx-type-specimen">
            <span class="nx-type-specimen__label">JetBrains Mono · código</span>
            <div class="nx-type-specimen__sample nx-type-specimen__sample--mono">modelo += 3 * x + 2 * y;</div>
        </div>
    </section>

    <section class="nx-kit-section" aria-labelledby="kit-buttons">
        <div class="nx-kit-section__head">
            <div>
                <h2 class="nx-kit-section__title" id="kit-buttons">Botones</h2>
                <p class="nx-kit-section__desc">Una acción principal por zona. El vino no se repite en detalles decorativos.</p>
            </div>
        </div>
        <div class="nx-kit-row">
            <button class="nx-btn nx-btn--primary" type="button"><?= nexo_icon('plus') ?> Acción principal</button>
            <button class="nx-btn nx-btn--secondary" type="button">Acción secundaria</button>
            <button class="nx-btn nx-btn--ghost" type="button">Acción silenciosa</button>
            <button class="nx-btn nx-btn--tutor" type="button"><?= nexo_icon('sparkles') ?> Tutor</button>
            <button class="nx-btn nx-btn--danger-ghost" type="button"><?= nexo_icon('trash') ?> Eliminar</button>
            <button class="nx-btn nx-btn--primary" type="button" disabled>Deshabilitado</button>
        </div>
        <div class="nx-kit-row" style="margin-top: 12px;">
            <button class="nx-btn nx-btn--primary nx-btn--sm" type="button">Pequeño</button>
            <button class="nx-btn nx-btn--primary" type="button">Medio</button>
            <button class="nx-btn nx-btn--primary nx-btn--lg" type="button">Grande</button>
            <button class="nx-icon-btn nx-icon-btn--bordered" type="button" aria-label="Ajustes"><?= nexo_icon('settings') ?></button>
            <button class="nx-btn nx-btn--primary is-loading" type="button">Guardando</button>
        </div>
    </section>

    <section class="nx-kit-section" aria-labelledby="kit-forms">
        <div class="nx-kit-section__head">
            <div>
                <h2 class="nx-kit-section__title" id="kit-forms">Formularios</h2>
                <p class="nx-kit-section__desc">Etiquetas visibles, ayuda cercana y errores que dicen qué corregir.</p>
            </div>
        </div>
        <div class="nx-grid nx-grid--two">
            <div class="nx-field">
                <label class="nx-label" for="kit-input">Correo institucional</label>
                <input class="nx-input" id="kit-input" type="email" placeholder="nombre@unac.edu.pe">
                <span class="nx-hint">Usa el correo con el que te registraste.</span>
            </div>
            <div class="nx-field nx-field--error">
                <label class="nx-label" for="kit-error">Archivo adjunto</label>
                <input class="nx-input" id="kit-error" type="text" value="apuntes.rar" aria-invalid="true">
                <span class="nx-error">Ese formato no está permitido. Usa PDF, DOCX, PPTX, JPG, PNG o ZIP.</span>
            </div>
            <div class="nx-field">
                <label class="nx-label" for="kit-select">Prioridad</label>
                <select class="nx-select" id="kit-select">
                    <option>Alta</option>
                    <option selected>Media</option>
                    <option>Baja</option>
                </select>
            </div>
            <div class="nx-field">
                <span class="nx-label">Avisos</span>
                <label class="nx-check">
                    <input type="checkbox" checked>
                    <span>Recibir avisos de comentarios en mis páginas</span>
                </label>
                <label class="nx-check" style="margin-top:8px;">
                    <input type="checkbox">
                    <span>Recibir resumen semanal por correo</span>
                </label>
            </div>
        </div>
    </section>

    <section class="nx-kit-section" aria-labelledby="kit-feedback">
        <div class="nx-kit-section__head">
            <div>
                <h2 class="nx-kit-section__title" id="kit-feedback">Avisos y estados</h2>
                <p class="nx-kit-section__desc">El color semántico nunca es la única señal: siempre hay texto e icono.</p>
            </div>
        </div>
        <div class="nx-stack">
            <div class="nx-alert nx-alert--info"><?= nexo_icon('info') ?><div class="nx-alert__body"><span class="nx-alert__title">Contexto</span>El tutor responderá con las páginas de este espacio cuando el backend esté conectado.</div></div>
            <div class="nx-alert nx-alert--success"><?= nexo_icon('check-circle') ?><div class="nx-alert__body"><span class="nx-alert__title">Guardado local</span>Los cambios se conservaron en esta pestaña.</div></div>
            <div class="nx-alert nx-alert--warning"><?= nexo_icon('alert-triangle') ?><div class="nx-alert__body"><span class="nx-alert__title">Sin conexión al tutor</span>La interfaz está lista, pero el endpoint de chat aún no responde.</div></div>
            <div class="nx-alert nx-alert--danger"><?= nexo_icon('alert-circle') ?><div class="nx-alert__body"><span class="nx-alert__title">Formato no permitido</span>Los archivos .rar no se aceptan. Convierte el archivo a ZIP o PDF.</div></div>
        </div>

        <div class="nx-kit-row" style="margin-top: 20px;">
            <span class="nx-badge">Neutro</span>
            <span class="nx-badge nx-badge--wine">Vino</span>
            <span class="nx-badge nx-badge--gold">Oro</span>
            <span class="nx-badge nx-badge--success">Listo</span>
            <span class="nx-badge nx-badge--danger">Error</span>
            <span class="nx-badge nx-badge--outline">Outline</span>
            <span class="nx-status"><span class="nx-dot nx-dot--success"></span>En línea</span>
            <span class="nx-status"><span class="nx-dot nx-dot--gold"></span>Pendiente</span>
            <span class="nx-status"><span class="nx-dot nx-dot--wine"></span>En curso</span>
        </div>

        <div class="nx-kit-row" style="margin-top: 20px;">
            <button class="nx-btn nx-btn--secondary" type="button" data-demo-toast="Guardado en la vista de diseño.">Probar toast</button>
            <button class="nx-btn nx-btn--secondary" type="button" data-dialog-open="kit-dialog">Abrir diálogo</button>
        </div>
    </section>

    <section class="nx-kit-section" aria-labelledby="kit-empty">
        <div class="nx-kit-section__head">
            <div>
                <h2 class="nx-kit-section__title" id="kit-empty">Estados vacíos</h2>
                <p class="nx-kit-section__desc">Un vacío debe decir qué falta y cuál es el siguiente paso.</p>
            </div>
        </div>
        <div class="nx-kit-grid">
            <div class="nx-card" style="padding: var(--space-5);">
                <div class="nx-empty nx-empty--compact">
                    <?= nexo_illustration('book', 'nx-empty__art') ?>
                    <p class="nx-empty__title">Sin páginas todavía</p>
                    <p class="nx-empty__text">Crea la primera página del espacio para empezar a ordenar el material.</p>
                    <button class="nx-btn nx-btn--primary nx-btn--sm" type="button"><?= nexo_icon('plus') ?> Nueva página</button>
                </div>
            </div>
            <div class="nx-card" style="padding: var(--space-5);">
                <div class="nx-empty nx-empty--compact">
                    <?= nexo_illustration('search', 'nx-empty__art') ?>
                    <p class="nx-empty__title">Nada con ese nombre</p>
                    <p class="nx-empty__text">Prueba con otra palabra o busca por el nombre del espacio.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="nx-kit-section" aria-labelledby="kit-icons">
        <div class="nx-kit-section__head">
            <div>
                <h2 class="nx-kit-section__title" id="kit-icons">Iconografía</h2>
                <p class="nx-kit-section__desc">Trazo de 1.5 px, 24×24, sin emojis. El icono acompaña al texto, no lo reemplaza.</p>
            </div>
            <span class="nx-chip"><?= count($icons) ?> iconos en uso</span>
        </div>
        <div class="nx-kit-icons">
            <?php foreach ($icons as $icon): ?>
                <span class="nx-kit-icon">
                    <?= nexo_icon($icon) ?>
                    <span><?= esc($icon) ?></span>
                </span>
            <?php endforeach ?>
        </div>
    </section>

    <section class="nx-kit-section" aria-labelledby="kit-copy">
        <div class="nx-kit-section__head">
            <div>
                <h2 class="nx-kit-section__title" id="kit-copy">Voz</h2>
                <p class="nx-kit-section__desc">Cercana, concreta y sin promesas que el backend no pueda cumplir.</p>
            </div>
        </div>
        <div class="nx-grid nx-grid--two">
            <div class="nx-code-sample">Bien: “El tutor aún no está conectado. Tus apuntes siguen aquí.”<br><br>Mal: “La IA ya leyó todos tus documentos y responderá en segundos.”</div>
            <div class="nx-code-sample">Bien: “No encontramos esa página. Vuelve a tu mesa o revisa la dirección.”<br><br>Mal: “Error 404. Algo salió mal.”</div>
        </div>
    </section>
</div>

<dialog class="nx-modal nx-modal--sm" id="kit-dialog" aria-labelledby="kit-dialog-title">
    <div class="nx-modal__panel">
        <div class="nx-modal__head">
            <div>
                <h2 class="nx-modal__title" id="kit-dialog-title">Un diálogo del sistema</h2>
                <p class="nx-modal__desc">Los modales usan el elemento nativo &lt;dialog&gt;, con foco atrapado y cierre con Escape.</p>
            </div>
            <button class="nx-icon-btn" type="button" data-dialog-close aria-label="Cerrar"><?= nexo_icon('x') ?></button>
        </div>
        <div class="nx-modal__body">
            <div class="nx-alert nx-alert--info"><?= nexo_icon('info') ?><div class="nx-alert__body">Esta es una muestra del UI kit. No guarda nada.</div></div>
        </div>
        <div class="nx-modal__foot">
            <button class="nx-btn nx-btn--secondary" type="button" data-dialog-close>Cancelar</button>
            <button class="nx-btn nx-btn--primary" type="button" data-dialog-close>Entendido</button>
        </div>
    </div>
</dialog>
