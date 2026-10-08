/**
 * starwishx/accordion-two-photo — editor script (parent + item).
 *
 * Compiled by gulp (nativeBlockEditorScripts) into build/editor.js with a
 * build/editor.asset.php next to it; block.json "editorScript" points at the
 * bundle. @wordpress/* imports resolve to the wp.* globals WordPress already
 * loads in the editor. Both blocks are registered server-side from their
 * block.json (title, attributes, parent, supports), so this file only adds
 * what needs JavaScript: the edit components, the InnerBlocks save and the
 * transform from the legacy ACF block.
 *
 * The editor markup mirrors render.php (same BEM classes) so build/style.css
 * makes the canvas WYSIWYG: section > .container > ol.accordion-two-photo__list
 * > li items.
 *
 * File: inc/blocks/accordion-two-photo/editor.js
 */

import { registerBlockType } from "@wordpress/blocks";
import {
  InnerBlocks,
  useBlockProps,
  useInnerBlocksProps,
} from "@wordpress/block-editor";

import { ITEM, PARENT } from "./editor/names.js";
import ItemEdit from "./editor/item.js";
import transforms from "./editor/transforms.js";

function ParentEdit() {
  const blockProps = useBlockProps({ className: "section accordion-two-photo" });
  const listProps = useInnerBlocksProps(
    { className: "accordion-two-photo__list" },
    {
      allowedBlocks: [ITEM],
      template: [[ITEM]],
      renderAppender: InnerBlocks.ButtonBlockAppender,
    },
  );

  return (
    <section {...blockProps}>
      <div className="container accordion-two-photo__container">
        <ol {...listProps} />
      </div>
    </section>
  );
}

registerBlockType(PARENT, {
  edit: ParentEdit,
  // Dynamic block: render.php builds the markup, the post only stores the items.
  save: () => <InnerBlocks.Content />,
  transforms,
});

registerBlockType(ITEM, {
  edit: ItemEdit,
  save: () => null,
});
