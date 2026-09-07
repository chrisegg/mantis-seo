# Changelog

## Unreleased

### Redirects

- **Trailing slashes:** A single plain redirect now matches both `/page-name` and `/page-name/`. Regex rules are tested against both slash variants of the request path, so you no longer need a second rule for the trailing-slash form.

### REST API

- **Per-post SEO meta:** Public post types expose Mantis SEO fields (`_mantis_*`) under the core REST `meta` object so authenticated editors/agents can read and update titles, descriptions, social, robots, and schema overrides via `/wp-json/wp/v2/posts` and `/wp-json/wp/v2/pages` (and other public types). Updates require `edit_post` for the target post.

### Abilities / MCP

- **SEO abilities + opt-in toggles:** When the WordPress Abilities API is available (6.9+ or package), registers `mantis-seo/posts-list-seo`, `mantis-seo/post-get-seo`, `mantis-seo/post-update-seo`, and `mantis-seo/settings-get` with REST + MCP exposure. **Advanced → Abilities & MCP** adds a master switch and per-tool checkboxes (all off by default). No abilities are registered until enabled.

## 1.0.0-beta-1.1

### Redirects

- **Path/slug targets:** Redirect targets can be saved as full URLs or site-relative paths (for example `/category/docs`). The admin form no longer rejects or clears path-only values (HTML `type="url"` and `esc_url()` / `esc_url_raw()` on save were causing empty targets and failed saves). List “open target” links resolve relative paths to the live site URL.
- **Internals:** Target handling uses `Mantis_SEO_Redirects::sanitize_target()` for storage and `resolve_target_url()` for front-end redirects and admin links.

## 1.0.0-beta-1

**First release (beta).** Mantis SEO is a lightweight WordPress SEO plugin: titles & meta, Open Graph & X Cards, JSON-LD schema, XML sitemaps, and redirects—including Yoast migration and GitHub-based updates—in one package, without upsells.

Everything in this version is new.
This build is **beta**: behavior and features may still change before **1.0.0**.

Feedback welcome.
