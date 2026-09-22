/**
 * starwishx/projects — arrow buttons and mouse drag for the scroll-snap
 * carousel (front end).
 *
 * Registered through block.json "viewScriptModule", so WordPress loads the
 * compiled build/view.js as <script type="module"> only on pages where the
 * block renders. The carousel itself is CSS (scroll-snap on the track):
 * touch, trackpad, wheel and keyboard focus scroll it without any script.
 * This adds what a native scroller lacks:
 *
 *  - the two buttons scroll by one card and reflect the ends of the track
 *    with aria-disabled — not `disabled`, so a button that becomes inactive
 *    under the keyboard focus keeps it;
 *  - drag-to-scroll with a mouse (what Swiper calls simulateTouch). Touch
 *    pens keep the native gesture. Snapping is switched off while the pointer
 *    is down (every scrollLeft write would re-snap otherwise), the track then
 *    glides to the nearest card on release and snapping comes back once that
 *    scroll ends; the click that ends a drag is swallowed so a card link does
 *    not open.
 *
 * No Interactivity API, no imports — well under a kilobyte for the whole
 * behaviour.
 *
 * File: inc/blocks/projects/view.js
 */

const ROOT = ".projects";
const TRACK = ".projects__track";
const SLIDE = ".projects__slide";
const PREV = ".projects__arrow--prev";
const NEXT = ".projects__arrow--next";
const DRAGGING_CLASS = "is-dragging";
const DRAG_THRESHOLD = 6; // px before a mousedown becomes a drag, not a click
const SNAP_RESTORE_FALLBACK = 700; // ms, when the browser never fires scrollend

function gapOf(track) {
  return parseFloat(getComputedStyle(track).columnGap) || 0;
}

function setupArrows(root, track) {
  const prev = root.querySelector(PREV);
  const next = root.querySelector(NEXT);
  if (!prev || !next) return () => {};

  const step = () => {
    const slide = track.querySelector(SLIDE);
    return slide ? slide.offsetWidth + gapOf(track) : track.clientWidth;
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

  return update;
}

function setupMouseDrag(track) {
  let pointerId = null;
  let startX = 0;
  let startLeft = 0;
  let moved = false;
  let suppressClick = false;
  let restoreTimer = 0;

  // Left edge of each slide in the track's scroll coordinates, minus the
  // scroll padding — the positions the snap points sit at.
  const snapPositions = () => {
    const trackLeft = track.getBoundingClientRect().left;
    const pad = parseFloat(getComputedStyle(track).paddingLeft) || 0;
    return Array.from(track.querySelectorAll(SLIDE), (slide) => {
      return slide.getBoundingClientRect().left - trackLeft + track.scrollLeft - pad;
    });
  };

  const restoreSnap = () => {
    clearTimeout(restoreTimer);
    track.removeEventListener("scrollend", restoreSnap);
    track.style.scrollSnapType = "";
    track.style.scrollBehavior = "";
  };

  const settle = () => {
    const current = track.scrollLeft;
    const target = snapPositions().reduce(
      (best, position) =>
        Math.abs(position - current) < Math.abs(best - current) ? position : best,
      current,
    );
    // Glide there with snapping still off; put it back when the glide ends.
    track.style.scrollBehavior = "smooth";
    track.addEventListener("scrollend", restoreSnap, { once: true });
    restoreTimer = setTimeout(restoreSnap, SNAP_RESTORE_FALLBACK);
    if (Math.abs(target - current) < 1) {
      restoreSnap();
    } else {
      track.scrollTo({ left: target });
    }
  };

  track.addEventListener("pointerdown", (event) => {
    if (event.pointerType !== "mouse" || event.button !== 0) return;
    clearTimeout(restoreTimer);
    track.removeEventListener("scrollend", restoreSnap);
    pointerId = event.pointerId;
    startX = event.clientX;
    startLeft = track.scrollLeft;
    moved = false;
    // Capture is taken only once this becomes a drag: with capture on, the
    // click that follows a plain press would be dispatched to the track
    // instead of the card link under the cursor.
  });

  track.addEventListener("pointermove", (event) => {
    if (pointerId === null || event.pointerId !== pointerId) return;
    const delta = event.clientX - startX;
    if (!moved) {
      if (Math.abs(delta) < DRAG_THRESHOLD) return;
      moved = true;
      track.setPointerCapture(pointerId);
      track.classList.add(DRAGGING_CLASS);
      track.style.scrollSnapType = "none";
      track.style.scrollBehavior = "auto";
    }
    track.scrollLeft = startLeft - delta;
  });

  const end = (event) => {
    if (pointerId === null || event.pointerId !== pointerId) return;
    if (track.hasPointerCapture(pointerId)) {
      track.releasePointerCapture(pointerId);
    }
    pointerId = null;
    if (!moved) return;
    moved = false;
    track.classList.remove(DRAGGING_CLASS);
    // The click that follows this pointerup belongs to the drag, not to the
    // card under the cursor. Cleared on the next task so a later keyboard
    // "click" is never swallowed.
    suppressClick = true;
    setTimeout(() => {
      suppressClick = false;
    }, 0);
    settle();
  };

  track.addEventListener("pointerup", end);
  track.addEventListener("pointercancel", end);

  track.addEventListener(
    "click",
    (event) => {
      if (!suppressClick) return;
      suppressClick = false;
      event.preventDefault();
      event.stopPropagation();
    },
    true,
  );

  // No ghost images / link drags while pulling the track.
  track.addEventListener("dragstart", (event) => event.preventDefault());
}

function setup(root) {
  const track = root.querySelector(TRACK);
  if (!track) return;

  const update = setupArrows(root, track);
  setupMouseDrag(track);

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
