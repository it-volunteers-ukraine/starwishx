function detectMouse() {
    return window.matchMedia("(pointer: fine)").matches;
}


const noMouse = !detectMouse();

// const sliderss = document.querySelectorAll("#slider-one-photo"); // лучше использовать класс
const items = document.getElementById("slider-one-photo").children; // лучше использовать класс
console.log('items: ', items)
const screenHeight = window.innerHeight;

function setHeight(item, index = 'undef') {
    console.log('call setHeight!')
    const itemContent = item.children[1];
    const itemContentText = itemContent.children[0];

    let curTextHeight = itemContentText.style.minHeight
    itemContentText.style.minHeight = 0;
    const textHeight = itemContentText.scrollHeight + "px";

    if (curTextHeight !== textHeight) {
        console.log("Set new height!");
        console.log("index: ", index, "textHeight: ", textHeight);
        itemContent.style.maxHeight = textHeight;
        itemContentText.style.minHeight = textHeight;
    } else {
        itemContentText.style.minHeight = curTextHeight;
    }

}

requestAnimationFrame(() => {
    Array.from(items).forEach((item, index, array) => {

        setHeight(item, index);

        if (noMouse) {
            console.log('no mouse detected, using scroll effect');

            const itemChildren = item.children[1]//.children[0];
            // console.log("itemChildren", itemChildren);

            const elemScrollHeight = itemChildren.scrollHeight;
            console.log('index:', index, 'elemScrollHeight: ', elemScrollHeight);
            const elemOffsetHeight = itemChildren.offsetHeight;
            console.log('index:', index, 'elemOffsetHeight: ', elemOffsetHeight);
            // const elemClientHeight = itemChildren.clientHeight;
            // console.log('index:', index, 'elemClientHeight: ', elemClientHeight);
            // const elemComputedStyle = getComputedStyle(itemChildren).height;
            // console.log('index:', index, 'elemComputedStyle: ', elemComputedStyle);


            // отслеживаем скролл всей страницы
            window.addEventListener('scroll', () => {
                // console.log('scroll detected');
                const rect = item.getBoundingClientRect();
                const itemTop = rect.top;
                const itemScrolY = rect.top + window.scrollY;
                const itemBottom = rect.bottom;
                const itemHeight = rect.height;
                // console.log('itemTop:', itemTop, 'itemBottom:', itemBottom, 'itemHeight:', itemHeight);

                // проверяем, находится ли элемент в центре экрана, и центр экрана внутри элемента
                if (itemTop < screenHeight / 2 && itemBottom > screenHeight / 2) {
                    // элемент в центре экрана

                    if (!item.hasAttribute('data-active')) {
                        setHeight(item, index);
                        item.setAttribute('data-active', 'true');
                    }
                    // item.classList.add('active');
                } else {
                    // элемент не в центре экрана
                    // setHeight(item);
                    if (item.hasAttribute('data-active')) {
                        const heightContent = item.children[1].scrollHeight;
                        const offsettop = itemScrolY - window.innerHeight / 2 + heightContent / 2;
                        console.log('index:', index, 'heightContent:', heightContent, "offsettop:", offsettop);

                        item.removeAttribute('data-active');
                        setTimeout(() => {
                            // window.scrollTo({top: offsettop, behavior: 'smooth'});
                        }, 300);
                        // item.classList.remove('active');
                    }
                    // item.removeAttribute('data-active');
                    // item.classList.remove('active');
                }

            });
        }

        if (!noMouse) {
            console.log("!nomouse")
            item.addEventListener('mouseenter', () => {

                setHeight(item);
            })
        }
    });

});
