# Mantis SEO — Task List for Sub-Agents

> **Generated:** 2026-03-21
> **Instructions:** Each task below is self-contained with file locations, line numbers, and exact fixes. Pick a task, implement the fix, and check it off.

---

## 🔴 Priority 0 — Critical (Fix Immediately)

### TASK-001: Fix JSON-LD XSS vulnerability
- **File:** `includes/class-schema.php`, lines 33–42
- **What:** Remove `JSON_UNESCAPED_SLASHES` flag from `wp_json_encode()` call to prevent `</script>` injection
- **Why:** Authors can inject arbitrary JS via post titles that contain `</script>`
- **How:** Change `JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE` to just `JSON_UNESCAPED_UNICODE`
- **Test:** Verify JSON-LD output still valid; ensure forward slashes in URLs are escaped as `\/`
- [ ] Implemented
- [ ] Tested

### TASK-002: Fix Zip Slip path traversal in deploy.php
- **File:** `deploy.php`, lines 180–183
- **What:** Add zip entry name validation before `ZipArchive::extractTo()`
- **Why:** Malicious zip could write files outside the intended directory via `../` paths
- **How:** Loop through `$zip->numFiles`, check each `getNameIndex()` for `..`, reject if found
- **Test:** Verify deploy still works with normal zip; verify `..` paths are rejected
- [ ] Implemented
- [ ] Tested

---

## 🟠 Priority 1 — High

### TASK-003: Exclude noindex posts from sitemap
- **File:** `includes/class-sitemap-posts.php`, lines 83–91
- **What:** Add `meta_query` to WP_Query that excludes posts where `_mantis_robots_noindex = '1'`
- **Why:** Posts marked noindex shouldn't appear in sitemaps — contradicts robots directive
- **How:** Add to the WP_Query args:
  ```php
  'meta_query' => array(
      array(
          'relation' => 'OR',
          array(
              'key'     => '_mantis_robots_noindex',
              'compare' => 'NOT EXISTS',
          ),
          array(
              'key'     => '_mantis_robots_noindex',
              'value'   => '1',
              'compare' => '!=',
          ),
      ),
  ),
  ```
- [ ] Implemented
- [ ] Tested

### TASK-004: Validate regex patterns on redirect save
- **File:** `includes/class-redirects.php`
- **What:** In `add()` and `update()` methods, validate regex patterns before saving. In `get_match()`, escape the `#` delimiter.
- **Why:** Invalid regex causes ReDoS; unescaped delimiter breaks pattern matching
- **How:**
  1. In `add()` and `update()`: if format is `regex`, test with `@preg_match('#' . str_replace('#', '\#', $origin) . '#', '')` and return false if invalid
  2. In `get_match()`: use `str_replace('#', '\#', $redirect['origin'])` in the preg_match call
- [ ] Implemented
- [ ] Tested

### TASK-005: Protect deploy.log from public access
- **File:** `deploy.php`, line 52
- **What:** Change log location to be outside web root, or use `sys_get_temp_dir()`
- **How:** Change `$log_file = __DIR__ . '/deploy.log'` to `$log_file = sys_get_temp_dir() . '/mantis-seo-deploy.log'`
- [ ] Implemented
- [ ] Tested

### TASK-006: Remove wp-config.php fallback from deploy.php
- **File:** `deploy.php`, lines 27–29
- **What:** Make `deploy-config.php` required; don't fallback to loading `wp-config.php`
- **How:** If config file is missing, output an error and exit instead of loading wp-config.php
- [ ] Implemented
- [ ] Tested

### TASK-007: Extract shared meta save method
- **File:** `admin/class-metabox.php`, lines 336–384 and 389–442
- **What:** Extract duplicated field sanitization/save logic into a private `save_fields_for_post($post_id, $post_type)` method
- **Why:** ~50 lines are copy-pasted between `save_metabox()` and `ajax_save_meta()`; any bug fix must be applied twice
- **How:** Create private method with shared logic; have both callers use it
- [ ] Implemented
- [ ] Tested

---

## 🟡 Priority 2 — Medium

### TASK-008: Cache get_settings() result
- **File:** `includes/class-mantis-seo.php`, `get_settings()` method
- **What:** Add static property caching to avoid repeated `get_option()` + `wp_parse_args()` calls
- **How:** Add `private static $cached_settings = null;` — return cache if set; clear in `save_settings()`
- [ ] Implemented
- [ ] Tested

### TASK-009: Clean up meta on uninstall
- **File:** `uninstall.php`
- **What:** Add deletion of `_mantis_*` post meta and term meta on plugin uninstall
- **How:** Add:
  ```php
  $wpdb->query( "DELETE FROM $wpdb->postmeta WHERE meta_key LIKE '_mantis_%'" );
  $wpdb->query( "DELETE FROM $wpdb->termmeta WHERE meta_key LIKE '_mantis_%'" );
  ```
- [ ] Implemented
- [ ] Tested

### TASK-010: Fix sitemap lastmod to use actual dates
- **Files:** `includes/class-sitemap-posts.php:65` and `includes/class-sitemap-terms.php:110`
- **What:** Replace `current_time('c')` with actual last modification date of newest post in each sitemap segment
- **How:** Query `MAX(post_modified_gmt)` for the post type and use that
- [ ] Implemented
- [ ] Tested

