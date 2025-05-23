<?php
/**
 * Plugin Name: Bylaw Clause Manager
 * Text Domain: bylaw-clause-manager
 * Description: Manage nested, trackable bylaws with tagging, filtering, recursive rendering, anchors, and Select2 filtering.
 * Version: 1.0.27
 * Author: greghacke
 * Author URI: https://www.owbn.net
 * License: GPL-2.0-or-later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bylaw-clause-manager
 * Domain Path: /languages
 * GitHub Plugin URI: https://github.com/One-World-By-Night/bylaw_clause_manager
 * GitHub Branch: main
 */

// Ensure ACF is available before proceeding
if (!function_exists('acf_add_local_field_group')) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p><strong>Bylaw Clause Manager</strong> requires the <a href="https://wordpress.org/plugins/advanced-custom-fields/">Advanced Custom Fields</a> plugin to be installed and activated.</p></div>';
    });
    return;
}

// Hook ACF JSON path
add_filter('acf/settings/save_json', fn($path) => plugin_dir_path(__FILE__) . 'acf-json');
add_filter('acf/settings/load_json', fn($paths) => array_merge($paths, [plugin_dir_path(__FILE__) . 'acf-json']));


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
        'menu_icon' => 'dashicons-book-alt',
    ]);
}
add_action('init', 'bcm_register_bylaw_clause_cpt');

add_action('current_screen', function($screen) {
    if (!function_exists('acf_get_field_group')) {
        return;
    }

    // Force loading the ACF field group for post list screen
    if (is_admin() && $screen->post_type === 'bylaw_clause') {
        acf_get_field_groups(); // Forces loading of JSON
    }
});



function bcm_fix_clause_parents() {
    $posts = get_posts([
        'post_type' => 'bylaw_clause',
        'numberposts' => -1,
        'post_status' => ['draft', 'publish', 'pending', 'future', 'private'],
        'meta_key' => 'section_id',
    ]);

    // Build a lookup of section_id => post ID
    $id_map = [];
    foreach ($posts as $post) {
        $id_map[get_field('section_id', $post->ID)] = $post->ID;
    }

    $updated = 0;
    foreach ($posts as $post) {
        $parent_section = get_post_meta($post->ID, 'parent', true); // from import metadata
        if (!$parent_section || !isset($id_map[$parent_section])) {
            continue;
        }

        $target_parent_id = $id_map[$parent_section];
        $current_parent_id = get_field('parent_clause', $post->ID);

        if ((int) $current_parent_id !== (int) $target_parent_id) {
            update_field('field_parent_clause', $target_parent_id, $post->ID);
            $updated++;
        }
    }

    echo '<div class="notice notice-success"><p>' . esc_html("✅ $updated parent clauses updated.") . '</p></div>';
}

