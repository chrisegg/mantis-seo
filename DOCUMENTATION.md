# Mantis SEO — documentation

**WordPress block editor:** Plain HTML for copy-paste is in [`DOCUMENTATION.wp.html`](DOCUMENTATION.wp.html). Paste into a **Paragraph** block (or use **Ctrl/Cmd+V** in the visual editor); WordPress will usually split headings, lists, and tables into blocks. If paste is stripped, add a **Custom HTML** block and paste there instead. Update links to repo files (`README.md`, `LICENSE`) to your live URLs if you publish this as a page.

---

**Mantis SEO** is a lightweight SEO plugin for WordPress: titles and meta descriptions, canonical URLs and robots directives, Open Graph and X (Twitter) cards, JSON-LD structured data, XML sitemaps, redirects, and AI/GEO discovery (`llms.txt` and crawler policies) in one package—without upsells or feature gating.

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
| **AI / GEO** | `/llms.txt` (and optional `/llms-full.txt`), AI crawler `robots.txt` policies, guided brand-facts page wizard. |
| **Redirects** | Custom rules (plain or regex), multiple HTTP statuses, optional auto-redirect on slug changes. |
| **Tools** | Yoast SEO migration, settings export/import. |

If **Settings → Reading → “Discourage search engines from indexing this site”** is enabled, administrators see a prominent notice with a link to Reading settings.

---

## Installation and first steps

1. Install the plugin (upload the `mantis-seo` folder to `wp-content/plugins/` or install from a release zip), then activate **Mantis SEO** under **Plugins**.
2. Open **Mantis SEO** in the WordPress admin and work through **Titles & Meta**, **Social**, **Schema**, **Sitemap**, **AI / GEO**, and **Advanced** as needed.
3. After activation or if sitemap / `llms.txt` URLs return 404s, open **Settings → Permalinks** and click **Save Changes** once so rewrite rules refresh (normal WordPress behavior).

---

## Settings screen (admin tabs)

The main settings UI uses these panels (see `get_settings_panel_ids()` in the plugin code):

| Tab | Purpose |
|-----|---------|
| **Titles & Meta** | Homepage, post, and page **title** and **meta description** templates; **separator** character; **noindex** toggles for author archives, date archives, category archives, and tag archives. |
| **Social** | **Default Open Graph image** (URL, with media picker); **X card type** (`summary` or `summary_large_image`). |
| **Schema** | **Organization** name, URL, logo URL, and profile URLs used for `sameAs` (Facebook, Instagram, X, LinkedIn). |
| **Sitemap** | Turn the XML sitemap **on or off**; choose which **public post types** appear in sitemaps. |
| **AI / GEO** | Enable **`/llms.txt`** (and optional **`/llms-full.txt`**); site summary; priority pages and extra links; **AI crawler** allow/block/inherit policies; **brand facts page** wizard. |
| **Advanced** | Site verification values for **Google** and **Bing**; **Abilities & MCP** master switch and per-tool opt-in for AI agents. |
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

## AI / GEO

Use the **AI / GEO** tab to help assistants find accurate brand facts (sometimes called generative engine optimization).

### llms.txt

When enabled, Mantis serves:

| URL | Role |
|-----|------|
| `/llms.txt` | Short curated Markdown map (site summary, brand-facts page, priority pages, optional extra links) |
| `/llms-full.txt` | Optional longer Markdown dump of the facts page and selected priority pages |

`robots.txt` can include a comment pointing at `/llms.txt`. After enabling, save **Permalinks** once if the URLs 404.

### AI crawler policies

Optional groups in `robots.txt` for known AI user-agents. Each bot can be **Allow**, **Block**, or **No specific rule**. Defaults favor allowing search/citation bots (for example OAI-SearchBot, Claude-SearchBot, PerplexityBot) while leaving most training crawlers without a forced rule (or blocking common scrapers such as CCBot / Bytespider).

Training vs search is a policy choice: blocking GPTBot does not by itself block ChatGPT search indexing via OAI-SearchBot.

### Brand facts page wizard

The wizard creates (or updates) a published WordPress page with citation-friendly sections (who you are, who you serve, offers, key facts, preferred citation, contact). Mantis stores the page ID in settings, lists it first under **Brand facts for AI** in `llms.txt`, and can copy the summary into the llms.txt site summary when empty.

---

## Redirects

Manage rules under **Mantis SEO → Redirects**.

- **Origin:** URL path (or regex pattern if **regex** is selected). Plain origins match with or without a trailing slash (a rule for `/old-page` also matches `/old-page/`, and the reverse). If both forms are stored, the exact requested path wins.  
- **Target:** Full URL **or** site-relative path (for example `/docs/getting-started`).  
- **Type:** 301, 302, 303, 307, 308, 410, or 451.  

**Regex:** Patterns use `~` as the internal PCRE delimiter. A literal tilde in the pattern must be written as `\~`. Invalid regex shows an admin error referring to this rule. Request paths are also tried with and without a trailing slash (exact path first), so a pattern that anchors on one form still matches the other.

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

