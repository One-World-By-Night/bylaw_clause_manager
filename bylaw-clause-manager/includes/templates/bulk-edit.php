<?php

/** File: includes/templates/bulk-edit.php
 * Text Domain: bylaw-clause-manager
 * @version 2.3.0
 * @author greghacke
 * Function: Rendering logic for Bulk Edit settings page
 */

defined('ABSPATH') || exit;

/** Renders the Bulk Edit page in the WordPress admin.
 */
function bcm_render_bulk_edit_page()
{
    $groups = bcm_get_bylaw_groups();

    // Get current filter values
    $selected_group = isset($_GET['bcm_group']) ? sanitize_key($_GET['bcm_group']) : '';
    $title_filter = isset($_GET['bcm_filter']) ? sanitize_text_field($_GET['bcm_filter']) : '';
    $paged = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
    $per_page = 20;

    // Handle form submissions
    $notice = '';
    $notice_type = '';
    $preview_data = null;

    // Handle preview request
    if (isset($_POST['bcm_bulk_preview']) && check_admin_referer('bcm_bulk_rename', 'bcm_bulk_nonce')) {
        $old_prefix = sanitize_text_field($_POST['bcm_old_prefix'] ?? '');
        $new_prefix = sanitize_text_field($_POST['bcm_new_prefix'] ?? '');
        $rename_group = sanitize_key($_POST['bcm_rename_group'] ?? '');

        $preview_data = bcm_preview_bulk_rename($old_prefix, $new_prefix, $rename_group);

        if (is_wp_error($preview_data)) {
            $notice = $preview_data->get_error_message();
            $notice_type = 'error';
            $preview_data = null;
        }
    }

    // Handle execute request
    if (isset($_POST['bcm_bulk_execute']) && check_admin_referer('bcm_bulk_rename', 'bcm_bulk_nonce')) {
        $old_prefix = sanitize_text_field($_POST['bcm_old_prefix'] ?? '');
        $new_prefix = sanitize_text_field($_POST['bcm_new_prefix'] ?? '');
        $rename_group = sanitize_key($_POST['bcm_rename_group'] ?? '');

        $result = bcm_execute_bulk_rename($old_prefix, $new_prefix, $rename_group);

        if (is_wp_error($result)) {
            $notice = $result->get_error_message();
            $notice_type = 'error';
        } else {
            $success_count = count($result['success']);
            $failed_count = count($result['failed']);

            if ($failed_count === 0) {
                $notice = sprintf(__('Successfully renamed %d clauses.', 'bylaw-clause-manager'), $success_count);
                $notice_type = 'success';
            } else {
                $notice = sprintf(
                    __('Renamed %d clauses. %d failed.', 'bylaw-clause-manager'),
                    $success_count,
                    $failed_count
                );
                $notice_type = 'warning';
            }
        }
    }

?>
    <div class="wrap">
        <h1><?php esc_html_e('Bulk Edit Bylaw Clauses', 'bylaw-clause-manager'); ?></h1>

        <?php if ($notice): ?>
            <div class="notice notice-<?php echo esc_attr($notice_type); ?> is-dismissible">
                <p><?php echo esc_html($notice); ?></p>
            </div>
        <?php endif; ?>

        <!-- Bulk Rename Section -->
        <div class="card" style="max-width: 800px; padding: 20px; margin-bottom: 20px;">
            <h2><?php esc_html_e('Bulk Rename Clauses', 'bylaw-clause-manager'); ?></h2>
            <p><?php esc_html_e('Rename a clause and all its children. Example: Change "10_c" to "10_d" and all children (10_c_i, 10_c_ii) will become (10_d_i, 10_d_ii).', 'bylaw-clause-manager'); ?></p>

            <form method="post" action="">
                <?php wp_nonce_field('bcm_bulk_rename', 'bcm_bulk_nonce'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="bcm_rename_group"><?php esc_html_e('Bylaw Group', 'bylaw-clause-manager'); ?></label>
                        </th>
                        <td>
                            <select name="bcm_rename_group" id="bcm_rename_group" required>
                                <option value=""><?php esc_html_e('— Select —', 'bylaw-clause-manager'); ?></option>
                                <?php foreach ($groups as $key => $label): ?>
                                    <option value="<?php echo esc_attr($key); ?>" <?php selected(isset($_POST['bcm_rename_group']) ? $_POST['bcm_rename_group'] : '', $key); ?>>
                                        <?php echo esc_html($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="bcm_old_prefix"><?php esc_html_e('Current Title Prefix', 'bylaw-clause-manager'); ?></label>
                        </th>
                        <td>
                            <input type="text"
                                name="bcm_old_prefix"
                                id="bcm_old_prefix"
                                value="<?php echo esc_attr($_POST['bcm_old_prefix'] ?? ''); ?>"
                                placeholder="<?php esc_attr_e('e.g., 10_c', 'bylaw-clause-manager'); ?>"
                                class="regular-text"
                                required />
                            <p class="description"><?php esc_html_e('The current title prefix to rename.', 'bylaw-clause-manager'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="bcm_new_prefix"><?php esc_html_e('New Title Prefix', 'bylaw-clause-manager'); ?></label>
                        </th>
                        <td>
                            <input type="text"
                                name="bcm_new_prefix"
                                id="bcm_new_prefix"
                                value="<?php echo esc_attr($_POST['bcm_new_prefix'] ?? ''); ?>"
                                placeholder="<?php esc_attr_e('e.g., 10_d', 'bylaw-clause-manager'); ?>"
                                class="regular-text"
                                required />
                            <p class="description"><?php esc_html_e('The new title prefix.', 'bylaw-clause-manager'); ?></p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <?php submit_button(__('Preview Changes', 'bylaw-clause-manager'), 'secondary', 'bcm_bulk_preview', false); ?>
                </p>
            </form>

            <?php if ($preview_data && !is_wp_error($preview_data)): ?>
                <hr />
                <h3><?php esc_html_e('Preview Changes', 'bylaw-clause-manager'); ?></h3>
                <p><?php printf(esc_html__('%d clauses will be renamed:', 'bylaw-clause-manager'), count($preview_data)); ?></p>

                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Current Title', 'bylaw-clause-manager'); ?></th>
                            <th><?php esc_html_e('New Title', 'bylaw-clause-manager'); ?></th>
                            <th><?php esc_html_e('Section ID', 'bylaw-clause-manager'); ?></th>
                            <th><?php esc_html_e('Parent', 'bylaw-clause-manager'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($preview_data as $item): ?>
                            <tr>
                                <td><?php echo esc_html($item['old_title']); ?></td>
                                <td><strong><?php echo esc_html($item['new_title']); ?></strong></td>
                                <td>
                                    <?php if ($item['old_section_id'] !== $item['new_section_id']): ?>
                                        <span style="text-decoration: line-through;"><?php echo esc_html($item['old_section_id']); ?></span>
                                        → <strong><?php echo esc_html($item['new_section_id']); ?></strong>
                                    <?php else: ?>
                                        <?php echo esc_html($item['old_section_id'] ?: '—'); ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($item['parent_changed']): ?>
                                        <span style="text-decoration: line-through;"><?php echo esc_html($item['old_parent_title']); ?></span>
                                        → <strong><?php echo esc_html($item['new_parent_title']); ?></strong>
                                    <?php else: ?>
                                        <?php echo esc_html($item['old_parent_title'] ?: '—'); ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <form method="post" action="" style="margin-top: 15px;">
                    <?php wp_nonce_field('bcm_bulk_rename', 'bcm_bulk_nonce'); ?>
                    <input type="hidden" name="bcm_rename_group" value="<?php echo esc_attr($_POST['bcm_rename_group'] ?? ''); ?>" />
                    <input type="hidden" name="bcm_old_prefix" value="<?php echo esc_attr($_POST['bcm_old_prefix'] ?? ''); ?>" />
                    <input type="hidden" name="bcm_new_prefix" value="<?php echo esc_attr($_POST['bcm_new_prefix'] ?? ''); ?>" />

                    <?php submit_button(__('Execute Rename', 'bylaw-clause-manager'), 'primary', 'bcm_bulk_execute', false); ?>
                    <span style="margin-left: 10px; color: #d63638;">
                        <?php esc_html_e('⚠ This action cannot be undone!', 'bylaw-clause-manager'); ?>
                    </span>
                </form>
            <?php endif; ?>
        </div>

        <hr />

        <!-- Browse Clauses Section -->
        <h2><?php esc_html_e('Browse Clauses', 'bylaw-clause-manager'); ?></h2>

        <form method="get" action="<?php echo esc_url(admin_url('edit.php')); ?>">
            <input type="hidden" name="post_type" value="bylaw_clause" />
            <input type="hidden" name="page" value="bcm_bulk_edit" />

            <div class="tablenav top" style="margin-bottom: 15px;">
                <div class="alignleft actions">
                    <select name="bcm_group" id="bcm-bulk-group">
                        <option value=""><?php esc_html_e('— Select Bylaw Group —', 'bylaw-clause-manager'); ?></option>
                        <?php foreach ($groups as $key => $label): ?>
                            <option value="<?php echo esc_attr($key); ?>" <?php selected($selected_group, $key); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <input type="text"
                        name="bcm_filter"
                        value="<?php echo esc_attr($title_filter); ?>"
                        placeholder="<?php esc_attr_e('Filter by title prefix...', 'bylaw-clause-manager'); ?>"
                        style="width: 200px;" />

                    <?php submit_button(__('Filter', 'bylaw-clause-manager'), 'secondary', 'submit', false); ?>
                </div>
            </div>
        </form>

        <?php if ($selected_group): ?>
            <?php
            // Query clauses for selected group
            $args = [
                'post_type'      => 'bylaw_clause',
                'posts_per_page' => -1,
                'post_status'    => ['publish', 'draft', 'pending', 'private'],
                'orderby'        => 'title',
                'order'          => 'ASC',
                'meta_query'     => [
                    [
                        'key'     => 'bylaw_group',
                        'value'   => $selected_group,
                        'compare' => '='
                    ]
                ]
            ];

            $all_posts = get_posts($args);

            // Apply prefix filter if provided
            if ($title_filter !== '') {
                $all_posts = array_filter($all_posts, function ($post) use ($title_filter) {
                    return strpos(get_the_title($post), $title_filter) === 0;
                });
                $all_posts = array_values($all_posts); // Re-index
            }

            $total_items = count($all_posts);
            $total_pages = ceil($total_items / $per_page);

            // Paginate manually
            $paged_posts = array_slice($all_posts, ($paged - 1) * $per_page, $per_page);
            ?>

            <div class="bcm-bulk-results">
                <p>
                    <?php printf(
                        esc_html__('Found %d clauses in "%s"', 'bylaw-clause-manager'),
                        $total_items,
                        esc_html($groups[$selected_group] ?? $selected_group)
                    ); ?>
                </p>

                <?php if (!empty($paged_posts)): ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <td class="manage-column column-cb check-column">
                                    <input type="checkbox" id="bcm-select-all" />
                                </td>
                                <th class="manage-column"><?php esc_html_e('Title', 'bylaw-clause-manager'); ?></th>
                                <th class="manage-column"><?php esc_html_e('Section ID', 'bylaw-clause-manager'); ?></th>
                                <th class="manage-column"><?php esc_html_e('Parent', 'bylaw-clause-manager'); ?></th>
                                <th class="manage-column"><?php esc_html_e('Status', 'bylaw-clause-manager'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($paged_posts as $post):
                                $post_id = $post->ID;
                                $section_id = get_post_meta($post_id, 'section_id', true);
                                $parent_id = get_post_meta($post_id, 'parent_clause', true);
                                $parent_title = $parent_id ? get_the_title($parent_id) : '—';
                            ?>
                                <tr>
                                    <th scope="row" class="check-column">
                                        <input type="checkbox" name="bcm_clause_ids[]" value="<?php echo esc_attr($post_id); ?>" class="bcm-clause-checkbox" />
                                    </th>
                                    <td>
                                        <strong>
                                            <a href="<?php echo esc_url(get_edit_post_link($post_id)); ?>">
                                                <?php echo esc_html(get_the_title($post)); ?>
                                            </a>
                                        </strong>
                                    </td>
                                    <td><?php echo esc_html($section_id ?: '—'); ?></td>
                                    <td><?php echo esc_html($parent_title); ?></td>
                                    <td><?php echo esc_html(get_post_status($post)); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <div class="tablenav bottom">
                            <div class="tablenav-pages">
                                <span class="displaying-num">
                                    <?php printf(esc_html__('%d items', 'bylaw-clause-manager'), $total_items); ?>
                                </span>
                                <span class="pagination-links">
                                    <?php
                                    $base_url = add_query_arg([
                                        'post_type'  => 'bylaw_clause',
                                        'page'       => 'bcm_bulk_edit',
                                        'bcm_group'  => $selected_group,
                                        'bcm_filter' => $title_filter,
                                    ], admin_url('edit.php'));

                                    // First page
                                    if ($paged > 1): ?>
                                        <a class="first-page button" href="<?php echo esc_url(add_query_arg('paged', 1, $base_url)); ?>">
                                            <span aria-hidden="true">«</span>
                                        </a>
                                        <a class="prev-page button" href="<?php echo esc_url(add_query_arg('paged', $paged - 1, $base_url)); ?>">
                                            <span aria-hidden="true">‹</span>
                                        </a>
                                    <?php else: ?>
                                        <span class="tablenav-pages-navspan button disabled" aria-hidden="true">«</span>
                                        <span class="tablenav-pages-navspan button disabled" aria-hidden="true">‹</span>
                                    <?php endif; ?>

                                    <span class="paging-input">
                                        <span class="tablenav-paging-text">
                                            <?php echo esc_html($paged); ?> of <span class="total-pages"><?php echo esc_html($total_pages); ?></span>
                                        </span>
                                    </span>

                                    <?php if ($paged < $total_pages): ?>
                                        <a class="next-page button" href="<?php echo esc_url(add_query_arg('paged', $paged + 1, $base_url)); ?>">
                                            <span aria-hidden="true">›</span>
                                        </a>
                                        <a class="last-page button" href="<?php echo esc_url(add_query_arg('paged', $total_pages, $base_url)); ?>">
                                            <span aria-hidden="true">»</span>
                                        </a>
                                    <?php else: ?>
                                        <span class="tablenav-pages-navspan button disabled" aria-hidden="true">›</span>
                                        <span class="tablenav-pages-navspan button disabled" aria-hidden="true">»</span>
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>
                    <?php endif; ?>

                <?php else: ?>
                    <p><?php esc_html_e('No clauses found matching your criteria.', 'bylaw-clause-manager'); ?></p>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <div class="card" style="max-width: 600px; padding: 20px;">
                <p><?php esc_html_e('Select a Bylaw Group above to view clauses.', 'bylaw-clause-manager'); ?></p>
            </div>
        <?php endif; ?>
    </div>

    <script>
        jQuery(document).ready(function($) {
            $('#bcm-select-all').on('change', function() {
                $('.bcm-clause-checkbox').prop('checked', $(this).prop('checked'));
            });
        });
    </script>
<?php
}
