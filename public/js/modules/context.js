import { api, ApiError } from '../core/api.js';
import {
  escapeHtml,
  focusables,
  lockScroll,
  qs,
  qsa,
  setHidden,
  trapFocus,
  unlockScroll,
} from '../core/dom.js';
import { icon } from '../core/icons.js';
import { toast } from '../core/toast.js';

let context = null;
let lastFocus = null;

function currentPageId() {
  return Number(document.body.dataset.pageId || 0);
}

function isPreview() {
  return document.body.dataset.preview === '1';
}

function canManage() {
  return document.querySelector('[data-editor]')?.dataset.canManage === '1';
}

function setExpanded(open) {
  qsa('[data-context-toggle]').forEach((button) => button.setAttribute('aria-expanded', open ? 'true' : 'false'));
}

export function openContext() {
  if (!context) return;
  lastFocus = document.activeElement;
  context.classList.add('is-open');
  context.removeAttribute('inert');
  context.setAttribute('aria-hidden', 'false');
  qs('[data-context-backdrop]')?.classList.add('is-visible');
  setExpanded(true);
  lockScroll();
  window.requestAnimationFrame(() => focusables(context)[0]?.focus());
}

export function closeContext() {
  if (!context) return;
  context.classList.remove('is-open');
  context.setAttribute('aria-hidden', 'true');
  context.setAttribute('inert', '');
  qs('[data-context-backdrop]')?.classList.remove('is-visible');
  setExpanded(false);
  unlockScroll();

  if (lastFocus instanceof HTMLElement && lastFocus.isConnected) {
    lastFocus.focus();
  }
}

function activateTab(name) {
  qsa('[data-context-tab]', context).forEach((button) => {
    const active = button.dataset.contextTab === name;
    button.classList.toggle('is-active', active);
    button.setAttribute('aria-selected', active ? 'true' : 'false');
  });

  qsa('[data-context-panel]', context).forEach((panel) => {
    setHidden(panel, panel.dataset.contextPanel !== name);
  });
}

function commentCount(delta = 0) {
  const badge = qs('[data-context-tab="comments"] .nx-badge', context);
  if (!badge) return;
  const current = Number(badge.textContent || 0);
  badge.textContent = String(Math.max(0, current + delta));
}

function addCommentToList(comment) {
  const section = qs('[data-context-panel="comments"]', context);
  if (!section) return;

  section.querySelector('.nx-empty')?.remove();
  let list = qs('.nx-comments', section);

  if (!list) {
    list = document.createElement('div');
    list.className = 'nx-comments';
    section.prepend(list);
  }

  const name = comment.author || document.body.dataset.userName || 'Tú';
  const initials = name
    .trim()
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0])
    .join('')
    .toUpperCase();

  const article = document.createElement('article');
  article.className = 'nx-comment';
  article.innerHTML = `
    <span class="nx-avatar nx-avatar--sm nx-avatar--${escapeHtml(comment.tone || 'wine')}" aria-hidden="true">${escapeHtml(comment.initials || initials || '?')}</span>
    <div class="nx-comment__body">
      <div class="nx-comment__head">
        <span class="nx-comment__name">${escapeHtml(name)}</span>
        <span class="nx-comment__time">Ahora</span>
      </div>
      <p class="nx-comment__text">${escapeHtml(comment.content || '')}</p>
      <div class="nx-comment__actions">
        <button type="button" data-comment-reply>Responder</button>
        ${comment.id && (comment.mine || canManage()) ? `<button type="button" data-comment-delete="${Number(comment.id)}">Eliminar</button>` : ''}
      </div>
    </div>
  `;
  list.appendChild(article);
  article.scrollIntoView({ block: 'nearest' });
}

function submitComment(form) {
  const input = qs('[data-comment-input]', form);
  const button = qs('[data-comment-submit]', form);
  const content = String(input?.value || '').trim();
  if (!input || !content) return;

  const pageId = currentPageId();
  const preview = isPreview();

  if (preview || !pageId) {
    addCommentToList({ content });
    input.value = '';
    commentCount(1);
    toast.success('El comentario quedó en la vista local. No se guardó en la base de datos.', 'Comentario local');
    return;
  }

  button?.classList.add('is-loading');
  if (button) button.disabled = true;

  api.comments
    .create({ page_id: pageId, content })
    .then((comment) => {
      addCommentToList(normalizeComment(comment, content));
      input.value = '';
      commentCount(1);
      toast.success('Tu comentario se publicó en la página.', 'Comentario guardado');
    })
    .catch((error) => {
      if (error instanceof ApiError && error.unavailable) {
        toast.warning(
          'El backend de comentarios aún no está disponible. El comentario no se guardó.',
          'Sin conexión',
        );
      } else {
        toast.error(error.message || 'No se pudo publicar el comentario.');
      }
    })
    .finally(() => {
      button?.classList.remove('is-loading');
      if (button) button.disabled = false;
    });
}

function normalizeComment(comment, fallbackContent) {
  return {
    content: comment?.content || fallbackContent,
    author:
      comment?.author ||
      comment?.user?.name ||
      document.body.dataset.userName ||
      'Tú',
    tone: comment?.tone || 'wine',
    initials: comment?.initials || '',
    id: comment?.id || null,
    mine: comment?.mine === true,
  };
}

