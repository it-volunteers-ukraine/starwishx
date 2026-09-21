/**
 * starwishx/video-text — click-to-play facade (front end only).
 *
 * Registered through block.json "viewScriptModule", so WordPress loads the
 * compiled build/view.js as <script type="module"> only on pages where the
 * block renders (deferred by nature: the DOM is parsed when this runs). The
 * facade is a real <button>, so Enter/Space need no handler. No Interactivity
 * API, no imports — a few hundred bytes for a single interaction.
 *
 * File: inc/blocks/video-text/view.js
 */

const ROOT = ".video-text";
const FACADE = ".video-text__facade";

function play(facade) {
  const src = facade.dataset.embedUrl;
  if (!src) return;

  const iframe = document.createElement("iframe");
  iframe.className = "video-text__player";
  iframe.src = src; // carries autoplay=1; the user gesture precedes creation
  iframe.title = facade.dataset.title || facade.getAttribute("aria-label") || "";
  iframe.allow =
    "accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share";
  iframe.allowFullscreen = true;
  iframe.referrerPolicy = "strict-origin-when-cross-origin";

  facade.replaceWith(iframe);
  iframe.focus(); // keep keyboard focus with the player once the button is gone
}

document.querySelectorAll(ROOT).forEach((root) => {
  root.addEventListener("click", (event) => {
    const facade = event.target.closest(FACADE);
    if (facade && root.contains(facade)) play(facade);
  });
});
