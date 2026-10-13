<?php
/**
 * Foliora_Loader
 *
 * This is the single source of truth for "is an add-on active?" anywhere in the
 * free plugin. Nothing else should check for an add-on plugin directly —
 * always go through Foliora_Loader::is_pro_active() so the detection
 * logic only lives in one place.
 *
 * Contract with add-on extensions (e.g. separate add-on plugin):
 *   1. An add-on fires `do_action( 'foliora/pro_loaded' )` on its own
 *      `plugins_loaded` (priority 5, i.e. before Foliora free boots at 20).
 *   2. An add-on also defines the constant FOLIORA_PRO_VERSION.
 *   3. Foliora free never ships any add-on code and shows no locked or
 *      upsell UI. Anything an add-on adds lives entirely inside that
 *      plugin and hooks into the filters/actions Foliora free exposes
 *      (see class-foliora-viewer.php and docs/HOOKS.md).
 *
 * @package Foliora
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Foliora_Loader {

	/**
	 * Cached result so we don't re-run the check on every call.
	 *
	 * @var bool|null
	 */
	private static $is_pro_active = null;

	/**
	 * Whether an add-on is installed, active, and has confirmed itself
	 * loaded via the `foliora/pro_loaded` action.
	 *
	 * @return bool
	 */
	public static function is_pro_active() {
		if ( null !== self::$is_pro_active ) {
			return self::$is_pro_active;
		}

		$pro_confirmed = defined( 'FOLIORA_PRO_VERSION' ) && did_action( 'foliora/pro_loaded' );

		/**
		 * Filter: foliora/is_pro_active
		 *
		 * Lets an add-on (or a site owner, for testing) override the
		 * detection result explicitly.
		 *
		 * @param bool $pro_confirmed
		 */
		self::$is_pro_active = (bool) apply_filters( 'foliora/is_pro_active', $pro_confirmed );

		return self::$is_pro_active;
	}

	/**
	 * Reset the cached add-on detection result.
	 *
	 * Intended for unit-test isolation and for any scenario where an add-on is
	 * activated or deactivated at run-time after the initial detection has
	 * already been cached (e.g. plugin-in-plugin activation tests).
	 *
	 * Production code should not need to call this directly; the cache is
	 * intentionally persistent for the lifetime of a single request.
	 *
	 * @return void
	 */
	public static function reset() {
		self::$is_pro_active = null;
	}

	/**
	 * Whether a single named add-on feature is enabled on this site.
	 * An add-on hooks `foliora/feature_enabled` and returns true for the
	 * features the site's license includes. Always false while no add-on is
	 * active.
	 *
	 * @param string $feature_key Feature key chosen by the add-on.
	 * @return bool
	 */
	public static function is_feature_enabled( $feature_key ) {
		if ( ! self::is_pro_active() ) {
			return false;
		}

		return (bool) apply_filters( 'foliora/feature_enabled', true, $feature_key );
	}
}
