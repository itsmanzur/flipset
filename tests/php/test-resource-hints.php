<?php
/**
 * PDF.js preload hints and poster cover priority.
 *
 * @package Flipset
 */

class Test_Flipset_Resource_Hints extends Flipset_TestCase {

	public function set_up() {
		parent::set_up();
		delete_option( 'flipset_settings' );
		$this->reset_poster_count();
	}

	public function tear_down() {
		delete_option( 'flipset_settings' );
		parent::tear_down();
	}

	private function viewer() {
		return Flipset::instance()->viewer;
	}

	private function reset_poster_count() {
		$property = new ReflectionProperty( 'Flipset_Viewer', 'posters_rendered' );
		$property->setAccessible( true );
		$property->setValue( $this->viewer(), 0 );
	}

	/**
	 * Visit a post with the given content and return what print_resource_hints() prints.
	 *
	 * @param string $content Post content.
	 * @return string
	 */
	private function hints_for( $content ) {
		$post_id = self::factory()->post->create( array( 'post_content' => $content ) );
		$this->go_to( get_permalink( $post_id ) );
		ob_start();
		$this->viewer()->print_resource_hints();
		return (string) ob_get_clean();
	}

	public function test_shortcode_viewer_is_eager() {
		$this->assertTrue( $this->viewer()->content_has_eager_viewer( '[flipset file="https://example.com/a.pdf"]' ) );
	}

	public function test_poster_and_lazy_viewers_are_not_eager() {
		$this->assertFalse( $this->viewer()->content_has_eager_viewer( '[flipset file="https://example.com/a.pdf" poster="true"]' ) );
		$this->assertFalse( $this->viewer()->content_has_eager_viewer( '[flipset file="https://example.com/a.pdf" loading="lazy"]' ) );
	}

	public function test_one_eager_viewer_among_posters_is_enough() {
		$content = '[flipset file="https://example.com/a.pdf" poster="true"] [flipset file="https://example.com/b.pdf"]';
		$this->assertTrue( $this->viewer()->content_has_eager_viewer( $content ) );
	}

	public function test_site_settings_are_respected() {
		update_option( 'flipset_settings', array( 'poster_mode' => true ) );
		$this->assertFalse( $this->viewer()->content_has_eager_viewer( '[flipset file="https://example.com/a.pdf"]' ) );
		$this->assertTrue( $this->viewer()->content_has_eager_viewer( '[flipset file="https://example.com/a.pdf" poster="false"]' ) );

		update_option( 'flipset_settings', array( 'lazy_loading' => true ) );
		$this->assertFalse( $this->viewer()->content_has_eager_viewer( '[flipset file="https://example.com/a.pdf"]' ) );
		$this->assertTrue( $this->viewer()->content_has_eager_viewer( '[flipset file="https://example.com/a.pdf" loading="eager"]' ) );
	}

	public function test_viewer_blocks_are_found_even_when_nested() {
		$this->assertTrue( $this->viewer()->content_has_eager_viewer( '<!-- wp:flipset/viewer {"file":"https://example.com/a.pdf"} /-->' ) );
		$this->assertFalse( $this->viewer()->content_has_eager_viewer( '<!-- wp:flipset/viewer {"file":"https://example.com/a.pdf","poster":"true"} /-->' ) );

		$nested = '<!-- wp:group --><div class="wp-block-group"><!-- wp:flipset/viewer {"file":"https://example.com/a.pdf"} /--></div><!-- /wp:group -->';
		$this->assertTrue( $this->viewer()->content_has_eager_viewer( $nested ) );
	}

	public function test_no_viewer_or_escaped_shortcode_is_not_eager() {
		$this->assertFalse( $this->viewer()->content_has_eager_viewer( '' ) );
		$this->assertFalse( $this->viewer()->content_has_eager_viewer( '<p>No PDF here.</p>' ) );
		$this->assertFalse( $this->viewer()->content_has_eager_viewer( '[[flipset file="https://example.com/a.pdf"]]' ) );
		$this->assertFalse( $this->viewer()->content_has_eager_viewer( '[flipset_library]' ) );
	}

	public function test_saved_embed_settings_are_used() {
		$poster = Flipset_Embeds::save(
			array(
				'file'   => 'https://example.com/a.pdf',
				'poster' => 'true',
			)
		);
		$this->assertNotWPError( $poster );
		$this->assertFalse( $this->viewer()->content_has_eager_viewer( '[flipset id="' . $poster . '"]' ) );
		// An explicit attribute still beats the saved one, as it does when rendering.
		$this->assertTrue( $this->viewer()->content_has_eager_viewer( '[flipset id="' . $poster . '" poster="false"]' ) );
	}

	public function test_hints_are_printed_for_an_eager_viewer() {
		$html = $this->hints_for( '[flipset file="https://example.com/a.pdf"]' );
		$this->assertStringContainsString( 'rel="preload"', $html );
		$this->assertStringContainsString( 'assets/vendor/pdfjs/pdf.min.js?ver=' . FLIPSET_VERSION, $html );
		$this->assertStringContainsString( 'as="script"', $html );
		$this->assertStringContainsString( 'rel="prefetch"', $html );
		$this->assertStringContainsString( 'pdf.worker.min.js', $html );
	}

	public function test_no_hints_for_posters_or_plain_pages() {
		$this->assertSame( '', $this->hints_for( '[flipset file="https://example.com/a.pdf" poster="true"]' ) );
		$this->assertSame( '', $this->hints_for( '<p>Just text.</p>' ) );
	}

	public function test_hints_filter_can_turn_them_off() {
		add_filter( 'flipset/resource_hints', '__return_false' );
		$html = $this->hints_for( '[flipset file="https://example.com/a.pdf"]' );
		remove_filter( 'flipset/resource_hints', '__return_false' );
		$this->assertSame( '', $html );
	}

	public function test_first_poster_cover_gets_high_priority() {
		$image_id = self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' );
		$use      = function () use ( $image_id ) {
			return $image_id;
		};
		add_filter( 'flipset/poster_image_id', $use );

		$atts   = array(
			'file'   => 'https://example.com/a.pdf',
			'poster' => 'true',
		);
		$first  = $this->viewer()->render_shortcode( $atts );
		$second = $this->viewer()->render_shortcode( $atts );
		remove_filter( 'flipset/poster_image_id', $use );

		$this->assertStringContainsString( 'flipset-poster-image', $first );
		$this->assertStringContainsString( 'fetchpriority="high"', $first );
		$this->assertStringNotContainsString( 'loading="lazy"', $first );

		$this->assertStringContainsString( 'flipset-poster-image', $second );
		$this->assertStringContainsString( 'loading="lazy"', $second );
		$this->assertStringNotContainsString( 'fetchpriority="high"', $second );
	}
}
