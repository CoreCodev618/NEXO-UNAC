import { api, ApiError } from '../core/api.js';
import {
  appUrl,
  escapeHtml,
  formatDueDate,
  isOverdue,
  qs,
  qsa,
  setHidden,
} from '../core/dom.js';
import { icon } from '../core/icons.js';
import { toast } from '../core/toast.js';

let board = null;
let dragged = null;

function statusLabel(status) {
  return { todo: 'Por hacer', doing: 'En curso', done: 'Hecho' }[status] || 'Sin estado';
}

function priorityLabel(priority) {
  return { alta: 'Alta', media: 'Media', baja: 'Baja' }[priority] || 'Media';
}

function listFor(status) {
  return qs(`[data-board-list][data-status="${status}"]`, board);
}

function columnFor(status) {
  return qs(`.nx-board__col[data-status="${status}"]`, board);
}

function cardFor(id) {
  return qsa('[data-task-card]', board).find((card) => String(card.dataset.taskId) === String(id)) || null;
}

function refreshColumn(status) {
  const column = columnFor(status);
  if (!column) return;
  const cards = qsa('[data-task-card]', column);
  const visible = cards.filter((card) => !card.hasAttribute('hidden'));
  const count = qs('[data-board-count]', column);
  if (count) count.textContent = String(visible.length);

  const empty = qs('[data-board-empty]', column);
  if (empty) setHidden(empty, visible.length > 0);
}

function refreshAll() {
  qsa('.nx-board__col', board).forEach((column) => refreshColumn(column.dataset.status));
}

function applyFilters() {
  const priority = qs('[data-board-filter-priority]', board)?.value || '';
  const assignee = qs('[data-board-filter-assignee]', board)?.value || '';

  qsa('[data-task-card]', board).forEach((card) => {
    const matchPriority = !priority || card.dataset.taskPriority === priority;
    const matchAssignee = !assignee || String(card.dataset.taskAssignee) === assignee;
    setHidden(card, !(matchPriority && matchAssignee));
  });

  refreshAll();
}

function setCardData(card, task) {
  card.dataset.taskId = String(task.id);
  card.dataset.taskStatus = task.status;
  card.dataset.taskPriority = task.priority;
  card.dataset.taskAssignee = String(task.assigned_to || '');
  card.dataset.taskTitle = task.title;
  card.dataset.taskDue = task.due_date || '';
  card.dataset.taskPage = task.page_id ? String(task.page_id) : '';
  card.dataset.taskPageTitle = task.page_title || '';
  card.dataset.taskAssigneeName = task.assignee_name || '';
  card.dataset.taskInitials = task.assignee_initials || '';
  card.dataset.taskTone = task.assignee_tone || 'paper';
  card.setAttribute('aria-label', task.title);
}

function dueMarkup(task) {
  const overdue = task.due_date && task.status !== 'done' && isOverdue(task.due_date);
  return `${icon('clock')}<span class="${overdue ? 'is-overdue' : ''}">${escapeHtml(formatDueDate(task.due_date))}</span>`;
}

function cardMarkup(task) {
  const pageHref = task.page_id ? appUrl(`pages/${task.page_id}`) : null;
  const pageTitle = task.page_title || 'Sin página';

  return `
    <div class="nx-task__head">
      <button class="nx-task__title" type="button" data-task-edit="${escapeHtml(task.id)}">${escapeHtml(task.title)}</button>
      <div class="nx-dropdown">
        <button class="nx-icon-btn nx-task__menu-btn" type="button" data-menu-trigger="task-menu-${escapeHtml(task.id)}" aria-expanded="false" aria-label="Opciones de la tarea">${icon('more-horizontal')}</button>
        <div class="nx-menu nx-menu--right" id="task-menu-${escapeHtml(task.id)}" hidden>
          <button class="nx-menu__item" type="button" data-task-edit="${escapeHtml(task.id)}">${icon('pencil')}<span>Editar tarea</span></button>
          ${pageHref ? `<a class="nx-menu__item" href="${escapeHtml(pageHref)}">${icon('file-text')}<span>Abrir página</span></a>` : ''}
          <div class="nx-menu__separator"></div>
          <button class="nx-menu__item nx-menu__item--danger" type="button" data-task-delete="${escapeHtml(task.id)}">${icon('trash')}<span>Eliminar</span></button>
        </div>
      </div>
    </div>
    <div class="nx-task__meta">
      <span data-task-due-label>${dueMarkup(task)}</span>
      <span class="nx-priority nx-priority--${escapeHtml(task.priority)}">${escapeHtml(priorityLabel(task.priority))}</span>
    </div>
    <div class="nx-task__foot">
      ${pageHref
        ? `<a class="nx-task__page" href="${escapeHtml(pageHref)}">${icon('file-text')}<span>${escapeHtml(pageTitle)}</span></a>`
        : `<span class="nx-task__page nx-muted">${icon('file-text')}<span>Sin página</span></span>`}
      <span class="nx-task__assignee">
        <span class="nx-avatar nx-avatar--sm nx-avatar--${escapeHtml(task.assignee_tone || 'paper')}" title="${escapeHtml(task.assignee_name || 'Sin asignar')}" aria-label="${escapeHtml(task.assignee_name || 'Sin asignar')}">${escapeHtml(task.assignee_initials || '—')}</span>
      </span>
    </div>
  `;
}

