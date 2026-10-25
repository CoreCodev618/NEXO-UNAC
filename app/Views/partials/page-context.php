<?php
/**
 * Panel contextual de una página: comentarios, archivos y detalles.
 *
 * @var array  $page
 * @var array  $user
 * @var string $prefix
 */
$comments    = $page['comments'] ?? [];
$attachments = $page['attachments'] ?? [];
$canEdit     = (bool) ($page['can_edit'] ?? true);
?>
<aside class="nx-context" id="nx-context" aria-labelledby="nx-context-title" aria-hidden="true" inert>
    <div class="nx-context__head">
        <span class="nx-context__title" id="nx-context-title">Contexto</span>
        <button class="nx-icon-btn" type="button" data-context-close aria-label="Cerrar contexto">
            <?= nexo_icon('x') ?>
        </button>
    </div>

    <div class="nx-context-tabs" role="tablist" aria-label="Contexto de la página">
        <button class="nx-tab is-active" type="button" role="tab" aria-selected="true" aria-controls="nx-context-comments" id="nx-context-tab-comments" data-context-tab="comments">
            Comentarios
            <span class="nx-badge"><?= count($comments) ?></span>
        </button>
        <button class="nx-tab" type="button" role="tab" aria-selected="false" aria-controls="nx-context-files" id="nx-context-tab-files" data-context-tab="files">
            Archivos
            <span class="nx-badge"><?= count($attachments) ?></span>
        </button>
        <button class="nx-tab" type="button" role="tab" aria-selected="false" aria-controls="nx-context-details" id="nx-context-tab-details" data-context-tab="details">
            Detalles
        </button>
    </div>

    <div class="nx-context__body nx-scroll">
        <section id="nx-context-comments" role="tabpanel" aria-labelledby="nx-context-tab-comments" data-context-panel="comments">
            <?php if ($comments === []): ?>
                <div class="nx-empty nx-empty--compact">
                    <?= nexo_illustration('chat', 'nx-empty__art') ?>
                    <p class="nx-empty__title">Todavía no hay comentarios</p>
                    <p class="nx-empty__text">Deja una nota para tu equipo o pregúntale algo al tutor.</p>
                </div>
            <?php else: ?>
                <div class="nx-comments">
                    <?php foreach ($comments as $comment): ?>
                        <article class="nx-comment">
                            <span class="nx-avatar nx-avatar--sm nx-avatar--<?= esc($comment['tone'] ?? 'paper', 'attr') ?>" aria-hidden="true">
                                <?= esc($comment['initials'] ?? nexo_initials($comment['author'] ?? '')) ?>
                            </span>
                            <div class="nx-comment__body">
                                <div class="nx-comment__head">
                                    <span class="nx-comment__name"><?= esc($comment['author'] ?? 'Usuario') ?></span>
                                    <span class="nx-comment__time"><?= esc(nexo_datetime($comment['created_at'] ?? null)) ?></span>
                                </div>
                                <p class="nx-comment__text"><?= esc($comment['content'] ?? '') ?></p>
                                <div class="nx-comment__actions">
                                    <button type="button" data-comment-reply>Responder</button>
                                    <?php if (! empty($comment['mine']) || ! empty($page['can_manage'])): ?>
                                        <button type="button" data-comment-delete="<?= (int) $comment['id'] ?>">Eliminar</button>
                                    <?php endif ?>
                                </div>
                            </div>
                        </article>
                    <?php endforeach ?>
                </div>
            <?php endif ?>

            <form class="nx-comment-form" data-comment-form>
                <span class="nx-avatar nx-avatar--sm nx-avatar--<?= esc($user['tone'] ?? 'wine', 'attr') ?>" aria-hidden="true"><?= esc($user['initials'] ?? '?') ?></span>
                <div class="nx-comment-form__body" style="flex:1; min-width:0;">
                    <label class="nx-sr-only" for="nx-comment-input">Escribe un comentario</label>
                    <textarea class="nx-textarea" id="nx-comment-input" rows="2" placeholder="Escribe un comentario…" data-comment-input></textarea>
                    <div class="nx-comment-form__foot">
                        <span class="nx-hint">Solo para miembros del espacio</span>
                        <button class="nx-btn nx-btn--primary nx-btn--sm" type="submit" data-comment-submit>
                            <?= nexo_icon('send') ?>
                            Comentar
                        </button>
                    </div>
                </div>
            </form>
        </section>

        <section id="nx-context-files" role="tabpanel" aria-labelledby="nx-context-tab-files" data-context-panel="files" hidden>
            <?php if ($canEdit): ?>
                <label class="nx-dropzone" data-upload-zone>
                    <?= nexo_icon('upload') ?>
                    <strong>Adjuntar un archivo</strong>
                    <span>PDF, DOCX, PPTX, JPG, PNG o ZIP · máx. 10 MB</span>
                    <input
                        class="nx-sr-only"
                        type="file"
                        accept=".pdf,.docx,.pptx,.jpg,.jpeg,.png,.zip"
                        data-upload-input
                    >
                </label>
                <p class="nx-hint" style="margin-top: 8px;">Los archivos se guardan en el espacio y se pueden citar desde el tutor.</p>
            <?php else: ?>
                <div class="nx-context-note">Solo lectura: un editor del espacio puede adjuntar archivos.</div>
            <?php endif ?>

            <div class="nx-attachment-list" style="margin-top: 16px;" data-attachment-list>
                <?php foreach ($attachments as $file): ?>
                    <?php
                    $name = (string) ($file['file_name'] ?? basename((string) ($file['file_path'] ?? 'archivo')));
                    $ext  = nexo_attachment_ext($name, $file['file_type'] ?? null);
                    $url  = (string) ($file['download_url'] ?? '#');
                    ?>
                    <a class="nx-attachment" href="<?= esc($url, 'attr') ?>" data-attachment-download="<?= esc($name, 'attr') ?>" data-attachment-url="<?= esc($url, 'attr') ?>">
                        <span class="nx-attachment__icon nx-attachment__icon--<?= esc(nexo_attachment_tone($ext), 'attr') ?>" aria-hidden="true"><?= esc($ext) ?></span>
                        <span class="nx-attachment__main">
                            <span class="nx-attachment__name"><?= esc($name) ?></span>
                            <span class="nx-attachment__meta"><?= esc(nexo_bytes($file['file_size'] ?? null)) ?> · <?= esc(nexo_date($file['created_at'] ?? null)) ?></span>
                        </span>
                        <span class="nx-attachment__action"><?= nexo_icon('download') ?></span>
                    </a>
                <?php endforeach ?>

                <?php if ($attachments === []): ?>
                    <div class="nx-context-note">Aún no hay archivos adjuntos en esta página.</div>
                <?php endif ?>
            </div>
        </section>

        <section id="nx-context-details" role="tabpanel" aria-labelledby="nx-context-tab-details" data-context-panel="details" hidden>
            <dl class="nx-meta-grid">
                <div>
                    <dt>Creada</dt>
                    <dd><?= esc(nexo_date($page['created_at'] ?? null, 'long')) ?></dd>
                </div>
                <div>
                    <dt>Última edición</dt>
                    <dd><?= esc(nexo_datetime($page['updated_at'] ?? null)) ?></dd>
                </div>
                <div>
                    <dt>Autor</dt>
                    <dd><?= esc($page['author'] ?? 'Sin autor') ?></dd>
                </div>
                <div>
                    <dt>Lectura</dt>
                    <dd><?= (int) ($page['read_time'] ?? 1) ?> min · <?= (int) ($page['word_count'] ?? 0) ?> palabras</dd>
                </div>
            </dl>

            <div class="nx-aside-section">
                <div class="nx-aside-title">Etiquetas</div>
                <div class="nx-doc-tags" data-page-tags>
                    <?php foreach ($page['tags'] ?? [] as $tag): ?>
                        <?php $tagId = (int) ($tag['id'] ?? 0); ?>
                        <span class="nx-chip" data-page-tag="<?= $tagId ?>">
                            <?= nexo_icon('tag') ?><?= esc($tag['name'] ?? $tag) ?>
                            <?php if ($canEdit && $tagId > 0): ?>
                                <button class="nx-chip__remove" type="button" data-tag-remove="<?= $tagId ?>" aria-label="Quitar etiqueta <?= esc($tag['name'] ?? $tag, 'attr') ?>">
                                    <?= nexo_icon('x') ?>
                                </button>
                            <?php endif ?>
                        </span>
                    <?php endforeach ?>
                    <?php if (($page['tags'] ?? []) === []): ?>
                        <span class="nx-hint" data-tags-empty>Sin etiquetas todavía.</span>
                    <?php endif ?>
                </div>
                <?php if ($canEdit): ?>
                    <form class="nx-tag-form" data-tag-form>
                        <label class="nx-sr-only" for="nx-tag-input">Nueva etiqueta</label>
                        <input class="nx-input nx-input--sm" id="nx-tag-input" type="text" maxlength="80" placeholder="Añadir etiqueta" data-tag-input>
                        <button class="nx-btn nx-btn--secondary nx-btn--sm" type="submit"><?= nexo_icon('plus') ?> Añadir</button>
                    </form>
                <?php endif ?>
            </div>

            <div class="nx-aside-section">
                <div class="nx-aside-title">Miembros del espacio</div>
                <div class="nx-member-list">
                    <?php foreach (($page['workspace']['members'] ?? []) as $member): ?>
                        <div class="nx-member-row">
                            <span class="nx-avatar nx-avatar--sm nx-avatar--<?= esc($member['tone'], 'attr') ?>" aria-hidden="true"><?= esc($member['initials']) ?></span>
                            <span class="nx-member-row__main">
                                <span class="nx-member-row__name"><?= esc($member['name']) ?></span>
                                <span class="nx-member-row__role"><?= esc(nexo_role_label($member['role'])) ?></span>
                            </span>
                        </div>
                    <?php endforeach ?>
                </div>
            </div>
        </section>
    </div>
</aside>
