<?php

namespace InspireLabs\WoocommerceInpost\shipping;

use InspireLabs\WoocommerceInpost\EasyPack;
use InspireLabs\WoocommerceInpost\EasyPack_Helper;
use Exception;
use InspireLabs\WoocommerceInpost\EasyPack_API;
use InspireLabs\WoocommerceInpost\Geowidget_v5;
use InspireLabs\WoocommerceInpost\shipx\models\shipment\ShipX_Shipment_Model;
use InspireLabs\WoocommerceInpost\shipx\models\shipment\ShipX_Shipment_Parcel_Dimensions_Model;
use InspireLabs\WoocommerceInpost\shipx\models\shipment\ShipX_Shipment_Parcel_Model;
use InspireLabs\WoocommerceInpost\shipx\models\shipment\ShipX_Shipment_Parcel_Weight_Model;
use ReflectionException;
use InspireLabs\WoocommerceInpost\EmailFilters\TrackingInfoEmail;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

if ( ! class_exists( 'EasyPack_Shipping_Parcel_Machines_Weekend' ) ) {
	class EasyPack_Shipping_Parcel_Machines_Weekend extends EasyPack_Shippng_Parcel_Machines {

		const SERVICE_ID = ShipX_Shipment_Model::SERVICE_INPOST_LOCKER_WEEKEND;

		const NONCE_ACTION = self::SERVICE_ID;

		const SHIPPING_METHOD_ID = 'easypack_parcel_machines_weekend';

		static $review_order_after_shipping_once = false;

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
			$this->method_description = $this->get_method_description();
			$this->method_title       = $this->get_method_title();
			$this->init();
		}

		public function get_method_title(): string {
			return __( 'InPost Locker Weekend', 'inpost-for-woocommerce' );
		}

		public function get_method_description(): string {
			return __( 'Allow customers to pick up orders in weekend.', 'inpost-for-woocommerce' );
		}

		protected function get_settings_default_title(): string {
			return __( 'InPost Locker Weekend', 'inpost-for-woocommerce' );
		}

		protected static function get_order_metabox_template(): string {
			return 'views/html-order-matabox-parcel-machines-weekend.php';
		}

		protected static function get_geowidget_method_id(): string {
			return 'easypack_parcel_machines_weekend';
		}

		public function generate_rates_html( $key, $data ) {

			$rates = EasyPack_Helper()->get_saved_method_rates( $this->id, $this->instance_id );
			ob_start();
			include 'views/html-rates-weekend.php';

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
					'title'    => __( 'Method title', 'inpost-for-woocommerce' ),
					'type'     => 'text',
					'default'  => $this->get_settings_default_title(),
					'desc_tip' => false,
				),
				'delivery_terms'                         => array(
					'title'    => __( 'Terms of delivery', 'inpost-for-woocommerce' ),
					'type'     => 'text',
					'default'  => '',
					'desc_tip' => false,
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

				array(
					'title'       => __( 'Time settings', 'inpost-for-woocommerce' ),
					'type'        => 'title',
					'description' => '',
					'id'          => 'section_easypack_weekend_settings',
				),

				'day_from'                               => array(
					'title'   => __( 'Available from day of week', 'inpost-for-woocommerce' ),
					'type'    => 'select',
					'class'   => 'wc-enhanced-select',
					'default' => '4',
					'options' => array(
						'1' => __( 'Monday', 'inpost-for-woocommerce' ),
						'2' => __( 'Tuesday', 'inpost-for-woocommerce' ),
						'3' => __( 'Wednesday', 'inpost-for-woocommerce' ),
						'4' => __( 'Thursday', 'inpost-for-woocommerce' ),
						'5' => __( 'Friday', 'inpost-for-woocommerce' ),
						'6' => __( 'Saturday', 'inpost-for-woocommerce' ),
						'7' => __( 'Sunday', 'inpost-for-woocommerce' ),
					),
				),

				'hour_from'                              => array(
					'title'    => __( 'Available from hour', 'inpost-for-woocommerce' ),
					'type'     => 'time',
					'default'  => '',
					'desc_tip' => false,
				),

				'day_to'                                 => array(
					'title'   => __( 'Available to day of week', 'inpost-for-woocommerce' ),
					'type'    => 'select',
					'class'   => 'wc-enhanced-select',
					'default' => '5',
					'options' => array(
						'1' => __( 'Monday', 'inpost-for-woocommerce' ),
						'2' => __( 'Tuesday', 'inpost-for-woocommerce' ),
						'3' => __( 'Wednesday', 'inpost-for-woocommerce' ),
						'4' => __( 'Thursday', 'inpost-for-woocommerce' ),
						'5' => __( 'Friday', 'inpost-for-woocommerce' ),
						'6' => __( 'Saturday', 'inpost-for-woocommerce' ),
						'7' => __( 'Sunday', 'inpost-for-woocommerce' ),
					),
				),

				'hour_to'                                => array(
					'title'    => __( 'Available to hour', 'inpost-for-woocommerce' ),
					'type'     => 'time',
					'default'  => '',
					'desc_tip' => false,
				),

				array(
					'title'       => __( 'Price settings', 'inpost-for-woocommerce' ),
					'type'        => 'title',
					'description' => '',
					'id'          => 'section_easypack_weekend_price_settings',
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
						'Enter the amount of the order from which the shipping will be free (does not include virtual products). ',
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
				'default_send_method'                    => array(
					'title'   => __( 'Default send method', 'inpost-for-woocommerce' ),
					'type'    => 'select',
					'class'   => 'wc-enhanced-select',
					'default' => 'parcel_machine',
					'options' => array(
						'parcel_machine' => __( 'Parcel Locker', 'inpost-for-woocommerce' ),
						'pop'            => __( 'POP', 'inpost-for-woocommerce' ),
						'courier'        => __( 'Courier', 'inpost-for-woocommerce' ),
					),
				),

				array(
					'title'       => __( 'Rates table', 'inpost-for-woocommerce' ),
					'type'        => 'title',
					'description' => '',
					'id'          => 'section_general_settings',
				),
				'based_on'                               => array(
					'title'       => __( 'Based on', 'inpost-for-woocommerce' ),
					'type'        => 'select',
					'desc_tip'    => __(
						'Select the method of calculating shipping cost. If the cost of shipping is to be calculated based on the weight of the cart and the products do not have a defined weight, the cost will be calculated incorrectly.',
						'inpost-for-woocommerce'
					),
					'description' => sprintf(
						'<b id="easypack_dimensions_warning" style="color:red;display:none">%1s</b> %1s',
						__( 'Attention!', 'inpost-for-woocommerce' ),
						__( 'Set the dimension in the settings of each product. The default value is size \'A\'', 'inpost-for-woocommerce' )
					),
					'class'       => 'wc-enhanced-select easypack_based_on',
					'options'     => array(
						'price'       => esc_html__( 'Price', 'inpost-for-woocommerce' ),
						'weight'      => esc_html__( 'Weight', 'inpost-for-woocommerce' ),
						'product_qty' => esc_html__( 'Products qty', 'inpost-for-woocommerce' ),
						'size'        => esc_html__( 'Size (A, B, C)', 'inpost-for-woocommerce' ),
					),
				),
				'rates'                                  => array(
					'title'    => '',
					'type'     => 'rates',
					'class'    => 'easypack_rates',
					'default'  => '',
					'desc_tip' => '',
				),

				'gabaryt_a'                              => array(
					'title'             => __( 'Size A', 'inpost-for-woocommerce' ),
					'type'              => 'number',
					'custom_attributes' => array(
						'step' => 'any',
						'min'  => '0',
					),
					'class'             => 'easypack_gabaryt_a',
					'default'           => '',
					'desc_tip'          => __( 'Set a flat-rate shipping for size A', 'inpost-for-woocommerce' ),
					'placeholder'       => '0.00',
				),

				'gabaryt_b'                              => array(
					'title'             => __( 'Size B', 'inpost-for-woocommerce' ),
					'type'              => 'number',
					'custom_attributes' => array(
						'step' => 'any',
						'min'  => '0',
					),
					'class'             => 'easypack_gabaryt_b',
					'default'           => '',
					'desc_tip'          => __( 'Set a flat-rate shipping for size B', 'inpost-for-woocommerce' ),
					'placeholder'       => '0.00',
				),

				'gabaryt_c'                              => array(
					'title'             => __( 'Size C', 'inpost-for-woocommerce' ),
					'type'              => 'number',
					'custom_attributes' => array(
						'step' => 'any',
						'min'  => '0',
					),
					'class'             => 'easypack_gabaryt_c',
					'default'           => '',
					'desc_tip'          => __( 'Set a flat-rate shipping for size C', 'inpost-for-woocommerce' ),
					'placeholder'       => '0.00',
				),
			);

			$settings = $this->add_shipping_classes_settings( $settings );

			$this->instance_form_fields = $settings;
			$this->form_fields          = $settings;
		}


		public function process_admin_options() {
			parent::process_admin_options();
			EasyPack_API()->clear_cache();
		}




		/**
		 * @param array $package
		 */
		public function calculate_shipping( $package = array() ) {
			if ( EasyPack_API()->normalize_country_code_for_inpost( $package['destination']['country'] )
				== EasyPack_API()->getCountry()
			) {
				// check time interval for Paczka w Weekend.
				if ( $this->check_allowed_interval_for_weekend() ) {

					if ( ! $this->calculate_shipping_free_shipping( $package ) ) {

						$rate = array(
							'id'      => $this->get_rate_id(),
							'label'   => $this->title,
							'cost'    => 0,
							'package' => $package,
						);

						// Calculate the costs.
						$has_costs = false; // True when a cost is set. False if all costs are blank strings.
						$cost      = $this->get_option( 'cost' );

						if ( '' !== $cost ) {
							$has_costs    = true;
							$rate['cost'] = $this->evaluate_cost(
								$cost,
								array(
									'qty'  => $this->get_package_item_qty( $package ),
									'cost' => $package['contents_cost'],
								)
							);
						}

						// Add shipping class costs.
						$shipping_classes = WC()->shipping()->get_shipping_classes();

						if ( ! empty( $shipping_classes ) ) {
							$found_shipping_classes = $this->find_shipping_classes( $package );
							$highest_class_cost     = 0;

							foreach ( $found_shipping_classes as $shipping_class => $products ) {
								// Also handles BW compatibility when slugs were used instead of ids.
								$shipping_class_term = get_term_by( 'slug', $shipping_class, 'product_shipping_class' );
								$class_cost_string   = $shipping_class_term && $shipping_class_term->term_id ? $this->get_option( 'class_cost_' . $shipping_class_term->term_id, $this->get_option( 'class_cost_' . $shipping_class, '' ) ) : $this->get_option( 'no_class_cost', '' );

								if ( '' === $class_cost_string ) {
									continue;
								}

								$has_costs  = true;
								$class_cost = $this->evaluate_cost(
									$class_cost_string,
									array(
										'qty'  => array_sum( wp_list_pluck( $products, 'quantity' ) ),
										'cost' => array_sum( wp_list_pluck( $products, 'line_total' ) ),
									)
								);

								if ( 'class' === $this->type ) {
									$rate['cost'] += $class_cost;
								} else {
									$highest_class_cost = $class_cost > $highest_class_cost ? $class_cost : $highest_class_cost;
								}
							}

							if ( 'order' === $this->type && $highest_class_cost ) {
								$rate['cost'] += $highest_class_cost;
							}
						}

						if ( $has_costs ) {
							$this->add_rate( $rate );
						}

						/**
						 * Developers can add additional flat rates based on this one via this action since @version 2.4.
						 *
						 * Previously there were (overly complex) options to add additional rates however this was not user.
						 * friendly and goes against what Flat Rate Shipping was originally intended for.
						 */
						do_action( 'woocommerce_' . $this->id . '_shipping_add_rate', $this, $rate );

						if ( ! $has_costs ) {
							if ( ! $this->calculate_shipping_flat( $package ) ) {
								$this->calculate_shipping_table_rate( $package );
							}
						}
					}
				}
			}
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

			// required parameter for Paczka w Weekend.
			$shipment_array['end_of_week_collection'] = true;

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


		public function order_metabox( $post ) {
			static::order_metabox_content( $post );
		}


		/**
		 * @param $post
		 * @param bool                      $output
		 * @param ShipX_Shipment_Model|null $shipment
		 *
		 * @return string
		 * @throws Exception
		 */
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

			$geowidget_config = ( new Geowidget_v5() )->get_pickup_delivery_configuration( static::get_geowidget_method_id() );
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

			/**
			 * id, template, dimensions, weight, tracking_number, is_not_standard
			 */

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
				$parcel                = new ShipX_Shipment_Parcel_Model();
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

			if ( EasyPack_API()->getCountry() === EasyPack_API::COUNTRY_PL ) {
				$send_methods = array(
					'parcel_machine' => __( 'Parcel locker', 'inpost-for-woocommerce' ),
					'courier'        => __( 'Courier', 'inpost-for-woocommerce' ),
				);
			} else {
				$send_methods = array(
					'parcel_machine' => __( 'Parcel locker', 'inpost-for-woocommerce' ),
				);
			}
			$selected_service = $shipment_service->get_customer_service_name_by_id( static::SERVICE_ID );
			include static::get_order_metabox_template();

			wp_nonce_field( static::NONCE_ACTION, 'wp_nonce' );
			if ( ! $output ) {
				$out = ob_get_clean();

				return $out;
			}
		}


		public function get_logo() {

			$custom_logo = null;

			if ( empty( $custom_logo ) ) {
				return '<img style="height:22px; float:right;" src="'
					. untrailingslashit(
						EasyPack()->getPluginImages()
						. 'logo/inpost-paczka-w-weekend.png" />'
					);
			} else {
				return '<img style="height:22px; float:right;" src="'
					. untrailingslashit( $custom_logo );
			}
		}


		/**
		 * Validate time interval for Paczka w Weekend
		 *
		 * @param $instance_id
		 * @return bool
		 * @throws
		 */
		public function check_allowed_interval_for_weekend( $instance_id = null ) {

			$tz   = new \DateTimeZone( 'Europe/Warsaw' );
			$date = new \DateTimeImmutable( 'now', $tz );
			// number of current day of week
			$current_week_day_number = $date->format( 'N' );

			$week_day_from  = '';
			$week_day_to    = '';
			$week_hour_from = '';
			$week_hour_to   = '';

			if ( EasyPack_Helper()->is_flexible_shipping_activated() ) {

				$flexible_shipping_method_settings = get_option( 'woocommerce_flexible_shipping_single_' . $instance_id . '_settings' );

				$week_day_from  = isset( $flexible_shipping_method_settings['fs_inpost_pl_weekend_day_from'] )
					? $flexible_shipping_method_settings['fs_inpost_pl_weekend_day_from'] : '';
				$week_day_to    = isset( $flexible_shipping_method_settings['fs_inpost_pl_weekend_day_to'] )
					? $flexible_shipping_method_settings['fs_inpost_pl_weekend_day_to'] : '';
				$week_hour_from = isset( $flexible_shipping_method_settings['fs_inpost_pl_weekend_hour_from'] )
					? $flexible_shipping_method_settings['fs_inpost_pl_weekend_hour_from'] : '';
				$week_hour_to   = isset( $flexible_shipping_method_settings['fs_inpost_pl_weekend_hour_to'] )
					? $flexible_shipping_method_settings['fs_inpost_pl_weekend_hour_to'] : '';

			}

			if ( empty( $week_day_from ) || empty( $week_day_to ) || empty( $week_hour_from ) || empty( $week_hour_to ) ) {
				$week_day_from  = ! empty( $this->instance_settings['day_from'] ) ? $this->instance_settings['day_from'] : '';
				$week_day_to    = ! empty( $this->instance_settings['day_to'] ) ? $this->instance_settings['day_to'] : '';
				$week_hour_from = ! empty( $this->instance_settings['hour_from'] ) ? $this->instance_settings['hour_from'] : '';
				$week_hour_to   = ! empty( $this->instance_settings['hour_to'] ) ? $this->instance_settings['hour_to'] : '';

			}

			if ( ! empty( $week_day_from ) && ! empty( $week_day_to ) && ! empty( $week_hour_from ) && ! empty( $week_hour_to ) ) {

				// if current day in days interval
				if ( $current_week_day_number >= $week_day_from && $current_week_day_number <= $week_day_to ) {

					// easy case
					if ( $current_week_day_number > $week_day_from && $current_week_day_number < $week_day_to ) {
						return true;
					}

					$current_day       = $date->format( 'd' );
					$current_month     = $date->format( 'm' );
					$current_year      = $date->format( 'Y' );
					$full_current_date = $current_day . '-' . $current_month . '-' . $current_year;

					$current_time_stamp = $date->getTimestamp();

					// if current day of week match begin day
					if ( $week_day_from == $current_week_day_number ) {

						$begin_timestamp = \DateTime::createFromFormat(
							'd-m-Y H:i',
							$full_current_date . ' ' . $week_hour_from,
							$tz
						)->getTimestamp();

						if ( $current_time_stamp > $begin_timestamp ) {
							return true;
						}
					}

					// if current day of week match end day
					if ( $week_day_to == $current_week_day_number ) {

						$end_timestamp = \DateTime::createFromFormat(
							'd-m-Y H:i',
							$full_current_date . ' ' . $week_hour_to,
							$tz
						)->getTimestamp();

						if ( $current_time_stamp < $end_timestamp ) {
							return true;
						}
					}
				}
			}

			return false;
		}
	}
}
