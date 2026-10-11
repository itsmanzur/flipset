<?php
/**
 * Foliora_Loader tests.
 *
 * @package Foliora
 */

class Test_Foliora_Loader extends Foliora_TestCase {

	public function test_pro_is_inactive_by_default() {
		$this->assertFalse( Foliora_Loader::is_pro_active() );
	}

	public function test_is_pro_active_filter_can_force_true() {
		add_filter( 'foliora/is_pro_active', '__return_true' );
		$this->reset_loader_cache();
		$this->assertTrue( Foliora_Loader::is_pro_active() );
		remove_filter( 'foliora/is_pro_active', '__return_true' );
	}

	public function test_is_pro_active_result_is_cached() {
		add_filter( 'foliora/is_pro_active', '__return_true' );
		$this->reset_loader_cache();
		$this->assertTrue( Foliora_Loader::is_pro_active() );
		remove_filter( 'foliora/is_pro_active', '__return_true' );
		$this->assertTrue( Foliora_Loader::is_pro_active(), 'Cached result should survive removing the filter' );
	}

	public function test_feature_enabled_is_false_when_pro_inactive() {
		$this->assertFalse( Foliora_Loader::is_feature_enabled( 'epub_reader' ) );
		$this->assertFalse( Foliora_Loader::is_feature_enabled( 'bookmarks' ) );
	}

	public function test_feature_enabled_defaults_true_when_pro_forced() {
		add_filter( 'foliora/is_pro_active', '__return_true' );
		$this->reset_loader_cache();
		$this->assertTrue( Foliora_Loader::is_feature_enabled( 'epub_reader' ) );
		remove_filter( 'foliora/is_pro_active', '__return_true' );
	}

	public function test_feature_enabled_respects_per_key_filter() {
		add_filter( 'foliora/is_pro_active', '__return_true' );
		$this->reset_loader_cache();
		add_filter(
			'foliora/feature_enabled',
			static function ( $enabled, $key ) {
				return 'bookmarks' === $key;
			},
			10,
			2
		);
		$this->assertTrue( Foliora_Loader::is_feature_enabled( 'bookmarks' ) );
		$this->assertFalse( Foliora_Loader::is_feature_enabled( 'epub_reader' ) );
		remove_all_filters( 'foliora/feature_enabled' );
		remove_filter( 'foliora/is_pro_active', '__return_true' );
	}
}
