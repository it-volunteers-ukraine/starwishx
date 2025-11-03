document.addEventListener("DOMContentLoaded", function () {
  const SELECTORS = {
    ACCORDION: ".accordion",
    ITEM_HEADER: ".accordion-item-header",
    ITEM: ".accordion-item",
    DESCRIPTION_WRAPPER: ".accordion-item-description-wrapper",
    OPEN: "open",
  };

  const TRANSITION = "max-height 0.3s ease";

  document.querySelectorAll(SELECTORS.ACCORDION).forEach((accordion) => {
    let currentlyOpenItem = null;

    accordion.querySelectorAll(SELECTORS.ITEM_HEADER).forEach((header) => {
      const item = header.closest(SELECTORS.ITEM);
      if (!item) return;

      const controlsId = header.getAttribute("aria-controls");
      const panel = controlsId
        ? document.getElementById(controlsId)
        : item.querySelector(SELECTORS.DESCRIPTION_WRAPPER);
      if (!panel) return;

      // Accessibility setup
      if (!header.hasAttribute("tabindex")) {
        header.setAttribute("tabindex", "0");
      }

      if (!header.hasAttribute("role")) {
        header.setAttribute("role", "button");
      }

      // Initialize state
      const headerAriaExpanded = header.getAttribute("aria-expanded");
      const initiallyOpen =
        headerAriaExpanded === "true" ||
        item.classList.contains(SELECTORS.OPEN);

      if (!header.hasAttribute("aria-expanded")) {
        header.setAttribute("aria-expanded", initiallyOpen ? "true" : "false");
      }

      if (!panel.hasAttribute("aria-hidden")) {
        panel.setAttribute("aria-hidden", initiallyOpen ? "false" : "true");
      }

      // Animation setup
      panel.style.overflow = "hidden";

      // Set initial state
      if (initiallyOpen) {
        panel.style.maxHeight = "none";
        currentlyOpenItem = item;
      } else {
        panel.style.maxHeight = "0px";
      }

      if (initiallyOpen) {
        currentlyOpenItem = item;
      }

      function setOpenState(open) {
        if (open) {
          item.classList.add(SELECTORS.OPEN);

          // Calculate actual height
          panel.style.maxHeight = "none";
          const height = panel.scrollHeight;

          // Animate from 0 to full height
          panel.style.maxHeight = "0px";
          panel.style.transition = TRANSITION;

          requestAnimationFrame(() => {
            panel.style.maxHeight = height + "px";
          });

          currentlyOpenItem = item;
        } else {
          const height = panel.scrollHeight;
          panel.style.maxHeight = height + "px";
          panel.style.transition = TRANSITION;

          requestAnimationFrame(() => {
            panel.style.maxHeight = "0px";
          });

          item.classList.remove(SELECTORS.OPEN);
          if (currentlyOpenItem === item) {
            currentlyOpenItem = null;
          }
        }

        header.setAttribute("aria-expanded", open ? "true" : "false");
        panel.setAttribute("aria-hidden", open ? "false" : "true");
      }

      function handleTransitionEnd() {
        if (item.classList.contains(SELECTORS.OPEN)) {
          panel.style.maxHeight = "none";
        }
      }

      panel.addEventListener("transitionend", handleTransitionEnd);

      function toggle() {
        const nowOpen = !item.classList.contains(SELECTORS.OPEN);

        // Close previously open item if opening a new one
        if (nowOpen && currentlyOpenItem && currentlyOpenItem !== item) {
          const currentHeader = currentlyOpenItem.querySelector(
            SELECTORS.ITEM_HEADER
          );
          const currentPanel = currentlyOpenItem.querySelector(
            SELECTORS.DESCRIPTION_WRAPPER
          );

          if (currentHeader && currentPanel) {
            currentlyOpenItem.classList.remove(SELECTORS.OPEN);
            currentHeader.setAttribute("aria-expanded", "false");
            currentPanel.setAttribute("aria-hidden", "true");

            const currentHeight = currentPanel.scrollHeight;
            currentPanel.style.maxHeight = currentHeight + "px";
            currentPanel.style.transition = TRANSITION;

            requestAnimationFrame(() => {
              currentPanel.style.maxHeight = "0px";
            });
          }
        }

        setOpenState(nowOpen);
      }

      // Event listeners
      header.addEventListener("click", toggle);
      header.addEventListener("keydown", (e) => {
        switch (e.key) {
          case "Enter":
          case " ":
          case "Spacebar":
            e.preventDefault();
            toggle();
            break;
          case "ArrowRight":
            e.preventDefault();
            if (!item.classList.contains(SELECTORS.OPEN)) {
              toggle();
            }
            break;
          case "ArrowLeft":
            e.preventDefault();
            if (item.classList.contains(SELECTORS.OPEN)) {
              toggle();
            }
            break;
        }
      });
    });
  });
});
