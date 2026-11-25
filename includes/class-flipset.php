<?php
/**
 * Flipset
 *
 * Thin singleton that boots the free plugin's pieces. Deliberately does
 * almost nothing itself — it exists so `flipset_run()` has one obvious
 * place to call, and so add-ons (or a future admin dashboard widget,
 * WP-CLI command, etc.) have a stable `Flipset::instance()` to hook into.
 *
 * @package Flipset
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin bootstrap: a singleton that owns one instance of each Flipset component.
 */
class Flipset {

	/**
	 * The singleton instance.
	 *
	 * @var Flipset|null
	 */
	private static $instance = null;

	/**
	 * Viewer renderer.
	 *
	 * @var Flipset_Viewer
	 */
	public $viewer;

	/**
	 * Thumbnail and cover storage.
	 *
	 * @var Flipset_Thumbnails
	 */
	public $thumbnails;

	/**
	 * PDF text indexing and search.
	 *
	 * @var Flipset_SEO
	 */
	public $seo;

	/**
	 * PDF accessibility flags.
	 *
	 * @var Flipset_A11y
	 */
	public $a11y;

	/**
	 * Translation and optimizer compatibility.
	 *
	 * @var Flipset_Compat
	 */
	public $compat;

	/**
	 * Page builder integrations.
	 *
	 * @var Flipset_Builders
	 */
	public $builders;

	/**
	 * Front-end PDF library.
	 *
	 * @var Flipset_Library
	 */
	public $library;

	/**
	 * Saved embeds.
	 *
	 * @var Flipset_Embeds
	 */
	public $embeds;

	/**
	 * Block registration.
	 *
	 * @var Flipset_Block
	 */
	public $block;

	/**
	 * PDF URL auto-embeds.
	 *
	 * @var Flipset_Autoembed
	 */
	public $autoembed;

	/**
	 * Admin screens.
	 *
	 * @var Flipset_Admin
	 */
	public $admin;

	/**
	 * The shared plugin instance, created on first use.
	 *
	 * @return Flipset
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * URL of a plugin script or stylesheet, minified when a build is present.
	 *
	 * `npm run build` writes flipset-viewer.min.js (and .min.css) with a source map next
	 * to each readable file. The release zip always has them; a git checkout has them
	 * only after a build. The readable file is used when no minified one exists, and
	 * whenever SCRIPT_DEBUG is on.
	 *
	 * @param string $relative Path from the plugin folder, e.g. 'assets/js/flipset-viewer.js'.
	 * @return string
	 */
	public static function asset_url( $relative ) {
		$relative = ltrim( (string) $relative, '/' );
		$debug    = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG;
		if ( ! $debug && preg_match( '/^(assets\/(?:js|css)\/[a-z0-9-]+)\.(js|css)$/', $relative, $m ) ) {
			$min = $m[1] . '.min.' . $m[2];
			if ( file_exists( FLIPSET_DIR . $min ) ) {
				return FLIPSET_URL . $min;
			}
		}
		return FLIPSET_URL . $relative;
	}

	/**
	 * Create the components; use Flipset::instance() instead.
	 */
	private function __construct() {
		$this->viewer     = new Flipset_Viewer();
		$this->thumbnails = new Flipset_Thumbnails();
		$this->seo        = new Flipset_SEO();
		$this->a11y       = new Flipset_A11y();
		$this->compat     = new Flipset_Compat();
		$this->builders   = new Flipset_Builders();
		$this->library    = new Flipset_Library();
		$this->embeds     = new Flipset_Embeds();
		$this->block      = new Flipset_Block();
		$this->autoembed  = new Flipset_Autoembed();
		$this->admin      = new Flipset_Admin();
	}

	/**
	 * Wire everything up. Runs on `plugins_loaded` (see flipset.php) so
	 * that by this point any add-on — if installed — has already fired
	 * `flipset/pro_loaded` and Flipset_Loader::is_pro_active() is
	 * reliable everywhere below.
	 */
	public function run() {
		$this->embeds->init();
		$this->viewer->init();
		$this->thumbnails->init();
		$this->seo->init();
		$this->a11y->init();
		$this->compat->init();
		$this->builders->init();
		$this->library->init();
		$this->block->init();
		$this->autoembed->init();

		if ( is_admin() ) {
			$this->admin->init();
		}

		/**
		 * Action: flipset/loaded
		 *
		 * Fires once the free plugin has fully booted. Add-ons can
		 * use this instead of their own plugins_loaded if they need
		 * Flipset's classes to already exist.
		 */
		do_action( 'flipset/loaded' );
	}
}
