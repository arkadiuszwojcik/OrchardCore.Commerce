<?php /** @var ShipX_Shipment_Model $shipment */

use InspireLabs\WoocommerceInpost\shipx\models\shipment\ShipX_Shipment_Model;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
} // Exit if accessed directly.

?>

<?php if ($shipment instanceof ShipX_Shipment_Model
    &&
    !empty($shipment->getInternalData()->getTrackingNumber()) ) : ?>
    <script type="text/javascript">
        document.addEventListener('click', function (e) {
            e = e || window.event;
            var target = e.target || e.srcElement;
            if (target.hasAttribute('id') && target.getAttribute('id') === 'get_stickers') {
                e.preventDefault();
                e.stopPropagation();

                var metabox = jQuery(target).closest('.postbox');
                jQuery(metabox).find('#easypack_error').html('');

                var beforeSend = function () {
                    jQuery(metabox).find("#easypack_spinner").addClass("is-active");
                    jQuery(metabox).find('#easypack_send_parcels').attr('disabled', true);
                };

                var action = 'easypack';
                var easypack_action = 'easypack_create_bulk_labels';
                var order_ids = <?php echo esc_attr($order_id); ?>;
                beforeSend();
                var request = new XMLHttpRequest();
                request.open('POST', ajaxurl, true);
                request.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
                request.responseType = 'blob';

                request.onload = function () {
                    // Only handle status code 200
                    if (request.status === 200 && request.response.size > 0) {

                        var content_type = request.getResponseHeader("content-type");
                        if (content_type === 'application/pdf') {

                            var filename = 'inpost_zamowenie_' + order_ids + '.pdf';

                            // download file
                            var blob = new Blob([request.response], {type: 'application/pdf'});
                            var link = document.createElement('a');
                            link.href = window.URL.createObjectURL(blob);
                            link.download = filename;
                            document.body.appendChild(link);
                            link.click();
                            document.body.removeChild(link);
                        } else {
                            // some error occured
                            let text_from_blob = new Blob([request.response], {type: 'text/html'});
                            var reader = new FileReader();
                            reader.onload = function () {
                                let textResponse = JSON.parse(reader.result);
                                console.log(textResponse);
                                if (textResponse.details.key == 'ParcelLabelExpired') {
                                    jQuery(metabox).find('#easypack_error').html('Etykieta wygasła');
                                    jQuery(metabox).find('#easypack_error').css('color', '#f00');
                                } else {
                                    //alert(reader.result);
                                    console.log(reader.result);
                                    console.log(textResponse);
                                    let status = '';
                                    let message = '';
                                    let details = '';
                                    if(textResponse.hasOwnProperty('status')){
                                        status = textResponse.status;
                                    }
                                    if(textResponse.hasOwnProperty('message')){
                                        message = textResponse.message;
                                    }
                                    if(textResponse.hasOwnProperty('details')){
                                        details = textResponse.details;
                                        if(details.hasOwnProperty('shipment_ids')){
                                            details = details.shipment_ids[0];
                                        }
                                    }
                                    let error_details = '<b><span>Status: '+ status +'</span></b>' +
                                        '<br><b><span>'+ details +'</span></b>' +
                                        '<br><b><span>'+ message +'</span></b>';
                                    jQuery(metabox).find('#easypack_error').html(error_details);
                                    jQuery(metabox).find('#easypack_error').css('color', '#f00');
                                    console.log('Inpost API get label status: ' + status);
                                    console.log(message);
                                    console.log(details);
                                }
                            };
                            reader.readAsText(text_from_blob);
                            jQuery(metabox).find("#easypack_spinner").removeClass("is-active");
                            jQuery(metabox).find('#easypack_send_parcels').attr('disabled', false);
                            return;
                        }

                        jQuery(metabox).find("#easypack_spinner").removeClass("is-active");
                        jQuery(metabox).find('#easypack_send_parcels').attr('disabled', false);
                    } else {
                        jQuery(metabox).find('#easypack_error').html('Wystąpił błąd');
                        jQuery(metabox).find('#easypack_error').css('color', '#f00');
                    }

                    jQuery(metabox).find("#easypack_spinner").removeClass("is-active");
                    jQuery(metabox).find('#easypack_send_parcels').attr('disabled', false);
                };

                request.send('action=' + action + '&easypack_action=' + easypack_action + '&security=' + easypack_nonce + '&order_ids=' + JSON.stringify([order_ids]));
            }
        });
    </script>
<?php endif ?>
