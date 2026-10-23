import { api, ApiError } from '../core/api.js';
import { escapeHtml, qs, qsa } from '../core/dom.js';
import { toast } from '../core/toast.js';

function isPreview() {
  return document.body.dataset.preview === '1';
}

function slug(value) {
  return String(value || 'pagina')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '') || 'pagina';
}

function blockToMarkdown(block) {
  const type = block.dataset.blockType || 'parrafo';
  const text = (selector) => (qs(selector, block)?.innerText || '').replace(/\u00a0/g, ' ').trim();

  if (type === 'parrafo') {
    const field = qs('[data-field="text"]', block);
    const value = (field?.innerText || '').replace(/\u00a0/g, ' ').trim();
    if (!value) return '';
    const style = field?.dataset.style || 'p';
    if (style === 'h2') return `## ${value}`;
    if (style === 'h3') return `### ${value}`;
    return value;
  }

  if (type === 'checklist') {
    return qsa('.nx-checklist__item', block)
      .map((item) => {
        const checked = item.classList.contains('is-checked');
        return `- [${checked ? 'x' : ' '}] ${(qs('.nx-checklist__text', item)?.innerText || '').trim()}`;
      })
      .join('\n');
  }

  if (type === 'codigo') {
    const language = qs('[data-field="language"]', block)?.value || 'texto';
    const code = qs('[data-field="code"]', block)?.value || '';
    return `\`\`\`${language}\n${code}\n\`\`\``;
  }

  if (type === 'tabla') {
    const headers = qsa('thead th .nx-tableblock__cell', block).map((cell) => cell.innerText.trim());
    const rows = qsa('tbody tr', block).map((row) =>
      qsa('.nx-tableblock__cell', row).map((cell) => cell.innerText.trim()),
    );
    if (headers.length === 0) return '';
    return [
      `| ${headers.join(' | ')} |`,
      `| ${headers.map(() => '---').join(' | ')} |`,
      ...rows.map((row) => `| ${headers.map((_, index) => row[index] || '').join(' | ')} |`),
    ].join('\n');
  }

  if (type === 'quote') {
    const value = text('[data-field="text"]');
    const cite = qs('[data-field="cite"]', block)?.value.trim();
    if (!value) return '';
    return `> ${value.replace(/\n/g, '\n> ')}${cite ? `\n>\n> — ${cite}` : ''}`;
  }

  if (type === 'callout') {
    const title = text('[data-field="title"]');
    const value = text('[data-field="text"]');
    return `> **${title || 'Aviso'}**\n> ${value.replace(/\n/g, '\n> ')}`;
  }

  return '';
}

function exportMarkdown() {
  const title = (qs('[data-page-title-input]')?.innerText || document.body.dataset.pageTitle || 'Página').trim();
  const parts = [`# ${title}`, ''];

  qsa('.nx-block').forEach((block) => {
    const value = blockToMarkdown(block);
    if (value) {
      parts.push(value, '');
    }
  });

  const markdown = parts.join('\n').trimEnd() + '\n';
  const blob = new Blob([markdown], { type: 'text/markdown;charset=utf-8' });
  const url = URL.createObjectURL(blob);
  const anchor = document.createElement('a');
  anchor.href = url;
  anchor.download = `${slug(title)}.md`;
  document.body.appendChild(anchor);
  anchor.click();
  anchor.remove();
  URL.revokeObjectURL(url);
  toast.success('La página se descargó como Markdown, con sus bloques actuales.', 'Markdown listo');
}

async function sharePage(button) {
  const url = window.location.href;

  try {
    await navigator.clipboard.writeText(url);
    toast.success('El enlace de esta página está en el portapapeles.', 'Enlace copiado');
  } catch {
    toast.info(url, 'Copia el enlace');
    button?.blur();
  }
}

function setTaskRowState(row, done) {
  if (!row) return;
  row.classList.toggle('is-done', done);
  const check = qs('[data-task-toggle]', row);
  if (check) {
    check.dataset.taskStatus = done ? 'done' : 'todo';
    check.setAttribute('aria-pressed', done ? 'true' : 'false');
  }
}

function toggleTask(button) {
  const row = button.closest('.nx-task-row');
  if (!row) return;

  const current = button.dataset.taskStatus || 'todo';
  const done = current !== 'done';
  const id = button.dataset.taskToggle;

  if (isPreview() || !id || String(id).startsWith('new-')) {
    setTaskRowState(row, done);
    toast.success(
      done ? 'La tarea quedó marcada como hecha en la vista local.' : 'La tarea volvió a pendientes en la vista local.',
      'Cambio local',
    );
    return;
  }

  button.disabled = true;
  api.tasks
    .update(id, { status: done ? 'done' : 'todo' })
    .then(() => {
      setTaskRowState(row, done);
      toast.success(done ? 'La tarea quedó marcada como hecha.' : 'La tarea volvió a pendientes.', 'Tarea actualizada');
    })
    .catch((error) => {
      if (error instanceof ApiError && error.unavailable) {
        toast.warning(
          'El backend de tareas todavía no está disponible. La tarea no cambió.',
          'Sin conexión',
        );
      } else {
        toast.error(error.message || 'No se pudo actualizar la tarea.');
      }
    })
    .finally(() => {
      button.disabled = false;
    });
}

function bindSettingsForms() {
  qsa('[data-settings-form]').forEach((form) => {
    form.addEventListener('submit', (event) => {
      event.preventDefault();
      const button = qs('button[type="submit"]', form);
      button?.classList.add('is-loading');
      if (button) button.disabled = true;

      window.setTimeout(() => {
        button?.classList.remove('is-loading');
        if (button) button.disabled = false;
        toast.warning(
          isPreview()
            ? 'Los ajustes no se guardan en la vista de diseño. El formulario está listo para conectar el endpoint de perfil.'
            : 'El endpoint de perfil y preferencias todavía no está disponible. Los datos no se guardaron.',
          'Sin guardado',
        );
      }, 420);
    });
  });
}

export function initWorkspace() {
  document.addEventListener('click', (event) => {
    const coming = event.target.closest('[data-coming-soon]');
    if (coming) {
      event.preventDefault();
      toast.info(coming.dataset.comingSoon, 'Todavía no');
      return;
    }

    const demo = event.target.closest('[data-demo-toast]');
    if (demo) {
      event.preventDefault();
      toast.success(demo.dataset.demoToast || 'Acción de demostración.', 'Vista de diseño');
      return;
    }

    const share = event.target.closest('[data-share-page]');
    if (share) {
      event.preventDefault();
      sharePage(share);
      return;
    }

    const exportButton = event.target.closest('[data-export-markdown]');
    if (exportButton) {
      event.preventDefault();
      exportMarkdown();
      return;
    }

    const print = event.target.closest('[data-print-page]');
    if (print) {
      event.preventDefault();
      window.print();
      return;
    }

    const toggle = event.target.closest('[data-task-toggle]');
    if (toggle) {
      event.preventDefault();
      toggleTask(toggle);
    }
  });

  bindSettingsForms();
}

export { exportMarkdown, slug };
