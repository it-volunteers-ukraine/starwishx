<?php

declare(strict_types=1);

namespace Launchpad\Panels;

use Launchpad\Contracts\PanelInterface;

abstract class AbstractPanel implements PanelInterface
{
    protected const NAMESPACE  = 'launchpad';

    protected function statePath(string $key): string
    {
        return sprintf('state.panels.%s.%s', $this->getId(), $key);
    }

    public function getIcon(): string
    {
        return 'admin-generic';
    }

    protected function startBuffer(): void
    {
        ob_start();
    }

    protected function endBuffer(): string
    {
        return ob_get_clean();
    }
}
