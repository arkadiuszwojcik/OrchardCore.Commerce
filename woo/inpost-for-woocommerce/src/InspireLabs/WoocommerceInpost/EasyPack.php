<?php

namespace InspireLabs\WoocommerceInpost;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use Exception;
use InspireLabs\WoocommerceInpost\admin\EasyPack_Custom_Product_List_Table;
use InspireLabs\WoocommerceInpost\EasyPack_Helper;
use InspireLabs\WoocommerceInpost\admin\Alerts;
use InspireLabs\WoocommerceInpost\admin\EasyPack_Product_Shipping_Method_Selector;
use InspireLabs\WoocommerceInpost\admin\EasyPack_Settings_General;
use InspireLabs\WoocommerceInpost\EmailFilters\NewOrderEmail;
use InspireLabs\WoocommerceInpost\EmailFilters\TrackingInfoEmail;
use InspireLabs\WoocommerceInpost\shipping\EasyPack_Shipping_Method_Courier;
use InspireLabs\WoocommerceInpost\shipping\EasyPack_Shipping_Method_Courier_C2C;
use InspireLabs\WoocommerceInpost\shipping\EasyPack_Shipping_Method_Courier_C2C_COD;
use InspireLabs\WoocommerceInpost\shipping\EasyPack_Shipping_Method_Courier_COD;
use InspireLabs\WoocommerceInpost\shipping\EasyPack_Shipping_Method_Courier_Local_Express;
use InspireLabs\WoocommerceInpost\shipping\EasyPack_Shipping_Method_Courier_Local_Express_COD;
use InspireLabs\WoocommerceInpost\shipping\EasyPack_Shipping_Method_Courier_Local_Standard;
use InspireLabs\WoocommerceInpost\shipping\EasyPack_Shipping_Method_Courier_Local_Standard_COD;
use InspireLabs\WoocommerceInpost\shipping\EasyPack_Shipping_Method_Courier_LSE;
use InspireLabs\WoocommerceInpost\shipping\EasyPack_Shipping_Method_Courier_LSE_COD;
use InspireLabs\WoocommerceInpost\shipping\EasyPack_Shipping_Method_Courier_Palette;
use InspireLabs\WoocommerceInpost\shipping\EasyPack_Shipping_Method_Courier_Palette_COD;
use InspireLabs\WoocommerceInpost\shipping\EasyPack_Shipping_Method_EsmartMix;
use InspireLabs\WoocommerceInpost\shipping\EasyPack_Shipping_Parcel_Machines_Weekend_COD;
use InspireLabs\WoocommerceInpost\shipping\Easypack_Shipping_Rates;
use InspireLabs\WoocommerceInpost\shipping\EasyPack_Shippng_Parcel_Machines;
use InspireLabs\WoocommerceInpost\shipping\EasyPack_Shippng_Parcel_Machines_COD;
use InspireLabs\WoocommerceInpost\shipx\models\shipment\ShipX_Shipment_Cod_Model;
use InspireLabs\WoocommerceInpost\shipx\models\shipment\ShipX_Shipment_Contstants;
use InspireLabs\WoocommerceInpost\shipx\services\courier_pickup\ShipX_Courier_Pickup_Service;
use InspireLabs\WoocommerceInpost\shipx\services\organization\ShipX_Organization_Service;
use InspireLabs\WoocommerceInpost\shipx\services\shipment\ShipX_Shipment_Price_Calculator_Service;
use InspireLabs\WoocommerceInpost\shipx\services\shipment\ShipX_Shipment_Service;
use InspireLabs\WoocommerceInpost\shipx\services\shipment\ShipX_Shipment_Status_Service;
use stdClass;
use WC_Order;
use WC_Order_Item_Shipping;
use WC_Shipping_Method;
use InspireLabs\WoocommerceInpost\shipping\EasyPack_Shipping_Parcel_Machines_Weekend;
use InspireLabs\WoocommerceInpost\shipping\EasyPack_Shipping_Parcel_Machines_Economy;
use InspireLabs\WoocommerceInpost\shipping\EasyPack_Shipping_Parcel_Machines_Economy_COD;
use InspireLabs\WoocommerceInpost\EasyPackBulkOrders;
use Automattic\WooCommerce\Utilities\OrderUtil;
use InspireLabs\WoocommerceInpost\shipx\models\shipment\ShipX_Shipment_Model;


class EasyPack extends inspire_Plugin4 {

	const LABELS_DIRECTORY = __DIR__ . DIRECTORY_SEPARATOR . 'web' . DIRECTORY_SEPARATOR . 'labels';

	const CLASSES_DIRECTORY = __DIR__ . DIRECTORY_SEPARATOR . 'classes';

	const ATTRIBUTE_PREFIX           = 'woo_inpost';
	const ATTRIBUTE_TEMPLATES_PREFIX = 'easypack_courier_tmplts';

	const ENVIRONMENT_PRODUCTION = 'production';

	const ENVIRONMENT_SANDBOX = 'sandbox';


	public static $instance;

	public static $text_domain = 'inpost-for-woocommerce';

	protected $_pluginNamespace = 'inpost-for-woocommerce';

	/**
	 * @var WC_Shipping_Method[]
	 */
	protected $shipping_methods = array();

	protected $settings;

	/**
	 * @var string
	 */
	private static $environment;

	private static $assets_js_uri;
	private static $assets_css_uri;