function appendTagChip(tag) {
  const container = qs('[data-page-tags]', context);
  if (!container || !tag?.id) return;

  if (qs(`[data-page-tag="${Number(tag.id)}"]`, container)) return;
  qs('[data-tags-empty]', container)?.remove();

  const chip = document.createElement('span');
  chip.className = 'nx-chip';
  chip.dataset.pageTag = String(Number(tag.id));
  chip.innerHTML = `${icon('tag')}<span>${escapeHtml(tag.name || 'Etiqueta')}</span>
    <button class="nx-chip__remove" type="button" data-tag-remove="${Number(tag.id)}" aria-label="Quitar etiqueta ${escapeHtml(tag.name || '')}">${icon('x')}</button>`;
  container.appendChild(chip);
}

function submitTag(form) {
  const input = qs('[data-tag-input]', form);
  const button = qs('button[type="submit"]', form);
  const name = String(input?.value || '').trim();
  const pageId = currentPageId();

  if (!input || !name) return;

  if (isPreview() || !pageId) {
    input.value = '';
    toast.info('Etiqueta agregada solo a la vista de diseño; no se guardó.', 'Vista demo');
    return;
  }

  button?.classList.add('is-loading');
  if (button) button.disabled = true;

  api.tags
    .attach({ page_id: pageId, name })
    .then((tag) => {
      appendTagChip(tag);
      input.value = '';
      toast.success('Etiqueta añadida a la página.', 'Etiqueta guardada');
    })
    .catch((error) => toast.error(error.message || 'No se pudo guardar la etiqueta.'))
    .finally(() => {
      button?.classList.remove('is-loading');
      if (button) button.disabled = false;
    });
}

function deleteComment(button) {
  const id = Number(button.dataset.commentDelete || 0);
  const pageId = currentPageId();
  if (!id || !pageId) return;

  button.disabled = true;
  api.comments
    .remove(id)
    .then(() => {
      button.closest('.nx-comment')?.remove();
      commentCount(-1);
      toast.success('El comentario se eliminó.', 'Comentario eliminado');
    })
    .catch((error) => {
      button.disabled = false;
      toast.error(error.message || 'No se pudo eliminar el comentario.');
    });
}

function removeTag(button) {
  const id = Number(button.dataset.tagRemove || 0);
  const pageId = currentPageId();
  const chip = button.closest('[data-page-tag]');
  if (!id || !pageId) return;

  if (isPreview()) {
    chip?.remove();
    toast.info('Etiqueta quitada solo de la vista de diseño.', 'Vista demo');
    return;
  }

  button.disabled = true;
  api.tags
    .detach(id, pageId)
    .then(() => {
      chip?.remove();
      toast.success('Etiqueta quitada.', 'Etiqueta actualizada');
    })
    .catch((error) => {
      button.disabled = false;
      toast.error(error.message || 'No se pudo quitar la etiqueta.');
    });
}

export function initContext() {
  context = qs('#nx-context');
  if (!context) return;

  qsa('[data-context-toggle]').forEach((button) => {
    button.addEventListener('click', (event) => {
      event.preventDefault();
      if (context.classList.contains('is-open')) closeContext();
      else openContext();
    });
  });

  qsa('[data-context-close]').forEach((button) => button.addEventListener('click', closeContext));
  qs('[data-context-backdrop]')?.addEventListener('click', closeContext);

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && context.classList.contains('is-open')) {
      event.preventDefault();
      closeContext();
      return;
    }

    if (event.key === 'Tab' && context.classList.contains('is-open')) {
      trapFocus(context, event);
    }
  });

  qsa('[data-context-tab]', context).forEach((button) => {
    button.addEventListener('click', () => activateTab(button.dataset.contextTab));
  });

  qs('[data-comment-form]', context)?.addEventListener('submit', (event) => {
    event.preventDefault();
    submitComment(event.currentTarget);
  });

  qs('[data-tag-form]', context)?.addEventListener('submit', (event) => {
    event.preventDefault();
    submitTag(event.currentTarget);
  });

  context.addEventListener('click', (event) => {
    const reply = event.target.closest('[data-comment-reply]');
    if (reply) {
      activateTab('comments');
      qs('[data-comment-input]', context)?.focus();
    }

    const removeComment = event.target.closest('[data-comment-delete]');
    if (removeComment) {
      deleteComment(removeComment);
    }

    const removeTagButton = event.target.closest('[data-tag-remove]');
    if (removeTagButton) {
      removeTag(removeTagButton);
    }

    const download = event.target.closest('[data-attachment-download]');
    if (download) {
      const url = download.dataset.attachmentUrl || '';
      if (url && url !== '#') {
        window.location.href = url;
      } else {
        event.preventDefault();
        toast.warning('Este adjunto no tiene una ruta de descarga disponible.', 'Descarga no disponible');
      }
    }
  });

  qsa('[data-context-tab]', context).forEach((button) => {
    button.addEventListener('keydown', (event) => {
      if (event.key !== 'ArrowRight' && event.key !== 'ArrowLeft') return;
      const tabs = qsa('[data-context-tab]', context);
      const index = tabs.indexOf(button);
      const next = event.key === 'ArrowRight' ? (index + 1) % tabs.length : (index - 1 + tabs.length) % tabs.length;
      event.preventDefault();
      tabs[next].focus();
      activateTab(tabs[next].dataset.contextTab);
    });
  });
}
