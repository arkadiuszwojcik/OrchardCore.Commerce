<?php /** @var ShipX_Shipment_Model $shipment */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
} // Exit if accessed directly.

use InspireLabs\WoocommerceInpost\EasyPack;
use InspireLabs\WoocommerceInpost\shipx\models\shipment\ShipX_Shipment_Model;
use InspireLabs\WoocommerceInpost\shipx\models\shipment_cost\ShipX_Shipment_Cost_Model; ?>

<?php


$calculated_charge_amount
    = $calculated_charge_amount_nc
    = $cod_charge_amount
    = $fuel_charge_amount
    = $insurance_charge_amount
    = $notification_charge_amount
    = '';

if ($shipment instanceof ShipX_Shipment_Model) {
    $cost = $shipment->getInternalData()->getShipmentCost();
} else {
    $cost = null;
}

if ($cost instanceof ShipX_Shipment_Cost_Model) {
    if (true !== $cost->isError()) {
        $calculated_charge_amount    = $cost->getCalculatedChargeAmount();
        $calculated_charge_amount_nc = $cost->getCalculatedChargeAmountNonCommission();
        $cod_charge_amount           = $cost->getCodChargeAmount();
        $fuel_charge_amount          = $cost->getFuelChargeAmount();
        $insurance_charge_amount     = $cost->getInsuranceChargeAmount();
        $notification_charge_amount  = $cost->getNotificationChargeAmount();
    }
}

?>

<ul class="shipment_costs_wrapper<?php echo null === $cost ? ' hidden' : '' ?>">

    <span style="font-weight: bold"><?php esc_html_e('Shipment costs:', 'inpost-for-woocommerce') ?> </span>
    <li>
        <ol style="list-style-type: none">
            <li>
                <span><?php esc_html_e('Calculated charge amount:', 'inpost-for-woocommerce') ?></span>
                <span id="calculated_charge_amount"> <?php echo esc_html( $calculated_charge_amount ); ?></span>
            </li>

            <li>
                <span><?php esc_html_e('Calculated charge amount (non commission):', 'inpost-for-woocommerce') ?></span>
                <span id="calculated_charge_amount_nc"><?php echo esc_html( $calculated_charge_amount_nc ); ?></span>
            </li>

            <li>
                <span><?php esc_html_e('COD charge amount:', 'inpost-for-woocommerce') ?></span>
                <span id="cod_charge_amount"> <?php echo esc_html( $cod_charge_amount ); ?></span>
            </li>

            <li>
                <span><?php esc_html_e('Fuel charge amount:', 'inpost-for-woocommerce') ?></span>
                <span id="fuel_charge_amount"> <?php echo esc_html( $fuel_charge_amount ); ?></span>
            </li>

            <li>
                <span><?php esc_html_e('Insurance charge amount:', 'inpost-for-woocommerce') ?></span>
                <span id="insurance_charge_amount"> <?php echo esc_html( $insurance_charge_amount ); ?></span>
            </li>

            <li>
                <span><?php esc_html_e('Notification charge amount:', 'inpost-for-woocommerce') ?></span>
                <span id="notification_charge_amount"> <?php echo esc_html( $notification_charge_amount ); ?></span>
            </li>
        </ol>
    </li>
</ul>

