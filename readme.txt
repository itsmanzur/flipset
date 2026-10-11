=== Foliora – PDF Viewer, PDF Embed, 3D Flipbook & Document Library ===
Contributors: itsmanzur
Tags: pdf, pdf viewer, pdf embed, flipbook, document library
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Embed PDFs with a fast PDF viewer, 3D flipbook and document library. Searchable PDF text, accessible, no CDN. Gutenberg, Elementor, Divi ready.

== Description ==

Foliora embeds PDF documents in posts, pages and widgets with a PDF.js build that ships inside the plugin. Use a shortcode, a Gutenberg block, a page-builder widget, or paste a PDF link on its own line. There is no CDN, no external service and no API key: rendering happens in the visitor's browser, from files on your own site.

**Why Foliora?**

* **Fast and local** – bundled PDF.js, nothing loaded from a CDN, optional lazy loading.
* **Accessible** – keyboard controls, screen reader page announcements, and a check that flags PDFs without accessibility tags.
* **3D flipbook** – page, scroll, spread and flipbook views, all in the free plugin.
* **Searchable PDF text** – WordPress site search can find words inside your embedded PDFs.
* **Works where you build** – Gutenberg, Elementor, Divi and Beaver Builder.
* **Translation-ready** – WPML and Polylang support, Bengali (bn_BD) bundled.

= Features =

**Viewer**

* Shortcode `[foliora file="https://example.com/document.pdf"]` and a "Foliora Viewer" Gutenberg block with a Media Library picker and first-page preview.
* Paste a direct `.pdf` link on its own line to embed it. PDF attachment pages can show the viewer too (a setting).
* Zoom, fit page or width, rotate, find with match count, page thumbnails, document outline, hand tool, fullscreen, presentation mode, print and download.
* Selectable text and working links inside the PDF; password-protected PDFs ask for the password.
* Light, dark and sepia reading themes.
* Start page, `#page=4` deep links and last-page resume on the visitor's device.
* Show or hide download, print, search, share and other controls per embed or site-wide.
* Share dialog with X, Facebook, LinkedIn, WhatsApp, email and copy link.
* Clear message with a direct link when a PDF on another domain blocks cross-origin access (CORS).

**Flipbook and view modes**

* Single page, continuous scroll, two-page spread and 3D flipbook (`view="flip"`).
* Realistic page curl with a short page-turn sound generated in the browser, no audio files.
* Spread and flipbook fall back to a single page on narrow screens.
* Touch support: pinch to zoom and swipe to turn pages.

**Document library**

* `[foliora_library]` shortcode and "Foliora Library" block: a grid of your Media Library PDFs with first-page thumbnails, search and pagination; a document opens in place.
* Foliora → Docs & Features lists your PDFs with preview, search-index status and accessibility status, and copies shortcodes.
* Shortcode Builder with live preview and one-click presets; save an embed once and reuse it as `[foliora id="1"]` or with the block.

**SEO and search**

* PDF text is extracted in your browser from Foliora → Docs & Features and stored on your site, so posts and pages that embed a PDF match in WordPress search.
* Open Graph and Twitter image from the PDF's first page, unless an SEO plugin already provides one.
* Schema.org DigitalDocument structured data.
* A plain link to the PDF is shown when JavaScript is off.

**Accessibility**

* Labelled toolbar buttons, visible keyboard focus and full keyboard control.
* Page changes are announced to screen readers; reduced-motion preference is respected.
* Foliora → Docs & Features marks PDFs that are not tagged for accessibility.

**Builders and integrations**

* Native Elementor, Divi and Beaver Builder widgets for the viewer and the library.
* WPML and Polylang: a translated Media Library PDF is swapped in for the current language.
* Script exclusions for WP Rocket, W3 Total Cache, Autoptimize, LiteSpeed Cache and SiteGround Optimizer, so PDF.js is not minified, combined or delayed.

**Developer friendly**

* Public filters and actions, and DOM events such as `foliora:ready` and `foliora:pagechange`, documented in `docs/HOOKS.md`.
* WP-CLI: `wp foliora status`, `wp foliora reindex`, `wp foliora clear_cache`.
* Clean uninstall: settings and Foliora data are removed, your media is kept.

= External services =

Foliora does not contact any server on its own. It has no analytics, no licence check, no update ping and no remote scripts, fonts or images. The visitor's browser opens an outside address only when the visitor clicks one of these links:

