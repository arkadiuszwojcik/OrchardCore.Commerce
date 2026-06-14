<?php

namespace InspireLabs\WoocommerceInpost;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
} // Exit if accessed directly.

/**
 * Geowidget_v5
 */
class Geowidget_v5 {

	/**
	 * GEOWIDGET_WIDTH
	 */
	const GEOWIDGET_WIDTH = 800;
	/**
	 * GEOWIDGET_HEIGHT
	 */
	const GEOWIDGET_HEIGHT = 600;

	/**
	 * Environment
	 *
	 * @var string
	 */
	private $environment;
	/**
	 * Assets js uri
	 *
	 * @var mixed|string
	 */
	private $assets_js_uri;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->environment   = EasyPack::get_environment();
		$this->assets_js_uri = EasyPack::get_assets_js_uri();
	}

	/**
	 * Init assets
	 *
	 * @return void
	 */
	public function init_assets() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ), 76 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ), 76 );
	}

	/**
	 * Enqueue scripts
	 *
	 * @return void
	 */
	public function enqueue_scripts() {

		if ( is_checkout() || has_block( 'woocommerce/checkout' ) || get_option( 'easypack_debug_mode_enqueue_scripts' ) === 'yes' ) {

			wp_enqueue_style( 'geowidget-css', $this->get_geowidget_css_src(), array(), WOOCOMMERCE_INPOST_PL_PLUGIN_VERSION );
			wp_enqueue_script(
				'geowidget-inpost',
				$this->get_geowidget_js_src(),
				array( 'jquery' ),
				WOOCOMMERCE_INPOST_PL_PLUGIN_VERSION,
				array( 'in_footer' => true )
			);
			if ( 'yes' !== get_option( 'easypack_js_map_button' ) ) {
				wp_enqueue_script(
					'inpost-pl-manage-geowidget',
					$this->get_easypack_js_src(),
					array( 'jquery' ),
					WOOCOMMERCE_INPOST_PL_PLUGIN_VERSION,
					array( 'in_footer' => true )
				);
				wp_localize_script(
					'inpost-pl-manage-geowidget',
					'inpost_pl_map',
					array(
						'button_text1'       => esc_html__( 'Select Parcel Locker', 'inpost-for-woocommerce' ),
						'button_text2'       => esc_html__( 'Change Parcel Locker', 'inpost-for-woocommerce' ),
						'error_text'         => esc_html__( 'Some error is occured', 'inpost-for-woocommerce' ),
						'selected_text'      => esc_html__( 'Selected parcel locker:', 'inpost-for-woocommerce' ),
						'updated_text'       => esc_html__( 'Pick up point has been successfuly written', 'inpost-for-woocommerce' ),
						'geowidget_v5_token' => EasyPack::ENVIRONMENT_SANDBOX === $this->environment
							? get_option( 'easypack_geowidget_sandbox_token' )
							: get_option( 'easypack_geowidget_production_token' ),
						'inpost_methods'     => EasyPack_Helper()->get_inpost_methods(),
						'ajaxurl'            => admin_url( 'admin-ajax.php' ),
						'security'           => wp_create_nonce( 'easypack_nonce' ),
						'preloader'          => esc_url( EasyPack()->getPluginImages() . 'inpost-pl-loader.gif' ),
					)
				);
			}
		}
	}

	/**
	 * Enqueue admin scripts
	 *
	 * @return void
	 */
	public function enqueue_admin_scripts() {

		if ( EasyPack_Helper()->is_required_pages_for_modal() ) {
			wp_enqueue_script(
				'easypack-admin-geowidget-settings',
				$this->assets_js_uri . 'admin-geowidget-settings.js',
				array( 'jquery' ),
				WOOCOMMERCE_INPOST_PL_PLUGIN_VERSION,
				array( 'in_footer' => true )
			);
			wp_localize_script(
				'easypack-admin-geowidget-settings',
				'easypackAdminGeowidgetSettings',
				array(
					'width'  => self::GEOWIDGET_WIDTH,
					'height' => self::GEOWIDGET_HEIGHT,
					'token'  => $this->get_token(),
					'title'  => __( 'Select parcel locker', 'inpost-for-woocommerce' ),
				)
			);
			wp_enqueue_style( 'geowidget-css', $this->get_geowidget_css_src(), array(), WOOCOMMERCE_INPOST_PL_PLUGIN_VERSION );
		}

		$current_screen = get_current_screen();

		// only on settings page.
		if ( is_a( $current_screen, 'WP_Screen' ) && 'woocommerce_page_wc-settings' === $current_screen->id ) {
			if ( isset( $_GET['tab'] ) && 'easypack_general' === $_GET['tab'] ) {
				// color picker on settings page.
				wp_enqueue_style( 'wp-color-picker' );
				wp_enqueue_script( 'wp-color-picker' );
				add_action( 'admin_footer', array( $this, 'easypack_color_picker_script' ), 99 );

			}
		}

		if ( EasyPack_Helper()->is_admin_orders_or_plugin_settings_related_page() ) {
			wp_enqueue_script(
				'geowidget-inpost',
				$this->get_geowidget_js_src(),
				array( 'jquery' ),
				WOOCOMMERCE_INPOST_PL_PLUGIN_VERSION,
				array( 'in_footer' => true )
			);

		}
	}

	/**
	 * Get geowidget js src
	 *
	 * @return string
	 */
	private function get_geowidget_js_src(): string {
		return EasyPack::ENVIRONMENT_SANDBOX === $this->environment
			? EasyPack()->getPluginJs() . 'sandbox-inpost-geowidget.js'
			: EasyPack()->getPluginJs() . 'inpost-geowidget.js';
	}

	/**
	 * Get easypack js src
	 *
	 * @return string
	 */
	private function get_easypack_js_src(): string {
		return EasyPack()->getPluginJs() . 'inpost-pl.js';
	}

	/**
	 * Get geowidget css src
	 *
	 * @return string
	 */
	private function get_geowidget_css_src(): string {
		return EasyPack()->getPluginCss() . 'inpost-geowidget.css';
	}


	/**
	 * Get token
	 *
	 * @return string
	 */
	public function get_token(): string {
		return EasyPack::ENVIRONMENT_SANDBOX === $this->environment
			? get_option( 'easypack_geowidget_sandbox_token' )
			: get_option( 'easypack_geowidget_production_token' );
	}


	/**
	 * Get pickup delivery configuration
	 *
	 * @param string $shipping_method_id $shipping_method_id.
	 *
	 * @return string
	 */
	public function get_pickup_delivery_configuration( string $shipping_method_id ): string {

		$default = 'parcelCollect';

		switch ( $shipping_method_id ) {
			case 'easypack_parcel_machines':
				return 'parcelCollect';

			case 'easypack_parcel_machines_economy':
				return 'parcelCollect';

			case 'easypack_parcel_machines_economy_cod':
				return 'parcelCollectPayment';

			case 'easypack_parcel_machines_cod':
				return 'parcelCollectPayment';

			case 'easypack_shipping_courier_c2c':
				return 'parcelSend';

			case 'easypack_parcel_machines_weekend':
				return 'parcelCollect247';
			case 'easypack_parcel_machines_weekend_cod':
				return 'parcelCollect247';
			default:
				return $default;

		}
	}


	/**
	 * Get pickup point configuration
	 *
	 * @return string
	 */
	public function get_pickup_point_configuration(): string {
		return 'parcelSend';
	}


	/**
	 * Color picker script
	 *
	 * @return void
	 */
	public function easypack_color_picker_script() {
		?>
		<script type="text/javascript">
			jQuery(document).ready(function($){
				$('#easypack_custom_button_css').wpColorPicker({
					defaultColor: false,
					change: function(event, ui){ },
					clear: function(){ },
					hide: true,
					palettes: true
				});
			});
		</script>
		<?php
	}
}
