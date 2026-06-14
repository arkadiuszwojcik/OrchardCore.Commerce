<?php

namespace InspireLabs\WoocommerceInpost\shipping;

use InspireLabs\WoocommerceInpost\shipx\models\shipment\ShipX_Shipment_Model;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

if ( ! class_exists( 'EasyPack_Shipping_Method_Courier_LSE_COD' ) ) {
	class EasyPack_Shipping_Method_Courier_LSE_COD extends EasyPack_Shipping_Method_Courier_COD {

		const WP_AJAX_ACTION_CREATE = 'courier_lse_create_package_cod';

		const NONCE_ACTION = 'easypack_shipping_courier_cod';

		const SERVICE_ID = ShipX_Shipment_Model::SERVICE_INPOST_COURIER_LOCAL_SUPER_EXPRESS;

		const SHIPPING_METHOD_ID = 'easypack_shipping_courier_lse_cod';

		public function get_method_title(): string {
			return __( 'InPost Courier Local Super Express COD', 'inpost-for-woocommerce' );
		}

		public function get_method_description(): string {
			return esc_html__( 'InPost Courier Local Super Express COD', 'inpost-for-woocommerce' );
		}
	}
}
