<?php

/**
 * Legacy acf/breadcrumbs block — adapter only.
 *
 * Breadcrumbs are site chrome now: every template prints the trail with
 * sw_breadcrumbs() (Shared\Breadcrumbs\Trail + template-parts/breadcrumbs.php).
 * This block survives only because some page content still embeds it; it
 * delegates to the same helper, which renders once per request, so after the
 * template's trail an embedded copy prints nothing. Delete the block once no
 * content uses it.
 *
 * File: inc/acf/blocks/breadcrumbs/breadcrumbs.php
 */

declare(strict_types=1);

sw_breadcrumbs((bool) ($block['data']['show_last_item'] ?? true));
