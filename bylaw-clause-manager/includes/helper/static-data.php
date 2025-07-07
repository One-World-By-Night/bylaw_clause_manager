<?php

/** File: includes/helper/static-data.php
 * Text Domain: bylaw-clause-manager
 * @version 2.2.4
 * @author greghacke
 * Function: Static data helper functions for the Bylaw Clause Manager plugin
 */

defined( 'ABSPATH' ) || exit;

/** Retrieves the Bylaw Groups from the options table.
 *
 * @return array
 */
function bcm_get_bylaw_groups() {
    $groups = get_option('bcm_bylaw_groups', []);

    return (is_array($groups) && !empty($groups)) ? $groups : [
        'character'   => esc_html__('Character', 'bylaw-clause-manager'),
        'council'     => esc_html__('Council', 'bylaw-clause-manager'),
        'coordinator' => esc_html__('Coordinator', 'bylaw-clause-manager'),
    ];
}