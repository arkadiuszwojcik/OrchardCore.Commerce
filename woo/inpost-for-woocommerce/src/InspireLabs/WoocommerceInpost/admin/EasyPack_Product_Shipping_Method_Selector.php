<?php

namespace InspireLabs\WoocommerceInpost\admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
} // Exit if accessed directly.

use InspireLabs\WoocommerceInpost\EasyPack;
use InspireLabs\WoocommerceInpost\shipping\EasyPack_Shippng_Parcel_Machines;
use WC_Shipping;
use WC_Shipping_Method;

/**
 * EasyPack_Product_Shipping_Method_Selector
 */
class EasyPack_Product_Shipping_Method_Selector {


	/**
	 * META_ID
	 */
	const META_ID = EasyPack::ATTRIBUTE_PREFIX . '_shipping_methods_allowed';

	/**
	 * META_ID_SIZE
	 */
	const META_ID_SIZE = EasyPack::ATTRIBUTE_PREFIX . '_parcel_dimensions';

	/**
	 * Inpost methods
	 *
	 * @var $inpost_methods inpost methods.
	 */
	public static $inpost_methods;

	/**
	 * Product edit hooks
	 *
	 * @return void
	 */
	public function handle_product_edit_hooks() {
		add_filter( 'woocommerce_product_data_tabs', array( $this, 'filter_woocommerce_product_data_tabs' ), 10, 1 );
		add_action( 'woocommerce_product_data_panels', array( $this, 'action_woocommerce_product_data_panels' ), 10, 0 );
		add_action( 'woocommerce_admin_process_product_object', array( $this, 'action_woocommerce_admin_process_product_object' ), 10, 1 );
		add_action( 'woocommerce_product_options_shipping', array( $this, 'easypack_parcel_size_select' ), 10, 0 );
	}


	/**
	 * Get product config by product ID
	 *
	 * @param int $product_id $product_id.
	 *
	 * @return array|null
	 */
	public function get_config_by_product_id( int $product_id ): ?array {
		$meta = get_post_meta( $product_id, self::META_ID, true );
		// if saved zero methods (all methods unchecked before save) - we have empty array.
		// if product is new - return null to show all checked in is_checked function.
		return is_array( $meta ) ? $meta : null;
	}

	/**
	 * Get inpost methods by product_id
	 *
	 * @param int $product_id $product_id.
	 *
	 * @return array
	 */
	private function get_inpost_methods_by_product_id( int $product_id ): array {
		$return              = array();
		$new_config_settings = false;

		$config_raw = $this->get_config_by_product_id( $product_id );

		// reset settings for old plugin version.
		if ( ! $this->saved_product_settings_is_actual( $config_raw ) ) {
			$config_raw = null;
		}

		$all_inpost_methods = EasyPack_Helper()->get_inpost_methods();

		$is_esmartmix_enabled_for_all = 'yes' === get_option( 'easypack_enable_for_all_esmartmix' );

		if ( null === $config_raw ) { // by default for all products.
			if ( ! empty( $all_inpost_methods ) ) {
				foreach ( $all_inpost_methods as $method ) {
					if ( 'easypack_shipping_esmartmix' === $method['method_title'] ) {
						if ( ! $is_esmartmix_enabled_for_all ) {
							continue;
						}
					}
					$return[] = $method['method_title_with_id'];
				}
			}
		}

		if ( null !== $config_raw && is_array( $config_raw ) ) {

			if ( $is_esmartmix_enabled_for_all ) {
				foreach ( $all_inpost_methods as $method ) {
					if ( 'easypack_shipping_esmartmix' === $method['method_title'] ) {
						$config_raw[] = $method['method_title_with_id'];
					}
				}
			}

			return $config_raw;
		}

		return $return;
	}


	/**
	 * Add custom product setting tab.
	 *
	 * @param  array $default_tabs $default_tabs.
	 */
	public function filter_woocommerce_product_data_tabs( $default_tabs ): array {
		$default_tabs['custom_tab'] = array(
			'label'    => __( 'InPost', 'inpost-for-woocommerce' ),
			'target'   => 'wk_custom_tab_data',
			'priority' => 60,
			'class'    => array(),
		);

		return $default_tabs;
	}

