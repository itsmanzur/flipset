<?php
/**
 * Flipset_A11y tests.
 *
 * @package Flipset
 */

class Test_Flipset_A11y extends Flipset_TestCase {

	public function test_unchecked_pdf_is_not_tagged() {
		$pdf_id = $this->create_pdf_attachment();
		$this->assertFalse( Flipset_A11y::is_checked( $pdf_id ) );
		$this->assertFalse( Flipset_A11y::is_tagged( $pdf_id ) );
	}

	public function test_stores_tagged_and_untagged_flags() {
		$pdf_id = $this->create_pdf_attachment();
		update_post_meta( $pdf_id, Flipset_A11y::META_CHECKED, '1' );
		update_post_meta( $pdf_id, Flipset_A11y::META_TAGGED, '0' );
		$this->assertTrue( Flipset_A11y::is_checked( $pdf_id ) );
		$this->assertFalse( Flipset_A11y::is_tagged( $pdf_id ) );

		update_post_meta( $pdf_id, Flipset_A11y::META_TAGGED, '1' );
		$this->assertTrue( Flipset_A11y::is_tagged( $pdf_id ) );
	}

	public function test_pdf_tagged_filter_can_override() {
		$pdf_id = $this->create_pdf_attachment();
		update_post_meta( $pdf_id, Flipset_A11y::META_CHECKED, '1' );
		update_post_meta( $pdf_id, Flipset_A11y::META_TAGGED, '0' );
		add_filter( 'flipset/pdf_tagged', '__return_true' );
		$this->assertTrue( Flipset_A11y::is_tagged( $pdf_id ) );
		remove_filter( 'flipset/pdf_tagged', '__return_true' );
	}

	public function test_checking_enabled_defaults_true() {
		$this->assertTrue( Flipset_A11y::checking_enabled() );
		update_option(
			'flipset_settings',
			array(
				'check_pdf_a11y' => false,
			)
		);
		$this->assertFalse( Flipset_A11y::checking_enabled() );
	}
}
