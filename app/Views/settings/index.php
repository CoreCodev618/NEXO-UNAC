<?php
/**
 * Ajustes de la cuenta y del espacio activo.
 *
 * @var array  $user
 * @var array  $workspace
 * @var string $tab
 * @var string $baseUrl
 * @var string $prefix
 * @var bool   $isPreview
 */
$tabs = [
    'profile'   => ['label' => 'Perfil', 'icon' => 'user'],
    'workspace' => ['label' => 'Espacio', 'icon' => 'layers'],
    'about'     => ['label' => 'Acerca de', 'icon' => 'info'],
];
?>
<div class="nx-page">
    <header class="nx-page__head">
        <div class="nx-page__head-main">
            <p class="nx-eyebrow">Ajustes</p>
            <h1 class="nx-page__title">Tu cuenta, a tu manera.</h1>
            <p class="nx-page__sub">
                Revisa tus datos, decide cómo te avisamos y ajusta lo que el equipo ve de ti.
            </p>
        </div>
    </header>

    <?php if ($isPreview): ?>
        <div class="nx-alert nx-alert--warning" style="margin-bottom: 24px;">
            <?= nexo_icon('info') ?>
            <div class="nx-alert__body">
                <span class="nx-alert__title">Vista de diseño</span>
                Los formularios de esta pantalla no escriben en la base de datos. Sirven para revisar estados, jerarquía y accesibilidad.
            </div>
        </div>
    <?php endif ?>

    <div class="nx-grid nx-grid--settings">
        <nav class="nx-settings-nav" aria-label="Secciones de ajustes">
            <?php foreach ($tabs as $key => $meta): ?>
                <a
                    class="nx-nav__item<?= $tab === $key ? ' is-active' : '' ?>"
                    href="<?= esc($baseUrl, 'attr') ?>?tab=<?= esc($key, 'attr') ?>"
                    <?= $tab === $key ? 'aria-current="page"' : '' ?>
                >
                    <?= nexo_icon($meta['icon']) ?>
                    <span><?= esc($meta['label']) ?></span>
                </a>
            <?php endforeach ?>
        </nav>

        <div>
            <?php if ($tab === 'profile'): ?>
                <section class="nx-settings-section" aria-labelledby="settings-profile">
                    <div class="nx-settings-section__head">
                        <h2 class="nx-settings-section__title" id="settings-profile">Perfil</h2>
                        <p class="nx-settings-section__desc">Así te ven tus compañeros en los comentarios, tareas y espacios.</p>
                    </div>

                    <div class="nx-profile-card">
                        <span class="nx-avatar nx-avatar--lg nx-avatar--<?= esc($user['tone'], 'attr') ?>" aria-hidden="true"><?= esc($user['initials']) ?></span>
                        <div class="nx-profile-card__main">
                            <span class="nx-profile-card__name"><?= esc($user['name']) ?></span>
                            <span class="nx-profile-card__email"><?= esc($user['email']) ?></span>
                            <span class="nx-badge nx-badge--wine" style="align-self:flex-start; margin-top:4px;"><?= esc($user['role_label']) ?> · FIIS</span>
                        </div>
                        <button class="nx-btn nx-btn--secondary nx-btn--sm" type="button" data-coming-soon="La carga de foto de perfil llegará con el almacenamiento de archivos del backend.">
                            <?= nexo_icon('upload') ?>
                            Cambiar foto
                        </button>
                    </div>
                </section>

                <section class="nx-settings-section" aria-labelledby="settings-data">
                    <div class="nx-settings-section__head">
                        <h2 class="nx-settings-section__title" id="settings-data">Datos personales</h2>
                        <p class="nx-settings-section__desc">Mantén tu nombre de usuario reconocible para el equipo.</p>
                    </div>

                    <form class="nx-stack" data-settings-form="perfil">
                        <div class="nx-grid nx-grid--two">
                            <div class="nx-field">
                                <label class="nx-label" for="settings-name">Nombre completo</label>
                                <input class="nx-input" id="settings-name" name="name" type="text" value="<?= esc($user['name']) ?>" autocomplete="name">
                            </div>
                            <div class="nx-field">
                                <label class="nx-label" for="settings-username">Nombre de usuario</label>
                                <input class="nx-input" id="settings-username" name="username" type="text" value="<?= esc($user['username']) ?>" autocomplete="username">
                            </div>
                        </div>

                        <div class="nx-field">
                            <label class="nx-label" for="settings-email">
                                Correo institucional
                                <span class="nx-label__hint">Lo usa el tutor para identificar tus documentos</span>
                            </label>
                            <input class="nx-input" id="settings-email" name="email" type="email" value="<?= esc($user['email']) ?>" autocomplete="email">
                        </div>

                        <div class="nx-field">
                            <label class="nx-label" for="settings-bio">Una línea sobre ti</label>
                            <textarea class="nx-textarea" id="settings-bio" name="bio" rows="2" maxlength="160" placeholder="Ej.: Estudiante de Sistemas, interesado en datos y optimización."></textarea>
                            <span class="nx-hint">Se muestra en tu perfil dentro de NEXO. Máximo 160 caracteres.</span>
                        </div>

                        <div class="nx-row" style="justify-content:flex-end;">
                            <button class="nx-btn nx-btn--primary" type="submit"><?= nexo_icon('check') ?> Guardar cambios</button>
                        </div>
                    </form>
                </section>

                <section class="nx-settings-section" aria-labelledby="settings-security">
                    <div class="nx-settings-section__head">
                        <h2 class="nx-settings-section__title" id="settings-security">Seguridad</h2>
                        <p class="nx-settings-section__desc">Tu sesión y tu contraseña. Nada de esto se comparte con otros espacios.</p>
                    </div>

                    <div class="nx-settings-row">
                        <div class="nx-settings-row__main">
                            <span class="nx-settings-row__title">Contraseña</span>
                            <span class="nx-settings-row__desc">Última actualización: no disponible en la demo.</span>
                        </div>
                        <button class="nx-btn nx-btn--secondary nx-btn--sm" type="button" data-coming-soon="El cambio de contraseña se habilitará con la configuración de correo del backend.">Cambiar contraseña</button>
                    </div>

                    <div class="nx-settings-row">
                        <div class="nx-settings-row__main">
                            <span class="nx-settings-row__title">Sesiones activas</span>
                            <span class="nx-settings-row__desc">Cierra la sesión en otros equipos si prestaste tu cuenta.</span>
                        </div>
                        <button class="nx-btn nx-btn--danger-ghost nx-btn--sm" type="button" data-coming-soon="El cierre de otras sesiones requiere el backend de autenticación.">Cerrar otras sesiones</button>
                    </div>
                </section>

                <section class="nx-settings-section" aria-labelledby="settings-prefs">
                    <div class="nx-settings-section__head">
                        <h2 class="nx-settings-section__title" id="settings-prefs">Preferencias</h2>
                        <p class="nx-settings-section__desc">Pequeñas decisiones que hacen la mesa más tuya.</p>
                    </div>

                    <form class="nx-stack" data-settings-form="preferencias">
                        <div class="nx-settings-row">
                            <div class="nx-settings-row__main">
                                <span class="nx-settings-row__title">Resumen semanal</span>
                                <span class="nx-settings-row__desc">Un correo con lo que se movió en tus espacios.</span>
                            </div>
                            <label class="nx-switch">
                                <input type="checkbox" name="weekly_digest" checked>
                                <span class="nx-switch__track" aria-hidden="true"></span>
                                <span class="nx-sr-only">Recibir resumen semanal</span>
                            </label>
                        </div>

                        <div class="nx-settings-row">
                            <div class="nx-settings-row__main">
                                <span class="nx-settings-row__title">Avisos de comentarios</span>
                                <span class="nx-settings-row__desc">Cuando alguien responde en una página que sigues.</span>
                            </div>
                            <label class="nx-switch">
                                <input type="checkbox" name="comment_notifications" checked>
                                <span class="nx-switch__track" aria-hidden="true"></span>
                                <span class="nx-sr-only">Recibir avisos de comentarios</span>
                            </label>
                        </div>

                        <div class="nx-settings-row">
                            <div class="nx-settings-row__main">
                                <span class="nx-settings-row__title">Avisos de tareas</span>
                                <span class="nx-settings-row__desc">Un recordatorio el día antes de la fecha límite.</span>
                            </div>
                            <label class="nx-switch">
                                <input type="checkbox" name="task_notifications">
                                <span class="nx-switch__track" aria-hidden="true"></span>
                                <span class="nx-sr-only">Recibir avisos de tareas</span>
                            </label>
                        </div>

                        <div class="nx-settings-row">
                            <div class="nx-settings-row__main">
                                <span class="nx-settings-row__title">Densidad de lectura</span>
                                <span class="nx-settings-row__desc">El ancho del texto en las páginas del editor.</span>
                            </div>
                            <select class="nx-select" name="reading_density" style="width: 180px;">
                                <option value="comoda">Cómoda</option>
                                <option value="compacta">Compacta</option>
                            </select>
                        </div>

                        <div class="nx-row" style="justify-content:flex-end;">
                            <button class="nx-btn nx-btn--primary" type="submit"><?= nexo_icon('check') ?> Guardar preferencias</button>
                        </div>
                    </form>
                </section>

            <?php elseif ($tab === 'workspace'): ?>
                <section class="nx-settings-section" aria-labelledby="settings-space">
                    <div class="nx-settings-section__head">
                        <h2 class="nx-settings-section__title" id="settings-space">Espacio activo</h2>
                        <p class="nx-settings-section__desc">Estos ajustes se aplican al espacio que tienes abierto.</p>
                    </div>

                    <div class="nx-profile-card">
                        <span class="nx-icon-tile nx-icon-tile--lg nx-icon-tile--<?= esc($workspace['tone'], 'attr') ?>" aria-hidden="true"><?= nexo_icon($workspace['icon']) ?></span>
                        <div class="nx-profile-card__main">
                            <span class="nx-profile-card__name"><?= esc($workspace['name']) ?></span>
                            <span class="nx-profile-card__email"><?= esc($workspace['type_full']) ?> · <?= esc($workspace['faculty']) ?></span>
                        </div>
                        <a class="nx-btn nx-btn--secondary nx-btn--sm" href="<?= nexo_url($prefix, 'workspaces/' . $workspace['id']) ?>">Abrir espacio <?= nexo_icon('arrow-right') ?></a>
                    </div>
                </section>

                <section class="nx-settings-section" aria-labelledby="settings-space-members">
                    <div class="nx-settings-section__head">
                        <h2 class="nx-settings-section__title" id="settings-space-members">Miembros y permisos</h2>
                        <p class="nx-settings-section__desc">Tú eres propietario de este espacio. Puedes invitar editores y lectores.</p>
                    </div>

                    <div class="nx-settings-row">
                        <div class="nx-settings-row__main">
                            <span class="nx-settings-row__title">Miembros del espacio</span>
                            <span class="nx-settings-row__desc"><?= count($workspace['members']) ?> personas tienen acceso a las páginas y archivos.</span>
                        </div>
                        <button class="nx-btn nx-btn--secondary nx-btn--sm" type="button" data-coming-soon="La gestión de miembros se activará con el backend de workspaces.">Gestionar miembros</button>
                    </div>

                    <div class="nx-settings-row">
                        <div class="nx-settings-row__main">
                            <span class="nx-settings-row__title">Quién puede editar</span>
                            <span class="nx-settings-row__desc">Los propietarios y editores pueden cambiar páginas y bloques.</span>
                        </div>
                        <select class="nx-select" style="width: 180px;" aria-label="Quién puede editar">
                            <option>Propietarios y editores</option>
                            <option>Solo propietarios</option>
                        </select>
                    </div>

                    <div class="nx-settings-row">
                        <div class="nx-settings-row__main">
                            <span class="nx-settings-row__title">Quién puede invitar</span>
                            <span class="nx-settings-row__desc">Controla si los editores pueden sumar a más personas.</span>
                        </div>
                        <label class="nx-switch">
                            <input type="checkbox" checked>
                            <span class="nx-switch__track" aria-hidden="true"></span>
                            <span class="nx-sr-only">Permitir que los editores inviten</span>
                        </label>
                    </div>
                </section>

                <section class="nx-settings-section" aria-labelledby="settings-space-prefs">
                    <div class="nx-settings-section__head">
                        <h2 class="nx-settings-section__title" id="settings-space-prefs">Avisos del espacio</h2>
                        <p class="nx-settings-section__desc">Elige cuándo el espacio te escribe.</p>
                    </div>

                    <div class="nx-settings-row">
                        <div class="nx-settings-row__main">
                            <span class="nx-settings-row__title">Cambios en páginas</span>
                            <span class="nx-settings-row__desc">Cuando alguien edita o comenta una página que sigues.</span>
                        </div>
                        <label class="nx-switch">
                            <input type="checkbox" checked>
                            <span class="nx-switch__track" aria-hidden="true"></span>
                            <span class="nx-sr-only">Avisos de cambios en páginas</span>
                        </label>
                    </div>

                    <div class="nx-settings-row">
                        <div class="nx-settings-row__main">
                            <span class="nx-settings-row__title">Tareas vencidas</span>
                            <span class="nx-settings-row__desc">Un resumen al inicio de la semana con lo que quedó pendiente.</span>
                        </div>
                        <label class="nx-switch">
                            <input type="checkbox" checked>
                            <span class="nx-switch__track" aria-hidden="true"></span>
                            <span class="nx-sr-only">Avisos de tareas vencidas</span>
                        </label>
                    </div>
                </section>

                <section class="nx-settings-section" aria-labelledby="settings-danger">
                    <div class="nx-settings-section__head">
                        <h2 class="nx-settings-section__title" id="settings-danger">Zona de cuidado</h2>
                        <p class="nx-settings-section__desc">Estas acciones afectan a todo el equipo.</p>
                    </div>

                    <div class="nx-settings-row">
                        <div class="nx-settings-row__main">
                            <span class="nx-settings-row__title">Salir del espacio</span>
                            <span class="nx-settings-row__desc">Dejarás de ver sus páginas y tareas. Un propietario puede volver a invitarte.</span>
                        </div>
                        <button class="nx-btn nx-btn--danger-ghost nx-btn--sm" type="button" data-coming-soon="Salir del espacio requiere el backend de membresías.">Salir del espacio</button>
                    </div>
                </section>

            <?php else: ?>
                <section class="nx-settings-section" aria-labelledby="settings-about">
                    <div class="nx-settings-section__head">
                        <h2 class="nx-settings-section__title" id="settings-about">Acerca de NEXO</h2>
                        <p class="nx-settings-section__desc">Un lugar para el material del salón, sin ruido de más.</p>
                    </div>

                    <div class="nx-about-card">
                        <span class="nx-about-card__mark" aria-hidden="true">N</span>
                        <h3>NEXO UNAC</h3>
                        <p>
                            Páginas compartidas, tareas con fecha y un tutor que responde con el material del curso.
                            NEXO nace en la Facultad de Ingeniería Industrial y de Sistemas para que estudiar no dependa de cinco pestañas abiertas.
                        </p>
                        <div class="nx-row nx-row--wrap" style="margin-top: 14px; gap: 8px;">
                            <span class="nx-chip"><?= nexo_icon('layers') ?>Biblioteca 1.0</span>
                            <span class="nx-chip"><?= nexo_icon('code') ?>CodeIgniter 4</span>
                            <span class="nx-chip"><?= nexo_icon('graduation-cap') ?>FIIS · UNAC</span>
                        </div>
                    </div>
                </section>

                <section class="nx-settings-section" aria-labelledby="settings-status">
                    <div class="nx-settings-section__head">
                        <h2 class="nx-settings-section__title" id="settings-status">Estado del sistema</h2>
                        <p class="nx-settings-section__desc">Lo que ya funciona y lo que todavía está en camino.</p>
                    </div>

                    <div class="nx-settings-row">
                        <div class="nx-settings-row__main">
                            <span class="nx-settings-row__title">Interfaz y navegación</span>
                            <span class="nx-settings-row__desc">Shell, editor, tablero, calendario y estados vacíos.</span>
                        </div>
                        <span class="nx-status"><span class="nx-dot nx-dot--success"></span>Listo</span>
                    </div>

                    <div class="nx-settings-row">
                        <div class="nx-settings-row__main">
                            <span class="nx-settings-row__title">API de páginas y bloques</span>
                            <span class="nx-settings-row__desc">El guardado real se conecta en la fase F3 del backend.</span>
                        </div>
                        <span class="nx-status"><span class="nx-dot nx-dot--gold"></span>Pendiente</span>
                    </div>

                    <div class="nx-settings-row">
                        <div class="nx-settings-row__main">
                            <span class="nx-settings-row__title">Tutor con contexto</span>
                            <span class="nx-settings-row__desc">La interfaz existe; el endpoint de chat aún no responde.</span>
                        </div>
                        <span class="nx-status"><span class="nx-dot nx-dot--gold"></span>Pendiente</span>
                    </div>

                    <div class="nx-settings-row">
                        <div class="nx-settings-row__main">
                            <span class="nx-settings-row__title">Radar de eventos</span>
                            <span class="nx-settings-row__desc">Las vistas de eventos las entrega el equipo de backend.</span>
                        </div>
                        <span class="nx-status"><span class="nx-dot nx-dot--gold"></span>Pendiente</span>
                    </div>
                </section>

                <section class="nx-settings-section" aria-labelledby="settings-shortcuts">
                    <div class="nx-settings-section__head">
                        <h2 class="nx-settings-section__title" id="settings-shortcuts">Atajos de teclado</h2>
                        <p class="nx-settings-section__desc">Para no soltar el teclado mientras escribes.</p>
                    </div>

                    <div class="nx-settings-row">
                        <div class="nx-settings-row__main">
                            <span class="nx-settings-row__title">Abrir búsqueda y comandos</span>
                            <span class="nx-settings-row__desc">Encuentra una página o ejecuta una acción.</span>
                        </div>
                        <span class="nx-kbd">⌘ K</span>
                    </div>
                    <div class="nx-settings-row">
                        <div class="nx-settings-row__main">
                            <span class="nx-settings-row__title">Abrir el tutor</span>
                            <span class="nx-settings-row__desc">Pregunta con el contexto de la página abierta.</span>
                        </div>
                        <span class="nx-kbd">⌘ ⇧ T</span>
                    </div>
                    <div class="nx-settings-row">
                        <div class="nx-settings-row__main">
                            <span class="nx-settings-row__title">Insertar un bloque</span>
                            <span class="nx-settings-row__desc">Escribe una barra en el editor.</span>
                        </div>
                        <span class="nx-kbd">/</span>
                    </div>
                    <div class="nx-settings-row">
                        <div class="nx-settings-row__main">
                            <span class="nx-settings-row__title">Guardar</span>
                            <span class="nx-settings-row__desc">Fuerza un guardado del documento.</span>
                        </div>
                        <span class="nx-kbd">⌘ S</span>
                    </div>
                </section>
            <?php endif ?>
        </div>
    </div>
</div>
