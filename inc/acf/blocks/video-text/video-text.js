/**
 * Video facade — click-to-play handler.
 *
 * Replaces the cover image + play button with an autoplay iframe
 * when the user clicks or presses Enter/Space on the facade element.
 *
 * File: inc/acf/blocks/video-text/video-text.js
 */

document.addEventListener("click", function (e) {
  var facade = e.target.closest("[data-embed-url]");
  if (!facade) return;

  var url = facade.getAttribute("data-embed-url");
  if (!url) return;

  var iframe = document.createElement("iframe");
  iframe.src = url;
  iframe.setAttribute("allow", "autoplay; encrypted-media");
  iframe.setAttribute("allowfullscreen", "");
  iframe.setAttribute(
    "title",
    facade.getAttribute("aria-label") || "Video player",
  );

  facade.replaceWith(iframe);
});

document.addEventListener("keydown", function (e) {
  if (e.key !== "Enter" && e.key !== " ") return;

  var facade = e.target.closest("[data-embed-url]");
  if (!facade) return;

  e.preventDefault();
  facade.click();
});
