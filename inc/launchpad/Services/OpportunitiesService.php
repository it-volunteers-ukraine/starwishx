<?php
// File: inc/launchpad/Services/OpportunitiesService.php
declare(strict_types=1);

namespace Launchpad\Services;

use Launchpad\Data\Repositories\CountriesRepository;
use Launchpad\Data\Repositories\OpportunityCountriesRepository;
use Launchpad\Data\Repositories\OpportunityDetailsRepository;
use Shared\Policy\UrlPolicy;
use WP_Error;

class OpportunitiesService
{
    public const ITEMS_PER_PAGE = 4;

    public const TITLE_MIN_LENGTH = 30;
    public const TITLE_MAX_LENGTH = 108;

    public const COMPANY_MIN_LENGTH     = 2;
    public const DESCRIPTION_MIN_LENGTH = 50;

    private OpportunityDetailsRepository $detailsRepository;
    private OpportunityCountriesRepository $countriesRepository;
    private CountriesRepository $countriesDictionary;

    /**
     * Repositories are injected so tests can swap them. Defaults keep
     * the call-site in LaunchpadCore unchanged during migration — pass
     * null to get the concrete implementation.
     */
    public function __construct(
        ?OpportunityDetailsRepository $detailsRepository = null,
        ?OpportunityCountriesRepository $countriesRepository = null,
        ?CountriesRepository $countriesDictionary = null
    ) {
        $this->detailsRepository   = $detailsRepository   ?? new OpportunityDetailsRepository();
        $this->countriesRepository = $countriesRepository ?? new OpportunityCountriesRepository();
        $this->countriesDictionary = $countriesDictionary ?? new CountriesRepository();
    }

    /**
     * Cleanup hook: remove the details row when an opportunity is deleted.
     *
     * Wired from LaunchpadCore::bootstrap on the delete_post action. No FK
     * cascade (dbDelta limitation), so this is the only thing keeping
     * wp_opportunity_details in sync with wp_posts.
     */
    public function cleanupDetails(int $postId): void
    {
        $post = get_post($postId);
        if (!$post || $post->post_type !== 'opportunity') {
            return;
        }

        $this->detailsRepository->deleteByPost($postId);
    }

    /**
     * Cleanup hook: remove junction rows when an opportunity is deleted.
     *
     * Wired from LaunchpadCore::bootstrap on the delete_post action.
     * Mirrors cleanupDetails — same FK-less cascade rationale, same
     * post-type guard so non-opportunity deletions don't pay the DB
     * cost of an empty DELETE.
     */
    public function cleanupCountries(int $postId): void
    {
        $post = get_post($postId);
        if (!$post || $post->post_type !== 'opportunity') {
            return;
        }

        $this->countriesRepository->deleteByPost($postId);
    }

    /**
     * Translatable status labels for opportunity cards.
     */
    public static function getStatusLabel(string $slug): string
    {
        $labels = [
            'draft'   => __('Draft', 'starwishx'),
            'pending' => __('Pending Review', 'starwishx'),
            'publish' => __('Published', 'starwishx'),
        ];

        return $labels[$slug] ?? ucfirst($slug);
    }

    public const DESCRIPTION_MAX_LENGTH  = 4000;
    public const REQUIREMENTS_MAX_LENGTH = 3000;
    public const DETAILS_MAX_LENGTH      = 2000;

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

        // Batch-fetch typed date rows for the whole page in one query.
        // Replaces the per-card post_meta + DateTime parsing loop. Missing
        // ids (backfill not run yet) fall through to the legacy path below.
        $postIds       = array_map(static fn($p) => (int) $p->ID, $query->posts);
        $detailsByPost = $this->detailsRepository->getBatchDates($postIds);

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
            // Description now stored in post_content (native WordPress column)
            $raw_description = $post->post_content ?: '';
            // 2. Logic: Priority to native excerpt, fallback to trimmed description
            $display_text = !empty($raw_excerpt) ? $raw_excerpt : $raw_description;
            // 3. Clean and Truncate (approx 20 words for a clean card look)
            $trimmed_excerpt = wp_trim_words($display_text, 30, '...');

