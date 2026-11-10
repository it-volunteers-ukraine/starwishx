function detectMouse() {
  return window.matchMedia("(pointer: fine)").matches;
}

const slider = document.getElementById("accordion-opportunities");
if (!slider) throw new Error("accordion-opportunities not found");

const noMouse = !detectMouse();
const isClickModeForTouch = slider.dataset.clickMode === "true";
const items = slider.children;

function setHeight(item) {
  const itemContent = item.children[1];
  if (!itemContent) return;
  const itemContentText = itemContent.children[0];
  if (!itemContentText) return;

  const prevMinHeight = itemContentText.style.minHeight;

  if (prevMinHeight !== "") {
    const existingScrollHeight = itemContentText.scrollHeight + "px";
    if (prevMinHeight === existingScrollHeight) return;
  }

  itemContentText.style.minHeight = "0";
  const textHeight = itemContentText.scrollHeight + "px";

  itemContent.style.maxHeight = textHeight;
  itemContentText.style.minHeight = textHeight;
}

requestAnimationFrame(() => {
  const itemsArray = Array.from(items);

  itemsArray.forEach((item) => {
    setHeight(item);
  });

  // IntersectionObserver for touch devices (scroll-activated accordion)
  // rootMargin "0% 0px -50% 0px" shrinks the observation area to a
  // zero-height line at viewport center — fires when an item straddles it.
  if (!isClickModeForTouch && noMouse) {
    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            setHeight(entry.target);
            entry.target.setAttribute("data-active", "true");
          } else {
            // Only collapse if item is BELOW viewport (not yet reached)
            // If it's above (top < 0), it already passed — leave it open
            if (entry.boundingClientRect.top >= 0) {
              entry.target.removeAttribute("data-active");
            }
          }
        });
      },
      { rootMargin: "0px 0px -25% 0px" },
    );

    itemsArray.forEach((item) => observer.observe(item));
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
