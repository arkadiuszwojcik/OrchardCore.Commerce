let inpostPlGeowidgetModalBlock;


function inpost_pl_validate_parcel_machine_for_gpay_block() {

	let selected_shipping_radio = document.querySelector( '#shipping-option .wc-block-components-radio-control__input:checked' );
	if (selected_shipping_radio !== undefined && selected_shipping_radio !== null) {
		let id = selected_shipping_radio.value;
		console.log( 'Shipping method ID:', id );

		if ( id.indexOf( 'easypack_parcel_machines' ) !== -1 ) {
			let hidden_input = document.getElementById( 'inpost-parcel-locker-id' );
			if ( hidden_input !== undefined && hidden_input !== null ) {
				let paczkomat_id = hidden_input.value;
				console.log( 'Paczkomat ID:', paczkomat_id );

				if (paczkomat_id.trim() === '') {
					return false;
				}
			}
		}
	}

	return true;
}

function inpost_pl_get_shipping_method_block() {
	let data                = {};
	let shipping_block_html = jQuery( '.wc-block-components-shipping-rates-control' );
	if (typeof shipping_block_html != 'undefined' && shipping_block_html !== null) {
		let shipping_radio_buttons = jQuery( shipping_block_html ).find( 'input[name^="radio-control-"]' );
		if ( shipping_radio_buttons.length > 0 ) {
			let method                  = jQuery( 'input[name^="radio-control-"]:checked' ).val();
			let ship_method_instance_id = '';
			if ('undefined' == typeof method || null === method) {
				method = jQuery( 'input[name^="radio-control-"]' ).val();
			}

			if (typeof method != 'undefined' && method !== null) {
				if (method.indexOf( ':' ) > -1) {
					let arr                 = method.split( ':' );
					method                  = arr[0];
					ship_method_instance_id = arr[1];
				}
			}
			data.method      = method;
			data.instance_id = ship_method_instance_id;
		}
	}

	return data;
}

function inpost_pl_change_react_input_value(input,value) {

	if (typeof input != 'undefined' && input !== null) {
		var nativeInputValueSetter = Object.getOwnPropertyDescriptor(
			window.HTMLInputElement.prototype,
			"value"
		).set;
		nativeInputValueSetter.call( input, value );

		var inputEvent = new Event( "input", {bubbles: true} );
		input.dispatchEvent( inputEvent );
	}
}


function inpost_pl_select_point_callback_blocks(point) {

	let selected_point_data = '';
	let parcelMachineAddressDesc;
	let address_line1 = '';
	let address_line2 = '';

	let point_name = '';

	if (point) {
		jQuery( '#easypack_selected_point_data' ).each(
			function (ind, elem) {
				jQuery( elem ).remove();
			}
		);

		if ( 'name' in point ) {
			point_name = point.name;
			if (point_name.startsWith( "PL_" )) {
				// Remove first 3 characters "PL_".
				point_name = point_name.slice( 3 );
			}
		}

		inpost_pl_change_react_input_value( document.getElementById( 'inpost-parcel-locker-id' ), point_name );

		if ( typeof point.location_description != 'undefined' && point.location_description !== null ) {
			parcelMachineAddressDesc = point.location_description;
		}
		if ( typeof point.address.line2 != 'undefined' && point.address.line2 !== null ) {
			address_line2 = point.address.line2;
		}
		if ( typeof point.address.line1 != 'undefined' && point.address.line1 !== null ) {
			address_line1 = point.address.line1;
		}

		if (point.location_description) {

			selected_point_data = '<div class="easypack_selected_point_data" id="easypack_selected_point_data">\n'
				+ '<div id="selected-parcel-machine-id">' + point_name + '</div>\n'
				+ '<span id="selected-parcel-machine-desc">' + address_line1 + '<br>' + address_line2 + '</span><br>'
				+ '<span id="selected-parcel-machine-desc1">(' + point.location_description + ')</span></div>';

		} else {
			selected_point_data = '<div class="easypack_selected_point_data" id="easypack_selected_point_data">\n'
				+ '<div id="selected-parcel-machine-id">' + point_name + '</div>\n'
				+ '<span id="selected-parcel-machine-desc">' + address_line1 + '<br>' + address_line2 + '</span></div>';
		}

		jQuery( '#inpost_pl_selected_point_data_wrap' ).html( selected_point_data );
		jQuery( '#inpost_pl_selected_point_data_wrap' ).show();
		jQuery( "#easypack_block_type_geowidget" ).text( easypack_block.button_text2 );

		let data = {
			action: 'inpost_save_to_wc_session',
			security: easypack_block.security,
			key: 'inpost_pl_wc_paczkomat',
			value: point_name
		};

		// console.log(data);

		jQuery.ajax(
			{
				type: 'POST',
				url: easypack_block.ajaxurl,
				data: data,
				dataType: 'json',
				success: function (response) {
					//console.log( 'Paczkomat saved in session data:', response );
				},
				error: function (jqXHR, textStatus, errorThrown) {
					console.log( "Error response saving paczkomato into session" );
					console.log( textStatus );
					console.log( 'Error: ' + errorThrown + ' ' + jqXHR.responseText );
				}
			}
		);

	}

	inpostPlGeowidgetModalBlock.close();
}


