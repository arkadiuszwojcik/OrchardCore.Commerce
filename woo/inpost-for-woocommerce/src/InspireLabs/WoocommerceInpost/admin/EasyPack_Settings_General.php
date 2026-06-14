<?php

namespace InspireLabs\WoocommerceInpost\admin;

use InspireLabs\WoocommerceInpost\EasyPack;
use Exception;
use InspireLabs\WoocommerceInpost\EasyPack_API;
use InspireLabs\WoocommerceInpost\shipx\models\courier_pickup\ShipX_Dispatch_Order_Point_Model;
use WC_Admin_Settings;
use WC_Settings_Page;

/**
 * EasyPack General Settings
 *
 *
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! class_exists( 'EasyPack_Settings_General' ) ) :

	/**
	 * EasyPack_Settings_General
	 */
	class EasyPack_Settings_General extends WC_Settings_Page {

		static $prevent_duplicate = array();

		private $status_service;

		/**
		 * Constructor.
		 */
		public function __construct() {

			$this->id    = 'easypack_general';
			$this->label = __( 'InPost', 'inpost-for-woocommerce' );
			parent::__construct();
			add_filter( 'woocommerce_settings_tabs_array', array( $this, 'add_settings_page' ), 20 );
			add_action( 'woocommerce_settings_' . $this->id, array( $this, 'output' ) );
			add_action( 'woocommerce_settings_save_' . $this->id, array( $this, 'save' ) );

			$this->status_service = EasyPack::EasyPack()->get_shipment_status_service();
		}


		/**
		 * Output the settings
		 */
		public function output() {
			$easypack_api_change = get_option( 'easypack_api_error_login', 0 );
			?>
			<input id="easypack_api_change" type="hidden"
					name="easypack_api_change"
					value="<?php echo esc_attr( $easypack_api_change ); ?>">
			<style>
				.form-table {
					border-bottom: 1px solid #ccc;
				}
			</style>
			<?php
			$settings = $this->get_settings();
			WC_Admin_Settings::output_fields( $settings );
		}


		/**
		 * @param ShipX_Dispatch_Order_Point_Model[] $points
		 *
		 * @return array
		 */
		public function getDispathPointsOptions( $points ) {
			$return = array();

			foreach ( $points as $point ) {
				$return[ $point->getId() ] = $point->getName();
			}

			return $return;
		}

		/**
		 * Get settings array
		 *
		 * @return array
		 * @throws Exception
		 */
		public function get_settings() {

			$default_locker_attributes  = array();
			$default_locker_placeholder = '';
			if ( ! empty( get_option( 'easypack_geowidget_production_token' ) )
				|| ! empty( get_option( 'easypack_geowidget_sandbox_token' ) ) ) {
				$default_locker_attributes  = array(
					'data-geowidget_config' => 'parcelSend',
				);
				$default_locker_placeholder = __( 'Click to choose the default locker point on the map', 'inpost-for-woocommerce' );

			} else {

				$default_locker_attributes  = array(
					'disabled' => '',
				);
				$default_locker_placeholder = __( 'This field must be filled in after the Geowiget token has been saved', 'inpost-for-woocommerce' );
			}

			$settings = array(
				array(
					'title' => __( 'Help', 'inpost-for-woocommerce' ),
					'type'  => 'title',
					'desc'  => '<b>' . __( 'Documentation InPost:  ', 'inpost-for-woocommerce' ) . '</b>'
						. '<a href="https://dokumentacja-inpost.atlassian.net/wiki/spaces/PL/pages/61833233/WooCommerce" target="_blank">'
						. __( 'How to set up plugin', 'inpost-for-woocommerce' ) . '</a><br></br>'
						. '<b>' . __( 'In case of questions/problems related to the plugin, please contact us via the ', 'inpost-for-woocommerce' ) . '</b>'
						. '<a href="https://inpost.pl/formularz-wsparcie" target="_blank">'
						. __( 'InPost form', 'inpost-for-woocommerce' ) . '</a>',
					'id'    => 'help_options',
				),

				array(
					'type' => 'sectionend',
					'id'   => 'country_options',
				),

				array(
					'title' => __( 'Logging in', 'inpost-for-woocommerce' ),
					'type'  => 'title',
					'desc'  => '',
					'id'    => 'general_options',
				),

				array(
					'title'             => __( 'API URL', 'inpost-for-woocommerce' ),
					'id'                => 'easypack_api_url',
					'css'               => 'min-width:300px;',
					'default'           => 'https://sandbox-api-shipx-pl.easypack24.net',
					'type'              => 'text',
					'desc_tip'          => false,
					'class'             => 'easypack-api-url',
					'custom_attributes' => array( 'required' => 'required' ),
				),
				array(
					'title'             => __( 'Organization ID', 'inpost-for-woocommerce' ),
					'id'                => 'easypack_organization_id',
					'css'               => 'min-width:300px;',
					'default'           => '',
					'type'              => 'text',
					'desc_tip'          => false,
					'custom_attributes' => array( 'required' => 'required' ),
				),

				array(
					'title'             => __( 'Token', 'inpost-for-woocommerce' ),
					'id'                => 'easypack_token',
					'css'               => 'min-width:300px;',
					'default'           => '',
					'type'              => 'text',
					'desc_tip'          => false,
					'custom_attributes' => array( 'required' => 'required' ),
				),

				array(
					'title'    => __( 'Geowidget production token', 'inpost-for-woocommerce' ),
					'id'       => 'easypack_geowidget_production_token',
					'css'      => 'min-width:300px;',
					'default'  => '',
					'type'     => 'text',
					'desc_tip' => false,
				),
				array(
					'title'    => __( 'Geowidget sandbox token', 'inpost-for-woocommerce' ),
					'id'       => 'easypack_geowidget_sandbox_token',
					'css'      => 'min-width:300px;',
					'default'  => '',
					'type'     => 'text',
					'desc_tip' => false,
				),

				array(
					'title'   => __( 'API type', 'inpost-for-woocommerce' ),
					'id'      => 'easypack_api_environment',
					'default' => 'production',
					'type'    => 'select',
					'css'     => 'min-width: 300px;',
					'options' => array(
						'production' => __( 'Production', 'inpost-for-woocommerce' ),
						'sandbox'    => __( 'Sandbox', 'inpost-for-woocommerce' ),
					),
				),

				array(
					'title'    => __( 'Szybkie zwroty - link', 'inpost-for-woocommerce' ),
					'id'       => 'easypack_fast_return',
					'css'      => 'min-width:300px;',
					'default'  => '',
					'type'     => 'text',
					'desc_tip' => false,
					'class'    => 'easypack-api-url',
				),

				array(
					'type' => 'sectionend',
					'id'   => 'general_options',
				),

				array(
					'title' => __( 'Send options', 'inpost-for-woocommerce' ),
					'type'  => 'title',
					'desc'  => '',
					'id'    => 'send_options',
				),

				array(
					'title'    => __( 'Default package size', 'inpost-for-woocommerce' ),
					'id'       => 'easypack_default_package_size',
					'type'     => 'select',
					'class'    => 'wc-enhanced-select',
					'css'      => 'min-width: 300px;',
					'desc_tip' => false,
					'default'  => 'small',
					'options'  => EasyPack()->get_package_sizes(),
				),

				array(
					'title'    => __( 'Parcel locker label format', 'inpost-for-woocommerce' ),
					'id'       => 'easypack_label_format',
					'type'     => 'select',
					'class'    => 'wc-enhanced-select',
					'css'      => 'min-width: 300px;',
					'desc_tip' => false,
					'default'  => 'A6',
					'options'  => array(
						'A6' => 'A6',
						'A4' => 'A4',
					),
				),

				array(
					'title'             => __( 'Default send parcel locker', 'inpost-for-woocommerce' ),
					'id'                => 'easypack_default_machine_id',
					'type'              => 'text',
					'class'             => 'settings-geowidget-default',
					'css'               => 'min-width: 300px;',
					'desc_tip'          => false,
					'default'           => '',
					'options'           => array(),
					'placeholder'       => $default_locker_placeholder,
					'custom_attributes' => $default_locker_attributes,
				),

				array(
					'type' => 'sectionend',
					'id'   => 'send_options',
				),

				array(
					'title' => __( 'Courier options', 'inpost-for-woocommerce' ),
					'type'  => 'title',
					'desc'  => '',
					'id'    => 'courier_options',
				),

				array(
					'title'    => __(
						'Set default dimensions for courier shipments',
						'inpost-for-woocommerce'
					),
					'id'       => 'easypack_set_default_courier_dimensions',
					'type'     => 'checkbox',
					'class'    => '',
					'css'      => 'min-width: 300px;',
					'desc_tip' => '',
					'default'  => '',
					'options'  => array(),
				),

				array(
					'title'    => __( 'Box length (mm)', 'inpost-for-woocommerce' ),
					'id'       => 'easypack_default_courier_dimensions[length]',
					'css'      => 'min-width:300px;',
					'default'  => '',
					'type'     => 'text',
					'desc_tip' => false,
					/*'custom_attributes' => [ 'required' => false ],*/
					'class'    => 'easypack_hidden_setting',
				),

				array(
					'title'    => __( 'Box width (mm)', 'inpost-for-woocommerce' ),
					'id'       => 'easypack_default_courier_dimensions[width]',
					'css'      => 'min-width:300px;',
					'default'  => '',
					'type'     => 'text',
					'desc_tip' => false,
					/*'custom_attributes' => [ 'required' => false ],*/
					'class'    => 'easypack_hidden_setting',
				),

				array(
					'title'    => __( 'Box height (mm)', 'inpost-for-woocommerce' ),
					'id'       => 'easypack_default_courier_dimensions[height]',
					'css'      => 'min-width:300px;',
					'default'  => '',
					'type'     => 'text',
					'desc_tip' => false,
					/*'custom_attributes' => [ 'required' => false ],*/
					'class'    => 'easypack_hidden_setting',
				),

				array(
					'title'    => __( 'Box weight (kg)', 'inpost-for-woocommerce' ),
					'id'       => 'easypack_default_courier_dimensions[weight]',
					'css'      => 'min-width:300px;',
					'default'  => '',
					'type'     => 'text',
					'desc_tip' => false,
					/*'custom_attributes' => [ 'required' => false ],*/
					'class'    => 'easypack_hidden_setting',
				),

				array(
					'title'    => __( 'Box is non standard?', 'inpost-for-woocommerce' ),
					'id'       => 'easypack_default_courier_dimensions[non_standard]',
					'type'     => 'select',
					'class'    => 'wc-enhanced-select',
					'css'      => 'min-width: 300px;',
					'desc_tip' => false,
					'default'  => 'no',
					'options'  => array(
						'yes' => __( 'Yes', 'inpost-for-woocommerce' ),
						'no'  => __( 'No', 'inpost-for-woocommerce' ),
					),
					'class'    => 'easypack_hidden_setting',
				),

				$this->output_courier_templates_module(),

				array(
					'type' => 'hidden',
					'id'   => EasyPack::ATTRIBUTE_TEMPLATES_PREFIX . '_dmtemplate_selected',
				),

				array(
					'type' => 'sectionend',
					'id'   => 'courier_options',
				),

				array(
					'title' => __( 'Dispatch point', 'inpost-for-woocommerce' ),
					'type'  => 'title',
					'desc'  => '',
					'id'    => 'dispatch_point_options',
				),

				$this->output_dispath_point_module(),
				array(
					'type' => 'hidden',
					'id'   => EasyPack::ATTRIBUTE_PREFIX . '_dpoint_selected',
				),

				array(
					'type' => 'sectionend',
					'id'   => 'dispatch_point_options',
				),

				array(
					'title' => __( 'Other options', 'inpost-for-woocommerce' ),
					'type'  => 'title',
					'desc'  => '',
					'id'    => 'other_options',
				),

				array(
					'title'    => __(
						'Enable InPost methods for all products in your shop',
						'inpost-for-woocommerce'
					),
					'id'       => 'easypack_enable_for_all_products',
					'type'     => 'checkbox',
					'class'    => '',
					'css'      => 'min-width: 300px;',
					'desc_tip' => __(
						'Enable this setting if you want to enable all configured Inpost shipping methods for each product in your store.',
						'inpost-for-woocommerce'
					)
						. ' ' . __( 'This setting ignores the individual settings in the product settings in the Inpost tab.', 'inpost-for-woocommerce' ),
					'default'  => '',
					'options'  => array(),

				),

				array(
					'title'    => __(
						'Enable SmartCourier method for all products in your shop',
						'inpost-for-woocommerce'
					),
					'id'       => 'easypack_enable_for_all_esmartmix',
					'type'     => 'checkbox',
					'class'    => '',
					'css'      => 'min-width: 300px;',
					'desc_tip' => __(
						'Enable this setting if you want to enable SmartCourier shipping method for each product in your store.',
						'inpost-for-woocommerce'
					)
						. ' ' . __( 'This setting ignores the individual settings in the product settings in the Inpost tab.', 'inpost-for-woocommerce' ),
					'default'  => '',
					'options'  => array(),

				),

				array(
					'title'    => __( 'If there is no general InPost shipping method for the products, then always choose the most expensive one.', 'inpost-for-woocommerce' ),
					'id'       => 'easypack_set_major_method',
					'type'     => 'checkbox',
					'class'    => '',
					'css'      => 'min-width: 300px;',
					'desc_tip' => __( 'After enabling this setting, when there is a product', 'inpost-for-woocommerce' )
									. __( ' with the Paczkomat service only and another product with the Courier service only ', 'inpost-for-woocommerce' )
									. __( 'in the cart, the most expensive one will be selected and the delivery method will be displayed', 'inpost-for-woocommerce' ),
					'default'  => '',
					'options'  => array(),

				),

				array(
					'title'    => __( 'Enable webhooks', 'inpost-for-woocommerce' ),
					'id'       => 'easypack_enable_webhooks',
					'type'     => 'checkbox',
					'class'    => '',
					'css'      => 'min-width: 300px;',
					'desc_tip' => __( 'Enabling webhooks allows you to update the status of shipments in your order', 'inpost-for-woocommerce' ),
					'default'  => '',
					'options'  => array(),

				),

				array(
					'title'             => __( 'Webhook URL', 'inpost-for-woocommerce' ),
					'id'                => 'easypack_enable_webhooks_url',
					'type'              => 'text',
					'class'             => 'easypack_hidden_setting_webhook',
					'css'               => 'min-width: 300px;',
					'desc'              => '<span class="dashicons dashicons-admin-page inpost-copy-icon" id="inpost-copy-webhook-url-btn">
									<div class="inpost-copy-tooltip" id="copy-tooltip"></div>
								</span>',
					'default'           => '',
					'custom_attributes' => array( 'disabled' => 'disabled' ),

				),

				array(
					'title'    => __( 'Change order status to Completed if webhook status is received like:', 'inpost-for-woocommerce' ),
					'id'       => 'easypack_change_order_status_by_webhook',
					'type'     => 'select',
					'class'    => 'easypack_hidden_setting_webhook',
					'css'      => 'min-width: 300px;',
					'desc_tip' => false,
					'default'  => __( 'No', 'inpost-for-woocommerce' ),
					'options'  => array(
						'no'                        => __( 'No', 'inpost-for-woocommerce' ),
						'delivered'                 => $this->status_service->getStatusTitle( 'delivered' ),
						'taken_by_courier'          => $this->status_service->getStatusTitle( 'taken_by_courier' ),
						'sent_from_source_branch'   => $this->status_service->getStatusTitle( 'sent_from_source_branch' ),
						'collected_from_sender'     => $this->status_service->getStatusTitle( 'collected_from_sender' ),
						'adopted_at_source_branch'  => $this->status_service->getStatusTitle( 'adopted_at_source_branch' ),
						'adopted_at_sorting_center' => $this->status_service->getStatusTitle( 'adopted_at_sorting_center' ),
						'taken_by_courier_from_pok' => $this->status_service->getStatusTitle( 'taken_by_courier_from_pok' ),
						'unstack_from_box_machine'  => $this->status_service->getStatusTitle( 'unstack_from_box_machine' ),
						'unstack_from_customer_service_point' => $this->status_service->getStatusTitle( 'unstack_from_customer_service_point' ),
					),
				),

				array(
					'title'    => __( 'Creating a shipment automatically after paying for the order', 'inpost-for-woocommerce' ),
					'id'       => 'easypack_create_shipment_automatically',
					'type'     => 'checkbox',
					'class'    => '',
					'css'      => 'min-width: 300px;',
					'desc_tip' => __( 'Check your default settings before enable this option (parcel dimensions, etc.)', 'inpost-for-woocommerce' ),
					'default'  => '',
					'options'  => array(),
				),

				array(
					'title'    => __( 'Do not provide InPost parcel locker service if the weight of goods exceeds 25 kg', 'inpost-for-woocommerce' ),
					'id'       => 'easypack_over_weight',
					'type'     => 'checkbox',
					'class'    => '',
					'css'      => 'min-width: 300px;',
					'desc_tip' => false,
					'default'  => '',
					'options'  => array(),
				),

				array(
					'title'    => __(
						'Send an email to the buyer about the start of the delivery process just after parcel created?',
						'inpost-for-woocommerce'
					),
					'id'       => 'easypack_delivery_notice',
					'type'     => 'checkbox',
					'class'    => '',
					'css'      => 'min-width: 300px;',
					'desc_tip' => false,
					'default'  => '',
					'options'  => array(),

				),

				array(
					'title'    => __(
						'Change order status to Completed after shipment created?',
						'inpost-for-woocommerce'
					),
					'id'       => 'easypack_set_order_completed',
					'type'     => 'checkbox',
					'class'    => '',
					'css'      => 'min-width: 300px;',
					'desc_tip' => false,
					'default'  => '',
					'options'  => array(),

				),

				array(
					'title'    => __(
						"Automatically add buyer order comment to field 'Refernece'",
						'inpost-for-woocommerce'
					),
					'id'       => 'easypack_add_order_note',
					'type'     => 'checkbox',
					'class'    => '',
					'css'      => 'min-width: 300px;',
					'desc_tip' => false,
					'default'  => '',
					'options'  => array(),

				),

				// Custom CSS form map button start
				array(
					'name'    => __( 'Color of map button', 'inpost-for-woocommerce' ),
					'type'    => 'text',
					'desc'    => '',
					'default' => '#FCC905',
					'id'      => 'easypack_custom_button_css',
				),
				// Custom CSS form map button end.
				array(
					'type' => 'sectionend',
					'id'   => 'other_options',
				),

				array(
					'title' => __( 'Sender', 'inpost-for-woocommerce' ),
					'type'  => 'title',
					'desc'  => '',
					'id'    => 'sender_options',
				),

				array(
					'title'             => __( 'First Name', 'inpost-for-woocommerce' ),
					'id'                => 'easypack_sender_first_name',
					'css'               => 'min-width:300px;',
					'default'           => '',
					'type'              => 'text',
					'desc_tip'          => false,
					'custom_attributes' => array( 'required' => 'required' ),
				),

				array(
					'title'             => __( 'Last Name', 'inpost-for-woocommerce' ),
					'id'                => 'easypack_sender_last_name',
					'css'               => 'min-width:300px;',
					'default'           => '',
					'type'              => 'text',
					'desc_tip'          => false,
					'custom_attributes' => array( 'required' => 'required' ),
				),

				array(
					'title'    => __( 'Company Name', 'inpost-for-woocommerce' ),
					'id'       => 'easypack_sender_company_name',
					'css'      => 'min-width:300px;',
					'default'  => '',
					'type'     => 'text',
					'desc_tip' => false,
				),

				array(
					'title'    => __( 'Street', 'inpost-for-woocommerce' ),
					'id'       => 'easypack_sender_street',
					'css'      => 'min-width:300px;',
					'default'  => '',
					'type'     => 'text',
					'desc_tip' => false,
				),

				array(
					'title'    => __( 'Building no', 'inpost-for-woocommerce' ),
					'id'       => 'easypack_sender_building_no',
					'css'      => 'min-width:300px;',
					'default'  => '',
					'type'     => 'text',
					'desc_tip' => false,
				),

				array(
					'title'   => __( 'Post code', 'inpost-for-woocommerce' ),
					'id'      => 'easypack_sender_post_code',
					'css'     => 'min-width:300px;',
					'default' => '',
					'type'    => 'text',
				),

				array(
					'title'             => __( 'City', 'inpost-for-woocommerce' ),
					'id'                => 'easypack_sender_city',
					'css'               => 'min-width:300px;',
					'default'           => '',
					'type'              => 'text',
					'desc_tip'          => false,
					'custom_attributes' => array( 'required' => 'required' ),
				),

				array(
					'title'             => __( 'Email', 'inpost-for-woocommerce' ),
					'id'                => 'easypack_sender_email',
					'css'               => 'min-width:300px;',
					'default'           => '',
					'type'              => 'email',
					'desc_tip'          => false,
					'custom_attributes' => array( 'required' => 'required' ),
				),

				array(
					'title'             => __( 'Phone', 'inpost-for-woocommerce' ),
					'id'                => 'easypack_sender_phone',
					'css'               => 'min-width:300px;',
					'default'           => '',
					'type'              => 'text',
					'desc_tip'          => false,
					'custom_attributes' => array( 'required' => 'required' ),
				),

				array(
					'type' => 'sectionend',
					'id'   => 'sender_options',
				),

				array(
					'title' => __( 'Debug mode', 'inpost-for-woocommerce' ),
					'type'  => 'title',
					'desc'  => '',
					'id'    => 'debug_options',
				),

				array(
					'title'    => __(
						'Inpost shipping methods are only visible to administrators',
						'inpost-for-woocommerce'
					),
					'id'       => 'easypack_debug_mode',
					'type'     => 'checkbox',
					'class'    => '',
					'css'      => 'min-width: 300px;',
					'desc_tip' => false,
					'default'  => '',
					'options'  => array(),

				),

				array(
					'title'    => __(
						'Disable condition to enable geowidget scripts only on Checkout page',
						'inpost-for-woocommerce'
					),
					'id'       => 'easypack_debug_mode_enqueue_scripts',
					'type'     => 'checkbox',
					'class'    => '',
					'css'      => 'min-width: 300px;',
					'desc_tip' => false,
					'default'  => '',
					'options'  => array(),

				),

				array(
					'title'   => __( 'Map button output', 'inpost-for-woocommerce' ),
					'id'      => 'easypack_button_output',
					'type'    => 'select',
					'class'   => 'wc-enhanced-select',
					'css'     => 'min-width: 300px;',
					'default' => 'woocommerce_review_order_after_shipping',
					'options' => array(
						'woocommerce_review_order_before_submit' => __( 'Before the \'Place order\' button', 'inpost-for-woocommerce' ),
						'woocommerce_after_order_notes'    => __( 'After the \'Add note\' field', 'inpost-for-woocommerce' ),
						'woocommerce_before_order_notes'   => __( 'Before the \'Add note\' field', 'inpost-for-woocommerce' ),
						'woocommerce_review_order_before_payment' => __( 'Before information about payment methods', 'inpost-for-woocommerce' ),
						'woocommerce_review_order_before_order_total' => __( 'Before information about the total amount of the order', 'inpost-for-woocommerce' ),
						'woocommerce_review_order_after_order_total' => __( 'After information about the total amount of the order', 'inpost-for-woocommerce' ),
						'woocommerce_review_order_after_shipping' => __( 'After describing the delivery methods', 'inpost-for-woocommerce' ),
						'woocommerce_review_order_before_shipping' => __( 'Before describing the delivery methods', 'inpost-for-woocommerce' ),
						'woocommerce_after_checkout_shipping_form' => __( 'After form with shipping address', 'inpost-for-woocommerce' ),
						'woocommerce_checkout_after_customer_details' => __( 'After customer details', 'inpost-for-woocommerce' ),
						'woocommerce_checkout_before_order_review' => __( 'Before order details review', 'inpost-for-woocommerce' ),
						'woocommerce_review_order_after_submit' => __( 'After the \'Place order\' button', 'inpost-for-woocommerce' ),
						'woocommerce_before_checkout_form' => __( 'Before form with checkout details', 'inpost-for-woocommerce' ),
					),
				),

				array(
					'title'    => __(
						'Enable JS mode of the map button - if the \'Select Parcel Locker\' button does not appear in your checkout',
						'inpost-for-woocommerce'
					),
					'id'       => 'easypack_js_map_button',
					'type'     => 'checkbox',
					'class'    => '',
					'css'      => 'min-width: 300px;',
					'desc_tip' => false,
					'default'  => '',
					'options'  => array(),

				),

				array(
					'title' => '',
					'type'  => 'title',
					'desc'  => '<b>' . __( 'The settings associated with the map button do not work if you are using Checkout based on Blocks.', 'inpost-for-woocommerce' ) . '</b>'
						. '<br></br>'
						. __( 'Please keep in mind that different templates may have a non-standard appearance ', 'inpost-for-woocommerce' )
						. __( 'design, and do not always contain in the code the entire list of standard hooks ', 'inpost-for-woocommerce' )
						. __( 'that are used to operate plugins - in particular, to display the map button.', 'inpost-for-woocommerce' )
						. '<br></br>'
						. __( 'By default, the button should be visible immediately after the block with a choice of delivery methods.', 'inpost-for-woocommerce' )
						. '<br>'
						. __( 'You can try changing where you want the map button to appear using a setting.', 'inpost-for-woocommerce' )
						. '<br>'
						. __( 'If the button is not visible after activating the plugin, it is recommended to start with option \'Before the Place order\' button.', 'inpost-for-woocommerce' ),
					'id'    => 'easypack_button_output_help_desc',
				),

				array(
					'type' => 'sectionend',
					'id'   => 'debug_options',
				),
			);

			return $settings;
		}

		/**
		 * @param string|int $key
		 * @param array $array
		 *
		 * @return mixed
		 */
		private function get_from_array( $key, $array ) {
			return isset( $array[ $key ] ) ? $array[ $key ] : null;
		}

		/**
		 * Save settings
		 *
		 * @throws Exception
		 */
		public function save() {
			$settings = $this->get_settings();

			WC_Admin_Settings::save_fields( $settings );
			EasyPack_API()->clear_cache();

			if ( ! empty( $_REQUEST['easypack_organization_id'] ) && ! empty( $_REQUEST['easypack_token'] ) ) {
				$ping = EasyPack_API()->ping();
				EasyPack()->get_merchant_services( true );
			}

			$easypack_api_change = sanitize_text_field( $_REQUEST['easypack_api_change'] );

			if ( $easypack_api_change == '0' ) {
				$args                       = array();
				$args['first_name']         = get_option( 'easypack_sender_first_name' );
				$args['last_name']          = get_option( 'easypack_sender_last_name' );
				$args['company_name']       = get_option( 'easypack_sender_company_name' );
				$args['phone']              = get_option( 'easypack_sender_phone' );
				$args['email']              = get_option( 'easypack_sender_email' );
				$args['default_machine_id'] = get_option( 'easypack_default_machine_id' );
				$args['address']            = array();

				if ( EasyPack_API()->api_country() !== EasyPack_API::COUNTRY_PL ) {
					unset( $args['default_machine_id'] );
				}
				if ( isset( $args['default_machine_id'] ) && $args['default_machine_id'] == '' ) {
					unset( $args['default_machine_id'] );
				}
			}
		}

		/**
		 * @param int $type
		 *
		 * @return array[]
		 * @throws Exception
		 */
		private function output_dispath_point_module(): array {
			add_action(
				'woocommerce_admin_field_manage_dispath_points_module',
				function ( array $data ) {
					if ( isset( self::$prevent_duplicate['manage_dispath_points_module'] )
						&& self::$prevent_duplicate['manage_dispath_points_module']
					) {
						return;
					}

					$saved_points = get_option( EasyPack::ATTRIBUTE_PREFIX . '_dpoint' );

					?>
					<tr valign="top">

						<th scope="row" class="titledesc">
							<label for="woo_inpost_dpoint_0">
								<?php echo esc_attr( $data['name'] ); ?></label>
						</th>
						<td class="forminp forminp-text">

							<table id="<?php echo esc_attr( EasyPack::ATTRIBUTE_PREFIX ); ?>_dpoint-cell">
								<thead>
								<th><?php esc_html_e( 'Selected', 'inpost-for-woocommerce' ); ?></th>
								<th><?php esc_html_e( 'Point name', 'inpost-for-woocommerce' ); ?></th>
								<th><?php esc_html_e( 'Street', 'inpost-for-woocommerce' ); ?></th>
								<th><?php esc_html_e( 'Building number', 'inpost-for-woocommerce' ); ?></th>
								<th><?php esc_html_e( 'Postal code', 'inpost-for-woocommerce' ); ?></th>
								<th><?php esc_html_e( 'City', 'inpost-for-woocommerce' ); ?></th>
								</thead>

								<?php
								if ( is_array( $saved_points )
									&& ! empty( $saved_points && isset( $saved_points['street'] ) )
								) {

									$dpoints = is_array( $saved_points['street'] )
										? $saved_points['street']
										: array( 0 => $saved_points );

									// we have only single dpoint
									if ( ! is_array( $saved_points['street'] ) ) {

										$saved_points_fix = array();
										foreach ( $saved_points as $v ) {
											$saved_points_fix['point_name']      = array( isset( $saved_points['point_name'] ) ? $saved_points['point_name'] : '' );
											$saved_points_fix['street']          = array( $saved_points['street'] );
											$saved_points_fix['building_number'] = array( $saved_points['building_number'] );
											$saved_points_fix['postal_code']     = array( $saved_points['postal_code'] );
											$saved_points_fix['city']            = array( $saved_points['city'] );
										}

										$saved_points = $saved_points_fix;
									}

									foreach ( $dpoints as $i => $v ) {

										$id = $i;
										?>

										<tr id="<?php echo esc_attr( EasyPack::ATTRIBUTE_PREFIX ); ?>_dpoint_<?php echo esc_attr( $i ); ?>"
											class="<?php echo esc_attr( EasyPack::ATTRIBUTE_PREFIX ); ?>_dpoint-cell-wraper">
											<td>
												<input type="radio"
														value="<?php echo esc_attr( $i ); ?>"
													<?php
													echo $i === (int) get_option( EasyPack::ATTRIBUTE_PREFIX . '_dpoint_selected' )
														? 'checked' : ''
													?>
														class="<?php echo esc_attr( EasyPack::ATTRIBUTE_PREFIX ); ?>_dpoint_selected"
														name="<?php echo esc_attr( EasyPack::ATTRIBUTE_PREFIX ); ?>_dpoint_selected">
												<input type="button"
														value="X"
														class="<?php echo esc_attr( EasyPack::ATTRIBUTE_PREFIX ); ?>_dpoint-remove"
														data-remove="<?php echo esc_attr( EasyPack::ATTRIBUTE_PREFIX ); ?>_dpoint_<?php echo esc_attr( $i ); ?>">

											</td>
											<td>
												<input
														class="<?php echo esc_attr( EasyPack::ATTRIBUTE_PREFIX ); ?>_dpoint"
														value="<?php echo ! empty( $saved_points['point_name'] ) && isset( $saved_points['point_name'][ $i ] ) ? esc_attr( $saved_points['point_name'][ $i ] ) : ''; ?>"
														name="<?php echo esc_attr( EasyPack::ATTRIBUTE_PREFIX ); ?>_dpoint[point_name][]"
														id="<?php echo esc_attr( EasyPack::ATTRIBUTE_PREFIX ); ?>_dpoint_point_name">
											</td>
											<td>
												<input
														class="<?php echo esc_attr( EasyPack::ATTRIBUTE_PREFIX ); ?>_dpoint"
														value="<?php echo ! empty( $saved_points['street'] ) ? esc_attr( $saved_points['street'][ $i ] ) : ''; ?>"
														name="<?php echo esc_attr( EasyPack::ATTRIBUTE_PREFIX ); ?>_dpoint[street][]"
														id="<?php echo esc_attr( EasyPack::ATTRIBUTE_PREFIX ); ?>_dpoint_street">
											</td>
											<td>
												<input
														class="<?php echo esc_attr( EasyPack::ATTRIBUTE_PREFIX ); ?>_dpoint"
														value="<?php echo ! empty( $saved_points['building_number'] ) ? esc_attr( $saved_points['building_number'][ $i ] ) : ''; ?>"
														name="<?php echo esc_attr( EasyPack::ATTRIBUTE_PREFIX ); ?>_dpoint[building_number][]"
														id="<?php echo esc_attr( EasyPack::ATTRIBUTE_PREFIX ); ?>_dpoint_building_number">
											</td>
											<td>
												<input
														class="<?php echo esc_attr( EasyPack::ATTRIBUTE_PREFIX ); ?>_dpoint"
														value="<?php echo ! empty( $saved_points['postal_code'] ) ? esc_attr( $saved_points['postal_code'][ $i ] ) : ''; ?>"
														name="<?php echo esc_attr( EasyPack::ATTRIBUTE_PREFIX ); ?>_dpoint[postal_code][]"
														id="<?php echo esc_attr( EasyPack::ATTRIBUTE_PREFIX ); ?>_dpoint_postal_code">
											</td>
											<td>
												<input
														class="<?php echo esc_attr( EasyPack::ATTRIBUTE_PREFIX ); ?>_dpoint"
														value="<?php echo ! empty( $saved_points['city'] ) ? esc_attr( $saved_points['city'][ $i ] ) : ''; ?>"
														name="<?php echo esc_attr( EasyPack::ATTRIBUTE_PREFIX ); ?>_dpoint[city][]"
														id="<?php echo esc_attr( EasyPack::ATTRIBUTE_PREFIX ); ?>_dpoint_city">
											</td>
										</tr>
										<?php
									}
								} else {
									$k = 0;
									?>
									<tr id="<?php echo esc_attr( EasyPack::ATTRIBUTE_PREFIX ); ?>_dpoint_<?php echo esc_attr( $k ); ?>"
										class="<?php echo esc_attr( EasyPack::ATTRIBUTE_PREFIX ); ?>_dpoint-cell-wraper">
										<td>
											<input type="radio"
													value="<?php echo esc_attr( $k ); ?>"
												<?php
												echo $k === (int) get_option( EasyPack::ATTRIBUTE_PREFIX . '_dpoint_selected' )
													? 'checked' : ''
												?>
													class="<?php echo esc_attr( EasyPack::ATTRIBUTE_PREFIX ); ?>_dpoint_selected"
													name="<?php echo esc_attr( EasyPack::ATTRIBUTE_PREFIX ); ?>_dpoint_selected">
											<input type="button"
													value="X"
													class="<?php echo esc_attr( EasyPack::ATTRIBUTE_PREFIX ); ?>_dpoint-remove"
													data-remove="<?php echo esc_attr( EasyPack::ATTRIBUTE_PREFIX ); ?>_dpoint_<?php echo esc_attr( $k ); ?>">

										</td>
										<td>
											<input
													class="<?php echo esc_attr( EasyPack::ATTRIBUTE_PREFIX ); ?>_dpoint"
													value=""
													name="<?php echo esc_attr( EasyPack::ATTRIBUTE_PREFIX ); ?>_dpoint[point_name]"
													id="<?php echo esc_attr( EasyPack::ATTRIBUTE_PREFIX ); ?>_dpoint_point_name">
										</td>
										<td>
											<input
													class="<?php echo esc_attr( EasyPack::ATTRIBUTE_PREFIX ); ?>_dpoint"
													value=""
													name="<?php echo esc_attr( EasyPack::ATTRIBUTE_PREFIX ); ?>_dpoint[street]"
													id="<?php echo esc_attr( EasyPack::ATTRIBUTE_PREFIX ); ?>_dpoint_street">
										</td>
										<td>
											<input
													class="<?php echo esc_attr( EasyPack::ATTRIBUTE_PREFIX ); ?>_dpoint"
													value=""
													name="<?php echo esc_attr( EasyPack::ATTRIBUTE_PREFIX ); ?>_dpoint[building_number]"
													id="<?php echo esc_attr( EasyPack::ATTRIBUTE_PREFIX ); ?>_dpoint_building_number">
										</td>
										<td>
											<input
													class="<?php echo esc_attr( EasyPack::ATTRIBUTE_PREFIX ); ?>_dpoint"
													value=""
													name="<?php echo esc_attr( EasyPack::ATTRIBUTE_PREFIX ); ?>_dpoint[postal_code]"
													id="<?php echo esc_attr( EasyPack::ATTRIBUTE_PREFIX ); ?>_dpoint_postal_code">
										</td>
										<td>
											<input
													class="<?php echo esc_attr( EasyPack::ATTRIBUTE_PREFIX ); ?>_dpoint"
													value=""
													name="<?php echo esc_attr( EasyPack::ATTRIBUTE_PREFIX ); ?>_dpoint[city]"
													id="<?php echo esc_attr( EasyPack::ATTRIBUTE_PREFIX ); ?>_dpoint_city">
										</td>
									</tr>
																	<?php
								}
								?>

							</table>
						</td>
					</tr>
					<tr>
						<td>
							<button id="<?php echo esc_attr( EasyPack::ATTRIBUTE_PREFIX ); ?>_dpoint_add"
									value="Add dispath point"><?php esc_html_e( 'Add dispath point', 'inpost-for-woocommerce' ); ?>
							</button>
						</td>
					</tr>
					<?php

					self::$prevent_duplicate['manage_dispath_points_module'] = true;
				},
				10
			);

			return array(
				'name'  => __( 'Manage dispath points', 'inpost-for-woocommerce' ),
				'title' => __( 'Manage dispath points', 'inpost-for-woocommerce' ),
				'type'  => 'manage_dispath_points_module',
				'id'    => EasyPack::ATTRIBUTE_PREFIX . '_dpoint',

			);
		}





		/**
		 * Outputs the courier templates management module HTML.
		 *
		 * @return array Configuration array for the templates module field.
		 *
		 * @since 1.0.0
		 * @access private
		 */
		private function output_courier_templates_module() {
			add_action(
				'woocommerce_admin_field_manage_inpost_pl_courier_templates_module',
				function ( $data ) {
					if ( isset( self::$prevent_duplicate['manage_inpost_pl_courier_templates_module'] )
						&& self::$prevent_duplicate['manage_inpost_pl_courier_templates_module']
					) {
						return;
					}

					$saved_templates = get_option( EasyPack::ATTRIBUTE_TEMPLATES_PREFIX . '_dmtemplates' );
					?>
					<tr id="inpost-pl-courier-templates">
						<th scope="row" class="titledesc">
							<label for="inpost_pl_ctemplate_0">
								<?php echo esc_attr( $data['name'] ); ?></label>
						</th>
						<td class="forminp forminp-text">

							<table id="<?php echo esc_attr( EasyPack::ATTRIBUTE_TEMPLATES_PREFIX ); ?>_ctemplate-cell">
								<thead>
								<th><?php esc_html_e( 'Selected', 'inpost-for-woocommerce' ); ?></th>
								<th><?php esc_html_e( 'Template name', 'inpost-for-woocommerce' ); ?></th>
								<th><?php esc_html_e( 'Length (mm)', 'inpost-for-woocommerce' ); ?></th>
								<th><?php esc_html_e( 'Width (mm)', 'inpost-for-woocommerce' ); ?></th>
								<th><?php esc_html_e( 'Height (mm)', 'inpost-for-woocommerce' ); ?></th>
								<th><?php esc_html_e( 'Weight (kg)', 'inpost-for-woocommerce' ); ?></th>
								<th><?php esc_html_e( 'Not standard?', 'inpost-for-woocommerce' ); ?></th>
								</thead>

								<?php

								if ( is_array( $saved_templates ) && ! empty( $saved_templates ) && ! empty( $saved_templates[0]['name'] ) ) {
									$k = 0;
									foreach ( $saved_templates as $i => $dim_template ) {
										if ( ! is_numeric( $i ) || empty( $dim_template['name'] ) ) {
											continue;
										}

										$dim_template_slug = ! empty( $dim_template['slug'] ) ? $dim_template['slug'] : '';
										$is_not_standard   = ! empty( $dim_template['not_standard'] ) ? $dim_template['not_standard'] : '';

										?>
										<tr id="<?php echo esc_attr( EasyPack::ATTRIBUTE_TEMPLATES_PREFIX ); ?>_dmtemplate_<?php echo esc_attr( $k ); ?>"
											class="<?php echo esc_attr( EasyPack::ATTRIBUTE_TEMPLATES_PREFIX ); ?>_dmtemplate-cell-wraper" data-index="<?php echo esc_attr( $k ); ?>">
											<td class="inpost_pl_manage_template_td">
												<input  type="radio"
														data-id="selected"
														<?php echo $i === (int) get_option( EasyPack::ATTRIBUTE_TEMPLATES_PREFIX . '_dmtemplate_selected' ) ? 'checked' : ''; ?>
														value="<?php echo esc_attr( $k ); ?>"
														class="<?php echo esc_attr( EasyPack::ATTRIBUTE_TEMPLATES_PREFIX ); ?>_dmtemplate_selected"
														name="<?php echo esc_attr( EasyPack::ATTRIBUTE_TEMPLATES_PREFIX ); ?>_dmtemplate_selected"
												>
												<input
														type="button"
														value="X"
														class="<?php echo esc_attr( EasyPack::ATTRIBUTE_TEMPLATES_PREFIX ); ?>_dmtemplate-remove"
														data-remove="<?php echo esc_attr( $k ); ?>"
												>
											</td>
											<td>
												<input
														class="<?php echo esc_attr( EasyPack::ATTRIBUTE_TEMPLATES_PREFIX ); ?>_dmtemplate"
														value="<?php echo esc_html( $dim_template['name'] ); ?>"
														data-id="name"
														type="text"
														name="<?php echo esc_attr( EasyPack::ATTRIBUTE_TEMPLATES_PREFIX ); ?>_dmtemplates[<?php echo esc_attr( $k ); ?>][name]"
												>
												<input
														class="inpost_intl_template_slug"
														value="<?php echo esc_attr( $dim_template_slug ); ?>"
														data-id="slug"
														type="hidden"
														name="<?php echo esc_attr( EasyPack::ATTRIBUTE_TEMPLATES_PREFIX ); ?>_dmtemplates[<?php echo esc_attr( $k ); ?>][slug]"
												>
											</td>
											<td>
												<input
														class="<?php echo esc_attr( EasyPack::ATTRIBUTE_TEMPLATES_PREFIX ); ?>_dmtemplate inpost_intl_validated_input"
														value="<?php echo esc_html( $dim_template['length'] ); ?>"
														data-id="length"
														type="number"
														step="0.1"
														min="0"
														max="3500"
														name="<?php echo esc_attr( EasyPack::ATTRIBUTE_TEMPLATES_PREFIX ); ?>_dmtemplates[<?php echo esc_attr( $k ); ?>][length]"
												>
											</td>
											<td>
												<input
														class="<?php echo esc_attr( EasyPack::ATTRIBUTE_TEMPLATES_PREFIX ); ?>_dmtemplate inpost_intl_validated_input"
														value="<?php echo esc_html( $dim_template['width'] ); ?>"
														data-id="width"
														type="number"
														step="0.1"
														min="0"
														max="2400"
														name="<?php echo esc_attr( EasyPack::ATTRIBUTE_TEMPLATES_PREFIX ); ?>_dmtemplates[<?php echo esc_attr( $k ); ?>][width]"
												>
											</td>
											<td>
												<input
														class="<?php echo esc_attr( EasyPack::ATTRIBUTE_TEMPLATES_PREFIX ); ?>_dmtemplate inpost_intl_validated_input"
														value="<?php echo esc_html( $dim_template['height'] ); ?>"
														data-id="height"
														type="number"
														step="0.1"
														min="0"
														max="2400"
														name="<?php echo esc_attr( EasyPack::ATTRIBUTE_TEMPLATES_PREFIX ); ?>_dmtemplates[<?php echo esc_attr( $k ); ?>][height]"
												>
											</td>
											<td>
												<input
														class="<?php echo esc_attr( EasyPack::ATTRIBUTE_TEMPLATES_PREFIX ); ?>_dmtemplate inpost_intl_validated_input"
														value="<?php echo esc_html( $dim_template['weight'] ); ?>"
														data-id="weight"
														type="number"
														step="0.1"
														min="0"
														max="50"
														name="<?php echo esc_attr( EasyPack::ATTRIBUTE_TEMPLATES_PREFIX ); ?>_dmtemplates[<?php echo esc_attr( $k ); ?>][weight]"
												>
											</td>
											<td>
												<select
														class="<?php echo esc_attr( EasyPack::ATTRIBUTE_TEMPLATES_PREFIX ); ?>_dmtemplate"
														data-id="not_standard"
														name="<?php echo esc_attr( EasyPack::ATTRIBUTE_TEMPLATES_PREFIX ); ?>_dmtemplates[<?php echo esc_attr( $k ); ?>][not_standard]"
												>
													<option value="0" <?php selected( $is_not_standard, '0' ); ?>><?php esc_html_e( 'No', 'inpost-for-woocommerce' ); ?></option>
													<option value="1" <?php selected( $is_not_standard, '1' ); ?>><?php esc_html_e( 'Yes', 'inpost-for-woocommerce' ); ?></option>
												</select>
											</td>
										</tr>
										<?php
										++$k;
									}
								} else {

									$k = 0;
									?>
									<tr id="<?php echo esc_attr( EasyPack::ATTRIBUTE_TEMPLATES_PREFIX ); ?>_dmtemplate_<?php echo esc_attr( $k ); ?>"
										class="<?php echo esc_attr( EasyPack::ATTRIBUTE_TEMPLATES_PREFIX ); ?>_dmtemplate-cell-wraper">
										<td class="inpost_pl_manage_template_td">
											<input  type="radio"
													data-id="selected"
													value="<?php echo esc_attr( $k ); ?>"
													class="<?php echo esc_attr( EasyPack::ATTRIBUTE_TEMPLATES_PREFIX ); ?>_dmtemplate_selected"
													name="<?php echo esc_attr( EasyPack::ATTRIBUTE_TEMPLATES_PREFIX ); ?>_dmtemplate_selected"
											>
											<input
													type="button"
													value="X"
													class="<?php echo esc_attr( EasyPack::ATTRIBUTE_TEMPLATES_PREFIX ); ?>_dmtemplate-remove"
													data-remove="<?php echo esc_attr( $k ); ?>"
											>
										</td>
										<td>
											<input
													class="<?php echo esc_attr( EasyPack::ATTRIBUTE_TEMPLATES_PREFIX ); ?>_dmtemplate"
													value=""
													data-id="name"
													type="text"
													name="<?php echo esc_attr( EasyPack::ATTRIBUTE_TEMPLATES_PREFIX ); ?>_dmtemplates[<?php echo esc_attr( $k ); ?>][name]"
											>
											<input
													class="inpost_intl_template_slug"
													value=""
													data-id="slug"
													type="hidden"
													name="<?php echo esc_attr( EasyPack::ATTRIBUTE_TEMPLATES_PREFIX ); ?>_dmtemplates[<?php echo esc_attr( $k ); ?>][slug]"
											>
										</td>
										<td>
											<input
													class="<?php echo esc_attr( EasyPack::ATTRIBUTE_TEMPLATES_PREFIX ); ?>_dmtemplate inpost_intl_validated_input"
													value=""
													data-id="length"
													type="number"
													step="0.1"
													min="0"
													max="3500"
													name="<?php echo esc_attr( EasyPack::ATTRIBUTE_TEMPLATES_PREFIX ); ?>_dmtemplates[<?php echo esc_attr( $k ); ?>][length]"
											>
										</td>
										<td>
											<input
													class="<?php echo esc_attr( EasyPack::ATTRIBUTE_TEMPLATES_PREFIX ); ?>_dmtemplate inpost_intl_validated_input"
													value=""
													data-id="width"
													type="number"
													step="0.1"
													min="0"
													max="2400"
													name="<?php echo esc_attr( EasyPack::ATTRIBUTE_TEMPLATES_PREFIX ); ?>_dmtemplates[<?php echo esc_attr( $k ); ?>][width]"
											>
										</td>
										<td>
											<input
													class="<?php echo esc_attr( EasyPack::ATTRIBUTE_TEMPLATES_PREFIX ); ?>_dmtemplate inpost_intl_validated_input"
													value=""
													data-id="height"
													type="number"
													step="0.1"
													min="0"
													max="2400"
													name="<?php echo esc_attr( EasyPack::ATTRIBUTE_TEMPLATES_PREFIX ); ?>_dmtemplates[<?php echo esc_attr( $k ); ?>][height]"
											>
										</td>
										<td>
											<input
													class="<?php echo esc_attr( EasyPack::ATTRIBUTE_TEMPLATES_PREFIX ); ?>_dmtemplate inpost_intl_validated_input"
													value=""
													data-id="weight"
													type="number"
													step="0.1"
													min="0"
													max="50"
													name="<?php echo esc_attr( EasyPack::ATTRIBUTE_TEMPLATES_PREFIX ); ?>_dmtemplates[<?php echo esc_attr( $k ); ?>][weight]"
											>
										</td>
										<td>
											<select
													class="<?php echo esc_attr( EasyPack::ATTRIBUTE_TEMPLATES_PREFIX ); ?>_dmtemplate"
													data-id="not_standard"
													name="<?php echo esc_attr( EasyPack::ATTRIBUTE_TEMPLATES_PREFIX ); ?>_dmtemplates[<?php echo esc_attr( $k ); ?>][not_standard]"
											>
												<option value="0"><?php esc_html_e( 'No', 'inpost-for-woocommerce' ); ?></option>
												<option value="1"><?php esc_html_e( 'Yes', 'inpost-for-woocommerce' ); ?></option>
											</select>
										</td>
									</tr>
									<?php
								}
								?>
							</table>
						</td>
					</tr>
					<tr>
						<td>
							<button id="<?php echo esc_attr( EasyPack::ATTRIBUTE_TEMPLATES_PREFIX ); ?>_dmtemplate_add"
									value="Add courier template"><?php echo esc_html__( 'Add template', 'inpost-for-woocommerce' ); ?>
							</button>
						</td>
					</tr>
					<?php

					self::$prevent_duplicate['manage_inpost_pl_courier_templates_module'] = true;
				},
				10
			);

			return array(
				'name'  => esc_html__( 'Manage courier templates', 'inpost-for-woocommerce' ),
				'title' => esc_html__( 'Manage courier templates', 'inpost-for-woocommerce' ),
				'type'  => 'manage_inpost_pl_courier_templates_module',
				'id'    => EasyPack::ATTRIBUTE_TEMPLATES_PREFIX . '_dmtemplates',

			);
		}
	}

endif;
