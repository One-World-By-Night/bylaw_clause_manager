<?php

/** File: includes/admin/metabox.php
 * Text Domain: bylaw-clause-manager
 * @version 2.2.4
 * @author greghacke
 * Function: Register and render the Bylaw Clause metabox in the admin area
 */

defined('ABSPATH') || exit;

/** Register the Bylaw Clause metabox
 * This function adds a metabox to the Bylaw Clause post type edit screen.
 */
add_action('add_meta_boxes', function () {
    add_meta_box(
        'bcm_clause_meta',
        __('Bylaw Clause Details', 'bylaw-clause-manager'),
        'bcm_render_clause_metabox',
        'bylaw_clause',
        'normal',
        'default'
    );
});

/**
 * Render the Bylaw Clause metabox UI
 * This function outputs the HTML for the Bylaw Clause metabox. 
 * It includes fields for selecting the Bylaw Group, Parent Clause, Section ID, Tags, and Vote Metadata.
 * It also includes nonce fields for security.
 *
 * @param WP_Post $post
 */
function bcm_render_clause_metabox($post) {
    wp_nonce_field('bcm_clause_meta_save', 'bcm_clause_meta_nonce');

    $group     = get_post_meta($post->ID, 'bylaw_group', true);
    $parent    = get_post_meta($post->ID, 'parent_clause', true);
    $section_id = get_post_meta($post->ID, 'section_id', true);
    $tags      = get_post_meta($post->ID, 'tags', true);
    $vote_date = get_post_meta($post->ID, 'vote_date', true);
    $vote_ref  = get_post_meta($post->ID, 'vote_reference', true);
    $vote_url  = get_post_meta($post->ID, 'vote_url', true);

    $groups = bcm_get_bylaw_groups();
    
    echo '<p><label><strong>' . esc_html__('Bylaw Group', 'bylaw-clause-manager') . '</strong><br />';
    echo '<select name="bcm_bylaw_group" id="bcm_bylaw_group" style="width:100%;">';
    echo '<option value="">' . esc_html__('— Select —', 'bylaw-clause-manager') . '</option>';
    foreach ($groups as $key => $label) {
        printf(
            '<option value="%s"%s>%s</option>',
            esc_attr($key),
            selected($group, $key, false),
            esc_html($label)
        );
    }
    echo '</select></label></p>';

    echo '<p><label><strong>' . esc_html__('Parent Clause', 'bylaw-clause-manager') . '</strong><br />';
    echo '<select name="bcm_parent_clause" id="bcm_parent_clause" class="bcm-parent-select" style="width:100%;">';
    echo '<option value="">' . esc_html__('— None —', 'bylaw-clause-manager') . '</option>';
    
    // Only fetch clauses if a group is selected
    if (!empty($group)) {
        $clauses = get_posts([
            'post_type'      => 'bylaw_clause',
            'posts_per_page' => -1,
            'post_status'    => ['publish', 'draft', 'pending', 'private'],
            'orderby'        => 'title',
            'order'          => 'ASC',
            'meta_query'     => [
                [
                    'key'     => 'bylaw_group',
                    'value'   => $group,
                    'compare' => '='
                ]
            ]
        ]);
        
        foreach ($clauses as $clause) {
            if ($clause->ID == $post->ID) continue; // Avoid self-parenting
            $title   = get_the_title($clause);
            $snippet = mb_substr(wp_strip_all_tags($clause->post_content), 0, 30);
            printf(
                '<option value="%s"%s>%s – %s</option>',
                esc_attr($clause->ID),
                selected($parent, $clause->ID, false),
                esc_html($title),
                esc_html($snippet)
            );
        }
    }
    
    echo '</select></label></p>';

    // Add script to update parent options when group changes
    echo '<script>
    jQuery(document).ready(function($) {
        $("#bcm_bylaw_group").on("change", function() {
            var group = $(this).val();
            var currentParent = "' . esc_js($parent) . '";
            var postId = ' . (int)$post->ID . ';
            
            $.post(ajaxurl, {
                action: "bcm_get_group_clauses",
                group: group,
                current_post: postId,
                nonce: "' . wp_create_nonce('bcm_ajax_nonce') . '"
            }, function(response) {
                var select = $("#bcm_parent_clause");
                select.empty().append(\'<option value="">— None —</option>\');
                
                if (response.success && response.data) {
                    $.each(response.data, function(i, clause) {
                        select.append($(\'<option>\').val(clause.id).text(clause.title + " – " + clause.snippet));
                    });
                    
                    // Restore previous selection if it exists in new options
                    if (currentParent && select.find(\'option[value="\' + currentParent + \'"]\').length) {
                        select.val(currentParent);
                    }
                }
                
                // Reinitialize Select2 if needed
                if (select.hasClass("select2-hidden-accessible")) {
                    select.select2("destroy");
                }
                select.select2({ width: "100%" });
            });
        });
    });
    </script>';

    echo '<p><label><strong>' . esc_html__('Section ID', 'bylaw-clause-manager') . '</strong><br />';
    echo '<input type="text" name="bcm_section_id" value="' . esc_attr($section_id) . '" style="width:100%;" /></label></p>';

    echo '<p><label><strong>' . esc_html__('Tags', 'bylaw-clause-manager') . '</strong><br />';
    echo '<input type="text" name="bcm_tags" value="' . esc_attr($tags) . '" style="width:100%;" /></label></p>';

    echo '<hr><h4>' . esc_html__('Vote Metadata', 'bylaw-clause-manager') . '</h4>';

    echo '<p><label><strong>' . esc_html__('Vote Date', 'bylaw-clause-manager') . '</strong><br />';
    echo '<input type="date" name="bcm_vote_date" value="' . esc_attr($vote_date) . '" /></label></p>';

    echo '<p><label><strong>' . esc_html__('Vote Reference', 'bylaw-clause-manager') . '</strong><br />';
    echo '<input type="text" name="bcm_vote_reference" value="' . esc_attr($vote_ref) . '" style="width:100%;" /></label></p>';

    echo '<p><label><strong>' . esc_html__('Vote URL', 'bylaw-clause-manager') . '</strong><br />';
    echo '<input type="url" name="bcm_vote_url" value="' . esc_attr($vote_url) . '" style="width:100%;" /></label></p>';
}