function inpost_pl_create_validation_modal() {
	// Create main modal container.
	const modal = document.createElement( 'div' );
	modal.id    = 'inpost_pl_checkout_validation_modal';
	Object.assign(
		modal.style,
		{
			display: 'none',
			position: 'fixed',
			top: '0',
			left: '0',
			width: '100%',
			height: '100%',
			backgroundColor: 'rgba(0, 0, 0, 0.5)',
			justifyContent: 'center',
			alignItems: 'center',
			zIndex: '1000'
		}
	);

	// Create modal content container.
	const modalContent = document.createElement( 'div' );
	Object.assign(
		modalContent.style,
		{
			backgroundColor: 'white',
			width: '90%',
			maxWidth: '300px',
			padding: '20px',
			position: 'relative',
			textAlign: 'center',
			borderRadius: '10px',
			boxShadow: '0px 4px 10px rgba(0, 0, 0, 0.1)'
		}
	);

	// Create close button (×).
	const closeSpan       = document.createElement( 'span' );
	closeSpan.id          = 'inp_pl_close_modal_cross';
	closeSpan.textContent = '×';
	Object.assign(
		closeSpan.style,
		{
			position: 'absolute',
			top: '10px',
			right: '15px',
			fontSize: '20px',
			cursor: 'pointer'
		}
	);

	// Create message div.
	const messageDiv       = document.createElement( 'div' );
	messageDiv.textContent = 'Musisz wybrać paczkomat.';
	Object.assign(
		messageDiv.style,
		{
			margin: '20px 0',
			fontSize: '18px'
		}
	);

	// Create OK button.
	const okButton       = document.createElement( 'button' );
	okButton.id          = 'inp_pl_close_modal_button';
	okButton.textContent = 'Ok';
	Object.assign(
		okButton.style,
		{
			padding: '10px 20px',
			backgroundColor: '#FFA900',
			color: 'white',
			border: 'none',
			borderRadius: '5px',
			cursor: 'pointer',
			fontSize: '16px'
		}
	);

	// Assemble the modal.
	modalContent.appendChild( closeSpan );
	modalContent.appendChild( messageDiv );
	modalContent.appendChild( okButton );
	modal.appendChild( modalContent );

	return modal;
}

