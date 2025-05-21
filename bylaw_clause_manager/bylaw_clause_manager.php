<?php
/**
 * Plugin Name: Bylaw Clause Manager
 * Description: Manage nested, trackable bylaws with tagging, filtering, recursive rendering, anchors, and Select2 filtering.
 * Version: 1.0.10
 * Author: OWBN (Greg H.)
 * Author URI: https://www.owbn.net
 * License: MIT
 * License URI: https://opensource.org/licenses/MIT
 * Text Domain: bylaw-clause-manager
 * Domain Path: /languages
 * GitHub Plugin URI: https://github.com/One-World-By-Night/bylaw_clause_manager
 * GitHub Branch: main
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

// Register ACF Fields
function bcm_register_acf_fields() {
    if (function_exists('acf_add_local_field_group')) {
        acf_add_local_field_group([
            'key' => 'group_bcm_bylaw_fields',
            'title' => 'Bylaw Clause Fields',
            'fields' => [
                [
                    'key' => 'field_bylaw_group',
                    'label' => 'Bylaw Group',
                    'name' => 'bylaw_group',
                    'type' => 'select',
                    'choices' => [
                        'character' => 'Character',
                        'council' => 'Council',
                        'coordinator' => 'Coordinator',
                    ],
                    'allow_null' => 0,
                    'multiple' => 0,
                    'ui' => 1,
                    'return_format' => 'value',
                ],
                [
                    'key' => 'field_parent_clause',
                    'label' => 'Parent Clause',
                    'name' => 'parent_clause',
                    'type' => 'post_object',
                    'post_type' => ['bylaw_clause'],
                    'return_format' => 'id',
                    'allow_null' => 1,
                    'multiple' => 0,
                ],
                [ 'key' => 'field_section_id', 'label' => 'Section ID', 'name' => 'section_id', 'type' => 'text' ],
                [ 'key' => 'field_tags', 'label' => 'Tags (comma-separated)', 'name' => 'tags', 'type' => 'text' ],
                [ 'key' => 'field_sort_order', 'label' => 'Sort Order', 'name' => 'sort_order', 'type' => 'number' ],
                [ 'key' => 'field_vote_date', 'label' => 'Vote Date', 'name' => 'vote_date', 'type' => 'date_picker', 'display_format' => 'F j, Y', 'return_format' => 'F j, Y' ],
                [ 'key' => 'field_vote_reference', 'label' => 'Vote Reference', 'name' => 'vote_reference', 'type' => 'text' ],
            ],
            'location' => [[ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'bylaw_clause' ]]],
        ]);
    }
}
add_action('acf/init', 'bcm_register_acf_fields');

add_filter('acf/fields/post_object/result/name=parent_clause', function($title, $post, $field, $post_id) {
    $section_id = get_field('section_id', $post->ID);
    return $title . ' [' . $section_id . ']';
}, 10, 4);

add_filter('manage_bylaw_clause_posts_columns', function($columns) {
    $new = [];
    foreach ($columns as $key => $label) {
        $new[$key] = $label;
        if ($key === 'title') {
            $new['bylaw_group'] = 'Bylaw Group';
            $new['parent_clause'] = 'Parent Clause';
        }
    }
    return $new;
});

add_action('manage_bylaw_clause_posts_custom_column', function($column, $post_id) {
    if ($column === 'bylaw_group') {
        $val = get_field('bylaw_group', $post_id);
        echo esc_html(ucfirst($val) ?: '—');
    } elseif ($column === 'parent_clause') {
        $parent_id = get_field('parent_clause', $post_id);
        echo $parent_id ? esc_html(get_the_title($parent_id)) : '—';
    }
}, 10, 2);

add_filter('manage_edit-bylaw_clause_sortable_columns', function($columns) {
    $columns['bylaw_group'] = 'bylaw_group';
    $columns['parent_clause'] = 'parent_clause';
    return $columns;
});

add_action('pre_get_posts', function($query) {
    if (!is_admin() || !$query->is_main_query()) return;
    $orderby = $query->get('orderby');
    if ($orderby === 'bylaw_group') {
        $query->set('meta_key', 'bylaw_group');
        $query->set('orderby', 'meta_value');
    }
    if ($orderby === 'parent_clause') {
        $query->set('meta_key', 'parent_clause');
        $query->set('orderby', 'meta_value');
    }
    if ($query->get('post_type') === 'bylaw_clause' && isset($_GET['bylaw_group']) && $_GET['bylaw_group'] !== '') {
        $query->set('meta_query', [[
            'key' => 'bylaw_group',
            'value' => sanitize_text_field($_GET['bylaw_group']),
            'compare' => '='
        ]]);
    }
});

add_action('restrict_manage_posts', function() {
    global $typenow;
    if ($typenow !== 'bylaw_clause') return;
    $selected = $_GET['bylaw_group'] ?? '';
    $options = [ '' => 'All Bylaw Groups', 'character' => 'Character', 'council' => 'Council', 'coordinator' => 'Coordinator' ];
    echo '<select name="bylaw_group">';
    foreach ($options as $val => $label) {
        printf('<option value="%s"%s>%s</option>', esc_attr($val), selected($selected, $val, false), esc_html($label));
    }
    echo '</select>';
});

function bcm_render_bylaw_tree($parent_id = 0, $depth = 0, $group = null) {
    $meta_query = [];
    if ($parent_id === 0) {
        $meta_query[] = [
            'relation' => 'OR',
            [ 'key' => 'parent_clause', 'compare' => 'NOT EXISTS' ],
            [ 'key' => 'parent_clause', 'value' => '', 'compare' => '=' ],
            [ 'key' => 'parent_clause', 'value' => '0', 'compare' => '=' ]
        ];
    } else {
        $meta_query[] = [ 'key' => 'parent_clause', 'value' => $parent_id, 'compare' => '=' ];
    }
    if ($depth === 0 && $group) {
        $meta_query[] = [ 'key' => 'bylaw_group', 'value' => $group, 'compare' => '=' ];
    }

    $clauses = get_posts([
        'post_type' => 'bylaw_clause',
        'meta_query' => $meta_query,
        'orderby' => 'meta_value_num',
        'meta_key' => 'sort_order',
        'order' => 'ASC',
        'numberposts' => -1
    ]);

    if (!$clauses) return;

    foreach ($clauses as $clause) {
        $section = get_field('section_id', $clause->ID);
        $content = $clause->post_content;
        $tags = get_field('tags', $clause->ID);
        $parent = get_field('parent_clause', $clause->ID);
        $vote_date = get_field('vote_date', $clause->ID);
        $vote_ref = get_field('vote_reference', $clause->ID);

        if ((int) $clause->ID === (int) $parent) continue;

        $class_string = '';
        $tag_array = [];
        if (!empty($tags)) {
            $tag_array = array_map('trim', explode(',', strtolower($tags)));
            $class_string = implode(' ', array_map('sanitize_html_class', $tag_array));
        }

        $vote_marker = '';
        if ($vote_date || $vote_ref) {
            $tooltip_parts = [];
            if ($vote_date) $tooltip_parts[] = 'Vote Date: ' . esc_html($vote_date);
            if ($vote_ref) $tooltip_parts[] = 'Reference: ' . esc_html($vote_ref);
            $tooltip_text = implode(' | ', $tooltip_parts);
            $vote_marker = '<span class="vote-tooltip" data-tooltip="' . esc_attr($tooltip_text) . '"><sup>📜</sup></span>';
        }

        $anchor_id = sanitize_title($section);
        echo '<div class="bylaw-clause ' . esc_attr($class_string) . '" id="clause-' . esc_attr($anchor_id) . '" data-id="' . esc_attr($clause->ID) . '" data-parent="' . esc_attr($parent ? $parent : 0) . '" style="margin-left:' . (20 * $depth) . 'px;">';

        echo "<div class=\"bylaw-label-wrap\">\n";
        echo "  <span class=\"bylaw-label-number\">" . esc_html($section) . ".</span>\n";
        echo "  <div class=\"bylaw-label-text\">\n";
        echo      apply_filters('the_content', $content) . "\n";
        echo "    " . $vote_marker . "\n";
        echo "  </div>\n";
        echo "</div>\n";

        echo '</div>';

        bcm_render_bylaw_tree($clause->ID, $depth + 1, $group);
    }
}

add_shortcode('render_bylaws', function($atts) {
    $atts = shortcode_atts([ 'group' => null ], $atts);
    echo '<div id="bcm-toolbar">
            <label for="bcm-tag-select">Filter by Tag:</label>
            <select id="bcm-tag-select" multiple style="width: 300px;"></select>
            <button onclick="window.print()">Print / Export PDF</button>
          </div>';
    ob_start();
    bcm_render_bylaw_tree(0, 0, $atts['group']);
    return ob_get_clean();
});

add_filter('acf/prepare_field/name=parent_clause', function($field) {
    $current_group = get_field('bylaw_group');

    if ($current_group) {
        $field['instructions'] = 'Only clauses from the selected Bylaw Group ("' . ucfirst($current_group) . '") are shown here.';
    } else {
        $field['instructions'] = 'Select a Bylaw Group first to filter available parent clauses.';
    }

    return $field;
});

add_filter('acf/fields/post_object/query/name=parent_clause', function($args, $field, $post_id) {
    if (!$post_id || get_post_type($post_id) !== 'bylaw_clause') return $args;

    // Get the group from current post
    $group = get_field('bylaw_group', $post_id);

    if (!$group) return $args;

    // Add meta_query to filter by matching bylaw_group
    $args['meta_query'] = [
        [
            'key' => 'bylaw_group',
            'value' => $group,
            'compare' => '='
        ]
    ];

    // Optionally exclude current post (avoid assigning itself as parent)
    $args['post__not_in'] = [$post_id];

    return $args;
}, 10, 3);

add_filter('acf/fields/post_object/result/name=parent_clause', function($title, $post, $field, $post_id) {
    $section_id = get_field('section_id', $post->ID);
    $preview = mb_substr(strip_tags($post->post_content), 0, 25);
    $preview .= (mb_strlen($post->post_content) > 25 ? '…' : '');
    return "{$post->post_title} {$section_id} {$preview}";
}, 10, 4);

function bcm_enqueue_assets() {
    $plugin_url = plugins_url('', __FILE__);
    wp_enqueue_style('select2-style', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css');
    wp_enqueue_script('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', ['jquery'], null, true);
    wp_enqueue_script('bcm-filter', $plugin_url . '/js/filter.js', ['jquery', 'select2'], false, true);
}

function bcm_output_inline_assets() {
    ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <style>
        .bylaw-clause {
            margin-bottom: 0.25em;
        }
         .bylaw-clause strong {
            display: block;
            font-size: 1.1em;
            margin-bottom: 0.1em;
        }
        .bylaw-content {
            margin-left: 1em;
            font-size: 0.95em;
        }
        .bylaw-label-wrap {
            display: flex;
            align-items: flex-start;
            gap: 0.25em;
            flex-wrap: wrap;
            line-height: 1;
        }

        .bylaw-label-number {
            font-weight: bold;
            white-space: nowrap;
        }

        .bylaw-label-text {
            word-break: break-word;
            flex: 1;
            min-width: 0;
        }

        .bylaw-content {
            margin-left: 1em;
            font-size: 0.95em;
        }
        .bylaw-content p {
            margin: 0.25em 0; /* Reduce spacing between paragraphs */
        }
       .vote-tooltip {
            position: relative;
            display: inline-block;
            cursor: help;
            font-size: 0.85em;
            color: #555;
            margin-left: 4px;
        }
        .vote-tooltip::after {
            content: attr(data-tooltip);
            position: absolute;
            top: 120%;
            left: 50%;
            transform: translateX(-50%);
            background-color: #333;
            color: #fff;
            padding: 6px 8px;
            border-radius: 4px;
            white-space: pre-wrap;
            font-size: 0.75em;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.2s ease-in-out;
            z-index: 9999;
            min-width: 160px;
            max-width: 280px;
            text-align: left;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.3);
        }
        .vote-tooltip:hover::after {
            opacity: 1;
            pointer-events: auto;
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <?php
}
add_action('wp_head', 'bcm_output_inline_assets', 100);

add_filter('acf/settings/save_json', fn($path) => plugin_dir_path(__FILE__) . 'acf-json');
add_filter('acf/settings/load_json', fn($paths) => array_merge($paths, [plugin_dir_path(__FILE__) . 'acf-json']));
add_action('wp_enqueue_scripts', 'bcm_enqueue_assets', 100);