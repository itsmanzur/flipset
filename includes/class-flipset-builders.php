<?php
/**
 * Flipset_Builders
 *
 * Registers native Elementor, Divi, and Beaver Builder widgets only when
 * that builder is active. Rendering always delegates to Flipset_Viewer /
 * Flipset_Library so shortcode, block, and builder output stay aligned.
 *
 * @package Flipset
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Flipset_Builders {

	public function init() {
		add_action( 'elementor/widgets/register', array( $this, 'register_elementor_widgets' ) );
		add_action( 'elementor/widgets/widgets_registered', array( $this, 'register_elementor_widgets_legacy' ) );
		add_action( 'elementor/elements/categories_registered', array( $this, 'register_elementor_category' ) );
		add_action( 'et_builder_ready', array( $this, 'register_divi_modules' ) );
		add_action( 'init', array( $this, 'register_beaver_modules' ), 11 );
	}

	/**
	 * @param \Elementor\Elements_Manager $elements_manager Manager.
	 */
	public function register_elementor_category( $elements_manager ) {
		if ( ! is_object( $elements_manager ) || ! method_exists( $elements_manager, 'add_category' ) ) {
			return;
		}
		$elements_manager->add_category(
			'flipset',
			array(
				'title' => __( 'Flipset', 'flipset' ),
				'icon'  => 'eicon-document-file',
			)
		);
	}

	/**
	 * Elementor < 3.5 used widgets_registered. 3.5+ also fires that
	 * hook as a deprecated alias, so skip when the new hook already ran.
	 *
	 * @param \Elementor\Widgets_Manager $widgets_manager Manager.
	 */
	public function register_elementor_widgets_legacy( $widgets_manager ) {
		if ( defined( 'ELEMENTOR_VERSION' ) && version_compare( ELEMENTOR_VERSION, '3.5.0', '>=' ) ) {
			return;
		}
		$this->register_elementor_widgets( $widgets_manager );
	}

	/**
	 * @param \Elementor\Widgets_Manager $widgets_manager Manager.
	 */
	public function register_elementor_widgets( $widgets_manager ) {
		if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
			return;
		}

		require_once FLIPSET_DIR . 'includes/builders/class-flipset-elementor-viewer.php';
		require_once FLIPSET_DIR . 'includes/builders/class-flipset-elementor-library.php';

		if ( method_exists( $widgets_manager, 'register' ) ) {
			$widgets_manager->register( new Flipset_Elementor_Viewer() );
			$widgets_manager->register( new Flipset_Elementor_Library() );
			return;
		}

		if ( method_exists( $widgets_manager, 'register_widget_type' ) ) {
			$widgets_manager->register_widget_type( new Flipset_Elementor_Viewer() );
			$widgets_manager->register_widget_type( new Flipset_Elementor_Library() );
		}
	}

	public function register_divi_modules() {
		if ( ! class_exists( 'ET_Builder_Module' ) ) {
			return;
		}
		require_once FLIPSET_DIR . 'includes/builders/class-flipset-divi-viewer.php';
		require_once FLIPSET_DIR . 'includes/builders/class-flipset-divi-library.php';
		new Flipset_Divi_Viewer();
		new Flipset_Divi_Library();
	}

	public function register_beaver_modules() {
		if ( ! class_exists( 'FLBuilder' ) ) {
			return;
		}

		require_once FLIPSET_DIR . 'includes/builders/class-flipset-beaver-viewer.php';
		require_once FLIPSET_DIR . 'includes/builders/class-flipset-beaver-library.php';

		FLBuilder::register_module(
			'Flipset_Beaver_Viewer',
			array(
				'general' => array(
					'title'    => __( 'PDF', 'flipset' ),
					'sections' => array(
						'file'   => array(
							'title'  => __( 'Document', 'flipset' ),
							'fields' => array(
								'file'  => array(
									'type'        => 'text',
									'label'       => __( 'PDF URL', 'flipset' ),
									'connections' => array( 'url', 'string' ),
									'help'        => __( 'Paste a Media Library PDF URL, or copy one from Flipset → Documents.', 'flipset' ),
								),
								'title' => array(
									'type'    => 'text',
									'label'   => __( 'Title caption', 'flipset' ),
									'default' => '',
								),
								'page'  => array(
									'type'    => 'unit',
									'label'   => __( 'Start page', 'flipset' ),
									'default' => '1',
									'slider'  => array(
										'min'  => 1,
										'max'  => 50,
										'step' => 1,
									),
								),
							),
						),
						'layout' => array(
							'title'  => __( 'Layout', 'flipset' ),
							'fields' => array(
								'width'    => array(
									'type'    => 'text',
									'label'   => __( 'Width', 'flipset' ),
									'default' => '100%',
								),
								'height'   => array(
									'type'    => 'text',
									'label'   => __( 'Height', 'flipset' ),
									'default' => '600px',
								),
								'download' => array(
									'type'    => 'select',
									'label'   => __( 'Download button', 'flipset' ),
									'default' => 'true',
									'options' => array(
										'true'  => __( 'Show', 'flipset' ),
										'false' => __( 'Hide', 'flipset' ),
									),
								),
								'print'    => array(
									'type'    => 'select',
									'label'   => __( 'Print button', 'flipset' ),
									'default' => 'true',
									'options' => array(
										'true'  => __( 'Show', 'flipset' ),
										'false' => __( 'Hide', 'flipset' ),
									),
								),
							),
						),
					),
				),
			)
		);

		FLBuilder::register_module(
			'Flipset_Beaver_Library',
			array(
				'general' => array(
					'title'    => __( 'Library', 'flipset' ),
					'sections' => array(
						'grid' => array(
							'title'  => __( 'Grid', 'flipset' ),
							'fields' => array(
								'columns'  => array(
									'type'    => 'select',
									'label'   => __( 'Columns', 'flipset' ),
									'default' => '3',
									'options' => array(
										'1' => '1',
										'2' => '2',
										'3' => '3',
										'4' => '4',
										'5' => '5',
										'6' => '6',
									),
								),
								'per_page' => array(
									'type'    => 'unit',
									'label'   => __( 'Per page', 'flipset' ),
									'default' => '12',
								),
								'orderby'  => array(
									'type'    => 'select',
									'label'   => __( 'Order by', 'flipset' ),
									'default' => 'date',
									'options' => array(
										'date'     => __( 'Date', 'flipset' ),
										'title'    => __( 'Title', 'flipset' ),
										'modified' => __( 'Modified', 'flipset' ),
									),
								),
								'order'    => array(
									'type'    => 'select',
									'label'   => __( 'Order', 'flipset' ),
									'default' => 'DESC',
									'options' => array(
										'DESC' => __( 'Newest first', 'flipset' ),
										'ASC'  => __( 'Oldest first', 'flipset' ),
									),
								),
								'search'   => array(
									'type'    => 'select',
									'label'   => __( 'Search box', 'flipset' ),
									'default' => 'true',
									'options' => array(
										'true'  => __( 'Show', 'flipset' ),
										'false' => __( 'Hide', 'flipset' ),
									),
								),
							),
						),
					),
				),
			)
		);
	}
}
