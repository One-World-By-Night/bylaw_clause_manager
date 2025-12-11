<?php

/** File: includes/shortcodes/listing.php
 * Text Domain: bylaw-clause-manager
 * @version 2.3.6
 * @author greghacke
 * Function: Shortcodes for rendering Bylaw Clauses
 */

defined('ABSPATH') || exit;

/** Renders the Bylaw Clause listing shortcode.
 * This shortcode displays a list of Bylaw Clauses, allowing users to filter by tags and print/export the list.
 * It queries the latest modified clause to show the last updated timestamp.
 * The shortcode accepts an optional 'group' attribute to filter clauses by a specific Bylaw Group.
 * The output includes a toolbar for filtering and printing, and it renders the Bylaw Clause tree structure.
 * The rendered output is wrapped in a div with the class 'bcm-wrapper' for styling.
 * The shortcode can be used in posts or pages with the format:
 *      [render_bylaws group="group-slug"]
 * 
 * Caching: Output is cached via transients for non-editors. Cache invalidates via version flag on clause save.
 */
add_shortcode('render_bylaws', function ($atts) {
    $atts = shortcode_atts(['group' => null], $atts);

    // === CACHE CHECK START ===
    // Skip cache for logged-in users with edit capability (they need fresh data)
    $use_cache = !current_user_can('edit_posts');
    $cache_version = get_option('bcm_cache_version', 0);
    $cache_key = 'bcm_bylaws_' . md5(serialize($atts) . $cache_version);

    if ($use_cache && ($cached = get_transient($cache_key))) {
        return $cached;
    }
    // === CACHE CHECK END ===

    ob_start();

    // Query latest modified clause for timestamp
    $args = [
        'post_type'      => 'bylaw_clause',
        'posts_per_page' => 1,
        'orderby'        => 'modified',
        'order'          => 'DESC',
        'post_status'    => 'any',
        'fields'         => 'ids',
    ];

    if (!empty($atts['group'])) {
        $args['meta_query'] = [[
            'key'     => 'bylaw_group',
            'value'   => $atts['group'],
            'compare' => '='
        ]];
    }

    $latest = get_posts($args);
    $latest_time = $latest ? get_post_modified_time('F j, Y', false, $latest[0]) : '';

    echo '<div class="bcm-wrapper">';

    if ($latest_time) {
        echo '<div class="bcm-updated"><strong>' . esc_html__('Last Updated:', 'bylaw-clause-manager') . ' ' . esc_html($latest_time) . '</strong></div>';
    }

    echo '<div id="bcm-toolbar"><!-- #bcm-toolbar -->';
    echo '  <label for="bcm-content-filter">' . esc_html__('Filter by Content:', 'bylaw-clause-manager') . '</label>';
    echo '  <input type="text" id="bcm-content-filter" placeholder="' . esc_attr__('Press Enter or click Search...', 'bylaw-clause-manager') . '" style="width: 300px;" />';
    echo '  <button type="button" id="bcm-content-search">' . esc_html__('Search', 'bylaw-clause-manager') . '</button>';
    echo '  <button type="button" onclick="bcmClearFilters()">' . esc_html__('Clear Filters', 'bylaw-clause-manager') . '</button>';
    echo '  <button type="button" onclick="window.print()">' . esc_html__('Print / Export PDF', 'bylaw-clause-manager') . '</button>';
    echo '</div><!-- #bcm-toolbar -->';

    bcm_render_bylaw_tree(0, 0, $atts['group']);

    echo '</div>';

    // === CACHE STORE START ===
    $output = ob_get_clean();

    if ($use_cache) {
        set_transient($cache_key, $output, 12 * HOUR_IN_SECONDS);
    }

    return $output;
    // === CACHE STORE END ===
});