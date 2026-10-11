# Foliora developer hooks

Foliora (free) is a complete PDF viewer with no locked features and no upsell UI. It exposes WordPress filters and actions so a separate add-on (a future Foliora Pro, or any third party) can replace rendering, inject a toolbar, change the credit link, or gate licensed features without modifying this plugin.

You can build your own “Foliora Pro”-style extension with this public contract. You do not need undocumented internals.

Hook names use a slash namespace (`foliora/did_thing`) by design. All live PHP hooks follow that style.

**Viewer pipeline (in order):** saved-embed merge (`id`) → `shortcode_atts()` → `foliora/viewer_atts` → empty-file check → `foliora/pre_render_viewer` → `foliora/before_viewer` → toolbar (`foliora/download_url`) → `foliora/show_branding` → `foliora/viewer_file_url` → `foliora/viewer_html`.

---

## `foliora/loaded`

**Type:** action
**Fired from:** `Foliora::run()` in `includes/class-foliora.php`

Runs once after every free-plugin component (saved embeds, viewer, thumbnails, SEO, accessibility, compat, builders, library, block, auto-embed and, on admin screens, the admin classes) has run its `init()`.

**Parameters**

None.

Use this when your add-on needs Foliora’s classes to already exist, instead of racing `plugins_loaded`.

```php
add_action( 'foliora/loaded', function () {
	if ( class_exists( 'Foliora_Viewer' ) ) {
		// Safe to call Foliora APIs or enqueue companion assets.
	}
} );
```

---

## `foliora/pro_loaded`

**Type:** action (fired by the add-on, **consumed** by free)
**Consumed in:** `Foliora_Loader::is_pro_active()` via `did_action( 'foliora/pro_loaded' )`

The free plugin does **not** fire this. A Pro-style add-on must fire it on `plugins_loaded` **before** Foliora boots (Foliora free runs at priority `20`). Also define `FOLIORA_PRO_VERSION`. Both are required for `Foliora_Loader::is_pro_active()` to return true (unless overridden by `foliora/is_pro_active`).

```php
add_action( 'plugins_loaded', function () {
	if ( ! defined( 'FOLIORA_PRO_VERSION' ) ) {
		define( 'FOLIORA_PRO_VERSION', '1.0.0' );
	}
	do_action( 'foliora/pro_loaded' );
}, 5 );
```

---

## `foliora/is_pro_active`

**Type:** filter
**Fired from:** `Foliora_Loader::is_pro_active()` in `includes/class-foliora-loader.php`

Overrides Pro detection. The default is `defined( 'FOLIORA_PRO_VERSION' ) && did_action( 'foliora/pro_loaded' )`.

**Parameters**

| Name | Type | Meaning |
|------|------|---------|
| `$pro_confirmed` | `bool` | Built-in detection result |

The result is **cached** on the first call for the rest of the request. Hook this filter before anything that calls `Foliora_Loader::is_pro_active()` (for example on `plugins_loaded` priority `15`, before Foliora’s `20`).

```php
add_filter( 'foliora/is_pro_active', function ( $pro_confirmed ) {
	// Force “Pro UI” on a staging site without the add-on installed.
	if ( defined( 'WP_ENVIRONMENT_TYPE' ) && 'local' === WP_ENVIRONMENT_TYPE ) {
		return true;
	}
	return $pro_confirmed;
} );
```

---

## `foliora/feature_enabled`

**Type:** filter
**Fired from:** `Foliora_Loader::is_feature_enabled()` in `includes/class-foliora-loader.php`

Per-feature license gate. If Pro is not active, this filter never runs — the method returns `false`. If Pro **is** active, the default passed in is `true` for **every** key, including ones your add-on has not built yet; return `false` for any feature the current license does not include or that is not implemented.

**Parameters**

| Name | Type | Meaning |
|------|------|---------|
| `$enabled` | `bool` | Default `true` when Pro is active |
| `$feature_key` | `string` | The feature key your add-on asks about. Free defines none and shows no locked or upsell UI |

```php
add_filter( 'foliora/feature_enabled', function ( $enabled, $feature_key ) {
	$licensed = array( 'bookmarks' );
	return in_array( $feature_key, $licensed, true );
}, 10, 2 );
```

---

## `foliora/viewer_atts`

**Type:** filter
**Fired from:** `Foliora_Viewer::render_shortcode()` in `includes/class-foliora-viewer.php`

