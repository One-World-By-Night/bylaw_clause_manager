<?php

/** File: includes/init.php
 * Text Domain: bylaw-clause-manager
 * @version 2.2.4
 * @author greghacke
 * Function:  Porvide a single entry point to load all plugin components in standard and class-based structure
 */

defined('ABSPATH') || exit;

// Load plugin core bootstraps first
require_once __DIR__ . '/core/bootstrap.php';      // e.g., define constants, paths, version
require_once __DIR__ . '/core/authorization.php';  // e.g., user auth logic

// Autoload classes (optional PSR-like loader for 'classes/' dir)
$classes = glob(__DIR__ . '/classes/*.php');
if ($classes) {
    foreach ($classes as $class_file) {
        require_once $class_file;
    }
}

// Load helper functions (used by later modules)
require_once __DIR__ . '/helper/static-data.php';
require_once __DIR__ . '/helper/init.php';

// Load admin functionality (menus, settings, enqueue)
require_once __DIR__ . '/admin/init.php';

// Load hooks (REST API, save, validation, webhooks)
require_once __DIR__ . '/hooks/init.php';

// Load rendering logic (output formatting)
require_once __DIR__ . '/render/init.php';

// Load shortcodes
require_once __DIR__ . '/shortcodes/init.php';

// Load templates if needed
require_once __DIR__ . '/templates/init.php';


// Load plugin-specific tools
require_once __DIR__ . '/tools/init.php';

// Load language support (i18n)
require_once __DIR__ . '/languages/init.php';

// Plugin-level init hook
do_action('wppluginname_loaded');