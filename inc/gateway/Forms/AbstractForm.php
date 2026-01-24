<?php

declare(strict_types=1);

namespace Gateway\Forms;

use Shared\Contracts\RenderableInterface;
use Shared\Contracts\StateProviderInterface;
use Shared\Core\Traits\BufferedRenderTrait;

/**
 * Base class for Gateway forms.
 * Mirrors Launchpad's AbstractPanel pattern.
 */
abstract class AbstractForm implements RenderableInterface, StateProviderInterface
{
    use BufferedRenderTrait;

    abstract public function getId(): string;
    abstract public function getLabel(): string;
    abstract public function getInitialState(?int $userId = null): array;
    abstract public function render(): string;

    /**
     * Standardizes the key used in JS: 'login' -> 'isLoginActive'
     */
    public function getStateKey(): string
    {
        $id = $this->getId();
        $camel = str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $id)));
        return 'is' . $camel . 'Active';
    }

    /**
     * Returns JavaScript-friendly ID (camelCase).
     * Converts: 'lost-password' → 'lostPassword', 'reset-password' → 'resetPassword'
     * Ensures alignment with JS store action naming.
     */
    public function getJsId(): string
    {
        return str_replace(' ', '', lcfirst(ucwords(str_replace(['-', '_'], ' ', $this->getId()))));
    }
}
