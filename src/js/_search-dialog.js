/**
 * Site search (template-parts/search-modal.php).
 *
 * The search is a native <dialog> opened with showModal(), so the browser
 * provides the rest: focus stays inside, Escape closes it, the page behind is
 * inert, and focus returns to the button that opened it. This module only
 * wires the triggers (the header buttons with aria-controls="searchModal"),
 * the clear button, and closing on a click outside the panel.
 *
 * Pages without the site header (/gateway/) have no dialog; nothing runs.
 */

document.addEventListener('DOMContentLoaded', () => {
  const dialog = document.getElementById('searchModal');
  if (!(dialog instanceof HTMLDialogElement)) return;

  const form = dialog.querySelector('form');
  const input = dialog.querySelector('.search-input');
  const clear = dialog.querySelector('.form-clear-btn');

  document.querySelectorAll('[aria-controls="searchModal"]').forEach((trigger) => {
    trigger.addEventListener('click', () => {
      dialog.showModal();
      input?.focus();
    });
  });

  clear?.addEventListener('click', () => {
    if (!input) return;
    input.value = '';
    input.focus();
  });

  // One Escape closes, as the dialog pattern expects. Left to the browser, the
  // first press in a non-empty type="search" field only clears the text.
  dialog.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape' || event.isComposing) return;
    event.preventDefault();
    dialog.close();
  });

  // The dialog box itself is the full-screen dimmed layer, so a click that
  // lands on it directly, not on the panel inside, is a click outside.
  dialog.addEventListener('click', (event) => {
    if (event.target === dialog) dialog.close();
  });

  // Back to the server-rendered value: empty, or the query on a search page.
  dialog.addEventListener('close', () => form?.reset());
});
