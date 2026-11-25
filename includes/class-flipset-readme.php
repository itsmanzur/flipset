<?php
/**
 * Reads the changelog out of a plugin's readme.txt.
 *
 * The Welcome screen shows "What's new" from the readme that ships with the
 * plugin, so the release notes are written once (in readme.txt) and never
 * copied into PHP. Only the local file is read; nothing is fetched.
 *
 * @package Flipset
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Small readme.txt changelog parser.
 */
class Flipset_Readme {

	/**
	 * Parsed results, keyed by file path, for the current request.
	 *
	 * @var array
	 */
	private static $cache = array();

	/**
	 * Changelog entries from a readme.txt file, newest first.
	 *
	 * @param string $file  Absolute path to readme.txt.
	 * @param int    $limit Maximum number of versions (0 = all).
	 * @return array[] Each: array( 'version' => '1.2.0', 'notes' => string[], 'items' => array( array( 'type' => 'new', 'text' => '...' ) ) ).
	 */
	public static function changelog( $file, $limit = 0 ) {
		$file = (string) $file;
		if ( ! isset( self::$cache[ $file ] ) ) {
			$text                 = ( '' !== $file && is_readable( $file ) ) ? (string) file_get_contents( $file ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local readme.txt shipped with the plugin.
			self::$cache[ $file ] = self::parse_changelog( $text );
		}
		$entries = self::$cache[ $file ];
		return $limit > 0 ? array_slice( $entries, 0, (int) $limit ) : $entries;
	}

	/**
	 * Parse the `== Changelog ==` section of readme.txt text.
	 *
	 * @param string $text Full readme.txt contents.
	 * @return array[]
	 */
	public static function parse_changelog( $text ) {
		$lines   = preg_split( '/\r\n|\r|\n/', (string) $text );
		$entries = array();
		$inside  = false;
		$current = null;

		foreach ( $lines as $line ) {
			$trim = trim( $line );

			if ( preg_match( '/^==\s*(.+?)\s*==$/', $trim, $m ) ) {
				if ( $inside ) {
					break; // The next `== Section ==` ends the changelog.
				}
				$inside = ( 0 === strcasecmp( $m[1], 'Changelog' ) );
				continue;
			}
			if ( ! $inside || '' === $trim ) {
				continue;
			}

			if ( preg_match( '/^=\s*(.+?)\s*=$/', $trim, $m ) ) {
				if ( null !== $current ) {
					$entries[] = $current;
				}
				$current = array(
					'version' => $m[1],
					'notes'   => array(),
					'items'   => array(),
				);
				continue;
			}
			if ( null === $current ) {
				continue;
			}

			if ( preg_match( '/^[*\-]\s+(.+)$/', $trim, $m ) ) {
				$current['items'][] = self::classify( $m[1] );
			} else {
				$current['notes'][] = $trim;
			}
		}
		if ( $inside && null !== $current ) {
			$entries[] = $current;
		}
		return $entries;
	}

	/**
	 * Split a changelog line into its type prefix ("New:", "Fixed:" ...) and text.
	 *
	 * @param string $line One bullet, without the leading "* ".
	 * @return array { type: string, text: string } Type is '' when the line has no known prefix.
	 */
	public static function classify( $line ) {
		$map = array(
			'new'         => 'new',
			'added'       => 'new',
			'add'         => 'new',
			'feature'     => 'new',
			'improved'    => 'improved',
			'improvement' => 'improved',
			'tweak'       => 'improved',
			'changed'     => 'improved',
			'fixed'       => 'fixed',
			'fix'         => 'fixed',
			'security'    => 'security',
			'developer'   => 'developer',
			'dev'         => 'developer',
		);
		if ( preg_match( '/^([A-Za-z]+):\s*(.+)$/', (string) $line, $m ) ) {
			$key = strtolower( $m[1] );
			if ( isset( $map[ $key ] ) ) {
				return array(
					'type' => $map[ $key ],
					// "New: hide a PDF" reads as "Hide a PDF" once the prefix is a tag.
					'text' => ucfirst( $m[2] ),
				);
			}
		}
		return array(
			'type' => '',
			'text' => (string) $line,
		);
	}

	/**
	 * Escape a readme line for HTML and turn `code` spans into <code>.
	 *
	 * @param string $text Raw readme text.
	 * @return string Safe HTML.
	 */
	public static function inline_html( $text ) {
		$parts = explode( '`', (string) $text );
		$last  = count( $parts ) - 1;
		$html  = '';
		foreach ( $parts as $i => $part ) {
			if ( 0 === $i % 2 ) {
				$html .= esc_html( $part );
			} elseif ( $i < $last ) {
				// Odd parts sit between a pair of backticks.
				$html .= '<code>' . esc_html( $part ) . '</code>';
			} else {
				// An unmatched final backtick stays plain text.
				$html .= esc_html( '`' . $part );
			}
		}
		return $html;
	}
}
