<?php

/** File: includes/admin/init.php
 * Text Domain: bylaw-clause-manager
 * @version 2.3.0
 * @author greghacke
 * Function: Quickly initialize the admin area of the Bylaw Clause Manager plugin.
 */

defined('ABSPATH') || exit;

/** --- Require each admin file once --- */
require_once __DIR__ . '/cpt.php';
require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/enqueue.php';
require_once __DIR__ . '/metabox.php';
require_once __DIR__ . '/save.php';

// AJAX handler for fetching clauses by group
add_action('wp_ajax_bcm_get_group_clauses', function () {
    check_ajax_referer('bcm_ajax_nonce', 'nonce');

    $group = sanitize_key($_POST['group'] ?? '');
    $current_post = absint($_POST['current_post'] ?? 0);

    if (empty($group)) {
        wp_send_json_success([]);
        return;
    }

    $clauses = get_posts([
        'post_type'      => 'bylaw_clause',
        'posts_per_page' => -1,
        'post_status'    => ['publish', 'draft', 'pending', 'private'],
        'orderby'        => 'title',
        'order'          => 'ASC',
        'exclude'        => $current_post,
        'meta_query'     => [
            [
                'key'     => 'bylaw_group',
                'value'   => $group,
                'compare' => '='
            ]
        ]
    ]);

    $results = [];
    foreach ($clauses as $clause) {
        $results[] = [
            'id'      => $clause->ID,
            'title'   => get_the_title($clause),
            'snippet' => mb_substr(wp_strip_all_tags($clause->post_content), 0, 30)
        ];
    }

    wp_send_json_success($results);
});
