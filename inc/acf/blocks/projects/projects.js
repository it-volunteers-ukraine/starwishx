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
    if (section && container.scrollWidth > container.clientWidth) {
      let isAutoScrolling = false;

      const observer = new IntersectionObserver(([{ intersectionRatio }]) => {
        if (intersectionRatio >= 0.6 && !isAutoScrolling) {
          isAutoScrolling = true;
          observer.disconnect(); 

          setTimeout(() => {
            const targetScroll = container.scrollWidth - container.clientWidth;
            const duration = 2500; 
            const startTime = performance.now();
            const startScroll = container.scrollLeft;

            const animateScroll = (currentTime) => {
              if (isAutoScrolling) {
                const elapsed = currentTime - startTime;
                const progress = Math.min(elapsed / duration, 1);
                const easeOut = 1 - Math.pow(1 - progress, 3);
                const newScroll = startScroll + (targetScroll - startScroll) * easeOut;

                container.scrollLeft = newScroll;

                if (progress < 1) {
                  requestAnimationFrame(animateScroll);
                }
              }
            };

            requestAnimationFrame(animateScroll);
          }, 300); 
        }
      }, {
        threshold: Array.from({ length: 11 }, (_, i) => i / 10) 
      });

      observer.observe(section);
    }
  }
});