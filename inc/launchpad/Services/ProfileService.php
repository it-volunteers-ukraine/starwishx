<?php
// File: inc/launchpad/Services/ProfileService.php
declare(strict_types=1);

namespace Launchpad\Services;

use Shared\Policy\PhonePolicy;
use WP_Error;

class ProfileService
{
    /**
     * Translatable role labels for Launchpad users.
     */
    public static function getRoleLabel(string $slug): string
    {
        $labels = [
            'subscriber'    => __('Subscriber', 'starwishx'),
            'contributor'   => __('Contributor', 'starwishx'),
            'author'        => __('Author', 'starwishx'),
            'editor'        => __('Editor', 'starwishx'),
            'administrator' => __('Administrator', 'starwishx'),
        ];

        return $labels[$slug] ?? ucfirst($slug);
    }

    /**
     * Get standardized profile data (Core + ACF).
     */
    public function getProfileData(int $userId): array
    {
        $user = get_userdata($userId);

        if (!$user) {
            return [];
        }

        // ACF for Users requires 'user_{id}' format
        $acfId = 'user_' . $userId;

        // Phone field: ACF Phone Number plugin returns object/array, flatten to E.164 string
        $phoneRaw = get_field('phone', $acfId);
        $phoneString = '';

        if (is_array($phoneRaw)) {
            // If ACF returned an array, try the international or e164 keys
            $phoneString = $phoneRaw['international'] ?? $phoneRaw['e164'] ?? '';
        } elseif (is_object($phoneRaw)) {
            // If it's the PhoneNumber object from log1x/acf-phone-number, prefer its methods
            if (method_exists($phoneRaw, 'international') || method_exists($phoneRaw, 'e164')) {
                // try methods first
                $phoneString = (method_exists($phoneRaw, 'international') ? $phoneRaw->international() : '')
                    ?: (method_exists($phoneRaw, 'e164') ? $phoneRaw->e164() : '');
            } elseif (method_exists($phoneRaw, 'toArray')) {
                // fall back to toArray()
                $arr = $phoneRaw->toArray();
                $phoneString = $arr['international'] ?? $arr['e164'] ?? '';
            } else {
                // last resort: try reading public property
                $phoneString = $phoneRaw->international ?? $phoneRaw->e164 ?? '';
            }
        } else {
            // fallback if plugin inactive or raw value is a string
            $phoneString = (string) $phoneRaw;
        }


        return [
            'id'        => $user->ID,
            'firstName' => $user->first_name,
            'lastName'  => $user->last_name,
            'email'     => $user->user_email,
            'role'      => $user->roles[0] ?? 'subscriber',
            'roleLabel' => self::getRoleLabel($user->roles[0] ?? 'subscriber'),
            'avatarUrl' => get_avatar_url($userId, ['size' => 150]),

            // Return flattened string for the frontend Input
            // instead of 'phone'     => get_field('phone', $acfId) ?: '',
            'phone'        => $phoneString,
            'telegram'     => get_field('telegram', $acfId) ?: '',
            'organization' => get_field('organization', $acfId) ?: '',
        ];
    }

    /**
     * Check if a user has a complete profile (name + phone).
     * Single source of truth for profile completeness gating.
     */
    public function isProfileComplete(int $userId): bool
    {
        $user = get_userdata($userId);
        if (!$user) return false;

        $acfId = 'user_' . $userId;
        $phone = get_field('phone', $acfId);

        $hasPhone = is_array($phone)
            ? !empty($phone['e164'] ?? $phone['international'] ?? '')
            : !empty((string) $phone);

        return !empty($user->first_name) && $hasPhone;
    }

    /**
     * Update user profile (Core + ACF).
     */
    public function updateProfile(int $userId, array $data): array|WP_Error
    {
        // 1. Update Core WP User Data
        $coreArgs = ['ID' => $userId];
        $hasCoreUpdate = false;

        if (isset($data['firstName'])) {
            $coreArgs['first_name'] = sanitize_text_field($data['firstName']);
            $hasCoreUpdate = true;
        }
        if (isset($data['lastName'])) {
            $coreArgs['last_name']  = sanitize_text_field($data['lastName']);
            $hasCoreUpdate = true;
        }
        if (isset($data['email'])) {
            $coreArgs['user_email'] = sanitize_email($data['email']);
            $hasCoreUpdate = true;
        }

        if ($hasCoreUpdate) {
            $result = wp_update_user($coreArgs);
            if (is_wp_error($result)) {
                return $result;
            }
        }

        // 2. Update ACF Fields
        $acfId = 'user_' . $userId;

        if (isset($data['phone'])) {
            $e164 = preg_replace('/[^\d\+]/', '', (string) $data['phone']);
            $country = !empty($data['phoneCountry'])
                ? sanitize_text_field($data['phoneCountry'])
                : 'ua';

            // Server-side validation (libphonenumber when available, E.164 regex fallback)
            if ($e164 !== '') {
                $validation = PhonePolicy::validate($e164, $country);
                if (is_wp_error($validation)) {
                    return $validation;
                }
            }

            $fieldObj = get_field_object('phone', $acfId);
            $key = is_array($fieldObj) && !empty($fieldObj['key']) ? $fieldObj['key'] : 'phone';

            update_field($key, ['number' => $e164, 'country' => $country], $acfId);
        }

        if (isset($data['telegram'])) {
            update_field('telegram', sanitize_text_field($data['telegram']), $acfId);
        }

        if (isset($data['organization'])) {
            update_field('organization', sanitize_text_field($data['organization']), $acfId);
        }

        // 3. Fetch fresh data to see the result of the update
        $freshProfile = $this->getProfileData($userId);

        // 4. --- ROLE UPGRADE LOGIC ---
        $user = get_userdata($userId);

        // Only attempt upgrade if the user currently has the 'subscriber' role
        if ($user && in_array('subscriber', $user->roles, true)) {

            if ($this->isProfileComplete($userId)) {
                $user->remove_role('subscriber');
                $user->add_role('contributor');
                $freshProfile['_roleUpgraded'] = true;
                $freshProfile['role'] = 'contributor';
                $freshProfile['roleLabel'] = self::getRoleLabel('contributor');
            }
        }

        // Mirror: degrade contributor → subscriber if profile is now incomplete
        if ($user && in_array('contributor', $user->roles, true)) {
            if (!$this->isProfileComplete($userId)) {
                $user->remove_role('contributor');
                $user->add_role('subscriber');
                $freshProfile['_roleDegraded'] = true;
                $freshProfile['role'] = 'subscriber';
                $freshProfile['roleLabel'] = self::getRoleLabel('subscriber');
            }
        }

        // Returns an array (with optional flag), satisfying the return type
        return $freshProfile;
    }
}
