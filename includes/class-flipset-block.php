<?php
/**
 * Flipset_Block
 *
 * Registers a dynamic Gutenberg block that wraps the [flipset] shortcode,
 * so editors get a Media Library picker, height slider, and a first-page
 * PDF preview instead of typing shortcode attributes by hand.
 *
 * Rendering stays server-side via Flipset_Viewer so block and shortcode
 * output cannot drift apart.
 *
 * @package Flipset
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Flipset_Block {

	public function init() {
		add_action( 'init', array( $this, 'register_block' ) );
	}

	public function register_block() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		Flipset_Viewer::register_pdfjs();

		wp_register_script(
			'flipset-block-editor',
			FLIPSET_URL . 'assets/js/flipset-block.js',
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'flipset-pdfjs' ),
			FLIPSET_VERSION,
			true
		);

		wp_register_style(
			'flipset-block-editor',
			FLIPSET_URL . 'assets/css/flipset-block-editor.css',
			array(),
			FLIPSET_VERSION
		);

		// Editor strings come from WordPress.org language packs (no bundled JSON).
		wp_set_script_translations( 'flipset-block-editor', 'flipset' );

		wp_localize_script(
			'flipset-block-editor',
			'FlipsetBlock',
			array(
				'workerSrc' => FLIPSET_URL . 'assets/vendor/pdfjs/pdf.worker.min.js',
			)
		);

		wp_register_style(
			'flipset-library',
			FLIPSET_URL . 'assets/css/flipset-library.css',
			array(),
			FLIPSET_VERSION
		);

		wp_register_script(
			'flipset-library-block-editor',
			FLIPSET_URL . 'assets/js/flipset-library-block.js',
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-server-side-render' ),
			FLIPSET_VERSION,
			true
		);

		wp_set_script_translations( 'flipset-library-block-editor', 'flipset' );

		register_block_type(
			FLIPSET_DIR . 'blocks/viewer',
			array(
				'render_callback' => array( $this, 'render_block' ),
			)
		);

		register_block_type(
			FLIPSET_DIR . 'blocks/library',
			array(
				'render_callback' => array( $this, 'render_library_block' ),
			)
		);

		$this->register_block_patterns();
	}

	/**
	 * Register curated block patterns for document layouts.
	 */
	public function register_block_patterns() {
		if ( ! function_exists( 'register_block_pattern_category' ) || ! function_exists( 'register_block_pattern' ) ) {
			return;
		}

		register_block_pattern_category(
			'flipset',
			array(
				'label' => __( 'Flipset Documents', 'flipset' ),
			)
		);

		register_block_pattern(
			'flipset/lead-magnet',
			array(
				'title'       => __( 'Whitepaper / Lead Magnet', 'flipset' ),
				'description' => __( 'Two-column document showcase with title, summary, and PDF viewer.', 'flipset' ),
				'categories'  => array( 'flipset', 'featured' ),
				'content'     => '<!-- wp:columns {"verticalAlignment":"center"} -->
<div class="wp-block-columns are-vertically-aligned-center"><!-- wp:column {"verticalAlignment":"center","width":"40%"} -->
<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:40%"><!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading">' . esc_html__( 'Free Research Report', 'flipset' ) . '</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>' . esc_html__( 'Explore key insights, comprehensive findings, and actionable recommendations in our latest publication.', 'flipset' ) . '</p>
<!-- /wp:paragraph -->

<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button {"className":"is-style-fill"} -->
<div class="wp-block-button is-style-fill"><a class="wp-block-button__link wp-element-button">' . esc_html__( 'Download PDF', 'flipset' ) . '</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"center","width":"60%"} -->
<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:60%"><!-- wp:flipset/viewer {"height":"480px"} /--></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->',
			)
		);

		register_block_pattern(
			'flipset/research-paper',
			array(
				'title'       => __( 'Academic / Research Document', 'flipset' ),
				'description' => __( 'Formal document layout with metadata, abstract callout, and full-width PDF viewer.', 'flipset' ),
				'categories'  => array( 'flipset' ),
				'content'     => '<!-- wp:group {"layout":{"type":"constrained"}} -->
<div class="wp-block-group"><!-- wp:heading {"textAlign":"center","level":1} -->
<h1 class="wp-block-heading has-text-align-center">' . esc_html__( 'Document Title & Research Paper', 'flipset' ) . '</h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center","style":{"typography":{"fontStyle":"italic"}}} -->
<p class="has-text-align-center" style="font-style:italic">' . esc_html__( 'Published by Author / Institution — Last Updated: 2026', 'flipset' ) . '</p>
<!-- /wp:paragraph -->

<!-- wp:quote {"className":"is-style-plain"} -->
<blockquote class="wp-block-quote is-style-plain"><p><strong>' . esc_html__( 'Abstract:', 'flipset' ) . '</strong> ' . esc_html__( 'This publication examines core methodologies, experimental data, and concluding outcomes.', 'flipset' ) . '</p></blockquote>
<!-- /wp:quote -->

<!-- wp:flipset/viewer {"width":"100%","height":"650px"} /--></div>
<!-- /wp:group -->',
			)
		);

		register_block_pattern(
			'flipset/annual-report',
			array(
				'title'       => __( 'Annual Report / Catalog Hero', 'flipset' ),
				'description' => __( 'Wide annual report showcase with key metrics and wide-format PDF reader.', 'flipset' ),
				'categories'  => array( 'flipset' ),
				'content'     => '<!-- wp:group {"align":"wide","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide"><!-- wp:heading {"textAlign":"center"} -->
<h2 class="wp-block-heading has-text-align-center">' . esc_html__( 'Annual Report & Highlights', 'flipset' ) . '</h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center"} -->
<p class="has-text-align-center">' . esc_html__( 'Browse through our full annual review, financial statistics, and milestones.', 'flipset' ) . '</p>
<!-- /wp:paragraph -->

<!-- wp:flipset/viewer {"width":"100%","height":"600px"} /--></div>
<!-- /wp:group -->',
			)
		);
	}

	/**
	 * Delegates to the same shortcode renderer used by [flipset].
	 *
	 * @param array    $attributes Block attributes.
	 * @param string   $content    Block inner content (unused).
	 * @param WP_Block $block      Block instance when available.
	 * @return string
	 */
	public function render_block( $attributes, $content = '', $block = null ) {
		unset( $content ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed

		// block.json gives every attribute a default, so $attributes always holds
		// file="", width="100%", etc. For a saved embed those defaults would
		// overwrite the stored config. Pass only what the author actually set.
		if ( ! empty( $attributes['id'] ) && $block instanceof WP_Block
			&& isset( $block->parsed_block['attrs'] ) && is_array( $block->parsed_block['attrs'] ) ) {
			$attributes = $block->parsed_block['attrs'];
		}

		$html = Flipset::instance()->viewer->render_shortcode( $attributes );
		if ( function_exists( 'get_block_wrapper_attributes' ) ) {
			$html = '<div ' . get_block_wrapper_attributes( array( 'class' => 'flipset-block' ) ) . '>' . $html . '</div>';
		}
		return wp_kses( $html, Flipset_Viewer::allowed_html() );
	}

	/**
	 * Delegates to the same shortcode renderer used by [flipset_library].
	 *
	 * @param array    $attributes Block attributes.
	 * @param string   $content    Block inner content (unused).
	 * @param WP_Block $block      Block instance when available.
	 * @return string
	 */
	public function render_library_block( $attributes, $content = '', $block = null ) {
		unset( $content, $block ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$html = Flipset::instance()->library->render_shortcode( $attributes );
		if ( function_exists( 'get_block_wrapper_attributes' ) ) {
			$html = '<div ' . get_block_wrapper_attributes( array( 'class' => 'flipset-library-block' ) ) . '>' . $html . '</div>';
		}
		return wp_kses( $html, Flipset_Viewer::allowed_html() );
	}
}
