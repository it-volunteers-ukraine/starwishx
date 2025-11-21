document.addEventListener("DOMContentLoaded", function () {
    var swiper = new Swiper(".mySwiper", {
        slidesPerView: 1,
        spaceBetween: 20,
        pagination: {
            el: ".swiper-paginations",
            clickable: true,
        },
        loop: true, // если нужен бесконечный скролл
    }, 100);
});

сonsole.log('news-page.js loaded');