/**
 * starwishx/projects — arrow buttons for the scroll-snap carousel (front end).
 *
 * Registered through block.json "viewScriptModule", so WordPress loads the
 * compiled build/view.js as <script type="module"> only on pages where the
 * block renders. The carousel itself is CSS (scroll-snap on the track):
 * touch, trackpad, wheel and keyboard focus scroll it without any script.
 * This only makes the two buttons scroll by one card and reflects the ends
 * of the track with aria-disabled — not `disabled`, so a button that becomes
 * inactive under the keyboard focus keeps it. No Interactivity API, no
 * imports — a few hundred bytes for one interaction.
 *
 * File: inc/blocks/projects/view.js
 */

const ROOT = ".projects";
const TRACK = ".projects__track";
const SLIDE = ".projects__slide";
const PREV = ".projects__arrow--prev";
const NEXT = ".projects__arrow--next";

function setup(root) {
  const track = root.querySelector(TRACK);
  const prev = root.querySelector(PREV);
  const next = root.querySelector(NEXT);
  if (!track || !prev || !next) return;

  const step = () => {
    const slide = track.querySelector(SLIDE);
    const gap = parseFloat(getComputedStyle(track).columnGap) || 0;
    return slide ? slide.offsetWidth + gap : track.clientWidth;
  };

  const setDisabled = (button, disabled) => {
    button.setAttribute("aria-disabled", disabled ? "true" : "false");
  };

  const update = () => {
    const maxLeft = track.scrollWidth - track.clientWidth;
    setDisabled(prev, track.scrollLeft <= 1);
    setDisabled(next, track.scrollLeft >= maxLeft - 1);
  };

  const scroll = (direction) => (event) => {
    if (event.currentTarget.getAttribute("aria-disabled") === "true") return;
    track.scrollBy({ left: direction * step() }); // behavior comes from CSS scroll-behavior
  };

  prev.addEventListener("click", scroll(-1));
  next.addEventListener("click", scroll(1));

  let frame = 0;
  const scheduleUpdate = () => {
    if (frame) return;
    frame = requestAnimationFrame(() => {
      frame = 0;
      update();
    });
  };

  track.addEventListener("scroll", scheduleUpdate, { passive: true });
  window.addEventListener("resize", scheduleUpdate, { passive: true });
  update();
}

document.querySelectorAll(ROOT).forEach(setup);
