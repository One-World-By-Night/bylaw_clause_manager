<?php

/** File: includes/admin/enqueue.php
 * Text Domain: bylaw-clause-manager
 * @version 2.1.1
 * @author greghacke
 * Function: Create the custom post type for bylaw clauses
 */

defined('ABSPATH') || exit;

/** Register the custom post type for bylaw clauses
 * Definition of the custom post type 'bylaw_clause'
 * This post type is used to manage bylaw clauses within the plugin.
 * It supports titles, editors, and revisions, and is shown in the REST API.
 * The post type is public and has a custom rewrite rule that includes the bylaw group in the URL.
 */
function bcm_register_bylaw_clause_cpt() {
    register_post_type('bylaw_clause', [
        'labels' => [
            'name'          => esc_html__('Bylaw Clauses', 'bylaw-clause-manager'),
            'singular_name' => esc_html__('Bylaw Clause', 'bylaw-clause-manager'),
        ],
        'public'        => true,
        'has_archive'   => false,
        'rewrite'       => [
            'slug'       => 'bylaw-clause/%bylaw_group%',
            'with_front' => false,
        ],
        'supports'      => ['title', 'editor', 'revisions'],
        'show_in_rest'  => true,
        'menu_icon'     => 'dashicons-book-alt',
    ]);
}
add_action('init', 'bcm_register_bylaw_clause_cpt');

/** Create the custom permalink structure for bylaw clauses
 * This function modifies the permalink structure for the 'bylaw_clause' post type.
 * It uses the 'bylaw_group' custom field to create a more descriptive URL.
 * The URL format is: /bylaw-clause/{group}/{slug}/
 */
function bcm_custom_bylaw_permalink($post_link, $post) {
    if ($post->post_type !== 'bylaw_clause') return $post_link;

    $group = get_post_meta($post->ID, 'bylaw_group', true);
    $group = $group ? sanitize_title($group) : 'uncategorized';

    $slug = sanitize_title($post->post_title);

    return home_url("/bylaw-clause/{$group}/{$slug}/");
}
add_filter('post_type_link', 'bcm_custom_bylaw_permalink', 10, 2);

/** Create custom rewrite rules for bylaw clauses
 * This function adds a custom rewrite rule for the 'bylaw_clause' post type.
 * It allows the URL structure to include the bylaw group and the clause slug.
 * 
 */
function bcm_custom_rewrite_rules() {
    add_rewrite_rule(
        '^bylaw-clause/([^/]+)/([^/]+)/?$',
        'index.php?post_type=bylaw_clause&name=$matches[2]',
        'top'
    );
}
add_action('init', 'bcm_custom_rewrite_rules');