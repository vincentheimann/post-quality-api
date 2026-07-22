# Post Quality API

Minimal WordPress plugin exposing a single REST endpoint that returns a post's content, metadata, and Yoast SEO scores. Built for external integrations — n8n, Zapier, Make, or any HTTP client — that need clean post data without scraping HTML.

## Requirements

- WordPress 6.0+
- PHP 8.0+
- Yoast SEO (optional — SEO fields return `null` when Yoast is not active)

## Installation

1. Copy `post_quality_api.php` into `wp-content/plugins/post-quality-api/` (or install the zip from the GitHub Release).
2. Activate **Post Quality API** in the WordPress admin.
3. Create an [Application Password](https://make.wordpress.org/core/2020/11/05/application-passwords-integration-guide/) for a user with the `edit_posts` capability — the endpoint requires authentication.

## Usage

```
GET /wp-json/post-quality/v1/post-score?slug=<post-slug>&post_type=<type>
```

| Parameter | Required | Description |
|---|---|---|
| `slug` | yes | Slug of a published post |
| `post_type` | no | Any viewable post type. Defaults to `post` |

Example with an Application Password:

```bash
curl -u "user:app-password" \
  "https://example.com/wp-json/post-quality/v1/post-score?slug=my-article"
```

Response fields:

| Field | Description |
|---|---|
| `id`, `slug`, `post_type`, `title`, `link` | Post identity and permalink |
| `excerpt` | Manual excerpt, or first 55 words of the content |
| `content_html` | Raw stored post content |
| `content` | Plain text with shortcodes rendered and tags stripped |
| `yoast_active` | Whether Yoast SEO is active |
| `seo_score`, `readability_score` | Yoast scores (`null` without Yoast) |
| `focus_keyphrase`, `meta_description`, `seo_title` | Yoast SEO metadata (`null` without Yoast) |

Errors: `404` when no published post matches the slug, `401`/`403` when the authenticated user cannot read the post.

## Security

- `permission_callback` requires `edit_posts`; a per-post `read_post` check prevents IDOR on protected content.
- All inputs are sanitized and validated through the REST args schema.

## Development

Single file by design (KISS) — no build step, no dependencies. Part of the [Post Quality ecosystem](https://github.com/vincentheimann/post-quality-manager): the companion **Post Quality Manager** plugin and the n8n workflows that consume this endpoint live in that repository. Changes to this response schema must be checked against those workflows. Agent-facing guidelines are in `CLAUDE.md`.

## License

GPL-2.0-or-later
