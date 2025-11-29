<?php

/** File: includes/render/listing.php
 * Text Domain: bylaw-clause-manager
 * @version 2.3.0
 * @author author
 * Function: Generate a hierarchical tree structure of Bylaw Clauses.
 */

defined('ABSPATH') || exit;

/** Renders the Bylaw Clause tree structure recursively.
 * This function retrieves all Bylaw Clauses from the database, ordered by their sequence.
 * It builds a hierarchical tree structure based on the parent-child relationships defined in the 'parent_clause' meta field.
 * The function supports depth control, allowing it to render nested clauses up to a specified depth.
 * It also supports filtering by Bylaw Group if specified.
 */
function bcm_render_bylaw_tree($parent_id = 0, $depth = 0, $group = null)
{
    $meta_query = [];

    if ($parent_id === 0) {
        $meta_query[] = [
            'relation' => 'OR',
            ['key' => 'parent_clause', 'compare' => 'NOT EXISTS'],
            ['key' => 'parent_clause', 'value' => '', 'compare' => '='],
            ['key' => 'parent_clause', 'value' => '0', 'compare' => '=']
        ];
    } else {
        $meta_query[] = ['key' => 'parent_clause', 'value' => $parent_id, 'compare' => '='];
    }

    if ($depth === 0 && $group) {
        $meta_query[] = ['key' => 'bylaw_group', 'value' => $group, 'compare' => '='];
    }

    $clauses = get_posts([
        'post_type'   => 'bylaw_clause',
        'meta_query'  => $meta_query,
        'numberposts' => -1,
    ]);

    usort($clauses, function ($a, $b) {
        return bcm_title_sort_key($a->post_title) <=> bcm_title_sort_key($b->post_title);
    });

    if (!$clauses) return;

    foreach ($clauses as $clause) {
        $section      = get_post_meta($clause->ID, 'section_id', true);
        $content      = $clause->post_content;
        $parent       = get_post_meta($clause->ID, 'parent_clause', true);

        // Build vote tooltip
        $vote_marker = bcm_generate_vote_tooltip($clause->ID);

        if ((int)$clause->ID === (int)$parent) continue;

        $anchor_id = sanitize_title($section ?: $clause->ID);
        $margin    = 20 * (int)$depth;

        // Store content in data attribute for searching
        $search_content = wp_strip_all_tags($content);

        echo "\n" . '<div class="bylaw-clause" id="clause-' . esc_attr($anchor_id) . '" data-id="' . esc_attr($clause->ID) . '" data-parent="' . esc_attr($parent ?: 0) . '" data-content="' . esc_attr(strtolower($search_content)) . '" style="margin-left:' . esc_attr($margin) . 'px;">';
        echo "\n  <div class=\"bylaw-label-wrap\">";
        echo "\n    <div class=\"bylaw-label-text\">";

        // Sanitize and strip wrapping <p> tags
        $filtered_content = apply_filters('the_content', $content);
        $filtered_content = preg_replace('#^<p>|</p>$#', '', trim($filtered_content));

        echo "\n      <p>" . esc_html($section) . ". " . wp_kses_post($filtered_content);
        if (!empty($vote_marker)) {
            echo ' ' . wp_kses_post($vote_marker);
        }
        echo "</p>";

        echo "\n    </div>";
        echo "\n  </div>";
        echo "\n</div>\n";

        bcm_render_bylaw_tree($clause->ID, $depth + 1, $group);
    }
}

/** Generates a tooltip for the vote information of a Bylaw Clause.
 * This function retrieves the vote date, reference, and URL from the post meta of the given clause ID.
 * If any of these values are present, it constructs a tooltip string with the relevant information.
 * The tooltip is returned as a span element with the class 'vote-tooltip', which can be styled with CSS.
 * If no vote information is available, an empty string is returned.
 */
function bcm_generate_vote_tooltip($clause_id)
{
    $vote_date = get_post_meta($clause_id, 'vote_date', true);
    $vote_ref  = get_post_meta($clause_id, 'vote_reference', true);
    $vote_url  = get_post_meta($clause_id, 'vote_url', true);

    if (!$vote_date && !$vote_ref && !$vote_url) return '';

    $tooltip_parts = [];
    if ($vote_date) $tooltip_parts[] = 'Date: ' . esc_html($vote_date);
    if ($vote_ref)  $tooltip_parts[] = 'Reference: ' . esc_html($vote_ref);
    if ($vote_url)  $tooltip_parts[] = 'URL: <a href="' . esc_url($vote_url) . '" target="_blank" rel="noopener noreferrer">' . esc_html($vote_url) . '</a>';

    // Return tooltip safely inside span
    return '<span class="vote-tooltip">[ref]<span class="tooltip-content">' . implode('<br />', $tooltip_parts) . '</span></span>';
}
