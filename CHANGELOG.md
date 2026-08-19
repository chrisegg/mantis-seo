# Changelog

## 1.0.0-beta-1.2

### AI / GEO

- **New settings tab:** **AI / GEO** for optimizing discovery by AI assistants and crawlers.
- **llms.txt:** Serves a curated Markdown map at `/llms.txt` (optional `/llms-full.txt`) with site summary, brand-facts page, priority pages, and extra links.
- **AI crawler policies:** Optional `robots.txt` rules for search vs training bots (OAI-SearchBot, GPTBot, Claude-*, Perplexity*, Google-Extended, and others).
- **Brand facts wizard:** Guided form creates (or updates) a citation-ready WordPress page and links it first in `llms.txt`.

## 1.0.0-beta-1.1

### Redirects

- **Path/slug targets:** Redirect targets can be saved as full URLs or site-relative paths (for example `/category/docs`). The admin form no longer rejects or clears path-only values (HTML `type="url"` and `esc_url()` / `esc_url_raw()` on save were causing empty targets and failed saves). List “open target” links resolve relative paths to the live site URL.
- **Internals:** Target handling uses `Mantis_SEO_Redirects::sanitize_target()` for storage and `resolve_target_url()` for front-end redirects and admin links.

## 1.0.0-beta-1

**First release (beta).** Mantis SEO is a lightweight WordPress SEO plugin: titles & meta, Open Graph & X Cards, JSON-LD schema, XML sitemaps, and redirects—including Yoast migration and GitHub-based updates—in one package, without upsells.

Everything in this version is new.
This build is **beta**: behavior and features may still change before **1.0.0**.

Feedback welcome.
