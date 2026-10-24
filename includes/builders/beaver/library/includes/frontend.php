<?php
/**
 * Beaver Builder frontend: Flipset Library.
 *
 * @package Flipset
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$flipset_atts = array(
	'columns'  => isset( $settings->columns ) ? sanitize_text_field( (string) $settings->columns ) : '3',
	'per_page' => isset( $settings->per_page ) ? absint( $settings->per_page ) : 12,
	'orderby'  => isset( $settings->orderby ) ? sanitize_key( $settings->orderby ) : 'date',
	'order'    => isset( $settings->order ) ? sanitize_key( $settings->order ) : 'DESC',
	'search'   => isset( $settings->search ) ? sanitize_text_field( (string) $settings->search ) : 'true',
);

echo wp_kses( Flipset::instance()->library->render_shortcode( $flipset_atts ), Flipset_Viewer::allowed_html() );
