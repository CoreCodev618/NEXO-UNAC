import {
  lockScroll,
  normalizeText,
  qs,
  qsa,
  setHidden,
  trapFocus,
  unlockScroll,
} from '../core/dom.js';

let activeIndex = -1;
let lastFocus = null;

function items() {
  return qsa('[data-palette-item]').filter((item) => item.offsetParent !== null);
}

function setActive(item) {
  const all = items();
  const input = qs('[data-palette-input]');

  all.forEach((entry) => {
    entry.classList.remove('is-active');
    entry.setAttribute('aria-selected', 'false');
  });

  if (!item) {
    activeIndex = -1;
    input?.removeAttribute('aria-activedescendant');
    return;
  }

  item.classList.add('is-active');
  item.setAttribute('aria-selected', 'true');
  activeIndex = all.indexOf(item);
  input?.setAttribute('aria-activedescendant', item.id);
  item.scrollIntoView({ block: 'nearest' });
}

function filter(input, empty) {
  const query = normalizeText(input.value);
  let visible = 0;

  qsa('[data-palette-group]').forEach((group) => {
    let groupVisible = 0;

    qsa('[data-palette-item]', group).forEach((item) => {
      const match = !query || normalizeText(item.dataset.search || '').includes(query);
      setHidden(item, !match);
      if (match) {
        groupVisible += 1;
        visible += 1;
      }
    });

    setHidden(group, groupVisible === 0);
  });

  setHidden(empty, visible > 0);
  setActive(items()[0] || null);
}

function close(palette) {
  if (!palette || palette.hasAttribute('hidden')) return;
  const input = qs('[data-palette-input]', palette);
  palette.setAttribute('hidden', '');
  input?.setAttribute('aria-expanded', 'false');
  input?.removeAttribute('aria-activedescendant');
  unlockScroll();
  if (lastFocus instanceof HTMLElement && lastFocus.isConnected) {
    lastFocus.focus();
  }
}

function open(palette, input, empty) {
  if (!palette || !input) return;
  lastFocus = document.activeElement;
  palette.removeAttribute('hidden');
  input.setAttribute('aria-expanded', 'true');
  lockScroll();
  input.value = '';
  filter(input, empty);
  window.requestAnimationFrame(() => input.focus());
}

function activate(item, palette) {
  if (!item) return;
  const action = item.dataset.action;
  const url = item.dataset.url;

  close(palette);

  if (action === 'tutor') {
    document.dispatchEvent(new CustomEvent('nx:open-chat', { bubbles: true }));
    return;
  }

  if (url && url !== '#') {
    window.location.href = url;
  }
}

export function initPalette() {
  const palette = qs('#nx-palette');
  if (!palette) return;

  const input = qs('[data-palette-input]', palette);
  const empty = qs('[data-palette-empty]', palette);
  const itemsList = qsa('[data-palette-item]');

  itemsList.forEach((item, index) => {
    item.id = `nx-palette-item-${index}`;
    item.setAttribute('role', 'option');
  });

  qsa('[data-palette-open]').forEach((button) => {
    button.addEventListener('click', (event) => {
      event.preventDefault();
      open(palette, input, empty);
    });
  });

  qsa('[data-palette-close]').forEach((button) => {
    button.addEventListener('click', () => close(palette));
  });

  input?.addEventListener('input', () => filter(input, empty));

  palette.addEventListener('click', (event) => {
    const item = event.target.closest('[data-palette-item]');
    if (item) {
      event.preventDefault();
      activate(item, palette);
    }
  });

  palette.addEventListener('mousemove', (event) => {
    const item = event.target.closest('[data-palette-item]');
    if (item && item.offsetParent !== null) {
      setActive(item);
    }
  });

  document.addEventListener('keydown', (event) => {
    const isOpen = !palette.hasAttribute('hidden');
    const usesMod = event.metaKey || event.ctrlKey;

    if (usesMod && event.key.toLowerCase() === 'k') {
      event.preventDefault();
      if (isOpen) close(palette);
      else open(palette, input, empty);
      return;
    }

    if (!isOpen) return;

    if (event.key === 'Escape') {
      event.preventDefault();
      close(palette);
      return;
    }

    if (event.key === 'Tab') {
      trapFocus(palette, event);
      return;
    }

    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
      event.preventDefault();
      const list = items();
      if (list.length === 0) return;
      const next =
        event.key === 'ArrowDown'
          ? (activeIndex + 1 + list.length) % list.length
          : (activeIndex - 1 + list.length) % list.length;
      setActive(list[next]);
      input?.setAttribute('aria-activedescendant', list[next].id);
      return;
    }

    if (event.key === 'Enter') {
      const list = items();
      const item = list[activeIndex] || list[0];
      if (item) {
        event.preventDefault();
        activate(item, palette);
      }
    }
  });

  document.addEventListener('nx:open-palette', () => open(palette, input, empty));

  const shortcuts = itemsList
    .map((item) => ({
      item,
      keys: (item.dataset.shortcut || '').toLowerCase().replace(/\s+/g, ''),
    }))
    .filter((entry) => /^[a-z]+$/.test(entry.keys));

  let shortcutBuffer = '';
  let shortcutTimer = 0;

  const resetShortcut = () => {
    shortcutBuffer = '';
    window.clearTimeout(shortcutTimer);
  };

  document.addEventListener('keydown', (event) => {
    if (
      !palette.hasAttribute('hidden') ||
      event.defaultPrevented ||
      event.repeat ||
      event.metaKey ||
      event.ctrlKey ||
      event.altKey ||
      event.key.length !== 1 ||
      !/[a-z]/i.test(event.key) ||
      document.querySelector('dialog[open]') ||
      qs('#nx-chat')?.classList.contains('is-open')
    ) {
      return;
    }

    if (
      event.target instanceof HTMLElement &&
      event.target.closest('input, textarea, select, [contenteditable="true"]')
    ) {
      resetShortcut();
      return;
    }

    shortcutBuffer += event.key.toLowerCase();
    const exact = shortcuts.find((entry) => entry.keys === shortcutBuffer);
    const isPrefix = shortcuts.some((entry) => entry.keys.startsWith(shortcutBuffer));

    if (exact) {
      event.preventDefault();
      resetShortcut();
      activate(exact.item, palette);
      return;
    }

    if (isPrefix) {
      event.preventDefault();
      window.clearTimeout(shortcutTimer);
      shortcutTimer = window.setTimeout(resetShortcut, 900);
      return;
    }

    resetShortcut();
  });
}
