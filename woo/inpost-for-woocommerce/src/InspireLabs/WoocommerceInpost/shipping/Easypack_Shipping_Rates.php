<?php

namespace InspireLabs\WoocommerceInpost\shipping;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

use InspireLabs\WoocommerceInpost\EasyPack_Helper;
use WC_Shipping_Method;
use WC_Shipping_Rate;

/**
 * Class Easypack_Shipping_Rates
 *
 * Handles display of shipping method logos and delivery terms in WooCommerce checkout.
 */
class Easypack_Shipping_Rates {

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'woocommerce_after_shipping_rate', array( $this, 'display_shipping_method_logo' ), 10, 2 );
	}

	/**
	 * Display InPost shipping method logo and delivery terms.
	 *
	 * @param WC_Shipping_Rate $method Shipping method object.
	 * @param int              $index  Shipping method index.
	 *
	 * @return void
	 */
	public function display_shipping_method_logo( $method, $index ) {

		// Early return if required helpers are not available.
		if ( ! function_exists( 'EasyPack_Helper' ) || ! function_exists( 'EasyPack' ) ) {
			return;
		}

		$method_id      = $method->get_method_id();
		$fs_method_name = null;

		// Check for Flexible Shipping integration.
		if ( EasyPack_Helper()->is_flexible_shipping_activated() ) {
			if ( false !== strpos( $method_id, 'flexible_shipping_single' ) ) {
				$fs_method_name = EasyPack_Helper()->get_method_linked_to_fs_by_instance_id(
					$method->get_instance_id()
				);
			}
		}

		// Check if this is an EasyPack shipping method.
		if ( ! $this->is_easypack_method( $method_id, $fs_method_name ) ) {
			return;
		}

		// Get method metadata.
		$meta           = $method->get_meta_data();
		$custom_logo    = isset( $meta['logo'] ) ? $meta['logo'] : '';
		$delivery_terms = isset( $meta['delivery_terms'] ) ? $meta['delivery_terms'] : '';

		// Get logo HTML.
		$logo_html = $this->get_logo_html( $method_id, $fs_method_name, $custom_logo );

		// Build output.
		$output  = '<span class="inpost_pl-shipping-method-meta-wrap">';
		$output .= $logo_html;

		if ( ! empty( $delivery_terms ) ) {
			$output .= sprintf(
				'<span class="inpost_pl_shipping_meta" id="inpost_pl_delivery_terms">%s</span>',
				esc_html( $delivery_terms )
			);
		}

		$output .= '</span>';

		echo wp_kses_post( $output );
	}

	/**
	 * Check if shipping method is an EasyPack method.
	 *
	 * @param string      $method_id      Method ID.
	 * @param string|null $fs_method_name Flexible Shipping method name.
	 *
	 * @return bool True if EasyPack method, false otherwise.
	 */
	private function is_easypack_method( $method_id, $fs_method_name ) {
		if ( 0 === strpos( $method_id, 'easypack_' ) ) {
			return true;
		}

		if ( null !== $fs_method_name && 0 === strpos( $fs_method_name, 'easypack_' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Get logo HTML for shipping method.
	 *
	 * @param string      $method_id      Method ID.
	 * @param string|null $fs_method_name Flexible Shipping method name.
	 * @param string      $custom_logo    Custom logo URL.
	 *
	 * @return string Logo HTML markup.
	 */
	private function get_logo_html( $method_id, $fs_method_name, $custom_logo ) {

		// Use custom logo if provided.
		if ( ! empty( $custom_logo ) ) {
			return sprintf(
				'<span class="inpost_pl_shipping_meta easypack-custom-shipping-method-logo"><img src="%s" alt="InPost" /></span>',
				esc_url( $custom_logo )
			);
		}

		// Get validated method name.
		$method_name = EasyPack_Helper()->validate_method_name( $method_id );

		// Determine logo file and CSS class.
		$logo_file = $this->determine_logo_file( $method_name, $fs_method_name );
		$css_class = $this->get_logo_css_class( $method_name, $fs_method_name );

		return sprintf(
			'<span class="inpost_pl_shipping_meta %s"><img src="%s" alt="InPost" /></span>',
			esc_attr( $css_class ),
			esc_url( EasyPack()->getPluginImages() . 'logo/' . $logo_file )
		);
	}

	/**
	 * Determine logo filename based on shipping method.
	 *
	 * @param string      $method_name    Method name.
	 * @param string|null $fs_method_name Flexible Shipping method name.
	 *
	 * @return string Logo filename.
	 */
	private function determine_logo_file( $method_name, $fs_method_name ) {

		$weekend_methods = array(
			'easypack_parcel_machines_weekend',
			'easypack_parcel_machines_weekend_cod',
		);

		$locker_methods = array(
			'easypack_parcel_machines',
			'easypack_parcel_machines_cod',
		);

		// Weekend delivery logo.
		if ( in_array( $method_name, $weekend_methods, true )
			|| in_array( $fs_method_name, $weekend_methods, true ) ) {
			return 'inpost-paczka-w-weekend.png';
		}

		// Locker delivery logo.
		if ( in_array( $method_name, $locker_methods, true )
			|| in_array( $fs_method_name, $locker_methods, true ) ) {
			return 'inpost-paczkomat-logo.png';
		}

		// Courier delivery logo (default).
		return 'inpost-kurier-logo.png';
	}

	/**
	 * Get CSS class for logo based on shipping method.
	 *
	 * @param string      $method_name    Method name.
	 * @param string|null $fs_method_name Flexible Shipping method name.
	 *
	 * @return string CSS class name.
	 */
	private function get_logo_css_class( $method_name, $fs_method_name ) {

		$weekend_methods = array(
			'easypack_parcel_machines_weekend',
			'easypack_parcel_machines_weekend_cod',
		);

		if ( in_array( $method_name, $weekend_methods, true )
			|| in_array( $fs_method_name, $weekend_methods, true ) ) {
			return 'easypack-weekend-shipping-method-logo';
		}

		return 'easypack-shipping-method-logo';
	}
}
