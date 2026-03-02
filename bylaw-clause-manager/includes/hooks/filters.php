<?php

defined('ABSPATH') || exit;

add_filter('manage_edit-bylaw_clause_sortable_columns', function ($columns) {
    $columns['bylaw_group']   = 'bylaw_group';
    $columns['parent_clause'] = 'parent_clause';
    $columns['short_content'] = 'short_content';
    return $columns;
});

$nonce_raw = filter_input(INPUT_GET, 'bcm_filter_nonce_field', FILTER_UNSAFE_RAW);
$group_raw = filter_input(INPUT_GET, 'bylaw_group', FILTER_UNSAFE_RAW);
$title_raw = filter_input(INPUT_GET, 'bcm_title_filter', FILTER_UNSAFE_RAW);

$nonce = is_string($nonce_raw) ? sanitize_text_field(wp_unslash($nonce_raw)) : '';
$group = is_string($group_raw) ? sanitize_text_field(wp_unslash($group_raw)) : '';
$title = is_string($title_raw) ? sanitize_text_field(wp_unslash($title_raw)) : '';

add_action('pre_get_posts', function ($query) use ($nonce, $group, $title) {
    if (!is_admin() || !$query->is_main_query()) return;

    $orderby = $query->get('orderby');
    if (in_array($orderby, ['bylaw_group', 'parent_clause', 'tags'], true)) {
        $query->set('meta_key', $orderby);
        $query->set('orderby', 'meta_value');
    }
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
    if (!$orderby) {
        $query->set('orderby', 'title');
        $query->set('order', 'ASC');
    }
});

add_filter('posts_search', function ($search, $wp_query) {
    global $wpdb;

    if (!is_admin() || !$wp_query->is_main_query()) return $search;
    if ($wp_query->get('post_type') !== 'bylaw_clause') return $search;

    $input = $wp_query->query_vars['s'] ?? '';
    if ($input === '') return $search;

    $like = $wpdb->esc_like($input) . '%'; // Match only prefixes
    return " AND {$wpdb->posts}.post_title LIKE '{$like}' ";
}, 10, 2);
