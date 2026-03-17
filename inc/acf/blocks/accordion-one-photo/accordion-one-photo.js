function detectMouse() {
  return window.matchMedia("(pointer: fine)").matches;
}

const slider = document.getElementById("slider-one-photo");
if (!slider) throw new Error("slider-one-photo not found");

const noMouse = !detectMouse();
const isClickModeForTouch = slider.dataset.clickMode === "true";
const items = slider.children;

function setHeight(item) {
  const itemContent = item.children[1];
  if (!itemContent) return;
  const itemContentText = itemContent.children[0];
  if (!itemContentText) return;

  let curTextHeight = itemContentText.style.minHeight;
  itemContentText.style.minHeight = 0;
  const textHeight = itemContentText.scrollHeight + "px";

  if (curTextHeight !== textHeight) {
    itemContent.style.maxHeight = textHeight;
    itemContentText.style.minHeight = textHeight;
  } else {
    itemContentText.style.minHeight = curTextHeight;
  }
}

requestAnimationFrame(() => {
  const itemsArray = Array.from(items);

  itemsArray.forEach((item) => {
    setHeight(item);
  });

  // Single scroll listener for touch devices (scroll-activated accordion)
  if (!isClickModeForTouch && noMouse) {
    window.addEventListener("scroll", () => {
      const screenHeight = window.innerHeight;

      itemsArray.forEach((item) => {
        const rect = item.getBoundingClientRect();

        if (rect.top < screenHeight / 2 && rect.bottom > screenHeight / 2) {
          if (!item.hasAttribute("data-active")) {
            setHeight(item);
            item.setAttribute("data-active", "true");
          }
        } else {
          if (item.hasAttribute("data-active")) {
            item.removeAttribute("data-active");
          }
        }
      });
    });
  }

  // Hover: recalculate height on mouseenter
  if (!noMouse) {
    itemsArray.forEach((item) => {
      item.addEventListener("mouseenter", () => {
        setHeight(item);
      });
    });
  }

  // Click mode for touch devices
  if (isClickModeForTouch && noMouse) {
    itemsArray.forEach((item, index) => {
      item.children[0].addEventListener("click", () => {
        itemsArray.forEach((el, elIndex) => {
          setHeight(el);
          if (index !== elIndex) {
            el.removeAttribute("data-active");
          } else {
            if (el.hasAttribute("data-active")) {
              el.removeAttribute("data-active");
            } else {
              el.setAttribute("data-active", "true");
            }
          }
        });
      });
    });
  }
});
