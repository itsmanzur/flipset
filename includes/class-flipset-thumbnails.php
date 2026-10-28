<?php
/**
 * Flipset_Thumbnails
 *
 * Persistent first-page previews for Media Library PDFs. Generated once
 * from the Documents admin screen (PDF.js canvas -> PNG), stored as real
 * image attachments parented to the PDF.
 *
 * @package Flipset
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Flipset_Thumbnails {
	/** Largest accepted thumbnail, in decoded bytes. */
	const MAX_BYTES = 2097152;

	/** Smallest possible PNG is 67 bytes; anything below cannot be an image. */
	const MIN_BYTES = 67;

	/** Largest accepted width or height, in pixels. */
	const MAX_DIMENSION = 4096;

	/** Generated first-page image (attachment ID, owned by Flipset). */
	const META_KEY = '_flipset_thumbnail_id';

	/** Image picked by the site owner as the cover (attachment ID, never deleted by Flipset). */
	const COVER_META_KEY = '_flipset_cover_id';

	public function init() {
		add_action( 'wp_ajax_flipset_save_thumbnail', array( $this, 'ajax_save_thumbnail' ) );
		add_action( 'wp_ajax_flipset_set_cover', array( $this, 'ajax_set_cover' ) );
		add_action( 'delete_attachment', array( $this, 'on_delete_attachment' ) );
	}

	/**
	 * Image URL for a PDF's stored thumbnail, or empty if none yet.
	 *
	 * @param int    $pdf_attachment_id PDF attachment post ID.
	 * @param string $size              Registered image size.
	 * @return string
	 */
	public static function get_thumbnail_url( $pdf_attachment_id, $size = 'medium' ) {
		$thumb_id = self::get_thumbnail_id( $pdf_attachment_id );
		if ( ! $thumb_id ) {
			return '';
		}

		$url = wp_get_attachment_image_url( $thumb_id, $size );
		return $url ? $url : '';
	}

	/**
	 * The image to show for a PDF: the custom cover when one is set and still
	 * exists, otherwise the generated first-page image.
	 *
	 * @param int $pdf_attachment_id PDF attachment post ID.
	 * @return int
	 */
	public static function get_thumbnail_id( $pdf_attachment_id ) {
		$cover_id = self::get_cover_id( $pdf_attachment_id );
		if ( $cover_id ) {
			return $cover_id;
		}

		return self::get_generated_id( $pdf_attachment_id );
	}

	/**
	 * Custom cover image ID, or 0 when none is set or the image is gone.
	 *
	 * @param int $pdf_attachment_id PDF attachment post ID.
	 * @return int
	 */
	public static function get_cover_id( $pdf_attachment_id ) {
		$pdf_attachment_id = absint( $pdf_attachment_id );
		if ( ! $pdf_attachment_id ) {
			return 0;
		}

		$cover_id = absint( get_post_meta( $pdf_attachment_id, self::COVER_META_KEY, true ) );
		return ( $cover_id && wp_attachment_is_image( $cover_id ) ) ? $cover_id : 0;
	}

	/**
	 * First-page image generated in the browser, ignoring any custom cover.
	 *
	 * @param int $pdf_attachment_id PDF attachment post ID.
	 * @return int
	 */
	public static function get_generated_id( $pdf_attachment_id ) {
		$pdf_attachment_id = absint( $pdf_attachment_id );
		if ( ! $pdf_attachment_id ) {
			return 0;
		}

		return absint( get_post_meta( $pdf_attachment_id, self::META_KEY, true ) );
	}

	public function ajax_save_thumbnail() {
		check_ajax_referer( 'flipset_admin', 'nonce' );

		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden.', 'flipset' ) ), 403 );
		}

		$pdf_id = isset( $_POST['attachment_id'] ) ? absint( wp_unslash( $_POST['attachment_id'] ) ) : 0;
		$pdf    = $pdf_id ? get_post( $pdf_id ) : null;

		if ( ! $pdf || 'attachment' !== $pdf->post_type || 'application/pdf' !== $pdf->post_mime_type ) {
			wp_send_json_error( array( 'message' => __( 'That file is not a PDF attachment.', 'flipset' ) ) );
		}

		if ( ! current_user_can( 'edit_post', $pdf_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden.', 'flipset' ) ), 403 );
		}

		// Sanitise at the point of reading: the value is a data URL, so only the
		// prefix plus the base64 alphabet may survive.
		$raw    = isset( $_POST['thumbnail_data'] ) ? sanitize_text_field( wp_unslash( $_POST['thumbnail_data'] ) ) : '';
		$prefix = 'data:image/png;base64,';
		if ( 0 !== stripos( $raw, $prefix ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid thumbnail data.', 'flipset' ) ) );
		}

		$payload = preg_replace( '#[^A-Za-z0-9+/=]#', '', substr( $raw, strlen( $prefix ) ) );
		if ( ! is_string( $payload ) || '' === $payload ) {
			wp_send_json_error( array( 'message' => __( 'Invalid thumbnail data.', 'flipset' ) ) );
		}

		// Base64 expands data by 4/3; refuse oversized input before decoding it.
		if ( strlen( $payload ) > (int) ceil( self::MAX_BYTES * 4 / 3 ) + 4 ) {
			wp_send_json_error( array( 'message' => __( 'Thumbnail is too large.', 'flipset' ) ) );
		}

		$binary = base64_decode( $payload, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- decoding the admin-uploaded PNG data URL; the payload is reduced to the base64 alphabet, decoded strictly and then validated as an image.
		if ( false === $binary || strlen( $binary ) < self::MIN_BYTES ) {
			wp_send_json_error( array( 'message' => __( 'Invalid thumbnail data.', 'flipset' ) ) );
		}

		if ( strlen( $binary ) > self::MAX_BYTES ) {
			wp_send_json_error( array( 'message' => __( 'Thumbnail is too large.', 'flipset' ) ) );
		}

		// The full 8-byte PNG signature (\x89PNG\r\n\x1a\n), then a real image parse.
		if ( substr( $binary, 0, 8 ) !== "\x89PNG\r\n\x1a\n" ) {
			wp_send_json_error( array( 'message' => __( 'Invalid thumbnail data.', 'flipset' ) ) );
		}

		$info = getimagesizefromstring( $binary );
		if ( ! is_array( $info )
			|| ! isset( $info['mime'], $info[0], $info[1] )
			|| 'image/png' !== $info['mime']
			|| $info[0] < 1 || $info[0] > self::MAX_DIMENSION
			|| $info[1] < 1 || $info[1] > self::MAX_DIMENSION ) {
			wp_send_json_error( array( 'message' => __( 'Invalid thumbnail data.', 'flipset' ) ) );
		}

		$filename = 'flipset-thumb-' . $pdf_id . '.png';
		$upload   = wp_upload_bits( $filename, null, $binary );
		if ( ! empty( $upload['error'] ) ) {
			wp_send_json_error( array( 'message' => sanitize_text_field( $upload['error'] ) ) );
		}

		// Re-encode the stored file so anything that is not pixel data (trailing
		// bytes, extra chunks) is dropped.
		$editor = wp_get_image_editor( $upload['file'] );
		if ( is_wp_error( $editor ) ) {
			wp_delete_file( $upload['file'] );
			wp_send_json_error( array( 'message' => __( 'Could not save the thumbnail.', 'flipset' ) ) );
		}
		$saved = $editor->save( $upload['file'], 'image/png' );
		if ( is_wp_error( $saved ) ) {
			wp_delete_file( $upload['file'] );
			wp_send_json_error( array( 'message' => __( 'Could not save the thumbnail.', 'flipset' ) ) );
		}

		$checked = wp_check_filetype_and_ext( $upload['file'], $filename );
		if ( empty( $checked['type'] ) || 'image/png' !== $checked['type'] ) {
			wp_delete_file( $upload['file'] );
			wp_send_json_error( array( 'message' => __( 'Invalid thumbnail data.', 'flipset' ) ) );
		}

		$old_id = self::get_generated_id( $pdf_id );

		$filetype = wp_check_filetype( $filename, null );
		$title    = get_the_title( $pdf );
		if ( '' === $title ) {
			$title = wp_basename( (string) wp_get_attachment_url( $pdf_id ) );
		}

		$attach_id = wp_insert_attachment(
			array(
				'post_mime_type' => $filetype['type'] ? $filetype['type'] : 'image/png',
				'post_title'     => sprintf(
					/* translators: %s: PDF title. */
					__( 'Flipset thumbnail: %s', 'flipset' ),
					$title
				),
				'post_content'   => '',
				'post_status'    => 'inherit',
				'post_parent'    => $pdf_id,
			),
			$upload['file'],
			$pdf_id
		);

		if ( is_wp_error( $attach_id ) || ! $attach_id ) {
			wp_send_json_error( array( 'message' => __( 'Could not save the thumbnail.', 'flipset' ) ) );
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';
		$metadata = wp_generate_attachment_metadata( $attach_id, $upload['file'] );
		wp_update_attachment_metadata( $attach_id, $metadata );

		if ( $old_id && (int) $old_id !== (int) $attach_id ) {
			wp_delete_attachment( $old_id, true );
		}

		update_post_meta( $pdf_id, self::META_KEY, (int) $attach_id );
		update_post_meta( $attach_id, '_wp_attachment_image_alt', sanitize_text_field( $title ) );

		$url = wp_get_attachment_image_url( $attach_id, 'medium' );

		wp_send_json_success(
			array(
				'id'  => (int) $attach_id,
				'url' => $url ? $url : (string) $upload['url'],
			)
		);
	}

	/**
	 * Set or clear the custom cover of a PDF. An image ID of 0 clears it.
	 */
	public function ajax_set_cover() {
		check_ajax_referer( 'flipset_admin', 'nonce' );

		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden.', 'flipset' ) ), 403 );
		}

		$pdf_id = isset( $_POST['attachment_id'] ) ? absint( wp_unslash( $_POST['attachment_id'] ) ) : 0;
		$pdf    = $pdf_id ? get_post( $pdf_id ) : null;

		if ( ! $pdf || 'attachment' !== $pdf->post_type || 'application/pdf' !== $pdf->post_mime_type ) {
			wp_send_json_error( array( 'message' => __( 'That file is not a PDF attachment.', 'flipset' ) ) );
		}

		if ( ! current_user_can( 'edit_post', $pdf_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden.', 'flipset' ) ), 403 );
		}

		$image_id = isset( $_POST['image_id'] ) ? absint( wp_unslash( $_POST['image_id'] ) ) : 0;

		if ( $image_id ) {
			if ( ! wp_attachment_is_image( $image_id ) || ! current_user_can( 'edit_post', $image_id ) ) {
				wp_send_json_error( array( 'message' => __( 'Pick an image from the Media Library.', 'flipset' ) ) );
			}
			update_post_meta( $pdf_id, self::COVER_META_KEY, $image_id );
		} else {
			delete_post_meta( $pdf_id, self::COVER_META_KEY );
		}

		$thumb_id = self::get_thumbnail_id( $pdf_id );
		$url      = $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'thumbnail' ) : '';

		wp_send_json_success(
			array(
				'custom' => (bool) self::get_cover_id( $pdf_id ),
				'url'    => $url ? $url : '',
				'needs'  => ! $thumb_id,
			)
		);
	}

	/**
	 * When a PDF is deleted, also remove its generated thumbnail image.
	 *
	 * @param int $post_id Attachment ID being deleted.
	 */
	public function on_delete_attachment( $post_id ) {
		$post_id = absint( $post_id );
		if ( ! $post_id ) {
			return;
		}

		$mime = get_post_mime_type( $post_id );
		if ( 'application/pdf' === $mime ) {
			$thumb_id = self::get_generated_id( $post_id );
			delete_post_meta( $post_id, self::META_KEY );
			delete_post_meta( $post_id, self::COVER_META_KEY );

			if ( $thumb_id && $thumb_id !== $post_id ) {
				wp_delete_attachment( $thumb_id, true );
			}
			return;
		}

		$parent = wp_get_post_parent_id( $post_id );
		if ( $parent && self::get_generated_id( $parent ) === $post_id ) {
			delete_post_meta( $parent, self::META_KEY );
		}

		// An image used as a custom cover was deleted: forget it so the PDF falls back to its generated image.
		if ( wp_attachment_is_image( $post_id ) ) {
			$pdf_ids = get_posts(
				array(
					'post_type'      => 'attachment',
					'post_status'    => 'any',
					'fields'         => 'ids',
					'posts_per_page' => -1,
					'meta_key'       => self::COVER_META_KEY, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- rare: only when an image is deleted.
					'meta_value'     => $post_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- rare: only when an image is deleted.
				)
			);
			foreach ( $pdf_ids as $pdf_id ) {
				delete_post_meta( $pdf_id, self::COVER_META_KEY );
			}
		}
	}
}
