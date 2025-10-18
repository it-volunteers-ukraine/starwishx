function detectMouse() {
    return window.matchMedia("(pointer: fine)").matches;
}

console.log('detect mouse: ', detectMouse());

const noMouse = !detectMouse();

const items = document.querySelectorAll("#item"); // лучше использовать класс
const screenHeight = window.innerHeight;

items.forEach((item) => {
    if (noMouse) {
        console.log('no mouse detected, using scroll effect');
        // отслеживаем скролл всей страницы
        window.addEventListener('scroll', () => {
            console.log('scroll detected');
            const rect = item.getBoundingClientRect();
            const itemTop = rect.top;
            const itemBottom = rect.bottom;
            const itemHeight = rect.height;

            // проверяем, находится ли элемент в центре экрана, и центр экрана внутри элемента
            if (itemTop < screenHeight / 2 && itemBottom > screenHeight / 2) {
                // элемент в центре экрана
                item.setAttribute('data-active', 'true');
                // item.classList.add('active');
            } else {
                // элемент не в центре экрана
                item.removeAttribute('data-active');
                item.classList.remove('active');
            }
           
        });
    }
});