## REST API (per-post SEO meta)

Per-post SEO fields are registered with `show_in_rest` for every **public** post type (same set as the editor metabox). Authenticated clients that can `edit_post` for a given post may read and update them through the core posts REST routes under the `meta` object.

**Auth:** Use an Application Password (or another REST-capable auth method) for a user who can edit the target posts.

**Endpoints (examples):**

- List/read: `GET /wp-json/wp/v2/pages/{id}` or `GET /wp-json/wp/v2/posts/{id}`
- Update: `POST` / `PUT` / `PATCH` the same URL with a `meta` payload

**Example update body:**

```json
{
  "meta": {
    "_mantis_title": "Example SEO Title",
    "_mantis_description": "A concise meta description for search results.",
    "_mantis_focus_keyword": "example keyword",
    "_mantis_robots_noindex": "0",
    "_mantis_robots_nofollow": "0"
  }
}
```

**Registered meta keys** (all strings; robots flags are `"1"` or `"0"`):

| Key | Purpose |
|-----|---------|
| `_mantis_focus_keyword` | Focus keyword |
| `_mantis_title` | SEO title override (empty = use global template) |
| `_mantis_description` | Meta description override (empty = use global template) |
| `_mantis_canonical` | Canonical URL |
| `_mantis_robots_noindex` | `"1"` = noindex, `"0"` = index |
| `_mantis_robots_nofollow` | `"1"` = nofollow, `"0"` = follow |
| `_mantis_og_title` | Open Graph title |
| `_mantis_og_description` | Open Graph description |
| `_mantis_og_image` | Open Graph image URL |
| `_mantis_twitter_title` | X title |
| `_mantis_twitter_description` | X description |
| `_mantis_twitter_image` | X image URL |
| `_mantis_schema_page_type` | Schema.org page `@type` (empty = default; allowlisted values only) |
| `_mantis_schema_article_type` | Schema.org article `@type` (empty = default; allowlisted values only) |

Registration lives in `Mantis_SEO_Rest_Meta` (`includes/class-rest-meta.php`). Term SEO meta is **not** exposed via REST in this version.

---

## Abilities API / MCP (agent tools)

When `wp_register_ability()` is available (WordPress 6.9+ or the Abilities API package), `Mantis_SEO_Abilities` (`includes/class-abilities.php`) can register category `mantis-seo` and these abilities (also exposed for MCP when the WordPress MCP adapter is present):

| Ability | Purpose |
|---------|---------|
| `mantis-seo/posts-list-seo` | List posts/pages with SEO summary; `missing_only` finds gaps |
| `mantis-seo/post-get-seo` | One post: content excerpt + current SEO + resolved title/description |
| `mantis-seo/post-update-seo` | Write SEO fields (`force` to overwrite existing title/description) |
| `mantis-seo/settings-get` | Read global title/metadesc templates (readonly) |

**Enable in admin:** **Mantis SEO → Advanced → Abilities & MCP**. Turn on **Enable MCP / Abilities**, then opt in to each tool (read-only and write groups). Everything defaults to **off**; nothing is registered until both the master switch and the specific tool are enabled.

Updates require `edit_post` for the target post. On older WordPress without the Abilities API, registration is skipped and core REST `meta` remains available.

---

## Developer snapshot

This section is a quick reference, not a full public API.

| Item | Detail |
|------|--------|
| Main settings | WordPress option `mantis_settings` (array), merged with defaults in `Mantis_SEO_Main::get_settings()`. |
| Post/term SEO meta | Prefix `_mantis_` (see `Mantis_SEO_Meta::META_PREFIX`). Examples: `_mantis_title`, `_mantis_description`, `_mantis_canonical`, `_mantis_og_title`, `_mantis_robots_noindex`. |
| REST (post SEO) | Core `wp/v2` post/page `meta` keys above; `edit_post` required to update. See **REST API (per-post SEO meta)**. |
| Abilities / MCP | Settings: `mcp_enabled`, `mcp_abilities` (per-tool flags). Abilities register only when enabled. See **Abilities API / MCP**. |
| Redirect storage | Table `{prefix}mantis_redirects` (`Mantis_SEO_Redirects::TABLE_NAME`). |
| Capabilities | Settings and redirects: `manage_options`. Saving post SEO: `edit_post` for that post. Term SEO: `edit_term`. |
| AI discovery | `Mantis_SEO_LLMs_Txt`, `Mantis_SEO_AI_Bots`, `Mantis_SEO_AI_Page`. Settings keys: `ai_llms_*`, `ai_page_id`, `ai_robots_enabled`, `ai_bot_policies`. |
| Text domain | `mantis-seo` |
| Version constant | `MANTIS_SEO_VERSION` in the bootstrap file |

For coding standards and hooks used internally, follow the existing plugin files and WordPress conventions described in [README.md](README.md) under **Development**.
