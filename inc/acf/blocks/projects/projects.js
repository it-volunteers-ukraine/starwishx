document.addEventListener('DOMContentLoaded', function () {
    const swiperContainer = document.querySelector('.projects-swiper');
    
    if (!swiperContainer) {
        return;
    }

    const swiper = new Swiper('.projects-swiper', {
        slidesPerView: 1,
        spaceBetween: 20,
        speed: 600,
        loop: false,
        watchOverflow: true,
        navigation: {
            nextEl: '.arrow-right',
            prevEl: '.arrow-left',
        },
        breakpoints: {
            420: {
                slidesPerView: 2,
                spaceBetween: 15,
            },
            768: {
                slidesPerView: 2,
                spaceBetween: 20,
            },
            1024: {
                slidesPerView: 3,
                spaceBetween: 20,
            },
            1350: {
                slidesPerView: 4,
                spaceBetween: 20,
            },
        },
    });
});
