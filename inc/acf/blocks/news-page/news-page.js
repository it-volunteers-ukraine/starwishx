(function () {
    if (typeof Swiper === 'undefined') {
        console.error('Swiper not loaded');
        return;
    }

    document.querySelectorAll('.mySwiper').forEach(function (el) {
        if (el.swiper) return;

        new Swiper(el, {
            slidesPerView: 1,
            spaceBetween: 20,
            loop: true,
            pagination: {
                el: el.querySelector('.swiper-pagination'),
                clickable: true,
            },
        });
    });

    console.log('news-page.js loaded');
})();
