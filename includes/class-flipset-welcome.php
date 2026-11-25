<?php
/**
 * Welcome screen: Flipset → Welcome.
 *
 * Opened once after the first activation (see flipset_maybe_activation_redirect()
 * in flipset.php) and always reachable from the menu afterwards. Every claim on
 * the page comes from readme.txt; the changelog is read from it at runtime and
 * the FAQ and features come from the same arrays the Docs & Features screen uses.
 * The page makes no remote requests: the video is a plain link.
 *
 * Add-ons extend the page with filters only:
 *   - flipset/welcome/sections      add, move or remove sections
 *   - flipset/welcome/hero_actions  change the hero buttons
 *   - flipset/welcome/after_hero    print something right under the hero
 *
 * @package Flipset
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Welcome screen controller.
 */
class Flipset_Welcome {

	/**
	 * Admin page slug.
	 */
	const SLUG = 'flipset-welcome';

	/**
	 * Option set the first time the Welcome screen is shown. After that the
	 * activation redirect never runs again, even on reactivation.
	 */
	const SEEN_OPTION = 'flipset_welcome_seen';

	/**
	 * Hook the page into the admin.
	 */
	public function init() {
		// Priority 30: after Flipset's own pages (10) and Pro's (20), so Welcome is the last item.
		add_action( 'admin_menu', array( $this, 'add_page' ), 30 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_filter( 'plugin_row_meta', array( $this, 'row_meta' ), 10, 2 );
	}

	/**
	 * URL of the Welcome screen.
	 *
	 * @return string
	 */
	public static function url() {
		return admin_url( 'admin.php?page=' . self::SLUG );
	}

	/**
	 * Has the Welcome screen been shown on this site?
	 *
	 * @return bool
	 */
	public static function seen() {
		return (bool) get_option( self::SEEN_OPTION );
	}

	/**
	 * Register Flipset → Welcome.
	 */
	public function add_page() {
		add_submenu_page(
			Flipset_Admin::PAGE_SLUG,
			__( 'Welcome to Flipset', 'flipset' ),
			__( 'Welcome', 'flipset' ),
			'manage_options',
			self::SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Add a "Welcome" link under the plugin description on the Plugins screen.
	 *
	 * @param string[] $links Row meta links.
	 * @param string   $file  Plugin basename.
	 * @return string[]
	 */
	public function row_meta( $links, $file ) {
		if ( FLIPSET_BASENAME === $file && current_user_can( 'manage_options' ) ) {
			$links[] = '<a href="' . esc_url( self::url() ) . '">' . esc_html__( 'Welcome & what\'s new', 'flipset' ) . '</a>';
		}
		return $links;
	}

	/**
	 * Styles for the Welcome screen only. No scripts: the FAQ and changelog use native <details>.
	 *
	 * @param string $hook Current admin page hook suffix.
	 */
	public function enqueue( $hook ) {
		if ( false === strpos( (string) $hook, self::SLUG ) ) {
			return;
		}
		$ver = static function ( $relative ) {
			return ( defined( 'WP_DEBUG' ) && WP_DEBUG && file_exists( FLIPSET_DIR . $relative ) ) ? FLIPSET_VERSION . '.' . filemtime( FLIPSET_DIR . $relative ) : FLIPSET_VERSION;
		};
		wp_enqueue_style( 'flipset-admin-ui', Flipset::asset_url( 'assets/css/flipset-admin-ui.css' ), array(), $ver( 'assets/css/flipset-admin-ui.css' ) );
		// FAQ and feature-card styles are shared with Docs & Features.
		wp_enqueue_style( 'flipset-docs', Flipset::asset_url( 'assets/css/flipset-docs.css' ), array( 'flipset-admin-ui' ), $ver( 'assets/css/flipset-docs.css' ) );
		wp_enqueue_style( 'flipset-welcome', Flipset::asset_url( 'assets/css/flipset-welcome.css' ), array( 'flipset-admin-ui', 'flipset-docs' ), $ver( 'assets/css/flipset-welcome.css' ) );
	}

	/**
	 * Fixed outside addresses, all taken from readme.txt.
	 *
	 * @param string $key Link key.
	 * @return string
	 */
	public static function link( $key ) {
		$links = array(
			'site'       => 'https://flipset.thereadscope.com',
			'docs'       => 'https://flipset.thereadscope.com/flipset-docs/',
			'builders'   => 'https://flipset.thereadscope.com/flipset-docs/page-builders/',
			'video'      => 'https://youtu.be/RGBoQxO4mUM',
			'support'    => 'https://wordpress.org/support/plugin/flipset/',
			'review'     => 'https://wordpress.org/support/plugin/flipset/reviews/#new-post',
			'issues'     => 'https://github.com/itsmanzur/flipset/issues',
			'security'   => 'https://github.com/itsmanzur/flipset/security/policy',
			'changelog'  => 'https://wordpress.org/plugins/flipset/#developers',
		);
		return isset( $links[ $key ] ) ? $links[ $key ] : '';
	}

	/**
	 * Print a link that opens in a new tab, with the screen-reader hint.
	 *
	 * @param string $url   Address.
	 * @param string $label Visible text.
	 * @param string $class CSS classes.
	 * @param string $icon  Optional dashicon name (without the "dashicons-" prefix).
	 */
	public static function external_link( $url, $label, $class = 'fa-link', $icon = '' ) {
		printf(
			'<a class="%1$s" href="%2$s" target="_blank" rel="noopener noreferrer">%3$s%4$s<span class="screen-reader-text"> %5$s</span><span class="fw-ext dashicons dashicons-external" aria-hidden="true"></span></a>',
			esc_attr( $class ),
			esc_url( $url ),
			$icon ? '<span class="dashicons dashicons-' . esc_attr( $icon ) . '" aria-hidden="true"></span>' : '',
			esc_html( $label ),
			esc_html__( '(opens in a new tab)', 'flipset' )
		);
	}

	/**
	 * Sections below the hero, in order.
	 *
	 * @return array[] id => { title, sub, kicker, priority, callback }.
	 */
	private function sections() {
		$sections = array(
			'start'     => array(
				'kicker'   => __( 'Quick start', 'flipset' ),
				'title'    => __( 'Four ways to put a PDF on a page', 'flipset' ),
				'sub'      => __( 'Each one shows the same viewer. Pick the one that fits how you build.', 'flipset' ),
				'priority' => 10,
				'callback' => array( $this, 'section_start' ),
			),
			'features'  => array(
				'kicker'   => __( 'In the free plugin', 'flipset' ),
				'title'    => __( 'What Flipset does', 'flipset' ),
				'sub'      => '',
				'priority' => 20,
				'callback' => array( $this, 'section_features' ),
			),
			'faq'       => array(
				'kicker'   => __( 'FAQ', 'flipset' ),
				'title'    => __( 'Common questions', 'flipset' ),
				'sub'      => '',
				'priority' => 30,
				'callback' => array( $this, 'section_faq' ),
			),
			'help'      => array(
				'kicker'   => __( 'Help', 'flipset' ),
				'title'    => __( 'Support and feedback', 'flipset' ),
				'sub'      => '',
				'priority' => 40,
				'callback' => array( $this, 'section_help' ),
			),
			'changelog' => array(
				'kicker'   => __( 'Changelog', 'flipset' ),
				'title'    => __( 'What\'s new in Flipset', 'flipset' ),
				'sub'      => __( 'The latest releases, from the plugin\'s readme.', 'flipset' ),
				'priority' => 50,
				'callback' => array( $this, 'section_changelog' ),
			),
		);

		/**
		 * Filter: flipset/welcome/sections
		 *
		 * Add, reorder or remove Welcome screen sections. Each section prints its
		 * own body; the heading (h2) is printed for it from `title`.
		 *
		 * @param array[] $sections id => { kicker, title, sub, priority, callback }.
		 */
		$sections = (array) apply_filters( 'flipset/welcome/sections', $sections );

		$sections = array_filter(
			$sections,
			static function ( $section ) {
				return is_array( $section ) && ! empty( $section['title'] ) && isset( $section['callback'] ) && is_callable( $section['callback'] );
			}
		);
		uasort(
			$sections,
			static function ( $a, $b ) {
				return ( isset( $a['priority'] ) ? (int) $a['priority'] : 50 ) <=> ( isset( $b['priority'] ) ? (int) $b['priority'] : 50 );
			}
		);
		return $sections;
	}

	/**
	 * Render the Welcome screen.
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( ! self::seen() ) {
			update_option( self::SEEN_OPTION, FLIPSET_VERSION, false );
		}

		$builder_url   = admin_url( 'admin.php?page=' . Flipset_Admin::BUILDER_SLUG );
		$dashboard_url = admin_url( 'admin.php?page=' . Flipset_Admin::PAGE_SLUG );
		$sections      = $this->sections();

		$actions = array(
			array(
				'label' => __( 'Create your first embed', 'flipset' ),
				'url'   => $builder_url,
				'style' => 'primary',
				'icon'  => 'plus-alt2',
			),
			array(
				'label'    => __( 'Read the docs', 'flipset' ),
				'url'      => self::link( 'docs' ),
				'style'    => 'ghost-dark',
				'icon'     => 'book',
				'external' => true,
			),
			array(
				'label'    => __( 'See the live demo', 'flipset' ),
				'url'      => self::link( 'site' ),
				'style'    => 'ghost-dark',
				'icon'     => 'visibility',
				'external' => true,
			),
		);
		/**
		 * Filter: flipset/welcome/hero_actions
		 *
		 * @param array[] $actions Each: label, url, style (primary|ghost-dark), icon, external (bool).
		 */
		$actions = (array) apply_filters( 'flipset/welcome/hero_actions', $actions );
		?>
		<div class="wrap flipset-admin fw-welcome">
			<hr class="wp-header-end">
			<div class="fa-hero fw-hero">
				<div class="fw-hero__grid">
					<div class="fw-hero__copy">
						<p class="fa-hero__label"><?php esc_html_e( 'Thanks for installing Flipset', 'flipset' ); ?></p>
						<div class="fa-hero__kicker">
							<span class="fa-mark dashicons dashicons-book-alt" aria-hidden="true"></span>
							<h1 class="fa-hero__title"><?php esc_html_e( 'Welcome to Flipset', 'flipset' ); ?></h1>
							<span class="fw-version">
								<?php
								/* translators: %s: version number. */
								echo esc_html( sprintf( __( 'Version %s', 'flipset' ), FLIPSET_VERSION ) );
								?>
							</span>
						</div>
						<p class="fa-hero__lede fw-hero__lede"><?php esc_html_e( 'Embed PDFs with a fast PDF viewer, 3D flipbook and document library. Searchable PDF text, accessible, no CDN. Works with Gutenberg, Elementor, Divi.', 'flipset' ); ?></p>
						<ul class="fw-facts" aria-label="<?php esc_attr_e( 'At a glance', 'flipset' ); ?>">
							<li><span class="dashicons dashicons-shield" aria-hidden="true"></span><?php esc_html_e( 'No CDN, no external requests, no cookies, no tracking', 'flipset' ); ?></li>
							<li><span class="dashicons dashicons-book" aria-hidden="true"></span><?php esc_html_e( 'Page, scroll, spread and 3D flipbook views', 'flipset' ); ?></li>
							<li><span class="dashicons dashicons-search" aria-hidden="true"></span><?php esc_html_e( 'PDF text is searchable in WordPress search', 'flipset' ); ?></li>
							<li><span class="dashicons dashicons-universal-access" aria-hidden="true"></span><?php esc_html_e( 'Keyboard control and screen reader page announcements', 'flipset' ); ?></li>
						</ul>
						<div class="fa-hero__actions fw-hero__actions">
							<?php foreach ( $actions as $action ) : ?>
								<?php
								if ( empty( $action['label'] ) || empty( $action['url'] ) ) {
									continue;
								}
								$class = 'fa-btn fa-btn--' . ( ( isset( $action['style'] ) && 'primary' === $action['style'] ) ? 'primary' : 'ghost-dark' );
								$icon  = isset( $action['icon'] ) ? sanitize_html_class( $action['icon'] ) : '';
								if ( ! empty( $action['external'] ) ) {
									self::external_link( $action['url'], $action['label'], $class, $icon );
									continue;
								}
								?>
								<a class="<?php echo esc_attr( $class ); ?>" href="<?php echo esc_url( $action['url'] ); ?>">
									<?php if ( $icon ) : ?>
										<span class="dashicons dashicons-<?php echo esc_attr( $icon ); ?>" aria-hidden="true"></span>
									<?php endif; ?>
									<?php echo esc_html( $action['label'] ); ?>
								</a>
							<?php endforeach; ?>
						</div>
					</div>
					<a class="fw-video" href="<?php echo esc_url( self::link( 'video' ) ); ?>" target="_blank" rel="noopener noreferrer">
						<span class="fw-video__page fw-video__page--back" aria-hidden="true"></span>
						<span class="fw-video__page" aria-hidden="true">
							<span class="fw-video__play dashicons dashicons-controls-play"></span>
						</span>
						<span class="fw-video__label">
							<?php esc_html_e( 'Watch the video on YouTube', 'flipset' ); ?>
							<span class="screen-reader-text"><?php esc_html_e( '(opens in a new tab)', 'flipset' ); ?></span>
						</span>
					</a>
				</div>
				<div class="fa-hero__divider" role="presentation"></div>
				<div class="fw-hero__foot">
					<?php if ( ! empty( $sections ) ) : ?>
						<nav class="fw-toc" aria-label="<?php esc_attr_e( 'On this page', 'flipset' ); ?>">
							<span class="fa-hero__label"><?php esc_html_e( 'On this page:', 'flipset' ); ?></span>
							<?php foreach ( $sections as $id => $section ) : ?>
								<a class="fa-chip" href="#fw-<?php echo esc_attr( sanitize_key( $id ) ); ?>"><?php echo esc_html( ! empty( $section['kicker'] ) ? $section['kicker'] : $section['title'] ); ?></a>
							<?php endforeach; ?>
						</nav>
					<?php endif; ?>
					<a class="fw-skip" href="<?php echo esc_url( $dashboard_url ); ?>"><?php esc_html_e( 'Go to the dashboard', 'flipset' ); ?> <span aria-hidden="true">&rarr;</span></a>
				</div>
			</div>

			<?php
			/**
			 * Action: flipset/welcome/after_hero
			 *
			 * Print extra content right under the Welcome screen hero.
			 */
			do_action( 'flipset/welcome/after_hero' );
			?>

			<?php foreach ( $sections as $id => $section ) : ?>
				<?php $sid = 'fw-' . sanitize_key( $id ); ?>
				<section class="fw-section fw-section--<?php echo esc_attr( sanitize_key( $id ) ); ?>" id="<?php echo esc_attr( $sid ); ?>" aria-labelledby="<?php echo esc_attr( $sid ); ?>-title">
					<header class="fw-section__head">
						<?php if ( ! empty( $section['kicker'] ) ) : ?>
							<p class="fw-kicker"><?php echo esc_html( $section['kicker'] ); ?></p>
						<?php endif; ?>
						<h2 class="fw-section__title" id="<?php echo esc_attr( $sid ); ?>-title"><?php echo esc_html( $section['title'] ); ?></h2>
						<?php if ( ! empty( $section['sub'] ) ) : ?>
							<p class="fw-section__sub"><?php echo esc_html( $section['sub'] ); ?></p>
						<?php endif; ?>
					</header>
					<?php call_user_func( $section['callback'] ); ?>
				</section>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Quick start: the four ways readme.txt lists for adding a PDF.
	 */
	public function section_start() {
		$settings     = get_option( Flipset_Admin::OPTION_NAME, array() );
		$autoembed_on = ! isset( $settings['autoembed_url'] ) || ! empty( $settings['autoembed_url'] );
		$builder_url  = admin_url( 'admin.php?page=' . Flipset_Admin::BUILDER_SLUG );
		$settings_url = admin_url( 'admin.php?page=' . Flipset_Admin::SETTINGS_SLUG );
		?>
		<ol class="fw-steps">
			<li class="fa-card fw-step">
				<span class="fa-num" aria-hidden="true">1</span>
				<h3 class="fw-step__title"><?php esc_html_e( 'Shortcode', 'flipset' ); ?></h3>
				<p class="fw-step__text"><?php esc_html_e( 'Type it yourself, or build one in the Shortcode Builder with its live preview.', 'flipset' ); ?></p>
				<code class="fw-step__code">[flipset file="https://example.com/document.pdf"]</code>
				<a class="fa-btn fa-btn--secondary fa-btn--sm" href="<?php echo esc_url( $builder_url ); ?>"><?php esc_html_e( 'Open the Shortcode Builder', 'flipset' ); ?></a>
			</li>
			<li class="fa-card fw-step">
				<span class="fa-num" aria-hidden="true">2</span>
				<h3 class="fw-step__title"><?php esc_html_e( 'Block', 'flipset' ); ?></h3>
				<p class="fw-step__text"><?php esc_html_e( 'Insert the "Flipset Viewer" block and pick a PDF from the Media Library. The editor shows the first page.', 'flipset' ); ?></p>
				<a class="fa-btn fa-btn--secondary fa-btn--sm" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=page' ) ); ?>"><?php esc_html_e( 'Add a new page', 'flipset' ); ?></a>
			</li>
			<li class="fa-card fw-step">
				<span class="fa-num" aria-hidden="true">3</span>
				<h3 class="fw-step__title"><?php esc_html_e( 'Page builder widget', 'flipset' ); ?></h3>
				<p class="fw-step__text"><?php esc_html_e( 'Elementor, Divi and Beaver Builder each have a Flipset Viewer and a Flipset Library widget.', 'flipset' ); ?></p>
				<?php self::external_link( self::link( 'builders' ), __( 'Page builder guide', 'flipset' ), 'fa-btn fa-btn--secondary fa-btn--sm' ); ?>
			</li>
			<li class="fa-card fw-step">
				<span class="fa-num" aria-hidden="true">4</span>
				<h3 class="fw-step__title"><?php esc_html_e( 'Paste a link', 'flipset' ); ?></h3>
				<p class="fw-step__text"><?php esc_html_e( 'Paste a direct .pdf link on its own line in a post or page.', 'flipset' ); ?></p>
				<?php if ( $autoembed_on ) : ?>
					<p class="fw-state is-on"><span class="dashicons dashicons-yes" aria-hidden="true"></span><?php esc_html_e( 'On for this site', 'flipset' ); ?></p>
				<?php else : ?>
					<p class="fw-state is-off"><span class="dashicons dashicons-minus" aria-hidden="true"></span><?php esc_html_e( 'Turned off in Settings', 'flipset' ); ?></p>
					<a class="fa-btn fa-btn--secondary fa-btn--sm" href="<?php echo esc_url( $settings_url ); ?>"><?php esc_html_e( 'Open Settings', 'flipset' ); ?></a>
				<?php endif; ?>
			</li>
		</ol>
		<p class="fw-note">
			<?php esc_html_e( 'Want a gallery of all your PDFs?', 'flipset' ); ?>
			<?php
			echo Flipset_Readme::inline_html( __( 'Add `[flipset_library]` to a page, or insert the Flipset Library block.', 'flipset' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside inline_html().
			?>
		</p>
		<?php
	}

	/**
	 * Feature highlights: the first items of each Features tab card.
	 */
	public function section_features() {
		$features_url = admin_url( 'admin.php?page=' . Flipset_Admin::DOCS_SLUG . '&tab=features' );
		?>
		<div class="fw-features">
			<?php foreach ( Flipset_Admin::feature_cards() as $card ) : ?>
				<div class="fa-card fw-feature">
					<div class="fw-feature__head">
						<span class="fa-tile-icon <?php echo esc_attr( $card['tint'] ); ?>"><span class="dashicons <?php echo esc_attr( $card['icon'] ); ?>" aria-hidden="true"></span></span>
						<h3 class="fw-feature__title"><?php echo esc_html( $card['title'] ); ?></h3>
					</div>
					<ul class="fa-feature__list">
						<?php foreach ( array_slice( $card['items'], 0, 3 ) as $item ) : ?>
							<li>
								<span class="fa-feature__check dashicons dashicons-yes" aria-hidden="true"></span>
								<span><?php echo esc_html( rtrim( $item[0], ':' ) ); ?></span>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endforeach; ?>
		</div>
		<p class="fw-more"><a class="fa-link" href="<?php echo esc_url( $features_url ); ?>"><?php esc_html_e( 'See every feature', 'flipset' ); ?> <span aria-hidden="true">&rarr;</span></a></p>
		<?php
	}

	/**
	 * Short FAQ (items flagged `welcome`), with a way to the full answers.
	 */
	public function section_faq() {
		$items = array_filter(
			Flipset_Admin::faq_items(),
			static function ( $item ) {
				return ! empty( $item['welcome'] );
			}
		);
		$guide_url = admin_url( 'admin.php?page=' . Flipset_Admin::DOCS_SLUG . '&tab=docs' ) . '#guide-faq';
		Flipset_Admin::render_faq_list( $items, true );
		?>
		<div class="fa-card fw-callout">
			<div class="fw-callout__copy">
				<h3 class="fw-callout__title"><?php esc_html_e( 'Need more answers?', 'flipset' ); ?></h3>
				<p><?php esc_html_e( 'The user guide in Docs & Features covers the shortcode settings, the document library, deep links and page builders.', 'flipset' ); ?></p>
			</div>
			<div class="fw-callout__actions">
				<a class="fa-btn fa-btn--secondary fa-btn--sm" href="<?php echo esc_url( $guide_url ); ?>"><?php esc_html_e( 'Open the user guide', 'flipset' ); ?></a>
				<?php self::external_link( self::link( 'docs' ), __( 'Online documentation', 'flipset' ), 'fa-btn fa-btn--primary fa-btn--sm' ); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Support, reviews and bug reports. No ratings or review text are shown or fetched.
	 */
	public function section_help() {
		$cards = array(
			array(
				'icon'  => 'sos',
				'tint'  => '',
				'title' => __( 'Ask a question', 'flipset' ),
				'text'  => __( 'Get help in the support forum on WordPress.org.', 'flipset' ),
				'url'   => self::link( 'support' ),
				'cta'   => __( 'Support forum', 'flipset' ),
			),
			array(
				'icon'  => 'star-filled',
				'tint'  => 'is-violet',
				'title' => __( 'Enjoying Flipset?', 'flipset' ),
				'text'  => __( 'A review on WordPress.org helps other site owners find the plugin.', 'flipset' ),
				'url'   => self::link( 'review' ),
				'cta'   => __( 'Write a review', 'flipset' ),
			),
			array(
				'icon'  => 'lightbulb',
				'tint'  => 'is-sky',
				'title' => __( 'Report a bug or ask for a feature', 'flipset' ),
				'text'  => __( 'Open an issue on GitHub.', 'flipset' ),
				'url'   => self::link( 'issues' ),
				'cta'   => __( 'GitHub issues', 'flipset' ),
			),
			array(
				'icon'  => 'lock',
				'tint'  => 'is-green',
				'title' => __( 'Found a security problem?', 'flipset' ),
				'text'  => __( 'Please do not post it in the public forum. Use GitHub\'s private reporting.', 'flipset' ),
				'url'   => self::link( 'security' ),
				'cta'   => __( 'Security policy', 'flipset' ),
			),
		);
		?>
		<ul class="fw-help">
			<?php foreach ( $cards as $card ) : ?>
				<li class="fa-card fw-help__card">
					<span class="fa-tile-icon <?php echo esc_attr( $card['tint'] ); ?>"><span class="dashicons dashicons-<?php echo esc_attr( $card['icon'] ); ?>" aria-hidden="true"></span></span>
					<h3 class="fw-help__title"><?php echo esc_html( $card['title'] ); ?></h3>
					<p class="fw-help__text"><?php echo esc_html( $card['text'] ); ?></p>
					<?php self::external_link( $card['url'], $card['cta'], 'fa-link fw-help__link' ); ?>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php
	}

	/**
	 * Labels for changelog line types.
	 *
	 * @return string[]
	 */
	public static function change_labels() {
		return array(
			'new'       => __( 'New', 'flipset' ),
			'improved'  => __( 'Improved', 'flipset' ),
			'fixed'     => __( 'Fixed', 'flipset' ),
			'security'  => __( 'Security', 'flipset' ),
			'developer' => __( 'Developer', 'flipset' ),
		);
	}

	/**
	 * Print changelog entries as <details> blocks, the first one open.
	 *
	 * @param array[] $entries From Flipset_Readme::changelog().
	 * @param string  $current Version installed now; marked "Installed".
	 */
	public static function render_changelog( $entries, $current = '' ) {
		if ( empty( $entries ) ) {
			echo '<p class="fw-note">' . esc_html__( 'No changelog found.', 'flipset' ) . '</p>';
			return;
		}
		$labels = self::change_labels();
		echo '<div class="fw-changelog">';
		foreach ( array_values( $entries ) as $i => $entry ) {
			$is_current = ( '' !== $current && $current === $entry['version'] );
			$is_ahead   = ( '' !== $current && version_compare( $entry['version'], $current, '>' ) );
			?>
			<details class="fa-card fw-release"<?php echo 0 === $i ? ' open' : ''; ?>>
				<summary class="fw-release__head">
					<span class="fw-release__version">
						<?php
						/* translators: %s: version number. */
						echo esc_html( sprintf( __( 'Version %s', 'flipset' ), $entry['version'] ) );
						?>
					</span>
					<?php if ( $is_current ) : ?>
						<span class="fa-tag fa-tag--free"><?php esc_html_e( 'Installed', 'flipset' ); ?></span>
					<?php elseif ( $is_ahead ) : ?>
						<span class="fa-tag fw-tag--next"><?php esc_html_e( 'In development', 'flipset' ); ?></span>
					<?php endif; ?>
					<span class="fw-release__count">
						<?php
						/* translators: %d: number of changes in a release. */
						echo esc_html( sprintf( _n( '%d change', '%d changes', count( $entry['items'] ), 'flipset' ), count( $entry['items'] ) ) );
						?>
					</span>
					<span class="dashicons dashicons-arrow-down-alt2 fw-release__arrow" aria-hidden="true"></span>
				</summary>
				<div class="fw-release__body">
					<?php foreach ( $entry['notes'] as $note ) : ?>
						<p class="fw-release__note"><?php echo Flipset_Readme::inline_html( $note ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside inline_html(). ?></p>
					<?php endforeach; ?>
					<?php if ( ! empty( $entry['items'] ) ) : ?>
						<ul class="fw-release__list">
							<?php foreach ( $entry['items'] as $item ) : ?>
								<li>
									<?php if ( '' !== $item['type'] && isset( $labels[ $item['type'] ] ) ) : ?>
										<span class="fw-tag fw-tag--<?php echo esc_attr( $item['type'] ); ?>"><?php echo esc_html( $labels[ $item['type'] ] ); ?></span>
									<?php endif; ?>
									<span class="fw-release__text"><?php echo Flipset_Readme::inline_html( $item['text'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside inline_html(). ?></span>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>
			</details>
			<?php
		}
		echo '</div>';
	}

	/**
	 * Latest releases, read from readme.txt.
	 */
	public function section_changelog() {
		self::render_changelog( Flipset_Readme::changelog( FLIPSET_DIR . 'readme.txt', 3 ), FLIPSET_VERSION );
		?>
		<p class="fw-more"><?php self::external_link( self::link( 'changelog' ), __( 'Full changelog on WordPress.org', 'flipset' ) ); ?></p>
		<?php
	}
}
