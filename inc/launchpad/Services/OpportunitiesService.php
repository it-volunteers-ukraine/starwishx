<?php
// File: inc/launchpad/Services/OpportunitiesService.php
declare(strict_types=1);

namespace Launchpad\Services;

use WP_Error;

class OpportunitiesService
{
    public const ITEMS_PER_PAGE = 4;

    /**
     * Get list of opportunities with flexible filtering.
     * 
     * @param int   $userId
     * @param array $filters [ 'statuses' => [], 'categories' => [] ]
     * @param int   $limit
     * @param int   $offset
     */
    public function getUserOpportunities(int $userId, array $filters = [], int $limit = self::ITEMS_PER_PAGE, int $offset = 0): array
    {
        $args = [
            'post_type'      => 'opportunity',
            'author'         => $userId,
            'posts_per_page' => $limit,
            'offset'         => $offset,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'post_status'    => ['publish', 'draft', 'pending'],
        ];

        // 1. Filter by Statuses (Dynamic)
        if (!empty($filters['statuses'])) {
            // Only allow valid statuses to be queried
            $allowed = ['publish', 'draft', 'pending'];
            $args['post_status'] = array_intersect($filters['statuses'], $allowed);
        }
        // 2. Filter by Categories (Existing)
        if (!empty($filters['categories'])) {
            $args['tax_query'] = [[
                'taxonomy' => 'category-oportunities',
                'field'    => 'term_id',
                'terms'    => $filters['categories'],
            ]];
        }

        $query = new \WP_Query($args);

        $opportunities = [];
        foreach ($query->posts as $post) {
            // -1. :) Get category-oportunities
            $terms = get_the_terms($post->ID, 'category-oportunities');
            $top_category = '';

            if ($terms && !is_wp_error($terms)) {
                foreach ($terms as $term) {
                    // We only want the top-level parent (parent ID is 0)
                    if ($term->parent === 0) {
                        $top_category = $term->name;
                        break; // Stop after finding the first root category
                    }
                }

                // Fallback: If no root category is assigned but subcategories are,
                // we could optionally fetch the parent of a subcategory.
                if (empty($top_category) && !empty($terms)) {
                    $first_term = reset($terms);
                    $parent_term = get_term($first_term->parent, 'category-oportunities');
                    if ($parent_term && !is_wp_error($parent_term)) {
                        $top_category = $parent_term->name;
                    }
                }
            }

            // 0. Image Logic: Fetch 'medium' size for grid performance
            $thumbnail_id = get_post_thumbnail_id($post->ID);
            $thumbnail_url = $thumbnail_id ? wp_get_attachment_image_url($thumbnail_id, 'medium') : null;

            // 1. Get raw data
            $raw_excerpt    = $post->post_excerpt;
            // $raw_description = get_field('opportunity_description', $post->ID) ?: '';
            // Should be more optimal 
            $raw_description = get_post_meta($post->ID, 'opportunity_description', true) ?: '';
            // 2. Logic: Priority to native excerpt, fallback to trimmed description
            $display_text = !empty($raw_excerpt) ? $raw_excerpt : $raw_description;
            // 3. Clean and Truncate (approx 20 words for a clean card look)
            $trimmed_excerpt = wp_trim_words($display_text, 30, '...');

            // Fetch ACF Dates (using get_post_meta for performance in loops)
            $raw_date_starts = get_post_meta($post->ID, 'opportunity_date_starts', true);
            $raw_date_ends   = get_post_meta($post->ID, 'opportunity_date_ends', true);
            // Logic: Is Expired?
            $is_expired = false;
            if (!empty($raw_date_ends)) {
                // Assuming ACF stores as 'd/m/Y' based on your single-opportunity.php
                // $end_dt = \DateTime::createFromFormat('d/m/Y', $raw_date_ends);
                // Try Ymd first (raw meta), then d/m/Y (ACF format)
                $end_dt = \DateTime::createFromFormat('Ymd', $raw_date_ends) ?: \DateTime::createFromFormat('d/m/Y', $raw_date_ends);
                if ($end_dt && $end_dt < new \DateTime('today')) {
                    $is_expired = true;
                }
            }

            $opportunities[] = [
                'id'            => $post->ID,
                'title'         => $post->post_title,
                'excerpt'       => $trimmed_excerpt,
                'thumbnailUrl'  => $thumbnail_url,
                'date'          => get_the_date('d.m.y', $post->ID),
                'status'        => $post->post_status,
                'dateStarts'    => $this->formatDateForUI($raw_date_starts),
                'dateEnds'      => $this->formatDateForUI($raw_date_ends),
                'isExpired'     => $is_expired,
                'commentsCount' => (int) get_comments_number($post->ID),
                'categoryName'  => $top_category,
                'editUrl'       => get_edit_post_link($post->ID),
                'viewUrl'       => get_permalink($post->ID),
            ];
        }

        return $opportunities;
    }

