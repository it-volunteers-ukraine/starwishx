document.addEventListener('DOMContentLoaded', function () {
    const swiperContainer = document.querySelector('.projects-swiper');
    
    if (!swiperContainer) {
        return;
    }

    const swiper = new Swiper('.projects-swiper', {
        slidesPerView: 1.1,
        spaceBetween: 10,
        speed: 600,
        loop: false,
        watchOverflow: true,
        autoHeight: true,
        navigation: {
            nextEl: '.arrow-right',
            prevEl: '.arrow-left',
        },
        breakpoints: {
            420: {
                slidesPerView: 1.2,
                spaceBetween: 10,
            },
            540: {
                slidesPerView: 1.6,
                spaceBetween: 10,
            },
            768: {
                slidesPerView: 2.1,
                spaceBetween: 15,
            },
            1024: {
                slidesPerView: 3.1,
                spaceBetween: 20,
            },
            1350: {
                slidesPerView: 4.1,
                spaceBetween: 20,
            },
        },
    });
});