* **Share dialog (X, Facebook, LinkedIn, WhatsApp)** – the "Share" button in the viewer builds share links. Nothing is sent until the visitor clicks one; then the address of the current page (optionally with the page number) and, for X and WhatsApp, the document title are passed to that network in the link:
  * X (Twitter), `twitter.com/intent/tweet` – [Terms](https://twitter.com/en/tos), [Privacy](https://twitter.com/en/privacy)
  * Facebook, `facebook.com/sharer/sharer.php` – [Terms](https://www.facebook.com/terms.php), [Privacy](https://www.facebook.com/privacy/policy/)
  * LinkedIn, `linkedin.com/sharing/share-offsite` – [Terms](https://www.linkedin.com/legal/user-agreement), [Privacy](https://www.linkedin.com/legal/privacy-policy)
  * WhatsApp, `api.whatsapp.com/send` – [Terms](https://www.whatsapp.com/legal/terms-of-service), [Privacy](https://www.whatsapp.com/legal/privacy-policy)
* **Email share** uses a `mailto:` link and opens the visitor's own email program; no server is involved.
* **"Powered by Foliora" credit** – off by default. If an administrator turns it on under Foliora → Settings → Credit link, a normal link to https://thereadscope.com/foliora is shown under each viewer. The plugin sends nothing there; the visitor's browser makes an ordinary page request only if the link is clicked.
* **PDFs hosted on another domain** – if you embed a PDF from another website, the visitor's browser downloads it directly from that host, which receives the visitor's IP address. PDFs in your own Media Library are served by your own site.

= Third-party libraries =

* **PDF.js 3.11.174** by Mozilla – Apache License 2.0. Bundled in `assets/vendor/pdfjs/` as `pdf.min.js` and `pdf.worker.min.js`, the official minified build from the `pdfjs-dist` 3.11.174 release. Upstream project: https://github.com/mozilla/pdf.js – the human-readable source for this version is at https://github.com/mozilla/pdf.js/tree/v3.11.174. Licence text: https://www.apache.org/licenses/LICENSE-2.0
* No other third-party library is bundled. The plugin's own JavaScript and CSS are shipped unminified.

== Installation ==

1. Upload the `foliora` folder to `/wp-content/plugins/`, or install the plugin from the Plugins screen.
2. Activate the plugin. You will land on the Foliora dashboard.
3. Upload a PDF in Media, or open Foliora → Docs & Features.
4. Copy a shortcode, create a test page, or insert the "Foliora Viewer" block in the editor.

== Frequently Asked Questions ==

= Does this send my documents to a third-party server? =

No. Rendering happens in the visitor's browser with the PDF.js library bundled in this plugin. See "External services" above for the few links that open outside sites only when clicked.

= How do I use the 3D flipbook? =

Use `[foliora file="https://example.com/document.pdf" view="flip"]`, choose "3D FlipBook" in the View setting of the Foliora Viewer block, or switch the view from the More tools menu in the toolbar. On narrow screens (under 640 px) it shows a single page.

= Shortcode or block - which should I use? =

They render the same viewer. Use the block in the editor if you want a file picker and first-page preview. Use `[foliora file="..."]` in the classic editor, widgets or PHP templates. Elementor, Divi and Beaver Builder have Foliora Viewer and Foliora Library widgets that use the same renderer.

= Can I save an embed and reuse it? =

Yes. Build it in Foliora → Shortcode Builder, save it, and use `[foliora id="1"]` or the block's saved-embed ID. Edit the saved embed later and every page that uses it updates.

= Does it work with WPML or Polylang? =

Yes. Translate the PDF in the Media Library (WPML Media Translation or Polylang's media option). Foliora swaps the file URL to the current language's attachment. You can also put a different PDF on each language's page. `wpml-config.xml` ships with the plugin so the block's `file` and `title` attributes are translatable.

= The viewer is blank after enabling a cache plugin =

Foliora excludes its PDF.js scripts from minify, combine and delay in WP Rocket, W3 Total Cache, Autoptimize, LiteSpeed Cache and SiteGround Optimizer, and marks those tags `data-no-optimize`. If another optimizer still concatenates them, add `foliora-viewer.js`, `pdf.min.js` and `pdf.worker.min.js` to that plugin's exclude list.

= A PDF loads on my own site but fails from another domain =

The file URL must allow the page origin to fetch it. Cross-origin PDFs need CORS headers (`Access-Control-Allow-Origin`) on the file host. Files in your own Media Library work without extra setup.

= Large PDFs feel slow =

Foliora draws the visible page (or a two-page spread). Continuous scroll renders nearby pages only. Very large files still need to be downloaded once, so keep uploads reasonably sized when you can.

= How do I show a gallery of all my PDFs? =

Add `[foliora_library]` to a page, or insert the Foliora Library block. Visitors see a grid of Media Library PDFs and can open any document in the Foliora viewer without leaving the page. Optional attributes: `columns`, `per_page`, `orderby` (`date`, `title` or `modified`), `order` (`ASC` or `DESC`) and `search` (`true` or `false`).

= How do library thumbnails get generated? =

The first time you open Foliora → Docs & Features, the plugin draws each PDF's first page in your browser (bundled PDF.js) and saves a PNG in the Media Library, parented to that PDF. After that the library reuses the stored image. Thumbnails are not regenerated on every page load.

= What does "Untagged" mean on the Documents list? =

The PDF has no accessibility tags (its MarkInfo dictionary is not marked). Screen readers may miss headings and reading order. Export a tagged PDF from Word, InDesign or Acrobat. Foliora does not rewrite the file. You can turn the check off under Foliora → Settings → Search and sharing.

= Does Foliora include translations? =

Yes. A Bengali (`bn_BD`) translation ships in the plugin. If a language pack from WordPress.org is installed, that pack takes precedence.

= Does WordPress search find text inside PDFs? =

Yes. Opening Foliora → Docs & Features extracts text from each PDF (the same visit that generates thumbnails) and stores it so posts and pages that embed those files match in the site search. The library search uses that text too. Password-protected PDFs are skipped, and scanned image-only PDFs have no text to find. Turn it off under Foliora → Settings → Search and sharing.

= Why does a shared page show a PDF cover image? =

If the page embeds a Foliora PDF and a first-page thumbnail exists, Foliora adds Open Graph and Twitter image tags using that cover. If Yoast SEO, Rank Math or a similar SEO plugin already sets an image, Foliora does not override it. You can disable this under Foliora → Settings.

= How do I hide download or print? =

Foliora → Settings has checkboxes for the site default. A single embed can override with `[foliora file="..." download="false" print="false"]`, or use the matching toggles on the block. The `hide` attribute hides other controls, for example `hide="share,fullscreen"`.

= Can I open the viewer on a specific page? =

Yes. Use `[foliora file="..." page="4"]`, set Start page on the block, or link to the page with `#page=4`.

= Does Foliora show a "Powered by" link? =

No, not unless you ask for it. The credit link is off by default. If you would like to support the plugin, tick Foliora → Settings → Credit link and a small "Powered by Foliora" link appears under each viewer.

= Does the free plugin collect analytics? =

No. There is no tracking pixel, no remote API and no phone-home. See the Privacy section for what is stored.

= Where do I get support or report a bug? =

Use the support forum at https://wordpress.org/support/plugin/foliora/ or open an issue on GitHub: https://github.com/itsmanzur/foliora/issues

== Developer Hooks ==

Add-ons can extend the viewer through public WordPress filters and actions. The full list, parameters and copy-paste examples are in `docs/HOOKS.md` in the GitHub repository: https://github.com/itsmanzur/foliora/blob/main/docs/HOOKS.md

== Screenshots ==

1. Front-end viewer with the compact toolbar (page, zoom, find, theme, share, fullscreen).
2. The 3D flipbook view.
3. Foliora dashboard with the setup checklist.
4. Shortcode Builder with live preview and saved embeds.
5. Docs & Features screen listing Media Library PDFs with previews and search/accessibility status.
6. Settings with a live width and height preview.
7. Gutenberg "Foliora Viewer" block with a Media Library picker and first-page preview.
8. Front-end document library grid (`[foliora_library]`).

== Privacy ==

Foliora does not collect any visitor data, sets no cookies, and has no analytics or tracking. The plugin has no remote requests of its own (see "External services").

What the plugin stores on your site:

* **Settings** – one option, `foliora_settings`.
* **Saved embeds** – a private custom post type, `foliora_embed`, with the embed's configuration in post meta.
* **PDF data** – post meta on your PDF attachments (extracted text, page count, indexed flag, accessibility flag, thumbnail ID) and a combined search index on posts and pages that embed a PDF.
* **Thumbnails** – first-page PNG images generated in your browser and saved in the Media Library.
* **Admin notice state** – one user meta value per administrator who dismisses the welcome notice, and a short-lived transient used for the redirect after activation.

In the visitor's own browser, Foliora uses `localStorage` (not cookies) to remember the last page read (`foliora:page:<file URL>`) and the chosen reading theme (`foliora_theme`). These values never leave the browser, and the visitor can clear them at any time.

The share feature only builds links; it contacts no one until the visitor clicks a link.

Deleting the plugin from the Plugins screen removes the settings, saved embeds, Foliora post and user meta, the transient and the thumbnails it generated. Your uploaded PDFs and other media are kept.

== Credits ==

Foliora is developed and maintained by itsmanzur.

Source code: https://github.com/itsmanzur/foliora
Website: https://thereadscope.com/foliora
PDF.js by Mozilla (Apache-2.0).

Support / Bug reports: https://wordpress.org/support/plugin/foliora/ and https://github.com/itsmanzur/foliora/issues

== Changelog ==

= 1.0.0 =
* Initial release.
* PDF viewer with a bundled PDF.js build, shortcode, Gutenberg block and direct PDF URL embed.
* Page, continuous scroll, two-page spread and 3D flipbook views; light, dark and sepia themes.
* Document library shortcode and block, Shortcode Builder with saved embeds, and a Docs & Features screen.
* PDF text search in WordPress search, Open Graph image and structured data.
* Accessibility: keyboard control, screen reader announcements and tagged-PDF check.
* Elementor, Divi and Beaver Builder widgets; WPML and Polylang support; Bengali translation.
* Public hooks and WP-CLI commands for developers.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
