/**
 * starwishx/faq-item — edit component.
 *
 * The question is edited inline (RichText as the h3 render.php emits); the
 * answer is a small InnerBlocks area limited to paragraphs and lists — the
 * same blocks the FAQPage schema renders. Plain divs stand in for
 * <details>/<summary> so every answer stays visible while editing.
 *
 * File: inc/blocks/faq/editor/item.js
 */

import {
  RichText,
  useBlockProps,
  useInnerBlocksProps,
} from "@wordpress/block-editor";
import { __ } from "@wordpress/i18n";

import { ANSWER_BLOCKS } from "./names.js";

export default function ItemEdit({ attributes, setAttributes }) {
  const { question } = attributes;

  const blockProps = useBlockProps({ className: "faq__item" });
  const answerProps = useInnerBlocksProps(
    { className: "faq__answer" },
    {
      allowedBlocks: ANSWER_BLOCKS,
      template: [["core/paragraph", { placeholder: __("Answer", "starwishx") }]],
    },
  );
  // Provided by BlocksCore (inline data of the shared editor helper); read at
  // render time so script order never matters.
  const spriteUrl = window.starwishxBlockEditor?.spriteUrl;

  return (
    <li {...blockProps}>
      <div className="faq__details">
        <div className="faq__summary">
          <RichText
            tagName="h3"
            className="faq__question"
            value={question}
            onChange={(value) => setAttributes({ question: value })}
            allowedFormats={[]}
            disableLineBreaks
            placeholder={__("Question", "starwishx")}
          />
          {spriteUrl && (
            <svg
              className="faq__icon"
              width="24"
              height="24"
              aria-hidden="true"
              focusable="false"
            >
              <use href={`${spriteUrl}#icon-plus`} />
            </svg>
          )}
        </div>
        <div {...answerProps} />
      </div>
    </li>
  );
}
