/**
 * starwishx/accordion-opportunities-item — edit component.
 *
 * The category is picked in the shared "Category" sidebar panel (the termId
 * attribute carries "control": "term"); its name is looked up in the term
 * list BlocksCore inlines, so the canvas needs no REST request. The
 * description is edited inline (RichText), the photo through a
 * MediaPlaceholder in the empty slot and the shared "Media" panel afterwards.
 * The markup mirrors render.php so build/style.css + build/editor.css style
 * the canvas (every item shown open).
 *
 * File: inc/blocks/accordion-opportunities/editor/item.js
 */

import {
  MediaPlaceholder,
  RichText,
  useBlockProps,
} from "@wordpress/block-editor";
import { useSelect } from "@wordpress/data";
import { __ } from "@wordpress/i18n";

import { TERM_LIST_KEY } from "./names.js";

function termName(termId) {
  const terms = window.starwishxBlockEditor?.terms?.[TERM_LIST_KEY] || [];
  const term = terms.find((entry) => Number(entry.id) === Number(termId));
  return term ? term.name : "";
}

function Photo({ id, onSelect }) {
  const media = useSelect(
    (select) => (id ? select("core").getMedia(id) : null),
    [id],
  );

  if (!id) {
    return (
      <MediaPlaceholder
        className="accordion-opportunities__photo-placeholder"
        icon="format-image"
        labels={{
          title: __("Photo", "starwishx"),
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
    <figure className="accordion-opportunities__photo">
      <img
        className="accordion-opportunities__image"
        src={src}
        alt={media?.alt_text || ""}
      />
    </figure>
  );
}

export default function ItemEdit({ attributes, setAttributes }) {
  const { termId, description, photoId } = attributes;
  const blockProps = useBlockProps({
    className: "accordion-opportunities__item",
  });
  const name = termName(termId);

  return (
    <li {...blockProps}>
      <div className="accordion-opportunities__heading-row">
        <span className="accordion-opportunities__toggle" aria-hidden="true" />
        <h3 className="h5 accordion-opportunities__item-title">
          {name || (
            <em>{__("Choose a category in the sidebar", "starwishx")}</em>
          )}
        </h3>
      </div>
      <div className="accordion-opportunities__content">
        <div className="accordion-opportunities__body">
          <RichText
            tagName="p"
            className="accordion-opportunities__description"
            value={description}
            onChange={(value) => setAttributes({ description: value })}
            allowedFormats={["core/bold", "core/italic", "core/link"]}
            placeholder={__("Description", "starwishx")}
          />
        </div>
        <Photo
          id={photoId}
          onSelect={(media) =>
            setAttributes({ photoId: Number(media?.id) || 0 })
          }
        />
      </div>
    </li>
  );
}