jQuery( document ).ready(
	function () {

		let inpost_methods = inpost_pl_get_configured_inpost_methods();
		//console.log( inpost_methods );

		let validation_modal = inpost_pl_create_validation_modal();
		// Append modal to body.
		document.body.appendChild( validation_modal );

		// Event Listeners for closing modal.
		let modal_close_1 = document.getElementById( 'inp_pl_close_modal_cross' );
		if (typeof modal_close_1 != 'undefined' && modal_close_1 !== null) {
			modal_close_1.addEventListener( 'click', inpost_pl_close_validation_modal );
		}
		let modal_close_2 = document.getElementById( 'inp_pl_close_modal_button' );
		if (typeof modal_close_2 != 'undefined' && modal_close_2 !== null) {
			modal_close_2.addEventListener( 'click', inpost_pl_close_validation_modal );
		}

		setTimeout(
			function () {

				let token         = easypack_block.geowidget_v5_token;
				let shipping_data = inpost_pl_get_shipping_method_block();
				let config        = 'parcelCollect';
				let method        = shipping_data.method;
				let instance_id   = shipping_data.instance_id;

				config = inpost_pl_get_map_config_based_on_instance_id( instance_id, method );

				let wH = jQuery( window ).height() - 80;

				inpostPlGeowidgetModalBlock = new jBox(
					'Modal',
					{
						width: 800,
						height: wH,
						attach: '#eqasypack_show_geowidget',
						title: 'Wybierz paczkomat',
						content: '<inpost-geowidget id="inpost-geowidget" onpoint="inpost_pl_select_point_callback_blocks" token="' + token + '" language="pl" config="' + config + '"></inpost-geowidget>'
					}
				);

				jQuery( "#easypack_block_type_geowidget" ).on(
					'click',
					function (e) {
						e.preventDefault();
						//console.log( 'inpost-pl: click on map button jQuery' );
						if ( typeof inpostPlGeowidgetModalBlock != 'undefined' && inpostPlGeowidgetModalBlock !== null ) {

							let checked_radio_control = jQuery( 'input[name^="radio-control-"]:checked' );
							if ( typeof checked_radio_control != 'undefined' && checked_radio_control !== null) {
								let id          = jQuery( checked_radio_control ).attr( 'id' );
								let instance_id = null;
								let method_id   = null;
								let method_data = null;
								if (typeof id != 'undefined' && id !== null) {
									method_data = id.split( ":" );
									instance_id = method_data[method_data.length - 1];
									method_id   = method_data[0];

									if (typeof method_id != 'undefined' && method_id !== null) {
										let token  = easypack_block.geowidget_v5_token;
										let config = 'parcelCollect';

										config = inpost_pl_get_map_config_based_on_instance_id( instance_id, method_id );

										let map_content = '<inpost-geowidget id="inpost-geowidget" onpoint="inpost_pl_select_point_callback_blocks" token="' + token + '" language="pl" config="' + config + '"></inpost-geowidget>';
										inpostPlGeowidgetModalBlock.setContent( map_content );
									}
								}
							}

							if ( ! inpostPlGeowidgetModalBlock.isOpen ) {
								inpostPlGeowidgetModalBlock.open();
							}
						}
					}
				);

				jQuery( 'input[name^="radio-control-"]' ).on(
					'change',
					function () {
						if (this.checked) {
							const parent = document.getElementById( "shipping-option" );
							if ( parent && parent.contains( this ) ) {
								jQuery( '#inpost_pl_selected_point_data_wrap' ).hide();
								inpost_pl_change_react_input_value( document.getElementById( 'inpost-parcel-locker-id' ), '' );

								let config = 'parcelCollect';

								let shipping_method_data = jQuery( this ).attr( 'id' );
								if (typeof shipping_method_data != 'undefined' && shipping_method_data !== null) {
									let method_data = shipping_method_data.split( ":" );
									let instance_id = method_data[method_data.length - 1];
									let method_id   = method_data[0];
									config          = inpost_pl_get_map_config_based_on_instance_id( instance_id, method_id );
								}

								let map_content = '<inpost-geowidget id="inpost-geowidget" onpoint="inpost_pl_select_point_callback_blocks" token="' + token + '" language="pl" config="' + config + '"></inpost-geowidget>';

								inpostPlGeowidgetModalBlock.setContent( map_content );
							}
						}
					}
				);

			},
			1200
		);
	}
);


document.addEventListener(
	'click',
	function (e) {
		e          = e || window.event;
		var target = e.target || e.srcElement;

		if ( target.hasAttribute( 'id' ) ) {
			if (target.getAttribute( 'id' ) === 'easypack_block_type_geowidget' ) {
				e.preventDefault();

				if ( typeof inpostPlGeowidgetModalBlock != 'undefined' && inpostPlGeowidgetModalBlock !== null ) {

					let checked_radio_control = jQuery( 'input[name^="radio-control-"]:checked' );
					if ( typeof checked_radio_control != 'undefined' && checked_radio_control !== null) {
						let id          = jQuery( checked_radio_control ).attr( 'id' );
						let instance_id = null;
						let method_id   = null;
						let method_data = null;
						if (typeof id != 'undefined' && id !== null) {
							method_data = id.split( ":" );
							instance_id = method_data[method_data.length - 1];
							method_id   = method_data[0];

							if (typeof method_id != 'undefined' && method_id !== null) {
								let token  = easypack_block.geowidget_v5_token;
								let config = 'parcelCollect';

								config = inpost_pl_get_map_config_based_on_instance_id( instance_id, method_id );

								let map_content = '<inpost-geowidget id="inpost-geowidget" onpoint="inpost_pl_select_point_callback_blocks" token="' + token + '" language="pl" config="' + config + '"></inpost-geowidget>';
								inpostPlGeowidgetModalBlock.setContent( map_content );
							}
						}
					}

					if ( ! inpostPlGeowidgetModalBlock.isOpen ) {
						inpostPlGeowidgetModalBlock.open();
					}
				}
			}
		}

		if ( target.closest( '.wc-block-components-checkout-place-order-button' ) || target.classList.contains( 'wc-block-components-checkout-place-order-button' )
			|| target.classList.contains( 'wc-block-checkout__actions_row' ) ) {

			let reactjs_input       = document.getElementById( 'inpost-parcel-locker-id' );
			let reactjs_input_lalue = false;
			if (typeof reactjs_input != 'undefined' && reactjs_input !== null) {
				reactjs_input_lalue = reactjs_input.value;
				if ( ! reactjs_input_lalue ) {
					inpost_pl_open_validation_modal();
				}
			}
		}
	}
);


