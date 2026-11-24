<?php
// file: inc/launchpad/Enums/ProfileView.php
declare(strict_types=1);

namespace Launchpad\Enums;

/**
 * The profile panel's routing (?view): the card, the edit form or the
 * password form. inc/launchpad/Assets/core/actions.ts routes the same values.
 */
enum ProfileView: string
{
    case CARD     = '';
    case PROFILE  = 'profile';
    case PASSWORD = 'password';

    /**
     * The view the request's ?view asks for; anything else is the card.
     */
    public static function fromRequest(): self
    {
        $view = isset($_GET['view']) ? sanitize_key(wp_unslash($_GET['view'])) : '';
        return self::tryFrom($view) ?? self::CARD;
    }
}
