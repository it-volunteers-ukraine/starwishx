<?php
// File: inc/launchpad/Services/MediaService.php
declare(strict_types=1);

namespace Launchpad\Services;

use WP_Error;

class MediaService
{
    private const ALLOWED_MIMES = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
    ];

    private const MAX_SIZE = 5 * 1024 * 1024; // 5MB

    /**
     * Handle the file upload securely.
     */
    public function uploadFile(array $file, int $userId): array|WP_Error
    {
        // 1. Basic Validation
        if ($file['size'] > self::MAX_SIZE) {
            return new WP_Error('file_too_large', __('File is too large. Max 5MB.', 'starwishx'));
        }

        // 2. Strict MIME Validation (Magic Bytes)
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $realMime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($realMime, self::ALLOWED_MIMES)) {
            return new WP_Error('invalid_mime', __('Invalid file type. Only PDF and DOC/DOCX allowed.', 'starwishx'));
        }

        // 3. Prepare for WordPress Core Upload
        if (!function_exists('media_handle_sideload')) {
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');
        }

        // 4. Handle Upload
        $upload_overrides = ['test_form' => false];
        $movefile = wp_handle_upload($file, $upload_overrides);

        if (isset($movefile['error'])) {
            return new WP_Error('upload_error', $movefile['error']);
        }

        // 5. Create Attachment Record
        $filename = preg_replace('/\.[^.]+$/', '', basename($file['name'])); // Sanitize filename
        $attachment = [
            'guid'           => $movefile['url'],
            'post_mime_type' => $movefile['type'],
            'post_title'     => $filename,
            'post_content'   => '',
            'post_status'    => 'inherit',
            'post_author'    => $userId // Crucial for ownership checks later
        ];

        $attach_id = wp_insert_attachment($attachment, $movefile['file']);

        if (is_wp_error($attach_id)) {
            return $attach_id;
        }

        // 6. Generate Meta
        $attach_data = wp_generate_attachment_metadata($attach_id, $movefile['file']);
        wp_update_attachment_metadata($attach_id, $attach_data);

        // 7. Security & Cleanup Tags
        // Tag as temporary so Cron can clean it if not claimed
        update_post_meta($attach_id, '_launchpad_temp_upload', time());
        // Explicitly tag owner for double-verification
        update_post_meta($attach_id, '_launchpad_uploaded_by', $userId);

        return [
            'id'   => $attach_id,
            'url'  => $movefile['url'],
            'name' => $filename,
            'size' => size_format($file['size'], 2)
        ];
    }

    /**
     * Cron Job Handler: Delete orphans older than 24 hours.
     */
    public function cleanupOrphans(): void
    {
        $query = new \WP_Query([
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'posts_per_page' => 50,
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'     => '_launchpad_temp_upload',
                    'value'   => time() - DAY_IN_SECONDS, // Older than 24h
                    'compare' => '<',
                    'type'    => 'NUMERIC'
                ]
            ]
        ]);

        if (!empty($query->posts)) {
            foreach ($query->posts as $attach_id) {
                wp_delete_attachment($attach_id, true);
            }
        }
    }
}
