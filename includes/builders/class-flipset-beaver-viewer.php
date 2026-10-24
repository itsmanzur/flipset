<?php
/**
 * Beaver Builder module: Flipset Viewer.
 *
 * @package Flipset
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Flipset_Beaver_Viewer extends FLBuilderModule {

	public function __construct() {
		parent::__construct(
			array(
				'name'            => __( 'Flipset Viewer', 'flipset' ),
				'description'     => __( 'Embed a PDF with the Flipset viewer.', 'flipset' ),
				'group'           => __( 'Flipset', 'flipset' ),
				'category'        => __( 'Media', 'flipset' ),
				'dir'             => FLIPSET_DIR . 'includes/builders/beaver/viewer/',
				'url'             => FLIPSET_URL . 'includes/builders/beaver/viewer/',
				'editor_export'   => true,
				'enabled'         => true,
				'partial_refresh' => true,
			)
		);
	}
}
