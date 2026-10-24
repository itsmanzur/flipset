<?php
/**
 * Trait_Flipset_Att_Flag
 *
 * Shared helper for resolving shortcode/block boolean-ish attribute values.
 * Used by Flipset_Viewer and Flipset_Library so the resolution logic lives
 * in exactly one place.
 *
 * Accepted truthy strings  : '1', 'true', 'yes', 'on'
 * Accepted falsy strings   : '0', 'false', 'no', 'off'
 * Empty string or null     : returns $default
 * Boolean passed directly  : returned as-is
 *
 * @package Flipset
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Trait Flipset_Att_Flag
 */
trait Flipset_Att_Flag {

	/**
	 * Resolve a shortcode/block attribute to a boolean.
	 *
	 * @param mixed $value   Attribute value from shortcode_atts() or block attrs.
	 * @param bool  $default Fallback when $value is empty or unrecognised.
	 * @return bool
	 */
	private function att_flag( $value, $default ) {
		if ( is_bool( $value ) ) {
			return $value;
		}
		if ( null === $value || '' === $value ) {
			return (bool) $default;
		}
		$v = strtolower( (string) $value );
		if ( in_array( $v, array( '0', 'false', 'no', 'off' ), true ) ) {
			return false;
		}
		if ( in_array( $v, array( '1', 'true', 'yes', 'on' ), true ) ) {
			return true;
		}
		return (bool) $default;
	}
}
