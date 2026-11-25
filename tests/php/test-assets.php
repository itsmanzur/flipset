<?php
/**
 * Flipset loads its scripts and styles only where a viewer or library is shown.
 *
 * @package Flipset
 */

class Test_Flipset_Assets extends Flipset_TestCase {

	public function set_up() {
		parent::set_up();
		$this->forget_enqueued();
	}

	public function tear_down() {
		$this->forget_enqueued();
		parent::tear_down();
	}

	/**
	 * Dequeue every Flipset handle and reset the "already enqueued" flags, so each test
	 * sees only what its own page asked for.
	 */
	private function forget_enqueued() {
		foreach ( $this->flipset_handles() as $handle ) {
			wp_dequeue_script( $handle );
			wp_dequeue_style( $handle );
		}
		foreach ( array( 'viewer', 'library' ) as $component ) {
			$object   = Flipset::instance()->$component;
			$property = new ReflectionProperty( get_class( $object ), 'assets_enqueued' );
			$property->setAccessible( true );
			$property->setValue( $object, false );
		}
	}

	/**
	 * Flipset handles currently in the script and style queues.
	 *
	 * @return string[]
	 */
	private function flipset_handles() {
		$queued = array_merge( wp_scripts()->queue, wp_styles()->queue );
		return array_values(
			array_unique(
				array_filter(
					$queued,
					function ( $handle ) {
						return 0 === strpos( (string) $handle, 'flipset' );
					}
				)
			)
		);
	}

	/**
	 * Visit a post and run what a theme runs for it: the enqueue hooks and the content filter.
	 *
	 * @param string $content Post content.
	 * @return string Rendered content.
	 */
	private function visit_post( $content ) {
		$post_id = self::factory()->post->create( array( 'post_content' => $content ) );
		$this->go_to( get_permalink( $post_id ) );
		do_action( 'wp_enqueue_scripts' );
		do_action( 'enqueue_block_assets' );
		return apply_filters( 'the_content', get_post_field( 'post_content', $post_id ) );
	}

	public function test_page_without_viewer_loads_no_flipset_assets() {
		$this->visit_post( '<p>Just text, no PDF here.</p>' );
		$this->assertSame( array(), $this->flipset_handles() );
	}

	public function test_library_block_style_is_not_loaded_on_every_page() {
		$block = WP_Block_Type_Registry::get_instance()->get_registered( 'flipset/library' );
		$this->assertNotNull( $block );
		$this->assertSame( array(), (array) $block->style_handles, 'A front-end block style would load on every page of a classic theme.' );
	}

	public function test_page_with_shortcode_loads_viewer_assets() {
		$this->visit_post( '[flipset file="https://example.com/a.pdf"]' );
		$handles = $this->flipset_handles();
		$this->assertContains( 'flipset-viewer', $handles );
		$this->assertContains( 'flipset-pdfjs', $handles );
		$this->assertNotContains( 'flipset-library', $handles );
		$this->assertNotContains( 'flipset-poster', $handles );
	}

	public function test_page_with_viewer_block_loads_viewer_assets() {
		$this->visit_post( '<!-- wp:flipset/viewer {"file":"https://example.com/a.pdf"} /-->' );
		$this->assertContains( 'flipset-viewer', $this->flipset_handles() );
	}

	public function test_page_with_poster_loads_only_poster_script() {
		$this->visit_post( '[flipset file="https://example.com/a.pdf" poster="true"]' );
		$handles = $this->flipset_handles();
		$this->assertContains( 'flipset-poster', $handles );
		$this->assertNotContains( 'flipset-pdfjs', $handles );
	}

	public function test_page_with_library_loads_library_and_viewer_assets() {
		$this->create_pdf_attachment( 'Asset Check PDF' );
		$this->visit_post( '[flipset_library]' );
		$handles = $this->flipset_handles();
		$this->assertContains( 'flipset-library', $handles );
		$this->assertContains( 'flipset-viewer', $handles );
	}

	public function test_empty_library_loads_only_its_stylesheet() {
		$this->visit_post( '[flipset_library]' );
		$this->assertSame( array( 'flipset-library' ), $this->flipset_handles() );
	}

	public function test_plain_pdf_link_in_text_loads_nothing() {
		// Only a PDF link on a line of its own becomes a viewer.
		$this->visit_post( '<p>Read <a href="https://example.com/a.pdf">the guide</a> later.</p>' );
		$this->assertSame( array(), $this->flipset_handles() );
	}

	public function test_elementor_preview_loads_every_widget_file() {
		Flipset::instance()->builders->enqueue_elementor_preview_assets();
		$handles = $this->flipset_handles();
		foreach ( array( 'flipset-viewer', 'flipset-pdfjs', 'flipset-poster', 'flipset-library', 'flipset-elementor-preview' ) as $handle ) {
			$this->assertContains( $handle, $handles );
		}
	}

	public function test_elementor_preview_files_are_not_loaded_on_the_live_site() {
		$this->visit_post( '<p>Just text.</p>' );
		$this->assertNotContains( 'flipset-elementor-preview', $this->flipset_handles() );
	}

	public function test_admin_assets_load_only_on_flipset_screens() {
		$admin = Flipset::instance()->admin;

		$admin->enqueue_admin_assets( 'index.php' );
		$this->assertSame( array(), $this->flipset_handles() );

		$admin->enqueue_admin_assets( 'edit.php' );
		$this->assertSame( array(), $this->flipset_handles() );

		$admin->enqueue_admin_assets( Flipset_Admin::PAGE_SLUG . '_page_' . Flipset_Admin::SETTINGS_SLUG );
		$this->assertContains( 'flipset-admin', $this->flipset_handles() );
	}
}
