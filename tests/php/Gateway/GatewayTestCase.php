<?php

/**
 * Base for the Gateway tests: REST calls as the browser makes them (JSON
 * body, wp_rest nonce), users with known passwords, the mail WordPress
 * "sent", the client IP, and redirects that can be asserted despite exit.
 *
 * Every test runs in a transaction the WP test suite rolls back, so users,
 * options and rate-limit transients don't outlive it.
 *
 * File: tests/php/Gateway/GatewayTestCase.php
 */

declare(strict_types=1);

namespace Starwishx\Tests\Gateway;

use WP_REST_Request;
use WP_REST_Response;
use WP_UnitTestCase;

abstract class GatewayTestCase extends WP_UnitTestCase
{
    public const string PASSWORD = 'Str0ng!Passw0rd#1';

    #[\Override]
    public function set_up(): void
    {
        parent::set_up();
        wp_set_current_user(0);
        $this->fromIp('198.51.100.10');
        reset_phpmailer_instance();

        // Routes register on rest_api_init; a fresh server per test.
        global $wp_rest_server;
        $wp_rest_server = null;
        rest_get_server();
    }

    #[\Override]
    public function tear_down(): void
    {
        global $wp_rest_server;
        $wp_rest_server = null;
        parent::tear_down();
    }

    /** Requests come from this client IP (ClientIp reads REMOTE_ADDR). */
    protected function fromIp(string $ip): void
    {
        $_SERVER['REMOTE_ADDR'] = $ip;
    }

    /**
     * A gateway/v1 request as the store sends it: a JSON body and the
     * nonce for the current user (pass '' to send none).
     *
     * @param array<string, mixed> $body
     */
    protected function rest(string $method, string $route, array $body = [], ?string $nonce = null): WP_REST_Response
    {
        $request = new WP_REST_Request($method, '/gateway/v1/' . ltrim($route, '/'));
        $nonce ??= wp_create_nonce('wp_rest');
        if ($nonce !== '') {
            $request->set_header('X-WP-Nonce', $nonce);
        }
        if ($method === 'GET') {
            $request->set_query_params($body);
        } else {
            $request->set_header('Content-Type', 'application/json');
            $request->set_body((string) wp_json_encode($body));
        }
        return rest_do_request($request);
    }

    /** The WP error code of a response ('' for a success). */
    protected static function code(WP_REST_Response $response): string
    {
        $data = $response->get_data();
        return is_array($data) && is_string($data['code'] ?? null) ? $data['code'] : '';
    }

    /**
     * A user with self::PASSWORD, activated unless said otherwise (the users
     * module starts every new account unactivated).
     */
    protected function user(string $login = 'anna', bool $activated = true, string $role = 'subscriber'): \WP_User
    {
        $id = self::factory()->user->create([
            'user_login' => $login,
            'user_email' => "{$login}@example.com",
            'user_pass'  => self::PASSWORD,
            'role'       => $role,
        ]);
        if ($activated) {
            \users()->stateService()->activate($id);
        }
        return get_userdata($id);
    }

    /**
     * The mails "sent" so far (the suite's MockPHPMailer).
     *
     * @return list<array{to: array<int, array<int, string>>, subject: string, body: string}>
     */
    protected function sentMail(): array
    {
        $mailer = tests_retrieve_phpmailer_instance();
        return $mailer ? $mailer->mock_sent : [];
    }

    /**
     * The mails "sent" to `$address` (registration also notifies the admin).
     *
     * @return list<array{to: array<int, array<int, string>>, subject: string, body: string}>
     */
    protected function mailTo(string $address): array
    {
        return array_values(array_filter(
            $this->sentMail(),
            static fn(array $mail): bool => ($mail['to'][0][0] ?? '') === $address
        ));
    }

    /** The first link to the gateway in a mail body. */
    protected static function gatewayLink(string $body): string
    {
        preg_match('~https?://\S+/gateway/\S*~', $body, $m);
        return $m[0] ?? '';
    }
}
