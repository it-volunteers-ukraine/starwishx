<?php
// File: inc/launchpad/Core/StateAggregator.php
declare(strict_types=1);

namespace Launchpad\Core;

class StateAggregator
{
    public function aggregate(array $panels, int $userId, string $activePanelId): array
    {
        $user = get_userdata($userId);
        $state = [
            'activePanel' => $activePanelId,
            'panelMap'    => [], // The Manifest for JS
            'panels'      => [],
            'navigation'  => [],
            'user'        => [
                'id'          => $userId,
                'displayName' => $user->display_name ?? '',
                'avatarUrl'   => get_avatar_url($userId, ['size' => 48]),
            ],
        ];

        foreach ($panels as $id => $panel) {
            $stateKey = $panel->getStateKey();

            $state['panelMap'][$id] = $stateKey;

            $state['navigation'][] = [
                'id'    => $panel->getId(),
                'label' => $panel->getLabel(),
                'icon'  => $panel->getIcon(),
            ];

            $panelState = ($id === $activePanelId) ? $panel->getInitialState($userId) : ['_loaded' => false];
            $state['panels'][$id] = array_merge([
                '_loaded'   => true,
                'isLoading' => false,
                'isSaving'  => false,
                'error'     => null,
            ], $panelState);

            $state[$stateKey] = ($id === $activePanelId);
        }

        return $state;
    }

    private function toCamelCase(string $id): string
    {
        return lcfirst(str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $id))));
    }
}
