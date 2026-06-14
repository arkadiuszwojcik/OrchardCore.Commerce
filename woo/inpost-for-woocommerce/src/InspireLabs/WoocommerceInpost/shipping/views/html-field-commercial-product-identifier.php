<?php /** @var ShipX_Shipment_Model $shipment */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
} // Exit if accessed directly.

use InspireLabs\WoocommerceInpost\EasyPack;
use InspireLabs\WoocommerceInpost\shipx\models\shipment\ShipX_Shipment_Model; ?>
<p>
<?php if ( $shipment instanceof ShipX_Shipment_Model ): ?>
    <label disabled style="display: block" for="commercial_product_identifier"
           class="graytext">
		<?php esc_html_e( 'Commercial product identifier: ', 'inpost-for-woocommerce' ); ?>
    </label>
<?php else: ?>
    <label disabled style="display: block" for="commercial_product_identifier" class="">
		<?php esc_html_e( 'Commercial product identifier: ', 'inpost-for-woocommerce' ); ?>
    </label>
<?php endif ?>

<?php if ( $shipment instanceof ShipX_Shipment_Model
           && null !== $shipment->getCommercialProductIdentifier() && ! $additional_package
): ?>
    <input disabled class="commercial_product_identifier"
           type="text"
           style=""
           value="
<?php echo esc_attr( $shipment->getCommercialProductIdentifier() ); ?>"
           id="commercial_product_identifier"
           name="commercial_product_identifier">
<?php else: ?>
    <input class="commercial_product_identifier"
           type="text"
           style=""
           value="
<?php echo esc_attr( $commercial_product_identifier ) ?>"
           id="commercial_product_identifier"
           name="commercial_product_identifier">
<?php endif; ?>
</p>