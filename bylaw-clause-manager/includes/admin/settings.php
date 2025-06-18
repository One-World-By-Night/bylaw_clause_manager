<?php

/** File: includes/admin/settings.php
 * Text Domain: bylaw-clause-manager
 * @version 2.1.2
 * @author greghacke
 * Function: Set up the admin settings page for managing bylaw groups
 */

defined( 'ABSPATH' ) || exit;

/** Render the Bylaw Group settings page
 * This function displays the settings page for managing bylaw groups.
 */

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