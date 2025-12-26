<?php
// File: inc/launchpad/Services/ProfileService.php

declare(strict_types=1);

namespace Launchpad\Services;

use WP_Error;

class ProfileService
{
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

        // --- PHONE NUMBER HANDLING ---
        $phoneRaw = get_field('phone', $acfId);
        $phoneString = '';
        error_log('phone field raw: ' . print_r($phoneRaw, true));

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
            'avatarUrl' => get_avatar_url($userId, ['size' => 150]),

            // Return flattened string for the frontend Input
            // instead of 'phone'     => get_field('phone', $acfId) ?: '',
            'phone'     => $phoneString,
            'telegram'  => get_field('telegram', $acfId) ?: '',
        ];
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

        // --- Replace the existing phone update block with this ---
        if (isset($data['phone'])) {
            $rawPhone = (string) $data['phone'];

            // Normalize whitespace and remove control characters
            $normalized = preg_replace('/\s+/u', ' ', trim($rawPhone));

            // Pretty (readable) and strict E.164 forms
            $savePretty = preg_replace('/[^\d\+\s]/u', '', $normalized); // +380 44 123 1231
            $saveE164   = preg_replace('/[^\d\+]/', '', $savePretty);     // +380441231231

            // Get field object and key (safer than using the name)
            $fieldObj = get_field_object('phone', $acfId);
            $acfFieldKey = is_array($fieldObj) && !empty($fieldObj['key']) ? $fieldObj['key'] : 'phone';

            // Resolve default country from field settings, fallback to 'us'
            $defaultCountry = 'us';
            if (is_array($fieldObj)) {
                if (!empty($fieldObj['default_country'])) {
                    $defaultCountry = $fieldObj['default_country'];
                } elseif (!empty($fieldObj['value']['country'])) {
                    $defaultCountry = $fieldObj['value']['country'];
                }
            }

            // Try: save as array { number, country } (pretty first)
            error_log('ProfileService.save: trying to save pretty phone array: ' . $savePretty . ' country: ' . $defaultCountry);
            $valueArr = ['number' => $savePretty, 'country' => $defaultCountry];
            $updated = update_field($acfFieldKey, $valueArr, $acfId);
            $after = get_field('phone', $acfId);
            error_log('ProfileService.save: after save (pretty) -> ' . print_r($after, true) . '; update_field returned: ' . var_export($updated, true));

            // Fallback: try E.164 numeric (no spaces)
            if (empty($after)) {
                error_log('ProfileService.save: pretty save produced no usable value, trying E164: ' . $saveE164);
                $valueArr2 = ['number' => $saveE164, 'country' => $defaultCountry];
                $updated2 = update_field($acfFieldKey, $valueArr2, $acfId);
                $after2 = get_field('phone', $acfId);
                error_log('ProfileService.save: after save (e164) -> ' . print_r($after2, true) . '; update_field returned: ' . var_export($updated2, true));
                $finalPhoneRaw = $after2;
            } else {
                $finalPhoneRaw = $after;
            }

            // Flatten for response: if object -> use international/e164; if array -> use keys
            $phoneStringForResponse = '';
            if (is_object($finalPhoneRaw) && method_exists($finalPhoneRaw, 'international')) {
                $phoneStringForResponse = $finalPhoneRaw->international();
            } elseif (is_array($finalPhoneRaw)) {
                $phoneStringForResponse = $finalPhoneRaw['international'] ?? $finalPhoneRaw['e164'] ?? $finalPhoneRaw['number'] ?? '';
            } else {
                $phoneStringForResponse = (string) $finalPhoneRaw;
            }

            $data['phone'] = $phoneStringForResponse;
            error_log('ProfileService.save: phone final string for response: ' . $phoneStringForResponse);
        }

        if (isset($data['telegram'])) {
            update_field('telegram', sanitize_text_field($data['telegram']), $acfId);
        }

        // Return fresh data (Service handles the Array->String conversion again here)
        return $this->getProfileData($userId);
    }
}
