<?php

namespace InspireLabs\WoocommerceInpost\shipping;

use InspireLabs\WoocommerceInpost\EasyPack;
use InspireLabs\WoocommerceInpost\EasyPack_Helper;
use Exception;
use InspireLabs\WoocommerceInpost\EasyPack_API;
use InspireLabs\WoocommerceInpost\shipx\models\shipment\ShipX_Shipment_Model;
use InspireLabs\WoocommerceInpost\shipx\models\shipment\ShipX_Shipment_Parcel_Dimensions_Model;
use InspireLabs\WoocommerceInpost\shipx\models\shipment\ShipX_Shipment_Parcel_Model;
use InspireLabs\WoocommerceInpost\shipx\models\shipment\ShipX_Shipment_Parcel_Weight_Model;
use ReflectionException;
use InspireLabs\WoocommerceInpost\EmailFilters\TrackingInfoEmail;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

if ( ! class_exists( 'EasyPack_Shipping_Method_Courier' ) ) {
	class EasyPack_Shipping_Method_Courier extends EasyPack_Shippng_Parcel_Machines {

		const WP_AJAX_ACTION_CREATE = 'courier_create_package';

		const SERVICE_ID = ShipX_Shipment_Model::SERVICE_INPOST_COURIER_STANDARD;

		const NONCE_ACTION = self::SERVICE_ID;

		const SHIPPING_METHOD_ID = 'easypack_shipping_courier';

		/**
		 * Constructor for shipping class
		 *
		 * @access public
		 * @return void
		 */
		public function __construct( $instance_id = 0 ) {
			$this->init_form_fields();
			$this->instance_id        = absint( $instance_id );
			$this->supports           = array(
				'shipping-zones',
				'instance-settings',
			);
			$this->id                 = static::SHIPPING_METHOD_ID;
			$this->method_title       = $this->get_method_title();
			$this->method_description = $this->get_method_description();
			$this->init();
		}

		public function get_method_title(): string {
			return esc_html__( 'InPost Courier', 'inpost-for-woocommerce' );
		}

		public function get_method_description(): string {
			return esc_html__( 'InPost Courier', 'inpost-for-woocommerce' );
		}

		protected function get_settings_default_title(): string {
			return __( 'InPost Courier', 'inpost-for-woocommerce' );
		}

		protected static function get_order_metabox_template(): string {
			return 'views/html-order-metabox-courier.php';
		}

		protected static function get_send_methods_for_order_metabox(): array {
			if ( EasyPack_API()->getCountry() === EasyPack_API::COUNTRY_PL ) {
				return array(
					'courier' => __( 'Courier', 'inpost-for-woocommerce' ),
					'pop'     => __( 'POP', 'inpost-for-woocommerce' ),
				);
			}

			return array(
				'parcel_machine' => __( 'Parcel locker', 'inpost-for-woocommerce' ),
			);
		}

		public function generate_rates_html( $key, $data ) {
			$rates = EasyPack_Helper()->get_saved_method_rates( $this->id, $this->instance_id );
			ob_start();
			include 'views/html-rates-courier.php';

			return ob_get_clean();
		}

		public function init_form_fields() {

			$settings = array(
				array(
					'title'       => __( 'General settings', 'inpost-for-woocommerce' ),
					'type'        => 'title',
					'description' => '',
					'id'          => 'section_general_settings',
				),
				'logo_upload'                            => array(
					'name'  => __( 'Change logo', 'inpost-for-woocommerce' ),
					'title' => __( 'Upload custom logo', 'inpost-for-woocommerce' ),
					'type'  => 'logo_upload',
					'id'    => 'logo_upload',
				),
				'title'                                  => array(
					'title'             => __( 'Method title', 'inpost-for-woocommerce' ),
					'type'              => 'text',
					'default'           => $this->get_settings_default_title(),
					'custom_attributes' => array( 'required' => 'required' ),
					'desc_tip'          => false,
				),
				'delivery_terms'                         => array(
					'title'    => __( 'Terms of delivery', 'inpost-for-woocommerce' ),
					'type'     => 'text',
					'default'  => '',
					'desc_tip' => false,
				),
				'sms'                                    => array(
					'title'       => __( 'SMS notifications', 'inpost-for-woocommerce' ),
					'label'       => '',
					'type'        => 'checkbox',
					'description' => '',
					'default'     => 'no',
					'desc_tip'    => true,
				),
				'email'                                  => array(
					'title'       => __( 'Email notifications', 'inpost-for-woocommerce' ),
					'label'       => '',
					'type'        => 'checkbox',
					'description' => '',
					'default'     => 'no',
					'desc_tip'    => true,
				),
				'insurance_inpost_pl'                    => array(
					'title'       => __( 'Insurance', 'inpost-for-woocommerce' ),
					'label'       => __( 'Set from order amount', 'inpost-for-woocommerce' ),
					'type'        => 'checkbox',
					'description' => '',
					'default'     => 'no',
					'desc_tip'    => true,
				),
				'insurance_value_inpost_pl'              => array(
					'title'             => __( 'Default insurance amount', 'inpost-for-woocommerce' ),
					'type'              => 'number',
					'custom_attributes' => array(
						'step' => 'any',
						'min'  => '0',
					),
					'default'           => '',
					'desc_tip'          => false,
					'placeholder'       => '0.00',
				),
				'free_shipping_cost'                     => array(
					'title'             => __( 'Free shipping', 'inpost-for-woocommerce' ),
					'type'              => 'number',
					'custom_attributes' => array(
						'step' => 'any',
						'min'  => '0',
					),
					'default'           => '',
					'desc_tip'          => __(
						'Enter the amount of the contract, from which shipping will be free (does not include virtual products).',
						'inpost-for-woocommerce'
					),
					'placeholder'       => '0.00',
				),
				'show_free_shipping_label'               => array(
					'title'       => '',
					'label'       => __( 'Add label (free) to the end of title of shipping method', 'inpost-for-woocommerce' ),
					'type'        => 'checkbox',
					'description' => '',
					'default'     => 'yes',
					'desc_tip'    => true,
				),
				'apply_minimum_order_rule_before_coupon' => array(
					'title'       => __( 'Coupons discounts', 'inpost-for-woocommerce' ),
					'label'       => __( 'Apply minimum order rule before coupon discount', 'inpost-for-woocommerce' ),
					'type'        => 'checkbox',
					'description' => __( 'If checked, free shipping would be available based on pre-discount order amount.', 'inpost-for-woocommerce' ),
					'default'     => 'no',
					'desc_tip'    => true,
				),
				'flat_rate'                              => array(
					'title'   => __( 'Flat rate', 'inpost-for-woocommerce' ),
					'type'    => 'checkbox',
					'label'   => __( 'Set a flat-rate shipping fee for the entire order.', 'inpost-for-woocommerce' ),
					'class'   => 'easypack_flat_rate',
					'default' => 'yes',
				),
				'cost_per_order'                         => array(
					'title'             => __( 'Cost of delivery', 'inpost-for-woocommerce' ),
					'type'              => 'number',
					'custom_attributes' => array(
						'step' => 'any',
						'min'  => '0',
					),
					'class'             => 'easypack_cost_per_order',
					'default'           => '',
					'desc_tip'          => __(
						'Set a flat-rate shipping for all orders',
						'inpost-for-woocommerce'
					),
					'placeholder'       => '0.00',
				),
				'tax_status'                             => array(
					'title'   => __( 'Tax status', 'inpost-for-woocommerce' ),
					'type'    => 'select',
					'class'   => 'wc-enhanced-select',
					'default' => 'none',
					'options' => array(
						'none'    => _x( 'None', 'Tax status', 'inpost-for-woocommerce' ),
						'taxable' => __( 'Taxable', 'inpost-for-woocommerce' ),
					),
				),
				'source_of_parcel_dimensions'            => array(
					'title'   => __( 'Where to get the dimensions of parcels', 'inpost-for-woocommerce' ),
					'type'    => 'select',
					'class'   => 'wc-enhanced-select',
					'default' => 'courier',
					'options' => array(
						'courier_template'                => __( 'Template', 'inpost-for-woocommerce' ),
						'courier_default_dimensions'      => __( 'Default value', 'inpost-for-woocommerce' ),
						'courier_dimensions_from_product' => __( 'Product configuration', 'inpost-for-woocommerce' ),
					),
				),
				'default_send_method'                    => array(
					'title'   => __( 'Default send method', 'inpost-for-woocommerce' ),
					'type'    => 'select',
					'class'   => 'wc-enhanced-select',
					'default' => 'courier',
					'options' => array(
						'pop'     => __( 'POP', 'inpost-for-woocommerce' ),
						'courier' => __( 'Courier', 'inpost-for-woocommerce' ),
					),
				),
				array(
					'title'       => __( 'Rates table', 'inpost-for-woocommerce' ),
					'type'        => 'title',
					'description' => '',
					'id'          => 'section_general_settings',
				),
				'based_on'                               => array(
					'title'    => esc_html__( 'Based on', 'inpost-for-woocommerce' ),
					'type'     => 'select',
					'desc_tip' => esc_html__(
						'Select the method of calculating shipping cost. If the cost of shipping is to be calculated based on the weight of the cart and the products do not have a defined weight, the cost will be calculated incorrectly.',
						'inpost-for-woocommerce'
					),
					'class'    => 'wc-enhanced-select easypack_based_on',
					'options'  => array(
						'price'       => esc_html__( 'Price', 'inpost-for-woocommerce' ),
						'weight'      => esc_html__( 'Weight', 'inpost-for-woocommerce' ),
						'product_qty' => esc_html__( 'Products qty', 'inpost-for-woocommerce' ),
					),
				),
				'rates'                                  => array(
					'title'    => '',
					'type'     => 'rates',
					'class'    => 'easypack_rates',
					'default'  => '',
					'desc_tip' => '',
				),
			);

			$settings = $this->add_shipping_classes_settings( $settings );

			$this->form_fields          = $settings;
			$this->instance_form_fields = $settings;
		}


		public function process_admin_options() {
			parent::process_admin_options();
			EasyPack_API()->clear_cache();
		}



		public function order_metabox( $post ) {
			static::order_metabox_content( $post );
		}

		public function woocommerce_review_order_after_shipping() {
		}




		/**
		 * Creates a shipment model from AJAX request data.
		 *
		 * @return ShipX_Shipment_Model|null The created shipment object or null on failure.
		 * @throws Exception When validation fails.
		 *
		 * @since 1.0.0
		 * @access public static
		 */
		public static function ajax_create_shipment_model() {

			$order_id = (int) sanitize_text_field( wp_unslash( $_POST['order_id'] ) );

			$order = wc_get_order( $order_id );
			if ( ! $order || is_wp_error( $order ) || ! is_object( $order ) ) {
				return null;
			}

			$shipmentService = EasyPack::EasyPack()->get_shipment_service();

			$cod_amount          = null;
			$insurance_amount    = '';
			$reference_number    = '';
			$send_method         = '';
			$parcels             = array();
			$courier_parcel_data = array();

			// if Bulk create shipments.
			if ( isset( $_POST['action'] ) && 'easypack_bulk_create_shipments' === $_POST['action'] ) {

				$courier_parcel_source = EasyPack_Helper()->get_source_of_courier_dimensions( $order_id );
				$courier_parcel_data   = EasyPack_Helper()->get_courier_parcel_dimensions( $order_id, $courier_parcel_source );

				$insurance_amount = EasyPack_Helper()->get_insurance_amount( $order_id );

				$reference_number = EasyPack_Helper()->get_maybe_custom_reference_number( $order_id );

				if ( 'yes' === get_option( 'easypack_add_order_note' ) ) {
					$order_note       = $order->get_customer_note();
					$reference_number = $reference_number . ' ' . $order_note;
				}

				$send_method = EasyPack_Helper()->get_default_send_method( $order_id );

			} else {

				$courier_parcel_data = array(
					'length'       => isset( $_POST['parcel_length'] ) ? sanitize_text_field( $_POST['parcel_length'] ) : '',
					'width'        => isset( $_POST['parcel_width'] ) ? sanitize_text_field( $_POST['parcel_width'] ) : '',
					'height'       => isset( $_POST['parcel_height'] ) ? sanitize_text_field( $_POST['parcel_height'] ) : '',
					'weight'       => isset( $_POST['parcel_weight'] ) ? sanitize_text_field( $_POST['parcel_weight'] ) : '',
					'non_standard' => isset( $_POST['parcel_non_standard'] ) ? sanitize_text_field( $_POST['parcel_non_standard'] ) : '',
				);

				if ( isset( $_POST['insurance_amounts'] ) && is_array( $_POST['insurance_amounts'] ) ) {
					$insurance_amounts = array_map( 'sanitize_text_field', $_POST['insurance_amounts'] );

					if ( isset( $insurance_amounts[0] ) && is_numeric( $insurance_amounts[0] ) && floatval( $insurance_amounts[0] ) > 0 ) {
						$insurance_amount = $insurance_amounts[0];
					}
				}

				$send_method = isset( $_POST['send_method'] )
					? sanitize_text_field( $_POST['send_method'] )
					: 'courier';

				$reference_number = isset( $_POST['reference_number'] )
					? sanitize_text_field( $_POST['reference_number'] )
					: $order_id;

				$parcels = array();
				if ( isset( $_POST['parcel_mode'] ) && $_POST['parcel_mode'] === 'wielopaki' ) {
					if ( isset( $_POST['parcels'] ) && is_array( $_POST['parcels'] ) ) {
						foreach ( $_POST['parcels'] as $i => $p ) {
							$parcels[ $i ]['id']                   = isset( $p['id'] ) ? sanitize_text_field( $p['id'] ) : '';
							$parcels[ $i ]['is_non_standard']      = isset( $p['is_non_standard'] ) ? sanitize_text_field( $p['is_non_standard'] ) : '';
							$parcels[ $i ]['weight']['amount']     = isset( $p['weight']['amount'] ) ? sanitize_text_field( $p['weight']['amount'] ) : '';
							$parcels[ $i ]['dimensions']['length'] = isset( $p['dimensions']['length'] ) ? sanitize_text_field( $p['dimensions']['length'] ) : '';
							$parcels[ $i ]['dimensions']['width']  = isset( $p['dimensions']['width'] ) ? sanitize_text_field( $p['dimensions']['width'] ) : '';
							$parcels[ $i ]['dimensions']['height'] = isset( $p['dimensions']['height'] ) ? sanitize_text_field( $p['dimensions']['height'] ) : '';
						}
					}
				}
			}

			$shipment = $shipmentService->create_shipment_object_by_shiping_data(
				$parcels,
				$order_id,
				$send_method,
				static::SERVICE_ID,
				$courier_parcel_data,
				null,
				$cod_amount,
				$insurance_amount,
				$reference_number,
				null
			);
			$shipment->getInternalData()->setOrderId( $order_id );

			return $shipment;
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
			$label_url        = '';

			$shipment_data = array();

			try {

				$response = EasyPack_API()->customer_parcel_create( $shipment_array );

				$shipment_data = self::save_to_order_meta(
					$order_id,
					$shipment_model,
					$shipment_service,
					$status_service,
					$shipment_array,
					$response
				);

			} catch ( Exception $e ) {
				$ret['status']  = 'error';
				$ret['message'] = __( 'There are some errors. Please fix it: <br>', 'inpost-for-woocommerce' ) . EasyPack_API()->translate_error( $e->getMessage() );
			}

			if ( $ret['status'] == 'ok' ) {
				$order        = wc_get_order( $order_id );
				$tracking_url = EasyPack_Helper()->get_tracking_url();

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


		/**
		 * @param                           $post
		 * @param bool                      $output
		 *
		 * @param ShipX_Shipment_Model|null $shipment
		 *
		 * @return string
		 */
		public static function order_metabox_content(
			$post,
			$output = true,
			$shipment = null,
			$additional_package = false
		) {
			$wp_ajax_action_create = static::WP_AJAX_ACTION_CREATE;
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

			if ( false === $shipment instanceof ShipX_Shipment_Model ) {
				$shipment = $shipment_service->get_shipment_by_order_id( $order_id );
			}

			if ( $shipment instanceof ShipX_Shipment_Model
				&& false === $shipment_service->is_shipment_match_to_current_api( $shipment )
			) {
				wp_nonce_field( static::NONCE_ACTION, 'wp_nonce' );
				$wrong_api_env = true;
				include static::get_order_metabox_template();
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

				$parcel_machine_id = $shipment->getCustomAttributes()->getTargetPoint();
				$send_method       = $shipment->getCustomAttributes()->getSendingMethod();
				$disabled          = true;
			} else {
				$package_sizes_display = EasyPack()->get_package_sizes_display();
				$parcels               = array();
				$parcel                = new ShipX_Shipment_Parcel_Model();
				$dimensions            = self::get_single_product_dimensions( $order_id );

				$parcel->setDimensions( $dimensions );
				$weight = new ShipX_Shipment_Parcel_Weight_Model();
				$parcel->setWeight( $weight );

				$parcel->setTemplate( get_option( 'easypack_default_package_size', 'small' ) );
				$parcels[] = $parcel;

				$parcel_machine_from_order = $order->get_meta( '_parcel_machine_id' );
				$parcel_machine_id         = ! empty( $parcel_machine_from_order )
					? $parcel_machine_from_order
					: get_option( 'easypack_default_machine_id' );

				$tracking_url = false;
				$status       = 'new';

				$send_method = EasyPack_Helper()->get_default_send_method( $order_id );
				$disabled    = false;

			}
			$package_sizes = EasyPack()->get_package_sizes();

			$send_method_disabled = false;
			$send_methods = static::get_send_methods_for_order_metabox();
			$selected_service = $shipment_service->get_customer_service_name_by_id( static::SERVICE_ID );

			include static::get_order_metabox_template();

			wp_nonce_field( static::NONCE_ACTION, 'wp_nonce' );
			if ( ! $output ) {
				$out = ob_get_clean();

				return $out;
			}
		}
	}
}