### TASK-011: Fix canonical URL pagination
- **File:** `includes/class-meta.php`, lines 583–586
- **What:** Handle paginated archives in `get_canonical_url()`
- **How:** Use WordPress's `wp_get_canonical_url()` (available since WP 4.6) as fallback or append pagination query vars
- [ ] Implemented
- [ ] Tested

### TASK-012: Replace innerHTML with DOM APIs in media-upload.js
- **File:** `assets/js/media-upload.js`, line 39
- **What:** Replace `innerHTML` with DOM element creation
- **How:** Change to:
  ```javascript
  var img = document.createElement('img');
  img.src = url;
  img.alt = '';
  previewEl.textContent = '';
  previewEl.appendChild(img);
  ```
- [ ] Implemented
- [ ] Tested

### TASK-013: Whitelist redirect status codes
- **File:** `includes/class-redirects.php`
- **What:** Validate the redirect type against allowed HTTP codes before executing `wp_redirect()`
- **How:** Add validation: `$allowed = array(301, 302, 307, 308); if (!in_array($type, $allowed, true)) $type = 301;`
- Also validate in `add()` and `update()` methods
- [ ] Implemented
- [ ] Tested

### TASK-014: Use random_bytes for deploy temp directory
- **File:** `deploy.php`, line 165
- **What:** Replace `uniqid()` with `bin2hex(random_bytes(16))`
- [ ] Implemented
- [ ] Tested

### TASK-015: Add capability check to term meta save
- **File:** `admin/class-metabox.php`, line 513+
- **What:** Add `current_user_can('edit_term', $term_id)` check after nonce verification
- [ ] Implemented
- [ ] Tested

### TASK-016: Consolidate replace_vars maps
- **File:** `includes/class-meta.php`, lines 454–488 and 511–539
- **What:** Make `replace_vars()` call `get_replacements_for_preview()` and layer on pagination
- [ ] Implemented
- [ ] Tested

### TASK-017: Consolidate title resolution logic
- **File:** `includes/class-meta.php`, lines 164–179, 336–348, 364–383, 403–412
- **What:** Create a single `resolve_title($post)` method that all call sites delegate to
- [ ] Implemented
- [ ] Tested

### TASK-018: Cache regex redirects
- **File:** `includes/class-redirects.php`, line 201
- **What:** Add transient caching for regex redirects list, invalidated on add/update/delete
- [ ] Implemented
- [ ] Tested

---

## 🟢 Priority 3 — Low

### TASK-019: Move inline settings JS to external file
- **File:** `admin/class-settings-page.php`, lines 847–889
- **What:** Extract inline `<script>` to `assets/js/admin-settings.js`, enqueue properly
- **How:** Use `wp_enqueue_script()` and `wp_localize_script()` for data
- [ ] Implemented
- [ ] Tested

### TASK-020: Break up render_metabox()
- **File:** `admin/class-metabox.php`, lines 64–224
- **What:** Split into `render_preview()`, `render_tab_nav()`, `render_field()`, etc.
- [ ] Implemented
- [ ] Tested

### TASK-021: Break up settings render()
- **File:** `admin/class-settings-page.php`, lines 496–892
- **What:** Extract each panel into partial files (e.g., `admin/partials/panel-titles.php`)
- [ ] Implemented
- [ ] Tested

### TASK-022: Add redirect list pagination
- **File:** `includes/class-redirects.php`, `get_all()` method + `admin/class-redirects-page.php`
- **What:** Add pagination parameters and paginate the admin listing
- [ ] Implemented
- [ ] Tested

### TASK-023: Batch Yoast migration queries
- **File:** `includes/class-yoast-importer.php`, lines 108–109
- **What:** Process migration in batches of 500 rows using LIMIT/OFFSET
- [ ] Implemented
- [ ] Tested

### TASK-024: Batch CSV import post lookups
- **File:** `admin/class-settings-page.php`, line 304
- **What:** Collect all post IDs, batch-query with `get_posts(['post__in' => $ids])`
- [ ] Implemented
- [ ] Tested

---

## ⚪ Priority 4 — Cleanup

### TASK-025: Unify META_PREFIX constant
- **Files:** `includes/class-meta.php:22` and `admin/class-metabox.php:22`
- **What:** Define in one class, reference from the other
- [ ] Implemented
- [ ] Tested

### TASK-026: Remove unused $commit_msg variable
- **File:** `deploy.php`, line 248
- **What:** Delete the unused assignment
- [ ] Implemented
- [ ] Tested

### TASK-027: Remove redundant $skip_keys check
- **File:** `includes/class-yoast-importer.php`, lines 42–49
- **What:** Remove or add defensive comment
- [ ] Implemented
- [ ] Tested

### TASK-028: Deduplicate dark-mode inline script
- **Files:** `admin/class-settings-page.php:543` and `admin/class-redirects-page.php:120`
- **What:** Move to app-header partial
- [ ] Implemented
- [ ] Tested

### TASK-029: Remove identical textarea/input branch in JS
- **File:** `assets/js/admin-metabox.js`, lines 367–379
- **What:** Remove the unnecessary `if (field.tagName === 'TEXTAREA')` branch — both paths are identical
- [ ] Implemented
- [ ] Tested

### TASK-030: Add deploy rate limiting
- **File:** `deploy.php`
- **What:** Add file-based lock with `flock()` to prevent concurrent deploys
- [ ] Implemented
- [ ] Tested

### TASK-031: Add PHPCS comment for table name interpolation
- **File:** `uninstall.php`, line 15
- **What:** Add `// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared` comment
- [ ] Implemented
- [ ] Tested
