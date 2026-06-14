<?php
/**
 * Paczka w Weekend COD
 */

namespace InspireLabs\WoocommerceInpost\shipping;

use Exception;
use InspireLabs\WoocommerceInpost\EasyPack;
use InspireLabs\WoocommerceInpost\EasyPack_API;
use InspireLabs\WoocommerceInpost\EasyPack_Helper;
use InspireLabs\WoocommerceInpost\shipx\models\shipment\ShipX_Shipment_Model;
use ReflectionException;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

if ( ! class_exists( 'EasyPack_Shipping_Parcel_Machines_Weekend_COD' ) ) {
	class EasyPack_Shipping_Parcel_Machines_Weekend_COD extends EasyPack_Shipping_Parcel_Machines_Weekend {

		const SERVICE_ID = ShipX_Shipment_Model::SERVICE_INPOST_LOCKER_WEEKEND;

		const NONCE_ACTION = self::SERVICE_ID;

		const SHIPPING_METHOD_ID = 'easypack_parcel_machines_weekend_cod';

		public function get_method_title(): string {
			return esc_html__( 'InPost Locker Weekend COD', 'inpost-for-woocommerce' );
		}

		public function get_method_description(): string {
			return esc_html__( 'Allow customers to pick up orders in weekend.', 'inpost-for-woocommerce' );
		}

		protected function get_settings_default_title(): string {
			return esc_html__( 'InPost Locker Weekend COD', 'inpost-for-woocommerce' );
		}

		protected static function get_order_metabox_template(): string {
			return 'views/html-order-matabox-parcel-machines-weekend-cod.php';
		}

		protected static function get_geowidget_method_id(): string {
			return 'easypack_parcel_machines_weekend_cod';
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

				$parcel_machine_id = Easypack_Helper()->get_woo_order_meta( $order_id, '_parcel_machine_id' );

				$cod_amount = $parcels[0]['cod_amount'] ?? $order_amount;

				$insurance_amount = EasyPack_Helper()->get_insurance_amount( $order_id );

				$reference_number = EasyPack_Helper()->get_maybe_custom_reference_number( $order_id );

				if ( 'yes' === get_option( 'easypack_add_order_note' ) ) {
					$order_note       = $order->get_customer_note();
					$reference_number = $reference_number . ' ' . $order_note;
				}

				$send_method = EasyPack_Helper()->get_default_send_method( $order_id );

			} else {

				$parcel_machine_id = isset( $_POST['parcel_machine_id'] )
					? sanitize_text_field( wp_unslash( $_POST['parcel_machine_id'] ) ) : '';

				if ( isset( $_POST['insurance_amounts'] ) && is_array( $_POST['insurance_amounts'] ) ) {
					$insurance_amounts = array_map( 'sanitize_text_field', $_POST['insurance_amounts'] );

					if ( isset( $insurance_amounts[0] ) && is_numeric( $insurance_amounts[0] ) && floatval( $insurance_amounts[0] ) > 0 ) {
						$insurance_amount = $insurance_amounts[0];
					}
				}

				$cod_amounts = isset( $_POST['cod_amounts'] )
					? array_map( 'sanitize_text_field', $_POST['cod_amounts'] )
					: null;
				$cod_amount  = isset( $cod_amounts[0] ) ? $cod_amounts[0] : $order_amount;

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
				null
			);

			$shipment->getInternalData()->setOrderId( $order_id );

			return $shipment;
		}

		/**
		 * Ajax create package Weekend COD
		 *
		 * @param bool $courier $courier.
		 *
		 * @throws ReflectionException ReflectionException.
		 */
		public static function ajax_create_package( $courier = false ) {
			$ret = array( 'status' => 'ok' );

			$shipment_model = static::ajax_create_shipment_model();

			$order_id         = $shipment_model->getInternalData()->getOrderId();
			$shipment_service = EasyPack::EasyPack()->get_shipment_service();
			$shipment_array   = $shipment_service->shipment_to_array( $shipment_model );
			$status_service   = EasyPack::EasyPack()->get_shipment_status_service();

			// required parameter for Paczka w Weekend.
			$shipment_array['end_of_week_collection'] = true;

			$shipment_data = array();

			\wc_get_logger()->debug( 'PWW COD TO API: ', array( 'source' => 'pww-cod-log' ) );
			\wc_get_logger()->debug( print_r( $shipment_array, true ), array( 'source' => 'pww-cod-log' ) );

			try {

				$response = EasyPack_API()->customer_parcel_create( $shipment_array );

				\wc_get_logger()->debug( 'PWW COD API RESP: ', array( 'source' => 'pww-cod-log' ) );
				\wc_get_logger()->debug( print_r( $response, true ), array( 'source' => 'pww-cod-log' ) );

				$shipment_data = static::save_to_order_meta(
					$order_id,
					$shipment_model,
					$shipment_service,
					$status_service,
					$shipment_array,
					$response
				);

			} catch ( Exception $e ) {
				$ret['status']  = 'error';
				$ret['message'] = esc_html__( 'There are some errors. Please fix it: <br>', 'inpost-for-woocommerce' ) . EasyPack_API()->translate_error( $e->getMessage() );
			}

			if ( 'ok' === $ret['status'] ) {
				$order = wc_get_order( $order_id );

				$order->add_order_note(
					esc_html__( 'Shipment created', 'inpost-for-woocommerce' ),
					false
				);

				EasyPack_Helper()->set_order_status_completed( $order_id );

				if ( isset( $_POST['action'] ) && 'easypack_bulk_create_shipments' === $_POST['action'] ) {
					if ( ! empty( $shipment_data['tracking'] ) ) {
						$ret['tracking_number'] = $shipment_data['tracking'];
					} else {
						$ret['api_status'] = $status_service->getStatusDescription( $response['status'] );
					}
				} else {
					$ret['content'] = static::order_metabox_content( get_post( $order_id ), false, $shipment_model );
					if ( isset( $shipment_data['tracking'] ) && ! empty( $shipment_data['tracking'] ) ) {
						$ret['tracking_number'] = $shipment_data['tracking'];
						$ret['inpost_id']       = $shipment_data['inpost_id'];
					}
					$ret['api_status'] = $shipment_data['status'];
					$ret['ref_number'] = $shipment_array['reference'];
					$ret['service']    = $shipment_data['service'];
				}

				if ( 'yes' === get_option( 'easypack_delivery_notice' ) ) {
					wp_schedule_single_event(
						time() + 60,
						'send_tracking_numbers_email',
						array( $order_id )
					);
				}
			}
			echo wp_json_encode( $ret );
			wp_die();
		}
	}
}
