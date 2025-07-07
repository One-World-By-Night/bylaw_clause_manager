<?php

/** File: includes/hooks/init.php
 * Text Domain: bylaw-clause-manager
 * @version 2.2.4
 * @author greghacke
 * Function: Init hooks functionality for the plugin
 */

defined( 'ABSPATH' ) || exit;

/** --- Require each hooks file once --- */
require_once __DIR__ . '/filters.php';
require_once __DIR__ . '/rest-api.php';
require_once __DIR__ . '/webhooks.php';
