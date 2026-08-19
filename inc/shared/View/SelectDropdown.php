<?php

/**
 * Dropdown of option links, styled to look like a select.
 *
 * Built on <details>/<summary>, so it needs no JavaScript: the browser owns
 * open/close, keyboard interaction and the accessibility tree, and every
 * option is a plain link that works with scripting disabled. That matters for
 * controls that only change a URL — items per page, sort order — where a real
 * <select> would need a form and a submit-on-change handler.
 *
 * The current option renders as a <span> rather than a link: navigating to the
 * page you are already on is a wasted round trip.
 *
 * File: inc/shared/View/SelectDropdown.php
 */

declare(strict_types=1);

namespace Shared\View;

class SelectDropdown
{
    private static int $counter = 0;

    /**
     * @param array $options {
     *     Ordered list of choices.
     *
     *     @type string $label      Visible text.
     *     @type string $url        Target URL. Ignored when is_current is true.
     *     @type bool   $is_current Marks the active choice.
     * }
     * @param array $args {
     *     @type string $label     Accessible name for the control. Required.
     *     @type string $summary   Visible summary text. Defaults to the current option's label.
     *     @type string $class     Extra class on the <details> (e.g. 'sw-select--perpage').
     * }
     */
    public static function render(array $options, array $args = []): string
    {
        $options = array_values(array_filter(
            $options,
            static fn(array $option): bool => ($option['label'] ?? '') !== ''
        ));

        if (count($options) < 2) {
            return '';
        }

        $label = (string) ($args['label'] ?? '');
        $extra = (string) ($args['class'] ?? '');

        $current = null;
        foreach ($options as $option) {
            if (!empty($option['is_current'])) {
                $current = $option;
                break;
            }
        }

        $summary = (string) ($args['summary'] ?? ($current['label'] ?? $options[0]['label']));

        $id    = 'sw-select-' . ++self::$counter;
        $items = '';

        foreach ($options as $option) {
            $isCurrent = !empty($option['is_current']);

            $items .= sprintf(
                '<li class="sw-select__item">%s</li>',
                $isCurrent
                    ? sprintf(
                        '<span class="sw-select__option is-current" aria-current="true">%s</span>',
                        esc_html((string) $option['label'])
                    )
                    : sprintf(
                        '<a class="sw-select__option" href="%s">%s</a>',
                        esc_url((string) ($option['url'] ?? '#')),
                        esc_html((string) $option['label'])
                    )
            );
        }

        return sprintf(
            '<details class="sw-select %1$s">
                <summary class="sw-select__summary" aria-label="%2$s">
                    <span class="sw-select__value">%3$s</span>
                    %4$s
                </summary>
                <ul class="sw-select__list" id="%5$s">%6$s</ul>
            </details>',
            esc_attr($extra),
            esc_attr(trim($label . ': ' . $summary, ': ')),
            esc_html($summary),
            sw_svg('icon-arrow_down', 24, null, 'sw-select__chevron'),
            esc_attr($id),
            $items
        );
    }
}
