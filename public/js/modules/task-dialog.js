import { api, ApiError } from '../core/api.js';
import {
  dispatch,
  formatDueDate,
  isOverdue,
  qs,
  qsa,
  setHidden,
} from '../core/dom.js';
import { confirmDialog, openModal } from '../core/modal.js';
import { toast } from '../core/toast.js';

let modal = null;
let mode = 'create';
let editingId = null;

function field(name) {
  return qs(`[data-task-${name}]`, modal);
}

function form() {
  return qs('[data-task-form]', modal);
}

function findCard(id) {
  return qsa('[data-task-card]').find((card) => String(card.dataset.taskId) === String(id)) || null;
}

function selectedAssignee() {
  const select = field('assignee');
  const option = select?.selectedOptions?.[0];
  return {
    id: Number(option?.value || 0),
    name: option?.dataset.name || option?.textContent?.trim() || 'Sin asignar',
    initials: option?.dataset.initials || '—',
    tone: option?.dataset.tone || 'paper',
  };
}

function populate(task) {
  if (!modal) return;
  field('title').value = task.title || '';
  field('status-select').value = task.status || 'todo';
  field('priority').value = task.priority || 'media';
  field('due').value = task.due_date || '';
  field('assignee').value = String(task.assigned_to || field('assignee').options[0]?.value || '');
  field('page').value = task.page_id || '';
  field('id').value = task.id || '';
  field('workspace') && (field('workspace').value = task.workspace_id || field('workspace').value);
}

function openNew({ status = 'todo', due = '' } = {}) {
  if (!modal) return;
  mode = 'create';
  editingId = null;
  form().reset();

  field('id').value = '';
  field('page').value = '';
  field('status-select').value = status;
  field('priority').value = 'media';
  field('due').value = due;
  field('title').value = '';
  setHidden(field('delete'), true);

  qs('[data-task-modal-title]', modal).textContent = 'Nueva tarea';
  openModal(modal);
  window.requestAnimationFrame(() => field('title')?.focus());
}

function openEdit(task) {
  if (!modal || !task) return;
  mode = 'edit';
  editingId = task.id;
  populate(task);
  setHidden(field('delete'), false);
  qs('[data-task-modal-title]', modal).textContent = 'Editar tarea';
  openModal(modal);
  window.requestAnimationFrame(() => field('title')?.focus());
}

function cardToTask(card) {
  return {
    id: card.dataset.taskId,
    title: card.dataset.taskTitle || '',
    status: card.dataset.taskStatus || 'todo',
    priority: card.dataset.taskPriority || 'media',
    due_date: card.dataset.taskDue || '',
    assigned_to: Number(card.dataset.taskAssignee || 0),
    page_id: card.dataset.taskPage ? Number(card.dataset.taskPage) : null,
    workspace_id: Number(card.dataset.taskWorkspace || document.body.dataset.workspaceId || 0),
  };
}

function taskFromForm() {
  const assignee = selectedAssignee();
  const status = field('status-select').value;
  const due = field('due').value || null;
  const existingCard = editingId ? findCard(editingId) : null;

  return {
    id: mode === 'edit' ? editingId : `new-${Date.now()}`,
    workspace_id: Number(field('workspace').value || document.body.dataset.workspaceId || 0),
    page_id: field('page').value ? Number(field('page').value) : null,
    title: field('title').value.trim(),
    status,
    priority: field('priority').value,
    due_date: due,
    assigned_to: assignee.id,
    assignee_name: assignee.name,
    assignee_initials: assignee.initials,
    assignee_tone: assignee.tone,
    page_title: existingCard?.dataset.taskPageTitle || null,
    due_label: formatDueDate(due),
    is_overdue: Boolean(due && status !== 'done' && isOverdue(due)),
  };
}

function close() {
  if (modal?.open) modal.close();
}

function finishSave(task) {
  dispatch(modal, 'nx:task-saved', { task, mode });
  close();
  toast.success(
    document.body.dataset.preview === '1'
      ? 'La tarea quedó en la vista local y no se guardó en la base de datos.'
      : 'La tarea se guardó en el espacio.',
    mode === 'edit' ? 'Tarea actualizada' : 'Tarea creada',
  );
}

