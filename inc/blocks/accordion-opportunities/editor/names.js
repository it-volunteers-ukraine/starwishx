/**
 * Block names shared by the editor script, the item edit component and the
 * ACF transform. The server registers both blocks from block.json; JS only
 * adds edit/save/transforms by name.
 *
 * File: inc/blocks/accordion-opportunities/editor/names.js
 */

export const PARENT = "starwishx/accordion-opportunities";
export const ITEM = "starwishx/accordion-opportunities-item";

/** Key of the inlined term list, mirrors BlocksCore::termOptionsKey(). */
export const TERM_LIST_KEY = "category-oportunities|parents";