            // Prefer the typed row from wp_opportunity_details. Dates come
            // back as Y-m-d (DATE column) and is_expired is DB-derived.
            // When missing (pre-backfill rows), fall back to post_meta +
            // the historical 3-format parser — this fallback exists only to
            // keep pages rendering during the migration window.
            $details = $detailsByPost[(int) $post->ID] ?? null;
            if ($details !== null) {
                $raw_date_starts = $details['date_start'] ?? '';
                $raw_date_ends   = $details['date_end']   ?? '';
                $is_expired      = (bool) $details['is_expired'];
            } else {
                $raw_date_starts = get_post_meta($post->ID, 'opportunity_date_starts', true);
                $raw_date_ends   = get_post_meta($post->ID, 'opportunity_date_ends', true);
                $is_expired      = false;
                if (!empty($raw_date_ends)) {
                    $end_dt = \DateTime::createFromFormat('Y-m-d', $raw_date_ends)
                        ?: \DateTime::createFromFormat('Ymd', $raw_date_ends)
                        ?: \DateTime::createFromFormat('d/m/Y', $raw_date_ends);
                    if ($end_dt && $end_dt < new \DateTime('today')) {
                        $is_expired = true;
                    }
                }
            }

            $ratingAvg   = (float) get_post_meta($post->ID, '_opportunity_rating_avg', true);
            $ratingCount = (int) get_post_meta($post->ID, '_opportunity_rating_count', true);

