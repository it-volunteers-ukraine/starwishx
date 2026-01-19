<?php
// File: inc/launchpad/Services/SecurityService.php
declare(strict_types=1);

namespace Launchpad\Services;

use WP_Error;

class SecurityService
{
    /**
     * Changes user password after verifying the current one.
     */
    public function changePassword(int $userId, string $currentPassword, string $newPassword): bool|WP_Error
    {
        $user = get_userdata($userId);

        if (!$user || !wp_check_password($currentPassword, $user->user_pass, $user->ID)) {
            return new WP_Error('invalid_password', __('The current password you entered is incorrect.', 'starwishx'));
        }

        if (strlen($newPassword) < 8) {
            return new WP_Error('weak_password', __('Password must be at least 8 characters long.', 'starwishx'));
        }

        wp_set_password($newPassword, $userId);

        return true;
    }
}
