<?php
// File: inc/tour/Registry/ScenarioRegistry.php

declare(strict_types=1);

namespace Tour\Registry;

use Shared\Core\AbstractRegistry;
use Tour\Contracts\ScenarioInterface;

class ScenarioRegistry extends AbstractRegistry
{
    public function register(string $id, object $scenario, int $priority = 10): void
    {
        if (!$scenario instanceof ScenarioInterface) {
            throw new \InvalidArgumentException('Scenario must implement ScenarioInterface');
        }
        parent::register($id, $scenario, $priority);
    }
}
