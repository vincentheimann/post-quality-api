# Post Quality API — Claude Code entry point

Minimal single-file WordPress plugin (`post_quality_api.php`): one REST endpoint under the `post-quality/v1` namespace exposing post content, metadata, and Yoast SEO scores for external integrations (n8n, Zapier, Make).

## Rules

- Keep it a single file — no build step, no dependencies, KISS.
- Prefix everything `PQA_` / `pqa_`; sanitize input and escape output with WP core functions; every REST route needs a real `permission_callback`.
- PHP 8.0+, WordPress 6.0+. Never commit directly to `main`; Conventional Commits.
- The full engineering guidelines for this ecosystem live in `../post-quality-manager/` (AGENTS.md / WORKFLOW.md); consumers of this endpoint are the n8n workflows in `../post-quality-manager/n8n/`. Breaking changes to the response schema break those workflows — flag them.
