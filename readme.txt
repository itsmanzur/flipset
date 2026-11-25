=== Flipset – PDF Viewer, PDF Embed, 3D Flipbook & Document Library ===
Contributors: itsmanzur
Tags: pdf, pdf viewer, pdf embed, flipbook, document library
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.1.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Embed PDFs with a fast PDF viewer, 3D flipbook and document library. Searchable PDF text, accessible, no CDN. Works with Gutenberg, Elementor, Divi.

== Description ==

Flipset embeds PDF documents in posts, pages and widgets with a PDF.js build that ships inside the plugin. Use a shortcode, a Gutenberg block, a page-builder widget, or paste a PDF link on its own line. There is no CDN, no external service and no API key: rendering happens in the visitor's browser, from files on your own site.

https://youtu.be/RGBoQxO4mUM

👉 [Live demo](https://flipset.thereadscope.com) · [Documentation](https://flipset.thereadscope.com/flipset-docs/) · [Playground](https://flipset.thereadscope.com/#playground) · [Support](https://wordpress.org/support/plugin/flipset/)

**At a glance**

* **What it is:** a free WordPress PDF viewer, PDF embed plugin, 3D flipbook and PDF document library.
* **How to add a PDF:** shortcode `[flipset file="https://example.com/document.pdf"]`, the "Flipset Viewer" block, a page-builder widget, or a PDF link pasted on its own line.
* **View modes:** single page, continuous scroll, two-page spread, 3D flipbook (`view="page|scroll|spread|flip"`).
* **Document library:** `[flipset_library]` – a grid or list of your Media Library PDFs with search, sort and pagination.
* **Works with:** Gutenberg, Elementor, Divi, Beaver Builder, WPML, Polylang, WP Rocket, W3 Total Cache, Autoptimize, LiteSpeed Cache, SiteGround Optimizer.
* **Privacy:** no CDN, no external requests, no cookies, no tracking. PDF.js is bundled.
* **Security:** only `http` and `https` PDF addresses are accepted, PDF-embedded font code is not run, and there is a private way to report vulnerabilities.
* **SEO:** PDF text is searchable in WordPress search; Open Graph / Twitter cover image; Schema.org `DigitalDocument`; plain PDF link when JavaScript is off.
* **Accessibility:** full keyboard control, screen reader page announcements, reduced-motion support, untagged-PDF check.
* **Requirements:** WordPress 6.0+, PHP 7.4+.

**Quick start**

1. Install and activate Flipset.
2. Embed one PDF: `[flipset file="https://example.com/document.pdf"]`
3. Open it as a 3D flipbook: `[flipset file="https://example.com/document.pdf" view="flip"]`
4. Show every PDF in your Media Library: `[flipset_library]`
5. Prefer clicks to code? Use the "Flipset Viewer" or "Flipset Library" block, or Flipset → Shortcode Builder with its live preview.

**Common use cases**

* Brochures, catalogues and menus – a 3D flipbook that opens on the first page.
* Reports, white papers and e-books – a searchable PDF reader with a document outline and last-page resume.
* Manuals, forms and price lists – a PDF document library visitors can search and sort.
* Course material and handouts – an accessible PDF viewer with download and print switched on or off per embed.
* Magazines and newsletters – a flipbook in spread view with a share dialog.

**Why Flipset?**

* **Fast and local** – bundled PDF.js, nothing loaded from a CDN, optional lazy loading, and a poster mode that loads the viewer only when a visitor opens the document.
* **Accessible** – keyboard controls, screen reader page announcements, and a check that flags PDFs without accessibility tags.
* **3D flipbook** – page, scroll, spread and flipbook views, all in the free plugin.
* **Searchable PDF text** – WordPress site search can find words inside your embedded PDFs.
* **Safe by default** – only `http` and `https` PDF addresses are accepted, and PDFs cannot run code from their embedded fonts.
* **Works where you build** – Gutenberg, Elementor, Divi and Beaver Builder.
* **Translation-ready** – WPML and Polylang support; translations come from translate.wordpress.org.

= Features =

**Viewer**

* Shortcode `[flipset file="https://example.com/document.pdf"]` and a "Flipset Viewer" Gutenberg block with a Media Library picker and first-page preview.
* Paste a direct `.pdf` link on its own line to embed it. PDF attachment pages can show the viewer too (a setting).
* Zoom, fit page or width, rotate, find with match count, page thumbnails, document outline, hand tool, fullscreen, presentation mode, print and download.
* Selectable text and working links inside the PDF; password-protected PDFs ask for the password.
* Light, dark and sepia reading themes.
* Start page, `#page=4` deep links and last-page resume on the visitor's device.
* Poster mode: show the cover and an Open button; PDF.js (about 1.5 MB) and the PDF load only on click. Per embed (`poster="true"`) or as a site default.
* Show or hide download, print, search, share and other controls per embed or site-wide.
* Share dialog with X, Facebook, LinkedIn, WhatsApp, email and copy link.
* Clear message with a direct link when a PDF on another domain blocks cross-origin access (CORS).

**Flipbook and view modes**

* Single page, continuous scroll, two-page spread and 3D flipbook (`view="flip"`).
* Realistic page curl with a short page-turn sound generated in the browser, no audio files.
* Spread and flipbook fall back to a single page on narrow screens.
* Touch support: pinch to zoom and swipe to turn pages.

**Document library**

* `[flipset_library]` shortcode and "Flipset Library" block: a grid or list of your Media Library PDFs with first-page thumbnails, search, an optional sort menu and pagination; a document opens in place.
* Choose what the library shows: hide any PDF with "Hide from library" in Flipset → Docs & Features, list only the PDFs you pick (`ids`, or "Only these PDFs" in the block), or leave some out (`exclude`).
* Flipset → Docs & Features lists your PDFs with preview, search-index status and accessibility status, and copies shortcodes.
* Shortcode Builder with live preview and one-click presets; save an embed once and reuse it as `[flipset id="1"]` or with the block.

**SEO and search**

* PDF text is extracted in your browser from Flipset → Docs & Features and stored on your site, so posts and pages that embed a PDF match in WordPress search.
* Open Graph and Twitter image from the PDF's first page, unless an SEO plugin already provides one.
* Schema.org DigitalDocument structured data.
* A plain link to the PDF is shown when JavaScript is off.

**Accessibility**

* Labelled toolbar buttons, visible keyboard focus and full keyboard control.
* Page changes are announced to screen readers; reduced-motion preference is respected.
* Flipset → Docs & Features marks PDFs that are not tagged for accessibility.

**Security**

* A PDF address must be `http`, `https` or a site-relative path; `javascript:`, `data:`, `file:` and other schemes are refused everywhere, including in filters and saved embeds.
* The bundled PDF.js runs with `isEvalSupported: false`, so a PDF cannot run code generated from its fonts.
* Admin actions check a nonce and the user's capability.
* Vulnerabilities can be reported privately: https://github.com/itsmanzur/flipset/security/policy

**Builders and integrations**

* Native Elementor, Divi and Beaver Builder widgets for the viewer and the library.
* WPML and Polylang: a translated Media Library PDF is swapped in for the current language.
* Script exclusions for WP Rocket, W3 Total Cache, Autoptimize, LiteSpeed Cache and SiteGround Optimizer, so PDF.js is not minified, combined or delayed.

**Developer friendly**

* Public filters and actions, and DOM events such as `flipset:ready` and `flipset:pagechange`, documented in `docs/HOOKS.md`.
* WP-CLI: `wp flipset status`, `wp flipset reindex`, `wp flipset clear_cache`.
* Clean uninstall: settings and Flipset data are removed, your media is kept.

= Shortcode reference =

**`[flipset]` – PDF viewer.** Only `file` (or `id`) is required.

* `file` – URL of the PDF. `id` – ID of an embed saved in Flipset → Shortcode Builder.
* `view` – `page` (default), `scroll`, `spread` or `flip` (3D flipbook).
* `page` – start page (default `1`). `title` – caption for the viewer, also used as its accessible name.
* `width`, `height` – a number with a CSS unit, for example `100%` or `700px` (defaults come from Flipset → Settings).
* `theme` – `light`, `dark` or `sepia`.
* `download`, `print`, `search`, `share`, `fullscreen`, `presentation`, `shortcuts`, `theme_btn` – `true` or `false`; leave out to use the site setting.
* `hide` – comma-separated controls to hide, for example `hide="share,fullscreen"`.
* `loading` – `lazy` or `eager`. `hash` – `true` or `false` for `#page=` deep links. `resume` – `true` or `false` to remember the last page read.
* `poster` – `true` shows the cover and an Open button and loads the viewer only on click; `false` turns it off. Leave out to use Flipset → Settings → Poster mode.

Example: `[flipset file="https://example.com/catalogue.pdf" view="flip" theme="dark" height="700px" download="false"]`

**`[flipset_library]` – PDF document library.** No attributes are required.

* `columns` – number of grid columns (default `3`). `per_page` – documents per page (default `12`).
* `orderby` – `date` (default), `title` or `modified`. `order` – `DESC` (default) or `ASC`.
* `search` – `true` (default) or `false` for the search field.
* `sort_control` – `true` shows a Newest / Oldest / Title A–Z / Title Z–A menu (default `false`).
* `layout` – `grid` (default) or `list`. `layout_toggle` – `true` shows a grid / list switch (default `false`).
* `ids` – list only these PDFs, by Media Library ID, for example `ids="12,34"`. Named PDFs are listed even when hidden. Leave empty to list every PDF that is not hidden.
* `exclude` – leave these PDFs out, for example `exclude="56"`.

Example: `[flipset_library columns="4" per_page="8" sort_control="true" layout_toggle="true"]`

Full reference with every value: https://flipset.thereadscope.com/flipset-docs/shortcode/

= Extending Flipset =

Flipset exposes public WordPress filters and actions (see Developer Hooks below) so add-ons and themes can extend the viewer without modifying core code. No paid features are sold or locked in this plugin.

= External services =

Flipset does not contact any server on its own. It has no analytics, no licence check, no update ping and no remote scripts, fonts or images. The visitor's browser opens an outside address only when the visitor clicks one of these links:

* **Share dialog (X, Facebook, LinkedIn, WhatsApp)** – the "Share" button in the viewer builds share links. Nothing is sent until the visitor clicks one; then the address of the current page (optionally with the page number) and, for X and WhatsApp, the document title are passed to that network in the link:
  * X (Twitter), `twitter.com/intent/tweet` – [Terms](https://twitter.com/en/tos), [Privacy](https://twitter.com/en/privacy)
  * Facebook, `facebook.com/sharer/sharer.php` – [Terms](https://www.facebook.com/terms.php), [Privacy](https://www.facebook.com/privacy/policy/)
  * LinkedIn, `linkedin.com/sharing/share-offsite` – [Terms](https://www.linkedin.com/legal/user-agreement), [Privacy](https://www.linkedin.com/legal/privacy-policy)
  * WhatsApp, `api.whatsapp.com/send` – [Terms](https://www.whatsapp.com/legal/terms-of-service), [Privacy](https://www.whatsapp.com/legal/privacy-policy)
* **Email share** uses a `mailto:` link and opens the visitor's own email program; no server is involved.
* **"Powered by Flipset" credit** – off by default. If an administrator turns it on under Flipset → Settings → Credit link, a normal link to https://flipset.thereadscope.com is shown under each viewer. The plugin sends nothing there; the visitor's browser makes an ordinary page request only if the link is clicked.
* **PDFs hosted on another domain** – if you embed a PDF from another website, the visitor's browser downloads it directly from that host, which receives the visitor's IP address. PDFs in your own Media Library are served by your own site.

= Third-party libraries =

* **PDF.js 3.11.174** by Mozilla – Apache License 2.0. Bundled in `assets/vendor/pdfjs/` as `pdf.min.js` and `pdf.worker.min.js`, the official minified build from the `pdfjs-dist` 3.11.174 release. Upstream project: https://github.com/mozilla/pdf.js – the human-readable source for this version is at https://github.com/mozilla/pdf.js/tree/v3.11.174. Licence text: https://www.apache.org/licenses/LICENSE-2.0
* **Inter** by The Inter Project Authors – SIL Open Font License 1.1. Bundled in `assets/fonts/inter/` (a Latin-subset, weight-axis-only variable `woff2` made from the official font with `subset-font`, plus `OFL.txt`) and used only on the Flipset screens in wp-admin. Upstream project: https://github.com/rsms/inter – licence text: https://openfontlicense.org
* No other third-party library or font is bundled. The plugin's own JavaScript and CSS are shipped unminified.

== Installation ==

1. Upload the `flipset` folder to `/wp-content/plugins/`, or install the plugin from the Plugins screen.
2. Activate the plugin. The first time, you will land on the Flipset Welcome screen.
3. Upload a PDF in Media, or open Flipset → Docs & Features.
4. Copy a shortcode, create a test page, or insert the "Flipset Viewer" block in the editor.

== Frequently Asked Questions ==

= How do I embed a PDF in WordPress? =

Add `[flipset file="https://example.com/document.pdf"]` to a post or page, insert the "Flipset Viewer" block and pick a PDF from the Media Library, or paste a direct `.pdf` link on its own line. Step-by-step guide: https://flipset.thereadscope.com/flipset-docs/quick-start/

= Is Flipset a free PDF viewer plugin? =

Yes. The viewer, the 3D flipbook, the document library, PDF text search and the page-builder widgets are all in this plugin. No paid features are sold or locked here.

= Where is the documentation? =

https://flipset.thereadscope.com/flipset-docs/ – with guides for the [shortcode](https://flipset.thereadscope.com/flipset-docs/shortcode/), the [Gutenberg block](https://flipset.thereadscope.com/flipset-docs/gutenberg-block/), the [document library](https://flipset.thereadscope.com/flipset-docs/document-library/), [view modes](https://flipset.thereadscope.com/flipset-docs/view-modes/), [deep links](https://flipset.thereadscope.com/flipset-docs/deep-links/), [accessibility](https://flipset.thereadscope.com/flipset-docs/accessibility/), [page builders](https://flipset.thereadscope.com/flipset-docs/page-builders/), [WP-CLI](https://flipset.thereadscope.com/flipset-docs/wp-cli/), [hooks](https://flipset.thereadscope.com/flipset-docs/hooks/) and [troubleshooting](https://flipset.thereadscope.com/flipset-docs/troubleshooting/).

= Does this send my documents to a third-party server? =

No. Rendering happens in the visitor's browser with the PDF.js library bundled in this plugin. See "External services" above for the few links that open outside sites only when clicked.

= How do I use the 3D flipbook? =

Use `[flipset file="https://example.com/document.pdf" view="flip"]`, choose "3D FlipBook" in the View setting of the Flipset Viewer block, or switch the view from the More tools menu in the toolbar. On narrow screens (under 640 px) it shows a single page.

= Can I make a PDF flipbook in WordPress? =

Yes, in the free plugin. Add `view="flip"` to the shortcode, or choose "3D FlipBook" in the block's View setting. You get a page curl, a page-turn sound made in the browser and touch swipe. Narrow screens (under 640 px) show a single page.

= Does Flipset work with Elementor, Divi and Beaver Builder? =

Yes. Each has a Flipset Viewer widget and a Flipset Library widget that use the same renderer as the shortcode and block. See https://flipset.thereadscope.com/flipset-docs/page-builders/

= Shortcode or block - which should I use? =

They render the same viewer. Use the block in the editor if you want a file picker and first-page preview. Use `[flipset file="..."]` in the classic editor, widgets or PHP templates. Elementor, Divi and Beaver Builder have Flipset Viewer and Flipset Library widgets that use the same renderer.

= Can I save an embed and reuse it? =

Yes. Build it in Flipset → Shortcode Builder, save it, and use `[flipset id="1"]` or the block's saved-embed ID. Edit the saved embed later and every page that uses it updates.

= Does it work with WPML or Polylang? =

Yes. Translate the PDF in the Media Library (WPML Media Translation or Polylang's media option). Flipset swaps the file URL to the current language's attachment. You can also put a different PDF on each language's page. `wpml-config.xml` ships with the plugin so the block's `file` and `title` attributes are translatable.

= The viewer is blank after enabling a cache plugin =

Flipset excludes its PDF.js scripts from minify, combine and delay in WP Rocket, W3 Total Cache, Autoptimize, LiteSpeed Cache and SiteGround Optimizer, and marks those tags `data-no-optimize`. If another optimizer still concatenates them, add `flipset-viewer.js`, `pdf.min.js` and `pdf.worker.min.js` to that plugin's exclude list.

= A PDF loads on my own site but fails from another domain =

The file URL must allow the page origin to fetch it. Cross-origin PDFs need CORS headers (`Access-Control-Allow-Origin`) on the file host. Files in your own Media Library work without extra setup.

= Large PDFs feel slow =

Flipset draws the visible page (or a two-page spread). Continuous scroll renders nearby pages only. The viewer downloads only the parts of the file a page needs, so page 1 shows before a large PDF has fully arrived; later pages are fetched as the reader gets to them. On pages with several PDFs, turn on poster mode (`poster="true"` or Flipset → Settings → Poster mode): each viewer then shows its cover and loads nothing until it is opened.

Partial downloads need the server to answer HTTP range requests. Apache, Nginx, LiteSpeed and most CDNs do this for files in `wp-content/uploads` out of the box. To check, run `curl -I -H "Range: bytes=0-1023" https://your-site.com/wp-content/uploads/file.pdf`: the answer should be `206 Partial Content`. If it is `200`, the whole file is downloaded first (the viewer still works). Common causes are a CDN or plugin that gzip-compresses PDFs, or PDFs served through a PHP script. For a PDF on another domain, the file host must also send `Access-Control-Allow-Headers: Range` and `Access-Control-Expose-Headers: Accept-Ranges, Content-Range, Content-Length`. If a server answers range requests wrongly (pages fail to load), turn partial downloads off with `add_filter( 'flipset/progressive_loading', '__return_false' );`.

= Can I keep a PDF out of the library? =

Yes. In Flipset → Docs & Features, choose **Hide from library** under the file. It no longer appears in any library that lists every PDF, including its search. The file itself stays in your Media Library and can still be embedded on its own. To list only certain PDFs, use `[flipset_library ids="12,34"]` or "Only these PDFs" in the Flipset Library block; to leave a few out, use `exclude="56"`.

= How do I show a gallery of all my PDFs? =

Add `[flipset_library]` to a page, or insert the Flipset Library block. Visitors see a grid of Media Library PDFs and can open any document in the Flipset viewer without leaving the page. Optional attributes: `columns`, `per_page`, `orderby` (`date`, `title` or `modified`), `order` (`ASC` or `DESC`), `search` (`true` or `false`), `sort_control` (`true` shows a Newest / Oldest / Title A–Z / Title Z–A menu; default `false`), `layout` (`grid` or `list`; default `grid`) and `layout_toggle` (`true` shows a grid / list switch; default `false`).

= How do library thumbnails get generated? =

The first time you open Flipset → Docs & Features, the plugin draws each PDF's first page in your browser (bundled PDF.js) and saves a PNG in the Media Library, parented to that PDF. After that the library reuses the stored image. Thumbnails are not regenerated on every page load.

= What does "Untagged" mean on the Documents list? =

The PDF has no accessibility tags (its MarkInfo dictionary is not marked). Screen readers may miss headings and reading order. Export a tagged PDF from Word, InDesign or Acrobat. Flipset does not rewrite the file. You can turn the check off under Flipset → Settings → Search and sharing.

= Does Flipset include translations? =

Flipset is translation-ready (text domain `flipset`). Translations are delivered through translate.wordpress.org and installed automatically by WordPress for your site language. Contributions are welcome at https://translate.wordpress.org/projects/wp-plugins/flipset/ .

= Does WordPress search find text inside PDFs? =

Yes. Opening Flipset → Docs & Features extracts text from each PDF (the same visit that generates thumbnails) and stores it so posts and pages that embed those files match in the site search. The library search uses that text too. Password-protected PDFs are skipped, and scanned image-only PDFs have no text to find. Turn it off under Flipset → Settings → Search and sharing.

= Why does a shared page show a PDF cover image? =

If the page embeds a Flipset PDF and a first-page thumbnail exists, Flipset adds Open Graph and Twitter image tags using that cover. If Yoast SEO, Rank Math or a similar SEO plugin already sets an image, Flipset does not override it. You can disable this under Flipset → Settings.

= Can I use my own cover image for a PDF? =

Yes. Open Flipset → Docs & Features, choose **Change cover** under a document and pick any image from the Media Library. That image is then used in the library grid, as the Open Graph and Twitter image, and in the structured data. **Reset cover** goes back to the generated first page. Your image is never deleted by Flipset.

= How do I hide download or print? =

Flipset → Settings has checkboxes for the site default. A single embed can override with `[flipset file="..." download="false" print="false"]`, or use the matching toggles on the block. The `hide` attribute hides other controls, for example `hide="share,fullscreen"`.

= Can I open the viewer on a specific page? =

Yes. Use `[flipset file="..." page="4"]`, set Start page on the block, or link to the page with `#page=4`.

= Can I read EPUB ebooks? =

EPUB files are not supported in this version. Flipset focuses on fast, accessible PDF rendering, 3D flipbooks and document libraries.

= Does Flipset show a "Powered by" link? =

No, not unless you ask for it. The credit link is off by default. If you would like to support the plugin, tick Flipset → Settings → Credit link and a small "Powered by Flipset" link appears under each viewer.

= Does the free plugin collect analytics? =

No. There is no tracking pixel, no remote API and no phone-home. See the Privacy section for what is stored.

= Why is my PDF link refused? =

Flipset accepts only `http` and `https` addresses and site-relative paths such as `/wp-content/uploads/file.pdf`. Addresses that start with `javascript:`, `data:`, `file:` or another scheme are ignored for safety. Use the full link to the PDF, or pick the file from the Media Library.

= Where do I get support or report a bug? =

Use the support forum at https://wordpress.org/support/plugin/flipset/ or open an issue on GitHub: https://github.com/itsmanzur/flipset/issues

= How do I report a security problem? =

Please do not post it in the public forum. Use GitHub's private reporting: https://github.com/itsmanzur/flipset/security/policy

== Developer Hooks ==

Add-ons can extend the viewer through public WordPress filters and actions. The full list, parameters and copy-paste examples are in `docs/HOOKS.md` in the GitHub repository: https://github.com/itsmanzur/flipset/blob/main/docs/HOOKS.md – also online at https://flipset.thereadscope.com/flipset-docs/hooks/ (DOM events: https://flipset.thereadscope.com/flipset-docs/dom-events-js/).

= Source code and minified files =

Flipset loads minified scripts and stylesheets (`*.min.js`, `*.min.css`). The readable source of every one of them ships in the same folder (for example `assets/js/flipset-viewer.js` next to `assets/js/flipset-viewer.min.js`), with a source map, and is on GitHub: https://github.com/itsmanzur/flipset. The minified files are built with esbuild by `npm run build` (`bin/build-assets.js`). With `define( 'SCRIPT_DEBUG', true );` in `wp-config.php`, WordPress loads the readable files instead. The bundled PDF.js in `assets/vendor/pdfjs` is Mozilla's own release build.

== Screenshots ==

1. Front-end viewer with the compact toolbar (page, zoom, find, theme, share, fullscreen).
2. The 3D flipbook view.
3. Flipset dashboard with the setup checklist.
4. Shortcode Builder with live preview and saved embeds.
5. Docs & Features screen listing Media Library PDFs with previews, custom covers and search/accessibility status.
6. Settings with a live width and height preview.
7. Gutenberg "Flipset Viewer" block with a Media Library picker and first-page preview.
8. Front-end document library grid (`[flipset_library]`).

== Privacy ==

Flipset does not collect any visitor data, sets no cookies, and has no analytics or tracking. The plugin has no remote requests of its own (see "External services").

What the plugin stores on your site:

* **Settings** – one option, `flipset_settings`.
* **Saved embeds** – a private custom post type, `flipset_embed`, with the embed's configuration in post meta.
* **PDF data** – post meta on your PDF attachments (extracted text, page count, indexed flag, accessibility flag, thumbnail ID) and a combined search index on posts and pages that embed a PDF.
* **Thumbnails** – first-page PNG images generated in your browser and saved in the Media Library.
* **Library visibility** – a `_flipset_library_hidden` flag on each PDF you hide from the library.
* **Admin notice state** – one user meta value per administrator who dismisses the welcome notice or the library notice, a short-lived transient used for the redirect after activation, and one option, `flipset_welcome_seen`, that records that the Welcome screen was shown so the redirect happens only once.

In the visitor's own browser, Flipset uses `localStorage` (not cookies) to remember the last page read (`flipset:page:<file URL>`), the chosen reading theme (`flipset_theme`) and the chosen library layout, grid or list (`flipset-library-layout`). These values never leave the browser, and the visitor can clear them at any time.

The share feature only builds links; it contacts no one until the visitor clicks a link.

Deleting the plugin from the Plugins screen removes the settings, saved embeds, Flipset post and user meta, the transient and the thumbnails it generated. Your uploaded PDFs and other media are kept.

== Credits ==

Flipset is developed and maintained by itsmanzur.

* Website and live demo: https://flipset.thereadscope.com
* Documentation: https://flipset.thereadscope.com/flipset-docs/
* Source code: https://github.com/itsmanzur/flipset
* Support forum: https://wordpress.org/support/plugin/flipset/
* Bug reports: https://github.com/itsmanzur/flipset/issues
* Security issues (private): https://github.com/itsmanzur/flipset/security/policy
* Help translate: https://translate.wordpress.org/projects/wp-plugins/flipset/
* PDF.js by Mozilla (Apache-2.0).

== Changelog ==

= 1.2.0 =
* New: hide any PDF from the document library. Choose "Hide from library" under the file in Flipset → Docs & Features; it is left out of every library that lists all PDFs, including library search. Existing sites keep listing every PDF until a file is hidden.
* New: `ids` lists only the PDFs you name (`[flipset_library ids="12,34"]`), and `exclude` leaves some out. The Flipset Library block has a matching "Only these PDFs" picker.
* New: a Welcome screen under Flipset → Welcome, with four ways to embed a PDF, the main features, common questions, support links and the latest changes from this readme. It opens once after the first activation; bulk, network and WP-CLI activations and later reactivations do not redirect. Nothing on it is loaded from another site.
* New: a one-time notice on the Flipset screens explains that the library lists every Media Library PDF and how to change that.
* New: poster mode. A viewer can show the PDF's cover, its title and an Open button instead of loading straight away; PDF.js and the PDF download only when a visitor clicks. Turn it on per embed (`poster="true"`, the Shortcode Builder, the Flipset Viewer block, or the Elementor, Divi and Beaver Builder widgets) or for the whole site under Flipset → Settings. Without JavaScript the button opens the PDF directly.
* Improved: faster first page. On a post or page with a viewer that loads straight away, the browser starts fetching PDF.js from the page head instead of waiting for the footer, and fetches the PDF.js worker while idle. The first poster's cover image is fetched with high priority; later covers are lazy-loaded.
* Improved: page 1 of a large PDF shows sooner. The viewer now downloads only the parts of the file it needs (HTTP range requests) instead of streaming the whole file at the same time; on a slow connection a 1 MB PDF showed its first page in about half the time. Servers without range support still get a full download. The readme FAQ explains how to check your server.
* Improved: smaller downloads. Scripts and stylesheets now ship minified with source maps (built by esbuild), about 37% smaller in total; the viewer script goes from 94 KB to 46 KB. The readable files ship next to them and load when `SCRIPT_DEBUG` is on.
* Improved: the flipbook animation adapts to the device. It times each page turn; when frames drop it switches from the 16-strip bending page to 8 strips, then to a single page turned by the browser's own animation engine, which stays smooth on a busy phone. Phones with little memory start at 8 strips. The choice lasts for the browser session.
* Improved: lighter page turns. Pages the reader has already skipped past are no longer drawn: pressing Next quickly through seven pages now draws one page instead of seven.
* Improved: less memory on phones. Pages are drawn at no more than twice the screen's pixel density, with a size limit per page (smaller on phones and tablets), so zooming in on a large page no longer blanks it on iOS Safari or crashes the tab.
* Improved: the next page (or spread) is prepared while the browser is idle, so turning the page is quicker. Skipped on connections with Data Saver on or 2G.
* Improved: the library stylesheet now loads only on pages that show a library, not on every page.
* Fixed: on an HTTPS page, a PDF from your own site saved with an `http://` address (for example from before the site moved to HTTPS) showed a cross-origin error. Flipset now loads it over HTTPS.
* Fixed: Flipset widgets did not load or start in the Elementor editor preview.
* Fixed: on classic themes and Elementor pages the page jumped while a viewer or library loaded, because its stylesheet arrived in the page footer. It now loads in the page head. On a test page with five viewers the layout shift score fell from 0.44 to under 0.05.
* Fixed: library covers could fall short of their box when the theme sets `height: auto` on content images.
* Developer: new `flipset/poster_image_id`, `flipset/resource_hints`, `flipset/progressive_loading` and `flipset/head_styles` filters and `window.FlipsetPoster.init()`.
* Developer: new `flipset/welcome/sections`, `flipset/welcome/hero_actions` and `flipset/faq_items` filters and the `flipset/welcome/after_hero` action, so add-ons can add to the Welcome screen.

= 1.1.3 =
* Security: PDFs can no longer run generated code from their embedded fonts. Flipset now tells the bundled PDF.js not to use it (`isEvalSupported: false`) in the viewer, the thumbnail generator and the block editor preview.
* Security: a PDF address must be `http`, `https` or a site-relative path. `javascript:`, `data:`, `file:` and other schemes are refused in shortcodes, blocks, saved embeds and filters.
* Improved: PDF text sent from the browser for indexing is cut to the size limit before it is processed, and the stored page count is capped.
* Improved: Flipset → Docs & Features now documents the library's `order`, `sort_control`, `layout` and `layout_toggle` settings, the custom cover and sort and list features, and which PDF addresses are accepted.
* Improved: this readme has a Security section and answers for refused PDF links and for reporting a security problem.
* Added: a security policy (`SECURITY.md` on GitHub) with a private way to report vulnerabilities.
* Developer: new `Flipset_Viewer::sanitize_file_url()`; tests for the address rules and for `isEvalSupported`; a PHPStan job (report only) in CI; `docs/PDFJS.md` tracks the bundled PDF.js version and advisories.

= 1.1.2 =
* New: sort menu for the document library (`sort_control="true"`): Newest, Oldest, Title A–Z and Title Z–A. It re-sorts in place, keeps your search, goes back to page 1 and tells screen readers what changed.
* New: list layout for the library (`layout="list"`) and an optional grid / list switch for visitors (`layout_toggle="true"`). The visitor's choice is remembered in their own browser; nothing is stored on your site.
* New: library cards show the page count ("PDF · 12 pages · 198.9 KB") for PDFs whose text has been indexed in Flipset → Docs & Features. Nothing is shown when no page count is stored.
* New: the Flipset Library block has settings for the sort menu, the layout and the layout switch. Existing pages and blocks are unchanged unless you turn them on.
* Improved: code documentation and internal quality (every class and method is documented; the plugin passes its coding-standards check).
* Improved: this readme now has quick links, an at-a-glance summary, a quick start, a shortcode reference and more questions answered.
* Fixed: an unrecognised value in an on/off shortcode or block attribute (for example `search="maybe"`) fell back to "off" in 1.1.1; it now falls back to that attribute's default again.

= 1.1.1 =
* Improved: PDF library design. Cards have a consistent 3:4 cover, a two-line title and a muted "PDF · size" line; the grid follows the `columns` setting and drops to 2 columns on tablets and 1 on phones; clearer keyboard focus, hover that respects "reduce motion", RTL support, theme-friendly colours with AA contrast, a loading bar and a "No documents found" state.
* Improved: opening a document in the library moves focus into the viewer, and Esc closes it and returns focus to the card. After searching or changing page, focus stays on the library instead of jumping to the top of the page.
* Improved: the viewer says "Still loading…" if a very large file or a slow connection stalls for 20 seconds, instead of staying blank.
* Improved: toolbar buttons are 40px on touch tablets, not only on phones.
* Fixed: resizing or zooming while a PDF was still loading could stop a page from rendering ("Cannot use the same canvas…").
* Fixed: a library search with no results reloaded the whole page instead of updating in place, and a new search stayed on the old page number.
* Fixed: the Shortcode Builder made the page scroll sideways on phones, and its Copy / Create test page buttons ran off the card.
* Fixed: admin text in Bengali and other non-Latin languages now falls back to a system font that has those characters.

= 1.1.0 =
* New: choose your own cover image for any PDF. In Flipset → Docs & Features, use "Change cover" (and "Reset cover" to go back to the first page). The cover is used in the document library, the Open Graph and Twitter image, and the structured data.
* Improved: cleaner admin screens. One type scale, the Inter font bundled with the plugin (no request leaves your site), consistent weights and spacing, and better text contrast.
* Improved: Shortcode Builder tab labels no longer get cut off, and the cards on the Features screen line up.
* Fixed: tagged PDFs were reported as "Untagged" in the accessibility check.
* Added: an intro video on the plugin page.
* Developer: new `Flipset_Thumbnails::get_cover_id()` and `get_generated_id()`; `get_thumbnail_id()` now returns the custom cover when one is set.

= 1.0.0 =
* Initial release.
* PDF viewer with a bundled PDF.js build, shortcode, Gutenberg block and direct PDF URL embed.
* Page, continuous scroll, two-page spread and 3D flipbook views; light, dark and sepia themes.
* Document library shortcode and block, Shortcode Builder with saved embeds, and a Docs & Features screen.
* PDF text search in WordPress search, Open Graph image and structured data.
* Accessibility: keyboard control, screen reader announcements and tagged-PDF check.
* Elementor, Divi and Beaver Builder widgets; WPML and Polylang support; translation-ready.
* Public hooks and WP-CLI commands for developers.

== Upgrade Notice ==

= 1.1.3 =
Security hardening for PDF display and PDF addresses. Recommended for every site; no settings change.

= 1.1.2 =
Adds an optional sort menu and a grid / list layout to the document library (both off unless you turn them on). Library cards now also show a page count for PDFs that have one stored.

= 1.1.1 =
Redesigned document library cards (same shortcode and block settings) plus accessibility and mobile fixes. If your theme styles the `.flipset-library-*` classes, check the library page after updating.

= 1.1.0 =
Adds custom PDF cover images, cleaner admin typography and a fix for tagged PDFs being flagged as untagged. No settings change.

= 1.0.0 =
Initial release.
