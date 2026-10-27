/**
 * Listing Store — Filter Getters
 *
 * Derived state for the sidebar filter groups. Getters rather than callbacks
 * because archive-opportunity.php is rendered on the server too, and the
 * server only evaluates state and context: each has a PHP twin in
 * inc/listing/Core/DerivedState.php - keep the two in step.
 * 'this' refers to the state proxy.
 *
 * File: inc/listing/Assets/filters/getters.js
 */
import { getContext } from "@wordpress/interactivity";

export const filterGetters = {
  /**
   * "(n)" next to a filter group's title (context.filterId), or "".
   * Category counts a ticked parent once and ignores its children.
   */
  get filterCountLabel() {
    const filterId = getContext()?.filterId;
    if (!filterId) return "";

    const value = this.query[filterId];
    if (!value || (Array.isArray(value) && value.length === 0)) return "";
    if (typeof value === "string") return "(1)";

    if (filterId === "category") {
      const tree = this.facets["category-oportunities"] || [];
      let count = 0;
      for (const node of tree) {
        if (value.includes(node.id)) {
          count++;
          continue;
        }
        if (node.children?.length) {
          count += node.children.filter((c) => value.includes(c.id)).length;
        }
      }
      return count > 0 ? `(${count})` : "";
    }

    return `(${value.length})`;
  },

  /**
   * Does the filter group (context.filterId) have any selection?
   */
  get filterHasSelection() {
    const filterId = getContext()?.filterId;
    if (!filterId) return false;

    const value = this.query[filterId];
    return Array.isArray(value) ? value.length > 0 : !!value;
  },

  /**
   * Some, but not all, of a parent category's children are ticked - the
   * parent checkbox shows a dash.
   */
  get isPartiallySelected() {
    const item = getContext()?.item;
    if (!item?.children?.length) return false;

    const selected = this.query.category || [];
    const ticked = item.children.filter((c) => selected.includes(c.id)).length;
    return ticked > 0 && ticked < item.children.length;
  },

  /**
   * A parent category shows its children once it or any child is ticked.
   */
  get isParentExpanded() {
    const ctx = getContext();
    const item = ctx?.item;
    if (!item) return false;

    const selected = this.query[ctx.filterField || "category"] || [];
    return (
      selected.includes(item.id) ||
      !!item.children?.some((c) => selected.includes(c.id))
    );
  },

  /**
   * The count next to a parent category. While only some of its children
   * are ticked it is the distinct posts across them - selectedCount, which
   * the server computes for the current selection, so after a new tick it
   * catches up when the response arrives. Otherwise the parent's total.
   */
  get parentCount() {
    const item = getContext()?.item;
    if (!item) return 0;

    const selected = this.query.category || [];
    if (!item.children?.length || selected.includes(item.id)) return item.count;

    const someTicked = item.children.some((c) => selected.includes(c.id));
    return someTicked && item.selectedCount ? item.selectedCount : item.count;
  },
};
