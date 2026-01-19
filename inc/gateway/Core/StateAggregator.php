<?php
// File: inc\gateway\Core\StateAggregator.php
declare(strict_types=1);

namespace Gateway\Core;

use Shared\Contracts\StateAggregatorInterface;
use Shared\Contracts\StateProviderInterface;

class StateAggregator implements StateAggregatorInterface
{
    public function aggregate(array $forms, string $activeFormId, ?int $userId = null): array
    {
        $state = [
            'activeView' => $activeFormId,
            'formMap'    => [],
            'forms'      => [],
        ];

        foreach ($forms as $id => $form) {
            $stateKey = $form->getStateKey();

            $state['formMap'][$id] = $stateKey;

            if ($form instanceof StateProviderInterface) {
                $state['forms'][$id] = $form->getInitialState($userId);
            }

            // Set the boolean for SSR
            $state[$stateKey] = ($id === $activeFormId);
        }

        return $state;
    }
}
