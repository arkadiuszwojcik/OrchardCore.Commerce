var geowidgetModalOrder;
var easypack_current_metabox;

function selectPointCallbackAdditional(point) {
    if( typeof easypack_current_metabox != 'undefined' && easypack_current_metabox !== null ) {
        jQuery(easypack_current_metabox).find('#parcel_machine_id').val(point.name);
        geowidgetModalOrder.close();
    }
}

/* Show map for additional parcel */
document.addEventListener( 'click', function (e) {
    e = e || window.event;
    var target = e.target || e.srcElement;

    if ( target.classList.contains( 'settings-geowidget' ) ) {
        e.preventDefault();
        e.stopPropagation();
		
		jQuery( '.jBox-container' ).each(
            function (i, elem) {
                jQuery( elem ).remove();
            }
        );

        jQuery( '.jBox-overlay' ).each(
            function (i, elem) {
                jQuery( elem ).remove();
            }
        );

        easypack_current_metabox = jQuery(target).closest('.postbox');
        config = jQuery(easypack_current_metabox).find('#parcel_machine_id').data('geowidget_config');

        geowidgetModalOrder = new jBox('Modal', {
            width: easypackAdminGeowidgetSettings.width,
            height: easypackAdminGeowidgetSettings.height,
            attach: '.settings-geowidget',
            title: easypackAdminGeowidgetSettings.title,
            content: '<inpost-geowidget ' +
                'onpoint="selectPointCallbackAdditional" ' +
                'token="' + easypackAdminGeowidgetSettings.token + '" ' +
                'language="pl" ' +
                'config="' + config + '"></inpost-geowidget>'
        });

        if( typeof geowidgetModalOrder != 'undefined' && geowidgetModalOrder !== null ) {
            if( ! geowidgetModalOrder.isOpen ) {
                geowidgetModalOrder.open();
            }
        }
    }

}, false );

/* Get label for jsut created additional parcel */
document.addEventListener('click', function (e) {
    e = e || window.event;
    var target = e.target || e.srcElement;
    if (target.hasAttribute('id') && target.getAttribute('id') === 'get_sticker_additional_now') {
        e.preventDefault();
        e.stopPropagation();

        var metabox = jQuery(target).closest('.postbox');
        jQuery(metabox).find('#easypack_error').html('');

        var beforeSend = function () {
            var th_spinner = jQuery(metabox).find("#easypack_spinner");
            jQuery(metabox).find("#easypack_spinner").addClass("is-active");
            jQuery(metabox).find('#get_sticker_additional_now').attr('disabled', true);
        };

        var action = 'easypack';
        var easypack_action = 'easypack_create_additional_label';
        var inpost_id = target.getAttribute('data-id');
        var order_id = target.getAttribute('data-order-id');

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
                    var filename = 'inpost_zamowenie_dp_do_' + order_id + '_' + inpost_id + '.pdf';
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
                            alert(reader.result);
                        }
                    };
                    reader.readAsText(text_from_blob);
                    jQuery(metabox).find("#easypack_spinner").removeClass("is-active");
                    jQuery(metabox).find('#get_sticker_additional_now').attr('disabled', false);
                    return;
                }

                jQuery(metabox).find("#easypack_spinner").removeClass("is-active");
                jQuery(metabox).find('#get_sticker_additional_now').attr('disabled', false);
            } else {
                jQuery(metabox).find('#easypack_error').html('Wystąpił błąd');
                jQuery(metabox).find('#easypack_error').css('color', '#f00');
            }

            jQuery(metabox).find("#easypack_spinner").removeClass("is-active");
            jQuery(metabox).find('#get_sticker_additional_now').attr('disabled', false);
        };

        request.send('action=' + action + '&easypack_action=' + easypack_action + '&security=' + easypack_nonce + '&inpost_id=' + inpost_id);
    }
});

jQuery(document).ready(function (e) {
    /* Get labels for existing additional parcels */
    jQuery('.get_sticker_additional').click(function (e) {
        e.preventDefault();
        e.stopPropagation();
        var metabox = jQuery(this).closest('.postbox');
        jQuery(metabox).find('#easypack_error').html('');
        var beforeSend = function () {
            jQuery(metabox).find("#easypack_spinner").addClass("is-active");
            jQuery(metabox).find('#get_sticker_additional').attr('disabled', true);
        };

        var action = 'easypack';
        var easypack_action = 'easypack_create_additional_label';
        var inpost_id = jQuery(this).attr('data-id');
        var order_id = jQuery(this).attr('data-order-id');

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
                    var filename = 'inpost_zamowenie_dp_do_' + order_id + '_' + inpost_id + '.pdf';
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
                            alert(reader.result);
                        }
                    };
                    reader.readAsText(text_from_blob);
                    jQuery(metabox).find("#easypack_spinner").removeClass("is-active");
                    jQuery(metabox).find('#get_sticker_additional').attr('disabled', false);
                    return;
                }

                jQuery(metabox).find("#easypack_spinner").removeClass("is-active");
                jQuery(metabox).find('#get_sticker_additional').attr('disabled', false);
            } else {
                jQuery(metabox).find('#easypack_error').html('Wystąpił błąd');
                jQuery(metabox).find('#easypack_error').css('color', '#f00');
            }

            jQuery(metabox).find("#easypack_spinner").removeClass("is-active");
            jQuery(metabox).find('#get_sticker_additional').attr('disabled', false);
        };

        request.send('action=' + action + '&easypack_action=' + easypack_action + '&security=' + easypack_nonce + '&inpost_id=' + inpost_id);

    });
});