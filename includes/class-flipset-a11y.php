<?php
/**
 * Flipset_A11y
 *
 * Detects whether a Media Library PDF is a tagged (accessible) file.
 * PDF.js reads the MarkInfo dictionary on Flipset → Documents; the
 * result is stored on the attachment so editors see a warning when
 * screen-reader structure is missing.
 *
 * @package Flipset
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Flipset_A11y {

	const META_TAGGED  = '_flipset_pdf_tagged';
	const META_CHECKED = '_flipset_a11y_checked';

	public function init() {
		add_action( 'wp_ajax_flipset_save_a11y', array( $this, 'ajax_save_a11y' ) );
	}

	/**
	 * @param int $attachment_id PDF attachment ID.
	 * @return bool
	 */
	public static function is_checked( $attachment_id ) {
		$attachment_id = absint( $attachment_id );
		if ( ! $attachment_id ) {
			return false;
		}
		return '1' === (string) get_post_meta( $attachment_id, self::META_CHECKED, true );
	}

	/**
	 * @param int $attachment_id PDF attachment ID.
	 * @return bool
	 */
	public static function is_tagged( $attachment_id ) {
		$attachment_id = absint( $attachment_id );
		if ( ! $attachment_id ) {
			return false;
		}
		$tagged = ( '1' === (string) get_post_meta( $attachment_id, self::META_TAGGED, true ) );

		/**
		 * Filter: flipset/pdf_tagged
		 *
		 * Override the stored tagged-PDF flag for an attachment.
		 *
		 * @param bool $tagged         Whether MarkInfo /Marked is true.
		 * @param int  $attachment_id  PDF attachment ID.
		 */
		return (bool) apply_filters( 'flipset/pdf_tagged', $tagged, $attachment_id );
	}

	/**
	 * @return bool
	 */
	public static function checking_enabled() {
		$settings = get_option( 'flipset_settings', array() );
		return ! isset( $settings['check_pdf_a11y'] ) || ! empty( $settings['check_pdf_a11y'] );
	}

	public function ajax_save_a11y() {
		check_ajax_referer( 'flipset_admin', 'nonce' );

		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden.', 'flipset' ) ), 403 );
		}

		if ( ! self::checking_enabled() ) {
			wp_send_json_error( array( 'message' => __( 'PDF accessibility checks are turned off.', 'flipset' ) ) );
		}

		$pdf_id = isset( $_POST['attachment_id'] ) ? absint( wp_unslash( $_POST['attachment_id'] ) ) : 0;
		$pdf    = $pdf_id ? get_post( $pdf_id ) : null;

		if ( ! $pdf || 'attachment' !== $pdf->post_type || 'application/pdf' !== $pdf->post_mime_type ) {
			wp_send_json_error( array( 'message' => __( 'That file is not a PDF attachment.', 'flipset' ) ) );
		}

		if ( ! current_user_can( 'edit_post', $pdf_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden.', 'flipset' ) ), 403 );
		}

		$tagged = isset( $_POST['tagged'] ) && '1' === (string) wp_unslash( $_POST['tagged'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- compared to literal '1'.

		update_post_meta( $pdf_id, self::META_CHECKED, '1' );
		update_post_meta( $pdf_id, self::META_TAGGED, $tagged ? '1' : '0' );

		wp_send_json_success(
			array(
				'tagged' => $tagged,
			)
		);
	}
}
