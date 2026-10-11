<?php
/**
 * Foliora_Admin
 *
 * Top-level Foliora menu: Dashboard (home) + Settings. Dashboard is
 * the interactive shell — setup checklist and embed builder. Settings still owns Viewer Defaults via the Settings
 * API (PAGE_SLUG), so Foliora Pro can keep adding fields there.
 *
 * PAGE_SLUG stays `foliora-settings` because Pro's Protected Links
 * submenu and settings sections parent off it.
 *
 * @package Foliora
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Foliora_Admin {

	/**
	 * Settings API option group for this screen. Add-ons (Foliora Pro)
	 * must register_setting() against this same group so the single
	 * settings_fields() call in render_settings_page() covers every
	 * option on the page. Store add-on data under a *different* option
	 * name — this class's sanitize_settings() only ever writes
	 * OPTION_NAME and must not see or clobber add-on options.
	 */
	const OPTION_GROUP  = 'foliora_settings_group';
	const OPTION_NAME   = 'foliora_settings';
	const PAGE_SLUG     = 'foliora-settings';
	const BUILDER_SLUG  = 'foliora-shortcode-builder';
	const SETTINGS_SLUG = 'foliora-viewer-settings';
	const DOCS_SLUG     = 'foliora-documents';

	/**
	 * Hidden field that only the Settings screen form posts. Checkboxes are
	 * absent from a POST when unticked, so the sanitizer needs an explicit
	 * signal to tell "user unticked it" from "this update didn't mention it".
	 */
	const FORM_MARKER = '_foliora_form';

	public function init() {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'wp_ajax_foliora_mark_setup_copied', array( $this, 'ajax_mark_setup_copied' ) );
		add_action( 'wp_ajax_foliora_create_test_page', array( $this, 'ajax_create_test_page' ) );
		add_action( 'wp_ajax_foliora_dismiss_welcome', array( $this, 'ajax_dismiss_welcome' ) );
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
		wp_cache_delete( 'embed_count', 'foliora' );
	}

	public function add_settings_page() {
		add_menu_page(
			__( 'Foliora', 'foliora' ),
			__( 'Foliora', 'foliora' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_dashboard' ),
			'dashicons-media-document',
			58
		);

		add_submenu_page(
			self::PAGE_SLUG,
			__( 'Foliora Dashboard', 'foliora' ),
			__( 'Dashboard', 'foliora' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_dashboard' )
		);

		add_submenu_page(
			self::PAGE_SLUG,
			__( 'Foliora Shortcode Builder', 'foliora' ),
			__( 'Shortcode Builder', 'foliora' ),
			'manage_options',
			self::BUILDER_SLUG,
			array( $this, 'render_builder_page' )
		);

		add_submenu_page(
			self::PAGE_SLUG,
			__( 'Foliora Docs & Features', 'foliora' ),
			__( 'Docs & Features', 'foliora' ),
			'manage_options',
			self::DOCS_SLUG,
			array( $this, 'render_documents_page' )
		);

		add_submenu_page(
			self::PAGE_SLUG,
			__( 'Foliora Settings', 'foliora' ),
			__( 'Settings', 'foliora' ),
			'manage_options',
			self::SETTINGS_SLUG,
			array( $this, 'render_settings_page' )
		);
	}

	public function enqueue_admin_assets( $hook ) {
		$page         = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- admin screen routing.
		$is_dashboard = ( self::PAGE_SLUG === $page || 'toplevel_page_' . self::PAGE_SLUG === $hook );
		$is_builder   = ( self::BUILDER_SLUG === $page || self::PAGE_SLUG . '_page_' . self::BUILDER_SLUG === $hook || 'foliora_page_' . self::BUILDER_SLUG === $hook );
		$is_settings  = ( self::SETTINGS_SLUG === $page || self::PAGE_SLUG . '_page_' . self::SETTINGS_SLUG === $hook );
		$is_docs      = ( self::DOCS_SLUG === $page || self::PAGE_SLUG . '_page_' . self::DOCS_SLUG === $hook || 'foliora_page_' . self::DOCS_SLUG === $hook );

		if ( ! $is_dashboard && ! $is_builder && ! $is_settings && ! $is_docs ) {
			return;
		}

		// Shared admin design system: tokens + fa-* components, used by every Foliora screen.
		wp_enqueue_style(
			'foliora-admin-ui',
			FOLIORA_URL . 'assets/css/foliora-admin-ui.css',
			array(),
			FOLIORA_VERSION
		);

		wp_enqueue_style(
			'foliora-admin',
			FOLIORA_URL . 'assets/css/foliora-admin.css',
			array( 'foliora-admin-ui' ),
			FOLIORA_VERSION
		);

		if ( $is_dashboard ) {
			wp_enqueue_style(
				'foliora-dashboard',
				FOLIORA_URL . 'assets/css/foliora-dashboard.css',
				array( 'foliora-admin-ui' ),
				FOLIORA_VERSION
			);
		}

		if ( $is_docs ) {
			wp_enqueue_style(
				'foliora-docs',
				FOLIORA_URL . 'assets/css/foliora-docs.css',
				array( 'foliora-admin-ui' ),
				FOLIORA_VERSION
			);
		}

		if ( $is_settings || $is_builder ) {
			wp_enqueue_style(
				'foliora-viewer',
				FOLIORA_URL . 'assets/css/foliora-viewer.css',
				array(),
				FOLIORA_VERSION
			);
			Foliora_Viewer::register_pdfjs();
			wp_enqueue_script( 'foliora-pdfjs' );
			wp_enqueue_script(
				'foliora-viewer',
				FOLIORA_URL . 'assets/js/foliora-viewer.js',
				array( 'foliora-pdfjs' ),
				FOLIORA_VERSION,
				true
			);
			wp_localize_script(
				'foliora-viewer',
				'FolioraConfig',
				array(
					'workerSrc' => FOLIORA_URL . 'assets/vendor/pdfjs/pdf.worker.min.js',
					'isPro'     => Foliora_Loader::is_pro_active(),
					'i18n'      => Foliora_Viewer::viewer_i18n(),
				)
			);
		}

		if ( $is_docs && current_user_can( 'upload_files' ) ) {
			Foliora_Viewer::register_pdfjs();
			wp_enqueue_script( 'foliora-pdfjs' );
			wp_enqueue_script(
				'foliora-thumbnail-gen',
				FOLIORA_URL . 'assets/js/foliora-thumbnail-gen.js',
				array( 'foliora-pdfjs' ),
				FOLIORA_VERSION,
				true
			);
			wp_localize_script(
				'foliora-thumbnail-gen',
				'FolioraThumbGen',
				array(
					'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
					'nonce'     => wp_create_nonce( 'foliora_admin' ),
					'workerSrc' => FOLIORA_URL . 'assets/vendor/pdfjs/pdf.worker.min.js',
					'maxPages'  => 40,
					'maxChars'  => 80000,
					'i18n'      => array(
						'indexed'      => __( 'Indexed', 'foliora' ),
						'noText'       => __( 'No text', 'foliora' ),
						'tagged'       => __( 'Tagged', 'foliora' ),
						'untagged'     => __( 'Untagged', 'foliora' ),
						'untaggedHint' => __( 'This PDF has no accessibility tags. Export it from Word, InDesign, or Acrobat as a tagged PDF so screen readers can follow headings and reading order.', 'foliora' ),
						'taggedHint'   => __( 'Tagged PDF. Screen readers can follow its structure.', 'foliora' ),
						'indexedHint'  => __( 'Text extracted. WordPress search can find words in this PDF.', 'foliora' ),
						'noTextHint'   => __( 'No selectable text was found (a scanned image PDF), so it cannot be searched.', 'foliora' ),
					),
				)
			);
		}

		if ( $is_dashboard || $is_docs || $is_builder ) {
			wp_enqueue_media();
		}

		wp_enqueue_script(
			'foliora-admin',
			FOLIORA_URL . 'assets/js/foliora-admin.js',
			array(),
			FOLIORA_VERSION,
			true
		);

		wp_localize_script(
			'foliora-admin',
			'FolioraAdmin',
			array(
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( 'foliora_admin' ),
				'i18n'        => array(
					'selectPdf'       => __( 'Select PDF', 'foliora' ),
					'usePdf'          => __( 'Use this PDF', 'foliora' ),
					'copied'          => __( 'Copied!', 'foliora' ),
					'copiedToast'     => __( 'Shortcode copied', 'foliora' ),
					'codeCopied'      => __( 'Code snippet copied to clipboard', 'foliora' ),
					'dismissed'       => __( 'Dismissed', 'foliora' ),
					/* translators: 1: completed steps, 2: total steps. */
					'stepsComplete'   => __( '%1$s of %2$s steps complete', 'foliora' ),
					'setupDone'       => __( 'You are set. New documents go in Documents or the Media Library.', 'foliora' ),
					'dropHint'        => __( 'Drop a PDF to embed it', 'foliora' ),
					'uploading'       => __( 'Uploading…', 'foliora' ),
					'creating'        => __( 'Creating page…', 'foliora' ),
					'testPageReady'   => __( 'Test page ready', 'foliora' ),
					'createFail'      => __( 'Could not create the test page.', 'foliora' ),
					'copyShortcode'   => __( 'Copy shortcode', 'foliora' ),
					'notPdf'          => __( 'Please drop a PDF file.', 'foliora' ),
					'uploadFail'      => __( 'Upload failed. Try the Media Library.', 'foliora' ),
					'presetApplied'   => __( 'Preset applied', 'foliora' ),
					'selectPdfFirst'  => __( 'Please select a PDF file.', 'foliora' ),
					'previewEmpty'    => __( 'Select or upload a PDF above to see live interactive preview.', 'foliora' ),
					'saving'          => __( 'Saving…', 'foliora' ),
					'updateEmbed'     => __( 'Update Embed & Copy', 'foliora' ),
					'savedCopiedBtn'  => __( '✓ Saved! Copied', 'foliora' ),
					/* translators: %s: shortcode that was copied. */
					'toastSaved'      => __( 'Saved! %s copied to clipboard.', 'foliora' ),
					'saveFailed'      => __( 'Could not save embed.', 'foliora' ),
					'testPageFailed'  => __( 'Could not create test page.', 'foliora' ),
					/* translators: %s: text that was copied. */
					'copiedItem'      => __( 'Copied: %s', 'foliora' ),
					'embedDuplicated' => __( 'Embed duplicated successfully.', 'foliora' ),
					'duplicateFailed' => __( 'Could not duplicate embed.', 'foliora' ),
					/* translators: %s: embed ID. */
					'confirmDelete'   => __( 'Are you sure you want to delete this embed? Any shortcode using id="%s" will no longer work.', 'foliora' ),
					'embedDeleted'    => __( 'Embed deleted.', 'foliora' ),
					'deleteFailed'    => __( 'Could not delete embed.', 'foliora' ),
				),
				'uploadNonce' => wp_create_nonce( 'media-form' ),
				'canUpload'   => current_user_can( 'upload_files' ),
			)
		);
	}

	public function register_settings() {
		register_setting( self::OPTION_GROUP, self::OPTION_NAME, array( $this, 'sanitize_settings' ) );

		add_settings_section(
			'foliora_general',
			__( 'Viewer Defaults', 'foliora' ),
			'__return_false',
			self::PAGE_SLUG
		);

		add_settings_field(
			'default_width',
			__( 'Default width', 'foliora' ),
			array( $this, 'field_default_width' ),
			self::PAGE_SLUG,
			'foliora_general'
		);

		add_settings_field(
			'default_height',
			__( 'Default height', 'foliora' ),
			array( $this, 'field_default_height' ),
			self::PAGE_SLUG,
			'foliora_general'
		);

		add_settings_field(
			'allow_download',
			__( 'Download button', 'foliora' ),
			array( $this, 'field_allow_download' ),
			self::PAGE_SLUG,
			'foliora_general'
		);

		add_settings_field(
			'allow_print',
			__( 'Print button', 'foliora' ),
			array( $this, 'field_allow_print' ),
			self::PAGE_SLUG,
			'foliora_general'
		);

		add_settings_field(
			'default_theme',
			__( 'Default theme', 'foliora' ),
			array( $this, 'field_default_theme' ),
			self::PAGE_SLUG,
			'foliora_general'
		);

		add_settings_field(
			'lazy_loading',
			__( 'Lazy loading', 'foliora' ),
			array( $this, 'field_lazy_loading' ),
			self::PAGE_SLUG,
			'foliora_general'
		);

		add_settings_field(
			'show_branding',
			__( 'Credit link', 'foliora' ),
			array( $this, 'field_show_branding' ),
			self::PAGE_SLUG,
			'foliora_general'
		);

		add_settings_section(
			'foliora_toolbar',
			__( 'Toolbar Controls', 'foliora' ),
			array( $this, 'section_toolbar' ),
			self::PAGE_SLUG
		);

		add_settings_field(
			'allow_search',
			__( 'Search in document', 'foliora' ),
			array( $this, 'field_allow_search' ),
			self::PAGE_SLUG,
			'foliora_toolbar'
		);

		add_settings_field(
			'allow_theme',
			__( 'Theme switcher', 'foliora' ),
			array( $this, 'field_allow_theme' ),
			self::PAGE_SLUG,
			'foliora_toolbar'
		);

		add_settings_field(
			'allow_share',
			__( 'Share button', 'foliora' ),
			array( $this, 'field_allow_share' ),
			self::PAGE_SLUG,
			'foliora_toolbar'
		);

		add_settings_field(
			'allow_shortcuts',
			__( 'Keyboard shortcuts guide', 'foliora' ),
			array( $this, 'field_allow_shortcuts' ),
			self::PAGE_SLUG,
			'foliora_toolbar'
		);

		add_settings_field(
			'allow_fullscreen',
			__( 'Full screen button', 'foliora' ),
			array( $this, 'field_allow_fullscreen' ),
			self::PAGE_SLUG,
			'foliora_toolbar'
		);

		add_settings_field(
			'allow_presentation',
			__( 'Presentation button', 'foliora' ),
			array( $this, 'field_allow_presentation' ),
			self::PAGE_SLUG,
			'foliora_toolbar'
		);

		add_settings_section(
			'foliora_discover',
			__( 'Search and sharing', 'foliora' ),
			array( $this, 'section_discover' ),
			self::PAGE_SLUG
		);

		add_settings_field(
			'index_pdf_text',
			__( 'Site search', 'foliora' ),
			array( $this, 'field_index_pdf_text' ),
			self::PAGE_SLUG,
			'foliora_discover'
		);

		add_settings_field(
			'social_preview',
			__( 'Social preview', 'foliora' ),
			array( $this, 'field_social_preview' ),
			self::PAGE_SLUG,
			'foliora_discover'
		);

		add_settings_field(
			'check_pdf_a11y',
			__( 'Accessibility tags', 'foliora' ),
			array( $this, 'field_check_pdf_a11y' ),
			self::PAGE_SLUG,
			'foliora_discover'
		);

		add_settings_field(
			'autoembed_url',
			__( 'Direct PDF auto-embed', 'foliora' ),
			array( $this, 'field_autoembed_url' ),
			self::PAGE_SLUG,
			'foliora_discover'
		);

		add_settings_field(
			'embed_attachment_pages',
			__( 'Attachment pages', 'foliora' ),
			array( $this, 'field_embed_attachment_pages' ),
			self::PAGE_SLUG,
			'foliora_discover'
		);
	}

	/**
	 * Bound only to OPTION_NAME (`foliora_settings`). Returning a fresh
	 * array of free-plugin keys means extra POST keys cannot persist
	 * here, and a separately registered option such as
	 * `foliora_pro_settings` is never read or written.
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

		$flag_defaults = array(
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
		echo '<p class="description">' . esc_html__( 'Enable or disable specific buttons across all embedded PDF viewers. Individual embeds can also customize controls via the "hide" attribute.', 'foliora' ) . '</p>';
	}

	public function field_default_theme() {
		$settings = get_option( self::OPTION_NAME, array() );
		$current  = $settings['default_theme'] ?? 'light';
		?>
		<select name="<?php echo esc_attr( self::OPTION_NAME ); ?>[default_theme]" id="foliora-default-theme">
			<option value="light" <?php selected( $current, 'light' ); ?>><?php esc_html_e( 'Light (Default)', 'foliora' ); ?></option>
			<option value="dark" <?php selected( $current, 'dark' ); ?>><?php esc_html_e( 'Dark', 'foliora' ); ?></option>
			<option value="sepia" <?php selected( $current, 'sepia' ); ?>><?php esc_html_e( 'Sepia (Warm)', 'foliora' ); ?></option>
		</select>
		<p class="description"><?php esc_html_e( 'Default reader color scheme for visitors.', 'foliora' ); ?></p>
		<?php
	}

	/**
	 * Opt-in checkbox for the "Powered by Foliora" credit link (off by default).
	 */
	public function field_show_branding() {
		$settings = get_option( self::OPTION_NAME, array() );
		$checked  = ! empty( $settings['show_branding'] );
		printf(
			'<label><input type="checkbox" name="%1$s[show_branding]" value="1" %2$s /> %3$s</label>',
			esc_attr( self::OPTION_NAME ),
			checked( $checked, true, false ),
			esc_html__( 'Show a small “Powered by Foliora” link under each viewer. Off by default; turn it on if you would like to support the plugin.', 'foliora' )
		);
	}

	public function field_lazy_loading() {
		$settings = get_option( self::OPTION_NAME, array() );
		$checked  = ! empty( $settings['lazy_loading'] );
		printf(
			'<label><input type="checkbox" name="%1$s[lazy_loading]" value="1" %2$s /> %3$s</label>',
			esc_attr( self::OPTION_NAME ),
			checked( $checked, true, false ),
			esc_html__( 'Only load PDF documents when they scroll into the visitor viewport (faster initial page load).', 'foliora' )
		);
	}

	public function field_allow_search() {
		$settings = get_option( self::OPTION_NAME, array() );
		$checked  = ! isset( $settings['allow_search'] ) || ! empty( $settings['allow_search'] );
		printf(
			'<label><input type="checkbox" name="%1$s[allow_search]" value="1" %2$s /> %3$s</label>',
			esc_attr( self::OPTION_NAME ),
			checked( $checked, true, false ),
			esc_html__( 'Show the search/find input on the toolbar (or hide via hide="search").', 'foliora' )
		);
	}

	public function field_allow_theme() {
		$settings = get_option( self::OPTION_NAME, array() );
		$checked  = ! isset( $settings['allow_theme'] ) || ! empty( $settings['allow_theme'] );
		printf(
			'<label><input type="checkbox" name="%1$s[allow_theme]" value="1" %2$s /> %3$s</label>',
			esc_attr( self::OPTION_NAME ),
			checked( $checked, true, false ),
			esc_html__( 'Show the Dark / Light / Sepia theme toggle button.', 'foliora' )
		);
	}

	public function field_allow_share() {
		$settings = get_option( self::OPTION_NAME, array() );
		$checked  = ! isset( $settings['allow_share'] ) || ! empty( $settings['allow_share'] );
		printf(
			'<label><input type="checkbox" name="%1$s[allow_share]" value="1" %2$s /> %3$s</label>',
			esc_attr( self::OPTION_NAME ),
			checked( $checked, true, false ),
			esc_html__( 'Show the share and copy page link button.', 'foliora' )
		);
	}

	public function field_allow_shortcuts() {
		$settings = get_option( self::OPTION_NAME, array() );
		$checked  = ! isset( $settings['allow_shortcuts'] ) || ! empty( $settings['allow_shortcuts'] );
		printf(
			'<label><input type="checkbox" name="%1$s[allow_shortcuts]" value="1" %2$s /> %3$s</label>',
			esc_attr( self::OPTION_NAME ),
			checked( $checked, true, false ),
			esc_html__( 'Show the keyboard shortcuts guide button (?) and enable keyboard navigation.', 'foliora' )
		);
	}

	public function field_allow_fullscreen() {
		$settings = get_option( self::OPTION_NAME, array() );
		$checked  = ! isset( $settings['allow_fullscreen'] ) || ! empty( $settings['allow_fullscreen'] );
		printf(
			'<label><input type="checkbox" name="%1$s[allow_fullscreen]" value="1" %2$s /> %3$s</label>',
			esc_attr( self::OPTION_NAME ),
			checked( $checked, true, false ),
			esc_html__( 'Show the full screen toggle button.', 'foliora' )
		);
	}

	public function field_allow_presentation() {
		$settings = get_option( self::OPTION_NAME, array() );
		$checked  = ! isset( $settings['allow_presentation'] ) || ! empty( $settings['allow_presentation'] );
		printf(
			'<label><input type="checkbox" name="%1$s[allow_presentation]" value="1" %2$s /> %3$s</label>',
			esc_attr( self::OPTION_NAME ),
			checked( $checked, true, false ),
			esc_html__( 'Show the distraction-free presentation mode button.', 'foliora' )
		);
	}

	public function field_default_width() {
		$settings = get_option( self::OPTION_NAME, array() );
		printf(
			'<input type="text" id="foliora-default-width" name="%1$s[default_width]" value="%2$s" class="regular-text" placeholder="100%%" />',
			esc_attr( self::OPTION_NAME ),
			esc_attr( $settings['default_width'] ?? '100%' )
		);
	}

	public function field_default_height() {
		$settings = get_option( self::OPTION_NAME, array() );
		printf(
			'<input type="text" id="foliora-default-height" name="%1$s[default_height]" value="%2$s" class="regular-text" placeholder="600px" />',
			esc_attr( self::OPTION_NAME ),
			esc_attr( $settings['default_height'] ?? '600px' )
		);
	}

	public function field_allow_download() {
		$settings = get_option( self::OPTION_NAME, array() );
		$checked  = ! isset( $settings['allow_download'] ) || ! empty( $settings['allow_download'] );
		printf(
			'<label><input type="checkbox" name="%1$s[allow_download]" value="1" %2$s /> %3$s</label>',
			esc_attr( self::OPTION_NAME ),
			checked( $checked, true, false ),
			esc_html__( 'Show the download button on the viewer toolbar. Individual embeds can override this with download="false".', 'foliora' )
		);
	}

	public function field_allow_print() {
		$settings = get_option( self::OPTION_NAME, array() );
		$checked  = ! isset( $settings['allow_print'] ) || ! empty( $settings['allow_print'] );
		printf(
			'<label><input type="checkbox" name="%1$s[allow_print]" value="1" %2$s /> %3$s</label>',
			esc_attr( self::OPTION_NAME ),
			checked( $checked, true, false ),
			esc_html__( 'Show the print button on the viewer toolbar. Individual embeds can override this with print="false".', 'foliora' )
		);
	}

	public function section_discover() {
		echo '<p class="description">' . esc_html__( 'Make embedded PDFs findable in WordPress search and nicer when a page is shared.', 'foliora' ) . '</p>';
	}

	public function field_index_pdf_text() {
		$settings = get_option( self::OPTION_NAME, array() );
		$checked  = ! isset( $settings['index_pdf_text'] ) || ! empty( $settings['index_pdf_text'] );
		printf(
			'<label><input type="checkbox" name="%1$s[index_pdf_text]" value="1" %2$s /> %3$s</label>',
			esc_attr( self::OPTION_NAME ),
			checked( $checked, true, false ),
			esc_html__( 'Include text from embedded PDFs in WordPress site search (and in the document library search). Visit Foliora → Documents to extract text.', 'foliora' )
		);
	}

	public function field_social_preview() {
		$settings = get_option( self::OPTION_NAME, array() );
		$checked  = ! isset( $settings['social_preview'] ) || ! empty( $settings['social_preview'] );
		printf(
			'<label><input type="checkbox" name="%1$s[social_preview]" value="1" %2$s /> %3$s</label>',
			esc_attr( self::OPTION_NAME ),
			checked( $checked, true, false ),
			esc_html__( 'Use the PDF first-page thumbnail as the Open Graph / Twitter image when a post or page embeds Foliora and no SEO plugin already set an image.', 'foliora' )
		);
	}

	public function field_check_pdf_a11y() {
		$settings = get_option( self::OPTION_NAME, array() );
		$checked  = ! isset( $settings['check_pdf_a11y'] ) || ! empty( $settings['check_pdf_a11y'] );
		printf(
			'<label><input type="checkbox" name="%1$s[check_pdf_a11y]" value="1" %2$s /> %3$s</label>',
			esc_attr( self::OPTION_NAME ),
			checked( $checked, true, false ),
			esc_html__( 'Warn on Foliora → Documents when a PDF is not tagged. Tagged PDFs include structure (headings, reading order) that screen readers can follow.', 'foliora' )
		);
	}

	public function field_autoembed_url() {
		$settings = get_option( self::OPTION_NAME, array() );
		$checked  = ! isset( $settings['autoembed_url'] ) || ! empty( $settings['autoembed_url'] );
		printf(
			'<label><input type="checkbox" name="%1$s[autoembed_url]" value="1" %2$s /> %3$s</label>',
			esc_attr( self::OPTION_NAME ),
			checked( $checked, true, false ),
			esc_html__( 'Automatically convert standalone .pdf URLs pasted on their own line into an interactive Foliora viewer.', 'foliora' )
		);
	}

	public function field_embed_attachment_pages() {
		$settings = get_option( self::OPTION_NAME, array() );
		$checked  = ! isset( $settings['embed_attachment_pages'] ) || ! empty( $settings['embed_attachment_pages'] );
		printf(
			'<label><input type="checkbox" name="%1$s[embed_attachment_pages]" value="1" %2$s /> %3$s</label>',
			esc_attr( self::OPTION_NAME ),
			checked( $checked, true, false ),
			esc_html__( 'Automatically display the interactive Foliora PDF viewer on standard WordPress PDF attachment pages.', 'foliora' )
		);
	}

	public function render_dashboard() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$pretty       = (bool) get_option( 'permalink_structure' );
		$pdf_count    = $this->count_pdfs();
		$page_count   = $this->count_embeds();
		$saved_embeds = Foliora_Embeds::get_all();
		$saved_count  = count( $saved_embeds );
		$recent_saved = array_slice( $saved_embeds, 0, 5 );
		$settings     = get_option( self::OPTION_NAME, array() );
		$copied       = ! empty( $settings['setup_copied_shortcode'] );
		$recent       = $this->get_recent_pdfs( 4 );
		$prefill      = ! empty( $recent[0] ) ? $recent[0] : null;
		$prefill_url  = $prefill ? $prefill['url'] : '';
		$prefill_code = $prefill_url ? sprintf( '[foliora file="%s"]', esc_url_raw( $prefill_url ) ) : '';

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
		$show_welcome = ! $setup_done && ! get_user_meta( get_current_user_id(), 'foliora_welcome_dismissed', true );
		$title_attr   = $prefill ? $prefill['title'] : '';
		?>
		<div class="wrap foliora-admin fa-dash">
			<div class="fa-hero">
				<div class="fa-hero__top">
					<div class="fa-hero__copy">
						<div class="fa-hero__kicker">
							<span class="fa-mark dashicons dashicons-book-alt" aria-hidden="true"></span>
							<h1 class="fa-hero__title"><?php esc_html_e( 'Foliora', 'foliora' ); ?></h1>
						</div>
						<p class="fa-hero__lede">
							<?php esc_html_e( 'Embed PDFs with a bundled viewer. No CDN, no account, no API keys.', 'foliora' ); ?>
						</p>
						<?php if ( ! $pretty ) : ?>
							<span class="fa-chip fa-chip--warn"><?php esc_html_e( 'Plain permalinks — share links need Post name', 'foliora' ); ?></span>
						<?php endif; ?>
					</div>
					<div class="fa-hero__side">
						<div class="fa-ring<?php echo $setup_done ? ' is-complete' : ''; ?>" id="foliora-ring-wrap">
							<svg class="fa-ring__svg" viewBox="0 0 36 36" aria-hidden="true" focusable="false">
								<defs>
									<linearGradient id="fa-ring-grad" x1="0" y1="0" x2="1" y2="1">
										<stop offset="0" style="stop-color:var(--fb-accent)" />
										<stop offset="1" style="stop-color:var(--fb-accent-2)" />
									</linearGradient>
								</defs>
								<circle class="fa-ring__track" cx="18" cy="18" r="15.5" pathLength="100" />
								<circle class="fa-ring__fill" id="foliora-ring-fg" cx="18" cy="18" r="15.5" pathLength="100" stroke-dasharray="<?php echo esc_attr( (string) $percent ); ?> 100" />
							</svg>
							<span class="fa-ring__label" id="foliora-ring-label"><?php echo esc_html( $done . '/' . $total_steps ); ?></span>
						</div>
						<div class="fa-hero__actions">
							<a class="fa-btn fa-btn--primary" href="<?php echo esc_url( $builder_url ); ?>">
								<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
								<?php esc_html_e( 'New Embed', 'foliora' ); ?>
							</a>
							<a class="fa-btn fa-btn--ghost-dark" href="<?php echo esc_url( $docs_url ); ?>">
								<span class="dashicons dashicons-media-document" aria-hidden="true"></span>
								<?php esc_html_e( 'Documents', 'foliora' ); ?>
							</a>
						</div>
					</div>
				</div>
				<div class="fa-hero__divider" role="presentation"></div>
				<div class="fa-hero__quick">
					<span class="fa-hero__label"><?php esc_html_e( 'Quick actions:', 'foliora' ); ?></span>
					<div class="fa-hero__chips">
						<button type="button" class="fa-chip" id="fa-upload-trigger">
							<span aria-hidden="true">📤</span> <?php esc_html_e( 'Upload PDF', 'foliora' ); ?>
						</button>
						<input type="file" id="fa-upload-input" accept="application/pdf,.pdf" hidden />
						<a class="fa-chip" href="<?php echo esc_url( $builder_url ); ?>"><span aria-hidden="true">🧩</span> <?php esc_html_e( 'Shortcode Builder', 'foliora' ); ?></a>
						<a class="fa-chip" href="<?php echo esc_url( $embeds_url ); ?>"><span aria-hidden="true">📁</span> <?php esc_html_e( 'Saved Embeds', 'foliora' ); ?></a>
						<a class="fa-chip" href="<?php echo esc_url( $settings_url ); ?>"><span aria-hidden="true">⚙️</span> <?php esc_html_e( 'Viewer Settings', 'foliora' ); ?></a>
						<a class="fa-chip" href="<?php echo esc_url( $guide_url ); ?>"><span aria-hidden="true">📘</span> <?php esc_html_e( 'Docs & Features', 'foliora' ); ?></a>
					</div>
				</div>
			</div>

			<?php if ( $show_welcome ) : ?>
				<div class="fa-banner" id="foliora-welcome" role="status">
					<p>
						<?php esc_html_e( 'Pick a cover on the right — or drop a PDF — then copy the shortcode.', 'foliora' ); ?>
					</p>
					<button type="button" class="fa-banner__dismiss" id="foliora-welcome-dismiss">
						<span class="screen-reader-text"><?php esc_html_e( 'Dismiss this notice.', 'foliora' ); ?></span>
						<span aria-hidden="true">&times;</span>
					</button>
				</div>
			<?php endif; ?>

			<div class="fa-metrics" role="list">
				<a class="fa-card fa-card--lift fa-metric" role="listitem" id="foliora-metric-pdfs" href="<?php echo esc_url( $docs_url ); ?>">
					<span class="fa-metric__icon is-indigo"><span class="dashicons dashicons-media-document" aria-hidden="true"></span></span>
					<span class="fa-metric__body">
						<span class="fa-metric__value" data-count="<?php echo esc_attr( (string) $pdf_count ); ?>"><?php echo esc_html( (string) $pdf_count ); ?></span>
						<span class="fa-metric__label"><?php esc_html_e( 'PDFs', 'foliora' ); ?></span>
					</span>
					<span class="fa-metric__link" aria-hidden="true"><?php esc_html_e( 'View', 'foliora' ); ?> &rarr;</span>
				</a>
				<a class="fa-card fa-card--lift fa-metric" role="listitem" id="foliora-metric-embeds" href="<?php echo esc_url( $embeds_url ); ?>">
					<span class="fa-metric__icon is-violet"><span class="dashicons dashicons-portfolio" aria-hidden="true"></span></span>
					<span class="fa-metric__body">
						<span class="fa-metric__value" data-count="<?php echo esc_attr( (string) $saved_count ); ?>"><?php echo esc_html( (string) $saved_count ); ?></span>
						<span class="fa-metric__label"><?php esc_html_e( 'Saved Embeds', 'foliora' ); ?></span>
					</span>
					<span class="fa-metric__link" aria-hidden="true"><?php esc_html_e( 'View', 'foliora' ); ?> &rarr;</span>
				</a>
				<div class="fa-card fa-card--lift fa-metric" role="listitem" id="foliora-metric-pages">
					<span class="fa-metric__icon is-sky"><span class="dashicons dashicons-admin-page" aria-hidden="true"></span></span>
					<span class="fa-metric__body">
						<span class="fa-metric__value" data-count="<?php echo esc_attr( (string) $page_count ); ?>"><?php echo esc_html( (string) $page_count ); ?></span>
						<span class="fa-metric__label"><?php esc_html_e( 'Pages using Foliora', 'foliora' ); ?></span>
					</span>
				</div>
				<div class="fa-card fa-card--lift fa-metric<?php echo $setup_done ? ' is-complete' : ''; ?>" role="listitem" id="foliora-metric-setup" data-total="<?php echo esc_attr( (string) $total_steps ); ?>">
					<span class="fa-metric__icon is-green"><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span></span>
					<span class="fa-metric__body">
						<span class="fa-metric__value"><?php echo esc_html( $done . '/' . $total_steps ); ?></span>
						<span class="fa-metric__label"><?php esc_html_e( 'Setup', 'foliora' ); ?></span>
					</span>
				</div>
			</div>

			<div class="fa-workspace">
				<section class="fa-card fa-setup<?php echo $setup_done ? ' is-complete' : ''; ?>" id="foliora-setup-panel" aria-labelledby="foliora-setup-heading">
					<div class="fa-card__head">
						<span class="fa-card__icon dashicons dashicons-flag" aria-hidden="true"></span>
						<div class="fa-card__titles">
							<h2 class="fa-card__title" id="foliora-setup-heading"><?php esc_html_e( 'Get started', 'foliora' ); ?></h2>
							<p class="fa-card__sub" id="foliora-setup-status">
								<?php
								if ( $setup_done ) {
									esc_html_e( 'You are set. New documents go in Documents or the Media Library.', 'foliora' );
								} else {
									/* translators: 1: completed steps, 2: total steps. */
									echo esc_html( sprintf( __( '%1$s of %2$s steps complete', 'foliora' ), (string) $done, (string) $total_steps ) );
								}
								?>
							</p>
						</div>
					</div>
					<div class="fa-card__body">
						<div class="fa-progress" role="progressbar" aria-label="<?php esc_attr_e( 'Setup progress', 'foliora' ); ?>" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo esc_attr( (string) $percent ); ?>">
							<span style="width: <?php echo esc_attr( (string) $percent ); ?>%;"></span>
						</div>

						<div class="fa-setup__done">
							<span class="fa-setup__done-text"><span aria-hidden="true">✓</span> <?php esc_html_e( "You're all set", 'foliora' ); ?></span>
							<button type="button" class="fa-btn fa-btn--secondary fa-btn--sm" id="foliora-setup-toggle" aria-expanded="false" aria-controls="foliora-setup-steps" data-show="<?php esc_attr_e( 'Show steps', 'foliora' ); ?>" data-hide="<?php esc_attr_e( 'Hide steps', 'foliora' ); ?>">
								<?php esc_html_e( 'Show steps', 'foliora' ); ?>
							</button>
						</div>

						<ol class="fa-steps" id="foliora-setup-steps">
							<li class="<?php echo $steps['uploaded'] ? 'is-done' : ''; ?>" data-setup-step="uploaded">
								<span class="fa-steps__marker" aria-hidden="true"><span class="fa-steps__num">1</span><span class="fa-steps__check dashicons dashicons-yes"></span></span>
								<div class="fa-steps__copy">
									<span class="fa-steps__title"><?php esc_html_e( 'Upload a PDF', 'foliora' ); ?></span>
									<span class="fa-steps__help"><?php esc_html_e( 'Add a file, or drop one on the stage.', 'foliora' ); ?></span>
								</div>
								<a class="fa-btn fa-btn--secondary fa-btn--sm" href="<?php echo esc_url( admin_url( 'media-new.php' ) ); ?>">
									<span class="dashicons dashicons-upload" aria-hidden="true"></span><?php esc_html_e( 'Upload', 'foliora' ); ?>
								</a>
							</li>
							<li class="<?php echo $steps['copied'] ? 'is-done' : ''; ?>" data-setup-step="copied">
								<span class="fa-steps__marker" aria-hidden="true"><span class="fa-steps__num">2</span><span class="fa-steps__check dashicons dashicons-yes"></span></span>
								<div class="fa-steps__copy">
									<span class="fa-steps__title"><?php esc_html_e( 'Copy a shortcode', 'foliora' ); ?></span>
									<span class="fa-steps__help"><?php esc_html_e( 'Click the code on the stage to copy.', 'foliora' ); ?></span>
								</div>
								<button type="button" class="fa-btn fa-btn--secondary fa-btn--sm" data-foliora-focus="embed">
									<span class="dashicons dashicons-clipboard" aria-hidden="true"></span><?php esc_html_e( 'Show stage', 'foliora' ); ?>
								</button>
							</li>
							<li class="<?php echo $steps['embedded'] ? 'is-done' : ''; ?>" data-setup-step="embedded">
								<span class="fa-steps__marker" aria-hidden="true"><span class="fa-steps__num">3</span><span class="fa-steps__check dashicons dashicons-yes"></span></span>
								<div class="fa-steps__copy">
									<span class="fa-steps__title"><?php esc_html_e( 'Put it on a page', 'foliora' ); ?></span>
									<span class="fa-steps__help"><?php esc_html_e( 'Publish a test page, or use the viewer block.', 'foliora' ); ?></span>
								</div>
								<button type="button" class="fa-btn fa-btn--secondary fa-btn--sm" data-foliora-focus="embed-create">
									<span class="dashicons dashicons-admin-page" aria-hidden="true"></span><?php esc_html_e( 'Create page', 'foliora' ); ?>
								</button>
							</li>
						</ol>
					</div>
				</section>

				<section class="fa-card fa-embed" id="foliora-embed-panel" aria-labelledby="foliora-embed-heading">
					<div class="fa-card__head">
						<span class="fa-card__icon dashicons dashicons-media-document" aria-hidden="true"></span>
						<div class="fa-card__titles">
							<h2 class="fa-card__title" id="foliora-embed-heading"><?php esc_html_e( 'Embed a document', 'foliora' ); ?></h2>
							<p class="fa-card__sub"><?php esc_html_e( 'Drop a PDF, pick a cover, or browse the library.', 'foliora' ); ?></p>
						</div>
						<a class="fa-btn fa-btn--secondary fa-btn--sm fa-card__action" id="fa-open-builder" href="<?php echo esc_url( $open_url ); ?>" data-base="<?php echo esc_url( $builder_url ); ?>">
							<span class="dashicons dashicons-admin-customizer" aria-hidden="true"></span><?php esc_html_e( 'Open in Builder', 'foliora' ); ?>
						</a>
					</div>

					<div class="fa-card__body">
						<div class="fa-stage<?php echo $prefill ? '' : ' is-empty'; ?>" id="foliora-stage">
							<div class="fa-stage__cover" aria-hidden="true">
								<img id="foliora-stage-thumb" alt=""
								<?php
								if ( $prefill && ! empty( $prefill['thumb'] ) ) {
									echo ' src="' . esc_url( $prefill['thumb'] ) . '"';
								} else {
									echo ' hidden';
								}
								?>
								/>
								<span class="fa-stage__fallback dashicons dashicons-media-document" id="foliora-stage-fallback"<?php echo ( $prefill && ! empty( $prefill['thumb'] ) ) ? ' hidden' : ''; ?>></span>
							</div>
							<div class="fa-stage__meta">
								<p class="fa-stage__title" id="foliora-selected-file" title="<?php echo esc_attr( $title_attr ); ?>"><?php echo $prefill ? esc_html( $prefill['title'] ) : esc_html__( 'No document selected', 'foliora' ); ?></p>
								<label class="screen-reader-text" for="foliora-shortcode-output"><?php esc_html_e( 'Shortcode', 'foliora' ); ?></label>
								<div class="fa-code" id="foliora-code" title="<?php esc_attr_e( 'Click to copy', 'foliora' ); ?>">
									<input
										type="text"
										id="foliora-shortcode-output"
										readonly
										value="<?php echo esc_attr( $prefill_code ); ?>"
										placeholder='[foliora file="https://example.com/document.pdf"]'
									/>
									<button type="button" class="fa-btn fa-btn--primary fa-btn--sm" id="foliora-copy-shortcode" <?php disabled( ! $prefill_code ); ?>>
										<?php esc_html_e( 'Copy', 'foliora' ); ?>
									</button>
								</div>
								<div class="fa-stage__actions">
									<button type="button" class="fa-btn fa-btn--ghost-dark fa-btn--sm" id="foliora-create-test-page" <?php disabled( ! $prefill_code ); ?>>
										<?php esc_html_e( 'Create test page', 'foliora' ); ?>
									</button>
									<button type="button" class="fa-link" id="foliora-pick-pdf"><?php esc_html_e( 'Browse Media Library', 'foliora' ); ?></button>
								</div>
							</div>
							<div class="fa-drop" id="foliora-drop" hidden>
								<span class="dashicons dashicons-upload" aria-hidden="true"></span>
								<span><?php esc_html_e( 'Drop a PDF to embed it', 'foliora' ); ?></span>
							</div>
						</div>

						<?php if ( $recent ) : ?>
							<div class="fa-subhead"><h3><?php esc_html_e( 'Recent', 'foliora' ); ?></h3></div>
							<div class="fa-covers" role="list">
								<?php foreach ( $recent as $index => $item ) : ?>
									<button
										type="button"
										class="fa-cover foliora-recent-item<?php echo ( 0 === $index ) ? ' is-selected' : ''; ?>"
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
							<a class="fa-link" href="<?php echo esc_url( $settings_url ); ?>"><?php esc_html_e( 'Viewer defaults', 'foliora' ); ?> &rarr;</a>
						</p>
					</div>
				</section>
			</div>

			<section class="fa-card fa-saved" aria-labelledby="fa-saved-heading">
				<div class="fa-card__head">
					<span class="fa-card__icon dashicons dashicons-portfolio" aria-hidden="true"></span>
					<div class="fa-card__titles">
						<h2 class="fa-card__title" id="fa-saved-heading"><?php esc_html_e( 'Recent saved embeds', 'foliora' ); ?></h2>
					</div>
					<?php if ( $recent_saved ) : ?>
						<a class="fa-btn fa-btn--secondary fa-btn--sm fa-card__action" href="<?php echo esc_url( $embeds_url ); ?>"><?php esc_html_e( 'View all', 'foliora' ); ?></a>
					<?php endif; ?>
				</div>
				<?php if ( $recent_saved ) : ?>
					<ul class="fa-saved__list">
						<?php foreach ( $recent_saved as $embed ) : ?>
							<li class="fa-saved__row">
								<span class="fa-saved__name" title="<?php echo esc_attr( $embed['name'] ); ?>"><?php echo esc_html( '' !== $embed['name'] ? $embed['name'] : __( 'Untitled embed', 'foliora' ) ); ?></span>
								<span class="fa-saved__code">
									<code><?php echo esc_html( $embed['shortcode'] ); ?></code>
									<button type="button" class="fa-icon-btn" data-fa-copy="<?php echo esc_attr( $embed['shortcode'] ); ?>" aria-label="<?php esc_attr_e( 'Copy shortcode', 'foliora' ); ?>" title="<?php esc_attr_e( 'Copy shortcode', 'foliora' ); ?>">
										<span class="dashicons dashicons-admin-page" aria-hidden="true"></span>
									</button>
								</span>
								<span class="fa-saved__date"><?php echo esc_html( $embed['date'] ); ?></span>
								<a class="fa-btn fa-btn--secondary fa-btn--sm" href="<?php echo esc_url( add_query_arg( 'embed_id', (int) $embed['id'], $builder_url ) ); ?>">
									<span class="dashicons dashicons-edit" aria-hidden="true"></span><?php esc_html_e( 'Edit', 'foliora' ); ?>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php else : ?>
					<div class="fa-empty">
						<span class="fa-empty__icon dashicons dashicons-portfolio" aria-hidden="true"></span>
						<p class="fa-empty__title"><?php esc_html_e( 'No saved embeds yet', 'foliora' ); ?></p>
						<a class="fa-btn fa-btn--primary" href="<?php echo esc_url( $builder_url ); ?>"><?php esc_html_e( 'Create your first embed', 'foliora' ); ?></a>
					</div>
				<?php endif; ?>
			</section>

			<div class="foliora-toast" id="foliora-toast" hidden role="status"></div>
		</div>
		<?php
	}

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings    = get_option( self::OPTION_NAME, array() );
		$preview_url = $this->get_latest_pdf_url();
		$preview     = '';

		if ( $preview_url ) {
			$preview = Foliora::instance()->viewer->render_shortcode(
				array(
					'file'   => $preview_url,
					'width'  => $settings['default_width'] ?? '100%',
					'height' => $settings['default_height'] ?? '420px',
				)
			);
		}
		?>
		<div class="wrap foliora-admin foliora-settings-wrap">
			<h1><?php esc_html_e( 'Foliora Settings', 'foliora' ); ?></h1>
			<p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ); ?>">&larr; <?php esc_html_e( 'Back to Dashboard', 'foliora' ); ?></a>
			</p>

			<div class="foliora-settings-layout">
				<form method="post" action="options.php">
					<?php
					settings_fields( self::OPTION_GROUP );
					printf(
						'<input type="hidden" name="%1$s[%2$s]" value="1" />',
						esc_attr( self::OPTION_NAME ),
						esc_attr( self::FORM_MARKER )
					);
					do_settings_sections( self::PAGE_SLUG );
					submit_button();
					?>
				</form>

				<div class="foliora-live-preview">
					<h2><?php esc_html_e( 'Live preview', 'foliora' ); ?></h2>
					<p class="foliora-card-help">
						<?php esc_html_e( 'Width and height update this viewer immediately. Save to store them as site defaults.', 'foliora' ); ?>
					</p>
					<?php if ( $preview ) : ?>
						<div class="foliora-live-preview-stage">
							<?php echo $preview; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- viewer HTML is built with escaped atts. ?>
						</div>
						<p class="description">
							<?php esc_html_e( 'Preview uses the most recently uploaded PDF in the Media Library.', 'foliora' ); ?>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::DOCS_SLUG ) ); ?>"><?php esc_html_e( 'Open Documents', 'foliora' ); ?></a>
						</p>
					<?php else : ?>
						<div class="foliora-live-preview-empty">
							<p><?php esc_html_e( 'Upload a PDF to see a live preview of these defaults.', 'foliora' ); ?></p>
							<a class="button" href="<?php echo esc_url( admin_url( 'media-new.php' ) ); ?>"><?php esc_html_e( 'Upload PDF', 'foliora' ); ?></a>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
	}

	public function render_builder_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$editing_id    = isset( $_GET['embed_id'] ) ? absint( wp_unslash( $_GET['embed_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$editing_embed = $editing_id ? Foliora_Embeds::get( $editing_id ) : null;
		$saved_embeds  = Foliora_Embeds::get_all();

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
			$initial_preview = Foliora::instance()->viewer->render_shortcode(
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
		<div class="wrap foliora-admin foliora-builder-wrap">
			<div class="foliora-builder-header">
				<div class="foliora-builder-header-copy">
					<div class="foliora-builder-kicker">
						<span class="foliora-mark dashicons dashicons-admin-customizer" aria-hidden="true"></span>
						<h1><?php esc_html_e( 'Shortcode Builder & Live Preview', 'foliora' ); ?></h1>
					</div>
					<p class="foliora-builder-lede">
						<?php esc_html_e( 'Visually configure your PDF embed with real-time preview, then save to generate a clean shortcode like [foliora id="1"].', 'foliora' ); ?>
					</p>
				</div>
				<div class="foliora-builder-presets">
					<span class="foliora-presets-label"><?php esc_html_e( '1-Click Presets:', 'foliora' ); ?></span>
					<div class="foliora-presets-chips">
						<button type="button" class="foliora-preset-chip" data-preset="flipbook" title="<?php esc_attr_e( '3D realistic FlipBook with Web Audio page turn sound', 'foliora' ); ?>">
							<span class="foliora-preset-icon">✨</span> <?php esc_html_e( '3D FlipBook', 'foliora' ); ?>
						</button>
						<button type="button" class="foliora-preset-chip" data-preset="ebook" title="<?php esc_attr_e( 'Sepia theme, continuous scroll, search enabled', 'foliora' ); ?>">
							<span class="foliora-preset-icon">📖</span> <?php esc_html_e( 'E-Book Reader', 'foliora' ); ?>
						</button>
						<button type="button" class="foliora-preset-chip" data-preset="corporate" title="<?php esc_attr_e( 'Clean white theme, presentation and download enabled', 'foliora' ); ?>">
							<span class="foliora-preset-icon">💼</span> <?php esc_html_e( 'Corporate Report', 'foliora' ); ?>
						</button>
						<button type="button" class="foliora-preset-chip" data-preset="night" title="<?php esc_attr_e( 'Dark theme, fullscreen and zoom enabled', 'foliora' ); ?>">
							<span class="foliora-preset-icon">🌙</span> <?php esc_html_e( 'Night Reader', 'foliora' ); ?>
						</button>
						<button type="button" class="foliora-preset-chip" data-preset="viewonly" title="<?php esc_attr_e( 'Download and print hidden, pan and zoom allowed', 'foliora' ); ?>">
							<span class="foliora-preset-icon">🔒</span> <?php esc_html_e( 'View Only (Protected)', 'foliora' ); ?>
						</button>
						<button type="button" class="foliora-preset-chip" data-preset="minimal" title="<?php esc_attr_e( 'Lazy loaded, compact toolbar, faster initial load', 'foliora' ); ?>">
							<span class="foliora-preset-icon">⚡</span> <?php esc_html_e( 'Speed Optimized', 'foliora' ); ?>
						</button>
					</div>
				</div>
			</div>

			<!-- Builder / Saved Embeds View Switcher Tabs -->
			<div class="foliora-builder-view-nav">
				<button type="button" class="foliora-view-tab-btn is-active" data-view="builder">
					<span class="dashicons dashicons-admin-customizer" aria-hidden="true"></span>
					<span><?php esc_html_e( 'Shortcode Builder', 'foliora' ); ?></span>
				</button>
				<button type="button" class="foliora-view-tab-btn" data-view="embeds">
					<span class="dashicons dashicons-portfolio" aria-hidden="true"></span>
					<span><?php esc_html_e( 'My Saved Embeds', 'foliora' ); ?></span>
					<span class="foliora-count-pill" id="foliora-embeds-count-badge"><?php echo esc_html( (string) count( $saved_embeds ) ); ?></span>
				</button>
			</div>

			<?php if ( $editing_embed ) : ?>
			<div class="foliora-builder-edit-banner">
				<div class="foliora-edit-banner-left">
					<span class="dashicons dashicons-edit" aria-hidden="true"></span>
					<span>
						<?php
						printf(
							/* translators: 1: Embed name, 2: Embed ID */
							esc_html__( 'Editing Saved Embed: %1$s (ID: #%2$d)', 'foliora' ),
							'<strong>' . esc_html( $editing_embed['name'] ) . '</strong>',
							absint( $editing_id )
						);
						?>
					</span>
				</div>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::BUILDER_SLUG ) ); ?>" class="button button-small foliora-btn-new-embed">
					<?php esc_html_e( '+ Create New Embed', 'foliora' ); ?>
				</a>
			</div>
			<?php endif; ?>

			<!-- VIEW 1: Builder Layout -->
			<div class="foliora-main-view-pane is-active" id="foliora-view-builder">
				<div class="foliora-builder-layout">
					<!-- Left Column: 3 Streamlined Tabs -->
					<div class="foliora-builder-controls">
						<div class="foliora-card foliora-builder-tabs-card">
							<!-- Left Tabs Navigation Bar (3 Compact Tabs) -->
							<div class="foliora-builder-tabs-header">
								<nav class="foliora-ctrl-tabs-nav" role="tablist">
									<button type="button" class="foliora-ctrl-tab is-active" data-tab="doc" role="tab" aria-selected="true">
										<span class="dashicons dashicons-media-document" aria-hidden="true"></span>
										<span><?php esc_html_e( '1. Document & Style', 'foliora' ); ?></span>
									</button>
									<button type="button" class="foliora-ctrl-tab" data-tab="layout" role="tab" aria-selected="false">
										<span class="dashicons dashicons-layout" aria-hidden="true"></span>
										<span><?php esc_html_e( '2. Size & Layout', 'foliora' ); ?></span>
									</button>
									<button type="button" class="foliora-ctrl-tab" data-tab="controls" role="tab" aria-selected="false">
										<span class="dashicons dashicons-admin-settings" aria-hidden="true"></span>
										<span><?php esc_html_e( '3. Toolbar & Advanced', 'foliora' ); ?></span>
									</button>
								</nav>
							</div>

							<!-- Tab 1: Document & Style Pane -->
							<div class="foliora-ctrl-pane is-active" id="foliora-pane-doc" role="tabpanel">
								<div class="foliora-pane-body">
									<div class="foliora-form-row">
										<label for="foliora-builder-name" class="foliora-label"><?php esc_html_e( 'Embed Name / Title:', 'foliora' ); ?></label>
										<input type="text" id="foliora-builder-name" class="regular-text" value="<?php echo esc_attr( $default_name ); ?>" placeholder="<?php esc_attr_e( 'e.g. Annual Company Report 2026', 'foliora' ); ?>" />
										<input type="hidden" id="foliora-builder-id" value="<?php echo esc_attr( (string) $editing_id ); ?>" />
										<p class="description"><?php esc_html_e( 'Name for your internal reference in the Saved Embeds manager.', 'foliora' ); ?></p>
									</div>

									<hr class="foliora-pane-divider" />

									<div class="foliora-section-subhead">
										<h3><?php esc_html_e( 'PDF Document Source', 'foliora' ); ?></h3>
									</div>
									<div class="foliora-file-actions">
										<button type="button" class="button button-primary" id="foliora-builder-pick-pdf">
											<span class="dashicons dashicons-admin-media" aria-hidden="true"></span> <?php esc_html_e( 'Choose from Media Library', 'foliora' ); ?>
										</button>
										<button type="button" class="button" id="foliora-builder-upload-pdf">
											<span class="dashicons dashicons-upload" aria-hidden="true"></span> <?php esc_html_e( 'Upload New PDF', 'foliora' ); ?>
										</button>
									</div>

									<div class="foliora-form-row">
										<label for="foliora-builder-file" class="foliora-label"><?php esc_html_e( 'PDF File URL:', 'foliora' ); ?></label>
										<input type="url" id="foliora-builder-file" class="regular-text code" value="<?php echo esc_url( $default_pdf ); ?>" placeholder="https://example.com/document.pdf" />
									</div>

									<?php if ( ! empty( $recent_pdfs ) ) : ?>
									<div class="foliora-recent-picker">
										<span class="foliora-sublabel"><?php esc_html_e( 'Or select a recent PDF:', 'foliora' ); ?></span>
										<div class="foliora-recent-list">
											<?php foreach ( $recent_pdfs as $pdf_item ) : ?>
											<button type="button" class="foliora-recent-btn<?php echo ( $default_pdf === $pdf_item['url'] ) ? ' is-active' : ''; ?>" data-url="<?php echo esc_url( $pdf_item['url'] ); ?>" data-title="<?php echo esc_attr( $pdf_item['title'] ); ?>">
												<span class="dashicons dashicons-pdf" aria-hidden="true"></span>
												<span class="foliora-recent-title"><?php echo esc_html( $pdf_item['title'] ); ?></span>
											</button>
											<?php endforeach; ?>
										</div>
									</div>
									<?php endif; ?>

									<hr class="foliora-pane-divider" />

									<div class="foliora-section-subhead">
										<h3><?php esc_html_e( 'Appearance & Theme', 'foliora' ); ?></h3>
									</div>

									<div class="foliora-form-row">
										<label class="foliora-label"><?php esc_html_e( 'Reading Theme:', 'foliora' ); ?></label>
										<div class="foliora-theme-pills">
											<label class="foliora-theme-pill foliora-theme-pill-light">
												<input type="radio" name="foliora_b_theme" value="light" <?php checked( $default_theme, 'light' ); ?> />
												<span class="foliora-pill-box">
													<span class="foliora-pill-icon">☀️</span>
													<span class="foliora-pill-label"><?php esc_html_e( 'Light', 'foliora' ); ?></span>
												</span>
											</label>
											<label class="foliora-theme-pill foliora-theme-pill-dark">
												<input type="radio" name="foliora_b_theme" value="dark" <?php checked( $default_theme, 'dark' ); ?> />
												<span class="foliora-pill-box">
													<span class="foliora-pill-icon">🌙</span>
													<span class="foliora-pill-label"><?php esc_html_e( 'Dark Mode', 'foliora' ); ?></span>
												</span>
											</label>
											<label class="foliora-theme-pill foliora-theme-pill-sepia">
												<input type="radio" name="foliora_b_theme" value="sepia" <?php checked( $default_theme, 'sepia' ); ?> />
												<span class="foliora-pill-box">
													<span class="foliora-pill-icon">☕</span>
													<span class="foliora-pill-label"><?php esc_html_e( 'Sepia (Warm)', 'foliora' ); ?></span>
												</span>
											</label>
										</div>
									</div>

									<div class="foliora-form-row">
										<label for="foliora-builder-title" class="foliora-label"><?php esc_html_e( 'Viewer Caption / Subtitle (Optional):', 'foliora' ); ?></label>
										<input type="text" id="foliora-builder-title" class="regular-text" value="<?php echo esc_attr( $default_title ); ?>" placeholder="<?php esc_attr_e( 'e.g. Official Edition', 'foliora' ); ?>" />
									</div>
								</div>
								<div class="foliora-pane-footer">
									<span></span>
									<button type="button" class="button foliora-tab-step" data-step-to="layout">
										<?php esc_html_e( 'Next: Size & Layout →', 'foliora' ); ?>
									</button>
								</div>
							</div>

							<!-- Tab 2: Size & Layout Pane -->
							<div class="foliora-ctrl-pane" id="foliora-pane-layout" role="tabpanel" style="display: none;">
								<div class="foliora-pane-body">
									<div class="foliora-section-subhead">
										<h3><?php esc_html_e( 'Viewer Dimensions', 'foliora' ); ?></h3>
									</div>

									<div class="foliora-grid-2">
										<div class="foliora-form-row">
											<label for="foliora-builder-width" class="foliora-label"><?php esc_html_e( 'Width:', 'foliora' ); ?></label>
											<input type="text" id="foliora-builder-width" class="regular-text" value="<?php echo esc_attr( $default_width ); ?>" placeholder="100%" />
											<div class="foliora-quick-chips">
												<button type="button" class="foliora-quick-chip" data-target="#foliora-builder-width" data-val="100%">100%</button>
												<button type="button" class="foliora-quick-chip" data-target="#foliora-builder-width" data-val="800px">800px</button>
												<button type="button" class="foliora-quick-chip" data-target="#foliora-builder-width" data-val="640px">640px</button>
											</div>
										</div>
										<div class="foliora-form-row">
											<label for="foliora-builder-height" class="foliora-label"><?php esc_html_e( 'Height:', 'foliora' ); ?></label>
											<input type="text" id="foliora-builder-height" class="regular-text" value="<?php echo esc_attr( $default_height ); ?>" placeholder="520px" />
											<div class="foliora-quick-chips">
												<button type="button" class="foliora-quick-chip" data-target="#foliora-builder-height" data-val="520px">520px</button>
												<button type="button" class="foliora-quick-chip" data-target="#foliora-builder-height" data-val="650px">650px</button>
												<button type="button" class="foliora-quick-chip" data-target="#foliora-builder-height" data-val="80vh">80vh</button>
											</div>
										</div>
									</div>

									<hr class="foliora-pane-divider" />

									<div class="foliora-section-subhead">
										<h3><?php esc_html_e( 'Initial Page & Display Mode', 'foliora' ); ?></h3>
									</div>

									<div class="foliora-grid-2">
										<div class="foliora-form-row">
											<label for="foliora-builder-page" class="foliora-label"><?php esc_html_e( 'Start Page:', 'foliora' ); ?></label>
											<input type="number" id="foliora-builder-page" class="small-text" min="1" value="<?php echo esc_attr( (string) $default_page ); ?>" />
										</div>
										<div class="foliora-form-row">
											<label class="foliora-label"><?php esc_html_e( 'Initial View Layout:', 'foliora' ); ?></label>
											<div class="foliora-view-pills">
												<label class="foliora-view-pill">
													<input type="radio" name="foliora_b_view" value="page" <?php checked( $default_view, 'page' ); ?> />
													<span><?php esc_html_e( 'Single Page', 'foliora' ); ?></span>
												</label>
												<label class="foliora-view-pill">
													<input type="radio" name="foliora_b_view" value="scroll" <?php checked( $default_view, 'scroll' ); ?> />
													<span><?php esc_html_e( 'Continuous Scroll', 'foliora' ); ?></span>
												</label>
												<label class="foliora-view-pill">
													<input type="radio" name="foliora_b_view" value="spread" <?php checked( $default_view, 'spread' ); ?> />
													<span><?php esc_html_e( 'Two-Page Spread', 'foliora' ); ?></span>
												</label>
												<label class="foliora-view-pill foliora-view-pill-flip">
													<input type="radio" name="foliora_b_view" value="flip" <?php checked( $default_view, 'flip' ); ?> />
													<span>✨ <?php esc_html_e( '3D FlipBook', 'foliora' ); ?></span>
												</label>
											</div>
										</div>
									</div>
								</div>
								<div class="foliora-pane-footer">
									<button type="button" class="button foliora-tab-step" data-step-to="doc">
										<?php esc_html_e( '← Back', 'foliora' ); ?>
									</button>
									<button type="button" class="button foliora-tab-step" data-step-to="controls">
										<?php esc_html_e( 'Next: Toolbar & Advanced →', 'foliora' ); ?>
									</button>
								</div>
							</div>

							<!-- Tab 3: Toolbar & Advanced Pane -->
							<div class="foliora-ctrl-pane" id="foliora-pane-controls" role="tabpanel" style="display: none;">
								<div class="foliora-pane-body">
									<div class="foliora-section-subhead">
										<h3><?php esc_html_e( 'Toolbar Buttons & Controls', 'foliora' ); ?></h3>
										<p class="description"><?php esc_html_e( 'Uncheck any button you want to hide from the viewer toolbar:', 'foliora' ); ?></p>
									</div>

									<div class="foliora-tool-switches-grid">
										<label class="foliora-tool-switch">
											<input type="checkbox" class="foliora-tool-toggle" data-tool="download" data-attr="download" data-global="<?php echo ( ! isset( $settings['allow_download'] ) || ! empty( $settings['allow_download'] ) ) ? '1' : '0'; ?>" <?php checked( ! in_array( 'download', $default_hide, true ) ); ?> />
											<span class="foliora-switch-label">📥 <?php esc_html_e( 'Download', 'foliora' ); ?></span>
											<span class="foliora-loc-badge is-bar"><?php esc_html_e( 'Toolbar', 'foliora' ); ?></span>
										</label>
										<label class="foliora-tool-switch">
											<input type="checkbox" class="foliora-tool-toggle" data-tool="print" data-attr="print" data-global="<?php echo ( ! isset( $settings['allow_print'] ) || ! empty( $settings['allow_print'] ) ) ? '1' : '0'; ?>" <?php checked( ! in_array( 'print', $default_hide, true ) ); ?> />
											<span class="foliora-switch-label">🖨️ <?php esc_html_e( 'Print', 'foliora' ); ?></span>
											<span class="foliora-loc-badge is-menu"><?php esc_html_e( '⋮ Menu', 'foliora' ); ?></span>
										</label>
										<label class="foliora-tool-switch">
											<input type="checkbox" class="foliora-tool-toggle" data-tool="search" data-attr="search" data-global="<?php echo ( ! isset( $settings['allow_search'] ) || ! empty( $settings['allow_search'] ) ) ? '1' : '0'; ?>" <?php checked( ! in_array( 'search', $default_hide, true ) ); ?> />
											<span class="foliora-switch-label">🔍 <?php esc_html_e( 'Text Search (Ctrl+F)', 'foliora' ); ?></span>
											<span class="foliora-loc-badge is-bar"><?php esc_html_e( 'Toolbar', 'foliora' ); ?></span>
										</label>
										<label class="foliora-tool-switch">
											<input type="checkbox" class="foliora-tool-toggle" data-tool="theme" data-attr="theme_btn" data-global="<?php echo ( ! isset( $settings['allow_theme'] ) || ! empty( $settings['allow_theme'] ) ) ? '1' : '0'; ?>" <?php checked( ! in_array( 'theme', $default_hide, true ) ); ?> />
											<span class="foliora-switch-label">🎨 <?php esc_html_e( 'Theme Switcher', 'foliora' ); ?></span>
											<span class="foliora-loc-badge is-menu"><?php esc_html_e( '⋮ Menu', 'foliora' ); ?></span>
										</label>
										<label class="foliora-tool-switch">
											<input type="checkbox" class="foliora-tool-toggle" data-tool="share" data-attr="share" data-global="<?php echo ( ! isset( $settings['allow_share'] ) || ! empty( $settings['allow_share'] ) ) ? '1' : '0'; ?>" <?php checked( ! in_array( 'share', $default_hide, true ) ); ?> />
											<span class="foliora-switch-label">🔗 <?php esc_html_e( 'Share & Page Link', 'foliora' ); ?></span>
											<span class="foliora-loc-badge is-menu"><?php esc_html_e( '⋮ Menu', 'foliora' ); ?></span>
										</label>
										<label class="foliora-tool-switch">
											<input type="checkbox" class="foliora-tool-toggle" data-tool="shortcuts" data-attr="shortcuts" data-global="<?php echo ( ! isset( $settings['allow_shortcuts'] ) || ! empty( $settings['allow_shortcuts'] ) ) ? '1' : '0'; ?>" <?php checked( ! in_array( 'shortcuts', $default_hide, true ) ); ?> />
											<span class="foliora-switch-label">⌨️ <?php esc_html_e( 'Shortcuts Guide (?)', 'foliora' ); ?></span>
											<span class="foliora-loc-badge is-menu"><?php esc_html_e( '⋮ Menu', 'foliora' ); ?></span>
										</label>
										<label class="foliora-tool-switch">
											<input type="checkbox" class="foliora-tool-toggle" data-tool="fullscreen" data-attr="fullscreen" data-global="<?php echo ( ! isset( $settings['allow_fullscreen'] ) || ! empty( $settings['allow_fullscreen'] ) ) ? '1' : '0'; ?>" <?php checked( ! in_array( 'fullscreen', $default_hide, true ) ); ?> />
											<span class="foliora-switch-label">⛶ <?php esc_html_e( 'Full Screen', 'foliora' ); ?></span>
											<span class="foliora-loc-badge is-bar"><?php esc_html_e( 'Toolbar', 'foliora' ); ?></span>
										</label>
										<label class="foliora-tool-switch">
											<input type="checkbox" class="foliora-tool-toggle" data-tool="presentation" data-attr="presentation" data-global="<?php echo ( ! isset( $settings['allow_presentation'] ) || ! empty( $settings['allow_presentation'] ) ) ? '1' : '0'; ?>" <?php checked( ! in_array( 'presentation', $default_hide, true ) ); ?> />
											<span class="foliora-switch-label">📽️ <?php esc_html_e( 'Presentation Mode', 'foliora' ); ?></span>
											<span class="foliora-loc-badge is-menu"><?php esc_html_e( '⋮ Menu', 'foliora' ); ?></span>
										</label>
										<label class="foliora-tool-switch">
											<input type="checkbox" class="foliora-tool-toggle" data-tool="zoom" <?php checked( ! in_array( 'zoom', $default_hide, true ) ); ?> />
											<span class="foliora-switch-label">🔍 <?php esc_html_e( 'Zoom In / Out', 'foliora' ); ?></span>
											<span class="foliora-loc-badge is-bar"><?php esc_html_e( 'Toolbar', 'foliora' ); ?></span>
										</label>
										<label class="foliora-tool-switch">
											<input type="checkbox" class="foliora-tool-toggle" data-tool="rotate" <?php checked( ! in_array( 'rotate', $default_hide, true ) ); ?> />
											<span class="foliora-switch-label">🔄 <?php esc_html_e( 'Rotate Document', 'foliora' ); ?></span>
											<span class="foliora-loc-badge is-menu"><?php esc_html_e( '⋮ Menu', 'foliora' ); ?></span>
										</label>
										<label class="foliora-tool-switch">
											<input type="checkbox" class="foliora-tool-toggle" data-tool="pan" <?php checked( ! in_array( 'pan', $default_hide, true ) ); ?> />
											<span class="foliora-switch-label">✋ <?php esc_html_e( 'Hand / Pan Tool', 'foliora' ); ?></span>
											<span class="foliora-loc-badge is-menu"><?php esc_html_e( '⋮ Menu', 'foliora' ); ?></span>
										</label>
									</div>

									<hr class="foliora-pane-divider" />

									<div class="foliora-section-subhead">
										<h3><?php esc_html_e( 'Performance & Advanced', 'foliora' ); ?></h3>
									</div>

									<div class="foliora-checkbox-group">
										<label class="foliora-check-label">
											<input type="checkbox" id="foliora-builder-lazy" <?php checked( $default_lazy ); ?> />
											<span><strong><?php esc_html_e( 'Lazy Loading (loading="lazy")', 'foliora' ); ?></strong> — <?php esc_html_e( 'Only load PDF when scrolled into viewport.', 'foliora' ); ?></span>
										</label>
										<label class="foliora-check-label">
											<input type="checkbox" id="foliora-builder-hash" <?php checked( $default_hash ); ?> />
											<span><strong><?php esc_html_e( 'Sync Browser URL Hash (hash="true")', 'foliora' ); ?></strong> — <?php esc_html_e( 'Update #page=N in address bar for easy bookmarking.', 'foliora' ); ?></span>
										</label>
										<label class="foliora-check-label">
											<input type="checkbox" id="foliora-builder-resume" <?php checked( $default_resume ); ?> />
											<span><strong><?php esc_html_e( 'Remember Last Read Page (resume="true")', 'foliora' ); ?></strong> — <?php esc_html_e( 'Auto-resume where visitor left off.', 'foliora' ); ?></span>
										</label>
									</div>
								</div>
								<div class="foliora-pane-footer">
									<button type="button" class="button foliora-tab-step" data-step-to="layout">
										<?php esc_html_e( '← Back', 'foliora' ); ?>
									</button>
									<span></span>
								</div>
							</div>
						</div>
					</div>

					<!-- Right Column: Sticky Live Preview & Output -->
					<div class="foliora-builder-sidebar">
						<div class="foliora-builder-sticky">
							<!-- Live Preview Card -->
							<div class="foliora-card foliora-builder-preview-card">
								<div class="foliora-preview-header">
									<div class="foliora-preview-title">
										<span class="dashicons dashicons-visibility" aria-hidden="true"></span>
										<h3><?php esc_html_e( 'Real-Time Live Preview', 'foliora' ); ?></h3>
									</div>
									<div class="foliora-device-switcher" id="foliora-device-switcher">
										<button type="button" class="foliora-device-btn is-active" data-device="desktop" title="<?php esc_attr_e( 'Desktop View (100%)', 'foliora' ); ?>">
											<span class="dashicons dashicons-desktop" aria-hidden="true"></span>
										</button>
										<button type="button" class="foliora-device-btn" data-device="tablet" title="<?php esc_attr_e( 'Tablet View (768px)', 'foliora' ); ?>">
											<span class="dashicons dashicons-tablet" aria-hidden="true"></span>
										</button>
										<button type="button" class="foliora-device-btn" data-device="mobile" title="<?php esc_attr_e( 'Mobile View (375px)', 'foliora' ); ?>">
											<span class="dashicons dashicons-smartphone" aria-hidden="true"></span>
										</button>
									</div>
								</div>

								<!-- Live Preview Frame -->
								<div class="foliora-builder-frame-wrap">
									<div class="foliora-builder-frame" id="foliora-builder-frame">
										<div class="foliora-builder-preview-stage" id="foliora-builder-stage">
											<?php if ( $initial_preview ) : ?>
												<?php echo $initial_preview; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
											<?php else : ?>
												<div class="foliora-builder-empty-state">
													<span class="dashicons dashicons-pdf" aria-hidden="true"></span>
													<p><?php esc_html_e( 'Select or upload a PDF above to see live interactive preview.', 'foliora' ); ?></p>
												</div>
											<?php endif; ?>
										</div>
									</div>
								</div>
							</div>

							<!-- Generated Output Card -->
							<div class="foliora-card foliora-builder-output-card">
								<div class="foliora-output-tabs">
									<button type="button" class="foliora-tab-btn is-active" data-format="shortcode"><?php esc_html_e( 'Shortcode', 'foliora' ); ?></button>
									<button type="button" class="foliora-tab-btn" data-format="php"><?php esc_html_e( 'PHP Snippet', 'foliora' ); ?></button>
									<button type="button" class="foliora-tab-btn" data-format="block"><?php esc_html_e( 'Block Code', 'foliora' ); ?></button>
								</div>

								<div class="foliora-output-box">
									<textarea id="foliora-builder-output" class="foliora-builder-textarea" readonly rows="3"><?php echo $editing_id ? esc_textarea( sprintf( '[foliora id="%d"]', $editing_id ) ) : ''; ?></textarea>
								</div>

								<div class="foliora-output-actions">
									<button type="button" class="button button-primary button-hero" id="foliora-builder-save">
										<span class="dashicons dashicons-saved" aria-hidden="true"></span>
										<span id="foliora-save-btn-text"><?php echo $editing_id ? esc_html__( 'Update Embed & Copy', 'foliora' ) : esc_html__( 'Save Embed & Get Shortcode', 'foliora' ); ?></span>
									</button>
									<button type="button" class="button button-secondary" id="foliora-builder-copy" title="<?php esc_attr_e( 'Copy code to clipboard', 'foliora' ); ?>">
										<span class="dashicons dashicons-clipboard" aria-hidden="true"></span> <?php esc_html_e( 'Copy', 'foliora' ); ?>
									</button>
									<button type="button" class="button button-secondary" id="foliora-builder-create-page">
										<span class="dashicons dashicons-admin-page" aria-hidden="true"></span> <?php esc_html_e( 'Create Test Page', 'foliora' ); ?>
									</button>
								</div>

								<div class="foliora-code-mode-row">
									<label class="foliora-code-mode-toggle">
										<input type="checkbox" id="foliora-toggle-raw-shortcode" />
										<span><?php esc_html_e( 'Developer: Show full inline shortcode', 'foliora' ); ?></span>
									</label>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>

			<!-- VIEW 2: My Saved Embeds Manager -->
			<div class="foliora-main-view-pane" id="foliora-view-embeds" style="display: none;">
				<div class="foliora-card foliora-embeds-manager-card">
					<div class="foliora-manager-header">
						<div class="foliora-manager-title">
							<span class="dashicons dashicons-portfolio" aria-hidden="true"></span>
							<h2><?php esc_html_e( 'My Saved Embeds', 'foliora' ); ?></h2>
						</div>
						<div class="foliora-manager-actions">
							<input type="search" id="foliora-embeds-search" class="regular-text" placeholder="<?php esc_attr_e( 'Search embeds…', 'foliora' ); ?>" />
							<button type="button" class="button button-primary foliora-switch-to-builder">
								<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span> <?php esc_html_e( 'Create New Embed', 'foliora' ); ?>
							</button>
						</div>
					</div>

					<div class="foliora-embeds-table-wrap">
						<?php if ( ! empty( $saved_embeds ) ) : ?>
						<table class="wp-list-table widefat fixed striped foliora-embeds-table">
							<thead>
								<tr>
									<th scope="col" class="column-id" style="width: 65px;"><?php esc_html_e( 'ID', 'foliora' ); ?></th>
									<th scope="col" class="column-name"><?php esc_html_e( 'Embed Name / Title', 'foliora' ); ?></th>
									<th scope="col" class="column-file"><?php esc_html_e( 'PDF Document Source', 'foliora' ); ?></th>
									<th scope="col" class="column-layout" style="width: 130px;"><?php esc_html_e( 'Layout & Theme', 'foliora' ); ?></th>
									<th scope="col" class="column-shortcode" style="width: 190px;"><?php esc_html_e( 'Shortcode', 'foliora' ); ?></th>
									<th scope="col" class="column-date" style="width: 110px;"><?php esc_html_e( 'Date', 'foliora' ); ?></th>
									<th scope="col" class="column-actions" style="width: 180px; text-align: right;"><?php esc_html_e( 'Actions', 'foliora' ); ?></th>
								</tr>
							</thead>
							<tbody id="foliora-embeds-tbody">
								<?php foreach ( $saved_embeds as $emb ) : ?>
								<tr data-embed-id="<?php echo esc_attr( (string) $emb['id'] ); ?>" data-search-text="<?php echo esc_attr( strtolower( $emb['name'] . ' ' . $emb['title'] . ' ' . wp_basename( $emb['file'] ) ) ); ?>">
									<td class="column-id"><strong>#<?php echo esc_html( (string) $emb['id'] ); ?></strong></td>
									<td class="column-name">
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::BUILDER_SLUG . '&embed_id=' . $emb['id'] ) ); ?>" class="row-title">
											<?php echo esc_html( $emb['name'] ); ?>
										</a>
										<?php if ( ! empty( $emb['title'] ) && $emb['title'] !== $emb['name'] ) : ?>
											<span class="foliora-sub-title">(<?php echo esc_html( $emb['title'] ); ?>)</span>
										<?php endif; ?>
									</td>
									<td class="column-file">
										<span class="foliora-file-name" title="<?php echo esc_attr( $emb['file'] ); ?>">
											📄 <?php echo esc_html( wp_basename( (string) $emb['file'] ) ); ?>
										</span>
									</td>
									<td class="column-layout">
										<span class="foliora-tag-pill is-view"><?php echo esc_html( ucfirst( $emb['view'] ) ); ?></span>
										<span class="foliora-tag-pill is-theme"><?php echo esc_html( ucfirst( $emb['theme'] ) ); ?></span>
									</td>
									<td class="column-shortcode">
										<code class="foliora-shortcode-chip" data-copy="<?php echo esc_attr( $emb['shortcode'] ); ?>" title="<?php esc_attr_e( 'Click to copy shortcode', 'foliora' ); ?>">
											<?php echo esc_html( $emb['shortcode'] ); ?>
											<span class="dashicons dashicons-clipboard" aria-hidden="true"></span>
										</code>
									</td>
									<td class="column-date"><?php echo esc_html( $emb['date'] ); ?></td>
									<td class="column-actions" style="text-align: right;">
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::BUILDER_SLUG . '&embed_id=' . $emb['id'] ) ); ?>" class="button button-small">
											<?php esc_html_e( 'Edit', 'foliora' ); ?>
										</a>
										<button type="button" class="button button-small foliora-embed-duplicate-btn" data-id="<?php echo esc_attr( (string) $emb['id'] ); ?>" title="<?php esc_attr_e( 'Duplicate this embed', 'foliora' ); ?>">
											<?php esc_html_e( 'Duplicate', 'foliora' ); ?>
										</button>
										<button type="button" class="button button-small foliora-embed-delete-btn" data-id="<?php echo esc_attr( (string) $emb['id'] ); ?>" title="<?php esc_attr_e( 'Delete this embed', 'foliora' ); ?>" style="color:#b32d2e;">
											<?php esc_html_e( 'Delete', 'foliora' ); ?>
										</button>
									</td>
								</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
						<?php else : ?>
						<div class="foliora-embeds-empty-state">
							<span class="dashicons dashicons-portfolio" aria-hidden="true"></span>
							<h3><?php esc_html_e( 'No saved embeds yet', 'foliora' ); ?></h3>
							<p><?php esc_html_e( 'Create your first PDF embed in the Shortcode Builder and click "Save Embed & Get Shortcode" to generate a shortcode like [foliora id="1"].', 'foliora' ); ?></p>
							<button type="button" class="button button-primary foliora-switch-to-builder">
								<?php esc_html_e( 'Open Shortcode Builder', 'foliora' ); ?>
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
		$a11y_done = Foliora_A11y::is_checked( $id );
		if ( $a11y_on && $a11y_done && ! Foliora_A11y::is_tagged( $id ) ) {
			return 'attention';
		}
		if ( ! Foliora_Thumbnails::get_thumbnail_id( $id )
			|| ( $index_on && ! Foliora_SEO::is_indexed( $id ) )
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
		$a11y_on      = Foliora_A11y::checking_enabled();

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
				'foliora_content_search' => ( '' !== $search ),
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
				if ( Foliora_A11y::is_checked( $attachment->ID ) && ! Foliora_A11y::is_tagged( $attachment->ID ) ) {
					++$untagged_n;
				}
			}
		}

		$ledes = array(
			'documents' => __( 'Every PDF in your Media Library, ready to embed. No duplicate storage.', 'foliora' ),
			'docs'      => __( 'Shortcodes, blocks, deep links and page builders, all in one reference.', 'foliora' ),
			'features'  => __( 'Everything Foliora can do. Fast, local and accessible.', 'foliora' ),
		);
		?>
		<div class="wrap foliora-admin foliora-docs">
			<hr class="wp-header-end" />

			<div class="fa-hero fa-docs-hero">
				<div class="fa-hero__top">
					<div class="fa-hero__copy">
						<div class="fa-hero__kicker">
							<span class="fa-mark dashicons dashicons-book-alt" aria-hidden="true"></span>
							<h1 class="fa-hero__title"><?php esc_html_e( 'Docs & Features', 'foliora' ); ?></h1>
						</div>
						<p class="fa-hero__lede"><?php echo esc_html( $ledes[ $current_tab ] ); ?></p>
					</div>
					<div class="fa-hero__side">
						<div class="fa-hero__actions">
							<a class="fa-btn fa-btn--primary" href="<?php echo esc_url( admin_url( 'media-new.php' ) ); ?>">
								<span class="dashicons dashicons-upload" aria-hidden="true"></span>
								<?php esc_html_e( 'Upload PDF', 'foliora' ); ?>
							</a>
							<a class="fa-btn fa-btn--ghost-dark" href="<?php echo esc_url( $builder_url ); ?>">
								<span class="dashicons dashicons-admin-customizer" aria-hidden="true"></span>
								<?php esc_html_e( 'Open Shortcode Builder', 'foliora' ); ?>
							</a>
						</div>
					</div>
				</div>
			</div>

			<nav class="fa-segmented fa-docs-nav" aria-label="<?php esc_attr_e( 'Docs & Features sections', 'foliora' ); ?>">
				<a class="fa-segmented__btn<?php echo 'documents' === $current_tab ? ' is-active' : ''; ?>" href="<?php echo esc_url( $docs_url ); ?>"<?php echo 'documents' === $current_tab ? ' aria-current="page"' : ''; ?>>
					<span aria-hidden="true">📄</span>
					<span><?php esc_html_e( 'Documents Library', 'foliora' ); ?></span>
					<span class="fa-pill"><?php echo esc_html( (string) $this->count_pdfs() ); ?></span>
				</a>
				<a class="fa-segmented__btn<?php echo 'docs' === $current_tab ? ' is-active' : ''; ?>" href="<?php echo esc_url( $guide_url ); ?>"<?php echo 'docs' === $current_tab ? ' aria-current="page"' : ''; ?>>
					<span aria-hidden="true">📘</span>
					<span><?php esc_html_e( 'User Guide', 'foliora' ); ?></span>
				</a>
				<a class="fa-segmented__btn<?php echo 'features' === $current_tab ? ' is-active' : ''; ?>" href="<?php echo esc_url( $feat_url ); ?>"<?php echo 'features' === $current_tab ? ' aria-current="page"' : ''; ?>>
					<span aria-hidden="true">⭐</span>
					<span><?php esc_html_e( 'All Features', 'foliora' ); ?></span>
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
						<h2 class="fa-card__title" id="fa-howto-title"><?php esc_html_e( '4 ways to embed your documents', 'foliora' ); ?></h2>
					</div>
					<button type="button" class="fa-btn fa-btn--secondary fa-btn--sm fa-card__action" id="fa-howto-toggle" aria-expanded="true" aria-controls="fa-howto-body" data-show="<?php esc_attr_e( 'Show', 'foliora' ); ?>" data-hide="<?php esc_attr_e( 'Hide', 'foliora' ); ?>">
						<?php esc_html_e( 'Hide', 'foliora' ); ?>
					</button>
				</div>
				<div class="fa-howto__body" id="fa-howto-body">
					<div class="fa-card__body">
						<div class="fa-tiles fa-tiles--4">
							<div class="fa-tile">
								<span class="fa-num" aria-hidden="true">1</span>
								<div class="fa-tile__copy">
									<span class="fa-tile__title"><?php esc_html_e( 'Shortcode Builder', 'foliora' ); ?></span>
									<p><?php esc_html_e( 'Use the visual Shortcode Builder to configure 3D FlipBook, theme, and toolbar to generate clean [foliora id="1"] shortcodes.', 'foliora' ); ?></p>
								</div>
							</div>
							<div class="fa-tile">
								<span class="fa-num" aria-hidden="true">2</span>
								<div class="fa-tile__copy">
									<span class="fa-tile__title"><?php esc_html_e( 'Direct Shortcode Copy', 'foliora' ); ?></span>
									<p><?php esc_html_e( 'Click "Copy" on any row below to quickly copy [foliora file="..."] and paste it into classic editor, pages, or widgets.', 'foliora' ); ?></p>
								</div>
							</div>
							<div class="fa-tile">
								<span class="fa-num" aria-hidden="true">3</span>
								<div class="fa-tile__copy">
									<span class="fa-tile__title"><?php esc_html_e( '1-Click Test Page', 'foliora' ); ?></span>
									<p><?php esc_html_e( 'Hover over any document title below and click "Create test page" to publish and preview instantly in a new tab.', 'foliora' ); ?></p>
								</div>
							</div>
							<div class="fa-tile">
								<span class="fa-num" aria-hidden="true">4</span>
								<div class="fa-tile__copy">
									<span class="fa-tile__title"><?php esc_html_e( 'Gutenberg & oEmbed', 'foliora' ); ?></span>
									<p><?php esc_html_e( 'Insert the "Foliora Viewer" block, or simply paste any direct .pdf link on a new line to auto-embed.', 'foliora' ); ?></p>
								</div>
							</div>
						</div>
					</div>
					<div class="fa-infobar">
						<span class="fa-infobar__text">
							<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
							<?php esc_html_e( 'Opening this screen automatically generates first-page preview thumbnails, indexes PDF text for site search, and checks accessibility tags.', 'foliora' ); ?>
						</span>
						<a class="fa-link" href="<?php echo esc_url( $guide_url ); ?>"><?php esc_html_e( 'Full Documentation & Cheatsheet', 'foliora' ); ?> &rarr;</a>
					</div>
				</div>
			</section>

			<p class="foliora-thumb-status" hidden><?php esc_html_e( 'Generating thumbnails, indexing PDF text, and checking accessibility tags…', 'foliora' ); ?></p>

				<?php if ( $untagged_n ) : ?>
				<div class="notice notice-warning inline foliora-a11y-notice">
					<p>
						<?php
						echo esc_html(
							sprintf(
								/* translators: %s: number of untagged PDFs on this page. */
								_n(
									'%s PDF on this page is not tagged. Screen readers may not follow the intended reading order. Export tagged PDFs from Word, InDesign, or Acrobat.',
									'%s PDFs on this page are not tagged. Screen readers may not follow the intended reading order. Export tagged PDFs from Word, InDesign, or Acrobat.',
									$untagged_n,
									'foliora'
								),
								number_format_i18n( $untagged_n )
							)
						);
						?>
					</p>
				</div>
				<?php endif; ?>

			<section class="fa-card fa-docs-card" aria-label="<?php esc_attr_e( 'Documents', 'foliora' ); ?>">
				<div class="fa-docs-toolbar">
					<form method="get" class="fa-search foliora-docs-search" role="search">
						<input type="hidden" name="page" value="<?php echo esc_attr( self::DOCS_SLUG ); ?>" />
						<?php if ( 'all' !== $status ) : ?>
							<input type="hidden" name="status" value="<?php echo esc_attr( $status ); ?>" />
						<?php endif; ?>
						<label class="screen-reader-text" for="foliora-docs-search"><?php esc_html_e( 'Search documents', 'foliora' ); ?></label>
						<span class="fa-search__icon dashicons dashicons-search" aria-hidden="true"></span>
						<input type="search" class="fa-search__input" id="foliora-docs-search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search documents…', 'foliora' ); ?>" />
						<button type="submit" class="fa-btn fa-btn--secondary fa-btn--sm" id="search-submit"><?php esc_html_e( 'Search', 'foliora' ); ?></button>
					</form>

					<div class="fa-filters" role="group" aria-label="<?php esc_attr_e( 'Filter by status', 'foliora' ); ?>">
						<?php
						$filters = array(
							'all'       => __( 'All', 'foliora' ),
							'ready'     => __( 'Ready', 'foliora' ),
							'pending'   => __( 'Pending', 'foliora' ),
							'attention' => __( 'Needs attention', 'foliora' ),
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
								_n( '%s document', '%s documents', $total, 'foliora' ),
								number_format_i18n( $total )
							)
						);
						?>
					</span>
				</div>

				<?php if ( $query && $query->have_posts() ) : ?>
				<div class="fa-table-wrap">
				<table class="foliora-docs-table fa-table">
					<thead>
						<tr>
							<th scope="col" class="foliora-docs-thumb-col"><?php esc_html_e( 'Preview', 'foliora' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Document', 'foliora' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Search', 'foliora' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Accessibility', 'foliora' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Shortcode', 'foliora' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $query->posts as $attachment ) : ?>
							<?php
							$url   = wp_get_attachment_url( $attachment->ID );
							$path  = get_attached_file( $attachment->ID );
							$bytes = ( $path && file_exists( $path ) ) ? filesize( $path ) : 0;
							$size  = $bytes ? size_format( $bytes ) : '—';
							$code  = sprintf( '[foliora file="%s"]', esc_url_raw( $url ) );
							$edit  = admin_url( 'upload.php?item=' . (int) $attachment->ID );
							$title = get_the_title( $attachment );
							if ( '' === $title ) {
								$title = wp_basename( $url );
							}
							$thumb_id    = Foliora_Thumbnails::get_thumbnail_id( $attachment->ID );
							$indexed     = Foliora_SEO::is_indexed( $attachment->ID );
							$has_text    = $indexed && '' !== Foliora_SEO::get_extracted_text( $attachment->ID );
							$a11y_done   = Foliora_A11y::is_checked( $attachment->ID );
							$a11y_tagged = $a11y_done && Foliora_A11y::is_tagged( $attachment->ID );
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
								<td class="foliora-docs-thumb-col fa-col-thumb">
									<?php if ( $thumb_id ) : ?>
										<?php
										echo wp_get_attachment_image(
											$thumb_id,
											'thumbnail',
											false,
											array(
												'class' => 'foliora-docs-thumb-img',
												'alt'   => $title,
											)
										);
										?>
									<?php else : ?>
										<span
											class="foliora-thumb-placeholder"
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
										<button type="button" class="fa-link foliora-doc-create-test-btn" data-pdf-url="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'Create test page', 'foliora' ); ?></button>
										<a class="fa-link" href="<?php echo esc_url( $open_url ); ?>"><?php esc_html_e( 'Open in Builder', 'foliora' ); ?></a>
										<a class="fa-link" href="<?php echo esc_url( $edit ); ?>"><?php esc_html_e( 'View in Media', 'foliora' ); ?></a>
										<a class="fa-link" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open file', 'foliora' ); ?></a>
									</div>
								</td>
								<td class="fa-col-search">
									<?php if ( $indexed && $has_text ) : ?>
										<span class="fa-status foliora-index-status is-ready" title="<?php esc_attr_e( 'Text extracted. WordPress search can find words in this PDF.', 'foliora' ); ?>"><?php esc_html_e( 'Indexed', 'foliora' ); ?></span>
									<?php elseif ( $indexed ) : ?>
										<span class="fa-status foliora-index-status is-empty" title="<?php esc_attr_e( 'No selectable text was found (a scanned image PDF), so it cannot be searched.', 'foliora' ); ?>"><?php esc_html_e( 'No text', 'foliora' ); ?></span>
									<?php elseif ( ! $index_on ) : ?>
										<span class="fa-status is-empty" title="<?php esc_attr_e( 'PDF text indexing is turned off in Settings.', 'foliora' ); ?>"><?php esc_html_e( 'Off', 'foliora' ); ?></span>
									<?php else : ?>
										<span class="fa-status foliora-index-status is-pending" title="<?php esc_attr_e( 'Waiting for text extraction. Keep this screen open.', 'foliora' ); ?>"><?php esc_html_e( 'Pending', 'foliora' ); ?></span>
									<?php endif; ?>
								</td>
								<td class="fa-col-a11y">
									<?php if ( $a11y_done && $a11y_tagged ) : ?>
										<span class="fa-status foliora-a11y-status is-ready" title="<?php esc_attr_e( 'Tagged PDF. Screen readers can follow its structure.', 'foliora' ); ?>"><?php esc_html_e( 'Tagged', 'foliora' ); ?></span>
									<?php elseif ( $a11y_done ) : ?>
										<span
											class="fa-status foliora-a11y-status is-warn"
											title="<?php esc_attr_e( 'This PDF has no accessibility tags. Export it from Word, InDesign, or Acrobat as a tagged PDF so screen readers can follow headings and reading order.', 'foliora' ); ?>"
										><?php esc_html_e( 'Untagged', 'foliora' ); ?></span>
									<?php elseif ( ! $a11y_on ) : ?>
										<span class="fa-status is-empty" title="<?php esc_attr_e( 'Accessibility checks are turned off in Settings.', 'foliora' ); ?>"><?php esc_html_e( 'Off', 'foliora' ); ?></span>
									<?php else : ?>
										<span class="fa-status foliora-a11y-status is-pending" title="<?php esc_attr_e( 'Waiting for the accessibility check. Keep this screen open.', 'foliora' ); ?>"><?php esc_html_e( 'Pending', 'foliora' ); ?></span>
									<?php endif; ?>
								</td>
								<td class="fa-col-code">
									<div class="fa-code-chip">
										<code class="foliora-docs-shortcode" title="<?php echo esc_attr( $code ); ?>"><?php echo esc_html( $code ); ?></code>
										<button type="button" class="fa-icon-btn foliora-copy-btn" data-foliora-copy="<?php echo esc_attr( $code ); ?>" aria-label="<?php esc_attr_e( 'Copy shortcode', 'foliora' ); ?>" title="<?php esc_attr_e( 'Copy shortcode', 'foliora' ); ?>">
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
					<div class="fa-empty foliora-docs-empty">
						<span class="fa-empty__tile dashicons dashicons-media-document" aria-hidden="true"></span>
						<?php if ( '' !== $search ) : ?>
							<p class="fa-empty__title">
								<?php
								/* translators: %s: the search term. */
								echo esc_html( sprintf( __( 'No documents match "%s"', 'foliora' ), $search ) );
								?>
							</p>
							<a class="fa-btn fa-btn--primary" href="<?php echo esc_url( $docs_url ); ?>"><?php esc_html_e( 'Clear search', 'foliora' ); ?></a>
						<?php elseif ( 'all' !== $status ) : ?>
							<p class="fa-empty__title"><?php esc_html_e( 'No documents in this status.', 'foliora' ); ?></p>
							<a class="fa-btn fa-btn--secondary" href="<?php echo esc_url( $docs_url ); ?>"><?php esc_html_e( 'Show all documents', 'foliora' ); ?></a>
						<?php else : ?>
							<p class="fa-empty__title"><?php esc_html_e( 'No PDFs yet', 'foliora' ); ?></p>
							<a class="fa-btn fa-btn--primary" href="<?php echo esc_url( admin_url( 'media-new.php' ) ); ?>"><?php esc_html_e( 'Upload PDF', 'foliora' ); ?></a>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</section>
			<?php endif; ?>
			<div class="foliora-toast" id="foliora-toast" hidden role="status"></div>
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
		<div class="foliora-guide-wrap">
			<section class="fa-card fa-guide-intro">
				<div class="fa-card__head">
					<span class="fa-card__icon dashicons dashicons-book-alt" aria-hidden="true"></span>
					<div class="fa-card__titles">
						<h2 class="fa-card__title"><?php esc_html_e( 'Foliora User Guide & Complete Reference', 'foliora' ); ?></h2>
						<p class="fa-card__sub"><?php esc_html_e( 'Learn how to easily embed PDFs, customize 3D FlipBook view modes, use shortcodes, integrate with page builders, and optimize for SEO and speed.', 'foliora' ); ?></p>
					</div>
				</div>
			</section>

			<nav class="fa-subnav" id="fa-guide-nav" aria-label="<?php esc_attr_e( 'Guide sections', 'foliora' ); ?>">
				<a href="#guide-quickstart" class="fa-chip fa-chip--light"><span class="dashicons dashicons-controls-play" aria-hidden="true"></span> <?php esc_html_e( 'Quick Start', 'foliora' ); ?></a>
				<a href="#guide-shortcodes" class="fa-chip fa-chip--light"><span class="dashicons dashicons-editor-code" aria-hidden="true"></span> <?php esc_html_e( '[foliora] API', 'foliora' ); ?></a>
				<a href="#guide-library" class="fa-chip fa-chip--light"><span class="dashicons dashicons-grid-view" aria-hidden="true"></span> <?php esc_html_e( '[foliora_library]', 'foliora' ); ?></a>
				<a href="#guide-deeplink" class="fa-chip fa-chip--light"><span class="dashicons dashicons-admin-links" aria-hidden="true"></span> <?php esc_html_e( 'Deep Linking', 'foliora' ); ?></a>
				<a href="#guide-builders" class="fa-chip fa-chip--light"><span class="dashicons dashicons-layout" aria-hidden="true"></span> <?php esc_html_e( 'Page Builders', 'foliora' ); ?></a>
				<a href="#guide-faq" class="fa-chip fa-chip--light"><span class="dashicons dashicons-sos" aria-hidden="true"></span> <?php esc_html_e( 'FAQs & Tips', 'foliora' ); ?></a>
			</nav>

			<!-- 1. Quick Start -->
			<section id="guide-quickstart" class="foliora-guide-section">
				<div class="foliora-guide-section-head">
					<div class="foliora-guide-sec-icon-wrap">
						<span class="dashicons dashicons-controls-play foliora-guide-icon-sec" aria-hidden="true"></span>
					</div>
					<div>
						<h3><?php esc_html_e( '1. Quick Start: 3 Easy Ways to Embed', 'foliora' ); ?></h3>
						<p class="foliora-guide-subtitle"><?php esc_html_e( 'Choose whichever workflow fits your editing style.', 'foliora' ); ?></p>
					</div>
				</div>
				<div class="fa-tiles fa-tiles--3">
					<div class="fa-tile">
						<div class="fa-num" aria-hidden="true">A</div>
						<div class="fa-tile__copy">
							<h4><?php esc_html_e( 'Shortcode (Anywhere)', 'foliora' ); ?></h4>
							<p><?php esc_html_e( 'Copy [foliora file="..."] from the Documents Library tab and paste it into any post, page, widget, or template.', 'foliora' ); ?></p>
							<div class="foliora-mini-code-box">
								<code>[foliora file="https://site.com/doc.pdf"]</code>
								<button type="button" class="foliora-copy-snippet-btn" data-foliora-copy='[foliora file="https://site.com/doc.pdf"]' title="<?php esc_attr_e( 'Copy shortcode', 'foliora' ); ?>">
									<span class="dashicons dashicons-admin-page" aria-hidden="true"></span>
								</button>
							</div>
						</div>
					</div>
					<div class="fa-tile">
						<div class="fa-num" aria-hidden="true">B</div>
						<div class="fa-tile__copy">
							<h4><?php esc_html_e( 'Gutenberg Native Block', 'foliora' ); ?></h4>
							<p><?php esc_html_e( 'In the Block Editor, insert the "Foliora Viewer" block. Pick any PDF from your Media Library with instant live rendering.', 'foliora' ); ?></p>
							<div class="foliora-step-chip is-blue">
								<span class="dashicons dashicons-block-default" aria-hidden="true"></span>
								<span><?php esc_html_e( 'Visual Editor Block', 'foliora' ); ?></span>
							</div>
						</div>
					</div>
					<div class="fa-tile">
						<div class="fa-num" aria-hidden="true">C</div>
						<div class="fa-tile__copy">
							<h4><?php esc_html_e( 'Auto oEmbed (Paste URL)', 'foliora' ); ?></h4>
							<p><?php esc_html_e( 'Simply paste a direct .pdf link on a new line in your editor. WordPress automatically transforms it into an interactive reader.', 'foliora' ); ?></p>
							<div class="foliora-step-chip is-green">
								<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
								<span><?php esc_html_e( 'Zero Shortcode Required', 'foliora' ); ?></span>
							</div>
						</div>
					</div>
				</div>
			</section>

			<!-- 2. [foliora] Shortcode Cheatsheet -->
			<section id="guide-shortcodes" class="foliora-guide-section">
				<div class="foliora-guide-section-head">
					<div class="foliora-guide-sec-icon-wrap">
						<span class="dashicons dashicons-editor-code foliora-guide-icon-sec" aria-hidden="true"></span>
					</div>
					<div>
						<h3><?php esc_html_e( '2. [foliora] Shortcode Parameters & Attributes', 'foliora' ); ?></h3>
						<p class="foliora-guide-subtitle"><?php esc_html_e( 'Customize every aspect of your PDF viewer with these parameters:', 'foliora' ); ?></p>
					</div>
				</div>

				<div class="foliora-api-table-card">
					<table class="foliora-api-table">
						<thead>
							<tr>
								<th scope="col" style="width: 15%;"><?php esc_html_e( 'Attribute', 'foliora' ); ?></th>
								<th scope="col" style="width: 18%;"><?php esc_html_e( 'Type & Default', 'foliora' ); ?></th>
								<th scope="col" style="width: 42%;"><?php esc_html_e( 'Description & Options', 'foliora' ); ?></th>
								<th scope="col" style="width: 25%;"><?php esc_html_e( 'Example', 'foliora' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<tr style="background: rgba(79, 70, 229, 0.04);">
								<td>
									<div class="foliora-attr-cell">
										<code class="foliora-param-code" style="color: #4f46e5; border-color: #c7d2fe;">id</code>
										<span class="foliora-pill-req" style="background: #e0e7ff; color: #3730a3; border-color: #c7d2fe;"><?php esc_html_e( 'Recommended', 'foliora' ); ?></span>
									</div>
								</td>
								<td>
									<span class="foliora-type-pill">Integer</span>
									<span class="foliora-default-pill"><?php esc_html_e( 'Saved Embed ID', 'foliora' ); ?></span>
								</td>
								<td>
									<p class="foliora-tbl-desc"><strong><?php esc_html_e( 'Clean Saved Embed Shortcode:', 'foliora' ); ?></strong> <?php esc_html_e( 'Encapsulates all PDF settings, dimensions, themes, 3D flipbook mode, and toolbar options generated from the Shortcode Builder into a clean, short code.', 'foliora' ); ?></p>
								</td>
								<td>
									<div class="foliora-code-chip-copy" data-foliora-copy='[foliora id="1"]'>
										<code>[foliora id="1"]</code>
										<span class="dashicons dashicons-admin-page" aria-hidden="true"></span>
									</div>
								</td>
							</tr>
							<tr>
								<td>
									<div class="foliora-attr-cell">
										<code class="foliora-param-code">file</code>
										<span class="foliora-pill-req"><?php esc_html_e( 'Required unless id is set', 'foliora' ); ?></span>
									</div>
								</td>
								<td>
									<span class="foliora-type-pill">URL string</span>
								</td>
								<td>
									<p class="foliora-tbl-desc"><?php esc_html_e( 'Direct URL to your PDF document (local Media Library or CORS-enabled remote host).', 'foliora' ); ?></p>
								</td>
								<td>
									<div class="foliora-code-chip-copy" data-foliora-copy='file="https://example.com/doc.pdf"'>
										<code>file="https://example.com/doc.pdf"</code>
										<span class="dashicons dashicons-admin-page" aria-hidden="true"></span>
									</div>
								</td>
							</tr>
							<tr>
								<td>
									<div class="foliora-attr-cell">
										<code class="foliora-param-code">view</code>
										<span class="foliora-pill-opt"><?php esc_html_e( 'Optional', 'foliora' ); ?></span>
									</div>
								</td>
								<td>
									<span class="foliora-type-pill">Enum</span>
									<span class="foliora-default-pill">default: "page"</span>
								</td>
								<td>
									<p class="foliora-tbl-desc"><strong><?php esc_html_e( 'Reading Layout Mode:', 'foliora' ); ?></strong></p>
									<ul class="foliora-option-list">
										<li><code class="foliora-val-code">flip</code> &mdash; <?php esc_html_e( '3D realistic FlipBook with Web Audio page turn sound.', 'foliora' ); ?></li>
										<li><code class="foliora-val-code">scroll</code> &mdash; <?php esc_html_e( 'Continuous vertical scrolling feed.', 'foliora' ); ?></li>
										<li><code class="foliora-val-code">spread</code> &mdash; <?php esc_html_e( 'Two-page magazine book spread.', 'foliora' ); ?></li>
										<li><code class="foliora-val-code">page</code> &mdash; <?php esc_html_e( 'Standard single page reader.', 'foliora' ); ?></li>
									</ul>
								</td>
								<td>
									<div class="foliora-code-chip-copy" data-foliora-copy='view="flip"'>
										<code>view="flip"</code>
										<span class="dashicons dashicons-admin-page" aria-hidden="true"></span>
									</div>
								</td>
							</tr>
							<tr>
								<td>
									<div class="foliora-attr-cell">
										<code class="foliora-param-code">width</code>
										<span class="foliora-pill-opt"><?php esc_html_e( 'Optional', 'foliora' ); ?></span>
									</div>
								</td>
								<td>
									<span class="foliora-type-pill">CSS Unit</span>
									<span class="foliora-default-pill">default: "100%"</span>
								</td>
								<td>
									<p class="foliora-tbl-desc"><?php esc_html_e( 'Viewer container width. Supports percentage (%), pixels (px), or viewport width (vw).', 'foliora' ); ?></p>
								</td>
								<td>
									<div class="foliora-code-chip-copy" data-foliora-copy='width="850px"'>
										<code>width="850px"</code>
										<span class="dashicons dashicons-admin-page" aria-hidden="true"></span>
									</div>
								</td>
							</tr>
							<tr>
								<td>
									<div class="foliora-attr-cell">
										<code class="foliora-param-code">height</code>
										<span class="foliora-pill-opt"><?php esc_html_e( 'Optional', 'foliora' ); ?></span>
									</div>
								</td>
								<td>
									<span class="foliora-type-pill">CSS Unit</span>
									<span class="foliora-default-pill">default: "420px"</span>
								</td>
								<td>
									<p class="foliora-tbl-desc"><?php esc_html_e( 'Viewer container height. Supports pixels (px), viewport height (vh), or em/rem.', 'foliora' ); ?></p>
								</td>
								<td>
									<div class="foliora-code-chip-copy" data-foliora-copy='height="650px"'>
										<code>height="650px"</code>
										<span class="dashicons dashicons-admin-page" aria-hidden="true"></span>
									</div>
								</td>
							</tr>
							<tr>
								<td>
									<div class="foliora-attr-cell">
										<code class="foliora-param-code">page</code>
										<span class="foliora-pill-opt"><?php esc_html_e( 'Optional', 'foliora' ); ?></span>
									</div>
								</td>
								<td>
									<span class="foliora-type-pill">Integer</span>
									<span class="foliora-default-pill">default: 1</span>
								</td>
								<td>
									<p class="foliora-tbl-desc"><?php esc_html_e( 'Initial starting page number when the document opens.', 'foliora' ); ?></p>
								</td>
								<td>
									<div class="foliora-code-chip-copy" data-foliora-copy='page="5"'>
										<code>page="5"</code>
										<span class="dashicons dashicons-admin-page" aria-hidden="true"></span>
									</div>
								</td>
							</tr>
							<tr>
								<td>
									<div class="foliora-attr-cell">
										<code class="foliora-param-code">download</code>
										<span class="foliora-pill-opt"><?php esc_html_e( 'Optional', 'foliora' ); ?></span>
									</div>
								</td>
								<td>
									<span class="foliora-type-pill">Boolean</span>
									<span class="foliora-default-pill">default: true</span>
								</td>
								<td>
									<p class="foliora-tbl-desc"><?php esc_html_e( 'Show or hide the direct PDF download button in the toolbar.', 'foliora' ); ?></p>
								</td>
								<td>
									<div class="foliora-code-chip-copy" data-foliora-copy='download="false"'>
										<code>download="false"</code>
										<span class="dashicons dashicons-admin-page" aria-hidden="true"></span>
									</div>
								</td>
							</tr>
							<tr>
								<td>
									<div class="foliora-attr-cell">
										<code class="foliora-param-code">print</code>
										<span class="foliora-pill-opt"><?php esc_html_e( 'Optional', 'foliora' ); ?></span>
									</div>
								</td>
								<td>
									<span class="foliora-type-pill">Boolean</span>
									<span class="foliora-default-pill">default: true</span>
								</td>
								<td>
									<p class="foliora-tbl-desc"><?php esc_html_e( 'Show or hide the print button in the toolbar.', 'foliora' ); ?></p>
								</td>
								<td>
									<div class="foliora-code-chip-copy" data-foliora-copy='print="false"'>
										<code>print="false"</code>
										<span class="dashicons dashicons-admin-page" aria-hidden="true"></span>
									</div>
								</td>
							</tr>
							<tr>
								<td>
									<div class="foliora-attr-cell">
										<code class="foliora-param-code">search</code>
										<span class="foliora-pill-opt"><?php esc_html_e( 'Optional', 'foliora' ); ?></span>
									</div>
								</td>
								<td>
									<span class="foliora-type-pill">Boolean</span>
									<span class="foliora-default-pill">default: true</span>
								</td>
								<td>
									<p class="foliora-tbl-desc"><?php esc_html_e( 'Enable or disable the in-document text search (Find) toolbar tool.', 'foliora' ); ?></p>
								</td>
								<td>
									<div class="foliora-code-chip-copy" data-foliora-copy='search="false"'>
										<code>search="false"</code>
										<span class="dashicons dashicons-admin-page" aria-hidden="true"></span>
									</div>
								</td>
							</tr>
							<tr>
								<td>
									<div class="foliora-attr-cell">
										<code class="foliora-param-code">title</code>
										<span class="foliora-pill-opt"><?php esc_html_e( 'Optional', 'foliora' ); ?></span>
									</div>
								</td>
								<td>
									<span class="foliora-type-pill">String</span>
								</td>
								<td>
									<p class="foliora-tbl-desc"><?php esc_html_e( 'Optional caption heading displayed directly above the viewer frame.', 'foliora' ); ?></p>
								</td>
								<td>
									<div class="foliora-code-chip-copy" data-foliora-copy='title="Annual Report 2026"'>
										<code>title="Annual Report 2026"</code>
										<span class="dashicons dashicons-admin-page" aria-hidden="true"></span>
									</div>
								</td>
							</tr>
							<tr>
								<td>
									<div class="foliora-attr-cell">
										<code class="foliora-param-code">hide</code>
										<span class="foliora-pill-opt"><?php esc_html_e( 'Optional', 'foliora' ); ?></span>
									</div>
								</td>
								<td>
									<span class="foliora-type-pill">CSV List</span>
								</td>
								<td>
									<p class="foliora-tbl-desc"><?php esc_html_e( 'Hide specific toolbar buttons (comma-separated):', 'foliora' ); ?></p>
									<p class="foliora-csv-pills">
										<span>nav</span> <span>zoom</span> <span>fit</span> <span>rotate</span> <span>view</span> <span>pan</span> <span>download</span> <span>print</span> <span>search</span> <span>theme</span> <span>share</span> <span>fullscreen</span>
									</p>
								</td>
								<td>
									<div class="foliora-code-chip-copy" data-foliora-copy='hide="download,print,share"'>
										<code>hide="download,print,share"</code>
										<span class="dashicons dashicons-admin-page" aria-hidden="true"></span>
									</div>
								</td>
							</tr>
						</tbody>
					</table>
				</div>

				<!-- Ready-to-Use Shortcode Presets -->
				<div class="foliora-presets-shelf">
					<div class="foliora-presets-head">
						<span class="dashicons dashicons-superhero foliora-presets-icon" aria-hidden="true"></span>
						<div>
							<h4><?php esc_html_e( 'Ready-to-Use Copy Presets', 'foliora' ); ?></h4>
							<p><?php esc_html_e( 'Click the copy button on any preset to copy the full shortcode:', 'foliora' ); ?></p>
						</div>
					</div>
					<div class="foliora-presets-grid">
						<div class="foliora-preset-card">
							<div class="foliora-preset-card-top">
								<span class="foliora-preset-tag is-purple"><?php esc_html_e( '3D FlipBook Mode', 'foliora' ); ?></span>
								<span class="foliora-preset-hint"><?php esc_html_e( 'Realistic Sound & Perspective', 'foliora' ); ?></span>
							</div>
							<div class="foliora-preset-code-row">
								<code>[foliora file="https://site.com/catalog.pdf" view="flip" height="600px"]</code>
								<button type="button" class="foliora-copy-preset-btn" data-foliora-copy='[foliora file="https://site.com/catalog.pdf" view="flip" height="600px"]'>
									<span class="dashicons dashicons-admin-page" aria-hidden="true"></span>
									<span><?php esc_html_e( 'Copy', 'foliora' ); ?></span>
								</button>
							</div>
						</div>

						<div class="foliora-preset-card">
							<div class="foliora-preset-card-top">
								<span class="foliora-preset-tag is-blue"><?php esc_html_e( 'Vertical Continuous Scroll', 'foliora' ); ?></span>
								<span class="foliora-preset-hint"><?php esc_html_e( 'Ideal for Long Reports & Ebooks', 'foliora' ); ?></span>
							</div>
							<div class="foliora-preset-code-row">
								<code>[foliora file="https://site.com/ebook.pdf" view="scroll" height="700px"]</code>
								<button type="button" class="foliora-copy-preset-btn" data-foliora-copy='[foliora file="https://site.com/ebook.pdf" view="scroll" height="700px"]'>
									<span class="dashicons dashicons-admin-page" aria-hidden="true"></span>
									<span><?php esc_html_e( 'Copy', 'foliora' ); ?></span>
								</button>
							</div>
						</div>

						<div class="foliora-preset-card">
							<div class="foliora-preset-card-top">
								<span class="foliora-preset-tag is-slate"><?php esc_html_e( 'Locked Down Presentation', 'foliora' ); ?></span>
								<span class="foliora-preset-hint"><?php esc_html_e( 'No Direct Download or Printing', 'foliora' ); ?></span>
							</div>
							<div class="foliora-preset-code-row">
								<code>[foliora file="https://site.com/slides.pdf" download="false" print="false" view="spread"]</code>
								<button type="button" class="foliora-copy-preset-btn" data-foliora-copy='[foliora file="https://site.com/slides.pdf" download="false" print="false" view="spread"]'>
									<span class="dashicons dashicons-admin-page" aria-hidden="true"></span>
									<span><?php esc_html_e( 'Copy', 'foliora' ); ?></span>
								</button>
							</div>
						</div>
					</div>
				</div>
			</section>

			<!-- 3. [foliora_library] Grid Gallery Reference -->
			<section id="guide-library" class="foliora-guide-section">
				<div class="foliora-guide-section-head">
					<div class="foliora-guide-sec-icon-wrap">
						<span class="dashicons dashicons-grid-view foliora-guide-icon-sec" aria-hidden="true"></span>
					</div>
					<div>
						<h3><?php esc_html_e( '3. [foliora_library] Document Gallery Shortcode', 'foliora' ); ?></h3>
						<p class="foliora-guide-subtitle"><?php esc_html_e( 'Display all your Media Library PDFs in a responsive, searchable grid gallery with lightbox reading modal:', 'foliora' ); ?></p>
					</div>
				</div>
				
				<div class="foliora-api-table-card">
					<table class="foliora-api-table">
						<thead>
							<tr>
								<th scope="col" style="width: 15%;"><?php esc_html_e( 'Attribute', 'foliora' ); ?></th>
								<th scope="col" style="width: 20%;"><?php esc_html_e( 'Values / Default', 'foliora' ); ?></th>
								<th scope="col" style="width: 65%;"><?php esc_html_e( 'Description & Behavior', 'foliora' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td><code class="foliora-param-code">columns</code></td>
								<td>
									<span class="foliora-type-pill">2 | 3 | 4</span>
									<span class="foliora-default-pill">default: 3</span>
								</td>
								<td><p class="foliora-tbl-desc"><?php esc_html_e( 'Number of document grid columns on desktop viewports (automatically reflows on mobile).', 'foliora' ); ?></p></td>
							</tr>
							<tr>
								<td><code class="foliora-param-code">per_page</code></td>
								<td>
									<span class="foliora-type-pill">Integer</span>
									<span class="foliora-default-pill">default: 12</span>
								</td>
								<td><p class="foliora-tbl-desc"><?php esc_html_e( 'How many PDF cards to display per page before showing pagination controls.', 'foliora' ); ?></p></td>
							</tr>
							<tr>
								<td><code class="foliora-param-code">orderby</code></td>
								<td>
									<span class="foliora-type-pill">date | title | modified</span>
									<span class="foliora-default-pill">default: "date"</span>
								</td>
								<td><p class="foliora-tbl-desc"><?php esc_html_e( 'Sorting order for the gallery catalog items.', 'foliora' ); ?></p></td>
							</tr>
							<tr>
								<td><code class="foliora-param-code">search</code></td>
								<td>
									<span class="foliora-type-pill">Boolean</span>
									<span class="foliora-default-pill">default: true</span>
								</td>
								<td><p class="foliora-tbl-desc"><?php esc_html_e( 'Show real-time instant search input box above the document gallery.', 'foliora' ); ?></p></td>
							</tr>
						</tbody>
					</table>
				</div>

				<div class="foliora-preset-card foliora-preset-single">
					<div class="foliora-preset-card-top">
						<span class="foliora-preset-tag is-teal"><?php esc_html_e( 'Interactive Gallery Preset', 'foliora' ); ?></span>
						<span class="foliora-preset-hint"><?php esc_html_e( 'Searchable 4-column document directory', 'foliora' ); ?></span>
					</div>
					<div class="foliora-preset-code-row">
						<code>[foliora_library columns="4" per_page="16" orderby="title" search="true"]</code>
						<button type="button" class="foliora-copy-preset-btn" data-foliora-copy='[foliora_library columns="4" per_page="16" orderby="title" search="true"]'>
							<span class="dashicons dashicons-admin-page" aria-hidden="true"></span>
							<span><?php esc_html_e( 'Copy', 'foliora' ); ?></span>
						</button>
					</div>
				</div>
			</section>

			<!-- 4. Deep Linking & Page Anchors -->
			<section id="guide-deeplink" class="foliora-guide-section">
				<div class="foliora-guide-section-head">
					<div class="foliora-guide-sec-icon-wrap">
						<span class="dashicons dashicons-admin-links foliora-guide-icon-sec" aria-hidden="true"></span>
					</div>
					<div>
						<h3><?php esc_html_e( '4. Deep Linking & URL Hashes (#page=X)', 'foliora' ); ?></h3>
						<p class="foliora-guide-subtitle"><?php esc_html_e( 'Send visitors directly to any specific page inside an embedded document by appending hash anchors:', 'foliora' ); ?></p>
					</div>
				</div>
				<div class="foliora-deeplink-grid">
					<div class="foliora-deeplink-box">
						<div class="foliora-deeplink-code">
							<code>https://yourdomain.com/handbook/<strong>#page=14</strong></code>
						</div>
						<p><?php esc_html_e( 'Loads the handbook page and automatically jumps directly to page 14 with smooth animation.', 'foliora' ); ?></p>
					</div>
					<div class="foliora-deeplink-box">
						<div class="foliora-deeplink-code">
							<code>https://yourdomain.com/handbook/<strong>#foliora-page=5</strong></code>
						</div>
						<p><?php esc_html_e( 'Alternative namespaced syntax to avoid conflicting with other on-page anchors or sliders.', 'foliora' ); ?></p>
					</div>
				</div>
			</section>

			<!-- 5. Page Builders Integration -->
			<section id="guide-builders" class="foliora-guide-section">
				<div class="foliora-guide-section-head">
					<div class="foliora-guide-sec-icon-wrap">
						<span class="dashicons dashicons-layout foliora-guide-icon-sec" aria-hidden="true"></span>
					</div>
					<div>
						<h3><?php esc_html_e( '5. Page Builders & Compatibility', 'foliora' ); ?></h3>
						<p class="foliora-guide-subtitle"><?php esc_html_e( 'Native visual blocks and widgets bundled for your favorite WordPress builders:', 'foliora' ); ?></p>
					</div>
				</div>
				<div class="fa-tiles fa-tiles--2">
					<div class="fa-tile fa-tile--col">
						<div class="fa-tile__head">
							<div class="fa-tile-icon is-indigo"><span class="dashicons dashicons-block-default"></span></div>
							<h4><?php esc_html_e( 'Gutenberg Block Editor', 'foliora' ); ?></h4>
						</div>
						<p><?php esc_html_e( 'Search for "Foliora Viewer" or "Foliora Library" in the block inserter. Customize dimensions, starting page, view mode (including 3D FlipBook), and download toggles directly in the block sidebar.', 'foliora' ); ?></p>
					</div>
					<div class="fa-tile fa-tile--col">
						<div class="fa-tile__head">
							<div class="fa-tile-icon is-violet"><span class="dashicons dashicons-art"></span></div>
							<h4><?php esc_html_e( 'Elementor Page Builder', 'foliora' ); ?></h4>
						</div>
						<p><?php esc_html_e( 'Find "Foliora PDF Viewer" and "Foliora PDF Library" widgets in the Elementor panel. Full visual styling controls with zero extra setup.', 'foliora' ); ?></p>
					</div>
					<div class="fa-tile fa-tile--col">
						<div class="fa-tile__head">
							<div class="fa-tile-icon is-sky"><span class="dashicons dashicons-welcome-widgets-menus"></span></div>
							<h4><?php esc_html_e( 'Divi & Beaver Builder', 'foliora' ); ?></h4>
						</div>
						<p><?php esc_html_e( 'Native Divi modules and Beaver Builder modules are bundled right in Foliora. Select your PDF directly inside their builder interfaces.', 'foliora' ); ?></p>
					</div>
					<div class="fa-tile fa-tile--col">
						<div class="fa-tile__head">
							<div class="fa-tile-icon is-green"><span class="dashicons dashicons-translation"></span></div>
							<h4><?php esc_html_e( 'WPML & Polylang Multi-Language', 'foliora' ); ?></h4>
						</div>
						<p><?php esc_html_e( 'When you translate Media Library PDFs, Foliora automatically swaps the document URL for the active visitor language on that page.', 'foliora' ); ?></p>
					</div>
				</div>
			</section>

			<!-- 6. Troubleshooting & FAQs -->
			<section id="guide-faq" class="foliora-guide-section">
				<div class="foliora-guide-section-head">
					<div class="foliora-guide-sec-icon-wrap">
						<span class="dashicons dashicons-sos foliora-guide-icon-sec" aria-hidden="true"></span>
					</div>
					<div>
						<h3><?php esc_html_e( '6. Troubleshooting & Best Practices', 'foliora' ); ?></h3>
						<p class="foliora-guide-subtitle"><?php esc_html_e( 'Answers to frequent configuration questions and hosting best practices:', 'foliora' ); ?></p>
					</div>
				</div>
				<div class="foliora-faq-list">
					<details class="foliora-faq-item" open>
						<summary>
							<span class="foliora-faq-q"><?php esc_html_e( 'How do I resolve CORS (Cross-Origin) errors when loading remote PDFs?', 'foliora' ); ?></span>
							<span class="dashicons dashicons-arrow-down-alt2 foliora-faq-arrow" aria-hidden="true"></span>
						</summary>
						<div class="foliora-faq-answer">
							<p><?php esc_html_e( 'Browsers block scripts from reading PDFs hosted on other domains unless that remote server allows CORS. The easiest fix is uploading the PDF directly to your WordPress Media Library. If you must use a remote host, configure the header Access-Control-Allow-Origin: * on that remote server.', 'foliora' ); ?></p>
						</div>
					</details>
					<details class="foliora-faq-item">
						<summary>
							<span class="foliora-faq-q"><?php esc_html_e( 'Why are PDF.js scripts excluded from cache plugins?', 'foliora' ); ?></span>
							<span class="dashicons dashicons-arrow-down-alt2 foliora-faq-arrow" aria-hidden="true"></span>
						</summary>
						<div class="foliora-faq-answer">
							<p><?php esc_html_e( 'PDF.js requires its worker script (pdf.worker.min.js) to load in an isolated worker thread. Combining, deferring, or minifying it with other theme JS breaks web worker loading. Foliora automatically sets data-no-optimize rules for WP Rocket, LiteSpeed Cache, Autoptimize, and W3 Total Cache.', 'foliora' ); ?></p>
						</div>
					</details>
					<details class="foliora-faq-item">
						<summary>
							<span class="foliora-faq-q"><?php esc_html_e( 'How do thumbnail generation and search indexing work?', 'foliora' ); ?></span>
							<span class="dashicons dashicons-arrow-down-alt2 foliora-faq-arrow" aria-hidden="true"></span>
						</summary>
						<div class="foliora-faq-answer">
							<p><?php esc_html_e( 'The first time an admin visits the Documents Library tab, the browser renders page 1 of each PDF and saves a high-quality cover thumbnail in your Media Library, while simultaneously extracting text so visitors can search for PDF contents using WordPress site search.', 'foliora' ); ?></p>
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
				'title' => __( 'Reading Modes & 3D FlipBook Engine', 'foliora' ),
				'items' => array(
					array( __( '16-Bone 3D FlipBook:', 'foliora' ), __( 'Ultra-realistic 3D curved page turning with natural paper inertia, dynamic specular lighting, center spine depth shadow, and stacked paper thickness.', 'foliora' ) ),
					array( __( 'Web Audio Paper Sound:', 'foliora' ), __( 'Synthesized real-time page-turn audio feedback (no heavy external mp3 files required).', 'foliora' ) ),
					array( __( 'Multiple Layout Modes:', 'foliora' ), __( 'Single Page, Two-Page Magazine Spread, Continuous Vertical Scroll, and 3D FlipBook.', 'foliora' ) ),
					array( __( 'Glassmorphism Thumbnails Panel:', 'foliora' ), __( 'Slide-out thumbnail card preview strip with instant jump-to-page navigation.', 'foliora' ) ),
					array( __( 'Touch & Mobile Gesture Engine:', 'foliora' ), __( 'Natural swipe-to-flip gestures and hardware-accelerated pinch-to-zoom.', 'foliora' ) ),
					array( __( 'Reading Themes:', 'foliora' ), __( 'Instant Dark, Light, and Sepia eye-care reading themes.', 'foliora' ) ),
				),
			),
			array(
				'icon'  => 'dashicons-admin-customizer',
				'tint'  => 'is-violet',
				'title' => __( 'Shortcode Builder & Saved Embeds', 'foliora' ),
				'items' => array(
					array( __( 'Clean ID Shortcodes [foliora id="1"]:', 'foliora' ), __( 'Encapsulates long attributes into a clean, easy-to-manage ID-based shortcode.', 'foliora' ) ),
					array( __( 'Saved Embeds Manager:', 'foliora' ), __( 'Dedicated manager with instant search, 1-click copy, duplicate, and live editing.', 'foliora' ) ),
					array( __( 'Live Interactive Preview:', 'foliora' ), __( 'Real-time responsive preview (Desktop, Tablet, Mobile) that updates instantly as you tweak settings.', 'foliora' ) ),
					array( __( '1-Click Presets:', 'foliora' ), __( 'One-click configurations for 3D FlipBook, E-Book Reader, Corporate Report, Night Reader, and Speed Optimized.', 'foliora' ) ),
					array( __( 'Developer Raw Code Toggle:', 'foliora' ), __( 'Switch between clean ID shortcodes, raw inline shortcodes, PHP snippets, and Gutenberg block code.', 'foliora' ) ),
				),
			),
			array(
				'icon'  => 'dashicons-layout',
				'tint'  => 'is-sky',
				'title' => __( 'Page Builders & Documents Library', 'foliora' ),
				'items' => array(
					array( __( 'Gutenberg Native Block:', 'foliora' ), __( 'Foliora Viewer block with Media picker & live first-page preview.', 'foliora' ) ),
					array( __( 'Auto oEmbed:', 'foliora' ), __( 'Simply paste any direct .pdf link on a new line to embed instantly.', 'foliora' ) ),
					array( __( 'Page Builders Compatibility:', 'foliora' ), __( 'Native widgets for Elementor, Divi and Beaver Builder; the shortcode works everywhere else.', 'foliora' ) ),
					array( __( 'Documents Library Grid:', 'foliora' ), __( '[foliora_library] shortcode for searchable PDF galleries.', 'foliora' ) ),
					array( __( 'Attachment Pages:', 'foliora' ), __( 'Automatic interactive reader on standard WordPress attachment pages.', 'foliora' ) ),
				),
			),
			array(
				'icon'  => 'dashicons-search',
				'tint'  => 'is-green',
				'title' => __( 'SEO, Search & Accessibility', 'foliora' ),
				'items' => array(
					array( __( 'Site Search Indexing:', 'foliora' ), __( 'Extracts PDF text so WordPress search finds content inside documents.', 'foliora' ) ),
					array( __( 'Schema.org JSON-LD:', 'foliora' ), __( 'Automatic DigitalDocument rich snippets for Google search results.', 'foliora' ) ),
					array( __( 'Social Card Previews:', 'foliora' ), __( 'Open Graph & Twitter Cards cover preview generated from the PDF.', 'foliora' ) ),
					array( __( 'Accessibility Diagnostics:', 'foliora' ), __( 'Detects untagged PDFs and tells you how to export an accessible one.', 'foliora' ) ),
					array( __( 'Zero CDN Dependency:', 'foliora' ), __( 'Bundled locally hosted PDF.js, no third-party tracking or remote downtime.', 'foliora' ) ),
				),
			),
		);
		?>
		<div class="foliora-features-showcase">
			<section class="fa-card fa-features-intro">
				<div class="fa-card__head">
					<span class="fa-card__icon dashicons dashicons-star-filled" aria-hidden="true"></span>
					<div class="fa-card__titles">
						<h2 class="fa-card__title"><?php esc_html_e( 'Everything Foliora Can Do', 'foliora' ); ?></h2>
						<p class="fa-card__sub"><?php esc_html_e( 'A fast, accessible and locally rendered PDF reading experience with no external dependencies, plus builder widgets and SEO integration.', 'foliora' ); ?></p>
					</div>
				</div>
				<div class="fa-card__body fa-stats">
					<span class="fa-stat"><strong>4</strong> <?php esc_html_e( 'reading modes', 'foliora' ); ?></span>
					<span class="fa-stat"><strong>Gutenberg · Elementor · Divi · Beaver</strong></span>
					<span class="fa-stat"><strong>0</strong> <?php esc_html_e( 'external requests', 'foliora' ); ?></span>
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
							<span class="fa-tag fa-tag--free"><?php esc_html_e( 'Free', 'foliora' ); ?></span>
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
		check_ajax_referer( 'foliora_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden.', 'foliora' ) ), 403 );
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
		check_ajax_referer( 'foliora_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden.', 'foliora' ) ), 403 );
		}

		$file = isset( $_POST['file'] ) ? esc_url_raw( wp_unslash( $_POST['file'] ) ) : '';
		if ( '' === $file ) {
			wp_send_json_error( array( 'message' => __( 'Select a PDF first.', 'foliora' ) ) );
		}

		// Ensure the URL resolves to a PDF attachment in this site's Media Library
		// so an arbitrary external URL cannot be published via this AJAX endpoint.
		$attachment_id = Foliora_Compat::attachment_id_from_url( $file );
		if ( ! $attachment_id ) {
			wp_send_json_error( array( 'message' => __( 'Only PDFs from the Media Library can be used here.', 'foliora' ) ) );
		}
		$mime = get_post_mime_type( $attachment_id );
		if ( 'application/pdf' !== $mime ) {
			wp_send_json_error( array( 'message' => __( 'That file is not a PDF.', 'foliora' ) ) );
		}

		$shortcode = isset( $_POST['shortcode'] ) ? sanitize_text_field( wp_unslash( $_POST['shortcode'] ) ) : '';
		$content   = ( '' !== $shortcode ) ? $shortcode : '[foliora file="' . $file . '"]';
		$existing  = get_page_by_path( 'foliora-test' );

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
				wp_send_json_error( array( 'message' => is_wp_error( $result ) ? $result->get_error_message() : __( 'Could not update the test page.', 'foliora' ) ) );
			}
			$page_id = $existing->ID;
		} else {
			$page_id = wp_insert_post(
				array(
					'post_type'    => 'page',
					'post_status'  => 'publish',
					'post_title'   => __( 'Foliora Test', 'foliora' ),
					'post_name'    => 'foliora-test',
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
		check_ajax_referer( 'foliora_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden.', 'foliora' ) ), 403 );
		}

		update_user_meta( get_current_user_id(), 'foliora_welcome_dismissed', 1 );
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
				'thumb' => Foliora_Thumbnails::get_thumbnail_url( $id, 'thumbnail' ),
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
		$cached = wp_cache_get( 'embed_count', 'foliora' );
		if ( false !== $cached ) {
			return (int) $cached;
		}

		global $wpdb;

		$like_shortcode = '%' . $wpdb->esc_like( '[foliora' ) . '%';
		$like_block     = '%' . $wpdb->esc_like( 'wp:foliora/' ) . '%';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- LIKE across post_content is not available via WP_Query; result is cached.
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_status IN ('publish','draft','private') AND post_type IN ('post','page') AND (post_content LIKE %s OR post_content LIKE %s)",
				$like_shortcode,
				$like_block
			)
		);

		wp_cache_set( 'embed_count', $count, 'foliora', 5 * MINUTE_IN_SECONDS );

		return $count;
	}
}
