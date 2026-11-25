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

	/**
	 * Open a post and run only the enqueue hook, so what is queued is what the head prints.
	 *
	 * @param string $content Post content.
	 * @param array  $meta    Post meta.
	 * @return string[] Flipset handles queued before the content renders.
	 */
	private function head_handles( $content, $meta = array() ) {
		$post_id = self::factory()->post->create( array( 'post_content' => $content ) );
		foreach ( $meta as $key => $value ) {
			update_post_meta( $post_id, $key, wp_slash( $value ) );
		}
		$this->go_to( get_permalink( $post_id ) );
		do_action( 'wp_enqueue_scripts' );
		return $this->flipset_handles();
	}

	public function test_viewer_stylesheet_loads_in_the_head() {
		$this->assertSame( array( 'flipset-viewer' ), $this->head_handles( '[flipset file="https://example.com/a.pdf"]' ) );
		$this->forget_enqueued();
		$this->assertSame( array( 'flipset-viewer' ), $this->head_handles( '[flipset file="https://example.com/a.pdf" poster="true"]' ) );
		$this->forget_enqueued();
		$this->assertSame( array( 'flipset-viewer' ), $this->head_handles( '<!-- wp:flipset/viewer {"file":"https://example.com/a.pdf"} /-->' ) );
	}

	public function test_library_stylesheet_loads_in_the_head() {
		$this->assertSame( array( 'flipset-library' ), $this->head_handles( '[flipset_library]' ) );
		$this->assertTrue( wp_style_is( 'flipset-viewer', 'registered' ), 'The library stylesheet depends on the viewer one.' );
	}

	public function test_no_stylesheet_in_the_head_without_a_viewer() {
		$this->assertSame( array(), $this->head_handles( '<p>No PDF. [flipset_unknown]</p>' ) );
	}

	public function test_elementor_widgets_load_stylesheets_in_the_head() {
		$data = wp_json_encode(
			array(
				array(
					'elType'     => 'widget',
					'widgetType' => 'flipset-viewer',
					'settings'   => array( 'file' => 'https://example.com/a.pdf' ),
				),
			)
		);
		$this->assertSame(
			array( 'flipset-viewer' ),
			$this->head_handles( '', array( '_elementor_edit_mode' => 'builder', '_elementor_data' => $data ) )
		);
		$this->forget_enqueued();

		$data = wp_json_encode( array( array( 'widgetType' => 'shortcode', 'settings' => array( 'shortcode' => '[flipset_library]' ) ) ) );
		$this->assertSame(
			array( 'flipset-library' ),
			$this->head_handles( '', array( '_elementor_edit_mode' => 'builder', '_elementor_data' => $data ) )
		);
	}

	public function test_elementor_data_is_ignored_when_elementor_does_not_build_the_page() {
		$data = wp_json_encode( array( array( 'widgetType' => 'flipset-viewer' ) ) );
		$this->assertSame( array(), $this->head_handles( '<p>Text.</p>', array( '_elementor_data' => $data ) ) );
	}

	public function test_head_styles_filter() {
		$off = function () {
			return array(
				'viewer'  => false,
				'library' => false,
			);
		};
		add_filter( 'flipset/head_styles', $off );
		$handles = $this->head_handles( '[flipset file="https://example.com/a.pdf"]' );
		remove_filter( 'flipset/head_styles', $off );
		$this->assertSame( array(), $handles );
	}

	public function test_viewer_config_turns_progressive_loading_on_by_default() {
		$this->assertTrue( Flipset_Viewer::progressive_loading() );
		Flipset::instance()->viewer->enqueue_assets();
		$this->assertStringContainsString( '"progressive":"1"', (string) wp_scripts()->get_data( 'flipset-viewer', 'data' ) );
	}

	public function test_progressive_loading_filter_can_turn_it_off() {
		add_filter( 'flipset/progressive_loading', '__return_false' );
		$this->assertFalse( Flipset_Viewer::progressive_loading() );
		remove_filter( 'flipset/progressive_loading', '__return_false' );
	}

	public function test_asset_url_uses_the_minified_file_when_built() {
		$min = FLIPSET_DIR . 'assets/js/flipset-unit-test-asset.min.js';
		$this->assertSame( FLIPSET_URL . 'assets/js/flipset-unit-test-asset.js', Flipset::asset_url( 'assets/js/flipset-unit-test-asset.js' ) );

		file_put_contents( $min, '/* test */' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		try {
			$url = Flipset::asset_url( 'assets/js/flipset-unit-test-asset.js' );
		} finally {
			wp_delete_file( $min );
		}
		if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) {
			$this->assertSame( FLIPSET_URL . 'assets/js/flipset-unit-test-asset.js', $url );
		} else {
			$this->assertSame( FLIPSET_URL . 'assets/js/flipset-unit-test-asset.min.js', $url );
		}
	}

	public function test_asset_url_leaves_other_paths_alone() {
		$this->assertSame( FLIPSET_URL . 'assets/vendor/pdfjs/pdf.min.js', Flipset::asset_url( 'assets/vendor/pdfjs/pdf.min.js' ) );
		$this->assertSame( FLIPSET_URL . 'assets/js/flipset-viewer.min.js', Flipset::asset_url( '/assets/js/flipset-viewer.min.js' ) );
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
