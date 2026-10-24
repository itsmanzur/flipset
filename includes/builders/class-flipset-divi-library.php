<?php
/**
 * Divi module: Flipset Library.
 *
 * @package Flipset
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Flipset_Divi_Library extends ET_Builder_Module {

	/**
	 * Divi module slug.
	 *
	 * @var string
	 */
	public $slug = 'flipset_library';
	/**
	 * Visual Builder support level.
	 *
	 * @var string
	 */
	public $vb_support = 'partial';

	/**
	 * Module credits shown in the Divi builder.
	 *
	 * @var array
	 */
	protected $module_credits = array(
		'module_uri' => 'https://flipset.thereadscope.com',
		'author'     => 'itsmanzur',
		'author_uri' => 'https://thereadscope.com',
	);

	public function init() {
		$this->name                   = __( 'Flipset Library', 'flipset' );
		$this->settings_modal_toggles = array(
			'general' => array(
				'toggles' => array(
					'main_content' => __( 'Grid', 'flipset' ),
				),
			),
		);
	}

	public function get_fields() {
		return array(
			'columns'  => array(
				'label'           => __( 'Columns', 'flipset' ),
				'type'            => 'select',
				'options'         => array(
					'1' => '1',
					'2' => '2',
					'3' => '3',
					'4' => '4',
					'5' => '5',
					'6' => '6',
				),
				'default'         => '3',
				'option_category' => 'layout',
				'toggle_slug'     => 'main_content',
			),
			'per_page' => array(
				'label'           => __( 'Per page', 'flipset' ),
				'type'            => 'text',
				'default'         => '12',
				'option_category' => 'layout',
				'toggle_slug'     => 'main_content',
			),
			'orderby'  => array(
				'label'           => __( 'Order by', 'flipset' ),
				'type'            => 'select',
				'options'         => array(
					'date'     => __( 'Date', 'flipset' ),
					'title'    => __( 'Title', 'flipset' ),
					'modified' => __( 'Modified', 'flipset' ),
				),
				'default'         => 'date',
				'option_category' => 'layout',
				'toggle_slug'     => 'main_content',
			),
			'order'    => array(
				'label'           => __( 'Order', 'flipset' ),
				'type'            => 'select',
				'options'         => array(
					'DESC' => __( 'Newest first', 'flipset' ),
					'ASC'  => __( 'Oldest first', 'flipset' ),
				),
				'default'         => 'DESC',
				'option_category' => 'layout',
				'toggle_slug'     => 'main_content',
			),
			'search'   => array(
				'label'           => __( 'Search box', 'flipset' ),
				'type'            => 'yes_no_button',
				'options'         => array(
					'on'  => __( 'Show', 'flipset' ),
					'off' => __( 'Hide', 'flipset' ),
				),
				'default'         => 'on',
				'option_category' => 'layout',
				'toggle_slug'     => 'main_content',
			),
		);
	}

	public function render( $attrs, $content, $render_slug ) {
		unset( $attrs, $content, $render_slug );

		return Flipset::instance()->library->render_shortcode(
			array(
				'columns'  => isset( $this->props['columns'] ) ? $this->props['columns'] : '3',
				'per_page' => isset( $this->props['per_page'] ) ? $this->props['per_page'] : '12',
				'orderby'  => isset( $this->props['orderby'] ) ? $this->props['orderby'] : 'date',
				'order'    => isset( $this->props['order'] ) ? $this->props['order'] : 'DESC',
				'search'   => ( isset( $this->props['search'] ) && 'off' === $this->props['search'] ) ? 'false' : 'true',
			)
		);
	}
}
