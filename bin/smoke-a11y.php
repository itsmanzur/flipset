<?php
/**
 * Smoke-check Documents a11y column as user 1. Run via WP-CLI eval-file.
 */
wp_set_current_user( 1 );
if ( ! class_exists( 'Flipset' ) ) {
	echo "NO_FLIPSET\n";
	exit( 1 );
}

ob_start();
Flipset::instance()->admin->render_documents_page();
$html = ob_get_clean();

echo ( false !== strpos( $html, 'flipset-a11y-status' ) ? 'HAS_A11Y' : 'NO_A11Y' ) . PHP_EOL;
echo ( false !== strpos( $html, 'Accessibility' ) ? 'HAS_COL' : 'NO_COL' ) . PHP_EOL;
echo ( false !== strpos( $html, 'data-needs-a11y' ) ? 'HAS_NEEDS' : 'NO_NEEDS' ) . PHP_EOL;

unload_textdomain( 'flipset' );
load_textdomain( 'flipset', WP_PLUGIN_DIR . '/flipset/translations-src/flipset-bn_BD.mo' );
echo 'BN_DOCUMENTS=' . __( 'Documents', 'flipset' ) . PHP_EOL;
echo 'BN_UNTAGGED=' . __( 'Untagged', 'flipset' ) . PHP_EOL;