Runs immediately after `shortcode_atts()`, which fills defaults from Foliora → Settings (width, height, theme, lazy loading) and, when `id` is set, after the saved embed's configuration has been merged (explicit attributes win). Swap `file`, `width`, `height`, `mode`, `view` and so on without replacing the whole viewer. The empty-file check runs **after** this filter, so you can inject a missing file URL.

Used by `[foliora]` and by anything that calls `render_shortcode()` (the viewer block, the library’s in-place embeds).

**Parameters**

| Name | Type | Meaning |
|------|------|---------|
| `$atts` | `array` | See the attribute table below |

**Attributes** (every key is always present; booleans arrive as strings such as `'true'` / `'false'`, or `''` for "use the Settings default"):

| Key | Values / default |
|-----|------------------|
| `id` | Saved embed ID (`foliora_embed` post) or `''` |
| `file` | PDF URL (required) |
| `width`, `height` | CSS size; defaults from Settings (`100%` / `600px`) |
| `mode` | `pdf` (default). Reserved for add-ons: read it in `foliora/pre_render_viewer` to render something other than a PDF |
| `page` | Start page, default `1` |
| `title` | Optional caption |
| `theme` | `light` (default from Settings), `dark`, `sepia` |
| `view` | `page` (default), `scroll`, `spread`, `flip` (3D flipbook). Anything else falls back to `page`; `spread` and `flip` fall back to `page` in containers narrower than 640px |
| `download`, `print`, `search`, `share`, `shortcuts`, `presentation`, `fullscreen` | `true` / `false` / `''` (Settings default) |
| `theme_btn` | `true` / `false` / `''` — the light/dark/sepia toggle button |
| `hide` | Comma list of controls to hide: `nav`, `zoom`, `fit`, `rotate`, `view`, `pan`, `download`, `print`, `search`, `theme`, `share`, `shortcuts`, `presentation`, `fullscreen` |
| `loading` | `eager` (default) or `lazy` |
| `hash` | `true` (default) — sync `#page=N` in the URL |
| `resume` | `true` (default) — remember the last page in `localStorage` |

Return an array. Non-array values are discarded and treated as empty attributes.

```php
add_filter( 'foliora/viewer_atts', function ( $atts ) {
	if ( function_exists( 'pll_current_language' ) && 'fr' === pll_current_language() ) {
		$atts['file'] = 'https://example.com/docs/guide-fr.pdf';
	}
	return $atts;
} );
```

The free plugin already swaps Media Library attachments to the current WPML / Polylang translation (priority `5` on this same filter). Hook at `10` or later to override that.

---

## `foliora/translate_attachment_id`

**Type:** filter
**Fired from:** `Foliora_Compat::translate_attachment_id()` in `includes/class-foliora-compat.php`

Last chance to pick a different PDF attachment for the current language.

**Parameters**

| Name | Type | Meaning |
|------|------|---------|
| `$attachment_id` | `int` | ID after WPML / Polylang |
| `$original` | `int` | ID parsed from the file URL |

---

## `foliora/pre_render_viewer`

**Type:** filter
**Fired from:** `Foliora_Viewer::render_shortcode()` in `includes/class-foliora-viewer.php`

Return any non-`null` value to **replace** the default PDF.js markup entirely (EPUB viewer, a custom canvas, etc.). Returning `null` (the default) lets the free plugin render.

If you only need to change the file URL or wrap the HTML, use `foliora/viewer_atts` or `foliora/viewer_html` instead.

**Parameters**

| Name | Type | Meaning |
|------|------|---------|
| `$output` | `string\|null` | Default `null` (free plugin renders) |
| `$atts` | `array` | Attributes after `foliora/viewer_atts` |

Non-null output still passes through `foliora/viewer_html` before it is returned.

```php
add_filter( 'foliora/pre_render_viewer', function ( $output, $atts ) {
	if ( isset( $atts['mode'] ) && 'epub' === $atts['mode'] ) {
		return '<div class="my-epub-viewer" data-file="' . esc_url( $atts['file'] ) . '"></div>';
	}
	return $output;
}, 10, 2 );
```

---

## `foliora/before_viewer`

**Type:** action
**Fired from:** `Foliora_Viewer::render_shortcode()` in `includes/class-foliora-viewer.php`

