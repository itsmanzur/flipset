# WordPress Playground demo

A one-click demo of Flipset that runs in the browser with [WordPress Playground](https://wordpress.org/playground/). It installs Flipset, adds an eight-page sample PDF to the Media Library and opens a page that shows the PDF as a flipbook, followed by a document library.

| File | Use |
|------|-----|
| `.wordpress-org/blueprints/blueprint.json` | The **Live Preview** on WordPress.org. Installs the released plugin from WordPress.org. |
| `docs/playground/blueprint-dev.json` | The same demo with the code on GitHub `main`, for checking changes before a release. |
| `docs/playground/flipset-demo.pdf` | The sample booklet. Both blueprints download it from GitHub `main`, so it must stay at this path. |
| `bin/make-demo-pdf.js` | Writes the booklet (`node bin/make-demo-pdf.js`). Plain shapes and Helvetica text, nothing borrowed. |

## Try it

- Released version: <https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/itsmanzur/flipset/main/.wordpress-org/blueprints/blueprint.json>
- `main` branch: <https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/itsmanzur/flipset/main/docs/playground/blueprint-dev.json>

The `main` branch has no minified files (they are built for the release zip), so the readable scripts load there. That is expected.

Offline, run the blueprint with the Playground CLI: `npx @wp-playground/cli server --blueprint=docs/playground/blueprint-dev.json`, then open <http://127.0.0.1:9400/flipset-demo/>.

## On WordPress.org

1. Copy `.wordpress-org/blueprints/blueprint.json` to `assets/blueprints/blueprint.json` in the plugin's SVN repository and commit it.
2. In the plugin's **Advanced** view on WordPress.org, set the preview to public. A **Live Preview** button then shows on the plugin page.

The blueprint installs whatever version is live on WordPress.org, so the preview always matches the released plugin.
