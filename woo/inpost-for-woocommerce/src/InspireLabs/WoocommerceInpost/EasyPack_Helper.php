<?php

namespace InspireLabs\WoocommerceInpost;

use InspireLabs\WoocommerceInpost\EasyPack;
use Exception;
use InspireLabs\WoocommerceInpost\shipping\EasyPack_Shippng_Parcel_Machines;
use InspireLabs\WoocommerceInpost\shipx\models\courier_pickup\ShipX_Dispatch_Order_Model;
use InspireLabs\WoocommerceInpost\shipx\models\shipment\ShipX_Shipment_Model;
use Requests_Utility_CaseInsensitiveDictionary;
use WC_Shipping_Method;
use WP_Error;


/**
 * EasyPack Helper
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'EasyPack_Helper' ) ) :

	class EasyPack_Helper {

		protected static $instance;
		protected static $css_embeded;

		private $cached_zones = array();

		public function __construct() {
			add_filter( 'query_vars', array( $this, 'query_vars' ) );

			add_action( 'woocommerce_before_my_account', array( $this, 'woocommerce_before_my_account' ) );
			add_filter( 'woocommerce_screen_ids', array( $this, 'woocommerce_screen_ids' ) );

			add_action( 'woocommerce_shipping_zone_method_added', array( $this, 'clear_zones_cache' ) );
			add_action( 'woocommerce_shipping_zone_method_deleted', array( $this, 'clear_zones_cache' ) );
			add_action( 'woocommerce_shipping_zone_method_status_toggled', array( $this, 'clear_zones_cache' ) );
		}

		public static function EasyPack_Helper() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}

			return self::$instance;
		}



		public function print_sticker_by_inpost_id( $inpost_id ) {
			$ret = array( 'status' => 'ok' );

			try {
				$results = EasyPack_API()->customer_shipments_labels( array( $inpost_id ) );

				if ( ! isset( $results['headers'] ) ) {
					return;

				} else {
					$headers = $results['headers'];
				}

				/**
				 * @var Requests_Utility_CaseInsensitiveDictionary $headers
				 */

				header(
					sprintf(
						'Content-type:%s',
						$headers->getAll()['content-type']
					)
				);
				// PDF string.
				echo $results['body']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				die();

			} catch ( Exception $e ) {
				$ret['status']  = 'error';
				$ret['message'] = $e->getMessage();
				wp_die( esc_html__( 'Error while creating manifest:  ', 'inpost-for-woocommerce' ) . esc_html( $e->getMessage() ) );

			}
		}


		public function print_stickers(
			$return_stickers = false,
			$orders = null
		) {
			$ret = array( 'status' => 'ok' );

			if ( null === $orders ) {
				$orders = isset( $_POST['easypack_parcel'] ) ? (array) $_POST['easypack_parcel'] : array();
				$orders = array_map( 'sanitize_text_field', $orders );
			}

			$selected_shipments_ids = array();
			$shipment_service       = EasyPack()->get_shipment_service();

			if ( ! empty( $orders ) ) {

				if ( is_array( $orders ) ) {
					foreach ( $orders as $order ) {
						$inpost_internal_data = $shipment_service->get_shipment_by_order_id( (int) $order );

						if ( $inpost_internal_data && is_object( $inpost_internal_data ) ) {
							$selected_shipments_ids[] = $inpost_internal_data->getInternalData()->getInpostId();
						}

						// check for additional packages.
						$existed_additional_packages = EasyPack_Helper()->get_saved_additional_packages( $order );
						if ( is_array( $existed_additional_packages ) && ! empty( $existed_additional_packages ) ) {
							foreach ( $existed_additional_packages as $additional_package ) {
								if ( is_array( $additional_package ) ) {
									foreach ( $additional_package as $key => $data ) {
										if ( isset( $data['inpost_id'] ) && ! empty( $data['inpost_id'] ) ) {
											$selected_shipments_ids[] = $data['inpost_id'];
										}
									}
								}
							}
						}
					}
				} else {

					$inpost_internal_data = $shipment_service->get_shipment_by_order_id( (int) $orders );

					if ( $inpost_internal_data && is_object( $inpost_internal_data ) ) {
						$selected_shipments_ids[] = $inpost_internal_data->getInternalData()->getInpostId();
					}

					// check for additional packages.
					$existed_additional_packages = EasyPack_Helper()->get_saved_additional_packages( $orders );
					if ( is_array( $existed_additional_packages ) && ! empty( $existed_additional_packages ) ) {
						foreach ( $existed_additional_packages as $additional_package ) {
							if ( is_array( $additional_package ) ) {
								foreach ( $additional_package as $key => $data ) {
									if ( isset( $data['inpost_id'] ) && ! empty( $data['inpost_id'] ) ) {
										$selected_shipments_ids[] = $data['inpost_id'];
									}
								}
							}
						}
					}
				}
			}

			try {
				if ( true === $return_stickers ) {
					$results
						= EasyPack_API()->customer_shipments_return_labels( $selected_shipments_ids );
				} elseif ( is_array( $selected_shipments_ids ) && count( $selected_shipments_ids ) > 1 ) {

					$results = EasyPack_API()->customer_shipments_labels( $selected_shipments_ids );
				} elseif ( isset( $selected_shipments_ids[0] ) && ! empty( $selected_shipments_ids[0] ) ) {
					$results = EasyPack_API()->customer_parcel_sticker( $selected_shipments_ids[0] );
				}

				if ( ! isset( $results['headers'] ) ) {
					if ( isset( $_POST['easypack_action'] ) && $_POST['easypack_action'] === 'easypack_create_bulk_labels' ) {

						\wc_get_logger()->debug( 'Inpost IDs for labels: ', array( 'source' => 'inpost-label-log' ) );
						\wc_get_logger()->debug( print_r( $selected_shipments_ids, true ), array( 'source' => 'inpost-label-log' ) );
						\wc_get_logger()->debug( 'API response: ', array( 'source' => 'inpost-label-log' ) );
						\wc_get_logger()->debug( print_r( $results, true ), array( 'source' => 'inpost-label-log' ) );

						echo json_encode(
							array(
								'status'  => isset( $results['status'] ) ? $results['status'] : 'Błąd',
								'details' => isset( $results['details'] ) ? $results['details'] : 'Opóźnienie odpowiedzi API - odśwież stronę i spróbuj ponownie (lub później)',
							)
						);
					}
					return;

				} else {
					$headers = $results['headers'];
				}

				/**
				 * @var Requests_Utility_CaseInsensitiveDictionary $headers
				 */

				header(
					sprintf(
						'Content-type:%s',
						$headers->getAll()['content-type']
					)
				);
				// PDF string.
				echo $results['body']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				die();

			} catch ( Exception $e ) {
				$ret['status']  = 'error';
				$ret['message'] = $e->getMessage();
				wp_die( esc_html__( 'Error while creating manifest:  ', 'inpost-for-woocommerce' ) . esc_html( $e->getMessage() ) );

			}
		}


		public function post_confirmation_pdf( $shipment_ids ) {
			$ret = array( 'status' => 'ok' );

			try {

				$results = EasyPack_API()->get_post_confirmations( $shipment_ids );

				if ( isset( $results['headers'] ) ) {
					header(
						sprintf(
							'Content-type:%s',
							$results['headers']['data']['content-type']
						)
					);
					// PDF string.
					echo $results['body']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					wp_die();

				} else {

					\wc_get_logger()->debug( 'Inpost post_confirmation_pdf: ', array( 'source' => 'inpost-debug-log' ) );
					\wc_get_logger()->debug( print_r( $shipment_ids, true ), array( 'source' => 'inpost-debug-log' ) );
					\wc_get_logger()->debug( print_r( $results, true ), array( 'source' => 'inpost-debug-log' ) );

					if ( isset( $results['error'] ) ) {
						$error_message = '';
						if ( isset( $results['message'] ) ) {
							$error_message = $results['message'];
						} else {
							$error_message = $results['error'];
						}
						wp_die( esc_html( $error_message ) );
					}
				}
			} catch ( Exception $e ) {
				$ret['status']  = 'error';
				$ret['message'] = $e->getMessage();
				wp_die( esc_html__( 'Error while creating manifest PDF: ', 'inpost-for-woocommerce' ) . esc_html( $e->getMessage() ) );

			}
		}



		/**
		 * Allow for custom query variables
		 */
		public function query_vars( $query_vars ) {
			$query_vars[] = 'easypack_download';

			return $query_vars;
		}


		/**
		 * @param string|null $country
		 *
		 * @return string
		 */
		public function get_tracking_url( $country = null ) {
			if ( null === $country ) {
				if ( EasyPack_API()->is_pl() ) {
					return 'https://inpost.pl/sledzenie-przesylek?number=';
				}

				return 'https://tracking.inpost.co.uk/';
			}

			if ( EasyPack_API()->normalize_country_code_for_inpost( $country )
				=== EasyPack_API::COUNTRY_PL
			) {
				return 'https://inpost.pl/sledzenie-przesylek?number=';
			}

			return 'https://tracking.inpost.co.uk/';
		}



		function woocommerce_before_my_account() {
			if ( get_option( 'easypack_returns_page' )
				&& trim( get_option( 'easypack_returns_page' ) ) !== ''
			) {
				$page = get_page( get_option( 'easypack_returns_page' ) );
				if ( $page ) {
					$img_src = EasyPack()->getPluginImages()
								. 'logo/small/white.png';
					$args    = array(
						'returns_page'       => get_page_link( get_option( 'easypack_returns_page' ) ),
						'returns_page_title' => $page->post_title,
						'img_src'            => $img_src,
					);
					wc_get_template(
						'myaccount/before-my-account.php',
						$args,
						'',
						plugin_dir_path( EasyPack()->getPluginFilePath() )
						. 'templates/'
					);
				}
			}
		}

		function woocommerce_screen_ids( $screen_ids ) {
			$screen_ids[] = 'inpost_page_easypack_shipment';

			return $screen_ids;
		}

		/**
		 * Check if at least one physical product exists in cart
		 *
		 * @param array $cart_contents
		 *
		 * @return bool
		 */
		public function physical_goods_in_cart( $cart_contents ) {
			$res = false;

			if ( ! empty( $cart_contents ) ) {
				foreach ( $cart_contents as $cart_item_key => $cart_item ) {
					// if variation in cart.
					if ( $cart_item['variation_id'] ) {

						$variant = wc_get_product( $cart_item['variation_id'] );
						if ( ! $variant->is_virtual() /* && ! $variant->is_downloadable() */ ) {
							$res = true;
							break;
						}
					} else {
						// simple product.
						$_product = wc_get_product( $cart_item['product_id'] );
						if ( ! $_product->is_virtual() /* && ! $_product->is_downloadable() */ ) {
							$res = true;
							break;
						}
					}
				}
			}

			return $res;
		}

		public function validate_method_name( $method_name ) {
			if ( stripos( $method_name, ':' ) ) {
				return trim( explode( ':', $method_name )[0] );
			}
			return $method_name;
		}

		public function validate_method_instance_id( $method_name ) {
			if ( stripos( $method_name, ':' ) ) {
				return trim( explode( ':', $method_name )[1] );
			}
			return null;
		}


		/**
		 * Convert size from model data to letter symbol (A,B,C)
		 *
		 * @param string $size
		 *
		 * @return string
		 */
		public function convert_size_to_symbol( $size ) {
			if ( 'small' === $size ) {
				return 'A';
			}
			if ( 'medium' === $size ) {
				return 'B';
			}
			if ( 'large' === $size ) {
				return 'C';
			}
			if ( 'xlarge' === $size ) {
				return 'D';
			}

			return $size; // for Kurier shipments with dimensions.
		}


		/**
		 * Gets the saved shipping rates for a specific shipping method.
		 *
		 * @param string $id The shipping method ID.
		 * @param int    $instance_id The shipping method instance ID.
		 * @return array The saved shipping rates.
		 *
		 * @since 1.0.0
		 * @access public
		 */
		public function get_saved_method_rates( $id, $instance_id ) {

			$rates = get_option( 'woocommerce_' . $id . '_' . $instance_id . '_rates' );
			// backward compatibility.
			if ( ! $rates ) {
				$rates = get_option( 'woocommerce_' . $id . '_rates', array() );
			}

			return is_array( $rates ) ? $rates : array();
		}


		/**
		 * Integration with plugin "Flexible shipping"
		 *
		 * @return boolean
		 */
		public function is_flexible_shipping_activated() {
			$plugin_file    = 'flexible-shipping/flexible-shipping.php';
			$active_plugins = (array) get_option( 'active_plugins', array() );

			if ( is_multisite() ) {
				$active_plugins = array_merge( $active_plugins, get_site_option( 'active_sitewide_plugins', array() ) );
			}

			return in_array( $plugin_file, $active_plugins ) || array_key_exists( $plugin_file, $active_plugins );
		}

		/**
		 * Integration with plugin "Flexible shipping" (get shipping method linked in FS settings)
		 *
		 * @param mixed $chosen_shipping_methods $chosen_shipping_methods.
		 *
		 * @return string
		 */
		public function get_method_linked_to_fs( $chosen_shipping_methods ) {
			$method_linked_to_fs = '';

			if ( ! empty( $chosen_shipping_methods ) && is_array( $chosen_shipping_methods ) ) {
				foreach ( $chosen_shipping_methods as $shipping_method ) {
					if ( 0 === strpos( $shipping_method, 'flexible_shipping_single' ) ) {

						$shipping_method_instance_id = $this->validate_method_instance_id( $shipping_method );
						if ( isset( $shipping_method_instance_id ) ) {
							$method_linked_to_fs = $this->get_method_linked_to_fs_by_instance_id( $shipping_method_instance_id );
						}
					}
				}
			}

			return $method_linked_to_fs;
		}


		/**
		 * Integration with plugin "Flexible shipping" (get shipping method linked in FS settings)
		 *
		 * @param string $instance_id $instance_id.
		 *
		 * @return string
		 */
		public function get_method_linked_to_fs_by_instance_id( $instance_id ) {
			$method_linked_to_fs      = '';
			$shipping_method_settings = get_option( 'woocommerce_flexible_shipping_single_' . $instance_id . '_settings' );

			if ( isset( $shipping_method_settings['fs_inpost_pl_method'] ) && ! empty( $shipping_method_settings['fs_inpost_pl_method'] ) ) {
				$method_linked_to_fs = $shipping_method_settings['fs_inpost_pl_method'];
			}

			return $method_linked_to_fs;
		}



		/**
		 * Gets the class name for a shipping method by its ID.
		 *
		 * @param string|int $shipping_id The shipping method ID or instance ID.
		 * @return string The class name for the shipping method.
		 *
		 * @since 1.0.0
		 * @access public
		 */
		public function get_class_name_by_shipping_id( $shipping_id ) {

			$class_name = '';

			// if we get instance_id of shipping method.
			if ( is_numeric( $shipping_id ) ) {
				if ( class_exists( 'WC_Shipping_Zones' ) ) {
					$shipping_method = \WC_Shipping_Zones::get_shipping_method( $shipping_id );
					// Get the shipping method ID from the instance object.
					if ( $shipping_method ) {
						$shipping_id = $shipping_method->id;
					}
				}
			}

			switch ( $shipping_id ) {
				case 'easypack_parcel_machines_economy':
					$class_name = 'EasyPack_Shipping_Parcel_Machines_Economy';
					break;
				case 'easypack_parcel_machines_economy_cod':
					$class_name = 'EasyPack_Shipping_Parcel_Machines_Economy_COD';
					break;
				case 'easypack_shipping_courier_c2c_cod':
					$class_name = 'EasyPack_Shipping_Method_Courier_C2C_COD';
					break;
				case 'easypack_shipping_courier_c2c':
					$class_name = 'EasyPack_Shipping_Method_Courier_C2C';
					break;
				case 'easypack_parcel_machines':
					$class_name = 'EasyPack_Shippng_Parcel_Machines';
					break;
				case 'easypack_parcel_machines_cod':
					$class_name = 'EasyPack_Shippng_Parcel_Machines_COD';
					break;
				case 'easypack_parcel_machines_weekend':
					$class_name = 'EasyPack_Shipping_Parcel_Machines_Weekend';
					break;
				case 'easypack_parcel_machines_weekend_cod':
					$class_name = 'EasyPack_Shipping_Parcel_Machines_Weekend_COD';
					break;
				case 'easypack_shipping_courier':
					$class_name = 'EasyPack_Shipping_Method_Courier';
					break;
				case 'easypack_cod_shipping_courier':
					$class_name = 'EasyPack_Shipping_Method_Courier_COD';
					break;
				case 'easypack_shipping_courier_local_express':
					$class_name = 'EasyPack_Shipping_Method_Courier_Local_Express';
					break;
				case 'easypack_shipping_courier_le_cod':
					$class_name = 'EasyPack_Shipping_Method_Courier_Local_Express_COD';
					break;
				case 'easypack_shipping_courier_local_standard':
					$class_name = 'EasyPack_Shipping_Method_Courier_Local_Standard';
					break;
				case 'easypack_shipping_courier_local_standard_cod':
					$class_name = 'EasyPack_Shipping_Method_Courier_Local_Standard_COD';
					break;
				case 'easypack_shipping_courier_lse':
					$class_name = 'EasyPack_Shipping_Method_Courier_LSE';
					break;
				case 'easypack_shipping_courier_lse_cod':
					$class_name = 'EasyPack_Shipping_Method_Courier_LSE_COD';
					break;
				case 'easypack_shipping_courier_palette':
					$class_name = 'EasyPack_Shipping_Method_Courier_Palette';
					break;
				case 'courier_palette_cod_create_package':
					$class_name = 'EasyPack_Shipping_Method_Courier_Palette_COD';
					break;
				case 'easypack_shipping_esmartmix':
					$class_name = 'EasyPack_Shipping_Method_EsmartMix';
					break;
			}

			return $class_name;
		}


		/**
		 * Inline CSS for button
		 *
		 * @return void
		 */
		public function include_inline_css() {
			add_action(
				'wp_head',
				function () {
					$custom_button_color = get_option( 'easypack_custom_button_css' );

					if ( ! empty( $custom_button_color ) && ! self::$css_embeded ) {

						$easypack_button_settings_css = '';

						if ( isset( $custom_button_color ) ) {
							$custom_button_color           = sanitize_text_field( $custom_button_color );
							$easypack_button_settings_css .= '.easypack_show_geowidget {
                                  background:  ' . $custom_button_color . ' !important;
                                }';
						}
						self::$css_embeded = true;
						echo wp_kses( "<style>$easypack_button_settings_css</style>", array( 'style' => array() ) );
					}
				},
				100
			);
		}



		/**
		 * Retrieves parcel dimensions from the selected courier template.
		 *
		 * @return array Dimensions array with length, width, height, and weight.
		 *
		 * @since 1.7.3
		 * @access public
		 */
		public function get_courier_parcel_data_from_template() {
			$dimensions['length']       = '';
			$dimensions['width']        = '';
			$dimensions['height']       = '';
			$dimensions['weight']       = '';
			$dimensions['non_standard'] = 'no';

			$all_templates = get_option( 'easypack_courier_tmplts_dmtemplates', array() );
			if ( ! empty( $all_templates ) && is_array( $all_templates ) ) {
				$selected_template = get_option( 'easypack_courier_tmplts_dmtemplate_selected', 0 );

				if ( ! empty( $all_templates[ $selected_template ] ) && is_array( $all_templates[ $selected_template ] ) ) {
					$dimensions = $all_templates[ $selected_template ];
					if ( isset( $dimensions['not_standard'] ) && '1' === $dimensions['not_standard'] ) {
						$dimensions['non_standard'] = 'yes';
					}
				}
			}

			return $dimensions;
		}


		/**
		 * Checks if a shipping method ID belongs to a courier service.
		 *
		 * @param string $shipment_id The shipping method ID to check.
		 * @return bool True if it's a courier service, false otherwise.
		 *
		 * @since 1.0.0
		 * @access public
		 */
		public function is_courier_service_by_id( $shipment_id ) {

			if ( in_array(
				$shipment_id,
				array(
					'easypack_shipping_courier',
					'easypack_cod_shipping_courier',
					'easypack_shipping_courier_local_express',
					'easypack_shipping_courier_le_cod',
					'easypack_shipping_courier_local_standard',
					'easypack_shipping_courier_local_standard_cod',
					'easypack_shipping_courier_lse',
					'easypack_shipping_courier_lse_cod',
					'easypack_shipping_courier_palette',
					'easypack_shipping_courier_palette_cod',
					'easypack_shipping_esmartmix',
				)
			)
			) {
				return true;
			}

			return false;
		}


		/**
		 * Calculates the total weight of an order.
		 *
		 * @param int $order_id The order id.
		 * @return float The total weight of the order in kg.
		 *
		 * @since 1.0.0
		 * @access public
		 */
		public function get_order_weight( $order_id ) {

			$weight = 0;

			$order = wc_get_order( $order_id );

			if ( ! $order || is_wp_error( $order ) ) {
				return $weight;
			}

			if ( count( $order->get_items() ) > 0 ) {
				foreach ( $order->get_items() as $item ) {
					if ( isset( $item['product_id'] ) && $item['product_id'] > 0 ) {
						if ( is_object( $item ) ) {
							$_product = $item->get_product();
							if ( ! $_product->is_virtual() ) {
								$weight += (float) $_product->get_weight() * (int) $item['qty'];
							}
						}
					}
				}
			}

			return $weight;
		}


		/**
		 * Determines if the current page requires the modal scripts.
		 *
		 * @return bool True if the current page requires modal scripts, false otherwise.
		 *
		 * @since 1.0.0
		 * @access public
		 */
		public function is_required_pages_for_modal() {
			global $pagenow, $post_type;
			$current_screen = get_current_screen();

			if ( 'shop_order' === $post_type ) {
				if ( 'post.php' === $pagenow || 'post-new.php' === $pagenow ) {
					return true;
				}
			}

			if ( 'shop_order_placehold' === $post_type ) {
				return true;
			}

			if ( is_a( $current_screen, 'WP_Screen' ) && 'woocommerce_page_wc-orders' === $current_screen->id ) {
				return true;
			}

			if ( is_checkout() || has_block( 'woocommerce/checkout' ) || get_option( 'easypack_debug_mode_enqueue_scripts' ) === 'yes' ) {
				return true;
			}

			if ( is_a( $current_screen, 'WP_Screen' ) && 'inpost_page_easypack_shipment' === $current_screen->id ) {
				return true;
			}

			if ( is_a( $current_screen, 'WP_Screen' ) && 'woocommerce_page_wc-settings' === $current_screen->id ) {
				if ( isset( $_GET['tab'] ) && 'easypack_general' === $_GET['tab'] ) {
					return true;
				}
			}

			return false;
		}


		/**
		 * Get configured Inpost methods
		 *
		 * @return array
		 */
		public function get_inpost_methods(): array {

			$configured_shipping_methods = array();

			$delivery_zones = $this->get_cached_zones();

			foreach ( (array) $delivery_zones as $key => $the_zone ) {
				if ( isset( $the_zone['shipping_methods'] ) ) {
					foreach ( $the_zone['shipping_methods'] as $configured_method ) {
						if ( 0 === strpos( $configured_method->id, 'easypack_' ) ) {
							$configured_shipping_methods[ $configured_method->instance_id ]['user_title']           = $configured_method->title;
							$configured_shipping_methods[ $configured_method->instance_id ]['method_title']         = $configured_method->id;
							$configured_shipping_methods[ $configured_method->instance_id ]['method_title_with_id'] = $configured_method->id . ':' . $configured_method->instance_id;
							$configured_shipping_methods[ $configured_method->instance_id ]['inpost_title']         = $configured_method->id;

							$logo_src = $this->get_shipping_method_logo_src( $configured_method->id, $configured_method->instance_id );
							$configured_shipping_methods[ $configured_method->instance_id ]['inpost_icon']    = ! empty( $logo_src ) ? $logo_src : false;
							$configured_shipping_methods[ $configured_method->instance_id ]['delivery_terms'] = $this->get_shipping_method_delivery_terms( $configured_method->id, $configured_method->instance_id );

							if ( 0 === strpos( $configured_method->id, 'easypack_parcel_machines' ) ) {
								$configured_shipping_methods[ $configured_method->instance_id ]['need_map'] = 1;
							}
						} elseif ( 0 === strpos( $configured_method->id, 'flexible_shipping' ) ) {
							// Integration with Flexible Shipping.
							$linked_method = EasyPack_Helper()->get_method_linked_to_fs_by_instance_id( $configured_method->instance_id );
							if ( 0 === strpos( $linked_method, 'easypack_' ) ) {
								$configured_shipping_methods[ $configured_method->instance_id ]['user_title']           = $configured_method->title;
								$configured_shipping_methods[ $configured_method->instance_id ]['method_title']         = $configured_method->id;
								$configured_shipping_methods[ $configured_method->instance_id ]['method_title_with_id'] = $configured_method->id . ':' . $configured_method->instance_id;
								$configured_shipping_methods[ $configured_method->instance_id ]['inpost_title']         = $linked_method;

								$configured_shipping_methods[ $configured_method->instance_id ]['inpost_icon'] = EasyPack()->getPluginImages() . 'logo/small/white.png';

								if ( 0 === strpos( $linked_method, 'easypack_parcel_machines' ) ) {
									$configured_shipping_methods[ $configured_method->instance_id ]['need_map'] = 1;
								}
							}
						}
					}
				}
			}

			return $configured_shipping_methods;
		}


		/**
		 * Set order status completed
		 *
		 * @param int $order_id $order_id.
		 * @return void
		 */
		public function set_order_status_completed( $order_id ) {

			if ( get_option( 'easypack_set_order_completed' ) === 'yes' ) {
				$order = wc_get_order( $order_id );
				if ( $order ) {
					$current_order_status = $order->get_status();
					if ( 'completed' !== $current_order_status ) {
						$order->update_status( 'wc-completed' );
					}
				}
			}
		}


		/**
		 * Determines the appropriate parcel size for an order.
		 *
		 * @param int $order_id The order ID.
		 * @return string The parcel size ('small', 'medium', or 'large').
		 *
		 * @since 1.0.0
		 * @access public
		 */
		public function get_parcel_size_from_settings( $order_id ) {

			$size = 'small';

			$tem_array = array();

			$order = wc_get_order( $order_id );

			$shipping_method = '';

			if ( $order && ! is_wp_error( $order ) ) {

				$shipping_methods = $order->get_shipping_methods();

				if ( ! empty( $shipping_methods ) && is_array( $shipping_methods ) ) {
					foreach ( $shipping_methods as $method ) {
						$shipping_method = $method->get_method_id();
					}
				}

				$items = $order->get_items();

				if ( ! empty( $items ) ) {

					foreach ( $items as $item ) {

						// Compatibility for woocommerce 3+.
						$product_id = version_compare( WC_VERSION, '3.0', '<' ) ? $item['product_id'] : $item->get_product_id();

						$size_from_product = get_post_meta( $product_id, 'woo_inpost_parcel_dimensions', true );

						if ( ! empty( $size_from_product ) ) {
							$tem_array[] = $size_from_product;
						}
					}
				}
			}

			// define size as biggest value among the products in the order.
			if ( ! empty( $tem_array ) ) {

				$tem_array = array_unique( $tem_array );

				if ( in_array( 'large', $tem_array, true ) ) {
					return 'large';
				} elseif ( in_array( 'medium', $tem_array, true ) ) {
					return 'medium';
				} elseif ( in_array( 'small', $tem_array, true ) ) {
					return 'small';
				}

				// or get size from global settings.
			} elseif ( ! empty( $shipping_method ) ) {

				if ( 'easypack_shipping_courier_c2c' === $shipping_method
					|| 'easypack_shipping_courier_c2c_cod' === $shipping_method
				) {
					$size = $this->get_default_size_c2c( $order_id );
				} else {
					$size = get_option( 'easypack_default_package_size' );
				}
			} else {
				$size = get_option( 'easypack_default_package_size' );
			}

			return $size;
		}


		/**
		 * Gets all active InPost shipping methods from WooCommerce zones.
		 *
		 * @return array Array of active shipping methods with instance IDs as keys and titles as values.
		 *
		 * @since 1.0.0
		 * @access public
		 */
		public function get_active_shipping_methods(): array {

			$configured_shipping_methods = array();
			$delivery_zones              = \WC_Shipping_Zones::get_zones();
			foreach ( (array) $delivery_zones as $key => $the_zone ) {
				// only for zone "PL".
				if ( is_array( $the_zone['shipping_methods'] ) ) {
					foreach ( $the_zone['shipping_methods'] as $configured_method ) {
						// only active methods.
						if ( isset( $configured_method->enabled ) && 'yes' === $configured_method->enabled ) {
							if ( 0 === strpos( $configured_method->id, 'easypack_' ) ) {
								$configured_shipping_methods[ $configured_method->instance_id ] = $configured_method->title;

							} elseif ( 0 === strpos( $configured_method->id, 'flexible_shipping' ) ) {
								// Integration with Flexible Shipping.
								$linked_method = EasyPack_Helper()->get_method_linked_to_fs_by_instance_id( $configured_method->instance_id );
								if ( 0 === strpos( $linked_method, 'easypack_' ) ) {
									$configured_shipping_methods[ $configured_method->instance_id ] = $configured_method->title;
								}
							}
						}
					}
				}
			}

			return $configured_shipping_methods;
		}


		/**
		 * Retrieves saved additional packages for an order.
		 *
		 * @param int $order_id The order ID.
		 * @return array Array of additional packages or empty array if none found.
		 *
		 * @access public
		 */
		public function get_saved_additional_packages( $order_id ) {

			$additional_packages = get_post_meta( $order_id, '_easypack_additional_packages', true );

			if ( empty( $additional_packages ) ) {
				return array();
			}

			if ( is_array( $additional_packages ) ) {
				return $additional_packages;
			}

			if ( ! is_string( $additional_packages ) ) {
				return array();
			}

			$unserialized = @unserialize( $additional_packages, array( 'allowed_classes' => false ) );

			return is_array( $unserialized ) ? $unserialized : array();
		}


		/**
		 * Generates and outputs a posting confirmation PDF for selected orders.
		 *
		 * @return void
		 *
		 * @since 1.0.0
		 * @access public
		 */
		public function print_posting_confirmation() {
			$orders = isset( $_POST['easypack_parcel'] ) ? (array) $_POST['easypack_parcel'] : array();
			$orders = array_map( 'sanitize_text_field', $orders );

			if ( empty( $orders ) ) {
				return;
			}

			$shipment_service = EasyPack()->get_shipment_service();
			$shipment_ids     = array();

			foreach ( $orders as $order ) {
				$inpost_internal_data = $shipment_service->get_shipment_by_order_id( (int) $order );

				if ( $inpost_internal_data && is_object( $inpost_internal_data ) ) {
					$shipment_ids[] = $inpost_internal_data->getInternalData()->getInpostId();
				}
			}

			$this->post_confirmation_pdf( $shipment_ids );
		}


		/**
		 * Check if used any Sequential Order Numbers
		 *
		 * @param int $order_id $order_id.
		 * @return mixed
		 */
		public function get_maybe_custom_reference_number( $order_id ) {

			$reference_number = $order_id;

			if ( class_exists( 'Wt_Advanced_Order_Number' ) || class_exists( 'WC_Seq_Order_Number' ) ) {
				$order = wc_get_order( $order_id );
				if ( $order && ! is_wp_error( $order ) ) {
					if ( ! empty( $order->get_meta( '_order_number' ) ) ) {
						$reference_number = $order->get_meta( '_order_number' );
					}
				}
			}

			return $reference_number;
		}


		/**
		 * Validate if orders have inpost statuses or inpost IDs
		 *
		 * @param array $arr $arr.
		 * @return array
		 */
		public function validate_order_ids_before_get_labels_from_api( array $arr ): array {
			// we need validate chosen orders if they already has status which is allowing to get labels.
			$validated_ids = array();

			foreach ( $arr as $order_id ) {

				$is_tracking_exists = get_post_meta( $order_id, '_easypack_parcel_tracking', true );
				if ( ! $is_tracking_exists ) {
					$order = wc_get_order( $order_id );
					if ( $order && ! is_wp_error( $order ) ) {
						$is_tracking_exists = $order->get_meta( '_easypack_parcel_tracking' );
					}
				}

				if ( ! empty( $is_tracking_exists ) ) {
					$validated_ids[] = $order_id;
				} else {

					$shipment = $this->get_woo_order_meta( $order_id, '_shipx_shipment_object' );

					if ( ! $shipment instanceof ShipX_Shipment_Model ) {
						if ( 'yes' === get_option( 'woocommerce_custom_orders_table_enabled' ) ) {
							$from_order_meta_raw = isset( get_post_meta( $order_id )['_shipx_shipment_object'][0] )
								? get_post_meta( $order_id )['_shipx_shipment_object'][0]
								: '';

							if ( ! empty( $from_order_meta_raw ) ) {
								$shipment = unserialize(
									$from_order_meta_raw,
									array(
										'allowed_classes' => array( ShipX_Shipment_Model::class ),
									)
								);
							}
						}
					}

					if ( is_object( $shipment ) && $shipment instanceof ShipX_Shipment_Model ) {
						$inpost_id = $shipment->getInternalData()->getInpostId();
						if ( ! empty( $inpost_id ) ) {
							$validated_ids[] = $order_id;
						}
					}
				}
			}

			return $validated_ids;
		}



		/**
		 * Get insurance amount
		 *
		 * @param int $order_id $order_id.
		 *
		 * @return false|float
		 */
		public function get_insurance_amount( $order_id ) {

			/**
			 * Priority to get insurance:
			 * 1) from shipping method settings if method linked to FS.
			 * 2) from shipping method settings.
			 * 3) from old settings (deleted already).
			 */

			$insurance_amount = false;

			$order = wc_get_order( $order_id );

			if ( ! $order || is_wp_error( $order ) ) {
				return false;
			}

			$shipping_method_name = '';
			$method_instance_id   = '';

			foreach ( $order->get_shipping_methods() as $shipping_method ) {
				$method_instance_id   = $shipping_method->get_instance_id();
				$shipping_method_name = $shipping_method->get_method_id();
			}

			$shipping_method_settings_name = 'woocommerce_' . $shipping_method_name . '_' . $method_instance_id . '_settings';

			$shipping_method_settings = get_option( $shipping_method_settings_name );

			if ( $this->is_flexible_shipping_activated() ) {
				if ( isset( $shipping_method_settings['fs_insurance_inpost_pl'] ) && 'yes' === $shipping_method_settings['fs_insurance_inpost_pl'] ) {
					$insurance_amount = $order->get_total();
				}
				if ( isset( $shipping_method_settings['fs_insurance_inpost_pl'] ) && 'no' === $shipping_method_settings['fs_insurance_inpost_pl'] ) {
					$insurance_amount = floatval( $shipping_method_settings['fs_insurance_value_inpost_pl'] );
				}
			}

			if ( is_bool( $insurance_amount ) && ! $insurance_amount ) {

				if ( ! empty( $shipping_method_settings['insurance_inpost_pl'] ) ) {
					if ( 'yes' === $shipping_method_settings['insurance_inpost_pl'] ) {
						$insurance_amount = $order->get_total();
					}
					if ( 'no' === $shipping_method_settings['insurance_inpost_pl'] ) {
						$insurance_amount = floatval( $shipping_method_settings['insurance_value_inpost_pl'] );
					}
				} else {
					// From old general settings.
					$insurance_mode = get_option( 'easypack_insurance_amount_mode', '2' );
					if ( '1' === $insurance_mode ) {
						$insurance_amount = $order->get_total();
					}
					if ( '2' === $insurance_mode ) {
						$insurance_amount = floatval( get_option( 'easypack_insurance_amount_default' ) );
					}
				}
			}

			return $insurance_amount ? $insurance_amount : 0.00;
		}


		/**
		 * Determines if the current page is related to admin orders or plugin settings.
		 *
		 * @return bool True if on an admin orders or plugin settings page, false otherwise.
		 *
		 * @since 1.0.0
		 * @access public
		 */
		public function is_admin_orders_or_plugin_settings_related_page() {

			if ( ! is_admin() ) {
				return;
			}

			if ( ! function_exists( 'get_current_screen' ) ) {
				return;
			}

			global $pagenow, $post_type, $post;
			$current_screen = get_current_screen();

			$order_id = null;

			if ( 'shop_order' === $post_type || 'shop_order_placehold' === $post_type || 'shop_order' === $current_screen->post_type ) {
				if ( isset( $_GET['action'] ) && 'edit' === $_GET['action'] ) {
					if ( isset( $_GET['page'] ) && 'wc-orders' === $_GET['page'] ) {
						$order_id = $_GET['id'] ? $_GET['id'] : null;
					} elseif ( isset( $_GET['post'] ) && is_numeric( $_GET['post'] ) ) {
						$order_id = $_GET['post'];
					}
				}
			}

			if ( $order_id ) {
				$order = wc_get_order( $order_id );
				if ( $order && is_a( $order, 'WC_Order' ) ) {

					if ( ! empty( $this->get_woo_order_meta( $order_id, '_parcel_machine_id' ) ) ) {
						return true;
					}
				}
			}

			if ( is_a( $current_screen, 'WP_Screen' ) && 'woocommerce_page_wc-settings' === $current_screen->id ) {
				if ( isset( $_GET['tab'] ) && 'easypack_general' === $_GET['tab'] ) {
					return true;
				}
			}

			return false;
		}



		public function clear_zones_cache() {
			$this->cached_zones = null;
		}



		private function get_cached_zones() {
			if ( empty( $this->cached_zones ) ) {
				if ( class_exists( 'WC_Shipping_Zones' ) ) {
					$this->cached_zones = \WC_Shipping_Zones::get_zones();
				}
			}

			if ( empty( $this->cached_zones ) ) {
				if ( class_exists( 'WC_Shipping_Zones' ) ) {
					$worldwide_zone = \WC_Shipping_Zones::get_zone( 0 );
					if ( ! empty( $worldwide_zone ) ) {
						// Add the worldwide zone to our cached zones.
						$this->cached_zones[0] = array(
							'zone_id'                 => 0,
							'zone_name'               => $worldwide_zone->get_zone_name(),
							'zone_order'              => 0,
							'zone_locations'          => $worldwide_zone->get_zone_locations(),
							'zone_location_code'      => array(),
							'formatted_zone_location' => $worldwide_zone->get_formatted_location(),
							'shipping_methods'        => $worldwide_zone->get_shipping_methods(),
						);
					}
				}
			}

			return $this->cached_zones;
		}


		/**
		 * Retrieves the locker ID associated with an order, checking both order metadata and post meta.
		 *
		 * First attempts to retrieve the locker ID from the order's metadata using `_parcel_machine_id`.
		 * If not found, it falls back to retrieving it from the order's post meta using the same key.
		 *
		 * @param int|string $order_id The ID of the order.
		 *
		 * @return string|null The locker ID if found, otherwise null.
		 */
		public function get_locker_id_from_meta( $order_id ) {
			$locker_id = null;

			$order = wc_get_order( $order_id );
			if ( $order && ! is_wp_error( $order ) ) {
				$locker_id = $order->get_meta( '_parcel_machine_id' );
			}

			if ( empty( $locker_id ) ) {
				$locker_id = get_post_meta( $order_id, '_parcel_machine_id', true );
			}

			return $locker_id;
		}



		/**
		 * Retrieves the InPost ID associated with a WooCommerce order.
		 *
		 * First attempts to get the InPost ID from the order meta using WC Order object.
		 * If unsuccessful, tries to retrieve it from post meta, handling both standard
		 * and custom orders table scenarios.
		 *
		 * @param int $order_id The WooCommerce order ID.
		 *
		 * @return string|int|null The InPost shipment ID if found, null otherwise
		 *
		 * @throws WP_Error Potentially from wc_get_order().
		 */
		public function get_inpost_id_by_order( $order_id ) {

			$inpost_id = null;

			$order = wc_get_order( $order_id );
			if ( $order && ! is_wp_error( $order ) ) {
				$shipment = $order->get_meta( '_shipx_shipment_object' );
				if ( is_object( $shipment ) && $shipment instanceof ShipX_Shipment_Model ) {
					$inpost_id = $shipment->getInternalData()->getInpostId();
				}
			}

			if ( ! $inpost_id ) {

				$shipment = $this->get_woo_order_meta( $order_id, '_shipx_shipment_object' );

				if ( ! $shipment instanceof ShipX_Shipment_Model ) {
					if ( 'yes' === get_option( 'woocommerce_custom_orders_table_enabled' ) ) {
						$from_order_meta_raw = isset( get_post_meta( $order_id )['_shipx_shipment_object'][0] )
							? get_post_meta( $order_id )['_shipx_shipment_object'][0]
							: '';

						if ( ! empty( $from_order_meta_raw ) ) {
							$shipment = unserialize(
								$from_order_meta_raw,
								array(
									'allowed_classes' => array( ShipX_Shipment_Model::class ),
								)
							);
						}
					}
				}

				if ( is_object( $shipment ) && $shipment instanceof ShipX_Shipment_Model ) {
					$inpost_id = $shipment->getInternalData()->getInpostId();
				}
			}

			return $inpost_id;
		}

		/**
		 * Gets courier templates formatted for a select dropdown.
		 *
		 * @return array Array of template options with keys as IDs and values as template names.
		 *
		 * @since 1.7.2
		 * @access public
		 */
		public function get_templates_for_select() {
			$select_options = array();

			$all_templates = get_option( 'easypack_courier_tmplts_dmtemplates', array() );
			foreach ( $all_templates as $key => $template ) {
				if ( ! empty( $template['name'] ) ) {
					$select_options[ $key ] = $template['name'];
				}
			}

			return $select_options;
		}


		/**
		 * Retrieves the logo source URL for a specific shipping method.
		 *
		 * @param string $shipping_method_id The shipping method ID.
		 * @param int    $shipping_method_instance_id The shipping method instance ID.
		 * @return string The URL to the shipping method logo.
		 *
		 * @since 1.7.2
		 * @access public
		 */
		public function get_shipping_method_logo_src( $shipping_method_id, $shipping_method_instance_id ) {
			$shipping_method_settings_name = 'woocommerce_' . $shipping_method_id . '_' . $shipping_method_instance_id . '_settings';
			$shipping_method_settings      = get_option( $shipping_method_settings_name );

			$logo_src = EasyPack()->getPluginImages() . 'logo/small/white.png';
			if ( ! empty( $shipping_method_settings['logo_upload'] ) ) {
				$custom_logo = $shipping_method_settings['logo_upload'];
				return esc_url( $custom_logo );
			}

			if ( 'easypack_parcel_machines_weekend' === $shipping_method_id
				|| 'easypack_parcel_machines_weekend_cod' === $shipping_method_id
			) {
				$logo_src = EasyPack()->getPluginImages() . 'logo/inpost-paczka-w-weekend.png';
			}

			return esc_url( $logo_src );
		}


		/**
		 * Retrieves the delivery terms for a specific shipping method.
		 *
		 * @param string $shipping_method_id The shipping method ID.
		 * @param int    $shipping_method_instance_id The shipping method instance ID.
		 * @return string|bool The delivery terms text or false if not set.
		 *
		 * @since 1.7.2
		 * @access public
		 */
		public function get_shipping_method_delivery_terms( $shipping_method_id, $shipping_method_instance_id ) {
			$shipping_method_settings_name = 'woocommerce_' . $shipping_method_id . '_' . $shipping_method_instance_id . '_settings';
			$shipping_method_settings      = get_option( $shipping_method_settings_name );

			$delivery_terms = false;
			if ( ! empty( $shipping_method_settings['delivery_terms'] ) ) {
				$delivery_terms = trim( $shipping_method_settings['delivery_terms'] );

			}

			return $delivery_terms;
		}


		/**
		 * Retrieves order meta data with fallback to post meta.
		 *
		 * @param int    $order_id The order ID.
		 * @param string $meta_key The meta key to retrieve.
		 * @return mixed|null The meta value or null if not found.
		 *
		 * @since 1.7.3
		 * @access public
		 */
		public function get_woo_order_meta( $order_id, $meta_key ) {

			$res = null;

			if ( empty( $order_id ) || empty( $meta_key ) ) {
				return null;
			}

			$order = wc_get_order( $order_id );
			if ( ! $order || is_wp_error( $order ) ) {
				return null;
			}

			$res = $order->get_meta( $meta_key );

			if ( empty( $res ) ) {
				$res = get_post_meta( $order_id, $meta_key, true );
			}

			return $res;
		}



		/**
		 * Get max parcel size among the products in cart
		 */
		public function get_max_gabaryt( $package ) {

			if ( isset( $package['contents'] ) && ! empty( $package['contents'] ) ) {

				$possible_gabaryts = array();

				foreach ( $package['contents'] as $cart_item_key => $cart_item ) {
					$product_id          = $cart_item['product_id'];
					$possible_gabaryts[] = get_post_meta( $product_id, EasyPack::ATTRIBUTE_PREFIX . '_parcel_dimensions', true );

				}

				if ( ! empty( $possible_gabaryts ) ) {
					if ( in_array( 'large', $possible_gabaryts ) ) {
						return 'c';
					}
					if ( in_array( 'medium', $possible_gabaryts ) ) {
						return 'b';
					}
				}
			}
			// by default.
			return 'a';
		}


		/**
		 * Calculates the total subtotal of items in a package.
		 *
		 * @param array $items The items in the package.
		 * @return float The total subtotal including tax.
		 *
		 * @since 1.7.5
		 * @access public
		 */
		public function package_subtotal( $items ) {
			$subtotal = 0;
			foreach ( $items as $item ) {
				$subtotal += $item['line_subtotal'] + $item['line_subtotal_tax'];
			}

			return $subtotal;
		}


		/**
		 * Calculates the total quantity of products in a package.
		 *
		 * @param array $items The items in the package.
		 * @return int The total product quantity.
		 *
		 * @since 1.7.5
		 * @access public
		 */
		public function package_product_qty( $items ) {
			$package_product_qty = 0;
			foreach ( $items as $item ) {
				$package_product_qty += intval( $item['quantity'] );
			}

			return $package_product_qty;
		}


		/**
		 * Calculates the total weight of a package.
		 *
		 * @param array $items The items in the package.
		 * @return float The total weight.
		 *
		 * @since 1.7.5
		 * @access public
		 */
		public function package_weight( $items ) {
			$weight = 0;
			foreach ( $items as $item ) {
				if ( ! empty( $item['data']->get_weight() ) ) {
					$weight += floatval( $item['data']->get_weight() ) * $item['quantity'];
				}
			}

			return $weight;
		}

		/**
		 * Finds the key with the largest value in an array.
		 *
		 * @param array $array The input array.
		 * @return array An array containing the key with the largest value.
		 *
		 * @since 1.7.5
		 * @access public
		 */
		public function find_key_with_biggest_value( $array ) {
			if ( empty( $array ) ) {
				return array();
			}

			$max_value = max( $array );
			$max_key   = array_search( $max_value, $array );

			/*
			return [
				'key' => $max_key,
				'value' => $max_value
			];*/

			return array( $max_key );
		}


		/**
		 * Gets the webhook URL for InPost order updates.
		 *
		 * @return string The webhook URL.
		 *
		 * @since 1.7.5
		 * @access public
		 */
		public function get_webhook_url() {
			$url = home_url() . '/wp-json/inpost_pl/v1/order/update/';

			return $url;
		}

		/**
		 * Refreshes the shipment status for an order.
		 *
		 * @param int $order_id The order ID.
		 * @return array The response from the status refresh operation.
		 *
		 * @since 1.7.5
		 * @access public
		 */
		public function refresh_shipment_status( $order_id ) {

			$response = array();

			$shipment = $this->get_woo_order_meta( $order_id, '_shipx_shipment_object' );

			if ( empty( $shipment ) || false === $shipment instanceof ShipX_Shipment_Model ) {
				return array();
			}

			// do not update if webhook is enabled and webhook status exists.
			if ( 'yes' === get_option( 'easypack_enable_webhooks' ) ) {
				$webhook_status = $this->get_woo_order_meta( $order_id, 'easypack_webhook_status' );
				if ( ! empty( $webhook_status ) ) {
					return array();
				}
			}

			$status_srv = EasyPack()->get_shipment_status_service();
			$response   = $status_srv->refreshStatus( $shipment );

			return $response;
		}


		/**
		 * Gets the default send method for an order.
		 *
		 * @param int $order_id The order ID.
		 * @return string The default send method.
		 *
		 * @since 1.7.6
		 * @access public
		 */
		public function get_default_send_method( $order_id ) {

			$send_method = 'courier';

			$order = wc_get_order( $order_id );

			if ( ! $order || is_wp_error( $order ) ) {
				return $send_method;
			}

			$shipping_method_name = '';
			$method_instance_id   = '';

			foreach ( $order->get_shipping_methods() as $shipping_method ) {
				$method_instance_id   = $shipping_method->get_instance_id();
				$shipping_method_name = $shipping_method->get_method_id();
			}

			$shipping_method_settings_name = 'woocommerce_' . $shipping_method_name . '_' . $method_instance_id . '_settings';

			$shipping_method_settings = get_option( $shipping_method_settings_name );

			if ( ! empty( $shipping_method_settings['default_send_method'] ) ) {
				$send_method = $shipping_method_settings['default_send_method'];
			} elseif ( ! empty( get_option( 'easypack_default_send_method' ) ) ) {
				$send_method = get_option( 'easypack_default_send_method' );

				if ( false !== strpos( $shipping_method_name, 'courier' ) ) {
					if ( 'easypack_shipping_courier_c2c' !== $shipping_method_name && 'easypack_shipping_courier_c2c_cod' !== $shipping_method_name ) {
						if ( ! in_array( $send_method, array( 'pop', 'courier' ), true ) ) {
							$send_method = 'courier';
						}
					}
				}
			}

			return $send_method;
		}


		/**
		 * Gets the default package size for C2C shipments.
		 *
		 * @param int $order_id The order ID.
		 * @return string The default package size.
		 *
		 * @since 1.7.6
		 * @access public
		 */
		public function get_default_size_c2c( $order_id ) {

			$default_size = 'small';

			$order = wc_get_order( $order_id );

			if ( ! $order || is_wp_error( $order ) ) {
				return $default_size;
			}

			$shipping_method_name = '';
			$method_instance_id   = '';

			foreach ( $order->get_shipping_methods() as $shipping_method ) {
				$method_instance_id   = $shipping_method->get_instance_id();
				$shipping_method_name = $shipping_method->get_method_id();
			}

			$shipping_method_settings_name = 'woocommerce_' . $shipping_method_name . '_' . $method_instance_id . '_settings';

			$shipping_method_settings = get_option( $shipping_method_settings_name );

			if ( ! empty( $shipping_method_settings['default_package_size_c2c'] ) ) {
				$default_size = $shipping_method_settings['default_package_size_c2c'];
			} elseif ( ! empty( get_option( 'easypack_default_package_size_c2c' ) ) ) {
				$default_size = get_option( 'easypack_default_package_size_c2c' );
			}

			return $default_size;
		}


		/**
		 * Gets the source of courier dimensions for an order.
		 *
		 * @param int $order_id The order ID.
		 * @return string The source of courier dimensions.
		 *
		 * @since 1.7.6
		 * @access public
		 */
		public function get_source_of_courier_dimensions( $order_id ) {

			$default_source = '';

			$order = wc_get_order( $order_id );

			if ( ! $order || is_wp_error( $order ) ) {
				return $default_source;
			}

			$shipping_method_name = '';
			$method_instance_id   = '';

			foreach ( $order->get_shipping_methods() as $shipping_method ) {
				$method_instance_id   = $shipping_method->get_instance_id();
				$shipping_method_name = $shipping_method->get_method_id();
			}

			$shipping_method_settings_name = 'woocommerce_' . $shipping_method_name . '_' . $method_instance_id . '_settings';

			$shipping_method_settings = get_option( $shipping_method_settings_name );

			if ( ! empty( $shipping_method_settings['source_of_parcel_dimensions'] ) ) {
				$default_source = $shipping_method_settings['source_of_parcel_dimensions'];
			}

			return $default_source;
		}



		/**
		 * Gets courier parcel dimensions for an order.
		 *
		 * @param int         $order_id The order ID.
		 * @param string|null $source The source of dimensions.
		 * @return array The dimensions array with length, width, height, weight, and non_standard properties.
		 *
		 * @since 1.7.6
		 * @access public
		 */
		public function get_courier_parcel_dimensions( $order_id, $source = null ) {

			$dimensions['length']       = '';
			$dimensions['width']        = '';
			$dimensions['height']       = '';
			$dimensions['weight']       = '';
			$dimensions['non_standard'] = 'no';

			$order = wc_get_order( $order_id );

			if ( ! $order || is_wp_error( $order ) ) {
				return $dimensions;
			}

			if ( empty( $source ) ) {
				// compatibility with old settings.
				if ( 'yes' === get_option( 'easypack_set_default_courier_dimensions' ) ) {
					$dimensions = get_option( 'easypack_default_courier_dimensions' );
				} else {
					$dimensions = $this->get_courier_parcel_data_from_template();
				}
			}

			if ( 'courier_default_dimensions' === $source ) {
				if ( ! empty( get_option( 'easypack_default_courier_dimensions' ) ) ) {
					$dimensions = get_option( 'easypack_default_courier_dimensions' );
				}
			}

			if ( 'courier_template' === $source ) {

				$dimensions = $this->get_courier_parcel_data_from_template();
			}

			if ( 'courier_dimensions_from_product' === $source ) {

				$items = $order->get_items();

				if ( ! $items || ! is_array( $items ) || 0 === count( $items ) ) {
					return $dimensions;
				}

				if ( 1 === count( $items ) ) {
					$dimensions                 = $this->calculate_dimensions_for_single_product( $items );
					$dimensions['non_standard'] = 'no';

				} else {
					$dimensions                 = $this->calculate_dimensions_for_multiple_product( $items );
					$dimensions['non_standard'] = 'no';
				}
			}

			return $dimensions;
		}


		/**
		 * Calculate dimensions for a single product.
		 *
		 * @since 1.7.6
		 *
		 * @param array $items Order items.
		 * @return array Array with length, width, height (in mm) and weight (in kg).
		 */
		public function calculate_dimensions_for_single_product( $items ) {
			$item = reset( $items ); // Get first item.

			$calculated_length = 0;
			$calculated_width  = 0;
			$calculated_height = 0;
			$total_weight      = 0;

			$product = $item->get_product();

			if ( is_object( $product ) ) {
				$quantity = (int) $item->get_quantity();

				// Get weight in kg.
				$weight       = $product->get_weight();
				$total_weight = ( $weight && is_numeric( $weight ) ) ? floatval( $weight ) * $quantity : 0;

				// Get dimensions in cm (WooCommerce default) and validate.
				$length = $product->get_length();
				$width  = $product->get_width();
				$height = $product->get_height();

				$length = ( $length && is_numeric( $length ) ) ? floatval( $length ) : 0;
				$width  = ( $width && is_numeric( $width ) ) ? floatval( $width ) : 0;
				$height = ( $height && is_numeric( $height ) ) ? floatval( $height ) : 0;

				// Convert cm to mm.
				$length_mm = $length * 10;
				$width_mm  = $width * 10;
				$height_mm = $height * 10;

				// For multiple quantities, arrange in most compact way.
				if ( $quantity > 1 && $length_mm > 0 && $width_mm > 0 && $height_mm > 0 ) {
					// Stack items along the smallest dimension for compact packaging.
					$dimensions_array = array( $length_mm, $width_mm, $height_mm );
					sort( $dimensions_array ); // Sort to find smallest dimension.

					// Multiply the smallest dimension by quantity (stacking).
					$dimensions_array[0] = $dimensions_array[0] * $quantity;

					// Sort again to get final dimensions in ascending order.
					sort( $dimensions_array );

					$calculated_length = $dimensions_array[2]; // Largest.
					$calculated_width  = $dimensions_array[1]; // Middle.
					$calculated_height = $dimensions_array[0]; // Smallest.
				} else {
					// Single item or no dimensions - just convert to mm.
					$calculated_length = $length_mm;
					$calculated_width  = $width_mm;
					$calculated_height = $height_mm;
				}
			}

			return array(
				'length' => round( $calculated_length, 1 ),
				'width'  => round( $calculated_width, 1 ),
				'height' => round( $calculated_height, 1 ),
				'weight' => round( $total_weight, 2 ),
			);
		}


		/**
		 * Calculate dimensions for multiple products.
		 *
		 * @param array $items Order items.
		 * @return array Array with length, width, height (in mm) and weight (in kg).
		 */
		public function calculate_dimensions_for_multiple_product( $items ) {
			$calculated_length = 0;
			$calculated_width  = 0;
			$calculated_height = 0;

			$total_weight = 0;
			$total_volume = 0;
			$max_length   = 0;
			$max_width    = 0;

			foreach ( $items as $item ) {
				$product = $item->get_product();

				if ( is_object( $product ) ) {
					$quantity = (int) $item->get_quantity();

					// Get weight.
					$weight        = $product->get_weight();
					$weight        = ( $weight && is_numeric( $weight ) ) ? floatval( $weight ) : 0;
					$total_weight += $weight * $quantity;

					// Get dimensions and validate.
					$length = $product->get_length();
					$width  = $product->get_width();
					$height = $product->get_height();

					$length = ( $length && is_numeric( $length ) ) ? floatval( $length ) : 0;
					$width  = ( $width && is_numeric( $width ) ) ? floatval( $width ) : 0;
					$height = ( $height && is_numeric( $height ) ) ? floatval( $height ) : 0;

					// Calculate volume only if all dimensions are present.
					if ( $length > 0 && $width > 0 && $height > 0 ) {
						$item_volume   = $length * $width * $height * $quantity;
						$total_volume += $item_volume;

						$max_length = max( $max_length, $length );
						$max_width  = max( $max_width, $width );
					}
				}
			}

			// Calculate combined dimensions based on total volume.
			if ( $total_volume > 0 && $max_length > 0 && $max_width > 0 ) {
				$calculated_length = $max_length;
				$calculated_width  = $max_width;
				$calculated_height = $total_volume / ( $calculated_length * $calculated_width );

				// Ensure height is not smaller than width for practical packaging.
				if ( $calculated_height > $calculated_width ) {
					$temp              = $calculated_width;
					$calculated_width  = $calculated_height;
					$calculated_height = $temp;
				}
			}

			// Convert from cm to mm.
			$calculated_length = $calculated_length * 10;
			$calculated_width  = $calculated_width * 10;
			$calculated_height = $calculated_height * 10;

			return array(
				'length' => round( $calculated_length, 1 ),
				'width'  => round( $calculated_width, 1 ),
				'height' => round( $calculated_height, 1 ),
				'weight' => round( $total_weight, 2 ),
			);
		}


		/**
		 * Conditionally sets the PWW parameter for shipments.
		 *
		 * Checks if PWW mode is enabled for the shipping method and if the
		 * selected point is a 24/7 point. If both conditions are met and
		 * current time is within PWW window, sets end_of_week_collection flag.
		 *
		 * @param int   $order_id The WooCommerce order ID.
		 * @param array $shipment_array The shipment data array.
		 * @return array Modified shipment array with PWW parameter if applicable.
		 */
		public function maybe_set_pww_param( $order_id, $shipment_array ) {

			$pww_enabled  = false;
			$is_pww_point = false;

			$order = wc_get_order( $order_id );

			if ( ! $order || is_wp_error( $order ) ) {
				return $shipment_array;
			}

			$shipping_method_name = '';
			$method_instance_id   = '';

			foreach ( $order->get_shipping_methods() as $shipping_method ) {
				$method_instance_id   = $shipping_method->get_instance_id();
				$shipping_method_name = $shipping_method->get_method_id();
			}

			$shipping_method_settings_name = 'woocommerce_' . $shipping_method_name . '_' . $method_instance_id . '_settings';
			$shipping_method_settings      = get_option( $shipping_method_settings_name );

			if ( ! empty( $shipping_method_settings['pww_mode'] ) ) {
				if ( 'yes' === $shipping_method_settings['pww_mode'] ) {
					$pww_enabled = true;
				}
			}

			if ( ! $pww_enabled ) {
				return $shipment_array;
			}

			$point = $shipment_array['custom_attributes']['target_point'];
			// $point = 'POP-RYP8'; example - not 24/7 point.

			if ( ! empty( $point ) ) {
				$point_data = $this->get_point_data( $point );
				if ( ! empty( $point_data['items'][0]['opening_hours'] ) ) {
					$opening_hours = $point_data['items'][0]['opening_hours'];
					if ( strpos( $opening_hours, '24/7' ) !== false ) {
						$is_pww_point = true;
					}
				}
			}

			if ( ! $is_pww_point ) {
				return $shipment_array;
			}

			if ( $this->is_pww_time() ) {
				$shipment_array['end_of_week_collection'] = true;
			}

			return $shipment_array;
		}


		/**
		 * Retrieves data for a specific InPost point by name.
		 *
		 * @param string $point_name The name of the point to retrieve.
		 * @return array The point data or empty array if not found.
		 *
		 * @since 1.7.7
		 * @access public
		 */
		public function get_point_data( $point_name ) {

			$point_data = array();

			$api = new EasyPack_API();
			if ( 'production' === get_option( 'easypack_api_environment' ) ) {
				$api_url = 'https://api.inpost.pl/v1/points?name=' . $point_name;
			} else {
				$api_url = 'https://sandbox-api-gateway-pl.easypack24.net/v1/points?name=' . $point_name;
			}

			$args = array(
				'method'  => 'GET',
				'headers' => array(
					'Authorization' => 'Bearer ' . get_option( 'easypack_token' ),
					'Content-Type'  => 'application/json',
				),
			);

			$response = wp_remote_get( $api_url, $args );
			$code     = wp_remote_retrieve_response_code( $response );

			if ( 200 === $code ) {

				$response_body = wp_remote_retrieve_body( $response );

				try {

					$point_data = json_decode( $response_body, true );

				} catch ( Exception $e ) {
					if ( function_exists( 'wc_get_logger' ) ) {
						\wc_get_logger()->debug( 'Error when get point data:', array( 'source' => 'inpost-pl-get-point' ) );
						\wc_get_logger()->debug( 'Point :' . $point_name, array( 'source' => 'inpost-pl-get-point' ) );
						\wc_get_logger()->debug( print_r( $e->getMessage(), true ), array( 'source' => 'inpost-pl-get-point' ) );
					}
				}
			} elseif ( function_exists( 'wc_get_logger' ) ) {
					\wc_get_logger()->debug( 'Error when get point data:', array( 'source' => 'inpost-pl-get-point' ) );
					\wc_get_logger()->debug( 'Response code :' . $code, array( 'source' => 'inpost-pl-get-point' ) );
					\wc_get_logger()->debug( print_r( $response, true ), array( 'source' => 'inpost-pl-get-point' ) );
			}

			return $point_data;
		}


		/**
		 * Checks if the current time is within the PWW time window.
		 *
		 * PWW time is defined as:
		 * - Thursday after 20:00 (inclusive)
		 * - Friday before 18:00 (exclusive)
		 *
		 * @return bool True if current time is within PWW time window, false otherwise.
		 */
		public function is_pww_time() {

			// Get current time in Warsaw timezone.
			$tz           = new \DateTimeZone( 'Europe/Warsaw' );
			$current_time = new \DateTimeImmutable( 'now', $tz );

			// Get current day of week (1 = Monday, 2 = Tuesday, etc.)
			$current_day = (int) $current_time->format( 'N' );

			// Get current hour and minute
			$current_hour   = (int) $current_time->format( 'G' );
			$current_minute = (int) $current_time->format( 'i' );

			// Check if it's Thursday after 20:00
			if ( 4 === $current_day && ( $current_hour > 20 || ( 20 === $current_hour && $current_minute >= 0 ) ) ) {
				return true;
			}

			// Check if it's Friday before 18:00
			if ( 5 === $current_day && ( $current_hour < 18 ) ) {
				return true;
			}

			return false;
		}
	}


endif;
