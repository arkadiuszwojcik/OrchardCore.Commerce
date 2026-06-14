jQuery( document ).ready(
	function ($) {

		document.addEventListener(
			'click',
			function (e) {
				e          = e || window.event;
				var target = e.target;

				if ( target.hasAttribute( 'id' ) && target.getAttribute( 'id' ) === 'cb-select-all-1' ) {
					let row_checkboxes = $( 'input[id^="inpost_pl_allowed_methods"]' );
					if ( target.checked ) {
						if ( row_checkboxes ) {
							$( row_checkboxes ).each(
								function (i, elem) {
									$( elem ).prop( 'checked', true );
								}
							);
						}
					} else {
						if ( row_checkboxes ) {
							$( row_checkboxes ).each(
								function (i, elem) {
									$( elem ).prop( 'checked', false );
								}
							);
						}
					}
				}
			}
		);

		document.addEventListener(
			'change',
			function (e) {
				e          = e || window.event;
				var target = e.target;

				if (target.hasAttribute( 'name' ) && target.getAttribute( 'name' ) === 'easypack_all_parcel_dimensions') {
					let radio_value       = target.value;
					let all_locker_radios = $( 'input[name^="easypack_parcel_dimensions_"]' );
					all_locker_radios.each(
						function (i, elem) {
							if ( radio_value === $( elem ).val() ) {
								$( elem ).prop( 'checked', true );
							}
						}
					);
				}

				if ( target.classList.contains( 'inpost_pl_rule_checkbox' ) ) {
					let id = target.getAttribute( 'id' );

					let rows       = $( 'ul[class^="inpost_pl_allowed_methods_"]' );
					let rows_total = 0;
					rows.each(
						function (i, elem) {
							let same_checkboxes = $( elem ).find( 'input[data-method="' + id + '"]' );
							same_checkboxes.each(
								function (i2, checkbox) {
									if (target.checked) {
										$( checkbox ).prop( 'checked', true );
									} else {
										$( checkbox ).prop( 'checked', false );
									}
								}
							);
							rows_total++;
						}
					);

				}

				if ( target.classList.contains( 'inpost_pl_check_row' ) ) {
					let product_id     = target.value;
					let row_checkboxes = $( 'input[id^="inpost_pl_allowed_methods[' + product_id + ']"]' );
					if ( target.checked ) {
						if ( row_checkboxes ) {
							$( row_checkboxes ).each(
								function (i, elem) {
									$( elem ).prop( 'checked', true );
								}
							);
						}
					} else {
						if ( row_checkboxes ) {
							$( row_checkboxes ).each(
								function (i, elem) {
									$( elem ).prop( 'checked', false );
								}
							);
						}
					}
				}
			}
		);

		$( document ).on(
			'click',
			'.inpost-pl-update-reload',
			function (e) {
				e.preventDefault();
				location.reload();
			}
		);

		$( document ).on(
			'click',
			'.inpost-pl-update-single-product',
			function (e) {
				e.preventDefault();
				let product_id    = $( this ).attr( 'data-product-id' );
				let preloader     = $( 'img[data-id="' + product_id + '"]' );
				let action_result = $( 'span[data-action-id="' + product_id + '"]' );
				$( action_result ).hide();

				let collect_data = {};

				let rows = $( 'ul[class="inpost_pl_allowed_methods_' + product_id + '"]' );
				rows.each(
					function (i, elem) {
						let locker_size = $( 'input[name^="easypack_parcel_dimensions_' + product_id + '"]:checked' ).val();
						collect_data[i] = { allowed_methods: [], product_id: product_id, locker_size: locker_size };
						$( preloader ).show();
						let checkboxes = $( elem ).find( 'input[id^="inpost_pl_allowed_methods"]' );
						checkboxes.each(
							function (i2, checkbox) {
								if ($( checkbox ).is( ':checked' )) {
									collect_data[i].allowed_methods.push( $( checkbox ).attr( 'data-method' ) );
								}
							}
						);
					}
				);

				if (Object.keys( collect_data ).length === 0) {
					alert( 'Some error with procsessing products' );
				}

				$( this ).prop( 'disabled', 'disabled' );

				$.ajax(
					{
						type: 'POST',
						dataType: 'json',
						url: inpost_product_table.admin_url,
						data: {
							action: 'inpost_product_table',
							security: inpost_product_table.nonce,
							product_id: product_id,
							product_data: collect_data[0],
							data_key: 0,
							all_data: collect_data
						},
						success: function (response) {

						},
						error: function (response) {
							console.log( "error response" );
							console.log( response );
						},
						complete: function (response) {
							$( preloader ).hide();
							$( action_result ).show();
							$( 'input[data-product-id="' + product_id + '"]' ).prop( 'disabled', false );
						}
					}
				);

			}
		);

		$( document ).on(
			'click',
			'.inpost-pl-update-product-bulk',
			function (e) {
				e.preventDefault();

				let collect_data = {};

				let rows       = $( 'ul[class^="inpost_pl_allowed_methods_"]' );
				let rows_total = 0;
				rows.each(
					function (i, elem) {
						let product_id           = $( elem ).attr( 'data-product-id' );
						let locker_size          = $( 'input[name^="easypack_parcel_dimensions_' + product_id + '"]:checked' ).val();
						collect_data[i]          = { allowed_methods: [], product_id: product_id, locker_size: locker_size };
						let preloader            = $( 'img[data-id="' + product_id + '"]' );
						let single_update_button = $( '.inpost-pl-update-single-product' );
						$( single_update_button ).prop( 'disabled', 'disabled' );
						$( preloader ).show();
						let checkboxes = $( elem ).find( 'input[id^="inpost_pl_allowed_methods"]' );
						checkboxes.each(
							function (i2, checkbox) {
								if ($( checkbox ).is( ':checked' )) {
									collect_data[i].allowed_methods.push( $( checkbox ).attr( 'data-method' ) );
								}
							}
						);
						rows_total++;
					}
				);

				if (Object.keys( collect_data ).length === 0) {
					alert( 'Some error with procsessing products' );
				}

				$( '.inpost-pl-action-status' ).each(
					function (i, elem) {
						$( elem ).hide();
					}
				);

				let bulk_actions_current   = 0;
				let bulk_actions_remainded = 0;
				let bulk_actions_fail      = 0;
				let bulk_actions_success   = 0;

				bulk_actions_remainded = rows_total;
				bulk_process( collect_data );

				function bulk_process(all_rows_data, status = false) {
					let product_to_proccess = all_rows_data[bulk_actions_current];
					bulk_actions_remainded  = bulk_actions_remainded - 1;

					if (bulk_actions_remainded < 0) {
						return bulk_finish();
					}

					$.ajax(
						{
							type: 'POST',
							dataType: 'json',
							url: inpost_product_table.admin_url,
							data: {
								action: 'inpost_product_table',
								security: inpost_product_table.nonce,
								product_id: product_to_proccess.product_id,
								product_data: product_to_proccess,
								data_key: bulk_actions_current,
								all_data: all_rows_data
							},
							success: function (response) {
								bulk_update( response, true );
								bulk_actions_current = bulk_actions_current + 1;
								bulk_process( response );
							},
							error: function (response) {

								bulk_update( response, false );
								bulk_actions_current = bulk_actions_current + 1;
								bulk_process( response );
							},
							complete: function (response) {

							}
						}
					);
				}

				function bulk_finish() {
					console.log( 'FINISH' );
				}

				function bulk_update(response, success = false) {

					bulk_actions_fail = bulk_actions_fail + 1;

					if (success) {
						bulk_actions_success = bulk_actions_success + 1;
					} else {
						bulk_actions_fail = bulk_actions_fail + 1;
					}

					let product_id = response[bulk_actions_current].product_id;

					let preloader     = $( 'img[data-id="' + product_id + '"]' );
					let action_result = $( 'span[data-action-id="' + product_id + '"]' );
					$( preloader ).hide();
					$( action_result ).show();
					$( 'input[data-product-id="' + product_id + '"]' ).prop( 'disabled', false );

				}

				return false;

			}
		);

	}
)