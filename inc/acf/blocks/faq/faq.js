document.addEventListener("DOMContentLoaded", function () {
  const SELECTORS = {
    ACCORDION: ".accordion",
    ITEM_HEADER: ".accordion-item-header",
    ITEM: ".accordion-item",
    DESCRIPTION_WRAPPER: ".accordion-item-description-wrapper",
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

      // Initialize state - ONLY use aria-expanded now
      const headerAriaExpanded = header.getAttribute("aria-expanded");
      const initiallyOpen = headerAriaExpanded === "true";

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

      function setOpenState(open) {
        // Calculate heights before changing ARIA attributes
        if (open) {
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

          if (currentlyOpenItem === item) {
            currentlyOpenItem = null;
          }
        }

        // Update ARIA attributes (this triggers CSS changes)
        header.setAttribute("aria-expanded", open ? "true" : "false");
        panel.setAttribute("aria-hidden", open ? "false" : "true");
      }

      function handleTransitionEnd() {
        // Only cleanup max-height if the item is expanded
        if (header.getAttribute("aria-expanded") === "true") {
          panel.style.maxHeight = "none";
        }
      }

      panel.addEventListener("transitionend", handleTransitionEnd);

      function toggle() {
        // Check state using ARIA attribute only
        const nowOpen = header.getAttribute("aria-expanded") === "false";

        // Close previously open item if opening a new one
        if (nowOpen && currentlyOpenItem && currentlyOpenItem !== item) {
          const currentHeader = currentlyOpenItem.querySelector(
            SELECTORS.ITEM_HEADER
          );
          const currentPanel = currentlyOpenItem.querySelector(
            SELECTORS.DESCRIPTION_WRAPPER
          );

          if (currentHeader && currentPanel) {
            // Close the previously open item using ARIA only
            currentHeader.setAttribute("aria-expanded", "false");
            currentPanel.setAttribute("aria-hidden", "true");

            const currentHeight = currentPanel.scrollHeight;
            currentPanel.style.maxHeight = currentHeight + "px";
            currentPanel.style.transition = TRANSITION;

            requestAnimationFrame(() => {
              currentPanel.style.maxHeight = "0px";
            });

            // Update tracking
            currentlyOpenItem = null;
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
            // Check state using ARIA attribute
            if (header.getAttribute("aria-expanded") === "false") {
              toggle();
            }
            break;
          case "ArrowLeft":
            e.preventDefault();
            // Check state using ARIA attribute
            if (header.getAttribute("aria-expanded") === "true") {
              toggle();
            }
            break;
        }
      });
    });
  });
});
