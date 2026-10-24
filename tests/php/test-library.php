<?php
/**
 * Flipset_Library tests.
 *
 * @package Flipset
 */

class Test_Flipset_Library extends Flipset_TestCase {

	public function test_shortcode_is_registered() {
		$this->assertTrue( shortcode_exists( 'flipset_library' ) );
	}

	public function test_empty_library_shows_empty_state() {
		$html = Flipset::instance()->library->render_shortcode( array() );
		$this->assertStringContainsString( 'flipset-library', $html );
		$this->assertStringContainsString( 'No PDFs found', $html );
		$this->assertStringContainsString( 'flipset-library-search', $html );
	}

	public function test_search_can_be_disabled() {
		$html = Flipset::instance()->library->render_shortcode( array( 'search' => 'false' ) );
		$this->assertStringNotContainsString( 'flipset-library-search', $html );
	}

	public function test_library_lists_pdf_attachment() {
		$this->create_pdf_attachment( 'Quarterly Report' );
		$html = Flipset::instance()->library->render_shortcode(
			array(
				'columns'  => '2',
				'search'   => 'false',
				'per_page' => '12',
			)
		);
		$this->assertStringContainsString( 'flipset-library-item', $html );
		$this->assertStringContainsString( 'Quarterly Report', $html );
		$this->assertStringContainsString( '--flipset-cols: 2', $html );
		$this->assertStringContainsString( 'flipset-viewer', $html );
	}

	public function test_invalid_columns_fall_back_to_default() {
		$this->create_pdf_attachment( 'Fallback PDF' );
		$html = Flipset::instance()->library->render_shortcode(
			array(
				'columns' => '99',
				'search'  => 'false',
			)
		);
		$this->assertStringContainsString( '--flipset-cols: 3', $html );
	}

	public function test_search_no_match_message() {
		$this->create_pdf_attachment( 'Something else' );
		$property = new ReflectionProperty( 'Flipset_Library', 'instance_n' );
		$property->setAccessible( true );
		$next = (int) $property->getValue() + 1;
		$_GET[ 'flipset_q_' . $next ] = 'zzzz-no-such-pdf';
		$html = Flipset::instance()->library->render_shortcode( array( 'search' => 'true' ) );
		unset( $_GET[ 'flipset_q_' . $next ] );
		$this->assertStringContainsString( 'No PDFs matched that search', $html );
	}
}
