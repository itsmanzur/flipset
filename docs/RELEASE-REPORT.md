# Foliora 1.0.0 — Release readiness report

Date: 2026-10-08. Scope: verify by running the plugin, fix what breaks, add no features.

## Environment used

Docker is **not installed**, so `wp-env` was not possible. Everything ran on the existing LocalWP site instead.

| Item | Value |
|---|---|
| Site | `themezur.local` (LocalWP), WordPress 7.1.2, PHP 8.5.1, MySQL 8.4, WP_DEBUG + WP_DEBUG_LOG on |
| Tooling | WP-CLI 2.12 (Local's bundled), Plugin Check 2.0.0 (already installed on the site), PHPCS 3.x + WPCS 3.1 + PHPCompatibilityWP (installed outside the repo), built-in Chromium browser pane |
| Other active plugins | WooCommerce, DearFlip Lite 2.4.37, Tourivo, others |
| Destructive tests | Run on a throwaway clone (`local_rt` DB + copy of `uploads`), **dropped and deleted afterwards**. The real DB only received short-lived test pages, which were deleted. |

Browser tests used the pane's mobile emulation and synthetic touch events, not a physical phone.

## STEP 1 — Static checks

| # | Check | Result | Evidence |
|---|---|---|---|
| 1 | `php -l` on every PHP file | PASS | 54 files, 0 failures. JS files also pass `node -c`. |
| 2 | PHPCS WordPress-Extra + WordPress-Docs + PHPCompatibilityWP (7.4+) | PASS for everything that matters; docblock-only errors remain | 0 security / escaping / nonce / SQL / i18n / compatibility errors. 197 style errors auto-fixed (PHPCBF), 6 more fixed by hand. **170 errors remain, all "missing docblock text"** (see decisions below). |
| 3 | `wp plugin check foliora` (all default categories) | PASS | 0 errors and 0 warnings in shipped files. The tool scans the whole working tree, so 30 findings appear for files **not in the zip** (`bin/`, `tests/`, `.github`, hidden files, `*.zip`, `dist/`). |
| 4 | No remote requests / CDN / tracking | PASS | See URL table below. `fetch()` is only used for same-origin `admin-ajax.php` and the library pager. No `wp_remote_*`, no `curl`, no analytics, no web fonts. |

### Warning / error decisions

| Finding | Decision | Reason |
|---|---|---|
| `Squiz.Commenting.*` missing docblocks (84 function, 53 short-description, 20 class, 13 param) | **Keep** | WordPress-Docs is stricter than WordPress.org requires. Auto-generated placeholder docs would add noise. Worth doing by hand later. |
| `ValidHookName.UseUnderscores` (19) | **Keep** | Hooks such as `foliora/viewer_atts` are the public API for Foliora Pro and are documented in `docs/HOOKS.md`. Renaming would break the Pro contract. |
| `UnusedFunctionParameter` (7) | **Keep** | Required callback signatures (WP_Block args, filters). |
| `NoReservedKeywordParameterNames` (`$default`, `$do`) (5) | **Keep** | Internal helper/callback names; renaming gains nothing. |
| `load_plugin_textdomain` discouraged (Plugin Check) | **Keep, annotated** | Only reached on WP < 6.1 (no textdomain registry), where bundled `/languages` files are otherwise never read. `phpcs:ignore` with the reason added in `foliora.php`. |
| `base64_decode` discouraged | **Keep, annotated** | Decodes the admin-posted PNG data URL; strict mode plus a full PNG signature check follow. |
| Plugin Check `missing_direct_file_access_protection` for `uninstall.php` | Not an issue | It fires for the copy in `dist/foliora/` (my build stage); Plugin Check explicitly skips the real root `uninstall.php`. |

### External URLs found in shipped PHP/JS/JSON (outside `assets/vendor`)

| Host | Where / why | Contacted at load? |
|---|---|---|
| `thereadscope.com` | Plugin URI/author, "Powered by Foliora" link, upgrade link | No (user clicks) |
| `twitter.com`, `facebook.com`, `linkedin.com`, `api.whatsapp.com` | Share dialog `href`s built on click | No (user clicks) |
| `schema.org` | JSON-LD `@context` string | No |
| `schemas.wp.org` | `$schema` key in `block.json` | No |
| `www.w3.org` | SVG `xmlns` | No |
| `www.gnu.org` | License URI in header | No |
| `example.com`, `site.com`, `yourdomain.com` | Placeholder examples in the docs/builder UI | No |

## STEP 2 — Runtime sanity (debug.log watched)

| Action | Result | Evidence |
|---|---|---|
| Activate → deactivate → activate → deactivate → activate | PASS | No Foliora lines in `debug.log`. |
| Dashboard, Shortcode Builder, Docs & Features, Settings (rendered as admin, `bn_BD`) | PASS | 0 PHP notices/warnings from Foliora on all four. |
| Post with shortcode, block, flip view, saved `id`, library shortcode + block, bare `.pdf` URL | PASS | All rendered viewer markup, 0 PHP errors. `[foliora]` without a file and `id="999999"` show the editor-only notice. |
| Front end in browser (shortcode, flip, scroll/spread cycle) | PASS | 0 JS console errors, 0 unhandled rejections. |
| `debug.log` after the whole session | PASS | New entries are only from WooCommerce, WP core and Elementor, triggered by my CLI harness having no admin screen. Nothing from Foliora. |

## STEP 3 — Functional checklist

| | Item | Result | Evidence |
|---|---|---|---|
| a | Upload PDF → Embed → copy shortcode → paste → renders | NOT TESTED (UI) | Needs wp-admin login. Parts verified separately: `foliora_save_embed` AJAX returns OK, `[foliora file]` renders. |
| b | Page / scroll / spread / flip modes | PASS | Toolbar view button cycled all four, `data-view` updated, no errors. |
| b | Flip sound on/off | NOT TESTED | Cannot listen to audio. Flip cycled with no errors. |
| c | Light / dark / sepia | PASS | Theme button cycled `light → dark → sepia → light`. |
| c | Keyboard navigation | PASS | ArrowRight/Left, Home, End. |
| c | Search inside PDF | PASS | Query `e` → "8 of 70", 11 spans highlighted, jumped to page. |
| c | Thumbnails, TOC | PASS | 7 thumbs (lazy-rendered), TOC 11 items + jump. |
| c | Download / print flags | PASS (download) / NOT TESTED (print) | Download link has `download` attr and correct href; flags toggle buttons. Print opens a new window — not exercised. |
| c | Fullscreen, presentation | NOT TESTED | Need a real user gesture; the buttons are also off in this site's saved settings. |
| c | Touch pinch on mobile viewport | PASS | Synthetic two-finger pinch: 300% then 94%, transform cleared, swipe → next page. Not a physical device. |
| d | Password-protected PDF | **FAIL → fixed → PASS** | Before: generic "could not be loaded". After: prompt, wrong password shows "Incorrect password.", correct password opens (7 pages). |
| e | `[foliora id="N"]` | PASS | Renders with saved file/view/theme. |
| e | Block with `id` attribute | **FAIL → fixed → PASS** | Before: "no file URL" notice. After: renders; explicit attrs (e.g. width) still override. |
| e | Edit saved embed → front end updates | PASS | Changed `view` to `scroll`; both block and shortcode followed. |
| e | Saved Embeds manager Duplicate / Delete | **FAIL → fixed → PASS** | Both buttons always errored; see bug 3. Verified via AJAX harness. |
| f | Bare `.pdf` URL auto-embed | PASS | Embeds on its own line; an inline URL inside a sentence is left as text. |
| f | PDF attachment page | PASS | Viewer on a PDF attachment query; image attachments untouched; off when the setting is off. |
| g | WP search finds a word inside the PDF | PASS | Host page found; absent-word control returns 0; Documents-library search also finds it. |
| g | Open Graph + JSON-LD | PASS | `og:image` from the generated thumbnail + 7 more tags; `DigitalDocument` JSON-LD valid. |
| g | OG skipped when Yoast / Rank Math active | PASS (simulated) / NOT TESTED (real plugins) | Defining `WPSEO_VERSION` suppresses output. The real plugins are not installed. |
| h | No fatal without Elementor / Divi / Beaver | PASS | Plugin loads and all screens render with none active. |
| h | Elementor widgets appear | PASS | `foliora-viewer`, `foliora-library`, "Foliora" category; both render markup. (Tested on the clone, since Elementor is inactive on the real site.) |
| h | Divi, Beaver Builder widgets | NOT TESTED | Not installed. |
| i | Conflict with another flipbook plugin (DearFlip Lite) | PASS | Both on one page: 0 errors, both work, distinct script handles. Both share `window.pdfjsLib` (same 3.11.174); DearFlip repoints `workerSrc` to its own copy, which is compatible. |
| j | Page-cache plugin | NOT TESTED | None installed. No Foliora nonce or per-user data is printed on the front end (only i18n + worker URL). |
| k | `bn_BD` admin | PASS | All 4 admin screens: 0 untranslated UI strings after the fix below. |
| k | `bn_BD` front end | PASS | 24/24 toolbar labels and all viewer i18n strings Bengali (forced locale on the clone — see open items). |
| l | Uninstall | PASS | See table below. |
| m | Capabilities and nonces | PASS after fix | Matrix below. |

### Uninstall (clone DB)

| Data | Before | After |
|---|---|---|
| `foliora_settings` option | 1 | 0 |
| `foliora_activation_redirect` transient | 1 | 0 |
| `foliora_embed` posts | 27 | 0 |
| Foliora post meta rows (9 keys) | 19 | 0 |
| `foliora_welcome_dismissed` user meta | 1 | 0 |
| Foliora-generated thumbnail attachments + files | 2 | 0 |
| **User PDFs, other media, pages/posts** | 2 / 28 / 16 | 2 / 28 / 16 (kept) |

### Capability / nonce matrix (every AJAX action)

Admin succeeded on all 10 actions. Author, Contributor and Subscriber were denied on all actions except the three `upload_files` ones (thumbnail, extracted text, a11y), where an Author is allowed **on their own PDFs only** after bug 5 below. A bad nonce or a missing nonce returned `-1` for every action. There are no `wp_ajax_nopriv_foliora_*` handlers, so logged-out requests never reach Foliora code.

## Bugs found and fixed

| # | Bug | Fix (file) |
|---|---|---|
| 1 | Dashboard "PDFs" card showed 0 while PDFs existed, and the "Upload a PDF" onboarding step stayed unchecked. `wp_count_attachments()` is keyed by MIME type, but the code read `->inherit`. | `includes/class-foliora-admin.php` `count_pdfs()` now counts with a `WP_Query` (no stale cache). Card shows 2 and the step is done. |
| 2 | Bengali admin showed English for "Shortcode Builder", "Docs & Features", "Reader font & custom color themes" and ~380 more strings. The `.pot` was stale (220 strings) and the `bn_BD` PO/MO lacked 386 of 584 strings. | Regenerated `languages/foliora.pot` (591 strings), translated all missing strings (`bin/bn-extra.json`, merged by `bin/build-bn-po.js`), rebuilt `.po`, `.mo`, JSON. |
| 3 | Saved Embeds manager Duplicate and Delete always failed: JS sent `embed_id`, handlers read `id`. | `includes/class-foliora-embeds.php` handlers read `embed_id` (fallback `id`). |
| 4 | `<!-- wp:foliora/viewer {"id":N} -->` rendered "no file URL". `block.json` defaults overwrote the saved embed's config. | `includes/class-foliora-block.php` passes only the attributes the author set when `id` is present. |
| 5 | Any Author could overwrite thumbnail / extracted text / a11y flag of **another user's** PDF (capability check only, no object check). | `edit_post` check added in `class-foliora-thumbnails.php`, `class-foliora-seo.php`, `class-foliora-a11y.php`. |
| 6 | Password-protected PDFs never showed the prompt: `onPassword` was passed inside `getDocument()` params, where pdf.js ignores it. | `assets/js/foliora-viewer.js` sets `loadingTask.onPassword`. |
| 7 | Admin JS used `t('selectPdf', …)` for unrelated messages (alerts and the preview placeholder showed "Select PDF"), and had un-localized strings (`Saving…`, `Saved! … copied`, delete confirm, error alerts). | `assets/js/foliora-admin.js` + 14 new localized keys in `class-foliora-admin.php`. |
| 8 | Uninstall left data behind (a11y/thumbnail meta, thumbnail attachments, user meta, transient, saved embeds). | `uninstall.php` cleans all of it; user media is kept. |
| 9 | Bundled translations never loaded on WP 6.0–6.6. | `foliora.php` falls back to `load_plugin_textdomain()` (annotated). |
| 10 | Zip used backslash paths (breaks Linux extraction) and shipped `.editorconfig` / `.gitattributes`. | `bin/build-zip.js` writes forward slashes and excludes both. |
| 11 | Style: loop `count()`, lonely `if`, missing `@param`, comment endings, 200 whitespace/alignment errors. | PHPCBF + manual edits across `includes/`. |

### Step 4 items from the live site

1. **"Foliora" shown twice in the sidebar:** not reproducible in the current code. Built the admin menu programmatically: exactly one top-level item (`Foliora`), submenu `Dashboard / Shortcode Builder / Docs & Features / Settings`. The `foliora_embed` CPT has `show_in_menu => false` and `show_ui => false`. No other plugin registers a Foliora menu, and there is only one copy of the plugin (the `dist/` copy is three levels deep and not loadable). If it still appears, it was an older build; please send a screenshot.
2. **"0 PDF" card:** bug 1.
3. **English strings on a Bengali site:** bug 2 (strings were already wrapped in `__()`, the translations were missing). Plus bug 7 for the admin JS.
4. **Readme and header untouched.** Only the Plugin Check annotation inside `foliora.php` changed; no header or readme edits.

## Still open

1. **WordPress-Docs docblocks** (170 errors) — cosmetic; add by hand if you want a clean PHPCS run.
2. **NOT TESTED:** real Yoast / Rank Math, Divi, Beaver Builder, a page-cache plugin, flip sound, fullscreen / presentation, the print window (now prints multiple pages in spread/scroll), the wp-admin click-through (upload → Embed → copy), block editor in `bn_BD`, physical touch device.
3. **Not run:** PHPUnit and Playwright suites (they need `wp-env`/Docker).
4. **Site language:** `WPLANG` on the real site was `bn_BD` at the start and is now empty. I did not change it; the front-end language check used a forced locale on the clone.
5. **Saved-settings bug (found and fixed afterwards):** a fresh install activated from wp-admin, and the dashboard "copy shortcode" click, stored every `allow_*`, `index_pdf_text`, `social_preview` and `check_pdf_a11y` toggle as **off**. The settings sanitizer treated any array containing `default_width` as a submitted form. It now needs an explicit `_foliora_form` marker that only the Settings screen posts. Sites already affected (like this dev site) keep their stored `false` values and must re-tick the boxes once in Foliora → Settings.
6. **Bengali PDF search:** matching depends on the PDF's text layer. The sample PDF's extracted text is visual-order with NUL bytes, so Bengali words don't match. Not a Foliora defect.
7. **Shared `pdfjsLib` global (fixed afterwards):** Foliora now keeps its own reference (`window.Foliora.pdfjsLib`) and restores `window.pdfjsLib` to what it was. Tested with DearFlip's bundled PDF.js 2.5.207 and 4.10.39, both load orders.
8. `foliora-thumbnail-gen.js` still passes `onPassword` in the `getDocument()` params (dead, harmless: password PDFs are skipped via the rejection path). Same pattern was the cause of bug 6 in the viewer; it was left here on purpose because skipping locked PDFs is the desired behaviour.
9. `git`: `dist/` was untracked and added to `.gitignore`; the deletions are staged, nothing committed.
