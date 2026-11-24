<?php
// file: inc/launchpad/Enums/OpportunityView.php
declare(strict_types=1);

namespace Launchpad\Enums;

/**
 * The opportunities panel's routing (?view): the list, or the form.
 *
 * The values are the TS `OpportunityView` union (inc/launchpad/Assets/
 * types.ts) and what the client writes to ?view: change them together.
 */
enum OpportunityView: string
{
    case LIST = 'list';
    case ADD  = 'add';
    case EDIT = 'edit';

    /**
     * The view the request's ?view asks for; anything else is the list.
     */
    public static function fromRequest(): self
    {
        $view = isset($_GET['view']) ? sanitize_key(wp_unslash($_GET['view'])) : '';
        return self::tryFrom($view) ?? self::LIST;
    }

    public function isForm(): bool
    {
        return $this !== self::LIST;
    }
}