add_filter('manage_bylaw_clause_posts_columns', function($columns) {
    $new = [];
    foreach ($columns as $key => $label) {
        $new[$key] = $label;
        if ($key === 'title') {
            $new['bylaw_group'] = 'Bylaw Group';
            $new['parent_clause'] = 'Parent Clause';
            $new['tags'] = 'Tags';
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
    } elseif ($column === 'tags') {
        echo '<pre>';
        print_r([
            'meta'        => get_post_meta($post_id, 'tags', true),
            'acf_field'   => get_field('tags', $post_id),
            'meta_keys'   => get_post_meta($post_id),
        ]);
        echo '</pre>';
    }

    // ✅ Add this block to ALL rows (regardless of column)
    echo wp_kses_post(sprintf(
        '<div class="bcm-quickedit-data" 
            data-id="%d" 
            data-bcm-group="%s" 
            data-bcm-parent="%d" 
            data-bcm-tags="%s"></div>',
        $post_id,
        esc_attr(get_field('bylaw_group', $post_id) ?: ''),
        (int) get_field('parent_clause', $post_id),
        esc_attr(get_field('tags', $post_id) ?: '')
    ));
}, 10, 2);

add_filter('manage_edit-bylaw_clause_sortable_columns', function($columns) {
    $columns['bylaw_group'] = 'bylaw_group';
    $columns['parent_clause'] = 'parent_clause';
    $columns['tags'] = 'tags';
    return $columns;
});

// Sanitize filter values before the hook runs
$nonce_raw   = filter_input(INPUT_GET, 'bcm_filter_nonce_field', FILTER_UNSAFE_RAW);
$group_raw   = filter_input(INPUT_GET, 'bylaw_group', FILTER_UNSAFE_RAW);
$title_raw   = filter_input(INPUT_GET, 'bcm_title_filter', FILTER_UNSAFE_RAW);

$nonce       = is_string($nonce_raw) ? sanitize_text_field(wp_unslash($nonce_raw)) : '';
$group       = is_string($group_raw) ? sanitize_text_field(wp_unslash($group_raw)) : '';
$title       = is_string($title_raw) ? sanitize_text_field(wp_unslash($title_raw)) : '';

add_action('pre_get_posts', function($query) use ($nonce, $group, $title) {
    if (!is_admin() || !$query->is_main_query()) {
        return;
    }

    $orderby = $query->get('orderby');

    if ($orderby === 'bylaw_group') {
        $query->set('meta_key', 'bylaw_group');
        $query->set('orderby', 'meta_value');
    }

    if ($orderby === 'parent_clause') {
        $query->set('meta_key', 'parent_clause');
        $query->set('orderby', 'meta_value');
    }

    if (
        $query->get('post_type') === 'bylaw_clause' &&
        !empty($nonce) &&
        wp_verify_nonce($nonce, 'bcm_filter_nonce')
    ) {
        if (!empty($group)) {
            $query->set('meta_query', [[
                'key' => 'bylaw_group',
                'value' => $group,
                'compare' => '='
            ]]);
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

add_filter('posts_search', function($search, $wp_query) {
    global $wpdb;
    if (!is_admin() || !$wp_query->is_main_query()) return $search;
    if ($wp_query->get('post_type') !== 'bylaw_clause') return $search;

    $input = $wp_query->query_vars['s'] ?? '';
    if ($input === '') return $search;

    $like = $wpdb->esc_like($input) . '%'; // Note: trailing %, no leading %
    return " AND {$wpdb->posts}.post_title LIKE '{$like}' ";
}, 10, 2);

add_action('restrict_manage_posts', function() {
    global $typenow;

    // Apply only to 'bylaw_clause' post type
    if ($typenow !== 'bylaw_clause') {
        return;
    }

    // Output nonce field for filter validation
    wp_nonce_field('bcm_filter_nonce', 'bcm_filter_nonce_field');

    // Sanitize selected group from GET
    $selected_raw = $_GET['bylaw_group'] ?? '';
    $selected_group = sanitize_text_field(wp_unslash($selected_raw));

    // Define group options
    $options = [
        '' => 'All Bylaw Groups',
        'character' => 'Character',
        'council' => 'Council',
        'coordinator' => 'Coordinator'
    ];

    // Render select dropdown
    echo '<select name="bylaw_group">';
    foreach ($options as $value => $label) {
        printf(
            '<option value="%s"%s>%s</option>',
            esc_attr($value),
            selected($selected_group, $value, false),
            esc_html($label)
        );
    }
    echo '</select>';

    // Sanitize title filter input
    $raw_title_filter = $_GET['bcm_title_filter'] ?? '';
    $title_filter = sanitize_text_field(wp_unslash($raw_title_filter));

    // Render title input
    printf(
        '<input type="text" name="bcm_title_filter" placeholder="%s" value="%s" style="margin-left: 10px;" />',
        esc_attr__('Filter Title', 'bylaw-clause-manager'),
        esc_attr($title_filter)
    );
});

function bcm_render_bylaw_tree($parent_id = 0, $depth = 0, $group = null) {
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

    // Sort by sequence intelligently
    usort($clauses, function($a, $b) {
        return bcm_sequence_to_int(get_field('sequence', $a->ID)) <=> bcm_sequence_to_int(get_field('sequence', $b->ID));
    });

    if (!$clauses) return;

    foreach ($clauses as $clause) {
        $section   = get_field('section_id', $clause->ID);
        $content   = $clause->post_content;
        $tags      = get_field('tags', $clause->ID);
        $parent    = get_field('parent_clause', $clause->ID);
        $vote_marker = bcm_generate_vote_tooltip($clause->ID);

        if ((int)$clause->ID === (int)$parent) continue;

        // Tag-based classes
        $class_string = '';
        if (!empty($tags)) {
            $tag_array = array_map('trim', explode(',', strtolower($tags)));
            $class_string = implode(' ', array_map('sanitize_html_class', $tag_array));
        }

        // Build the clause HTML
        $anchor_id = sanitize_title($section);
        $margin    = 20 * (int)$depth;

        echo '<div class="bylaw-clause ' . esc_attr($class_string) . '" id="clause-' . esc_attr($anchor_id) . '" data-id="' . esc_attr($clause->ID) . '" data-parent="' . esc_attr($parent ?: 0) . '" style="margin-left:' . esc_attr($margin) . 'px;">';

        echo '  <div class="bylaw-label-wrap">';
        echo '    <span class="bylaw-label-number">' . esc_html($section) . '.</span>';
        echo '    <div class="bylaw-label-text">' . wp_kses_post(apply_filters('the_content', $content)) . '</div>';
        echo '    ' . wp_kses_post($vote_marker);
        echo '  </div>'; // Close bylaw-label-wrap

        echo '</div>'; // Close bylaw-clause

        bcm_render_bylaw_tree($clause->ID, $depth + 1, $group);
    }
}

function bcm_generate_vote_tooltip($clause_id) {
    $vote_date = get_field('vote_date', $clause_id);
    $vote_ref  = get_field('vote_reference', $clause_id);
    $vote_url  = get_field('vote_url', $clause_id);

    if (!$vote_date && !$vote_ref && !$vote_url) return '';

    $tooltip_parts = [];

    if ($vote_date) $tooltip_parts[] = 'Vote Date: ' . esc_html($vote_date);
    if ($vote_ref)  $tooltip_parts[] = 'Reference: ' . esc_html($vote_ref);
    if ($vote_url) {
        $tooltip_parts[] = '<a href="' . esc_url($vote_url) . '" target="_blank" rel="noopener noreferrer">View Details</a>';
    }

    return '<span class="vote-tooltip"><sup>📜</sup><div class="tooltip-content">' . implode(' | ', $tooltip_parts) . '</div></span>';
}

function bcm_sequence_to_int($seq) {
    if (!$seq) return 999999; // fallback for blank

    $seq = trim($seq);
    $lower = strtolower($seq);

    // Numeric
    if (is_numeric($seq)) {
        return (int)$seq;
    }

    // Letter a–z or A–Z
    if (preg_match('/^[a-zA-Z]$/', $seq)) {
        return ord(strtolower($seq)) - ord('a') + 1000;
    }

    // Roman numerals (i–xx)
    $roman_map = [
        'i'=>1,'ii'=>2,'iii'=>3,'iv'=>4,'v'=>5,
        'vi'=>6,'vii'=>7,'viii'=>8,'ix'=>9,'x'=>10,
        'xi'=>11,'xii'=>12,'xiii'=>13,'xiv'=>14,'xv'=>15,
        'xvi'=>16,'xvii'=>17,'xviii'=>18,'xix'=>19,'xx'=>20
    ];
    if (isset($roman_map[$lower])) {
        return 2000 + $roman_map[$lower];
    }

    // Fallback hash
    return 900000 + crc32($seq);
}

add_shortcode('render_bylaws', function($atts) {
    $atts = shortcode_atts([ 'group' => null ], $atts);
    ob_start();

    // Query the latest modified clause in this group (or all)
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
        echo '<div class="bcm-updated"><strong>Last Updated: ' . esc_html($latest_time) . '</strong></div>';
    }

    echo '<div id="bcm-toolbar">
            <label for="bcm-tag-select">Filter by Tag:</label>
            <select id="bcm-tag-select" multiple style="width: 300px;"></select>
            <button type="button" onclick="bcmClearFilters()">Clear Filters</button>
            <button type="button" onclick="window.print()">Print / Export PDF</button>
          </div>';

    bcm_render_bylaw_tree(0, 0, $atts['group']);
    echo '</div>';
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
    $preview = mb_substr(wp_strip_all_tags($post->post_content), 0, 25);
    if (mb_strlen($post->post_content) > 25) {
        $preview .= '…';
    }

    return trim($title . ' ' . $preview);
}, 10, 4);

function bcm_enqueue_assets() {
    $plugin_url = plugins_url('', __FILE__);

    // Load local Select2 assets
    wp_enqueue_style(
        'select2-style',
        plugins_url('css/select2.min.css', __FILE__),
        [],
        '4.1.0'
    );

    wp_enqueue_script(
        'select2',
        plugins_url('js/select2.min.js', __FILE__),
        ['jquery'],
        '4.1.0',
        true
    );

    // Load your custom filter script
    wp_enqueue_script(
        'bcm-filter',
        $plugin_url . '/js/filter.js',
        ['jquery', 'select2'],
        filemtime(plugin_dir_path(__FILE__) . 'js/filter.js'),
        true
    );
}

add_action('admin_enqueue_scripts', function($hook) {
    global $typenow;

    // Only enqueue on the Bylaw Clause list page
    if ($hook !== 'edit.php' || $typenow !== 'bylaw_clause') return;

    $plugin_url = plugins_url('', __FILE__);

    // Select2 assets
    wp_enqueue_style(
        'select2-admin-style',
        $plugin_url . '/css/select2.min.css',
        [],
        '4.1.0'
    );

    wp_enqueue_script(
        'select2-admin-script',
        $plugin_url . '/js/select2.min.js',
        ['jquery'],
        '4.1.0',
        true
    );

    // ✅ This is the part that fixes the missing script
    wp_enqueue_script(
        'bcm-filter',
        plugins_url('js/filter.js', __FILE__), // Points to correct plugin-relative URL
        ['jquery', 'select2-admin-script'],
        filemtime(plugin_dir_path(__FILE__) . 'js/filter.js'),
        true
    );

    // Inline script to init Select2 on quick edit dropdowns
    wp_add_inline_script('select2-admin-script', '
        jQuery(function($) {
            function initSelect2QuickEdit() {
                $(".bcm-select2").each(function() {
                    if ($.fn.select2) {
                        if ($(this).hasClass("select2-hidden-accessible")) {
                            $(this).select2("destroy");
                        }
                        $(this).select2({ width: "100%" });
                    }
                });
            }

            $(document).on("click", ".editinline", function() {
                setTimeout(initSelect2QuickEdit, 100);
            });

            $(document).ajaxSuccess(function(e, xhr, settings) {
                if (settings.data && settings.data.includes("action=inline-save")) {
                    setTimeout(initSelect2QuickEdit, 200);
                }
            });
        });
    ');
});

function bcm_output_inline_assets() {
    $plugin_url = plugins_url('', __FILE__);

    // Register and enqueue local Select2 CSS
    wp_register_style(
        'select2',
        $plugin_url . '/css/select2.min.css',
        [],
        '4.1.0'
    );
    wp_enqueue_style('select2');

    // Register and enqueue local Select2 JS
    wp_register_script(
        'select2',
        $plugin_url . '/js/select2.min.js',
        ['jquery'],
        '4.1.0',
        true
    );
    wp_enqueue_script('select2');

    // Add inline custom styles (plugin-specific)
    wp_add_inline_style('select2', bcm_get_inline_styles());
}

function bcm_get_inline_styles() {
    $css  = "#bcm-toolbar {\n";
    $css .= "  margin-bottom: 1em;\n";
    $css .= "  display: flex;\n";
    $css .= "  align-items: center;\n";
    $css .= "  gap: 0.75em;\n";
    $css .= "  flex-wrap: wrap;\n";
    $css .= "}\n";

    $css .= "#bcm-toolbar button {\n";
    $css .= "  padding: 4px 10px;\n";
    $css .= "  font-size: 0.9em;\n";
    $css .= "  border-radius: 4px;\n";
    $css .= "  border: 1px solid #ccc;\n";
    $css .= "  background-color: #f5f5f5;\n";
    $css .= "  cursor: pointer;\n";
    $css .= "  transition: all 0.2s ease-in-out;\n";
    $css .= "  line-height: 1.2;\n";
    $css .= "}\n";

    $css .= "#bcm-toolbar button:hover {\n";
    $css .= "  background-color: #e6e6e6;\n";
    $css .= "  border-color: #999;\n";
    $css .= "}\n";

    $css .= "#bcm-toolbar label {\n";
    $css .= "  margin-right: 0.5em;\n";
    $css .= "  font-size: 0.95em;\n";
    $css .= "  font-weight: 500;\n";
    $css .= "}\n";

    // CLAUSE CONTAINERS
    $css .= ".bylaw-clause {\n";
    $css .= "  display: block;\n";
    $css .= "  width: 100%;\n";
    $css .= "  clear: both;\n";
    $css .= "  margin-bottom: 0.25em;\n";
    $css .= "}\n";

    $css .= ".bylaw-clause strong {\n";
    $css .= "  display: block;\n";
    $css .= "  font-size: 1.1em;\n";
    $css .= "  margin-bottom: 0.1em;\n";
    $css .= "}\n";

    $css .= ".bylaw-content {\n";
    $css .= "  margin-left: 1em;\n";
    $css .= "  font-size: 0.95em;\n";
    $css .= "  line-height: 1.1;\n";
    $css .= "}\n";

    // LABELS
    $css .= ".bylaw-label-wrap {\n";
    $css .= "  display: flex;\n"; 
    $css .= "  align-items: flex-start;\n";
    $css .= "  gap: 1em;\n";
    $css .= "  margin-bottom: 0.25em;\n";
    $css .= "  line-height: 1.1;\n";
    $css .= "  flex-wrap: wrap;\n";
    $css .= "}\n";

    $css .= ".bylaw-label-number {\n";
    $css .= "  font-weight: normal;\n";
    $css .= "  white-space: nowrap;\n";
    $css .= "  font-size: 1em;\n";
    $css .= "  flex-shrink: 0;\n";
    $css .= "}\n";

    $css .= ".bylaw-label-text {\n";
    $css .= "  word-break: break-word;\n";
    $css .= "  line-height: 1.1;\n";
    $css .= "  font-size: 0.95em;\n";
    $css .= "  flex: 1;\n";
    $css .= "  min-width: 0;\n";
    $css .= "}\n";

    $css .= ".bylaw-content p {\n";
    $css .= "  margin: 0.25em 0;\n";
    $css .= "}\n";

    // TOOLTIP
    $css .= ".vote-tooltip {\n";
    $css .= "  position: relative;\n";
    $css .= "  display: inline-block;\n";
    $css .= "  cursor: help;\n";
    $css .= "  font-size: 0.85em;\n";
    $css .= "  color: #555;\n";
    $css .= "  margin-left: 4px;\n";
    $css .= "}\n";

    $css .= ".vote-tooltip .tooltip-content {\n";
    $css .= "  display: none;\n";
    $css .= "  position: absolute;\n";
    $css .= "  top: 120%;\n";
    $css .= "  left: 50%;\n";
    $css .= "  transform: translateX(-50%);\n";
    $css .= "  background-color: #333;\n";
    $css .= "  color: #fff;\n";
    $css .= "  padding: 6px 8px;\n";
    $css .= "  border-radius: 4px;\n";
    $css .= "  font-size: 0.75em;\n";
    $css .= "  z-index: 9999;\n";
    $css .= "  min-width: 160px;\n";
    $css .= "  max-width: 280px;\n";
    $css .= "  text-align: left;\n";
    $css .= "  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.3);\n";
    $css .= "  white-space: normal;\n";
    $css .= "}\n";

    $css .= ".vote-tooltip:hover .tooltip-content {\n";
    $css .= "  display: block;\n";
    $css .= "}\n";

    $css .= ".tooltip-content a {\n";
    $css .= "  color: #9cf;\n";
    $css .= "  text-decoration: underline;\n";
    $css .= "}\n";

    $css .= ".tooltip-content a:hover {\n";
    $css .= "  color: #cde;\n";
    $css .= "}\n";

    return $css;
}

add_action('wp_enqueue_scripts', 'bcm_output_inline_assets', 100);

function bcm_enqueue_print_styles() {
    // Adjust the path as needed
    $theme_print_css = get_stylesheet_directory_uri() . '/print.css';

    wp_enqueue_style('bcm-theme-print', $theme_print_css, [], null, 'print');
}
add_action('wp_enqueue_scripts', 'bcm_enqueue_print_styles');
add_action('wp_enqueue_scripts', 'bcm_enqueue_assets', 100);

add_action('quick_edit_custom_box', function($column, $post_type) {
    if ($post_type !== 'bylaw_clause') return;

    // ✅ Only output ONCE — doesn't matter which column triggered it
    static $printed = false;
    if ($printed) return;
    $printed = true;
    ?>
    <fieldset class="inline-edit-col-right inline-custom-meta">
        <div class="inline-edit-col">
            <?php wp_nonce_field('bcm_qe_save', 'bcm_qe_nonce'); ?>

            <!-- Bylaw Group -->
            <?php $field = get_field_object('field_bylaw_group'); ?>
            <?php if ($field && !empty($field['choices'])): ?>
                <label>
                    <span class="title"><?php esc_html_e('Bylaw Group', 'bylaw-clause-manager'); ?></span>
                    <select name="bcm_qe_bylaw_group" class="bcm-qe-bylaw-group">
                        <option value=""><?php esc_html_e('— Select —', 'bylaw-clause-manager'); ?></option>
                        <?php foreach ($field['choices'] as $val => $label): ?>
                            <option value="<?php echo esc_attr($val); ?>"><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            <?php endif; ?>

            <!-- Parent Clause -->
            <?php
            $clauses = get_posts([
                'post_type' => 'bylaw_clause',
                'posts_per_page' => -1,
                'orderby' => 'title',
                'order' => 'ASC',
                'post_status' => ['publish', 'draft', 'pending', 'private'],
            ]);
            ?>
            <label style="margin-top: 10px; display: block;">
                <span class="title"><?php esc_html_e('Parent Clause', 'bylaw-clause-manager'); ?></span>
                <select name="bcm_qe_parent_clause" class="bcm-qe-parent-clause bcm-select2" style="width: 100%;">
                    <option value=""><?php esc_html_e('— Select —', 'bylaw-clause-manager'); ?></option>
                    <?php foreach ($clauses as $c):
                        $sid = get_field('section_id', $c->ID);
                        $title = get_the_title($c->ID);
                        $content = wp_strip_all_tags($c->post_content);
                        $preview = mb_substr($content, 0, 25);
                        if (mb_strlen($content) > 25) {
                            $preview .= '…';
                        }
                        $label = trim("{$title} {$preview}");
                        ?>
                        <option value="<?php echo esc_attr($c->ID); ?>"><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <!-- Tags -->
            <label style="margin-top: 10px; display: block;">
                <span class="title"><?php esc_html_e('Tags (comma-separated)', 'bylaw-clause-manager'); ?></span>
                <input type="text" name="bcm_qe_tags" class="bcm-qe-tags" style="width: 100%;" />
            </label>
        </div>
    </fieldset>
    <?php
}, 10, 2);

add_action('save_post_bylaw_clause', function($post_id) {
    // Bail on autosave or if not a valid post type
    if (
        defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ||
        get_post_type($post_id) !== 'bylaw_clause'
    ) {
        return;
    }

    // Bail if this is not from our quick edit UI
    if (
        !isset($_POST['bcm_qe_nonce']) ||
        !wp_verify_nonce(wp_unslash($_POST['bcm_qe_nonce']), 'bcm_qe_save')
    ) {
        return;
    }

    // Capability check
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    // Save Bylaw Group using dynamic ACF choices
    if (isset($_POST['bcm_qe_bylaw_group'])) {
        $group_raw = wp_unslash($_POST['bcm_qe_bylaw_group']);
        $group = sanitize_text_field($group_raw);
        $field = get_field_object('field_bylaw_group');
        if ($field && isset($field['choices'][$group])) {
            update_field('field_bylaw_group', $group, $post_id);
        }
    }

    // Save Parent Clause if valid
    if (isset($_POST['bcm_qe_parent_clause'])) {
        $pid_raw = wp_unslash($_POST['bcm_qe_parent_clause']);
        $pid = absint($pid_raw);
        if ($pid && get_post_type($pid) === 'bylaw_clause') {
            update_field('field_parent_clause', $pid, $post_id);
        } else {
            update_field('field_parent_clause', null, $post_id);
        }
    }

    // Save Tags
    if (isset($_POST['bcm_qe_tags'])) {
        $tags_raw = wp_unslash($_POST['bcm_qe_tags']);
        $tags = sanitize_text_field($tags_raw);
        update_field('field_tags', $tags, $post_id);
    }

});