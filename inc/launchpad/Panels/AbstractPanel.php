<?php

declare(strict_types=1);

namespace Launchpad\Panels;

use Shared\Core\Traits\BufferedRenderTrait;
use Launchpad\Contracts\PanelInterface;

abstract class AbstractPanel implements PanelInterface
{
    use BufferedRenderTrait;

    protected const NAMESPACE  = 'launchpad';

    /**
     * Converts 'my-panel-id' to 'isMyPanelIdActive'
     */
    public function getStateKey(): string
    {
        $id = $this->getId();
        // Standardize: remove non-alphanumeric, camelCase it.
        $camel = str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $id)));
        return 'is' . $camel . 'Active';
    }

    protected function statePath(string $key): string
    {
        return sprintf('state.panels.%s.%s', $this->getId(), $key);
    }

    public function getIcon(): string
    {
        return 'admin-generic';
    }
}