function inpost_pl_open_validation_modal() {
	document.getElementById( 'inpost_pl_checkout_validation_modal' ).style.display = 'flex';
}

function inpost_pl_close_validation_modal() {
	document.getElementById( 'inpost_pl_checkout_validation_modal' ).style.display = 'none';

	// Scroll to map button.
	let scrollToElement = document.getElementById( 'easypack_block_type_geowidget' );

	if (scrollToElement) {
		scrollToElement.scrollIntoView( {behavior: 'smooth' } );
	}

}

function inpost_pl_get_map_config_based_on_instance_id(instance_id, method) {
	let map_config     = 'parcelCollect';
	let inpost_methods = inpost_pl_get_configured_inpost_methods();

	if (instance_id !== undefined && instance_id !== null && instance_id !== '') {
		let selected_method = inpost_methods[instance_id];
		if (typeof selected_method != 'undefined' && selected_method !== null) {
			let method_id = selected_method.inpost_title;
			if (method_id === 'easypack_parcel_machines_cod') {
				map_config = 'parcelCollectPayment';
			}
			if (method_id === 'easypack_shipping_courier_c2c') {
				map_config = 'parcelSend';
			}
			if ( method_id === 'easypack_parcel_machines_weekend' || method_id === 'easypack_parcel_machines_weekend_cod' ) {
				map_config = 'parcelCollect247';
			}
		}

	} else {
		if (method === 'easypack_parcel_machines_cod') {
			map_config = 'parcelCollectPayment';
		}
		if (method === 'easypack_shipping_courier_c2c') {
			map_config = 'parcelSend';
		}
		if (method === 'easypack_parcel_machines_weekend' || method === 'easypack_parcel_machines_weekend_cod') {
			map_config = 'parcelCollect247';
		}
	}

	return map_config;
}

function inpost_pl_get_configured_inpost_methods() {
	if (typeof wcSettings != 'undefined' && wcSettings !== null) {
		if (wcSettings.inpost_pl_block_data && wcSettings.inpost_pl_block_data.configured_methods) {
			return wcSettings.inpost_pl_block_data.configured_methods;
		}
	}
	return [];
}


window.addEventListener(
	'message',
	function (event) {

		let parsedData;
		try {
			if (typeof event.data === 'string') {
				parsedData = JSON.parse( event.data );
			} else {
				parsedData = event.data;
			}

			// Now check for Google Pay click.
			if (
			parsedData.type === "parent" &&
			parsedData.message &&
			parsedData.message.action === "stripe-frame-event" &&
			parsedData.message.payload &&
			parsedData.message.payload.event === "click" &&
			parsedData.message.payload.data
			) {
				if (
					"google_pay" === parsedData.message.payload.data.paymentMethodType
					|| "apple_pay" === parsedData.message.payload.data.paymentMethodType
					|| "apple_pay_inner" === parsedData.message.payload.data.paymentMethodType
				) {
					// console.log('Google Pay button or ApplePay button click detected');
					if ( ! inpost_pl_validate_parcel_machine_for_gpay_block() ) {
							// console.log('Parcel machine validation failed');

							alert( 'Wygląda na to, że zapomniałeś wybrać paczkomat.' + "\n\n" + ' Jeśli tak, zamknij okno modalne, wybierz punkt za pomocą przycisku "Wybierz punkt odbioru", a następnie wróć do płatności.' );

							return false;
					}
				}

			}
		} catch (err) {
			// console.log('Error processing message:', err);
		}
	}
);