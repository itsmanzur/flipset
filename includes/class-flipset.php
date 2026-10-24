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

class Flipset {

	/**
	 * @var Flipset|null
	 */
	private static $instance = null;

	/**
	 * @var Flipset_Viewer
	 */
	public $viewer;

	/**
	 * @var Flipset_Thumbnails
	 */
	public $thumbnails;

	/**
	 * @var Flipset_SEO
	 */
	public $seo;

	/**
	 * @var Flipset_A11y
	 */
	public $a11y;

	/**
	 * @var Flipset_Compat
	 */
	public $compat;

	/**
	 * @var Flipset_Builders
	 */
	public $builders;

	/**
	 * @var Flipset_Library
	 */
	public $library;

	/**
	 * @var Flipset_Embeds
	 */
	public $embeds;

	/**
	 * @var Flipset_Block
	 */
	public $block;

	/**
	 * @var Flipset_Autoembed
	 */
	public $autoembed;

	/**
	 * @var Flipset_Admin
	 */
	public $admin;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

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
