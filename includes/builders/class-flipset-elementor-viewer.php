<?php
/**
 * Elementor widget: Flipset Viewer.
 *
 * @package Flipset
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Flipset_Elementor_Viewer extends \Elementor\Widget_Base {

	public function get_name() {
		return 'flipset-viewer';
	}

	public function get_title() {
		return __( 'Flipset Viewer', 'flipset' );
	}

	public function get_icon() {
		return 'eicon-document-file';
	}

	public function get_categories() {
		return array( 'flipset', 'general' );
	}

	public function get_keywords() {
		return array( 'pdf', 'document', 'viewer', 'flipset', 'ebook' );
	}

	protected function register_controls() {
		$this->start_controls_section(
			'section_file',
			array(
				'label' => __( 'Document', 'flipset' ),
			)
		);

		$this->add_control(
			'file',
			array(
				'label'       => __( 'PDF file', 'flipset' ),
				'type'        => \Elementor\Controls_Manager::MEDIA,
				'media_types' => array( 'application/pdf' ),
				'description' => __( 'Choose a PDF from the Media Library.', 'flipset' ),
			)
		);

		$this->add_control(
			'title',
			array(
				'label'   => __( 'Title caption', 'flipset' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => '',
			)
		);

		$this->add_control(
			'page',
			array(
				'label'   => __( 'Start page', 'flipset' ),
				'type'    => \Elementor\Controls_Manager::NUMBER,
				'default' => 1,
				'min'     => 1,
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_layout',
			array(
				'label' => __( 'Layout', 'flipset' ),
			)
		);

		$this->add_control(
			'width',
			array(
				'label'   => __( 'Width', 'flipset' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => '100%',
			)
		);

		$this->add_control(
			'height',
			array(
				'label'   => __( 'Height', 'flipset' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => '600px',
			)
		);

		$this->add_control(
			'download',
			array(
				'label'        => __( 'Download button', 'flipset' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'Show', 'flipset' ),
				'label_off'    => __( 'Hide', 'flipset' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'print',
			array(
				'label'        => __( 'Print button', 'flipset' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'Show', 'flipset' ),
				'label_off'    => __( 'Hide', 'flipset' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->end_controls_section();
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		$file     = '';
		if ( ! empty( $settings['file']['url'] ) ) {
			$file = $settings['file']['url'];
		}

		$atts = array(
			'file'     => esc_url_raw( $file ),
			'width'    => isset( $settings['width'] ) ? sanitize_text_field( (string) $settings['width'] ) : '100%',
			'height'   => isset( $settings['height'] ) ? sanitize_text_field( (string) $settings['height'] ) : '600px',
			'page'     => isset( $settings['page'] ) ? absint( $settings['page'] ) : 1,
			'title'    => isset( $settings['title'] ) ? sanitize_text_field( (string) $settings['title'] ) : '',
			'download' => ( ! isset( $settings['download'] ) || 'yes' === $settings['download'] ) ? 'true' : 'false',
			'print'    => ( ! isset( $settings['print'] ) || 'yes' === $settings['print'] ) ? 'true' : 'false',
		);

		echo wp_kses( Flipset::instance()->viewer->render_shortcode( $atts ), Flipset_Viewer::allowed_html() );
	}
}
