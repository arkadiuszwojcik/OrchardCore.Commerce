<?php

namespace InspireLabs\WoocommerceInpost\shipping;

use InspireLabs\WoocommerceInpost\shipx\models\shipment\ShipX_Shipment_Model;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

if ( ! class_exists( 'EasyPack_Shipping_Method_Courier_Local_Standard_COD' ) ) {
	class EasyPack_Shipping_Method_Courier_Local_Standard_COD extends EasyPack_Shipping_Method_Courier_COD {

		const WP_AJAX_ACTION_CREATE = 'courier_local_standard_cod_create_package';

		const NONCE_ACTION = self::SERVICE_ID;

		const SERVICE_ID = ShipX_Shipment_Model::SERVICE_INPOST_COURIER_LOCAL_STANDARD;

		const SHIPPING_METHOD_ID = 'easypack_shipping_courier_local_standard_cod';

		public function get_method_title(): string {
			return __( 'InPost Courier Local Standard COD', 'inpost-for-woocommerce' );
		}

		public function get_method_description(): string {
			return esc_html__( 'InPost Courier Local Standard COD', 'inpost-for-woocommerce' );
		}
	}
}
