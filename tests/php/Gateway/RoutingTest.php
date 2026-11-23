<?php

/**
 * Where the Gateway sends people: its URLs (GatewayView), the WordPress
 * login/register/lost-password/logout URL filters, the wp-login.php
 * interception, and which views the registry knows.
 *
 * File: tests/php/Gateway/RoutingTest.php
 */

declare(strict_types=1);

namespace Starwishx\Tests\Gateway;

use Gateway\Core\RedirectHandler;
use Gateway\Enums\GatewayView;

/** Thrown from wp_redirect, so interceptWpLogin()'s exit is never reached. */
final class RedirectIntercepted extends \RuntimeException
{
    public function __construct(public readonly string $location)
    {
        parent::__construct("redirect: {$location}");
    }
}

final class RoutingTest extends GatewayTestCase
{
    /** @var array{0: array<string, mixed>, 1: array<string, mixed>, 2: string|null} */
    private array $saved;

    #[\Override]
    public function set_up(): void
    {
        parent::set_up();
        $this->saved = [$_GET, $_REQUEST, $_SERVER['REQUEST_METHOD'] ?? null];
        add_filter('wp_redirect', static function (string $location): never {
            throw new RedirectIntercepted($location);
        });
    }

    #[\Override]
    public function tear_down(): void
    {
        [$_GET, $_REQUEST, $method] = $this->saved;
        $_SERVER['REQUEST_METHOD'] = $method;
        parent::tear_down();
    }

    /**
     * Where wp-login.php?{query} sends a visitor ('' when it passes through).
     *
     * @param array<string, mixed> $query
     */
    private function wpLogin(array $query, string $method = 'GET'): string
    {
        // WordPress slashes the superglobals; the handler unslashes.
        $_GET = $_REQUEST = wp_slash($query);
        $_SERVER['REQUEST_METHOD'] = $method;
        try {
            (new RedirectHandler())->interceptWpLogin();
        } catch (RedirectIntercepted $redirect) {
            return $redirect->location;
        }
        return '';
    }

    // --- GatewayView ---------------------------------------------------------

    public function test_view_urls(): void
    {
        $base = home_url('/gateway/');
        $this->assertSame($base, GatewayView::LOGIN->url());
        $this->assertSame("{$base}?view=register", GatewayView::REGISTER->url());
        $this->assertSame("{$base}?view=lost-password", GatewayView::LOST_PASSWORD->url());
        $this->assertSame("{$base}?a=1", GatewayView::LOGIN->url(['a' => '1']));
    }

    public function test_reset_url_encodes_key_and_login(): void
    {
        $this->assertSame(
            home_url('/gateway/?view=reset-password&key=aB3%26x&login=jo%20hn'),
            GatewayView::resetUrl('aB3&x', 'jo hn')
        );
    }

    /** @return array<string, array{string, string}> */
    public static function awkwardKeysAndLogins(): array
    {
        return [
            'query syntax' => ['a+b=c', 'x&y'],
            'percent'      => ['100%', '50%off'],
            'space and @'  => ['K3y', 'jo hn@example.com'],
            'hash'         => ['k#1', 'a.b-c_d'],
        ];
    }

    /**
     * Whatever the key and login, the link gives them back unchanged - read
     * by the WHATWG parser, as the browser reads the link in the mail.
     *
     * @dataProvider awkwardKeysAndLogins
     */
    public function test_reset_url_round_trips_key_and_login(string $key, string $login): void
    {
        $query = self::queryOf(GatewayView::resetUrl($key, $login));

        $this->assertSame(['view' => 'reset-password', 'key' => $key, 'login' => $login], $query);
    }

    // --- WordPress URL filters ---------------------------------------------

    public function test_wordpress_auth_urls_point_at_the_gateway(): void
    {
        $this->assertSame(GatewayView::LOGIN->url(), wp_login_url());
        $this->assertSame(GatewayView::REGISTER->url(), wp_registration_url());
        $this->assertSame(GatewayView::LOST_PASSWORD->url(), wp_lostpassword_url());
        $this->assertSame(
            add_query_arg('redirect_to', urlencode(home_url('/x/')), GatewayView::LOGIN->url()),
            wp_login_url(home_url('/x/'))
        );
    }

    public function test_logout_goes_through_wordpress_then_lands_on_the_gateway(): void
    {
        $url = wp_logout_url();
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        $this->assertStringContainsString('wp-login.php', $url);
        $this->assertSame('logout', $query['action']);
        $this->assertSame(GatewayView::LOGIN->url(), $query['redirect_to']);
        $this->assertNotEmpty($query['_wpnonce']);
    }

    // --- wp-login.php interception ---------------------------------------

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function wpLoginActions(): array
    {
        return [
            'login (default)'  => [[], '/gateway/'],
            'login'            => [['action' => 'login'], '/gateway/'],
            'register'         => [['action' => 'register'], '/gateway/?view=register'],
            'lostpassword'     => [['action' => 'lostpassword'], '/gateway/?view=lost-password'],
            'retrievepassword' => [['action' => 'retrievepassword'], '/gateway/?view=lost-password'],
            'rp keeps the key' => [['action' => 'rp', 'key' => 'K3y', 'login' => 'jo hn'], '/gateway/?view=reset-password&key=K3y&login=jo%20hn'],
            'resetpass'        => [['action' => 'resetpass', 'key' => 'K', 'login' => 'a'], '/gateway/?view=reset-password&key=K&login=a'],
            'redirect_to kept' => [['redirect_to' => 'http://example.org/x/'], '/gateway/?redirect_to=http%3A%2F%2Fexample.org%2Fx%2F'],
        ];
    }

    /**
     * @dataProvider wpLoginActions
     * @param array<string, mixed> $query
     */
    public function test_wp_login_redirects_to_the_gateway(array $query, string $path): void
    {
        $this->assertSame(home_url($path), $this->wpLogin($query));
    }

    public function test_other_actions_and_posts_pass_through(): void
    {
        foreach (['logout', 'postpass', 'confirmaction', 'some_2fa_step'] as $action) {
            $this->assertSame('', $this->wpLogin(['action' => $action]), $action);
        }
        $this->assertSame('', $this->wpLogin(['action' => 'login'], 'POST'));
    }

    public function test_array_parameters_never_crash(): void
    {
        $this->assertSame('', $this->wpLogin(['action' => ['login']]));
        $this->assertSame(home_url('/gateway/'), $this->wpLogin(['redirect_to' => ['x']]));
        $this->assertStringStartsWith(
            home_url('/gateway/?view=reset-password'),
            $this->wpLogin(['action' => 'rp', 'key' => ['x'], 'login' => ['y']])
        );
    }

    // --- Registry -----------------------------------------------------------

    public function test_unknown_view_falls_back_to_login_in_the_registry(): void
    {
        $registry = \gateway()->registry();
        foreach (GatewayView::cases() as $view) {
            $this->assertTrue($registry->has($view->value), $view->value);
        }
        $this->assertFalse($registry->has('foo'));
    }
}
