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
 *   - flipset/welcome/hero_actions  change the hero button and links
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
		// "Install" in the plugin list opens WordPress's own plugin details window.
		Flipset_Readscope_Plugins::enqueue();
	}

	/**
	 * Fixed outside addresses, all taken from readme.txt.
	 *
	 * @param string $key Link key.
	 * @return string
	 */
	public static function link( $key ) {
		$links = array(
			'site'      => 'https://flipset.thereadscope.com',
			'docs'      => 'https://flipset.thereadscope.com/flipset-docs/',
			'builders'  => 'https://flipset.thereadscope.com/flipset-docs/page-builders/',
			'video'     => 'https://youtu.be/RGBoQxO4mUM',
			'support'   => 'https://wordpress.org/support/plugin/flipset/',
			'review'    => 'https://wordpress.org/support/plugin/flipset/reviews/#new-post',
			'issues'    => 'https://github.com/itsmanzur/flipset/issues',
			'security'  => 'https://github.com/itsmanzur/flipset/security/policy',
			'changelog' => 'https://wordpress.org/plugins/flipset/#developers',
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
	 * @return array[] id => { title, sub, kicker, priority, callback }. `kicker` is the short name used in the page menu.
	 */
	private function sections() {
		$sections = array(
			'start'     => array(
				'kicker'   => __( 'Add a PDF', 'flipset' ),
				'title'    => __( 'Add a PDF your way', 'flipset' ),
				'sub'      => __( 'All of these show the same viewer.', 'flipset' ),
				'priority' => 10,
				'callback' => array( $this, 'section_start' ),
			),
			'features'  => array(
				'kicker'   => __( 'Features', 'flipset' ),
				'title'    => __( 'Included in Flipset', 'flipset' ),
				'sub'      => __( 'No paid features are sold or locked in this plugin.', 'flipset' ),
				'priority' => 20,
				'callback' => array( $this, 'section_features' ),
			),
			'changelog' => array(
				'kicker'   => __( 'What\'s new', 'flipset' ),
				'title'    => __( 'Recent releases', 'flipset' ),
				'sub'      => __( 'Read from the changelog in the plugin\'s readme.', 'flipset' ),
				'priority' => 30,
				'callback' => array( $this, 'section_changelog' ),
			),
			'faq'       => array(
				'kicker'   => __( 'Answers', 'flipset' ),
				'title'    => __( 'Quick answers', 'flipset' ),
				'sub'      => '',
				'priority' => 40,
				'callback' => array( $this, 'section_faq' ),
			),
			'help'      => array(
				'kicker'   => __( 'Help', 'flipset' ),
				'title'    => __( 'Need a hand?', 'flipset' ),
				'sub'      => '',
				'priority' => 50,
				'callback' => array( $this, 'section_help' ),
			),
			'family'    => array(
				'kicker'   => __( 'More plugins', 'flipset' ),
				'title'    => __( 'More from The Readscope', 'flipset' ),
				'sub'      => __( 'Free plugins by the same team on WordPress.org.', 'flipset' ),
				'priority' => 60,
				'callback' => array( $this, 'section_family' ),
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
	 * Hero button and links.
	 *
	 * @return array[]
	 */
	private function hero_actions() {
		$actions = array(
			array(
				'label' => __( 'Create your first embed', 'flipset' ),
				'url'   => admin_url( 'admin.php?page=' . Flipset_Admin::BUILDER_SLUG ),
				'style' => 'primary',
				'icon'  => 'plus-alt2',
			),
			array(
				'label'    => __( 'Documentation', 'flipset' ),
				'url'      => self::link( 'docs' ),
				'style'    => 'link',
				'external' => true,
			),
			array(
				'label'    => __( 'Live demo', 'flipset' ),
				'url'      => self::link( 'site' ),
				'style'    => 'link',
				'external' => true,
			),
			array(
				'label'    => __( 'Video on YouTube', 'flipset' ),
				'url'      => self::link( 'video' ),
				'style'    => 'link',
				'external' => true,
			),
		);
		/**
		 * Filter: flipset/welcome/hero_actions
		 *
		 * @param array[] $actions Each: label, url, style (primary|ghost-dark|link), icon, external (bool).
		 */
		return (array) apply_filters( 'flipset/welcome/hero_actions', $actions );
	}

	/**
	 * Print one hero action.
	 *
	 * @param array $action Item from hero_actions().
	 */
	private function render_action( $action ) {
		$style = isset( $action['style'] ) && in_array( $action['style'], array( 'primary', 'ghost-dark', 'link' ), true ) ? $action['style'] : 'link';
		$class = 'link' === $style ? 'fw-hero__link' : 'fa-btn fa-btn--' . $style;
		$icon  = isset( $action['icon'] ) ? sanitize_html_class( $action['icon'] ) : '';
		if ( ! empty( $action['external'] ) ) {
			self::external_link( $action['url'], $action['label'], $class, $icon );
			return;
		}
		?>
		<a class="<?php echo esc_attr( $class ); ?>" href="<?php echo esc_url( $action['url'] ); ?>">
			<?php if ( $icon ) : ?>
				<span class="dashicons dashicons-<?php echo esc_attr( $icon ); ?>" aria-hidden="true"></span>
			<?php endif; ?>
			<?php echo esc_html( $action['label'] ); ?>
		</a>
		<?php
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

		$dashboard_url = admin_url( 'admin.php?page=' . Flipset_Admin::PAGE_SLUG );
		$sections      = $this->sections();
		$buttons       = array();
		$links         = array();
		foreach ( $this->hero_actions() as $action ) {
			if ( empty( $action['label'] ) || empty( $action['url'] ) ) {
				continue;
			}
			if ( isset( $action['style'] ) && in_array( $action['style'], array( 'primary', 'ghost-dark' ), true ) ) {
				$buttons[] = $action;
			} else {
				$links[] = $action;
			}
		}
		$modes = array(
			'page'   => __( 'Page', 'flipset' ),
			'scroll' => __( 'Scroll', 'flipset' ),
			'spread' => __( 'Spread', 'flipset' ),
			'flip'   => __( '3D flipbook', 'flipset' ),
		);
		?>
		<div class="wrap flipset-admin fw-welcome">
			<hr class="wp-header-end">
			<div class="fa-hero fw-hero">
				<div class="fw-hero__grid">
					<div class="fw-hero__copy">
						<div class="fa-hero__kicker">
							<span class="fa-mark dashicons dashicons-book-alt" aria-hidden="true"></span>
							<h1 class="fa-hero__title"><?php esc_html_e( 'Welcome to Flipset', 'flipset' ); ?></h1>
						</div>
						<p class="fa-hero__lede fw-hero__lede"><?php esc_html_e( 'Embed PDFs with a fast PDF viewer, 3D flipbook and document library. Searchable PDF text, accessible, no CDN. Works with Gutenberg, Elementor, Divi.', 'flipset' ); ?></p>
						<ul class="fw-facts" aria-label="<?php esc_attr_e( 'At a glance', 'flipset' ); ?>">
							<li><?php esc_html_e( 'No CDN, no external requests, no cookies, no tracking', 'flipset' ); ?></li>
							<li><?php esc_html_e( 'PDF text is searchable in WordPress search', 'flipset' ); ?></li>
							<li><?php esc_html_e( 'Keyboard control and screen reader page announcements', 'flipset' ); ?></li>
						</ul>
						<div class="fw-hero__actions">
							<?php
							foreach ( $buttons as $action ) {
								$this->render_action( $action );
							}
							?>
							<?php if ( $links ) : ?>
								<span class="fw-hero__links">
									<?php
									foreach ( $links as $action ) {
										$this->render_action( $action );
									}
									?>
								</span>
							<?php endif; ?>
						</div>
					</div>
					<div class="fw-modes">
						<p class="fw-modes__title" id="fw-modes-title"><?php esc_html_e( 'Four views, one viewer', 'flipset' ); ?></p>
						<ul class="fw-modes__list" aria-labelledby="fw-modes-title">
							<?php foreach ( $modes as $mode => $label ) : ?>
								<li class="fw-mode fw-mode--<?php echo esc_attr( $mode ); ?>">
									<span class="fw-mode__art" aria-hidden="true"><i></i><i></i><i></i></span>
									<span class="fw-mode__label"><?php echo esc_html( $label ); ?></span>
									<code class="fw-mode__code">view="<?php echo esc_html( $mode ); ?>"</code>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>
				</div>
				<div class="fa-hero__divider" role="presentation"></div>
				<div class="fw-hero__foot">
					<span class="fw-version">
						<?php
						/* translators: %s: version number. */
						echo esc_html( sprintf( __( 'Version %s', 'flipset' ), FLIPSET_VERSION ) );
						?>
					</span>
					<?php if ( ! empty( $sections ) ) : ?>
						<nav class="fw-toc" aria-label="<?php esc_attr_e( 'On this page', 'flipset' ); ?>">
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
	 * Ways to add a PDF, as listed in readme.txt, one row each.
	 */
	public function section_start() {
		$settings     = get_option( Flipset_Admin::OPTION_NAME, array() );
		$autoembed_on = ! isset( $settings['autoembed_url'] ) || ! empty( $settings['autoembed_url'] );
		$builder_url  = admin_url( 'admin.php?page=' . Flipset_Admin::BUILDER_SLUG );
		$settings_url = admin_url( 'admin.php?page=' . Flipset_Admin::SETTINGS_SLUG );
		?>
		<ul class="fa-card fw-ways">
			<li class="fw-way">
				<span class="fa-tile-icon"><span class="dashicons dashicons-shortcode" aria-hidden="true"></span></span>
				<div class="fw-way__copy">
					<h3 class="fw-way__title"><?php esc_html_e( 'Shortcode', 'flipset' ); ?></h3>
					<p class="fw-way__text"><?php esc_html_e( 'Type it yourself, or build one in the Shortcode Builder with its live preview.', 'flipset' ); ?></p>
					<code class="fw-way__code">[flipset file="https://example.com/document.pdf"]</code>
				</div>
				<a class="fa-btn fa-btn--secondary fa-btn--sm fw-way__action" href="<?php echo esc_url( $builder_url ); ?>"><?php esc_html_e( 'Open the Shortcode Builder', 'flipset' ); ?></a>
			</li>
			<li class="fw-way">
				<span class="fa-tile-icon is-violet"><span class="dashicons dashicons-block-default" aria-hidden="true"></span></span>
				<div class="fw-way__copy">
					<h3 class="fw-way__title"><?php esc_html_e( 'Block', 'flipset' ); ?></h3>
					<p class="fw-way__text"><?php esc_html_e( 'Insert the "Flipset Viewer" block and pick a PDF from the Media Library. The editor shows the first page.', 'flipset' ); ?></p>
				</div>
				<a class="fa-btn fa-btn--secondary fa-btn--sm fw-way__action" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=page' ) ); ?>"><?php esc_html_e( 'Add a new page', 'flipset' ); ?></a>
			</li>
			<li class="fw-way">
				<span class="fa-tile-icon is-sky"><span class="dashicons dashicons-layout" aria-hidden="true"></span></span>
				<div class="fw-way__copy">
					<h3 class="fw-way__title"><?php esc_html_e( 'Page builder widget', 'flipset' ); ?></h3>
					<p class="fw-way__text"><?php esc_html_e( 'Elementor, Divi and Beaver Builder each have a Flipset Viewer and a Flipset Library widget.', 'flipset' ); ?></p>
				</div>
				<?php self::external_link( self::link( 'builders' ), __( 'Page builder guide', 'flipset' ), 'fa-btn fa-btn--secondary fa-btn--sm fw-way__action' ); ?>
			</li>
			<li class="fw-way">
				<span class="fa-tile-icon is-green"><span class="dashicons dashicons-admin-links" aria-hidden="true"></span></span>
				<div class="fw-way__copy">
					<h3 class="fw-way__title">
						<?php esc_html_e( 'Paste a link', 'flipset' ); ?>
						<?php if ( $autoembed_on ) : ?>
							<span class="fw-state is-on"><?php esc_html_e( 'On for this site', 'flipset' ); ?></span>
						<?php else : ?>
							<span class="fw-state is-off"><?php esc_html_e( 'Turned off in Settings', 'flipset' ); ?></span>
						<?php endif; ?>
					</h3>
					<p class="fw-way__text"><?php esc_html_e( 'Paste a direct .pdf link on its own line in a post or page.', 'flipset' ); ?></p>
				</div>
				<?php if ( ! $autoembed_on ) : ?>
					<a class="fa-btn fa-btn--secondary fa-btn--sm fw-way__action" href="<?php echo esc_url( $settings_url ); ?>"><?php esc_html_e( 'Open Settings', 'flipset' ); ?></a>
				<?php endif; ?>
			</li>
			<li class="fw-way">
				<span class="fa-tile-icon"><span class="dashicons dashicons-grid-view" aria-hidden="true"></span></span>
				<div class="fw-way__copy">
					<h3 class="fw-way__title"><?php esc_html_e( 'A gallery of all your PDFs', 'flipset' ); ?></h3>
					<p class="fw-way__text">
						<?php
						echo Flipset_Readme::inline_html( __( 'Add `[flipset_library]` to a page, or insert the Flipset Library block.', 'flipset' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside inline_html().
						?>
					</p>
				</div>
			</li>
		</ul>
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
		$items     = array_filter(
			Flipset_Admin::faq_items(),
			static function ( $item ) {
				return ! empty( $item['welcome'] );
			}
		);
		$guide_url = admin_url( 'admin.php?page=' . Flipset_Admin::DOCS_SLUG . '&tab=docs' ) . '#guide-faq';
		Flipset_Admin::render_faq_list( $items, false );
		?>
		<p class="fw-more">
			<a class="fa-link" href="<?php echo esc_url( $guide_url ); ?>"><?php esc_html_e( 'More in the user guide', 'flipset' ); ?> <span aria-hidden="true">&rarr;</span></a>
			<span class="fw-sep" aria-hidden="true">·</span>
			<?php self::external_link( self::link( 'docs' ), __( 'Online documentation', 'flipset' ) ); ?>
		</p>
		<?php
	}

	/**
	 * Support, review and bug links in one strip. No ratings or review text are shown or fetched.
	 */
	public function section_help() {
		$links = array(
			array( __( 'Support forum', 'flipset' ), self::link( 'support' ), 'sos' ),
			array( __( 'Report a bug or ask for a feature', 'flipset' ), self::link( 'issues' ), 'lightbulb' ),
			array( __( 'Report a security problem privately', 'flipset' ), self::link( 'security' ), 'lock' ),
			array( __( 'Leave a review on WordPress.org', 'flipset' ), self::link( 'review' ), 'star-filled' ),
		);
		?>
		<ul class="fa-card fw-help">
			<?php foreach ( $links as $link ) : ?>
				<li><?php self::external_link( $link[1], $link[0], 'fw-help__link', $link[2] ); ?></li>
			<?php endforeach; ?>
		</ul>
		<?php
	}

	/**
	 * The other Readscope plugins, with install state from this site.
	 */
	public function section_family() {
		( new Flipset_Readscope_Plugins( 'flipset' ) )->render();
	}

	/**
	 * Group names for changelog line types, in display order.
	 *
	 * @return string[]
	 */
	public static function change_labels() {
		return array(
			'new'       => __( 'New', 'flipset' ),
			'improved'  => __( 'Improved', 'flipset' ),
			'fixed'     => __( 'Fixed', 'flipset' ),
			'security'  => __( 'Security', 'flipset' ),
			'developer' => __( 'For developers', 'flipset' ),
			''          => __( 'Changes', 'flipset' ),
		);
	}

	/**
	 * Print changelog entries as <details> blocks (the first open), with the
	 * lines of each release grouped by type.
	 *
	 * @param array[] $entries From Flipset_Readme::changelog().
	 * @param string  $current Version installed now; marked "Installed".
	 */
	public static function render_changelog( $entries, $current = '' ) {
		if ( empty( $entries ) ) {
			echo '<p class="fw-more">' . esc_html__( 'No changelog found.', 'flipset' ) . '</p>';
			return;
		}
		$labels = self::change_labels();
		echo '<div class="fw-changelog">';
		foreach ( array_values( $entries ) as $i => $entry ) {
			$is_current = ( '' !== $current && $current === $entry['version'] );
			$is_ahead   = ( '' !== $current && version_compare( $entry['version'], $current, '>' ) );
			$groups     = array();
			foreach ( $entry['items'] as $item ) {
				$type              = isset( $labels[ $item['type'] ] ) ? $item['type'] : '';
				$groups[ $type ][] = $item['text'];
			}
			$groups = array_filter( array_replace( array_fill_keys( array_keys( $labels ), array() ), $groups ) );
			?>
			<details class="fa-card fw-release"<?php echo 0 === $i ? ' open' : ''; ?>>
				<summary class="fw-release__head">
					<span class="fw-release__version"><?php echo esc_html( $entry['version'] ); ?></span>
					<?php if ( $is_current ) : ?>
						<span class="fa-tag fa-tag--free"><?php esc_html_e( 'Installed', 'flipset' ); ?></span>
					<?php elseif ( $is_ahead ) : ?>
						<span class="fa-tag fw-tag--next"><?php esc_html_e( 'In development', 'flipset' ); ?></span>
					<?php endif; ?>
					<span class="fw-release__summary">
						<?php
						$parts = array();
						foreach ( $groups as $type => $lines ) {
							/* translators: 1: number of changes, 2: change group name such as "New" or "Fixed". */
							$parts[] = sprintf( __( '%1$d %2$s', 'flipset' ), count( $lines ), $labels[ $type ] );
						}
						echo esc_html( implode( ' · ', $parts ) );
						?>
					</span>
					<span class="dashicons dashicons-arrow-down-alt2 fw-release__arrow" aria-hidden="true"></span>
				</summary>
				<div class="fw-release__body">
					<?php foreach ( $entry['notes'] as $note ) : ?>
						<p class="fw-release__note"><?php echo Flipset_Readme::inline_html( $note ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside inline_html(). ?></p>
					<?php endforeach; ?>
					<?php foreach ( $groups as $type => $lines ) : ?>
						<div class="fw-group fw-group--<?php echo esc_attr( '' === $type ? 'other' : $type ); ?>">
							<h3 class="fw-group__title"><?php echo esc_html( $labels[ $type ] ); ?></h3>
							<ul class="fw-group__list">
								<?php foreach ( $lines as $line ) : ?>
									<li><?php echo Flipset_Readme::inline_html( $line ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside inline_html(). ?></li>
								<?php endforeach; ?>
							</ul>
						</div>
					<?php endforeach; ?>
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
