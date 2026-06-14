<?php
/**
 * EasyPack Shipping Method Parcel Machines
 */

namespace InspireLabs\WoocommerceInpost\shipping;

use Exception;
use InspireLabs\WoocommerceInpost\admin\EasyPack_Product_Shipping_Method_Selector;
use InspireLabs\WoocommerceInpost\EasyPack;
use InspireLabs\WoocommerceInpost\EasyPack_Helper;
use InspireLabs\WoocommerceInpost\EasyPack_API;
use InspireLabs\WoocommerceInpost\Geowidget_v5;
use InspireLabs\WoocommerceInpost\shipx\models\shipment\ShipX_Shipment_Model;
use InspireLabs\WoocommerceInpost\shipx\models\shipment\ShipX_Shipment_Parcel_Dimensions_Model;
use InspireLabs\WoocommerceInpost\shipx\models\shipment\ShipX_Shipment_Parcel_Model;
use InspireLabs\WoocommerceInpost\shipx\services\shipment\ShipX_Shipment_Service;
use ReflectionException;
use WC_Eval_Math;
use WC_Shipping_Method;
use InspireLabs\WoocommerceInpost\EmailFilters\TrackingInfoEmail;
use Automattic\WooCommerce\Utilities\OrderUtil;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly.


if ( ! class_exists( 'EasyPack_Shippng_Parcel_Machines' ) ) {

	class EasyPack_Shippng_Parcel_Machines extends WC_Shipping_Method {

		static $logo_printed;

		static $setup_hooks_once = false;

		const SERVICE_ID = ShipX_Shipment_Model::SERVICE_INPOST_LOCKER_STANDARD;

		const NONCE_ACTION = self::SERVICE_ID;

		static $prevent_duplicate = array();

		static $review_order_after_shipping_once = false;

		static $woocommerce_checkout_after_order_review_once = false;

		public $ignore_discounts;

		protected $free_shipping_cost;
		protected $type;
		protected $flat_rate;
		protected $fee_cost;
		protected $cost_per_order;
		protected $based_on;
		protected $show_free_shipping_label;

		/**
		 * Constructor for shipping class
		 *
		 * @param int $instance_id Instance ID.
		 * @access public
		 * @return void
		 */
		public function __construct( $instance_id = 0 ) {
			parent::__construct();

			$this->instance_id = absint( $instance_id );
			$this->supports    = array(
				'shipping-zones',
				'instance-settings',
			);

			$this->id                 = 'easypack_parcel_machines';
			$this->method_description
						= esc_html__(
							'Inpost Parcel Locker. Allow customers to pick up orders themselves.',
							'inpost-for-woocommerce'
						);

			$this->method_title = __( 'InPost Locker 24/7', 'inpost-for-woocommerce' );
			$this->init();
		}


		/**
		 * Init your settings
		 *
		 * @access public
		 * @return void
		 */
		public function init(): void {

			$this->init_form_fields();
			$this->init_settings();
			$this->title                    = $this->get_option( 'title' );
			$this->free_shipping_cost       = $this->get_option( 'free_shipping_cost' );
			$this->show_free_shipping_label
									= $this->get_option( 'show_free_shipping_label' );
			$this->type                     = $this->get_option( 'type', 'class' );

			$this->flat_rate      = $this->get_option( 'flat_rate' );
			$this->cost_per_order = $this->get_option( 'cost_per_order' );
			$this->based_on       = $this->get_option( 'based_on' );

			$this->tax_status = $this->get_option( 'tax_status' );

			$this->ignore_discounts = $this->get_option( 'apply_minimum_order_rule_before_coupon' );

			$this->setup_hooks_once();
		}

		private function setup_hooks_once(): void {

			EasyPack_Helper()->include_inline_css();

			add_action(
				'woocommerce_update_options_shipping_' . $this->id,
				array( $this, 'process_admin_options' )
			);

			$hook_name = get_option( 'easypack_button_output', 'woocommerce_review_order_after_shipping' );

			add_action(
				$hook_name,
				array( $this, 'woocommerce_review_order_after_shipping' )
			);

			add_action( 'woocommerce_after_checkout_validation', array( $this, 'woocommerce_after_checkout_validation' ), 10, 2 );

			add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ), 10, 2 );

			add_filter(
				'woocommerce_cart_shipping_method_full_label',
				array( $this, 'woocommerce_cart_shipping_method_full_label' ),
				10,
				2
			);

			add_filter(
				'woocommerce_order_shipping_to_display_shipped_via',
				array( $this, 'woocommerce_order_shipping_to_display_shipped_via' ),
				10,
				2
			);

			add_filter(
				'woocommerce_my_account_my_orders_actions',
				array( $this, 'woocommerce_my_account_my_orders_actions' ),
				10,
				2
			);

			add_filter(
				'woocommerce_order_shipping_to_display',
				array( $this, 'woocommerce_order_shipping_to_display' ),
				9999,
				3
			);

			add_action( 'wp_head', array( $this, 'add_styles_for_my_orders_page' ), 100 );
		}

		public function admin_options() {
			?>
			<table class="form-table">
				<?php $this->generate_settings_html(); ?>
			</table>
			<?php
		}

		public function generate_rates_html( $key, $data ) {
			$rates = EasyPack_Helper()->get_saved_method_rates( $this->id, $this->instance_id );

			ob_start();
			include 'views/html-rates.php';

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
					'default'  => __( 'InPost Locker 24/7', 'inpost-for-woocommerce' ),
					'desc_tip' => false,
				),
				'delivery_terms'                         => array(
					'title'    => __( 'Terms of delivery', 'inpost-for-woocommerce' ),
					'type'     => 'text',
					'default'  => '',
					'desc_tip' => false,
				),
                'pww_mode'                         => array(
                    'title'       => __( 'PWW mode', 'inpost-for-woocommerce' ),
                    'label'       => __( 'Enabling this option allows you to create Parcel on Weekend shipments (despite the customer choosing the InPost Paczkomat 24/7 service) in the time intervals from Thursday (20:00) to Friday (18:00)', 'inpost-for-woocommerce' ),
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
						'Enter the amount of the order from which the shipping will be free (does not include virtual products). ',
						'inpost-for-woocommerce'
					),
					'placeholder'       => '0.00',
				),
				'show_free_shipping_label'               => array(
					'title'       => '',
					'label'       => __( 'Add label \'(free)\' to the end of title of shipping method', 'inpost-for-woocommerce' ),
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


		/**
		 * Generates HTML for the logo upload field in shipping method settings.
		 *
		 * @param string $key Field key.
		 * @param array  $data Field data.
		 * @return string HTML for the logo upload field.
		 *
		 * @since 1.0.0
		 * @access public
		 */
		public function generate_logo_upload_html( $key, $data ) {
			$field_key = $this->get_field_key( $key );

			$defaults = array(
				'title'             => 'Upload custom logo',
				'disabled'          => false,
				'class'             => '',
				'css'               => '',
				'placeholder'       => '',
				'type'              => 'text',
				'desc_tip'          => false,
				'description'       => '',
				'custom_attributes' => array(),
			);

			$data = wp_parse_args( $data, $defaults );

			ob_start();
			?>
			<tr valign="top">
				<th scope="row" class="titledesc">
					<label for="<?php echo esc_attr( $field_key ); ?>"><?php echo wp_kses_post( $data['title'] ); ?>
											<?php
											echo wp_kses_post( $this->get_tooltip_html( $data ) ); // WPCS: XSS ok.
											?>
					</label>
				</th>
				<td class="forminp">
					<fieldset>
						<legend class="screen-reader-text">
							<span><?php echo wp_kses_post( $data['title'] ); ?></span>
						</legend>
						<img src='<?php echo esc_attr( $this->get_instance_option( $key ) ); ?>'
							style='width: 60px; height: auto; background-size: cover; display: <?php echo ! empty( $this->get_instance_option( $key ) ) ? 'block' : 'none'; ?>; margin-bottom: 10px;'
							id='woo-inpost-logo-preview'>
						<ul id="woo-inpost-logo-action" style='display: <?php echo ! empty( $this->get_instance_option( $key ) ) ? 'block' : 'none'; ?>;'>
							<li>
								<a id="woo-inpost-logo-delete" href="#" title="Delete image">
									<?php echo esc_html__( 'Delete', 'inpost-for-woocommerce' ); ?>
								</a>
							</li>
						</ul>
						<button class='woo-inpost-logo-upload-btn'>
							<?php echo esc_html__( 'Upload', 'inpost-for-woocommerce' ); ?>
						</button>
						<input class="input-text regular-input" type="hidden"
								name="<?php echo esc_attr( $field_key ); ?>"
								id="woocommerce_easypack_logo_upload"
								style="<?php echo esc_attr( $data['css'] ); ?>"
								value="<?php echo esc_attr( $this->get_instance_option( $key ) ); ?>"
								placeholder="<?php echo esc_attr( $data['placeholder'] ); ?>"/>
						<?php
						echo wp_kses_post( $this->get_description_html( $data ) ); // WPCS: XSS ok.
						?>
					</fieldset>
				</td>
			</tr>
			<?php

			return ob_get_clean();
		}


		/**
		 * Adds a shipping rate with custom meta data for EasyPack methods.
		 *
		 * @param array $args Arguments for the shipping rate.
		 * @return void
		 *
		 * @since 1.0.0
		 * @access public
		 */
		public function add_rate( $args = array() ) {

			$delivery_terms = $this->get_instance_option( 'delivery_terms' );

			$args['meta_data'] = array(
				'logo'           => $this->get_instance_option( 'logo_upload' ),
				'delivery_terms' => $delivery_terms,
			);

			parent::add_rate( $args );
		}

		public function process_admin_options() {
			parent::process_admin_options();

			if ( isset( $_POST['rates'] ) && is_array( $_POST['rates'] ) ) {

				$save_rates = array();

				foreach ( $_POST['rates'] as $key => $rate ) {
					$save_rates[ (int) $key ] = array_map( 'sanitize_text_field', $rate );
				}

				update_option( 'woocommerce_' . $this->id . '_' . $this->instance_id . '_rates', $save_rates, false );
			}
		}

		public function calculate_shipping_free_shipping( $package ) {

			$total = WC()->cart->get_displayed_subtotal();

			if ( WC()->cart->display_prices_including_tax() ) {
				$total = $total - WC()->cart->get_discount_tax();
			}

			if ( 'no' === $this->ignore_discounts ) {
				$total = $total - WC()->cart->get_discount_total();
			}

			if ( ! empty( $this->free_shipping_cost )
				&& $this->free_shipping_cost <= $total
			) {

				// Add filter to allow disabling free shipping.
				$allow_free_shipping = apply_filters( 'inpost_pl_allow_free_shipping', true, $total, $package );

				if ( $allow_free_shipping ) {
					$add_free_ship_label = '';
					if ( 'yes' === $this->show_free_shipping_label ) {
						$add_free_ship_label = __( ' (free)', 'inpost-for-woocommerce' );
					}

					$add_rate = array(
						'id'    => $this->get_rate_id(),
						'label' => $this->title . ' ' . $add_free_ship_label,
						'cost'  => 0,
					);
					$this->add_rate( $add_rate );

					return true;
				}
			}

			return false;
		}

		public function calculate_shipping_flat( $package ) {

			if ( 'yes' === $this->flat_rate ) {

				if ( (float) $this->cost_per_order > 0 ) {

					$add_rate = array(
						'id'    => $this->get_rate_id(),
						'label' => $this->title,
						'cost'  => $this->cost_per_order,
					);
					$this->add_rate( $add_rate );

				} else {

					$add_free_ship_label = '';
					if ( 'yes' === $this->show_free_shipping_label ) {
						$add_free_ship_label = __( ' (free)', 'inpost-for-woocommerce' );
					}

					$add_rate = array(
						'id'    => $this->get_rate_id(),
						'label' => $this->title . ' ' . $add_free_ship_label,
						'cost'  => 0,
					);
					$this->add_rate( $add_rate );
				}

				return true;
			}

			return false;
		}


		/**
		 * Calculate shipping based on table rates
		 *
		 * @param array $package $package.
		 */
		public function calculate_shipping_table_rate( array $package ): void {

			// based on gabaryt.
			if ( 'size' === $this->based_on ) {

				$max_gabaryt = EasyPack_Helper()->get_max_gabaryt( $package );
				$cost        = $this->instance_settings[ 'gabaryt_' . $max_gabaryt ];

				$add_rate = array(
					'id'      => $this->get_rate_id(),
					'label'   => $this->title,
					'cost'    => $cost,
					'package' => $package,
				);
				$this->add_rate( $add_rate );

				return;
			}

			$rates = EasyPack_Helper()->get_saved_method_rates( $this->id, $this->instance_id );

			if ( is_array( $rates ) ) {
				foreach ( $rates as $key => $rate ) {
					if ( empty( $rates[ $key ]['min'] ) || '' === trim( $rates[ $key ]['min'] ) ) {
						$rates[ $key ]['min'] = 0;
					}
					if ( empty( $rates[ $key ]['max'] ) || '' === trim( $rates[ $key ]['max'] ) ) {
						$rates[ $key ]['max'] = PHP_INT_MAX;
					}
				}
			}
			$value = 0;
			if ( 'price' === $this->based_on ) {
				$value = EasyPack_Helper()->package_subtotal( $package['contents'] );
			}
			if ( 'product_qty' === $this->based_on ) {
				$value = EasyPack_Helper()->package_product_qty( $package['contents'] );
			}
			if ( 'weight' === $this->based_on ) {
				$value = EasyPack_Helper()->package_weight( $package['contents'] );
			}
			foreach ( $rates as $rate ) {
				if ( floatval( $rate['min'] ) <= $value && floatval( $rate['max'] ) >= $value ) {

					$add_rate = array(
						'id'    => $this->get_rate_id(),
						'label' => $this->title,
						'cost'  => $rate['cost'],
					);
					$this->add_rate( $add_rate );

					return;
				}
			}
		}

		/**
		 * Calculate shipping
		 *
		 * @param array $package
		 */
		public function calculate_shipping( $package = array() ) {
			if ( EasyPack_API()->normalize_country_code_for_inpost( $package['destination']['country'] )
				== EasyPack_API()->getCountry()
			) {
				/**
				 * order to caluclate shipping:
				 * 1) free shipping level
				 * 2) based on shipping class if exists
				 * 3) flat shipping settings (Cost of delivery)
				 * 4) table of costs based on weight/gabaryt
				 */

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


		/**
		 * Output template with Choose Parcel Locker button
		 */
		public function woocommerce_review_order_after_shipping() {

			if ( get_option( 'easypack_js_map_button' ) !== 'yes' ) {

				$chosen_shipping_methods = array();
				$parcel_machine_id       = '';
				$fs_method_name          = '';

				if ( is_object( WC()->session ) ) {
					$chosen_shipping_methods = WC()->session->get( 'chosen_shipping_methods' );

					if ( EasyPack_Helper()->is_flexible_shipping_activated() ) {
						$fs_method_name = EasyPack_Helper()->get_method_linked_to_fs( $chosen_shipping_methods );
					}

					if ( ! empty( $chosen_shipping_methods ) && is_array( $chosen_shipping_methods ) ) {
						// remove digit postfix (for example "easypack_parcel_machines:18") in method name.
						foreach ( $chosen_shipping_methods as $key => $method ) {
							$chosen_shipping_methods[ $key ] = EasyPack_Helper()->validate_method_name( $method );
						}
					}

					$parcel_machine_id = WC()->session->get( 'parcel_machine_id' );
				}

				$method_name = EasyPack_Helper()->validate_method_name( $this->id );

				if ( ! empty( $chosen_shipping_methods ) && is_array( $chosen_shipping_methods ) ) {
					if ( in_array( $method_name, $chosen_shipping_methods, true ) || $fs_method_name === $method_name ) {
						if ( ! self::$review_order_after_shipping_once ) {
							$args                       = array( 'parcel_machines' => array() );
							$args['parcel_machine_id']  = $parcel_machine_id;
							$args['shipping_method_id'] = $this->id;
							wc_get_template(
								'checkout/easypack-review-order-after-shipping.php',
								$args,
								'',
								EasyPack()->getTemplatesFullPath()
							);

							self::$review_order_after_shipping_once = true;
						}
					}
				}
			}
		}


		/**
		 * Validates checkout fields for EasyPack shipping methods.
		 *
		 * @param array    $fields The checkout fields.
		 * @param WP_Error $errors The error object to add validation errors to.
		 * @return void
		 *
		 * @since 1.0.0
		 * @access public
		 */
		public function woocommerce_after_checkout_validation( $fields, $errors ) {

			$chosen_shipping_methods       = array();
			$at_least_one_physical_product = false;
			$fs_method_name                = '';
			static $alert_shown;

			if ( is_object( WC()->session ) ) {
				$chosen_shipping_methods = WC()->session->get( 'chosen_shipping_methods' );
				if ( EasyPack_Helper()->is_flexible_shipping_activated() ) {
					$fs_method_name = EasyPack_Helper()->get_method_linked_to_fs( $chosen_shipping_methods );
				}
				$cart_contents = WC()->session->get( 'cart' );

				$at_least_one_physical_product = EasyPack_Helper()->physical_goods_in_cart( $cart_contents );
			}

			if ( ! empty( $chosen_shipping_methods ) && is_array( $chosen_shipping_methods ) ) {

				// remove digit postfix (for example "easypack_parcel_machines:18") in method name.
				foreach ( $chosen_shipping_methods as $key => $method ) {
					$chosen_shipping_methods[ $key ] = EasyPack_Helper()->validate_method_name( $method );
				}

				$method_name = EasyPack_Helper()->validate_method_name( $this->id );

				if ( in_array( $method_name, $chosen_shipping_methods, true ) || $fs_method_name === $method_name ) {

					if ( false === $this->is_method_courier() && $at_least_one_physical_product ) {

						if ( empty( $_POST['parcel_machine_id'] ) ) {

							if ( ! $alert_shown ) {
								$alert_shown = true;
								if ( 'pl-PL' === get_bloginfo( 'language' ) ) {
									$errors->add( 'validation', __( 'Paczkomat jest wymaganym polem', 'inpost-for-woocommerce' ) );
								} else {
									$errors->add( 'validation', __( 'Parcel locker Inpost is required field', 'inpost-for-woocommerce' ) );
								}
							}
						}
					}
				}
			}
		}



		/**
		 * Gets the InPost logo HTML for display in admin interface.
		 *
		 * Returns HTML image tag with InPost white logo, using custom logo
		 * if available or falling back to default plugin logo with
		 * consistent styling for admin metabox headers.
		 *
		 * @return string HTML image tag containing the logo.
		 */
		public function get_logo() {

			$custom_logo = null;

			if ( empty( $custom_logo ) ) {
				return '<img style="height:22px; float:right;" src="'
						. untrailingslashit(
							EasyPack()->getPluginImages()
											. 'logo/small/white.png"/>'
						);
			} else {
				return '<img style="height:22px; float:right;" src="'
						. untrailingslashit( $custom_logo );
			}
		}


		/**
		 * Adds InPost metabox to order edit screen for compatible shipping methods.
		 *
		 * Handles both HPOS and traditional order storage, validates order existence,
		 * checks for matching shipping method or Flexible Shipping integration,
		 * and adds the InPost metabox with logo to the order sidebar.
		 *
		 * @param string           $post_type The post type being edited.
		 * @param WP_Post|WC_Order $post The post or order object being edited.
		 * @return void
		 */
		public function add_meta_boxes( $post_type, $post ) {

			$order_id = null;

			if ( 'yes' === get_option( 'woocommerce_custom_orders_table_enabled' ) ) {
				// HPOS usage is enabled.
				if ( is_a( $post, 'WC_Order' ) ) {
					$order_id = $post->get_id();
				}
			} else {
				// Traditional orders are in use.
				if ( is_object( $post ) && $post->post_type == 'shop_order' ) {
					$order_id = $post->ID;
				}
			}

			if ( $order_id ) {

				$order = wc_get_order( $order_id );
				if ( ! $order || is_wp_error( $order ) ) {
					return;
				}

				$fs_method_name = Easypack_Helper()->get_woo_order_meta( $order_id, '_fs_easypack_method_name' );
				// show metabox only for matched shipping method (plus Flexible shipping integration).

				if ( $order->has_shipping_method( $this->id ) || $fs_method_name === $this->id ) {

					add_meta_box(
						'easypack_parcel_machines',
						esc_html__( 'InPost', 'inpost-for-woocommerce' )
						. $this->get_logo(),
						array( $this, 'order_metabox' ),
						null,
						'side',
						'default'
					);
				}
			}
		}


		/**
		 * Renders the order metabox for the current post.
		 *
		 * Delegates to the static metabox content method to display
		 * order-related information and controls.
		 *
		 * @param WP_Post|WC_Order $post The WordPress post object representing the order.
		 * @return void
		 */
		public function order_metabox( $post ) {
			self::order_metabox_content( $post );
		}



		/**
		 * Ajax create package
		 *
		 * @throws ReflectionException
		 */
		public static function ajax_create_package() {
			$ret = array( 'status' => 'ok' );

			$shipment_model   = self::ajax_create_shipment_model();
			$order_id         = $shipment_model->getInternalData()->getOrderId();
			$shipment_service = EasyPack::EasyPack()->get_shipment_service();
			$status_service   = EasyPack::EasyPack()->get_shipment_status_service();
			$shipment_array   = $shipment_service->shipment_to_array( $shipment_model );

			$shipment_data = array();

            $shipment_array = EasyPack_Helper()->maybe_set_pww_param( $order_id, $shipment_array );

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
				\wc_get_logger()->debug( 'INPOST create shipment Exception: ', array( 'source' => 'inpost-pl-create-shipment-exception-for-order-' . $order_id ) );
				\wc_get_logger()->debug( print_r( $order_id, true ), array( 'source' => 'inpost-pl-create-shipment-exception-for-order-' . $order_id ) );
				\wc_get_logger()->debug( print_r( $e->getMessage(), true ), array( 'source' => 'inpost-pl-create-shipment-exception-for-order-' . $order_id ) );
				\wc_get_logger()->debug( print_r( $shipment_array, true ), array( 'source' => 'inpost-pl-create-shipment-exception-for-order-' . $order_id ) );

				$ret['status']  = 'error';
				$ret['message'] = esc_html__( 'There are some errors. Please fix it:', 'inpost-for-woocommerce' )
									. PHP_EOL
									. EasyPack_API()->translate_error( $e->getMessage() );
			}

			if ( 'ok' === $ret['status'] ) {
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
					$ret['content'] = self::order_metabox_content( get_post( $order_id ), false, $shipment_model );
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
		 * Order metabox content
		 *
		 * @param $post
		 * @param bool                      $output $output.
		 * @param ShipX_Shipment_Model|null $shipment $shipment.
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

			$geowidget_config = ( new Geowidget_v5() )->get_pickup_delivery_configuration( 'easypack_parcel_machines' );
			if ( false === $shipment instanceof ShipX_Shipment_Model ) {
				$shipment = $shipment_service->get_shipment_by_order_id( $order_id );
			}

			if ( $shipment instanceof ShipX_Shipment_Model
				&& false === $shipment_service->is_shipment_match_to_current_api( $shipment )
			) {
				wp_nonce_field( self::NONCE_ACTION, 'wp_nonce' );
				$wrong_api_env = true;
				include 'views/html-order-matabox-parcel-machines.php';
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
					'pop'            => __( 'POP', 'inpost-for-woocommerce' ),
				);
			} else {
				$send_methods = array(
					'parcel_machine' => __( 'Parcel locker', 'inpost-for-woocommerce' ),
				);
			}
			$selected_service = $shipment_service->get_customer_service_name_by_id( self::SERVICE_ID );
			include 'views/html-order-matabox-parcel-machines.php';

			wp_nonce_field( self::NONCE_ACTION, 'wp_nonce' );
			if ( ! $output ) {
				$out = ob_get_clean();

				return $out;
			}
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

				$insurance_amount = EasyPack_Helper()->get_insurance_amount( $order_id );

				$reference_number = EasyPack_Helper()->get_maybe_custom_reference_number( $order_id );

				if ( 'yes' === get_option( 'easypack_add_order_note' ) ) {
					$order_note       = $order->get_customer_note();
					$reference_number = $reference_number . ' ' . $order_note;
				}

				$send_method = EasyPack_Helper()->get_default_send_method( $order_id );

			} else {

				$parcel_machine_id = isset( $_POST['parcel_machine_id'] )
					? sanitize_text_field( wp_unslash( $_POST['parcel_machine_id'] ) )
					: '';

				if ( isset( $_POST['insurance_amounts'] ) && is_array( $_POST['insurance_amounts'] ) ) {
					$insurance_amounts = array_map( 'sanitize_text_field', $_POST['insurance_amounts'] );

					if ( isset( $insurance_amounts[0] ) && is_numeric( $insurance_amounts[0] ) && floatval( $insurance_amounts[0] ) > 0 ) {
						$insurance_amount = $insurance_amounts[0];
					}
				}

				$send_method = isset( $_POST['send_method'] )
					? sanitize_text_field( wp_unslash( $_POST['send_method'] ) )
					: 'parcel_machine';

				$reference_number = isset( $_POST['reference_number'] )
					? sanitize_text_field( wp_unslash( $_POST['reference_number'] ) )
					: $order_id;

				$parcels = isset( $_POST['parcels'] )
					? array_map( 'sanitize_text_field', $_POST['parcels'] )
					: array( get_option( 'easypack_default_package_size' ) );
			}

			$shipment = $shipmentService->create_shipment_object_by_shiping_data(
				$parcels,
				$order_id,
				$send_method,
				self::SERVICE_ID,
				array(),
				$parcel_machine_id,
				null,
				$insurance_amount,
				$reference_number,
				null
			);

			$shipment->getInternalData()->setOrderId( $order_id );

			return $shipment;
		}


		public static function ajax_cancel_package() {

			$ret              = array(
				'status'  => 'ok',
				'message' => '',
			);
			$order_id         = sanitize_text_field( $_POST['order_id'] );
			$order            = wc_get_order( $order_id );
			$post             = get_post( $order_id );
			$shipment_service = EasyPack::EasyPack()->get_shipment_service();
			$shipment         = $shipment_service->get_shipment_by_order_id( $order_id );

			try {
				$cancelled_parcel = EasyPack_API()->customer_parcel_cancel( $shipment->getInternalData()->getInpostId() );

			} catch ( Exception $e ) {
				$ret['status']   = 'error';
				$ret['message'] .= $e->getMessage();
			}

			$status_srv = EasyPack()->get_shipment_status_service();
			$status_srv->refreshStatus( $shipment );
			if ( $ret['status'] === 'ok' ) {

				$order->add_order_note( __( 'Shipment canceled', 'inpost-for-woocommerce' ), false );
				$ret['content'] = self::order_metabox_content( $post, false );
			}
			echo json_encode( $ret );
			wp_die();
		}



		function woocommerce_cart_shipping_method_full_label( $label, $method ) {

			if ( in_array( $this->id, self::$prevent_duplicate ) ) {
				return $label;
			}

			if ( $method->id === $this->id ) {

				if ( ! ( $method->cost > 0 ) ) {
					$label .= ': ' . wc_price( 0 );
				}
				self::$prevent_duplicate[] = $this->id;

				return $label;
			}

			return $label;
		}


		/**
		 * Modifies the shipping method display to include the InPost logo.
		 *
		 * @param string   $via The original shipping method display text.
		 * @param WC_Order $order The order object.
		 * @return string Modified shipping method display text with logo.
		 *
		 * @since 1.0.0
		 * @access public
		 */
		function woocommerce_order_shipping_to_display_shipped_via( $via, $order ) {

			if ( self::$logo_printed === 1 ) {
				return $via;
			}

			$shipping_method_id = '';

			foreach ( $order->get_items( 'shipping' ) as $item_id => $item ) {
				$shipping_method_id = $item->get_method_id();
			}

			if ( 'easypack_parcel_machines_weekend' === $shipping_method_id || 'easypack_parcel_machines_weekend_cod' === $shipping_method_id ) {
				$img                = ' <span class="easypack-shipping-method-logo" 
                               style="display: inline;">
                               <img style="max-width: 100px; max-height: 40px;	display: inline; border:none;" src="'
						. EasyPack()->getPluginImages()
						. 'logo/inpost-paczka-w-weekend.png" />
                         <span>';
				$via               .= $img;
				self::$logo_printed = 1;

			} elseif ( $order->has_shipping_method( $this->id ) ) {

					$img                = ' <span class="easypack-shipping-method-logo" 
                               style="display: inline;">
                               <img style="max-width: 100px; max-height: 40px;	display: inline; border:none;" src="'
							. EasyPack()->getPluginImages()
							. 'logo/small/white.png" />
                         <span>';
					$via               .= $img;
					self::$logo_printed = 1;
			}

			return $via;
		}

		/**
		 * Modifies shipping display for InPost orders with zero shipping cost.
		 *
		 * Adds zero price display to shipping method name when order uses this
		 * shipping method and has no shipping cost, ensuring consistent formatting
		 * with colon separator and price display.
		 *
		 * @param string   $shipping The shipping display string.
		 * @param WC_Order $order The WooCommerce order object.
		 * @param string   $tax_display The tax display setting.
		 * @return string Modified shipping display string.
		 */
		public function woocommerce_order_shipping_to_display( $shipping, $order, $tax_display ) {
			if ( $order->has_shipping_method( $this->id ) ) {
				if ( ! ( 0 < abs( (float) $order->get_shipping_total() ) ) && $order->get_shipping_method() ) {
					if ( ! stripos( $shipping, ':' ) ) {
						$shipping .= ': ' . wc_price( 0 );
					}
				}

				return $shipping;
			}

			return $shipping;
		}


		/**
		 * Adds InPost-specific actions to My Account order actions.
		 *
		 * Adds tracking and fast returns links for orders using this shipping method,
		 * retrieves shipment status and tracking number from order metadata,
		 * and displays appropriate action buttons based on shipment status.
		 *
		 * @param array    $actions Existing order actions array.
		 * @param WC_Order $order The WooCommerce order object.
		 * @return array Modified actions array with InPost-specific actions.
		 */
		function woocommerce_my_account_my_orders_actions( $actions, $order ) {
			if ( $order->has_shipping_method( $this->id ) ) {
				$status = $order->get_meta( '_easypack_status' );
				if ( ! $status ) {
					$status = get_post_meta( $order->get_id(), '_easypack_status', true );
				}

				$tracking_url = false;
				$fast_returns = get_option( 'easypack_fast_return' );

				if ( $status != 'new' ) {
					$tracking_url    = EasyPack_Helper()->get_tracking_url();
					$tracking_number = $order->get_meta( '_easypack_parcel_tracking' );
					if ( ! $tracking_number ) {
						$tracking_number = get_post_meta( $order->get_id(), '_easypack_parcel_tracking', true );
					}

					$tracking_url = trim( $tracking_url, ',' );
				}

				if ( $tracking_number ) {
					$actions['easypack_tracking'] = array(
						'url'  => esc_url( $tracking_url . $tracking_number ),
						'name' => __( 'Track shipment', 'inpost-for-woocommerce' ),
					);
				}

				if ( ! empty( $fast_returns ) ) {
					$actions['fast_return'] = array(
						'url'  => get_option( 'easypack_fast_return' ),
						'name' => __( 'Szybkie zwroty', 'inpost-for-woocommerce' ),
					);
				}
			}

			return $actions;
		}


		/**
		 * Checks if the current shipping method is a courier-based service.
		 *
		 * Determines whether the method ID matches any of the InPost courier
		 * shipping options including standard, express, COD, and palette services.
		 *
		 * @return bool True if method is courier-based, false otherwise.
		 */
		protected function is_method_courier() {
			return 'easypack_shipping_courier' === $this->id
					|| 'easypack_shipping_esmartmix' === $this->id
					|| 'easypack_cod_shipping_courier' === $this->id
					|| 'easypack_shipping_courier_c2c' === $this->id
					|| 'easypack_shipping_courier_c2c_cod' === $this->id
					|| 'easypack_shipping_courier_lse' === $this->id
					|| 'easypack_shipping_courier_local_standard' === $this->id
					|| 'easypack_shipping_courier_local_express' === $this->id
					|| 'easypack_shipping_courier_palette' === $this->id
					|| 'easypack_shipping_courier_lse_cod' === $this->id
					|| 'easypack_shipping_courier_local_standard_cod' === $this->id
					|| 'easypack_shipping_courier_local_express_cod' === $this->id
					|| 'easypack_shipping_courier_palette_cod' === $this->id;
		}

		/**
		 * Get single product dimensions
		 *
		 * @param int $wc_order_id
		 *
		 * @return ShipX_Shipment_Parcel_Dimensions_Model
		 */
		protected static function get_single_product_dimensions( int $wc_order_id ): ShipX_Shipment_Parcel_Dimensions_Model {
			$order = wc_get_order( $wc_order_id );

			$items = $order->get_items();

			if ( count( $items ) > 1 ) {
				return new ShipX_Shipment_Parcel_Dimensions_Model();
			}

			foreach ( $order->get_items() as $item_id => $item ) {
				$product_id = $item->get_product_id();
				$product    = wc_get_product( $product_id );

				if ( $item->get_quantity() > 1 ) {
					return new ShipX_Shipment_Parcel_Dimensions_Model();
				}

				if ( ! $product || is_wp_error( $product ) ) {
					continue;
				}

				$height = (float) $product->get_height();
				$width  = (float) $product->get_width();
				$length = (float) $product->get_length();

				if ( $height > 0 || $width > 0 || $length > 0 ) {
					$dims = new ShipX_Shipment_Parcel_Dimensions_Model();
					$dims->setHeight(
						$height * 10
					);
					$dims->setWidth(
						$width * 10
					);
					$dims->setLength(
						$length * 10
					);
					$dims->setUnit( 'mm' );

					return $dims;
				}
			}

			return new ShipX_Shipment_Parcel_Dimensions_Model();
		}



		/**
		 * Inline CSS for buttons in My Orders section
		 *
		 * @return void
		 */
		public function add_styles_for_my_orders_page() {
			if ( ! empty( get_option( 'easypack_fast_return' ) ) ) {
				echo wp_kses(
					'<style>.woocommerce-button.wp-element-button.button.view {
                      margin-right: 5px;
                      margin-bottom: 5px;
                    }
                    </style>',
					array( 'style' => array() )
				);
			}
		}


		/**
		 * Evaluate a cost from a sum/string.
		 *
		 * @param  string $sum Sum of shipping.
		 * @param  array  $args Args, must contain `cost` and `qty` keys. Having `array()` as default is for back compat reasons.
		 * @return string
		 */
		protected function evaluate_cost( $sum, $args = array() ) {
			// Add warning for subclasses.
			if ( ! is_array( $args ) || ! array_key_exists( 'qty', $args ) || ! array_key_exists( 'cost', $args ) ) {
				wc_doing_it_wrong( __FUNCTION__, '$args must contain `cost` and `qty` keys.', '4.0.1' );
			}

			include_once WC()->plugin_path() . '/includes/libraries/class-wc-eval-math.php';

			// Allow 3rd parties to process shipping cost arguments.
			$args           = apply_filters( 'woocommerce_evaluate_shipping_cost_args', $args, $sum, $this );
			$locale         = localeconv();
			$decimals       = array( wc_get_price_decimal_separator(), $locale['decimal_point'], $locale['mon_decimal_point'], ',' );
			$this->fee_cost = $args['cost'];

			// Expand shortcodes.
			add_shortcode( 'fee', array( $this, 'fee' ) );

			$sum = do_shortcode(
				str_replace(
					array(
						'[qty]',
						'[cost]',
					),
					array(
						$args['qty'],
						$args['cost'],
					),
					$sum
				)
			);

			remove_shortcode( 'fee', array( $this, 'fee' ) );

			// Remove whitespace from string.
			$sum = preg_replace( '/\s+/', '', $sum );

			// Remove locale from string.
			$sum = str_replace( $decimals, '.', $sum );

			// Trim invalid start/end characters.
			$sum = rtrim( ltrim( $sum, "\t\n\r\0\x0B+*/" ), "\t\n\r\0\x0B+-*/" );

			// Do the math.
			return $sum ? WC_Eval_Math::evaluate( $sum ) : 0;
		}


		/**
		 * Get items in package.
		 *
		 * @param  array $package Package of items from cart.
		 * @return int
		 */
		public function get_package_item_qty( $package ): int {
			$total_quantity = 0;
			foreach ( $package['contents'] as $item_id => $values ) {
				if ( $values['quantity'] > 0 && $values['data']->needs_shipping() ) {
					$total_quantity += $values['quantity'];
				}
			}
			return $total_quantity;
		}

		/**
		 * Finds and returns shipping classes and the products with said class.
		 *
		 * @param mixed $package Package of items from cart.
		 * @return array
		 */
		public function find_shipping_classes( $package ) {
			$found_shipping_classes = array();

			foreach ( $package['contents'] as $item_id => $values ) {
				if ( $values['data']->needs_shipping() ) {
					$found_class = $values['data']->get_shipping_class();

					if ( ! isset( $found_shipping_classes[ $found_class ] ) ) {
						$found_shipping_classes[ $found_class ] = array();
					}

					$found_shipping_classes[ $found_class ][ $item_id ] = $values;
				}
			}

			return $found_shipping_classes;
		}


		public function add_shipping_classes_settings( $settings ) {
			$cost_desc        = __( 'Enter a cost (excl. tax) or sum, e.g. <code>10.00 * [qty]</code>.', 'inpost-for-woocommerce' ) . '<br/><br/>' . __( 'Use <code>[qty]</code> for the number of items, <br/><code>[cost]</code> for the total cost of items, and <code>[fee percent="10" min_fee="20" max_fee=""]</code> for percentage based fees.', 'inpost-for-woocommerce' );
			$shipping_classes = WC()->shipping()->get_shipping_classes();
			if ( ! empty( $shipping_classes ) ) {
				$settings['class_costs'] = array(
					'title'       => __( 'Shipping class costs', 'inpost-for-woocommerce' ),
					'type'        => 'title',
					'default'     => '',
					/* translators: %s: URL for link. */
					'description' => sprintf( __( 'These costs can optionally be added based on the <a href="%s">product shipping class</a>.', 'inpost-for-woocommerce' ), admin_url( 'admin.php?page=wc-settings&tab=shipping&section=classes' ) ),
				);
				foreach ( $shipping_classes as $shipping_class ) {
					if ( ! isset( $shipping_class->term_id ) ) {
						continue;
					}
					$settings[ 'class_cost_' . $shipping_class->term_id ] = array(
						/* translators: %s: shipping class name */
						'title'             => sprintf( __( '"%s" shipping class cost', 'inpost-for-woocommerce' ), esc_html( $shipping_class->name ) ),
						'type'              => 'text',
						'placeholder'       => __( 'N/A', 'inpost-for-woocommerce' ),
						'description'       => $cost_desc,
						'default'           => $this->get_option( 'class_cost_' . $shipping_class->slug ), // Before 2.5.0, we used slug here which caused issues with long setting names.
						'desc_tip'          => true,
						'sanitize_callback' => array( $this, 'sanitize_cost' ),
					);
				}

				$settings['no_class_cost'] = array(
					'title'             => __( 'No shipping class cost', 'inpost-for-woocommerce' ),
					'type'              => 'text',
					'placeholder'       => __( 'N/A', 'inpost-for-woocommerce' ),
					'description'       => $cost_desc,
					'default'           => '',
					'desc_tip'          => true,
					'sanitize_callback' => array( $this, 'sanitize_cost' ),
				);

				$settings['type'] = array(
					'title'   => __( 'Calculation type', 'inpost-for-woocommerce' ),
					'type'    => 'select',
					'class'   => 'wc-enhanced-select',
					'default' => 'class',
					'options' => array(
						'class' => __( 'Per class: Charge shipping for each shipping class individually', 'inpost-for-woocommerce' ),
						'order' => __( 'Per order: Charge shipping for the most expensive shipping class', 'inpost-for-woocommerce' ),
					),
				);
			}

			return $settings;
		}


		public static function save_to_order_meta(
			$order_id,
			$shipment_model,
			$shipment_service,
			$status_service,
			$shipment_array,
			$response
		) {

			$shipment_data       = array();
			$additional_packages = array();
			$internal_data       = null;

			$order         = wc_get_order( $order_id );
			$inpost_method = $shipment_array['service'];

			if ( ! $order || is_wp_error( $order ) ) {
				return $shipment_data;
			}

			$is_additional_package_processing = false;

			if ( isset( $_POST['easypack_additional_package'] ) && 'true' === $_POST['easypack_additional_package'] ) {
				$is_additional_package_processing = true;
			}

			if ( ! $is_additional_package_processing ) {
				
				if ( $order && ! is_wp_error( $order ) ) {
					$order->update_meta_data( '_easypack_parcel_create_args', $shipment_array );
					$order->save();
				}
				
			} else {
				$additional_packages = EasyPack_Helper()->get_saved_additional_packages( $order_id );

				$additional_package = array();

				$additional_package[ $inpost_method ]['inpost_id']  = $response['id'];
				$additional_package[ $inpost_method ]['status']     = $status_service->getStatusDescription( $response['status'] );
				$additional_package[ $inpost_method ]['apistatus']  = $response['status'];
				$additional_package[ $inpost_method ]['ref_number'] = $shipment_array['reference'];
				$additional_package[ $inpost_method ]['args']       = $shipment_array;
				// $additional_package[$inpost_method]['url'] = $response['href'];
			}

			if ( ! $is_additional_package_processing ) {
				$internal_data = $shipment_model->getInternalData();
				$internal_data->setInpostId( $response['id'] );
				$internal_data->setStatus( $response['status'] );
				$internal_data->setStatusTitle( $status_service->getStatusTitle( $response['status'] ) );
				$internal_data->setStatusDescription( $status_service->getStatusDescription( $response['status'] ) );
				$internal_data->setStatusChangedTimestamp( time() );
				$internal_data->setCreatedAt( time() );
				$internal_data->setUrl( $response['href'] );
			}

			for ( $i = 0; $i < 3; $i++ ) {
				sleep( 1 );
				// $search_in_api = EasyPack_API()->customer_parcel_get_by_id( $shipment_model->getInternalData()->getInpostId() );
				$search_in_api = EasyPack_API()->customer_parcel_get_by_id( $response['id'] );

				if ( isset( $search_in_api['parcels'][0]['tracking_number'] ) ) {
					$shipment_data['tracking'] = $search_in_api['parcels'][0]['tracking_number'];
					$shipment_model->getInternalData()->setTrackingNumber( $search_in_api['parcels'][0]['tracking_number'] );
					break;
				}
			}

			$shipment_data['inpost_id'] = $response['id'];
			$shipment_data['status']    = $status_service->getStatusDescription( $response['status'] );
			$shipment_data['service']   = $shipment_service->get_customer_service_name_by_id( self::SERVICE_ID );

			if ( $internal_data ) {
				$shipment_model->setInternalData( $internal_data );
			}

			if ( ! $is_additional_package_processing ) {
				update_post_meta( $order_id, '_easypack_status', 'created' );
				if ( 'yes' === get_option( 'woocommerce_custom_orders_table_enabled' ) ) {
					if ( $order && ! is_wp_error( $order ) ) {
						$order->update_meta_data( '_easypack_status', 'created' );
						$order->save();
					}
				}
			}

			if ( isset( $shipment_data['inpost_id'] ) && ! empty( $shipment_data['inpost_id'] ) ) {

				if ( ! $is_additional_package_processing ) {
					if ( isset( $shipment_data['tracking'] ) && ! empty( $shipment_data['tracking'] ) ) {
						$shipment_model->getInternalData()->setTrackingNumber( $shipment_data['tracking'] );
						update_post_meta( $order_id, '_easypack_parcel_tracking', $shipment_data['tracking'] );
						if ( $order && ! is_wp_error( $order ) ) {
							$order->update_meta_data( '_easypack_parcel_tracking', $shipment_data['tracking'] );
							$order->save();
						}
					}
				} else {
					$additional_package[ $inpost_method ]['tracking'] = isset( $shipment_data['tracking'] ) ? $shipment_data['tracking'] : '';
					$additional_packages[]                            = $additional_package;

					update_post_meta( $order_id, '_easypack_additional_packages', $additional_packages );
					if ( $order && ! is_wp_error( $order ) ) {
						$order->update_meta_data( '_easypack_additional_packages', $additional_packages );
						$order->save();
					}
				}
			}

			// zapisz koszt przesyłki do przesyłki.
			// $price_calculator = EasyPack()->get_shipment_price_calculator_service();

			if ( ! $is_additional_package_processing ) {
				$shipment_service->update_shipment_to_db( $shipment_model );
			}

			return $shipment_data;
		}
	}


}
