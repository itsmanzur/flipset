<?php
/**
 * Beaver Builder module: Flipset Library.
 *
 * @package Flipset
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Flipset_Beaver_Library extends FLBuilderModule {

	public function __construct() {
		parent::__construct(
			array(
				'name'            => __( 'Flipset Library', 'flipset' ),
				'description'     => __( 'A grid of Media Library PDFs.', 'flipset' ),
				'group'           => __( 'Flipset', 'flipset' ),
				'category'        => __( 'Media', 'flipset' ),
				'dir'             => FLIPSET_DIR . 'includes/builders/beaver/library/',
				'url'             => FLIPSET_URL . 'includes/builders/beaver/library/',
				'editor_export'   => true,
				'enabled'         => true,
				'partial_refresh' => true,
			)
		);
	}
}
