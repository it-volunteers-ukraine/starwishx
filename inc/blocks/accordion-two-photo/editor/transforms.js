/**
 * "Transform to → Accordion two photo" for the legacy ACF blocks.
 *
 * ACF stores repeater rows flat in the block comment's `data` attribute:
 * `accordion` = row count, `accordion_{i}_title|text|photo1|photo2` per row
 * (photos as attachment IDs). One child block per row, no re-entry. The text
 * is passed through untouched — the ACF template echoed it raw, and the new
 * render.php sanitises it with wp_kses.
 *
 * File: inc/blocks/accordion-two-photo/editor/transforms.js
 */

import { createBlock } from "@wordpress/blocks";

import { ITEM, PARENT } from "./names.js";

const str = (value) => (value == null ? "" : String(value));
const int = (value) => {
  const n = parseInt(value, 10);
  return Number.isFinite(n) && n > 0 ? n : 0;
};

export default {
  from: [
    {
      type: "block",
      blocks: ["acf/accordion-two-photo-compact", "acf/accordion-two-photo"],
      transform: (attributes) => {
        const data = attributes?.data || {};
        const items = Array.from({ length: int(data.accordion) }, (_, i) =>
          createBlock(ITEM, {
            title: str(data[`accordion_${i}_title`]),
            text: str(data[`accordion_${i}_text`]),
            photo1Id: int(data[`accordion_${i}_photo1`]),
            photo2Id: int(data[`accordion_${i}_photo2`]),
          }),
        );

        return createBlock(PARENT, {}, items);
      },
    },
  ],
};
