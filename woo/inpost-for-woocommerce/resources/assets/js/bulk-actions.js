let successful_ids = {};

jQuery( document ).ready(
	function () {

		jQuery( '#doaction' ).on(
			'click',
			function (e) {
				var form   = jQuery( '#posts-filter' );
				let action = jQuery( '#bulk-action-selector-top' ).val();

				// shipments + labels.
				if (action === 'easypack_bulk_create_shipments_then_labels') {
					e.preventDefault();
					let selected_data = inpost_table_processing();
					if ( typeof selected_data != 'undefined' && selected_data !== null ) {
						if ( Object.keys( selected_data.orders ).length ) {
							inpost_process_selected_item( selected_data.orders, 1, selected_data.selected_row_count, form, 0, 1 );
						}
					}
				}

				// only shipments.
				if ( action === 'easypack_bulk_create_shipments'
					|| action === 'easypack_bulk_create_shipments_A'
					|| action === 'easypack_bulk_create_shipments_B'
					|| action === 'easypack_bulk_create_shipments_C') {
					e.preventDefault();
					let selected_data = inpost_table_processing();

					if ( typeof selected_data != 'undefined' && selected_data !== null ) {
						if ( Object.keys( selected_data.orders ).length ) {
							let locker_size = false;
							if ( action === 'easypack_bulk_create_shipments_A' ) {
								locker_size = 'easypack_bulk_create_shipments_A';
							} else if ( action === 'easypack_bulk_create_shipments_B' ) {
								locker_size = 'easypack_bulk_create_shipments_B';
							} else if ( action === 'easypack_bulk_create_shipments_C' ) {
								locker_size = 'easypack_bulk_create_shipments_C';
							} else {
								locker_size = false;
							}
							inpost_process_selected_item( selected_data.orders, 1, selected_data.selected_row_count, form, 0, 0, locker_size );
						}
					}
				}

				// only labels.
				if (action === 'easypack_bulk_create_labels') {
					e.preventDefault();

					let selected_data = inpost_table_processing();

					if ( typeof selected_data != 'undefined' && selected_data !== null ) {
						if (Object.keys( selected_data.orders ).length) {
							print_labels_bulk( selected_data.orders );
						}
					}
				}
			}
		);


		//let activeTooltip = null;

		jQuery(".inpost-pl-status-container").hover(
			function() {
				// Only create tooltip if none exists
				if (true) {
					const $container = jQuery(this);
					const $statusDiv = $container.find(".inpost-pl-status-tooltip");
					const statusText = $statusDiv.text();

					// Create tooltip
					let activeTooltip = jQuery('<div class="inpost-pl-status-value-tooltip">' + statusText + '</div>');

					// Position tooltip relative to the container
					const containerPos = $container.offset();
					activeTooltip.css({
						position: 'absolute',
						background: '#333',
						color: '#fff',
						padding: '5px 10px',
						borderRadius: '3px',
						fontSize: '12px',
						zIndex: 1000,
						top: containerPos.top - 40, // Position above the status bar
						left: containerPos.left,
						boxShadow: '0 2px 5px rgba(0,0,0,0.2)',
						maxWidth: '300px'
					});

					// Add arrow
					activeTooltip.append('<div class="tooltip-arrow"></div>');

					// Add to body
					jQuery('body').append(activeTooltip);

					// Set timeout to remove tooltip
					setTimeout(function() {
						activeTooltip.fadeOut(300, function() {
							jQuery(this).remove();
							activeTooltip = null;
						});
					}, 3000);
				}
			}
		);
	}
);

document.addEventListener(
	'click',
	function (e) {
		e          = e || window.event;
		var target = e.target || e.srcElement;

		if ( target.classList.contains( 'dashicons-media-spreadsheet' ) ) {
			e.preventDefault();

			let order_id = target.getAttribute( 'data-id' );

			let row_id                    = '#post-' + order_id;
			let inpost_custom_column_cell = jQuery( row_id + ' > .easypack_shipping_statuses' );
			if ( ! inpost_custom_column_cell.length > 0 ) {
				row_id                    = '#order-' + order_id;
				inpost_custom_column_cell = jQuery( row_id + ' > .easypack_shipping_statuses' );
			}

			jQuery( inpost_custom_column_cell ).addClass( 'order-preview' );
			jQuery( inpost_custom_column_cell ).addClass( 'disabled' );
			jQuery( inpost_custom_column_cell ).find( '.inpost-status-inside-td' ).hide();
			print_labels_bulk( [order_id] );

		}
	}
);

