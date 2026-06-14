<?php
/**
 * Order metabox template for shipment management with COD support
 *
 * @var WP_Post|WC_Order                $post                         The post or order object
 * @var int                             $order_id                     The ID of the order
 * @var WC_Order                        $order                        The order object
 * @var ShipX_Shipment_Service          $shipment_service             The shipment service instance
 * @var ShipX_Shipment_Model|null       $shipment                     The shipment model instance or null
 * @var bool                            $wrong_api_env                Flag indicating if there's a wrong API environment
 * @var array                           $parcels                      Array of parcel objects
 * @var string|bool                     $tracking_url                 The tracking URL or false if not available
 * @var string|null                     $stickers_url                 The stickers URL or null if not available
 * @var array                           $api_status_update_response   Response from status update API call
 * @var string                          $parcel_machine_id            The ID of the selected parcel machine
 * @var string                          $send_method                  The selected sending method
 * @var bool                            $disabled                     Flag indicating if form fields should be disabled
 * @var array                           $package_sizes                Available package sizes
 * @var bool                            $send_method_disabled         Flag indicating if send method selection is disabled
 * @var array                           $send_methods                 Available send methods with labels
 * @var string                          $selected_service             The name of the selected customer service
 * @var array                           $package_sizes_display        Display information for package sizes (only if shipment is null)
 * @var string                          $status                       Status of the shipment (only if shipment is null)
 * @var array                           $geowidget_config             Configuration for the geowidget
 * @var bool                            $additional_package           Flag indicating if this is an additional package
 *
 * @package InspireLabs\WoocommerceInpost
 */

use InspireLabs\WoocommerceInpost\EasyPack;
use InspireLabs\WoocommerceInpost\EasyPack_Helper;
use InspireLabs\WoocommerceInpost\shipx\models\shipment\ShipX_Shipment_Contstants;
use InspireLabs\WoocommerceInpost\shipx\models\shipment\ShipX_Shipment_Model;
use InspireLabs\WoocommerceInpost\shipx\models\shipment\ShipX_Shipment_Parcel_Model;
use InspireLabs\WoocommerceInpost\shipx\services\shipment\ShipX_Shipment_Service;

/** @var string $wp_ajax_action_create */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$status_service = EasyPack()->get_shipment_status_service();

if ( true === $wrong_api_env ) {
    $internal_data = $shipment->getInternalData();
    $origin_api    = $shipment->getInternalData()->getApiVersion();

    if ( $internal_data->getApiVersion() === $internal_data::API_VERSION_PRODUCTION ) {
        ?>
        <span style="font-weight: bold; color: #a00">
			<?php esc_html_e( 'This shipment was created in production API. Change API environment to production to process this shipment', 'inpost-for-woocommerce' ); ?>
		</span>
        <?php
    }

    if ( $internal_data->getApiVersion() === $internal_data::API_VERSION_SANDBOX ) {
        ?>
        <span style="font-weight: bold; color: #a00">
			<?php esc_html_e( 'This shipment was created in sandbox API. Change API environment to sandbox to process this shipment', 'inpost-for-woocommerce' ); ?>
		</span>
        <?php
    }
    return;
}

$first_parcel     = true;
$shipment_service = EasyPack()->get_shipment_service();

$class             = array( 'wc-enhanced-select' );
$custom_attributes = array( 'style' => 'width:100%;' );

if ( $disabled ) {
    $custom_attributes['disabled'] = 'disabled';
    $class[]                       = 'easypack-disabled';
}

/** @var ShipX_Shipment_Model $shipment */
/** @var WC_Order $order */
?>

    <p>
        <span style="font-weight: bold"><?php esc_html_e( 'Service:', 'inpost-for-woocommerce' ); ?></span>
        <span><?php echo esc_html( $selected_service ); ?></span>
    </p>

    <p>
        <span style="font-weight: bold"><?php esc_html_e( 'Status:', 'inpost-for-woocommerce' ); ?></span>
        <?php
        if ( $shipment instanceof ShipX_Shipment_Model && ! $additional_package ) {
            $status       = $shipment->getInternalData()->getStatus();
            $status_title = $shipment->getInternalData()->getStatusTitle();
            $status_desc  = $shipment->getInternalData()->getStatusDescription();
            ?>
            <span title="<?php echo esc_attr( $status_desc ); ?>"><?php echo esc_html( $status_title ); ?> (<?php echo esc_html( $status ); ?>)</span>
        <?php } else { ?>
            <?php esc_html_e( 'Not created yet (new)', 'inpost-for-woocommerce' ); ?>
        <?php } ?>
    </p>

