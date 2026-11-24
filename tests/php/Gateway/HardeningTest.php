<?php

/**
 * The language-level guard rails on the Gateway's security code:
 *
 *   #[\NoDiscard]          a check whose result is ignored warns (PHP 8.5);
 *                          `(void)` marks a deliberate discard
 *   #[\SensitiveParameter] passwords and reset keys never show in a
 *                          backtrace - PHP 8.5 prints one for fatal errors
 *
 * File: tests/php/Gateway/HardeningTest.php
 */

declare(strict_types=1);

namespace Starwishx\Tests\Gateway;

use Gateway\Services\AuthService;
use Gateway\Services\PasswordResetService;
use Shared\Policy\EmailPolicy;
use Shared\Policy\PasswordPolicy;
use Shared\Policy\RateLimitPolicy;
use Shared\Policy\UsernamePolicy;

final class HardeningTest extends GatewayTestCase
{
    /** The warnings `$code` raises. */
    private static function warningsOf(\Closure $code): string
    {
        $warnings = '';
        set_error_handler(static function (int $no, string $message) use (&$warnings): bool {
            $warnings .= $message . "\n";
            return true;
        });
        try {
            $code();
        } finally {
            restore_error_handler();
        }
        return $warnings;
    }

    /**
     * Each check called as a bare statement, its result dropped.
     *
     * @return array<string, array{\Closure(): void, string}>
     */
    public static function ignoredChecks(): array
    {
        return [
            'rate limit' => [static function (): void { RateLimitPolicy::check('rl_test_nodiscard', 5, 60); }, 'RateLimitPolicy::check()'],
            'password'   => [static function (): void { PasswordPolicy::validate('short'); }, 'PasswordPolicy::validate()'],
            'username'   => [static function (): void { UsernamePolicy::validate('ab'); }, 'UsernamePolicy::validate()'],
            'email'      => [static function (): void { EmailPolicy::validate('not-an-email'); }, 'EmailPolicy::validate()'],
        ];
    }

    /**
     * @dataProvider ignoredChecks
     * @param \Closure(): void $call
     */
    public function test_ignoring_a_checks_result_warns(\Closure $call, string $method): void
    {
        $this->assertStringContainsString($method, self::warningsOf($call));
    }

    public function test_a_void_cast_is_a_deliberate_discard(): void
    {
        $this->assertSame('', self::warningsOf(static function (): void {
            (void) RateLimitPolicy::check('rl_test_nodiscard', 5, 60);
        }));
    }

    /** @return array<string, array{class-string, string, string}> */
    public static function secrets(): array
    {
        return [
            'login password'   => [AuthService::class, 'authenticate', 'password'],
            'reset key'        => [PasswordResetService::class, 'resetPassword', 'key'],
            'new password'     => [PasswordResetService::class, 'resetPassword', 'new_password'],
            'key check'        => [PasswordResetService::class, 'validateKey', 'key'],
            'policy password'  => [PasswordPolicy::class, 'validate', 'password'],
        ];
    }

    /**
     * Checked on the declaration: whether a trace shows arguments at all
     * depends on php.ini (zend.exception_ignore_args), so a thrown trace
     * would prove nothing where they're off.
     *
     * @dataProvider secrets
     * @param class-string $class
     */
    public function test_secrets_are_redacted_from_backtraces(string $class, string $method, string $param): void
    {
        $parameter = new \ReflectionParameter([$class, $method], $param);

        $this->assertNotEmpty($parameter->getAttributes(\SensitiveParameter::class), "{$class}::{$method}(\${$param})");
    }
}
