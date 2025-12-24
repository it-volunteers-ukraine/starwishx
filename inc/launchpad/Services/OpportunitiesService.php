<?php
// File: inc/launchpad/Services/OpportunitiesService.php

declare(strict_types=1);

namespace Launchpad\Services;

use WP_Error;

class OpportunitiesService
{
    public const ITEMS_PER_PAGE = 2;

    /**
     * Get list of opportunities for the dashboard grid.
     */
    public function getUserOpportunities(int $userId, int $limit = self::ITEMS_PER_PAGE, int $offset = 0): array
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

        $query = new \WP_Query($args);

        $opportunities = [];
        foreach ($query->posts as $post) {
            $opportunities[] = [
                'id'        => $post->ID,
                'title'     => $post->post_title,
                'date'      => get_the_date('', $post->ID),
                'status'    => $post->post_status,
                // Using camelCase for JS consistency
                'editUrl'   => get_edit_post_link($post->ID),
                'viewUrl'   => get_permalink($post->ID),
            ];
        }

        return $opportunities;
    }

    public function countUserOpportunities(int $userId): int
    {
        $args = [
            'post_type'      => 'opportunity',
            'author'         => $userId,
            'posts_per_page' => -1,
            'post_status'    => ['publish', 'draft', 'pending'],
        ];

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
        $postData = [
            'post_type'   => 'opportunity',
            'post_title'  => sanitize_text_field($data['title']),
            'post_status' => 'pending', // Always pending on save? Or keep existing status?
            'post_author' => get_current_user_id(),
        ];

        // 1. Create or Update Core Post
        if ($postId) {
            $postData['ID'] = $postId;
            // Optional: Preserve status if editing
            // $postData['post_status'] = get_post_status($postId); 
            $id = wp_update_post($postData, true);
        } else {
            $id = wp_insert_post($postData, true);
        }

        if (is_wp_error($id)) {
            return $id;
        }

        // 2. Save ACF Fields
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
        // Category
        $catId = (int) ($data['category'] ?? 0);
        update_field('opportunity_category', $catId, $id);
        wp_set_object_terms($id, $catId, 'category-oportunities');

        // Country
        $countryId = (int) ($data['country'] ?? 0);
        update_field('country', $countryId, $id);
        wp_set_object_terms($id, $countryId, 'country');

        // Seekers (Multi)
        $seekers = array_map('intval', $data['seekers'] ?? []);
        update_field('opportunity_seekers', $seekers, $id);
        wp_set_object_terms($id, $seekers, 'category-seekers');

        // Group 3
        update_field('opportunity_description', $data['description'], $id);
        update_field('opportunity_requirements', $data['requirements'], $id);
        update_field('opportunity_details', $data['details'], $id);

        return $id;
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
}