	/**
	 * Get labels uri
	 *
	 * @return string
	 */
	public static function getLabelsUri(): string {
		return plugins_url() . '/woo-inpost/web/labels/';
	}

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct();
		$this->hooks();
	}


	/**
	 * Hooks
	 *
	 * @return void
	 */
	public function hooks() {

		add_action( 'plugins_loaded', array( $this, 'init_easypack' ), 100 );
		
		add_action( 'plugins_loaded', array( $this, 'add_settings_to_flexible_shipping' ), 20 );
		add_action( 'init', array( $this, 'load_plugin_textdomain' ) );
		add_filter( 'woocommerce_package_rates', array( $this, 'check_paczka_weekend_fs_settings' ), 10, 2 );

		// Initialize InPost shipping methods when WooCommerce shipping is initializing.		
		add_action(
			'init',
			function () {
				if ( 'yes' === get_option( 'easypack_debug_mode' ) ) {
					if ( current_user_can( 'administrator' ) ) {
						$this->init_shipping_methods();
					}
				} else {
					$this->init_shipping_methods();
				}
			},
			5
		);

		add_action( 'send_tracking_numbers_email', array( $this, 'send_tracking_numbers_email_callback' ) );

		add_action( 'woocommerce_checkout_process', array( $this, 'validation_old_checkout' ), 9999 );

		add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'update_order_meta_old_checkout' ) );

		add_action( 'send_shipment_automatically', array( $this, 'send_shipment_automatically_callback' ) );
		add_action( 'woocommerce_payment_complete', array( $this, 'create_shipment_automatically' ), 20 );
		add_action( 'woocommerce_order_status_changed', array( $this, 'create_shipment_automatically_on_paid' ), 10, 4 );

		add_filter( 'woocommerce_order_item_get_formatted_meta_data', array( $this, 'wc_order_shipping_method_logo' ), 10, 2 );
	}

	/**
	 * Get assets JS uri
	 *
	 * @return string
	 */
	public static function get_assets_js_uri(): string {
		return self::$assets_js_uri;
	}

	/**
	 * Get assets CSS uri
	 *
	 * @return string
	 */
	public static function get_assets_css_uri(): string {
		return self::$assets_css_uri;
	}

	public static function EasyPack(): EasyPack {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function init_easypack() {
		$this->setup_environment();
		self::$assets_js_uri  = $this->getPluginJs();
		self::$assets_css_uri = $this->getPluginCss();
		( new Geowidget_v5() )->init_assets();
		$this->init_alerts();

		add_filter( 'woocommerce_get_settings_pages', array( $this, 'woocommerce_get_settings_pages' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ), 75 );

		add_filter( 'woocommerce_order_item_display_meta_value', array( $this, 'change_order_item_meta_value' ), 20, 3 );
		add_action( 'woocommerce_cart_item_removed', array( $this, 'clear_wc_shipping_cache' ) );
		add_action( 'woocommerce_add_to_cart', array( $this, 'clear_wc_shipping_cache' ) );
		add_action( 'woocommerce_after_cart_item_quantity_update', array( $this, 'clear_wc_shipping_cache' ) );
		add_action( 'woocommerce_before_checkout_form', array( $this, 'clear_wc_shipping_cache' ) );
		add_filter( 'woocommerce_locate_template', array( $this, 'easypack_woo_templates' ), 1, 3 );

		add_action( 'woocommerce_before_thankyou', array( $this, 'possibility_setup_missed_locker_on_typ' ), 20 );

		// integration Products table start.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_product_table_assets' ), 100 );
		add_action( 'wp_ajax_inpost_product_table', array( $this, 'inpost_product_table_callback' ) );

		add_action(
			'admin_menu',
			function () {
				( new EasyPack_Custom_Product_List_Table() );
				$product_table = new EasyPack_Custom_Product_List_Table();
				add_submenu_page(
					'inpost',
					__( 'Products Settings', 'inpost-for-woocommerce' ),
					__( 'Products Settings', 'inpost-for-woocommerce' ),
					'view_woocommerce_reports',
					'easypack_product_settings',
					array( $product_table, 'render_custom_product_settings_page' )
				);
			},
			9999
		);
		// integration Products table end.

		try {
			( new Easypack_Shipping_Rates() )->init();
			( new EasyPackBulkOrders() )->hooks();
			( new EasyPackCoupons() )->hooks();
			( new EasyPack_Webhook() )->hooks();

			// integration with Woocommerce blocks start.
			add_action(
				'woocommerce_blocks_checkout_block_registration',
				function ( $integration_registry ) {
					$integration_registry->register( new EasyPackWooBlocks() );
				}
			);

			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_block_script' ), 100 );

			add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( $this, 'block_checkout_save_parcel_locker_in_order_meta' ), 10, 2 );
			// integration with Woocommerce blocks end.

			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ), 75 );
			add_filter( 'woocommerce_shipping_methods', array( $this, 'woocommerce_shipping_methods' ), 1000 );

			add_filter( 'woocommerce_shipping_packages', array( $this, 'woocommerce_shipping_packages' ), 1000 );
			add_filter( 'woocommerce_package_rates', array( $this, 'filter_shipping_methods' ), PHP_INT_MAX );
			add_filter( 'woocommerce_get_order_item_totals', array( $this, 'show_parcel_machine_in_order_details' ), 2, 100 );
			$this->init_email_filters();
			( new EasyPack_Product_Shipping_Method_Selector() )->handle_product_edit_hooks();
		} catch ( Exception $exception ) {
			\wc_get_logger()->debug( 'INPOST Exception: ', array( 'source' => 'inpost-log' ) );
			\wc_get_logger()->debug( print_r( $exception->getMessage(), true ), array( 'source' => 'inpost-log' ) );

		}
	}

	public function woocommerce_checkout_after_order_review() {
		echo '<input type="hidden" id="parcel_machine_id"
                     name="parcel_machine_id" class="parcel_machine_id"/>
            <input type="hidden" id="parcel_machine_desc"
                   name="parcel_machine_desc" class="parcel_machine_desc"/>';
	}

	/**
	 * Get environment
	 *
	 * @return string
	 */
	public static function get_environment(): string {
		return self::$environment;
	}

	/**
	 * Setup environment
	 *
	 * @return void
	 */
	private function setup_environment() {
		if ( self::ENVIRONMENT_SANDBOX === get_option( 'easypack_api_environment' ) ) {
			self::$environment = self::ENVIRONMENT_SANDBOX;
		} else {
			self::$environment = self::ENVIRONMENT_PRODUCTION;
		}
	}

	private function init_email_filters() {
		( new NewOrderEmail() )->init();
	}

	private function init_alerts() {
		$alerts = new Alerts();
	}

	/**
	 * Show parcel machine in order details
	 *
	 * @param array    $items $items.
	 *
	 * @param WC_Order $wcOrder $wcOrder.
	 *
	 * @return array
	 */
	public function show_parcel_machine_in_order_details( $items, $wcOrder ) {

		$parcel_desc       = html_entity_decode( $wcOrder->get_meta( '_parcel_machine_desc' ) );
		$parcel_machine_id = html_entity_decode( $wcOrder->get_meta( '_parcel_machine_id' ) );

		$parcel_locker_methods = array(
			'easypack_parcel_machines',
			'easypack_parcel_machines_cod',
			'easypack_parcel_machines_economy',
			'easypack_parcel_machines_economy_cod',
			'easypack_parcel_machines_weekend',
			'easypack_parcel_machines_weekend_cod',
		);

		$fs_method_name = get_post_meta( $wcOrder->get_id(), '_fs_easypack_method_name', true );

		$shipping_method_id = '';

		foreach ( $wcOrder->get_items( 'shipping' ) as $item_id => $item ) {
			$shipping_method_id = $item->get_method_id(); // The method ID.
		}

		if ( in_array( $shipping_method_id, $parcel_locker_methods )
			|| ( isset( $fs_method_name ) && in_array( $fs_method_name, $parcel_locker_methods ) ) ) {

			if ( isset( $items['shipping'] ) && ! empty( $parcel_machine_id ) ) {
				$items['shipping']['value']
					.= sprintf(
						'<br>%1s:<br><span class="ep-chosen-parcel-machine">%1s</span><br><span class="italic">%3s</span>',
						esc_html__( 'Selected parcel machine', 'inpost-for-woocommerce' ),
						esc_attr( $parcel_machine_id ),
						esc_html( $parcel_desc )
					);
			}
		}

		return $items;
	}


	/**
	 * Initializes all available InPost shipping methods based on organization settings.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 * @access public
	 */
	public function init_shipping_methods() {

		$merchant_services   = array();
		$can_update_services = is_admin() && ( current_user_can( 'manage_woocommerce' ) || current_user_can( 'manage_options' ) );

		$main_methods = array(
			'inpost_locker_standard',
			'inpost_courier_palette',
			'inpost_courier_standard',
			'inpost_courier_c2c',
			'inpost_courier_express_1000',
			'inpost_courier_express_1200',
			'inpost_courier_express_1700',
			'inpost_locker_economy',
			'inpost_courier_alcohol',
		);

		$stored_organization = get_option( 'inpost_pl_organisation_services' );

		// get Shipping methods from stored settings.
		if ( ! empty( $stored_organization ) && is_array( $stored_organization ) ) {
			if ( isset( $stored_organization['services'] ) && is_array( $stored_organization['services'] ) ) {
				foreach ( $main_methods as $service ) {
					if ( in_array( $service, $stored_organization['services'], true ) ) {
						$merchant_services[] = $service;
					}
				}
			}
		}

		if ( empty( $merchant_services ) ) {
			// base method.
			$merchant_services = array( 'inpost_locker_standard' );
			if ( $can_update_services ) {
				$this->get_merchant_services();
			}
		}

		if ( ( time() - (int) get_option( 'inpost_pl_last_time_update_services', 0 ) ) > 86400 ) {
			if ( $can_update_services ) {
				$this->get_merchant_services();
			}
		}

		if ( is_array( $merchant_services ) && ! empty( $merchant_services ) ) {
			if ( in_array( EasyPack_Shippng_Parcel_Machines::SERVICE_ID, $merchant_services ) ) {

				$easyPack_Shippng_Parcel_Machines = new EasyPack_Shippng_Parcel_Machines();
				$this->shipping_methods[]         = $easyPack_Shippng_Parcel_Machines;

				$easyPack_Shippng_Parcel_Machines_Cod = new EasyPack_Shippng_Parcel_Machines_COD();
				$this->shipping_methods[]             = $easyPack_Shippng_Parcel_Machines_Cod;

				$easyPack_Shippng_Parcel_Machines_Weekend = new EasyPack_Shipping_Parcel_Machines_Weekend();
				$this->shipping_methods[]                 = $easyPack_Shippng_Parcel_Machines_Weekend;

				$easyPack_Shippng_Parcel_Machines_Weekend_COD = new EasyPack_Shipping_Parcel_Machines_Weekend_COD();
				$this->shipping_methods[]                     = $easyPack_Shippng_Parcel_Machines_Weekend_COD;
			}

			if ( in_array( EasyPack_Shipping_Parcel_Machines_Economy::SERVICE_ID, $merchant_services ) ) {
				$easyPack_Shippng_Parcel_Machines_Economy = new EasyPack_Shipping_Parcel_Machines_Economy();
				$this->shipping_methods[]                 = $easyPack_Shippng_Parcel_Machines_Economy;

				$easyPack_Shippng_Parcel_Machines_Economy_COD = new EasyPack_Shipping_Parcel_Machines_Economy_COD();
				$this->shipping_methods[]                     = $easyPack_Shippng_Parcel_Machines_Economy_COD;
			}

			if ( in_array( EasyPack_Shipping_Method_EsmartMix::SERVICE_ID, $merchant_services ) ) {
				$easyPack_Shipping_Method_EsmartMix = new EasyPack_Shipping_Method_EsmartMix();
				$this->shipping_methods[]           = $easyPack_Shipping_Method_EsmartMix;
			}

			if ( in_array( EasyPack_Shipping_Method_Courier_Local_Standard::SERVICE_ID, $merchant_services ) ) {
				$shipping_Method_Courier_local_standard = new EasyPack_Shipping_Method_Courier_Local_Standard();
				$this->shipping_methods[]               = $shipping_Method_Courier_local_standard;
			}

			if ( in_array( EasyPack_Shipping_Method_Courier_LSE_COD::SERVICE_ID, $merchant_services ) ) {
				$shipping_Method_Courier_LSE_COD = new EasyPack_Shipping_Method_Courier_LSE_COD();
				$this->shipping_methods[]        = $shipping_Method_Courier_LSE_COD;
			}

			if ( in_array( EasyPack_Shipping_Method_Courier_COD::SERVICE_ID, $merchant_services ) ) {
				$shipping_Method_Courier_COD = new EasyPack_Shipping_Method_Courier_COD();
				$this->shipping_methods[]    = $shipping_Method_Courier_COD;
			}

			if ( in_array( EasyPack_Shipping_Method_Courier::SERVICE_ID, $merchant_services ) ) {
				$shipping_Method_Courier  = new EasyPack_Shipping_Method_Courier();
				$this->shipping_methods[] = $shipping_Method_Courier;
			}

			if ( in_array( EasyPack_Shipping_Method_Courier_LSE::SERVICE_ID, $merchant_services ) ) {
				$shipping_Method_Courier_LSE = new EasyPack_Shipping_Method_Courier_LSE();
				$this->shipping_methods[]    = $shipping_Method_Courier_LSE;
			}

			if ( in_array( EasyPack_Shipping_Method_Courier_Local_Standard_COD::SERVICE_ID, $merchant_services ) ) {
				$shipping_Method_Courier_local_standard_cod = new EasyPack_Shipping_Method_Courier_Local_Standard_COD();
				$this->shipping_methods[]                   = $shipping_Method_Courier_local_standard_cod;
			}

			if ( in_array( EasyPack_Shipping_Method_Courier_Local_Express::SERVICE_ID, $merchant_services ) ) {
				$shipping_Method_Courier_local_express = new EasyPack_Shipping_Method_Courier_Local_Express();
				$this->shipping_methods[]              = $shipping_Method_Courier_local_express;
			}

			if ( in_array( EasyPack_Shipping_Method_Courier_Local_Express_COD::SERVICE_ID, $merchant_services ) ) {
				$shipping_Method_Courier_local_express_cod = new EasyPack_Shipping_Method_Courier_Local_Express_COD();
				$this->shipping_methods[]                  = $shipping_Method_Courier_local_express_cod;
			}

			if ( in_array( EasyPack_Shipping_Method_Courier_Palette::SERVICE_ID, $merchant_services ) ) {
				$shipping_Method_Courier_Palette = new EasyPack_Shipping_Method_Courier_Palette();
				$this->shipping_methods[]        = $shipping_Method_Courier_Palette;
			}

			if ( in_array( EasyPack_Shipping_Method_Courier_Palette_COD::SERVICE_ID, $merchant_services ) ) {
				$shipping_Method_Courier_Palette_Cod = new EasyPack_Shipping_Method_Courier_Palette_COD();
				$this->shipping_methods[]            = $shipping_Method_Courier_Palette_Cod;
			}

			if ( in_array( EasyPack_Shipping_Method_Courier_C2C::SERVICE_ID, $merchant_services ) ) {
				$shipping_Method_Courier_c2c = new EasyPack_Shipping_Method_Courier_C2C();
				$this->shipping_methods[]    = $shipping_Method_Courier_c2c;

				$shipping_Method_Courier_c2c_cod = new EasyPack_Shipping_Method_Courier_C2C_COD();
				$this->shipping_methods[]        = $shipping_Method_Courier_c2c_cod;
			}
		}

		EasyPack_Product_Shipping_Method_Selector::$inpost_methods = $this->shipping_methods;
	}


	/**
	 * Registers InPost shipping methods with WooCommerce.
	 *
	 * @param array $methods Array of shipping methods.
	 * @return array Modified array of shipping methods.
	 *
	 * @since 1.0.0
	 * @access public
	 */
	public function woocommerce_shipping_methods( $methods ) {
		foreach ( $this->shipping_methods as $shipping_method ) {
			$methods[ $shipping_method->id ] = get_class( $shipping_method );
		}

		return $methods;
	}

	public function woocommerce_shipping_packages( $packages ) {

		$methods_allowed_by_cart = array();

		if ( is_object( WC()->session ) ) {
			$cart = WC()->cart->cart_contents;

			if ( ! empty( $cart ) && is_array( $cart ) ) {
				$methods_allowed_by_cart = ( new EasyPack_Product_Shipping_Method_Selector() )->get_methods_allowed_by_cart( $cart );
			}
		}

		$rates_allowed = array();
		$rates         = array();

		if ( isset( $packages[0]['rates'] ) ) {
			$rates = $packages[0]['rates'];
		}

		if ( is_array( $rates ) && ! empty( $rates ) ) {

			$fs_linked_methods_disabled = array();
			$fs_linked_methods_enabled  = array();

			foreach ( $rates as $k => $rate_object ) {
				// if Flexible shipping is active we need check if some our Easypack methods is linked to FS.
				if ( EasyPack_Helper()->is_flexible_shipping_activated() ) {
					$linked_method = '';
					$instance_id   = '';

					if ( stripos( $k, ':' ) ) {
						$instance_id = trim( explode( ':', $k )[1] );
					}
					// check if some easypack method linked to Flexible Shipping.
					if ( ! empty( $instance_id ) ) {
						$linked_method = EasyPack_Helper()->get_method_linked_to_fs_by_instance_id( $instance_id );
					}

					if ( 0 === strpos( $k, 'flexible_shipping' ) ) {
						// check if linked FS methods are allowed for all products in cart.
						if ( 0 === strpos( $linked_method, 'easypack_' ) ) {
							if ( in_array( $k, $methods_allowed_by_cart ) ) {
								$rates_allowed[ $k ]         = $rate_object;
								$fs_linked_methods_enabled[] = $k;
							} else {
								$fs_linked_methods_disabled[] = $k;
							}
						}
					}
				}
				// if FS is not active or for not integrated methods.
				if ( 0 === strpos( $k, 'easypack_' ) ) {
					if ( in_array( $k, $methods_allowed_by_cart ) ) {
						$rates_allowed[ $k ] = $rate_object;
					}
				} else {
					$rates_allowed[ $k ] = $rate_object;
				}
			}
		}

		if ( ! empty( $rates_allowed ) ) {

			if ( ! empty( $fs_linked_methods_disabled ) ) {
				foreach ( $fs_linked_methods_disabled as $method_id ) {
					unset( $rates_allowed[ $method_id ] );
				}
			}

			$packages[0]['rates'] = $rates_allowed;

		} else {
			// No InPost allowed methods.
			if ( empty( $methods_allowed_by_cart ) ) {

				if ( isset( $packages[0]['rates'] ) && is_array( $packages[0]['rates'] ) && ! empty( $packages[0]['rates'] ) ) {

					foreach ( $packages[0]['rates'] as $k => $rate ) {
						if ( 0 === strpos( $k, 'easypack_' ) ) {
							unset( $packages[0]['rates'][ $k ] );
						}
					}

					if ( ! empty( $fs_linked_methods_enabled ) ) {
						foreach ( $fs_linked_methods_enabled as $method_id ) {
							unset( $packages[0]['rates'][ $method_id ] );
						}
					}
				}
			}
		}

		return $packages;
	}

	public function woocommerce_get_settings_pages( $woocommerce_settings ) {
		new EasyPack_Settings_General();
		return $woocommerce_settings;
	}

	public function get_package_sizes() {
		return array(
			'small'  => __( 'A 8 x 38 x 64 cm', 'inpost-for-woocommerce' ),
			'medium' => __( 'B 19 x 38 x 64 cm', 'inpost-for-woocommerce' ),
			'large'  => __( 'C 41 x 38 x 64 cm', 'inpost-for-woocommerce' ),
		);
	}

	public function get_package_sizes_xlarge() {
		return array(
			'small'  => __( 'A 8 x 38 x 64 cm', 'inpost-for-woocommerce' ),
			'medium' => __( 'B 19 x 38 x 64 cm', 'inpost-for-woocommerce' ),
			'large'  => __( 'C 41 x 38 x 64 cm', 'inpost-for-woocommerce' ),
			'xlarge' => __( 'D 50 x 50 x 80 cm', 'inpost-for-woocommerce' ),
		);
	}

	public function get_package_sizes_display() {
		return array(
			'small'  => __( 'A', 'inpost-for-woocommerce' ),
			'medium' => __( 'B', 'inpost-for-woocommerce' ),
			'large'  => __( 'C', 'inpost-for-woocommerce' ),
		);
	}

	public function get_package_weights_parcel_machines() {
		return array(
			'1'  => __( '1 kg', 'inpost-for-woocommerce' ),
			'2'  => __( '2 kg', 'inpost-for-woocommerce' ),
			'5'  => __( '5 kg', 'inpost-for-woocommerce' ),
			'10' => __( '10 kg', 'inpost-for-woocommerce' ),
			'15' => __( '15 kg', 'inpost-for-woocommerce' ),
			'20' => __( '20 kg', 'inpost-for-woocommerce' ),
		);
	}

	public function get_package_weights_courier() {
		return array(
			'1'  => __( '1 kg', 'inpost-for-woocommerce' ),
			'2'  => __( '2 kg', 'inpost-for-woocommerce' ),
			'5'  => __( '5 kg', 'inpost-for-woocommerce' ),
			'10' => __( '10 kg', 'inpost-for-woocommerce' ),
			'15' => __( '15 kg', 'inpost-for-woocommerce' ),
			'20' => __( '20 kg', 'inpost-for-woocommerce' ),
			'25' => __( '25 kg', 'inpost-for-woocommerce' ),
		);
	}


	function getTemplatePathFull() {
		return implode( '/', array( $this->_pluginPath, $this->getTemplatePath() ) );
	}


	public function enqueue_scripts() {
		if ( is_cart() || is_checkout() || has_block( 'woocommerce/checkout' ) || 'yes' === get_option( 'easypack_debug_mode_enqueue_scripts' ) ) {
			wp_enqueue_style( 'easypack-front', $this->getPluginCss() . 'front.css', array(), WOOCOMMERCE_INPOST_PL_PLUGIN_VERSION );
		}

		if ( is_checkout() || has_block( 'woocommerce/checkout' ) || get_option( 'easypack_debug_mode_enqueue_scripts' ) === 'yes' ) {
			wp_enqueue_style( 'easypack-jbox-css', $this->getPluginCss() . 'jBox.all.min.css', array(), WOOCOMMERCE_INPOST_PL_PLUGIN_VERSION );
			wp_enqueue_script(
				'easypack-jquery-modal',
				$this->getPluginJs() . 'jBox.all.min.js',
				array( 'jquery' ),
				WOOCOMMERCE_INPOST_PL_PLUGIN_VERSION,
				array( 'in_footer' => true )
			);

			if ( get_option( 'easypack_js_map_button' ) === 'yes' && ! has_block( 'woocommerce/checkout' ) ) {
				wp_enqueue_script(
					'easypack-front-js',
					$this->getPluginJs() . 'front.js',
					array( 'jquery' ),
					WOOCOMMERCE_INPOST_PL_PLUGIN_VERSION,
					array( 'in_footer' => true )
				);
				wp_localize_script(
					'easypack-front-js',
					'easypack_front_map',
					array(
						'button_text1'       => __( 'Select Parcel Locker', 'inpost-for-woocommerce' ),
						'button_text2'       => __( 'Change Parcel Locker', 'inpost-for-woocommerce' ),
						'selected_text'      => __( 'Selected parcel locker:', 'inpost-for-woocommerce' ),
						'geowidget_v5_token' => self::ENVIRONMENT_SANDBOX === self::get_environment()
							? get_option( 'easypack_geowidget_sandbox_token' )
							: get_option( 'easypack_geowidget_production_token' ),
						'inpost_methods'     => EasyPack_Helper()->get_inpost_methods(),
						'error_text'         => esc_html__( 'Some error is occured', 'inpost-for-woocommerce' ),
						'updated_text'       => esc_html__( 'Pick up point has been successfuly written', 'inpost-for-woocommerce' ),
						'ajaxurl'            => admin_url( 'admin-ajax.php' ),
						'security'           => wp_create_nonce( 'easypack_nonce' ),
						'preloader'          => esc_url( $this->getPluginImages() . 'inpost-pl-loader.gif' ),
					)
				);
			}
		}
	}

	function enqueue_admin_scripts() {

		$current_screen = get_current_screen();

		$admin_css_path     = $this->_pluginPath . '/resources/assets/css/admin.css';
		$admin_css_path_ver = file_exists( $admin_css_path ) ? filemtime( $admin_css_path ) : WOOCOMMERCE_INPOST_PL_PLUGIN_VERSION;

		if ( is_a( $current_screen, 'WP_Screen' ) && 'inpost_page_easypack_shipment' === $current_screen->id ) {
			wp_enqueue_style( 'easypack-admin', $this->getPluginCss() . 'admin.css', array(), $admin_css_path_ver );
		}

		if ( 'yes' === get_option( 'woocommerce_custom_orders_table_enabled' ) ) {
			// HPOS usage is enabled.
			$post_id = isset( $_GET['id'] ) ? sanitize_text_field( $_GET['id'] ) : null;

			if ( $post_id ) {
				$post_type = get_post_type( $post_id );

				if ( 'shop_order_placehold' === $post_type || 'shop_order' === $post_type ) {
					wp_enqueue_style( 'easypack-admin', $this->getPluginCss() . 'admin.css', array(), $admin_css_path_ver );
				}
			} elseif ( is_a( $current_screen, 'WP_Screen' ) && 'woocommerce_page_wc-orders' === $current_screen->id ) {

				wp_enqueue_style( 'easypack-admin', $this->getPluginCss() . 'admin.css', array(), $admin_css_path_ver );
			}
		} else {

			$post_id = isset( $_GET['post'] ) ? sanitize_text_field( $_GET['post'] ) : null;

			if ( $post_id ) {
				$post_type = get_post_type( $post_id );

				if ( 'shop_order_placehold' === $post_type || 'shop_order' === $post_type ) {
					wp_enqueue_style( 'easypack-admin', $this->getPluginCss() . 'admin.css', array(), $admin_css_path_ver );
				}
			}
		}

		if ( EasyPack_Helper()->is_required_pages_for_modal() ) {
			wp_enqueue_style( 'easypack-admin-modal', $this->getPluginCss() . 'modal.css', array(), WOOCOMMERCE_INPOST_PL_PLUGIN_VERSION );
			wp_enqueue_style( 'easypack-jbox-css', $this->getPluginCss() . 'jBox.all.min.css', array(), WOOCOMMERCE_INPOST_PL_PLUGIN_VERSION );
		}

		$admin_js_path     = $this->_pluginPath . '/resources/assets/js/admin.js';
		$admin_js_path_ver = file_exists( $admin_js_path ) ? filemtime( $admin_js_path ) : WOOCOMMERCE_INPOST_PL_PLUGIN_VERSION;
		wp_enqueue_script( 'easypack-admin', $this->getPluginJs() . 'admin.js', array( 'jquery' ), $admin_js_path_ver, array( 'in_footer' => true ) );
		wp_localize_script(
			'easypack-admin',
			'easypack_settings',
			array(
				'default_logo'      => EasyPack()->getPluginImages() . 'logo/small/white.png',
				'ajaxurl'           => admin_url( 'admin-ajax.php' ),
				'nonce'             => wp_create_nonce( 'easypack-shipment-manager' ),
				'courier_templates' => get_option( 'easypack_courier_tmplts_dmtemplates', array() ),
			)
		);

		if ( EasyPack_Helper()->is_required_pages_for_modal() ) {
			wp_enqueue_script( 'easypack-admin-modal', $this->getPluginJs() . 'modal.js', array( 'jquery' ), WOOCOMMERCE_INPOST_PL_PLUGIN_VERSION, array( 'in_footer' => true ) );
			wp_enqueue_script( 'easypack-jquery-modal', $this->getPluginJs() . 'jBox.all.min.js', array( 'jquery' ), WOOCOMMERCE_INPOST_PL_PLUGIN_VERSION, array( 'in_footer' => true ) );
		}

		if ( is_a( $current_screen, 'WP_Screen' ) && 'woocommerce_page_wc-settings' === $current_screen->id ) {
			if ( isset( $_GET['tab'] ) && 'easypack_general' === $_GET['tab'] ) {
				wp_register_script(
					'easypack-admin-settings-page',
					$this->getPluginJs() . 'admin-settings-page.js',
					array( 'jquery' ),
					WOOCOMMERCE_INPOST_PL_PLUGIN_VERSION,
					true
				);

				wp_localize_script(
					'easypack-admin-settings-page',
					'easypack_settings',
					array(
						'change_country_alert' => __( 'Are you sure to change the country?', 'inpost-for-woocommerce' ),
						'debug_notice'         => __(
							'Does not work simultaneously with the option \'JS mode of map button\'',
							'inpost-for-woocommerce'
						),
						'webhook_notice'       => __( 'Copied!', 'inpost-for-woocommerce' ),
						'webhook_url'          => esc_url( EasyPack_Helper()->get_webhook_url() ),
					)
				);

				wp_enqueue_script( 'easypack-admin-settings-page' );

				wp_enqueue_style( 'easypack-admin', $this->getPluginCss() . 'admin.css', array(), $admin_css_path_ver );
			}
		}

		if ( is_a( $current_screen, 'WP_Screen' ) && 'woocommerce_page_wc-settings' === $current_screen->id ) {
			if ( isset( $_GET['tab'] ) && 'shipping' === $_GET['tab'] && isset( $_GET['instance_id'] ) ) {

				wp_enqueue_media(); // logo upload dependency.

				wp_register_script(
					'easypack-shipping-method-settings',
					$this->getPluginJs() . 'shipping-settings-page.js',
					array( 'jquery' ),
					WOOCOMMERCE_INPOST_PL_PLUGIN_VERSION,
					true
				);

				wp_enqueue_script( 'easypack-shipping-method-settings' );

				wp_enqueue_style( 'easypack-admin', $this->getPluginCss() . 'admin.css', array(), $admin_css_path_ver );
			}
		}
	}


	/**
	 * @return ShipX_Shipment_Service
	 */
	public function get_shipment_service() {
		return new ShipX_Shipment_Service();
	}

	/**
	 * @return ShipX_Organization_Service
	 */
	public function get_organization_service() {
		return new ShipX_Organization_Service();
	}

	/**
	 * @return ShipX_Shipment_Price_Calculator_Service
	 */
	public function get_shipment_price_calculator_service() {
		return new ShipX_Shipment_Price_Calculator_Service();
	}

	/**
	 * @return ShipX_Courier_Pickup_Service
	 */
	public function get_courier_pickup_service() {
		return new ShipX_Courier_Pickup_Service();
	}

	/**
	 * @return ShipX_Shipment_Status_Service
	 */
	public function get_shipment_status_service() {
		return new ShipX_Shipment_Status_Service();
	}


	/**
	 * Replace custom logo link of shipping method for correct view
	 */
	public function change_order_item_meta_value( $value, $meta, $item ) {

		if ( $item === null ) {
			return $value;
		}

		if ( is_admin() && $item->get_type() === 'shipping' && $meta->key === 'logo' ) {
			if ( ! empty( $value ) ) {
				$value = '<img style="width: 60px; height: auto; background-size: cover;" src="' . esc_url( $value ) . '">';
			}
		}
		return $value;
	}

	/**
	 * Clear WC shipping methods cache
	 */
	public function clear_wc_shipping_cache() {
		\WC_Cache_Helper::get_transient_version( 'shipping', true );
	}

	/**
	 * Define path to Woocommerce templates in our plugin
	 */
	public function easypack_woo_templates( $template, $template_name, $template_path ) {
		global $woocommerce;
		$_template = $template;
		if ( ! $template_path ) {
			$template_path = $woocommerce->template_url;
		}

		$plugin_templates_path = untrailingslashit( EasyPack()->getPluginFullPath() ) . '/resources/templates/';

		$template = locate_template(
			array(
				$template_path . $template_name,
				$template_name,
			)
		);

		if ( ! $template && file_exists( $plugin_templates_path . $template_name ) ) {
			$template = $plugin_templates_path . $template_name;
		}

		if ( ! $template ) {
			$template = $_template;
		}

		return $template;
	}



	/**
	 * Returns available package size options with dimensions.
	 *
	 * Provides an array of standard InPost package sizes (gabaryt)
	 * with their dimensions translated for display in the interface.
	 *
	 * @return array Array of package sizes with translated labels.
	 */
	public function get_package_sizes_gabaryt() {
		return array(
			'small'  => __( 'Size A (8 x 38 x 64 cm)', 'inpost-for-woocommerce' ),
			'medium' => __( 'Size B (19 x 38 x 64 cm)', 'inpost-for-woocommerce' ),
			'large'  => __( 'Size C (41 x 38 x 64 cm)', 'inpost-for-woocommerce' ),
		);
	}


	/**
	 * Retrieves or updates data from the InPost API.
	 *
	 * Attempts to fetch organization data using stored API credentials.
	 * Validates that API key and organization ID are available before making the request.
	 * Handles and returns any errors encountered during the API communication.
	 *
	 * @return string|false Error message if an error occurred, false otherwise.
	 */
	private function get_or_update_data_from_api() {

		$error = false;

		$organization_id = get_option( 'easypack_organization_id' );
		$api_key         = get_option( 'easypack_token' );

		if ( empty( $api_key ) || empty( $organization_id ) ) {
			$error = esc_html__( 'API key or Organization ID is empty', 'inpost-for-woocommerce' );
			return $error;
		}

		try {
			$organization_service = self::EasyPack()->get_organization_service();
			$organization         = $organization_service->query_organisation();
			if ( ! is_object( $organization ) ) {
				$error = esc_html__( "Error when trying to get API services. Check wc-logs, file 'inpost-pl-services-error'", 'inpost-for-woocommerce' );
			}
		} catch ( Exception $e ) {
			$error = $e->getMessage();
		}

		return $error;
	}


	/**
	 * Enqueues JavaScript for InPost blocks on checkout pages.
	 *
	 * Loads the front-blocks.js script when on checkout pages with WooCommerce blocks,
	 * or when debug mode is enabled. Includes version control based on file modification time
	 * and localizes the script with necessary configuration data and translations.
	 */
	public function enqueue_block_script() {
		if ( ( is_checkout() && has_block( 'woocommerce/checkout' ) ) || has_block( 'woocommerce/checkout' ) || 'yes' === get_option( 'easypack_debug_mode_enqueue_scripts' ) ) {

			$front_blocks_js_path = WOOCOMMERCE_INPOST_PLUGIN_DIR . '/resources/assets/js/front-blocks.js';
			wp_enqueue_script(
				'easypack-front-blocks-js',
				$this->getPluginJs() . 'front-blocks.js',
				array( 'jquery' ),
				file_exists( $front_blocks_js_path ) ? filemtime( $front_blocks_js_path ) : WOOCOMMERCE_INPOST_PL_PLUGIN_VERSION,
				array( 'in_footer' => true )
			);
			wp_localize_script(
				'easypack-front-blocks-js',
				'easypack_block',
				array(
					'ajaxurl'            => admin_url( 'admin-ajax.php' ),
					'security'           => wp_create_nonce( 'easypack_nonce' ),
					'button_text1'       => esc_html__( 'Select Parcel Locker', 'inpost-for-woocommerce' ),
					'button_text2'       => esc_html__( 'Change Parcel Locker', 'inpost-for-woocommerce' ),
					'phone_text'         => esc_html__( 'Phone number (required)', 'inpost-for-woocommerce' ),
					'geowidget_v5_token' => self::ENVIRONMENT_SANDBOX === self::get_environment()
						? get_option( 'easypack_geowidget_sandbox_token' )
						: get_option( 'easypack_geowidget_production_token' ),
					'inpost_methods'     => EasyPack_Helper()->get_inpost_methods(),
				)
			);

		}
	}


	public function block_checkout_save_parcel_locker_in_order_meta( $order, $request ) {

		if ( ! $order ) {
			return;
		}

		$order_id           = $order->get_id();
		$shipping_method_id = null;
		$fs_method_name     = '';

		foreach ( $order->get_items( 'shipping' ) as $item_id => $item ) {
			$shipping_method_id          = $item->get_method_id();
			$shipping_method_instance_id = $item->get_instance_id();
		}

		if ( EasyPack_Helper()->is_flexible_shipping_activated() ) {
			foreach ( $order->get_shipping_methods() as $shipping_method ) {
				$fs_instance_id = $shipping_method->get_instance_id();
			}

			$fs_method_name = EasyPack_Helper()->get_method_linked_to_fs_by_instance_id( $fs_instance_id );
			if ( ! empty( $fs_method_name ) ) {
				$order->update_meta_data( '_fs_easypack_method_name', $fs_method_name );
				$order->save();
			}
		}

		$request_body = json_decode( $request->get_body(), true );

		if ( ! empty( $request_body['extensions']['inpost']['inpost-parcel-locker-id'] ) ) {

			$parcel_machine_id = sanitize_text_field( $request_body['extensions']['inpost']['inpost-parcel-locker-id'] );

			if ( 'PL_' === substr( $parcel_machine_id, 0, 3 ) ) {
				$parcel_machine_id = substr( $parcel_machine_id, 3 );
			}

			update_post_meta( $order_id, '_parcel_machine_id', $parcel_machine_id );
			$order->update_meta_data( '_parcel_machine_id', $parcel_machine_id );
			$order->save();

		} else {

			// additional check if used Google Pay payment method - we extract paczkomat number from WC session.
			if ( 0 === strpos( $shipping_method_id, 'easypack_parcel_machines' ) || 0 === strpos( $fs_method_name, 'easypack_parcel_machines' ) ) {

				if ( is_object( WC() ) && property_exists( WC(), 'session' ) ) {
					$inpost_pl_paczkomat = WC()->session->get( 'inpost_pl_wc_paczkomat' );

					if ( $inpost_pl_paczkomat ) {

						if ( 'PL_' === substr( $inpost_pl_paczkomat, 0, 3 ) ) {
							$inpost_pl_paczkomat = substr( $inpost_pl_paczkomat, 3 );
						}

						update_post_meta( $order_id, '_parcel_machine_id', $inpost_pl_paczkomat );
						$order->update_meta_data( '_parcel_machine_id', $inpost_pl_paczkomat );
						$order->save();
					}
					// Clear session data.
					WC()->session->__unset( 'inpost_pl_wc_paczkomat' );
				}
			}
		}
	}



	public function filter_shipping_methods( $rates ) {

		global $woocommerce;

		// API doesn't accept COD amount > 5000.
		$methods_to_disable_5000 = array(
			'easypack_parcel_machines_economy_cod',
			'easypack_parcel_machines_cod',
			'easypack_shipping_courier_c2c_cod',
			'easypack_parcel_machines_weekend_cod',
		);

		$methods_to_disable_15000 = array(
			'easypack_cod_shipping_courier',
		);

		$order_total_amount = floatval( $woocommerce->cart->cart_contents_total ) + floatval( $woocommerce->cart->tax_total );

		if ( $order_total_amount > 5000 ) {
			foreach ( $rates as $rate_key => $rate ) {
				if ( in_array( $rate->method_id, $methods_to_disable_5000, true ) ) {
					unset( $rates[ $rate_key ] );
				}

				if ( $order_total_amount > 15000 ) {
					if ( in_array( $rate->method_id, $methods_to_disable_15000, true ) ) {
						unset( $rates[ $rate_key ] );
					}
				}

				if ( EasyPack_Helper()->is_flexible_shipping_activated() ) {
					$linked_method = '';
					$instance_id   = $rate->instance_id;
					// check if some easypack method linked to Flexible Shipping.
					if ( ! empty( $instance_id ) ) {
						$linked_method = EasyPack_Helper()->get_method_linked_to_fs_by_instance_id( $instance_id );
					}

					if ( ! empty( $linked_method ) && 0 === strpos( $rate_key, 'flexible_shipping' ) ) {
						if ( 0 === strpos( $linked_method, 'easypack_' ) ) {
							if ( in_array( $linked_method, $methods_to_disable_5000, true ) ) {
								unset( $rates[ $rate_key ] );
							}

							if ( $order_total_amount > 15000 ) {
								if ( in_array( $linked_method, $methods_to_disable_15000, true ) ) {
									unset( $rates[ $rate_key ] );
								}
							}
						}
					}
				}
			}
		}

		return $rates;
	}


	public function inpost_product_table_callback() {

		check_ajax_referer( 'inpost_product_table', 'security' );

		if ( isset( $_POST['product_data'] ) && is_array( $_POST['product_data'] ) && isset( $_POST['product_id'] ) ) {
			$product_id = sanitize_text_field( wp_unslash( $_POST['product_id'] ) );

			$wc_product = wc_get_product( $product_id );

			if ( ! $wc_product || is_wp_error( $wc_product ) ) {
				$error = 'Product #' . $product_id . ' does not exist';
				wp_send_json( array( 'error' => $error ) );
				wp_die();
			}

			$locker_size_meta_key     = self::ATTRIBUTE_PREFIX . '_parcel_dimensions';
			$allowed_methods_meta_key = self::ATTRIBUTE_PREFIX . '_shipping_methods_allowed';
			if ( isset( $_POST['product_data']['allowed_methods'] ) ) {
				$allowed_methods = array_map( 'sanitize_text_field', wp_unslash( $_POST['product_data']['allowed_methods'] ) );
				update_post_meta( $product_id, $allowed_methods_meta_key, $allowed_methods );
			} else {
				$allowed_methods = array();
				update_post_meta( $product_id, $allowed_methods_meta_key, $allowed_methods );
			}
			if ( isset( $_POST['product_data']['locker_size'] ) && ! empty( $_POST['product_data']['locker_size'] ) ) {
				$locker_size = sanitize_text_field( wp_unslash( $_POST['product_data']['locker_size'] ) );
				update_post_meta( $product_id, $locker_size_meta_key, $locker_size );
			}
		}

		if ( isset( $_POST['all_data'] ) ) {
			$data_return = $_POST['all_data'];
		} else {
			wp_send_json( array( 'error' => esc_html__( 'Error in data', 'inpost-for-woocommerce' ) ) );
		}

		echo wp_json_encode( $data_return );
		wp_die();
	}

	function enqueue_product_table_assets() {

		$current_screen = get_current_screen();

		if ( is_a( $current_screen, 'WP_Screen' ) && 'inpost_page_easypack_product_settings' === $current_screen->id ) {

			$plugin_data            = new EasyPack();
			$product_table_css_path = dirname( WOOCOMMERCE_INPOST_PLUGIN_FILE ) . '/resources/assets/css/product-table.css';

			$product_table_script     = dirname( WOOCOMMERCE_INPOST_PLUGIN_FILE ) . '/resources/js/product-table.js';
			$product_table_script_ver = file_exists( $product_table_script ) ? filemtime( $product_table_script ) : WOOCOMMERCE_INPOST_PL_PLUGIN_VERSION;
			wp_enqueue_script(
				'easypack-product-table',
				$plugin_data->getPluginJs() . 'product-table.js',
				array( 'jquery' ),
				$product_table_script_ver,
				true
			);
			wp_localize_script(
				'easypack-product-table',
				'inpost_product_table',
				array(
					'nonce'     => wp_create_nonce( 'inpost_product_table' ),
					'admin_url' => admin_url( 'admin-ajax.php' ),
				)
			);

			$product_table_css_ver = file_exists( $product_table_css_path ) ? filemtime( $product_table_css_path ) : WOOCOMMERCE_INPOST_PL_PLUGIN_VERSION;
			wp_enqueue_style( 'easypack-product-table', $this->getPluginCss() . 'product-table.css', array(), $product_table_css_ver );
		}
	}


	/**
	 * Add custom fields to each shipping method.
	 */
	public function add_settings_to_flexible_shipping() {
		if ( EasyPack_Helper()->is_flexible_shipping_activated() ) {
			// Flexible Shipping "A single Flexible Shipping method" uses method id: flexible_shipping_single.			
			add_filter( 'woocommerce_shipping_instance_form_fields_flexible_shipping_single', array( $this, 'add_map_field' ), PHP_INT_MAX );
		}
	}


	/**
	 * Add integration fields with plugin Flexible Shipping.
	 *
	 * @param array $settings $settings.
	 *
	 * @return array
	 */
	public function add_map_field( $settings ): array {

		$has_intergrations = false;

		$find_key = '';
		$i        = 1;
		foreach ( $settings as $key => $setting ) {

			// divide fs settings array and put our settings before this field.
			if ( 'method_integration' === $key ) {
				$find_key          = $i;
				$has_intergrations = true;
			}
			++$i;
		}
		// show our field near with native integration field.
		if ( $has_intergrations ) {

			$position = (int) $find_key - 1;

			$settings_begin = array_slice( $settings, 0, $position, true );

			$addtitional_settings = $this->settings_block_for_flexible_shipping();

			$settings_end = array_slice( $settings, $position, count( $settings ) - $position, true );

			$new_settings = array_merge( $settings_begin, $addtitional_settings, $settings_end );

			return $new_settings;

		} else {

			return $settings + $this->settings_block_for_flexible_shipping();
		}
	}


	/**
	 * Delete Paczka Weekend Shipping method from cart if time interval is not allowed.
	 *
	 * @param mixed $rates $rates.
	 * @param mixed $package $package.
	 *
	 * @return mixed
	 */
	public function check_paczka_weekend_fs_settings( $rates, $package ): array {

		if ( ! EasyPack_Helper()->is_flexible_shipping_activated() ) {
			return is_array( $rates ) ? $rates : array();
		}

		if ( is_array( $rates ) && ! empty( $rates ) ) {
			foreach ( $rates as $rate_key => $rate ) {
				if ( 'easypack_parcel_machines_weekend' === EasyPack_Helper()->get_method_linked_to_fs_by_instance_id( $rate->instance_id )
					|| 'easypack_parcel_machines_weekend_cod' === EasyPack_Helper()->get_method_linked_to_fs_by_instance_id( $rate->instance_id )

				) {

					$paczka_weekend = new EasyPack_Shipping_Parcel_Machines_Weekend();
					if ( ! $paczka_weekend->check_allowed_interval_for_weekend( $rate->instance_id ) ) {
						unset( $rates[ $rate_key ] ); // hide Paczka w Weekend if not match into time interval.
					}
				}
			}
		}

		return is_array( $rates ) ? $rates : array();
	}


	/**
	 * Additional settings fields visible on Flexible Shipping method setting's page.
	 *
	 * @return array
	 */
	public function settings_block_for_flexible_shipping(): array {
		$settings = array();
		if ( EasyPack_Helper()->is_flexible_shipping_activated() ) {
			$settings['fs_inpost_pl_method'] = array(
				'title'   => esc_html__( "Integration with 'InPost PL' plugin", 'inpost-for-woocommerce' ),
				'type'    => 'select',
				'default' => 'all',
				'options' => array(
					'0'                                    => esc_html__( 'None', 'inpost-for-woocommerce' ),
					'easypack_parcel_machines'             => esc_html__( 'InPost Locker 24/7', 'inpost-for-woocommerce' ),
					'easypack_parcel_machines_cod'         => esc_html__( 'InPost Locker 24/7 COD', 'inpost-for-woocommerce' ),
					'easypack_shipping_courier_c2c'        => esc_html__( 'InPost Courier C2C', 'inpost-for-woocommerce' ),
					'easypack_shipping_courier_c2c_cod'    => esc_html__( 'InPost Courier C2C COD', 'inpost-for-woocommerce' ),
					'easypack_parcel_machines_weekend'     => esc_html__( 'InPost Locker Weekend', 'inpost-for-woocommerce' ),
					'easypack_parcel_machines_weekend_cod' => esc_html__( 'InPost Locker Weekend COD', 'inpost-for-woocommerce' ),
					'easypack_shipping_courier'            => esc_html__( 'InPost Courier', 'inpost-for-woocommerce' ),
					'easypack_cod_shipping_courier'        => esc_html__( 'InPost Courier COD', 'inpost-for-woocommerce' ),
					'easypack_shipping_esmartmix'          => esc_html__( 'InPost Smart Courier', 'inpost-for-woocommerce' ),
					'easypack_shipping_courier_local_express' => esc_html__( 'InPost Courier Local Express', 'inpost-for-woocommerce' ),
					'easypack_shipping_courier_le_cod'     => esc_html__( 'InPost Courier Local Express COD', 'inpost-for-woocommerce' ),
					'easypack_shipping_courier_local_standard' => esc_html__( 'InPost Courier Local Standard', 'inpost-for-woocommerce' ),
					'easypack_shipping_courier_local_standard_cod' => esc_html__( 'InPost Courier Local Standard COD', 'inpost-for-woocommerce' ),
					'easypack_shipping_courier_lse'        => esc_html__( 'InPost Courier Local Super Express', 'inpost-for-woocommerce' ),
					'easypack_shipping_courier_lse_cod'    => esc_html__( 'InPost Courier Local Super Express COD', 'inpost-for-woocommerce' ),
					'easypack_shipping_courier_palette'    => esc_html__( 'InPost Courier Palette', 'inpost-for-woocommerce' ),
					'easypack_shipping_courier_palette_cod' => esc_html__( 'InPost Courier Palette COD', 'inpost-for-woocommerce' ),

				),
			);

			$settings['fs_inpost_pl_weekend_day_from'] = array(
				'title'   => esc_html__( 'Available from day of week', 'inpost-for-woocommerce' ),
				'type'    => 'select',
				'class'   => 'wc-enhanced-select fs-inpost-pl-weekend',
				'default' => '4',
				'options' => array(
					'1' => esc_html__( 'Monday', 'inpost-for-woocommerce' ),
					'2' => esc_html__( 'Tuesday', 'inpost-for-woocommerce' ),
					'3' => esc_html__( 'Wednesday', 'inpost-for-woocommerce' ),
					'4' => esc_html__( 'Thursday', 'inpost-for-woocommerce' ),
					'5' => esc_html__( 'Friday', 'inpost-for-woocommerce' ),
					'6' => esc_html__( 'Saturday', 'inpost-for-woocommerce' ),
				),
			);

			$settings['fs_inpost_pl_weekend_hour_from'] = array(
				'title'    => esc_html__( 'Available from hour', 'inpost-for-woocommerce' ),
				'type'     => 'time',
				'default'  => '',
				'desc_tip' => false,
				'class'    => 'fs-inpost-pl-weekend',
			);

			$settings['fs_inpost_pl_weekend_day_to'] = array(
				'title'   => esc_html__( 'Available to day of week', 'inpost-for-woocommerce' ),
				'type'    => 'select',
				'class'   => 'wc-enhanced-select fs-inpost-pl-weekend',
				'default' => '5',
				'options' => array(
					'1' => esc_html__( 'Monday', 'inpost-for-woocommerce' ),
					'2' => esc_html__( 'Tuesday', 'inpost-for-woocommerce' ),
					'3' => esc_html__( 'Wednesday', 'inpost-for-woocommerce' ),
					'4' => esc_html__( 'Thursday', 'inpost-for-woocommerce' ),
					'5' => esc_html__( 'Friday', 'inpost-for-woocommerce' ),
					'6' => esc_html__( 'Saturday', 'inpost-for-woocommerce' ),
				),
			);

			$settings['fs_inpost_pl_weekend_hour_to'] = array(
				'title'    => esc_html__( 'Available to hour', 'inpost-for-woocommerce' ),
				'type'     => 'time',
				'default'  => '',
				'desc_tip' => false,
				'class'    => 'fs-inpost-pl-weekend',
			);

			$settings['fs_insurance_inpost_pl'] = array(
				'title'    => esc_html__( 'Insurance', 'inpost-for-woocommerce' )
								. ' InPost PL: '
								. esc_html__( 'set from order amount', 'inpost-for-woocommerce' ),
				'type'     => 'checkbox',
				'default'  => 'no',
				'desc_tip' => false,
				'class'    => 'fs-inpost-pl-insurance',
			);

			$settings['fs_insurance_value_inpost_pl'] = array(
				'title'             => esc_html__( 'Default insurance amount', 'inpost-for-woocommerce' ),
				'type'              => 'number',
				'custom_attributes' => array(
					'step' => 'any',
					'min'  => '0',
				),
				'default'           => '0.00',
				'desc_tip'          => false,
				'class'             => 'fs-inpost-pl-insurance-value',
			);
		}

		return $settings;
	}


	/**
	 * Cron callback to send one or several tracking numbers.
	 *
	 * @param mixed $order_id $order_id.
	 *
	 * @return void.
	 */
	public function send_tracking_numbers_email_callback( $order_id ) {

		if ( empty( $order_id ) ) {
			return;
		}

		( new TrackingInfoEmail() )->send_tracking_info_email( $order_id );
	}


	/**
	 * Loads the plugin text domain for internationalization.
	 *
	 * Sets up translation files from the plugin's languages directory
	 * to enable multilingual support for the WooCommerce InPost plugin.
	 *
	 * @return void
	 */
	public function load_plugin_textdomain() {
		load_plugin_textdomain(
			'inpost-for-woocommerce',
			false,
			dirname( plugin_basename( WOOCOMMERCE_INPOST_PLUGIN_FILE ) ) . '/languages/'
		);
	}


	/**
	 * Displays locker selection interface for orders missing parcel machine assignment.
	 *
	 * Checks if order uses InPost shipping method but lacks locker selection,
	 * fetches nearby pickup points via API based on postal code, displays
	 * warning message with selectable points list and map widget for locker selection.
	 *
	 * @param int $order_id The WooCommerce order ID to check.
	 * @return void Outputs HTML interface or returns early if conditions not met.
	 */
	public function possibility_setup_missed_locker_on_typ( $order_id ) {

		if ( ! $order_id ) {
			return;
		}

		$order = wc_get_order( $order_id );

		if ( ! $order || is_wp_error( $order ) ) {
			return;
		}

		$parcel_machine_id = $order->get_meta( '_parcel_machine_id' );
		if ( ! empty( $parcel_machine_id ) ) {
			return;
		}

		$is_inpost_method_with_locker = false;
		$fs_method_name               = '';
		foreach ( $order->get_shipping_methods() as $shipping_method ) {
			$shipping_method_instance_id = $shipping_method->get_instance_id();
			$shipping_method_id          = $shipping_method->get_method_id();
			$fs_method_name              = EasyPack_Helper()->get_method_linked_to_fs_by_instance_id( $shipping_method_instance_id );
			if ( 0 === strpos( $shipping_method_id, 'easypack_parcel_machines' ) || 0 === strpos( $fs_method_name, 'easypack_parcel_machines' ) ) {
				$is_inpost_method_with_locker = true;
			}
		}

		if ( $is_inpost_method_with_locker ) {

			$zip_code = $order->get_shipping_postcode();
			if ( empty( $zip_code ) ) {
				$zip_code = $order->get_billing_postcode();
			}

			if ( ! empty( $zip_code ) ) {

				$points_data = array();

				$api_url = 'https://api.inpost.pl/v1/points';

				if ( 'sandbox' === get_option( 'easypack_api_environment' ) ) {
					$api_url = 'https://sandbox-api-gateway-pl.easypack24.net/v1/points';
				}

				$url = $api_url . '?relative_post_code=' . $zip_code . '&max_distance=3000&limit=5';

				$args = array(
					'method'  => 'GET',
					'headers' => array(
						'Authorization' => 'Bearer ' . get_option( 'easypack_token' ),
						'Accept'        => 'application/json',
						'Content-Type'  => 'application/json',
					),
				);

				$response = wp_remote_get( $url, $args );
				$code     = wp_remote_retrieve_response_code( $response );

				if ( 200 === $code ) {
					$response_body = wp_remote_retrieve_body( $response );

					try {
						$points_data = json_decode( $response_body, true );
					} catch ( Exception $e ) {

					}
				}
			}

			echo '<div class="pre-order-details-message" style="padding: 5px; margin-bottom: 20px; border-radius: 5px;">';
			echo '<div class="pre-order-details-message-wrap" style="background: #f65d5d;padding:30px;">';
			echo '<p style="color:#fff">⚠️ <b>' . esc_html__( 'It seems you forgot to select the InPost pick up point.', 'inpost-for-woocommerce' ) . '</b></p>';
			echo '<p style="color:#fff"><b>' . esc_html__( 'If so, please select InPost pick up point from this list or on the map.', 'inpost-for-woocommerce' ) . '</b></p>';
			echo '</div>';

			if ( ! empty( $points_data['items'] ) ) {
				echo '<div class="inpost-pl-related-points-container" style="background: #fcc905; margin-top: 15px;">';

				foreach ( $points_data['items'] as $point ) {
					// Get address lines.
					$address_line1 = isset( $point['address']['line1'] ) ? esc_html( $point['address']['line1'] ) : '';
					$address_line2 = isset( $point['address']['line2'] ) ? esc_html( $point['address']['line2'] ) : '';

					// Get point name for data-id.
					$point_id = isset( $point['name'] ) ? esc_attr( $point['name'] ) : '';

					// Get point type for display (optional - shows if it's locker or shop).
					$point_type = '';
					if ( isset( $point['type'] ) && is_array( $point['type'] ) ) {
						if ( in_array( 'parcel_locker', $point['type'] ) ) {
							$point_type = '📦 ' . esc_html__( 'Paczkomat', 'inpost-for-woocommerce' );
						} elseif ( in_array( 'pok', $point['type'] ) || in_array( 'pop', $point['type'] ) ) {
							$point_type = '🏪 ' . esc_html__( 'POP', 'inpost-for-woocommerce' );
						}
					}

					// Get distance (optional).
					$distance = isset( $point['distance'] ) ? ' (' . round( $point['distance'] / 1000, 1 ) . ' km)' : '';

					// Create button with address as two lines.
					echo '<button class="inpost-pl-related-point-btn" data-id="' . esc_attr( $point_id ) . '" data-address-id="' . esc_attr( $address_line1 ) . ' ' . esc_attr( $address_line2 ) . '" style="
                            display: block;
                            width: 100%;
                            margin: 8px 0;
                            padding: 12px 15px;
                            background: #fff;
                            border: 3px solid #ddd;
                            border-radius: 6px;
                            text-align: left;
                            cursor: pointer;
                            transition: all 0.3s ease;
                            font-size: 14px;
                            line-height: 1.4;
                        ">';

					// Display point type and ID.
					if ( $point_type ) {
						echo '<span class="inpost-pl-related-locker-info">' . esc_html( $point_type ) . ' - ' . esc_html( $point_id ) . esc_html( $distance ) . '</span><br>';
					}

					// Display address line 1.
					if ( $address_line1 ) {
						echo '<b>' . esc_html( $address_line1 ) . '</b><br>';
					}

					// Display address line 2.
					if ( $address_line2 ) {
						echo '<span style="color: #555;">' . esc_html( $address_line2 ) . '</span>';
					}

					echo '<span class="inpost-pl-select-from-points-preloader"><img src="' . esc_url( $this->getPluginImages() . 'inpost-pl-loader.gif' ) . '"></span>';
					echo '</button>';
				}
			}

			echo '</div><input type="hidden" id="inpost-pl-related-data-order" value="' . esc_attr( $order_id ) . '">';

			echo '<div id="inpost-pl-typ-map-data" data-id="' . esc_attr( $order_id ) . '">
                    <div class="inpost_pl_geowidget_related_preloader">
                         <img src="' . esc_url( $this->getPluginImages() . 'inpost-pl-loader.gif' ) . '">
                    </div>
                    <div class="easypack_show_geowidget inpost_pl_geowidget_typ" id="easypack_show_geowidget">
                        ' . esc_html__( 'Select parcel locker', 'inpost-for-woocommerce' ) . '
                    </div>
                    <div id="selected-parcel-machine" class="hidden-inpost-pl-typ-data">
                        <div>
                          <span class="font-height-600">'
				. esc_html__( 'Selected parcel locker:', 'inpost-for-woocommerce' ) .
				'</span>
                        </div>
                        <span class="italic" id="selected-parcel-locker-pl-id" style="display: none;"></span>
                        <br><span class="italic" id="selected-parcel-machine-desc"></span>
                    </div>
                </div>';

		}
	}


	/**
	 * Updates order meta data for the old checkout process with EasyPack information.
	 *
	 * @param int $order_id The ID of the order being processed.
	 * @return void
	 *
	 * @since 1.7.2
	 * @access public
	 */
	public function update_order_meta_old_checkout( $order_id ) {

		$order = wc_get_order( $order_id );
		if ( ! $order || is_wp_error( $order ) ) {
			return;
		}

		if ( ! empty( $_POST['parcel_machine_id'] ) ) {
			$paczkomat_id = sanitize_text_field( wp_unslash( $_POST['parcel_machine_id'] ) );
			if ( 'PL_' === substr( $paczkomat_id, 0, 3 ) ) {
				$paczkomat_id = substr( $paczkomat_id, 3 );
			}
			$order->update_meta_data( '_parcel_machine_id', $paczkomat_id );
			$order->save();
		}

		if ( ! empty( $_POST['parcel_machine_desc'] ) ) {
			$paczkomat_desc = sanitize_text_field( wp_unslash( $_POST['parcel_machine_desc'] ) );
			$order->update_meta_data( '_parcel_machine_desc', $paczkomat_desc );
			$order->save();
		}

		// save easypack method name in metadata to show later required metabox in order details.
		if ( EasyPack_Helper()->is_flexible_shipping_activated() ) {
			foreach ( $order->get_shipping_methods() as $shipping_method ) {
				$fs_instance_id = $shipping_method->get_instance_id();
			}

			$fs_method_name = EasyPack_Helper()->get_method_linked_to_fs_by_instance_id( $fs_instance_id );
			if ( ! empty( $fs_method_name ) ) {
				$order->update_meta_data( '_fs_easypack_method_name', $fs_method_name );
				$order->save();
			}
		}
	}



	/**
	 * Validates the old checkout process for EasyPack shipping methods.
	 *
	 * @return void
	 * @throws Exception When validation fails.
	 *
	 * @since 1.7.2
	 * @access public
	 */
	public function validation_old_checkout() {

		$chosen_shipping_methods       = array();
		$at_least_one_physical_product = false;
		$fs_method_name                = '';
		static $alert_shown;
		static $alert_shown_phone;

		if ( ! is_object( WC()->session ) ) {
			return;
		}

		$chosen_shipping_methods = WC()->session->get( 'chosen_shipping_methods' );
		if ( EasyPack_Helper()->is_flexible_shipping_activated() ) {
			$fs_method_name = EasyPack_Helper()->get_method_linked_to_fs( $chosen_shipping_methods );
		}
		$cart_contents = WC()->session->get( 'cart' );

		$at_least_one_physical_product = EasyPack_Helper()->physical_goods_in_cart( $cart_contents );

		if ( ! $at_least_one_physical_product ) {
			return;
		}

		if ( ! empty( $chosen_shipping_methods ) && is_array( $chosen_shipping_methods ) ) {

			$selected_shipping_method_name = '';
			if ( ! empty( $chosen_shipping_methods[0] ) ) {
				$selected_shipping_method_name = EasyPack_Helper()->validate_method_name( $chosen_shipping_methods[0] );
			}

			$locker_require_methods = array(
				'easypack_parcel_machines',
				'easypack_parcel_machines_cod',
				'easypack_parcel_machines_economy',
				'easypack_parcel_machines_economy_cod',
				'easypack_parcel_machines_weekend',
				'easypack_parcel_machines_weekend_cod',
			);

			if ( in_array( $selected_shipping_method_name, $locker_require_methods, true ) || in_array( $fs_method_name, $locker_require_methods, true ) ) {
				if ( empty( $_POST['parcel_machine_id'] ) ) {
					if ( ! $alert_shown ) {
						$alert_shown = true;
						if ( 'pl-PL' === get_bloginfo( 'language' ) ) {
							wc_add_notice( 'Musisz wybrać paczkomat InPost', 'error' );
							throw new Exception( 'InPost PL' );

						} else {
							wc_add_notice( 'Parcel locker InPost must be choosen', 'error' );
							throw new Exception( 'InPost PL' );
						}
					}
				}
			}
		}
	}

	/**
	 * Retrieves or updates merchant services from the InPost API.
	 *
	 * If forced, clears cached data and fetches fresh data from API.
	 * Otherwise, checks if cached data is older than 24 hours and updates if needed.
	 * Implements error handling with a 1-hour timeout between API calls on failure.
	 *
	 * @param bool $forced Whether to force update regardless of cache status.
	 */
	public function get_merchant_services( $forced = false ) {

		if ( $forced ) {
			delete_option( 'inpost_pl_last_time_update_services' );
			delete_option( 'inpost_pl_organisation_services' );
			delete_option( 'inpost_pl_api_returned_error' );
			$this->get_or_update_data_from_api();
		}

		// try to connect to API only once per 1 hour to avoid make website slow.
		$maybe_timeout = get_option( 'inpost_pl_api_returned_error' );
		$now           = time();
		if ( $maybe_timeout && $maybe_timeout > $now ) {
			return;
		} else {
			delete_option( 'inpost_pl_api_returned_error' );
		}

		$maybe_error = $this->get_or_update_data_from_api();

		if ( ! empty( $maybe_error ) ) {
			$timeout = time() + 300;
			update_option( 'inpost_pl_api_returned_error', $timeout );

			if ( $forced ) {
				$alerts = new Alerts();
				$error  = sprintf(
					'%s %s <br><a target="_blank" href="https://inpost.pl/formularz-wsparcie">%s</a>',
					'InPost PL: ' . esc_html__( 'Some error occured whe we try to get list of services:', 'inpost-for-woocommerce' ),
					esc_html( $maybe_error ),
					esc_html__( 'contact to support', 'inpost-for-woocommerce' )
				);

				$alerts->add_error( $error );
			}
		}
	}


	/**
	 * Creates a shipment automatically for an order if enabled.
	 *
	 * @param int $order_id The order ID.
	 * @return void
	 *
	 * @since 1.7.6
	 * @access public
	 */
	public function create_shipment_automatically( $order_id ) {

		$order = wc_get_order( $order_id );

		if ( ! $order || is_wp_error( $order ) ) {
			return;
		}

		if ( 'yes' !== get_option( 'easypack_create_shipment_automatically' ) ) {
			return;
		}

		if ( empty( $order->get_date_paid() ) ) {
			return;
		}

		$shipment = EasyPack_Helper()->get_woo_order_meta( $order_id, '_shipx_shipment_object' );
		if ( $shipment instanceof ShipX_Shipment_Model ) {
			return;
		}

		// $this->send_shipment_automatically( $order_id );
		wp_schedule_single_event(
			time() + 60,
			'send_shipment_automatically',
			array( $order_id )
		);
	}


	/**
	 * Creates a shipment automatically when an order is paid.
	 *
	 * @param int      $order_id The order ID.
	 * @param string   $status_from The previous order status.
	 * @param string   $status_to The new order status.
	 * @param WC_Order $order The order object.
	 * @return void
	 *
	 * @since 1.7.6
	 * @access public
	 */
	public function create_shipment_automatically_on_paid( $order_id, $status_from, $status_to, $order ) {

		if ( ! $order || is_wp_error( $order ) ) {
			return;
		}

		if ( 'yes' !== get_option( 'easypack_create_shipment_automatically' ) ) {
			return;
		}

		if ( empty( $order->get_date_paid() ) ) {
			return;
		}

		$shipment = EasyPack_Helper()->get_woo_order_meta( $order_id, '_shipx_shipment_object' );
		if ( $shipment instanceof ShipX_Shipment_Model ) {
			return;
		}

		$paid_statuses = array( 'processing', 'completed' );

		if ( in_array( $status_to, $paid_statuses ) && ! in_array( $status_from, $paid_statuses ) ) {
			// $this->send_shipment_automatically( $order_id );

			wp_schedule_single_event(
				time() + 40,
				'send_shipment_automatically',
				array( $order_id )
			);
		}
	}


	/**
	 * Sends a shipment automatically for an order.
	 *
	 * @param int $order_id The order ID.
	 * @return void
	 *
	 * @since 1.7.6
	 * @access public
	 */
	public function send_shipment_automatically_callback( $order_id ) {

		static $order_processed = false;

		// detect InPost shipping class we need for each order.
		$service                 = '';
		$ship_method_name        = '';
		$ship_method_instance_id = '';
		$sms_service             = false;
		$email_service           = false;

		$order = wc_get_order( $order_id );
		foreach ( $order->get_items( 'shipping' ) as $item_id => $item ) {
			$item_data               = $item->get_data();
			$service                 = $item_data['method_id'];
			$ship_method_name        = $item->get_method_id();
			$ship_method_instance_id = $item->get_instance_id();
		}

		$shipping_method_settings = get_option( 'woocommerce_' . $ship_method_name . '_' . $ship_method_instance_id . '_settings' );
		if ( isset( $shipping_method_settings['sms'] ) ) {
			if ( 'yes' === $shipping_method_settings['sms'] ) {
				$sms_service = true;
			}
		}
		if ( isset( $shipping_method_settings['email'] ) ) {
			if ( 'yes' === $shipping_method_settings['email'] ) {
				$email_service = true;
			}
		}

		$fs_method_name = EasyPack_Helper()->get_woo_order_meta( $order_id, '_fs_easypack_method_name' );

		$is_any_inpost_method                          = ! empty( $service ) && 0 === strpos( $service, 'easypack_' );
		$is_inpost_method_linked_via_flexible_shipping = ! empty( $service ) && 0 === strpos( $fs_method_name, 'easypack_' );
		if ( $is_inpost_method_linked_via_flexible_shipping ) {
			// use InPost method name linked to FS from metadata.
			$service = $fs_method_name;
		}

		if ( ! $is_any_inpost_method && ! $is_inpost_method_linked_via_flexible_shipping ) {
			return;
		}

		$cod_amount = null;

		$shipping_method_class_name = EasyPack_Helper()->get_class_name_by_shipping_id( $service );

		if ( '_COD' === substr( $shipping_method_class_name, -4 ) ) {
			$cod_amount = $order->get_total();
		}

		$class_with_namespace = 'InspireLabs\WoocommerceInpost\shipping\\' . $shipping_method_class_name;

		if ( ! class_exists( $class_with_namespace ) ) {
			return;
		}

		$class_instance = new $class_with_namespace();

		$commercial_product_identifier = null;
		if ( 'EasyPack_Shipping_Parcel_Machines_Economy' === $shipping_method_class_name || 'EasyPack_Shipping_Parcel_Machines_Economy_COD' === $shipping_method_class_name ) {
			$cpi = $class_instance::$instance->get_option( 'commercial_product_identifier' );
			if ( ! empty( $cpi ) ) {
				$commercial_product_identifier = $cpi;
			}
		}

		if ( ! $order_processed ) {

			$order_processed = true;

			try {

				$shipment_service        = self::EasyPack()->get_shipment_service();
				$parcels                 = array( Easypack_Helper()->get_parcel_size_from_settings( $order_id ) );
				$send_method             = EasyPack_Helper()->get_default_send_method( $order_id );
				$locker                  = Easypack_Helper()->get_woo_order_meta( $order_id, '_parcel_machine_id' );
				$parcel_machine_id       = ! empty( $locker ) ? $locker : null;
				$insurance_amount        = EasyPack_Helper()->get_insurance_amount( $order_id );
				$reference_number        = EasyPack_Helper()->get_maybe_custom_reference_number( $order_id );
				$courier_parcel_data     = array();
				$is_service_courier_type = $shipment_service->is_service_id_courier_type( $class_instance::SERVICE_ID );
				if ( $is_service_courier_type ) {
					$courier_parcel_source = EasyPack_Helper()->get_source_of_courier_dimensions( $order_id );
					$courier_parcel_data   = EasyPack_Helper()->get_courier_parcel_dimensions( $order_id, $courier_parcel_source );
				}

				$shipment_model = $shipment_service->create_shipment_object_by_shiping_data(
					$parcels,
					$order_id,
					$send_method,
					$class_instance::SERVICE_ID,
					$courier_parcel_data,
					$parcel_machine_id,
					$cod_amount,
					$insurance_amount,
					$reference_number,
					$commercial_product_identifier
				);

				$additional_services = array();

				$shipment_model->getInternalData()->setOrderId( $order_id );

				$status_service = self::EasyPack()->get_shipment_status_service();

				$shipment_array = $shipment_service->shipment_to_array( $shipment_model );

				if ( $is_service_courier_type ) {
					if ( ! isset( $shipment_array['custom_attributes'] ) || ! is_array( $shipment_array['custom_attributes'] ) ) {
						$shipment_array['custom_attributes'] = array();
					}

					$shipment_array['custom_attributes']['sending_method'] = 'dispatch_order';

					if ( ! isset( $shipment_array['additional_services'] ) || ! is_array( $shipment_array['additional_services'] ) ) {
						$shipment_array['additional_services'] = array();
					}
					if ( $sms_service ) {
						$shipment_array['additional_services'][] = 'sms';
						$additional_services[]                   = $shipment_model::ADDITIONAL_SERVICES_SMS;
					}
					if ( $email_service ) {
						$additional_services[]                   = $shipment_model::ADDITIONAL_SERVICES_EMAIL;
						$shipment_array['additional_services'][] = 'email';
					}
				} else {
					$shipment_array = EasyPack_Helper()->maybe_set_pww_param( $order_id, $shipment_array );
				}

				if ( null !== $cod_amount && floatval( $cod_amount ) > 0 ) {
					$cod = new ShipX_Shipment_Cod_Model();
					$cod->setCurrency( ShipX_Shipment_Contstants::CURRENCY_PLN );
					$cod->setAmount( (float) $cod_amount );
					$shipment_model->setCod( $cod );
				}

				if ( ! empty( $additional_services ) ) {
					$shipment_model->setAdditionalServices( $additional_services );
				}

				$response = EasyPack_API()->customer_parcel_create( $shipment_array );

				$shipment_data = $class_instance::save_to_order_meta(
					$order_id,
					$shipment_model,
					$shipment_service,
					$status_service,
					$shipment_array,
					$response
				);

				\wc_get_logger()->debug( 'INPOST create_package automatically: ', array( 'source' => 'inpost-pl-auto-order-' . $order_id ) );
				\wc_get_logger()->debug( 'DATA to API: ', array( 'source' => 'inpost-pl-auto-order-' . $order_id ) );
				\wc_get_logger()->debug( print_r( $shipment_array, true ), array( 'source' => 'inpost-pl-auto-order-' . $order_id ) );
				\wc_get_logger()->debug( 'RESPONSE from API: ', array( 'source' => 'inpost-pl-auto-order-' . $order_id ) );
				\wc_get_logger()->debug( print_r( $response, true ), array( 'source' => 'inpost-pl-auto-order-' . $order_id ) );

			} catch ( Exception $e ) {
				\wc_get_logger()->debug( 'INPOST create_package automatically Exception: ', array( 'source' => 'inpost-pl-auto-order-' . $order_id ) );
				\wc_get_logger()->debug( print_r( $order_id, true ), array( 'source' => 'inpost-pl-auto-order-' . $order_id ) );
				\wc_get_logger()->debug( print_r( $e->getMessage(), true ), array( 'source' => 'inpost-pl-auto-order-' . $order_id ) );
				\wc_get_logger()->debug( print_r( $shipment_array, true ), array( 'source' => 'inpost-pl-auto-order-' . $order_id ) );
			}
		}
	}




	/**
	 * Customizes the display of InPost shipping method logos in order details.
	 *
	 * This function modifies the formatted meta data for shipping line items to display
	 * InPost logos properly. The function replaces
	 * the text representation with an appropriate image.
	 *
	 * @since 1.0.0
	 *
	 * @param array                   $formatted_meta          The array of formatted meta data for the order item.
	 * @param \WC_Order_Item_Shipping $wc_order_item_shipping_obj The shipping line item object.
	 *
	 * @return array The modified array of formatted meta data with proper logo display.
	 */
	public function wc_order_shipping_method_logo( $formatted_meta, $wc_order_item_shipping_obj ) {

		$display_value          = '';
		$custom_logo            = false;
		$shipping_method_id     = null;
		$shipping_instance_id   = null;
		$logo_meta_id           = null;
		$delivery_terms_meta_id = null;
		$fs_method_name         = '';

		$courier_methods = array(
			'easypack_shipping_courier',
			'easypack_shipping_courier_local_express',
			'easypack_shipping_courier_c2c',
			'easypack_shipping_courier_c2c_cod',
			'easypack_cod_shipping_courier',
			'easypack_shipping_courier_le_cod',
			'easypack_shipping_courier_local_standard',
			'easypack_shipping_courier_local_standard_cod',
			'easypack_shipping_courier_lse',
			'easypack_shipping_courier_lse_cod',
			'easypack_shipping_courier_palette',
			'easypack_shipping_courier_palette_cod',
			'easypack_shipping_esmartmix',
		);

		if ( $wc_order_item_shipping_obj instanceof WC_Order_Item_Shipping ) {

			$shipping_method_id   = $wc_order_item_shipping_obj->get_method_id();
			$shipping_instance_id = $wc_order_item_shipping_obj->get_instance_id();
			$meta                 = $wc_order_item_shipping_obj->get_meta_data();

			if ( is_array( $meta ) ) {
				foreach ( $meta as $key => $obj ) {
					if ( 'logo' === $obj->key ) {
						$logo_meta_id = $obj->id;
					}
					if ( 'delivery_terms' === $obj->key ) {
						$delivery_terms_meta_id = $obj->id;
					}
				}
			}

			if ( EasyPack_Helper()->is_flexible_shipping_activated() ) {
				$fs_method_name = EasyPack_Helper()->get_method_linked_to_fs_by_instance_id( $shipping_instance_id );
			}

			if ( $shipping_method_id ) {

				$shipping_method_settings_name = 'woocommerce_' . $shipping_method_id . '_' . $shipping_instance_id . '_settings';
				$shipping_method_settings      = get_option( $shipping_method_settings_name );

				if ( ! empty( $shipping_method_settings['logo_upload'] ) ) {
					$custom_logo = $shipping_method_settings['logo_upload'];
				}

				if ( $custom_logo ) {
					$logo_src      = $custom_logo;
					$display_value = '<p><img style="width: 40px;height:auto;" src="' . esc_url( $logo_src ) . '"></p>';
				} elseif ( 0 === strpos( $shipping_method_id, 'easypack_parcel_machines_weekend' )
					|| 0 === strpos( $fs_method_name, 'easypack_parcel_machines_weekend' ) ) {

					$logo_src      = $this->getPluginImages() . 'logo/inpost-paczka-w-weekend.png';
					$display_value = '<p><img style="width: 40px;height:auto;" src="' . esc_url( $logo_src ) . '"></p>';
				} elseif ( 0 === strpos( $shipping_method_id, 'easypack_parcel_machines' )
					|| 0 === strpos( $fs_method_name, 'easypack_parcel_machines' ) ) {

					$logo_src      = $this->getPluginImages() . 'logo/inpost-paczkomat-logo.png';
					$display_value = '<p><img style="width: 40px;height:auto;" src="' . esc_url( $logo_src ) . '"></p>';
				} elseif ( in_array( $shipping_method_id, $courier_methods, true )
						|| in_array( $fs_method_name, $courier_methods, true ) ) {

					$logo_src      = $this->getPluginImages() . 'logo/inpost-kurier-logo.png';
					$display_value = '<p><img style="width: 40px;height:auto;" src="' . esc_url( $logo_src ) . '"></p>';
				}
			}
		}

		if ( $logo_meta_id ) {
			// Create new meta object.
			$new_meta                = new stdClass();
			$new_meta->key           = 'logo';
			$new_meta->value         = $logo_src;
			$new_meta->display_key   = 'By';
			$new_meta->display_value = $display_value;

			$formatted_meta[ $logo_meta_id ] = $new_meta;
		}

		if ( $delivery_terms_meta_id ) {
			unset( $formatted_meta[ $delivery_terms_meta_id ] );
		}

		return $formatted_meta;
	}
}
