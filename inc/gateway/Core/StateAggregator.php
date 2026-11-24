<?php
// File: inc/gateway/Core/StateAggregator.php

declare(strict_types=1);

namespace Gateway\Core;

use Gateway\Forms\AbstractForm;
use Shared\Contracts\StateAggregatorInterface;

/**
 * The forms' state for SSR hydration (templates/page-gateway.php), merged
 * into the 'gateway' namespace over GatewayCore::enqueueAssets()' settings.
 * Shape: inc/gateway/Assets/types.ts (GatewayServerState).
 */
final class StateAggregator implements StateAggregatorInterface
{
    /**
     * @param array<string, AbstractForm> $forms        FormRegistry::getAll() - only forms get in.
     * @param string                      $activeFormId The form to show.
     * @param int|null                    $userId       Unused: the Gateway is guest-only.
     * @return array{activeView: string, formMap: array<string, string>, forms: array<string, array<string, mixed>>}&array<string, mixed>
     */
    #[\Override]
    public function aggregate(array $forms, string $activeFormId, ?int $userId = null): array
    {
        $state = [
            'activeView' => $activeFormId,
            'formMap'    => [],
            'forms'      => [],
        ];

        foreach ($forms as $id => $form) {
            // The visibility flag (e.g. isLostPasswordActive): the TS store
            // sets it from ?view, the template binds `hidden` to it.
            $stateKey = $form->getStateKey();

            $state['formMap'][$id] = $stateKey;
            $state['forms'][$form->getJsId()] = $form->getInitialState($userId);
            $state[$stateKey] = ($id === $activeFormId);
        }

        return $state;
    }
}
