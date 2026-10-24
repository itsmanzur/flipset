<?php
/**
 * Flipset_Viewer
 *
 * Registers the [flipset] shortcode and renders the front-end PDF viewer.
 * Rendering uses the PDF.js build bundled under assets/vendor/pdfjs —
 * never a CDN — so the plugin has no remote-code dependency (a WP.org
 * requirement) and keeps working on locked-down/offline sites.
 *
 * @package Flipset
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Flipset_Viewer {

	use Flipset_Att_Flag;

	/**
	 * Track whether we've already enqueued assets for this request, so
	 * multiple shortcodes on one page don't double-load PDF.js.
	 *
	 * @var bool
	 */
	private $assets_enqueued = false;

	public function init() {
		add_shortcode( 'flipset', array( $this, 'render_shortcode' ) );
	}

	/**
	 * [flipset file="https://example.com/file.pdf" width="100%" height="600px"]
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_shortcode( $atts ) {
		$settings = get_option( 'flipset_settings', array() );

		$raw_atts = is_array( $atts ) ? $atts : array();
		if ( ! empty( $raw_atts['id'] ) ) {
			$embed_id     = absint( $raw_atts['id'] );
			$saved_config = Flipset_Embeds::get( $embed_id );
			if ( ! empty( $saved_config ) && is_array( $saved_config ) ) {
				// Saved config supplies defaults; explicit shortcode attributes take precedence.
				$atts = array_merge( $saved_config, $raw_atts );
			} else {
				// translators: %d: embed ID number.
				return $this->admin_notice( sprintf( __( 'Flipset: Embed ID #%d not found.', 'flipset' ), $embed_id ) );
			}
		}

		$atts = shortcode_atts(
			array(
				'id'           => '',
				'file'         => '',
				'width'        => $settings['default_width'] ?? '100%',
				'height'       => $settings['default_height'] ?? '600px',
				'mode'         => 'pdf', // Reserved for add-ons: read it in flipset/pre_render_viewer.
				'page'         => '1',
				'title'        => '',
				'theme'        => $settings['default_theme'] ?? 'light',
				'download'     => '',
				'print'        => '',
				'search'       => '',
				'theme_btn'    => '',
				'share'        => '',
				'shortcuts'    => '',
				'presentation' => '',
				'fullscreen'   => '',
				'loading'      => ! empty( $settings['lazy_loading'] ) ? 'lazy' : 'eager',
				'hide'         => '',
				'hash'         => 'true',
				'view'         => 'page',
				'resume'       => 'true',
			),
			$atts,
			'flipset'
		);

		/**
		 * Filter: flipset/viewer_atts
		 *
		 * Override resolved shortcode/block attributes (every key of the
		 * shortcode_atts() list: id, file, width, height, mode, page, title, theme,
		 * download, print, search, theme_btn, share, shortcuts, presentation,
		 * fullscreen, loading, hide, hash, view, resume) without replacing the
		 * entire viewer. Runs after defaults are applied and before the
		 * empty-file check. See docs/HOOKS.md for the value lists.
		 *
		 * @param array $atts Resolved attributes.
		 */
		$atts = apply_filters( 'flipset/viewer_atts', $atts );
		if ( ! is_array( $atts ) ) {
			$atts = array();
		}

		// width and height end up in an inline style attribute: allow only a
		// number with a CSS length unit, never free text.
		$atts['width']  = self::sanitize_dimension( $atts['width'] ?? '', self::sanitize_dimension( $settings['default_width'] ?? '', '100%' ) );
		$atts['height'] = self::sanitize_dimension( $atts['height'] ?? '', self::sanitize_dimension( $settings['default_height'] ?? '', '600px' ) );

		if ( empty( $atts['file'] ) ) {
			return $this->admin_notice( __( 'Flipset: no file URL was provided. Use [flipset file="https://example.com/document.pdf"].', 'flipset' ) );
		}

		/**
		 * Filter: flipset/pre_render_viewer
		 *
		 * Lets an add-on take over rendering completely — e.g. an EPUB
		 * viewer, or the same PDF viewer wrapped in its bookmark / reader
		 * toolbar — without this free class needing to know add-on
		 * internals. Return any non-null value to short-circuit the
		 * default PDF.js output below.
		 *
		 * @param string|null $output Default null (let free plugin render).
		 * @param array       $atts   Resolved shortcode/block attributes.
		 */
		$pro_output = apply_filters( 'flipset/pre_render_viewer', null, $atts );
		if ( null !== $pro_output ) {
			return wp_kses( (string) apply_filters( 'flipset/viewer_html', $pro_output, $atts ), self::allowed_html() );
		}

		$this->enqueue_assets();

		$instance_id = 'flipset-viewer-' . wp_unique_id();

		/**
		 * Action: flipset/before_viewer
		 *
		 * An add-on can hook here to inject a toolbar (bookmarks, reader
		 * fonts, etc.) above the base viewer without free plugin code
		 * needing to know the add-on exists beyond this hook.
		 */
		ob_start();
		do_action( 'flipset/before_viewer', $instance_id, $atts );
		$before = wp_kses( (string) ob_get_clean(), self::allowed_html() );

		$toolbar = $this->render_toolbar( $instance_id, $atts, $settings );

		$caption = '';
		$title   = isset( $atts['title'] ) ? sanitize_text_field( (string) $atts['title'] ) : '';
		if ( '' !== $title ) {
			$caption = sprintf( '<p class="flipset-caption">%s</p>', esc_html( $title ) );
		}

		$start_page = max( 1, absint( $atts['page'] ?? 1 ) );
		$sync_hash  = $this->att_flag( $atts['hash'] ?? 'true', true );
		$resume     = $this->att_flag( $atts['resume'] ?? 'true', true );
		$view       = strtolower( (string) ( $atts['view'] ?? 'page' ) );
		if ( ! in_array( $view, array( 'page', 'scroll', 'spread', 'flip' ), true ) ) {
			$view = 'page';
		}

		$initial_theme = strtolower( (string) ( $atts['theme'] ?? 'light' ) );
		if ( ! in_array( $initial_theme, array( 'light', 'dark', 'sepia' ), true ) ) {
			$initial_theme = 'light';
		}

		$is_lazy = 'lazy' === strtolower( (string) ( $atts['loading'] ?? 'eager' ) );

		$branding = '';
		// Off by default (WordPress.org guideline 10); an administrator opts in under Flipset → Settings.
		if ( apply_filters( 'flipset/show_branding', ! empty( $settings['show_branding'] ) ) ) {
			$branding = sprintf(
				'<p class="flipset-branding"><a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a></p>',
				esc_url( 'https://flipset.thereadscope.com' ),
				esc_html__( 'Powered by Flipset', 'flipset' )
			);
		}

		$file_src = apply_filters( 'flipset/viewer_file_url', $atts['file'], $atts );

		$noscript = sprintf(
			'<noscript><div class="flipset-noscript"><p><a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a></p></div></noscript>',
			esc_url( $file_src ),
			sprintf(
				/* translators: %s: document title or fallback */
				esc_html__( 'Open document: %s (PDF)', 'flipset' ),
				esc_html( '' !== $title ? $title : wp_basename( (string) $file_src ) )
			)
		);

		$modals = $this->render_modals();

		$container = sprintf(
			'<div class="flipset-wrap" style="width:%1$s;" role="region" aria-label="%7$s">%9$s<div class="flipset-shell flipset-theme-%14$s" style="height:%2$s;"><div class="flipset-chrome" style="display:flex;flex-wrap:nowrap;align-items:center;">%3$s%4$s</div><div id="%5$s" class="flipset-viewer" data-file="%6$s" data-page="%10$s" data-hash="%11$s" data-view="%12$s" data-resume="%13$s" data-theme="%14$s" data-loading="%15$s" tabindex="0" role="document" aria-busy="true">%17$s</div>%16$s</div>%8$s</div>',
			esc_attr( $atts['width'] ),
			esc_attr( $atts['height'] ),
			$before,
			$toolbar,
			esc_attr( $instance_id ),
			esc_url( $file_src ),
			esc_attr__( 'PDF viewer', 'flipset' ),
			$branding,
			$caption,
			esc_attr( (string) $start_page ),
			$sync_hash ? '1' : '0',
			esc_attr( $view ),
			$resume ? '1' : '0',
			esc_attr( $initial_theme ),
			$is_lazy ? 'lazy' : 'eager',
			$modals,
			$noscript
		);

		/**
		 * Filter: flipset/viewer_html
		 *
		 * Last chance to wrap or replace the finished viewer markup.
		 * Also applied to non-null `flipset/pre_render_viewer` output.
		 *
		 * @param string $html Rendered viewer HTML.
		 * @param array  $atts Resolved attributes.
		 */
		// Escape at the single point where the markup leaves the renderer, after
		// every filter has had its say.
		return wp_kses( (string) apply_filters( 'flipset/viewer_html', $container, $atts ), self::allowed_html() );
	}

	/**
	 * Chrome for the free PDF viewer. An add-on's toolbar is
	 * printed into the same .flipset-chrome row via flipset/before_viewer.
	 *
	 * @param string $instance_id Viewer element id.
	 * @param array  $atts        Resolved attributes.
	 * @param array  $settings    Plugin settings.
	 * @return string
	 */
	private function render_toolbar( $instance_id, $atts, $settings ) {
		$file_url = isset( $atts['file'] ) ? (string) $atts['file'] : '';

		$hide_list = array_filter( array_map( 'trim', explode( ',', strtolower( (string) ( $atts['hide'] ?? '' ) ) ) ) );

		$show_nav    = ! in_array( 'nav', $hide_list, true );
		$show_zoom   = ! in_array( 'zoom', $hide_list, true );
		$show_fit    = ! in_array( 'fit', $hide_list, true );
		$show_rotate = ! in_array( 'rotate', $hide_list, true );
		$show_view   = ! in_array( 'view', $hide_list, true );
		$show_pan    = ! in_array( 'pan', $hide_list, true );

		$download   = ! in_array( 'download', $hide_list, true ) && $this->att_flag(
			$atts['download'] ?? '',
			! isset( $settings['allow_download'] ) || ! empty( $settings['allow_download'] )
		);
		$print      = ! in_array( 'print', $hide_list, true ) && $this->att_flag(
			$atts['print'] ?? '',
			! isset( $settings['allow_print'] ) || ! empty( $settings['allow_print'] )
		);
		$search     = ! in_array( 'search', $hide_list, true ) && $this->att_flag(
			$atts['search'] ?? '',
			! isset( $settings['allow_search'] ) || ! empty( $settings['allow_search'] )
		);
		$theme_btn  = ! in_array( 'theme', $hide_list, true ) && $this->att_flag(
			$atts['theme_btn'] ?? '',
			! isset( $settings['allow_theme'] ) || ! empty( $settings['allow_theme'] )
		);
		$share      = ! in_array( 'share', $hide_list, true ) && $this->att_flag(
			$atts['share'] ?? '',
			! isset( $settings['allow_share'] ) || ! empty( $settings['allow_share'] )
		);
		$shortcuts  = ! in_array( 'shortcuts', $hide_list, true ) && $this->att_flag(
			$atts['shortcuts'] ?? '',
			! isset( $settings['allow_shortcuts'] ) || ! empty( $settings['allow_shortcuts'] )
		);
		$present    = ! in_array( 'presentation', $hide_list, true ) && $this->att_flag(
			$atts['presentation'] ?? '',
			! isset( $settings['allow_presentation'] ) || ! empty( $settings['allow_presentation'] )
		);
		$fullscreen = ! in_array( 'fullscreen', $hide_list, true ) && $this->att_flag(
			$atts['fullscreen'] ?? '',
			! isset( $settings['allow_fullscreen'] ) || ! empty( $settings['allow_fullscreen'] )
		);

		$download_url = apply_filters( 'flipset/download_url', $file_url, $atts );
		ob_start();
		?>
		<div class="flipset-toolbar" role="toolbar" data-target="<?php echo esc_attr( $instance_id ); ?>" aria-controls="<?php echo esc_attr( $instance_id ); ?>" aria-label="<?php esc_attr_e( 'PDF viewer', 'flipset' ); ?>">
			<div class="flipset-tool-group flipset-panel-group" hidden>
				<button type="button" class="flipset-btn flipset-toc" hidden aria-expanded="false" title="<?php esc_attr_e( 'Table of contents', 'flipset' ); ?>" aria-label="<?php esc_attr_e( 'Table of contents', 'flipset' ); ?>">
					<?php $this->the_icon( 'toc' ); ?>
				</button>
				<button type="button" class="flipset-btn flipset-thumbs" hidden aria-expanded="false" title="<?php esc_attr_e( 'Page thumbnails', 'flipset' ); ?>" aria-label="<?php esc_attr_e( 'Page thumbnails', 'flipset' ); ?>">
					<?php $this->the_icon( 'thumbs' ); ?>
				</button>
			</div>
			<?php if ( $show_nav ) : ?>
			<div class="flipset-tool-group flipset-nav-group">
				<button type="button" class="flipset-btn flipset-prev" title="<?php esc_attr_e( 'Previous page (←)', 'flipset' ); ?>" aria-label="<?php esc_attr_e( 'Previous page', 'flipset' ); ?>">
					<?php $this->the_icon( 'prev' ); ?>
				</button>
				<span class="flipset-page-indicator">
					<input type="number" class="flipset-page-input" min="1" value="1" aria-label="<?php esc_attr_e( 'Page', 'flipset' ); ?>" />
					<span class="flipset-page-slash" aria-hidden="true">/</span>
					<span class="flipset-page-count">1</span>
				</span>
				<button type="button" class="flipset-btn flipset-next" title="<?php esc_attr_e( 'Next page (→)', 'flipset' ); ?>" aria-label="<?php esc_attr_e( 'Next page', 'flipset' ); ?>">
					<?php $this->the_icon( 'next' ); ?>
				</button>
			</div>
			<?php endif; ?>
			<div class="flipset-tool-group flipset-zoom-group">
				<?php if ( $show_zoom ) : ?>
				<button type="button" class="flipset-btn flipset-zoom-out" title="<?php esc_attr_e( 'Zoom out (-)', 'flipset' ); ?>" aria-label="<?php esc_attr_e( 'Zoom out', 'flipset' ); ?>">
					<?php $this->the_icon( 'minus' ); ?>
				</button>
				<span class="flipset-zoom-label" aria-live="polite">100%</span>
				<button type="button" class="flipset-btn flipset-zoom-in" title="<?php esc_attr_e( 'Zoom in (+)', 'flipset' ); ?>" aria-label="<?php esc_attr_e( 'Zoom in', 'flipset' ); ?>">
					<?php $this->the_icon( 'plus' ); ?>
				</button>
				<?php endif; ?>
				<?php if ( $show_fit ) : ?>
				<button type="button" class="flipset-btn flipset-fit" title="<?php esc_attr_e( 'Fit page (0)', 'flipset' ); ?>" aria-label="<?php esc_attr_e( 'Fit page', 'flipset' ); ?>">
					<?php $this->the_icon( 'fit' ); ?>
				</button>
				<?php endif; ?>
			</div>
			<?php if ( $search ) : ?>
			<div class="flipset-tool-group flipset-find-group">
				<span class="flipset-find">
					<?php $this->the_icon( 'search' ); ?>
					<input type="search" class="flipset-find-input" placeholder="<?php esc_attr_e( 'Find', 'flipset' ); ?>" aria-label="<?php esc_attr_e( 'Find in document', 'flipset' ); ?>" />
				</span>
				<span class="flipset-find-status" hidden aria-live="polite"></span>
				<button type="button" class="flipset-btn flipset-find-prev" title="<?php esc_attr_e( 'Previous match', 'flipset' ); ?>" aria-label="<?php esc_attr_e( 'Previous match', 'flipset' ); ?>">
					<?php $this->the_icon( 'up' ); ?>
				</button>
				<button type="button" class="flipset-btn flipset-find-next" title="<?php esc_attr_e( 'Next match', 'flipset' ); ?>" aria-label="<?php esc_attr_e( 'Next match', 'flipset' ); ?>">
					<?php $this->the_icon( 'down' ); ?>
				</button>
			</div>
			<?php endif; ?>
			<div class="flipset-tool-group flipset-tool-group-end">
				<?php if ( $download ) : ?>
				<a class="flipset-btn flipset-download" href="<?php echo esc_url( $download_url ); ?>" download title="<?php esc_attr_e( 'Download', 'flipset' ); ?>" aria-label="<?php esc_attr_e( 'Download', 'flipset' ); ?>">
					<?php $this->the_icon( 'download' ); ?>
				</a>
				<?php endif; ?>
				<?php if ( $fullscreen ) : ?>
				<button type="button" class="flipset-btn flipset-fs" title="<?php esc_attr_e( 'Full screen (F)', 'flipset' ); ?>" aria-label="<?php esc_attr_e( 'Full screen', 'flipset' ); ?>">
					<?php $this->the_icon( 'fs' ); ?>
				</button>
				<?php endif; ?>
				<div class="flipset-more-wrap">
					<button type="button" class="flipset-btn flipset-more" aria-expanded="false" aria-haspopup="true" title="<?php esc_attr_e( 'More tools', 'flipset' ); ?>" aria-label="<?php esc_attr_e( 'More tools', 'flipset' ); ?>">
						<?php $this->the_icon( 'more' ); ?>
					</button>
					<div class="flipset-more-menu" hidden role="menu">
						<?php if ( $show_rotate ) : ?>
						<button type="button" class="flipset-more-item flipset-rotate" role="menuitem">
							<?php $this->the_icon( 'rotate' ); ?>
							<span><?php esc_html_e( 'Rotate', 'flipset' ); ?></span>
						</button>
						<?php endif; ?>
						<?php if ( $show_view ) : ?>
						<button type="button" class="flipset-more-item flipset-view" role="menuitem">
							<?php $this->the_icon( 'view' ); ?>
							<span><?php esc_html_e( 'View Mode', 'flipset' ); ?></span>
						</button>
						<?php endif; ?>
						<?php if ( $show_pan ) : ?>
						<button type="button" class="flipset-more-item flipset-pan" role="menuitem" aria-pressed="false">
							<?php $this->the_icon( 'pan' ); ?>
							<span><?php esc_html_e( 'Hand Tool', 'flipset' ); ?></span>
						</button>
						<?php endif; ?>
						<?php if ( $theme_btn ) : ?>
						<button type="button" class="flipset-more-item flipset-theme-toggle" role="menuitem">
							<?php $this->the_icon( 'theme' ); ?>
							<span><?php esc_html_e( 'Switch Theme', 'flipset' ); ?></span>
						</button>
						<?php endif; ?>
						<?php if ( $share ) : ?>
						<button type="button" class="flipset-more-item flipset-share-btn" role="menuitem" aria-expanded="false">
							<?php $this->the_icon( 'share' ); ?>
							<span><?php esc_html_e( 'Share Document', 'flipset' ); ?></span>
						</button>
						<?php endif; ?>
						<?php if ( $shortcuts ) : ?>
						<button type="button" class="flipset-more-item flipset-shortcuts-btn" role="menuitem" aria-expanded="false">
							<?php $this->the_icon( 'shortcuts' ); ?>
							<span><?php esc_html_e( 'Shortcuts Guide', 'flipset' ); ?></span>
						</button>
						<?php endif; ?>
						<?php if ( $print ) : ?>
						<button type="button" class="flipset-more-item flipset-print" role="menuitem">
							<?php $this->the_icon( 'print' ); ?>
							<span><?php esc_html_e( 'Print Document', 'flipset' ); ?></span>
						</button>
						<?php endif; ?>
						<?php if ( $present ) : ?>
						<button type="button" class="flipset-more-item flipset-present" role="menuitem" aria-pressed="false">
							<?php $this->the_icon( 'present' ); ?>
							<span><?php esc_html_e( 'Presentation Mode', 'flipset' ); ?></span>
						</button>
						<?php endif; ?>
					</div>
				</div>
			</div>
			<span class="flipset-sr-only flipset-page-live" aria-live="polite"></span>
			<span class="flipset-sr-only"><?php esc_html_e( 'Keyboard: arrows or Page Up and Page Down change page, plus and minus zoom, R rotates, Home and End jump to first or last page, Ctrl or Command F finds text, D toggles reading theme, ? opens shortcuts guide.', 'flipset' ); ?></span>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Modals for keyboard shortcuts and sharing.
	 *
	 * @return string
	 */
	private function render_modals() {
		ob_start();
		?>
		<div class="flipset-modal flipset-shortcuts-modal" hidden role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Keyboard shortcuts', 'flipset' ); ?>">
			<div class="flipset-modal-backdrop"></div>
			<div class="flipset-modal-card">
				<div class="flipset-modal-header">
					<h3 class="flipset-modal-title"><?php esc_html_e( 'Keyboard Shortcuts', 'flipset' ); ?></h3>
					<button type="button" class="flipset-modal-close" aria-label="<?php esc_attr_e( 'Close', 'flipset' ); ?>">
						<?php $this->the_icon( 'close' ); ?>
					</button>
				</div>
				<div class="flipset-modal-body">
					<div class="flipset-shortcuts-grid">
						<div class="flipset-shortcut-row">
							<span class="flipset-shortcut-desc"><?php esc_html_e( 'Next / Previous Page', 'flipset' ); ?></span>
							<span class="flipset-shortcut-keys"><kbd>→</kbd> <kbd>←</kbd> / <kbd>J</kbd> <kbd>K</kbd></span>
						</div>
						<div class="flipset-shortcut-row">
							<span class="flipset-shortcut-desc"><?php esc_html_e( 'Zoom In / Out', 'flipset' ); ?></span>
							<span class="flipset-shortcut-keys"><kbd>+</kbd> <kbd>-</kbd></span>
						</div>
						<div class="flipset-shortcut-row">
							<span class="flipset-shortcut-desc"><?php esc_html_e( 'Fit Page / Width', 'flipset' ); ?></span>
							<span class="flipset-shortcut-keys"><kbd>0</kbd></span>
						</div>
						<div class="flipset-shortcut-row">
							<span class="flipset-shortcut-desc"><?php esc_html_e( 'Rotate Document', 'flipset' ); ?></span>
							<span class="flipset-shortcut-keys"><kbd>R</kbd></span>
						</div>
						<div class="flipset-shortcut-row">
							<span class="flipset-shortcut-desc"><?php esc_html_e( 'Toggle Theme (Dark/Sepia/Light)', 'flipset' ); ?></span>
							<span class="flipset-shortcut-keys"><kbd>D</kbd></span>
						</div>
						<div class="flipset-shortcut-row">
							<span class="flipset-shortcut-desc"><?php esc_html_e( 'Find in Document', 'flipset' ); ?></span>
							<span class="flipset-shortcut-keys"><kbd>/</kbd> or <kbd>Ctrl+F</kbd></span>
						</div>
						<div class="flipset-shortcut-row">
							<span class="flipset-shortcut-desc"><?php esc_html_e( 'Hand / Pan Tool', 'flipset' ); ?></span>
							<span class="flipset-shortcut-keys"><kbd>H</kbd></span>
						</div>
						<div class="flipset-shortcut-row">
							<span class="flipset-shortcut-desc"><?php esc_html_e( 'Full Screen', 'flipset' ); ?></span>
							<span class="flipset-shortcut-keys"><kbd>F</kbd></span>
						</div>
						<div class="flipset-shortcut-row">
							<span class="flipset-shortcut-desc"><?php esc_html_e( 'Presentation Mode', 'flipset' ); ?></span>
							<span class="flipset-shortcut-keys"><kbd>P</kbd></span>
						</div>
						<div class="flipset-shortcut-row">
							<span class="flipset-shortcut-desc"><?php esc_html_e( 'First / Last Page', 'flipset' ); ?></span>
							<span class="flipset-shortcut-keys"><kbd>Home</kbd> <kbd>End</kbd></span>
						</div>
						<div class="flipset-shortcut-row">
							<span class="flipset-shortcut-desc"><?php esc_html_e( 'Close Overlay / Clear Search', 'flipset' ); ?></span>
							<span class="flipset-shortcut-keys"><kbd>Esc</kbd></span>
						</div>
					</div>
				</div>
			</div>
		</div>

		<div class="flipset-modal flipset-share-modal" hidden role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Share document', 'flipset' ); ?>">
			<div class="flipset-modal-backdrop"></div>
			<div class="flipset-modal-card">
				<div class="flipset-modal-header">
					<h3 class="flipset-modal-title"><?php esc_html_e( 'Share Document', 'flipset' ); ?></h3>
					<button type="button" class="flipset-modal-close" aria-label="<?php esc_attr_e( 'Close', 'flipset' ); ?>">
						<?php $this->the_icon( 'close' ); ?>
					</button>
				</div>
				<div class="flipset-modal-body">
					<div class="flipset-share-link-group">
						<input type="text" class="flipset-share-input" readonly value="" />
						<button type="button" class="flipset-btn flipset-share-copy">
							<span class="flipset-share-copy-text"><?php esc_html_e( 'Copy', 'flipset' ); ?></span>
						</button>
					</div>
					<label class="flipset-share-page-option">
						<input type="checkbox" class="flipset-share-page-check" checked /> <?php esc_html_e( 'Link to current page (page ', 'flipset' ); ?><span class="flipset-share-curr-page">1</span>)
					</label>
					<div class="flipset-share-buttons">
						<a href="#" class="flipset-share-item flipset-share-x" target="_blank" rel="noopener noreferrer">X (Twitter)</a>
						<a href="#" class="flipset-share-item flipset-share-fb" target="_blank" rel="noopener noreferrer">Facebook</a>
						<a href="#" class="flipset-share-item flipset-share-linkedin" target="_blank" rel="noopener noreferrer">LinkedIn</a>
						<a href="#" class="flipset-share-item flipset-share-wa" target="_blank" rel="noopener noreferrer">WhatsApp</a>
						<a href="#" class="flipset-share-item flipset-share-email" target="_blank" rel="noopener noreferrer">Email</a>
					</div>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Validate a CSS length used for the viewer width or height.
	 *
	 * @param mixed  $value   Raw value.
	 * @param string $fallback Fallback.
	 * @return string
	 */
	public static function sanitize_dimension( $value, $fallback ) {
		$value = trim( sanitize_text_field( (string) $value ) );
		if ( preg_match( '/^(0|\d+(\.\d+)?(px|%|em|rem|vh|vw|svh|dvh|lvh))$/', $value ) ) {
			return $value;
		}
		return (string) $fallback;
	}

	/**
	 * Keep only known control names in a comma separated "hide" list.
	 *
	 * @param mixed $value Raw list.
	 * @return string
	 */
	public static function sanitize_hide_list( $value ) {
		$known = array( 'nav', 'zoom', 'fit', 'rotate', 'view', 'pan', 'download', 'print', 'search', 'theme', 'share', 'shortcuts', 'presentation', 'fullscreen' );

		/**
		 * Filter: flipset/hide_tokens
		 *
		 * Control names accepted by the "hide" attribute. Add-ons that print
		 * extra controls add their own names here.
		 *
		 * @param string[] $known Allowed names.
		 */
		$filtered = apply_filters( 'flipset/hide_tokens', $known );
		$known    = is_array( $filtered ) ? array_map( 'strval', $filtered ) : $known;

		$tokens = array_map( 'trim', explode( ',', strtolower( sanitize_text_field( (string) $value ) ) ) );
		$tokens = array_values( array_unique( array_intersect( $tokens, $known ) ) );
		return implode( ',', $tokens );
	}

	/**
	 * Normalise a stored on/off shortcode attribute to true, false or empty (inherit).
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function sanitize_flag( $value ) {
		$v = strtolower( trim( (string) $value ) );
		if ( in_array( $v, array( '1', 'true', 'yes', 'on' ), true ) ) {
			return 'true';
		}
		if ( in_array( $v, array( '0', 'false', 'no', 'off' ), true ) ) {
			return 'false';
		}
		return '';
	}

	/**
	 * Tags and attributes the viewer and library markup needs, for wp_kses().
	 * Everything else is stripped, so output built from settings, post titles
	 * or filters can never carry script, iframes or event handlers.
	 *
	 * @return array<string,array<string,bool>>
	 */
	public static function allowed_html() {
		$g = array(
			'class'            => true,
			'id'               => true,
			'hidden'           => true,
			'role'             => true,
			'tabindex'         => true,
			'title'            => true,
			'style'            => true,
			'lang'             => true,
			'dir'              => true,
			'data-*'           => true,
			'aria-label'       => true,
			'aria-labelledby'  => true,
			'aria-describedby' => true,
			'aria-hidden'      => true,
			'aria-live'        => true,
			'aria-busy'        => true,
			'aria-expanded'    => true,
			'aria-pressed'     => true,
			'aria-controls'    => true,
			'aria-haspopup'    => true,
			'aria-current'     => true,
			'aria-modal'       => true,
			'aria-valuemin'    => true,
			'aria-valuemax'    => true,
			'aria-valuenow'    => true,
		);

		$tags = array(
			'div'      => array(),
			'span'     => array(),
			'p'        => array(),
			'nav'      => array(),
			'ul'       => array(),
			'li'       => array(),
			'h2'       => array(),
			'h3'       => array(),
			'strong'   => array(),
			'kbd'      => array(),
			'noscript' => array(),
			'figure'   => array(),
			'a'        => array(
				'href'     => true,
				'target'   => true,
				'rel'      => true,
				'download' => true,
			),
			'button'   => array(
				'type'     => true,
				'disabled' => true,
				'name'     => true,
				'value'    => true,
			),
			'form'     => array(
				'method' => true,
				'action' => true,
			),
			'label'    => array(
				'for' => true,
			),
			'input'    => array(
				'type'         => true,
				'name'         => true,
				'value'        => true,
				'placeholder'  => true,
				'min'          => true,
				'max'          => true,
				'checked'      => true,
				'readonly'     => true,
				'disabled'     => true,
				'autocomplete' => true,
			),
			'img'      => array(
				'src'      => true,
				'srcset'   => true,
				'sizes'    => true,
				'alt'      => true,
				'width'    => true,
				'height'   => true,
				'loading'  => true,
				'decoding' => true,
			),
		);

		$allowed = array();
		foreach ( $tags as $tag => $attrs ) {
			$allowed[ $tag ] = array_merge( $g, $attrs );
		}

		$allowed = array_merge( $allowed, self::svg_allowed_html() );

		/**
		 * Filter: flipset/allowed_html
		 *
		 * Add-ons that print extra markup inside the viewer or library output
		 * (through flipset/before_viewer or flipset/viewer_html) extend this list.
		 *
		 * @param array $allowed Tag => attributes map used with wp_kses().
		 */
		$filtered = apply_filters( 'flipset/allowed_html', $allowed );
		return is_array( $filtered ) ? $filtered : $allowed;
	}

	/**
	 * Tags and attributes of the static line icons (inline SVG).
	 *
	 * @return array<string,array<string,bool>>
	 */
	public static function svg_allowed_html() {
		$paint = array(
			'class'           => true,
			'fill'            => true,
			'stroke'          => true,
			'stroke-width'    => true,
			'stroke-linecap'  => true,
			'stroke-linejoin' => true,
			'aria-hidden'     => true,
		);

		return array(
			'svg'      => array_merge(
				$paint,
				array(
					'xmlns'     => true,
					'width'     => true,
					'height'    => true,
					'viewbox'   => true,
					'focusable' => true,
				)
			),
			'path'     => array_merge( $paint, array( 'd' => true ) ),
			'polyline' => array_merge( $paint, array( 'points' => true ) ),
			'line'     => array_merge(
				$paint,
				array(
					'x1' => true,
					'y1' => true,
					'x2' => true,
					'y2' => true,
				)
			),
			'circle'   => array_merge(
				$paint,
				array(
					'cx' => true,
					'cy' => true,
					'r'  => true,
				)
			),
			'rect'     => array_merge(
				$paint,
				array(
					'x'      => true,
					'y'      => true,
					'width'  => true,
					'height' => true,
					'rx'     => true,
					'ry'     => true,
				)
			),
		);
	}

	/**
	 * Print one toolbar icon, escaped at output.
	 *
	 * @param string $name Icon key.
	 */
	private function the_icon( $name ) {
		echo wp_kses( $this->toolbar_icon( $name ), self::svg_allowed_html() );
	}

	/**
	 * Toolbar glyph markup for admin screens that show the same icons.
	 *
	 * @param string $name Icon key.
	 * @return string SVG markup.
	 */
	public function get_toolbar_icon( $name ) {
		return $this->toolbar_icon( $name );
	}

	/**
	 * Inline 16px stroke icons for the viewer chrome.
	 *
	 * @param string $name Icon key.
	 * @return string
	 */
	private function toolbar_icon( $name ) {
		$paths = array(
			'prev'      => '<polyline points="15 18 9 12 15 6" />',
			'next'      => '<polyline points="9 18 15 12 9 6" />',
			'minus'     => '<line x1="5" y1="12" x2="19" y2="12" />',
			'plus'      => '<line x1="12" y1="5" x2="12" y2="19" /><line x1="5" y1="12" x2="19" y2="12" />',
			'fit'       => '<path d="M8 3H5a2 2 0 0 0-2 2v3" /><path d="M16 3h3a2 2 0 0 1 2 2v3" /><path d="M8 21H5a2 2 0 0 1-2-2v-3" /><path d="M16 21h3a2 2 0 0 0 2-2v-3" />',
			'rotate'    => '<polyline points="23 4 23 10 17 10" /><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10" />',
			'search'    => '<circle cx="11" cy="11" r="7" /><line x1="21" y1="21" x2="16.65" y2="16.65" />',
			'up'        => '<polyline points="18 15 12 9 6 15" />',
			'down'      => '<polyline points="6 9 12 15 18 9" />',
			'download'  => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" /><polyline points="7 10 12 15 17 10" /><line x1="12" y1="15" x2="12" y2="3" />',
			'print'     => '<polyline points="6 9 6 2 18 2 18 9" /><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" /><rect x="6" y="14" width="12" height="8" />',
			'fs'        => '<polyline points="15 3 21 3 21 9" /><polyline points="9 21 3 21 3 15" /><line x1="21" y1="3" x2="14" y2="10" /><line x1="3" y1="21" x2="10" y2="14" />',
			'toc'       => '<line x1="8" y1="6" x2="21" y2="6" /><line x1="8" y1="12" x2="21" y2="12" /><line x1="8" y1="18" x2="21" y2="18" /><line x1="3" y1="6" x2="3.01" y2="6" /><line x1="3" y1="12" x2="3.01" y2="12" /><line x1="3" y1="18" x2="3.01" y2="18" />',
			'thumbs'    => '<rect x="3" y="3" width="7" height="7" /><rect x="14" y="3" width="7" height="7" /><rect x="3" y="14" width="7" height="7" /><rect x="14" y="14" width="7" height="7" />',
			'view'      => '<rect x="3" y="3" width="7" height="18" /><rect x="14" y="3" width="7" height="18" />',
			'pan'       => '<path d="M18 11V6a2 2 0 0 0-4 0v5" /><path d="M14 10V4a2 2 0 0 0-4 0v8" /><path d="M10 11V6a2 2 0 0 0-4 0v7" /><path d="M6 13v3a6 6 0 0 0 6 6h2a6 6 0 0 0 6-6v-5a2 2 0 0 0-4 0" />',
			'more'      => '<circle cx="12" cy="5" r="1.5" /><circle cx="12" cy="12" r="1.5" /><circle cx="12" cy="19" r="1.5" />',
			'present'   => '<rect x="2" y="3" width="20" height="14" rx="2" /><line x1="8" y1="21" x2="16" y2="21" /><line x1="12" y1="17" x2="12" y2="21" />',
			'theme'     => '<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z" />',
			'share'     => '<circle cx="18" cy="5" r="3" /><circle cx="6" cy="12" r="3" /><circle cx="18" cy="19" r="3" /><line x1="8.59" y1="13.51" x2="15.42" y2="17.49" /><line x1="15.41" y1="6.51" x2="8.59" y2="10.49" />',
			'shortcuts' => '<circle cx="12" cy="12" r="10" /><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3" /><line x1="12" y1="17" x2="12.01" y2="17" />',
			'close'     => '<line x1="18" y1="6" x2="6" y2="18" /><line x1="6" y1="6" x2="18" y2="18" />',
			'copy'      => '<rect x="9" y="9" width="13" height="13" rx="2" ry="2" /><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" />',
			'check'     => '<polyline points="20 6 9 17 4 12" />',
		);
		$body  = $paths[ $name ] ?? '';
		return '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $body . '</svg>';
	}

	/**
	 * Strings shared by the front-end viewer and the settings live preview.
	 *
	 * @return array
	 */
	public static function viewer_i18n() {
		return array(
			'loading'        => __( 'Loading document…', 'flipset' ),
			/* translators: %s: percent loaded. */
			'loadingPct'     => __( 'Loading %s…', 'flipset' ),
			'error'          => __( 'This document could not be loaded.', 'flipset' ),
			/* translators: 1: current page number, 2: total pages. */
			'pageStatus'     => __( 'Page %1$s of %2$s', 'flipset' ),
			'passwordPrompt' => __( 'This document is password-protected.', 'flipset' ),
			'passwordWrong'  => __( 'Incorrect password.', 'flipset' ),
			'passwordUnlock' => __( 'Unlock', 'flipset' ),
			'passwordLabel'  => __( 'Password', 'flipset' ),
			'toc'            => __( 'Table of contents', 'flipset' ),
			'thumbs'         => __( 'Page thumbnails', 'flipset' ),
			'outlineNav'     => __( 'Document outline', 'flipset' ),
			'thumbsNav'      => __( 'Page thumbnails', 'flipset' ),
			'closePanel'     => __( 'Close panel', 'flipset' ),
			'fitPage'        => __( 'Fit page', 'flipset' ),
			'fitWidth'       => __( 'Fit width', 'flipset' ),
			/* translators: 1: current match, 2: total matches. */
			'findStatus'     => __( '%1$s of %2$s', 'flipset' ),
			'findNone'       => __( 'No matches', 'flipset' ),
			'viewPage'       => __( 'Single page', 'flipset' ),
			'viewScroll'     => __( 'Continuous scroll', 'flipset' ),
			'viewSpread'     => __( 'Two-page spread', 'flipset' ),
			'viewFlip'       => __( '3D FlipBook', 'flipset' ),
			'pan'            => __( 'Hand tool', 'flipset' ),
			'select'         => __( 'Text select', 'flipset' ),
			'more'           => __( 'More tools', 'flipset' ),
			'present'        => __( 'Presentation', 'flipset' ),
			'copied'         => __( 'Link copied!', 'flipset' ),
			'clickToLoad'    => __( 'Click to load document', 'flipset' ),
			'corsTitle'      => __( 'Unable to load document (Cross-Origin Restriction)', 'flipset' ),
			/* translators: %s: remote host name */
			'corsDesc'       => __( 'This PDF is hosted on an external domain (%s) which restricts cross-origin access (CORS).', 'flipset' ),
			'corsDownload'   => __( 'Open / Download PDF directly', 'flipset' ),
			'corsAdminHint'  => __( 'Site Admin: To enable direct embedding, configure Access-Control-Allow-Origin headers on the host server or upload the PDF to your WordPress Media Library.', 'flipset' ),
		);
	}

	/**
	 * Version of the bundled assets/vendor/pdfjs build. The capture snippet only
	 * accepts a global with this exact version as "ours", so a different
	 * plugin's PDF.js is never mistaken for Flipset's. Update it together with
	 * the vendor files.
	 */
	const PDFJS_VERSION = '3.11.174';

	/**
	 * Run just BEFORE the bundle: remember whatever PDF.js another plugin
	 * already put on the page, because the bundle assigns window.pdfjsLib
	 * unconditionally.
	 */
	const PDFJS_BEFORE = '(function(w){var f=w.Flipset=w.Flipset||{};f._prevPdfjs={g:w.pdfjsLib,u:w["pdfjs-dist/build/pdf"]};})(window);';

	/**
	 * Run just AFTER the bundle: keep a private reference to our own PDF.js
	 * (window.Flipset.pdfjsLib) and put window.pdfjsLib back exactly as it was
	 * before our bundle ran (another plugin's PDF.js, or unset). Flipset then
	 * leaves no global footprint and other plugins that look for a global
	 * PDF.js load their own. Each bundle is self-contained, so nothing is shared.
	 */
	const PDFJS_AFTER = '(function(w){var f=w.Flipset=w.Flipset||{},p=f._prevPdfjs||{},c=w.pdfjsLib,k="pdfjs-dist/build/pdf";delete f._prevPdfjs;if(!f.pdfjsLib&&c&&c!==p.g&&c.version==="' . self::PDFJS_VERSION . '"){f.pdfjsLib=c;if(p.g){w.pdfjsLib=p.g;}else{try{delete w.pdfjsLib;}catch(e){w.pdfjsLib=undefined;}}if(p.u){w[k]=p.u;}else{try{delete w[k];}catch(e){w[k]=undefined;}}}})(window);';

	/**
	 * Register the bundled PDF.js once, with the capture snippets attached.
	 * Safe to call from any enqueue path; later calls are no-ops.
	 */
	public static function register_pdfjs() {
		if ( wp_script_is( 'flipset-pdfjs', 'registered' ) ) {
			return;
		}
		wp_register_script(
			'flipset-pdfjs',
			FLIPSET_URL . 'assets/vendor/pdfjs/pdf.min.js',
			array(),
			FLIPSET_VERSION,
			true
		);
		wp_add_inline_script( 'flipset-pdfjs', self::PDFJS_BEFORE, 'before' );
		wp_add_inline_script( 'flipset-pdfjs', self::PDFJS_AFTER, 'after' );
	}

	/**
	 * Enqueue the bundled PDF.js build + our thin wrapper script once per
	 * request.
	 */
	public function enqueue_assets() {
		if ( $this->assets_enqueued ) {
			return;
		}
		$this->assets_enqueued = true;

		wp_enqueue_style(
			'flipset-viewer',
			FLIPSET_URL . 'assets/css/flipset-viewer.css',
			array(),
			FLIPSET_VERSION
		);

		self::register_pdfjs();
		wp_enqueue_script( 'flipset-pdfjs' );

		wp_enqueue_script(
			'flipset-viewer',
			FLIPSET_URL . 'assets/js/flipset-viewer.js',
			array( 'flipset-pdfjs' ),
			FLIPSET_VERSION,
			true
		);

		wp_localize_script(
			'flipset-viewer',
			'FlipsetConfig',
			array(
				'workerSrc' => FLIPSET_URL . 'assets/vendor/pdfjs/pdf.worker.min.js',
				'isPro'     => Flipset_Loader::is_pro_active(),
				'i18n'      => self::viewer_i18n(),
			)
		);
	}


	private function admin_notice( $message ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return '';
		}
		return '<p class="flipset-admin-notice">' . esc_html( $message ) . '</p>';
	}
}
