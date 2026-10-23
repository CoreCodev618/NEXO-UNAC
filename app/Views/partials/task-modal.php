<?php
/**
 * Diálogo para crear y editar tareas.
 *
 * @var array $members
 */
?>
<dialog class="nx-modal nx-modal--sm" id="nx-task-modal" aria-labelledby="nx-task-modal-title">
    <div class="nx-modal__panel">
        <form data-task-form>
            <div class="nx-modal__head">
                <div>
                    <h2 class="nx-modal__title" id="nx-task-modal-title" data-task-modal-title>Nueva tarea</h2>
                    <p class="nx-modal__desc">Define qué hay que hacer, cuándo y quién lo toma.</p>
                    <?php if (! empty($isPreview)): ?>
                        <p class="nx-hint">Vista demo: la tarea se guarda en este navegador, no en la base de datos.</p>
                    <?php endif ?>
                </div>
                <button class="nx-icon-btn" type="button" data-task-close aria-label="Cerrar">
                    <?= nexo_icon('x') ?>
                </button>
            </div>

            <div class="nx-modal__body">
                <div class="nx-task-form">
                    <input type="hidden" name="id" value="" data-task-id>
                    <input type="hidden" name="workspace_id" value="<?= (int) ($workspace['id'] ?? 0) ?>" data-task-workspace>
                    <input type="hidden" name="page_id" value="" data-task-page>

                    <div class="nx-field nx-field--full">
                        <label class="nx-label" for="task-title">Título</label>
                        <input class="nx-input" id="task-title" name="title" type="text" maxlength="180" placeholder="¿Qué hay que hacer?" required data-task-title>
                    </div>

                    <div class="nx-field">
                        <label class="nx-label" for="task-status">Estado</label>
                        <select class="nx-select" id="task-status" name="status" data-task-status-select>
                            <option value="todo">Por hacer</option>
                            <option value="doing">En curso</option>
                            <option value="done">Hecho</option>
                        </select>
                    </div>

                    <div class="nx-field">
                        <label class="nx-label" for="task-priority">Prioridad</label>
                        <select class="nx-select" id="task-priority" name="priority" data-task-priority>
                            <option value="baja">Baja</option>
                            <option value="media" selected>Media</option>
                            <option value="alta">Alta</option>
                        </select>
                    </div>

                    <div class="nx-field">
                        <label class="nx-label" for="task-due">Fecha límite</label>
                        <input class="nx-input" id="task-due" name="due_date" type="date" data-task-due>
                    </div>

                    <div class="nx-field">
                        <label class="nx-label" for="task-assignee">Asignar a</label>
                        <select class="nx-select" id="task-assignee" name="assigned_to" data-task-assignee>
                            <?php foreach ($members as $member): ?>
                                <option
                                    value="<?= (int) $member['id'] ?>"
                                    data-initials="<?= esc($member['initials'], 'attr') ?>"
                                    data-tone="<?= esc($member['tone'], 'attr') ?>"
                                    data-name="<?= esc($member['name'], 'attr') ?>"
                                ><?= esc($member['name']) ?></option>
                            <?php endforeach ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="nx-modal__foot nx-modal__foot--split">
                <button class="nx-btn nx-btn--danger-ghost" type="button" data-task-delete hidden>
                    <?= nexo_icon('trash') ?>
                    Eliminar
                </button>
                <span></span>
                <div class="nx-row" style="gap: 8px;">
                    <button class="nx-btn nx-btn--secondary" type="button" data-task-close>Cancelar</button>
                    <button class="nx-btn nx-btn--primary" type="submit" data-task-save>
                        <?= nexo_icon('check') ?>
                        Guardar tarea
                    </button>
                </div>
            </div>
        </form>
    </div>
</dialog>
