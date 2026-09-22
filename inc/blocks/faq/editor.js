/**
 * starwishx/faq — editor script (parent + item).
 *
 * Compiled by gulp (nativeBlockEditorScripts) into build/editor.js (+ the
 * .asset.php manifest); block.json "editorScript" points at the bundle. Both
 * blocks are registered server-side from their block.json, so this file only
 * adds what needs JavaScript: the edit components, the InnerBlocks saves and
 * the transform from the legacy ACF block. The canvas mirrors render.php's
 * classes (with plain divs instead of <details>, so answers stay open while
 * editing) and build/style.css + build/editor.css make it WYSIWYG.
 *
 * File: inc/blocks/faq/editor.js
 */

import { registerBlockType } from "@wordpress/blocks";
import {
  InnerBlocks,
  InspectorControls,
  RichText,
  useBlockProps,
  useInnerBlocksProps,
} from "@wordpress/block-editor";
import { PanelBody, ToggleControl } from "@wordpress/components";
import { __ } from "@wordpress/i18n";

import { ITEM, PARENT } from "./editor/names.js";
import ItemEdit from "./editor/item.js";
import transforms from "./editor/transforms.js";

function ParentEdit({ attributes, setAttributes }) {
  const { suptitle, title, openFirst } = attributes;

  const blockProps = useBlockProps({ className: "section faq" });
  const listProps = useInnerBlocksProps(
    { className: "faq__list" },
    {
      allowedBlocks: [ITEM],
      template: [[ITEM]],
      renderAppender: InnerBlocks.ButtonBlockAppender,
    },
  );

  return (
    <>
      <InspectorControls>
        <PanelBody title={__("Settings", "starwishx")}>
          <ToggleControl
            __nextHasNoMarginBottom
            label={__("Open the first question", "starwishx")}
            help={__(
              "The first answer is visible when the page loads; the others open one at a time.",
              "starwishx",
            )}
            checked={!!openFirst}
            onChange={(value) => setAttributes({ openFirst: !!value })}
          />
        </PanelBody>
      </InspectorControls>

      <section {...blockProps}>
        <div className="container">
          <div className="faq__inner">
            <RichText
              tagName="p"
              className="faq__suptitle"
              value={suptitle}
              onChange={(value) => setAttributes({ suptitle: value })}
              allowedFormats={[]}
              disableLineBreaks
              placeholder={__("Label", "starwishx")}
            />
            <RichText
              tagName="h2"
              className="h2-big faq__title"
              value={title}
              onChange={(value) => setAttributes({ title: value })}
              allowedFormats={[]}
              disableLineBreaks
              placeholder={__("Title", "starwishx")}
            />
            <ol {...listProps} />
          </div>
        </div>
      </section>
    </>
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
  // The answer's core blocks must persist.
  save: () => <InnerBlocks.Content />,
});
