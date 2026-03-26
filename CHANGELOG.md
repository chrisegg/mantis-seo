# Changelog

## 1.0.0-beta-1.1

### Redirects

- **Path/slug targets:** Redirect targets can be saved as full URLs or site-relative paths (for example `/category/docs`). The admin form no longer rejects or clears path-only values (HTML `type="url"` and `esc_url()` / `esc_url_raw()` on save were causing empty targets and failed saves). List “open target” links resolve relative paths to the live site URL.
- **Internals:** Target handling uses `Mantis_SEO_Redirects::sanitize_target()` for storage and `resolve_target_url()` for front-end redirects and admin links.

## 1.0.0-beta-1

**First release (beta).** Mantis SEO is a lightweight WordPress SEO plugin: titles & meta, Open Graph & X Cards, JSON-LD schema, XML sitemaps, and redirects—including Yoast migration and GitHub-based updates—in one package, without upsells.

Everything in this version is new.
This build is **beta**: behavior and features may still change before **1.0.0**.

Feedback welcome.
