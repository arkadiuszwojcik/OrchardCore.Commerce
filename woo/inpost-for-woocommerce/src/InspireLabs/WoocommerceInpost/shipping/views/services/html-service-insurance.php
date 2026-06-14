<?php
/**
 * Processing insurance amount
 *
 * @var ShipX_Shipment_Model $shipment
 */

use InspireLabs\WoocommerceInpost\shipx\models\shipment\ShipX_Shipment_Model;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
} // Exit if accessed directly.

if ( $shipment instanceof ShipX_Shipment_Model && ! $additional_package ) {
	$input_disabled = ' disabled ';
	?>
	<label disabled style="display: block" for="insurance_amounts" class="graytext">
		<?php esc_html_e( 'Insurance amount: ', 'inpost-for-woocommerce' ); ?>
	</label>
	<?php
} else {
	$input_disabled = '';
	?>
	<label style="display: block" for="insurance_amounts">
		<?php esc_html_e( 'Insurance amount: ', 'inpost-for-woocommerce' ); ?>
	</label>
	<?php
}

if ( $shipment instanceof ShipX_Shipment_Model ) {
	?>
	
	<?php
	$insurance = '0.00';
	if ( null !== $shipment->getInsurance() ) {
		$insurance = $shipment->getInsurance()->getAmount();
	}
	?>

	<input <?php echo esc_attr( $input_disabled ); ?>
			class="insurance_amount var1"
			type="number"
			style=""
			value="<?php echo esc_attr( $insurance ); ?>"
			placeholder="0.00"
			step="any"
			min="0"
			id="insurance_amounts"
			name="insurance_amounts[]">
	<?php
} else {

	$insurance = EasyPack_Helper()->get_insurance_amount( $order_id );
	?>
	<input <?php echo esc_attr( $input_disabled ); ?>
			class="insurance_amount"
			type="number" style=""
			value="<?php echo esc_attr( $insurance ); ?>"
			placeholder="0.00"
			step="any"
			min="0"
			id="insurance_amounts"
			name="insurance_amounts[]<?php echo esc_attr( $input_disabled ); ?>">
	<?php
}
