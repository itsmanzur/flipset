# Flipset 1.0.0 — manual release checklist

Docker was unavailable, so these are the items that could not be verified automatically (see `RELEASE-REPORT.md`). Run them on a fresh site with `WP_DEBUG` and `WP_DEBUG_LOG` on, and read `wp-content/debug.log` after each step. Mark each PASS / FAIL.

## Fresh install

- [ ] Upload `flipset.zip` via Plugins → Add New → Upload. Activate. It redirects to the Flipset dashboard.
- [ ] Dashboard shows the right PDF count after uploading a PDF; "Upload a PDF" step turns done.
- [ ] No "Powered by Flipset" link under any viewer on a fresh install. Tick Flipset → Settings → Credit link, save: the link appears; untick: it disappears.
- [ ] Only one top-level **Flipset** menu; submenu: Dashboard, Shortcode Builder, Docs & Features, Settings.

## Embed flow (wp-admin)

- [ ] Upload a PDF, click **Embed**, copy the shortcode, paste it into a page. The PDF renders on the front end.
- [ ] Shortcode Builder: create, **Save Embed & Get Shortcode**, use `[flipset id="N"]`. Edit the embed; the front end updates.
- [ ] Saved Embeds: **Duplicate** creates a copy; **Delete** removes it after confirmation.
- [ ] Block editor: insert **Flipset Viewer**, pick a PDF, preview shows page 1. Insert the block with a saved embed `id`.

## Viewer controls (front end)

- [ ] Flip view: a short page-turn sound plays on each turn (there is no mute control).
- [ ] Fullscreen button and Presentation button (enable them in Settings first).
- [ ] Print button: see "What Print should produce" below.
- [ ] Touch pinch-zoom and swipe on a real phone.

## Other plugins

- [ ] **Yoast SEO** or **Rank Math** active: Flipset outputs no Open Graph tags (they supply their own).
- [ ] **Elementor** active: the Flipset widgets appear and render. Repeat with **Divi** and **Beaver Builder**.
- [ ] Without Elementor/Divi/Beaver nothing fatals.
- [ ] A page-cache plugin (WP Rocket, LiteSpeed Cache, W3TC): the viewer still works on a cached page; it still works after "minify/defer JS".

## Languages

- [ ] Settings → General → Site Language = **বাংলা**. Flipset admin pages, the front-end toolbar and the block editor labels are Bengali. No English strings remain.

## Uninstall (use a disposable site)

- [ ] Delete the plugin. `flipset_settings`, saved embeds, Flipset post meta and thumbnails are gone; **your PDFs and other media stay**.

## WordPress.org submission

- [ ] Zip contains no `tests/`, `docs/`, `bin/`, `.github`, hidden files (checked: 64 files, forward-slash paths).
- [ ] Plugin Check on the uploaded zip shows 0 errors.
- [ ] `readme.txt` renders correctly in the WordPress.org readme validator.

## What Print should produce

Print renders the PDF pages again off-screen at a fixed print size (about A4 at 200 dpi, rotation kept), so the output does not depend on the on-screen zoom. A new tab opens, shows the loading text, then the browser print dialog opens by itself. One PDF page is one sheet, with no blank sheet at the end. The page numbers are also sent in the `flipset:print` event (`detail.pages`).

| View | Sheets in the print preview |
|------|-----------------------------|
| Page | The current page only. |
| Spread | The two pages shown side by side, as two sheets. Page 1 (the cover) prints alone. |
| Flip | Same as spread: the two visible pages (the cover or the last odd page prints alone). |
| Scroll | Every page that is at least partly visible in the scroll area, in order (usually 1 to 3 pages, at most 24). |
| Any view, container narrower than 640px | Spread and flip show a single page there, so the current page only. |

Also check:

- [ ] **Single-page PDF**: exactly one sheet in all four views.
- [ ] **Password PDF**: pressing Print before unlocking does nothing (no empty tab); after unlocking it prints normally.
- [ ] **Large PDF** (hundreds of pages): Print only renders the pages listed above, never the whole file, and opens the dialog within a few seconds. Memory stays low (each sheet is capped at 16 megapixels).
- [ ] **Popup blocker on**: nothing prints and nothing breaks; allowing popups for the site fixes it.
- [ ] If page rendering ever fails, the new tab shows the PDF file itself so it can be printed from the browser's own viewer.
