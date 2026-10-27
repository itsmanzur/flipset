# WordPress.org assets

Source and build scripts for everything in `.wordpress-org/` (deployed to the SVN `/assets` folder, never shipped in the plugin zip; see `.distignore` and `bin/build-zip.js`).

| Asset | Built by |
| --- | --- |
| `icon-128x128.png`, `icon-256x256.png`, `icon.svg` | `node bin/wporg-assets/build-icons.js` (source: `icon.svg`) |
| `banner-1544x500.png`, `banner-772x250.png` and `-rtl` variants | `node bin/wporg-assets/build-banners.js` (source: `banner.html`) |
| `screenshot-1.png` … `screenshot-8.png` | `node bin/wporg-assets/screenshots.js [step ...]` |

Screenshot numbers match the captions under `== Screenshots ==` in `readme.txt`.

The scripts drive headless Edge or Chrome through `cdp.js` (no npm dependencies, Node 22+).

## Retaking the screenshots

The screenshots are real UI, taken from a throw-away WordPress site with fictional content.

1. Generate the sample PDFs: `node bin/wporg-assets/sample-pdfs.js` (writes `out/pdf/`).
2. Set up a scratch WordPress site with this plugin active, import the PDFs into the Media Library and create three pages: one with `[flipset]` in single-page view, one in flip view, and one with `[flipset_library]`.
3. Save the admin login cookies as `out/cookies.json` (CDP `Network.setCookie` objects) and the page ids plus the catalogue PDF URL as `out/pages.json`: `{"lib":13,"view":14,"flip":15,"catUrl":"…"}`.
4. Run `node bin/wporg-assets/screenshots.js prep` once so thumbnails, text indexing and the tagged-PDF flag are generated, then run the numbered steps. Set `SITE` if the site is not on `http://127.0.0.1:8100`.

`out/` is git-ignored. Delete `out/cookies.json` and destroy the admin session when you are done.
