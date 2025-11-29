<?php

/** File: includes/tools/bulk-operations.php
 * Text Domain: bylaw-clause-manager
 * @version 2.3.0
 * @author greghacke
 * Function: Bulk operation functions for Bylaw Clause Manager
 */

defined('ABSPATH') || exit;

/**
 * Check if a clause title already exists in a bylaw group.
 *
 * @param string $title The title to check.
 * @param string $group The bylaw group.
 * @param int    $exclude_id Optional post ID to exclude from check.
 * @return bool True if exists, false if available.
 */
function bcm_clause_title_exists($title, $group, $exclude_id = 0)
{
    $args = [
        'post_type'      => 'bylaw_clause',
        'post_status'    => ['publish', 'draft', 'pending', 'private'],
        'title'          => $title,
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'meta_query'     => [
            [
                'key'     => 'bylaw_group',
                'value'   => $group,
                'compare' => '='
            ]
        ]
    ];

    if ($exclude_id) {
        $args['post__not_in'] = [$exclude_id];
    }

    $existing = get_posts($args);
    return !empty($existing);
}

/**
 * Get all clauses matching a title prefix in a group.
 *
 * @param string $prefix The title prefix to match.
 * @param string $group  The bylaw group.
 * @return array Array of WP_Post objects.
 */
function bcm_get_clauses_by_prefix($prefix, $group)
{
    $args = [
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
    ];

    $all_posts = get_posts($args);

    // Filter by prefix
    return array_filter($all_posts, function ($post) use ($prefix) {
        $title = get_the_title($post);
        return $title === $prefix || strpos($title, $prefix . '_') === 0;
    });
}

/**
 * Calculate new title by replacing the old prefix with new prefix.
 *
 * @param string $old_title  Current title.
 * @param string $old_prefix Old prefix to replace.
 * @param string $new_prefix New prefix.
 * @return string New title.
 */
function bcm_calculate_new_title($old_title, $old_prefix, $new_prefix)
{
    if ($old_title === $old_prefix) {
        return $new_prefix;
    }

    // Replace prefix portion
    if (strpos($old_title, $old_prefix . '_') === 0) {
        return $new_prefix . substr($old_title, strlen($old_prefix));
    }

    return $old_title;
}

/**
 * Calculate new section ID based on title change.
 *
 * @param string $old_section_id Current section ID.
 * @param string $old_title      Old title.
 * @param string $new_title      New title.
 * @return string New section ID.
 */
function bcm_calculate_new_section_id($old_section_id, $old_title, $new_title)
{
    // Extract the last segment from titles
    $old_parts = explode('_', $old_title);
    $new_parts = explode('_', $new_title);

    $old_last = end($old_parts);
    $new_last = end($new_parts);

    // If section ID matches old last segment, update it
    if ($old_section_id === $old_last) {
        return $new_last;
    }

    return $old_section_id;
}

/**
 * Preview bulk rename operation without making changes.
 *
 * @param string $old_prefix Old title prefix.
 * @param string $new_prefix New title prefix.
 * @param string $group      Bylaw group.
 * @return array|WP_Error Preview data or error.
 */
function bcm_preview_bulk_rename($old_prefix, $new_prefix, $group)
{
    // Validate inputs
    if (empty($old_prefix) || empty($new_prefix) || empty($group)) {
        return new WP_Error('invalid_input', __('All fields are required.', 'bylaw-clause-manager'));
    }

    if ($old_prefix === $new_prefix) {
        return new WP_Error('same_prefix', __('Old and new prefixes are the same.', 'bylaw-clause-manager'));
    }

    // Check if new base title already exists
    if (bcm_clause_title_exists($new_prefix, $group)) {
        return new WP_Error('title_exists', sprintf(
            __('A clause with title "%s" already exists in this group.', 'bylaw-clause-manager'),
            $new_prefix
        ));
    }

    // Get all clauses to rename
    $clauses = bcm_get_clauses_by_prefix($old_prefix, $group);

    if (empty($clauses)) {
        return new WP_Error('no_clauses', sprintf(
            __('No clauses found with prefix "%s".', 'bylaw-clause-manager'),
            $old_prefix
        ));
    }

    // Build preview data
    $preview = [];
    $new_titles_map = []; // old_id => new_title for parent lookups

    // First pass: calculate all new titles
    foreach ($clauses as $clause) {
        $old_title = get_the_title($clause);
        $new_title = bcm_calculate_new_title($old_title, $old_prefix, $new_prefix);
        $new_titles_map[$clause->ID] = $new_title;

        // Check for conflicts with existing titles (outside our rename set)
        if ($old_title !== $new_prefix && bcm_clause_title_exists($new_title, $group, $clause->ID)) {
            return new WP_Error('conflict', sprintf(
                __('Conflict: "%s" would become "%s" which already exists.', 'bylaw-clause-manager'),
                $old_title,
                $new_title
            ));
        }
    }

    // Second pass: build full preview with parent info
    foreach ($clauses as $clause) {
        $old_title = get_the_title($clause);
        $new_title = $new_titles_map[$clause->ID];
        $old_section_id = get_post_meta($clause->ID, 'section_id', true);
        $new_section_id = bcm_calculate_new_section_id($old_section_id, $old_title, $new_title);

        $old_parent_id = get_post_meta($clause->ID, 'parent_clause', true);
        $old_parent_title = $old_parent_id ? get_the_title($old_parent_id) : '';
        $new_parent_title = $old_parent_title;

        // If parent is in our rename set, show its new title
        if ($old_parent_id && isset($new_titles_map[$old_parent_id])) {
            $new_parent_title = $new_titles_map[$old_parent_id];
        }

        $preview[] = [
            'id'               => $clause->ID,
            'old_title'        => $old_title,
            'new_title'        => $new_title,
            'old_section_id'   => $old_section_id,
            'new_section_id'   => $new_section_id,
            'old_parent_id'    => $old_parent_id,
            'old_parent_title' => $old_parent_title,
            'new_parent_title' => $new_parent_title,
            'parent_changed'   => $old_parent_title !== $new_parent_title,
        ];
    }

    return $preview;
}

/**
 * Execute bulk rename operation.
 *
 * @param string $old_prefix Old title prefix.
 * @param string $new_prefix New title prefix.
 * @param string $group      Bylaw group.
 * @return array|WP_Error Results or error.
 */
function bcm_execute_bulk_rename($old_prefix, $new_prefix, $group)
{
    // Run preview first to validate
    $preview = bcm_preview_bulk_rename($old_prefix, $new_prefix, $group);

    if (is_wp_error($preview)) {
        return $preview;
    }

    $results = [
        'success' => [],
        'failed'  => [],
    ];

    // Build ID-to-new-title map for parent updates
    $id_to_new_title = [];
    foreach ($preview as $item) {
        $id_to_new_title[$item['id']] = $item['new_title'];
    }

    // Execute updates
    foreach ($preview as $item) {
        $update_result = wp_update_post([
            'ID'         => $item['id'],
            'post_title' => $item['new_title'],
            'post_name'  => sanitize_title($item['new_title']),
        ], true);

        if (is_wp_error($update_result)) {
            $results['failed'][] = [
                'id'    => $item['id'],
                'title' => $item['old_title'],
                'error' => $update_result->get_error_message(),
            ];
            continue;
        }

        // Update section ID
        update_post_meta($item['id'], 'section_id', $item['new_section_id']);

        $results['success'][] = [
            'id'        => $item['id'],
            'old_title' => $item['old_title'],
            'new_title' => $item['new_title'],
        ];
    }

    return $results;
}
