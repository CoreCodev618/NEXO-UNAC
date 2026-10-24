import { initDropdowns } from './core/dropdown.js';
import { initModals } from './core/modal.js';
import { toast } from './core/toast.js';
import { initUploads } from './core/upload.js';
import { initCalendar } from './modules/calendar.js';
import { initRagUpload } from './modules/rag-upload.js';
import { initChat, openChat } from './modules/chat.js';
import { initContext } from './modules/context.js';
import { initEditor } from './modules/editor.js';
import { initKanban } from './modules/kanban.js';
import { initPalette } from './modules/palette.js';
import { initSidebar } from './modules/sidebar.js';
import { initTaskDialog } from './modules/task-dialog.js';
import { initWorkspace } from './modules/workspace.js';

function boot() {
  initDropdowns();
  initModals();
  initSidebar();
  initPalette();
  initChat();
  initTaskDialog();
  initKanban();
  initCalendar();
  initContext();
  initEditor();
  initUploads();
  initRagUpload();
  initWorkspace();

  window.NEXO = {
    toast,
    openChat,
    openPalette: () => document.dispatchEvent(new CustomEvent('nx:open-palette', { bubbles: true })),
  };
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', boot, { once: true });
} else {
  boot();
}
