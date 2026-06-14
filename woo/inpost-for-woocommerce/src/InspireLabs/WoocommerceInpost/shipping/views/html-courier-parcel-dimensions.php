<?php
/**
 * Template for Shipment Parcel settings.
 *
 * @var ShipX_Shipment_Model $shipment
 * @var ShipX_Shipment_Parcel_Model $parcel
 * @var ShipX_Shipment_Parcel_Model[] $parcels
 * @var int $order_id
 * @var array $length
 * @var array $width
 * @var array $height
 * @var string $selected_service
 */

use InspireLabs\WoocommerceInpost\EasyPack;
use InspireLabs\WoocommerceInpost\shipx\models\shipment\ShipX_Shipment_Model;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
} // Exit if accessed directly.

$courier_parcel_size = array();

$length_value       = 0;
$width_value        = 0;
$height_value       = 0;
$weight_value       = 0;
$non_standard_value = 'no';
$courier_templates  = array();
$selected_template  = 0;

$all_templates = get_option( 'easypack_courier_tmplts_dmtemplates', array() );

if ( ! empty( $all_templates ) && is_array( $all_templates ) ) {
    $courier_templates = EasyPack_Helper()->get_templates_for_select();
    $selected_template = get_option( 'easypack_courier_tmplts_dmtemplate_selected', 0 );
}

$courier_parcel_source = EasyPack_Helper()->get_source_of_courier_dimensions( $order_id );
$courier_parcel_size   = EasyPack_Helper()->get_courier_parcel_dimensions( $order_id, $courier_parcel_source );

if ( 'courier_default_dimensions' === $courier_parcel_source || 'courier_dimensions_from_product' === $courier_parcel_source ) {
    $courier_templates = array( '' => esc_html__( 'Choose template', 'inpost-for-woocommerce' ) ) + $courier_templates;
    $selected_template = '';
} elseif ( empty( $courier_parcel_source ) ) {
    if ( 'yes' === get_option( 'easypack_set_default_courier_dimensions' ) ) {
        $courier_templates = array( '' => esc_html__( 'Choose template', 'inpost-for-woocommerce' ) ) + $courier_templates;
        $selected_template = '';
    }
}

$length_value = ! empty( $courier_parcel_size['length'] )
    ? $courier_parcel_size['length']
    : $parcel->getDimensions()->getLength();

$width_value = ! empty( $courier_parcel_size['width'] )
    ? $courier_parcel_size['width']
    : $parcel->getDimensions()->getWidth();

$height_value = ! empty( $courier_parcel_size['height'] )
    ? $courier_parcel_size['height']
    : $parcel->getDimensions()->getHeight();

$weight_value = ! empty( $courier_parcel_size['weight'] )
    ? $courier_parcel_size['weight']
    : EasyPack_Helper()->get_order_weight( $order_id );

$non_standard_value = ! empty( $courier_parcel_size['not_standard'] )
    ? 'yes'
    : 'no';
?>
<span class="easypack-courier-repeat-block">
	<span class="easypack-courier-repeat-block-btn-close">X</span>
	<span class="easypack-courier-repeat-block-title">
		<label for="easypack_courier_parcel_id">
			<?php echo esc_html__( 'Parcel id', 'inpost-for-woocommerce' ); ?>
			<input id="easypack_courier_parcel_id" class="easypack-repeat-block-title" type="text" value="" name="easypack_courier_parcel_id[]">
		</label>
	</span>
<?php

if ( ! empty( $courier_templates ) ) {
    echo esc_html__( 'Templates:', 'inpost-for-woocommerce' );

    // Select among saved templates.
    $params = array(
        'type'              => 'select',
        'options'           => $courier_templates,
        'class'             => array( 'inpost_pl_package_dimensions' ),
        'input_class'       => array( 'inpost_pl_package_dimensions' ),
        'label'             => '',
        'custom_attributes' => array(
            'style' => 'background: #eee',
        ),
    );
    woocommerce_form_field( 'inpost_pl_package_size_dimensions', $params, $selected_template );
}

woocommerce_form_field( 'parcel_length', $length, $length_value );
woocommerce_form_field( 'parcel_width', $width, $width_value );
woocommerce_form_field( 'parcel_height', $height, $height_value );

$weight = array(
    'type'        => 'number',
    'class'       => array( 'easypack_parcel' ),
    'input_class' => array( 'easypack_parcel' ),
    'label'       => esc_html__( 'Weight:', 'inpost-for-woocommerce' ) . ' ' . $parcel->getWeight()->getUnit(),
    'required'    => true,
);

woocommerce_form_field( 'parcel_weight', $weight, $weight_value );

$non_standard = array(
    'type'        => 'select',
    'options'     => array(
        'no'  => esc_html__( 'no', 'inpost-for-woocommerce' ),
        'yes' => esc_html__( 'yes', 'inpost-for-woocommerce' ),

    ),
    'class'       => array( 'easypack_parcel' ),
    'input_class' => array( 'easypack_parcel' ),
    'label'       => esc_html__( 'Non standard', 'inpost-for-woocommerce' ),
    'required'    => true,
);

if ( 'InPost SmartCourier' === $selected_service ) {
    return;
}

woocommerce_form_field( 'parcel_non_standard', $non_standard, $non_standard_value );
?>
</span>