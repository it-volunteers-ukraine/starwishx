/**
 * Media Library picker for block attributes marked { "control": "media" }
 * (integer attachment ID, default 0) in block.json.
 *
 * PHP-only (supports.autoRegister) blocks get their inspector fields generated
 * by core, which has no media control. This adds an InspectorControls "Media"
 * panel to any block declaring such attributes; every other block is passed
 * through untouched. No build step: wp.* globals only, served from source by
 * Blocks\Core\BlocksCore::enqueueEditorAssets(), which also injects the UI
 * strings as window.starwishxBlockEditor (PHP is the i18n authority).
 *
 * File: inc/blocks/Assets/editor-media-attributes.js
 */
(function (wp) {
  "use strict";

  var config = window.starwishxBlockEditor || {};
  var MARKER = config.mediaControl || "media";
  var i18n = config.i18n || {};

  var addFilter = wp.hooks.addFilter;
  var createHigherOrderComponent = wp.compose.createHigherOrderComponent;
  var el = wp.element.createElement;
  var Fragment = wp.element.Fragment;
  var getBlockType = wp.blocks.getBlockType;
  var InspectorControls = wp.blockEditor.InspectorControls;
  // Filled in by wp-editor through the editor.MediaUpload filter; withFilters
  // re-renders once the filter lands, so no dependency on wp-editor is needed.
  var MediaUpload = wp.blockEditor.MediaUpload;
  var MediaUploadCheck = wp.blockEditor.MediaUploadCheck;
  var PanelBody = wp.components.PanelBody;
  var BaseControl = wp.components.BaseControl;
  var Button = wp.components.Button;
  var Flex = wp.components.Flex;
  var FlexItem = wp.components.FlexItem;
  var useSelect = wp.data.useSelect;

  /** [ [attributeName, definition], ... ] for the attributes carrying the marker. */
  function getMediaAttributes(blockName) {
    var blockType = getBlockType(blockName);
    var attributes = (blockType && blockType.attributes) || {};

    return Object.keys(attributes)
      .filter(function (name) {
        return attributes[name] && attributes[name].control === MARKER;
      })
      .map(function (name) {
        return [name, attributes[name]];
      });
  }

  function MediaAttributeControl(props) {
    var attrName = props.attrName;
    var id = Number(props.value) || 0;
    var setAttributes = props.setAttributes;
    var label = props.def.label || attrName;

    var media = useSelect(
      function (select) {
        return id ? select("core").getMedia(id) : null;
      },
      [id],
    );
    var sizes = (media && media.media_details && media.media_details.sizes) || {};
    var thumb =
      (sizes.medium && sizes.medium.source_url) ||
      (sizes.thumbnail && sizes.thumbnail.source_url) ||
      (media && media.source_url);

    function update(value) {
      var next = {};
      next[attrName] = value;
      setAttributes(next);
    }

    return el(
      BaseControl,
      { __nextHasNoMarginBottom: true },
      el(BaseControl.VisualLabel, null, label),
      el(
        MediaUploadCheck,
        { fallback: el("p", null, i18n.noPermission || "") },
        el(MediaUpload, {
          title: label,
          allowedTypes: ["image"],
          value: id,
          onSelect: function (item) {
            update(Number(item && item.id) || 0);
          },
          render: function (renderProps) {
            return el(
              Fragment,
              null,
              id && thumb
                ? el("img", {
                    src: thumb,
                    alt: (media && media.alt_text) || "",
                    style: {
                      display: "block",
                      maxWidth: "100%",
                      height: "auto",
                      marginBottom: "8px",
                      borderRadius: "2px",
                    },
                  })
                : null,
              el(
                Flex,
                { justify: "flex-start", gap: 2 },
                el(
                  FlexItem,
                  null,
                  el(
                    Button,
                    { variant: "secondary", size: "compact", onClick: renderProps.open },
                    id ? i18n.replace : i18n.select,
                  ),
                ),
                id
                  ? el(
                      FlexItem,
                      null,
                      el(
                        Button,
                        {
                          variant: "tertiary",
                          size: "compact",
                          isDestructive: true,
                          onClick: function () {
                            update(0);
                          },
                        },
                        i18n.remove,
                      ),
                    )
                  : null,
              ),
            );
          },
        }),
      ),
    );
  }

  var withMediaAttributes = createHigherOrderComponent(function (BlockEdit) {
    return function MediaAttributesBlockEdit(props) {
      var mediaAttributes = getMediaAttributes(props.name);

      if (!mediaAttributes.length) {
        return el(BlockEdit, props); // core, ACF and every other block: untouched
      }

      return el(
        Fragment,
        null,
        el(BlockEdit, props),
        // Only the selected block mounts the controls (and their getMedia fetches).
        props.isSelected &&
          el(
            InspectorControls,
            null,
            el(
              PanelBody,
              { title: i18n.panelTitle || "Media", initialOpen: true },
              mediaAttributes.map(function (entry) {
                return el(MediaAttributeControl, {
                  key: entry[0],
                  attrName: entry[0],
                  def: entry[1],
                  value: props.attributes[entry[0]],
                  setAttributes: props.setAttributes,
                });
              }),
            ),
          ),
      );
    };
  }, "withMediaAttributes");

  addFilter("editor.BlockEdit", "starwishx/media-attributes", withMediaAttributes);
})(window.wp);
