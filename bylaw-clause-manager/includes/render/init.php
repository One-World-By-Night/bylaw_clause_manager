<?php

/** File: includes/render/init.php
 * Text Domain: bylaw-clause-manager
 * @version 2.2.4
 * @author greghacke
 * Function: Init render functionality for the plugin
 */

defined( 'ABSPATH' ) || exit;

/** --- Require each render file once --- */
require_once __DIR__ . '/admin.php';
require_once __DIR__ . '/listing.php';