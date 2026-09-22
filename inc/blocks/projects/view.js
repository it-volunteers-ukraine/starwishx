/**
 * starwishx/projects — arrow buttons, mouse drag and easing for the
 * scroll-snap carousel (front end).
 *
 * Registered through block.json "viewScriptModule", so WordPress loads the
 * compiled build/view.js as <script type="module"> only on pages where the
 * block renders. The carousel itself is CSS (scroll-snap on the track):
 * touch, trackpad, wheel and keyboard focus scroll it without any script.
 * This adds what a native scroller lacks:
 *
 *  - the two buttons move one card and reflect the ends of the track with
 *    aria-disabled — not `disabled`, so a button that becomes inactive under
 *    the keyboard focus keeps it;
 *  - drag-to-scroll with a mouse (what Swiper called simulateTouch). Touch
 *    and pens keep the native gesture. Snapping is off while the pointer is
 *    down (every scrollLeft write would re-snap otherwise) and the click that
 *    ends a drag is swallowed so a card link does not open;
 *  - the motion: buttons and drag releases run through one small
 *    requestAnimationFrame animator with an ease-out curve (the old slider's
 *    600 ms), and a release with velocity flicks on to the card that motion
 *    reaches, like a slider's momentum. Targets are always snap positions,
 *    so restoring scroll-snap afterwards never moves anything. Users who
 *    prefer reduced motion get instant jumps instead.
 *
 * No Interactivity API, no imports.
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
const SLIDE_DURATION = 600; // ms for a button step (the old slider's `speed`)
const FLICK_MIN_DURATION = 250; // ms
const VELOCITY_WINDOW = 100; // ms of pointer history the release velocity is read from
const FLICK_PROJECTION = 220; // ms the release velocity is projected forward

const easeOutCubic = (t) => 1 - (1 - t) ** 3;
const prefersReducedMotion = () =>
  window.matchMedia("(prefers-reduced-motion: reduce)").matches;
const clamp = (value, min, max) => Math.min(max, Math.max(min, value));

/**
 * Snap geometry of a track: the scroll positions its slides align at.
 */
function geometry(track) {
  const trackLeft = track.getBoundingClientRect().left;
  const pad = parseFloat(getComputedStyle(track).paddingLeft) || 0;
  const maxLeft = Math.max(0, track.scrollWidth - track.clientWidth);
  const positions = Array.from(track.querySelectorAll(SLIDE), (slide) =>
    clamp(
      slide.getBoundingClientRect().left - trackLeft + track.scrollLeft - pad,
      0,
      maxLeft,
    ),
  );

  const nearestIndex = (x) => {
    let index = 0;
    positions.forEach((position, i) => {
      if (Math.abs(position - x) < Math.abs(positions[index] - x)) index = i;
    });
    return index;
  };

  return { positions, maxLeft, nearestIndex };
}

/**
 * Animates track.scrollLeft to a target with snapping suspended, then hands
 * control back to CSS.
 */
function createAnimator(track) {
  let frame = 0;

  const suspendSnap = () => {
    track.style.scrollSnapType = "none";
    track.style.scrollBehavior = "auto";
  };
  const restoreSnap = () => {
    track.style.scrollSnapType = "";
    track.style.scrollBehavior = "";
  };
  /** Stops a running slide; true when one was in flight (snap is then still suspended). */
  const cancel = () => {
    const wasRunning = frame !== 0;
    if (frame) cancelAnimationFrame(frame);
    frame = 0;
    return wasRunning;
  };

  const to = (target, duration = SLIDE_DURATION) => {
    cancel();
    const start = track.scrollLeft;
    const distance = target - start;

    if (Math.abs(distance) < 1) {
      restoreSnap();
      return;
    }
    if (prefersReducedMotion()) {
      suspendSnap();
      track.scrollLeft = target;
      restoreSnap();
      return;
    }

    suspendSnap();
    const startedAt = performance.now();
    const tick = (now) => {
      const progress = Math.min(1, (now - startedAt) / duration);
      track.scrollLeft = start + distance * easeOutCubic(progress);
      if (progress < 1) {
        frame = requestAnimationFrame(tick);
      } else {
        frame = 0;
        restoreSnap();
      }
    };
    frame = requestAnimationFrame(tick);
  };

  return { to, cancel, suspendSnap, restoreSnap };
}

