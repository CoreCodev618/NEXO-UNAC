import { appUrl, escapeHtml, qs } from '../core/dom.js';
import { icon } from '../core/icons.js';

function linkFor(task) {
  return task.page_id
    ? appUrl(`pages/${task.page_id}`)
    : appUrl(`workspaces/${document.body.dataset.workspaceId}/kanban`);
}

function chipFor(task) {
  const anchor = document.createElement('a');
  anchor.className = `nx-calendar__task nx-calendar__task--${task.priority}${task.status === 'done' ? ' is-done' : ''}`;
  anchor.href = linkFor(task);
  anchor.title = task.title;
  anchor.dataset.calendarTaskId = String(task.id);
  anchor.innerHTML = `${icon(task.status === 'done' ? 'check' : 'dot')}<span>${escapeHtml(task.title)}</span>`;
  return anchor;
}

function addToCalendar(task) {
  if (!task?.due_date) return;
  const day = qs(`.nx-calendar__day[data-date="${task.due_date}"]`);
  const list = qs('.nx-calendar__tasks', day);
  if (!list) return;
  list.querySelector('[data-calendar-task-id="' + String(task.id) + '"]')?.remove();
  list.appendChild(chipFor(task));
}

function removeFromCalendar(id) {
  document
    .querySelectorAll(`[data-calendar-task-id="${String(id)}"]`)
    .forEach((chip) => chip.remove());
}

export function initCalendar() {
  if (!qs('.nx-calendar')) return;

  document.addEventListener('nx:task-saved', (event) => {
    const { task, mode } = event.detail || {};
    if (!task) return;
    if (mode === 'edit') removeFromCalendar(task.id);
    addToCalendar(task);
  });

  document.addEventListener('nx:task-deleted', (event) => {
    if (event.detail?.id) removeFromCalendar(event.detail.id);
  });
}
