<?php

defined('ABSPATH') || exit;

function bcm_register_bylaw_clause_cpt()
{
    register_post_type('bylaw_clause', [
        'labels' => [
            'name'          => esc_html__('Bylaw Clauses', 'bylaw-clause-manager'),
            'singular_name' => esc_html__('Bylaw Clause', 'bylaw-clause-manager'),
        ],
        'public'        => true,
        'has_archive'   => false,
        'rewrite'       => [
            'slug'       => 'bylaw-clause/%bylaw_group%',
            'with_front' => false,
        ],
        'supports'      => ['title', 'editor', 'revisions'],
        'show_in_rest'  => true,
        'menu_icon'     => 'dashicons-book-alt',
    ]);
}
add_action('init', 'bcm_register_bylaw_clause_cpt');

function bcm_custom_bylaw_permalink($post_link, $post)
{
    if ($post->post_type !== 'bylaw_clause') return $post_link;

    $group = get_post_meta($post->ID, 'bylaw_group', true);
    $group = $group ? sanitize_title($group) : 'uncategorized';

    $slug = sanitize_title($post->post_title);

    return home_url("/bylaw-clause/{$group}/{$slug}/");
}
add_filter('post_type_link', 'bcm_custom_bylaw_permalink', 10, 2);

function bcm_custom_rewrite_rules()
{
    add_rewrite_rule(
        '^bylaw-clause/([^/]+)/([^/]+)/?$',
        'index.php?post_type=bylaw_clause&name=$matches[2]',
        'top'
    );
}
add_action('init', 'bcm_custom_rewrite_rules');