function setupArrows(root, track, animator) {
  const prev = root.querySelector(PREV);
  const next = root.querySelector(NEXT);
  if (!prev || !next) return () => {};

  const setDisabled = (button, disabled) => {
    button.setAttribute("aria-disabled", disabled ? "true" : "false");
  };

  const update = () => {
    const maxLeft = track.scrollWidth - track.clientWidth;
    setDisabled(prev, track.scrollLeft <= 1);
    setDisabled(next, track.scrollLeft >= maxLeft - 1);
  };

  const step = (direction) => (event) => {
    if (event.currentTarget.getAttribute("aria-disabled") === "true") return;
    const { positions, nearestIndex } = geometry(track);
    if (!positions.length) return;
    const index = clamp(
      nearestIndex(track.scrollLeft) + direction,
      0,
      positions.length - 1,
    );
    animator.to(positions[index]);
  };

  prev.addEventListener("click", step(-1));
  next.addEventListener("click", step(1));

  return update;
}

function setupMouseDrag(track, animator) {
  let pointerId = null;
  let startX = 0;
  let startLeft = 0;
  let moved = false;
  let interrupted = false; // the press stopped a running slide
  let suppressClick = false;
  let samples = []; // recent { time, x } for the release velocity

  const flick = () => {
    const now = performance.now();
    const recent = samples.filter((sample) => now - sample.time <= VELOCITY_WINDOW);
    const first = recent[0];
    const last = recent[recent.length - 1];
    // Pointer velocity (px/ms); the content moves the other way.
    const velocity =
      first && last && last.time > first.time
        ? (last.x - first.x) / (last.time - first.time)
        : 0;

    const { positions, maxLeft, nearestIndex } = geometry(track);
    if (!positions.length) {
      animator.restoreSnap();
      return;
    }
    const current = track.scrollLeft;
    const projected = clamp(current - velocity * FLICK_PROJECTION, 0, maxLeft);
    const target = positions[nearestIndex(projected)];
    const duration = clamp(
      Math.abs(target - current) / Math.max(Math.abs(velocity), 0.5),
      FLICK_MIN_DURATION,
      SLIDE_DURATION,
    );
    animator.to(target, duration);
  };

  track.addEventListener("pointerdown", (event) => {
    if (event.pointerType !== "mouse" || event.button !== 0) return;
    interrupted = animator.cancel(); // a press interrupts any running slide
    pointerId = event.pointerId;
    startX = event.clientX;
    startLeft = track.scrollLeft;
    moved = false;
    samples = [{ time: performance.now(), x: event.clientX }];
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
      animator.suspendSnap();
    }
    const now = performance.now();
    samples.push({ time: now, x: event.clientX });
    samples = samples.filter((sample) => now - sample.time <= VELOCITY_WINDOW);
    track.scrollLeft = startLeft - delta;
  });

  const end = (event) => {
    if (pointerId === null || event.pointerId !== pointerId) return;
    if (track.hasPointerCapture(pointerId)) {
      track.releasePointerCapture(pointerId);
    }
    pointerId = null;
    if (!moved) {
      // A plain press that stopped a slide mid-way: finish settling on a card.
      if (interrupted) flick();
      return;
    }
    moved = false;
    track.classList.remove(DRAGGING_CLASS);
    // The click that follows this pointerup belongs to the drag, not to the
    // card under the cursor. Cleared on the next task so a later keyboard
    // "click" is never swallowed.
    suppressClick = true;
    setTimeout(() => {
      suppressClick = false;
    }, 0);
    flick();
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

  const animator = createAnimator(track);
  const update = setupArrows(root, track, animator);
  setupMouseDrag(track, animator);

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
