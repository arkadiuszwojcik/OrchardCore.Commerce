<?php

namespace InspireLabs\WoocommerceInpost\shipping;

use InspireLabs\WoocommerceInpost\shipx\models\shipment\ShipX_Shipment_Model;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

if ( ! class_exists( 'EasyPack_Shipping_Method_Courier_Palette' ) ) {
	class EasyPack_Shipping_Method_Courier_Palette extends EasyPack_Shipping_Method_Courier {

		const WP_AJAX_ACTION_CREATE = 'courier_palette_create_package';

		const SERVICE_ID = ShipX_Shipment_Model::SERVICE_INPOST_COURIER_PALETTE;

		const NONCE_ACTION = self::SERVICE_ID;

		const SHIPPING_METHOD_ID = 'easypack_shipping_courier_palette';

		public function get_method_title(): string {
			return __( 'InPost Courier Palette', 'inpost-for-woocommerce' );
		}

		public function get_method_description(): string {
			return esc_html__( 'InPost Courier Palette', 'inpost-for-woocommerce' );
		}
	}
}