    public function countUserOpportunities(int $userId, array $filters = []): int
    {
        $args = [
            'post_type'      => 'opportunity',
            'author'         => $userId,
            'posts_per_page' => -1,
            'post_status'    => ['publish', 'draft', 'pending'],
        ];

        if (!empty($filters['statuses'])) {
            $allowed = ['publish', 'draft', 'pending'];
            $args['post_status'] = array_intersect($filters['statuses'], $allowed);
        }

        $query = new \WP_Query($args);
        return $query->found_posts;
    }

    /**
     * Fetch taxonomy terms to populate frontend dropdowns.
     */
    public function getFormOptions(): array
    {
        return [
            'categories' => $this->getTerms('category-oportunities'),
            'countries'  => $this->getTerms('country'),
            'seekers'    => $this->getTerms('category-seekers'),
        ];
    }

    private function getTerms(string $taxonomy): array
    {
        $terms = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false]);

        if (is_wp_error($terms)) {
            return [];
        }

        return array_map(fn($t) => [
            'id' => $t->term_id,
            'name' => $t->name
        ], $terms);
    }

    /**
     * Fetch a single opportunity with all ACF fields for editing.
     */
    public function getSingleOpportunity(int $userId, int $postId): ?array
    {
        $post = get_post($postId);

        // Security: Ensure post exists, is opportunity, and belongs to user
        if (!$post || $post->post_type !== 'opportunity' || (int) $post->post_author !== $userId) {
            return null;
        }

        // Helper to safely get array of ints
        $getIntArray = fn($field) => array_map('intval', get_field($field, $postId) ?: []);

        // Map ACF fields to our simplified FormData structure
        return [
            'id' => $post->ID,
            'title' => $post->post_title,
            'status' => $post->post_status,

            // Group 1: Applicant
            'applicant_name'  => get_field('opportunity_applicant_name', $postId) ?: '',
            'applicant_mail'  => get_field('opportunity_applicant_mail', $postId) ?: '',
            'applicant_phone' => get_field('opportunity_applicant_phone', $postId) ?: '',

            // Group 2: Info
            'company'         => get_field('opportunity_company', $postId) ?: '',
            'date_starts'     => $this->formatDateForInput(get_field('opportunity_date_starts', $postId)),
            'date_ends'       => $this->formatDateForInput(get_field('opportunity_date_ends', $postId)),
            'category'        => get_field('opportunity_category', $postId) ?: '',
            'country'         => get_field('country', $postId) ?: '',
            'city'            => get_field('city', $postId) ?: '',
            'sourcelink'      => get_field('opportunity_sourcelink', $postId) ?: '',

            // FIX: Ensure these are Integers for JS .includes() check
            'subcategory'     => $getIntArray('opportunity_subcategory'),
            'seekers'         => $getIntArray('opportunity_seekers'),

            // Group 3: Description
            'description'     => get_field('opportunity_description', $postId) ?: '',
            'requirements'    => get_field('opportunity_requirements', $postId) ?: '',
            'details'         => get_field('opportunity_details', $postId) ?: '',
        ];
    }

    /**
     * Save Opportunity (Core Post + ACF Fields).
     */
    public function saveOpportunity(array $data, ?int $postId = null): int|WP_Error
    {
        global $wpdb;
        $current_user_id = get_current_user_id();
        // If updating, verify ownership inside the service too.
        if ($postId) {
            $existing_post = get_post($postId);
            if (
                !$existing_post
                || $existing_post->post_type !== 'opportunity'
                || (int)$existing_post->post_author !== $current_user_id
            ) {
                return new WP_Error('forbidden', 'You do not have permission to edit this item.');
            }
        }

        $wpdb->query('START TRANSACTION');

        try {
            // 1. Prepare Core Post Data
            $postData = [
                'post_type'   => 'opportunity',
                'post_title'  => sanitize_text_field($data['title']),
                'post_author' => get_current_user_id(),
            ];

            // Determine Status
            // Logic: Use provided status, or default to 'draft' for new, or preserve for updates.
            if (!empty($data['status'])) {
                $postData['post_status'] = $data['status'];
            } elseif (!$postId) {
                $postData['post_status'] = 'draft';
            }
            // Write Post
            if ($postId) {
                $postData['ID'] = $postId;
                $id = wp_update_post($postData, true);
            } else {
                $id = wp_insert_post($postData, true);
            }

            // If core post fails, we abort immediately
            if (is_wp_error($id)) {
                throw new \Exception($id->get_error_message());
            }

            // 2. Save ACF Fields
            // Note: update_field returns false on failure OR if the value didn't change.
            // We generally trust ACF here, but we could check specifically if needed.
            // Group 1
            update_field('opportunity_applicant_name', $data['applicant_name'], $id);
            update_field('opportunity_applicant_mail', $data['applicant_mail'], $id);
            update_field('opportunity_applicant_phone', $data['applicant_phone'], $id);

            // Group 2
            update_field('opportunity_company', $data['company'], $id);
            update_field('opportunity_date_starts', $data['date_starts'], $id);
            update_field('opportunity_date_ends', $data['date_ends'], $id);
            update_field('city', $data['city'], $id);
            update_field('opportunity_sourcelink', $data['sourcelink'], $id);

            // Taxonomies: Save to ACF field AND actual WP Taxonomy
            // wp_set_object_terms returns WP_Error or Term IDs
            // Category
            $catId = (int) ($data['category'] ?? 0);
            update_field('opportunity_category', $catId, $id);
            wp_set_object_terms($id, $catId, 'category-oportunities');
            // $catRes = wp_set_object_terms($id, (int)($data['category'] ?? 0), 'category-oportunities');
            // if (is_wp_error($catRes)) throw new \Exception(__('Failed to save category.', 'starwishx'));

            // Country
            $countryId = (int) ($data['country'] ?? 0);
            update_field('country', $countryId, $id);
            wp_set_object_terms($id, $countryId, 'country');
            // $countRes = wp_set_object_terms($id, (int)($data['country'] ?? 0), 'country');
            // if (is_wp_error($countRes)) throw new \Exception(__('Failed to save country.', 'starwishx'));

            // Seekers (Multi)
            $seekers = array_map('intval', $data['seekers'] ?? []);
            update_field('opportunity_seekers', $seekers, $id);
            wp_set_object_terms($id, $seekers, 'category-seekers');
            // $seekRes = wp_set_object_terms($id, $seekers, 'category-seekers');
            // if (is_wp_error($seekRes)) throw new \Exception(__('Failed to save seekers.', 'starwishx'));

            // Group 3
            update_field('opportunity_description', $data['description'], $id);
            update_field('opportunity_requirements', $data['requirements'], $id);
            update_field('opportunity_details', $data['details'], $id);

            $wpdb->query('COMMIT');
            return $id;
        } catch (\Throwable $e) {
            $wpdb->query('ROLLBACK');
            // Log the actual error for the developer
            error_log('OpportunitiesService Save Failure: ' . $e->getMessage());

            return new WP_Error('db_transaction_failed', $e->getMessage());
        }
    }

    /**
     * Update only the status of an opportunity.
     */
    public function updateStatus(int $userId, int $postId, string $newStatus): int|WP_Error
    {
        $post = get_post($postId);

        // 1. Validation & Permissions
        if (!$post || $post->post_type !== 'opportunity' || (int) $post->post_author !== $userId) {
            return new WP_Error('forbidden', __('Access denied.', 'starwishx'));
        }

        // 2. Workflow Rules (Logic check)
        // Contributors can only submit 'drafts' for review.
        if ($newStatus === 'pending' && $post->post_status !== 'draft') {
            return new WP_Error('invalid_transition', __('Only drafts can be submitted for review.', 'starwishx'));
        }

        // 3. Atomic Update
        return wp_update_post([
            'ID'          => $postId,
            'post_status' => $newStatus,
        ], true);
    }

    /**
     * Helper: Convert ACF 'd/m/Y' to HTML5 'Y-m-d' for date inputs.
     */
    private function formatDateForInput($dateStr): string
    {
        if (empty($dateStr)) {
            return '';
        }
        $date = \DateTime::createFromFormat('d/m/Y', $dateStr);
        return $date ? $date->format('Y-m-d') : '';
    }

    /**
     * Helper: Convert various date strings to a UI-friendly format (d.m.y).
     * 
     * @param string|null $dateStr Raw date from DB (usually Ymd or d/m/Y)
     * @param string $format The target format, defaults to d.m.y
     * @return string
     */
    private function formatDateForUI(?string $dateStr, string $format = 'd.m.y'): string
    {
        if (empty($dateStr)) {
            return '';
        }

        // 1. Try ACF's raw DB format (Ymd - 20210903)
        $date = \DateTime::createFromFormat('Ymd', $dateStr);

        // 2. Fallback: Try d/m/Y (if ACF formatting was applied)
        if (!$date) {
            $date = \DateTime::createFromFormat('d/m/Y', $dateStr);
        }

        // 3. Fallback: Try standard Y-m-d
        if (!$date) {
            $date = \DateTime::createFromFormat('Y-m-d', $dateStr);
        }

        return $date ? $date->format($format) : $dateStr;
    }
}
