<?php

/**
 * POST gateway/v1/register: the account it creates, the activation mail,
 * and what it refuses.
 *
 * File: tests/php/Gateway/RegisterTest.php
 */

declare(strict_types=1);

namespace Starwishx\Tests\Gateway;

use Gateway\Enums\GatewayView;

final class RegisterTest extends GatewayTestCase
{
    private function register(string $username, string $email): \WP_REST_Response
    {
        return $this->rest('POST', 'register', ['username' => $username, 'email' => $email]);
    }

    public function test_creates_an_unactivated_subscriber_and_mails_the_activation_link(): void
    {
        $response = $this->register('newbie', 'newbie@example.com');

        $this->assertSame(200, $response->get_status());
        $user = get_user_by('login', 'newbie');
        $this->assertInstanceOf(\WP_User::class, $user);
        $this->assertSame(['subscriber'], $user->roles);
        $this->assertFalse(\users()->stateService()->isActivated($user->ID));

        $mail = $this->mailTo('newbie@example.com');
        $this->assertCount(1, $mail);

        // The link opens the reset view with a key that works for this user.
        $link = self::gatewayLink($mail[0]['body']);
        parse_str((string) parse_url($link, PHP_URL_QUERY), $query);
        $this->assertStringStartsWith(GatewayView::RESET_PASSWORD->url(), $link);
        $this->assertSame('newbie', $query['login']);
        $this->assertInstanceOf(\WP_User::class, check_password_reset_key($query['key'], 'newbie'));
    }

    public function test_the_new_account_cannot_log_in_until_it_sets_a_password(): void
    {
        $this->register('newbie', 'newbie@example.com');
        parse_str((string) parse_url(self::gatewayLink($this->mailTo('newbie@example.com')[0]['body']), PHP_URL_QUERY), $query);

        $reset = $this->rest('POST', 'password/reset', [
            'login'    => $query['login'],
            'key'      => $query['key'],
            'password' => self::PASSWORD,
        ]);
        $this->assertSame(200, $reset->get_status());

        $login = $this->rest('POST', 'login', ['username' => 'newbie', 'password' => self::PASSWORD]);
        $this->assertSame(200, $login->get_status(), 'activated by setting the password');
    }

    public function test_a_privileged_default_role_never_reaches_self_registration(): void
    {
        update_option('default_role', 'administrator');
        $log = ini_set('error_log', (string) tempnam(sys_get_temp_dir(), 'gw')); // the expected warning

        $this->assertSame(200, $this->register('sneaky', 'sneaky@example.com')->get_status());

        $this->assertSame(['subscriber'], get_user_by('login', 'sneaky')->roles);
        ini_set('error_log', (string) $log);
    }

    public function test_contributor_default_role_is_kept(): void
    {
        update_option('default_role', 'contributor');

        $this->register('writer', 'writer@example.com');

        $this->assertSame(['contributor'], get_user_by('login', 'writer')->roles);
    }

    public function test_taken_username_or_email_is_a_conflict(): void
    {
        $this->user('anna');

        $this->assertSame('username_exists', self::code($this->register('anna', 'other@example.com')));
        $this->assertSame(409, $this->register('anna', 'other@example.com')->get_status());
        $this->assertSame('email_exists', self::code($this->register('other', 'anna@example.com')));
    }

    /** @return array<string, array{string, string, string}> */
    public static function invalidFields(): array
    {
        return [
            'username too short' => ['ab', 'ok@example.com', 'username'],
            'username with a space' => ['bad name', 'ok@example.com', 'username'],
            'cyrillic username' => ['кирило', 'ok@example.com', 'username'],
            'email shape' => ['goodname', 'not-an-email', 'email'],
            'empty email' => ['goodname', '', 'email'],
        ];
    }

    /** @dataProvider invalidFields */
    public function test_invalid_fields_come_back_as_field_errors(string $username, string $email, string $field): void
    {
        $response = $this->register($username, $email);

        $this->assertSame(422, $response->get_status());
        $this->assertSame('invalid_data', self::code($response));
        $this->assertArrayHasKey($field, $response->get_data()['data']['field_errors']);
        $this->assertCount(0, $this->mailTo('ok@example.com'));
    }

    public function test_the_dns_probe_rejects_a_domain_without_mail(): void
    {
        // On by default; the bootstrap turns it off for the network-free run.
        add_filter('starwishx/email_dns_check', '__return_true', 20);
        add_filter('starwishx/email_dns_check', static fn(bool $on, string $email): bool => $on && str_ends_with($email, '.invalid'), 30, 2);

        // .invalid never resolves (RFC 2606): no network is needed to fail.
        $response = $this->register('goodname', 'someone@nothing.invalid');

        $this->assertSame(422, $response->get_status());
        $this->assertArrayHasKey('email', $response->get_data()['data']['field_errors']);
    }

    public function test_five_registrations_per_ip_per_hour(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->assertSame(200, $this->register("user{$i}x", "user{$i}@example.com")->get_status());
        }

        $sixth = $this->register('user6x', 'user6@example.com');
        $this->assertSame(429, $sixth->get_status());
        $this->assertFalse(get_user_by('login', 'user6x'));
    }

    public function test_logged_in_users_cannot_register(): void
    {
        wp_set_current_user($this->user('anna')->ID);

        $this->assertSame('already_authenticated', self::code($this->register('newbie', 'newbie@example.com')));
    }

    public function test_malformed_input_is_a_client_error_not_a_crash(): void
    {
        foreach ([
            ['username' => ['newbie'], 'email' => 'newbie@example.com'],
            ['username' => 'newbie', 'email' => ['x']],
            ['username' => 12345, 'email' => 'newbie@example.com'],
        ] as $body) {
            $status = $this->rest('POST', 'register', $body)->get_status();
            $this->assertSame(422, $status, (string) wp_json_encode($body));
        }
        $this->assertFalse(get_user_by('login', 'newbie'));
    }
}
