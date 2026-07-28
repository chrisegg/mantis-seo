# Mantis SEO — documentation

**WordPress block editor:** Plain HTML for copy-paste is in [`DOCUMENTATION.wp.html`](DOCUMENTATION.wp.html). Paste into a **Paragraph** block (or use **Ctrl/Cmd+V** in the visual editor); WordPress will usually split headings, lists, and tables into blocks. If paste is stripped, add a **Custom HTML** block and paste there instead. Update links to repo files (`README.md`, `LICENSE`) to your live URLs if you publish this as a page.

---

**Mantis SEO** is a lightweight SEO plugin for WordPress: titles and meta descriptions, canonical URLs and robots directives, Open Graph and X (Twitter) cards, JSON-LD structured data, XML sitemaps, and redirects in one package—without upsells or feature gating.

- **Website:** [mantiseo.com](https://mantiseo.com)
- **Requirements:** WordPress 6.0 or newer, PHP 7.4 or newer (see the `Version:` and `Requires at least` headers in the main plugin file for the authoritative current values).
- **License:** [GNU General Public License v3](LICENSE)

For a short project overview, installation checklist, and repository layout, see [README.md](README.md).

---

## What the plugin does

| Area | Summary |
|------|---------|
| **Titles & meta** | Global templates with variables; per-post and per-term overrides; canonical and robots (`index` / `noindex`, `follow` / `nofollow`). |
| **Social** | Default Open Graph image, X card type, and per-content OG/X title, description, and image URLs. |
| **JSON-LD** | Linked `@graph` (WebSite with optional SearchAction, WebPage, Article on posts, BreadcrumbList, optional Organization). |
| **Sitemaps** | Index and per–post-type XML sitemaps; can be disabled if you use another sitemap solution. |
| **Redirects** | Custom rules (plain or regex), multiple HTTP statuses, optional auto-redirect on slug changes. |
| **Tools** | Yoast SEO migration, settings export/import. |

If **Settings → Reading → “Discourage search engines from indexing this site”** is enabled, administrators see a prominent notice with a link to Reading settings.

---

## Installation and first steps

1. Install the plugin (upload the `mantis-seo` folder to `wp-content/plugins/` or install from a release zip), then activate **Mantis SEO** under **Plugins**.
2. Open **Mantis SEO** in the WordPress admin and work through **Titles & Meta**, **Social**, **Schema**, **Sitemap**, and **Advanced** as needed.
3. After activation or if sitemap URLs return 404s, open **Settings → Permalinks** and click **Save Changes** once so rewrite rules refresh (normal WordPress behavior).

---

## Settings screen (admin tabs)

The main settings UI uses these panels (see `get_settings_panel_ids()` in the plugin code):

| Tab | Purpose |
|-----|---------|
| **Titles & Meta** | Homepage, post, and page **title** and **meta description** templates; **separator** character; **noindex** toggles for author archives, date archives, category archives, and tag archives. |
| **Social** | **Default Open Graph image** (URL, with media picker); **X card type** (`summary` or `summary_large_image`). |
| **Schema** | **Organization** name, URL, logo URL, and profile URLs used for `sameAs` (Facebook, Instagram, X, LinkedIn). |
| **Sitemap** | Turn the XML sitemap **on or off**; choose which **public post types** appear in sitemaps. |
| **Advanced** | Site verification values for **Google** and **Bing** (stored and output as verification meta tags). |
| **Tools** | **Migrate from Yoast SEO** when Yoast data is detected; **export** settings as JSON; **import** from several file formats (see [Migration and import](#migration-and-import)). |

### Default templates (reference)

Out of the box, title templates resemble:

- Homepage: `%site_name% - %site_description%`
- Post: `%post_title% %page% %separator% %site_name%`
- Page: `%post_title% %page% %separator% %site_name%`

Default meta description templates use `%excerpt%` for posts and pages; the homepage description starts empty until you set it.

Default **archive noindex** flags: author and date archives **on**; category and tag archives **off**. Default sitemap: **enabled** for `post` and `page`.

---

## Title and meta template variables

Placeholders use the form `%name%`. They are replaced when titles and descriptions are rendered (and in previews where applicable).

| Variable | Meaning |
|----------|---------|
| `%post_title%` | Current post or page title (or “No title” if empty). In dropdowns this is the primary “post title” pill. |
| `%title%` | Same as `%post_title%` (alias). |
| `%site_name%` | Site title from **Settings → General**. |
| `%sitename%` | Alias for `%site_name%`. |
| `%site_description%` | Tagline from **Settings → General**. |
| `%sitedesc%` | Alias for `%site_description%`. |
| `%separator%` | The separator set under **Titles & Meta** (falls back to `|` if unset in the replacement map before settings load). |
| `%page%` | On the front end, when the main query is paginated (`paged` / `page` query var), expands to a localized “Page *x* of *y*” string; otherwise empty. In some preview contexts it may stay empty. |
| `%date%` | Current date, formatted with the site’s date format option. |
| `%excerpt%` | Manual excerpt if set; otherwise a trimmed plain-text excerpt from post content (about 30 words). |

`%post_title%`, `%title%`, `%excerpt%`, and related post fields only apply when a specific post context exists (e.g. singular content). Homepage and archive templates rely on site-wide variables unless the plugin resolves a dedicated context for that URL.

---

## Homepage vs blog index

- If the front page is a **static page**, that page uses the **page** title and meta description templates (and any per-page SEO overrides).
- The **Homepage** title and description templates apply to the blog posts index: either **Your latest posts** as the front page, or a dedicated **posts page** when a static front page is set.

---

## Post and page editor: SEO Settings

For every **public** post type, the editor includes a **Mantis SEO** meta box with an **SEO Settings** button. That opens a slide-out panel with:

| Tab | Fields |
|-----|--------|
| **Meta** | Focus keyword (reference only—not output as HTML meta), SEO title, meta description, canonical URL, **Noindex** / **Nofollow** checkboxes. |
| **Facebook** | Open Graph title, description, image URL. |
| **X** | X card title, description, image URL. |

A **snippet preview** shows roughly how a search result might look, with character counts for title and description.

You can use **Save SEO** to persist meta via AJAX without reloading. Classic **Update** / **Publish** also saves the same fields. If SEO title or meta description match the effective global template exactly, the plugin may clear the stored override so the default template applies.

### Focus keyword

The focus keyword is saved for your own workflow; it is **not** emitted as a `<meta>` tag.

---

## Categories and tags

On **Categories** and **Tags** add/edit screens, Mantis adds:

- Focus keyword  
- SEO title  
- Meta description  
- **Robots:** Noindex, Nofollow  

There are no separate Facebook/X fields for terms; social tags for term archives use global defaults and fallbacks where applicable.

---

## Structured data (JSON-LD)

The plugin outputs a JSON-LD **`@graph`** with stable `@id` links where appropriate, including:

- **WebSite** (optional **SearchAction** for site search)  
- **WebPage** for the current document  
- **Article** on singular **posts**  
- **BreadcrumbList**  
- **Organization** when organization fields are filled in schema settings  

Open Graph may include `article:published_time` and `article:modified_time` on suitable singular URLs.

---

## XML sitemaps

With pretty permalinks and the sitemap enabled:

| URL | Role |
|-----|------|
| `/sitemap_index.xml` | Sitemap index |
| `/sitemap_{post-type}.xml` | URLs for one enabled public post type |

Submit the **index** URL in Google Search Console and Bing Webmaster Tools. If another plugin or system already serves sitemaps, disable Mantis’s sitemap here to avoid duplicates.

---

## Redirects

Manage rules under **Mantis SEO → Redirects**.

- **Origin:** URL path (or regex pattern if **regex** is selected).  
- **Target:** Full URL **or** site-relative path (for example `/docs/getting-started`).  
- **Type:** 301, 302, 303, 307, 308, 410, or 451.  

**Regex:** Patterns use `~` as the internal PCRE delimiter. A literal tilde in the pattern must be written as `\~`. Invalid regex shows an admin error referring to this rule.

The plugin can **suggest or create redirects** when a published post or page **slug** changes, and trash/delete flows may prompt about redirects where applicable.

---

## Migration and import

### Yoast SEO

When Yoast SEO has data and migration has not already run, **Tools** offers **Migrate from Yoast**. That imports compatible global settings, post/term meta, and redirects. **Deactivate Yoast** after migration so you do not output duplicate meta tags and schema.

### Import file formats (Tools → import)

The importer tries formats in this order:

1. **Mantis JSON** — A file exported via **Download export file** (`mantis_seo_export` + `settings` object).  
2. **Yoast INI-style export** — Text containing `[wpseo…]` sections (Yoast’s export format).  
3. **Yoast Keywords CSV** — Rows that map post IDs to SEO title and meta description; updates per-post `_mantis_*` meta.  
4. **Generic CSV** — Rows as `key,value` or `key;value` mapped to known Mantis setting keys.

Accepted file extensions include `.json`, `.ini`, `.txt`, and `.csv` (see the import field’s `accept` attribute in the admin UI).

---

## Updates (GitHub releases)

Publishing a **GitHub release** builds an installable zip and attaches **`mantis-seo.zip`**. Installed sites can check the latest release and download:

`https://github.com/chrisegg/mantis-seo/releases/download/{tag}/mantis-seo.zip`

Use a release tag whose version aligns with the **`Version:`** header in the main plugin file for that build. Release notes can appear in WordPress “View version details” when provided.

---

## Uninstall and stored data

When the plugin is **deleted** via **Plugins → Delete**, `uninstall.php` runs and:

- Drops the `{wpdb_prefix}mantis_redirects` table.  
- Deletes all **post meta** and **term meta** rows whose keys start with `_mantis_`.  
- Deletes options: `mantis_settings`, `mantis_migrated_from_yoast`, `mantis_redirect_notice`.  

Other options used internally (for example rewrite migration counters) may remain in the database but are harmless; they do not recreate plugin behavior without the plugin active.

---

## Developer snapshot

This section is a quick reference, not a full public API.

| Item | Detail |
|------|--------|
| Main settings | WordPress option `mantis_settings` (array), merged with defaults in `Mantis_SEO_Main::get_settings()`. |
| Post/term SEO meta | Prefix `_mantis_` (see `Mantis_SEO_Meta::META_PREFIX`). Examples: `_mantis_title`, `_mantis_description`, `_mantis_canonical`, `_mantis_og_title`, `_mantis_robots_noindex`. |
| Redirect storage | Table `{prefix}mantis_redirects` (`Mantis_SEO_Redirects::TABLE_NAME`). |
| Capabilities | Settings and redirects: `manage_options`. Saving post SEO: `edit_post` for that post. Term SEO: `edit_term`. |
| Text domain | `mantis-seo` |
| Version constant | `MANTIS_SEO_VERSION` in the bootstrap file |

For coding standards and hooks used internally, follow the existing plugin files and WordPress conventions described in [README.md](README.md) under **Development**.
