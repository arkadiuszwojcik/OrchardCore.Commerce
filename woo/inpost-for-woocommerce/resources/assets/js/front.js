var inpostjsGeowidgetModal;
let inpost_pl_is_ajax_get_point_running = false;

function inpost_pl_get_shipping_method_js_mode() {
	let data    = {};
	let method  = jQuery( 'input[name^="shipping_method"]:checked' ).val();
	let postfix = '';
	if ('undefined' == typeof method || null === method ) {
		method = jQuery( 'input[name^="shipping_method"]' ).val();
	}
	if (typeof method != 'undefined' && method !== null) {
		if (method.indexOf( ':' ) > -1) {
			let arr = method.split( ':' );
			method  = arr[0];
			postfix = arr[1];
		}
	}
	data.method  = method;
	data.postfix = postfix;

	return data;
}

function inpost_pl_select_point_callback_js_mode(point) {

	let selected_point_data      = '';
	let parcelMachineAddressDesc = '';
	let address_line1            = '';
	let address_line2            = '';

	let point_name = '';
	if ( 'name' in point ) {
		point_name = point.name;
		if (point_name.startsWith( "PL_" )) {
			// Remove first 3 characters "PL_".
			point_name = point_name.slice( 3 );
		}
	}

	if ( typeof point.location_description != 'undefined' && point.location_description !== null ) {
		parcelMachineAddressDesc = point.location_description;
	}
	if ( typeof point.address.line1 != 'undefined' && point.address.line1 !== null ) {
		address_line1 = point.address.line1;
	}
	if ( typeof point.address.line2 != 'undefined' && point.address.line2 !== null ) {
		address_line2 = point.address.line2;
	}

	if ( point.location_description ) {

		selected_point_data = '<div class="easypack_selected_point_data" id="easypack_selected_point_data" style="margin-bottom:15px">\n'
			+ '<div id="selected-parcel-machine-id">' + point_name + '</div>\n'
			+ '<span id="selected-parcel-machine-desc">' + address_line1 + '<br>' + address_line2 + '</span><br>'
			+ '<span id="selected-parcel-machine-desc1">(' + point.location_description + ')</span>' +
			'<input type="hidden" id="parcel_machine_id"\n' +
			'name="parcel_machine_id" class="parcel_machine_id" value="' + point_name + '"/>\n' +
			'<input type="hidden" id="parcel_machine_desc"\n' +
			'name="parcel_machine_desc" class="parcel_machine_desc" value="' + parcelMachineAddressDesc + '"/></div>\n';

	} else {
		selected_point_data = '<div class="easypack_selected_point_data" id="easypack_selected_point_data" style="margin-bottom:15px">\n'
			+ '<div id="selected-parcel-machine-id">' + point_name + '</div>\n'
			+ '<span id="selected-parcel-machine-desc">' + point.address.line1 + '<br>' + address_line2 + '</span><br>'
		'<input type="hidden" id="parcel_machine_id"\n' +
		'name="parcel_machine_id" class="parcel_machine_id" value="' + point_name + '"/>\n' +
		'<input type="hidden" id="parcel_machine_desc"\n' +
		'name="parcel_machine_desc" class="parcel_machine_desc" value="' + parcelMachineAddressDesc + '"/></div>';
	}

	jQuery( '#easypack_selected_point_data' ).each(
		function (ind, elem) {
			jQuery( elem ).remove();
		}
	);

	jQuery( '.inpost_pl_additional_validation_field' ).each(
		function (ind, elem) {
			jQuery( elem ).remove();
		}
	);

	jQuery( '#easypack_js_type_geowidget' ).after( selected_point_data );
	jQuery( "#easypack_js_type_geowidget" ).text( easypack_front_map.button_text2 );

	let point_address = address_line1 + '<br>' + address_line2;

	let is_need_write_missed_locker = false;
	let typ_page                    = jQuery( '#selected-parcel-locker-pl-id' );
	if ( typeof typ_page != 'undefined' && typ_page !== null ) {
		if ( typ_page.length > 0 ) {
			is_need_write_missed_locker = true;
			jQuery( typ_page ).html( point_name );
			let typ_page_desc = jQuery( '#selected-parcel-machine-desc' );
			if (typeof typ_page_desc != 'undefined' && typ_page_desc !== null) {
				jQuery( typ_page_desc ).html( selected_point_data );
			}
			jQuery( '.hidden-inpost-pl-typ-data' ).css( 'display', 'block' );
		}
	}

	let EasyPackPointObject = { 'pointName': point_name, 'pointDesc': parcelMachineAddressDesc, 'visiblePointData': point_address };
	localStorage.setItem( 'EasyPackPointObject', JSON.stringify( EasyPackPointObject ) );

	// for some templates like Divi - add hidden fields for Inpost Parcel locker dynamically.
	var form               = document.getElementsByClassName( 'checkout woocommerce-checkout' )[0];
	var additionalInput1   = document.createElement( 'input' );
	additionalInput1.type  = 'hidden';
	additionalInput1.name  = 'parcel_machine_id';
	additionalInput1.value = point_name;
	additionalInput1.classList.add( 'inpost_pl_additional_validation_field' );

	var additionalInput2   = document.createElement( 'input' );
	additionalInput2.type  = 'hidden';
	additionalInput2.name  = 'parcel_machine_desc';
	additionalInput2.value = parcelMachineAddressDesc;
	additionalInput2.classList.add( 'inpost_pl_additional_validation_field' );

	if (form) {
		form.appendChild( additionalInput1 );
		form.appendChild( additionalInput2 );
	}

	inpostjsGeowidgetModal.close();

	if ( is_need_write_missed_locker ) {

		let preloader_gif = easypack_front_map.preloader;
		let preloader     = '<span class="inpost-pl-typ-preloader"><img src="' + preloader_gif + '" alt="inpost-pl-typ-preloader"></span>';

		let data = {
			action: 'update_locker_from_typ_page',
			order_id: jQuery( '#inpost-pl-typ-map-data' ).attr( 'data-id' ),
			inpost_pl_locker: point_name,
			inpost_pl_locker_desc: parcelMachineAddressDesc,
			security: easypack_front_map.security
		};

		jQuery.ajax(
			{
				type: 'POST',
				url: easypack_front_map.ajaxurl,
				data: data,
				dataType: 'json',
				beforeSend: function () {
					inpost_pl_is_ajax_get_point_running = true;
					jQuery( '.inpost_pl_geowidget_related_preloader' ).css( 'display', 'flex' );
					jQuery( '.inpost_pl_locker_changed' ).each(
						function (ind, elem) {
							jQuery( elem ).remove();
						}
					);
					jQuery( '.inpost-pl-related-point-btn' ).each(
						function (ind, elem) {
							jQuery( elem ).css( 'background', '#fff' );
						}
					);
				},
				success: function (data, textStatus, jqXHR) {
					console.log( 'inpost_pl_update_locker_from_map result:' );
					console.log( data );

					if ( 'success' in data ) {
						if ( data.success ) {
							jQuery( '#inpost-pl-typ-map-data' ).before( '<div class="inpost_pl_locker_changed" style="color:#26be22; font-weight: bold">' + easypack_front_map.updated_text + ': ' + point_name + '</div>' );
						} else {
							jQuery( '#inpost-pl-typ-map-data' ).before( '<div class="inpost_pl_locker_changed" style="color:#e03636; font-weight: bold">' + easypack_front_map.error_text + '</div>' );
						}

					} else {
						jQuery( '#inpost-pl-typ-map-data' ).before( '<div class="inpost_pl_locker_changed" style="color:#e03636; font-weight: bold">' + easypack_front_map.error_text + '</div>' );
					}
				},
				error: function (jqXHR, textStatus, errorThrown) {
					console.log( 'inpost_pl_update_locker_from_map_error' );
					console.log( 'error: ' + jqXHR.status );
					jQuery( '#inpost-pl-typ-map-data' ).before( '<div class="inpost_pl_locker_changed" style="color:#e03636; font-weight: bold">' + easypack_front_map.error_text + '</div>' );
					return false;
				},
				complete: function () {
					console.log( 'Complete' );
					inpost_pl_is_ajax_get_point_running = false;

					jQuery( '.inpost-pl-typ-preloader' ).each(
						function (ind, elem) {
							jQuery( elem ).remove();
						}
					);
					jQuery( '.inpost_pl_geowidget_related_preloader' ).each(
						function (ind, elem) {
							jQuery( elem ).css( 'display', 'none' );
						}
					);
				}
			}
		);

	}
}

