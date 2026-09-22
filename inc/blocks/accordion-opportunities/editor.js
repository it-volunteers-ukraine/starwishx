/**
 * starwishx/accordion-opportunities — editor script (parent + item).
 *
 * Compiled by gulp (nativeBlockEditorScripts) into build/editor.js (+ the
 * .asset.php manifest); block.json "editorScript" points at the bundle. Both
 * blocks are registered server-side from their block.json, so this file only
 * adds what needs JavaScript: the edit components, the InnerBlocks save and
 * the transform from the legacy ACF block. The canvas mirrors render.php
 * (same BEM classes) so build/style.css + build/editor.css make it WYSIWYG.
 *
 * File: inc/blocks/accordion-opportunities/editor.js
 */

import { registerBlockType } from "@wordpress/blocks";
import {
  InnerBlocks,
  InspectorControls,
  RichText,
  useBlockProps,
  useInnerBlocksProps,
} from "@wordpress/block-editor";
import { PanelBody, TextControl, ToggleControl } from "@wordpress/components";
import { __ } from "@wordpress/i18n";

import { ITEM, PARENT } from "./editor/names.js";
import ItemEdit from "./editor/item.js";
import transforms from "./editor/transforms.js";

function ParentEdit({ attributes, setAttributes }) {
  const { suptitle, header, buttonText, buttonUrl, clickForTouch } = attributes;

  const blockProps = useBlockProps({
    className: "section accordion-opportunities",
  });
  const listProps = useInnerBlocksProps(
    { className: "accordion-opportunities__list" },
    {
      allowedBlocks: [ITEM],
      template: [[ITEM]],
      renderAppender: InnerBlocks.ButtonBlockAppender,
    },
  );

  const buttonLabel = buttonText || __("Button", "starwishx");

  return (
    <>
      <InspectorControls>
        <PanelBody title={__("Settings", "starwishx")}>
          <TextControl
            __nextHasNoMarginBottom
            __next40pxDefaultSize
            label={__("Button text", "starwishx")}
            value={buttonText}
            onChange={(value) => setAttributes({ buttonText: value })}
          />
          <TextControl
            __nextHasNoMarginBottom
            __next40pxDefaultSize
            type="url"
            label={__("Button URL", "starwishx")}
            help={__("Empty: the opportunities archive.", "starwishx")}
            value={buttonUrl}
            onChange={(value) => setAttributes({ buttonUrl: value })}
          />
          <ToggleControl
            __nextHasNoMarginBottom
            label={__("Click for touch", "starwishx")}
            help={__(
              "On: on touch screens an item opens when its number is tapped. Off: items open as they scroll into view.",
              "starwishx",
            )}
            checked={!!clickForTouch}
            onChange={(value) => setAttributes({ clickForTouch: !!value })}
          />
        </PanelBody>
      </InspectorControls>

      <section {...blockProps}>
        <div className="container">
          <header className="accordion-opportunities__header">
            <RichText
              tagName="p"
              className="accordion-opportunities__suptitle"
              value={suptitle}
              onChange={(value) => setAttributes({ suptitle: value })}
              allowedFormats={[]}
              disableLineBreaks
              placeholder={__("Label", "starwishx")}
            />
            <div className="accordion-opportunities__heading">
              <RichText
                tagName="h2"
                className="h2-big accordion-opportunities__title"
                value={header}
                onChange={(value) => setAttributes({ header: value })}
                allowedFormats={[]}
                disableLineBreaks
                placeholder={__("Title", "starwishx")}
              />
              <span className="btn accordion-opportunities__button accordion-opportunities__button--header">
                {buttonLabel}
              </span>
            </div>
          </header>
          <ol {...listProps} />
          <span className="btn accordion-opportunities__button accordion-opportunities__button--footer">
            {buttonLabel}
          </span>
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
  save: () => null,
});
