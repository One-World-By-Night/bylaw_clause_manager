<?php
/**
 * Plugin Name: Bylaw Clause Manager
 * Text Domain: bylaw-clause-manager
 * Description: Manage nested, trackable bylaws with tagging, filtering, recursive rendering, anchors, and Select2 filtering.
 * Version: 2.0.0
 * Author: greghacke
 * Author URI: https://www.owbn.net
 * License: GPL-2.0-or-later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
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
        'rewrite' => [
            'slug' => 'bylaw-clause/%bylaw_group%',
            'with_front' => false,
        ],
        'supports' => ['title', 'editor', 'revisions'],
        'show_in_rest' => true,
        'menu_icon' => 'dashicons-book-alt',
    ]);
}
add_action('init', 'bcm_register_bylaw_clause_cpt');

function bcm_custom_bylaw_permalink($post_link, $post) {
    if ($post->post_type !== 'bylaw_clause') return $post_link;

    $group = get_post_meta($post->ID, 'bylaw_group', true);
    $group = $group ? sanitize_title($group) : 'uncategorized';

    $slug = sanitize_title($post->post_title);

    return home_url("/bylaw-clause/{$group}/{$slug}/");
}
add_filter('post_type_link', 'bcm_custom_bylaw_permalink', 10, 2);

function bcm_custom_rewrite_rules() {
    add_rewrite_rule(
        '^bylaw-clause/([^/]+)/([^/]+)/?$',
        'index.php?post_type=bylaw_clause&name=$matches[2]',
        'top'
    );
}
add_action('init', 'bcm_custom_rewrite_rules');

//  bcm_get_bylaw_groups: Retrieves the Bylaw Groups from the options table
// This function returns an array of Bylaw Groups
function bcm_get_bylaw_groups() {
    $groups = get_option('bcm_bylaw_groups', []);
    return (is_array($groups) && !empty($groups)) ? $groups : [
        'character'   => 'Character',
        'council'     => 'Council',
        'coordinator' => 'Coordinator',
    ];
}

// Admin menu for Bylaw Groups
// This adds a submenu page under the Bylaw Clause post type menu
add_action('admin_menu', function() {
    add_submenu_page(
        'edit.php?post_type=bylaw_clause',
        'Manage Bylaw Groups',
        'Bylaw Groups',
        'manage_options',
        'bcm_bylaw_groups',
        'bcm_render_bylaw_group_settings_page'
    );
});

// bcm_fix_clause_parents: Fixes the parent clause IDs for all bylaw clauses
// This uses post titles to determine the hierarchy
// It assumes that the post titles are formatted as "parent_child" (e.g., "Clause_1_1")
// This function should be run manually when needed
function bcm_fix_clause_parents() {
    $posts = get_posts([
        'post_type'   => 'bylaw_clause',
        'numberposts' => -1,
        'post_status' => ['draft', 'publish', 'pending', 'future', 'private'],
    ]);

    // Map post titles to IDs (normalized to lowercase for matching)
    $title_map = [];
    foreach ($posts as $post) {
        $title_map[strtolower($post->post_title)] = $post->ID;
    }

    $updated = 0;
    foreach ($posts as $post) {
        $title = $post->post_title;
        $parts = explode('_', $title);

        if (count($parts) <= 1) continue; // No parent

        array_pop($parts); // Remove last part to get parent title
        $parent_title = strtolower(implode('_', $parts));

        if (isset($title_map[$parent_title])) {
            $parent_id = $title_map[$parent_title];
            $current = get_post_meta($post->ID, 'parent_clause', true);

            if ((int) $current !== (int) $parent_id) {
                update_post_meta($post->ID, 'parent_clause', $parent_id);
                $updated++;
            }
        }
    }

    echo '<div class="notice notice-success"><p>' . esc_html("✅ $updated parent clauses updated based on post title hierarchy.") . '</p></div>';
}

// add_filter for manage_bylaw_clause_posts_custom_column
// This filter is used to add the custom columns to the Bylaw Clause post type admin list
add_filter('manage_bylaw_clause_posts_columns', function($columns) {
    $new = [];
    foreach ($columns as $key => $label) {
        $new[$key] = $label;
        if ($key === 'title') {
            $new['bylaw_group'] = 'Bylaw Group';
            $new['parent_clause'] = 'Parent Clause';
            $new['short_content'] = 'Content Preview';
        }
    }
    return $new;
});

// add_action for manage_bylaw_clause_posts_custom_column
// This action is used to populate the custom columns in the Bylaw Clause post type admin list
// It retrieves the custom field values and displays them in the respective columns
// It also outputs a div with the custom field values for use in Quick Edit
add_action('manage_bylaw_clause_posts_custom_column', function($column, $post_id) {
    $group     = get_post_meta($post_id, 'bylaw_group', true);
    $parent_id = get_post_meta($post_id, 'parent_clause', true);
    $tags      = get_post_meta($post_id, 'tags', true);

    if ($column === 'bylaw_group') {
        echo esc_html(ucfirst($group) ?: '—');

    } elseif ($column === 'parent_clause') {
        echo $parent_id ? esc_html(get_the_title($parent_id)) : '—';
    } elseif ($column === 'short_content') {
        $content = get_post_field('post_content', $post_id);
        $preview = wp_strip_all_tags($content);
        echo esc_html(mb_strimwidth($preview, 0, 60, '…')); // Trim to ~60 characters
    }

    // ✅ Keep this so Quick Edit still works
    echo wp_kses_post(sprintf(
        '<div class="bcm-quickedit-data" 
            data-id="%d" 
            data-bcm-group="%s" 
            data-bcm-parent="%d" 
            data-bcm-tags="%s"></div>',
        $post_id,
        esc_attr($group),
        (int) $parent_id,
        esc_attr($tags)
    ));
}, 10, 2);

add_action('admin_head', function() {
    echo '<style>
        .column-short_content {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 300px;
        }
    </style>';
});

// add filter for manage_edit-bylaw_clause_sortable_columns
// This filter is used to make the custom columns sortable in the Bylaw Clause post type admin list
add_filter('manage_edit-bylaw_clause_sortable_columns', function($columns) {
    $columns['bylaw_group'] = 'bylaw_group';
    $columns['parent_clause'] = 'parent_clause';
    $columns['short_content'] = 'short_content';
    return $columns;
});

// Sanitize filter values before the hook runs
$nonce_raw   = filter_input(INPUT_GET, 'bcm_filter_nonce_field', FILTER_UNSAFE_RAW);
$group_raw   = filter_input(INPUT_GET, 'bylaw_group', FILTER_UNSAFE_RAW);
$title_raw   = filter_input(INPUT_GET, 'bcm_title_filter', FILTER_UNSAFE_RAW);

$nonce       = is_string($nonce_raw) ? sanitize_text_field(wp_unslash($nonce_raw)) : '';
$group       = is_string($group_raw) ? sanitize_text_field(wp_unslash($group_raw)) : '';
$title       = is_string($title_raw) ? sanitize_text_field(wp_unslash($title_raw)) : '';

// add_action for pre_get_posts
// This action is used to modify the main query for the Bylaw Clause post type
// It sets the meta query based on the filter values and modifies the orderby clause
// It also sets the default orderby to title if no other orderby is set
add_action('pre_get_posts', function($query) use ($nonce, $group, $title) {
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

// Restrict search to post_title starting with the input (prefix match only)
// Prevents partial matches mid-string (e.g. avoids "10_B_iv_1_A_ii" matching "1_A_ii")
add_filter('posts_search', function($search, $wp_query) {
    global $wpdb;

    if (!is_admin() || !$wp_query->is_main_query()) return $search;
    if ($wp_query->get('post_type') !== 'bylaw_clause') return $search;

    $input = $wp_query->query_vars['s'] ?? '';
    if ($input === '') return $search;

    $like = $wpdb->esc_like($input) . '%'; // Match only prefixes
    return " AND {$wpdb->posts}.post_title LIKE '{$like}' ";
}, 10, 2);


// Render admin filter controls for Bylaw Group (from stored options) and Title Prefix
add_action('restrict_manage_posts', function() {
    global $typenow;

    if ($typenow !== 'bylaw_clause') return;

    // Output nonce for filter validation
    wp_nonce_field('bcm_filter_nonce', 'bcm_filter_nonce_field');

    // Initialize safe defaults
    $selected_group = '';
    $title_filter   = '';

    // Validate and sanitize inputs only if nonce is present and valid
    if (
        isset($_GET['bcm_filter_nonce_field']) &&
        wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['bcm_filter_nonce_field'])), 'bcm_filter_nonce')
    ) {
        $selected_group = isset($_GET['bylaw_group']) ? sanitize_text_field(wp_unslash($_GET['bylaw_group'])) : '';
        $title_filter   = isset($_GET['bcm_title_filter']) ? sanitize_text_field(wp_unslash($_GET['bcm_title_filter'])) : '';
    }

    // Load Bylaw Groups from options
    $groups = get_option('bcm_bylaw_groups', null);

    // Fallback if not yet saved — ensures the dropdown is always populated
    if (!is_array($groups)) {
        $groups = [
            'character'   => 'Character',
            'council'     => 'Council',
            'coordinator' => 'Coordinator',
        ];
    }

    // Bylaw Group dropdown
    echo '<select name="bylaw_group">';
    echo '<option value="">' . esc_html__('All Bylaw Groups', 'bylaw-clause-manager') . '</option>';
    foreach ($groups as $val => $label) {
        printf(
            '<option value="%s"%s>%s</option>',
            esc_attr($val),
            selected($selected_group, $val, false),
            esc_html($label)
        );
    }
    echo '</select>';

    // Title prefix filter input
    printf(
        '<input type="text" name="bcm_title_filter" placeholder="%s" value="%s" style="margin-left: 10px;" />',
        esc_attr__('Filter Title', 'bylaw-clause-manager'),
        esc_attr($title_filter)
    );
});

// bcm_render_bylaw_group_settings_page: Renders the Bylaw Group settings page
// This function handles the display and processing of the Bylaw Group settings page
// It includes a form for adding/editing Bylaw Groups, and handles saving the data
function bcm_render_bylaw_group_settings_page() {
    // Save changes if posted
    if (!empty($_POST['bcm_groups_keys']) && check_admin_referer('bcm_save_groups', 'bcm_nonce')) {
        $keys = array_map('sanitize_key', $_POST['bcm_groups_keys']);
        $labels = isset($_POST['bcm_groups_labels'])
            ? array_map('sanitize_text_field', wp_unslash($_POST['bcm_groups_labels']))
            : [];

        $combined = [];
        foreach ($keys as $i => $key) {
            if ($key !== '' && isset($labels[$i]) && $labels[$i] !== '') {
                $combined[$key] = $labels[$i];
            }
        }

        update_option('bcm_bylaw_groups', $combined);
        echo '<div class="updated"><p>Bylaw groups updated.</p></div>';
    }

    // Load saved groups
    $groups = bcm_get_bylaw_groups();

    ?>
    <div class="wrap">
        <h1>Manage Bylaw Groups</h1>
        <form method="post">
            <?php wp_nonce_field('bcm_save_groups', 'bcm_nonce'); ?>
            <table id="bcm-group-table" class="widefat fixed striped">
                <thead>
                    <tr>
                        <th>Group Key</th>
                        <th>Label</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($groups)): ?>
                        <?php foreach ($groups as $key => $label): ?>
                            <tr>
                                <td><input type="text" name="bcm_groups_keys[]" value="<?php echo esc_attr($key); ?>" required></td>
                                <td><input type="text" name="bcm_groups_labels[]" value="<?php echo esc_attr($label); ?>" required></td>
                                <td><button type="button" class="bcm-remove-group">Remove</button></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td><input type="text" name="bcm_groups_keys[]" value="" placeholder="e.g. character" required></td>
                            <td><input type="text" name="bcm_groups_labels[]" value="" placeholder="e.g. Character" required></td>
                            <td><button type="button" class="bcm-remove-group">Remove</button></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <p>
                <button type="button" class="button" id="bcm-add-group">Add Group</button>
            </p>

            <?php submit_button('Save Groups'); ?>
        </form>
    </div>
    <?php
}

// admin_enqueue_scripts: Enqueues scripts and styles for the Bylaw Group settings page
add_action('admin_enqueue_scripts', function($hook) {
    // Always load Select2 and filter.js for Bylaw Clauses + Manage Bylaw Groups
    if (
        $hook !== 'edit.php' &&
        $hook !== 'bylaw-clause_page_bcm_bylaw_groups' &&
        strpos($hook, 'bcm_bylaw_groups') === false
    ) return;

    wp_enqueue_style(
        'select2',
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

    wp_enqueue_script(
        'bcm-filter-v2',
        plugins_url('js/filter.js', __FILE__),
        ['jquery', 'select2'],
        filemtime(plugin_dir_path(__FILE__) . 'js/filter.js'),
        true
    );
});


// Quick Edit: Adds custom fields to the Quick Edit box for Bylaw Clause post type
// This includes fields for tags and parent clause
// It also handles saving the data when the Quick Edit form is submitted
// The fields are added to the Quick Edit box using the 'quick_edit_custom_box' action
add_action('quick_edit_custom_box', function($column, $post_type) {
    if ($post_type !== 'bylaw_clause') return;
    if (!in_array($column, ['tags', 'parent_clause'], true)) return;

    static $printed = false;
    if ($printed) return;
    $printed = true;

    // Get all bylaw groups (with fallback)
    $groups = bcm_get_bylaw_groups();

    // Get all clauses with their group
    $all_clauses = get_posts([
        'post_type'      => 'bylaw_clause',
        'posts_per_page' => -1,
        'post_status'    => ['publish', 'draft', 'pending', 'private'],
        'orderby'        => 'title',
        'order'          => 'ASC',
    ]);

    // Build clauses grouped by bylaw_group
    $grouped_clauses = [];
    foreach ($all_clauses as $clause) {
        $group = get_post_meta($clause->ID, 'bylaw_group', true) ?: 'uncategorized';
        $grouped_clauses[$group][] = $clause;
    }
    ?>

    <fieldset class="inline-edit-col-right inline-custom-meta">
        <div class="inline-edit-col">
            <?php wp_nonce_field('bcm_qe_save', 'bcm_qe_nonce'); ?>

            <!-- Tags Field -->
            <label style="display:block; margin-top: 8px;">
                <span class="title"><?php esc_html_e('Tags', 'bylaw-clause-manager'); ?></span>
                <input type="text" name="bcm_qe_tags" class="bcm-qe-tags" style="width:100%;"
                    placeholder="<?php esc_attr_e('Comma-separated…', 'bylaw-clause-manager'); ?>" />
            </label>

            <!-- Bylaw Group Dropdown -->
            <label style="display:block; margin-top: 10px;">
                <span class="title"><?php esc_html_e('Bylaw Group', 'bylaw-clause-manager'); ?></span>
                <select name="bcm_qe_bylaw_group" class="bcm-qe-group" style="width:100%;">
                    <option value=""><?php esc_html_e('— Select —', 'bylaw-clause-manager'); ?></option>
                    <?php foreach ($groups as $key => $label): ?>
                        <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <!-- Parent Clause Dropdown (grouped by Bylaw Group) -->
            <label style="display:block; margin-top: 10px;">
                <span class="title"><?php esc_html_e('Parent Clause', 'bylaw-clause-manager'); ?></span>
                <select name="bcm_qe_parent_clause" class="bcm-qe-parent" style="width:100%;">
                    <option value=""><?php esc_html_e('— None —', 'bylaw-clause-manager'); ?></option>
                    <?php foreach ($grouped_clauses as $group_key => $clauses): ?>
                        <optgroup label="<?php echo esc_attr($groups[$group_key] ?? ucfirst($group_key)); ?>" data-group="<?php echo esc_attr($group_key); ?>">
                            <?php foreach ($clauses as $clause): ?>
                                <?php
                                $title   = get_the_title($clause);
                                $content = wp_strip_all_tags($clause->post_content); // ← FIXED
                                $snippet = mb_substr($content, 0, 30);
                                $label   = $title . ' ' . $snippet;
                                ?>
                                <option value="<?php echo esc_attr($clause->ID); ?>">
                                    <?php echo esc_html($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
    </fieldset>

    <?php
}, 10, 2);

// Save Quick Edit data: Saves the custom fields from the Quick Edit box
// This includes saving the tags and parent clause fields
// It checks for the nonce and user permissions before saving
// It also prevents circular self-reference for the parent clause
add_action('save_post_bylaw_clause', function($post_id) {
    if (
        defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ||
        wp_is_post_revision($post_id) ||
        get_post_type($post_id) !== 'bylaw_clause' ||
        !current_user_can('edit_post', $post_id)
    ) {
        return;
    }

    if (!isset($_POST['bcm_qe_nonce'])) {
        return;
    }

    $bcm_nonce = sanitize_text_field(wp_unslash($_POST['bcm_qe_nonce']));

    if (!wp_verify_nonce($bcm_nonce, 'bcm_qe_save')) {
        return;
    }

    // Save bylaw_group
    if (isset($_POST['bcm_qe_bylaw_group'])) {
        $group = sanitize_key($_POST['bcm_qe_bylaw_group']);
        update_post_meta($post_id, 'bylaw_group', $group);
    }

    // Save tags
    if (isset($_POST['bcm_qe_tags'])) {
        $tags = sanitize_textarea_field(wp_unslash($_POST['bcm_qe_tags']));
        update_post_meta($post_id, 'tags', $tags);
    }

    // Save parent_clause, prevent circular self-reference
    if (isset($_POST['bcm_qe_parent_clause'])) {
        $pid = absint($_POST['bcm_qe_parent_clause']);
        if ($pid && $pid !== (int) $post_id) {
            update_post_meta($post_id, 'parent_clause', $pid);
        } else {
            delete_post_meta($post_id, 'parent_clause');
        }
    }
});


// bcm_render_bylaw_tree: Renders the Bylaw Clause tree recursively
// This function takes a parent ID and depth as parameters
// It retrieves the child clauses and renders them in a nested structure
// It also handles the display of tags, vote markers, and other metadata
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

    usort($clauses, function($a, $b) {
        return bcm_sequence_to_int(get_post_meta($a->ID, 'sequence', true))
            <=> bcm_sequence_to_int(get_post_meta($b->ID, 'sequence', true));
    });

    if (!$clauses) return;

    foreach ($clauses as $clause) {
        $section      = get_post_meta($clause->ID, 'section_id', true);
        $content      = $clause->post_content;
        $tags         = get_post_meta($clause->ID, 'tags', true);
        $parent       = get_post_meta($clause->ID, 'parent_clause', true);

        // Build vote tooltip using your existing function
        $vote_marker = bcm_generate_vote_tooltip($clause->ID);

        if ((int)$clause->ID === (int)$parent) continue;

        $class_string = '';
        if (!empty($tags)) {
            $tag_array = array_map('trim', explode(',', strtolower($tags)));
            $class_string = implode(' ', array_map('sanitize_html_class', $tag_array));
        }

        $anchor_id = sanitize_title($section ?: $clause->ID);
        $margin    = 20 * (int)$depth;

        echo "\n" . '<div class="bylaw-clause ' . esc_attr($class_string) . '" id="clause-' . esc_attr($anchor_id) . '" data-id="' . esc_attr($clause->ID) . '" data-parent="' . esc_attr($parent ?: 0) . '" style="margin-left:' . esc_attr($margin) . 'px;">';
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

// bcm_generate_vote_tooltip: Generates a tooltip for the vote marker
// This function retrieves the vote date, reference, and URL from post meta
function bcm_generate_vote_tooltip($clause_id) {
    $vote_date = get_post_meta($clause_id, 'vote_date', true);
    $vote_ref  = get_post_meta($clause_id, 'vote_reference', true);
    $vote_url  = get_post_meta($clause_id, 'vote_url', true);

    if (!$vote_date && !$vote_ref && !$vote_url) return '';

    $tooltip_parts = [];
    if ($vote_date) $tooltip_parts[] = 'Date: ' . esc_html($vote_date);
    if ($vote_ref)  $tooltip_parts[] = 'Reference: ' . esc_html($vote_ref);
    if ($vote_url)  $tooltip_parts[] = 'URL: <a href="' . esc_url($vote_url) . '" target="_blank" rel="noopener noreferrer">' . esc_html($vote_url) . '</a>';

    // Return tooltip safely inside span
    return '<span class="vote-tooltip">&nbsp;📜<span class="tooltip-content">' . implode('<br />', $tooltip_parts) . '</span></span>';
}

// bcm_sequence_to_int: Converts a sequence string to an integer for sorting
// This function handles the conversion of sequence strings to integers
// It supports numeric, single-letter, and Roman numeral formats
function bcm_sequence_to_int($seq) {
    if (!$seq) return 999999;

    $seq = trim($seq);
    $lower = strtolower($seq);

    // Numeric
    if (is_numeric($seq)) return (int)$seq;

    // Single letter
    if (preg_match('/^[a-zA-Z]$/', $seq)) {
        return ord($lower) - ord('a') + 1000;
    }

    // Roman numerals
    $roman_map = [
        'i'=>1,'ii'=>2,'iii'=>3,'iv'=>4,'v'=>5,
        'vi'=>6,'vii'=>7,'viii'=>8,'ix'=>9,'x'=>10,
        'xi'=>11,'xii'=>12,'xiii'=>13,'xiv'=>14,'xv'=>15,
        'xvi'=>16,'xvii'=>17,'xviii'=>18,'xix'=>19,'xx'=>20
    ];
    if (isset($roman_map[$lower])) {
        return 2000 + $roman_map[$lower];
    }

    // Fallback
    return 900000 + crc32($seq);
}

// shortcode for rendering bylaws
// This shortcode allows rendering of bylaws in a post or page
// It accepts a 'group' attribute to filter the bylaws by group
// It queries the latest modified clause to display the last updated timestamp
add_shortcode('render_bylaws', function($atts) {
    $atts = shortcode_atts(['group' => null], $atts);
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
    echo '  <label for="bcm-tag-select">' . esc_html__('Filter by Tag:', 'bylaw-clause-manager') . '</label>';
    echo '  <select id="bcm-tag-select" multiple style="width: 300px;"></select>';
    echo '  <button type="button" onclick="bcmClearFilters()">' . esc_html__('Clear Filters', 'bylaw-clause-manager') . '</button>';
    echo '  <button type="button" onclick="window.print()">' . esc_html__('Print / Export PDF', 'bylaw-clause-manager') . '</button>';
    echo '</div><!-- #bcm-toolbar -->';

    bcm_render_bylaw_tree(0, 0, $atts['group']);

    echo '</div>';

    return ob_get_clean();
});

// Enqueue assets for the Bylaw Clause Manager
// This function loads the necessary CSS and JS files for the plugin
// It includes the Select2 library and the custom filter script
// It also handles the loading of assets only on the Bylaw Clause post type admin page
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

    // ✅ Load the updated custom filter script (v2)
    wp_enqueue_script(
        'bcm-filter-v2',
        $plugin_url . '/js/filter.js',
        ['jquery', 'select2'],
        filemtime(plugin_dir_path(__FILE__) . 'js/filter.js'),
        true
    );
}

// bcm_get_inline_styles: Generates inline CSS styles for the Bylaw Clause Manager
// This function returns a string of CSS styles for the plugin
function bcm_get_inline_styles() {
    $css = '';

    $css .= "#bcm-toolbar{margin-bottom:1em;display:flex;align-items:center;gap:0.75em;flex-wrap:wrap;}";
    $css .= "#bcm-toolbar button{padding:4px 10px;font-size:0.9em;border-radius:4px;border:1px solid #ccc;background:#f5f5f5;cursor:pointer;transition:0.2s;line-height:1.2;}";
    $css .= "#bcm-toolbar button:hover{background:#e6e6e6;border-color:#999;}";
    $css .= "#bcm-toolbar label{margin-right:0.5em;font-size:0.95em;font-weight:500;}";

    $css .= ".bylaw-clause{display:block;margin-bottom:0em;}";
    $css .= ".bylaw-label-wrap{display:flex;align-items:flex-start;gap:1em;flex-wrap:wrap;margin-bottom:0.1em;line-height:1.1;}";
    $css .= ".bylaw-label-number{white-space:nowrap;font-size:1em;flex-shrink:0;}";
    $css .= ".bylaw-label-text{word-break:break-word;font-size:0.95em;flex:1;min-width:0;}";

    $css .= ".vote-tooltip{position:relative;display:inline-block;color:#555;cursor:help;font-size:0.85em;}";
    $css .= ".vote-tooltip .tooltip-content{display:none;position:absolute;top:100%;left:50%;transform:translateX(-50%);background:#333;color:#fff;padding:6px 8px;border-radius:4px;font-size:0.75em;z-index:9999;min-width:160px;max-width:280px;text-align:left;box-shadow:0 2px 6px rgba(0,0,0,0.3);white-space:normal;}";
    $css .= ".vote-tooltip:hover .tooltip-content{display:block;}";
    $css .= ".tooltip-content a{color:#9cf;text-decoration:underline;}";
    $css .= ".tooltip-content a:hover{color:#cde;}";

    return $css;
}

// bcm_enqueue_admin_assets
function bcm_enqueue_admin_assets($hook) {
    global $typenow;

    if ($hook !== 'edit.php' || $typenow !== 'bylaw_clause') return;

    wp_enqueue_style('select2', plugins_url('css/select2.min.css', __FILE__), [], '4.1.0');
    wp_enqueue_script('select2', plugins_url('js/select2.min.js', __FILE__), ['jquery'], '4.1.0', true);

    wp_enqueue_script(
        'bcm-filter-v2',
        plugins_url('js/filter.js', __FILE__),
        ['jquery', 'select2'],
        filemtime(plugin_dir_path(__FILE__) . 'js/filter.js'),
        true
    );
}
add_action('admin_enqueue_scripts', 'bcm_enqueue_admin_assets');

//bcm_enqueue_frontend_assets
function bcm_enqueue_frontend_assets() {
    wp_enqueue_style('select2-style', plugins_url('css/select2.min.css', __FILE__), [], '4.1.0');
    wp_enqueue_script('select2', plugins_url('js/select2.min.js', __FILE__), ['jquery'], '4.1.0', true);

    wp_enqueue_script(
        'bcm-filter-v2',
        plugins_url('js/filter.js', __FILE__),
        ['jquery', 'select2'],
        filemtime(plugin_dir_path(__FILE__) . 'js/filter.js'),
        true
    );

    wp_add_inline_style('select2-style', bcm_get_inline_styles());
}
add_action('wp_enqueue_scripts', 'bcm_enqueue_frontend_assets', 100);

// Add the meta box for the Bylaw Clause post type
// This function adds a meta box to the Bylaw Clause post type admin edit screen
add_action('add_meta_boxes', function() {
    add_meta_box(
        'bcm_clause_meta',
        'Bylaw Clause Details',
        'bcm_render_clause_metabox',
        'bylaw_clause',
        'normal',
        'default'
    );
});

// Render the meta box for the Bylaw Clause post type
// This function outputs the HTML for the meta box
function bcm_render_clause_metabox($post) {
    wp_nonce_field('bcm_clause_meta_save', 'bcm_clause_meta_nonce');

    $group = get_post_meta($post->ID, 'bylaw_group', true);
    $parent = get_post_meta($post->ID, 'parent_clause', true);
    $section_id = get_post_meta($post->ID, 'section_id', true);
    $tags = get_post_meta($post->ID, 'tags', true);
    $vote_date = get_post_meta($post->ID, 'vote_date', true);
    $vote_ref = get_post_meta($post->ID, 'vote_reference', true);
    $vote_url = get_post_meta($post->ID, 'vote_url', true);

    $groups = bcm_get_bylaw_groups();
    $clauses = get_posts([
        'post_type' => 'bylaw_clause',
        'posts_per_page' => -1,
        'post_status' => ['publish', 'draft', 'pending', 'private'],
        'orderby' => 'title',
        'order' => 'ASC',
    ]);

    echo '<p><label><strong>Bylaw Group</strong><br />';
    echo '<select name="bcm_bylaw_group" style="width:100%;">';
    echo '<option value="">— Select —</option>';
    foreach ($groups as $key => $label) {
        printf(
            '<option value="%s"%s>%s</option>',
            esc_attr($key),
            selected($group, $key, false),
            esc_html($label)
        );
    }
    echo '</select></label></p>';

    echo '<p><label><strong>Parent Clause</strong><br />';
    echo '<select name="bcm_parent_clause" style="width:100%;">';
    echo '<option value="">— None —</option>';
    foreach ($clauses as $clause) {
        if ($clause->ID == $post->ID) continue; // Prevent self-parenting
        $title   = get_the_title($clause);
        $snippet = mb_substr(wp_strip_all_tags($clause->post_content), 0, 30); // ← FIXED
        printf(
            '<option value="%s"%s>%s – %s</option>',
            esc_attr($clause->ID),
            selected($parent, $clause->ID, false),
            esc_html($title),
            esc_html($snippet)
        );
    }
    echo '</select></label></p>';

    echo '<p><label><strong>Section ID</strong><br />';
    echo '<input type="text" name="bcm_section_id" value="' . esc_attr($section_id) . '" style="width:100%;" />';
    echo '</label></p>';

    echo '<p><label><strong>Tags</strong><br />';
    echo '<input type="text" name="bcm_tags" value="' . esc_attr($tags) . '" style="width:100%;" /></label></p>';

    echo '<hr><h4>Vote Metadata</h4>';

    echo '<p><label><strong>Vote Date</strong><br />';
    echo '<input type="date" name="bcm_vote_date" value="' . esc_attr($vote_date) . '" /></label></p>';

    echo '<p><label><strong>Vote Reference</strong><br />';
    echo '<input type="text" name="bcm_vote_reference" value="' . esc_attr($vote_ref) . '" style="width:100%;" /></label></p>';

    echo '<p><label><strong>Vote URL</strong><br />';
    echo '<input type="url" name="bcm_vote_url" value="' . esc_attr($vote_url) . '" style="width:100%;" /></label></p>';
}

add_action('save_post_bylaw_clause', function($post_id) {
    if (
        defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ||
        !isset($_POST['bcm_clause_meta_nonce']) ||
        !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bcm_clause_meta_nonce'])), 'bcm_clause_meta_save') ||
        !current_user_can('edit_post', $post_id)
    ) {
        return;
    }

    $tags         = isset($_POST['bcm_tags'])         ? sanitize_text_field(wp_unslash($_POST['bcm_tags'])) : '';
    $group        = isset($_POST['bcm_bylaw_group'])  ? sanitize_key(wp_unslash($_POST['bcm_bylaw_group'])) : '';
    $parent_id    = isset($_POST['bcm_parent_clause']) ? absint(wp_unslash($_POST['bcm_parent_clause'])) : 0;
    $section_id   = isset($_POST['bcm_section_id'])   ? sanitize_text_field(wp_unslash($_POST['bcm_section_id'])) : '';
    $vote_date    = isset($_POST['bcm_vote_date'])    ? sanitize_text_field(wp_unslash($_POST['bcm_vote_date'])) : '';
    $vote_ref     = isset($_POST['bcm_vote_reference']) ? sanitize_text_field(wp_unslash($_POST['bcm_vote_reference'])) : '';
    $vote_url     = isset($_POST['bcm_vote_url'])     ? esc_url_raw(wp_unslash($_POST['bcm_vote_url'])) : '';

    update_post_meta($post_id, 'tags', $tags);
    update_post_meta($post_id, 'bylaw_group', $group);
    update_post_meta($post_id, 'parent_clause', ($parent_id !== $post_id ? $parent_id : ''));
    update_post_meta($post_id, 'section_id', $section_id);
    update_post_meta($post_id, 'vote_date', $vote_date);
    update_post_meta($post_id, 'vote_reference', $vote_ref);
    update_post_meta($post_id, 'vote_url', $vote_url);
});