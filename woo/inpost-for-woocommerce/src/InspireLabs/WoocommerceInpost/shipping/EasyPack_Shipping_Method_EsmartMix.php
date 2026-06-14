<?php

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

if ( ! class_exists( 'EasyPack_Shipping_Method_EsmartMix' ) ) {
	class EasyPack_Shipping_Method_EsmartMix extends EasyPack_Shipping_Method_Courier {

		const WP_AJAX_ACTION_CREATE = 'esmartmix_create_package';

		const SERVICE_ID = ShipX_Shipment_Model::SERVICE_INPOST_COURIER_ESMARTMIX;

		const NONCE_ACTION = self::SERVICE_ID;

		const SHIPPING_METHOD_ID = 'easypack_shipping_esmartmix';

		public function get_method_title(): string {
			return __( 'InPost SmartCourier', 'inpost-for-woocommerce' );
		}

		public function get_method_description(): string {
			return __( 'InPost SmartCourier', 'inpost-for-woocommerce' );
		}

		protected function get_settings_default_title(): string {
			return __( 'InPost SmartCourier', 'inpost-for-woocommerce' );
		}

		protected static function get_order_metabox_template(): string {
			return 'views/html-order-metabox-courier-esmartmix.php';
		}

		protected static function get_send_methods_for_order_metabox(): array {
			if ( EasyPack_API()->getCountry() === EasyPack_API::COUNTRY_PL ) {
				return array(
					'courier' => __( 'Courier', 'inpost-for-woocommerce' ),
				);
			}

			return array();
		}

		public function init_form_fields() {
			parent::init_form_fields();
			unset( $this->form_fields['sms'], $this->form_fields['email'] );
			unset( $this->instance_form_fields['sms'], $this->instance_form_fields['email'] );
		}

		/**
		 * @param bool $courier
		 *
		 * @throws ReflectionException
		 */
		public static function ajax_create_package( $courier = false ) {
			$ret = array( 'status' => 'ok' );

			$shipment_model = static::ajax_create_shipment_model();

			$order_id         = $shipment_model->getInternalData()->getOrderId();
			$shipment_service = EasyPack::EasyPack()->get_shipment_service();
			$shipment_array   = $shipment_service->shipment_to_array( $shipment_model );
			$status_service   = EasyPack::EasyPack()->get_shipment_status_service();

			$shipment_data = array();

			unset( $shipment_array['sender'] );
			unset( $shipment_array['receiver']['address']['id'] );
			unset( $shipment_array['parcels'][0]['template'] );
			unset( $shipment_array['parcels'][0]['tracking_number'] );
			unset( $shipment_array['parcels'][0]['is_non_standard'] );
			unset( $shipment_array['custom_attributes'] );
			unset( $shipment_array['cod'] );
			unset( $shipment_array['insurance'] );
			unset( $shipment_array['isReturn'] );
			unset( $shipment_array['additional_services'] );
			unset( $shipment_array['only_choice_of_offer'] );
			unset( $shipment_array['internal_data'] );
			unset( $shipment_array['commercial_product_identifier'] );

			try {

				$response = EasyPack_API()->customer_parcel_create( $shipment_array );

				$shipment_data = static::save_to_order_meta(
					$order_id,
					$shipment_model,
					$shipment_service,
					$status_service,
					$shipment_array,
					$response
				);

				if ( isset( $response['service'] ) && $response['service'] === 'unavailable' ) {
					$ret['status']  = 'error';
					$ret['message'] = __( 'There are some errors. Please fix it: <br>', 'inpost-for-woocommerce' )
						. __( 'The organization does not have an available service on its account', 'inpost-for-woocommerce' );
				}

				if ( isset( $response['selected_offer']['carrier'] )
					&& $response['selected_offer']['carrier'] === 'carrier_inpost_courier_unavailable'
				) {
					$ret['status']  = 'error';
					$ret['message'] = __( 'There are some errors. Please fix it: <br>', 'inpost-for-woocommerce' )
						. __( 'The organization does not have courier services available', 'inpost-for-woocommerce' );
				}

				if ( isset( $response['parcels']['dimensions'] )
					&& $response['parcels']['dimensions'] === 'invalid'
				) {
					$ret['status']  = 'error';
					$ret['message'] = __( 'There are some errors. Please fix it: <br>', 'inpost-for-woocommerce' )
						. __( 'Providing incorrect dimensions/exceeding the permissible dimensions', 'inpost-for-woocommerce' );
				}

				if ( isset( $response['parcels']['weight'] )
					&& $response['parcels']['weight'] === 'invalid'
				) {
					$ret['status']  = 'error';
					$ret['message'] = __( 'There are some errors. Please fix it: <br>', 'inpost-for-woocommerce' )
						. __( 'Exceeding the maximum weight', 'inpost-for-woocommerce' );
				}

				if ( isset( $response['transactions']['details'] )
					&& $response['transactions']['details'] !== null
				) {
					$ret['message'] = __( 'There are some transaction details: ', 'inpost-for-woocommerce' )
						. esc_html( $response['transactions']['details'] );
				}
			} catch ( Exception $e ) {
				$ret['status']  = 'error';
				$ret['message'] = __( 'There are some errors. Please fix it: <br>', 'inpost-for-woocommerce' ) . EasyPack_API()->translate_error( $e->getMessage() );
			}

			if ( $ret['status'] == 'ok' ) {
				$order = wc_get_order( $order_id );

				$order->add_order_note(
					__( 'Shipment created', 'inpost-for-woocommerce' ),
					false
				);

				EasyPack_Helper()->set_order_status_completed( $order_id );

				if ( isset( $_POST['action'] ) && $_POST['action'] === 'easypack_bulk_create_shipments' ) {
					if ( isset( $shipment_data['tracking'] ) && ! empty( $shipment_data['tracking'] ) ) {
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
			echo json_encode( $ret );
			wp_die();
		}
	}
}
