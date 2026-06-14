<?php
/*
	Plugin Name: InPost PL
	Plugin URI: https://wordpress.org/plugins/inpost-for-woocommerce/
	Description: InPost for WooCommerce is a dedicated integration plugin, designed for small and medium-sized businesses that want to quickly and conveniently integrate with InPost services.
	Version: 1.8.8
	Author: iLabs.dev
	Author URI: https://ilabs.dev/
	Text Domain: inpost-for-woocommerce
	Domain Path: /languages/
	Tested up to: 6.9
	License: GPLv3

	Copyright 2022 Inspire Labs sp. z o.o.

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; either version 3 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

*/
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use InspireLabs\WoocommerceInpost\admin\EasyPack_Shipment_Manager;
use InspireLabs\WoocommerceInpost\EasyPack;
use InspireLabs\WoocommerceInpost\EasyPack_AJAX;
use InspireLabs\WoocommerceInpost\EasyPack_API;
use InspireLabs\WoocommerceInpost\EasyPack_Helper;

define( 'WOOCOMMERCE_INPOST_PLUGIN_FILE', __FILE__ );
define( 'WOOCOMMERCE_INPOST_PLUGIN_DIR', __DIR__ );
define( 'WOOCOMMERCE_INPOST_PL_PLUGIN_VERSION', '1.8.8' );

//require_once __DIR__ . '/src/ShutdownHandler.php';
//\InspireLabs\WoocommerceInpost\ShutdownHandler::register( __FILE__ );

require_once __DIR__ . '/vendor/autoload.php';

/**
 * @return EasyPack
 */
function EasyPack() {
	return EasyPack::Easypack();
}

/**
 * @return EasyPack_API
 */
function EasyPack_API() {
	return EasyPack_API::EasyPack_API();
}

/**
 * @return EasyPack_Helper
 */
function EasyPack_Helper() {
	return EasyPack_Helper::EasyPack_Helper();
}

add_action(
	'plugins_loaded',
	function () {
		if ( easypack_is_woocommerce_activated() ) {
            if ( easypack_integrity_verified() ) {
                EasyPack_Shipment_Manager::init();
                EasyPack_Helper();
                EasyPack_AJAX::init();
                $_GLOBALS['EasyPack'] = EasyPack();

                add_action(
                    'before_woocommerce_init',
                    function () {
                        if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
                            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables',
                                __FILE__, true);
                        }
                    }
                );
            }
		}
	}
);

register_deactivation_hook( __FILE__, 'easypack_clear_wc_shipping_cache' );
function easypack_clear_wc_shipping_cache() {
	if ( easypack_is_woocommerce_activated() ) {
		\WC_Cache_Helper::get_transient_version( 'shipping', true );
	}
}

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'easypack_inpost_links_filter' );
function easypack_inpost_links_filter( $links ) {
	$plugin_links = array(
		'<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=easypack_general' ) ) . '">' . esc_html__( 'Settings', 'inpost-for-woocommerce' ) . '</a>',
		'<a href="https://inpost.pl/formularz-wsparcie" target="_blank">' . __( 'Support InPost', 'inpost-for-woocommerce' ) . '</a>',
	);

	return array_merge( $plugin_links, $links );
}


/**
 * Check if WooCommerce is activated
 */
if ( ! function_exists( 'easypack_is_woocommerce_activated' ) ) {
	function easypack_is_woocommerce_activated() {

		if ( function_exists( 'is_plugin_active' ) ) {
			if ( is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
				return true;
			}
		}

		if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
			return true;
		}

		if ( defined( 'WC_PLUGIN_FILE' ) && defined( 'WC_VERSION' ) ) {
			if ( version_compare( WC_VERSION, '5.5', '>=' ) ) {
				return true;
			}
		}

		return false;
	}
}

if ( ! function_exists( 'easypack_integrity_verified' ) ) {
    function easypack_integrity_verified() {
        if (class_exists('InspireLabs\WoocommerceInpost\admin\EasyPack_Shipment_Manager')
        && class_exists('InspireLabs\WoocommerceInpost\EasyPack_Helper')
        && class_exists('InspireLabs\WoocommerceInpost\EasyPack_AJAX')
        && class_exists('InspireLabs\WoocommerceInpost\EasyPack')
        ) {
            return true;
        }
        return false;
    }
}