function inpost_pl_js_set_locker_from_local_storage() {
	let EasyPackPointObject = localStorage.getItem( 'EasyPackPointObject' );

	if (EasyPackPointObject !== null) {
		let selected_point_data = '';
		let point,
			visible_desc,
			desc;

		let pointData = JSON.parse( EasyPackPointObject );
		if (typeof pointData != 'undefined' && pointData !== null) {
			if (typeof pointData.pointName != 'undefined' && pointData.pointName !== null) {
				point = pointData.pointName;
			}
			if (typeof pointData.visiblePointData != 'undefined' && pointData.visiblePointData !== null) {
				visible_desc = pointData.visiblePointData;
			}
			if (typeof pointData.pointDesc != 'undefined' && pointData.pointDesc !== null && typeof pointData.visiblePointData != 'undefined' && pointData.visiblePointData !== null) {
				desc                = pointData.pointDesc;
				selected_point_data = '<div class="easypack_selected_point_data" id="easypack_selected_point_data" style="margin-bottom:15px">\n'
					+ '<div id="selected-parcel-machine-id">' + point + '</div>\n'
					+ '<span id="selected-parcel-machine-desc1">(' + visible_desc + ')</span>' +
					'<input type="hidden" id="parcel_machine_id"\n' +
					'name="parcel_machine_id" class="parcel_machine_id" value="' + point + '"/>\n' +
					'<input type="hidden" id="parcel_machine_desc"\n' +
					'name="parcel_machine_desc" class="parcel_machine_desc" value="' + pointData.pointDesc + '"/></div>\n';
			} else {
				selected_point_data = '<div class="easypack_selected_point_data" id="easypack_selected_point_data" style="margin-bottom:15px">\n'
					+ '<div id="selected-parcel-machine-id">' + point + '</div>\n'
				'<input type="hidden" id="parcel_machine_id"\n' +
				'name="parcel_machine_id" class="parcel_machine_id" value="' + point + '"/>\n' +
				'<input type="hidden" id="parcel_machine_desc"\n' +
				'name="parcel_machine_desc" class="parcel_machine_desc" value="' + pointData.pointDesc + '"/></div>';
			}

			jQuery( '#easypack_js_type_geowidget' ).after( selected_point_data );
			jQuery( "#easypack_js_type_geowidget" ).text( easypack_front_map.button_text2 );
		}

	}
}


