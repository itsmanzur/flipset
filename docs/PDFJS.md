# Bundled PDF.js

Flipset renders PDFs with Mozilla PDF.js, shipped inside the plugin (no CDN).

| Item | Value |
|---|---|
| Version | 3.11.174 (`pdfjs-dist`, minified build) |
| Files | `assets/vendor/pdfjs/pdf.min.js`, `assets/vendor/pdfjs/pdf.worker.min.js` |
| Licence | Apache-2.0 (see `assets/vendor/pdfjs/LICENSE-NOTICE.txt`) |
| Listed in | `readme.txt` → Third-party libraries |

## Why 3.11.174

It is the last PDF.js release that ships a classic (non-module) build, which works with every caching and minifying plugin Flipset supports. Moving to a current release needs a module worker and a compatibility pass; that is scheduled for Flipset 2.0.

## Known advisories and how Flipset handles them

| Advisory | Affects | Fixed upstream | Flipset mitigation |
|---|---|---|---|
| CVE-2024-4367 (a crafted PDF font could run script) | PDF.js before 4.2.67 | 4.2.67 | Every `getDocument()` call passes `isEvalSupported: false` (viewer, thumbnail generator, block editor preview). A PHPUnit test fails if a call is added without it. Shipped in 1.1.3. |

Confirm advisory details against upstream before quoting them: <https://github.com/mozilla/pdf.js/security/advisories>

## Monthly check

On the first working day of each month:

1. Read the PDF.js security advisories (link above) and the `pdfjs-dist` release notes.
2. If an advisory affects 3.11.174 and `isEvalSupported: false` is not enough of a mitigation, ship a patch release with a backported fix or bring the 2.0 engine upgrade forward.
3. Update this file's table with the date checked.

Last checked: 2026-11-19.

## Rules for code in this repo

- Always pass `isEvalSupported: false` to `getDocument()`.
- Never enable `enableXfa` or load remote fonts for a PDF.
- Only http, https and site-relative PDF addresses may reach the page: use `Flipset_Viewer::sanitize_file_url()`.
