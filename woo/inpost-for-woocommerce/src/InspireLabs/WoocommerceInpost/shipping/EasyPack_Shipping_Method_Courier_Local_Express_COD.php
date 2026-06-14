<?php

namespace InspireLabs\WoocommerceInpost\shipping;

use InspireLabs\WoocommerceInpost\shipx\models\shipment\ShipX_Shipment_Model;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

if ( ! class_exists( 'EasyPack_Shipping_Method_Courier_Local_Express_COD' ) ) {
	class EasyPack_Shipping_Method_Courier_Local_Express_COD extends EasyPack_Shipping_Method_Courier_COD {

		const WP_AJAX_ACTION_CREATE = 'courier_local_express_cod_create_package';

		const NONCE_ACTION = self::SERVICE_ID;

		const SERVICE_ID = ShipX_Shipment_Model::SERVICE_INPOST_COURIER_LOCAL_EXPRESS;

		const SHIPPING_METHOD_ID = 'easypack_shipping_courier_le_cod';

		public function get_method_title(): string {
			return __( 'InPost Courier Local Express COD', 'inpost-for-woocommerce' );
		}

		public function get_method_description(): string {
			return esc_html__( 'InPost Courier Local Express COD', 'inpost-for-woocommerce' );
		}
	}
}
