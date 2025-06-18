<?php

/** File: includes/admin/init.php
 * Text Domain: bylaw-clause-manager
 * @version 2.1.2
 * @author greghacke
 * Function: Quickly initialize the admin area of the Bylaw Clause Manager plugin.
 */

defined( 'ABSPATH' ) || exit;

/** --- Require each admin file once --- */
require_once __DIR__ . '/cpt.php';
require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/enqueue.php';
require_once __DIR__ . '/metabox.php';
require_once __DIR__ . '/save.php';
