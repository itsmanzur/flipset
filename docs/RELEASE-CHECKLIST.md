# Foliora 1.0.0 — manual release checklist

Docker was unavailable, so these are the items that could not be verified automatically (see `RELEASE-REPORT.md`). Run them on a fresh site with `WP_DEBUG` and `WP_DEBUG_LOG` on, and read `wp-content/debug.log` after each step. Mark each PASS / FAIL.

## Fresh install

- [ ] Upload `foliora.zip` via Plugins → Add New → Upload. Activate. It redirects to the Foliora dashboard.
- [ ] Dashboard shows the right PDF count after uploading a PDF; "Upload a PDF" step turns done.
- [ ] Only one top-level **Foliora** menu; submenu: Dashboard, Shortcode Builder, Docs & Features, Settings.

## Embed flow (wp-admin)

- [ ] Upload a PDF, click **Embed**, copy the shortcode, paste it into a page. The PDF renders on the front end.
- [ ] Shortcode Builder: create, **Save Embed & Get Shortcode**, use `[foliora id="N"]`. Edit the embed; the front end updates.
- [ ] Saved Embeds: **Duplicate** creates a copy; **Delete** removes it after confirmation.
- [ ] Block editor: insert **Foliora Viewer**, pick a PDF, preview shows page 1. Insert the block with a saved embed `id`.

## Viewer controls (front end)

- [ ] Flip view with sound on, then off (audible page-turn).
- [ ] Fullscreen button and Presentation button (enable them in Settings first).
- [ ] Print button: print window opens. In **spread** view it prints both pages; in **scroll** view it prints the rendered pages.
- [ ] Touch pinch-zoom and swipe on a real phone.

## Other plugins

- [ ] **Yoast SEO** or **Rank Math** active: Foliora outputs no Open Graph tags (they supply their own).
- [ ] **Elementor** active: the Foliora widgets appear and render. Repeat with **Divi** and **Beaver Builder**.
- [ ] Without Elementor/Divi/Beaver nothing fatals.
- [ ] A page-cache plugin (WP Rocket, LiteSpeed Cache, W3TC): the viewer still works on a cached page; it still works after "minify/defer JS".

## Languages

- [ ] Settings → General → Site Language = **বাংলা**. Foliora admin pages, the front-end toolbar and the block editor labels are Bengali. No English strings remain.

## Uninstall (use a disposable site)

- [ ] Delete the plugin. `foliora_settings`, saved embeds, Foliora post meta and thumbnails are gone; **your PDFs and other media stay**.

## WordPress.org submission

- [ ] Zip contains no `tests/`, `docs/`, `bin/`, `.github`, hidden files (checked: 64 files, forward-slash paths).
- [ ] Plugin Check on the uploaded zip shows 0 errors.
- [ ] `readme.txt` renders correctly in the WordPress.org readme validator.
