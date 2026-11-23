<?php

/**
 * gateway/v1/password/*: requesting a link, setting the password with its
 * key, and the generator.
 *
 * File: tests/php/Gateway/PasswordTest.php
 */

declare(strict_types=1);

namespace Starwishx\Tests\Gateway;

final class PasswordTest extends GatewayTestCase
{
    private const string NEW_PASSWORD = 'N3w!Secure#Pass';

    private function lost(string $userLogin): \WP_REST_Response
    {
        return $this->rest('POST', 'password/lost', ['user_login' => $userLogin]);
    }

    /** @param array<string, mixed> $overrides */
    private function reset(array $overrides): \WP_REST_Response
    {
        return $this->rest('POST', 'password/reset', $overrides + ['password' => self::NEW_PASSWORD]);
    }

    // --- Lost password ---------------------------------------------------------

    public function test_unknown_account_gets_the_same_answer_and_no_mail(): void
    {
        $this->user('anna');

        $known = $this->lost('anna');
        reset_phpmailer_instance();
        $unknown = $this->lost('nobody-here');

        $this->assertSame(200, $unknown->get_status());
        $this->assertSame($known->get_data(), $unknown->get_data());
        $this->assertCount(0, $this->sentMail());
    }

    public function test_known_account_by_login_or_email_gets_a_working_link(): void
    {
        $user = $this->user('anna');

        foreach (['anna', 'anna@example.com'] as $userLogin) {
            reset_phpmailer_instance();
            $this->assertSame(200, $this->lost($userLogin)->get_status());

            $this->assertCount(1, $this->sentMail(), $userLogin);
            $query = self::queryOf(self::gatewayLink($this->onlyMailTo('anna@example.com')['body']));
            $this->assertSame('reset-password', $query['view']);
            $this->assertSame($user->ID, check_password_reset_key($query['key'], 'anna')->ID);
        }
    }

    public function test_three_requests_per_account_and_ip_per_quarter_hour(): void
    {
        $this->user('anna');
        for ($i = 0; $i < 3; $i++) {
            $this->assertSame(200, $this->lost('anna')->get_status());
        }
        $this->assertSame(429, $this->lost('anna')->get_status());
    }

    public function test_five_requests_per_ip_per_hour(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->assertSame(200, $this->lost("someone-{$i}")->get_status());
        }
        $this->assertSame(429, $this->lost('someone-else')->get_status());

        $this->fromIp('198.51.100.99');
        $this->assertSame(200, $this->lost('someone-else')->get_status());
    }

    // --- Reset ---------------------------------------------------------------

    public function test_a_valid_key_sets_the_password_once_and_activates(): void
    {
        $user = $this->user('fresh', activated: false);
        $key = get_password_reset_key($user);

        $response = $this->reset(['login' => 'fresh', 'key' => $key]);

        $this->assertSame(200, $response->get_status());
        $this->assertTrue(wp_check_password(self::NEW_PASSWORD, get_userdata($user->ID)->user_pass, $user->ID));
        $this->assertTrue(\users()->stateService()->isActivated($user->ID));

        // The key is spent.
        $again = $this->reset(['login' => 'fresh', 'key' => $key, 'password' => 'An0ther!Secure#Pass']);
        $this->assertSame('invalid_key', self::code($again));
    }

    public function test_a_wrong_key_or_login_is_refused(): void
    {
        $user = $this->user('anna');
        $key = get_password_reset_key($user);
        $this->user('bob');

        $this->assertSame('invalid_key', self::code($this->reset(['login' => 'anna', 'key' => 'wrong-key'])));
        $this->assertSame('invalid_key', self::code($this->reset(['login' => 'bob', 'key' => $key])));
        $this->assertSame(401, $this->reset(['login' => 'anna', 'key' => ''])->get_status());
        $this->assertTrue(wp_check_password(self::PASSWORD, get_userdata($user->ID)->user_pass, $user->ID));
    }

    /** @return array<string, array{string, string}> */
    public static function weakPasswords(): array
    {
        return [
            'too short'      => ['Sh0rt!', 'password_too_short'],
            // 9 characters, 15 bytes: the minimum is in characters.
            'short in characters, long in bytes' => ['Aпароль1!', 'password_too_short'],
            'no capital'     => ['n0capitals!here', 'password_too_weak'],
            'no digit'       => ['NoDigits!Here', 'password_too_weak'],
            'no symbol'      => ['NoSymbols1Here', 'password_too_weak'],
            'contains login' => ['Anna#Is#Great1', 'password_contains_userdata'],
        ];
    }

    /** @dataProvider weakPasswords */
    public function test_the_password_policy_applies(string $password, string $code): void
    {
        $user = $this->user('anna');
        $key = get_password_reset_key($user);

        $response = $this->reset(['login' => 'anna', 'key' => $key, 'password' => $password]);

        $this->assertSame(400, $response->get_status());
        $this->assertSame($code, self::code($response));
    }

    /** @return array<string, array{string, array<string, mixed>, string}> */
    public static function malformed(): array
    {
        return [
            'array login'     => ['password/reset', ['login' => ['anna'], 'key' => 'k', 'password' => self::NEW_PASSWORD], 'login'],
            'array key'       => ['password/reset', ['login' => 'anna', 'key' => ['k'], 'password' => self::NEW_PASSWORD], 'key'],
            'number password' => ['password/reset', ['login' => 'anna', 'key' => 'k', 'password' => 12345678], 'password'],
            'array user_login' => ['password/lost', ['user_login' => ['anna']], 'user_login'],
        ];
    }

    /**
     * A wrong type is a field error, never a crash.
     *
     * @dataProvider malformed
     * @param array<string, mixed> $body
     */
    public function test_malformed_input_is_a_field_error(string $route, array $body, string $field): void
    {
        $this->assertFieldError($this->rest('POST', $route, $body), $field);
    }

    public function test_a_missing_password_is_a_bad_request(): void
    {
        $response = $this->rest('POST', 'password/reset', ['login' => 'anna', 'key' => 'k']);

        $this->assertSame(400, $response->get_status());
        $this->assertSame('rest_missing_callback_param', self::code($response));
    }

    // --- Generate --------------------------------------------------------------

    public function test_generate_needs_a_page_nonce(): void
    {
        $this->assertSame(403, $this->rest('GET', 'password/generate', [], '')->get_status());

        $response = $this->rest('GET', 'password/generate');
        $this->assertSame(200, $response->get_status());
        $this->assertSame(24, strlen($response->get_data()['password']));
    }

    public function test_generate_works_for_logged_in_users_too(): void
    {
        // The Launchpad's change-password form uses it.
        wp_set_current_user($this->user('anna')->ID);

        $this->assertSame(200, $this->rest('GET', 'password/generate')->get_status());
    }
}
