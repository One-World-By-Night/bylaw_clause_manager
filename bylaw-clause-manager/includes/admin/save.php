<?php

/** File: includes/admin/save.php
 * Text Domain: bylaw-clause-manager
 * @version 2.3.0
 * @author greghacke
 * Function: Save post meta for the Bylaw Clause CPT
 */

defined('ABSPATH') || exit;

/** Save post meta from the regular edit screen.
 * This function handles saving custom fields for the Bylaw Clause CPT when using the regular edit screen.
 * It checks for the nonce to ensure security, verifies user permissions, and updates the post meta accordingly.
 * It updates the Bylaw Group, Tags, Parent Clause, and other custom fields based on the submitted data.
 * If the post is being autosaved or is a revision, it exits early to prevent unnecessary updates.
 * The function uses `sanitize_key`, `sanitize_text_field`, and `esc_url_raw` to ensure the data is clean and safe before saving.
 * It also checks if the post type is 'bylaw_clause' to ensure it only processes relevant posts.
 */
add_action('save_post_bylaw_clause', function ($post_id) {
    if (
        defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ||
        !isset($_POST['bcm_clause_meta_nonce']) ||
        !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bcm_clause_meta_nonce'])), 'bcm_clause_meta_save') ||
        !current_user_can('edit_post', $post_id)
    ) {
        return;
    }

    $tags         = isset($_POST['bcm_tags']) ? sanitize_text_field(wp_unslash($_POST['bcm_tags'])) : '';
    $group        = isset($_POST['bcm_bylaw_group']) ? sanitize_key(wp_unslash($_POST['bcm_bylaw_group'])) : '';
    $parent_id    = isset($_POST['bcm_parent_clause']) ? absint(wp_unslash($_POST['bcm_parent_clause'])) : 0;
    $section_id   = isset($_POST['bcm_section_id']) ? sanitize_text_field(wp_unslash($_POST['bcm_section_id'])) : '';
    $vote_date    = isset($_POST['bcm_vote_date']) ? sanitize_text_field(wp_unslash($_POST['bcm_vote_date'])) : '';
    $vote_ref     = isset($_POST['bcm_vote_reference']) ? sanitize_text_field(wp_unslash($_POST['bcm_vote_reference'])) : '';
    $vote_url     = isset($_POST['bcm_vote_url']) ? esc_url_raw(wp_unslash($_POST['bcm_vote_url'])) : '';

    update_post_meta($post_id, 'tags', $tags);
    update_post_meta($post_id, 'bylaw_group', $group);
    update_post_meta($post_id, 'section_id', $section_id);
    update_post_meta($post_id, 'vote_date', $vote_date);
    update_post_meta($post_id, 'vote_reference', $vote_ref);
    update_post_meta($post_id, 'vote_url', $vote_url);
    update_post_meta($post_id, 'parent_clause', ($parent_id !== $post_id ? $parent_id : ''));
});

/** Save post meta from the Quick Edit screen.
 * This function handles saving custom fields for the Bylaw Clause CPT when using Quick Edit.
 * It checks for the nonce to ensure security, verifies user permissions, and updates the post meta accordingly.
 * It updates the Bylaw Group, Tags, Parent Clause, and other custom fields based on the submitted data.
 * If the post is being autosaved or is a revision, it exits early to prevent unnecessary updates.
 * The function uses `sanitize_key`, `sanitize_text_field`, and `esc_url_raw` to ensure the data is clean and safe before saving.
 * It also checks if the post type is 'bylaw_clause' to ensure it only processes relevant posts.
 */
add_action('save_post_bylaw_clause', function ($post_id) {
    if (
        defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ||
        wp_is_post_revision($post_id) ||
        get_post_type($post_id) !== 'bylaw_clause' ||
        !current_user_can('edit_post', $post_id)
    ) {
        return;
    }

    if (!isset($_POST['bcm_qe_nonce'])) return;

    $bcm_nonce = sanitize_text_field(wp_unslash($_POST['bcm_qe_nonce']));
    if (!wp_verify_nonce($bcm_nonce, 'bcm_qe_save')) return;

    if (isset($_POST['bcm_qe_bylaw_group'])) {
        update_post_meta($post_id, 'bylaw_group', sanitize_key($_POST['bcm_qe_bylaw_group']));
    }

    if (isset($_POST['bcm_qe_tags'])) {
        update_post_meta($post_id, 'tags', sanitize_textarea_field(wp_unslash($_POST['bcm_qe_tags'])));
    }

    if (isset($_POST['bcm_qe_parent_clause'])) {
        $pid = absint($_POST['bcm_qe_parent_clause']);
        if ($pid && $pid !== (int) $post_id) {
            update_post_meta($post_id, 'parent_clause', $pid);
        } else {
            delete_post_meta($post_id, 'parent_clause');
        }
    }
});
