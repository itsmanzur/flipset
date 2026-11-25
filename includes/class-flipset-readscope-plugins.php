<?php
/**
 * "More from The Readscope": the other free plugins by the same author on WordPress.org.
 *
 * Shown as the last section of the Welcome screen. The list ships with the
 * plugin (no remote request); names and descriptions are taken from each
 * plugin's WordPress.org readme. Whether a plugin is installed or active is
 * read from get_plugins() on this site. "Install" opens WordPress's own
 * plugin details window, so WordPress.org is contacted only when the admin
 * clicks it. No prices and no paid versions are listed.
 *
 * The class is self-contained so the same file can be reused in the other
 * Readscope plugins: change the class prefix, the text domain and $self.
 *
 * @package Flipset
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * List and render the other Readscope plugins.
 */
class Flipset_Readscope_Plugins {

	/**
	 * WordPress.org slug of the plugin that ships this file; left out of the list.
	 *
	 * @var string
	 */
	private $self;

	/**
	 * Constructor.
	 *
	 * @param string $self WordPress.org slug of the current plugin.
	 */
	public function __construct( $self = 'flipset' ) {
		$this->self = (string) $self;
	}

	/**
	 * The plugins, in display order.
	 *
	 * @return array[] Each: slug, file (main plugin file name), name, text, icon (dashicon name).
	 */
	public function plugins() {
		$plugins = array(
			array(
				'slug' => 'accessiq',
				'file' => 'accessiq.php',
				'name' => 'AccessIQ',
				'text' => __( 'Free automated WCAG 2.1 & 2.2 accessibility checker + self-hosted click heatmaps + behavior analytics.', 'flipset' ),
				'icon' => 'universal-access',
			),
			array(
				'slug' => 'consentaro',
				'file' => 'consentaro.php',
				'name' => 'Consentaro',
				'text' => __( 'Ultra-light Google Consent Mode v2 for WordPress. Simple cookie banner, GTM loading after choice, WooCommerce-ready.', 'flipset' ),
				'icon' => 'shield',
			),
			array(
				'slug' => 'deskovi',
				'file' => 'deskovi.php',
				'name' => 'Deskovi',
				'text' => __( 'A lightweight WooCommerce helpdesk: tickets, order context for agents, and an optional customer chat widget — all stored locally on your site.', 'flipset' ),
				'icon' => 'format-chat',
			),
			array(
				'slug' => 'itsmanzur-docs',
				'file' => 'itsmanzur-docs.php',
				'name' => 'Nirdeshio',
				'text' => __( 'Product documentation, knowledge base, and help center for WordPress — fast, theme-independent, and easy to set up in 5 minutes.', 'flipset' ),
				'icon' => 'book',
			),
			array(
				'slug' => 'rawnaq',
				'file' => 'rawnaq.php',
				'name' => 'Rawnaq',
				'text' => __( 'Widgets and blocks for architects, interior designers and builders: quote form, timelines, diagrams, case studies. Elementor, Gutenberg, Divi.', 'flipset' ),
				'icon' => 'admin-appearance',
			),
			array(
				'slug' => 'masjidos',
				'file' => 'masjidos.php',
				'name' => 'MasjidOS',
				'text' => __( 'Mosque management for WordPress: prayer times, Jumuah & Minbar, Duas, Quran, Islamic calendar, TV display, events, and notices.', 'flipset' ),
				'icon' => 'building',
			),
			array(
				'slug' => 'the-readscope-tour-and-accommodation-booking',
				'file' => 'the-readscope-tour-and-accommodation-booking.php',
				'name' => 'The Readscope Tour and Accommodation Booking',
				'text' => __( 'The Next-Gen Travel, Tour & Accommodation Booking Engine for WordPress. Dual-engine architecture for tours and hotel stays.', 'flipset' ),
				'icon' => 'palmtree',
			),
		);

		/**
		 * Filter: flipset/welcome/readscope_plugins
		 *
		 * @param array[] $plugins Each: slug, file, name, text, icon.
		 */
		$plugins = (array) apply_filters( 'flipset/welcome/readscope_plugins', $plugins );

		$self = $this->self;
		return array_values(
			array_filter(
				$plugins,
				static function ( $plugin ) use ( $self ) {
					return is_array( $plugin ) && ! empty( $plugin['slug'] ) && ! empty( $plugin['name'] ) && $self !== $plugin['slug'];
				}
			)
		);
	}

