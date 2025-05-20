<?php
/**
 * Plugin Name: Bylaw Clause Manager
 * Description: Manage nested, trackable bylaws with tagging, filtering, and recursive rendering.
 * Version: 1.0
 * Author: OWBN
 */

// Register Custom Post Type
function bcm_register_bylaw_clause_cpt() {
    register_post_type('bylaw_clause', [
        'labels' => [
            'name' => 'Bylaw Clauses',
            'singular_name' => 'Bylaw Clause',
        ],
        'public' => true,
        'has_archive' => false,
        'supports' => ['title', 'editor', 'revisions'],
        'show_in_rest' => true,
    ]);
}
add_action('init', 'bcm_register_bylaw_clause_cpt');

// Register ACF Fields (assuming ACF is installed and active)
function bcm_register_acf_fields() {
    if (function_exists('acf_add_local_field_group')) {
        acf_add_local_field_group([
            'key' => 'group_bcm_bylaw_fields',
            'title' => 'Bylaw Clause Fields',
            'fields' => [
                [
                    'key' => 'field_section_id',
                    'label' => 'Section ID',
                    'name' => 'section_id',
                    'type' => 'text',
                ],
                [
                    'key' => 'field_label',
                    'label' => 'Label',
                    'name' => 'label',
                    'type' => 'text',
                ],
                [
                    'key' => 'field_tags',
                    'label' => 'Tags (comma-separated)',
                    'name' => 'tags',
                    'type' => 'text',
                ],
                [
                    'key' => 'field_sort_order',
                    'label' => 'Sort Order',
                    'name' => 'sort_order',
                    'type' => 'number',
                ],
                [
                    'key' => 'field_vote_date',
                    'label' => 'Vote Date',
                    'name' => 'vote_date',
                    'type' => 'date_picker',
                    'display_format' => 'F j, Y',
                    'return_format' => 'F j, Y',
                ],
                [
                    'key' => 'field_vote_reference',
                    'label' => 'Vote Reference',
                    'name' => 'vote_reference',
                    'type' => 'text',
                ],
                [
                    'key' => 'field_parent_clause',
                    'label' => 'Parent Clause',
                    'name' => 'parent_clause',
                    'type' => 'post_object',
                    'post_type' => ['bylaw_clause'],
                    'return_format' => 'id',
                    'allow_null' => 1,
                ],
            ],
            'location' => [
                [
                    [
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'bylaw_clause',
                    ],
                ],
            ],
        ]);
    }
}
add_action('acf/init', 'bcm_register_acf_fields');

// Recursive Rendering Function
function bcm_render_bylaw_tree($parent_id = 0, $depth = 0) {
    $clauses = get_posts([
        'post_type' => 'bylaw_clause',
        'meta_query' => [
            [
                'key' => 'parent_clause',
                'value' => $parent_id,
                'compare' => '='
            ]
        ],
        'orderby' => 'meta_value_num',
        'meta_key' => 'sort_order',
        'order' => 'ASC',
        'numberposts' => -1
    ]);

    if (!$clauses) return;

    foreach ($clauses as $clause) {
        $section      = get_field('section_id', $clause->ID);
        $label        = get_field('label', $clause->ID);
        $content      = get_field('content', $clause->ID);
        $tags         = get_field('tags', $clause->ID);
        $parent       = get_field('parent_clause', $clause->ID);
        $vote_date    = get_field('vote_date', $clause->ID);
        $vote_ref     = get_field('vote_reference', $clause->ID);

        $class_string = '';
        $tag_array = [];

        if (!empty($tags)) {
            $tag_array = array_map('trim', explode(',', strtolower($tags)));
            $class_string = implode(' ', array_map('sanitize_html_class', $tag_array));
        }

        // Build tooltip text
        $tooltip = '';
        if ($vote_date || $vote_ref) {
            $tooltip_parts = [];
            if ($vote_date) $tooltip_parts[] = 'Vote Date: ' . esc_html($vote_date);
            if ($vote_ref)  $tooltip_parts[] = 'Reference: ' . esc_html($vote_ref);
            $tooltip = implode(' | ', $tooltip_parts);
        }

        // Output clause block with data attributes and optional tooltip
        echo '<div class="bylaw-clause ' . esc_attr($class_string) . '" ' .
             'data-id="' . esc_attr($clause->ID) . '" ' .
             'data-parent="' . esc_attr($parent ? $parent : 0) . '" ' .
             (!empty($tooltip) ? 'title="' . esc_attr($tooltip) . '" ' : '') .
             'style="margin-left:' . (20 * $depth) . 'px;">';

        echo '<strong>' . esc_html($section . '. ' . $label) . '</strong>';

        if (!empty(trim(strip_tags($content)))) {
            echo '<div class="bylaw-content">' . $content . '</div>';
        }

        echo '</div>';

        // Recursive render of child clauses
        bcm_render_bylaw_tree($clause->ID, $depth + 1);
    }
}


// Shortcode for rendering
add_shortcode('render_bylaws', function($atts) {
    ob_start();
    bcm_render_bylaw_tree(0, 0);
    return ob_get_clean();
});

// Enqueue CSS and JS assets
function bcm_enqueue_assets() {
    $plugin_url = plugin_dir_url(__FILE__);

    // Load CSS
    wp_enqueue_style('bcm-style', $plugin_url . 'css/style.css');

    // Load JS (optional, only if filter.js is being used)
    wp_enqueue_script('bcm-filter', $plugin_url . 'js/filter.js', [], false, true);
}
add_action('wp_enqueue_scripts', 'bcm_enqueue_assets');