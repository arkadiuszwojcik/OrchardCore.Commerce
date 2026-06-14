<?php /** @var ShipX_Shipment_Model $shipment */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
} // Exit if accessed directly.

use InspireLabs\WoocommerceInpost\EasyPack;
use InspireLabs\WoocommerceInpost\shipx\models\shipment\ShipX_Shipment_Model;
use InspireLabs\WoocommerceInpost\shipx\models\shipment\ShipX_Shipment_Parcel_Model; ?>

<?php if ( $shipment instanceof ShipX_Shipment_Model && ! $additional_package ) : ?>
    <div class="easypack_one_else_parcel_wrapper" style="display: block; margin-top: 15px;">
        <a id="easypack_need_one_else_parcel" href="#">
            <?php esc_html_e("Create another shipment", "inpost-for-woocommerce"); ?>
        </a>

        <?php
        $logo = EasyPack()->getPluginImages() . 'logo/small/white.png';
        $active_methods = EasyPack_Helper()->get_active_shipping_methods();
        $params = [
            'type'        => 'select',
            'options'     => $active_methods,
            'class'       => [ 'easypack_additional_parcel' ],
            'input_class' => [ 'easypack_additional_parcel' ],
            'label'       => ''
        ];
        $order = wc_get_order( $order_id );
        $selected_option = null;
        if( $order && ! is_wp_error( $order ) && is_object( $order ) ) {
            $shipping_methods = $order->get_shipping_methods();
            if( !empty($shipping_methods) && is_array($shipping_methods) ) {
                foreach ( $shipping_methods as $method ) {
                    if( is_object($method)) {
                        $selected_option = $method->get_instance_id();
                    }
                }

            }
        }
        woocommerce_form_field( 'easypack_second_parcel', $params, $selected_option );
        ?>
        <button id="easypack_add_additional_parcel" class="button" style="margin-top: 15px;">
            <?php echo esc_html__( 'Add another parcel', 'inpost-for-woocommerce' ); ?>
        </button>
        <span id="easypack_additional_spinner" class="spinner" style="margin-top: 15px;"></span>

        <script type="text/javascript">
            jQuery('#easypack_add_additional_parcel').click(function (e) {

                var this_metabox = jQuery(this).closest('.postbox ');

                jQuery(this_metabox).find('#easypack_error').html('');
                jQuery(this).attr('disabled', true);
                jQuery(this_metabox).find("#easypack_additional_spinner").addClass("is-active");
                jQuery(this_metabox).find("#easypack_add_additional_parcel").attr('disabled', true);


                var data = {
                    action: 'easypack',
                    easypack_action: 'create_additional_package',
                    security: easypack_nonce,
                    easypack_additional_package_method_id: jQuery(this_metabox).find('#easypack_second_parcel').val(),
                    order_id: <?php echo esc_attr( $order_id ); ?>
                };

                jQuery.post(ajaxurl, data, function (response) {
                    if (response !== 0) {
                        response = JSON.parse(response);
                        if (response.status === 'ok') {

                            jQuery('.easypack_one_else_parcel_wrapper').each(function(ind, elem) {
                                jQuery(elem).hide();
                            });

                            let out = '<div id="easypack_parcel_additional" class="postbox">';
                            out += '<div class="postbox-header"><h2 class="hndle ui-sortable-handle easypack_parcel_additional">' +
                                'InPost Dodatkowa Paczka' +
                                '<img style="height:22px; float:right;" src="<?php echo esc_url( $logo ); ?>"></h2>\n' +
                                '</div>';

                            out += '<div class="inside easypack-second-metabox">';
                            out += response.content;
                            out += '<input type="hidden" value="true" id="easypack_additional_package">';
                            out += '</div></div>';

                            jQuery(this_metabox).after(out);

                            return false;
                        } else {
                            jQuery(this_metabox).find('#easypack_error').html(response.message);
                            jQuery(this_metabox).find("#easypack_add_additional_parcel").attr('disabled', false);
                        }
                    } else {
                        jQuery(this_metabox).find('#easypack_error').html('Invalid response.');
                        jQuery(this_metabox).find("#easypack_add_additional_parcel").attr('disabled', false);
                    }
                    jQuery(this_metabox).find("#easypack_additional_spinner").removeClass("is-active");
                    jQuery(this_metabox).find('#easypack_send_parcels').attr('disabled', false);

                });
                return false;

            });

            jQuery('#easypack_need_one_else_parcel').click(function (e) {
                e.preventDefault();
                let select_add_package = jQuery(this).next('.easypack_additional_parcel');
                let select_add_package_button = jQuery('#easypack_add_additional_parcel');
                jQuery(select_add_package).toggle();
                jQuery(select_add_package_button).toggle();
                jQuery('.select.easypack_additional_parcel').toggle();
            });
        </script>
    </div>
<?php endif; ?>
