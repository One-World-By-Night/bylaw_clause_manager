<?php

/** File: includes/hooks/filters.php
 * Text Domain: bylaw-clause-manager
 * @version 2.3.0
 * @author greghacke
 * Function: Filters for the Bylaw Clause Manager plugin.
 */

defined('ABSPATH') || exit;

/** Makes custom columns sortable in the Bylaw Clause CPT list view.
 * This function registers the custom columns ('bylaw_group', 'parent_clause', and 'short_content') as sortable.
 * This allows users to sort the list table by these columns, enhancing the usability of the admin interface.
 * The sorting functionality is handled by WordPress, which will query the database based on the column names.
 */
add_filter('manage_edit-bylaw_clause_sortable_columns', function ($columns) {
    $columns['bylaw_group']   = 'bylaw_group';
    $columns['parent_clause'] = 'parent_clause';
    $columns['short_content'] = 'short_content';
    return $columns;
});

/** Sanitizes GET parameters used in admin clause filtering.
 * This function retrieves and sanitizes the GET parameters used for filtering Bylaw Clauses in the admin interface.
 * It ensures that the parameters are safe for use in queries and output.
 * The parameters include a nonce for security, the Bylaw Group, and the title filter.
 * It uses `sanitize_text_field` to clean the input, preventing potential security issues such as XSS (Cross-Site Scripting).
 * The sanitized values are then used in the filtering logic of the Bylaw Clause list table.
 */
$nonce_raw = filter_input(INPUT_GET, 'bcm_filter_nonce_field', FILTER_UNSAFE_RAW);
$group_raw = filter_input(INPUT_GET, 'bylaw_group', FILTER_UNSAFE_RAW);
$title_raw = filter_input(INPUT_GET, 'bcm_title_filter', FILTER_UNSAFE_RAW);

$nonce = is_string($nonce_raw) ? sanitize_text_field(wp_unslash($nonce_raw)) : '';
$group = is_string($group_raw) ? sanitize_text_field(wp_unslash($group_raw)) : '';
$title = is_string($title_raw) ? sanitize_text_field(wp_unslash($title_raw)) : '';

/** pre_get_posts filter for Bylaw Clause CPT in admin.
 * This function modifies the main query for the Bylaw Clause custom post type in the WordPress admin.
 * It allows sorting by custom meta fields ('bylaw_group', 'parent_clause', 'tags') and filtering by these fields.
 * It checks if the request is for the admin area and if it's the main query before applying any modifications.
 * It sets the 'meta_key' and 'orderby' parameters based on the requested sorting criteria.
 * If the nonce is valid, it applies a meta query to filter by the specified Bylaw Group and title.
 * If no specific order is set, it defaults to sorting by title in ascending order.
 */
add_action('pre_get_posts', function ($query) use ($nonce, $group, $title) {
    if (!is_admin() || !$query->is_main_query()) return;

    $orderby = $query->get('orderby');

    // Sort by meta fields
    if (in_array($orderby, ['bylaw_group', 'parent_clause', 'tags'], true)) {
        $query->set('meta_key', $orderby);
        $query->set('orderby', 'meta_value');
    }

    // Filtering by meta (if nonce passes)
    if (
        $query->get('post_type') === 'bylaw_clause' &&
        !empty($nonce) &&
        wp_verify_nonce($nonce, 'bcm_filter_nonce')
    ) {
        $meta_query = [];

        if (!empty($group)) {
            $meta_query[] = [
                'key' => 'bylaw_group',
                'value' => $group,
                'compare' => '='
            ];
        }

        if (!empty($meta_query)) {
            $query->set('meta_query', $meta_query);
        }

        if (!empty($title)) {
            $query->set('s', $title);
        }
    }

    // Default sort by title
    if (!$orderby) {
        $query->set('orderby', 'title');
        $query->set('order', 'ASC');
    }
});

/** Custom search filter for Bylaw Clause CPT in admin.
 * This function modifies the search query for the Bylaw Clause custom post type in the WordPress admin.
 */
add_filter('posts_search', function ($search, $wp_query) {
    global $wpdb;

    if (!is_admin() || !$wp_query->is_main_query()) return $search;
    if ($wp_query->get('post_type') !== 'bylaw_clause') return $search;

    $input = $wp_query->query_vars['s'] ?? '';
    if ($input === '') return $search;

    $like = $wpdb->esc_like($input) . '%'; // Match only prefixes
    return " AND {$wpdb->posts}.post_title LIKE '{$like}' ";
}, 10, 2);