function print_labels_bulk(orders) {

	var beforeSend = function () {
		document.querySelector( 'body' ).style.opacity = "0.6";
		document.querySelector( 'body' ).style.cursor  = "wait";
	};

	var general_action = 'easypack';
	var easy_action    = 'easypack_create_bulk_labels';
	var order_ids      = JSON.stringify( orders );
	beforeSend();
	var request = new XMLHttpRequest();
	request.open( 'POST', easypack_bulk.ajaxurl, true );
	request.setRequestHeader( 'Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8' );
	request.responseType = 'blob';

	request.onload = function () {
		// Only handle status code 200.
		if ( request.status === 200 && request.response.size > 0 ) {

			var content_type = request.getResponseHeader( "content-type" );

			let file_name_part = '';
			jQuery.each(
				orders,
				function (ind, order_id ) {
					file_name_part               += '_' + order_id;
					let row_id                    = '#post-' + order_id;
					let inpost_custom_column_cell = jQuery( row_id + ' > .easypack_shipping_statuses' );
					if ( ! inpost_custom_column_cell.length > 0 ) {
						row_id                    = '#order-' + order_id;
						inpost_custom_column_cell = jQuery( row_id + ' > .easypack_shipping_statuses' );
					}
					jQuery( inpost_custom_column_cell ).removeClass( 'order-preview' );
					jQuery( inpost_custom_column_cell ).removeClass( 'disabled' );
					jQuery( inpost_custom_column_cell ).find( '.inpost-status-inside-td' ).show();
				}
			);

			var filename = '';
			var blob     = '';

			if ( content_type === 'application/zip' ) {
				if (Object.keys( orders ).length > 4) {
					const today          = new Date();
					const yyyy           = today.getFullYear();
					let mm               = today.getMonth() + 1;
					let dd               = today.getDate();
					const formattedToday = dd + '_' + mm + '_' + yyyy;
					file_name_part       = '_' + formattedToday;
				}
				filename = 'inpost_zamowenia' + file_name_part + '.zip';
				blob     = new Blob( [request.response], { type: 'application/zip' } );

			} else if ( content_type === 'application/pdf' ) {
				filename = 'inpost_zamowenie' + file_name_part + '.pdf';
				blob     = new Blob( [request.response], { type: 'application/pdf' } );

			} else {
				// some error occured.
				document.querySelector( 'body' ).style.opacity = "1";
				document.querySelector( 'body' ).style.cursor  = "unset";
				let text_from_blob                             = new Blob( [request.response], { type: 'text/html' } );
				var reader                                     = new FileReader();
				reader.onload                                  = function () {
					let textResponse = JSON.parse( reader.result );
					console.log( textResponse );
					if ( textResponse.details.key == 'ParcelLabelExpired' ) {
						alert( 'Prawdopodobnie 1 etykieta wygasła' );
					} else {
						alert( "Wystąpił błąd podczas pobierania etykiet" );
						console.log( reader.result );
					}
				};
				reader.readAsText( text_from_blob );
				return;
			}

			var link      = document.createElement( 'a' );
			var url       = window.URL || window.webkitURL;
			link.href     = url.createObjectURL( blob );
			link.download = filename;
			document.body.appendChild( link );
			link.click();
			document.body.removeChild( link );
		}

		// some error handling should be done here...
		document.querySelector( 'body' ).style.opacity = "1";
		document.querySelector( 'body' ).style.cursor  = "unset";
	};

	request.send( 'action=' + general_action + '&easypack_action=' + easy_action + '&security=' + easypack_nonce + '&order_ids=' + order_ids );

}


