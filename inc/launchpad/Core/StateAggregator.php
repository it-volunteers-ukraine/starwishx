<?php

declare(strict_types=1);

namespace Launchpad\Core;

use Launchpad\Contracts\PanelInterface;

class StateAggregator
{

    public function aggregate(array $panels, int $userId, string $activePanelId): array
    {
        $user = get_userdata($userId);

        $state = [
            'activePanel' => $activePanelId,
            'panels' => [],
            'navigation' => [],
            'user' => [
                'id'          => $userId,
                'displayName' => $user->display_name ?? '',
                'avatarUrl'   => get_avatar_url($userId, ['size' => 48]),
            ],
        ];

        /** @var PanelInterface $panel */
        foreach ($panels as $id => $panel) {
            // Navigation
            $state['navigation'][] = [
                'id'    => $panel->getId(),
                'label' => $panel->getLabel(),
                'icon'  => $panel->getIcon(),
            ];

            // Panel state
            if ($id === $activePanelId) {
                $panelState = $panel->getInitialState($userId);
            } else {
                $panelState = ['_loaded' => false];
            }

            $state['panels'][$id] = array_merge(
                $this->getDefaultPanelState(),
                $panelState
            );
        }

        // Computed state helpers for template
        foreach (array_keys($panels) as $id) {
            $camelId = $this->toCamelCase($id);
            $state["is{$camelId}Active"] = ($id === $activePanelId);
        }

        return $state;
    }

    private function getDefaultPanelState(): array
    {
        return [
            '_loaded'   => true,
            'isLoading' => false,
            'isSaving'  => false,
            'error'     => null,
        ];
    }

    private function toCamelCase(string $id): string
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $id)));
    }
}