function save() {
  const task = taskFromForm();
  if (!task.title) {
    field('title')?.focus();
    return;
  }

  const saveButton = qs('[data-task-save]', modal);
  const preview = document.body.dataset.preview === '1';

  if (preview) {
    finishSave(task);
    return;
  }

  saveButton?.classList.add('is-loading');
  if (saveButton) saveButton.disabled = true;

  const payload = {
    workspace_id: task.workspace_id,
    page_id: task.page_id,
    title: task.title,
    status: task.status,
    priority: task.priority,
    due_date: task.due_date,
    assigned_to: task.assigned_to,
  };

  const request =
    mode === 'edit' && !String(editingId).startsWith('new-')
      ? api.tasks.update(editingId, payload)
      : api.tasks.create(payload);

  request
    .then((saved) => {
      if (saved?.id) task.id = saved.id;
      finishSave(task);
    })
    .catch((error) => {
      if (error instanceof ApiError && error.unavailable) {
        toast.warning(
          'El backend de tareas aún no está disponible. La tarea no se guardó.',
          'Sin conexión',
        );
      } else {
        toast.error(error.message || 'No se pudo guardar la tarea.');
      }
    })
    .finally(() => {
      saveButton?.classList.remove('is-loading');
      if (saveButton) saveButton.disabled = false;
    });
}

async function deleteTask(task, fromModal = false) {
  if (!task) return;

  const confirmed = await confirmDialog({
    title: 'Eliminar la tarea',
    message: `«${task.title}» se quitará del tablero y del calendario. Esta acción no se puede deshacer.`,
    confirmLabel: 'Eliminar tarea',
    tone: 'danger',
  });

  if (!confirmed) return;

  const preview = document.body.dataset.preview === '1';

  if (preview) {
    dispatch(modal, 'nx:task-deleted', { id: task.id });
    if (fromModal) close();
    toast.success('La tarea se eliminó de la vista local.', 'Tarea eliminada');
    return;
  }

  try {
    if (!String(task.id).startsWith('new-')) {
      await api.tasks.remove(task.id);
    }
    dispatch(modal, 'nx:task-deleted', { id: task.id });
    if (fromModal) close();
    toast.success('La tarea se eliminó del espacio.', 'Tarea eliminada');
  } catch (error) {
    if (error instanceof ApiError && error.unavailable) {
      toast.warning('El backend de tareas aún no está disponible. La tarea no se eliminó.', 'Sin conexión');
    } else {
      toast.error(error.message || 'No se pudo eliminar la tarea.');
    }
  }
}

export function initTaskDialog() {
  modal = qs('#nx-task-modal');
  if (!modal) return;

  document.addEventListener('click', (event) => {
    const newButton = event.target.closest('[data-task-new]');
    if (newButton) {
      event.preventDefault();
      openNew({
        status: newButton.dataset.taskStatus || 'todo',
        due: newButton.dataset.taskDate || '',
      });
      return;
    }

    const editButton = event.target.closest('[data-task-edit]');
    if (editButton) {
      event.preventDefault();
      const card = findCard(editButton.dataset.taskEdit);
      if (card) openEdit(cardToTask(card));
      return;
    }

    const deleteButton = event.target.closest('[data-task-delete]');
    if (deleteButton && !deleteButton.closest('#nx-task-modal')) {
      event.preventDefault();
      const card = findCard(deleteButton.dataset.taskDelete);
      if (card) deleteTask(cardToTask(card), false);
    }
  });

  form()?.addEventListener('submit', (event) => {
    event.preventDefault();
    save();
  });

  qsa('[data-task-close]', modal).forEach((button) => button.addEventListener('click', close));

  field('delete')?.addEventListener('click', () => {
    const task = {
      id: editingId,
      title: field('title').value.trim(),
    };
    deleteTask(task, true);
  });

  modal.addEventListener('cancel', (event) => {
    event.preventDefault();
    close();
  });
}