function inpost_process_selected_item(orders, index, total, form, failed, need_labels = false, locker_size = false) {

	// if total reached.
	if (index > total) {
		return false;
	}

	var ajaxdata_process_item = {},
		order_id              = orders[index];

	ajaxdata_process_item['order_id']    = order_id;
	ajaxdata_process_item['action']      = 'easypack_bulk_create_shipments';
	ajaxdata_process_item['locker_size'] = '';
	ajaxdata_process_item['nonce']       = easypack_bulk.nonce;
	if ( locker_size ) {
		ajaxdata_process_item['locker_size'] = locker_size;
	}

	jQuery.ajax(
		{
			beforeSend: function () {
				document.querySelector( 'body' ).style.opacity = "0.6";
				document.querySelector( 'body' ).style.cursor  = "wait";
			},
			type: 'POST',
			url: easypack_bulk.ajaxurl,
			data: ajaxdata_process_item,
			dataType: 'json',
			success: function (data) {

				let row_id                    = '#post-' + order_id;
				let inpost_custom_column_cell = jQuery( row_id + ' > .easypack_shipping_statuses' );

				if ( ! inpost_custom_column_cell.length > 0 ) {
					row_id                    = '#order-' + order_id;
					inpost_custom_column_cell = jQuery( row_id + ' > .easypack_shipping_statuses' );
				}
				jQuery( inpost_custom_column_cell ).find( '.inpost-status-inside-td' ).removeClass( 'easypack-alert-status' );
				jQuery( inpost_custom_column_cell ).removeClass( 'order-preview' );
				jQuery( inpost_custom_column_cell ).removeClass( 'disabled' );

				if (data.status === 'ok') {
					let status_message = '';
					if (typeof data.tracking_number != 'undefined' && data.tracking_number !== null) {
						status_message = '<a href="#" ' +
						'target="_blank" ' +
						'data-id="' + order_id + '" ' +
						'class="get_sticker_action_orders">' +
						'<span title="Print stickers"  ' +
						'class="dashicons dashicons-media-spreadsheet" ' +
						'data-id="' + order_id + '">' +
						'</span></a> ' + data.tracking_number;
					} else if (typeof data.api_status != 'undefined' && data.api_status !== null) {
						status_message = data.api_status;
					} else {
						status_message = 'OK';
					}
					jQuery( inpost_custom_column_cell ).html( status_message );

					// collect succesful created orders for further print labels.
					successful_ids[index] = order_id;

					if (index <= total) {
						inpost_process_selected_item( orders, (index + 1), total, form, failed, need_labels, locker_size );
					}

				} else {
					failed++;

					if ( typeof data.message != 'undefined' && data.message !== null ) {
						let cleared_message_text = data.message.replace( /(<([^>]+)>)/ig, " " );
						console.log( cleared_message_text );
						if ( cleared_message_text.indexOf( 'height required' ) !== -1
						|| cleared_message_text.indexOf( 'length required' ) !== -1
						|| cleared_message_text.indexOf( 'width required' ) !== -1
						|| cleared_message_text.indexOf( 'weight amount required ' ) !== -1
						) {
							cleared_message_text = 'Sprawdź czy wymiary paczki są prawidłowe';
							jQuery( inpost_custom_column_cell ).addClass( 'easypack-alert-status' );
						}
						jQuery( inpost_custom_column_cell ).html( '<span style="color:#f00;">' + cleared_message_text + '</span>' );

					}

					if (data.status === 'already_created') {
						successful_ids[index] = order_id;
					}

					// continue with next.
					if (index < total) {
						inpost_process_selected_item( orders, (index + 1), total, form, failed, need_labels, locker_size );
					}
				}

				// last item.
				if (index == total) {
					if ( need_labels ) {
						print_labels_bulk( successful_ids );
					}
				}

			}, complete: function () {
				document.querySelector( 'body' ).style.opacity = "1";
				document.querySelector( 'body' ).style.cursor  = "unset";
			}
		}
	);

}

function inpost_table_processing() {
	var form  = jQuery( '#posts-filter' ),
		table = form.find( 'table' );

	if ( form.length === 0 ) {
		form  = jQuery( '#wc-orders-filter' );
		table = form.find( 'table' );
	}

	let all_rows = table.find( "th[class='check-column']" ).children( "input[type='checkbox']" );

	let result             = {};
	let selected_row_count = 0;
	let orders             = {};
	let index              = 1;
	all_rows.each(
		function (i, elem) {
			if (jQuery( elem ).is( ':checked' )) {

				let order_row = null;
				let order_id  = jQuery( elem ).val();

				console.log( "order_id" );
				console.log( order_id );

				let old_table = document.getElementById( 'post-' + order_id );
				let new_table = document.getElementById( 'order-' + order_id );

				if ( typeof old_table != 'undefined' && old_table !== null ) {
					order_row = old_table;
				} else if ( typeof new_table != 'undefined' && new_table !== null ) {
					order_row = new_table;
				}

				if ( order_row ) {
					let inpost_custom_column_cell = jQuery( order_row ).find( '.easypack_shipping_statuses' );
					if ( typeof inpost_custom_column_cell != 'undefined' && inpost_custom_column_cell !== null ) {
						console.log( "inpost_custom_column_cell" );
						console.log( inpost_custom_column_cell );
						jQuery( inpost_custom_column_cell ).find( '.inpost-status-inside-td' ).hide();
						jQuery( inpost_custom_column_cell ).addClass( 'order-preview' );
						jQuery( inpost_custom_column_cell ).addClass( 'disabled' );
						selected_row_count++;
						orders[index] = order_id;
						index++;
					}
				}
			}
		}
	);

	if (selected_row_count === 0) {
		alert( 'Nie wybrano żadnych zamówień' );
		return;
	} else {
		result['selected_row_count'] = selected_row_count;
		result['orders']             = orders;
		return result;
	}
}