<?php

/**
 * The E2E suite's test-data helper: boots the site's WordPress from the
 * command line and creates or removes what the specs need. CLI only; it lives
 * under /tests/, which never deploys (.distignore).
 *
 *   php wp.php <wp-path> user <login> [--inactive]  -> {login, email, password}
 *   php wp.php <wp-path> reset-key <login>          -> {key, url}
 *   php wp.php <wp-path> clear-limits               -> {cleared}
 *   php wp.php <wp-path> cleanup                    -> {users, cleared}
 *
 * Only logins starting with "e2e-" are ever created or deleted. The result is
 * printed after a marker line, so whatever WordPress or a plugin prints while
 * booting (notices, deprecations) can't corrupt it.
 *
 * File: tests/e2e/support/wp.php
 */

declare(strict_types=1);

const E2E_PREFIX = 'e2e-';
const E2E_MARKER = '@@E2E@@';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/** @param array<string, mixed> $result */
function e2e_out(array $result, int $code = 0): never
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    fwrite(STDOUT, "\n" . E2E_MARKER . json_encode($result, JSON_UNESCAPED_SLASHES) . "\n");
    exit($code);
}

function e2e_fail(string $message): never
{
    e2e_out(['error' => $message], 1);
}

$e2eArgs = array_slice($argv, 1);
$e2ePath = rtrim((string) array_shift($e2eArgs), '/\\');
$e2eCommand = (string) array_shift($e2eArgs);

if ($e2ePath === '' || !is_file($e2ePath . '/wp-load.php')) {
    fwrite(STDERR, "usage: php wp.php <wp-path> <command> [args]\n");
    exit(2);
}

// WordPress (and plugins such as Polylang) read the host of the request.
$_SERVER['HTTP_HOST'] = (string) (getenv('E2E_HOST') ?: 'localhost');
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['SERVER_NAME'] = $_SERVER['HTTP_HOST'];
$_SERVER['REQUEST_METHOD'] = 'GET';

ob_start();
require $e2ePath . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/user.php';

/** An "e2e-" login, or stop: nothing else is ever touched. */
function e2e_login(?string $login): string
{
    $login = (string) $login;
    if (!str_starts_with($login, E2E_PREFIX) || sanitize_user($login, true) !== $login) {
        e2e_fail("not an e2e login: {$login}");
    }
    return $login;
}

/** Deletes the rate-limit counters (RateLimitPolicy: "rl_" + md5). */
function e2e_clear_limits(): int
{
    global $wpdb;
    $cleared = (int) $wpdb->query(
        "DELETE FROM {$wpdb->options}
         WHERE option_name LIKE '\\_transient\\_rl\\_%'
            OR option_name LIKE '\\_transient\\_timeout\\_rl\\_%'"
    );
    if (wp_using_ext_object_cache()) {
        wp_cache_flush();
    }
    return $cleared;
}

switch ($e2eCommand) {
    case 'user':
        $login = e2e_login($e2eArgs[0] ?? null);
        $inactive = in_array('--inactive', $e2eArgs, true);
        // Meets PasswordPolicy: upper, lower, digit and symbol.
        $password = 'Aa1!' . wp_generate_password(16, false);
        $email = "{$login}@example.com";

        $id = wp_insert_user([
            'user_login' => $login,
            'user_email' => $email,
            'user_pass'  => $password,
            'role'       => 'subscriber',
        ]);
        if (is_wp_error($id)) {
            e2e_fail($id->get_error_message());
        }
        // user_register made it unactivated (UsersCore::onUserRegister).
        if (!$inactive) {
            \users()->stateService()->activate($id, ['trigger' => 'e2e']);
        }
        e2e_out(['login' => $login, 'email' => $email, 'password' => $password]);

    case 'reset-key':
        $login = e2e_login($e2eArgs[0] ?? null);
        $user = get_user_by('login', $login);
        if (!$user) {
            e2e_fail("no such user: {$login}");
        }
        $key = get_password_reset_key($user);
        if (is_wp_error($key)) {
            e2e_fail($key->get_error_message());
        }
        e2e_out(['key' => $key, 'url' => \Gateway\Enums\GatewayView::resetUrl($key, $login)]);

    case 'clear-limits':
        e2e_out(['cleared' => e2e_clear_limits()]);

    case 'cleanup':
        $users = get_users([
            'search'         => E2E_PREFIX . '*',
            'search_columns' => ['user_login'],
            'fields'         => ['ID', 'user_login'],
        ]);
        $deleted = 0;
        foreach ($users as $user) {
            if (str_starts_with($user->user_login, E2E_PREFIX) && wp_delete_user((int) $user->ID)) {
                $deleted++;
            }
        }
        e2e_out(['users' => $deleted, 'cleared' => e2e_clear_limits()]);

    default:
        e2e_fail("unknown command: {$e2eCommand}");
}
