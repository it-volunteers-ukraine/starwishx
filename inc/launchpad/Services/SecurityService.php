<?php
// File: inc/launchpad/Services/SecurityService.php
declare(strict_types=1);

namespace Launchpad\Services;

use Shared\Policy\PasswordPolicy;
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

        if (strlen($newPassword) < PasswordPolicy::MIN_LENGTH) {
            return new WP_Error(
                'weak_password',
                sprintf(
                    __('Password must be at least %d characters long.', 'starwishx'),
                    PasswordPolicy::MIN_LENGTH
                )
            );
        }

        wp_set_password($newPassword, $userId);

        return true;
    }
}
