<?php

/**
 * The forms' markup against what the store expects of it, read with PHP
 * 8.4's HTML5 parser (Dom\HTMLDocument) and CSS selectors:
 *
 *   - novalidate: the store's translated messages, not the browser's popups;
 *   - the submit handler is the form's own (actions.<jsId>.submit);
 *   - every data-field names a string field of the form's state - the store
 *     ignores any other, so a typo would leave an input silently dead;
 *   - every field has a label;
 *   - assets come from wherever the theme is installed.
 *
 * Plus the JS names a form id turns into.
 *
 * File: tests/php/Gateway/FormsTest.php
 */

declare(strict_types=1);

namespace Starwishx\Tests\Gateway;

use Dom\HTMLDocument;
use Gateway\Enums\GatewayView;
use Gateway\Forms\AbstractForm;

final class FormsTest extends GatewayTestCase
{
    private static function form(GatewayView $view): AbstractForm
    {
        $form = \gateway()->registry()->get($view->value);
        self::assertNotNull($form, "no {$view->value} form registered");
        return $form;
    }

    private static function parse(AbstractForm $form): HTMLDocument
    {
        return HTMLDocument::createFromString('<!doctype html><body>' . $form->render(), LIBXML_NOERROR);
    }

    /** @return array<string, array{GatewayView}> */
    public static function views(): array
    {
        $views = [];
        foreach (GatewayView::cases() as $view) {
            $views[$view->value] = [$view];
        }
        return $views;
    }

    /** @dataProvider views */
    public function test_the_store_validates_and_handles_the_submit(GatewayView $view): void
    {
        $form = self::form($view);
        $element = self::parse($form)->querySelector('form');

        $this->assertNotNull($element);
        $this->assertTrue($element->hasAttribute('novalidate'), 'novalidate');
        $this->assertSame("actions.{$form->getJsId()}.submit", $element->getAttribute('data-wp-on--submit'));
    }

    /** @dataProvider views */
    public function test_every_input_writes_a_string_field_and_has_a_label(GatewayView $view): void
    {
        $form = self::form($view);
        $document = self::parse($form);
        $state = $form->getInitialState();
        $inputs = $document->querySelectorAll('[data-field]');

        $this->assertGreaterThan(0, $inputs->length);
        foreach ($inputs as $input) {
            $field = (string) $input->getAttribute('data-field');
            $this->assertIsString($state[$field] ?? null, "data-field=\"{$field}\" is no string field of the state");

            $id = (string) $input->getAttribute('id');
            $this->assertNotSame('', $id, "{$field}: no id");
            $this->assertNotNull($document->querySelector("label[for=\"{$id}\"]"), "{$field}: no label for #{$id}");
        }
    }

    /**
     * The theme's URL is put somewhere else; every form's icons must follow
     * it. (The test run's own theme URL is a file path, which esc_url()
     * drops.)
     */
    public function test_icons_come_from_the_installed_theme(): void
    {
        $theme = 'https://cdn.example.org/sub/themes/renamed';
        add_filter('template_directory_uri', static fn(): string => $theme);

        $icons = 0;
        foreach (GatewayView::cases() as $view) {
            foreach (self::parse(self::form($view))->querySelectorAll('use') as $use) {
                $this->assertStringStartsWith("{$theme}/assets/img/sprites.svg#", (string) $use->getAttribute('href'), $view->value);
                $icons++;
            }
        }
        $this->assertGreaterThan(0, $icons);
    }

    public function test_form_ids_become_js_names(): void
    {
        $form = new class extends AbstractForm {
            public function getId(): string { return 'magic_link-login'; }
            public function getLabel(): string { return ''; }
            public function getInitialState(?int $userId = null): array { return []; }
            public function render(): string { return ''; }
        };

        $this->assertSame('magicLinkLogin', $form->getJsId());
        $this->assertSame('isMagicLinkLoginActive', $form->getStateKey());
    }
}