	/**
	 * Find an installed copy of a plugin by its main file name.
	 *
	 * The folder can differ from the WordPress.org slug (a renamed or development
	 * copy), so the main file name is matched first, then slug/slug.php.
	 *
	 * @param array $plugin    Item from plugins().
	 * @param array $installed Result of get_plugins().
	 * @return string Plugin basename, or '' when not installed.
	 */
	public static function find_installed( $plugin, $installed ) {
		$file = ! empty( $plugin['file'] ) ? $plugin['file'] : $plugin['slug'] . '.php';
		if ( isset( $installed[ $plugin['slug'] . '/' . $file ] ) ) {
			return $plugin['slug'] . '/' . $file;
		}
		foreach ( array_keys( $installed ) as $basename ) {
			if ( basename( $basename ) === $file ) {
				return $basename;
			}
		}
		return '';
	}

	/**
	 * Install/activate state for each plugin.
	 *
	 * @return array[] Items from plugins() plus `state` (active|installed|missing) and `basename`.
	 */
	public function with_state() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$installed = get_plugins();
		$list      = array();
		foreach ( $this->plugins() as $plugin ) {
			$basename           = self::find_installed( $plugin, $installed );
			$plugin['basename'] = $basename;
			if ( '' === $basename ) {
				$plugin['state'] = 'missing';
			} elseif ( is_plugin_active( $basename ) || ( is_multisite() && is_plugin_active_for_network( $basename ) ) ) {
				$plugin['state'] = 'active';
			} else {
				$plugin['state'] = 'installed';
			}
			$list[] = $plugin;
		}
		return $list;
	}

	/**
	 * Load WordPress's plugin details window (Thickbox) on the screen that shows the list.
	 */
	public static function enqueue() {
		if ( current_user_can( 'install_plugins' ) ) {
			add_thickbox();
			wp_enqueue_script( 'plugin-install' );
		}
	}

	/**
	 * Print the list.
	 */
	public function render() {
		$plugins = $this->with_state();
		if ( empty( $plugins ) ) {
			return;
		}
		?>
		<ul class="fw-family">
			<?php foreach ( $plugins as $plugin ) : ?>
				<li class="fa-card fw-family__item is-<?php echo esc_attr( $plugin['state'] ); ?>">
					<div class="fw-family__head">
						<span class="fa-tile-icon"><span class="dashicons dashicons-<?php echo esc_attr( $plugin['icon'] ); ?>" aria-hidden="true"></span></span>
						<h3 class="fw-family__name"><?php echo esc_html( $plugin['name'] ); ?></h3>
					</div>
					<p class="fw-family__text"><?php echo esc_html( $plugin['text'] ); ?></p>
					<div class="fw-family__foot">
						<?php $this->render_action( $plugin ); ?>
						<?php
						Flipset_Welcome::external_link(
							'https://wordpress.org/plugins/' . rawurlencode( $plugin['slug'] ) . '/',
							__( 'WordPress.org', 'flipset' ),
							'fa-link fw-family__more'
						);
						?>
					</div>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php
	}

	/**
	 * Status or button for one plugin.
	 *
	 * @param array $plugin Item from with_state().
	 */
	private function render_action( $plugin ) {
		if ( 'active' === $plugin['state'] ) {
			echo '<span class="fw-state is-on"><span class="dashicons dashicons-yes" aria-hidden="true"></span>' . esc_html__( 'Active', 'flipset' ) . '</span>';
			return;
		}

		if ( 'installed' === $plugin['state'] ) {
			if ( current_user_can( 'activate_plugin', $plugin['basename'] ) ) {
				$url = wp_nonce_url( admin_url( 'plugins.php?action=activate&plugin=' . rawurlencode( $plugin['basename'] ) ), 'activate-plugin_' . $plugin['basename'] );
				printf(
					'<a class="fa-btn fa-btn--secondary fa-btn--sm" href="%1$s">%2$s<span class="screen-reader-text"> %3$s</span></a>',
					esc_url( $url ),
					esc_html__( 'Activate', 'flipset' ),
					esc_html( $plugin['name'] )
				);
			} else {
				echo '<span class="fw-state is-off">' . esc_html__( 'Installed', 'flipset' ) . '</span>';
			}
			return;
		}

		if ( current_user_can( 'install_plugins' ) ) {
			// WordPress's own details window; it has the Install Now button.
			$url = self_admin_url( 'plugin-install.php?tab=plugin-information&plugin=' . rawurlencode( $plugin['slug'] ) . '&TB_iframe=true&width=772&height=600' );
			printf(
				'<a class="fa-btn fa-btn--secondary fa-btn--sm thickbox open-plugin-details-modal" href="%1$s" aria-label="%2$s">%3$s</a>',
				esc_url( $url ),
				/* translators: %s: plugin name. */
				esc_attr( sprintf( __( 'Install %s: opens the plugin details', 'flipset' ), $plugin['name'] ) ),
				esc_html__( 'Install', 'flipset' )
			);
		}
	}
}
