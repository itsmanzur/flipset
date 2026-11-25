<?php
/**
 * Tests for Flipset_Readme (changelog parser used by the Welcome screen).
 *
 * @package Flipset
 */

/**
 * Flipset_Readme tests.
 */
class Test_Flipset_Readme extends WP_UnitTestCase {

	/**
	 * Sample readme with a section after the changelog.
	 *
	 * @var string
	 */
	private $sample = "=== Demo ===\nStable tag: 1.1.0\n\n== Description ==\n\n= Not a version =\n* Not a change\n\n== Changelog ==\n\n= 1.1.0 =\n* New: poster mode (`poster=\"true\"`).\n* Improved: faster first page.\n* Fixed: blank preview.\n* Security: refuse `javascript:` links.\n* Developer: new filter.\n* Plain line without a prefix.\n\n= 1.0.0 =\nInitial release.\n\n* Viewer.\n\n== Upgrade Notice ==\n\n= 1.1.0 =\nUpgrade.\n";

	/**
	 * Versions come out newest first and stop at the next `==` section.
	 */
	public function test_parses_versions_in_order() {
		$entries = Flipset_Readme::parse_changelog( $this->sample );
		$this->assertCount( 2, $entries );
		$this->assertSame( '1.1.0', $entries[0]['version'] );
		$this->assertSame( '1.0.0', $entries[1]['version'] );
		$this->assertSame( array( 'Initial release.' ), $entries[1]['notes'] );
		$this->assertCount( 1, $entries[1]['items'] );
	}

	/**
	 * Prefixes map to types; unknown lines keep their text and get no type.
	 */
	public function test_classifies_prefixes() {
		$entries = Flipset_Readme::parse_changelog( $this->sample );
		$types   = wp_list_pluck( $entries[0]['items'], 'type' );
		$this->assertSame( array( 'new', 'improved', 'fixed', 'security', 'developer', '' ), $types );
		$this->assertSame( 'Faster first page.', $entries[0]['items'][1]['text'] );
		$this->assertSame( 'Plain line without a prefix.', $entries[0]['items'][5]['text'] );
	}

	/**
	 * Backtick spans become <code>, everything else is escaped.
	 */
	public function test_inline_html_escapes_and_marks_code() {
		$this->assertSame( 'use <code>[flipset id=&quot;1&quot;]</code> &lt;b&gt;', Flipset_Readme::inline_html( 'use `[flipset id="1"]` <b>' ) );
		$this->assertSame( 'a `b', Flipset_Readme::inline_html( 'a `b' ) );
	}

	/**
	 * The shipped readme has a changelog whose first entry is the plugin's own version or newer.
	 */
	public function test_shipped_readme_has_changelog() {
		$entries = Flipset_Readme::changelog( FLIPSET_DIR . 'readme.txt', 3 );
		$this->assertNotEmpty( $entries );
		$this->assertLessThanOrEqual( 3, count( $entries ) );
		$this->assertTrue( version_compare( $entries[0]['version'], FLIPSET_VERSION, '>=' ) );
	}

	/**
	 * A missing file gives an empty list, not a warning.
	 */
	public function test_missing_file_is_empty() {
		$this->assertSame( array(), Flipset_Readme::changelog( FLIPSET_DIR . 'no-such-readme.txt' ) );
	}
}
