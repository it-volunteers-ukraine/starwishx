/**
 * starwishx/animated-text — editor script.
 *
 * Compiled by gulp (nativeBlockEditorScripts) into build/editor.js (+ the
 * .asset.php manifest); block.json "editorScript" points at the bundle. The
 * block is registered server-side from block.json; this adds the InnerBlocks
 * edit/save (paragraphs and headings inside the tinted section) and the
 * transform from the legacy ACF block, whose WYSIWYG HTML rawHandler() turns
 * into core blocks (a centred paragraph keeps its alignment).
 *
 * File: inc/blocks/animated-text/editor.js
 */

import { createBlock, rawHandler, registerBlockType } from "@wordpress/blocks";
import {
  InnerBlocks,
  useBlockProps,
  useInnerBlocksProps,
} from "@wordpress/block-editor";

const NAME = "starwishx/animated-text";
const ALLOWED = ["core/paragraph", "core/heading"];

function Edit() {
  const blockProps = useBlockProps({ className: "section animated-text" });
  const contentProps = useInnerBlocksProps(
    { className: "animated-text__content" },
    {
      allowedBlocks: ALLOWED,
      template: [["core/paragraph", { align: "center" }]],
    },
  );

  return (
    <section {...blockProps}>
      <div className="container">
        <div {...contentProps} />
      </div>
    </section>
  );
}

registerBlockType(NAME, {
  edit: Edit,
  // Dynamic block: render.php builds the section, the post stores the inner blocks.
  save: () => <InnerBlocks.Content />,
  transforms: {
    from: [
      {
        type: "block",
        blocks: ["acf/animated-text"],
        transform: (attributes) => {
          const html = String(attributes?.data?.text ?? "");
          const inner = html.trim()
            ? rawHandler({ HTML: html }).filter((block) =>
                ALLOWED.includes(block.name),
              )
            : [];
          return createBlock(NAME, {}, inner);
        },
      },
    ],
  },
});