            $opportunities[] = [
                'id'            => $post->ID,
                'title'         => $post->post_title,
                'excerpt'       => $trimmed_excerpt,
                'thumbnailUrl'  => $thumbnail_url,
                'date'          => get_the_date('d.m.y', $post->ID),
                'status'        => $post->post_status,
                'statusLabel'   => self::getStatusLabel($post->post_status),
                'dateStarts'    => $this->formatDateForUI($raw_date_starts),
                'dateEnds'      => $this->formatDateForUI($raw_date_ends),
                'isExpired'     => $is_expired,
                'commentsCount' => (int) get_comments_number($post->ID),
                'ratingAvg'     => $ratingCount > 0 ? $ratingAvg : 0,
                'ratingRounded' => $ratingCount > 0 ? (int) round($ratingAvg) : 0,
                'ratingCount'   => $ratingCount,
                'commentsUrl'   => get_permalink($post->ID) . '#comments',
                'categoryName'  => $top_category,
                'editUrl'       => get_edit_post_link($post->ID),
                'viewUrl'       => get_permalink($post->ID),
            ];
        }

        return $opportunities;
    }

    public function countUserOpportunities(int $userId, array $filters = []): int
    {
        // We only need the count; fetch a single ID row so SQL_CALC_FOUND_ROWS
        // populates found_posts without inflating every post object.
        $args = [
            'post_type'      => 'opportunity',
            'author'         => $userId,
            'posts_per_page' => 1,
            'fields'         => 'ids',
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
            'categories' => $this->getHierarchicalCategoryTerms('category-oportunities'),
            'countries'  => $this->countriesDictionary->getAll(),
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
     * Get terms hierarchically (Parent -> Children).
     */
    private function getHierarchicalCategoryTerms(string $taxonomy): array
    {
        // 1. Get Parents (Term ID 0)
        $parents = get_terms([
            'taxonomy'   => $taxonomy,
            'hide_empty' => false,
            'parent'     => 0,
            'orderby'    => 'name',
        ]);

        if (is_wp_error($parents)) {
            return [];
        }

        $result = [];
        foreach ($parents as $parent) {
            // 2. Get Children for each parent
            $children = get_terms([
                'taxonomy'   => $taxonomy,
                'hide_empty' => false,
                'parent'     => $parent->term_id,
                'orderby'    => 'name',
            ]);

            $childData = [];
            if (!is_wp_error($children)) {
                $childData = array_map(fn($c) => [
                    'id'   => $c->term_id,
                    'name' => html_entity_decode($c->name)
                ], $children);
            }

            $result[] = [
                'id'       => $parent->term_id,
                'name'     => html_entity_decode($parent->name),
                'children' => $childData,
            ];
        }

        return $result;
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

        // Fetch Locations
        global $wpdb;

        // Join pivot table (wp_opportunity_locations) with dictionary (wp_katottg)
        // "SELECT k.code, k.name, k.level, k.category
        //  FROM wp_katottg k
        //  INNER JOIN wp_opportunity_locations ol ON k.code = ol.katottg_code
        //  WHERE ol.post_id = %d",
        $locations = $wpdb->get_results($wpdb->prepare(
            "SELECT code, name_category_oblast as name, level, category
                FROM wp_v_opportunity_search
                WHERE post_id = %d",
            $postId
        ), ARRAY_A);

        // Dates: prefer the typed details row. Already Y-m-d by column
        // type — matches the HTML5 <input type="date"> wire format, so no
        // conversion is needed. Fall back to ACF + formatDateForInput for
        // pre-backfill rows.
        $details = $this->detailsRepository->getDates($postId);
        if ($details !== null) {
            $dateStarts = $details['date_start'] ?? '';
            $dateEnds   = $details['date_end']   ?? '';
        } else {
            $dateStarts = $this->formatDateForInput(get_field('opportunity_date_starts', $postId));
            $dateEnds   = $this->formatDateForInput(get_field('opportunity_date_ends', $postId));
        }

        // Map ACF fields to our simplified FormData structure
        // Note: applicant fields are no longer returned - they're auto-filled from profile on save
        return [
            'id' => $post->ID,
            'title' => $post->post_title,
            'status' => $post->post_status,

            // Group 1: Info
            'company'         => get_field('opportunity_company', $postId) ?: '',
            'date_starts'     => $dateStarts,
            'date_ends'       => $dateEnds,
            'category'        => $getIntArray('opportunity_category'), // Now an array

            'country'         => $this->countriesRepository->getCountryId($postId) ?? '',
            'locations'       => $locations,
            // We keep 'city' just for now in case to not brake things
            'city'            => get_field('city', $postId) ?: '',
            'sourcelink'      => get_field('opportunity_sourcelink', $postId) ?: '',
            'application_form' => get_field('opportunity_application_form', $postId) ?: '',

            // FIX: Ensure these are Integers for JS .includes() check
            'subcategory'     => $getIntArray('opportunity_subcategory'),
            'seekers'         => $getIntArray('opportunity_seekers'),

            // Group 3: Description
            // Description now stored in post_content (native WordPress column)
            'description'     => $post->post_content ?: '',
            'requirements'    => get_field('opportunity_requirements', $postId) ?: '',
            'details'         => get_field('opportunity_details', $postId) ?: '',

            // Document handling
            'document'        => $this->getDocumentData($postId),
        ];
    }

    /**
     * Get document data for an opportunity.
     */
    private function getDocumentData(int $postId): ?array
    {
        $doc_field = get_field('opportunity_document', $postId);
        $doc_id = 0;
        $doc_data = null;

        if (is_array($doc_field) && isset($doc_field['ID'])) {
            $doc_id = (int) $doc_field['ID'];
        } elseif (is_numeric($doc_field)) {
            $doc_id = (int) $doc_field;
        }

        if ($doc_id) {
            $url = wp_get_attachment_url($doc_id);
            $path = get_attached_file($doc_id);
            if ($url && file_exists($path)) {
                $doc_data = [
                    'id'        => $doc_id,
                    'name'      => basename($path),
                    'url'       => $url,
                    'size'      => size_format(filesize($path), 2),
                    'isPending' => false
                ];
            }
        }

        return $doc_data;
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

        // Validate URL fields — validate() returns the normalized URL or WP_Error
        $fieldErrors = [];
        foreach (['sourcelink', 'application_form'] as $urlField) {
            if (!empty($data[$urlField])) {
                $urlResult = UrlPolicy::validate($data[$urlField]);
                if (is_wp_error($urlResult)) {
                    $fieldErrors[$urlField] = $urlResult->get_error_message();
                } else {
                    $data[$urlField] = $urlResult;
                }
            }
        }
        if (!empty($fieldErrors)) {
            return new WP_Error(
                'invalid_data',
                __('Please correct the highlighted fields.', 'starwishx'),
                ['status' => 422, 'field_errors' => $fieldErrors]
            );
        }

        $wpdb->query('START TRANSACTION');

        try {
            // 1. Prepare Core Post Data
            $postData = [
                'post_type'    => 'opportunity',
                'post_title'   => sanitize_text_field($data['title']),
                'post_content' => sanitize_textarea_field($data['description'] ?? ''),
                // Allows safe HTML
                // 'post_content' => wp_kses_post($data['description']), 

                'post_author'  => get_current_user_id(),
            ];

            // Determine Status
            // Logic: Use provided status, or default to 'draft' for new, or preserve for updates.
            // Defense-in-depth: only editors/admins may set 'publish' directly.
            if (!empty($data['status'])) {
                if ($data['status'] === 'publish' && !current_user_can('publish_posts')) {
                    $data['status'] = 'pending';
                }
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

            // Group 1: Applicant - Auto-fill from user profile
            $user = get_userdata($current_user_id);
            if ($user) {
                $acfId = 'user_' . $current_user_id;
                $firstName = $user->first_name;
                $lastName = $user->last_name;
                $fullName = trim($firstName . ' ' . $lastName);

                update_field('opportunity_applicant_name', $fullName, $id);
                update_field('opportunity_applicant_mail', $user->user_email, $id);

                // Phone requires special handling (ACF phone field)
                $phoneRaw = get_field('phone', $acfId);
                $phoneString = '';
                if (is_array($phoneRaw)) {
                    $phoneString = $phoneRaw['international'] ?? $phoneRaw['e164'] ?? '';
                } elseif (is_object($phoneRaw) && method_exists($phoneRaw, 'international')) {
                    $phoneString = $phoneRaw->international();
                } else {
                    $phoneString = (string) $phoneRaw;
                }
                update_field('opportunity_applicant_phone', $phoneString, $id);
            }

            // Group 2
            update_field('opportunity_company', $data['company'], $id);
            update_field('opportunity_date_starts', $data['date_starts'], $id);
            update_field('opportunity_date_ends', $data['date_ends'], $id);

            // Dual-write: typed storage in wp_opportunity_details.
            // post_meta above stays authoritative until the read-switch
            // migration lands (PR#5) and the backfill (PR#4) has run. Failure
            // here rolls back the whole save — drift is what this change
            // exists to prevent.
            $detailsWritten = $this->detailsRepository->upsertDates(
                (int) $id,
                $data['date_starts'] ?? null,
                $data['date_ends'] ?? null
            );
            if (!$detailsWritten) {
                throw new \RuntimeException('Failed to persist opportunity details row.');
            }
            update_field('city', $data['city'], $id);
            update_field('opportunity_sourcelink', $data['sourcelink'], $id);
            update_field('opportunity_application_form', $data['application_form'], $id);

            // Taxonomies: Save to ACF field AND actual WP Taxonomy
            // wp_set_object_terms returns WP_Error or Term IDs
            // Category (Multi/Hierarchical)
            $categories = array_map('intval', $data['category'] ?? []);
            update_field('opportunity_category', $categories, $id);
            wp_set_object_terms($id, $categories, 'category-oportunities');

            // Country — typed storage in wp_opportunity_countries replaces the
            // `country` taxonomy and ACF field. setCountry() is delete-then-
            // insert, atomic inside this transaction. Failure rolls back the
            // entire save (caught below).
            $countryId = (int) ($data['country'] ?? 0);
            $countryWritten = $this->countriesRepository->setCountry(
                (int) $id,
                $countryId > 0 ? $countryId : null
            );
            if (!$countryWritten) {
                throw new \RuntimeException('Failed to persist opportunity country.');
            }

            // Seekers (Multi)
            $seekers = array_map('intval', $data['seekers'] ?? []);
            update_field('opportunity_seekers', $seekers, $id);
            wp_set_object_terms($id, $seekers, 'category-seekers');

            // Group 3 - Description is now saved to post_content via wp_insert_post/wp_update_post
            update_field('opportunity_requirements', $data['requirements'], $id);
            update_field('opportunity_details', $data['details'], $id);

            // Handle Document Upload/Removal
            if (isset($data['document_id'])) {
                $new_doc_id = (int)$data['document_id'];
                $old_doc_field = get_field('opportunity_document', $id);
                $old_doc_id = is_array($old_doc_field) ? (int)$old_doc_field['ID'] : (int)$old_doc_field;

                // A. User Removed Document
                if ($new_doc_id === 0 && $old_doc_id > 0) {
                    update_field('opportunity_document', '', $id);
                    wp_delete_attachment($old_doc_id, true);
                }
                // B. User Added/Changed Document
                elseif ($new_doc_id > 0 && $new_doc_id !== $old_doc_id) {
                    // SECURITY: Verify Ownership
                    $is_owned_by_user = (int)get_post_field('post_author', $new_doc_id) === get_current_user_id();
                    $is_temp_file = get_post_meta($new_doc_id, '_launchpad_temp_upload', true);

                    if ($is_owned_by_user && $is_temp_file) {
                        // 1. Link logic
                        update_field('opportunity_document', $new_doc_id, $id);
                        wp_update_post([
                            'ID'          => $new_doc_id,
                            'post_parent' => $id
                        ]);

                        // 2. Remove Flags (Claim the file)
                        delete_post_meta($new_doc_id, '_launchpad_temp_upload');

                        // 3. Garbage collect old file
                        if ($old_doc_id > 0) {
                            wp_delete_attachment($old_doc_id, true);
                        }
                    }
                }
            }

            // Save Locations (Pivot Table)
            if (isset($data['locations']) && is_array($data['locations'])) {
                // A. Clear existing locations for this post
                $wpdb->delete('wp_opportunity_locations', ['post_id' => $id]);

                // B. Extract just the codes from the frontend objects
                $codes = array_column($data['locations'], 'code');

                if (!empty($codes)) {
                    // C. SECURITY: Verify codes exist in database to prevent bad data
                    // Create placeholders like: "%s, %s, %s"
                    $placeholders = implode(',', array_fill(0, count($codes), '%s'));

                    // Select only codes that actually exist in dictionary
                    $validCodes = $wpdb->get_col($wpdb->prepare(
                        "SELECT code FROM wp_katottg WHERE code IN ($placeholders)",
                        ...$codes
                    ));

                    // D. Bulk Insert
                    if (!empty($validCodes)) {
                        $values = [];
                        $queryPlaceholders = [];
                        foreach ($validCodes as $code) {
                            array_push($values, $id, $code);
                            $queryPlaceholders[] = "(%d, %s)";
                        }

                        $sql = "INSERT INTO wp_opportunity_locations (post_id, katottg_code) VALUES " . implode(', ', $queryPlaceholders);
                        $wpdb->query($wpdb->prepare($sql, $values));
                    }
                    // error_log($sql);
                }
            }

            // Validate content completeness before allowing pending status
            if (($data['status'] ?? '') === 'pending') {
                $valid = $this->validateForSubmission($id);
                if (is_wp_error($valid)) {
                    $wpdb->query('ROLLBACK');
                    return $valid;
                }
            }

            $wpdb->query('COMMIT');

            // Notify editors when opportunity is submitted for review
            if (($data['status'] ?? '') === 'pending') {
                do_action('sw_opportunity_pending', $id, [
                    'user_id' => get_current_user_id(),
                ]);
            }

            return $id;
        } catch (\Throwable $e) {
            $wpdb->query('ROLLBACK');
            error_log('OpportunitiesService Save Failure: ' . $e->getMessage());

            // Generic message — internal exception text can leak schema/ACF details.
            return new WP_Error(
                'db_transaction_failed',
                __('Could not save opportunity. Please try again.', 'starwishx'),
                ['status' => 500]
            );
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

        // 3. Content completeness gate — prevent submitting incomplete opportunities
        if ($newStatus === 'pending') {
            $valid = $this->validateForSubmission($postId);
            if (is_wp_error($valid)) {
                return $valid;
            }
        }

        // 4. Atomic Update
        $result = wp_update_post([
            'ID'          => $postId,
            'post_status' => $newStatus,
        ], true);

        // Notify editors when opportunity is submitted for review
        if (!is_wp_error($result) && $newStatus === 'pending') {
            do_action('sw_opportunity_pending', $postId, [
                'user_id' => $userId,
            ]);
        }

        return $result;
    }

    /**
     * Validate that an opportunity has all required fields before submission.
     * Reads from DB to validate actual saved state, not just user input.
     */
    private function validateForSubmission(int $postId): true|WP_Error
    {
        $post = get_post($postId);
        $errors = [];

        // Core fields
        $titleLen = mb_strlen(trim($post->post_title));
        if ($titleLen === 0) {
            $errors['title'] = __('Title is required.', 'starwishx');
        } elseif ($titleLen < self::TITLE_MIN_LENGTH) {
            $errors['title'] = sprintf(
                __('Title must be at least %d characters.', 'starwishx'),
                self::TITLE_MIN_LENGTH
            );
        } elseif ($titleLen > self::TITLE_MAX_LENGTH) {
            $errors['title'] = sprintf(
                __('Title must not exceed %d characters.', 'starwishx'),
                self::TITLE_MAX_LENGTH
            );
        }
        $descLen = mb_strlen(trim($post->post_content));
        if ($descLen === 0) {
            $errors['description'] = __('Description is required.', 'starwishx');
        } elseif ($descLen < self::DESCRIPTION_MIN_LENGTH) {
            $errors['description'] = sprintf(
                __('Description must be at least %d characters.', 'starwishx'),
                self::DESCRIPTION_MIN_LENGTH
            );
        }

        // ACF fields
        $companyLen = mb_strlen(trim((string) get_field('opportunity_company', $postId)));
        if ($companyLen === 0) {
            $errors['company'] = __('Company is required.', 'starwishx');
        } elseif ($companyLen < self::COMPANY_MIN_LENGTH) {
            $errors['company'] = sprintf(
                __('Company must be at least %d characters.', 'starwishx'),
                self::COMPANY_MIN_LENGTH
            );
        }
        if (!get_field('opportunity_sourcelink', $postId)) {
            $errors['sourcelink'] = __('Source link is required.', 'starwishx');
        }

        // Taxonomy: at least one category-oportunities term
        $terms = wp_get_object_terms($postId, 'category-oportunities', ['fields' => 'ids']);
        if (empty($terms) || is_wp_error($terms)) {
            $errors['category'] = __('At least one category is required.', 'starwishx');
        }

        // Taxonomy: at least one seeker
        $seekers = wp_get_object_terms($postId, 'category-seekers', ['fields' => 'ids']);
        if (empty($seekers) || is_wp_error($seekers)) {
            $errors['seekers'] = __('At least one seeker type is required.', 'starwishx');
        }

        // Dates — commented out, uncomment for testing
        // if (!get_field('opportunity_date_starts', $postId)) {
        //     $errors['date_starts'] = __('Start date is required.', 'starwishx');
        // }
        // if (!get_field('opportunity_date_ends', $postId)) {
        //     $errors['date_ends'] = __('End date is required.', 'starwishx');
        // }

        if (!empty($errors)) {
            return new WP_Error(
                'incomplete_opportunity',
                __('Please fill in all required fields before submitting for review.', 'starwishx'),
                ['status' => 422, 'field_errors' => $errors]
            );
        }

        return true;
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
     * Convert a date string to a UI-friendly format (d.m.y).
     *
     * After the backfill (PR#4) and dual-write (PR#3), input should be
     * canonical Y-m-d — the Ymd / d/m/Y branches remain as a defensive
     * fallback for any row that somehow escaped normalization (e.g., a
     * restored-from-backup opportunity that never hit the save path).
     */
    private function formatDateForUI(?string $dateStr, string $format = 'd.m.y'): string
    {
        if (empty($dateStr)) {
            return '';
        }

        $date = \DateTime::createFromFormat('Y-m-d', $dateStr)
            ?: \DateTime::createFromFormat('Ymd', $dateStr)
            ?: \DateTime::createFromFormat('d/m/Y', $dateStr);

        return $date ? $date->format($format) : $dateStr;
    }

    /** 
     * Autocomplete Location Search 
     * Uses the View for pre-formatted names.
     * 
     * @param string $search User input
     * @param array $levels Optional array of levels (1=Oblast, 2=Raion, 3=Hromada, 4=Settlement)
     */
    public function searchKatottg(string $search, array $levels = []): array
    {
        global $wpdb;

        // Optimization: Search start of string first for index utilization, then fuzzy?
        // For UX "Kyiv", "Vinnytsia" usually match start. Let's use standard fuzzy for coverage.
        // $like = '%' . $wpdb->esc_like($search) . '%';
        $like = $wpdb->esc_like($search) . '%';

        // Base Query against the VIEW
        $sql = "SELECT code, name_category_oblast as name, level, category_short as category 
                FROM wp_v_katottg_search
                WHERE name LIKE %s";

        $params = [$like];

        // Apply Level Filter if provided
        if (!empty($levels)) {
            // Securely build placeholders: %d, %d, %d
            $placeholders = implode(',', array_fill(0, count($levels), '%d'));

            $sql .= " AND level IN ($placeholders)";

            // Append integers to params
            foreach ($levels as $lvl) {
                $params[] = (int) $lvl;
            }
        }

        // Limit results for performance
        $sql .= " ORDER BY level ASC, name ASC LIMIT 10";

        return $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
    }
}
