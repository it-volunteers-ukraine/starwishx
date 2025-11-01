document.addEventListener('DOMContentLoaded', () => {
  const container = document.querySelector('[class*="projects-wrapper"]');
  const leftArrow = document.querySelector('[data-arrow="left"]');
  const rightArrow = document.querySelector('[data-arrow="right"]');
  if (!container || !leftArrow || !rightArrow) return;

  leftArrow.style.pointerEvents = 'auto';
  rightArrow.style.pointerEvents = 'auto';

  const updateArrows = () => {
    const maxScroll = container.scrollWidth - container.clientWidth;
    leftArrow.classList.toggle('disabled', container.scrollLeft <= 10);
    rightArrow.classList.toggle('disabled', container.scrollLeft >= maxScroll - 10);
  };

  const scrollAmount = 300;

  leftArrow.addEventListener('click', () => {
    container.scrollBy({ left: -scrollAmount, behavior: 'smooth' });
  });

  rightArrow.addEventListener('click', () => {
    container.scrollBy({ left: scrollAmount, behavior: 'smooth' });
  });

  updateArrows();
  container.addEventListener('scroll', updateArrows);
  window.addEventListener('resize', updateArrows);

  let isDown = false;
  let startX;
  let scrollLeft;

  container.addEventListener('mousedown', e => {
    isDown = true;
    startX = e.pageX - container.offsetLeft;
    scrollLeft = container.scrollLeft;
  });
  container.addEventListener('mouseleave', () => (isDown = false));
  container.addEventListener('mouseup', () => (isDown = false));
  container.addEventListener('mousemove', e => {
    if (!isDown) return;
    e.preventDefault();
    const x = e.pageX - container.offsetLeft;
    const walk = (x - startX) * 1.5;
    container.scrollLeft = scrollLeft - walk;
  });

  container.addEventListener('touchstart', e => {
    startX = e.touches[0].pageX;
    scrollLeft = container.scrollLeft;
  });
  container.addEventListener('touchmove', e => {
    const x = e.touches[0].pageX;
    const walk = (x - startX) * 1.2;
    container.scrollLeft = scrollLeft - walk;
  });

  if (window.matchMedia('(max-width: 420px)').matches) {
    const section = container.closest('[class*="projects-section"]');
    if (!section || container.scrollWidth <= container.clientWidth) return;

    let hasScrolled = false;

    const observer = new IntersectionObserver(([{ isIntersecting, intersectionRatio }]) => {
      if (isIntersecting && intersectionRatio >= 0.4 && !hasScrolled) {
        hasScrolled = true;
        observer.disconnect();

        setTimeout(() => {
          const target = container.scrollWidth - container.clientWidth;
          const start = container.scrollLeft;
          const duration = 2800;
          const startTime = performance.now();

          const animate = (now) => {
            const t = Math.min((now - startTime) / duration, 1);
            const ease = 1 - Math.pow(1 - t, 3);
            container.scrollLeft = start + (target - start) * ease;
            if (t < 1) requestAnimationFrame(animate);
          };

          requestAnimationFrame(animate);
        }, 200);
      }
    }, {
      threshold: [0.1, 0.2, 0.3, 0.4, 0.5, 0.6, 0.7, 0.8, 0.9, 1.0]
    });

    observer.observe(section);
  }
});