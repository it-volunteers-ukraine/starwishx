<?php
// File: inc/gateway/Core/FormRegistry.php

declare(strict_types=1);

namespace Gateway\Core;

use Shared\Core\AbstractRegistry;
use Gateway\Forms\AbstractForm;

/**
 * Registry for Gateway forms.
 * Extends shared AbstractRegistry with form-specific typing: only
 * AbstractForm instances get in, so everything read back is one.
 */
final class FormRegistry extends AbstractRegistry
{
    /**
     * Register a form with priority.
     */
    #[\Override]
    public function register(string $id, object $instance, int $priority = 10): void
    {
        if (!$instance instanceof AbstractForm) {
            throw new \InvalidArgumentException(
                sprintf('Form must extend AbstractForm, got %s', $instance::class)
            );
        }

        parent::register($id, $instance, $priority);
    }

    /**
     * Get form by ID with proper typing.
     */
    #[\Override]
    public function get(string $id): ?AbstractForm
    {
        $item = parent::get($id);
        return $item instanceof AbstractForm ? $item : null;
    }

    /**
     * Get all forms typed.
     *
     * @return array<string, AbstractForm>
     */
    #[\Override]
    public function getAll(): array
    {
        return parent::getAll();
    }
}
