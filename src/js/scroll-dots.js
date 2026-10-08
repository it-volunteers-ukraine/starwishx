/**
 * Position dots for native scroll-snap rows.
 *
 * Markup (server-rendered, e.g. archive-news.php):
 *   <ul id="track"> <li>…</li> … </ul>
 *   <div data-scroll-dots="track" hidden> <button aria-label="…"></button> … </div>
 *
 * The row itself is CSS (scroll-snap): swiping needs no script. This wires one
 * button per item - a click scrolls that item into view - and keeps
 * aria-current="true" on the button of the item in view. The dots ship
 * `hidden` and are revealed here, so without JS there are no dead controls.
 * Labels are translated in PHP, never set here.
 *
 * Loaded only where a template enqueues the `sw-scroll-dots` handle.
 *
 * File: src/js/scroll-dots.js
 */

const reducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

function setup(nav) {
  const track = document.getElementById(nav.dataset.scrollDots);
  if (!track) return;

  const items = Array.from(track.children);
  const buttons = Array.from(nav.querySelectorAll('button'));
  if (items.length < 2 || items.length !== buttons.length) return;

  const setCurrent = (index) => {
    buttons.forEach((button, i) => {
      if (i === index) {
        button.setAttribute('aria-current', 'true');
      } else {
        button.removeAttribute('aria-current');
      }
    });
  };

  buttons.forEach((button, i) => {
    button.addEventListener('click', () => {
      items[i].scrollIntoView({
        behavior: reducedMotion() ? 'auto' : 'smooth',
        block: 'nearest',
        inline: 'start',
      });
    });
  });

  // The item mostly inside the track is the current one.
  const observer = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) setCurrent(items.indexOf(entry.target));
      });
    },
    { root: track, threshold: 0.6 }
  );
  items.forEach((item) => observer.observe(item));

  nav.hidden = false;
}

document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-scroll-dots]').forEach(setup);
});
