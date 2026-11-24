<?php
// file: inc/gateway/Enums/GatewayView.php
declare(strict_types=1);

namespace Gateway\Enums;

/**
 * The Gateway's built-in views: the FormRegistry ids, the `?view=` values,
 * and the TS `FormId` union (inc/gateway/Assets/types.ts) - change them
 * together.
 *
 * Every Gateway URL is built here: the redirects from wp-login.php, the
 * login/register/lost-password URL filters, the links in the emails and the
 * store's baseUrl.
 */
enum GatewayView: string
{
    case LOGIN          = 'login';
    case REGISTER       = 'register';
    case LOST_PASSWORD  = 'lost-password';
    case RESET_PASSWORD = 'reset-password';

    /** The Gateway page; ManagedPage keeps it at this slug. */
    public const string PATH = '/gateway/';

    /**
     * The view's URL. Login is the page itself (no ?view). `$query` values
     * are added as given: encode them first (add_query_arg() doesn't).
     *
     * @param array<string, string> $query
     */
    public function url(array $query = []): string
    {
        $args = $this === self::LOGIN ? $query : ['view' => $this->value, ...$query];

        return $args === []
            ? home_url(self::PATH)
            : add_query_arg($args, home_url(self::PATH));
    }

    /**
     * The link that sets a password with a reset key: the lost-password
     * email and the activation email after registering.
     */
    public static function resetUrl(string $key, string $login): string
    {
        return self::RESET_PASSWORD->url([
            'key'   => rawurlencode($key),
            'login' => rawurlencode($login),
        ]);
    }
}
