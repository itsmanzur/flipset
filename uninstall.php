<?php
/**
 * Fires only when the user clicks "Delete" on the Plugins screen —
 * never on simple deactivation. Removes the options this plugin created.
 *
 * @package Flipset
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'flipset_settings' );
delete_transient( 'flipset_activation_redirect' );

// Thumbnails are real image attachments parented to the PDF; the meta key
// holding their IDs is about to be wiped, so delete the attachments first.
global $wpdb;
$flipset_thumbnail_ids = $wpdb->get_col( "SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_flipset_thumbnail_id'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-off uninstall cleanup.
if ( ! empty( $flipset_thumbnail_ids ) ) {
	foreach ( $flipset_thumbnail_ids as $flipset_thumbnail_id ) {
		$flipset_thumbnail_id = absint( $flipset_thumbnail_id );
		if ( $flipset_thumbnail_id ) {
			wp_delete_attachment( $flipset_thumbnail_id, true );
		}
	}
}

// Delete flipset_embed posts and their meta.
$flipset_embed_ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'flipset_embed'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-off uninstall cleanup.
if ( ! empty( $flipset_embed_ids ) ) {
	foreach ( $flipset_embed_ids as $flipset_embed_id ) {
		wp_delete_post( absint( $flipset_embed_id ), true );
	}
}

delete_post_meta_by_key( '_flipset_extracted_text' );
delete_post_meta_by_key( '_flipset_extracted_pages' );
delete_post_meta_by_key( '_flipset_text_indexed' );
delete_post_meta_by_key( '_flipset_search_index' );
delete_post_meta_by_key( '_flipset_pdf_tagged' );
delete_post_meta_by_key( '_flipset_a11y_checked' );
delete_post_meta_by_key( '_flipset_thumbnail_id' );
delete_post_meta_by_key( '_flipset_embed_config' );
delete_metadata( 'user', 0, 'flipset_welcome_dismissed', '', true );
