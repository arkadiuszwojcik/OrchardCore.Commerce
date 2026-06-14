<?php /** @var ShipX_Shipment_Model $shipment */
/** @var ShipX_Shipment_Parcel_Model $parcel */
/** @var ShipX_Shipment_Parcel_Model[] $parcels */

/**
 * @var int $order_id
 * @var array $length
 * @var array $width
 * @var array $height
 * @var string $selected_service
 * @var string $send_method - The selected sending method
 * @var bool $disabled - Flag indicating if form fields should be disabled *
 * @var bool $send_method_disabled - Flag indicating if send method selection is disabled
 * @var array $send_methods - Available send methods with labels
 */

use InspireLabs\WoocommerceInpost\EasyPack;
use InspireLabs\WoocommerceInpost\shipx\models\shipment\ShipX_Shipment_Model;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
} // Exit if accessed directly.

$custom_attributes = array('style' => 'width:100%;');
if ( $disabled || $send_method_disabled ) {
    $custom_attributes[ 'disabled' ] = 'disabled';
}
$params = array(
    'type' => 'select',
    'options' => $send_methods,
    'class' => array('wc-enhanced-select'),
    'custom_attributes' => $custom_attributes,
    'label' => esc_html__( 'Send method', 'inpost-for-woocommerce' ),
);
if( 'parcel_locker' === $send_method ) {
    $send_method = 'parcel_machine';
}
woocommerce_form_field( 'easypack_send_method', $params, $send_method );