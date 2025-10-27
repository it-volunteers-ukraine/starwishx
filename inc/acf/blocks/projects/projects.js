document.addEventListener('DOMContentLoaded', () => {
  const container = document.querySelector('.projects-wrapper');
  const leftArrow = document.querySelector('.arrow-left');
  const rightArrow = document.querySelector('.arrow-right');
  if (!container || !leftArrow || !rightArrow) return;

  let isDown = false;
  let startX;
  let scrollLeft;

  const updateArrows = () => {
    const maxScroll = container.scrollWidth - container.clientWidth;
    leftArrow.classList.toggle('disabled', container.scrollLeft <= 10);
    rightArrow.classList.toggle('disabled', container.scrollLeft >= maxScroll - 10);
  };

  updateArrows();
  container.addEventListener('scroll', updateArrows);
  window.addEventListener('resize', updateArrows);

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
});
