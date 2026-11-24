<?php

/**
 * The test environment itself: the theme is active and the Gateway is up.
 * If this fails, every other failure is noise.
 *
 * File: tests/php/Gateway/BootTest.php
 */

declare(strict_types=1);

namespace Starwishx\Tests\Gateway;

final class BootTest extends GatewayTestCase
{
    public function test_theme_is_active(): void
    {
        $this->assertSame(basename(STARWISHX_TESTS_THEME_DIR), get_stylesheet());
        $this->assertTrue(function_exists('gateway'));
    }

    public function test_test_database_is_not_a_site_database(): void
    {
        $this->assertStringContainsStringIgnoringCase('test', DB_NAME);
    }

    public function test_gateway_routes_are_registered(): void
    {
        $routes = array_keys(rest_get_server()->get_routes('gateway/v1'));
        foreach (['/gateway/v1/login', '/gateway/v1/logout', '/gateway/v1/register', '/gateway/v1/password/lost', '/gateway/v1/password/reset', '/gateway/v1/password/generate'] as $route) {
            $this->assertContains($route, $routes);
        }
    }
}