Fires only on the free PDF path (skipped when `pre_render_viewer` short-circuits). Echo markup; it is captured and inserted into `.foliora-chrome` **before** the free toolbar. Free already ships its own light / dark / sepia theme button (`theme_btn`); do not add a second one.

**Parameters**

| Name | Type | Meaning |
|------|------|---------|
| `$instance_id` | `string` | Unique viewer element id (`foliora-viewer-{n}`) |
| `$atts` | `array` | Resolved attributes |

```php
add_action( 'foliora/before_viewer', function ( $instance_id, $atts ) {
	echo '<div class="my-reader-bar" data-for="' . esc_attr( $instance_id ) . '">';
	echo '<button type="button">' . esc_html__( 'Bookmark', 'my-foliora-addon' ) . '</button>';
	echo '</div>';
}, 10, 2 );
```

---

## `foliora/show_branding`

**Type:** filter
**Fired from:** `Foliora_Viewer::render_shortcode()` in `includes/class-foliora-viewer.php`

Whether to print the “Powered by Foliora” credit under the viewer. The default is the **Credit link** checkbox under Foliora → Settings, which is **off** until an administrator turns it on. Return `true` or `false` to override the setting for a site, a page or a single viewer.

**Parameters**

| Name | Type | Meaning |
|------|------|---------|
| `$show` | `bool` | Default: the saved `show_branding` setting (`false` unless enabled) |

```php
add_filter( 'foliora/show_branding', function ( $show ) {
	// Always show the credit on the marketing site.
	return is_page( 'about' ) ? true : $show;
} );
```

---

## `foliora/viewer_html`

**Type:** filter
**Fired from:** `Foliora_Viewer::render_shortcode()` in `includes/class-foliora-viewer.php`

Last chance to wrap or post-process the finished viewer HTML. Applied to the free plugin’s container **and** to any non-`null` `foliora/pre_render_viewer` return. Not applied to the editor-only notices (no file URL, unknown saved-embed ID).

**Parameters**

| Name | Type | Meaning |
|------|------|---------|
| `$html` | `string` | Rendered markup |
| `$atts` | `array` | Resolved attributes |

Escape anything you concatenate. Return valid HTML.

```php
add_filter( 'foliora/viewer_html', function ( $html, $atts ) {
	$label = isset( $atts['file'] ) ? basename( wp_parse_url( $atts['file'], PHP_URL_PATH ) ) : '';
	return '<figure class="my-doc"><figcaption>' . esc_html( $label ) . '</figcaption>' . $html . '</figure>';
}, 10, 2 );
```

---

## `foliora/viewer_file_url`

**Type:** filter
**Fired from:** `Foliora_Viewer::render_shortcode()` in `includes/class-foliora-viewer.php`

The URL the front-end viewer loads (`data-file`) and the no-JavaScript fallback link uses. Use it for signed or proxied URLs. It runs only on the free PDF path.

**Parameters**

| Name | Type | Meaning |
|------|------|---------|
| `$file_url` | `string` | `$atts['file']` |
| `$atts` | `array` | Resolved attributes |

---

## `foliora/download_url`

**Type:** filter
**Fired from:** `Foliora_Viewer::render_toolbar()` in `includes/class-foliora-viewer.php`

The `href` of the toolbar Download button. Defaults to the file URL; change it to send downloads through a tracked or protected endpoint without changing what the viewer renders.

**Parameters**

| Name | Type | Meaning |
|------|------|---------|
| `$download_url` | `string` | The PDF URL |
| `$atts` | `array` | Resolved attributes |

---

## `foliora/schema_digital_document`

**Type:** filter
**Fired from:** `Foliora_SEO::output_schema_jsonld()` in `includes/class-foliora-seo.php`

The Schema.org `DigitalDocument` array printed as JSON-LD for each PDF embedded on a singular post or shown on a PDF attachment page. Return an array to print it; any other value drops that document.

**Parameters**

| Name | Type | Meaning |
|------|------|---------|
| `$doc` | `array` | `@context`, `@type`, `name`, `url`, `encodingFormat`, and when known `numberOfPages`, `thumbnailUrl`, `description` |
| `$pdf_id` | `int` | PDF attachment ID |
| `$post_id` | `int` | Singular post ID that embeds the PDF |

---

## Related: DOM events

These are **not** WordPress hooks. The front-end viewer dispatches bubbling `CustomEvent`s on the `.foliora-viewer` element (`assets/js/foliora-viewer.js`). `event.detail` always includes `fileUrl` plus the fields below.

