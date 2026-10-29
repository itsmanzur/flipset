<?php
/**
 * Plugin Name:       Flipset – PDF Viewer, PDF Embed, 3D Flipbook & Document Library
 * Plugin URI:        https://flipset.thereadscope.com
 * Description:       Embed PDFs with a fast PDF viewer, 3D flipbook and document library. Searchable PDF text, accessible, no CDN. Works with Gutenberg, Elementor, Divi.
 * Version:           1.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            The Readscope
 * Author URI:        https://thereadscope.com
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       flipset
 * Domain Path:       /languages
 *
 * @package Flipset
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * ---------------------------------------------------------------------
 * Core constants
 * ---------------------------------------------------------------------
 * Every other file in the plugin should reference these rather than
 * hard-coding paths/URLs, so any future folder
 * reshuffle stays painless.
 */
define( 'FLIPSET_VERSION', '1.1.0' );
define( 'FLIPSET_FILE', __FILE__ );
define( 'FLIPSET_DIR', plugin_dir_path( __FILE__ ) );
define( 'FLIPSET_URL', plugin_dir_url( __FILE__ ) );
define( 'FLIPSET_BASENAME', plugin_basename( __FILE__ ) );

/*
 * ---------------------------------------------------------------------
 * Dependencies
 * ---------------------------------------------------------------------
 */
require_once FLIPSET_DIR . 'includes/trait-flipset-att-flag.php';
require_once FLIPSET_DIR . 'includes/class-flipset-loader.php';
require_once FLIPSET_DIR . 'includes/class-flipset-viewer.php';
require_once FLIPSET_DIR . 'includes/class-flipset-thumbnails.php';
require_once FLIPSET_DIR . 'includes/class-flipset-seo.php';
require_once FLIPSET_DIR . 'includes/class-flipset-a11y.php';
require_once FLIPSET_DIR . 'includes/class-flipset-compat.php';
require_once FLIPSET_DIR . 'includes/class-flipset-builders.php';
require_once FLIPSET_DIR . 'includes/class-flipset-library.php';
require_once FLIPSET_DIR . 'includes/class-flipset-block.php';
require_once FLIPSET_DIR . 'includes/class-flipset-embeds.php';
require_once FLIPSET_DIR . 'includes/class-flipset-autoembed.php';
require_once FLIPSET_DIR . 'includes/class-flipset-admin.php';
require_once FLIPSET_DIR . 'includes/class-flipset.php';

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once FLIPSET_DIR . 'includes/class-flipset-cli.php';
}

/**
 * Activation hook: create default options, flush rewrite rules if we ever
 * add custom endpoints, etc.
 */
function flipset_activate() {
	if ( false === get_option( 'flipset_settings' ) ) {
		add_option(
			'flipset_settings',
			array(
				'default_width'          => '100%',
				'default_height'         => '600px',
				'autoembed_url'          => true,
				'embed_attachment_pages' => true,
			)
		);
	}
	set_transient( 'flipset_activation_redirect', 1, 60 );
}
register_activation_hook( FLIPSET_FILE, 'flipset_activate' );

/**
 * One-time redirect to the dashboard after activation so the welcome
 * checklist is the first thing a new site owner sees.
 */
function flipset_maybe_activation_redirect() {
	if ( ! get_transient( 'flipset_activation_redirect' ) ) {
		return;
	}
	delete_transient( 'flipset_activation_redirect' );

	if ( wp_doing_ajax() || wp_doing_cron() || is_network_admin() || isset( $_GET['activate-multi'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- core bulk-activation flag; value is not read.
		return;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	wp_safe_redirect( admin_url( 'admin.php?page=flipset-settings' ) );
	exit;
}
add_action( 'admin_init', 'flipset_maybe_activation_redirect' );

/**
 * Deactivation hook: intentionally left free of destructive cleanup.
 * Data removal belongs in uninstall.php, gated behind an explicit user
 * confirmation, never in deactivation.
 */
function flipset_deactivate() {
	// Nothing to do yet.
}
register_deactivation_hook( FLIPSET_FILE, 'flipset_deactivate' );

/**
 * Boot the plugin once all plugins are loaded, so any add-on extension (if active)
 * has had a chance to register itself before we check for it.
 */
function flipset_run() {
	Flipset::instance()->run();
}
add_action( 'plugins_loaded', 'flipset_run', 20 );
