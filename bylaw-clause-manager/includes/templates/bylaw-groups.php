<?php

/** File: includes/templates/bylaw-groups.php
 * Text Domain: bylaw-clause-manager
 * @version 2.1.1
 * @author greghacke
 * Function: Rendering logic for Bylaw Groups settings page
 */

defined( 'ABSPATH' ) || exit;

/** Renders the Bylaw Groups settings page in the WordPress admin.
 * This function displays a form for managing Bylaw Groups, allowing users to add, edit, and remove groups.
 * It handles form submissions to save changes to the groups, ensuring that only valid keys and labels are saved.
 * The page includes nonce verification for security, and it displays a success message upon saving.
 * The groups are displayed in a table format, with options to add new groups dynamically via JavaScript.
 * The form uses standard WordPress functions for sanitization and escaping to ensure data integrity and security.
 */
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