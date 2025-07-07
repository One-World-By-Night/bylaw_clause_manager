<?php

/** File: includes/admin/enqueue.php
 * Text Domain: bylaw-clause-manager
 * @version 2.2.4
 * @author greghacke
 * Function: Handles enqueuing of scripts and styles for Bylaw Clause Manager
 */

defined('ABSPATH') || exit;

/** Enqueue admin scripts and styles for Bylaw Clause admin pages.
 * Includes Select2, filter.js, and custom styling.
 */
function bcm_admin_enqueue_assets($hook) {
    // $allowed_hooks = [
    //     'edit.php',
    //     'post.php',
    //     'post-new.php',
    //     'bylaw-clause_page_bcm_bylaw_groups',
    // ];

    // if (!in_array($hook, $allowed_hooks, true)) {
    //     return;
    // }

    // __DIR__ resolves to /includes/admin/
    $base_url  = plugin_dir_url(__DIR__) . '/assets/';
    $base_path = plugin_dir_path(__DIR__) . '/assets/';

    wp_enqueue_style('bcm-select2', $base_url . 'css/select2.min.css', [], '4.1.0');
    wp_enqueue_script('bcm-select2', $base_url . 'js/select2.min.js', ['jquery'], '4.1.0', true);
    wp_enqueue_script('bcm-filter-v2', $base_url . 'js/filter.js', ['jquery', 'bcm-select2'], filemtime($base_path . 'js/filter.js'), true);
    wp_enqueue_style('bylaw-clause-manager-style', $base_url . 'css/style.css', [], filemtime($base_path . 'css/style.css'));
}

add_action('admin_enqueue_scripts', 'bcm_admin_enqueue_assets');

/** Enqueue frontend scripts and styles for Bylaw Clause listing shortcode.
 * Includes Select2, filter.js, and custom styling.
 */
function bcm_enqueue_assets() {
    $base_url  = plugin_dir_url(__DIR__) . 'assets/';
    $base_path = plugin_dir_path(__DIR__) . 'assets/';

    wp_enqueue_style('select2-style', $base_url . 'css/select2.min.css', [], '4.1.0');
    wp_enqueue_script('select2', $base_url . 'js/select2.min.js', ['jquery'], '4.1.0', true);
    wp_enqueue_script('bcm-filter-v2', $base_url . 'js/filter.js', ['jquery', 'select2'], filemtime($base_path . 'js/filter.js'), true);
    wp_enqueue_style('bylaw-clause-manager-style', $base_url . 'css/style.css', [], filemtime($base_path . 'css/style.css'));
}
add_action('wp_enqueue_scripts', 'bcm_enqueue_assets');