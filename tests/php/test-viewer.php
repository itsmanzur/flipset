<?php
/**
 * Flipset_Viewer tests.
 *
 * @package Flipset
 */

class Test_Flipset_Viewer extends Flipset_TestCase {

	public function test_empty_file_returns_admin_notice_for_editors() {
		$html = Flipset::instance()->viewer->render_shortcode( array() );
		$this->assertStringContainsString( 'flipset-admin-notice', $html );
		$this->assertStringContainsString( 'no file URL', $html );
	}

	public function test_empty_file_is_blank_for_anonymous_visitors() {
		wp_set_current_user( 0 );
		$html = Flipset::instance()->viewer->render_shortcode( array() );
		$this->assertSame( '', $html );
	}

	public function test_render_includes_viewer_chrome_and_file_url() {
		$url  = 'https://example.com/docs/manual.pdf';
		$html = Flipset::instance()->viewer->render_shortcode(
			array(
				'file'  => $url,
				'title' => 'Annual report',
				'page'  => '3',
			)
		);
		$this->assertStringContainsString( 'class="flipset-viewer"', $html );
		$this->assertStringContainsString( 'data-file="' . esc_url( $url ) . '"', $html );
		$this->assertStringContainsString( 'data-page="3"', $html );
		$this->assertStringContainsString( 'flipset-caption', $html );
		$this->assertStringContainsString( 'Annual report', $html );
		$this->assertStringContainsString( 'flipset-rotate', $html );
		$this->assertStringContainsString( 'flipset-download', $html );
		$this->assertStringContainsString( 'data-view="page"', $html );
		$this->assertStringContainsString( 'data-resume="1"', $html );
		$this->assertStringContainsString( 'flipset-find-status', $html );
		$this->assertStringContainsString( 'flipset-view', $html );
		$this->assertStringContainsString( 'flipset-pan', $html );
		$this->assertStringContainsString( 'flipset-more', $html );
		$this->assertStringContainsString( 'flipset-present', $html );
	}

	public function test_branding_credit_is_off_by_default() {
		delete_option( 'flipset_settings' );
		$html = Flipset::instance()->viewer->render_shortcode( array( 'file' => 'https://example.com/a.pdf' ) );
		$this->assertStringNotContainsString( 'flipset-branding', $html );
		$this->assertStringNotContainsString( 'Powered by Flipset', $html );
	}

	public function test_branding_credit_shows_only_when_enabled_in_settings() {
		update_option( 'flipset_settings', array( 'show_branding' => true ) );
		$html = Flipset::instance()->viewer->render_shortcode( array( 'file' => 'https://example.com/a.pdf' ) );
		$this->assertStringContainsString( 'flipset-branding', $html );

		update_option( 'flipset_settings', array( 'show_branding' => false ) );
		$html = Flipset::instance()->viewer->render_shortcode( array( 'file' => 'https://example.com/a.pdf' ) );
		$this->assertStringNotContainsString( 'flipset-branding', $html );
		delete_option( 'flipset_settings' );
	}

	public function test_download_and_print_can_be_hidden() {
		$html = Flipset::instance()->viewer->render_shortcode(
			array(
				'file'     => 'https://example.com/a.pdf',
				'download' => 'false',
				'print'    => 'false',
			)
		);
		$this->assertStringNotContainsString( 'flipset-download', $html );
		$this->assertStringNotContainsString( 'flipset-print', $html );
	}

	public function test_view_and_resume_attributes() {
		$html = Flipset::instance()->viewer->render_shortcode(
			array(
				'file'   => 'https://example.com/a.pdf',
				'view'   => 'scroll',
				'resume' => 'false',
			)
		);
		$this->assertStringContainsString( 'data-view="scroll"', $html );
		$this->assertStringContainsString( 'data-resume="0"', $html );

		$invalid = Flipset::instance()->viewer->render_shortcode(
			array(
				'file' => 'https://example.com/a.pdf',
				'view' => 'flip3d',
			)
		);
		$this->assertStringContainsString( 'data-view="page"', $invalid );
	}

	public function test_mode_attribute_shows_no_upsell_and_add_ons_can_take_over() {
		$atts = array(
			'file' => 'https://example.com/book.epub',
			'mode' => 'epub',
		);
		$html = Flipset::instance()->viewer->render_shortcode( $atts );
		$this->assertStringNotContainsString( 'flipset-locked', $html );
		$this->assertStringNotContainsString( 'Upgrade', $html );

		add_filter(
			'flipset/pre_render_viewer',
			function ( $output, $filter_atts ) {
				return 'epub' === $filter_atts['mode'] ? '<div class="my-epub"></div>' : $output;
			},
			10,
			2
		);
		$html = Flipset::instance()->viewer->render_shortcode( $atts );
		remove_all_filters( 'flipset/pre_render_viewer' );
		$this->assertStringContainsString( 'my-epub', $html );
	}

	public function test_shortcode_is_registered() {
		$this->assertTrue( shortcode_exists( 'flipset' ) );
	}

	public function test_render_enqueues_pdfjs_and_viewer() {
		Flipset::instance()->viewer->render_shortcode(
			array( 'file' => 'https://example.com/a.pdf' )
		);
		$this->assertTrue( wp_script_is( 'flipset-pdfjs', 'enqueued' ) );
		$this->assertTrue( wp_script_is( 'flipset-viewer', 'enqueued' ) );
		$this->assertTrue( wp_style_is( 'flipset-viewer', 'enqueued' ) );
	}

	public function test_viewer_atts_filter_can_inject_file() {
		add_filter(
			'flipset/viewer_atts',
			static function ( $atts ) {
				$atts['file'] = 'https://example.com/from-filter.pdf';
				return $atts;
			}
		);
		$html = Flipset::instance()->viewer->render_shortcode( array() );
		$this->assertStringContainsString( 'from-filter.pdf', $html );
		remove_all_filters( 'flipset/viewer_atts' );
	}
}
