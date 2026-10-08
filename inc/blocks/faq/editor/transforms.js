/**
 * "Transform to → FAQ" for the legacy ACF block.
 *
 * ACF stores the block fields and the repeater rows flat in the block
 * comment's `data` attribute: `suptitle`, `title`, `questions` = row count and
 * per row `questions_{i}_question` / `questions_{i}_answer` (a textarea). Each
 * row becomes a question item whose answer is one core paragraph per
 * blank-line-separated chunk, single line breaks kept as <br>.
 *
 * File: inc/blocks/faq/editor/transforms.js
 */

import { createBlock } from "@wordpress/blocks";

import { ITEM, PARENT } from "./names.js";

const str = (value) => (value == null ? "" : String(value));
const int = (value) => {
  const n = parseInt(value, 10);
  return Number.isFinite(n) && n > 0 ? n : 0;
};

function answerBlocks(text) {
  const chunks = str(text)
    .replace(/\r\n?/g, "\n")
    .split(/\n{2,}/)
    .map((chunk) => chunk.trim())
    .filter(Boolean);

  return chunks.map((chunk) =>
    createBlock("core/paragraph", { content: chunk.replace(/\n/g, "<br>") }),
  );
}

export default {
  from: [
    {
      type: "block",
      blocks: ["acf/faq"],
      transform: (attributes) => {
        const data = attributes?.data || {};
        const items = Array.from({ length: int(data.questions) }, (_, i) =>
          createBlock(
            ITEM,
            { question: str(data[`questions_${i}_question`]) },
            answerBlocks(data[`questions_${i}_answer`]),
          ),
        );

        return createBlock(
          PARENT,
          {
            suptitle: str(data.suptitle),
            title: str(data.title),
            openFirst: true,
          },
          items,
        );
      },
    },
  ],
};
