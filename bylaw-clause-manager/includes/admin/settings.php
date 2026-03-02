<?php

defined('ABSPATH') || exit;

add_action('admin_menu', function () {
    add_submenu_page(
        'edit.php?post_type=bylaw_clause',
        'Manage Bylaw Groups',
        'Bylaw Groups',
        'manage_options',
        'bcm_bylaw_groups',
        'bcm_render_bylaw_group_settings_page'
    );
    add_submenu_page(
        'edit.php?post_type=bylaw_clause',
        'Bulk Edit Clauses',
        'Bulk Edit',
        'manage_options',
        'bcm_bulk_edit',
        'bcm_render_bulk_edit_page'
    );
});
