<?php

/** File: includes/admin/save.php
 * Text Domain: bylaw-clause-manager
 * @version 2.3.6
 * @author greghacke
 * Function: Save post meta for the Bylaw Clause CPT
 */

defined('ABSPATH') || exit;

/** Save post meta from regular edit screen or Quick Edit.
 * Consolidated into single hook for performance.
 */
add_action('save_post_bylaw_clause', function ($post_id) {
    // Skip autosaves and revisions immediately
    if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || wp_is_post_revision($post_id)) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    // Track if we saved anything (for cache clearing)
    $saved = false;

    // Regular edit screen save
    if (
        isset($_POST['bcm_clause_meta_nonce']) &&
        wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bcm_clause_meta_nonce'])), 'bcm_clause_meta_save')
    ) {
        $fields = [
            'tags'           => isset($_POST['bcm_tags']) ? sanitize_text_field(wp_unslash($_POST['bcm_tags'])) : '',
            'bylaw_group'    => isset($_POST['bcm_bylaw_group']) ? sanitize_key(wp_unslash($_POST['bcm_bylaw_group'])) : '',
            'section_id'     => isset($_POST['bcm_section_id']) ? sanitize_text_field(wp_unslash($_POST['bcm_section_id'])) : '',
            'vote_date'      => isset($_POST['bcm_vote_date']) ? sanitize_text_field(wp_unslash($_POST['bcm_vote_date'])) : '',
            'vote_reference' => isset($_POST['bcm_vote_reference']) ? sanitize_text_field(wp_unslash($_POST['bcm_vote_reference'])) : '',
            'vote_url'       => isset($_POST['bcm_vote_url']) ? esc_url_raw(wp_unslash($_POST['bcm_vote_url'])) : '',
        ];

        foreach ($fields as $key => $value) {
            update_post_meta($post_id, $key, $value);
        }

        $parent_id = isset($_POST['bcm_parent_clause']) ? absint(wp_unslash($_POST['bcm_parent_clause'])) : 0;
        update_post_meta($post_id, 'parent_clause', ($parent_id !== $post_id ? $parent_id : ''));

        $saved = true;
    }

    // Quick Edit save
    if (
        isset($_POST['bcm_qe_nonce']) &&
        wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bcm_qe_nonce'])), 'bcm_qe_save')
    ) {
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

        $saved = true;
    }

    // Only clear cache if we actually saved something
    if ($saved) {
        bcm_clear_bylaw_cache($post_id);
    }
});

/** Clear bylaw transient cache efficiently.
 * Uses specific transient keys instead of LIKE query.
 */
function bcm_clear_bylaw_cache($post_id) {
    // Get the group for this post to clear only relevant cache
    $group = get_post_meta($post_id, 'bylaw_group', true);
    
    // Clear specific group transient
    if ($group) {
        delete_transient('bcm_bylaws_' . md5(serialize(['group' => $group])));
    }
    
    // Clear the "all groups" transient
    delete_transient('bcm_bylaws_' . md5(serialize(['group' => null])));
    
    // Set a version flag instead of mass deletion
    update_option('bcm_cache_version', time(), false);
}