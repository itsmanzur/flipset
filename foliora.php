<?php
/**
 * Plugin Name:       Foliora – PDF Viewer, PDF Embed, 3D Flipbook & Document Library
 * Plugin URI:        https://thereadscope.com/foliora
 * Description:       Embed PDFs with a fast PDF viewer, 3D flipbook and document library. Searchable PDF text, accessible, no CDN. Works with Gutenberg, Elementor, Divi.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            itsmanzur
 * Author URI:        https://thereadscope.com
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       foliora
 * Domain Path:       /languages
 *
 * @package Foliora
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
define( 'FOLIORA_VERSION', '1.0.0' );
define( 'FOLIORA_FILE', __FILE__ );
define( 'FOLIORA_DIR', plugin_dir_path( __FILE__ ) );
define( 'FOLIORA_URL', plugin_dir_url( __FILE__ ) );
define( 'FOLIORA_BASENAME', plugin_basename( __FILE__ ) );

/*
 * ---------------------------------------------------------------------
 * Dependencies
 * ---------------------------------------------------------------------
 */
require_once FOLIORA_DIR . 'includes/trait-foliora-att-flag.php';
require_once FOLIORA_DIR . 'includes/class-foliora-loader.php';
require_once FOLIORA_DIR . 'includes/class-foliora-viewer.php';
require_once FOLIORA_DIR . 'includes/class-foliora-thumbnails.php';
require_once FOLIORA_DIR . 'includes/class-foliora-seo.php';
require_once FOLIORA_DIR . 'includes/class-foliora-a11y.php';
require_once FOLIORA_DIR . 'includes/class-foliora-compat.php';
require_once FOLIORA_DIR . 'includes/class-foliora-builders.php';
require_once FOLIORA_DIR . 'includes/class-foliora-library.php';
require_once FOLIORA_DIR . 'includes/class-foliora-block.php';
require_once FOLIORA_DIR . 'includes/class-foliora-embeds.php';
require_once FOLIORA_DIR . 'includes/class-foliora-autoembed.php';
require_once FOLIORA_DIR . 'includes/class-foliora-admin.php';
require_once FOLIORA_DIR . 'includes/class-foliora.php';

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once FOLIORA_DIR . 'includes/class-foliora-cli.php';
}

/**
 * Activation hook: create default options, flush rewrite rules if we ever
 * add custom endpoints, etc.
 */
function foliora_activate() {
	if ( false === get_option( 'foliora_settings' ) ) {
		add_option(
			'foliora_settings',
			array(
				'default_width'          => '100%',
				'default_height'         => '600px',
				'autoembed_url'          => true,
				'embed_attachment_pages' => true,
			)
		);
	}
	set_transient( 'foliora_activation_redirect', 1, 60 );
}
register_activation_hook( FOLIORA_FILE, 'foliora_activate' );

/**
 * One-time redirect to the dashboard after activation so the welcome
 * checklist is the first thing a new site owner sees.
 */
function foliora_maybe_activation_redirect() {
	if ( ! get_transient( 'foliora_activation_redirect' ) ) {
		return;
	}
	delete_transient( 'foliora_activation_redirect' );

	if ( wp_doing_ajax() || wp_doing_cron() || is_network_admin() || isset( $_GET['activate-multi'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- core bulk-activation flag; value is not read.
		return;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	wp_safe_redirect( admin_url( 'admin.php?page=foliora-settings' ) );
	exit;
}
add_action( 'admin_init', 'foliora_maybe_activation_redirect' );

/**
 * Deactivation hook: intentionally left free of destructive cleanup.
 * Data removal belongs in uninstall.php, gated behind an explicit user
 * confirmation, never in deactivation.
 */
function foliora_deactivate() {
	// Nothing to do yet.
}
register_deactivation_hook( FOLIORA_FILE, 'foliora_deactivate' );

/**
 * Boot the plugin once all plugins are loaded, so any add-on extension (if active)
 * has had a chance to register itself before we check for it.
 */
function foliora_run() {
	Foliora::instance()->run();
}
add_action( 'plugins_loaded', 'foliora_run', 20 );
