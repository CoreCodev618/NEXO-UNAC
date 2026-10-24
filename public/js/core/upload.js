import { api, ApiError } from './api.js';
import { escapeHtml, qs } from './dom.js';
import { icon } from './icons.js';
import { toast } from './toast.js';

const MAX_BYTES = 10 * 1024 * 1024;
const ALLOWED = new Map([
  ['pdf', 'PDF'],
  ['docx', 'DOCX'],
  ['pptx', 'PPTX'],
  ['jpg', 'JPG'],
  ['jpeg', 'JPG'],
  ['png', 'PNG'],
  ['zip', 'ZIP'],
]);

function extension(name = '') {
  const parts = String(name).toLowerCase().split('.');
  return parts.length > 1 ? parts.pop() : '';
}

function bytes(value) {
  if (!value || value <= 0) return '—';
  const units = ['B', 'KB', 'MB', 'GB'];
  let index = 0;
  let size = Number(value);
  while (size >= 1024 && index < units.length - 1) {
    size /= 1024;
    index += 1;
  }
  return `${index === 0 ? Math.round(size) : size.toFixed(1).replace('.', ',')} ${units[index]}`;
}

function validate(file) {
  const ext = extension(file.name);

  if (ext === 'rar') {
    return 'Los archivos .rar no están permitidos. Comprime el contenido en un ZIP o súbelo como PDF.';
  }

  if (!ALLOWED.has(ext)) {
    return 'Ese formato no está permitido. Usa PDF, DOCX, PPTX, JPG, PNG o ZIP.';
  }

  if (file.size > MAX_BYTES) {
    return 'El archivo supera los 10 MB. Divídelo o comprímelo antes de subirlo.';
  }

  return null;
}

function appendLocalFile(list, file) {
  if (!list) return;
  const ext = ALLOWED.get(extension(file.name)) || 'FILE';
  const tone = ext === 'PDF' ? 'gold' : ['DOCX', 'JPG', 'PNG'].includes(ext) ? 'sage' : 'wine';
  const row = document.createElement('div');
  row.className = 'nx-attachment';
  row.innerHTML = `
    <span class="nx-attachment__icon nx-attachment__icon--${tone}" aria-hidden="true">${escapeHtml(ext)}</span>
    <span class="nx-attachment__main">
      <span class="nx-attachment__name">${escapeHtml(file.name)}</span>
      <span class="nx-attachment__meta">${bytes(file.size)} · listo en la vista local</span>
    </span>
    <span class="nx-attachment__action">${icon('check-circle')}</span>
  `;
  list.prepend(row);
}

function handleFile(zone, file) {
  const input = qs('[data-upload-input]', zone);
  const list = qs('[data-attachment-list]') || zone.parentElement?.querySelector('[data-attachment-list]');
  const error = validate(file);

  if (error) {
    toast.error(error, 'Archivo no permitido');
    if (input) input.value = '';
    return;
  }

  const preview = document.body.dataset.preview === '1';
  const pageId = Number(document.body.dataset.pageId || 0);

  if (preview || !pageId) {
    appendLocalFile(list, file);
    toast.success(
      preview
        ? 'El archivo quedó en la vista local. No se subió al servidor porque estás en la demo.'
        : 'El archivo está listo, pero el backend de archivos todavía no está conectado.',
      'Adjunto local',
    );
    if (input) input.value = '';
    return;
  }

  zone.classList.add('is-uploading');
  zone.setAttribute('aria-busy', 'true');

  api.attachments
    .upload(pageId, file)
    .then(() => {
      appendLocalFile(list, file);
      toast.success('El archivo se subió al espacio.', 'Adjunto guardado');
    })
    .catch((uploadError) => {
      if (uploadError instanceof ApiError && uploadError.unavailable) {
        toast.warning(
          'El backend de archivos aún no está disponible. El archivo no se subió.',
          'Sin conexión de archivos',
        );
      } else {
        toast.error(uploadError.message || 'No se pudo subir el archivo.');
      }
    })
    .finally(() => {
      zone.classList.remove('is-uploading');
      zone.removeAttribute('aria-busy');
      if (input) input.value = '';
    });
}

export function initUploads() {
  document.querySelectorAll('[data-upload-zone]').forEach((zone) => {
    const input = qs('[data-upload-input]', zone);
    if (!input || input.dataset.uploadReady === '1') return;
    input.dataset.uploadReady = '1';

    input.addEventListener('change', () => {
      const file = input.files?.[0];
      if (file) handleFile(zone, file);
    });

    ['dragenter', 'dragover'].forEach((eventName) => {
      zone.addEventListener(eventName, (event) => {
        event.preventDefault();
        zone.classList.add('is-dragging');
      });
    });

    ['dragleave', 'drop'].forEach((eventName) => {
      zone.addEventListener(eventName, (event) => {
        event.preventDefault();
        zone.classList.remove('is-dragging');
      });
    });

    zone.addEventListener('drop', (event) => {
      const file = event.dataTransfer?.files?.[0];
      if (file) handleFile(zone, file);
    });
  });
}
