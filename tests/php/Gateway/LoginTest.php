<?php

/**
 * POST gateway/v1/login: who gets in, what a failure reveals, the lockout.
 *
 * File: tests/php/Gateway/LoginTest.php
 */

declare(strict_types=1);

namespace Starwishx\Tests\Gateway;

final class LoginTest extends GatewayTestCase
{
    private function login(string $username, string $password = self::PASSWORD, ?string $nonce = null): \WP_REST_Response
    {
        return $this->rest('POST', 'login', ['username' => $username, 'password' => $password], $nonce);
    }

    public function test_success_returns_a_same_origin_redirect(): void
    {
        $this->user('anna');

        $response = $this->login('anna');

        $this->assertSame(200, $response->get_status());
        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertStringStartsWith(home_url(), $data['redirectTo']);
    }

    public function test_login_by_email_and_in_any_case(): void
    {
        $this->user('anna');

        $this->assertSame(200, $this->login('anna@example.com')->get_status());
        $this->assertSame(200, $this->login('ANNA')->get_status());
    }

    public function test_wrong_password_and_unknown_user_look_the_same(): void
    {
        $this->user('anna');

        $wrong = $this->login('anna', 'Wrong#Password1');
        $unknown = $this->login('nobody-here', 'Wrong#Password1');

        $this->assertSame(401, $wrong->get_status());
        $this->assertSame('auth_failed', self::code($wrong));
        $this->assertSame($wrong->get_status(), $unknown->get_status());
        $this->assertSame($wrong->get_data(), $unknown->get_data());
    }

    public function test_an_unactivated_account_gets_no_session(): void
    {
        $this->user('fresh', activated: false);

        $response = $this->login('fresh');

        $this->assertSame('account_not_activated', self::code($response));
        $this->assertSame(0, get_current_user_id());
    }

    public function test_logged_in_users_are_refused(): void
    {
        wp_set_current_user($this->user('anna')->ID);

        $this->assertSame('already_authenticated', self::code($this->login('anna')));
    }

    public function test_a_missing_or_foreign_nonce_is_refused(): void
    {
        $this->user('anna');

        foreach (['', 'not-a-nonce', wp_create_nonce('some_other_action')] as $nonce) {
            $response = $this->login('anna', nonce: $nonce);
            $this->assertSame(403, $response->get_status());
            $this->assertSame('rest_cookie_invalid_nonce', self::code($response));
        }
    }

    public function test_five_failures_lock_the_account_for_every_spelling(): void
    {
        $this->user('anna');

        for ($i = 0; $i < 5; $i++) {
            $this->assertSame(401, $this->login('anna', "Wrong#{$i}xxxxxx")->get_status());
        }

        // The right password, in any spelling, from this IP: locked.
        foreach (['anna', 'ANNA', 'Anna@Example.com'] as $spelling) {
            $response = $this->login($spelling);
            $this->assertSame(429, $response->get_status(), $spelling);
            $this->assertSame('rate_limited', self::code($response));
        }

        // Another IP isn't locked out of the account.
        $this->fromIp('198.51.100.99');
        $this->assertSame(200, $this->login('anna')->get_status());
    }

    public function test_a_name_no_account_has_is_locked_in_every_case_too(): void
    {
        // Keyed by the lower-cased name: probing spellings buys no attempts.
        for ($i = 0; $i < 5; $i++) {
            $this->login('ghost-user', "Wrong#{$i}xxxxxx");
        }

        $this->assertSame(429, $this->login('GHOST-USER', 'Wrong#xxxxxxx9')->get_status());
        $this->assertSame(429, $this->login(' Ghost-User ', 'Wrong#xxxxxxx9')->get_status());
    }

    public function test_one_ip_is_capped_over_all_accounts(): void
    {
        // Password spraying: one wrong password against many names.
        for ($i = 0; $i < 20; $i++) {
            $this->assertSame(401, $this->login("sprayed-{$i}", 'Summer#2026x')->get_status());
        }

        $this->user('anna');
        $this->assertSame(429, $this->login('anna')->get_status());

        $this->fromIp('198.51.100.99');
        $this->assertSame(200, $this->login('anna')->get_status());
    }

    public function test_success_clears_the_account_counter_but_not_the_ip_counter(): void
    {
        $this->user('anna');
        for ($i = 0; $i < 4; $i++) {
            $this->login('anna', "Wrong#{$i}xxxxxx");
        }
        $this->assertSame(200, $this->login('anna')->get_status());

        // Account counter reset: four more failures are still allowed...
        for ($i = 0; $i < 4; $i++) {
            $this->assertSame(401, $this->login('anna', "Wrong#{$i}yyyyyy")->get_status());
        }
        // ...but the IP has 8 failures on record, and keeps them.
        for ($i = 0; $i < 12; $i++) {
            $this->login("other-{$i}", 'Wrong#zzzzzz1');
        }
        $this->assertSame(429, $this->login('anna')->get_status());
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function malformed(): array
    {
        return [
            'array username'  => [['username' => ['anna'], 'password' => 'x']],
            'array password'  => [['username' => 'anna', 'password' => ['x']]],
            'number password' => [['username' => 'anna', 'password' => 12345678]],
            'missing password' => [['username' => 'anna']],
        ];
    }

    /**
     * @dataProvider malformed
     * @param array<string, mixed> $body
     */
    public function test_malformed_input_is_a_client_error(array $body): void
    {
        $response = $this->rest('POST', 'login', $body);

        $this->assertGreaterThanOrEqual(400, $response->get_status());
        $this->assertLessThan(500, $response->get_status());
    }
}
