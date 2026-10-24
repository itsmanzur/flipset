<?php
/**
 * Flipset_Admin
 *
 * Top-level Flipset menu: Dashboard (home) + Settings. Dashboard is
 * the interactive shell — setup checklist and embed builder. Settings still owns Viewer Defaults via the Settings
 * API (PAGE_SLUG), so add-ons can keep adding fields there.
 *
 * PAGE_SLUG stays `flipset-settings` because add-on settings
 * sections parent off it.
 *
 * @package Flipset
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Flipset_Admin {

	/**
	 * Settings API option group for this screen. Add-ons
	 * must register_setting() against this same group so the single
	 * settings_fields() call in render_settings_page() covers every
	 * option on the page. Store add-on data under a *different* option
	 * name — this class's sanitize_settings() only ever writes
	 * OPTION_NAME and must not see or clobber add-on options.
	 */
	const OPTION_GROUP  = 'flipset_settings_group';
	const OPTION_NAME   = 'flipset_settings';
	const PAGE_SLUG     = 'flipset-settings';
	const BUILDER_SLUG  = 'flipset-shortcode-builder';
	const SETTINGS_SLUG = 'flipset-viewer-settings';
	const DOCS_SLUG     = 'flipset-documents';

	/**
	 * Hidden field that only the Settings screen form posts. Checkboxes are
	 * absent from a POST when unticked, so the sanitizer needs an explicit
	 * signal to tell "user unticked it" from "this update didn't mention it".
	 */
	const FORM_MARKER = '_flipset_form';

	public function init() {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'wp_ajax_flipset_mark_setup_copied', array( $this, 'ajax_mark_setup_copied' ) );
		add_action( 'wp_ajax_flipset_create_test_page', array( $this, 'ajax_create_test_page' ) );
		add_action( 'wp_ajax_flipset_settings_preview', array( $this, 'ajax_settings_preview' ) );
		add_action( 'wp_ajax_flipset_dismiss_welcome', array( $this, 'ajax_dismiss_welcome' ) );
		// Invalidate the embed-count cache whenever a post is saved, trashed,
		// restored, or deleted so the Dashboard metric stays accurate without
		// waiting for the 5-minute TTL. count_embeds() only counts
		// publish/draft/private posts, so trash/untrash need their own hooks —
		// save_post does not reliably fire for those status transitions.
		add_action( 'save_post', array( $this, 'flush_embed_count_cache' ) );
		add_action( 'delete_post', array( $this, 'flush_embed_count_cache' ) );
		add_action( 'trashed_post', array( $this, 'flush_embed_count_cache' ) );
		add_action( 'untrashed_post', array( $this, 'flush_embed_count_cache' ) );
	}

	/**
	 * Delete the cached embed count so the Dashboard stat is recalculated on
	 * the next page load. Called on save_post and delete_post.
	 */
	public function flush_embed_count_cache() {
		wp_cache_delete( 'embed_count', 'flipset' );
	}

	public function add_settings_page() {
		add_menu_page(
			__( 'Flipset', 'flipset' ),
			__( 'Flipset', 'flipset' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_dashboard' ),
			'dashicons-media-document',
			58
		);

		add_submenu_page(
			self::PAGE_SLUG,
			__( 'Flipset Dashboard', 'flipset' ),
			__( 'Dashboard', 'flipset' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_dashboard' )
		);

		add_submenu_page(
			self::PAGE_SLUG,
			__( 'Flipset Shortcode Builder', 'flipset' ),
			__( 'Shortcode Builder', 'flipset' ),
			'manage_options',
			self::BUILDER_SLUG,
			array( $this, 'render_builder_page' )
		);

		add_submenu_page(
			self::PAGE_SLUG,
			__( 'Flipset Docs & Features', 'flipset' ),
			__( 'Docs & Features', 'flipset' ),
			'manage_options',
			self::DOCS_SLUG,
			array( $this, 'render_documents_page' )
		);

		add_submenu_page(
			self::PAGE_SLUG,
			__( 'Flipset Settings', 'flipset' ),
			__( 'Settings', 'flipset' ),
			'manage_options',
			self::SETTINGS_SLUG,
			array( $this, 'render_settings_page' )
		);
	}

	public function enqueue_admin_assets( $hook ) {
		$page         = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- admin screen routing.
		$is_dashboard = ( self::PAGE_SLUG === $page || 'toplevel_page_' . self::PAGE_SLUG === $hook );
		$is_builder   = ( self::BUILDER_SLUG === $page || self::PAGE_SLUG . '_page_' . self::BUILDER_SLUG === $hook || 'flipset_page_' . self::BUILDER_SLUG === $hook );
		$is_settings  = ( self::SETTINGS_SLUG === $page || self::PAGE_SLUG . '_page_' . self::SETTINGS_SLUG === $hook );
		$is_docs      = ( self::DOCS_SLUG === $page || self::PAGE_SLUG . '_page_' . self::DOCS_SLUG === $hook || 'flipset_page_' . self::DOCS_SLUG === $hook );

		if ( ! $is_dashboard && ! $is_builder && ! $is_settings && ! $is_docs ) {
			return;
		}

		// Shared admin design system: tokens + fa-* components, used by every Flipset screen.
		wp_enqueue_style(
			'flipset-admin-ui',
			FLIPSET_URL . 'assets/css/flipset-admin-ui.css',
			array(),
			$this->asset_ver( 'assets/css/flipset-admin-ui.css' )
		);

		wp_enqueue_style(
			'flipset-admin',
			FLIPSET_URL . 'assets/css/flipset-admin.css',
			array( 'flipset-admin-ui' ),
			$this->asset_ver( 'assets/css/flipset-admin.css' )
		);

		if ( $is_dashboard ) {
			wp_enqueue_style(
				'flipset-dashboard',
				FLIPSET_URL . 'assets/css/flipset-dashboard.css',
				array( 'flipset-admin-ui' ),
				$this->asset_ver( 'assets/css/flipset-dashboard.css' )
			);
		}

		if ( $is_settings ) {
			wp_enqueue_style(
				'flipset-settings',
				FLIPSET_URL . 'assets/css/flipset-settings.css',
				array( 'flipset-admin-ui' ),
				$this->asset_ver( 'assets/css/flipset-settings.css' )
			);
		}

		if ( $is_docs ) {
			wp_enqueue_style(
				'flipset-docs',
				FLIPSET_URL . 'assets/css/flipset-docs.css',
				array( 'flipset-admin-ui' ),
				$this->asset_ver( 'assets/css/flipset-docs.css' )
			);
		}

		if ( $is_settings || $is_builder ) {
			wp_enqueue_style(
				'flipset-viewer',
				FLIPSET_URL . 'assets/css/flipset-viewer.css',
				array(),
				$this->asset_ver( 'assets/css/flipset-viewer.css' )
			);
			Flipset_Viewer::register_pdfjs();
			wp_enqueue_script( 'flipset-pdfjs' );
			wp_enqueue_script(
				'flipset-viewer',
				FLIPSET_URL . 'assets/js/flipset-viewer.js',
				array( 'flipset-pdfjs' ),
				$this->asset_ver( 'assets/js/flipset-viewer.js' ),
				true
			);
			wp_localize_script(
				'flipset-viewer',
				'FlipsetConfig',
				array(
					'workerSrc' => FLIPSET_URL . 'assets/vendor/pdfjs/pdf.worker.min.js',
					'isPro'     => Flipset_Loader::is_pro_active(),
					'i18n'      => Flipset_Viewer::viewer_i18n(),
				)
			);
		}

		if ( $is_docs && current_user_can( 'upload_files' ) ) {
			Flipset_Viewer::register_pdfjs();
			wp_enqueue_script( 'flipset-pdfjs' );
			wp_enqueue_script(
				'flipset-thumbnail-gen',
				FLIPSET_URL . 'assets/js/flipset-thumbnail-gen.js',
				array( 'flipset-pdfjs' ),
				$this->asset_ver( 'assets/js/flipset-thumbnail-gen.js' ),
				true
			);
			wp_localize_script(
				'flipset-thumbnail-gen',
				'FlipsetThumbGen',
				array(
					'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
					'nonce'     => wp_create_nonce( 'flipset_admin' ),
					'workerSrc' => FLIPSET_URL . 'assets/vendor/pdfjs/pdf.worker.min.js',
					'maxPages'  => 40,
					'maxChars'  => 80000,
					'i18n'      => array(
						'indexed'      => __( 'Indexed', 'flipset' ),
						'noText'       => __( 'No text', 'flipset' ),
						'tagged'       => __( 'Tagged', 'flipset' ),
						'untagged'     => __( 'Untagged', 'flipset' ),
						'untaggedHint' => __( 'This PDF has no accessibility tags. Export it from Word, InDesign, or Acrobat as a tagged PDF so screen readers can follow headings and reading order.', 'flipset' ),
						'taggedHint'   => __( 'Tagged PDF. Screen readers can follow its structure.', 'flipset' ),
						'indexedHint'  => __( 'Text extracted. WordPress search can find words in this PDF.', 'flipset' ),
						'noTextHint'   => __( 'No selectable text was found (a scanned image PDF), so it cannot be searched.', 'flipset' ),
					),
				)
			);
		}

		if ( $is_dashboard || $is_docs || $is_builder ) {
			wp_enqueue_media();
		}

		wp_enqueue_script(
			'flipset-admin',
			FLIPSET_URL . 'assets/js/flipset-admin.js',
			array(),
			$this->asset_ver( 'assets/js/flipset-admin.js' ),
			true
		);

		wp_localize_script(
			'flipset-admin',
			'FlipsetAdmin',
			array(
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( 'flipset_admin' ),
				'i18n'        => array(
					'selectPdf'       => __( 'Select PDF', 'flipset' ),
					'usePdf'          => __( 'Use this PDF', 'flipset' ),
					'copied'          => __( 'Copied!', 'flipset' ),
					'copiedToast'     => __( 'Shortcode copied', 'flipset' ),
					'codeCopied'      => __( 'Code snippet copied to clipboard', 'flipset' ),
					'dismissed'       => __( 'Dismissed', 'flipset' ),
					/* translators: 1: completed steps, 2: total steps. */
					'stepsComplete'   => __( '%1$s of %2$s steps complete', 'flipset' ),
					'setupDone'       => __( 'You are set. New documents go in Documents or the Media Library.', 'flipset' ),
					'dropHint'        => __( 'Drop a PDF to embed it', 'flipset' ),
					'uploading'       => __( 'Uploading…', 'flipset' ),
					'creating'        => __( 'Creating page…', 'flipset' ),
					'testPageReady'   => __( 'Test page ready', 'flipset' ),
					'createFail'      => __( 'Could not create the test page.', 'flipset' ),
					'copyShortcode'   => __( 'Copy shortcode', 'flipset' ),
					'notPdf'          => __( 'Please drop a PDF file.', 'flipset' ),
					'uploadFail'      => __( 'Upload failed. Try the Media Library.', 'flipset' ),
					'presetApplied'   => __( 'Preset applied', 'flipset' ),
					'selectPdfFirst'  => __( 'Please select a PDF file.', 'flipset' ),
					'previewEmpty'    => __( 'Select or upload a PDF above to see live interactive preview.', 'flipset' ),
					'saving'          => __( 'Saving…', 'flipset' ),
					'updateEmbed'     => __( 'Update Embed & Copy', 'flipset' ),
					'savedCopiedBtn'  => __( '✓ Saved! Copied', 'flipset' ),
					/* translators: %s: shortcode that was copied. */
					'toastSaved'      => __( 'Saved! %s copied to clipboard.', 'flipset' ),
					'saveFailed'      => __( 'Could not save embed.', 'flipset' ),
					'testPageFailed'  => __( 'Could not create test page.', 'flipset' ),
					/* translators: %s: text that was copied. */
					'copiedItem'      => __( 'Copied: %s', 'flipset' ),
					'embedDuplicated' => __( 'Embed duplicated successfully.', 'flipset' ),
					'duplicateFailed' => __( 'Could not duplicate embed.', 'flipset' ),
					/* translators: %s: embed ID. */
					'confirmDelete'   => __( 'Are you sure you want to delete this embed? Any shortcode using id="%s" will no longer work.', 'flipset' ),
					'embedDeleted'    => __( 'Embed deleted.', 'flipset' ),
					'deleteFailed'    => __( 'Could not delete embed.', 'flipset' ),
				),
				'uploadNonce' => wp_create_nonce( 'media-form' ),
				'canUpload'   => current_user_can( 'upload_files' ),
			)
		);

		if ( $is_settings ) {
			wp_enqueue_script(
				'flipset-settings',
				FLIPSET_URL . 'assets/js/flipset-settings.js',
				array( 'flipset-viewer' ),
				$this->asset_ver( 'assets/js/flipset-settings.js' ),
				true
			);
			wp_localize_script(
				'flipset-settings',
				'FlipsetSettings',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'flipset_admin' ),
					'i18n'    => array(
						/* translators: 1: number of switches turned on, 2: number of switches in the section. */
						'countOn'      => __( '%1$s/%2$s on', 'flipset' ),
						'saved'        => __( 'Settings saved', 'flipset' ),
						'confirmReset' => __( 'Reset this section to the default values? Nothing is saved until you click Save changes.', 'flipset' ),
						'previewFail'  => __( 'Could not load the preview.', 'flipset' ),
						/* translators: %s: configured viewer height, for example 600px. */
						'heightChip'   => __( 'Height %s · scaled to fit', 'flipset' ),
						'loading'      => __( 'Loading preview…', 'flipset' ),
					),
				)
			);
		}
	}

	/**
	 * Asset version query. With WP_DEBUG on, the file's modification time is
	 * added so edited admin assets are never served from the browser cache.
	 *
	 * @param string $relative Path inside the plugin, e.g. assets/css/flipset-docs.css.
	 * @return string
	 */
	private function asset_ver( $relative ) {
		$file = FLIPSET_DIR . ltrim( $relative, '/' );
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && file_exists( $file ) ) {
			return FLIPSET_VERSION . '.' . filemtime( $file );
		}
		return FLIPSET_VERSION;
	}

	public function register_settings() {
		register_setting( self::OPTION_GROUP, self::OPTION_NAME, array( $this, 'sanitize_settings' ) );

		add_settings_section(
			'flipset_general',
			__( 'Viewer Defaults', 'flipset' ),
			'__return_false',
			self::PAGE_SLUG
		);

		add_settings_field(
			'default_width',
			__( 'Default width', 'flipset' ),
			array( $this, 'field_default_width' ),
			self::PAGE_SLUG,
			'flipset_general'
		);

		add_settings_field(
			'default_height',
			__( 'Default height', 'flipset' ),
			array( $this, 'field_default_height' ),
			self::PAGE_SLUG,
			'flipset_general'
		);

		add_settings_field(
			'allow_download',
			__( 'Download button', 'flipset' ),
			array( $this, 'field_allow_download' ),
			self::PAGE_SLUG,
			'flipset_general'
		);

		add_settings_field(
			'allow_print',
			__( 'Print button', 'flipset' ),
			array( $this, 'field_allow_print' ),
			self::PAGE_SLUG,
			'flipset_general'
		);

		add_settings_field(
			'default_theme',
			__( 'Default theme', 'flipset' ),
			array( $this, 'field_default_theme' ),
			self::PAGE_SLUG,
			'flipset_general'
		);

		add_settings_field(
			'lazy_loading',
			__( 'Lazy loading', 'flipset' ),
			array( $this, 'field_lazy_loading' ),
			self::PAGE_SLUG,
			'flipset_general'
		);

		add_settings_field(
			'show_branding',
			__( 'Credit link', 'flipset' ),
			array( $this, 'field_show_branding' ),
			self::PAGE_SLUG,
			'flipset_general'
		);

		add_settings_section(
			'flipset_toolbar',
			__( 'Toolbar Controls', 'flipset' ),
			array( $this, 'section_toolbar' ),
			self::PAGE_SLUG
		);

		add_settings_field(
			'allow_search',
			__( 'Search in document', 'flipset' ),
			array( $this, 'field_allow_search' ),
			self::PAGE_SLUG,
			'flipset_toolbar'
		);

		add_settings_field(
			'allow_theme',
			__( 'Theme switcher', 'flipset' ),
			array( $this, 'field_allow_theme' ),
			self::PAGE_SLUG,
			'flipset_toolbar'
		);

		add_settings_field(
			'allow_share',
			__( 'Share button', 'flipset' ),
			array( $this, 'field_allow_share' ),
			self::PAGE_SLUG,
			'flipset_toolbar'
		);

		add_settings_field(
			'allow_shortcuts',
			__( 'Keyboard shortcuts guide', 'flipset' ),
			array( $this, 'field_allow_shortcuts' ),
			self::PAGE_SLUG,
			'flipset_toolbar'
		);

		add_settings_field(
			'allow_fullscreen',
			__( 'Full screen button', 'flipset' ),
			array( $this, 'field_allow_fullscreen' ),
			self::PAGE_SLUG,
			'flipset_toolbar'
		);

		add_settings_field(
			'allow_presentation',
			__( 'Presentation button', 'flipset' ),
			array( $this, 'field_allow_presentation' ),
			self::PAGE_SLUG,
			'flipset_toolbar'
		);

		add_settings_section(
			'flipset_discover',
			__( 'Search and sharing', 'flipset' ),
			array( $this, 'section_discover' ),
			self::PAGE_SLUG
		);

		add_settings_field(
			'index_pdf_text',
			__( 'Site search', 'flipset' ),
			array( $this, 'field_index_pdf_text' ),
			self::PAGE_SLUG,
			'flipset_discover'
		);

		add_settings_field(
			'social_preview',
			__( 'Social preview', 'flipset' ),
			array( $this, 'field_social_preview' ),
			self::PAGE_SLUG,
			'flipset_discover'
		);

		add_settings_field(
			'check_pdf_a11y',
			__( 'Accessibility tags', 'flipset' ),
			array( $this, 'field_check_pdf_a11y' ),
			self::PAGE_SLUG,
			'flipset_discover'
		);

		add_settings_field(
			'autoembed_url',
			__( 'Direct PDF auto-embed', 'flipset' ),
			array( $this, 'field_autoembed_url' ),
			self::PAGE_SLUG,
			'flipset_discover'
		);

		add_settings_field(
			'embed_attachment_pages',
			__( 'Attachment pages', 'flipset' ),
			array( $this, 'field_embed_attachment_pages' ),
			self::PAGE_SLUG,
			'flipset_discover'
		);
	}

	/**
	 * Bound only to OPTION_NAME (`flipset_settings`). Returning a fresh
	 * array of free-plugin keys means extra POST keys cannot persist
	 * here, and a separately registered option such as
	 * `flipset_pro_settings` is never read or written.
	 *
	 * @param mixed $input Raw submitted option value.
	 * @return array Sanitized settings.
	 */
	public function sanitize_settings( $input ) {
		$existing = get_option( self::OPTION_NAME, array() );
		if ( ! is_array( $existing ) ) {
			$existing = array();
		}
		if ( ! is_array( $input ) ) {
			$input = array();
		}

		// Only the Settings screen posts the marker. Every other write
		// (activation, AJAX, add-ons, the add_option() re-run of this
		// callback) is programmatic: keys it omits keep their current value,
		// or their default when never stored, instead of turning off.
		$is_form = ! empty( $input[ self::FORM_MARKER ] );

		$flag_defaults = $this->flag_defaults();

		$valid_themes = array( 'light', 'dark', 'sepia' );
		$theme        = isset( $input['default_theme'] ) && in_array( $input['default_theme'], $valid_themes, true )
			? $input['default_theme']
			: ( $existing['default_theme'] ?? 'light' );

		$clean = array(
			'default_width'  => isset( $input['default_width'] ) ? $this->sanitize_css_dimension( $input['default_width'], $existing['default_width'] ?? '100%' ) : ( $existing['default_width'] ?? '100%' ),
			'default_height' => isset( $input['default_height'] ) ? $this->sanitize_css_dimension( $input['default_height'], $existing['default_height'] ?? '600px' ) : ( $existing['default_height'] ?? '600px' ),
			'default_theme'  => $theme,
		);

		foreach ( $flag_defaults as $key => $default ) {
			// 'setup_copied_shortcode' is never a form field.
			if ( $is_form && 'setup_copied_shortcode' !== $key ) {
				$clean[ $key ] = ! empty( $input[ $key ] );
			} elseif ( array_key_exists( $key, $input ) ) {
				$clean[ $key ] = ! empty( $input[ $key ] );
			} elseif ( array_key_exists( $key, $existing ) ) {
				$clean[ $key ] = ! empty( $existing[ $key ] );
			} else {
				$clean[ $key ] = $default;
			}
		}

		return $clean;
	}

	/**
	 * Validate a user-supplied CSS dimension value. Accepts common units:
	 * px, %, em, rem, vh, vw, svh, dvh, lvh. Returns $default on failure.
	 *
	 * @param string $value   Raw value from the settings form.
	 * @param string $default Fallback if the value is not a valid CSS dimension.
	 * @return string
	 */
	private function sanitize_css_dimension( $value, $default ) {
		$value = trim( sanitize_text_field( (string) $value ) );
		// Allow a positive number followed by a recognised CSS length unit.
		if ( preg_match( '/^\d+(\.\d+)?(px|%|em|rem|vh|vw|svh|dvh|lvh)$/', $value ) ) {
			return $value;
		}
		// Also allow bare "0" (no unit required for zero).
		if ( '0' === $value ) {
			return $value;
		}
		return $default;
	}

	public function section_toolbar() {
		echo '<p class="description">' . esc_html__( 'Enable or disable specific buttons across all embedded PDF viewers. Individual embeds can also customize controls via the "hide" attribute.', 'flipset' ) . '</p>';
	}

	public function field_default_theme() {
		$this->render_control( 'default_theme' );
	}

	public function field_show_branding() {
		$this->render_control( 'show_branding' );
	}

	public function field_lazy_loading() {
		$this->render_control( 'lazy_loading' );
	}

	public function field_allow_search() {
		$this->render_control( 'allow_search' );
	}

	public function field_allow_theme() {
		$this->render_control( 'allow_theme' );
	}

	public function field_allow_share() {
		$this->render_control( 'allow_share' );
	}

	public function field_allow_shortcuts() {
		$this->render_control( 'allow_shortcuts' );
	}

	public function field_allow_fullscreen() {
		$this->render_control( 'allow_fullscreen' );
	}

	public function field_allow_presentation() {
		$this->render_control( 'allow_presentation' );
	}

	public function field_default_width() {
		$this->render_control( 'default_width' );
	}

	public function field_default_height() {
		$this->render_control( 'default_height' );
	}

	public function field_allow_download() {
		$this->render_control( 'allow_download' );
	}

	public function field_allow_print() {
		$this->render_control( 'allow_print' );
	}

	public function field_index_pdf_text() {
		$this->render_control( 'index_pdf_text' );
	}

	public function field_social_preview() {
		$this->render_control( 'social_preview' );
	}

	public function field_check_pdf_a11y() {
		$this->render_control( 'check_pdf_a11y' );
	}

	public function field_autoembed_url() {
		$this->render_control( 'autoembed_url' );
	}

	public function field_embed_attachment_pages() {
		$this->render_control( 'embed_attachment_pages' );
	}

	public function section_discover() {
		echo '<p class="description">' . esc_html__( 'Make embedded PDFs findable in WordPress search and nicer when a page is shared.', 'flipset' ) . '</p>';
	}

	public function render_dashboard() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$pretty       = (bool) get_option( 'permalink_structure' );
		$pdf_count    = $this->count_pdfs();
		$page_count   = $this->count_embeds();
		$saved_embeds = Flipset_Embeds::get_all();
		$saved_count  = count( $saved_embeds );
		$recent_saved = array_slice( $saved_embeds, 0, 5 );
		$settings     = get_option( self::OPTION_NAME, array() );
		$copied       = ! empty( $settings['setup_copied_shortcode'] );
		$recent       = $this->get_recent_pdfs( 4 );
		$prefill      = ! empty( $recent[0] ) ? $recent[0] : null;
		$prefill_url  = $prefill ? $prefill['url'] : '';
		$prefill_code = $prefill_url ? sprintf( '[flipset file="%s"]', esc_url_raw( $prefill_url ) ) : '';

		$steps        = array(
			'uploaded' => $pdf_count > 0,
			'copied'   => $copied,
			'embedded' => $page_count > 0,
		);
		$done         = count( array_filter( $steps ) );
		$total_steps  = count( $steps );
		$percent      = (int) round( ( $done / $total_steps ) * 100 );
		$setup_done   = $done === $total_steps;
		$docs_url     = admin_url( 'admin.php?page=' . self::DOCS_SLUG );
		$guide_url    = admin_url( 'admin.php?page=' . self::DOCS_SLUG . '&tab=docs' );
		$settings_url = admin_url( 'admin.php?page=' . self::SETTINGS_SLUG );
		$builder_url  = admin_url( 'admin.php?page=' . self::BUILDER_SLUG );
		$embeds_url   = add_query_arg( 'view', 'embeds', $builder_url );
		$open_url     = ( $prefill && ! empty( $prefill['id'] ) ) ? add_query_arg( 'pdf_id', (int) $prefill['id'], $builder_url ) : $builder_url;
		$show_welcome = ! $setup_done && ! get_user_meta( get_current_user_id(), 'flipset_welcome_dismissed', true );
		$title_attr   = $prefill ? $prefill['title'] : '';
		?>
		<div class="wrap flipset-admin fa-dash">
			<div class="fa-hero">
				<div class="fa-hero__top">
					<div class="fa-hero__copy">
						<div class="fa-hero__kicker">
							<span class="fa-mark dashicons dashicons-book-alt" aria-hidden="true"></span>
							<h1 class="fa-hero__title"><?php esc_html_e( 'Flipset', 'flipset' ); ?></h1>
						</div>
						<p class="fa-hero__lede">
							<?php esc_html_e( 'Embed PDFs with a bundled viewer. No CDN, no account, no API keys.', 'flipset' ); ?>
						</p>
						<?php if ( ! $pretty ) : ?>
							<span class="fa-chip fa-chip--warn"><?php esc_html_e( 'Plain permalinks — share links need Post name', 'flipset' ); ?></span>
						<?php endif; ?>
					</div>
					<div class="fa-hero__side">
						<div class="fa-ring<?php echo $setup_done ? ' is-complete' : ''; ?>" id="flipset-ring-wrap">
							<svg class="fa-ring__svg" viewBox="0 0 36 36" aria-hidden="true" focusable="false">
								<defs>
									<linearGradient id="fa-ring-grad" x1="0" y1="0" x2="1" y2="1">
										<stop offset="0" style="stop-color:var(--fb-accent)" />
										<stop offset="1" style="stop-color:var(--fb-accent-2)" />
									</linearGradient>
								</defs>
								<circle class="fa-ring__track" cx="18" cy="18" r="15.5" pathLength="100" />
								<circle class="fa-ring__fill" id="flipset-ring-fg" cx="18" cy="18" r="15.5" pathLength="100" stroke-dasharray="<?php echo esc_attr( (string) $percent ); ?> 100" />
							</svg>
							<span class="fa-ring__label" id="flipset-ring-label"><?php echo esc_html( $done . '/' . $total_steps ); ?></span>
						</div>
						<div class="fa-hero__actions">
							<a class="fa-btn fa-btn--primary" href="<?php echo esc_url( $builder_url ); ?>">
								<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
								<?php esc_html_e( 'New Embed', 'flipset' ); ?>
							</a>
							<a class="fa-btn fa-btn--ghost-dark" href="<?php echo esc_url( $docs_url ); ?>">
								<span class="dashicons dashicons-media-document" aria-hidden="true"></span>
								<?php esc_html_e( 'Documents', 'flipset' ); ?>
							</a>
						</div>
					</div>
				</div>
				<div class="fa-hero__divider" role="presentation"></div>
				<div class="fa-hero__quick">
					<span class="fa-hero__label"><?php esc_html_e( 'Quick actions:', 'flipset' ); ?></span>
					<div class="fa-hero__chips">
						<button type="button" class="fa-chip" id="fa-upload-trigger">
							<span aria-hidden="true">📤</span> <?php esc_html_e( 'Upload PDF', 'flipset' ); ?>
						</button>
						<input type="file" id="fa-upload-input" accept="application/pdf,.pdf" hidden />
						<a class="fa-chip" href="<?php echo esc_url( $builder_url ); ?>"><span aria-hidden="true">🧩</span> <?php esc_html_e( 'Shortcode Builder', 'flipset' ); ?></a>
						<a class="fa-chip" href="<?php echo esc_url( $embeds_url ); ?>"><span aria-hidden="true">📁</span> <?php esc_html_e( 'Saved Embeds', 'flipset' ); ?></a>
						<a class="fa-chip" href="<?php echo esc_url( $settings_url ); ?>"><span aria-hidden="true">⚙️</span> <?php esc_html_e( 'Viewer Settings', 'flipset' ); ?></a>
						<a class="fa-chip" href="<?php echo esc_url( $guide_url ); ?>"><span aria-hidden="true">📘</span> <?php esc_html_e( 'Docs & Features', 'flipset' ); ?></a>
					</div>
				</div>
			</div>

			<?php if ( $show_welcome ) : ?>
				<div class="fa-banner" id="flipset-welcome" role="status">
					<p>
						<?php esc_html_e( 'Pick a cover on the right — or drop a PDF — then copy the shortcode.', 'flipset' ); ?>
					</p>
					<button type="button" class="fa-banner__dismiss" id="flipset-welcome-dismiss">
						<span class="screen-reader-text"><?php esc_html_e( 'Dismiss this notice.', 'flipset' ); ?></span>
						<span aria-hidden="true">&times;</span>
					</button>
				</div>
			<?php endif; ?>

			<div class="fa-metrics" role="list">
				<a class="fa-card fa-card--lift fa-metric" role="listitem" id="flipset-metric-pdfs" href="<?php echo esc_url( $docs_url ); ?>">
					<span class="fa-metric__icon is-indigo"><span class="dashicons dashicons-media-document" aria-hidden="true"></span></span>
					<span class="fa-metric__body">
						<span class="fa-metric__value" data-count="<?php echo esc_attr( (string) $pdf_count ); ?>"><?php echo esc_html( (string) $pdf_count ); ?></span>
						<span class="fa-metric__label"><?php esc_html_e( 'PDFs', 'flipset' ); ?></span>
					</span>
					<span class="fa-metric__link" aria-hidden="true"><?php esc_html_e( 'View', 'flipset' ); ?> &rarr;</span>
				</a>
				<a class="fa-card fa-card--lift fa-metric" role="listitem" id="flipset-metric-embeds" href="<?php echo esc_url( $embeds_url ); ?>">
					<span class="fa-metric__icon is-violet"><span class="dashicons dashicons-portfolio" aria-hidden="true"></span></span>
					<span class="fa-metric__body">
						<span class="fa-metric__value" data-count="<?php echo esc_attr( (string) $saved_count ); ?>"><?php echo esc_html( (string) $saved_count ); ?></span>
						<span class="fa-metric__label"><?php esc_html_e( 'Saved Embeds', 'flipset' ); ?></span>
					</span>
					<span class="fa-metric__link" aria-hidden="true"><?php esc_html_e( 'View', 'flipset' ); ?> &rarr;</span>
				</a>
				<div class="fa-card fa-card--lift fa-metric" role="listitem" id="flipset-metric-pages">
					<span class="fa-metric__icon is-sky"><span class="dashicons dashicons-admin-page" aria-hidden="true"></span></span>
					<span class="fa-metric__body">
						<span class="fa-metric__value" data-count="<?php echo esc_attr( (string) $page_count ); ?>"><?php echo esc_html( (string) $page_count ); ?></span>
						<span class="fa-metric__label"><?php esc_html_e( 'Pages using Flipset', 'flipset' ); ?></span>
					</span>
				</div>
				<div class="fa-card fa-card--lift fa-metric<?php echo $setup_done ? ' is-complete' : ''; ?>" role="listitem" id="flipset-metric-setup" data-total="<?php echo esc_attr( (string) $total_steps ); ?>">
					<span class="fa-metric__icon is-green"><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span></span>
					<span class="fa-metric__body">
						<span class="fa-metric__value"><?php echo esc_html( $done . '/' . $total_steps ); ?></span>
						<span class="fa-metric__label"><?php esc_html_e( 'Setup', 'flipset' ); ?></span>
					</span>
				</div>
			</div>

			<div class="fa-workspace">
				<section class="fa-card fa-setup<?php echo $setup_done ? ' is-complete' : ''; ?>" id="flipset-setup-panel" aria-labelledby="flipset-setup-heading">
					<div class="fa-card__head">
						<span class="fa-card__icon dashicons dashicons-flag" aria-hidden="true"></span>
						<div class="fa-card__titles">
							<h2 class="fa-card__title" id="flipset-setup-heading"><?php esc_html_e( 'Get started', 'flipset' ); ?></h2>
							<p class="fa-card__sub" id="flipset-setup-status">
								<?php
								if ( $setup_done ) {
									esc_html_e( 'You are set. New documents go in Documents or the Media Library.', 'flipset' );
								} else {
									/* translators: 1: completed steps, 2: total steps. */
									echo esc_html( sprintf( __( '%1$s of %2$s steps complete', 'flipset' ), (string) $done, (string) $total_steps ) );
								}
								?>
							</p>
						</div>
					</div>
					<div class="fa-card__body">
						<div class="fa-progress" role="progressbar" aria-label="<?php esc_attr_e( 'Setup progress', 'flipset' ); ?>" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo esc_attr( (string) $percent ); ?>">
							<span style="width: <?php echo esc_attr( (string) $percent ); ?>%;"></span>
						</div>

						<div class="fa-setup__done">
							<span class="fa-setup__done-text"><span aria-hidden="true">✓</span> <?php esc_html_e( "You're all set", 'flipset' ); ?></span>
							<button type="button" class="fa-btn fa-btn--secondary fa-btn--sm" id="flipset-setup-toggle" aria-expanded="false" aria-controls="flipset-setup-steps" data-show="<?php esc_attr_e( 'Show steps', 'flipset' ); ?>" data-hide="<?php esc_attr_e( 'Hide steps', 'flipset' ); ?>">
								<?php esc_html_e( 'Show steps', 'flipset' ); ?>
							</button>
						</div>

						<ol class="fa-steps" id="flipset-setup-steps">
							<li class="<?php echo $steps['uploaded'] ? 'is-done' : ''; ?>" data-setup-step="uploaded">
								<span class="fa-steps__marker" aria-hidden="true"><span class="fa-steps__num">1</span><span class="fa-steps__check dashicons dashicons-yes"></span></span>
								<div class="fa-steps__copy">
									<span class="fa-steps__title"><?php esc_html_e( 'Upload a PDF', 'flipset' ); ?></span>
									<span class="fa-steps__help"><?php esc_html_e( 'Add a file, or drop one on the stage.', 'flipset' ); ?></span>
								</div>
								<a class="fa-btn fa-btn--secondary fa-btn--sm" href="<?php echo esc_url( admin_url( 'media-new.php' ) ); ?>">
									<span class="dashicons dashicons-upload" aria-hidden="true"></span><?php esc_html_e( 'Upload', 'flipset' ); ?>
								</a>
							</li>
							<li class="<?php echo $steps['copied'] ? 'is-done' : ''; ?>" data-setup-step="copied">
								<span class="fa-steps__marker" aria-hidden="true"><span class="fa-steps__num">2</span><span class="fa-steps__check dashicons dashicons-yes"></span></span>
								<div class="fa-steps__copy">
									<span class="fa-steps__title"><?php esc_html_e( 'Copy a shortcode', 'flipset' ); ?></span>
									<span class="fa-steps__help"><?php esc_html_e( 'Click the code on the stage to copy.', 'flipset' ); ?></span>
								</div>
								<button type="button" class="fa-btn fa-btn--secondary fa-btn--sm" data-flipset-focus="embed">
									<span class="dashicons dashicons-clipboard" aria-hidden="true"></span><?php esc_html_e( 'Show stage', 'flipset' ); ?>
								</button>
							</li>
							<li class="<?php echo $steps['embedded'] ? 'is-done' : ''; ?>" data-setup-step="embedded">
								<span class="fa-steps__marker" aria-hidden="true"><span class="fa-steps__num">3</span><span class="fa-steps__check dashicons dashicons-yes"></span></span>
								<div class="fa-steps__copy">
									<span class="fa-steps__title"><?php esc_html_e( 'Put it on a page', 'flipset' ); ?></span>
									<span class="fa-steps__help"><?php esc_html_e( 'Publish a test page, or use the viewer block.', 'flipset' ); ?></span>
								</div>
								<button type="button" class="fa-btn fa-btn--secondary fa-btn--sm" data-flipset-focus="embed-create">
									<span class="dashicons dashicons-admin-page" aria-hidden="true"></span><?php esc_html_e( 'Create page', 'flipset' ); ?>
								</button>
							</li>
						</ol>
					</div>
				</section>

				<section class="fa-card fa-embed" id="flipset-embed-panel" aria-labelledby="flipset-embed-heading">
					<div class="fa-card__head">
						<span class="fa-card__icon dashicons dashicons-media-document" aria-hidden="true"></span>
						<div class="fa-card__titles">
							<h2 class="fa-card__title" id="flipset-embed-heading"><?php esc_html_e( 'Embed a document', 'flipset' ); ?></h2>
							<p class="fa-card__sub"><?php esc_html_e( 'Drop a PDF, pick a cover, or browse the library.', 'flipset' ); ?></p>
						</div>
						<a class="fa-btn fa-btn--secondary fa-btn--sm fa-card__action" id="fa-open-builder" href="<?php echo esc_url( $open_url ); ?>" data-base="<?php echo esc_url( $builder_url ); ?>">
							<span class="dashicons dashicons-admin-customizer" aria-hidden="true"></span><?php esc_html_e( 'Open in Builder', 'flipset' ); ?>
						</a>
					</div>

					<div class="fa-card__body">
						<div class="fa-stage<?php echo $prefill ? '' : ' is-empty'; ?>" id="flipset-stage">
							<div class="fa-stage__cover" aria-hidden="true">
								<img id="flipset-stage-thumb" alt=""
								<?php
								if ( $prefill && ! empty( $prefill['thumb'] ) ) {
									echo ' src="' . esc_url( $prefill['thumb'] ) . '"';
								} else {
									echo ' hidden';
								}
								?>
								/>
								<span class="fa-stage__fallback dashicons dashicons-media-document" id="flipset-stage-fallback"<?php echo ( $prefill && ! empty( $prefill['thumb'] ) ) ? ' hidden' : ''; ?>></span>
							</div>
							<div class="fa-stage__meta">
								<p class="fa-stage__title" id="flipset-selected-file" title="<?php echo esc_attr( $title_attr ); ?>"><?php echo $prefill ? esc_html( $prefill['title'] ) : esc_html__( 'No document selected', 'flipset' ); ?></p>
								<label class="screen-reader-text" for="flipset-shortcode-output"><?php esc_html_e( 'Shortcode', 'flipset' ); ?></label>
								<div class="fa-code" id="flipset-code" title="<?php esc_attr_e( 'Click to copy', 'flipset' ); ?>">
									<input
										type="text"
										id="flipset-shortcode-output"
										readonly
										value="<?php echo esc_attr( $prefill_code ); ?>"
										placeholder='[flipset file="https://example.com/document.pdf"]'
									/>
									<button type="button" class="fa-btn fa-btn--primary fa-btn--sm" id="flipset-copy-shortcode" <?php disabled( ! $prefill_code ); ?>>
										<?php esc_html_e( 'Copy', 'flipset' ); ?>
									</button>
								</div>
								<div class="fa-stage__actions">
									<button type="button" class="fa-btn fa-btn--ghost-dark fa-btn--sm" id="flipset-create-test-page" <?php disabled( ! $prefill_code ); ?>>
										<?php esc_html_e( 'Create test page', 'flipset' ); ?>
									</button>
									<button type="button" class="fa-link" id="flipset-pick-pdf"><?php esc_html_e( 'Browse Media Library', 'flipset' ); ?></button>
								</div>
							</div>
							<div class="fa-drop" id="flipset-drop" hidden>
								<span class="dashicons dashicons-upload" aria-hidden="true"></span>
								<span><?php esc_html_e( 'Drop a PDF to embed it', 'flipset' ); ?></span>
							</div>
						</div>

						<?php if ( $recent ) : ?>
							<div class="fa-subhead"><h3><?php esc_html_e( 'Recent', 'flipset' ); ?></h3></div>
							<div class="fa-covers" role="list">
								<?php foreach ( $recent as $index => $item ) : ?>
									<button
										type="button"
										class="fa-cover flipset-recent-item<?php echo ( 0 === $index ) ? ' is-selected' : ''; ?>"
										role="listitem"
										data-id="<?php echo esc_attr( (string) $item['id'] ); ?>"
										data-url="<?php echo esc_url( $item['url'] ); ?>"
										data-title="<?php echo esc_attr( $item['title'] ); ?>"
										data-thumb="<?php echo esc_url( $item['thumb'] ); ?>"
										title="<?php echo esc_attr( $item['title'] ); ?>"
										aria-pressed="<?php echo 0 === $index ? 'true' : 'false'; ?>"
									>
										<span class="fa-cover__thumb">
											<?php if ( $item['thumb'] ) : ?>
												<img src="<?php echo esc_url( $item['thumb'] ); ?>" alt="" />
											<?php else : ?>
												<span class="dashicons dashicons-media-document" aria-hidden="true"></span>
											<?php endif; ?>
										</span>
										<span class="fa-cover__name"><?php echo esc_html( $item['title'] ); ?></span>
									</button>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>

						<p class="fa-card__foot">
							<a class="fa-link" href="<?php echo esc_url( $settings_url ); ?>"><?php esc_html_e( 'Viewer defaults', 'flipset' ); ?> &rarr;</a>
						</p>
					</div>
				</section>
			</div>

			<section class="fa-card fa-saved" aria-labelledby="fa-saved-heading">
				<div class="fa-card__head">
					<span class="fa-card__icon dashicons dashicons-portfolio" aria-hidden="true"></span>
					<div class="fa-card__titles">
						<h2 class="fa-card__title" id="fa-saved-heading"><?php esc_html_e( 'Recent saved embeds', 'flipset' ); ?></h2>
					</div>
					<?php if ( $recent_saved ) : ?>
						<a class="fa-btn fa-btn--secondary fa-btn--sm fa-card__action" href="<?php echo esc_url( $embeds_url ); ?>"><?php esc_html_e( 'View all', 'flipset' ); ?></a>
					<?php endif; ?>
				</div>
				<?php if ( $recent_saved ) : ?>
					<ul class="fa-saved__list">
						<?php foreach ( $recent_saved as $embed ) : ?>
							<li class="fa-saved__row">
								<span class="fa-saved__name" title="<?php echo esc_attr( $embed['name'] ); ?>"><?php echo esc_html( '' !== $embed['name'] ? $embed['name'] : __( 'Untitled embed', 'flipset' ) ); ?></span>
								<span class="fa-saved__code">
									<code><?php echo esc_html( $embed['shortcode'] ); ?></code>
									<button type="button" class="fa-icon-btn" data-fa-copy="<?php echo esc_attr( $embed['shortcode'] ); ?>" aria-label="<?php esc_attr_e( 'Copy shortcode', 'flipset' ); ?>" title="<?php esc_attr_e( 'Copy shortcode', 'flipset' ); ?>">
										<span class="dashicons dashicons-admin-page" aria-hidden="true"></span>
									</button>
								</span>
								<span class="fa-saved__date"><?php echo esc_html( $embed['date'] ); ?></span>
								<a class="fa-btn fa-btn--secondary fa-btn--sm" href="<?php echo esc_url( add_query_arg( 'embed_id', (int) $embed['id'], $builder_url ) ); ?>">
									<span class="dashicons dashicons-edit" aria-hidden="true"></span><?php esc_html_e( 'Edit', 'flipset' ); ?>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php else : ?>
					<div class="fa-empty">
						<span class="fa-empty__icon dashicons dashicons-portfolio" aria-hidden="true"></span>
						<p class="fa-empty__title"><?php esc_html_e( 'No saved embeds yet', 'flipset' ); ?></p>
						<a class="fa-btn fa-btn--primary" href="<?php echo esc_url( $builder_url ); ?>"><?php esc_html_e( 'Create your first embed', 'flipset' ); ?></a>
					</div>
				<?php endif; ?>
			</section>

			<div class="flipset-toast" id="flipset-toast" hidden role="status"></div>
		</div>
		<?php
	}

	/**
	 * Default values of every on/off setting. Shared by the sanitize
	 * callback and the Settings screen's "Reset to defaults".
	 *
	 * @return array<string,bool>
	 */
	private function flag_defaults() {
		return array(
			'lazy_loading'           => false,
			'show_branding'          => false,
			'allow_download'         => true,
			'allow_print'            => true,
			'allow_search'           => true,
			'allow_theme'            => true,
			'allow_share'            => true,
			'allow_shortcuts'        => true,
			'allow_fullscreen'       => true,
			'allow_presentation'     => true,
			'index_pdf_text'         => true,
			'social_preview'         => true,
			'check_pdf_a11y'         => true,
			'autoembed_url'          => true,
			'embed_attachment_pages' => true,
			'setup_copied_shortcode' => false,
		);
	}

	/**
	 * Every field the Settings form posts, with its default value.
	 *
	 * @return array<string,mixed>
	 */
	private function settings_defaults() {
		$flags = $this->flag_defaults();
		unset( $flags['setup_copied_shortcode'] );
		return array_merge(
			array(
				'default_width'  => '100%',
				'default_height' => '600px',
				'default_theme'  => 'light',
			),
			$flags
		);
	}

	/**
	 * Presentation data for the Settings screen: control type, label, help
	 * text, per-embed override hint and toolbar icon for each field. Labels
	 * match the add_settings_field() titles.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function settings_schema() {
		return array(
			'default_width'          => array(
				'type'    => 'dimension',
				'label'   => __( 'Default width', 'flipset' ),
				'help'    => __( 'Width of every viewer. Use a number with a unit, for example 100% or 800px.', 'flipset' ),
				'presets' => array( '100%', '800px', '960px' ),
			),
			'default_height'         => array(
				'type'    => 'dimension',
				'label'   => __( 'Default height', 'flipset' ),
				'help'    => __( 'Height of the reading area, for example 600px or 80vh.', 'flipset' ),
				'presets' => array( '420px', '600px', '80vh' ),
			),
			'default_theme'          => array(
				'type'  => 'theme',
				'label' => __( 'Default theme', 'flipset' ),
				'help'  => __( 'Default reader color scheme for visitors.', 'flipset' ),
			),
			'lazy_loading'           => array(
				'type'  => 'switch',
				'label' => __( 'Lazy loading', 'flipset' ),
				'help'  => __( 'Only load PDF documents when they scroll into the visitor viewport (faster initial page load).', 'flipset' ),
			),
			'allow_download'         => array(
				'type'  => 'switch',
				'label' => __( 'Download button', 'flipset' ),
				'help'  => __( 'Show the download button on the viewer toolbar.', 'flipset' ),
				'hint'  => 'download="false"',
			),
			'allow_print'            => array(
				'type'  => 'switch',
				'label' => __( 'Print button', 'flipset' ),
				'help'  => __( 'Show the print button on the viewer toolbar.', 'flipset' ),
				'hint'  => 'print="false"',
			),
			'show_branding'          => array(
				'type'  => 'switch',
				'label' => __( 'Credit link', 'flipset' ),
				'help'  => __( 'Show a small “Powered by Flipset” link under each viewer. Off by default. Turn it on to support the plugin.', 'flipset' ),
			),
			'allow_search'           => array(
				'type'  => 'switch',
				'label' => __( 'Search in document', 'flipset' ),
				'help'  => __( 'Show the search/find input on the toolbar.', 'flipset' ),
				'hint'  => 'hide="search"',
				'icon'  => 'search',
			),
			'allow_theme'            => array(
				'type'  => 'switch',
				'label' => __( 'Theme switcher', 'flipset' ),
				'help'  => __( 'Show the Dark / Light / Sepia theme toggle button.', 'flipset' ),
				'hint'  => 'hide="theme"',
				'icon'  => 'theme',
			),
			'allow_share'            => array(
				'type'  => 'switch',
				'label' => __( 'Share button', 'flipset' ),
				'help'  => __( 'Show the share and copy page link button.', 'flipset' ),
				'hint'  => 'hide="share"',
				'icon'  => 'share',
			),
			'allow_shortcuts'        => array(
				'type'  => 'switch',
				'label' => __( 'Keyboard shortcuts guide', 'flipset' ),
				'help'  => __( 'Show the keyboard shortcuts guide button (?) and enable keyboard navigation.', 'flipset' ),
				'hint'  => 'hide="shortcuts"',
				'icon'  => 'shortcuts',
			),
			'allow_fullscreen'       => array(
				'type'  => 'switch',
				'label' => __( 'Full screen button', 'flipset' ),
				'help'  => __( 'Show the full screen toggle button.', 'flipset' ),
				'hint'  => 'hide="fullscreen"',
				'icon'  => 'fs',
			),
			'allow_presentation'     => array(
				'type'  => 'switch',
				'label' => __( 'Presentation button', 'flipset' ),
				'help'  => __( 'Show the distraction-free presentation mode button.', 'flipset' ),
				'hint'  => 'hide="presentation"',
				'icon'  => 'present',
			),
			'index_pdf_text'         => array(
				'type'  => 'switch',
				'label' => __( 'Site search', 'flipset' ),
				'help'  => __( 'Include text from embedded PDFs in WordPress site search (and in the document library search). Visit Flipset → Documents to extract text.', 'flipset' ),
			),
			'check_pdf_a11y'         => array(
				'type'  => 'switch',
				'label' => __( 'Accessibility tags', 'flipset' ),
				'help'  => __( 'Warn on Flipset → Documents when a PDF is not tagged. Tagged PDFs include structure (headings, reading order) that screen readers can follow.', 'flipset' ),
			),
			'social_preview'         => array(
				'type'  => 'switch',
				'label' => __( 'Social preview', 'flipset' ),
				'help'  => __( 'Use the PDF first-page thumbnail as the Open Graph / Twitter image when a post or page embeds Flipset and no SEO plugin already set an image.', 'flipset' ),
			),
			'autoembed_url'          => array(
				'type'  => 'switch',
				'label' => __( 'Direct PDF auto-embed', 'flipset' ),
				'help'  => __( 'Automatically convert standalone .pdf URLs pasted on their own line into an interactive Flipset viewer.', 'flipset' ),
			),
			'embed_attachment_pages' => array(
				'type'  => 'switch',
				'label' => __( 'Attachment pages', 'flipset' ),
				'help'  => __( 'Automatically display the interactive Flipset PDF viewer on standard WordPress PDF attachment pages.', 'flipset' ),
			),
		);
	}

	/**
	 * Current value of a setting (stored value, or its default).
	 *
	 * @param string $key Setting key.
	 * @return mixed
	 */
	private function setting_value( $key ) {
		$settings = get_option( self::OPTION_NAME, array() );
		$defaults = $this->settings_defaults();
		if ( is_array( $settings ) && array_key_exists( $key, $settings ) ) {
			return $settings[ $key ];
		}
		return $defaults[ $key ] ?? '';
	}

	/**
	 * One on/off switch. The real checkbox stays in the accessibility tree
	 * (role="switch"); only its look is replaced.
	 *
	 * @param string $key         Setting key.
	 * @param string $labelled_by Element id that names the switch.
	 * @param string $described   Element id of the help text.
	 */
	private function render_switch( $key, $labelled_by = '', $described = '' ) {
		printf(
			'<span class="fa-switch"><input type="checkbox" role="switch" id="flipset-%1$s" name="%2$s[%3$s]" value="1"%4$s%5$s%6$s /><span class="fa-switch__track" aria-hidden="true"><span class="fa-switch__knob"></span></span></span>',
			esc_attr( str_replace( '_', '-', $key ) ),
			esc_attr( self::OPTION_NAME ),
			esc_attr( $key ),
			checked( ! empty( $this->setting_value( $key ) ), true, false ),
			'' !== $labelled_by ? ' aria-labelledby="' . esc_attr( $labelled_by ) . '"' : '',
			'' !== $described ? ' aria-describedby="' . esc_attr( $described ) . '"' : ''
		);
	}

	/**
	 * Control markup for one field, shared by the registered field callbacks
	 * and the Settings screen renderer.
	 *
	 * @param string $key       Setting key.
	 * @param string $described Element id of the help text.
	 */
	private function render_control( $key, $described = '' ) {
		$schema = $this->settings_schema();
		if ( ! isset( $schema[ $key ] ) ) {
			return;
		}
		$field = $schema[ $key ];
		$value = $this->setting_value( $key );
		$id    = 'flipset-' . str_replace( '_', '-', $key );

		if ( 'dimension' === $field['type'] ) {
			?>
			<div class="fa-dimension">
				<input type="text" class="fa-input" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( self::OPTION_NAME . '[' . $key . ']' ); ?>" value="<?php echo esc_attr( (string) $value ); ?>" inputmode="decimal" autocomplete="off" spellcheck="false" data-fa-dimension aria-describedby="<?php echo esc_attr( trim( $described . ' ' . $id . '-error' ) ); ?>" />
				<div class="fa-presets" role="group" aria-label="<?php esc_attr_e( 'Quick values', 'flipset' ); ?>">
					<?php foreach ( $field['presets'] as $preset ) : ?>
						<button type="button" class="fa-chip fa-chip--light" data-fa-preset="<?php echo esc_attr( $preset ); ?>" data-target="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $preset ); ?></button>
					<?php endforeach; ?>
				</div>
				<p class="fa-field__error" id="<?php echo esc_attr( $id . '-error' ); ?>" role="alert" hidden><?php esc_html_e( 'Use a number with a unit, e.g. 600px', 'flipset' ); ?></p>
			</div>
			<?php
			return;
		}

		if ( 'theme' === $field['type'] ) {
			$themes = array(
				'light' => array( __( 'Light', 'flipset' ), '☀️' ),
				'dark'  => array( __( 'Dark Mode', 'flipset' ), '🌙' ),
				'sepia' => array( __( 'Sepia (Warm)', 'flipset' ), '☕' ),
			);
			?>
			<div class="flipset-theme-pills fa-theme-pills" role="radiogroup" aria-labelledby="<?php echo esc_attr( $id . '-label' ); ?>"<?php echo '' !== $described ? ' aria-describedby="' . esc_attr( $described ) . '"' : ''; ?>>
				<?php foreach ( $themes as $slug => $theme ) : ?>
					<label class="flipset-theme-pill flipset-theme-pill-<?php echo esc_attr( $slug ); ?>">
						<input type="radio" name="<?php echo esc_attr( self::OPTION_NAME . '[' . $key . ']' ); ?>" value="<?php echo esc_attr( $slug ); ?>" <?php checked( $value, $slug ); ?> />
						<span class="flipset-pill-box">
							<span class="fa-swatch fa-swatch--<?php echo esc_attr( $slug ); ?>" aria-hidden="true"><i></i><i></i><i></i></span>
							<span class="flipset-pill-icon" aria-hidden="true"><?php echo esc_html( $theme[1] ); ?></span>
							<span class="flipset-pill-label"><?php echo esc_html( $theme[0] ); ?></span>
						</span>
					</label>
				<?php endforeach; ?>
			</div>
			<?php
			return;
		}

		$this->render_switch( $key, $id . '-label', $described );
	}

	/**
	 * Inline code chip for an attribute override.
	 *
	 * @param string $code Attribute text such as hide="search".
	 */
	private function render_hint( $code ) {
		printf(
			'<code class="fa-code">%s</code>',
			esc_html( $code )
		);
	}

	/**
	 * A standard label + help + control row.
	 *
	 * @param string $key    Setting key.
	 * @param string $status Optional HTML for a status line (already escaped).
	 */
	private function render_field_row( $key, $status = '' ) {
		$schema = $this->settings_schema();
		if ( ! isset( $schema[ $key ] ) ) {
			return;
		}
		$field   = $schema[ $key ];
		$id      = 'flipset-' . str_replace( '_', '-', $key );
		$is_flag = 'switch' === $field['type'];
		$tag     = $is_flag ? 'label' : 'div';
		?>
		<div class="fa-field fa-field--<?php echo esc_attr( $field['type'] ); ?>">
			<<?php echo esc_html( $tag ); ?> class="fa-field__text"<?php echo $is_flag ? ' for="' . esc_attr( $id ) . '"' : ''; ?>>
				<<?php echo $is_flag ? 'span' : 'label'; ?> class="fa-field__label" id="<?php echo esc_attr( $id . '-label' ); ?>"<?php echo $is_flag ? '' : ' for="' . esc_attr( $id ) . '"'; ?>><?php echo esc_html( $field['label'] ); ?></<?php echo $is_flag ? 'span' : 'label'; ?>>
				<span class="fa-field__help" id="<?php echo esc_attr( $id . '-help' ); ?>">
					<?php echo esc_html( $field['help'] ); ?>
					<?php if ( ! empty( $field['hint'] ) ) : ?>
						<span class="fa-field__hint"><?php esc_html_e( 'Override per embed with', 'flipset' ); ?> <?php $this->render_hint( $field['hint'] ); ?></span>
					<?php endif; ?>
				</span>
				<?php
				if ( '' !== $status ) {
					echo wp_kses(
						$status,
						array(
							'span' => array( 'class' => true ),
							'a'    => array(
								'class' => true,
								'href'  => true,
							),
						)
					);
				}
				?>
			</<?php echo esc_html( $tag ); ?>>
			<div class="fa-field__control">
				<?php $this->render_control( $key, $id . '-help' ); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * A toolbar toggle tile: icon, label, help, override hint and switch.
	 *
	 * @param string $key Setting key.
	 */
	private function render_toolbar_tile( $key ) {
		$schema = $this->settings_schema();
		if ( ! isset( $schema[ $key ] ) ) {
			return;
		}
		$field = $schema[ $key ];
		$id    = 'flipset-' . str_replace( '_', '-', $key );
		$on    = ! empty( $this->setting_value( $key ) );
		?>
		<label class="fa-toggle-tile<?php echo $on ? ' is-on' : ''; ?>" for="<?php echo esc_attr( $id ); ?>">
			<span class="fa-toggle-tile__icon" aria-hidden="true">
				<?php echo wp_kses( Flipset::instance()->viewer->get_toolbar_icon( $field['icon'] ), Flipset_Viewer::svg_allowed_html() ); ?>
			</span>
			<span class="fa-toggle-tile__text">
				<span class="fa-field__label" id="<?php echo esc_attr( $id . '-label' ); ?>"><?php echo esc_html( $field['label'] ); ?></span>
				<span class="fa-field__help" id="<?php echo esc_attr( $id . '-help' ); ?>"><?php echo esc_html( $field['help'] ); ?></span>
				<span class="fa-field__hint"><?php $this->render_hint( $field['hint'] ); ?></span>
			</span>
			<?php $this->render_switch( $key, $id . '-label', $id . '-help' ); ?>
		</label>
		<?php
	}

	/**
	 * Cheap PDF counts for the status lines under the search settings.
	 *
	 * @return array{total:int,indexed:int,checked:int,tagged:int}
	 */
	private function settings_pdf_stats() {
		$count = static function ( $meta_query = array() ) {
			$args = array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'post_mime_type' => 'application/pdf',
				'posts_per_page' => 1,
				'fields'         => 'ids',
			);
			if ( $meta_query ) {
				$args['meta_query'] = $meta_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- one bounded count on an admin screen.
			}
			$query = new WP_Query( $args );
			return (int) $query->found_posts;
		};

		return array(
			'total'   => $count(),
			'indexed' => $count(
				array(
					array(
						'key'   => Flipset_SEO::META_INDEXED,
						'value' => '1',
					),
				)
			),
			'checked' => $count(
				array(
					array(
						'key'     => Flipset_A11y::META_CHECKED,
						'compare' => 'EXISTS',
					),
				)
			),
			'tagged'  => $count(
				array(
					array(
						'key'   => Flipset_A11y::META_TAGGED,
						'value' => '1',
					),
				)
			),
		);
	}

	/**
	 * Preview viewer markup. Every toolbar button is rendered so the screen
	 * can show or hide it live; the saved settings decide the initial state.
	 *
	 * @param string $file   PDF URL.
	 * @param string $width  CSS width.
	 * @param string $height CSS height.
	 * @param string $theme  light|dark|sepia.
	 * @return string
	 */
	private function render_settings_preview_viewer( $file, $width, $height, $theme ) {
		return Flipset::instance()->viewer->render_shortcode(
			array(
				'file'         => $file,
				'width'        => $width,
				'height'       => $height,
				'theme'        => $theme,
				'loading'      => 'eager',
				'download'     => 'true',
				'print'        => 'true',
				'search'       => 'true',
				'theme_btn'    => 'true',
				'share'        => 'true',
				'shortcuts'    => 'true',
				'presentation' => 'true',
				'fullscreen'   => 'true',
			)
		);
	}

	/**
	 * AJAX: viewer markup for another preview PDF (Settings live preview).
	 */
	public function ajax_settings_preview() {
		check_ajax_referer( 'flipset_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'flipset' ) ), 403 );
		}

		$pdf_id = isset( $_POST['pdf_id'] ) ? absint( wp_unslash( $_POST['pdf_id'] ) ) : 0;
		if ( ! $pdf_id || 'application/pdf' !== get_post_mime_type( $pdf_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Please select a PDF file.', 'flipset' ) ), 400 );
		}

		$url = wp_get_attachment_url( $pdf_id );
		if ( ! $url ) {
			wp_send_json_error( array( 'message' => __( 'Please select a PDF file.', 'flipset' ) ), 400 );
		}

		$raw_width  = isset( $_POST['width'] ) ? sanitize_text_field( wp_unslash( $_POST['width'] ) ) : '';
		$raw_height = isset( $_POST['height'] ) ? sanitize_text_field( wp_unslash( $_POST['height'] ) ) : '';
		$raw_theme  = isset( $_POST['theme'] ) ? sanitize_key( wp_unslash( $_POST['theme'] ) ) : 'light';
		$theme      = in_array( $raw_theme, array( 'light', 'dark', 'sepia' ), true ) ? $raw_theme : 'light';

		wp_send_json_success(
			array(
				'html' => $this->render_settings_preview_viewer(
					$url,
					$this->sanitize_css_dimension( $raw_width, '100%' ),
					$this->sanitize_css_dimension( $raw_height, '600px' ),
					$theme
				),
			)
		);
	}

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$width       = (string) $this->setting_value( 'default_width' );
		$height      = (string) $this->setting_value( 'default_height' );
		$theme       = (string) $this->setting_value( 'default_theme' );
		$recent_pdfs = $this->get_recent_pdfs( 12 );
		$preview     = '';
		$dash_url    = admin_url( 'admin.php?page=' . self::PAGE_SLUG );
		$docs_url    = admin_url( 'admin.php?page=' . self::DOCS_SLUG );
		$stats       = $this->settings_pdf_stats();

		if ( $recent_pdfs ) {
			$preview = $this->render_settings_preview_viewer( $recent_pdfs[0]['url'], $width, $height, $theme );
		}

		$toolbar_keys = array( 'allow_search', 'allow_theme', 'allow_share', 'allow_shortcuts', 'allow_fullscreen', 'allow_presentation' );
		$tabs         = array(
			'viewer'  => array(
				'label' => __( 'Viewer defaults', 'flipset' ),
				'emoji' => '🖥',
				'keys'  => array( 'allow_download', 'allow_print', 'lazy_loading', 'show_branding' ),
			),
			'toolbar' => array(
				'label' => __( 'Toolbar', 'flipset' ),
				'emoji' => '🧰',
				'keys'  => $toolbar_keys,
			),
			'sharing' => array(
				'label' => __( 'Search & sharing', 'flipset' ),
				'emoji' => '🔎',
				'keys'  => array( 'index_pdf_text', 'check_pdf_a11y', 'social_preview', 'autoembed_url', 'embed_attachment_pages' ),
			),
		);

		// Status lines under the search settings, built from escaped parts.
		$index_status = sprintf(
			'<span class="fa-field__status">%1$s <a class="fa-link" href="%2$s">%3$s</a></span>',
			esc_html(
				sprintf(
					/* translators: 1: number of indexed PDFs, 2: total PDFs. */
					__( '%1$s of %2$s PDFs indexed', 'flipset' ),
					number_format_i18n( $stats['indexed'] ),
					number_format_i18n( $stats['total'] )
				)
			),
			esc_url( $docs_url ),
			esc_html__( 'Manage in Documents →', 'flipset' )
		);
		$a11y_status = sprintf(
			'<span class="fa-field__status">%1$s <a class="fa-link" href="%2$s">%3$s</a></span>',
			esc_html(
				sprintf(
					/* translators: 1: number of tagged PDFs, 2: number of checked PDFs. */
					__( '%1$s of %2$s checked PDFs are tagged', 'flipset' ),
					number_format_i18n( $stats['tagged'] ),
					number_format_i18n( $stats['checked'] )
				)
			),
			esc_url( add_query_arg( 'status', 'attention', $docs_url ) ),
			esc_html__( 'Manage in Documents →', 'flipset' )
		);

		$form_id  = 'flipset-settings-form';
		$defaults = $this->settings_defaults();
		?>
		<div class="wrap flipset-admin flipset-settings-wrap">
			<hr class="wp-header-end" />
			<?php settings_errors(); ?>

			<div class="fa-hero">
				<div class="fa-hero__top">
					<div class="fa-hero__copy">
						<div class="fa-hero__kicker">
							<span class="fa-mark dashicons dashicons-admin-generic" aria-hidden="true"></span>
							<nav class="fa-chip fa-crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'flipset' ); ?>">
								<a href="<?php echo esc_url( $dash_url ); ?>"><?php esc_html_e( 'Flipset', 'flipset' ); ?></a>
								<span aria-hidden="true">/</span>
								<span aria-current="page"><?php esc_html_e( 'Settings', 'flipset' ); ?></span>
							</nav>
						</div>
						<h1 class="fa-hero__title"><?php esc_html_e( 'Settings', 'flipset' ); ?></h1>
						<p class="fa-hero__lede"><?php esc_html_e( 'Site-wide defaults for every Flipset viewer. Individual embeds and saved shortcodes can still override them.', 'flipset' ); ?></p>
					</div>
					<div class="fa-hero__side">
						<span class="fa-chip fa-chip--warn" id="fa-dirty-pill" hidden><?php esc_html_e( 'Unsaved changes', 'flipset' ); ?></span>
						<button type="submit" form="<?php echo esc_attr( $form_id ); ?>" class="fa-btn fa-btn--primary" data-fa-save>
							<span class="dashicons dashicons-saved" aria-hidden="true"></span>
							<span><?php esc_html_e( 'Save changes', 'flipset' ); ?></span>
						</button>
					</div>
				</div>
			</div>

			<div class="fa-segmented fa-settings-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Settings sections', 'flipset' ); ?>" hidden>
				<?php foreach ( $tabs as $slug => $tab ) : ?>
					<button type="button" class="fa-segmented__btn<?php echo 'viewer' === $slug ? ' is-active' : ''; ?>" role="tab" id="fa-tab-<?php echo esc_attr( $slug ); ?>" aria-controls="fa-panel-<?php echo esc_attr( $slug ); ?>" aria-selected="<?php echo 'viewer' === $slug ? 'true' : 'false'; ?>" tabindex="<?php echo 'viewer' === $slug ? '0' : '-1'; ?>" data-fa-tab="<?php echo esc_attr( $slug ); ?>">
						<span aria-hidden="true"><?php echo esc_html( $tab['emoji'] ); ?></span>
						<span><?php echo esc_html( $tab['label'] ); ?></span>
						<span class="fa-pill" data-fa-count="<?php echo esc_attr( $slug ); ?>"></span>
					</button>
				<?php endforeach; ?>
			</div>

			<div class="fa-settings">
				<form method="post" action="options.php" id="<?php echo esc_attr( $form_id ); ?>" class="fa-settings__form" data-defaults="<?php echo esc_attr( wp_json_encode( $defaults ) ); ?>" autocomplete="off" novalidate>
					<?php
					settings_fields( self::OPTION_GROUP );
					printf(
						'<input type="hidden" name="%1$s[%2$s]" value="1" />',
						esc_attr( self::OPTION_NAME ),
						esc_attr( self::FORM_MARKER )
					);
					?>
					<div class="fa-card fa-settings__card">

						<section class="fa-panel" id="fa-panel-viewer" role="tabpanel" aria-labelledby="fa-tab-viewer" data-fa-panel="viewer">
							<div class="fa-panel__body">
								<div class="fa-subhead"><h3><?php esc_html_e( 'Size', 'flipset' ); ?></h3></div>
								<?php $this->render_field_row( 'default_width' ); ?>
								<?php $this->render_field_row( 'default_height' ); ?>

								<div class="fa-subhead"><h3><?php esc_html_e( 'Reader', 'flipset' ); ?></h3></div>
								<?php $this->render_field_row( 'default_theme' ); ?>
								<?php $this->render_field_row( 'lazy_loading' ); ?>

								<div class="fa-subhead"><h3><?php esc_html_e( 'Toolbar actions', 'flipset' ); ?></h3></div>
								<?php $this->render_field_row( 'allow_download' ); ?>
								<?php $this->render_field_row( 'allow_print' ); ?>

								<div class="fa-credit">
									<span class="fa-credit__icon dashicons dashicons-heart" aria-hidden="true"></span>
									<div class="fa-credit__text">
										<label class="fa-field__label" for="flipset-show-branding" id="flipset-show-branding-label"><?php esc_html_e( 'Credit link', 'flipset' ); ?></label>
										<span class="fa-field__help" id="flipset-show-branding-help"><?php echo esc_html( $this->settings_schema()['show_branding']['help'] ); ?></span>
									</div>
									<?php $this->render_switch( 'show_branding', 'flipset-show-branding-label', 'flipset-show-branding-help' ); ?>
								</div>
							</div>
							<div class="fa-panel__foot">
								<button type="button" class="fa-link" data-fa-reset="viewer"><?php esc_html_e( 'Reset to defaults', 'flipset' ); ?></button>
							</div>
						</section>

						<section class="fa-panel" id="fa-panel-toolbar" role="tabpanel" aria-labelledby="fa-tab-toolbar" data-fa-panel="toolbar">
							<div class="fa-panel__body">
								<div class="fa-panel__intro">
									<p><?php esc_html_e( 'Enable or disable specific buttons across all embedded PDF viewers. Individual embeds can also customize controls via the "hide" attribute.', 'flipset' ); ?></p>
									<div class="fa-panel__bulk">
										<button type="button" class="fa-link" data-fa-bulk="on"><?php esc_html_e( 'Enable all', 'flipset' ); ?></button>
										<button type="button" class="fa-link" data-fa-bulk="off"><?php esc_html_e( 'Disable all', 'flipset' ); ?></button>
									</div>
								</div>
								<div class="fa-toggle-grid">
									<?php
									foreach ( $toolbar_keys as $key ) {
										$this->render_toolbar_tile( $key );
									}
									?>
								</div>
							</div>
							<div class="fa-panel__foot">
								<button type="button" class="fa-link" data-fa-reset="toolbar"><?php esc_html_e( 'Reset to defaults', 'flipset' ); ?></button>
							</div>
						</section>

						<section class="fa-panel" id="fa-panel-sharing" role="tabpanel" aria-labelledby="fa-tab-sharing" data-fa-panel="sharing">
							<div class="fa-panel__body">
								<div class="fa-panel__intro">
									<p><?php esc_html_e( 'Make embedded PDFs findable in WordPress search and nicer when a page is shared.', 'flipset' ); ?></p>
								</div>
								<div class="fa-subhead"><h3><?php esc_html_e( 'Search', 'flipset' ); ?></h3></div>
								<?php $this->render_field_row( 'index_pdf_text', $index_status ); ?>
								<?php $this->render_field_row( 'check_pdf_a11y', $a11y_status ); ?>

								<div class="fa-subhead"><h3><?php esc_html_e( 'Sharing', 'flipset' ); ?></h3></div>
								<?php $this->render_field_row( 'social_preview' ); ?>

								<div class="fa-subhead"><h3><?php esc_html_e( 'Auto-embed', 'flipset' ); ?></h3></div>
								<?php $this->render_field_row( 'autoembed_url' ); ?>
								<?php $this->render_field_row( 'embed_attachment_pages' ); ?>
							</div>
							<div class="fa-panel__foot">
								<button type="button" class="fa-link" data-fa-reset="sharing"><?php esc_html_e( 'Reset to defaults', 'flipset' ); ?></button>
							</div>
						</section>

					</div>
				</form>

				<aside class="fa-card fa-preview" id="fa-preview" aria-labelledby="fa-preview-title">
					<div class="fa-card__head">
						<span class="fa-card__icon dashicons dashicons-visibility" aria-hidden="true"></span>
						<div class="fa-card__titles">
							<h2 class="fa-card__title" id="fa-preview-title"><?php esc_html_e( 'Live preview', 'flipset' ); ?></h2>
						</div>
						<?php if ( $preview ) : ?>
							<span class="fa-chip fa-chip--light fa-preview__size" id="fa-preview-size">
								<?php
								echo esc_html(
									sprintf(
										/* translators: %s: configured viewer height, for example 600px. */
										__( 'Height %s · scaled to fit', 'flipset' ),
										$height
									)
								);
								?>
							</span>
							<div class="flipset-device-switcher" id="fa-device-switcher" role="group" aria-label="<?php esc_attr_e( 'Preview size', 'flipset' ); ?>">
								<button type="button" class="flipset-device-btn is-active" data-device="desktop" aria-pressed="true" title="<?php esc_attr_e( 'Desktop View (100%)', 'flipset' ); ?>">
									<span class="dashicons dashicons-desktop" aria-hidden="true"></span><span class="screen-reader-text"><?php esc_html_e( 'Desktop View (100%)', 'flipset' ); ?></span>
								</button>
								<button type="button" class="flipset-device-btn" data-device="tablet" aria-pressed="false" title="<?php esc_attr_e( 'Tablet View (768px)', 'flipset' ); ?>">
									<span class="dashicons dashicons-tablet" aria-hidden="true"></span><span class="screen-reader-text"><?php esc_html_e( 'Tablet View (768px)', 'flipset' ); ?></span>
								</button>
								<button type="button" class="flipset-device-btn" data-device="mobile" aria-pressed="false" title="<?php esc_attr_e( 'Mobile View (375px)', 'flipset' ); ?>">
									<span class="dashicons dashicons-smartphone" aria-hidden="true"></span><span class="screen-reader-text"><?php esc_html_e( 'Mobile View (375px)', 'flipset' ); ?></span>
								</button>
							</div>
						<?php endif; ?>
						<button type="button" class="fa-btn fa-btn--secondary fa-btn--sm fa-preview__toggle" id="fa-preview-toggle" aria-expanded="true" aria-controls="fa-preview-body" data-show="<?php esc_attr_e( 'Show preview', 'flipset' ); ?>" data-hide="<?php esc_attr_e( 'Hide preview', 'flipset' ); ?>" hidden><?php esc_html_e( 'Hide preview', 'flipset' ); ?></button>
					</div>
					<div class="fa-preview__body" id="fa-preview-body">
						<?php if ( $preview ) : ?>
							<div class="fa-preview__bar">
								<label for="fa-preview-pdf"><?php esc_html_e( 'Preview PDF', 'flipset' ); ?></label>
								<select id="fa-preview-pdf" class="fa-select">
									<?php foreach ( $recent_pdfs as $pdf ) : ?>
										<option value="<?php echo esc_attr( (string) $pdf['id'] ); ?>"><?php echo esc_html( $pdf['title'] ); ?></option>
									<?php endforeach; ?>
								</select>
							</div>
							<div class="fa-preview__stage" id="fa-preview-stage" aria-live="polite">
								<div class="fa-preview__device" id="fa-preview-device">
									<?php echo wp_kses( $preview, Flipset_Viewer::allowed_html() ); ?>
								</div>
							</div>
							<p class="fa-preview__foot"><?php esc_html_e( 'Unsaved changes are previewed here only.', 'flipset' ); ?></p>
						<?php else : ?>
							<div class="fa-empty">
								<span class="fa-empty__tile dashicons dashicons-media-document" aria-hidden="true"></span>
								<p class="fa-empty__title"><?php esc_html_e( 'Upload a PDF to preview', 'flipset' ); ?></p>
								<a class="fa-btn fa-btn--primary" href="<?php echo esc_url( admin_url( 'media-new.php' ) ); ?>"><?php esc_html_e( 'Upload PDF', 'flipset' ); ?></a>
							</div>
						<?php endif; ?>
					</div>
				</aside>
			</div>

			<div class="fa-savebar" id="fa-savebar" hidden>
				<span class="fa-savebar__text"><span class="dashicons dashicons-warning" aria-hidden="true"></span><?php esc_html_e( 'You have unsaved changes', 'flipset' ); ?></span>
				<span class="fa-savebar__actions">
					<button type="button" class="fa-link" id="fa-discard"><?php esc_html_e( 'Discard', 'flipset' ); ?></button>
					<button type="submit" form="<?php echo esc_attr( $form_id ); ?>" class="fa-btn fa-btn--primary fa-btn--sm" data-fa-save>
						<span><?php esc_html_e( 'Save changes', 'flipset' ); ?></span>
					</button>
				</span>
			</div>

			<div class="flipset-toast" id="flipset-toast" hidden role="status"></div>
		</div>
		<?php
	}

	public function render_builder_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$editing_id    = isset( $_GET['embed_id'] ) ? absint( wp_unslash( $_GET['embed_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$editing_embed = $editing_id ? Flipset_Embeds::get( $editing_id ) : null;
		$saved_embeds  = Flipset_Embeds::get_all();

		$settings    = get_option( self::OPTION_NAME, array() );
		$recent_pdfs = $this->get_recent_pdfs( 8 );

		if ( $editing_embed ) {
			$default_pdf    = $editing_embed['file'] ?? '';
			$default_title  = $editing_embed['title'] ?? '';
			$default_name   = $editing_embed['name'] ?? '';
			$default_width  = $editing_embed['width'] ?? '100%';
			$default_height = $editing_embed['height'] ?? '520px';
			$default_theme  = $editing_embed['theme'] ?? 'light';
			$default_page   = $editing_embed['page'] ?? 1;
			$default_view   = $editing_embed['view'] ?? 'page';
			$default_hide   = array_filter( array_map( 'trim', explode( ',', strtolower( $editing_embed['hide'] ?? '' ) ) ) );
			$default_lazy   = 'lazy' === ( $editing_embed['loading'] ?? '' );
			$default_hash   = 'false' !== ( $editing_embed['hash'] ?? 'true' );
			$default_resume = 'false' !== ( $editing_embed['resume'] ?? 'true' );
		} else {
			$preview_url   = $this->get_latest_pdf_url();
			$default_pdf   = $preview_url ? $preview_url : '';
			$default_title = ! empty( $recent_pdfs[0]['title'] ) ? $recent_pdfs[0]['title'] : '';

			// Read-only prefill from the dashboard's "Open in Builder" link.
			$pdf_param = isset( $_GET['pdf_id'] ) ? absint( wp_unslash( $_GET['pdf_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only prefill, validated below.
			if ( $pdf_param && 'application/pdf' === get_post_mime_type( $pdf_param ) ) {
				$pdf_param_url = wp_get_attachment_url( $pdf_param );
				if ( $pdf_param_url ) {
					$default_pdf   = $pdf_param_url;
					$default_title = get_the_title( $pdf_param );
				}
			}
			$default_name   = '';
			$default_width  = '100%';
			$default_height = '520px';
			$default_theme  = $settings['default_theme'] ?? 'light';
			$default_page   = 1;
			$default_view   = 'page';
			$default_hide   = array();
			$default_lazy   = ! empty( $settings['lazy_loading'] );
			$default_hash   = true;
			$default_resume = true;
		}

		$initial_preview = '';
		if ( $default_pdf ) {
			$initial_preview = Flipset::instance()->viewer->render_shortcode(
				array(
					'file'         => $default_pdf,
					'width'        => $default_width,
					'height'       => $default_height,
					'theme'        => $default_theme,
					'view'         => $default_view,
					'page'         => $default_page,
					'download'     => 'true',
					'print'        => 'true',
					'search'       => 'true',
					'theme_btn'    => 'true',
					'share'        => 'true',
					'shortcuts'    => 'true',
					'presentation' => 'true',
					'fullscreen'   => 'true',
				)
			);
		}
		?>
		<div class="wrap flipset-admin flipset-builder-wrap">
			<div class="flipset-builder-header">
				<div class="flipset-builder-header-copy">
					<div class="flipset-builder-kicker">
						<span class="flipset-mark dashicons dashicons-admin-customizer" aria-hidden="true"></span>
						<h1><?php esc_html_e( 'Shortcode Builder & Live Preview', 'flipset' ); ?></h1>
					</div>
					<p class="flipset-builder-lede">
						<?php esc_html_e( 'Visually configure your PDF embed with real-time preview, then save to generate a clean shortcode like [flipset id="1"].', 'flipset' ); ?>
					</p>
				</div>
				<div class="flipset-builder-presets">
					<span class="flipset-presets-label"><?php esc_html_e( '1-Click Presets:', 'flipset' ); ?></span>
					<div class="flipset-presets-chips">
						<button type="button" class="flipset-preset-chip" data-preset="flipbook" title="<?php esc_attr_e( '3D realistic FlipBook with Web Audio page turn sound', 'flipset' ); ?>">
							<span class="flipset-preset-icon">✨</span> <?php esc_html_e( '3D FlipBook', 'flipset' ); ?>
						</button>
						<button type="button" class="flipset-preset-chip" data-preset="ebook" title="<?php esc_attr_e( 'Sepia theme, continuous scroll, search enabled', 'flipset' ); ?>">
							<span class="flipset-preset-icon">📖</span> <?php esc_html_e( 'E-Book Reader', 'flipset' ); ?>
						</button>
						<button type="button" class="flipset-preset-chip" data-preset="corporate" title="<?php esc_attr_e( 'Clean white theme, presentation and download enabled', 'flipset' ); ?>">
							<span class="flipset-preset-icon">💼</span> <?php esc_html_e( 'Corporate Report', 'flipset' ); ?>
						</button>
						<button type="button" class="flipset-preset-chip" data-preset="night" title="<?php esc_attr_e( 'Dark theme, fullscreen and zoom enabled', 'flipset' ); ?>">
							<span class="flipset-preset-icon">🌙</span> <?php esc_html_e( 'Night Reader', 'flipset' ); ?>
						</button>
						<button type="button" class="flipset-preset-chip" data-preset="viewonly" title="<?php esc_attr_e( 'Download and print hidden, pan and zoom allowed', 'flipset' ); ?>">
							<span class="flipset-preset-icon">🔒</span> <?php esc_html_e( 'View Only (Protected)', 'flipset' ); ?>
						</button>
						<button type="button" class="flipset-preset-chip" data-preset="minimal" title="<?php esc_attr_e( 'Lazy loaded, compact toolbar, faster initial load', 'flipset' ); ?>">
							<span class="flipset-preset-icon">⚡</span> <?php esc_html_e( 'Speed Optimized', 'flipset' ); ?>
						</button>
					</div>
				</div>
			</div>

			<!-- Builder / Saved Embeds View Switcher Tabs -->
			<div class="flipset-builder-view-nav">
				<button type="button" class="flipset-view-tab-btn is-active" data-view="builder">
					<span class="dashicons dashicons-admin-customizer" aria-hidden="true"></span>
					<span><?php esc_html_e( 'Shortcode Builder', 'flipset' ); ?></span>
				</button>
				<button type="button" class="flipset-view-tab-btn" data-view="embeds">
					<span class="dashicons dashicons-portfolio" aria-hidden="true"></span>
					<span><?php esc_html_e( 'My Saved Embeds', 'flipset' ); ?></span>
					<span class="flipset-count-pill" id="flipset-embeds-count-badge"><?php echo esc_html( (string) count( $saved_embeds ) ); ?></span>
				</button>
			</div>

			<?php if ( $editing_embed ) : ?>
			<div class="flipset-builder-edit-banner">
				<div class="flipset-edit-banner-left">
					<span class="dashicons dashicons-edit" aria-hidden="true"></span>
					<span>
						<?php
						printf(
							/* translators: 1: Embed name, 2: Embed ID */
							esc_html__( 'Editing Saved Embed: %1$s (ID: #%2$d)', 'flipset' ),
							'<strong>' . esc_html( $editing_embed['name'] ) . '</strong>',
							absint( $editing_id )
						);
						?>
					</span>
				</div>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::BUILDER_SLUG ) ); ?>" class="button button-small flipset-btn-new-embed">
					<?php esc_html_e( '+ Create New Embed', 'flipset' ); ?>
				</a>
			</div>
			<?php endif; ?>

			<!-- VIEW 1: Builder Layout -->
			<div class="flipset-main-view-pane is-active" id="flipset-view-builder">
				<div class="flipset-builder-layout">
					<!-- Left Column: 3 Streamlined Tabs -->
					<div class="flipset-builder-controls">
						<div class="flipset-card flipset-builder-tabs-card">
							<!-- Left Tabs Navigation Bar (3 Compact Tabs) -->
							<div class="flipset-builder-tabs-header">
								<nav class="flipset-ctrl-tabs-nav" role="tablist">
									<button type="button" class="flipset-ctrl-tab is-active" data-tab="doc" role="tab" aria-selected="true">
										<span class="dashicons dashicons-media-document" aria-hidden="true"></span>
										<span><?php esc_html_e( '1. Document & Style', 'flipset' ); ?></span>
									</button>
									<button type="button" class="flipset-ctrl-tab" data-tab="layout" role="tab" aria-selected="false">
										<span class="dashicons dashicons-layout" aria-hidden="true"></span>
										<span><?php esc_html_e( '2. Size & Layout', 'flipset' ); ?></span>
									</button>
									<button type="button" class="flipset-ctrl-tab" data-tab="controls" role="tab" aria-selected="false">
										<span class="dashicons dashicons-admin-settings" aria-hidden="true"></span>
										<span><?php esc_html_e( '3. Toolbar & Advanced', 'flipset' ); ?></span>
									</button>
								</nav>
							</div>

							<!-- Tab 1: Document & Style Pane -->
							<div class="flipset-ctrl-pane is-active" id="flipset-pane-doc" role="tabpanel">
								<div class="flipset-pane-body">
									<div class="flipset-form-row">
										<label for="flipset-builder-name" class="flipset-label"><?php esc_html_e( 'Embed Name / Title:', 'flipset' ); ?></label>
										<input type="text" id="flipset-builder-name" class="regular-text" value="<?php echo esc_attr( $default_name ); ?>" placeholder="<?php esc_attr_e( 'e.g. Annual Company Report 2026', 'flipset' ); ?>" />
										<input type="hidden" id="flipset-builder-id" value="<?php echo esc_attr( (string) $editing_id ); ?>" />
										<p class="description"><?php esc_html_e( 'Name for your internal reference in the Saved Embeds manager.', 'flipset' ); ?></p>
									</div>

									<hr class="flipset-pane-divider" />

									<div class="flipset-section-subhead">
										<h3><?php esc_html_e( 'PDF Document Source', 'flipset' ); ?></h3>
									</div>
									<div class="flipset-file-actions">
										<button type="button" class="button button-primary" id="flipset-builder-pick-pdf">
											<span class="dashicons dashicons-admin-media" aria-hidden="true"></span> <?php esc_html_e( 'Choose from Media Library', 'flipset' ); ?>
										</button>
										<button type="button" class="button" id="flipset-builder-upload-pdf">
											<span class="dashicons dashicons-upload" aria-hidden="true"></span> <?php esc_html_e( 'Upload New PDF', 'flipset' ); ?>
										</button>
									</div>

									<div class="flipset-form-row">
										<label for="flipset-builder-file" class="flipset-label"><?php esc_html_e( 'PDF File URL:', 'flipset' ); ?></label>
										<input type="url" id="flipset-builder-file" class="regular-text code" value="<?php echo esc_url( $default_pdf ); ?>" placeholder="https://example.com/document.pdf" />
									</div>

									<?php if ( ! empty( $recent_pdfs ) ) : ?>
									<div class="flipset-recent-picker">
										<span class="flipset-sublabel"><?php esc_html_e( 'Or select a recent PDF:', 'flipset' ); ?></span>
										<div class="flipset-recent-list">
											<?php foreach ( $recent_pdfs as $pdf_item ) : ?>
											<button type="button" class="flipset-recent-btn<?php echo ( $default_pdf === $pdf_item['url'] ) ? ' is-active' : ''; ?>" data-url="<?php echo esc_url( $pdf_item['url'] ); ?>" data-title="<?php echo esc_attr( $pdf_item['title'] ); ?>">
												<span class="dashicons dashicons-pdf" aria-hidden="true"></span>
												<span class="flipset-recent-title"><?php echo esc_html( $pdf_item['title'] ); ?></span>
											</button>
											<?php endforeach; ?>
										</div>
									</div>
									<?php endif; ?>

									<hr class="flipset-pane-divider" />

									<div class="flipset-section-subhead">
										<h3><?php esc_html_e( 'Appearance & Theme', 'flipset' ); ?></h3>
									</div>

									<div class="flipset-form-row">
										<label class="flipset-label"><?php esc_html_e( 'Reading Theme:', 'flipset' ); ?></label>
										<div class="flipset-theme-pills">
											<label class="flipset-theme-pill flipset-theme-pill-light">
												<input type="radio" name="flipset_b_theme" value="light" <?php checked( $default_theme, 'light' ); ?> />
												<span class="flipset-pill-box">
													<span class="flipset-pill-icon">☀️</span>
													<span class="flipset-pill-label"><?php esc_html_e( 'Light', 'flipset' ); ?></span>
												</span>
											</label>
											<label class="flipset-theme-pill flipset-theme-pill-dark">
												<input type="radio" name="flipset_b_theme" value="dark" <?php checked( $default_theme, 'dark' ); ?> />
												<span class="flipset-pill-box">
													<span class="flipset-pill-icon">🌙</span>
													<span class="flipset-pill-label"><?php esc_html_e( 'Dark Mode', 'flipset' ); ?></span>
												</span>
											</label>
											<label class="flipset-theme-pill flipset-theme-pill-sepia">
												<input type="radio" name="flipset_b_theme" value="sepia" <?php checked( $default_theme, 'sepia' ); ?> />
												<span class="flipset-pill-box">
													<span class="flipset-pill-icon">☕</span>
													<span class="flipset-pill-label"><?php esc_html_e( 'Sepia (Warm)', 'flipset' ); ?></span>
												</span>
											</label>
										</div>
									</div>

									<div class="flipset-form-row">
										<label for="flipset-builder-title" class="flipset-label"><?php esc_html_e( 'Viewer Caption / Subtitle (Optional):', 'flipset' ); ?></label>
										<input type="text" id="flipset-builder-title" class="regular-text" value="<?php echo esc_attr( $default_title ); ?>" placeholder="<?php esc_attr_e( 'e.g. Official Edition', 'flipset' ); ?>" />
									</div>
								</div>
								<div class="flipset-pane-footer">
									<span></span>
									<button type="button" class="button flipset-tab-step" data-step-to="layout">
										<?php esc_html_e( 'Next: Size & Layout →', 'flipset' ); ?>
									</button>
								</div>
							</div>

							<!-- Tab 2: Size & Layout Pane -->
							<div class="flipset-ctrl-pane" id="flipset-pane-layout" role="tabpanel" style="display: none;">
								<div class="flipset-pane-body">
									<div class="flipset-section-subhead">
										<h3><?php esc_html_e( 'Viewer Dimensions', 'flipset' ); ?></h3>
									</div>

									<div class="flipset-grid-2">
										<div class="flipset-form-row">
											<label for="flipset-builder-width" class="flipset-label"><?php esc_html_e( 'Width:', 'flipset' ); ?></label>
											<input type="text" id="flipset-builder-width" class="regular-text" value="<?php echo esc_attr( $default_width ); ?>" placeholder="100%" />
											<div class="flipset-quick-chips">
												<button type="button" class="flipset-quick-chip" data-target="#flipset-builder-width" data-val="100%">100%</button>
												<button type="button" class="flipset-quick-chip" data-target="#flipset-builder-width" data-val="800px">800px</button>
												<button type="button" class="flipset-quick-chip" data-target="#flipset-builder-width" data-val="640px">640px</button>
											</div>
										</div>
										<div class="flipset-form-row">
											<label for="flipset-builder-height" class="flipset-label"><?php esc_html_e( 'Height:', 'flipset' ); ?></label>
											<input type="text" id="flipset-builder-height" class="regular-text" value="<?php echo esc_attr( $default_height ); ?>" placeholder="520px" />
											<div class="flipset-quick-chips">
												<button type="button" class="flipset-quick-chip" data-target="#flipset-builder-height" data-val="520px">520px</button>
												<button type="button" class="flipset-quick-chip" data-target="#flipset-builder-height" data-val="650px">650px</button>
												<button type="button" class="flipset-quick-chip" data-target="#flipset-builder-height" data-val="80vh">80vh</button>
											</div>
										</div>
									</div>

									<hr class="flipset-pane-divider" />

									<div class="flipset-section-subhead">
										<h3><?php esc_html_e( 'Initial Page & Display Mode', 'flipset' ); ?></h3>
									</div>

									<div class="flipset-grid-2">
										<div class="flipset-form-row">
											<label for="flipset-builder-page" class="flipset-label"><?php esc_html_e( 'Start Page:', 'flipset' ); ?></label>
											<input type="number" id="flipset-builder-page" class="small-text" min="1" value="<?php echo esc_attr( (string) $default_page ); ?>" />
										</div>
										<div class="flipset-form-row">
											<label class="flipset-label"><?php esc_html_e( 'Initial View Layout:', 'flipset' ); ?></label>
											<div class="flipset-view-pills">
												<label class="flipset-view-pill">
													<input type="radio" name="flipset_b_view" value="page" <?php checked( $default_view, 'page' ); ?> />
													<span><?php esc_html_e( 'Single Page', 'flipset' ); ?></span>
												</label>
												<label class="flipset-view-pill">
													<input type="radio" name="flipset_b_view" value="scroll" <?php checked( $default_view, 'scroll' ); ?> />
													<span><?php esc_html_e( 'Continuous Scroll', 'flipset' ); ?></span>
												</label>
												<label class="flipset-view-pill">
													<input type="radio" name="flipset_b_view" value="spread" <?php checked( $default_view, 'spread' ); ?> />
													<span><?php esc_html_e( 'Two-Page Spread', 'flipset' ); ?></span>
												</label>
												<label class="flipset-view-pill flipset-view-pill-flip">
													<input type="radio" name="flipset_b_view" value="flip" <?php checked( $default_view, 'flip' ); ?> />
													<span>✨ <?php esc_html_e( '3D FlipBook', 'flipset' ); ?></span>
												</label>
											</div>
										</div>
									</div>
								</div>
								<div class="flipset-pane-footer">
									<button type="button" class="button flipset-tab-step" data-step-to="doc">
										<?php esc_html_e( '← Back', 'flipset' ); ?>
									</button>
									<button type="button" class="button flipset-tab-step" data-step-to="controls">
										<?php esc_html_e( 'Next: Toolbar & Advanced →', 'flipset' ); ?>
									</button>
								</div>
							</div>

							<!-- Tab 3: Toolbar & Advanced Pane -->
							<div class="flipset-ctrl-pane" id="flipset-pane-controls" role="tabpanel" style="display: none;">
								<div class="flipset-pane-body">
									<div class="flipset-section-subhead">
										<h3><?php esc_html_e( 'Toolbar Buttons & Controls', 'flipset' ); ?></h3>
										<p class="description"><?php esc_html_e( 'Uncheck any button you want to hide from the viewer toolbar:', 'flipset' ); ?></p>
									</div>

									<div class="flipset-tool-switches-grid">
										<label class="flipset-tool-switch">
											<input type="checkbox" class="flipset-tool-toggle" data-tool="download" data-attr="download" data-global="<?php echo ( ! isset( $settings['allow_download'] ) || ! empty( $settings['allow_download'] ) ) ? '1' : '0'; ?>" <?php checked( ! in_array( 'download', $default_hide, true ) ); ?> />
											<span class="flipset-switch-label">📥 <?php esc_html_e( 'Download', 'flipset' ); ?></span>
											<span class="flipset-loc-badge is-bar"><?php esc_html_e( 'Toolbar', 'flipset' ); ?></span>
										</label>
										<label class="flipset-tool-switch">
											<input type="checkbox" class="flipset-tool-toggle" data-tool="print" data-attr="print" data-global="<?php echo ( ! isset( $settings['allow_print'] ) || ! empty( $settings['allow_print'] ) ) ? '1' : '0'; ?>" <?php checked( ! in_array( 'print', $default_hide, true ) ); ?> />
											<span class="flipset-switch-label">🖨️ <?php esc_html_e( 'Print', 'flipset' ); ?></span>
											<span class="flipset-loc-badge is-menu"><?php esc_html_e( '⋮ Menu', 'flipset' ); ?></span>
										</label>
										<label class="flipset-tool-switch">
											<input type="checkbox" class="flipset-tool-toggle" data-tool="search" data-attr="search" data-global="<?php echo ( ! isset( $settings['allow_search'] ) || ! empty( $settings['allow_search'] ) ) ? '1' : '0'; ?>" <?php checked( ! in_array( 'search', $default_hide, true ) ); ?> />
											<span class="flipset-switch-label">🔍 <?php esc_html_e( 'Text Search (Ctrl+F)', 'flipset' ); ?></span>
											<span class="flipset-loc-badge is-bar"><?php esc_html_e( 'Toolbar', 'flipset' ); ?></span>
										</label>
										<label class="flipset-tool-switch">
											<input type="checkbox" class="flipset-tool-toggle" data-tool="theme" data-attr="theme_btn" data-global="<?php echo ( ! isset( $settings['allow_theme'] ) || ! empty( $settings['allow_theme'] ) ) ? '1' : '0'; ?>" <?php checked( ! in_array( 'theme', $default_hide, true ) ); ?> />
											<span class="flipset-switch-label">🎨 <?php esc_html_e( 'Theme Switcher', 'flipset' ); ?></span>
											<span class="flipset-loc-badge is-menu"><?php esc_html_e( '⋮ Menu', 'flipset' ); ?></span>
										</label>
										<label class="flipset-tool-switch">
											<input type="checkbox" class="flipset-tool-toggle" data-tool="share" data-attr="share" data-global="<?php echo ( ! isset( $settings['allow_share'] ) || ! empty( $settings['allow_share'] ) ) ? '1' : '0'; ?>" <?php checked( ! in_array( 'share', $default_hide, true ) ); ?> />
											<span class="flipset-switch-label">🔗 <?php esc_html_e( 'Share & Page Link', 'flipset' ); ?></span>
											<span class="flipset-loc-badge is-menu"><?php esc_html_e( '⋮ Menu', 'flipset' ); ?></span>
										</label>
										<label class="flipset-tool-switch">
											<input type="checkbox" class="flipset-tool-toggle" data-tool="shortcuts" data-attr="shortcuts" data-global="<?php echo ( ! isset( $settings['allow_shortcuts'] ) || ! empty( $settings['allow_shortcuts'] ) ) ? '1' : '0'; ?>" <?php checked( ! in_array( 'shortcuts', $default_hide, true ) ); ?> />
											<span class="flipset-switch-label">⌨️ <?php esc_html_e( 'Shortcuts Guide (?)', 'flipset' ); ?></span>
											<span class="flipset-loc-badge is-menu"><?php esc_html_e( '⋮ Menu', 'flipset' ); ?></span>
										</label>
										<label class="flipset-tool-switch">
											<input type="checkbox" class="flipset-tool-toggle" data-tool="fullscreen" data-attr="fullscreen" data-global="<?php echo ( ! isset( $settings['allow_fullscreen'] ) || ! empty( $settings['allow_fullscreen'] ) ) ? '1' : '0'; ?>" <?php checked( ! in_array( 'fullscreen', $default_hide, true ) ); ?> />
											<span class="flipset-switch-label">⛶ <?php esc_html_e( 'Full Screen', 'flipset' ); ?></span>
											<span class="flipset-loc-badge is-bar"><?php esc_html_e( 'Toolbar', 'flipset' ); ?></span>
										</label>
										<label class="flipset-tool-switch">
											<input type="checkbox" class="flipset-tool-toggle" data-tool="presentation" data-attr="presentation" data-global="<?php echo ( ! isset( $settings['allow_presentation'] ) || ! empty( $settings['allow_presentation'] ) ) ? '1' : '0'; ?>" <?php checked( ! in_array( 'presentation', $default_hide, true ) ); ?> />
											<span class="flipset-switch-label">📽️ <?php esc_html_e( 'Presentation Mode', 'flipset' ); ?></span>
											<span class="flipset-loc-badge is-menu"><?php esc_html_e( '⋮ Menu', 'flipset' ); ?></span>
										</label>
										<label class="flipset-tool-switch">
											<input type="checkbox" class="flipset-tool-toggle" data-tool="zoom" <?php checked( ! in_array( 'zoom', $default_hide, true ) ); ?> />
											<span class="flipset-switch-label">🔍 <?php esc_html_e( 'Zoom In / Out', 'flipset' ); ?></span>
											<span class="flipset-loc-badge is-bar"><?php esc_html_e( 'Toolbar', 'flipset' ); ?></span>
										</label>
										<label class="flipset-tool-switch">
											<input type="checkbox" class="flipset-tool-toggle" data-tool="rotate" <?php checked( ! in_array( 'rotate', $default_hide, true ) ); ?> />
											<span class="flipset-switch-label">🔄 <?php esc_html_e( 'Rotate Document', 'flipset' ); ?></span>
											<span class="flipset-loc-badge is-menu"><?php esc_html_e( '⋮ Menu', 'flipset' ); ?></span>
										</label>
										<label class="flipset-tool-switch">
											<input type="checkbox" class="flipset-tool-toggle" data-tool="pan" <?php checked( ! in_array( 'pan', $default_hide, true ) ); ?> />
											<span class="flipset-switch-label">✋ <?php esc_html_e( 'Hand / Pan Tool', 'flipset' ); ?></span>
											<span class="flipset-loc-badge is-menu"><?php esc_html_e( '⋮ Menu', 'flipset' ); ?></span>
										</label>
									</div>

									<hr class="flipset-pane-divider" />

									<div class="flipset-section-subhead">
										<h3><?php esc_html_e( 'Performance & Advanced', 'flipset' ); ?></h3>
									</div>

									<div class="flipset-checkbox-group">
										<label class="flipset-check-label">
											<input type="checkbox" id="flipset-builder-lazy" <?php checked( $default_lazy ); ?> />
											<span><strong><?php esc_html_e( 'Lazy Loading (loading="lazy")', 'flipset' ); ?></strong> — <?php esc_html_e( 'Only load PDF when scrolled into viewport.', 'flipset' ); ?></span>
										</label>
										<label class="flipset-check-label">
											<input type="checkbox" id="flipset-builder-hash" <?php checked( $default_hash ); ?> />
											<span><strong><?php esc_html_e( 'Sync Browser URL Hash (hash="true")', 'flipset' ); ?></strong> — <?php esc_html_e( 'Update #page=N in address bar for easy bookmarking.', 'flipset' ); ?></span>
										</label>
										<label class="flipset-check-label">
											<input type="checkbox" id="flipset-builder-resume" <?php checked( $default_resume ); ?> />
											<span><strong><?php esc_html_e( 'Remember Last Read Page (resume="true")', 'flipset' ); ?></strong> — <?php esc_html_e( 'Auto-resume where visitor left off.', 'flipset' ); ?></span>
										</label>
									</div>
								</div>
								<div class="flipset-pane-footer">
									<button type="button" class="button flipset-tab-step" data-step-to="layout">
										<?php esc_html_e( '← Back', 'flipset' ); ?>
									</button>
									<span></span>
								</div>
							</div>
						</div>
					</div>

					<!-- Right Column: Sticky Live Preview & Output -->
					<div class="flipset-builder-sidebar">
						<div class="flipset-builder-sticky">
							<!-- Live Preview Card -->
							<div class="flipset-card flipset-builder-preview-card">
								<div class="flipset-preview-header">
									<div class="flipset-preview-title">
										<span class="dashicons dashicons-visibility" aria-hidden="true"></span>
										<h3><?php esc_html_e( 'Real-Time Live Preview', 'flipset' ); ?></h3>
									</div>
									<div class="flipset-device-switcher" id="flipset-device-switcher">
										<button type="button" class="flipset-device-btn is-active" data-device="desktop" title="<?php esc_attr_e( 'Desktop View (100%)', 'flipset' ); ?>">
											<span class="dashicons dashicons-desktop" aria-hidden="true"></span>
										</button>
										<button type="button" class="flipset-device-btn" data-device="tablet" title="<?php esc_attr_e( 'Tablet View (768px)', 'flipset' ); ?>">
											<span class="dashicons dashicons-tablet" aria-hidden="true"></span>
										</button>
										<button type="button" class="flipset-device-btn" data-device="mobile" title="<?php esc_attr_e( 'Mobile View (375px)', 'flipset' ); ?>">
											<span class="dashicons dashicons-smartphone" aria-hidden="true"></span>
										</button>
									</div>
								</div>

								<!-- Live Preview Frame -->
								<div class="flipset-builder-frame-wrap">
									<div class="flipset-builder-frame" id="flipset-builder-frame">
										<div class="flipset-builder-preview-stage" id="flipset-builder-stage">
											<?php if ( $initial_preview ) : ?>
												<?php echo wp_kses( $initial_preview, Flipset_Viewer::allowed_html() ); ?>
											<?php else : ?>
												<div class="flipset-builder-empty-state">
													<span class="dashicons dashicons-pdf" aria-hidden="true"></span>
													<p><?php esc_html_e( 'Select or upload a PDF above to see live interactive preview.', 'flipset' ); ?></p>
												</div>
											<?php endif; ?>
										</div>
									</div>
								</div>
							</div>

							<!-- Generated Output Card -->
							<div class="flipset-card flipset-builder-output-card">
								<div class="flipset-output-tabs">
									<button type="button" class="flipset-tab-btn is-active" data-format="shortcode"><?php esc_html_e( 'Shortcode', 'flipset' ); ?></button>
									<button type="button" class="flipset-tab-btn" data-format="php"><?php esc_html_e( 'PHP Snippet', 'flipset' ); ?></button>
									<button type="button" class="flipset-tab-btn" data-format="block"><?php esc_html_e( 'Block Code', 'flipset' ); ?></button>
								</div>

								<div class="flipset-output-box">
									<textarea id="flipset-builder-output" class="flipset-builder-textarea" readonly rows="3"><?php echo $editing_id ? esc_textarea( sprintf( '[flipset id="%d"]', $editing_id ) ) : ''; ?></textarea>
								</div>

								<div class="flipset-output-actions">
									<button type="button" class="button button-primary button-hero" id="flipset-builder-save">
										<span class="dashicons dashicons-saved" aria-hidden="true"></span>
										<span id="flipset-save-btn-text"><?php echo $editing_id ? esc_html__( 'Update Embed & Copy', 'flipset' ) : esc_html__( 'Save Embed & Get Shortcode', 'flipset' ); ?></span>
									</button>
									<button type="button" class="button button-secondary" id="flipset-builder-copy" title="<?php esc_attr_e( 'Copy code to clipboard', 'flipset' ); ?>">
										<span class="dashicons dashicons-clipboard" aria-hidden="true"></span> <?php esc_html_e( 'Copy', 'flipset' ); ?>
									</button>
									<button type="button" class="button button-secondary" id="flipset-builder-create-page">
										<span class="dashicons dashicons-admin-page" aria-hidden="true"></span> <?php esc_html_e( 'Create Test Page', 'flipset' ); ?>
									</button>
								</div>

								<div class="flipset-code-mode-row">
									<label class="flipset-code-mode-toggle">
										<input type="checkbox" id="flipset-toggle-raw-shortcode" />
										<span><?php esc_html_e( 'Developer: Show full inline shortcode', 'flipset' ); ?></span>
									</label>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>

			<!-- VIEW 2: My Saved Embeds Manager -->
			<div class="flipset-main-view-pane" id="flipset-view-embeds" style="display: none;">
				<div class="flipset-card flipset-embeds-manager-card">
					<div class="flipset-manager-header">
						<div class="flipset-manager-title">
							<span class="dashicons dashicons-portfolio" aria-hidden="true"></span>
							<h2><?php esc_html_e( 'My Saved Embeds', 'flipset' ); ?></h2>
						</div>
						<div class="flipset-manager-actions">
							<input type="search" id="flipset-embeds-search" class="regular-text" placeholder="<?php esc_attr_e( 'Search embeds…', 'flipset' ); ?>" />
							<button type="button" class="button button-primary flipset-switch-to-builder">
								<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span> <?php esc_html_e( 'Create New Embed', 'flipset' ); ?>
							</button>
						</div>
					</div>

					<div class="flipset-embeds-table-wrap">
						<?php if ( ! empty( $saved_embeds ) ) : ?>
						<table class="wp-list-table widefat fixed striped flipset-embeds-table">
							<thead>
								<tr>
									<th scope="col" class="column-id" style="width: 65px;"><?php esc_html_e( 'ID', 'flipset' ); ?></th>
									<th scope="col" class="column-name"><?php esc_html_e( 'Embed Name / Title', 'flipset' ); ?></th>
									<th scope="col" class="column-file"><?php esc_html_e( 'PDF Document Source', 'flipset' ); ?></th>
									<th scope="col" class="column-layout" style="width: 130px;"><?php esc_html_e( 'Layout & Theme', 'flipset' ); ?></th>
									<th scope="col" class="column-shortcode" style="width: 190px;"><?php esc_html_e( 'Shortcode', 'flipset' ); ?></th>
									<th scope="col" class="column-date" style="width: 110px;"><?php esc_html_e( 'Date', 'flipset' ); ?></th>
									<th scope="col" class="column-actions" style="width: 180px; text-align: right;"><?php esc_html_e( 'Actions', 'flipset' ); ?></th>
								</tr>
							</thead>
							<tbody id="flipset-embeds-tbody">
								<?php foreach ( $saved_embeds as $emb ) : ?>
								<tr data-embed-id="<?php echo esc_attr( (string) $emb['id'] ); ?>" data-search-text="<?php echo esc_attr( strtolower( $emb['name'] . ' ' . $emb['title'] . ' ' . wp_basename( $emb['file'] ) ) ); ?>">
									<td class="column-id"><strong>#<?php echo esc_html( (string) $emb['id'] ); ?></strong></td>
									<td class="column-name">
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::BUILDER_SLUG . '&embed_id=' . $emb['id'] ) ); ?>" class="row-title">
											<?php echo esc_html( $emb['name'] ); ?>
										</a>
										<?php if ( ! empty( $emb['title'] ) && $emb['title'] !== $emb['name'] ) : ?>
											<span class="flipset-sub-title">(<?php echo esc_html( $emb['title'] ); ?>)</span>
										<?php endif; ?>
									</td>
									<td class="column-file">
										<span class="flipset-file-name" title="<?php echo esc_attr( $emb['file'] ); ?>">
											📄 <?php echo esc_html( wp_basename( (string) $emb['file'] ) ); ?>
										</span>
									</td>
									<td class="column-layout">
										<span class="flipset-tag-pill is-view"><?php echo esc_html( ucfirst( $emb['view'] ) ); ?></span>
										<span class="flipset-tag-pill is-theme"><?php echo esc_html( ucfirst( $emb['theme'] ) ); ?></span>
									</td>
									<td class="column-shortcode">
										<code class="flipset-shortcode-chip" data-copy="<?php echo esc_attr( $emb['shortcode'] ); ?>" title="<?php esc_attr_e( 'Click to copy shortcode', 'flipset' ); ?>">
											<?php echo esc_html( $emb['shortcode'] ); ?>
											<span class="dashicons dashicons-clipboard" aria-hidden="true"></span>
										</code>
									</td>
									<td class="column-date"><?php echo esc_html( $emb['date'] ); ?></td>
									<td class="column-actions" style="text-align: right;">
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::BUILDER_SLUG . '&embed_id=' . $emb['id'] ) ); ?>" class="button button-small">
											<?php esc_html_e( 'Edit', 'flipset' ); ?>
										</a>
										<button type="button" class="button button-small flipset-embed-duplicate-btn" data-id="<?php echo esc_attr( (string) $emb['id'] ); ?>" title="<?php esc_attr_e( 'Duplicate this embed', 'flipset' ); ?>">
											<?php esc_html_e( 'Duplicate', 'flipset' ); ?>
										</button>
										<button type="button" class="button button-small flipset-embed-delete-btn" data-id="<?php echo esc_attr( (string) $emb['id'] ); ?>" title="<?php esc_attr_e( 'Delete this embed', 'flipset' ); ?>" style="color:#b32d2e;">
											<?php esc_html_e( 'Delete', 'flipset' ); ?>
										</button>
									</td>
								</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
						<?php else : ?>
						<div class="flipset-embeds-empty-state">
							<span class="dashicons dashicons-portfolio" aria-hidden="true"></span>
							<h3><?php esc_html_e( 'No saved embeds yet', 'flipset' ); ?></h3>
							<p><?php esc_html_e( 'Create your first PDF embed in the Shortcode Builder and click "Save Embed & Get Shortcode" to generate a shortcode like [flipset id="1"].', 'flipset' ); ?></p>
							<button type="button" class="button button-primary flipset-switch-to-builder">
								<?php esc_html_e( 'Open Shortcode Builder', 'flipset' ); ?>
							</button>
						</div>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Aggregate processing state of one PDF for the Documents list.
	 *
	 * 'attention' = checked and not tagged; 'pending' = a thumbnail, text
	 * extraction or accessibility check is still outstanding; otherwise 'ready'.
	 *
	 * @param int  $id       Attachment ID.
	 * @param bool $index_on Whether PDF text indexing is enabled.
	 * @param bool $a11y_on  Whether accessibility checks are enabled.
	 * @return string One of 'ready', 'pending', 'attention'.
	 */
	private function doc_status( $id, $index_on, $a11y_on ) {
		$a11y_done = Flipset_A11y::is_checked( $id );
		if ( $a11y_on && $a11y_done && ! Flipset_A11y::is_tagged( $id ) ) {
			return 'attention';
		}
		if ( ! Flipset_Thumbnails::get_thumbnail_id( $id )
			|| ( $index_on && ! Flipset_SEO::is_indexed( $id ) )
			|| ( $a11y_on && ! $a11y_done ) ) {
			return 'pending';
		}
		return 'ready';
	}

	public function render_documents_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$tab_param    = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'documents'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current_tab  = in_array( $tab_param, array( 'documents', 'docs', 'features' ), true ) ? $tab_param : 'documents';
		$paged        = max( 1, absint( wp_unslash( $_GET['paged'] ?? 1 ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$search       = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$status_param = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : 'all'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$status       = in_array( $status_param, array( 'all', 'ready', 'pending', 'attention' ), true ) ? $status_param : 'all';
		$settings     = get_option( self::OPTION_NAME, array() );
		$index_on     = ! isset( $settings['index_pdf_text'] ) || ! empty( $settings['index_pdf_text'] );
		$a11y_on      = Flipset_A11y::checking_enabled();

		$docs_url    = admin_url( 'admin.php?page=' . self::DOCS_SLUG );
		$guide_url   = add_query_arg( 'tab', 'docs', $docs_url );
		$feat_url    = add_query_arg( 'tab', 'features', $docs_url );
		$builder_url = admin_url( 'admin.php?page=' . self::BUILDER_SLUG );

		$query     = null;
		$total     = 0;
		$max_pages = 1;
		$counts    = array(
			'all'       => 0,
			'ready'     => 0,
			'pending'   => 0,
			'attention' => 0,
		);
		$counts_ok = true;
		$lib_total = 0;

		if ( 'documents' === $current_tab ) {
			$lib_total = $this->count_pdfs();
			$base_args = array(
				'post_type'              => 'attachment',
				'post_status'            => 'inherit',
				'post_mime_type'         => 'application/pdf',
				's'                      => $search,
				'flipset_content_search' => ( '' !== $search ),
			);

			// One light pass over the matching IDs powers the status chips and filter.
			$id_query  = new WP_Query(
				array_merge(
					$base_args,
					array(
						'fields'         => 'ids',
						'posts_per_page' => 1000, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- IDs only, bounded.
						'no_found_rows'  => true,
						'orderby'        => 'date',
						'order'          => 'DESC',
					)
				)
			);
			$ids       = array_map( 'intval', $id_query->posts );
			$counts_ok = count( $ids ) < 1000;
			update_meta_cache( 'post', $ids );

			$by_status = array();
			foreach ( $ids as $doc_id ) {
				$st                   = $this->doc_status( $doc_id, $index_on, $a11y_on );
				$by_status[ $doc_id ] = $st;
				++$counts[ $st ];
			}
			$counts['all'] = count( $ids );

			if ( 'all' === $status ) {
				$query     = new WP_Query(
					array_merge(
						$base_args,
						array(
							'posts_per_page' => 20,
							'paged'          => $paged,
							'orderby'        => 'date',
							'order'          => 'DESC',
						)
					)
				);
				$total     = (int) $query->found_posts;
				$max_pages = (int) $query->max_num_pages;

				// A page number past the end falls back to the last page.
				if ( $paged > 1 && ! $query->have_posts() ) {
					$paged     = $counts_ok ? max( 1, (int) ceil( $counts['all'] / 20 ) ) : 1;
					$query     = new WP_Query(
						array_merge(
							$base_args,
							array(
								'posts_per_page' => 20,
								'paged'          => $paged,
								'orderby'        => 'date',
								'order'          => 'DESC',
							)
						)
					);
					$total     = (int) $query->found_posts;
					$max_pages = max( 1, (int) $query->max_num_pages );
				}
			} else {
				$filtered  = array_keys( array_filter( $by_status, fn( $st ) => $st === $status ) );
				$total     = count( $filtered );
				$max_pages = max( 1, (int) ceil( $total / 20 ) );
				$paged     = min( $paged, $max_pages );
				$slice     = array_slice( $filtered, ( $paged - 1 ) * 20, 20 );
				$query     = new WP_Query(
					array(
						'post_type'      => 'attachment',
						'post_status'    => 'inherit',
						'post_mime_type' => 'application/pdf',
						'post__in'       => $slice ? $slice : array( 0 ),
						'orderby'        => 'post__in',
						'posts_per_page' => 20,
						'no_found_rows'  => true,
					)
				);
			}
		}

		$untagged_n = 0;
		if ( $a11y_on && $query && $query->have_posts() ) {
			foreach ( $query->posts as $attachment ) {
				if ( Flipset_A11y::is_checked( $attachment->ID ) && ! Flipset_A11y::is_tagged( $attachment->ID ) ) {
					++$untagged_n;
				}
			}
		}

		$ledes = array(
			'documents' => __( 'Every PDF in your Media Library, ready to embed. No duplicate storage.', 'flipset' ),
			'docs'      => __( 'Shortcodes, blocks, deep links and page builders, all in one reference.', 'flipset' ),
			'features'  => __( 'Everything Flipset can do. Fast, local and accessible.', 'flipset' ),
		);
		?>
		<div class="wrap flipset-admin flipset-docs">
			<hr class="wp-header-end" />

			<div class="fa-hero fa-docs-hero">
				<div class="fa-hero__top">
					<div class="fa-hero__copy">
						<div class="fa-hero__kicker">
							<span class="fa-mark dashicons dashicons-book-alt" aria-hidden="true"></span>
							<h1 class="fa-hero__title"><?php esc_html_e( 'Docs & Features', 'flipset' ); ?></h1>
						</div>
						<p class="fa-hero__lede"><?php echo esc_html( $ledes[ $current_tab ] ); ?></p>
					</div>
					<div class="fa-hero__side">
						<div class="fa-hero__actions">
							<a class="fa-btn fa-btn--primary" href="<?php echo esc_url( admin_url( 'media-new.php' ) ); ?>">
								<span class="dashicons dashicons-upload" aria-hidden="true"></span>
								<?php esc_html_e( 'Upload PDF', 'flipset' ); ?>
							</a>
							<a class="fa-btn fa-btn--ghost-dark" href="<?php echo esc_url( $builder_url ); ?>">
								<span class="dashicons dashicons-admin-customizer" aria-hidden="true"></span>
								<?php esc_html_e( 'Open Shortcode Builder', 'flipset' ); ?>
							</a>
						</div>
					</div>
				</div>
			</div>

			<nav class="fa-segmented fa-docs-nav" aria-label="<?php esc_attr_e( 'Docs & Features sections', 'flipset' ); ?>">
				<a class="fa-segmented__btn<?php echo 'documents' === $current_tab ? ' is-active' : ''; ?>" href="<?php echo esc_url( $docs_url ); ?>"<?php echo 'documents' === $current_tab ? ' aria-current="page"' : ''; ?>>
					<span aria-hidden="true">📄</span>
					<span><?php esc_html_e( 'Documents Library', 'flipset' ); ?></span>
					<span class="fa-pill"><?php echo esc_html( (string) $this->count_pdfs() ); ?></span>
				</a>
				<a class="fa-segmented__btn<?php echo 'docs' === $current_tab ? ' is-active' : ''; ?>" href="<?php echo esc_url( $guide_url ); ?>"<?php echo 'docs' === $current_tab ? ' aria-current="page"' : ''; ?>>
					<span aria-hidden="true">📘</span>
					<span><?php esc_html_e( 'User Guide', 'flipset' ); ?></span>
				</a>
				<a class="fa-segmented__btn<?php echo 'features' === $current_tab ? ' is-active' : ''; ?>" href="<?php echo esc_url( $feat_url ); ?>"<?php echo 'features' === $current_tab ? ' aria-current="page"' : ''; ?>>
					<span aria-hidden="true">⭐</span>
					<span><?php esc_html_e( 'All Features', 'flipset' ); ?></span>
				</a>
			</nav>

			<?php if ( 'features' === $current_tab ) : ?>
				<?php $this->render_features_tab(); ?>
			<?php elseif ( 'docs' === $current_tab ) : ?>
				<?php $this->render_user_guide_tab(); ?>
			<?php else : ?>

			<section class="fa-card fa-howto" id="fa-howto" aria-labelledby="fa-howto-title">
				<div class="fa-card__head">
					<span class="fa-card__icon dashicons dashicons-lightbulb" aria-hidden="true"></span>
					<div class="fa-card__titles">
						<h2 class="fa-card__title" id="fa-howto-title"><?php esc_html_e( '4 ways to embed your documents', 'flipset' ); ?></h2>
					</div>
					<button type="button" class="fa-btn fa-btn--secondary fa-btn--sm fa-card__action" id="fa-howto-toggle" aria-expanded="true" aria-controls="fa-howto-body" data-show="<?php esc_attr_e( 'Show', 'flipset' ); ?>" data-hide="<?php esc_attr_e( 'Hide', 'flipset' ); ?>">
						<?php esc_html_e( 'Hide', 'flipset' ); ?>
					</button>
				</div>
				<div class="fa-howto__body" id="fa-howto-body">
					<div class="fa-card__body">
						<div class="fa-tiles fa-tiles--4">
							<div class="fa-tile">
								<span class="fa-num" aria-hidden="true">1</span>
								<div class="fa-tile__copy">
									<span class="fa-tile__title"><?php esc_html_e( 'Shortcode Builder', 'flipset' ); ?></span>
									<p><?php esc_html_e( 'Use the visual Shortcode Builder to configure 3D FlipBook, theme, and toolbar to generate clean [flipset id="1"] shortcodes.', 'flipset' ); ?></p>
								</div>
							</div>
							<div class="fa-tile">
								<span class="fa-num" aria-hidden="true">2</span>
								<div class="fa-tile__copy">
									<span class="fa-tile__title"><?php esc_html_e( 'Direct Shortcode Copy', 'flipset' ); ?></span>
									<p><?php esc_html_e( 'Click "Copy" on any row below to quickly copy [flipset file="..."] and paste it into classic editor, pages, or widgets.', 'flipset' ); ?></p>
								</div>
							</div>
							<div class="fa-tile">
								<span class="fa-num" aria-hidden="true">3</span>
								<div class="fa-tile__copy">
									<span class="fa-tile__title"><?php esc_html_e( '1-Click Test Page', 'flipset' ); ?></span>
									<p><?php esc_html_e( 'Hover over any document title below and click "Create test page" to publish and preview instantly in a new tab.', 'flipset' ); ?></p>
								</div>
							</div>
							<div class="fa-tile">
								<span class="fa-num" aria-hidden="true">4</span>
								<div class="fa-tile__copy">
									<span class="fa-tile__title"><?php esc_html_e( 'Gutenberg & oEmbed', 'flipset' ); ?></span>
									<p><?php esc_html_e( 'Insert the "Flipset Viewer" block, or simply paste any direct .pdf link on a new line to auto-embed.', 'flipset' ); ?></p>
								</div>
							</div>
						</div>
					</div>
					<div class="fa-infobar">
						<span class="fa-infobar__text">
							<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
							<?php esc_html_e( 'Opening this screen automatically generates first-page preview thumbnails, indexes PDF text for site search, and checks accessibility tags.', 'flipset' ); ?>
						</span>
						<a class="fa-link" href="<?php echo esc_url( $guide_url ); ?>"><?php esc_html_e( 'Full Documentation & Cheatsheet', 'flipset' ); ?> &rarr;</a>
					</div>
				</div>
			</section>

			<p class="flipset-thumb-status" hidden><?php esc_html_e( 'Generating thumbnails, indexing PDF text, and checking accessibility tags…', 'flipset' ); ?></p>

				<?php if ( $untagged_n ) : ?>
				<div class="notice notice-warning inline flipset-a11y-notice">
					<p>
						<?php
						echo esc_html(
							sprintf(
								/* translators: %s: number of untagged PDFs on this page. */
								_n(
									'%s PDF on this page is not tagged. Screen readers may not follow the intended reading order. Export tagged PDFs from Word, InDesign, or Acrobat.',
									'%s PDFs on this page are not tagged. Screen readers may not follow the intended reading order. Export tagged PDFs from Word, InDesign, or Acrobat.',
									$untagged_n,
									'flipset'
								),
								number_format_i18n( $untagged_n )
							)
						);
						?>
					</p>
				</div>
				<?php endif; ?>

			<section class="fa-card fa-docs-card" aria-label="<?php esc_attr_e( 'Documents', 'flipset' ); ?>">
				<div class="fa-docs-toolbar">
					<form method="get" class="fa-search flipset-docs-search" role="search">
						<input type="hidden" name="page" value="<?php echo esc_attr( self::DOCS_SLUG ); ?>" />
						<?php if ( 'all' !== $status ) : ?>
							<input type="hidden" name="status" value="<?php echo esc_attr( $status ); ?>" />
						<?php endif; ?>
						<label class="screen-reader-text" for="flipset-docs-search"><?php esc_html_e( 'Search documents', 'flipset' ); ?></label>
						<span class="fa-search__icon dashicons dashicons-search" aria-hidden="true"></span>
						<input type="search" class="fa-search__input" id="flipset-docs-search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search documents…', 'flipset' ); ?>" />
						<button type="submit" class="fa-btn fa-btn--secondary fa-btn--sm" id="search-submit"><?php esc_html_e( 'Search', 'flipset' ); ?></button>
					</form>

					<div class="fa-filters" role="group" aria-label="<?php esc_attr_e( 'Filter by status', 'flipset' ); ?>">
						<?php
						$filters = array(
							'all'       => __( 'All', 'flipset' ),
							'ready'     => __( 'Ready', 'flipset' ),
							'pending'   => __( 'Pending', 'flipset' ),
							'attention' => __( 'Needs attention', 'flipset' ),
						);
						foreach ( $filters as $key => $label ) :
							$link = add_query_arg(
								array_filter(
									array(
										'status' => 'all' === $key ? '' : $key,
										's'      => $search,
									),
									'strlen'
								),
								$docs_url
							);
							?>
							<a class="fa-chip fa-chip--light<?php echo $status === $key ? ' is-active' : ''; ?>" href="<?php echo esc_url( $link ); ?>"<?php echo $status === $key ? ' aria-current="true"' : ''; ?>>
								<?php echo esc_html( $label ); ?>
								<?php if ( $counts_ok ) : ?>
									<span class="fa-chip__count"><?php echo esc_html( (string) $counts[ $key ] ); ?></span>
								<?php endif; ?>
							</a>
						<?php endforeach; ?>
					</div>

					<span class="fa-docs-count">
						<?php
						echo esc_html(
							sprintf(
								/* translators: %s: number of PDFs */
								_n( '%s document', '%s documents', $total, 'flipset' ),
								number_format_i18n( $total )
							)
						);
						?>
					</span>
				</div>

				<?php if ( $query && $query->have_posts() ) : ?>
				<div class="fa-table-wrap">
				<table class="flipset-docs-table fa-table">
					<thead>
						<tr>
							<th scope="col" class="flipset-docs-thumb-col"><?php esc_html_e( 'Preview', 'flipset' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Document', 'flipset' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Search', 'flipset' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Accessibility', 'flipset' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Shortcode', 'flipset' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $query->posts as $attachment ) : ?>
							<?php
							$url   = wp_get_attachment_url( $attachment->ID );
							$path  = get_attached_file( $attachment->ID );
							$bytes = ( $path && file_exists( $path ) ) ? filesize( $path ) : 0;
							$size  = $bytes ? size_format( $bytes ) : '—';
							$code  = sprintf( '[flipset file="%s"]', esc_url_raw( $url ) );
							$edit  = admin_url( 'upload.php?item=' . (int) $attachment->ID );
							$title = get_the_title( $attachment );
							if ( '' === $title ) {
								$title = wp_basename( $url );
							}
							$thumb_id    = Flipset_Thumbnails::get_thumbnail_id( $attachment->ID );
							$indexed     = Flipset_SEO::is_indexed( $attachment->ID );
							$has_text    = $indexed && '' !== Flipset_SEO::get_extracted_text( $attachment->ID );
							$a11y_done   = Flipset_A11y::is_checked( $attachment->ID );
							$a11y_tagged = $a11y_done && Flipset_A11y::is_tagged( $attachment->ID );
							$open_url    = add_query_arg( 'pdf_id', (int) $attachment->ID, $builder_url );
							?>
							<tr
								data-pdf-id="<?php echo esc_attr( (string) $attachment->ID ); ?>"
								data-pdf-url="<?php echo esc_url( $url ); ?>"
								data-pdf-title="<?php echo esc_attr( $title ); ?>"
								data-needs-thumb="<?php echo $thumb_id ? '0' : '1'; ?>"
								data-needs-index="<?php echo ( $index_on && ! $indexed ) ? '1' : '0'; ?>"
								data-needs-a11y="<?php echo ( $a11y_on && ! $a11y_done ) ? '1' : '0'; ?>"
							>
								<td class="flipset-docs-thumb-col fa-col-thumb">
									<?php if ( $thumb_id ) : ?>
										<?php
										echo wp_get_attachment_image(
											$thumb_id,
											'thumbnail',
											false,
											array(
												'class' => 'flipset-docs-thumb-img',
												'alt'   => $title,
											)
										);
										?>
									<?php else : ?>
										<span
											class="flipset-thumb-placeholder"
											data-pdf-id="<?php echo esc_attr( (string) $attachment->ID ); ?>"
											data-pdf-url="<?php echo esc_url( $url ); ?>"
											data-pdf-title="<?php echo esc_attr( $title ); ?>"
										></span>
									<?php endif; ?>
								</td>
								<td class="fa-col-doc">
									<a class="fa-doc__title" href="<?php echo esc_url( $edit ); ?>" title="<?php echo esc_attr( $title ); ?>"><?php echo esc_html( $title ); ?></a>
									<span class="fa-doc__meta"><?php echo esc_html( $size . ' · ' . get_the_date( '', $attachment ) ); ?></span>
									<div class="fa-row-actions">
										<button type="button" class="fa-link flipset-doc-create-test-btn" data-pdf-url="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'Create test page', 'flipset' ); ?></button>
										<a class="fa-link" href="<?php echo esc_url( $open_url ); ?>"><?php esc_html_e( 'Open in Builder', 'flipset' ); ?></a>
										<a class="fa-link" href="<?php echo esc_url( $edit ); ?>"><?php esc_html_e( 'View in Media', 'flipset' ); ?></a>
										<a class="fa-link" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open file', 'flipset' ); ?></a>
									</div>
								</td>
								<td class="fa-col-search">
									<?php if ( $indexed && $has_text ) : ?>
										<span class="fa-status flipset-index-status is-ready" title="<?php esc_attr_e( 'Text extracted. WordPress search can find words in this PDF.', 'flipset' ); ?>"><?php esc_html_e( 'Indexed', 'flipset' ); ?></span>
									<?php elseif ( $indexed ) : ?>
										<span class="fa-status flipset-index-status is-empty" title="<?php esc_attr_e( 'No selectable text was found (a scanned image PDF), so it cannot be searched.', 'flipset' ); ?>"><?php esc_html_e( 'No text', 'flipset' ); ?></span>
									<?php elseif ( ! $index_on ) : ?>
										<span class="fa-status is-empty" title="<?php esc_attr_e( 'PDF text indexing is turned off in Settings.', 'flipset' ); ?>"><?php esc_html_e( 'Off', 'flipset' ); ?></span>
									<?php else : ?>
										<span class="fa-status flipset-index-status is-pending" title="<?php esc_attr_e( 'Waiting for text extraction. Keep this screen open.', 'flipset' ); ?>"><?php esc_html_e( 'Pending', 'flipset' ); ?></span>
									<?php endif; ?>
								</td>
								<td class="fa-col-a11y">
									<?php if ( $a11y_done && $a11y_tagged ) : ?>
										<span class="fa-status flipset-a11y-status is-ready" title="<?php esc_attr_e( 'Tagged PDF. Screen readers can follow its structure.', 'flipset' ); ?>"><?php esc_html_e( 'Tagged', 'flipset' ); ?></span>
									<?php elseif ( $a11y_done ) : ?>
										<span
											class="fa-status flipset-a11y-status is-warn"
											title="<?php esc_attr_e( 'This PDF has no accessibility tags. Export it from Word, InDesign, or Acrobat as a tagged PDF so screen readers can follow headings and reading order.', 'flipset' ); ?>"
										><?php esc_html_e( 'Untagged', 'flipset' ); ?></span>
									<?php elseif ( ! $a11y_on ) : ?>
										<span class="fa-status is-empty" title="<?php esc_attr_e( 'Accessibility checks are turned off in Settings.', 'flipset' ); ?>"><?php esc_html_e( 'Off', 'flipset' ); ?></span>
									<?php else : ?>
										<span class="fa-status flipset-a11y-status is-pending" title="<?php esc_attr_e( 'Waiting for the accessibility check. Keep this screen open.', 'flipset' ); ?>"><?php esc_html_e( 'Pending', 'flipset' ); ?></span>
									<?php endif; ?>
								</td>
								<td class="fa-col-code">
									<div class="fa-code-chip">
										<code class="flipset-docs-shortcode" title="<?php echo esc_attr( $code ); ?>"><?php echo esc_html( $code ); ?></code>
										<button type="button" class="fa-icon-btn flipset-copy-btn" data-flipset-copy="<?php echo esc_attr( $code ); ?>" aria-label="<?php esc_attr_e( 'Copy shortcode', 'flipset' ); ?>" title="<?php esc_attr_e( 'Copy shortcode', 'flipset' ); ?>">
											<span class="dashicons dashicons-admin-page" aria-hidden="true"></span>
											<span class="dashicons dashicons-yes fa-icon-btn__done" aria-hidden="true"></span>
										</button>
									</div>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				</div>

					<?php if ( $max_pages > 1 ) : ?>
					<div class="fa-pager">
						<?php
						echo wp_kses_post(
							paginate_links(
								array(
									'base'      => add_query_arg( 'paged', '%#%' ),
									'format'    => '',
									'total'     => $max_pages,
									'current'   => $paged,
									'prev_text' => '&laquo;',
									'next_text' => '&raquo;',
								)
							)
						);
						?>
					</div>
					<?php endif; ?>
				<?php else : ?>
					<div class="fa-empty flipset-docs-empty">
						<span class="fa-empty__tile dashicons dashicons-media-document" aria-hidden="true"></span>
						<?php if ( '' !== $search ) : ?>
							<p class="fa-empty__title">
								<?php
								/* translators: %s: the search term. */
								echo esc_html( sprintf( __( 'No documents match "%s"', 'flipset' ), $search ) );
								?>
							</p>
							<a class="fa-btn fa-btn--primary" href="<?php echo esc_url( $docs_url ); ?>"><?php esc_html_e( 'Clear search', 'flipset' ); ?></a>
						<?php elseif ( 'all' !== $status ) : ?>
							<p class="fa-empty__title"><?php esc_html_e( 'No documents in this status.', 'flipset' ); ?></p>
							<a class="fa-btn fa-btn--secondary" href="<?php echo esc_url( $docs_url ); ?>"><?php esc_html_e( 'Show all documents', 'flipset' ); ?></a>
						<?php else : ?>
							<p class="fa-empty__title"><?php esc_html_e( 'No PDFs yet', 'flipset' ); ?></p>
							<a class="fa-btn fa-btn--primary" href="<?php echo esc_url( admin_url( 'media-new.php' ) ); ?>"><?php esc_html_e( 'Upload PDF', 'flipset' ); ?></a>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</section>
			<?php endif; ?>
			<div class="flipset-toast" id="flipset-toast" hidden role="status"></div>
		</div>
		<?php
		// Custom WP_Query does not change $wp_query, so wp_reset_postdata() is
		// not needed here. Explicitly unset to release the object from memory.
		unset( $query );
	}

	/**
	 * Render comprehensive user guide and shortcodes cheatsheet.
	 */
	public function render_user_guide_tab() {
		?>
		<div class="flipset-guide-wrap">
			<section class="fa-card fa-guide-intro">
				<div class="fa-card__head">
					<span class="fa-card__icon dashicons dashicons-book-alt" aria-hidden="true"></span>
					<div class="fa-card__titles">
						<h2 class="fa-card__title"><?php esc_html_e( 'Flipset User Guide & Complete Reference', 'flipset' ); ?></h2>
						<p class="fa-card__sub"><?php esc_html_e( 'Learn how to easily embed PDFs, customize 3D FlipBook view modes, use shortcodes, integrate with page builders, and optimize for SEO and speed.', 'flipset' ); ?></p>
					</div>
				</div>
			</section>

			<nav class="fa-subnav" id="fa-guide-nav" aria-label="<?php esc_attr_e( 'Guide sections', 'flipset' ); ?>">
				<a href="#guide-quickstart" class="fa-chip fa-chip--light"><span class="dashicons dashicons-controls-play" aria-hidden="true"></span> <?php esc_html_e( 'Quick Start', 'flipset' ); ?></a>
				<a href="#guide-shortcodes" class="fa-chip fa-chip--light"><span class="dashicons dashicons-editor-code" aria-hidden="true"></span> <?php esc_html_e( '[flipset] API', 'flipset' ); ?></a>
				<a href="#guide-library" class="fa-chip fa-chip--light"><span class="dashicons dashicons-grid-view" aria-hidden="true"></span> <?php esc_html_e( '[flipset_library]', 'flipset' ); ?></a>
				<a href="#guide-deeplink" class="fa-chip fa-chip--light"><span class="dashicons dashicons-admin-links" aria-hidden="true"></span> <?php esc_html_e( 'Deep Linking', 'flipset' ); ?></a>
				<a href="#guide-builders" class="fa-chip fa-chip--light"><span class="dashicons dashicons-layout" aria-hidden="true"></span> <?php esc_html_e( 'Page Builders', 'flipset' ); ?></a>
				<a href="#guide-faq" class="fa-chip fa-chip--light"><span class="dashicons dashicons-sos" aria-hidden="true"></span> <?php esc_html_e( 'FAQs & Tips', 'flipset' ); ?></a>
			</nav>

			<!-- 1. Quick Start -->
			<section id="guide-quickstart" class="flipset-guide-section">
				<div class="flipset-guide-section-head">
					<div class="flipset-guide-sec-icon-wrap">
						<span class="dashicons dashicons-controls-play flipset-guide-icon-sec" aria-hidden="true"></span>
					</div>
					<div>
						<h3><?php esc_html_e( '1. Quick Start: 3 Easy Ways to Embed', 'flipset' ); ?></h3>
						<p class="flipset-guide-subtitle"><?php esc_html_e( 'Choose whichever workflow fits your editing style.', 'flipset' ); ?></p>
					</div>
				</div>
				<div class="fa-tiles fa-tiles--3">
					<div class="fa-tile">
						<div class="fa-num" aria-hidden="true">A</div>
						<div class="fa-tile__copy">
							<h4><?php esc_html_e( 'Shortcode (Anywhere)', 'flipset' ); ?></h4>
							<p><?php esc_html_e( 'Copy [flipset file="..."] from the Documents Library tab and paste it into any post, page, widget, or template.', 'flipset' ); ?></p>
							<div class="flipset-mini-code-box">
								<code>[flipset file="https://site.com/doc.pdf"]</code>
								<button type="button" class="flipset-copy-snippet-btn" data-flipset-copy='[flipset file="https://site.com/doc.pdf"]' title="<?php esc_attr_e( 'Copy shortcode', 'flipset' ); ?>">
									<span class="dashicons dashicons-admin-page" aria-hidden="true"></span>
								</button>
							</div>
						</div>
					</div>
					<div class="fa-tile">
						<div class="fa-num" aria-hidden="true">B</div>
						<div class="fa-tile__copy">
							<h4><?php esc_html_e( 'Gutenberg Native Block', 'flipset' ); ?></h4>
							<p><?php esc_html_e( 'In the Block Editor, insert the "Flipset Viewer" block. Pick any PDF from your Media Library with instant live rendering.', 'flipset' ); ?></p>
							<div class="flipset-step-chip is-blue">
								<span class="dashicons dashicons-block-default" aria-hidden="true"></span>
								<span><?php esc_html_e( 'Visual Editor Block', 'flipset' ); ?></span>
							</div>
						</div>
					</div>
					<div class="fa-tile">
						<div class="fa-num" aria-hidden="true">C</div>
						<div class="fa-tile__copy">
							<h4><?php esc_html_e( 'Auto oEmbed (Paste URL)', 'flipset' ); ?></h4>
							<p><?php esc_html_e( 'Simply paste a direct .pdf link on a new line in your editor. WordPress automatically transforms it into an interactive reader.', 'flipset' ); ?></p>
							<div class="flipset-step-chip is-green">
								<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
								<span><?php esc_html_e( 'Zero Shortcode Required', 'flipset' ); ?></span>
							</div>
						</div>
					</div>
				</div>
			</section>

			<!-- 2. [flipset] Shortcode Cheatsheet -->
			<section id="guide-shortcodes" class="flipset-guide-section">
				<div class="flipset-guide-section-head">
					<div class="flipset-guide-sec-icon-wrap">
						<span class="dashicons dashicons-editor-code flipset-guide-icon-sec" aria-hidden="true"></span>
					</div>
					<div>
						<h3><?php esc_html_e( '2. [flipset] Shortcode Parameters & Attributes', 'flipset' ); ?></h3>
						<p class="flipset-guide-subtitle"><?php esc_html_e( 'Customize every aspect of your PDF viewer with these parameters:', 'flipset' ); ?></p>
					</div>
				</div>

				<div class="flipset-api-table-card">
					<table class="flipset-api-table">
						<thead>
							<tr>
								<th scope="col" style="width: 15%;"><?php esc_html_e( 'Attribute', 'flipset' ); ?></th>
								<th scope="col" style="width: 18%;"><?php esc_html_e( 'Type & Default', 'flipset' ); ?></th>
								<th scope="col" style="width: 42%;"><?php esc_html_e( 'Description & Options', 'flipset' ); ?></th>
								<th scope="col" style="width: 25%;"><?php esc_html_e( 'Example', 'flipset' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<tr style="background: rgba(79, 70, 229, 0.04);">
								<td>
									<div class="flipset-attr-cell">
										<code class="flipset-param-code" style="color: #4f46e5; border-color: #c7d2fe;">id</code>
										<span class="flipset-pill-req" style="background: #e0e7ff; color: #3730a3; border-color: #c7d2fe;"><?php esc_html_e( 'Recommended', 'flipset' ); ?></span>
									</div>
								</td>
								<td>
									<span class="flipset-type-pill">Integer</span>
									<span class="flipset-default-pill"><?php esc_html_e( 'Saved Embed ID', 'flipset' ); ?></span>
								</td>
								<td>
									<p class="flipset-tbl-desc"><strong><?php esc_html_e( 'Clean Saved Embed Shortcode:', 'flipset' ); ?></strong> <?php esc_html_e( 'Encapsulates all PDF settings, dimensions, themes, 3D flipbook mode, and toolbar options generated from the Shortcode Builder into a clean, short code.', 'flipset' ); ?></p>
								</td>
								<td>
									<div class="flipset-code-chip-copy" data-flipset-copy='[flipset id="1"]'>
										<code>[flipset id="1"]</code>
										<span class="dashicons dashicons-admin-page" aria-hidden="true"></span>
									</div>
								</td>
							</tr>
							<tr>
								<td>
									<div class="flipset-attr-cell">
										<code class="flipset-param-code">file</code>
										<span class="flipset-pill-req"><?php esc_html_e( 'Required unless id is set', 'flipset' ); ?></span>
									</div>
								</td>
								<td>
									<span class="flipset-type-pill">URL string</span>
								</td>
								<td>
									<p class="flipset-tbl-desc"><?php esc_html_e( 'Direct URL to your PDF document (local Media Library or CORS-enabled remote host).', 'flipset' ); ?></p>
								</td>
								<td>
									<div class="flipset-code-chip-copy" data-flipset-copy='file="https://example.com/doc.pdf"'>
										<code>file="https://example.com/doc.pdf"</code>
										<span class="dashicons dashicons-admin-page" aria-hidden="true"></span>
									</div>
								</td>
							</tr>
							<tr>
								<td>
									<div class="flipset-attr-cell">
										<code class="flipset-param-code">view</code>
										<span class="flipset-pill-opt"><?php esc_html_e( 'Optional', 'flipset' ); ?></span>
									</div>
								</td>
								<td>
									<span class="flipset-type-pill">Enum</span>
									<span class="flipset-default-pill">default: "page"</span>
								</td>
								<td>
									<p class="flipset-tbl-desc"><strong><?php esc_html_e( 'Reading Layout Mode:', 'flipset' ); ?></strong></p>
									<ul class="flipset-option-list">
										<li><code class="flipset-val-code">flip</code> &mdash; <?php esc_html_e( '3D realistic FlipBook with Web Audio page turn sound.', 'flipset' ); ?></li>
										<li><code class="flipset-val-code">scroll</code> &mdash; <?php esc_html_e( 'Continuous vertical scrolling feed.', 'flipset' ); ?></li>
										<li><code class="flipset-val-code">spread</code> &mdash; <?php esc_html_e( 'Two-page magazine book spread.', 'flipset' ); ?></li>
										<li><code class="flipset-val-code">page</code> &mdash; <?php esc_html_e( 'Standard single page reader.', 'flipset' ); ?></li>
									</ul>
								</td>
								<td>
									<div class="flipset-code-chip-copy" data-flipset-copy='view="flip"'>
										<code>view="flip"</code>
										<span class="dashicons dashicons-admin-page" aria-hidden="true"></span>
									</div>
								</td>
							</tr>
							<tr>
								<td>
									<div class="flipset-attr-cell">
										<code class="flipset-param-code">width</code>
										<span class="flipset-pill-opt"><?php esc_html_e( 'Optional', 'flipset' ); ?></span>
									</div>
								</td>
								<td>
									<span class="flipset-type-pill">CSS Unit</span>
									<span class="flipset-default-pill">default: "100%"</span>
								</td>
								<td>
									<p class="flipset-tbl-desc"><?php esc_html_e( 'Viewer container width. Supports percentage (%), pixels (px), or viewport width (vw).', 'flipset' ); ?></p>
								</td>
								<td>
									<div class="flipset-code-chip-copy" data-flipset-copy='width="850px"'>
										<code>width="850px"</code>
										<span class="dashicons dashicons-admin-page" aria-hidden="true"></span>
									</div>
								</td>
							</tr>
							<tr>
								<td>
									<div class="flipset-attr-cell">
										<code class="flipset-param-code">height</code>
										<span class="flipset-pill-opt"><?php esc_html_e( 'Optional', 'flipset' ); ?></span>
									</div>
								</td>
								<td>
									<span class="flipset-type-pill">CSS Unit</span>
									<span class="flipset-default-pill">default: "420px"</span>
								</td>
								<td>
									<p class="flipset-tbl-desc"><?php esc_html_e( 'Viewer container height. Supports pixels (px), viewport height (vh), or em/rem.', 'flipset' ); ?></p>
								</td>
								<td>
									<div class="flipset-code-chip-copy" data-flipset-copy='height="650px"'>
										<code>height="650px"</code>
										<span class="dashicons dashicons-admin-page" aria-hidden="true"></span>
									</div>
								</td>
							</tr>
							<tr>
								<td>
									<div class="flipset-attr-cell">
										<code class="flipset-param-code">page</code>
										<span class="flipset-pill-opt"><?php esc_html_e( 'Optional', 'flipset' ); ?></span>
									</div>
								</td>
								<td>
									<span class="flipset-type-pill">Integer</span>
									<span class="flipset-default-pill">default: 1</span>
								</td>
								<td>
									<p class="flipset-tbl-desc"><?php esc_html_e( 'Initial starting page number when the document opens.', 'flipset' ); ?></p>
								</td>
								<td>
									<div class="flipset-code-chip-copy" data-flipset-copy='page="5"'>
										<code>page="5"</code>
										<span class="dashicons dashicons-admin-page" aria-hidden="true"></span>
									</div>
								</td>
							</tr>
							<tr>
								<td>
									<div class="flipset-attr-cell">
										<code class="flipset-param-code">download</code>
										<span class="flipset-pill-opt"><?php esc_html_e( 'Optional', 'flipset' ); ?></span>
									</div>
								</td>
								<td>
									<span class="flipset-type-pill">Boolean</span>
									<span class="flipset-default-pill">default: true</span>
								</td>
								<td>
									<p class="flipset-tbl-desc"><?php esc_html_e( 'Show or hide the direct PDF download button in the toolbar.', 'flipset' ); ?></p>
								</td>
								<td>
									<div class="flipset-code-chip-copy" data-flipset-copy='download="false"'>
										<code>download="false"</code>
										<span class="dashicons dashicons-admin-page" aria-hidden="true"></span>
									</div>
								</td>
							</tr>
							<tr>
								<td>
									<div class="flipset-attr-cell">
										<code class="flipset-param-code">print</code>
										<span class="flipset-pill-opt"><?php esc_html_e( 'Optional', 'flipset' ); ?></span>
									</div>
								</td>
								<td>
									<span class="flipset-type-pill">Boolean</span>
									<span class="flipset-default-pill">default: true</span>
								</td>
								<td>
									<p class="flipset-tbl-desc"><?php esc_html_e( 'Show or hide the print button in the toolbar.', 'flipset' ); ?></p>
								</td>
								<td>
									<div class="flipset-code-chip-copy" data-flipset-copy='print="false"'>
										<code>print="false"</code>
										<span class="dashicons dashicons-admin-page" aria-hidden="true"></span>
									</div>
								</td>
							</tr>
							<tr>
								<td>
									<div class="flipset-attr-cell">
										<code class="flipset-param-code">search</code>
										<span class="flipset-pill-opt"><?php esc_html_e( 'Optional', 'flipset' ); ?></span>
									</div>
								</td>
								<td>
									<span class="flipset-type-pill">Boolean</span>
									<span class="flipset-default-pill">default: true</span>
								</td>
								<td>
									<p class="flipset-tbl-desc"><?php esc_html_e( 'Enable or disable the in-document text search (Find) toolbar tool.', 'flipset' ); ?></p>
								</td>
								<td>
									<div class="flipset-code-chip-copy" data-flipset-copy='search="false"'>
										<code>search="false"</code>
										<span class="dashicons dashicons-admin-page" aria-hidden="true"></span>
									</div>
								</td>
							</tr>
							<tr>
								<td>
									<div class="flipset-attr-cell">
										<code class="flipset-param-code">title</code>
										<span class="flipset-pill-opt"><?php esc_html_e( 'Optional', 'flipset' ); ?></span>
									</div>
								</td>
								<td>
									<span class="flipset-type-pill">String</span>
								</td>
								<td>
									<p class="flipset-tbl-desc"><?php esc_html_e( 'Optional caption heading displayed directly above the viewer frame.', 'flipset' ); ?></p>
								</td>
								<td>
									<div class="flipset-code-chip-copy" data-flipset-copy='title="Annual Report 2026"'>
										<code>title="Annual Report 2026"</code>
										<span class="dashicons dashicons-admin-page" aria-hidden="true"></span>
									</div>
								</td>
							</tr>
							<tr>
								<td>
									<div class="flipset-attr-cell">
										<code class="flipset-param-code">hide</code>
										<span class="flipset-pill-opt"><?php esc_html_e( 'Optional', 'flipset' ); ?></span>
									</div>
								</td>
								<td>
									<span class="flipset-type-pill">CSV List</span>
								</td>
								<td>
									<p class="flipset-tbl-desc"><?php esc_html_e( 'Hide specific toolbar buttons (comma-separated):', 'flipset' ); ?></p>
									<p class="flipset-csv-pills">
										<span>nav</span> <span>zoom</span> <span>fit</span> <span>rotate</span> <span>view</span> <span>pan</span> <span>download</span> <span>print</span> <span>search</span> <span>theme</span> <span>share</span> <span>fullscreen</span>
									</p>
								</td>
								<td>
									<div class="flipset-code-chip-copy" data-flipset-copy='hide="download,print,share"'>
										<code>hide="download,print,share"</code>
										<span class="dashicons dashicons-admin-page" aria-hidden="true"></span>
									</div>
								</td>
							</tr>
						</tbody>
					</table>
				</div>

				<!-- Ready-to-Use Shortcode Presets -->
				<div class="flipset-presets-shelf">
					<div class="flipset-presets-head">
						<span class="dashicons dashicons-superhero flipset-presets-icon" aria-hidden="true"></span>
						<div>
							<h4><?php esc_html_e( 'Ready-to-Use Copy Presets', 'flipset' ); ?></h4>
							<p><?php esc_html_e( 'Click the copy button on any preset to copy the full shortcode:', 'flipset' ); ?></p>
						</div>
					</div>
					<div class="flipset-presets-grid">
						<div class="flipset-preset-card">
							<div class="flipset-preset-card-top">
								<span class="flipset-preset-tag is-purple"><?php esc_html_e( '3D FlipBook Mode', 'flipset' ); ?></span>
								<span class="flipset-preset-hint"><?php esc_html_e( 'Realistic Sound & Perspective', 'flipset' ); ?></span>
							</div>
							<div class="flipset-preset-code-row">
								<code>[flipset file="https://site.com/catalog.pdf" view="flip" height="600px"]</code>
								<button type="button" class="flipset-copy-preset-btn" data-flipset-copy='[flipset file="https://site.com/catalog.pdf" view="flip" height="600px"]'>
									<span class="dashicons dashicons-admin-page" aria-hidden="true"></span>
									<span><?php esc_html_e( 'Copy', 'flipset' ); ?></span>
								</button>
							</div>
						</div>

						<div class="flipset-preset-card">
							<div class="flipset-preset-card-top">
								<span class="flipset-preset-tag is-blue"><?php esc_html_e( 'Vertical Continuous Scroll', 'flipset' ); ?></span>
								<span class="flipset-preset-hint"><?php esc_html_e( 'Ideal for Long Reports & Ebooks', 'flipset' ); ?></span>
							</div>
							<div class="flipset-preset-code-row">
								<code>[flipset file="https://site.com/ebook.pdf" view="scroll" height="700px"]</code>
								<button type="button" class="flipset-copy-preset-btn" data-flipset-copy='[flipset file="https://site.com/ebook.pdf" view="scroll" height="700px"]'>
									<span class="dashicons dashicons-admin-page" aria-hidden="true"></span>
									<span><?php esc_html_e( 'Copy', 'flipset' ); ?></span>
								</button>
							</div>
						</div>

						<div class="flipset-preset-card">
							<div class="flipset-preset-card-top">
								<span class="flipset-preset-tag is-slate"><?php esc_html_e( 'Read-Only Presentation', 'flipset' ); ?></span>
								<span class="flipset-preset-hint"><?php esc_html_e( 'No Direct Download or Printing', 'flipset' ); ?></span>
							</div>
							<div class="flipset-preset-code-row">
								<code>[flipset file="https://site.com/slides.pdf" download="false" print="false" view="spread"]</code>
								<button type="button" class="flipset-copy-preset-btn" data-flipset-copy='[flipset file="https://site.com/slides.pdf" download="false" print="false" view="spread"]'>
									<span class="dashicons dashicons-admin-page" aria-hidden="true"></span>
									<span><?php esc_html_e( 'Copy', 'flipset' ); ?></span>
								</button>
							</div>
						</div>
					</div>
				</div>
			</section>

			<!-- 3. [flipset_library] Grid Gallery Reference -->
			<section id="guide-library" class="flipset-guide-section">
				<div class="flipset-guide-section-head">
					<div class="flipset-guide-sec-icon-wrap">
						<span class="dashicons dashicons-grid-view flipset-guide-icon-sec" aria-hidden="true"></span>
					</div>
					<div>
						<h3><?php esc_html_e( '3. [flipset_library] Document Gallery Shortcode', 'flipset' ); ?></h3>
						<p class="flipset-guide-subtitle"><?php esc_html_e( 'Display all your Media Library PDFs in a responsive, searchable grid gallery with lightbox reading modal:', 'flipset' ); ?></p>
					</div>
				</div>
				
				<div class="flipset-api-table-card">
					<table class="flipset-api-table">
						<thead>
							<tr>
								<th scope="col" style="width: 15%;"><?php esc_html_e( 'Attribute', 'flipset' ); ?></th>
								<th scope="col" style="width: 20%;"><?php esc_html_e( 'Values / Default', 'flipset' ); ?></th>
								<th scope="col" style="width: 65%;"><?php esc_html_e( 'Description & Behavior', 'flipset' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td><code class="flipset-param-code">columns</code></td>
								<td>
									<span class="flipset-type-pill">2 | 3 | 4</span>
									<span class="flipset-default-pill">default: 3</span>
								</td>
								<td><p class="flipset-tbl-desc"><?php esc_html_e( 'Number of document grid columns on desktop viewports (automatically reflows on mobile).', 'flipset' ); ?></p></td>
							</tr>
							<tr>
								<td><code class="flipset-param-code">per_page</code></td>
								<td>
									<span class="flipset-type-pill">Integer</span>
									<span class="flipset-default-pill">default: 12</span>
								</td>
								<td><p class="flipset-tbl-desc"><?php esc_html_e( 'How many PDF cards to display per page before showing pagination controls.', 'flipset' ); ?></p></td>
							</tr>
							<tr>
								<td><code class="flipset-param-code">orderby</code></td>
								<td>
									<span class="flipset-type-pill">date | title | modified</span>
									<span class="flipset-default-pill">default: "date"</span>
								</td>
								<td><p class="flipset-tbl-desc"><?php esc_html_e( 'Sorting order for the gallery catalog items.', 'flipset' ); ?></p></td>
							</tr>
							<tr>
								<td><code class="flipset-param-code">search</code></td>
								<td>
									<span class="flipset-type-pill">Boolean</span>
									<span class="flipset-default-pill">default: true</span>
								</td>
								<td><p class="flipset-tbl-desc"><?php esc_html_e( 'Show real-time instant search input box above the document gallery.', 'flipset' ); ?></p></td>
							</tr>
						</tbody>
					</table>
				</div>

				<div class="flipset-preset-card flipset-preset-single">
					<div class="flipset-preset-card-top">
						<span class="flipset-preset-tag is-teal"><?php esc_html_e( 'Interactive Gallery Preset', 'flipset' ); ?></span>
						<span class="flipset-preset-hint"><?php esc_html_e( 'Searchable 4-column document directory', 'flipset' ); ?></span>
					</div>
					<div class="flipset-preset-code-row">
						<code>[flipset_library columns="4" per_page="16" orderby="title" search="true"]</code>
						<button type="button" class="flipset-copy-preset-btn" data-flipset-copy='[flipset_library columns="4" per_page="16" orderby="title" search="true"]'>
							<span class="dashicons dashicons-admin-page" aria-hidden="true"></span>
							<span><?php esc_html_e( 'Copy', 'flipset' ); ?></span>
						</button>
					</div>
				</div>
			</section>

			<!-- 4. Deep Linking & Page Anchors -->
			<section id="guide-deeplink" class="flipset-guide-section">
				<div class="flipset-guide-section-head">
					<div class="flipset-guide-sec-icon-wrap">
						<span class="dashicons dashicons-admin-links flipset-guide-icon-sec" aria-hidden="true"></span>
					</div>
					<div>
						<h3><?php esc_html_e( '4. Deep Linking & URL Hashes (#page=X)', 'flipset' ); ?></h3>
						<p class="flipset-guide-subtitle"><?php esc_html_e( 'Send visitors directly to any specific page inside an embedded document by appending hash anchors:', 'flipset' ); ?></p>
					</div>
				</div>
				<div class="flipset-deeplink-grid">
					<div class="flipset-deeplink-box">
						<div class="flipset-deeplink-code">
							<code>https://yourdomain.com/handbook/<strong>#page=14</strong></code>
						</div>
						<p><?php esc_html_e( 'Loads the handbook page and automatically jumps directly to page 14 with smooth animation.', 'flipset' ); ?></p>
					</div>
					<div class="flipset-deeplink-box">
						<div class="flipset-deeplink-code">
							<code>https://yourdomain.com/handbook/<strong>#flipset-page=5</strong></code>
						</div>
						<p><?php esc_html_e( 'Alternative namespaced syntax to avoid conflicting with other on-page anchors or sliders.', 'flipset' ); ?></p>
					</div>
				</div>
			</section>

			<!-- 5. Page Builders Integration -->
			<section id="guide-builders" class="flipset-guide-section">
				<div class="flipset-guide-section-head">
					<div class="flipset-guide-sec-icon-wrap">
						<span class="dashicons dashicons-layout flipset-guide-icon-sec" aria-hidden="true"></span>
					</div>
					<div>
						<h3><?php esc_html_e( '5. Page Builders & Compatibility', 'flipset' ); ?></h3>
						<p class="flipset-guide-subtitle"><?php esc_html_e( 'Native visual blocks and widgets bundled for your favorite WordPress builders:', 'flipset' ); ?></p>
					</div>
				</div>
				<div class="fa-tiles fa-tiles--2">
					<div class="fa-tile fa-tile--col">
						<div class="fa-tile__head">
							<div class="fa-tile-icon is-indigo"><span class="dashicons dashicons-block-default"></span></div>
							<h4><?php esc_html_e( 'Gutenberg Block Editor', 'flipset' ); ?></h4>
						</div>
						<p><?php esc_html_e( 'Search for "Flipset Viewer" or "Flipset Library" in the block inserter. Customize dimensions, starting page, view mode (including 3D FlipBook), and download toggles directly in the block sidebar.', 'flipset' ); ?></p>
					</div>
					<div class="fa-tile fa-tile--col">
						<div class="fa-tile__head">
							<div class="fa-tile-icon is-violet"><span class="dashicons dashicons-art"></span></div>
							<h4><?php esc_html_e( 'Elementor Page Builder', 'flipset' ); ?></h4>
						</div>
						<p><?php esc_html_e( 'Find "Flipset PDF Viewer" and "Flipset PDF Library" widgets in the Elementor panel. Full visual styling controls with zero extra setup.', 'flipset' ); ?></p>
					</div>
					<div class="fa-tile fa-tile--col">
						<div class="fa-tile__head">
							<div class="fa-tile-icon is-sky"><span class="dashicons dashicons-welcome-widgets-menus"></span></div>
							<h4><?php esc_html_e( 'Divi & Beaver Builder', 'flipset' ); ?></h4>
						</div>
						<p><?php esc_html_e( 'Native Divi modules and Beaver Builder modules are bundled right in Flipset. Select your PDF directly inside their builder interfaces.', 'flipset' ); ?></p>
					</div>
					<div class="fa-tile fa-tile--col">
						<div class="fa-tile__head">
							<div class="fa-tile-icon is-green"><span class="dashicons dashicons-translation"></span></div>
							<h4><?php esc_html_e( 'WPML & Polylang Multi-Language', 'flipset' ); ?></h4>
						</div>
						<p><?php esc_html_e( 'When you translate Media Library PDFs, Flipset automatically swaps the document URL for the active visitor language on that page.', 'flipset' ); ?></p>
					</div>
				</div>
			</section>

			<!-- 6. Troubleshooting & FAQs -->
			<section id="guide-faq" class="flipset-guide-section">
				<div class="flipset-guide-section-head">
					<div class="flipset-guide-sec-icon-wrap">
						<span class="dashicons dashicons-sos flipset-guide-icon-sec" aria-hidden="true"></span>
					</div>
					<div>
						<h3><?php esc_html_e( '6. Troubleshooting & Best Practices', 'flipset' ); ?></h3>
						<p class="flipset-guide-subtitle"><?php esc_html_e( 'Answers to frequent configuration questions and hosting best practices:', 'flipset' ); ?></p>
					</div>
				</div>
				<div class="flipset-faq-list">
					<details class="flipset-faq-item" open>
						<summary>
							<span class="flipset-faq-q"><?php esc_html_e( 'How do I resolve CORS (Cross-Origin) errors when loading remote PDFs?', 'flipset' ); ?></span>
							<span class="dashicons dashicons-arrow-down-alt2 flipset-faq-arrow" aria-hidden="true"></span>
						</summary>
						<div class="flipset-faq-answer">
							<p><?php esc_html_e( 'Browsers block scripts from reading PDFs hosted on other domains unless that remote server allows CORS. The easiest fix is uploading the PDF directly to your WordPress Media Library. If you must use a remote host, configure the header Access-Control-Allow-Origin: * on that remote server.', 'flipset' ); ?></p>
						</div>
					</details>
					<details class="flipset-faq-item">
						<summary>
							<span class="flipset-faq-q"><?php esc_html_e( 'Why are PDF.js scripts excluded from cache plugins?', 'flipset' ); ?></span>
							<span class="dashicons dashicons-arrow-down-alt2 flipset-faq-arrow" aria-hidden="true"></span>
						</summary>
						<div class="flipset-faq-answer">
							<p><?php esc_html_e( 'PDF.js requires its worker script (pdf.worker.min.js) to load in an isolated worker thread. Combining, deferring, or minifying it with other theme JS breaks web worker loading. Flipset automatically sets data-no-optimize rules for WP Rocket, LiteSpeed Cache, Autoptimize, and W3 Total Cache.', 'flipset' ); ?></p>
						</div>
					</details>
					<details class="flipset-faq-item">
						<summary>
							<span class="flipset-faq-q"><?php esc_html_e( 'How do thumbnail generation and search indexing work?', 'flipset' ); ?></span>
							<span class="dashicons dashicons-arrow-down-alt2 flipset-faq-arrow" aria-hidden="true"></span>
						</summary>
						<div class="flipset-faq-answer">
							<p><?php esc_html_e( 'The first time an admin visits the Documents Library tab, the browser renders page 1 of each PDF and saves a high-quality cover thumbnail in your Media Library, while simultaneously extracting text so visitors can search for PDF contents using WordPress site search.', 'flipset' ); ?></p>
						</div>
					</details>
				</div>
			</section>
		</div>
		<?php
	}

	/**
	 * Render the Features Showcase tab on the Docs & Features page.
	 */
	public function render_features_tab() {
		$cards = array(
			array(
				'icon'  => 'dashicons-book-alt',
				'tint'  => 'is-indigo',
				'title' => __( 'Reading Modes & 3D FlipBook Engine', 'flipset' ),
				'items' => array(
					array( __( '16-Bone 3D FlipBook:', 'flipset' ), __( 'Ultra-realistic 3D curved page turning with natural paper inertia, dynamic specular lighting, center spine depth shadow, and stacked paper thickness.', 'flipset' ) ),
					array( __( 'Web Audio Paper Sound:', 'flipset' ), __( 'Synthesized real-time page-turn audio feedback (no heavy external mp3 files required).', 'flipset' ) ),
					array( __( 'Multiple Layout Modes:', 'flipset' ), __( 'Single Page, Two-Page Magazine Spread, Continuous Vertical Scroll, and 3D FlipBook.', 'flipset' ) ),
					array( __( 'Glassmorphism Thumbnails Panel:', 'flipset' ), __( 'Slide-out thumbnail card preview strip with instant jump-to-page navigation.', 'flipset' ) ),
					array( __( 'Touch & Mobile Gesture Engine:', 'flipset' ), __( 'Natural swipe-to-flip gestures and hardware-accelerated pinch-to-zoom.', 'flipset' ) ),
					array( __( 'Reading Themes:', 'flipset' ), __( 'Instant Dark, Light, and Sepia eye-care reading themes.', 'flipset' ) ),
				),
			),
			array(
				'icon'  => 'dashicons-admin-customizer',
				'tint'  => 'is-violet',
				'title' => __( 'Shortcode Builder & Saved Embeds', 'flipset' ),
				'items' => array(
					array( __( 'Clean ID Shortcodes [flipset id="1"]:', 'flipset' ), __( 'Encapsulates long attributes into a clean, easy-to-manage ID-based shortcode.', 'flipset' ) ),
					array( __( 'Saved Embeds Manager:', 'flipset' ), __( 'Dedicated manager with instant search, 1-click copy, duplicate, and live editing.', 'flipset' ) ),
					array( __( 'Live Interactive Preview:', 'flipset' ), __( 'Real-time responsive preview (Desktop, Tablet, Mobile) that updates instantly as you tweak settings.', 'flipset' ) ),
					array( __( '1-Click Presets:', 'flipset' ), __( 'One-click configurations for 3D FlipBook, E-Book Reader, Corporate Report, Night Reader, and Speed Optimized.', 'flipset' ) ),
					array( __( 'Developer Raw Code Toggle:', 'flipset' ), __( 'Switch between clean ID shortcodes, raw inline shortcodes, PHP snippets, and Gutenberg block code.', 'flipset' ) ),
				),
			),
			array(
				'icon'  => 'dashicons-layout',
				'tint'  => 'is-sky',
				'title' => __( 'Page Builders & Documents Library', 'flipset' ),
				'items' => array(
					array( __( 'Gutenberg Native Block:', 'flipset' ), __( 'Flipset Viewer block with Media picker & live first-page preview.', 'flipset' ) ),
					array( __( 'Auto oEmbed:', 'flipset' ), __( 'Simply paste any direct .pdf link on a new line to embed instantly.', 'flipset' ) ),
					array( __( 'Page Builders Compatibility:', 'flipset' ), __( 'Native widgets for Elementor, Divi and Beaver Builder; the shortcode works everywhere else.', 'flipset' ) ),
					array( __( 'Documents Library Grid:', 'flipset' ), __( '[flipset_library] shortcode for searchable PDF galleries.', 'flipset' ) ),
					array( __( 'Attachment Pages:', 'flipset' ), __( 'Automatic interactive reader on standard WordPress attachment pages.', 'flipset' ) ),
				),
			),
			array(
				'icon'  => 'dashicons-search',
				'tint'  => 'is-green',
				'title' => __( 'SEO, Search & Accessibility', 'flipset' ),
				'items' => array(
					array( __( 'Site Search Indexing:', 'flipset' ), __( 'Extracts PDF text so WordPress search finds content inside documents.', 'flipset' ) ),
					array( __( 'Schema.org JSON-LD:', 'flipset' ), __( 'Automatic DigitalDocument rich snippets for Google search results.', 'flipset' ) ),
					array( __( 'Social Card Previews:', 'flipset' ), __( 'Open Graph & Twitter Cards cover preview generated from the PDF.', 'flipset' ) ),
					array( __( 'Accessibility Diagnostics:', 'flipset' ), __( 'Detects untagged PDFs and tells you how to export an accessible one.', 'flipset' ) ),
					array( __( 'Zero CDN Dependency:', 'flipset' ), __( 'Bundled locally hosted PDF.js, no third-party tracking or remote downtime.', 'flipset' ) ),
				),
			),
		);
		?>
		<div class="flipset-features-showcase">
			<section class="fa-card fa-features-intro">
				<div class="fa-card__head">
					<span class="fa-card__icon dashicons dashicons-star-filled" aria-hidden="true"></span>
					<div class="fa-card__titles">
						<h2 class="fa-card__title"><?php esc_html_e( 'Everything Flipset Can Do', 'flipset' ); ?></h2>
						<p class="fa-card__sub"><?php esc_html_e( 'A fast, accessible and locally rendered PDF reading experience with no external dependencies, plus builder widgets and SEO integration.', 'flipset' ); ?></p>
					</div>
				</div>
				<div class="fa-card__body fa-stats">
					<span class="fa-stat"><strong>4</strong> <?php esc_html_e( 'reading modes', 'flipset' ); ?></span>
					<span class="fa-stat"><strong>Gutenberg · Elementor · Divi · Beaver</strong></span>
					<span class="fa-stat"><strong>0</strong> <?php esc_html_e( 'external requests', 'flipset' ); ?></span>
				</div>
			</section>

			<div class="fa-feature-grid">
				<?php foreach ( $cards as $card ) : ?>
					<section class="fa-card fa-feature">
						<div class="fa-card__head">
							<span class="fa-tile-icon <?php echo esc_attr( $card['tint'] ); ?>"><span class="dashicons <?php echo esc_attr( $card['icon'] ); ?>" aria-hidden="true"></span></span>
							<div class="fa-card__titles">
								<h3 class="fa-card__title"><?php echo esc_html( $card['title'] ); ?></h3>
							</div>
							<span class="fa-tag fa-tag--free"><?php esc_html_e( 'Free', 'flipset' ); ?></span>
						</div>
						<ul class="fa-feature__list">
							<?php foreach ( $card['items'] as $item ) : ?>
								<li>
									<span class="fa-feature__check dashicons dashicons-yes" aria-hidden="true"></span>
									<span><strong><?php echo esc_html( $item[0] ); ?></strong> <?php echo esc_html( $item[1] ); ?></span>
								</li>
							<?php endforeach; ?>
						</ul>
					</section>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	public function ajax_mark_setup_copied() {
		check_ajax_referer( 'flipset_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden.', 'flipset' ) ), 403 );
		}

		$settings = get_option( self::OPTION_NAME, array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}
		$settings['setup_copied_shortcode'] = true;
		update_option( self::OPTION_NAME, $settings );

		wp_send_json_success();
	}

	public function ajax_create_test_page() {
		check_ajax_referer( 'flipset_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden.', 'flipset' ) ), 403 );
		}

		$file = isset( $_POST['file'] ) ? esc_url_raw( wp_unslash( $_POST['file'] ) ) : '';
		if ( '' === $file ) {
			wp_send_json_error( array( 'message' => __( 'Select a PDF first.', 'flipset' ) ) );
		}

		// Ensure the URL resolves to a PDF attachment in this site's Media Library
		// so an arbitrary external URL cannot be published via this AJAX endpoint.
		$attachment_id = Flipset_Compat::attachment_id_from_url( $file );
		if ( ! $attachment_id ) {
			wp_send_json_error( array( 'message' => __( 'Only PDFs from the Media Library can be used here.', 'flipset' ) ) );
		}
		$mime = get_post_mime_type( $attachment_id );
		if ( 'application/pdf' !== $mime ) {
			wp_send_json_error( array( 'message' => __( 'That file is not a PDF.', 'flipset' ) ) );
		}

		$shortcode = isset( $_POST['shortcode'] ) ? sanitize_text_field( wp_unslash( $_POST['shortcode'] ) ) : '';
		$content   = ( '' !== $shortcode ) ? $shortcode : '[flipset file="' . $file . '"]';
		$existing  = get_page_by_path( 'flipset-test' );

		if ( $existing ) {
			$result = wp_update_post(
				array(
					'ID'           => $existing->ID,
					'post_content' => $content,
					'post_status'  => 'publish',
				),
				true
			);
			if ( is_wp_error( $result ) || ! $result ) {
				wp_send_json_error( array( 'message' => is_wp_error( $result ) ? $result->get_error_message() : __( 'Could not update the test page.', 'flipset' ) ) );
			}
			$page_id = $existing->ID;
		} else {
			$page_id = wp_insert_post(
				array(
					'post_type'    => 'page',
					'post_status'  => 'publish',
					'post_title'   => __( 'Flipset Test', 'flipset' ),
					'post_name'    => 'flipset-test',
					'post_content' => $content,
				),
				true
			);
			if ( is_wp_error( $page_id ) ) {
				wp_send_json_error( array( 'message' => $page_id->get_error_message() ) );
			}
		}

		wp_send_json_success(
			array(
				'id'  => $page_id,
				'url' => get_permalink( $page_id ),
			)
		);
	}

	public function ajax_dismiss_welcome() {
		check_ajax_referer( 'flipset_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden.', 'flipset' ) ), 403 );
		}

		update_user_meta( get_current_user_id(), 'flipset_welcome_dismissed', 1 );
		wp_send_json_success();
	}

	private function get_latest_pdf_url() {
		$query = new WP_Query(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'post_mime_type' => 'application/pdf',
				'posts_per_page' => 1,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);
		if ( empty( $query->posts ) ) {
			return '';
		}
		$url = wp_get_attachment_url( (int) $query->posts[0] );
		return $url ? $url : '';
	}

	/**
	 * Most recently uploaded PDFs for the dashboard picker.
	 *
	 * @param int $limit Number of attachments.
	 * @return array<int,array{id:int,url:string,title:string,thumb:string}>
	 */
	private function get_recent_pdfs( $limit = 4 ) {
		$query = new WP_Query(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'post_mime_type' => 'application/pdf',
				'posts_per_page' => max( 1, absint( $limit ) ),
				'orderby'        => 'date',
				'order'          => 'DESC',
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		$items = array();
		foreach ( $query->posts as $id ) {
			$id  = (int) $id;
			$url = wp_get_attachment_url( $id );
			if ( ! $url ) {
				continue;
			}
			$title = get_the_title( $id );
			if ( '' === $title ) {
				$title = wp_basename( $url );
			}
			$items[] = array(
				'id'    => $id,
				'url'   => $url,
				'title' => $title,
				'thumb' => Flipset_Thumbnails::get_thumbnail_url( $id, 'thumbnail' ),
			);
		}

		return $items;
	}

	private function count_pdfs() {
		// wp_count_attachments() is keyed by MIME type (plus 'trash'), not by
		// post_status, so it can't be read with ->inherit. A direct query also
		// avoids a cached value going stale when a PDF is added or deleted.
		$query = new WP_Query(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'post_mime_type' => 'application/pdf',
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);
		return (int) $query->found_posts;
	}

	private function count_embeds() {
		$cached = wp_cache_get( 'embed_count', 'flipset' );
		if ( false !== $cached ) {
			return (int) $cached;
		}

		global $wpdb;

		$like_shortcode = '%' . $wpdb->esc_like( '[flipset' ) . '%';
		$like_block     = '%' . $wpdb->esc_like( 'wp:flipset/' ) . '%';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- LIKE across post_content is not available via WP_Query; result is cached.
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_status IN ('publish','draft','private') AND post_type IN ('post','page') AND (post_content LIKE %s OR post_content LIKE %s)",
				$like_shortcode,
				$like_block
			)
		);

		wp_cache_set( 'embed_count', $count, 'flipset', 5 * MINUTE_IN_SECONDS );

		return $count;
	}
}
