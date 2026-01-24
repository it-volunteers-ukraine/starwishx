<?php
// File: inc/gateway/Core/StateAggregator.php

declare(strict_types=1);

namespace Gateway\Core;

use Shared\Contracts\StateAggregatorInterface;
use Shared\Contracts\StateProviderInterface;
use Gateway\Forms\AbstractForm;

class StateAggregator implements StateAggregatorInterface
{
    /**
     * Aggregates state from multiple forms for SSR hydration.
     * 
     * @param array<string, object> $forms        List of form instances.
     * @param string                $activeFormId The ID of the form to be visible.
     * @param int|null              $userId       Optional user ID for personalized state.
     * @return array
     */
    public function aggregate(array $forms, string $activeFormId, ?int $userId = null): array
    {
        $state = [
            'activeView' => $activeFormId,
            'formMap'    => [],
            'forms'      => [],
        ];

        foreach ($forms as $id => $form) {
            /** 
             * 1. IDENTIFY THE FORM
             * 
             * We use the form's own getJsId() if available (Gateway forms),
             * otherwise we fall back to a manual transformation.
             */
            $jsKey = ($form instanceof AbstractForm)
                ? $form->getJsId()
                : str_replace(' ', '', lcfirst(ucwords(str_replace(['-', '_'], ' ', (string) $id))));

            /** 
             * 2. DETERMINE THE STATE KEY
             * 
             * The StateKey is the boolean flag used for FOUC protection and visibility.
             * e.g., 'isLostPasswordActive'
             */
            $stateKey = ($form instanceof AbstractForm)
                ? $form->getStateKey()
                : 'is' . ucfirst($jsKey) . 'Active';

            // Map the Registry ID to the State Visibility Key
            $state['formMap'][$id] = $stateKey;

            /** 
             * 3. EXTRACT INITIAL STATE
             * 
             * If the form provides a state, we nest it under the jsKey.
             */
            if ($form instanceof StateProviderInterface) {
                $state['forms'][$jsKey] = $form->getInitialState($userId);
            }

            /** 
             * 4. SET SSR VISIBILITY
             * 
             * This boolean is used by page-gateway.php for server-side hidden attributes.
             */
            $state[$stateKey] = ($id === $activeFormId);
        }

        return $state;
    }
}
