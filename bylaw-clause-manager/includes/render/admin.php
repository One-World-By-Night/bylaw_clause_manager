<?php

/** File: includes/render/admin.php
 * Text Domain: bylaw-clause-manager
 * @version 2.3.0
 * @author greghacke
 * Function: 
 */

defined('ABSPATH') || exit;

/** Render the Bylaw Clause CPT admin list table
 * This function modifies the admin list table for the Bylaw Clause custom post type (CPT).
 * It adds custom columns to display additional information such as Bylaw Group, Parent Clause, and a content preview.
 */
add_filter('manage_bylaw_clause_posts_columns', function ($columns) {
    $new = [];

    foreach ($columns as $key => $label) {
        $new[$key] = $label;

        if ($key === 'title') {
            $new['bylaw_group']    = esc_html__('Bylaw Group', 'bylaw-clause-manager');
            $new['parent_clause']  = esc_html__('Parent Clause', 'bylaw-clause-manager');
            $new['short_content']  = esc_html__('Content Preview', 'bylaw-clause-manager');
        }
    }

    return $new;
});

/** Renders custom column content for Bylaw Clause CPT list view.
 * This function populates the custom columns added to the Bylaw Clause CPT list table.
 * It retrieves the Bylaw Group, Parent Clause, and a short content preview for each post.
 * The content preview is trimmed to approximately 60 characters for better readability.
 * It also embeds Quick Edit data for each post, allowing inline editing of these fields.
 */
add_action('manage_bylaw_clause_posts_custom_column', function ($column, $post_id) {
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

    // Embed Quick Edit data (even if not visible)
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

/** Adds admin CSS to style custom columns in the list view.
 * This function adds custom CSS to the admin head to style the 'short_content' column.
 * It ensures that the content is displayed in a single line with ellipsis for overflow.
 * This improves readability and prevents layout issues in the admin list table.
 */
add_action('admin_head', function () {
    echo '<style>
        .column-short_content {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 300px;
        }
    </style>';
});

/** Renders filter dropdowns and search input for Bylaw Clause admin list view.
 * Adds:
 * - Bylaw Group dropdown (from stored options or fallback)
 * - Title prefix text input
 * - Nonce field for validation
 */
add_action('restrict_manage_posts', function () {
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

    // Load Bylaw Groups
    $groups = bcm_get_bylaw_groups(); // preferred over raw get_option()

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

/** Renders Quick Edit fields for Bylaw Clause CPT.
 * This function adds custom fields to the Quick Edit interface for the Bylaw Clause CPT.
 * It includes:
 * - Tags input field
 * - Bylaw Group dropdown
 * - Parent Clause dropdown (grouped by Bylaw Group)
 * * The fields are populated with existing data from the post being edited.
 * This allows users to quickly edit these fields without leaving the list view.
 * The function ensures that the dropdowns are populated with all available Bylaw Groups and Clauses.
 * The Parent Clause dropdown is grouped by Bylaw Group for better organization.
 * The Tags field allows for comma-separated input, making it easy to add or remove tags.
 */
add_action('quick_edit_custom_box', function ($column, $post_type) {
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
                <select name="bcm_qe_parent_clause" class="bcm-qe-parent" style="width:100%;" data-post-id="">
                    <option value=""><?php esc_html_e('— None —', 'bylaw-clause-manager'); ?></option>
                    <?php foreach ($grouped_clauses as $group_key => $clauses): ?>
                        <optgroup label="<?php echo esc_attr($groups[$group_key] ?? ucfirst($group_key)); ?>" data-group="<?php echo esc_attr($group_key); ?>" style="display:none;">
                            <?php foreach ($clauses as $clause): ?>
                                <?php
                                $title   = get_the_title($clause);
                                $content = wp_strip_all_tags($clause->post_content);
                                $snippet = mb_substr($content, 0, 30);
                                $label   = $title . ' – ' . $snippet;
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

add_action('pre_get_posts', function ($query) {
    if (!is_admin() || !$query->is_main_query()) return;

    global $typenow;
    if ($typenow !== 'bylaw_clause') return;

    if (
        isset($_GET['bcm_filter_nonce_field']) &&
        wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['bcm_filter_nonce_field'])), 'bcm_filter_nonce')
    ) {
        $title_filter = isset($_GET['bcm_title_filter']) ? sanitize_text_field(wp_unslash($_GET['bcm_title_filter'])) : '';

        if ($title_filter !== '') {
            // Fetch matching post IDs manually with strict prefix match
            $matched_ids = get_posts([
                'post_type'      => 'bylaw_clause',
                'post_status'    => ['publish', 'draft', 'pending', 'private'],
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'suppress_filters' => true,
            ]);

            $matching_ids = [];

            foreach ($matched_ids as $id) {
                $title = get_the_title($id);
                if (strpos($title, $title_filter) === 0) {
                    $matching_ids[] = $id;
                }
            }

            // If no matches, force no results
            $query->set('post__in', $matching_ids ?: [0]);
        }
    }
});
