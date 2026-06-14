<?php /** @var ShipX_Shipment_Model $shipment */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
} // Exit if accessed directly.

use InspireLabs\WoocommerceInpost\EasyPack;
use InspireLabs\WoocommerceInpost\EasyPack_Helper;
use InspireLabs\WoocommerceInpost\shipx\models\shipment\ShipX_Shipment_Model;
use InspireLabs\WoocommerceInpost\shipx\models\shipment\ShipX_Shipment_Parcel_Model; ?>

<?php if ( $shipment instanceof ShipX_Shipment_Model && ! $additional_package ) : ?>
    <?php $existed_additional_packages = EasyPack_Helper()->get_saved_additional_packages($order_id);
    if ( is_array($existed_additional_packages) && ! empty($existed_additional_packages) ) : ?>
        <div class="easypack_additional_packages_wrapper" style="display: block; margin-top: 15px;">
            <?php foreach ($existed_additional_packages as $package) : ?>
                <?php if ( is_array($package) && ! empty($package) ) : ?>
                    <div class="easypack_additional_package_data_heading">
                        <?php esc_html_e('Additional package:', 'inpost-for-woocommerce'); ?>
                    </div>
                    <?php foreach ($package as $service => $package_data) : ?>
                        <div class="easypack_additional_package_data">
                        <span class="easypack_additional_package_data_title">
                            <?php esc_html_e('Service:', 'inpost-for-woocommerce'); ?>
                        </span>
                            <span class="easypack_additional_package_data_item">
                            <?php $shipment_service = EasyPack::EasyPack()->get_shipment_service();
                            echo esc_html( $shipment_service->get_customer_service_name_by_id($service) ); ?>
                        </span>
                            <?php if ( isset($package_data['ref_number']) ) : ?>
                                <span class="easypack_additional_package_data_title">
                                <?php esc_html_e('Reference number:', 'inpost-for-woocommerce'); ?>
                            </span>
                                <span class="easypack_additional_package_data_item">
                                <?php echo esc_html( $package_data['ref_number'] ); ?>
                            </span>
                            <?php endif; ?>
                            <?php if ( isset($package_data['tracking']) ) :
                                $tracking_url = EasyPack_Helper()->get_tracking_url(); ?>
                                <span class="easypack_additional_package_data_title">
                                <?php esc_html_e('Tracking number:', 'inpost-for-woocommerce'); ?>
                            </span>
                                <span class="easypack_additional_package_data_item">
                                <?php echo sprintf( '<a target="_blank" href="%s">%s</a>',
                                    esc_url( $tracking_url . $package_data['tracking'] ),
                                    esc_attr( $package_data['tracking'] ) ); ?>
                            </span>
                                <span class="easypack_additional_package_data_title">
                                <?php echo sprintf( '<span class="get_additional_sticker_wrapper"></span>
                                                        <button id="get_sticker_additional" 
                                                        class="get_sticker_additional secondary" 
                                                        data-id="%s" data-order-id="%s" href="#">%s</button><span>',
                                    esc_attr( $package_data['inpost_id'] ),
                                    esc_attr( $order_id ),
                                    esc_html__('Get sticker for additional package', 'inpost-for-woocommerce') ); ?>
                            </span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>
