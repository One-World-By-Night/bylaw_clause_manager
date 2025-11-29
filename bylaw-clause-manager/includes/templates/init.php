<?php

/** File: includes/templates/init.php
 * Text Domain: bylaw-clause-manager
 * @version 2.3.0
 * @author greghacke
 * Function: Init teamplates functionality for the plugin
 */

defined('ABSPATH') || exit;

/** --- Require each render file once --- */
require_once __DIR__ . '/bylaw-groups.php';
require_once __DIR__ . '/bulk-edit.php';
