<?php

namespace InspireLabs\WoocommerceInpost\shipping;

use InspireLabs\WoocommerceInpost\EasyPack;
use InspireLabs\WoocommerceInpost\EasyPack_Helper;
use InspireLabs\WoocommerceInpost\shipx\models\shipment\ShipX_Shipment_Model;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

if ( ! class_exists( 'EasyPack_Shipping_Parcel_Machines_Economy_COD' ) ) {
	class EasyPack_Shipping_Parcel_Machines_Economy_COD extends EasyPack_Shipping_Parcel_Machines_Economy {

		const WP_AJAX_ACTION_CREATE = 'parcel_machines_economy_cod';

		const SERVICE_ID = ShipX_Shipment_Model::SERVICE_INPOST_LOCKER_ECONOMY;

		const NONCE_ACTION = self::SERVICE_ID;

		const SHIPPING_METHOD_ID = 'easypack_parcel_machines_economy_cod';

		public function get_method_title(): string {
			return __( 'InPost Locker Economy COD', 'inpost-for-woocommerce' );
		}

		public function get_method_description(): string {
			return __( 'InPost Locker Economy COD', 'inpost-for-woocommerce' );
		}

		protected function get_settings_default_title(): string {
			return __( 'InPost Locker Economy COD', 'inpost-for-woocommerce' );
		}

		protected static function get_order_metabox_template(): string {
			return 'views/html-order-matabox-parcel-machines-economy-cod.php';
		}

		protected static function get_geowidget_method_id(): string {
			return 'easypack_parcel_machines_economy_cod';
		}

		public static function ajax_create_shipment_model() {

			$order_id = (int) sanitize_text_field( wp_unslash( $_POST['order_id'] ) );

			$order = wc_get_order( $order_id );
			if ( ! $order || is_wp_error( $order ) || ! is_object( $order ) ) {
				return null;
			}

			$shipmentService = EasyPack::EasyPack()->get_shipment_service();

			$order_amount = $order->get_total();

			$insurance_amount = '';
			$reference_number = '';
			$send_method      = '';
			$parcels          = array();

			// if Bulk create shipments.
			if ( isset( $_POST['action'] ) && 'easypack_bulk_create_shipments' === $_POST['action'] ) {

				$parcels = array();

				if ( 'easypack_bulk_create_shipments_A' === $_POST['locker_size'] ) {
					$parcels = array( 'small' );
				} elseif ( 'easypack_bulk_create_shipments_B' === $_POST['locker_size'] ) {
					$parcels = array( 'medium' );
				} elseif ( 'easypack_bulk_create_shipments_C' === $_POST['locker_size'] ) {
					$parcels = array( 'large' );
				} else {

					$parcels = Easypack_Helper()->get_woo_order_meta( $order_id, '_easypack_parcels' );
					$parcels = ! empty( $parcels ) ? $parcels : array( Easypack_Helper()->get_parcel_size_from_settings( $order_id ) );
				}

				$commercial_product_identifier = static::$instance->get_option( 'commercial_product_identifier' );

				$insurance_amount = EasyPack_Helper()->get_insurance_amount( $order_id );

				$cod_amount = isset( $parcels[0]['cod_amount'] )
					? $parcels[0]['cod_amount']
					: $order_amount;

				$reference_number = EasyPack_Helper()->get_maybe_custom_reference_number( $order_id );

				if ( 'yes' === get_option( 'easypack_add_order_note' ) ) {
					$order_note       = $order->get_customer_note();
					$reference_number = $reference_number . ' ' . $order_note;
				}

				$send_method = EasyPack_Helper()->get_default_send_method( $order_id );

				$parcel_machine_id = Easypack_Helper()->get_woo_order_meta( $order_id, '_parcel_machine_id' );

			} else {

				$commercial_product_identifier = isset( $_POST['commercial_product_identifier'] )
					? sanitize_text_field( $_POST['commercial_product_identifier'] )
					: '';

				$parcel_machine_id = isset( $_POST['parcel_machine_id'] )
					? sanitize_text_field( $_POST['parcel_machine_id'] ) : '';

				$cod_amounts = isset( $_POST['cod_amounts'] )
					? array_map( 'sanitize_text_field', $_POST['cod_amounts'] )
					: null;
				$cod_amount  = isset( $cod_amounts[0] ) ? $cod_amounts[0] : $order_amount;

				if ( isset( $_POST['insurance_amounts'] ) && is_array( $_POST['insurance_amounts'] ) ) {
					$insurance_amounts = array_map( 'sanitize_text_field', $_POST['insurance_amounts'] );

					if ( isset( $insurance_amounts[0] ) && is_numeric( $insurance_amounts[0] ) && floatval( $insurance_amounts[0] ) > 0 ) {
						$insurance_amount = $insurance_amounts[0];
					}
				}

				$send_method = isset( $_POST['send_method'] )
					? sanitize_text_field( $_POST['send_method'] )
					: 'parcel_machine';

				$reference_number = isset( $_POST['reference_number'] )
					? sanitize_text_field( $_POST['reference_number'] )
					: $order_id;

				$parcels = isset( $_POST['parcels'] )
					? array_map( 'sanitize_text_field', $_POST['parcels'] )
					: array( get_option( 'easypack_default_package_size' ) );
			}

			$shipment = $shipmentService->create_shipment_object_by_shiping_data(
				$parcels,
				$order_id,
				$send_method,
				static::SERVICE_ID,
				array(),
				$parcel_machine_id,
				$cod_amount,
				$insurance_amount,
				$reference_number,
				$commercial_product_identifier
			);

			$shipment->getInternalData()->setOrderId( $order_id );

			return $shipment;
		}
	}
}
