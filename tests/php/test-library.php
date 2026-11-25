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
		$this->assertStringContainsString( 'No documents found', $html );
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
		$this->assertStringContainsString( 'No documents matched that search', $html );
	}

	/**
	 * Call a private Flipset_Library method.
	 *
	 * @param string $method Method name.
	 * @param mixed  ...$args Arguments.
	 * @return mixed
	 */
	private function call_private( $method, ...$args ) {
		$reflection = new ReflectionMethod( 'Flipset_Library', $method );
		$reflection->setAccessible( true );
		return $reflection->invoke( Flipset::instance()->library, ...$args );
	}

	/**
	 * Instance number the next rendered library will get (names its query vars).
	 *
	 * @return int
	 */
	private function next_library_uid() {
		$property = new ReflectionProperty( 'Flipset_Library', 'instance_n' );
		$property->setAccessible( true );
		return (int) $property->getValue() + 1;
	}

	/**
	 * Three PDFs whose date, title ascending and title descending orders all differ.
	 * Newest first: Bravo, Alpha, Charlie.
	 */
	private function create_ordered_pdfs() {
		$dates = array(
			'Bravo'   => '-1 day',
			'Alpha'   => '-2 days',
			'Charlie' => '-3 days',
		);
		foreach ( $dates as $title => $offset ) {
			$id   = $this->create_pdf_attachment( $title );
			$time = strtotime( $offset );
			wp_update_post(
				array(
					'ID'            => $id,
					'post_date'     => gmdate( 'Y-m-d H:i:s', $time ),
					'post_date_gmt' => gmdate( 'Y-m-d H:i:s', $time ),
				)
			);
		}
	}

	/**
	 * Titles of the rendered cards, in order.
	 *
	 * @param string $html Library HTML.
	 * @return string[]
	 */
	private function card_order( $html ) {
		preg_match_all( '/flipset-library-title">([^<]+)</', $html, $matches );
		return $matches[1];
	}

	public function test_new_attributes_default_to_the_previous_markup() {
		$this->create_pdf_attachment( 'Plain' );
		$html = Flipset::instance()->library->render_shortcode( array() );
		$this->assertStringContainsString( 'flipset-library-search', $html );
		$this->assertStringNotContainsString( '<select', $html );
		$this->assertStringNotContainsString( 'flipset-library-sort', $html );
		$this->assertStringNotContainsString( 'flipset-library-layout', $html );
		$this->assertStringNotContainsString( 'flipset-library-status', $html );
		$this->assertStringNotContainsString( 'is-layout-list', $html );
	}

	public function test_invalid_new_attribute_values_fall_back_to_defaults() {
		$this->create_pdf_attachment( 'Plain' );
		$html = Flipset::instance()->library->render_shortcode(
			array(
				'sort_control'  => 'maybe',
				'layout'        => 'banana',
				'layout_toggle' => 'x',
			)
		);
		$this->assertStringNotContainsString( '<select', $html );
		$this->assertStringNotContainsString( 'flipset-library-layout', $html );
		$this->assertStringNotContainsString( 'is-layout-list', $html );
	}

	public function test_sort_control_renders_a_labelled_select_with_four_choices() {
		$this->create_pdf_attachment( 'Plain' );
		$html = Flipset::instance()->library->render_shortcode( array( 'sort_control' => 'true' ) );
		$this->assertMatchesRegularExpression( '/<label[^>]*for="flipset_sort_\d+"[^>]*>Sort documents<\/label>/', $html );
		$this->assertMatchesRegularExpression( '/<select id="flipset_sort_\d+" name="flipset_sort_\d+"/', $html );
		foreach ( array( 'Newest', 'Oldest', 'Title A–Z', 'Title Z–A' ) as $label ) {
			$this->assertStringContainsString( '>' . $label . '</option>', $html );
		}
		$this->assertStringNotContainsString( 'Default order', $html );
		$this->assertStringContainsString( 'role="status"', $html );
	}

	public function test_sort_control_accepts_a_real_boolean_from_the_block() {
		$this->create_pdf_attachment( 'Plain' );
		$html = Flipset::instance()->library->render_shortcode( array( 'sort_control' => true ) );
		$this->assertStringContainsString( '<select', $html );
	}

	public function test_sort_menu_works_without_the_search_box() {
		$this->create_pdf_attachment( 'Plain' );
		$html = Flipset::instance()->library->render_shortcode(
			array(
				'search'       => 'false',
				'sort_control' => 'true',
			)
		);
		$this->assertStringContainsString( '<select', $html );
		$this->assertStringContainsString( 'class="flipset-library-sort"', $html );
		$this->assertStringNotContainsString( 'flipset-library-search', $html );
	}

	public function test_orderby_without_a_menu_entry_gets_a_default_order_option() {
		$this->create_pdf_attachment( 'Plain' );
		$html = Flipset::instance()->library->render_shortcode(
			array(
				'orderby'      => 'modified',
				'sort_control' => 'true',
			)
		);
		$this->assertStringContainsString( '>Default order</option>', $html );
	}

	public function test_sort_query_var_orders_the_grid() {
		$this->create_ordered_pdfs();
		$expected = array(
			'newest'     => array( 'Bravo', 'Alpha', 'Charlie' ),
			'oldest'     => array( 'Charlie', 'Alpha', 'Bravo' ),
			'title-asc'  => array( 'Alpha', 'Bravo', 'Charlie' ),
			'title-desc' => array( 'Charlie', 'Bravo', 'Alpha' ),
		);
		foreach ( $expected as $sort => $order ) {
			$var          = 'flipset_sort_' . $this->next_library_uid();
			$_GET[ $var ] = $sort;
			$html         = Flipset::instance()->library->render_shortcode(
				array(
					'search'       => 'false',
					'sort_control' => 'true',
				)
			);
			unset( $_GET[ $var ] );
			$this->assertSame( $order, $this->card_order( $html ), $sort );
			$this->assertMatchesRegularExpression( '/<option value="' . preg_quote( $sort, '/' ) . '" selected="selected">/', $html, $sort );
		}
	}

	public function test_sort_query_var_is_ignored_when_the_control_is_off() {
		$this->create_ordered_pdfs();
		$var          = 'flipset_sort_' . $this->next_library_uid();
		$_GET[ $var ] = 'title-desc';
		$html         = Flipset::instance()->library->render_shortcode( array( 'search' => 'false' ) );
		unset( $_GET[ $var ] );
		$this->assertSame( array( 'Bravo', 'Alpha', 'Charlie' ), $this->card_order( $html ) );
	}

	public function test_unknown_sort_value_keeps_the_shortcode_order() {
		$this->create_ordered_pdfs();
		$var          = 'flipset_sort_' . $this->next_library_uid();
		$_GET[ $var ] = 'title-desc; DROP TABLE';
		$html         = Flipset::instance()->library->render_shortcode(
			array(
				'search'       => 'false',
				'sort_control' => 'true',
			)
		);
		unset( $_GET[ $var ] );
		$this->assertSame( array( 'Bravo', 'Alpha', 'Charlie' ), $this->card_order( $html ) );
	}

	public function test_resolve_sort_maps_choices_onto_orderby_and_order() {
		$this->assertSame( array( 'date', 'DESC', 'newest' ), $this->call_private( 'resolve_sort', 'date', 'DESC', '' ) );
		$this->assertSame( array( 'date', 'ASC', 'oldest' ), $this->call_private( 'resolve_sort', 'date', 'ASC', '' ) );
		$this->assertSame( array( 'title', 'ASC', 'title-asc' ), $this->call_private( 'resolve_sort', 'title', 'ASC', '' ) );
		$this->assertSame( array( 'title', 'DESC', 'title-desc' ), $this->call_private( 'resolve_sort', 'title', 'DESC', '' ) );
		// No menu entry for it: the shortcode's own order stays, nothing is selected.
		$this->assertSame( array( 'modified', 'DESC', '' ), $this->call_private( 'resolve_sort', 'modified', 'DESC', '' ) );
		// A requested key wins over the shortcode attributes.
		$this->assertSame( array( 'date', 'ASC', 'oldest' ), $this->call_private( 'resolve_sort', 'title', 'ASC', 'oldest' ) );
		// Unknown keys are ignored.
		$this->assertSame( array( 'title', 'ASC', 'title-asc' ), $this->call_private( 'resolve_sort', 'title', 'ASC', 'bogus' ) );
	}

	public function test_layout_attribute_is_whitelisted() {
		$this->assertSame( 'list', $this->call_private( 'sanitize_layout', 'list' ) );
		$this->assertSame( 'list', $this->call_private( 'sanitize_layout', ' LIST ' ) );
		$this->assertSame( 'grid', $this->call_private( 'sanitize_layout', 'grid' ) );
		$this->assertSame( 'grid', $this->call_private( 'sanitize_layout', 'banana' ) );
		$this->assertSame( 'grid', $this->call_private( 'sanitize_layout', '' ) );
		$this->assertSame( 'grid', $this->call_private( 'sanitize_layout', null ) );
		$this->assertSame( 'grid', $this->call_private( 'sanitize_layout', array( 'list' ) ) );
	}

	public function test_list_layout_and_switch_markup() {
		$this->create_pdf_attachment( 'Plain' );
		$html = Flipset::instance()->library->render_shortcode(
			array(
				'layout'        => 'list',
				'layout_toggle' => 'true',
			)
		);
		$this->assertStringContainsString( 'flipset-library is-layout-list', $html );
		$this->assertMatchesRegularExpression( '/data-layout="list" aria-pressed="true"/', $html );
		$this->assertMatchesRegularExpression( '/data-layout="grid" aria-pressed="false"/', $html );
		$this->assertStringContainsString( 'aria-label="Layout"', $html );

		$grid = Flipset::instance()->library->render_shortcode( array( 'layout_toggle' => 'true' ) );
		$this->assertStringNotContainsString( 'is-layout-list', $grid );
		$this->assertMatchesRegularExpression( '/data-layout="grid" aria-pressed="true"/', $grid );
	}

	public function test_page_count_label_is_singular_plural_or_missing() {
		$this->assertSame( '', $this->call_private( 'page_count_label', 0 ) );
		$this->assertSame( '', $this->call_private( 'page_count_label', '' ) );
		$this->assertSame( '1 page', $this->call_private( 'page_count_label', 1 ) );
		$this->assertSame( '12 pages', $this->call_private( 'page_count_label', '12' ) );
		$this->assertSame( '1,234 pages', $this->call_private( 'page_count_label', 1234 ) );
	}

	public function test_card_shows_the_page_count_only_when_it_is_stored() {
		$id = $this->create_pdf_attachment( 'Counted' );

		$html = Flipset::instance()->library->render_shortcode( array( 'search' => 'false' ) );
		$this->assertMatchesRegularExpression( '/flipset-library-meta">PDF · [\d.,]+ [A-Za-z]+</', $html );
		$this->assertDoesNotMatchRegularExpression( '/flipset-library-meta">[^<]*\bpages?\b/', $html );

		update_post_meta( $id, Flipset_SEO::META_PAGES, 12 );
		$html = Flipset::instance()->library->render_shortcode( array( 'search' => 'false' ) );
		$this->assertMatchesRegularExpression( '/flipset-library-meta">PDF · 12 pages · /', $html );

		update_post_meta( $id, Flipset_SEO::META_PAGES, 1 );
		$html = Flipset::instance()->library->render_shortcode( array( 'search' => 'false' ) );
		$this->assertMatchesRegularExpression( '/flipset-library-meta">PDF · 1 page · /', $html );

		update_post_meta( $id, Flipset_SEO::META_PAGES, 0 );
		$html = Flipset::instance()->library->render_shortcode( array( 'search' => 'false' ) );
		$this->assertDoesNotMatchRegularExpression( '/flipset-library-meta">[^<]*\bpages?\b/', $html );
	}

	public function test_allowed_html_keeps_select_and_option() {
		$html = wp_kses( '<select name="x"><option value="a" selected="selected">A</option></select>', Flipset_Viewer::allowed_html() );
		$this->assertStringContainsString( '<select name="x">', $html );
		$this->assertStringContainsString( '<option value="a" selected="selected">A</option>', $html );
	}

	public function test_hidden_pdf_is_left_out_of_full_library() {
		$this->create_pdf_attachment( 'Public Brochure' );
		$secret = $this->create_pdf_attachment( 'Private Payroll' );

		Flipset_Library::set_hidden( $secret, true );
		$this->assertTrue( Flipset_Library::is_hidden( $secret ) );

		$html = Flipset::instance()->library->render_shortcode( array( 'search' => 'false' ) );
		$this->assertStringContainsString( 'Public Brochure', $html );
		$this->assertStringNotContainsString( 'Private Payroll', $html );

		Flipset_Library::set_hidden( $secret, false );
		$this->assertFalse( Flipset_Library::is_hidden( $secret ) );
		$this->assertSame( '', get_post_meta( $secret, Flipset_Library::META_HIDDEN, true ) );

		$html = Flipset::instance()->library->render_shortcode( array( 'search' => 'false' ) );
		$this->assertStringContainsString( 'Private Payroll', $html );
	}

	public function test_hidden_pdf_is_left_out_of_library_search() {
		$secret = $this->create_pdf_attachment( 'Hidden Findable' );
		Flipset_Library::set_hidden( $secret, true );

		$next                         = $this->next_library_uid();
		$_GET[ 'flipset_q_' . $next ] = 'Findable';
		$html                         = Flipset::instance()->library->render_shortcode( array( 'search' => 'true' ) );
		unset( $_GET[ 'flipset_q_' . $next ] );

		$this->assertStringNotContainsString( 'Hidden Findable', $html );
		$this->assertStringContainsString( 'No documents matched that search', $html );
	}

	public function test_ids_lists_only_those_pdfs_in_any_hidden_state() {
		$a = $this->create_pdf_attachment( 'Alpha Doc' );
		$this->create_pdf_attachment( 'Beta Doc' );
		$c = $this->create_pdf_attachment( 'Gamma Doc' );
		Flipset_Library::set_hidden( $c, true );

		$html = Flipset::instance()->library->render_shortcode(
			array(
				'search' => 'false',
				'ids'    => $a . ', ' . $c,
			)
		);
		$this->assertStringContainsString( 'Alpha Doc', $html );
		$this->assertStringContainsString( 'Gamma Doc', $html );
		$this->assertStringNotContainsString( 'Beta Doc', $html );
	}

	public function test_ids_from_block_array_and_non_pdf_ids_are_ignored() {
		$a    = $this->create_pdf_attachment( 'Block Picked' );
		$post = self::factory()->post->create( array( 'post_title' => 'Plain Post Not PDF' ) );

		$html = Flipset::instance()->library->render_shortcode(
			array(
				'search' => 'false',
				'ids'    => array( $a, $post ),
			)
		);
		$this->assertStringContainsString( 'Block Picked', $html );
		$this->assertStringNotContainsString( 'Plain Post Not PDF', $html );
	}

	public function test_exclude_removes_pdfs_with_and_without_ids() {
		$a = $this->create_pdf_attachment( 'Keep Me' );
		$b = $this->create_pdf_attachment( 'Drop Me' );

		$html = Flipset::instance()->library->render_shortcode(
			array(
				'search'  => 'false',
				'exclude' => (string) $b,
			)
		);
		$this->assertStringContainsString( 'Keep Me', $html );
		$this->assertStringNotContainsString( 'Drop Me', $html );

		$html = Flipset::instance()->library->render_shortcode(
			array(
				'search'  => 'false',
				'ids'     => $a . ',' . $b,
				'exclude' => (string) $b,
			)
		);
		$this->assertStringContainsString( 'Keep Me', $html );
		$this->assertStringNotContainsString( 'Drop Me', $html );
	}

	public function test_ids_that_are_all_excluded_show_nothing() {
		$this->create_pdf_attachment( 'Other Doc' );
		$b = $this->create_pdf_attachment( 'Only Doc' );

		$html = Flipset::instance()->library->render_shortcode(
			array(
				'search'  => 'false',
				'ids'     => (string) $b,
				'exclude' => (string) $b,
			)
		);
		$this->assertStringNotContainsString( 'Only Doc', $html );
		$this->assertStringNotContainsString( 'Other Doc', $html );
		$this->assertStringContainsString( 'No documents found', $html );
	}

	public function test_parse_ids() {
		$this->assertSame( array( 12, 34, 56 ), Flipset_Library::parse_ids( '12, 34 56' ) );
		$this->assertSame( array( 12, 34 ), Flipset_Library::parse_ids( '12,12,34' ) );
		$this->assertSame( array( 7 ), Flipset_Library::parse_ids( array( 7, '7', 'x', -3, 0 ) ) );
		$this->assertSame( array(), Flipset_Library::parse_ids( '' ) );
		$this->assertSame( array(), Flipset_Library::parse_ids( 'abc, 1.5, -2' ) );
		$this->assertSame( array(), Flipset_Library::parse_ids( null ) );
		$this->assertCount( Flipset_Library::MAX_IDS, Flipset_Library::parse_ids( implode( ',', range( 1, Flipset_Library::MAX_IDS + 50 ) ) ) );
	}

	public function test_set_hidden_ignores_zero_id() {
		Flipset_Library::set_hidden( 0, true );
		$this->assertFalse( Flipset_Library::is_hidden( 0 ) );
	}

	public function test_att_flag_falls_back_for_unrecognised_values() {
		$this->assertTrue( $this->call_private( 'att_flag', 'maybe', true ) );
		$this->assertFalse( $this->call_private( 'att_flag', 'maybe', false ) );
		$this->assertTrue( $this->call_private( 'att_flag', '', true ) );
		$this->assertFalse( $this->call_private( 'att_flag', 'off', true ) );
		$this->assertTrue( $this->call_private( 'att_flag', true, false ) );
	}
}
