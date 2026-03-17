<?php

/**
 * Interactive Comments Template
 * 
 * File: templates/parts/comments-interactive.php
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

$post_id = get_the_ID();

/** @var \Comments\Services\CommentsService $service */
$service = \comments()->service();

$limit = \Comments\Services\CommentsService::ITEMS_PER_PAGE;
$initial_comments  = $service->getPostComments($post_id, $limit, 0);
$total_count       = $service->countPostComments($post_id);
$aggregates        = $service->getAggregates($post_id);
// $has_comments      = !empty($initial_comments);

// Application state — post-specific data. Infrastructure config (config, nonce, restUrl)
// is already hydrated by CommentsCore::enqueueAssets() — wp_interactivity_state() merges.
$post_status  = get_post_status($post_id);
$has_ratings  = $aggregates['count'] > 0;
$rounded_avg  = (int) round((float) $aggregates['avg']);

wp_interactivity_state('comments', [
    'canComment'     => $post_status === 'publish',
    'list'           => $initial_comments,
    'aggregates'     => $aggregates,
    'page'           => 1,
    'hasMore'        => $total_count > $limit,
    'hasComments'    => $total_count > 0,
    'newContent'     => '',
    'newRating'      => 5,
    'error'          => null,
    'successMessage' => null,
    'isSubmitting'   => false,
    'isLoading'      => false,
    'showForm'       => false,
    // SSR mirrors of JS getters — enables server-side directive processing.
    // JS getters with same names override these for client-side reactivity.
    'hasRatings'     => $has_ratings,
    'roundedAvg'     => $rounded_avg,
]);
?>