| Event | Extra `detail` fields |
|-------|----------------------|
| `foliora:ready` | `numPages`, `viewMode` |
| `foliora:pagechange` | `page`, `numPages` |
| `foliora:zoom` | `zoom`, `fitMode` (`page`, `width` or `none`) |
| `foliora:search` | `query`, `hitsCount`, `currentHit` (absent when there are no hits) |
| `foliora:print` | `page`, `pages` (the page numbers actually sent to the print window) |
| `foliora:download` | — |
| `foliora:error` | `stage` (`render`) |

```js
document.addEventListener( 'foliora:pagechange', function ( event ) {
	console.log( event.detail.page, 'of', event.detail.numPages );
} );
```

### JavaScript globals

- `window.FolioraViewer.bind( element )` binds a `.foliora-viewer` that was added to the page after load.
- `window.Foliora.pdfjsLib` is Foliora's own bundled PDF.js (3.11.174), captured right after its script runs. Use it instead of `window.pdfjsLib`: Foliora puts `window.pdfjsLib` back exactly as it found it (another plugin's copy, or unset) so it never collides with other PDF plugins, and you should not depend on the global either. Set `workerSrc` on this object to Foliora's worker (`FolioraConfig.workerSrc`).

---

## `foliora/extracted_text`

**Type:** filter
**Fired from:** `Foliora_SEO::ajax_save_extracted_text()` in `includes/class-foliora-seo.php`

Alters plain text extracted from a PDF before it is stored on the attachment. Runs after sanitizing and clipping to `foliora/extracted_text_max_chars` (default 80,000).

**Parameters**

| Name | Type | Meaning |
|------|------|---------|
| `$text` | `string` | Sanitized PDF text |
| `$pdf_id` | `int` | Attachment ID |

```php
add_filter( 'foliora/extracted_text', function ( $text, $pdf_id ) {
	return str_replace( 'CONFIDENTIAL', '', $text );
}, 10, 2 );
```

---

## `foliora/search_index`

**Type:** filter
**Fired from:** `Foliora_SEO` when rebuilding `_foliora_search_index` on a post/page

Combined text copied onto a host post so core search can match PDF contents.

**Parameters**

| Name | Type | Meaning |
|------|------|---------|
| `$blob` | `string` | Combined extracted text |
| `$post_id` | `int` | Host post ID |
| `$ids` | `int[]` | Embedded PDF attachment IDs |

---

## `foliora/og_image`

**Type:** filter
**Fired from:** `Foliora_SEO` when resolving a social-card image

**Parameters**

| Name | Type | Meaning |
|------|------|---------|
| `$url` | `string` | First-page thumbnail URL, or empty |
| `$post_id` | `int` | Host post ID |
| `$pdf_id` | `int` | First embedded PDF attachment ID |

---

## `foliora/extracted_text_max_chars`

**Type:** filter
**Fired from:** `Foliora_SEO` when clipping extracted text

Default `80000`. Return a positive integer.

---

## Naming

Every **live** `apply_filters()` / `do_action()` in this plugin uses slash-style names (`foliora/…`). There is no live underscore-style `foliora_*` hook.

A commented-out Freemius stub in `foliora.php` contains `do_action( 'foliora_fs_loaded' )`. That line is not executed.

The library shortcode (`[foliora_library]`) still goes through the viewer hooks above when it renders an embed. Thumbnail and text-index AJAX have no additional PHP filters beyond `foliora/extracted_text` and `foliora/extracted_text_max_chars`. Tagged-PDF detection is stored via `foliora_save_a11y` and can be overridden with `foliora/pdf_tagged`.

---

## `foliora/pdf_tagged`

**Type:** filter
**Fired from:** `Foliora_A11y::is_tagged()` in `includes/class-foliora-a11y.php`

Overrides whether an attachment is treated as a tagged (accessible) PDF. The default is the MarkInfo `/Marked` flag stored when the Foliora → Docs & Features → Documents tab is opened.

**Parameters**

| Name | Type | Meaning |
|------|------|---------|
| `$tagged` | `bool` | Stored MarkInfo result |
| `$attachment_id` | `int` | PDF attachment ID |

```php
add_filter( 'foliora/pdf_tagged', function ( $tagged, $attachment_id ) {
	if ( 123 === (int) $attachment_id ) {
		return true;
	}
	return $tagged;
}, 10, 2 );
```
