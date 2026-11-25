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

	/**
	 * @dataProvider allowed_file_urls
	 *
	 * @param string $input    Raw address.
	 * @param string $expected Clean address.
	 */
	public function test_sanitize_file_url_keeps_http_https_and_relative( $input, $expected ) {
		$this->assertSame( $expected, Flipset_Viewer::sanitize_file_url( $input ) );
	}

	/**
	 * @return array
	 */
	public function allowed_file_urls() {
		return array(
			'https'    => array( 'https://example.com/a.pdf', 'https://example.com/a.pdf' ),
			'http'     => array( 'http://example.com/a.pdf', 'http://example.com/a.pdf' ),
			'relative' => array( '/wp-content/uploads/a.pdf', '/wp-content/uploads/a.pdf' ),
			'trimmed'  => array( "  https://example.com/a.pdf\n", 'https://example.com/a.pdf' ),
		);
	}

	/**
	 * @dataProvider refused_file_urls
	 *
	 * @param mixed $input Raw address.
	 */
	public function test_sanitize_file_url_refuses_other_schemes( $input ) {
		$this->assertSame( '', Flipset_Viewer::sanitize_file_url( $input ) );
	}

	/**
	 * @return array
	 */
	public function refused_file_urls() {
		return array(
			'javascript' => array( 'javascript:alert(1)' ),
			'mixed case' => array( 'JaVaScRiPt:alert(1)' ),
			'data'       => array( 'data:application/pdf;base64,AAAA' ),
			'file'       => array( 'file:///etc/passwd' ),
			'ftp'        => array( 'ftp://example.com/a.pdf' ),
			'blob'       => array( 'blob:https://example.com/1234' ),
			'array'      => array( array( 'https://example.com/a.pdf' ) ),
			'empty'      => array( '' ),
		);
	}

	public function test_render_refuses_a_javascript_file_address() {
		$html = Flipset::instance()->viewer->render_shortcode( array( 'file' => 'javascript:alert(1)' ) );
		$this->assertStringNotContainsString( 'javascript:', $html );
		$this->assertStringNotContainsString( 'data-file=', $html );
	}

	public function test_render_refuses_a_file_address_set_by_a_filter() {
		add_filter(
			'flipset/viewer_atts',
			static function ( $atts ) {
				$atts['file'] = 'data:text/html;base64,PHNjcmlwdD4=';
				return $atts;
			}
		);
		$html = Flipset::instance()->viewer->render_shortcode( array() );
		$this->assertStringNotContainsString( 'data:text/html', $html );
		remove_all_filters( 'flipset/viewer_atts' );
	}

	public function test_every_getdocument_call_turns_eval_off() {
		$files = array( 'flipset-viewer.js', 'flipset-thumbnail-gen.js', 'flipset-block.js' );
		foreach ( $files as $file ) {
			$code = file_get_contents( FLIPSET_DIR . 'assets/js/' . $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading a bundled script in a test.
			$this->assertNotFalse( $code, $file );
			$this->assertSame(
				preg_match_all( '/\.getDocument\(/', $code ),
				substr_count( $code, 'isEvalSupported: false' ),
				$file . ': every getDocument() call must set isEvalSupported: false (PDF.js CVE-2024-4367).'
			);
		}
	}
	/**
	 * Forget which viewer assets were enqueued, so a test sees only its own render.
	 */
	private function reset_viewer_assets() {
		$GLOBALS['wp_scripts'] = null;
		$GLOBALS['wp_styles']  = null;
		$property              = new ReflectionProperty( 'Flipset_Viewer', 'assets_enqueued' );
		$property->setAccessible( true );
		$property->setValue( Flipset::instance()->viewer, false );
	}

	public function test_poster_is_off_by_default() {
		delete_option( 'flipset_settings' );
		$this->reset_viewer_assets();
		$html = Flipset::instance()->viewer->render_shortcode( array( 'file' => 'https://example.com/a.pdf' ) );

		$this->assertStringNotContainsString( 'flipset-poster', $html );
		$this->assertStringNotContainsString( 'data-flipset-defer', $html );
		$this->assertTrue( wp_script_is( 'flipset-viewer', 'enqueued' ) );
		$this->assertTrue( wp_script_is( 'flipset-pdfjs', 'enqueued' ) );
	}

	public function test_poster_prints_cover_link_and_defers_viewer() {
		delete_option( 'flipset_settings' );
		$this->reset_viewer_assets();
		$url  = 'https://example.com/docs/catalogue.pdf';
		$html = Flipset::instance()->viewer->render_shortcode(
			array(
				'file'   => $url,
				'title'  => 'Spring Catalogue',
				'poster' => 'true',
			)
		);

		$this->assertStringContainsString( 'class="flipset-poster"', $html );
		$this->assertStringContainsString( 'has-poster', $html );
		$this->assertStringContainsString( 'data-flipset-defer="1"', $html );
		$this->assertStringContainsString( 'data-flipset-poster="1"', $html );
		$this->assertMatchesRegularExpression( '#<a class="flipset-poster-open" href="' . preg_quote( esc_url( $url ), '#' ) . '"#', $html );
		$this->assertStringContainsString( 'aria-label="Open document: Spring Catalogue"', $html );
		$this->assertStringContainsString( 'flipset-poster-title">Spring Catalogue<', $html );
		$this->assertStringContainsString( 'flipset-poster-icon', $html );

		// Only the small poster script: PDF.js and the viewer load on click.
		$this->assertTrue( wp_script_is( 'flipset-poster', 'enqueued' ) );
		$this->assertFalse( wp_script_is( 'flipset-pdfjs', 'enqueued' ) );
		$this->assertFalse( wp_script_is( 'flipset-viewer', 'enqueued' ) );
		$this->assertTrue( wp_style_is( 'flipset-viewer', 'enqueued' ) );
	}

	public function test_poster_title_falls_back_to_file_name() {
		$html = Flipset::instance()->viewer->render_shortcode(
			array(
				'file'   => 'https://example.com/files/user-guide.pdf?x=1',
				'poster' => 'true',
			)
		);
		$this->assertStringContainsString( 'flipset-poster-title">user-guide.pdf<', $html );
	}

	public function test_poster_uses_media_library_title() {
		$id   = $this->create_pdf_attachment( 'Library Titled PDF' );
		$html = Flipset::instance()->viewer->render_shortcode(
			array(
				'file'   => wp_get_attachment_url( $id ),
				'poster' => 'true',
			)
		);
		$this->assertStringContainsString( 'flipset-poster-title">Library Titled PDF<', $html );
	}

	public function test_poster_site_default_and_per_embed_override() {
		update_option( 'flipset_settings', array( 'poster_mode' => true ) );

		$html = Flipset::instance()->viewer->render_shortcode( array( 'file' => 'https://example.com/a.pdf' ) );
		$this->assertStringContainsString( 'flipset-poster', $html );

		$html = Flipset::instance()->viewer->render_shortcode(
			array(
				'file'   => 'https://example.com/a.pdf',
				'poster' => 'false',
			)
		);
		$this->assertStringNotContainsString( 'flipset-poster', $html );

		delete_option( 'flipset_settings' );
	}

	public function test_library_viewers_never_use_poster() {
		update_option( 'flipset_settings', array( 'poster_mode' => true ) );
		$this->create_pdf_attachment( 'Poster Off In Library' );

		$html = Flipset::instance()->library->render_shortcode( array( 'search' => 'false' ) );
		$this->assertStringContainsString( 'Poster Off In Library', $html );
		$this->assertStringNotContainsString( 'flipset-poster', $html );

		delete_option( 'flipset_settings' );
	}

	public function test_poster_image_filter_can_force_icon() {
		add_filter( 'flipset/poster_image_id', '__return_zero' );
		$html = Flipset::instance()->viewer->render_shortcode(
			array(
				'file'   => 'https://example.com/a.pdf',
				'poster' => 'true',
			)
		);
		remove_filter( 'flipset/poster_image_id', '__return_zero' );
		$this->assertStringContainsString( 'flipset-poster-icon', $html );
		$this->assertStringNotContainsString( 'flipset-poster-image', $html );
	}
	public function test_saved_embed_keeps_poster_choice() {
		delete_option( 'flipset_settings' );
		$on  = Flipset_Embeds::save(
			array(
				'file'   => 'https://example.com/a.pdf',
				'poster' => 'true',
			)
		);
		$off = Flipset_Embeds::save(
			array(
				'file'   => 'https://example.com/a.pdf',
				'poster' => 'maybe',
			)
		);
		$this->assertNotWPError( $on );
		$this->assertNotWPError( $off );
		$this->assertSame( 'true', Flipset_Embeds::get( $on )['poster'] );
		$this->assertSame( '', Flipset_Embeds::get( $off )['poster'] );

		$html = Flipset::instance()->viewer->render_shortcode( array( 'id' => (string) $on ) );
		$this->assertStringContainsString( 'flipset-poster', $html );

		$html = Flipset::instance()->viewer->render_shortcode( array( 'id' => (string) $off ) );
		$this->assertStringNotContainsString( 'flipset-poster', $html );
	}

	/**
	 * Run a callback as if the page was served over HTTPS.
	 *
	 * @param callable $callback Callback.
	 * @return mixed Its return value.
	 */
	private function on_https( $callback ) {
		$had   = array_key_exists( 'HTTPS', $_SERVER );
		$value = $had ? $_SERVER['HTTPS'] : null;
		$_SERVER['HTTPS'] = 'on';
		try {
			return $callback();
		} finally {
			if ( $had ) {
				$_SERVER['HTTPS'] = $value;
			} else {
				unset( $_SERVER['HTTPS'] );
			}
		}
	}

	public function test_same_site_http_pdf_is_loaded_over_https_on_https_pages() {
		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		$http = 'http://' . $host . '/wp-content/uploads/a.pdf';

		$this->assertSame(
			'https://' . $host . '/wp-content/uploads/a.pdf',
			$this->on_https(
				function () use ( $http ) {
					return Flipset_Viewer::match_page_scheme( $http );
				}
			)
		);

		$html = $this->on_https(
			function () use ( $http ) {
				return Flipset::instance()->viewer->render_shortcode( array( 'file' => $http ) );
			}
		);
		$this->assertStringContainsString( 'data-file="https://' . $host . '/wp-content/uploads/a.pdf"', $html );
		$this->assertStringNotContainsString( 'http://' . $host . '/wp-content/uploads/a.pdf', $html );
	}

	public function test_page_scheme_leaves_other_urls_alone() {
		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		$keep = array(
			'http://other.example/a.pdf',
			'http://' . $host . ':8080/a.pdf',
			'https://' . $host . '/a.pdf',
			'/wp-content/uploads/a.pdf',
			'',
		);
		foreach ( $keep as $url ) {
			$this->assertSame(
				$url,
				$this->on_https(
					function () use ( $url ) {
						return Flipset_Viewer::match_page_scheme( $url );
					}
				),
				$url
			);
		}

		// On a plain http page nothing changes, not even this site's own address.
		unset( $_SERVER['HTTPS'] );
		$this->assertSame( 'http://' . $host . '/a.pdf', Flipset_Viewer::match_page_scheme( 'http://' . $host . '/a.pdf' ) );
	}
}
