<?php

/**
 * AbstractApiController::reshapeInvalidParamError(): a failed
 * validate_callback on one of our routes becomes `invalid_data` with
 * `field_errors` (422), which the stores show inline. Anyone else's route
 * keeps WordPress's own `rest_invalid_param` (400).
 *
 * File: tests/php/Shared/ErrorShapeTest.php
 */

declare(strict_types=1);

namespace Starwishx\Tests\Shared;

use Shared\Core\AbstractApiController;
use Shared\Validation\RestArg;
use WP_REST_Request;
use WP_REST_Response;
use WP_UnitTestCase;

final class ErrorShapeTest extends WP_UnitTestCase
{
    /**
     * One string arg, as our controllers declare them.
     *
     * @return array<string, array<string, mixed>>
     */
    private static function args(): array
    {
        return ['name' => ['required' => true, 'validate_callback' => RestArg::string('Name')]];
    }

    #[\Override]
    public function set_up(): void
    {
        parent::set_up();

        add_action('rest_api_init', static function (): void {
            // Ours: the callback's receiver is an AbstractApiController.
            $ours = new class (self::args()) extends AbstractApiController {
                #[\Override]
                protected $namespace = 'starwishx-test/v1';

                /** @param array<string, mixed> $args */
                public function __construct(private readonly array $args) {}

                #[\Override]
                public function registerRoutes(): void
                {
                    register_rest_route($this->namespace, '/echo', [
                        'methods'             => 'POST',
                        'callback'            => [$this, 'echo'],
                        'permission_callback' => '__return_true',
                        'args'                => $this->args,
                    ]);
                }

                public function echo(): WP_REST_Response
                {
                    return new WP_REST_Response(['ok' => true]);
                }
            };
            $ours->registerRoutes();

            // Someone else's: a plain callback.
            register_rest_route('someone-else/v1', '/echo', [
                'methods'             => 'POST',
                'callback'            => static fn(): WP_REST_Response => new WP_REST_Response(['ok' => true]),
                'permission_callback' => '__return_true',
                'args'                => self::args(),
            ]);
        });

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

    private function post(string $route, mixed $name): WP_REST_Response
    {
        $request = new WP_REST_Request('POST', $route);
        $request->set_header('Content-Type', 'application/json');
        $request->set_body((string) wp_json_encode(['name' => $name]));
        return rest_do_request($request);
    }

    public function test_our_routes_answer_with_field_errors(): void
    {
        $response = $this->post('/starwishx-test/v1/echo', ['not', 'a', 'string']);
        $data = $response->get_data();

        $this->assertSame(422, $response->get_status());
        $this->assertSame('invalid_data', $data['code']);
        $this->assertIsString($data['data']['field_errors']['name'] ?? null);
        $this->assertArrayNotHasKey('params', $data['data']);
    }

    public function test_other_routes_keep_wordpress_shape(): void
    {
        $response = $this->post('/someone-else/v1/echo', ['not', 'a', 'string']);
        $data = $response->get_data();

        $this->assertSame(400, $response->get_status());
        $this->assertSame('rest_invalid_param', $data['code']);
        $this->assertArrayHasKey('name', $data['data']['params']);
    }

    public function test_valid_input_passes_through(): void
    {
        $this->assertSame(200, $this->post('/starwishx-test/v1/echo', 'Anna')->get_status());
        $this->assertSame(200, $this->post('/someone-else/v1/echo', 'Anna')->get_status());
    }
}
