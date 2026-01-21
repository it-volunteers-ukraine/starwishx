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
            // Convert 'lost-password' to 'lostPassword' for JS property access
            $jsFriendlyKey = str_replace(' ', '', lcfirst(ucwords(str_replace(['-', '_'], ' ', $id))));
            $stateKey = $form->getStateKey(); // e.g., 'isLostPasswordActive'

            $state['formMap'][$id] = $stateKey;

            if ($form instanceof StateProviderInterface) {
                // Use the camelCase key here!
                $state['forms'][$jsFriendlyKey] = $form->getInitialState($userId);
            }

            $state[$stateKey] = ($id === $activeFormId);
        }

        return $state;
    }
}
