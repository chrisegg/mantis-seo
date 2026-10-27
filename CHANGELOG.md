# Changelog

## Unreleased

### Redirects

- **Regex origins:** Lookbehinds (`(?<=`, `(?<!`) and named groups (`(?<name>`) can be saved from the redirects screen. Origins were passed through `sanitize_text_field()` before the format was known, which turned `<` into `&lt;` and then failed regex validation. Regex origins now keep those characters. Plain origins are still sanitized as text. The same helper runs for the form, slug changes, and the Yoast importer.

## 1.0.3

### Updates

- **Update notices:** WordPress never listed an update. The checker bailed unless the update transient already had a `checked` list, which WordPress 6.0–6.7 does not set on the save that matters, and it did not answer the `update_plugins_github.com` hook the `Update URI` header uses. Both paths now report the latest GitHub release, including from wp-cron.
- **Private repository:** Unauthenticated requests to this private repo get a 404, so there was never a version to compare. A read-only token in `wp-config.php` (`MANTIS_SEO_GITHUB_TOKEN`) is sent on the release check and the zip download. The Plugins and Updates screens say so when GitHub still refuses the check.

## 1.0.2

### Social images

- **Featured image fallback:** `og:image` went straight from the post's own Open Graph image to the site default, so the featured-image fallback never ran and every post without a custom OG image shared the site default (Facebook, LinkedIn, Slack and iMessage all read `og:image`). Both tags now use the first image that exists:
  - `og:image`: post OG image → featured image → post X image → site default.
  - `twitter:image`: post X image → post OG image → featured image → site default.
- **Missing attachments:** An image stored as an attachment ID that doesn't exist on the site (for example after copying content from another install) is skipped instead of producing an empty tag.

## 1.0.1

### Titles and meta

- **Dedicated posts page:** When Settings → Reading uses a separate page for posts, that page now gets its own title, meta description, robots and canonical from the page itself. It used to reuse the home page's title template and description, so the blog page and the home page showed the same title in search results.

## 1.0.0

First production release.

### Sitemap

- **Underscored post types and taxonomies:** Child sitemaps for post types or taxonomies with an underscore in their name (for example `my_type`, served at `/sitemap_my_types.xml`) returned 404 because the type was split on the first underscore to find a page number. Only a trailing `_<number>` is treated as a page now, and that page number is honoured when the generic rewrite rule catches a paged URL.
- **Common sitemap URLs:** While the Mantis sitemap is enabled, `/sitemap.xml`, `/wp-sitemap.xml`, and `/sitemap` 301 to `/sitemap_index.xml` (including on subdirectory installs).
- **`mantis_sitemap_posts_query_args` filter:** Other code can narrow which posts of a type are listed.

### llms.txt

- **Shortcodes:** Full-text entries strip shortcodes before tags, so raw `[shortcode]` markup is not left in the file.

### Titles

- **No double spaces:** Templates like `%post_title% %page% %separator% %site_name%` rendered `About  | Site` with two spaces whenever there was no page number. Resolved titles and descriptions now collapse repeated spaces.
- **No dangling separator:** An empty tagline in `%site_name% %separator% %site_description%` no longer leaves a trailing `|` or `-`.

### Meta

- **One robots tag:** Noindex and nofollow are merged into WordPress core's `wp_robots` output instead of printing a second `<meta name="robots">`.
- **One canonical:** Core's `rel_canonical` is removed because Mantis already prints the canonical link.
- **`mantis_seo_meta` filter:** Other code can adjust the title, description, robots, and canonical for the current request before Open Graph and X tags are built. The document title uses that filtered value.
- **Excerpts:** The `%excerpt%` variable strips shortcodes before it trims the post content.

### Schema

- **No HTML entities in JSON-LD:** The site name and page titles arrived HTML-escaped (`Pool &amp; Spa`, `Joe&#8217;s`) and were printed that way in structured data, which search engines read literally. Every string in the graph is decoded before encoding, and `<` and `>` are hex-escaped so a decoded title can't open a tag inside the script.

## 1.0.0-beta-1.2

### AI / GEO

- **New settings tab:** **AI / GEO** for optimizing discovery by AI assistants and crawlers.
- **llms.txt:** Serves a curated Markdown map at `/llms.txt` (optional `/llms-full.txt`) with site summary, brand-facts page, priority pages, and extra links.
- **AI crawler policies:** Optional `robots.txt` rules for search vs training bots (OAI-SearchBot, GPTBot, Claude-*, Perplexity*, Google-Extended, and others).
- **Brand facts wizard:** Guided form creates (or updates) a citation-ready WordPress page and links it first in `llms.txt`.

### Redirects

- **Trailing slashes:** A single plain redirect now matches both `/page-name` and `/page-name/`. Regex rules are tested against both slash variants of the request path, so you no longer need a second rule for the trailing-slash form.
- **Admin list:** The redirects table shows **20** rules per page, with Previous/Next pagination under the table.
- **Search:** Filter the list by origin, target, type, or format.

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
