/**
 * starwishx/accordion-two-photo-item — edit component.
 *
 * Inline editing where the content lives: RichText for the title (plain) and
 * the text (bold / italic / link, line breaks), and a MediaPlaceholder in each
 * empty photo slot. Replacing or removing a chosen photo happens in the shared
 * "Media" sidebar panel (the photo attributes carry "control": "media").
 * The markup mirrors render.php so build/style.css styles the canvas.
 *
 * File: inc/blocks/accordion-two-photo/editor/item.js
 */

import {
  MediaPlaceholder,
  RichText,
  useBlockProps,
} from "@wordpress/block-editor";
import { useSelect } from "@wordpress/data";
import { __, sprintf } from "@wordpress/i18n";

function Photo({ id, index, onSelect }) {
  const media = useSelect(
    (select) => (id ? select("core").getMedia(id) : null),
    [id],
  );

  if (!id) {
    return (
      <MediaPlaceholder
        className="accordion-two-photo__photo-placeholder"
        icon="format-image"
        labels={{
          /* translators: %d: photo slot number */
          title: sprintf(__("Photo %d", "starwishx"), index),
          instructions: __("Portrait photo, 3:4 works best.", "starwishx"),
        }}
        allowedTypes={["image"]}
        accept="image/*"
        onSelect={onSelect}
      />
    );
  }

  const sizes = media?.media_details?.sizes || {};
  const src = sizes.medium?.source_url || media?.source_url;

  if (!src) {
    return null; // media entity still loading
  }

  return (
    <img
      className={`accordion-two-photo__photo accordion-two-photo__photo--${index}`}
      src={src}
      alt={media?.alt_text || ""}
    />
  );
}

export default function ItemEdit({ attributes, setAttributes }) {
  const { title, text, photo1Id, photo2Id } = attributes;
  const blockProps = useBlockProps({ className: "accordion-two-photo__item" });
  // Provided by BlocksCore (inline data of the shared editor helper); read at
  // render time so script order never matters.
  const spriteUrl = window.starwishxBlockEditor?.spriteUrl;

  return (
    <li {...blockProps}>
      <div className="accordion-two-photo__heading">
        <RichText
          tagName="h2"
          className="h2-big accordion-two-photo__title"
          value={title}
          onChange={(value) => setAttributes({ title: value })}
          allowedFormats={[]}
          disableLineBreaks
          placeholder={__("Title", "starwishx")}
        />
      </div>
      <div className="accordion-two-photo__content">
        <RichText
          tagName="p"
          className="accordion-two-photo__text"
          value={text}
          onChange={(value) => setAttributes({ text: value })}
          allowedFormats={["core/bold", "core/italic", "core/link"]}
          placeholder={__("Text", "starwishx")}
        />
        <div className="accordion-two-photo__photos">
          <Photo
            id={photo1Id}
            index={1}
            onSelect={(media) =>
              setAttributes({ photo1Id: Number(media?.id) || 0 })
            }
          />
          <Photo
            id={photo2Id}
            index={2}
            onSelect={(media) =>
              setAttributes({ photo2Id: Number(media?.id) || 0 })
            }
          />
          {spriteUrl && (
            <svg
              className="accordion-two-photo__icon"
              width="24"
              height="24"
              aria-hidden="true"
              focusable="false"
            >
              <use href={`${spriteUrl}#icon-stars-gradient`} />
            </svg>
          )}
        </div>
      </div>
    </li>
  );
}
