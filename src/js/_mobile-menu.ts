/**
 * Mobile menu (header.php).
 *
 * The burger is a CSS checkbox toggle (#mobile-menu-toggle). This adds what
 * CSS cannot: the body class that locks the page behind the open menu, and
 * closing it on a click outside or on Escape.
 *
 * Clicks and keys inside a dialog opened from the menu (the site search)
 * belong to that dialog, so they leave the menu alone.
 *
 * Pages without the site header (/gateway/) have no toggle; nothing runs.
 */

/** The element an event happened on, or null (the document, a window). */
const elementOf = (event: Event) =>
  event.target instanceof Element ? event.target : null;

document.addEventListener("DOMContentLoaded", () => {
  const toggle = document.getElementById("mobile-menu-toggle");
  if (!(toggle instanceof HTMLInputElement)) return;

  const close = () => {
    toggle.checked = false;
    document.body.classList.remove("menu-open");
  };

  toggle.addEventListener("change", () => {
    document.body.classList.toggle("menu-open", toggle.checked);
  });

  document.addEventListener("click", (event) => {
    const target = elementOf(event);
    if (!toggle.checked || target?.closest("dialog")) return;
    const inside = target?.closest(
      ".burger-menu, .burger-menu-button, .mobile-header-buttons, #mobile-menu-toggle",
    );
    if (!inside) close();
  });

  document.addEventListener("keydown", (event) => {
    if (event.key !== "Escape" || !toggle.checked) return;
    if (elementOf(event)?.closest("dialog")) return;
    close();
  });
});
