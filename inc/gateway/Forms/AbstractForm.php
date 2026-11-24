<?php

declare(strict_types=1);

namespace Gateway\Forms;

use Shared\Contracts\RenderableInterface;
use Shared\Contracts\StateProviderInterface;
use Shared\Core\Traits\BufferedRenderTrait;

/**
 * Base class for Gateway forms.
 * Mirrors Launchpad's AbstractPanel pattern.
 *
 * The form's id is its FormRegistry id and `?view=` value; the JS names
 * derive from it (getJsId(), getStateKey()) and the TS store derives the
 * same names by type (inc/gateway/Assets/types.ts: FormKey, ActiveFlag).
 */
abstract class AbstractForm implements RenderableInterface, StateProviderInterface
{
    use BufferedRenderTrait;

    #[\Override]
    abstract public function getId(): string;

    #[\Override]
    abstract public function getLabel(): string;

    /**
     * The form's state under `state.forms.<jsId>`.
     *
     * @return array<string, mixed>
     */
    #[\Override]
    abstract public function getInitialState(?int $userId = null): array;

    #[\Override]
    abstract public function render(): string;

    /**
     * The form's visibility flag in JS: 'lost-password' -> 'isLostPasswordActive'.
     */
    #[\Override]
    public function getStateKey(): string
    {
        return 'is' . $this->pascalId() . 'Active';
    }

    /**
     * The form's key in JS (state.forms, actions): 'lost-password' -> 'lostPassword'.
     */
    public function getJsId(): string
    {
        return lcfirst($this->pascalId());
    }

    /**
     * An icon in the theme's sprite, wherever the theme is installed (the
     * folder name and a WordPress in a subdirectory both vary).
     */
    protected function sprite(string $icon): string
    {
        return get_template_directory_uri() . '/assets/img/sprites.svg#' . $icon;
    }

    /** 'lost-password' / 'lost_password' -> 'LostPassword'. */
    private function pascalId(): string
    {
        return $this->getId()
            |> (static fn(string $id): string => str_replace(['-', '_'], ' ', $id))
            |> ucwords(...)
            |> (static fn(string $words): string => str_replace(' ', '', $words));
    }
}
