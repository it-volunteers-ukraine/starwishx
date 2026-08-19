/**
 * Back-to-top control.
 *
 * Lives in the app bundle rather than an Interactivity store: it is needed on
 * every page, and a scroll listener plus one class toggle does not justify
 * loading a store on pages that otherwise ship no module.
 */

document.addEventListener('DOMContentLoaded', () => {
  const button = document.getElementById('scroll-top');
  if (!button) return;

  // Roughly one viewport — far enough that "back to top" is worth offering
  const threshold = () => window.innerHeight * 0.8;

  let ticking = false;

  const sync = () => {
    button.classList.toggle('is-visible', window.scrollY > threshold());
    ticking = false;
  };

  // rAF-throttled: scroll fires far more often than the class can change
  window.addEventListener(
    'scroll',
    () => {
      if (!ticking) {
        ticking = true;
        window.requestAnimationFrame(sync);
      }
    },
    { passive: true }
  );

  button.addEventListener('click', () => {
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    window.scrollTo({ top: 0, behavior: reduced ? 'auto' : 'smooth' });
  });

  sync();
});
