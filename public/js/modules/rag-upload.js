import { api, ApiError } from '../core/api.js';
import { appUrl, escapeHtml, qs } from '../core/dom.js';
import { icon } from '../core/icons.js';
import { toast } from '../core/toast.js';

const ALLOWED = new Set(['pdf', 'docx', 'pptx', 'txt', 'md', 'jpg', 'jpeg', 'png', 'webp']);

function extension(name = '') {
  const parts = String(name).toLowerCase().split('.');
  return parts.length > 1 ? parts.pop() : '';
}

function bytes(value) {
  const size = Number(value || 0);
  if (!size) return '—';

  const units = ['B', 'KB', 'MB', 'GB'];
  let index = 0;
  let current = size;

  while (current >= 1024 && index < units.length - 1) {
    current /= 1024;
    index += 1;
  }

  return `${index === 0 ? Math.round(current) : current.toFixed(1).replace('.', ',')} ${units[index]}`;
}

function dateLabel(value) {
  const text = String(value || '').slice(0, 10);
  if (!text) return '';

  const [year, month, day] = text.split('-');
  return year && month && day ? `${day}/${month}/${year}` : text;
}

function statusBadge(document) {
  if (document.status === 'indexed') {
    return `<span class="nx-badge nx-badge--success">${icon('check-circle')} Indexado</span>`;
  }

  if (document.status === 'error') {
    return `<span class="nx-badge nx-badge--danger">${icon('alert-triangle')} Error</span>`;
  }

  return `<span class="nx-badge nx-badge--outline">${icon('clock')} Pendiente</span>`;
}

function renderList(root, documents) {
  const list = qs('[data-rag-list]', root);
  const count = qs('[data-rag-count]', root);
  if (!list) return;

  if (count) count.textContent = String(documents.length);

  if (documents.length === 0) {
    list.innerHTML = `
      <div class="nx-rag__empty">
        ${icon('file-text')}
        <p>Todavía no hay material indexado para el tutor.</p>
        <span>Sube el archivo de la clase para que el tutor pueda citarlo.</span>
      </div>
    `;
    return;
  }

  list.innerHTML = documents
    .map((document) => {
      const ext = (extension(document.file_name || '') || 'FILE').toUpperCase();
      const meta = [
        document.size_label || bytes(document.size_bytes),
        document.topic || '',
        document.class_date ? dateLabel(document.class_date) : '',
      ]
        .filter(Boolean)
        .join(' · ');
      const link =
        document.page_id && document.status === 'indexed'
          ? `<a class="nx-rag__item-link" href="${appUrl(`pages/${document.page_id}`)}">Abrir página</a>`
          : '';
      const error =
        document.status === 'error' && document.error
          ? `<p class="nx-rag__item-error" title="${escapeHtml(document.error)}">${escapeHtml(document.error)}</p>`
          : '';

      return `
        <article class="nx-rag__item">
          <span class="nx-rag__filetype" aria-hidden="true">${escapeHtml(ext)}</span>
          <div class="nx-rag__item-main">
            <strong>${escapeHtml(document.title || 'Documento')}</strong>
            <span>${escapeHtml(meta || 'Sin metadatos')}</span>
            ${error}
          </div>
          <div class="nx-rag__item-side">
            ${statusBadge(document)}
            ${link}
          </div>
        </article>
      `;
    })
    .join('');
}

function setStatus(root, text, state = '') {
  const element = qs('[data-rag-status]', root);
  if (!element) return;
  element.textContent = text;
  element.dataset.state = state;
}

function setProgress(root, percent) {
  const bar = qs('[data-rag-progress-bar]', root);
  if (bar) bar.style.width = `${Math.max(0, Math.min(100, percent))}%`;
}

function setFileLabel(root, file) {
  const label = qs('[data-rag-file-label]', root);
  if (!label) return;
  label.textContent = file ? `${file.name} · ${bytes(file.size)}` : 'Arrastra un archivo o haz clic para elegirlo';
}

function validate(file, maxMb) {
  if (!file) return 'Elige un archivo.';

  const ext = extension(file.name);
  if (!ALLOWED.has(ext)) {
    return 'Para el tutor usa PDF, DOCX, PPTX, TXT, MD, JPG, PNG o WEBP.';
  }

  if (file.size > maxMb * 1024 * 1024) {
    return `El archivo supera el límite de ${maxMb} MB.`;
  }

  if (file.size <= 0) {
    return 'El archivo está vacío.';
  }

  return null;
}

async function refresh(root) {
  const list = qs('[data-rag-list]', root);
  if (!list) return;

  try {
    const documents = await api.documents.list();
    const pageId = Number(root.dataset.ragPage || 0);
    // Los documentos sin página (subidos antes de que B persistiera la
    // página actual) también se muestran para no ocultar material indexado.
    const scoped = (Array.isArray(documents) ? documents : []).filter(
      (document) => !pageId || !document.page_id || Number(document.page_id) === pageId,
    );

    renderList(root, scoped);
  } catch (error) {
    if (error instanceof ApiError && error.unavailable) {
      list.innerHTML = `<p class="nx-rag__empty">El servicio de documentos todavía no está disponible.</p>`;
      return;
    }

    list.innerHTML = `<p class="nx-rag__empty">No se pudo cargar el material: ${escapeHtml(error.message || 'error desconocido')}</p>`;
  }
}