	/**
	 * Is checkbox checked
	 *
	 * @param array|null $config_by_product $config_by_product.
	 * @param string     $method_id  $method_id.
	 *
	 * @return bool
	 */
	private function is_checked( ?array $config_by_product, $method_id ) {

		// reset settings for old plugin version.
		if ( ! $this->saved_product_settings_is_actual( $config_by_product ) ) {
			$config_by_product = null;
		}

		// first time product open for edit.
		if ( null === $config_by_product ) {
			return true;
		}

		if ( is_array( $config_by_product ) && in_array( $method_id, $config_by_product ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Contents custom product setting tab.
	 */
	public function action_woocommerce_product_data_panels() {
		global $post;
		echo '<div id="wk_custom_tab_data" class="panel woocommerce_options_panel">';
		$config_by_product = $this->get_config_by_product_id( $post->ID );

		foreach ( EasyPack_Helper()->get_inpost_methods() as $key => $method ) {
			woocommerce_wp_checkbox(
				array(
					'id'    => $this->get_post_key_from_method_id( $method['method_title_with_id'] ),
					'label' => $method['user_title'],
					'value' => $this->is_checked( $config_by_product, $method['method_title_with_id'] ) ? 'yes' : null,
				)
			);
		}

		echo '</div>';
	}

	/**
	 * Get_post_key_from_method_id
	 *
	 * @param string $method_id $method_id.
	 *
	 * @return string
	 */
	private function get_post_key_from_method_id( string $method_id ): string {
		return '_' . EasyPack::ATTRIBUTE_PREFIX . '_shipping_method_id_' . $method_id;
	}


	/**
	 * Save the checkbox.
	 *
	 * @param \WC_Product $product $product.
	 */
	public function action_woocommerce_admin_process_product_object( $product ): void {
		$allowed_methods = array();
		foreach ( EasyPack_Helper()->get_inpost_methods() as $method ) {
			$post_key = $this->get_post_key_from_method_id( $method['method_title_with_id'] );
			if ( isset( $_POST[ $post_key ] ) && $_POST[ $post_key ] === 'yes' ) {
				$allowed_methods[] = $method['method_title_with_id'];
			}
		}

		$product->update_meta_data( self::META_ID, $allowed_methods );

		if ( isset( $_POST['easypack_parcel_dimensions'] ) && ! empty( $_POST['easypack_parcel_dimensions'] ) ) {
			$product->update_meta_data( self::META_ID_SIZE, sanitize_text_field( $_POST['easypack_parcel_dimensions'] ) );
		}
		// clear shipping methods cache.
		\WC_Cache_Helper::get_transient_version( 'shipping', true );
	}

	/**
	 * Return allowed InPost shipping methods defined for products in cart
	 *
	 * @param array $contents_of_the_cart
	 *
	 * @return array
	 */
	public function get_methods_allowed_by_cart( array $contents_of_the_cart ): array {

		$config_by_product  = array();
		$physical_goods_ids = array();
		$total_weight       = 0;

		foreach ( $contents_of_the_cart as $cart_item_key => $cart_item ) {

			// if variation in cart.
			if ( $cart_item['variation_id'] ) {

				$variant = wc_get_product( $cart_item['variation_id'] );
				if ( ! $variant->is_virtual() /* && ! $variant->is_downloadable() */ ) {
					$physical_goods_ids[] = $cart_item['product_id'];
					$total_weight        += floatval( $variant->get_weight() ) * $cart_item['quantity'];
				}
			} else {

				$_product = wc_get_product( $cart_item['product_id'] );
				if ( ! $_product->is_virtual() /* && ! $_product->is_downloadable() */ ) {
					$physical_goods_ids[] = $cart_item['product_id'];
					$total_weight        += floatval( $_product->get_weight() ) * $cart_item['quantity'];
				}
			}
		}

		$physical_goods_ids = array_unique( $physical_goods_ids );

		$is_esmartmix_enabled_for_all = 'yes' === get_option( 'easypack_enable_for_all_esmartmix' );

		if ( ! empty( $physical_goods_ids ) ) {

			if ( get_option( 'easypack_enable_for_all_products' ) === 'yes' ) {

				foreach ( EasyPack_Helper()->get_inpost_methods() as $method ) {
					if ( 'easypack_shipping_esmartmix' === $method['method_title'] ) {
						if ( ! $is_esmartmix_enabled_for_all ) {
							continue;
						}
					}
					$config_by_product[] = $method['method_title_with_id'];
				}

				return $this->block_overweight( $total_weight, $config_by_product );

			} else {

				foreach ( $physical_goods_ids as $id ) {
					$config_by_product[ $id ] = $this->get_inpost_methods_by_product_id( $id );
				}

				if ( count( $physical_goods_ids ) === 1 ) {
					$config_by_product = $this->block_overweight( $total_weight, $config_by_product[ $id ] );
					return $config_by_product;
				}

                $config_by_product_raw = $config_by_product;

				if ( is_array( $config_by_product ) && count( $config_by_product ) > 1 ) {
					$config_by_product = call_user_func_array( 'array_intersect', $config_by_product );
					$config_by_product = $this->block_overweight( $total_weight, $config_by_product );
				}

                if( 'yes' === get_option('easypack_set_major_method')) {
                    if (empty($config_by_product)) {
                        $cart_data['contents'] = $contents_of_the_cart;
                        $config_by_product     = $this->set_most_expensive($config_by_product_raw, $cart_data);
                    }
                }

			}
		}

		return $config_by_product;
	}


	/**
	 * Add option with parcel dimensions on product edit page
	 */
	public function easypack_parcel_size_select() {
		global $post;

		$options = EasyPack()->get_package_sizes_gabaryt();

		woocommerce_wp_select(
			array(
				'id'      => 'easypack_parcel_dimensions',
				'label'   => __( 'InPost parcel dimensions', 'inpost-for-woocommerce' ),
				'options' => $options,
				'value'   => get_post_meta( $post->ID, self::META_ID_SIZE, true ),
			)
		);
	}


	/**
	 * Block weight over 25 kg for two methods: paczkomaty and courier_c2c
	 *
	 * @param float $total_weight $total_weight.
	 * @param array $config_by_product $config_by_product.
	 */
	private function block_overweight( $total_weight, $config_by_product ) {

		if ( 'yes' === get_option( 'easypack_over_weight' ) ) {
			if ( $total_weight >= 25 ) {

				if ( ! empty( $config_by_product ) && is_array( $config_by_product ) ) {

					foreach ( $config_by_product as $key => $allowed_method ) {
						if ( 0 === strpos( $allowed_method, 'easypack_' ) ) {
							$method_name = EasyPack_Helper()->validate_method_name( $allowed_method );
							if ( 'easypack_parcel_machines' === $method_name
								|| 'easypack_parcel_machines_cod' === $method_name
								|| 'easypack_shipping_courier_c2c' === $method_name
								|| 'easypack_shipping_courier_c2c_cod' === $method_name
								|| 'easypack_shipping_esmartmix' === $method_name
								|| 'easypack_parcel_machines_economy' === $method_name
								|| 'easypack_parcel_machines_economy_cod' === $method_name ) {
								unset( $config_by_product[ $key ] );
							}
							// integration with Flexible Shipping.
						} elseif ( 0 === strpos( $allowed_method, 'flexible_shipping' ) ) {
							$instance_id = '';
							if ( stripos( $allowed_method, ':' ) ) {
								$instance_id = trim( explode( ':', $allowed_method )[1] );
							}
							if ( ! empty( $instance_id ) ) {
								$linked_method = EasyPack_Helper()->get_method_linked_to_fs_by_instance_id( $instance_id );
								if ( 'easypack_parcel_machines' === $linked_method
									|| 'easypack_parcel_machines_cod' === $linked_method
									|| 'easypack_shipping_courier_c2c' === $linked_method
									|| 'easypack_shipping_courier_c2c_cod' === $linked_method
									|| 'easypack_shipping_esmartmix' === $linked_method
									|| 'easypack_parcel_machines_economy' === $linked_method
									|| 'easypack_parcel_machines_economy_cod' === $linked_method ) {
									unset( $config_by_product[ $key ] );
								}
							}
						}
					}
				}
			}
		}

		return $config_by_product;
	}


	/**
	 * We need to reset allowed shipping methods stored in product meta
	 * in some cases: on Flexible Shipping activating/ deactivating
	 * and when customer upgraded plugin as we changed this functionality
	 * after ver. 1.0.5
	 *
	 * @param  mixed $config $config.
	 * @return  bool
	 */
	private function saved_product_settings_is_actual( $config ): bool {
		// empty array in config.
		// settings are correct but all InPost methods are prohibited in product settings.
		if ( is_array( $config ) && empty( $config ) ) {
			return true;
		}

		if ( is_array( $config ) && ! empty( $config ) ) {

			foreach ( $config as $method_name ) {
				// check if settings were saved in previous version of plugin (<= 1.0.5).
				// or product has settings configured in those time when was active Flexible Shipping.
				$is_easypack_method_with_instance_id = stripos( $method_name, ':' ) && 0 === strpos( $method_name, 'easypack_' );
				$is_flexible_method_with_instance_id = stripos( $method_name, ':' ) && 0 === strpos( $method_name, 'flexible_shipping' );

				if ( $is_easypack_method_with_instance_id || $is_flexible_method_with_instance_id ) {
					return true;
				}
			}
		}

		return false;
	}


    /**
     * Determines the most expensive shipping method from a configuration.
     *
     * @param array $config The configuration mapping products to allowed methods.
     * @param array $cart_data The cart data.
     * @return array The shipping method with the highest cost.
     *
     * @since 1.7.5
     * @access private
     */
    private function set_most_expensive($config, $cart_data)
    {

        $cost = array();

        foreach( $config as $product_id => $allowed_methods ) {
            foreach ($allowed_methods as $method ) {

                $cost[$method] = $this->get_shipping_method_cost($method, $cart_data);
            }
        }

        return EasyPack_Helper()->find_key_with_biggest_value( $cost );
    }



    /**
     * Gets the shipping cost for a specific shipping method.
     *
     * @param string $method_id_with_instance The shipping method ID with instance ID.
     * @param array $cart_data The cart data.
     * @return float The shipping cost.
     *
     * @since 1.7.5
     * @access private
     */
    private function get_shipping_method_cost( $method_id_with_instance, $cart_data ) {
        $cost = 0;
        // Parse the method string to get instance_id
        $parts = explode(':', $method_id_with_instance);
        $method_id = $parts[0];
        $instance_id = isset($parts[1]) ? $parts[1] : 0;

        if( ! $instance_id || ! is_numeric($instance_id)) {
            return 0;
        }

        $settings_name = 'woocommerce_' . $method_id . '_' . $instance_id . '_settings';

        $instance_settings = get_option($settings_name);
        if( ! empty($instance_settings) ) {

            if (isset($instance_settings['flat_rate']) && 'yes' === $instance_settings['flat_rate']) {
                return ! empty($instance_settings['cost_per_order']) ? $instance_settings['cost_per_order'] : 0;
            } else {
                if( ! empty($instance_settings['based_on']) ) {
                    $cost = $this->get_cost_of_inpost_method($cart_data, $instance_settings['based_on'], $method_id, $instance_id );
                }
            }

        }

        return $cost;
    }


    /**
     * Calculates the cost of an InPost shipping method based on various criteria.
     *
     * @param array $cart_data The cart data.
     * @param string $based_on The basis for calculation ('size', 'price', 'product_qty', or 'weight').
     * @param string $method_id The shipping method ID.
     * @param int $instance_id The shipping method instance ID.
     * @return float The calculated shipping cost.
     *
     * @since 1.7.5
     * @access private
     */
    private function get_cost_of_inpost_method( array $cart_data, $based_on, $method_id, $instance_id ) {

        $cost = 0;

        $settings_name = 'woocommerce_' . $method_id . '_' . $instance_id . '_settings';
        $instance_settings = get_option($settings_name);

        // based on gabaryt.
        if ( 'size' === $based_on ) {
            $max_gabaryt = EasyPack_Helper()->get_max_gabaryt( $cart_data );
            $cost        = $instance_settings[ 'gabaryt_' . $max_gabaryt ];
            $cost = ! empty($cost) ? $cost : 0;
            return $cost;
        }

        $rates = EasyPack_Helper()->get_saved_method_rates( $method_id, $instance_id );

        if ( ! empty( $rates ) ) {
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
        if ( 'price' === $based_on ) {
            $value = EasyPack_Helper()->package_subtotal( $cart_data['contents'] );
        }
        if ( 'product_qty' === $based_on ) {
            $value = EasyPack_Helper()->package_product_qty( $cart_data['contents'] );
        }
        if ( 'weight' === $based_on ) {
            $value = EasyPack_Helper()->package_weight( $cart_data['contents'] );
        }

        foreach ( $rates as $rate ) {
            if ( floatval( $rate['min'] ) <= $value && floatval( $rate['max'] ) >= $value ) {
                $cost = ! empty( $rate['cost'] ) ? $rate['cost'] : 0;
            }
        }

        return $cost;
    }
}