<?php
if ( ! empty( $shipment instanceof ShipX_Shipment_Model && $shipment->getInternalData()->getTrackingNumber() ) && ! $additional_package ) {
    ?>
    <p>
		<span style="font-weight: bold">
			<?php esc_html_e( 'Tracking number:', 'inpost-for-woocommerce' ); ?>
		</span>
        <a target="_blank" href="<?php echo esc_url( $shipment_service->getTrackingUrl( $shipment ) ); ?>">
            <?php echo esc_html( $shipment->getInternalData()->getTrackingNumber() ); ?>
        </a>
    </p>
    <div class="padding-bottom15"></div>
    <?php
}

include 'costs/html-order-metabox-costs.php';
?>

    <div>
        <?php esc_html_e( 'Attributes:', 'inpost-for-woocommerce' ); ?>

        <?php
        /** @var ShipX_Shipment_Parcel_Model $parcel */
        /** @var ShipX_Shipment_Parcel_Model[] $parcels */
        $wielopak = false;
        if ( is_array( $parcels ) && ( count( $parcels ) > 1 ) ) {
            $wielopak = true;
        }

        if ( $wielopak && 'new' !== $status && ! $additional_package ) {
            ?>
            <span id="easypack_wielopak_label">
			<?php echo esc_html__( 'Multi-parcel', 'inpost-for-woocommerce' ); ?>
		</span>
            <?php
        }

        foreach ( $parcels as $parcel ) {
            if ( 'new' === $status || $additional_package ) {
                ?>
                <p>
                    <select id="easypack_send_method_many_qty">
                        <option value="1">1</option>
                        <option value="2">2</option>
                        <option value="3">3</option>
                        <option value="4">4</option>
                        <option value="5">5</option>
                        <option value="6">6</option>
                        <option value="7">7</option>
                        <option value="8">8</option>
                        <option value="9">9</option>
                        <option value="10">10</option>
                        <option value="11">11</option>
                    </select>
                    <label for="easypack_send_method_many">
                        <input name="easypack_send_method_many" id="easypack_send_method_many" type="checkbox" class="" value="1">
                        <?php echo esc_html__( 'Send as several packages', 'inpost-for-woocommerce' ); ?>
                    </label>
                </p>
                <ul id="easypack_parcels" class="easypack_courier_std" style="list-style:none">
                    <li>
                        <?php
                        $length = array(
                            'type'        => 'number',
                            'class'       => array( 'easypack_parcel' ),
                            'input_class' => array( 'easypack_parcel' ),
                            'label'       => __( 'Length:', 'inpost-for-woocommerce' ) . ' (' . $parcel->getDimensions()->getUnit() . ')',
                            'required'    => true,
                        );
                        $width  = array(
                            'type'        => 'number',
                            'class'       => array( 'easypack_parcel' ),
                            'input_class' => array( 'easypack_parcel' ),
                            'label'       => __( 'Width:', 'inpost-for-woocommerce' ) . ' (' . $parcel->getDimensions()->getUnit() . ')',
                            'required'    => true,
                        );
                        $height = array(
                            'type'        => 'number',
                            'class'       => array( 'easypack_parcel' ),
                            'input_class' => array( 'easypack_parcel' ),
                            'label'       => __( 'Height:', 'inpost-for-woocommerce' ) . ' (' . $parcel->getDimensions()->getUnit() . ')',
                            'required'    => true,
                        );

                        $cod_amount = isset( $saved_meta_data[0]['cod_amount'] )
                            ? $saved_meta_data[0]['cod_amount']
                            : $order->get_total();
                        ?>

                        <?php esc_html_e( 'COD amount: ', 'inpost-for-woocommerce' ); ?>
                        <input class="easypack_cod_amount" type="number" style=""
                               value="<?php echo esc_attr( $cod_amount ); ?>"
                               placeholder="0.00" step="any" min="0"
                               name="cod_amount[]">

                        <?php
                        // Parcel dimensions block for courier shipments.
                        include 'html-courier-parcel-dimensions.php';

                        if ( 'new' === $status && ! $first_parcel ) {
                            ?>
                            <button class="button easypack_remove_parcel"><?php esc_html_e( 'Remove', 'inpost-for-woocommerce' ); ?></button>
                            <?php
                        }
                        ?>
                    </li>
                </ul>
            <?php } else { ?>
                <ul id="easypack_parcels" class="easypack_courier_std" style="list-style:none">
                    <li>
                        <?php esc_html_e( 'Length', 'inpost-for-woocommerce' ); ?>: <?php echo esc_html( $parcel->getDimensions()->getLength() ); ?> <?php echo esc_html( $parcel->getDimensions()->getUnit() ); ?>
                        <br>
                        <?php esc_html_e( 'Width', 'inpost-for-woocommerce' ); ?>: <?php echo esc_html( $parcel->getDimensions()->getWidth() ); ?> <?php echo esc_html( $parcel->getDimensions()->getUnit() ); ?>
                        <br>
                        <?php esc_html_e( 'Height', 'inpost-for-woocommerce' ); ?>: <?php echo esc_html( $parcel->getDimensions()->getHeight() ); ?> <?php echo esc_html( $parcel->getDimensions()->getUnit() ); ?>
                        <br>
                        <?php esc_html_e( 'Non standard', 'inpost-for-woocommerce' ); ?>: <?php echo true === $parcel->is_non_standard() ? esc_html__( 'yes', 'inpost-for-woocommerce' ) : esc_html__( 'no', 'inpost-for-woocommerce' ); ?>
                        <br>
                        <?php esc_html_e( 'Weight', 'inpost-for-woocommerce' ); ?>: <?php echo esc_html( $parcel->getWeight()->getAmount() ); ?> <?php echo esc_html( $parcel->getWeight()->getUnit() ); ?>
                        <br>
                        <?php esc_html_e( 'COD amount', 'inpost-for-woocommerce' ); ?>: <?php echo esc_html( $shipment->getCod()->getAmount() ); ?>
                    </li>
                </ul>
                <?php
            }
            $first_parcel = false;
        }
        ?>
    </div>

