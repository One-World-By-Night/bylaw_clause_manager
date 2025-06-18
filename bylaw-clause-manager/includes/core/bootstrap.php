<?php

/** File: includes/core/bootstrap.php
 * Text Domain: bylaw-clause-manager
 * @version 2.1.2
 * @author greghacke
 * Function: Bootstrap the core functionality of the Bylaw Clause Manager plugin
 */

defined( 'ABSPATH' ) || exit;

// Define constants for the plugin
define('WPPLUGINNAME_PATH', plugin_dir_path(__FILE__) . '../');
define('WPPLUGINNAME_URL', plugin_dir_url(__FILE__) . '../');

// Define the plugin version
define('WPPLUGINNAME_VERSION', '2.1.0');