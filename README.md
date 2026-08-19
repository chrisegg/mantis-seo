# Mantis SEO

**Lightweight SEO for WordPress** — titles & meta, redirects, JSON-LD schema, XML sitemaps, social tags, and AI/GEO discovery in one plugin. No upsells, no feature gating.

- **Plugin URI:** [mantiseo.com](https://mantiseo.com)
- **Requires:** WordPress 6.0+ · PHP 7.4+
- **License:** [GPL v3](LICENSE)

---

## Features

### Titles & meta

- **Global templates** for homepage, posts, and pages with variable pills (e.g. `%post_title%`, `%separator%`, `%site_name%`, `%excerpt%`).
- **Per-content overrides** in the post/page editor: slide-out **SEO Settings** panel (Meta, Facebook / Open Graph, X / Twitter Cards). The **focus keyword** field is saved for your own reference (it is not output as a meta tag).
- **Canonical URLs** and **robots** (`index`/`noindex`, `follow`/`nofollow`).
- **Open Graph** and **X Card** tags, including default OG image and per-post image URLs.
- **Search engine visibility warning:** if **Settings → Reading → Discourage search engines** is enabled, administrators see a prominent admin notice with a link to Reading settings.

### Structured data (JSON-LD)

- **`@graph`** output with linked `@id`s: **WebSite** (with `SearchAction`), **WebPage**, **Article** (for the `post` post type), **BreadcrumbList**, and optional **Organization** (name, URL, logo, `sameAs` social profiles).
- **OG** `article:published_time` / `article:modified_time` on singular URLs where appropriate.

### XML sitemaps

- **Index:** `https://yoursite.com/sitemap_index.xml`
- **Per post-type** sitemaps (configurable public post types).
- Can be **disabled** in settings if you use another sitemap solution.

### AI / GEO

- **`/llms.txt`** curated Markdown map for AI assistants (optional **`/llms-full.txt`** dump).
- **AI crawler policies** in `robots.txt` (allow/block/inherit per bot: OpenAI, Anthropic, Perplexity, Google-Extended, and more).
- **Brand facts wizard** creates a citation-ready page and links it first in `llms.txt`.

### Redirects

- **301, 302, 303, 307, 308, 410,** and **451** redirects stored in a custom database table. **Regex** origins use a `~` delimiter internally—literal `~` in a pattern must be written as `\~`.
- **Redirect manager** under **Mantis SEO → Redirects**.
- **Automatic redirect** when a published post or page slug changes.
- **Trash / delete** flows integrate with redirect prompts where applicable.

### Tools & migration

- **Yoast SEO:** detect and **migrate** global settings, post/term meta, and redirects (when Yoast data is present).
- **Export / import** plugin settings as JSON from the **Tools** tab.

### Verification

- **Google** and **Bing** site verification meta tags from settings.

---

## Installation

1. Copy the `mantis-seo` folder into `wp-content/plugins/`.
2. In **Plugins**, activate **Mantis SEO**.
3. Visit **Mantis SEO** in the admin menu and configure **Titles & Meta**, **Social**, **Schema**, **Sitemap**, **AI / GEO**, and **Advanced**.
4. After activation or sitemap / llms.txt changes, visit **Settings → Permalinks** and click **Save** once if pretty permalinks or rewrite rules need a refresh (standard WordPress behavior).

---

## Usage

| Area | What to do |
|------|------------|
| **Mantis SEO → Titles & Meta** | Set title templates, meta description templates, separator, and archive **noindex** options (author, date, category, tag). |
| **Social** | Default Open Graph image, X card type (`summary` / `summary_large_image`). |
| **Schema** | Organization name, URL, logo, and `sameAs` URLs for major networks. |
| **Sitemap** | Toggle sitemap on/off and choose included post types. |
| **AI / GEO** | Enable `/llms.txt`, set crawler policies, and run the brand-facts page wizard. |
| **Post / Page editor** | Open **SEO Settings** in the sidebar; edit templates with pills, preview snippet, **Save SEO** (AJAX) without leaving the screen. |
| **Redirects** | Add rules from origin path to target URL; choose status code. |

**Homepage vs blog index:** If the front page is a **static page**, that page uses the **page** templates. The **Homepage description** setting applies to the **posts page** when it is not the front page (`is_home()` && ! `is_front_page()`).

---

## Sitemap URLs

With permalinks enabled and the sitemap turned on:

| URL | Role |
|-----|------|
| `/sitemap_index.xml` | Sitemap index |
| `/sitemap_{post-type}.xml` | Entries for each enabled public post type |

Submit the index URL in Google Search Console and Bing Webmaster Tools.

---

## AI discovery URLs

With permalinks enabled and **AI / GEO → Enable /llms.txt** on:

| URL | Role |
|-----|------|
| `/llms.txt` | Curated Markdown map for AI agents |
| `/llms-full.txt` | Optional expanded Markdown dump (when enabled) |

After enabling, save **Settings → Permalinks** once if these URLs 404.

---

## Yoast SEO migration

If Yoast SEO is installed and has data, the settings screen can offer **Migrate from Yoast**. That imports compatible options and meta keys into Mantis. **Deactivate Yoast** after migration to avoid duplicate meta tags and schema.

---

## Uninstall

Deleting the plugin via **Plugins → Delete** runs `uninstall.php`, which:

- Drops the `{prefix}mantis_redirects` table.
- Removes options: `mantis_settings`, `mantis_migrated_from_yoast`, `mantis_redirect_notice`.

**Post meta** (`_mantis_*`) is not bulk-deleted on uninstall (common pattern so content isn’t silently stripped if you reinstall). Remove manually if you need a clean database.

---

## Project structure

```
mantis-seo/
├── mantis-seo.php          # Bootstrap, constants, hooks
├── uninstall.php
├── admin/                  # Settings UI, metabox, redirects admin
├── includes/               # Meta, schema, sitemap, redirects, AI/llms.txt, Yoast importer
├── assets/                 # admin.css, template editor & metabox JS
└── languages/              # POT for translations
```

---

## Releases & updates

Publishing a **GitHub release** triggers [`.github/workflows/release-plugin.yml`](.github/workflows/release-plugin.yml), which builds an installable zip with a `mantis-seo/` root and uploads **`mantis-seo.zip`** to that release. Installed sites check `GET /repos/chrisegg/mantis-seo/releases/latest` and download:

`https://github.com/chrisegg/mantis-seo/releases/download/{tag}/mantis-seo.zip`

Use a release **tag** whose version (with or without a leading `v`) matches **`Version:`** in `mantis-seo.php` for that release. Release notes on GitHub populate the WordPress “View version details” changelog when present.

---

## Development

- Follow WordPress coding standards and capability checks (`manage_options` for settings, `edit_post` for metabox saves).
- Text domain: **`mantis-seo`**.
- Version constant: **`MANTIS_SEO_VERSION`** in the main plugin file.

---

## Credits

**Author:** Chris Eggleston — [mantiseo.com](https://mantiseo.com)

---

## License

This program is free software: you can redistribute it and/or modify it under the terms of the **GNU General Public License** as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version. See [LICENSE](LICENSE).
