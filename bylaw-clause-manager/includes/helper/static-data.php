<?php

defined('ABSPATH') || exit;

function bcm_get_bylaw_groups()
{
    $groups = get_option('bcm_bylaw_groups', []);

    return (is_array($groups) && !empty($groups)) ? $groups : [
        'character'   => esc_html__('Character', 'bylaw-clause-manager'),
        'council'     => esc_html__('Council', 'bylaw-clause-manager'),
        'coordinator' => esc_html__('Coordinator', 'bylaw-clause-manager'),
    ];
}
