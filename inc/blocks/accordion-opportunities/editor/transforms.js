/**
 * "Transform to → Accordion opportunities" for the legacy ACF block.
 *
 * ACF stores the block fields and the repeater rows flat in the block
 * comment's `data` attribute: `suptitle`, `header`, `btn_text`,
 * `mode_click_for_touch`, `accordion` = row count and per row
 * `accordion_{i}_accordion_opportunity_category` (term ID),
 * `accordion_{i}_description`, `accordion_{i}_photo` (attachment ID).
 * `btn_page` holds a page ID, not a URL, so the button falls back to the
 * opportunities archive — which is what the home page links to anyway.
 *
 * File: inc/blocks/accordion-opportunities/editor/transforms.js
 */

import { createBlock } from "@wordpress/blocks";

import { ITEM, PARENT } from "./names.js";

const str = (value) => (value == null ? "" : String(value));
const int = (value) => {
  const n = parseInt(value, 10);
  return Number.isFinite(n) && n > 0 ? n : 0;
};
const bool = (value) => value === true || value === 1 || value === "1";

export default {
  from: [
    {
      type: "block",
      blocks: ["acf/accordion-opportunities"],
      transform: (attributes) => {
        const data = attributes?.data || {};
        const items = Array.from({ length: int(data.accordion) }, (_, i) =>
          createBlock(ITEM, {
            termId: int(data[`accordion_${i}_accordion_opportunity_category`]),
            description: str(data[`accordion_${i}_description`]),
            photoId: int(data[`accordion_${i}_photo`]),
          }),
        );

        return createBlock(
          PARENT,
          {
            suptitle: str(data.suptitle),
            header: str(data.header),
            buttonText: str(data.btn_text),
            buttonUrl: "",
            clickForTouch: bool(data.mode_click_for_touch),
          },
          items,
        );
      },
    },
  ],
};
