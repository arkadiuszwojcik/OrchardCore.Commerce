(function ($) {
	$( document ).ready(
		function () {
			function display_rates() {
				if ($( '.easypack_flat_rate' ).prop( 'checked' )) {
					$( '#woocommerce_easypack_parcel_machines_weekend_3' ).css( 'display', 'none' );
					$( '.easypack_cost_per_order' ).closest( 'tr' ).css( 'display', 'table-row' );
					$( '.easypack_based_on' ).closest( 'tr' ).css( 'display', 'none' );
					$( '.easypack_rates' ).closest( 'tr' ).css( 'display', 'none' );
					$( '#woocommerce_easypack_parcel_machines_1' ).css( 'display', 'none' );
					$( '#woocommerce_easypack_parcel_machines_cod_1' ).css( 'display', 'none' );

					$( '.easypack_gabaryt_a' ).closest( 'tr' ).css( 'display', 'none' );
					$( '.easypack_gabaryt_b' ).closest( 'tr' ).css( 'display', 'none' );
					$( '.easypack_gabaryt_c' ).closest( 'tr' ).css( 'display', 'none' );

				} else {
					$( '#woocommerce_easypack_parcel_machines_weekend_3' ).css( 'display', 'block' );
					$( '.easypack_cost_per_order' ).closest( 'tr' ).css( 'display', 'none' );
					$( '.easypack_based_on' ).closest( 'tr' ).css( 'display', 'table-row' );
					$( '.easypack_rates' ).closest( 'tr' ).css( 'display', 'table-row' );
					$( '#woocommerce_easypack_parcel_machines_1' ).css( 'display', 'block' );
					$( '#woocommerce_easypack_parcel_machines_cod_1' ).css( 'display', 'block' );

					let select_position = $( "[id$='_based_on']" ).val();
					if (select_position === 'size') {
						$( '#woocommerce_easypack_parcel_machines_rates' ).closest( 'tr' ).hide();
						$( '#woocommerce_easypack_parcel_machines_rates' ).hide(); // on parcel lockers settings page.
						$( '#woocommerce_easypack_shipping_courier_c2c_rates' ).closest( 'tr' ).hide();
						$( '#woocommerce_easypack_shipping_courier_c2c_rates' ).hide(); // on c2c courier settings page.
						$( '#woocommerce_easypack_shipping_courier_c2c_cod_rates' ).closest( 'tr' ).hide();
						$( '#woocommerce_easypack_shipping_courier_c2c_cod_rates' ).hide();
						$( '.easypack_gabaryt_a' ).closest( 'tr' ).show();
						$( '.easypack_gabaryt_b' ).closest( 'tr' ).show();
						$( '.easypack_gabaryt_c' ).closest( 'tr' ).show();
					}
				}
			}

			let easypack_flat_rate = $( '.easypack_flat_rate' );
			if ( typeof easypack_flat_rate != 'undefined' && easypack_flat_rate !== null ) {
				$( easypack_flat_rate ).change(
					function () {
						display_rates();
					}
				);
				display_rates();
			}

			let insurance_checkbox = $( 'input:checkbox[id$="insurance_inpost_pl"]' );
			let insurance_value    = $( 'input[id$="insurance_value_inpost_pl"]' );

			if ( typeof insurance_checkbox != 'undefined' && insurance_checkbox !== null ) {
				if ( $( insurance_checkbox ).prop( 'checked' ) ) {
					$( insurance_value ).closest( 'tr' ).hide();
				} else {
					$( insurance_value ).closest( 'tr' ).show();
				}

				$( insurance_checkbox ).on(
					'change',
					function () {
						if ( this.checked ) {
							$( insurance_value ).closest( 'tr' ).hide();
						} else {
							$( insurance_value ).closest( 'tr' ).show();
						}
					}
				);
			}

		}
	);

})( jQuery );