async function upload(root, file) {
  const form = qs('[data-rag-form]', root);
  const input = qs('[data-rag-input]', root);
  const submit = qs('[data-rag-submit]', root);
  const progress = qs('[data-rag-progress]', root);
  const maxMb = Number(root.dataset.ragMaxMb || 20);
  const pageId = Number(root.dataset.ragPage || 0);

  const problem = validate(file, maxMb);
  if (problem) {
    toast.error(problem, 'Archivo no válido');
    return;
  }

  if (!pageId) {
    toast.warning('Guarda la página antes de subir material.', 'Falta la página');
    return;
  }

  const formData = new FormData();
  formData.append('file', file);
  formData.append('page_id', String(pageId));

  const title = qs('[data-rag-title]', root)?.value.trim();
  const topic = qs('[data-rag-topic]', root)?.value.trim();
  const curso = qs('[data-rag-curso]', root)?.value || '1';
  const date = qs('[data-rag-date]', root)?.value || '';

  if (title) formData.append('title', title);
  if (topic) formData.append('topic', topic);
  if (date) formData.append('class_date', date);
  formData.append('curso_id', curso);

  if (submit) submit.disabled = true;
  root.classList.add('is-uploading');
  root.setAttribute('aria-busy', 'true');
  if (progress) progress.hidden = false;
  setProgress(root, 4);
  setStatus(root, 'Subiendo y calculando embeddings… puede tardar unos segundos.', 'busy');

  try {
    const document = await api.documents.upload(formData, (percent) => setProgress(root, percent));
    const chunks = Number(document?.chunks || 0);

    toast.success(
      chunks > 0 ? `Se indexaron ${chunks} fragmentos del material.` : 'El material quedó indexado.',
      'Material listo',
    );

    form?.reset();
    await refresh(root);

    return true;
  } catch (error) {
    const saved = error instanceof ApiError && error.payload?.errors?.document;

    toast.error(error.message || 'No se pudo indexar el material.', 'No se pudo indexar');

    if (saved) {
      await refresh(root);
    }

    return false;
  } finally {
    root.classList.remove('is-uploading');
    root.removeAttribute('aria-busy');
    if (progress) progress.hidden = true;
    setProgress(root, 0);
  }
}

export function initRagUpload() {
  document.querySelectorAll('[data-rag-upload]').forEach((root) => {
    if (root.dataset.ragReady === '1') return;
    root.dataset.ragReady = '1';

    const preview = root.dataset.ragPreview === '1';
    const pageId = Number(root.dataset.ragPage || 0);
    const form = qs('[data-rag-form]', root);
    const input = qs('[data-rag-input]', root);

    if (!form || !input || preview || pageId <= 0) {
      return;
    }

    const dropzone = qs('[data-rag-dropzone]', root);
    const submit = qs('[data-rag-submit]', root);
    const dateInput = qs('[data-rag-date]', root);
    let selectedFile = null;

    if (dateInput && !dateInput.value) {
      dateInput.value = new Date().toLocaleDateString('en-CA');
    }

    const choose = (file) => {
      selectedFile = file || null;

      if (dateInput && !dateInput.value) {
        dateInput.value = new Date().toLocaleDateString('en-CA');
      }

      setFileLabel(root, selectedFile);

      const problem = selectedFile ? validate(selectedFile, Number(root.dataset.ragMaxMb || 20)) : null;

      if (problem) {
        setStatus(root, problem, 'error');
        if (submit) submit.disabled = true;
        return;
      }

      setStatus(root, selectedFile ? 'Listo para subir e indexar.' : 'Sin archivo seleccionado.');
      if (submit) submit.disabled = !selectedFile;
    };

    input.addEventListener('change', () => choose(input.files?.[0] || null));

    ['dragenter', 'dragover'].forEach((eventName) => {
      dropzone?.addEventListener(eventName, (event) => {
        event.preventDefault();
        dropzone.classList.add('is-dragging');
      });
    });

    ['dragleave', 'drop'].forEach((eventName) => {
      dropzone?.addEventListener(eventName, (event) => {
        event.preventDefault();
        dropzone.classList.remove('is-dragging');
      });
    });

    dropzone?.addEventListener('drop', (event) => {
      const file = event.dataTransfer?.files?.[0];
      if (file) choose(file);
    });

    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      const uploaded = await upload(root, selectedFile || input.files?.[0] || null);

      if (uploaded) {
        choose(null);
      } else if (submit) {
        submit.disabled = false;
      }
    });

    refresh(root);
  });
}
