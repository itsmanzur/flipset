<?php
/**
 * Flipset_Loader tests.
 *
 * @package Flipset
 */

class Test_Flipset_Loader extends Flipset_TestCase {

	public function test_pro_is_inactive_by_default() {
		$this->assertFalse( Flipset_Loader::is_pro_active() );
	}

	public function test_is_pro_active_filter_can_force_true() {
		add_filter( 'flipset/is_pro_active', '__return_true' );
		$this->reset_loader_cache();
		$this->assertTrue( Flipset_Loader::is_pro_active() );
		remove_filter( 'flipset/is_pro_active', '__return_true' );
	}

	public function test_is_pro_active_result_is_cached() {
		add_filter( 'flipset/is_pro_active', '__return_true' );
		$this->reset_loader_cache();
		$this->assertTrue( Flipset_Loader::is_pro_active() );
		remove_filter( 'flipset/is_pro_active', '__return_true' );
		$this->assertTrue( Flipset_Loader::is_pro_active(), 'Cached result should survive removing the filter' );
	}

	public function test_feature_enabled_is_false_when_pro_inactive() {
		$this->assertFalse( Flipset_Loader::is_feature_enabled( 'epub_reader' ) );
		$this->assertFalse( Flipset_Loader::is_feature_enabled( 'bookmarks' ) );
	}

	public function test_feature_enabled_defaults_true_when_pro_forced() {
		add_filter( 'flipset/is_pro_active', '__return_true' );
		$this->reset_loader_cache();
		$this->assertTrue( Flipset_Loader::is_feature_enabled( 'epub_reader' ) );
		remove_filter( 'flipset/is_pro_active', '__return_true' );
	}

	public function test_feature_enabled_respects_per_key_filter() {
		add_filter( 'flipset/is_pro_active', '__return_true' );
		$this->reset_loader_cache();
		add_filter(
			'flipset/feature_enabled',
			static function ( $enabled, $key ) {
				return 'bookmarks' === $key;
			},
			10,
			2
		);
		$this->assertTrue( Flipset_Loader::is_feature_enabled( 'bookmarks' ) );
		$this->assertFalse( Flipset_Loader::is_feature_enabled( 'epub_reader' ) );
		remove_all_filters( 'flipset/feature_enabled' );
		remove_filter( 'flipset/is_pro_active', '__return_true' );
	}
}
