/**
 * Block names shared by the editor script, the item edit component and the
 * ACF transform. The server registers both blocks from block.json; JS only
 * adds edit/save/transforms by name.
 *
 * File: inc/blocks/faq/editor/names.js
 */

export const PARENT = "starwishx/faq";
export const ITEM = "starwishx/faq-item";

/** Blocks an answer may contain. */
export const ANSWER_BLOCKS = ["core/paragraph", "core/list"];
