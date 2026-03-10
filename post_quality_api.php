<?php
/**
 * Plugin Name:       Post Quality API
 * Plugin URI:        https://github.com/vincentheimann/post-quality-api
 * Description:       REST endpoint exposing post content, metadata and Yoast SEO scores for external integrations (n8n, Zapier, Make, etc.).
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Vincent Heimann
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       post-quality-api
 */

if (!defined('ABSPATH'))
    exit;

define('PQA_VERSION', '1.0.0');
define('PQA_PLUGIN_FILE', __FILE__);
define('PQA_NAMESPACE', 'post-quality/v1');

/**
 * Register the REST route on init.
 */
add_action('rest_api_init', 'pqa_register_routes');

function pqa_register_routes(): void
{
    register_rest_route(PQA_NAMESPACE, '/post-score', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'pqa_post_score_callback',
        'permission_callback' => 'pqa_permission_check',
        'args' => pqa_get_route_args(),
    ]);
}

/**
 * Permission callback — requires edit_posts capability.
 * Compatible with WordPress Application Passwords.
 */
function pqa_permission_check(): bool
{
    return current_user_can('edit_posts');
}

/**
 * Define and validate route arguments.
 *
 * @return array
 */
function pqa_get_route_args(): array
{
    return [
        'slug' => [
            'required' => true,
            'type' => 'string',
            'description' => 'The post slug.',
            'sanitize_callback' => 'sanitize_title',
            'validate_callback' => fn($value) => !empty($value),
        ],
        'post_type' => [
            'required' => false,
            'type' => 'string',
            'default' => 'post',
            'description' => 'Post type to query. Defaults to "post".',
            'sanitize_callback' => 'sanitize_key',
            'validate_callback' => fn($value) => post_type_exists($value) && is_post_type_viewable($value),
        ],
    ];
}

/**
 * Main callback — returns post content, metadata and Yoast SEO scores.
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function pqa_post_score_callback(WP_REST_Request $request): WP_REST_Response|WP_Error
{
    $slug = $request->get_param('slug');
    $post_type = $request->get_param('post_type');

    $query = new WP_Query([
        'name' => $slug,
        'post_type' => $post_type,
        'post_status' => 'publish',
        'posts_per_page' => 1,
        'no_found_rows' => true,
    ]);

    if (!$query->have_posts()) {
        return new WP_Error(
            'pqa_not_found',
            sprintf('No published %s found with slug: %s', $post_type, $slug),
            ['status' => 404]
        );
    }

    $queried_post = $query->posts[0];
    $id = $queried_post->ID;

    // Security & Best Practice: Prevent IDOR by verifying granular read capability
    if (!current_user_can('read_post', $id)) {
        return new WP_Error(
            'pqa_forbidden',
            'You do not have permission to read this post.',
            ['status' => rest_authorization_required_code()]
        );
    }

    // Best Practice: Setup global post data safely for 'the_content' filter (shortcodes)
    global $post;
    $original_post = $post;
    $post = $queried_post;
    setup_postdata($post);

    $content_html = get_post_field('post_content', $id);
    $content_plain = wp_strip_all_tags(apply_filters('the_content', $content_html));

    $excerpt = has_excerpt($id)
        ? wp_strip_all_tags(get_the_excerpt($id))
        : wp_trim_words($content_plain, 55, '...');

    // Restore original post data
    $post = $original_post;
    if ($post) {
        setup_postdata($post);
    } else {
        wp_reset_postdata();
    }

    $yoast_active = pqa_is_yoast_active();

    $data = [
        'id' => $id,
        'slug' => $queried_post->post_name,
        'post_type' => $queried_post->post_type,
        'title' => html_entity_decode(get_the_title($id), ENT_QUOTES, 'UTF-8'),
        'link' => get_permalink($id),
        'excerpt' => $excerpt,
        'content_html' => $content_html,
        'content' => $content_plain,
        'yoast_active' => $yoast_active,
        'seo_score' => $yoast_active ? (int) get_post_meta($id, '_yoast_wpseo_linkdex', true) : null,
        'readability_score' => $yoast_active ? (int) get_post_meta($id, '_yoast_wpseo_content_score', true) : null,
        'focus_keyphrase' => $yoast_active ? (string) get_post_meta($id, '_yoast_wpseo_focuskw', true) : null,
        'meta_description' => $yoast_active ? (string) get_post_meta($id, '_yoast_wpseo_metadesc', true) : null,
        'seo_title' => $yoast_active ? (string) get_post_meta($id, '_yoast_wpseo_title', true) : null,
    ];

    return new WP_REST_Response($data, 200);
}

/**
 * Check whether Yoast SEO (free or premium) is active.
 *
 * @return bool
 */
function pqa_is_yoast_active(): bool
{
    return defined('WPSEO_VERSION');
}