function createCard(task) {
  const card = document.createElement('article');
  card.className = 'nx-task';
  card.dataset.taskCard = '';
  setCardData(card, task);
  card.innerHTML = cardMarkup(task);
  return card;
}

function insertCard(task) {
  const list = listFor(task.status) || listFor('todo');
  if (!list) return;
  list.appendChild(createCard(task));
  applyFilters();
}

function updateCard(task) {
  const card = cardFor(task.id);
  if (!card) {
    insertCard(task);
    return;
  }

  const oldStatus = card.dataset.taskStatus;
  setCardData(card, task);

  const title = qs('.nx-task__title', card);
  if (title) title.textContent = task.title;

  const due = qs('[data-task-due-label]', card);
  if (due) due.innerHTML = dueMarkup(task);

  const priority = qs('.nx-priority', card);
  if (priority) {
    priority.className = `nx-priority nx-priority--${task.priority}`;
    priority.textContent = priorityLabel(task.priority);
  }

  const page = qs('.nx-task__page', card);
  if (page) {
    if (task.page_id) {
      const anchor = document.createElement('a');
      anchor.className = 'nx-task__page';
      anchor.href = appUrl(`pages/${task.page_id}`);
      anchor.innerHTML = `${icon('file-text')}<span>${escapeHtml(task.page_title || 'Abrir página')}</span>`;
      page.replaceWith(anchor);
    } else {
      page.outerHTML = `<span class="nx-task__page nx-muted">${icon('file-text')}<span>Sin página</span></span>`;
    }
  }

  const avatar = qs('.nx-task__assignee .nx-avatar', card);
  if (avatar) {
    avatar.className = `nx-avatar nx-avatar--sm nx-avatar--${task.assignee_tone || 'paper'}`;
    avatar.textContent = task.assignee_initials || '—';
    avatar.title = task.assignee_name || 'Sin asignar';
    avatar.setAttribute('aria-label', task.assignee_name || 'Sin asignar');
  }

  if (oldStatus !== task.status) {
    listFor(task.status)?.appendChild(card);
  }

  applyFilters();
}

function removeCard(id) {
  const card = cardFor(id);
  if (!card) return;
  card.remove();
  refreshAll();
}

function moveTask(task, status, orderIndex) {
  if (document.body.dataset.preview === '1') {
    toast.success(`La tarea se movió a ${statusLabel(status)}. El cambio es local.`, 'Tarea movida');
    return Promise.resolve();
  }

  if (String(task.id).startsWith('new-')) {
    toast.info('La tarea todavía no existe en el servidor. Guarda primero sus datos.', 'Tarea local');
    return Promise.resolve();
  }

  return api.tasks
    .move(task.id, { status, order_index: orderIndex })
    .then(() => toast.success(`La tarea se movió a ${statusLabel(status)}.`, 'Tarea movida'))
    .catch((error) => {
      throw error;
    });
}

function initSortable() {
  if (!window.Sortable || board?.dataset.readonly === '1') return;

  qsa('[data-board-list]', board).forEach((list) => {
    new window.Sortable(list, {
      group: 'nx-tasks',
      animation: 150,
      draggable: '.nx-task',
      ghostClass: 'sortable-ghost',
      dragClass: 'sortable-drag',
      onStart(event) {
        const from = event.from;
        dragged = {
          oldStatus: from.dataset.status,
          oldIndex: qsa('.nx-task', from).indexOf(event.item),
          item: event.item,
        };
      },
      onEnd(event) {
        const card = event.item;
        const newStatus = event.to.dataset.status;
        const oldStatus = dragged?.oldStatus || card.dataset.taskStatus;
        const orderIndex = qsa('.nx-task', event.to).indexOf(card);

        card.dataset.taskStatus = newStatus;
        refreshAll();

        if (newStatus === oldStatus) {
          dragged = null;
          return;
        }

        const task = {
          id: card.dataset.taskId,
          title: card.dataset.taskTitle || '',
        };

        moveTask(task, newStatus, orderIndex).catch((error) => {
          if (dragged?.item) {
            const cards = qsa('.nx-task', event.from);
            const reference = cards[dragged.oldIndex] || null;
            event.from.insertBefore(card, reference);
            card.dataset.taskStatus = oldStatus;
            refreshAll();
          }

          if (error instanceof ApiError && error.unavailable) {
            toast.warning(
              'El backend de tareas aún no está disponible. La tarjeta volvió a su columna.',
              'No se pudo mover',
            );
          } else {
            toast.error(error.message || 'No se pudo mover la tarea.');
          }
        }).finally(() => {
          dragged = null;
        });
      },
    });
  });
}

export function initKanban() {
  board = qs('[data-board]');
  if (!board) return;

  initSortable();

  qsa('[data-board-filter-priority], [data-board-filter-assignee]', board).forEach((select) => {
    select.addEventListener('change', applyFilters);
  });

  document.addEventListener('nx:task-saved', (event) => {
    const { task, mode } = event.detail || {};
    if (!task) return;
    if (mode === 'edit') updateCard(task);
    else insertCard(task);
  });

  document.addEventListener('nx:task-deleted', (event) => {
    if (event.detail?.id) removeCard(event.detail.id);
  });

  applyFilters();
}
