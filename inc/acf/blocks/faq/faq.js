document.addEventListener("DOMContentLoaded", function () {
  document.querySelectorAll(".accordion-item-header").forEach((header) => {
    const item = header.closest(".accordion-item");
    if (!item) return;

    // prefer aria-controls -> find panel
    const controlsId = header.getAttribute("aria-controls");
    const panel = controlsId
      ? document.getElementById(controlsId)
      : item.querySelector(".accordion-item-description-wrapper");
    if (!panel) return;

    // If header isn't focusable, make it focusable.
    // Only add tabindex if it's missing (do not overwrite existing tabindex).
    if (!header.hasAttribute("tabindex")) {
      header.setAttribute("tabindex", "0");
    }

    // Ensure the header has role=button (if PHP already set it, do nothing).
    if (!header.hasAttribute("role")) {
      header.setAttribute("role", "button");
    }

    // Initialize panel aria-hidden based on aria-expanded or .open class
    const headerAriaExpanded = header.getAttribute("aria-expanded");
    const initiallyOpen =
      headerAriaExpanded === "true" || item.classList.contains("open");
    // Only set aria-expanded if it's missing (respect server-side value)
    if (!header.hasAttribute("aria-expanded")) {
      header.setAttribute("aria-expanded", initiallyOpen ? "true" : "false");
    } else {
      // ensure aria-expanded reflects the initial class if mismatch
      if ((header.getAttribute("aria-expanded") === "true") !== initiallyOpen) {
        header.setAttribute("aria-expanded", initiallyOpen ? "true" : "false");
      }
    }

    // Set aria-hidden on panel if missing, keep in sync
    if (!panel.hasAttribute("aria-hidden")) {
      panel.setAttribute("aria-hidden", initiallyOpen ? "false" : "true");
    } else {
      // ensure aria-hidden matches initiallyOpen
      panel.setAttribute("aria-hidden", initiallyOpen ? "false" : "true");
    }

    // Prepare for height transition (script won't overwrite existing inline styles unnecessarily)
    panel.style.overflow = "hidden";
    panel.style.transition = panel.style.transition || "max-height 0.28s ease";
    panel.style.maxHeight = initiallyOpen ? panel.scrollHeight + "px" : "0px";

    function setOpenState(open) {
      // toggle class on the item (your CSS depends on it)
      if (open) item.classList.add("open");
      else item.classList.remove("open");

      header.setAttribute("aria-expanded", open ? "true" : "false");
      panel.setAttribute("aria-hidden", open ? "false" : "true");

      if (open) {
        panel.style.maxHeight = panel.scrollHeight + "px";
      } else {
        // collapse: set current height then 0 to trigger transition
        panel.style.maxHeight = panel.scrollHeight + "px";
        window.requestAnimationFrame(() => {
          panel.style.maxHeight = "0px";
        });
      }
    }

    function toggle() {
      const nowOpen = item.classList.contains("open") ? false : true;
      setOpenState(nowOpen);
    }

    // click and keyboard handlers
    header.addEventListener("click", toggle);
    header.addEventListener("keydown", (e) => {
      if (e.key === "Enter" || e.key === " " || e.key === "Spacebar") {
        e.preventDefault();
        toggle();
      }
    });
  });
});