<?php
include 'services/html-service-insurance.php';
include 'html-field-reference.php';
include 'services/html-service-sms.php';
include 'html-send-method.php';
?>

    <p>
        <?php if ( 'new' === $status ) { ?>
            <button id="easypack_send_parcels" class="button button-primary"><?php esc_html_e( 'Send parcel', 'inpost-for-woocommerce' ); ?></button>
        <?php } ?>

        <?php include 'html-no-funds-alert.php'; ?>

        <?php
        if ( $shipment instanceof ShipX_Shipment_Model && ! empty( $shipment->getInternalData()->getTrackingNumber() ) && ! $additional_package ) {
            ?>
            <input id="get_stickers" type="submit" class="button button-primary" value="<?php esc_html_e( 'Get sticker', 'inpost-for-woocommerce' ); ?>">
            <input type="hidden" name="easypack_get_stickers_request" id="easypack_get_stickers_request">
            <input type="hidden" name="easypack_parcel" value="<?php echo esc_attr( $shipment->getInternalData()->getOrderId() ); ?>">
        <?php } ?>

        <span id="easypack_spinner" class="spinner"></span>
    </p>

    <p id="easypack_error"></p>

    <a href="#" download id="easypack_download" target="_blank" hidden></a>

    <script type="text/javascript">
        document.addEventListener('click', function (e) {
            e = e || window.event;
            let target = e.target || e.srcElement;

            if ( target.classList.contains( 'easypack-courier-repeat-block-btn-close' ) ) {
                let repeat_block = target.closest('.easypack-courier-repeat-block');
                repeat_block.remove();
                let repeat_blocks_left = document.querySelectorAll('.easypack-courier-repeat-block').length;

                if ( repeat_blocks_left === 1 ) {
                    jQuery('#easypack_send_method_many').prop('checked', false);
                    jQuery('.easypack-courier-repeat-block').each(function(ind, elem) {
                        jQuery(elem).removeClass('divided');
                        if ( ind > 0 ) {
                            jQuery(elem).remove();
                        }
                        jQuery('.easypack-courier-repeat-block-title').each(function(ind, elem) {
                            jQuery(elem).removeClass('divided');
                        });
                    });
                }

                let order_id = '<?php echo esc_attr( $order_id ); ?>';
                let set_limit = jQuery('#easypack_send_method_many_qty').val();
                jQuery('.easypack-courier-repeat-block-title').each(function(ind, elem) {
                    jQuery(elem).addClass('divided');
                    let num = ind + 1;
                    let divided_parcel_id = '#' + order_id + ' ' + num + '/' + set_limit;
                    jQuery(elem).find('.easypack-repeat-block-title').val(divided_parcel_id);
                });
            }

            if ( target.hasAttribute('id') && target.getAttribute('id') === 'easypack_send_method_many' ) {
                let repeat = jQuery('.easypack-courier-repeat-block');

                if ( jQuery(target).is(':checked') ) {
                    let set_limit = +jQuery('#easypack_send_method_many_qty').val();
                    let limit = set_limit - 1;
                    let repeat_blocks_qty = document.querySelectorAll('.easypack-courier-repeat-block').length;

                    while ( repeat_blocks_qty <= limit ) {
                        jQuery(repeat).clone().insertAfter(repeat);
                        repeat_blocks_qty++;
                    }

                    jQuery('.easypack-courier-repeat-block-btn-close').each(function(ind, elem) {
                        if ( ind > 0 ) {
                            jQuery(elem).addClass('divided');
                        }
                    });

                    let order_id = '<?php echo esc_attr( $order_id ); ?>';
                    jQuery('.easypack-courier-repeat-block-title').each(function(ind, elem) {
                        jQuery(elem).addClass('divided');
                        let num = ind + 1;
                        let divided_parcel_id = '#' + order_id + ' ' + num + '/' + set_limit;
                        jQuery(elem).find('.easypack-repeat-block-title').val(divided_parcel_id);
                    });

                    jQuery('.easypack-courier-repeat-block').each(function(ind, elem) {
                        jQuery(elem).addClass('divided');
                        if ( ind === 0 ) {
                            jQuery(elem).css('margin-top', '7px');
                        }
                    });
                } else {
                    jQuery('.easypack-courier-repeat-block-title').each(function(ind, elem) {
                        jQuery(elem).removeClass('divided');
                    });
                    jQuery('.easypack-courier-repeat-block').each(function(ind, elem) {
                        jQuery(elem).removeClass('divided');
                        if ( ind > 0 ) {
                            jQuery(elem).remove();
                        }
                    });
                }
            }
        });

        jQuery('#easypack_send_parcels').click(function (e) {
            let metabox = jQuery(this).closest('.postbox');
            let is_additional_package = '<?php echo esc_html( $additional_package ); ?>';
            jQuery(metabox).find('#easypack_error').html('');
            jQuery(this).attr('disabled', true);
            jQuery(metabox).find("#easypack_spinner").addClass("is-active");

            let parcels = [];
            jQuery(metabox).find('select.easypack_parcel').each(function (i) {
                parcels[i] = jQuery(this).val();
            });

            let cod_amounts = [];
            jQuery(metabox).find('input.easypack_cod_amount').each(function (i) {
                cod_amounts[i] = jQuery(this).val();
            });

            let insurance_amounts = [];
            jQuery(metabox).find('input.insurance_amount').each(function (i) {
                insurance_amounts[i] = jQuery(this).val();
            });

            let order_id = '<?php echo esc_attr( $order_id ); ?>';
            let data = {};

            if ( jQuery('#easypack_send_method_many').is(':checked') ) {
                let wielopak = [];
                jQuery('.easypack-courier-repeat-block').each(function(ind, elem) {
                    wielopak.push({
                        weight: {
                            unit: 'kg',
                            amount: jQuery(elem).find('#parcel_weight').val()
                        },
                        is_non_standard: jQuery(elem).find('#parcel_non_standard').val(),
                        id: jQuery(elem).find('.easypack-repeat-block-title').val(),
                        dimensions: {
                            unit: 'mm',
                            length: jQuery(elem).find('#parcel_length').val(),
                            width: jQuery(elem).find('#parcel_width').val(),
                            height: jQuery(elem).find('#parcel_height').val(),
                        }
                    });
                });

                data = {
                    action: 'easypack',
                    easypack_action: '<?php echo esc_attr( $wp_ajax_action_create ); ?>',
                    security: easypack_nonce,
                    order_id: order_id,
                    parcel_mode: 'wielopaki',
                    parcels: wielopak,
                    send_method: jQuery(metabox).find('#easypack_send_method').val(),
                    insurance_amounts: insurance_amounts,
                    easypack_sms: jQuery(metabox).find('#easypack_sms_enabled').is(':checked'),
                    easypack_email: jQuery(metabox).find('#easypack_email_enabled').is(':checked'),
                    reference_number: jQuery(metabox).find('#reference_number').val(),
                    easypack_additional_package: jQuery(metabox).find('#easypack_additional_package').val(),
                    cod_amounts: cod_amounts
                };
            } else {
                data = {
                    action: 'easypack',
                    easypack_action: '<?php echo esc_attr( $wp_ajax_action_create ); ?>',
                    security: easypack_nonce,
                    order_id: order_id,
                    parcel_machine_id: jQuery(metabox).find('#parcel_machine_id').val(),
                    parcel_length: jQuery(metabox).find('#parcel_length').val(),
                    parcel_width: jQuery(metabox).find('#parcel_width').val(),
                    parcel_height: jQuery(metabox).find('#parcel_height').val(),
                    parcel_weight: jQuery(metabox).find('#parcel_weight').val(),
                    parcel_non_standard: jQuery(metabox).find('#parcel_non_standard').val(),
                    send_method: jQuery(metabox).find('#easypack_send_method').val(),
                    insurance_amounts: insurance_amounts,
                    easypack_sms: jQuery(metabox).find('#easypack_sms_enabled').is(':checked'),
                    easypack_email: jQuery(metabox).find('#easypack_email_enabled').is(':checked'),
                    reference_number: jQuery(metabox).find('#reference_number').val(),
                    easypack_additional_package: jQuery(metabox).find('#easypack_additional_package').val(),
                    cod_amounts: cod_amounts
                };
            }

            jQuery.post(ajaxurl, data, function (response) {
                if ( response !== 0 && response !== '' ) {
                    response = JSON.parse(response);

                    if ( response.status === 'ok' ) {
                        if ( is_additional_package ) {
                            let additional_package_data = '';

                            if ( typeof response.tracking_number !== 'undefined' && response.tracking_number !== null ) {
                                additional_package_data += '<p><span style="font-weight: bold">Usługa: </span>\n' +
                                    '<span title="">' + response.service + '</span></p>';

                                additional_package_data += '<p><span style="font-weight: bold">Status: </span>\n' +
                                    '<span title="">' + response.api_status + '</span></p>';

                                additional_package_data += '<p><span style="font-weight: bold">Ref. number: </span>\n' +
                                    '<span title="">' + response.ref_number + '</span></p>';

                                let tracking_url = 'https://inpost.pl/sledzenie-przesylek?number=' + response.tracking_number;
                                let button_text = '<?php echo esc_html__( 'Get sticker for additional package', 'inpost-for-woocommerce' ); ?>';

                                additional_package_data += '<span style="font-weight:bold">Tracking number:</span>' +
                                    '<br><a target="_blank" ' +
                                    'href="' + tracking_url + '">' + response.tracking_number + '</a>' +
                                    '<br>';

                                additional_package_data += '<span id="easypack_spinner" class="spinner"></span>';

                                additional_package_data += '<input ' +
                                    'id="get_sticker_additional_now" ' +
                                    'type="button" ' +
                                    'data-id="' + response.inpost_id + '" ' +
                                    'data-order-id="' + order_id + '" ' +
                                    'class="button secondary" value="' + button_text + '">';
                            } else {
                                additional_package_data += '<p><span style="font-weight: bold">Status: </span>\n' +
                                    '<span title="">' + response.api_status + '</span></p>';
                            }

                            jQuery(metabox).find(".inside").html(additional_package_data);
                        } else {
                            jQuery(metabox).find(".inside").html(response.content);
                        }
                        return false;
                    } else {
                        jQuery(metabox).find('#easypack_error').html(response.message);
                        jQuery(metabox).find('#easypack_error').css('color', '#f00');
                    }
                } else {
                    jQuery(metabox).find('#easypack_error').html('Invalid response.');
                    jQuery(metabox).find('#easypack_error').css('color', '#f00');
                }

                jQuery(metabox).find("#easypack_spinner").removeClass("is-active");
                jQuery(metabox).find('#easypack_send_parcels').attr('disabled', false);
            });

            return false;
        });
    </script>

<?php
include 'services/html-service-get-label.php';
include 'services/html-service-view-additional-package.php';
include 'services/html-service-additional-package.php';