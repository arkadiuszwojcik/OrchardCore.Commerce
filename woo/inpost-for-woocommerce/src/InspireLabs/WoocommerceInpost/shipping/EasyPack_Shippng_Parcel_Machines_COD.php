<?php

namespace InspireLabs\WoocommerceInpost\shipping;

use Exception;
use InspireLabs\WoocommerceInpost\EasyPack;
use InspireLabs\WoocommerceInpost\EasyPack_API;
use InspireLabs\WoocommerceInpost\EasyPack_Helper;
use InspireLabs\WoocommerceInpost\Geowidget_v5;
use InspireLabs\WoocommerceInpost\shipx\models\shipment\ShipX_Shipment_Model;
use ReflectionException;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

if ( ! class_exists( 'EasyPack_Shippng_Parcel_Machines_COD' ) ) {
	class EasyPack_Shippng_Parcel_Machines_COD extends EasyPack_Shippng_Parcel_Machines {

		const SERVICE_ID = ShipX_Shipment_Model::SERVICE_INPOST_LOCKER_STANDARD;

		const NONCE_ACTION = 'easypack_parcel_machines_cod';

		const SHIPPING_METHOD_ID = 'easypack_parcel_machines_cod';

		public function __construct( $instance_id = 0 ) {
			parent::__construct();

			$this->instance_id = absint( $instance_id );
			$this->supports    = array(
				'shipping-zones',
				'instance-settings',
			);

			$this->id                 = static::SHIPPING_METHOD_ID;
			$this->method_title       = __( 'InPost Locker 24/7 COD', 'inpost-for-woocommerce' );
			$this->method_description = esc_html__( 'InPost Parcel Locker COD. Allow customers to pick up orders themselves.', 'inpost-for-woocommerce' );
			$this->init();
		}

		public function init_form_fields() {
			parent::init_form_fields();
			$this->instance_form_fields['title']['default'] = __( 'InPost Locker 24/7 COD', 'inpost-for-woocommerce' );
			$this->form_fields['title']['default']          = __( 'InPost Locker 24/7 COD', 'inpost-for-woocommerce' );
		}

		public function process_admin_options() {
			parent::process_admin_options();
			EasyPack_API()->clear_cache();
		}

		public function order_metabox( $post ) {
			static::order_metabox_content( $post );
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

				$cod_amount = isset( $parcels[0]['cod_amount'] )
					? $parcels[0]['cod_amount']
					: $order_amount;

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

		public static function ajax_create_package( $courier = false ) {
			$ret = array( 'status' => 'ok' );

			$shipment_model   = static::ajax_create_shipment_model();
			$order_id         = $shipment_model->getInternalData()->getOrderId();
			$shipment_service = EasyPack::EasyPack()->get_shipment_service();
			$shipment_array   = $shipment_service->shipment_to_array( $shipment_model );
			$status_service   = EasyPack::EasyPack()->get_shipment_status_service();

			$shipment_data = array();

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

			} catch ( Exception $e ) {
				$ret['status']  = 'error';
				$ret['message'] = esc_html__( 'There are some errors. Please fix it:', 'inpost-for-woocommerce' )
					. PHP_EOL
					. EasyPack_API()->translate_error( $e->getMessage() );
			}

			if ( 'ok' === $ret['status'] ) {
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
			echo wp_json_encode( $ret );
			wp_die();
		}

		public static function order_metabox_content(
			$post,
			$output = true,
			$shipment = null,
			$additional_package = false
		) {
			if ( ! $output ) {
				ob_start();
			}
			$shipment_service = EasyPack::EasyPack()->get_shipment_service();

			if ( is_a( $post, 'WC_Order' ) ) {
				$order_id = $post->get_id();
			} else {
				$order_id = $post->ID;
			}
			$send_method = '';

			$geowidget_config = ( new Geowidget_v5() )->get_pickup_delivery_configuration( 'easypack_parcel_machines_cod' );
			if ( false === $shipment instanceof ShipX_Shipment_Model ) {
				$shipment = $shipment_service->get_shipment_by_order_id( $order_id );
			}

			if ( $shipment instanceof ShipX_Shipment_Model
				&& false === $shipment_service->is_shipment_match_to_current_api( $shipment )
			) {
				wp_nonce_field( static::NONCE_ACTION, 'wp_nonce' );
				$wrong_api_env = true;
				include 'views/html-order-matabox-parcel-machines_cod.php';
				if ( ! $output ) {
					$out = ob_get_clean();

					return $out;
				}

				return '';
			}
			$wrong_api_env = false;

			$order = wc_get_order( $order_id );

			if ( null !== $shipment && ! $additional_package ) {
				$parcels      = $shipment->getParcels();
				$tracking_url = $shipment->getInternalData()->getTrackingNumber();
				$stickers_url = $shipment->getInternalData()->getLabelUrl();

				$api_status_update_response = array();

				if ( true === $output ) {
					$api_status_update_response = EasyPack_Helper()->refresh_shipment_status( $order_id );
				}

				$status            = $shipment->getInternalData()->getStatus();
				$parcel_machine_id = $shipment->getCustomAttributes()->getTargetPoint();
				$send_method       = $shipment->getCustomAttributes()->getSendingMethod();
				$disabled          = true;
			} else {
				$package_sizes_display = EasyPack()->get_package_sizes_display();
				$parcels               = array();
				$parcel                = new \InspireLabs\WoocommerceInpost\shipx\models\shipment\ShipX_Shipment_Parcel_Model();
				$parcel->setTemplate( get_option( 'easypack_default_package_size', 'small' ) );
				$parcels[] = $parcel;

				$parcel_machine_from_order = get_post_meta( $order_id, '_parcel_machine_id', true );
				$parcel_machine_id         = ! empty( $parcel_machine_from_order )
					? $parcel_machine_from_order
					: get_option( 'easypack_default_machine_id' );

				$tracking_url = false;
				$status       = 'new';
				$send_method  = EasyPack_Helper()->get_default_send_method( $order_id );
				$disabled     = false;
			}
			$package_sizes = EasyPack()->get_package_sizes();

			$send_method_disabled = false;
			$send_methods         = array(
				'parcel_machine' => __( 'Parcel locker', 'inpost-for-woocommerce' ),
				'courier'        => __( 'Courier', 'inpost-for-woocommerce' ),
				'pop'            => __( 'POP', 'inpost-for-woocommerce' ),
			);

			$selected_service = $shipment_service->get_customer_service_name_by_id( static::SERVICE_ID );
			include 'views/html-order-matabox-parcel-machines_cod.php';

			wp_nonce_field( static::NONCE_ACTION, 'wp_nonce' );
			if ( ! $output ) {
				$out = ob_get_clean();

				return $out;
			}
		}
	}
}
