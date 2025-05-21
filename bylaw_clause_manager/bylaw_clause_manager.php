<?php
/**
 * Plugin Name: Bylaw Clause Manager
 * Description: Manage nested, trackable bylaws with tagging, filtering, recursive rendering, anchors, and Select2 filtering.
 * Version: 1.0.24
 * Author: OWBN (Greg H.)
 * Author URI: https://www.owbn.net
 * License: MIT
 * License URI: https://opensource.org/licenses/MIT
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

    echo "<div class='notice notice-success'><p>✅ $updated parent clauses updated.</p></div>";
}

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

    // Handle sortable columns
    if ($orderby === 'bylaw_group') {
        $query->set('meta_key', 'bylaw_group');
        $query->set('orderby', 'meta_value');
    }
    if ($orderby === 'parent_clause') {
        $query->set('meta_key', 'parent_clause');
        $query->set('orderby', 'meta_value');
    }

    if ($query->get('post_type') === 'bylaw_clause') {
        // Filter by Bylaw Group
        if (!empty($_GET['bylaw_group'])) {
            $query->set('meta_query', [[
                'key' => 'bylaw_group',
                'value' => sanitize_text_field($_GET['bylaw_group']),
                'compare' => '='
            ]]);
        }

        // Filter by Title (map bcm_title_filter into s)
        if (!empty($_GET['bcm_title_filter'])) {
            $query->set('s', sanitize_text_field($_GET['bcm_title_filter']));
        }

        // Default sort by title
        if (!$orderby) {
            $query->set('orderby', 'title');
            $query->set('order', 'ASC');
        }
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
    if ($typenow !== 'bylaw_clause') return;

    // Bylaw Group Filter
    $selected = $_GET['bylaw_group'] ?? '';
    $options = [
        '' => 'All Bylaw Groups',
        'character' => 'Character',
        'council' => 'Council',
        'coordinator' => 'Coordinator'
    ];
    echo '<select name="bylaw_group">';
    foreach ($options as $val => $label) {
        printf(
            '<option value="%s"%s>%s</option>',
            esc_attr($val),
            selected($selected, $val, false),
            esc_html($label)
        );
    }
    echo '</select>';

    // Title Filter
    $title_filter = $_GET['bcm_title_filter'] ?? '';
    echo '<input type="text" name="bcm_title_filter" placeholder="Filter Title" value="' . esc_attr($title_filter) . '" style="margin-left: 10px;" />';
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
        'numberposts' => -1,
    ]);

    // Sort by sequence intelligently
    usort($clauses, function($a, $b) {
        return bcm_sequence_to_int(get_field('sequence', $a->ID)) <=> bcm_sequence_to_int(get_field('sequence', $b->ID));
    });

    if (!$clauses) return;

    foreach ($clauses as $clause) {
        $section = get_field('section_id', $clause->ID);
        $content = $clause->post_content;
        $tags = get_field('tags', $clause->ID);
        $parent = get_field('parent_clause', $clause->ID);
        $vote_date = get_field('vote_date', $clause->ID);
        $vote_ref = get_field('vote_reference', $clause->ID);

        if ((int) $clause->ID === (int) $parent) continue;

        // Tag-based classes
        $class_string = '';
        if (!empty($tags)) {
            $tag_array = array_map('trim', explode(',', strtolower($tags)));
            $class_string = implode(' ', array_map('sanitize_html_class', $tag_array));
        }

        // Vote marker
        $vote_marker = bcm_generate_vote_tooltip($clause->ID);

        // Render block
        $anchor_id = sanitize_title($section);
        echo '<div class="bylaw-clause ' . esc_attr($class_string) . '" id="clause-' . esc_attr($anchor_id) . '" data-id="' . esc_attr($clause->ID) . '" data-parent="' . esc_attr($parent ?: 0) . '" style="margin-left:' . (20 * $depth) . 'px;">';

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
    return trim(($section_id ? "{$section_id} " : '') . $post->post_title . ' ' . $preview);
}, 10, 4);

function bcm_enqueue_assets() {
    $plugin_url = plugins_url('', __FILE__);
    wp_enqueue_style('select2-style', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css');
    wp_enqueue_script('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', ['jquery'], null, true);
    wp_enqueue_script('bcm-filter', $plugin_url . '/js/filter.js', ['jquery', 'select2'], false, true);
}

add_action('admin_enqueue_scripts', function($hook) {
    if ($hook !== 'edit.php') return;

    wp_enqueue_style('select2-admin-style', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css');
    wp_enqueue_script('select2-admin-script', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', ['jquery'], null, true);

    wp_add_inline_script('select2-admin-script', <<<JS
    jQuery(function($) {
        function initSelect2QuickEdit() {
            $('.bcm-select2').each(function() {
                if (\$.fn.select2) {
                    // Destroy if already initialized
                    if ($(this).hasClass('select2-hidden-accessible')) {
                        $(this).select2('destroy');
                    }
                    // Re-initialize
                    $(this).select2({ width: '100%' });
                }
            });
        }

        // Run after Quick Edit is opened
        $(document).on('click', '.editinline', function() {
            setTimeout(initSelect2QuickEdit, 100);
        });

        // Also catch after inline save, which might refresh the row
        $(document).ajaxSuccess(function(e, xhr, settings) {
            if (settings.data && settings.data.includes('action=inline-save')) {
                setTimeout(initSelect2QuickEdit, 200);
            }
        });
    });
    JS);
});

function bcm_output_inline_assets() {
    ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <style>
        #bcm-toolbar {
            margin-bottom: 1em;
        }

        #bcm-toolbar button {
            padding: 4px 10px;
            font-size: 0.9em;
            border-radius: 4px;
            border: 1px solid #ccc;
            background-color: #f5f5f5;
            cursor: pointer;
            transition: all 0.2s ease-in-out;
            line-height: 1.2;
        }

        #bcm-toolbar button:hover {
            background-color: #e6e6e6;
            border-color: #999;
        }

        #bcm-toolbar label {
            margin-right: 0.5em;
            font-size: 0.95em;
            font-weight: 500;
        }

        #bcm-toolbar {
            display: flex;
            align-items: center;
            gap: 0.75em;
            flex-wrap: wrap;
        }

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
            line-height: 1.1;
        }

        .bylaw-label-wrap {
            display: flex;
            align-items: flex-start;
            gap: 0.3em;
            flex-wrap: wrap;
            line-height: 1.1;
        }

        .bylaw-label-number {
            font-weight: normal;
            white-space: nowrap;
            font-size: 1em;
        }

        .bylaw-label-text {
            word-break: break-word;
            flex: 1;
            min-width: 0;
            line-height: 1.1;
            font-size: 0.95em;
        }

        .bylaw-content p {
            margin: 0.25em 0;
        }

        .vote-tooltip {
            position: relative;
            display: inline-block;
            cursor: help;
            font-size: 0.85em;
            color: #555;
            margin-left: 4px;
        }

        .vote-tooltip .tooltip-content {
            display: none;
            position: absolute;
            top: 120%;
            left: 50%;
            transform: translateX(-50%);
            background-color: #333;
            color: #fff;
            padding: 6px 8px;
            border-radius: 4px;
            font-size: 0.75em;
            z-index: 9999;
            min-width: 160px;
            max-width: 280px;
            text-align: left;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.3);
            white-space: normal;
        }

        .vote-tooltip:hover .tooltip-content {
            display: block;
        }

        .tooltip-content a {
            color: #9cf;
            text-decoration: underline;
        }

        .tooltip-content a:hover {
            color: #cde;
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <?php
}
add_action('wp_head', 'bcm_output_inline_assets', 100);

function bcm_enqueue_print_styles() {
    // Adjust the path as needed
    $theme_print_css = get_stylesheet_directory_uri() . '/print.css';

    wp_enqueue_style('bcm-theme-print', $theme_print_css, [], null, 'print');
}
add_action('wp_enqueue_scripts', 'bcm_enqueue_print_styles');
add_action('wp_enqueue_scripts', 'bcm_enqueue_assets', 100);

add_action('quick_edit_custom_box', function($column, $post_type) {
    if ($post_type !== 'bylaw_clause') return;

    if (in_array($column, ['bylaw_group', 'parent_clause'], true)) {
        ?>
        <fieldset class="inline-edit-col-right inline-custom-meta">
            <div class="inline-edit-col">

                <?php if ($column === 'bylaw_group'):
                    $field = get_field_object('field_bylaw_group');
                    if ($field && !empty($field['choices'])): ?>
                        <label>
                            <span class="title">Bylaw Group</span>
                            <select name="bcm_qe_bylaw_group" class="bcm-qe-bylaw-group">
                                <option value="">— Select —</option>
                                <?php foreach ($field['choices'] as $val => $label): ?>
                                    <option value="<?= esc_attr($val) ?>"><?= esc_html($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if ($column === 'parent_clause'):
                    $clauses = get_posts([
                        'post_type' => 'bylaw_clause',
                        'posts_per_page' => -1,
                        'orderby' => 'title',
                        'order' => 'ASC',
                    ]);
                    ?>
                    <label style="margin-top: 10px; display: block;">
                        <span class="title">Parent Clause</span>
                        <select name="bcm_qe_parent_clause" class="bcm-qe-parent-clause bcm-select2" style="width: 100%;">
                            <option value="">— Select —</option>
                            <?php foreach ($clauses as $c):
                                $sid = get_field('section_id', $c->ID);
                                $content = strip_tags($c->post_content);
                                $preview = mb_substr($content, 0, 25) . (mb_strlen($content) > 25 ? '…' : '');
                                $label = trim(($sid ? "{$sid} " : '') . get_the_title($c->ID) . ' ' . $preview);
                                ?>
                                <option value="<?= esc_attr($c->ID) ?>"><?= esc_html($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                <?php endif; ?>

            </div>
        </fieldset>
        <?php
    }
}, 10, 2);

add_action('save_post_bylaw_clause', function($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

    // Save Bylaw Group using dynamic ACF choices
    if (isset($_POST['bcm_qe_bylaw_group'])) {
        $group = sanitize_text_field($_POST['bcm_qe_bylaw_group']);
        $field = get_field_object('field_bylaw_group');
        if ($field && isset($field['choices'][$group])) {
            update_field('field_bylaw_group', $group, $post_id);
        }
    }

    // Save Parent Clause if valid
    if (isset($_POST['bcm_qe_parent_clause'])) {
        $pid = absint($_POST['bcm_qe_parent_clause']);
        if ($pid && get_post_type($pid) === 'bylaw_clause') {
            update_field('field_parent_clause', $pid, $post_id);
        } elseif (!$pid) {
            update_field('field_parent_clause', null, $post_id); // clear it if blank
        }
    }
});