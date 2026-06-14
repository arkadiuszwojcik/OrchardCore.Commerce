<?php

namespace InspireLabs\WoocommerceInpost;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
} // Exit if accessed directly.

use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;
use InspireLabs\WoocommerceInpost\EasyPack;
use InspireLabs\WoocommerceInpost\EasyPack_Helper;

use Automattic\WooCommerce\Blocks\StoreApi\Schemas\CheckoutSchema;
use Automattic\WooCommerce\StoreApi\Schemas\ExtendSchema;
use Automattic\WooCommerce\StoreApi\StoreApi;


defined( 'ABSPATH' ) || exit;

/**
 * Class for integrating with WooCommerce Blocks
 */
class EasyPackWooBlocks implements IntegrationInterface {


	/**
	 * The name of the integration.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'inpost_pl_block';
	}

	/**
	 * When called invokes any initialization/setup for the integration.
	 */
	public function initialize() {

		$plugin_data = new EasyPack();
		$script_url  = $plugin_data->getPluginJs() . 'build/inpostpl-block-frontend.js';

		$dep = array(
			'dependencies' => array( 'wc-settings', 'wp-data', 'wp-blocks', 'wp-components', 'wp-element', 'wp-i18n', 'wp-primitives' ),
			'version'      => WOOCOMMERCE_INPOST_PL_PLUGIN_VERSION,
		);

		$script_asset = $dep;

		wp_register_script(
			'inpost-pl-wc-blocks-integration',
			$script_url,
			$script_asset['dependencies'],
			$script_asset['version'],
			true
		);

		$translations_folder = plugin_dir_path( WOOCOMMERCE_INPOST_PLUGIN_FILE );
		$translations_folder = $translations_folder . 'language';

		wp_set_script_translations(
			'inpost-pl-wc-blocks-integration',
			'inpost-for-woocommerce',
			$translations_folder
		);
	}

	/**
	 * Returns an array of script handles to enqueue in the frontend context.
	 *
	 * @return string[]
	 */
	public function get_script_handles() {
		return array( 'inpost-pl-wc-blocks-integration' );
	}

	/**
	 * Returns an array of script handles to enqueue in the editor context.
	 *
	 * @return string[]
	 */
	public function get_editor_script_handles() {
		return array( 'inpost-pl-wc-blocks-integration' );
	}

	/**
	 * An array of key, value pairs of data made available to the block on the client side.
	 *
	 * @return array
	 */
	public function get_script_data() {
		return array(
			'configured_methods' => EasyPack_Helper()->get_inpost_methods(),
			'map_btn_text'       => esc_html__( 'Select Parcel Locker', 'inpost-for-woocommerce' ),
		);
	}

	/**
	 * Get the file modified time as a cache buster if we're in dev mode.
	 *
	 * @param string $file Local path to the file.
	 * @return string The cache buster value to use for the given file.
	 */
	protected function get_file_version( $file ) {
		if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG && file_exists( $file ) ) {
			return filemtime( $file );
		}

		return WOOCOMMERCE_INPOST_PL_PLUGIN_VERSION;
	}
}
