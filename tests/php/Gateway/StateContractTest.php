<?php

/**
 * The state PHP hydrates for the Gateway store, as a shape (keys and value
 * types), pinned in tests/fixtures/gateway-state.json. The TS side checks
 * its own types against the same file (tests/ts/gateway/contract.test.ts),
 * so a field added, renamed or retyped on one side fails a test until the
 * other side follows.
 *
 *   UPDATE_FIXTURES=1 pnpm test:php   rewrites the fixture after a deliberate change
 *
 * File: tests/php/Gateway/StateContractTest.php
 */

declare(strict_types=1);

namespace Starwishx\Tests\Gateway;

use Shared\Policy\PasswordPolicy;
use Shared\Policy\UsernamePolicy;

final class StateContractTest extends GatewayTestCase
{
    private const string FIXTURE = STARWISHX_TESTS_THEME_DIR . '/tests/fixtures/gateway-state.json';

    /** Keys and JSON value types: 'string', 'number', 'boolean', 'null', or a nested shape. */
    private static function shape(mixed $value): mixed
    {
        return match (true) {
            is_array($value) => array_map(self::shape(...), $value),
            is_string($value) => 'string',
            is_int($value), is_float($value) => 'number',
            is_bool($value) => 'boolean',
            $value === null => 'null',
            default => get_debug_type($value),
        };
    }

    public function test_hydrated_state_matches_the_contract(): void
    {
        // What templates/page-gateway.php and GatewayCore::enqueueAssets()
        // hydrate, minus gatewaySettings (static strings).
        $state = \gateway()->getState('login') + [
            'passwordPolicy'    => PasswordPolicy::getClientRules(),
            'usernamePolicy'    => UsernamePolicy::getClientRules(),
            'validationStrings' => PasswordPolicy::getClientValidationStrings(),
        ];
        $shape = self::shape($state);

        if (getenv('UPDATE_FIXTURES')) {
            file_put_contents(self::FIXTURE, wp_json_encode($shape, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
        }

        $this->assertFileExists(self::FIXTURE, 'run once with UPDATE_FIXTURES=1');
        $this->assertSame(
            json_decode((string) file_get_contents(self::FIXTURE), true),
            $shape,
            'The hydrated gateway state changed shape: update the TS types (inc/gateway/Assets/types.ts), then UPDATE_FIXTURES=1.'
        );
    }
}