<footer id="comments" class="comments-area"
    aria-labelledby="reviews-heading"
    data-wp-interactive="comments"
    data-wp-context='{ "postId": <?php echo $post_id; ?> }'>

    <!-- HEADER SECTION -->
    <div class="comments-header">
        <div class="comments-header__info">
            <h5 id="reviews-heading" class="h5"><?php esc_html_e('Reviews', 'starwishx'); ?></h5>
            <span class="rating-badge"
                <?php if (!$has_ratings) echo 'hidden'; ?>
                data-wp-bind--hidden="!state.hasRatings"
                role="group" data-wp-bind--aria-label="state.ratingBadgeLabel">
                <span class="stars-display" data-wp-bind--data-rating="state.roundedAvg"
                    data-rating="<?php echo round((float) $aggregates['avg']); ?>"
                    aria-hidden="true">
                    <?php for ($s = 1; $s <= 5; $s++): ?>
                        <svg class="icon-star star-<?php echo $s; ?>" width="16" height="16" aria-hidden="true">
                            <use href="<?php echo get_template_directory_uri(); ?>/assets/img/sprites.svg#icon-star"></use>
                        </svg>
                    <?php endfor; ?>
                </span>
                <span data-wp-text="state.aggregates.avg">
                    <?php echo esc_html($aggregates['avg']); ?>
                </span>
                <span class="count">
                    (<span data-wp-text="state.aggregates.count"><?php echo esc_html($aggregates['count']); ?></span>)
                </span>
            </span>
        </div>

        <?php if (is_user_logged_in()) : ?>
            <button class="btn comments-header__button"
                data-wp-on--click="actions.toggleForm"
                data-wp-bind--hidden="!state.isAddReviewVisible">
                <?php sw_svg_e('icon-write') ?>
                <?php esc_html_e('Add review', 'starwishx'); ?>
            </button>
        <?php else: ?>
            <a href="<?php echo wp_login_url(get_permalink()); ?>" class="btn__small">
                <?php esc_html_e('Enter to comment', 'starwishx'); ?>
            </a>
        <?php endif; ?>
    </div>

    <!-- <div class="alert error" data-wp-bind--hidden="!state.error" data-wp-text="state.error"></div> -->

    <!-- FORM Add New comment-->
    <?php if (is_user_logged_in()) : ?>
        <div class="comments-form-wrapper" data-wp-bind--hidden="!state.showForm">
            <form class="comment-form" data-wp-on--submit="actions.submit">
                <!-- FIELDSET: Locks everything when submitting -->
                <fieldset data-wp-bind--disabled="state.isSubmitting">
                    <!-- STATUS AREA (Error / Info) -->
                    <div class="form-status-area">
                        <div class="alert-inline"
                            data-wp-bind--hidden="!state.error"
                            data-wp-text="state.error"></div>
                    </div>
                    <!-- INPUTS  -->
                    <div class="form-rating-input">
                        <span class="label"><?php esc_html_e('Your Rating:', 'starwishx'); ?></span>
                        <div class="stars-input" data-wp-bind--data-value="state.newRating">
                            <?php foreach (range(1, 5) as $i): ?>
                                <button type="button"
                                    class="star-btn"
                                    data-value="<?php echo $i; ?>"
                                    data-wp-on--click="actions.setRating">
                                    <?= sw_svg('icon-star', 16); ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="form-field">
                        <textarea
                            class="form-control"
                            rows="4"
                            placeholder="<?php esc_attr_e('Write your review here...', 'starwishx'); ?>"
                            data-wp-bind--value="state.newContent"
                            data-wp-on--input="actions.updateContent"></textarea>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn-secondary__small" data-wp-on--click="actions.toggleForm">
                            <?php esc_html_e('Cancel', 'starwishx'); ?>
                        </button>
                        <button type="submit" class="btn__small" data-wp-bind--disabled="state.isSubmitting">
                            <span data-wp-bind--hidden="state.isSubmitting"><?php esc_html_e('Send', 'starwishx'); ?></span>
                            <span data-wp-bind--hidden="!state.isSubmitting"><?php esc_html_e('Sending...', 'starwishx'); ?></span>
                        </button>
                    </div>
                </fieldset>
                <!-- LOCALIZED STATUS AREA -->
                <div class="form-status-area">
                    <!-- Sending Spinner/Text -->
                    <div class="sending-message" data-wp-bind--hidden="!state.isSubmitting">
                        <span class="spinner-icon">⏳</span> <?php esc_html_e('Publishing...', 'starwishx'); ?>
                    </div>
                    <!-- Success -->
                    <div class="status-message" data-wp-bind--hidden="!state.successMessage" data-wp-text="state.successMessage"></div>
                    <!-- Error -->
                    <div class="error-message" data-wp-bind--hidden="!state.error" data-wp-text="state.error"></div>
                </div>
            </form>
        </div>
    <?php else: ?>
        <!-- <p>Please <a href="< ?php echo wp_login_url(get_permalink()); ? >">login</a> to comment.</p> -->
    <?php endif; ?>

    <!-- COMMENTS LIST -->
    <div class="comments-list">
        <div <?php if ($total_count > 0) echo 'hidden'; ?>
            data-wp-bind--hidden="state.hasComments">
            <p><?php esc_html_e('No comments yet.', 'starwishx'); ?></p>
        </div>

        <template data-wp-each--item="state.list">
            <div class="comment-item" data-wp-context='{ "isEditing": false, "isReplying": false }'>

                <!-- VIEW mode. Hidden if this item is being edited -->
                <div class="comment-view" data-wp-bind--hidden="context.isEditing">
                    <div class="comment-header">
                        <div class="comment-author">
                            <img data-wp-bind--src="context.item.avatar" data-wp-bind--alt="context.item.author" class="avatar" alt="">
                            <strong class="author-name" data-wp-text="context.item.author"></strong>
                            <span class="badge-author" data-wp-bind--hidden="!context.item.isPostAuthor">
                                <?php esc_html_e('Author', 'starwishx'); ?>
                            </span>
                        </div>
                        <time class="comment-date" data-wp-text="context.item.date"
                            data-wp-bind--datetime="context.item.dateIso"></time>
                    </div>

                    <div class="comment-body">
                        <p data-wp-text="context.item.content"></p>
                    </div>

                    <div class="comment-footer">
                        <div class="comment-rating" data-wp-bind--hidden="!context.item.rating">
                            <!-- CSS Driven Display stars -->
                            <div class="stars-display" data-wp-bind--data-rating="context.item.rating"
                                role="img" data-wp-bind--aria-label="context.item.ratingLabel">
                                <?php for ($s = 1; $s <= 5; $s++): ?>
                                    <svg class="icon-star star-<?php echo $s; ?>" aria-hidden="true">
                                        <use href="<?php echo get_template_directory_uri(); ?>/assets/img/sprites.svg#icon-star"></use>
                                    </svg>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <div class="comment-actions">
                            <!-- data-wp-bind--hidden="!context.item.isMine" -->
                            <button class="btn-link-edit"
                                data-wp-bind--hidden="!context.item.isEditable"
                                data-wp-on--click="actions.startEdit">
                                <?php esc_html_e('Edit', 'starwishx'); ?>
                            </button>
                            <!-- Reply for PARENT items -->
                            <button class="btn-link-reply"
                                data-wp-bind--hidden="!context.item.canReply"
                                data-wp-on--click="actions.startReply">
                                <?php esc_html_e('Reply', 'starwishx'); ?>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- EDIT mode (In-Place) Visible only if this item is being edited -->
                <div class="comment-edit-mode" data-wp-bind--hidden="!context.isEditing">
                    <form class="comment-form comment-form--inline" data-wp-on--submit="actions.saveEdit">
                        <!-- FIELDSET: Locks on isUpdating -->
                        <fieldset data-wp-bind--disabled="state.isUpdating">
                            <div class="form-rating-input">
                                <!-- CSS Driven Stars for Edit Mode -->
                                <div class="stars-input" data-wp-bind--data-value="state.editRating">
                                    <?php foreach (range(1, 5) as $i): ?>
                                        <button type="button"
                                            class="star-btn"
                                            data-value="<?php echo $i; ?>"
                                            data-wp-on--click="actions.setEditRating">
                                            <?= sw_svg('icon-star', 16); ?>
                                        </button>
                                    <?php endforeach; ?>
                                </div>

                            </div>

                            <div class="form-field">
                                <textarea
                                    class="form-control"
                                    rows="3"
                                    data-wp-bind--value="state.editDraft"
                                    data-wp-on--input="actions.updateEditDraft"></textarea>
                            </div>

                            <div class="form-actions">
                                <button type="button" class="btn-secondary__small"
                                    data-wp-on--click="actions.cancelEdit">
                                    <?php esc_html_e('Cancel', 'starwishx'); ?>
                                </button>
                                <button type="submit" class="btn__small"
                                    data-wp-bind--disabled="state.isUpdating">
                                    <span data-wp-bind--hidden="state.isUpdating"><?php esc_html_e('Save', 'starwishx'); ?></span>
                                    <span data-wp-bind--hidden="!state.isUpdating">...</span>
                                </button>
                            </div>
                        </fieldset>
                        <!-- LOCALIZED STATUS -->
                        <div class="form-status-area">
                            <div class="sending-message" data-wp-bind--hidden="!state.isUpdating">
                                <?php esc_html_e('Updating...', 'starwishx'); ?>
                            </div>
                            <div class="status-message" data-wp-bind--hidden="!state.successMessage" data-wp-text="state.successMessage"></div>
                            <div class="error-message" data-wp-bind--hidden="!state.error" data-wp-text="state.error"></div>
                        </div>
                    </form>
                </div>

                <!-- REPLIES Thread -->
                <div class="comment-replies">
                    <div class="replies-list" data-wp-bind--hidden="!context.item.hasReplies">
                        <template data-wp-each--reply="context.item.replies">
                            <div class="reply-item" data-wp-context='{ "isEditing": false }'>
                                <!-- Reply VIEW mode -->
                                <div class="reply-view" data-wp-bind--hidden="context.isEditing">
                                    <div class="reply-header">
                                        <div class="comment-author">
                                            <img data-wp-bind--src="context.reply.avatar" data-wp-bind--alt="context.reply.author" class="avatar-small" alt="">
                                            <strong class="author-name" data-wp-text="context.reply.author"></strong>
                                            <span class="badge-author" data-wp-bind--hidden="!context.reply.isPostAuthor">
                                                <?php esc_html_e('Author', 'starwishx'); ?>
                                            </span>
                                        </div>
                                        <time class="comment-date" data-wp-text="context.reply.date"
                                            data-wp-bind--datetime="context.reply.dateIso"></time>
                                    </div>

                                    <div class="reply-body">
                                        <p data-wp-text="context.reply.content"></p>
                                    </div>

                                    <div class="reply-footer">
                                        <div class="comment-actions">
                                            <!-- EDIT BUTTON FOR REPLY -->
                                            <!-- data-wp-bind--hidden="!context.reply.isMine" -->
                                            <button class="btn-link-edit"
                                                data-wp-bind--hidden="!context.reply.isEditable"
                                                data-wp-on--click="actions.startReplyEdit">
                                                <?php esc_html_e('Edit', 'starwishx'); ?>
                                            </button>
                                            <button class="btn-link-reply"
                                                data-wp-bind--hidden="!context.item.canReply"
                                                data-wp-on--click="actions.startReply">
                                                <?php esc_html_e('Reply', 'starwishx'); ?>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Reply EDIT mode (Inline) -->
                                <div class="reply-edit-mode" data-wp-bind--hidden="!context.isEditing">
                                    <form class="comment-form comment-form--inline" data-wp-on--submit="actions.saveReplyEdit">
                                        <!-- FIELDSET: Locks on isUpdating -->
                                        <fieldset data-wp-bind--disabled="state.isUpdating">
                                            <div class="form-field">
                                                <textarea
                                                    class="form-control"
                                                    rows="2"
                                                    data-wp-bind--value="state.editDraft"
                                                    data-wp-on--input="actions.updateEditDraft"></textarea>
                                            </div>

                                            <div class="form-actions">
                                                <button type="button" class="btn-secondary__small"
                                                    data-wp-on--click="actions.cancelReplyEdit">
                                                    <?php esc_html_e('Cancel', 'starwishx'); ?>
                                                </button>
                                                <button type="submit" class="btn__small"
                                                    data-wp-bind--disabled="state.isUpdating">
                                                    <span data-wp-bind--hidden="state.isUpdating"><?php esc_html_e('Save', 'starwishx'); ?></span>
                                                    <span data-wp-bind--hidden="!state.isUpdating">...</span>
                                                </button>
                                            </div>
                                        </fieldset>
                                        <!-- Shared Status Area (reuses global flags, effectively scoped by viewing one form at a time) -->
                                        <div class="form-status-area">
                                            <div class="sending-message" data-wp-bind--hidden="!state.isUpdating"><?php esc_html_e('Updating...', 'starwishx'); ?></div>
                                            <div class="status-message" data-wp-bind--hidden="!state.successMessage" data-wp-text="state.successMessage"></div>
                                            <div class="error-message" data-wp-bind--hidden="!state.error" data-wp-text="state.error"></div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </template>
                    </div>

                    <!-- Reply Form -->
                    <div class="reply-form-wrapper" data-wp-bind--hidden="!context.isReplying">
                        <!-- Local Error (Reusing state.error works because context implies focus) -->
                        <div class="form-status-area" data-wp-bind--hidden="!state.error">
                            <div class="alert-inline" data-wp-text="state.error"></div>
                        </div>
                        <form class="comment-form comment-form--reply" data-wp-on--submit="actions.submitReply">
                            <!-- FIELDSET: Locks on isReplying -->
                            <fieldset data-wp-bind--disabled="state.isReplying">
                                <div class="form-field">
                                    <textarea class="form-control" rows="2"
                                        placeholder="<?php esc_attr_e('Write a reply...', 'starwishx'); ?>"
                                        data-wp-bind--value="state.replyDraft"
                                        data-wp-on--input="actions.updateReplyDraft"></textarea>
                                </div>
                                <div class="form-actions">
                                    <button type="button" class="btn-secondary__small" data-wp-on--click="actions.cancelReply">
                                        <?php esc_html_e('Cancel', 'starwishx'); ?>
                                    </button>
                                    <button type="submit" class="btn__small" data-wp-bind--disabled="state.isReplying">
                                        <?php esc_html_e('Reply', 'starwishx'); ?>
                                    </button>
                                </div>
                            </fieldset>
                            <!-- LOCALIZED STATUS -->
                            <div class="form-status-area">
                                <div class="sending-message" data-wp-bind--hidden="!state.isReplying">
                                    <?php esc_html_e('Sending...', 'starwishx'); ?>
                                </div>
                                <div class="status-message" data-wp-bind--hidden="!state.successMessage" data-wp-text="state.successMessage"></div>
                                <div class="error-message" data-wp-bind--hidden="!state.error" data-wp-text="state.error"></div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </template>
    </div>
    <!-- PAGINATION BUTTON -->
    <div class="comments-pagination"
        data-wp-bind--hidden="!state.hasMore">
        <button class="btn-tertiary comments-pagination__button"
            data-wp-on--click="actions.loadMore"
            data-wp-bind--disabled="state.isLoading">
            <span data-wp-bind--hidden="state.isLoading"><?php esc_html_e('Show more', 'starwishx'); ?></span>
            <span data-wp-bind--hidden="!state.isLoading"><?php esc_html_e('Loading...', 'starwishx'); ?></span>
        </button>
    </div>
</footer>