jQuery( document ).ready(
	function () {

		console.log( "Inpost PL: front_js" );

		// Prepare modal with map.
		let token         = easypack_front_map.geowidget_v5_token;
		let shipping_data = inpost_pl_get_shipping_method_js_mode();
		let method        = shipping_data.method;
		let postfix       = shipping_data.postfix;
		let config        = 'parcelCollect';

		if (typeof method != 'undefined' && method !== null) {
			config = inpost_pl_js_get_map_config_based_on_instance_id( postfix, method );
		}

		var wH = jQuery( window ).height() - 80;

		inpostjsGeowidgetModal = new jBox(
			'Modal',
			{
				width: 800,
				height: wH,
				attach: '#easypack_show_geowidget_modal',
				title: 'Wybierz paczkomat',
				content: '<inpost-geowidget id="inpost-geowidget" onpoint="inpost_pl_select_point_callback_js_mode" token="' + token + '" language="pl" config="' + config + '"></inpost-geowidget>'
			}
		);

		if ( typeof method != 'undefined' && method !== null ) {

			console.log( 'Inpost PL: ready' );
			console.log( method );

			let config = 'parcelCollect';
			config     = inpost_pl_js_get_map_config_based_on_instance_id( postfix, method );

			let wH = jQuery( window ).height() - 80;

			let map_content = '<inpost-geowidget id="inpost-geowidget" onpoint="inpost_pl_select_point_callback_js_mode" token="' + token + '" language="pl" config="' + config + '"></inpost-geowidget>';
			inpostjsGeowidgetModal.setContent( map_content );

			let selector              = method + ':' + postfix;
			let shipping_option_input = null;
			let li_parent             = null;

			console.log( "Inpost PL: shipping option input value" );
			console.log( selector );

			let shipping_options_block = jQuery( '#shipping_method' );
			if ( typeof shipping_options_block != 'undefined' && shipping_options_block !== null ) {
				jQuery( shipping_options_block ).find( 'input' ).each(
					function (index, input) {
						if ( selector === jQuery( input ).val() ) {
							shipping_option_input = input;
							console.log( "Inpost PL: shipping option input" );
							console.log( shipping_option_input );
						}
					}
				);
			}

			let map_button = '<div class="easypack_show_geowidget" id="easypack_js_type_geowidget">\n' +
				easypack_front_map.button_text1 + '</div>';
			if ( shipping_option_input ) {
				li_parent = jQuery( shipping_option_input ).closest( 'li' );
				console.log( "Inpost PL: li_parent" );
				console.log( li_parent );
			}

			if ( method.indexOf( 'easypack_parcel_machines' ) !== -1 ) {
				if ( typeof li_parent != 'undefined' && li_parent !== null ) {
					jQuery( li_parent ).after( map_button );
					console.log( "Inpost PL: add map button" );
					inpost_pl_js_set_locker_from_local_storage();

				}

			} else {
				let inpost_methods        = inpost_pl_js_get_configured_inpost_methods();
				let linked_fs_method_data = inpost_methods[postfix];
				if ( typeof linked_fs_method_data != 'undefined' && linked_fs_method_data !== null ) {
					let linked_fs_method = linked_fs_method_data.inpost_title;
					if ( linked_fs_method.indexOf( 'easypack_parcel_machines' ) !== -1 ) {
						if (typeof li_parent != 'undefined' && li_parent !== null) {
							jQuery( li_parent ).after( map_button );
							inpost_pl_js_set_locker_from_local_storage();
						}
					}
				}
			}

			jQuery( '#easypack_js_type_geowidget' ).on(
				'click',
				function () {
					if ( typeof inpostjsGeowidgetModal != 'undefined' && inpostjsGeowidgetModal !== null ) {
						if ( ! inpostjsGeowidgetModal.isOpen) {
							console.log( 'inpost geowidget open jQuery' );
							inpostjsGeowidgetModal.open();
						}
					}
				}
			);

			// open modal with map.
			document.addEventListener(
				'click',
				function (e) {
					e          = e || window.event;
					var target = e.target || e.srcElement;

					if (target.hasAttribute( 'id' ) && target.getAttribute( 'id' ) === 'easypack_js_type_geowidget') {
						e.preventDefault();
						if ( typeof inpostjsGeowidgetModal != 'undefined' && inpostjsGeowidgetModal !== null ) {
							if ( ! inpostjsGeowidgetModal.isOpen ) {
								inpostjsGeowidgetModal.open();
							}
						}
					}
				}
			);
		}

		jQuery( document.body ).on(
			'update_checkout',
			function () {
				jQuery( '.easypack_show_geowidget' ).each(
					function (ind, elem) {
						jQuery( elem ).remove();
					}
				);
				jQuery( '.easypack_selected_point_data' ).each(
					function (ind, elem) {
						jQuery( elem ).remove();
					}
				);

				if ( typeof method != 'undefined' && method !== null ) {

					console.log( 'Inpost PL: ready' );
					console.log( method );

					let config = 'parcelCollect';
					config     = inpost_pl_js_get_map_config_based_on_instance_id( postfix, method );

					let wH = jQuery( window ).height() - 80;

					let map_content = '<inpost-geowidget id="inpost-geowidget" onpoint="inpost_pl_select_point_callback_js_mode" token="' + token + '" language="pl" config="' + config + '"></inpost-geowidget>';
					inpostjsGeowidgetModal.setContent( map_content );

					let selector              = method + ':' + postfix;
					let shipping_option_input = null;
					let li_parent             = null;

					console.log( "Inpost PL: shipping option input value" );
					console.log( selector );

					let shipping_options_block = jQuery( '#shipping_method' );
					if ( typeof shipping_options_block != 'undefined' && shipping_options_block !== null ) {
						jQuery( shipping_options_block ).find( 'input' ).each(
							function (index, input) {
								if ( selector === jQuery( input ).val() ) {
									shipping_option_input = input;
									console.log( "Inpost PL: shipping option input" );
									console.log( shipping_option_input );
								}
							}
						);
					}

					let map_button = '<div class="easypack_show_geowidget" id="easypack_js_type_geowidget">\n' +
						easypack_front_map.button_text1 + '</div>';
					if ( shipping_option_input ) {
						li_parent = jQuery( shipping_option_input ).closest( 'li' );
						console.log( "Inpost PL: li_parent" );
						console.log( li_parent );
					}

					if ( method.indexOf( 'easypack_parcel_machines' ) !== -1 ) {
						if ( typeof li_parent != 'undefined' && li_parent !== null ) {
							jQuery( li_parent ).after( map_button );
							inpost_pl_js_set_locker_from_local_storage();
							console.log( "Inpost PL: add map button" );
						}

					} else {
						let inpost_methods        = inpost_pl_js_get_configured_inpost_methods();
						let linked_fs_method_data = inpost_methods[postfix];
						if ( typeof linked_fs_method_data != 'undefined' && linked_fs_method_data !== null ) {
							let linked_fs_method = linked_fs_method_data.inpost_title;
							if ( linked_fs_method.indexOf( 'easypack_parcel_machines' ) !== -1 ) {
								if (typeof li_parent != 'undefined' && li_parent !== null) {
									jQuery( li_parent ).after( map_button );
									inpost_pl_js_set_locker_from_local_storage();
								}
							}
						}
					}

					jQuery( '#easypack_js_type_geowidget' ).on(
						'click',
						function () {
							if ( typeof inpostjsGeowidgetModal != 'undefined' && inpostjsGeowidgetModal !== null ) {
								if ( ! inpostjsGeowidgetModal.isOpen) {
									console.log( 'inpost geowidget open jQuery' );
									inpostjsGeowidgetModal.open();
								}
							}
						}
					);

					// open modal with map.
					document.addEventListener(
						'click',
						function (e) {
							e          = e || window.event;
							var target = e.target || e.srcElement;

							if (target.hasAttribute( 'id' ) && target.getAttribute( 'id' ) === 'easypack_js_type_geowidget') {
								e.preventDefault();
								if ( typeof inpostjsGeowidgetModal != 'undefined' && inpostjsGeowidgetModal !== null ) {
									if ( ! inpostjsGeowidgetModal.isOpen ) {
										inpostjsGeowidgetModal.open();
									}
								}
							}
						}
					);
				}

			}
		);

		jQuery( document.body ).on(
			'updated_checkout',
			function () {

				let shipping_data = inpost_pl_get_shipping_method_js_mode();
				let method        = shipping_data.method;
				let postfix       = shipping_data.postfix;

				jQuery( '.easypack_show_geowidget' ).each(
					function (ind, elem) {
						jQuery( elem ).remove();
					}
				);
				jQuery( '.easypack_selected_point_data' ).each(
					function (ind, elem) {
						jQuery( elem ).remove();
					}
				);

				if ( typeof method != 'undefined' && method !== null ) {
					let config = 'parcelCollect';
					config     = inpost_pl_js_get_map_config_based_on_instance_id( postfix, method );

					let wH = jQuery( window ).height() - 80;

					let map_content = '<inpost-geowidget id="inpost-geowidget" onpoint="inpost_pl_select_point_callback_js_mode" token="' + token + '" language="pl" config="' + config + '"></inpost-geowidget>';
					inpostjsGeowidgetModal.setContent( map_content );

					let selector              = method + ':' + postfix;
					let shipping_option_input = null;
					let li_parent             = null;
					console.log( "shipping option input value" );
					console.log( selector );

					let shipping_options_block = jQuery( '#shipping_method' );
					if ( typeof shipping_options_block != 'undefined' && shipping_options_block !== null ) {
						jQuery( shipping_options_block ).find( 'input' ).each(
							function (index, input) {
								if ( selector === jQuery( input ).val() ) {
									shipping_option_input = input;
								}
							}
						);
					}

					let map_button = '<div class="easypack_show_geowidget" id="easypack_js_type_geowidget">\n' +
						easypack_front_map.button_text1 + '</div>';
					if ( shipping_option_input ) {
						li_parent = jQuery( shipping_option_input ).closest( 'li' );
					}

					if ( method.indexOf( 'easypack_parcel_machines' ) !== -1 ) {
						if ( typeof li_parent != 'undefined' && li_parent !== null ) {
							jQuery( li_parent ).after( map_button );
							inpost_pl_js_set_locker_from_local_storage();
						}

					} else {
						let inpost_methods        = inpost_pl_js_get_configured_inpost_methods();
						let linked_fs_method_data = inpost_methods[postfix];
						if ( typeof linked_fs_method_data != 'undefined' && linked_fs_method_data !== null ) {
							let linked_fs_method = linked_fs_method_data.inpost_title;
							if ( linked_fs_method.indexOf( 'easypack_parcel_machines' ) !== -1 ) {
								if (typeof li_parent != 'undefined' && li_parent !== null) {
									jQuery( li_parent ).after( map_button );
									inpost_pl_js_set_locker_from_local_storage();
								}
							}
						}
					}

					jQuery( '#easypack_js_type_geowidget' ).on(
						'click',
						function () {
							if ( typeof inpostjsGeowidgetModal != 'undefined' && inpostjsGeowidgetModal !== null ) {
								if ( ! inpostjsGeowidgetModal.isOpen) {
									console.log( 'inpost geowidget open jQuery' );
									inpostjsGeowidgetModal.open();
								}
							}
						}
					);

					// open modal with map.
					document.addEventListener(
						'click',
						function (e) {
							e          = e || window.event;
							var target = e.target || e.srcElement;

							if (target.hasAttribute( 'id' ) ) {
								if (target.getAttribute( 'id' ) === 'easypack_js_type_geowidget') {
									e.preventDefault();
									if (typeof inpostjsGeowidgetModal != 'undefined' && inpostjsGeowidgetModal !== null) {
										if ( ! inpostjsGeowidgetModal.isOpen) {
											inpostjsGeowidgetModal.open();
										}
									}
								}
							}
						}
					);

				}
			}
		);

		document.addEventListener(
			'change',
			function (e) {
				e          = e || window.event;
				var target = e.target;
				if (target.hasAttribute( 'name' )) {
					if (target.getAttribute( 'name' ) === 'shipping_method[0]') {
						localStorage.setItem( 'EasyPackPointObject', null );
						console.log( 'reset local storage value' );
					}
				}
			}
		);

		jQuery( document ).ready(
			function () {

				jQuery( '.inpost_pl_geowidget_typ' ).on(
					'click',
					function () {
						if ( typeof inpostjsGeowidgetModal != 'undefined' && inpostjsGeowidgetModal !== null ) {
							if ( ! inpostjsGeowidgetModal.isOpen) {
								console.log( 'inpost_pl_geowidget_typ open jQuery' );
								inpostjsGeowidgetModal.open();
							}
						}
					}
				);

				document.addEventListener(
					'click',
					function (e) {
						e          = e || window.event;
						var target = e.target || e.srcElement;

						if ( target.classList.contains( 'inpost_pl_geowidget_typ' ) ) {
							e.preventDefault();
							console.log( 'JS btn click' );
							if (typeof inpostjsGeowidgetModal != 'undefined' && inpostjsGeowidgetModal !== null) {
								if ( ! inpostjsGeowidgetModal.isOpen) {
									inpostjsGeowidgetModal.open();
								}
							}
						}
					}
				);

				jQuery( '.inpost-pl-related-point-btn' ).click(
					function (e) {
						e.preventDefault();
						console.log( 'inpost-pl-related-point-btn' );

						let this_btn = jQuery( this );

						if ( inpost_pl_is_ajax_get_point_running ) {
							console.log( 'AJAX already is running' );
							return false;
						}

						jQuery( '.inpost-pl-related-point-btn' ).each(
							function (ind, elem) {
								jQuery( elem ).css( 'background', '#fff' );
							}
						);

						let nearest_point_selected = jQuery( this_btn ).attr( 'data-id' );

						let data = {
							action: 'update_locker_from_typ_page',
							order_id: jQuery( '#inpost-pl-related-data-order' ).val(),
							inpost_pl_locker: nearest_point_selected,
							inpost_pl_locker_desc: jQuery( this_btn ).attr( 'data-address-id' ),
							security: easypack_front_map.security
						};

						jQuery.ajax(
							{
								type: 'POST',
								url: easypack_front_map.ajaxurl,
								data: data,
								dataType: 'json',
								beforeSend: function () {
									inpost_pl_is_ajax_get_point_running = true;
									jQuery( '.hidden-inpost-pl-typ-data' ).css( 'display', 'none' );
									jQuery( this_btn ).find( '.inpost-pl-select-from-points-preloader' ).css( 'display', 'block' );
									jQuery( '.inpost_pl_locker_changed' ).each(
										function (ind, elem) {
											jQuery( elem ).remove();
										}
									);
								},
								success: function (data, textStatus, jqXHR) {
									console.log( 'inpost_pl_choose_locker_from_nearest result:' );
									console.log( data );

									if ( 'success' in data ) {
										if ( data.success ) {
											jQuery( this_btn ).css( 'background', '#afeaad' );
											jQuery( this_btn ).find( '.inpost-pl-related-locker-info' ).after( '<div class="inpost_pl_locker_changed" style="color:#26be22; font-weight: bold">' + easypack_front_map.updated_text + ': ' + nearest_point_selected + '</div>' );
											jQuery( '#inpost-pl-typ-map-data' ).before( '<div class="inpost_pl_locker_changed" style="color:#26be22; font-weight: bold">' + easypack_front_map.updated_text + ': ' + nearest_point_selected + '</div>' );
										} else {
											jQuery( '#inpost-pl-typ-map-data' ).before( '<div class="inpost_pl_locker_changed" style="color:#e03636; font-weight: bold">' + easypack_front_map.error_text + '</div>' );
										}

									} else {
										jQuery( '#inpost-pl-typ-map-data' ).before( '<div class="inpost_pl_locker_changed" style="color:#e03636; font-weight: bold">' + easypack_front_map.error_text + '</div>' );
									}
								},
								error: function (jqXHR, textStatus, errorThrown) {
									console.log( 'inpost_pl_choose_locker_from_nearest_error' );
									console.log( 'error: ' + jqXHR.status );
									jQuery( '#inpost-pl-typ-map-data' ).before( '<div class="inpost_pl_locker_changed" style="color:#e03636; font-weight: bold">' + easypack_front_map.error_text + '</div>' );
									return false;
								},
								complete: function () {
									console.log( 'Complete' );
									inpost_pl_is_ajax_get_point_running = false;

									jQuery( this_btn ).find( '.inpost-pl-select-from-points-preloader' ).css( 'display', 'none' );
								}
							}
						);
					}
				);

			}
		);

	}
);


function inpost_pl_js_get_map_config_based_on_instance_id(instance_id, method) {
	let map_config     = 'parcelCollect';
	let inpost_methods = inpost_pl_js_get_configured_inpost_methods();

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
			if (method_id === 'easypack_parcel_machines_weekend' || method_id === 'easypack_parcel_machines_weekend_cod') {
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

function inpost_pl_js_get_configured_inpost_methods() {
	if (typeof window.easypack_front_map != 'undefined' && window.easypack_front_map !== null) {
		if ( window.easypack_front_map.inpost_methods ) {
			return window.easypack_front_map.inpost_methods;
		}
	}
	return [];
}