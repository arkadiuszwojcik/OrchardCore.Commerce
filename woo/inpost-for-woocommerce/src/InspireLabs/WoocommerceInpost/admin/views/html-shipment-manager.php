<?php
/**
 * @var ShipX_Dispatch_Order_Point_Model[] $view_var_points
 * @var array $view_var_send_methods
 * @var array $view_var_statuses
 * @var array $view_var_services
 * @var EasyPack_Shipment_Manager_List_Table $view_var_shipment_manager_list_table
 * @var int $dispatch_point
 */

use InspireLabs\WoocommerceInpost\admin\EasyPack_Shipment_Manager;
use InspireLabs\WoocommerceInpost\admin\EasyPack_Shipment_Manager_List_Table;
use InspireLabs\WoocommerceInpost\EasyPack;
use InspireLabs\WoocommerceInpost\shipx\models\courier_pickup\ShipX_Dispatch_Order_Point_Model;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly.

?>

<?php $is_courier_context = EasyPack_Shipment_Manager::is_courier_context(); ?>
<div class="wrap">
	<div id="icon-users" class="icon32"></div>
	<h2><?php esc_html_e( 'InPost Shipments', 'inpost-for-woocommerce' ); ?></h2>
	<?php $view_var_shipment_manager_list_table->prepare_items(); ?>
	<form method="get">
		<input type="hidden" name="page" value="easypack_shipment">
		<?php if ( true === $is_courier_context ) : ?>
			<div style="float:left;">
				<?php

				$point_select_items = array();
				foreach ( $view_var_points as $k => $point ) {
					$point_select_items[ $k ] = $point;
				}

				$params = array(
					'type'    => 'select',
					'options' => $point_select_items,
					'class'   => array( 'wc-enhanced-select' ),
					'label'   => esc_html__( 'Dispatch point ', 'inpost-for-woocommerce' ),
				);
				woocommerce_form_field( 'dispatch_point', $params, $dispatch_point );
				?>

			</div>

			<div style="float:left;">
				<p>&nbsp;
					<span class="tips"
							data-tip="<?php esc_html_e( 'From the list, select the packages that you want to be sent by courier.', 'inpost-for-woocommerce' ); ?>">
						<button id="easypack_get_courier" class="button-primary">
							<?php esc_html_e( 'Get courier', 'inpost-for-woocommerce' ); ?>
						</button>&nbsp;
					</span>
				</p>

			</div>

			<div style="float:left;">
				<p><span id="easypack_spinner_get_courier" class="spinner"></span></p>
			</div>
			<div style="clear:both;"></div>

		<?php else : ?>

			<p style="font-style: italic"><?php esc_html_e( 'Filter shipments by courier to show pickup options', 'inpost-for-woocommerce' ); ?></p>

		<?php endif; ?>


		<div style="float:none;">
			<h3><?php esc_html_e( 'Filters', 'inpost-for-woocommerce' ); ?></h3>

			<?php
			$params = array(
				'type'        => 'select',
				'options'     => $view_var_send_methods,
				'class'       => array( 'wc-enhanced-select' ),
				'label'       => __( 'Send method ', 'inpost-for-woocommerce' ),
				'label_class' => 'admin-label',
				'input_class' => array( 'admin-input' ),
			);
			woocommerce_form_field( 'send_method', $params, EasyPack_Shipment_Manager::getSendingMethodFilterFromRequest() );
			?>
		</div>
		<div style="float:none;">
			<?php
            //error_log(print_r('view_var_statuses', true));
            //error_log(print_r($view_var_statuses, true));
			$params = array(
				'type'        => 'select',
				'options'     => $view_var_statuses,
				'class'       => array( 'wc-enhanced-select' ),
				'label'       => __( 'Shipment status', 'inpost-for-woocommerce' ),
				'label_class' => 'admin-label',
				'input_class' => array( 'admin-input', 'max-width-select' ),
			);
			woocommerce_form_field( 'status', $params, EasyPack_Shipment_Manager::getStatusFilterFromRequest() );
			?>
		</div>
		<div style="float:none;">
			<?php
			$params = array(
				'type'        => 'select',
				'options'     => $view_var_services,
				'class'       => array( 'wc-enhanced-select' ),
				'label'       => __( 'Service', 'inpost-for-woocommerce' ),
				'label_class' => 'admin-label',
				'input_class' => array( 'admin-input' ),
			);
			woocommerce_form_field( 'service', $params, EasyPack_Shipment_Manager::getServiceFilterFromRequest() );
			?>
		</div>
		<div style="float:none;">
			<?php
			$params = array(
				'type'        => 'text',
				'class'       => array( '' ),
				'label'       => __( 'Tracking number', 'inpost-for-woocommerce' ),
				'label_class' => 'admin-label',
				'input_class' => array( 'admin-input' ),
			);
			woocommerce_form_field( 'tracking_number', $params, EasyPack_Shipment_Manager::getTrackingNumberFilterFromRequest() );
			?>
		</div>
		<div style="float:none;">
			<?php
			$params = array(
				'type'        => 'number',
				'class'       => array( '' ),
				'label'       => __( 'Order ID', 'inpost-for-woocommerce' ),
				'label_class' => 'admin-label',
				'input_class' => array( 'admin-input' ),
			);
			woocommerce_form_field( 'order_id', $params, EasyPack_Shipment_Manager::getOrderIdFilterFromRequest() );
			?>
		</div>
		<div style="float:none;">
			<?php
			$params = array(
				'type'        => 'text',
				'class'       => array( '' ),
				'label'       => __( 'Reference number', 'inpost-for-woocommerce' ),
				'label_class' => 'admin-label',
				'input_class' => array( 'admin-input' ),
			);
			woocommerce_form_field( 'reference_number', $params, EasyPack_Shipment_Manager::getReferenceNumberFilterFromRequest() );
			?>
		</div>
		<div style="float:none;">
			<?php
			$params = array(
				'type'        => 'text',
				'class'       => array( '' ),
				'label'       => __( 'Receiver email', 'inpost-for-woocommerce' ),
				'label_class' => 'admin-label',
				'input_class' => array( 'admin-input' ),
			);
			woocommerce_form_field( 'receiveresc_html_email', $params, EasyPack_Shipment_Manager::getReceiverEmailFilterFromRequest() );
			?>
		</div>
		<div style="float:none;">
			<?php
			$params = array(
				'type'        => 'text',
				'class'       => array( '' ),
				'label'       => __( 'Receiver phone', 'inpost-for-woocommerce' ),
				'label_class' => 'admin-label',
				'input_class' => array( 'admin-input' ),
			);
			woocommerce_form_field( 'receiver_phone', $params, EasyPack_Shipment_Manager::getReceiverPhoneFilterFromRequest() );
			?>
		</div>
		<div style="float:left;">
			<p>
				<input class="button button-primary" type="submit"
						value="<?php esc_html_e( 'Filter parcels', 'inpost-for-woocommerce' ); ?>"/>
			</p>
		</div>
		<div style="clear:both;"></div>


		<h3><?php esc_html_e( 'Actions for selected items', 'inpost-for-woocommerce' ); ?></h3>

		<div style="float:left;">
			<p>
				<?php if ( EasyPack_API()->is_pl() ) : ?>
				<span>
				<?php else : ?>
					<span class="tips" data-tip="">
				<?php endif; ?>
					<button class="button button-primary" id="get_stickers" name="get_stickers">
						<?php esc_html_e( 'Get stickers', 'inpost-for-woocommerce' ); ?>
					</button>
				</span>
			</p>
		</div>
		<div style="width: 10px; display: block"></div>
		<div style="float:left; padding-left: 10px;">
			<p>
				<?php if ( EasyPack_API()->is_pl() && true === $is_courier_context ) : ?>
				<span>
				<?php else : ?>
					<span class="tips" data-tip="">
				<?php endif; ?>
					<button class="button button-primary" id="get_return_stickers" name="get_return_stickers">
						<?php esc_html_e( 'Get return stickers', 'inpost-for-woocommerce' ); ?>
					</button>
				</span>
			</p>
		</div>

		<div style="float:left;">
			<p><span id="easypack_spinner_get_stickers" class="spinner"></span>
			</p>
		</div>


		<div style="float:left;">
			<p>
				<a id="easypack_create_posting_confirmation" class="button-primary"
					href=""><?php esc_html_e( 'Create posting confirmation', 'inpost-for-woocommerce' ); ?>
				</a>
			</p>
			<span id="inpost_confirmation_error_response"></span>
		</div>
		<div style="float:left;">
			<p><span id="easypack_spinner_posting_confirmation" class="spinner"></span></p>
		</div>
		<div style="clear:both;"></div>


		<div style="float:left;">
			<p>
				<?php if ( EasyPack_API()->api_country() == EasyPack_API()::COUNTRY_PL ) : ?>
				<span>
				<?php else : ?>
					<span class="tips" data-tip="
					<?php
					esc_html_e(
						'From the list, select the packages that you want to be collected to be sent. If Courier has been chosen, the collection of your packages by a courier will be arranged.',
						'inpost-for-woocommerce'
					);
					?>
						">
				<?php endif; ?>&nbsp;
				</span>
			</p>
		</div>
		<div style="float:left;">
			<p><span id="easypack_spinner_get_stickers" class="spinner"></span>
			</p>
		</div>
		<div style="clear:both;"></div>

	</form>
	<form id="easypack_shipment_form" method="post">
		<input type="hidden" id="easypack_posting_confirmation_request"
				name="easypack_posting_confirmation_request" value="0"/>
		<input type="hidden" id="easypack_create_manifest_input"
				name="easypack_create_manifest_input" value="0"/>
		<input type="hidden" id="easypack_dispatch_point"
				name="easypack_dispatch_point" value="0"/>
		<input type="hidden"
				name="page" value="easypack_shipment">
		<input type="hidden" name="easypack_get_stickers_request"
				id="easypack_get_stickers_request" value="0"/>
		<input type="hidden" name="easypack_get_stickers_ret_request"
				id="easypack_get_stickers_ret_request" value="0"/>
		<input type="hidden" name="easypack_get_sticker_single_request"
				id="easypack_get_sticker_single_request" value="0"/>
		<input type="hidden" name="get_sticker_order_id"
				id="get_sticker_order_id" value=""/>
		<input type="hidden" name="easypack_get_sticker_single_request_ret"
				id="easypack_get_sticker_single_request_ret" value="0"/>


		<?php
		$view_var_shipment_manager_list_table->display();
		?>
		<?php
		$total_pagination_pages = $view_var_shipment_manager_list_table->custom_pagination;
		$current_page           = isset( $_GET['shipments_page'] ) ? (int) sanitize_text_field( $_GET['shipments_page'] ) : 1;
		$next_page              = ( $current_page + 1 ) <= $total_pagination_pages ? $current_page + 1 : $total_pagination_pages;
		$previous_page          = ( $current_page - 1 ) >= $total_pagination_pages ? $current_page - 1 : 1;
		$is_disabled_first      = $current_page === 1 ? 'disabled' : null;
		$is_disabled_last       = $current_page == $total_pagination_pages ? 'disabled' : null;
		if ( $total_pagination_pages ) {
			$current_rel_uri = add_query_arg( null, null );
			?>
			<form method="get" action="<?php echo esc_url( $current_rel_uri ); ?>">
				<div class="tablenav-pages">
					<span class="displaying-num">
						Liczba stron w tabeli: <?php echo esc_html( $total_pagination_pages ); ?>
					</span>
					<span class="pagination-links">
						<?php if ( $is_disabled_first ) { ?>
							<span class="tablenav-pages-navspan button <?php echo esc_html( $is_disabled_first ); ?>" aria-hidden="true">«</span>
							<span class="tablenav-pages-navspan button <?php echo esc_html( $is_disabled_first ); ?>" aria-hidden="true">‹</span>
						<?php } else { ?>
							<a class="first-page button" href="<?php echo esc_url( $current_rel_uri ); ?>&shipments_page=<?php echo esc_attr( 1 ); ?>">
								<span class="screen-reader-text">1 strona</span>
								<span aria-hidden="true">«</span>
							</a>
							<a class="prev-page button" href="<?php echo esc_url( $current_rel_uri ); ?>&shipments_page=<?php echo esc_attr( $previous_page ); ?>">
								<span class="screen-reader-text">Poprzednia strona</span>
								<span aria-hidden="true">‹</span>
							</a>
						<?php } ?>

						<span class="screen-reader-text">Bieżąca strona</span>
						<span id="table-paging" class="paging-input">
							<span class="tablenav-paging-text">
								<?php echo esc_html( $current_page ); ?> strona z <span class="total-pages">
									<?php echo esc_html( $total_pagination_pages ); ?>
								</span>
							</span>
						</span>

						<?php if ( $is_disabled_last ) { ?>
							<span class="tablenav-pages-navspan button <?php echo esc_html( $is_disabled_last ); ?>" aria-hidden="true">›</span>
							<span class="tablenav-pages-navspan button <?php echo esc_html( $is_disabled_last ); ?>" aria-hidden="true">»</span>
						<?php } else { ?>
							<a class="next-page button" href="<?php echo esc_url( $current_rel_uri ); ?>&shipments_page=<?php echo esc_attr( $next_page ); ?>">
								<span class="screen-reader-text">Następna strona</span>
								<span aria-hidden="true">›</span>
							</a>
							<a class="last-page button" href="<?php echo esc_url( $current_rel_uri ); ?>&shipments_page=<?php echo esc_attr( $total_pagination_pages ); ?>">
								<span class="screen-reader-text">Ostatnia strona</span>
								<span aria-hidden="true">»</span>
							</a>
						<?php } ?>

					</span>
				</div>
			</form>
		<?php } ?>

	</form>

</div>

<script type="text/javascript">

	jQuery('#easypack_get_courier').click(function () {

		var parcels = [];
		var count_parcels = 0;
		jQuery('input.easypack_parcel').each(function (i) {
			if (jQuery(this).is(':checked')) {
				parcels[i] = jQuery(this).val();
				count_parcels++;
			}
		});
		if (count_parcels == 0) {
			alert('<?php esc_html_e( 'No parcels selected.', 'inpost-for-woocommerce' ); ?>');
			jQuery("#easypack_spinner_get_stickers").removeClass("is-active");
			return false;
		}
		jQuery('#easypack_create_manifest_input').val(1);
		jQuery('#easypack_dispatch_point').val(jQuery('#dispatch_point').val());
		jQuery("#easypack_shipment_form").submit();
		return false;
	});


	jQuery('#refresh_statuses_btn').click(function () {
		var obj = jQuery('<input>', {
			'type': 'hidden',
			'name': 'refresh_statuses',
			'value': '1'
		});
		jQuery('#easypack_shipment_form').append(obj).submit();
		return false;
	});


	jQuery('#get_stickers').click(function () {
		var parcels = [];
		var count_parcels = 0;
		jQuery('input.easypack_parcel').each(function (i) {
			if (jQuery(this).is(':checked')) {
				parcels[i] = jQuery(this).val();
				count_parcels++;
			}
		});
		if (count_parcels === 0) {
			alert('<?php esc_html_e( 'No parcels selected.', 'inpost-for-woocommerce' ); ?>');
			jQuery('#easypack_spinner_get_stickers').removeClass("is-active");
			return false;
		}

		jQuery('#easypack_get_stickers_request').val('1');
		jQuery('#easypack_shipment_form').attr('target', '_blank');
		jQuery('#easypack_shipment_form').submit();
		jQuery('#easypack_shipment_form').attr('target', '_self');
		jQuery('#easypack_get_stickers_request').val('0');

		return false;
	});

	jQuery('.get_sticker_action').click(function () {
		var parcel = jQuery(this).data('id');

		jQuery('#get_sticker_order_id').val(parcel);
		jQuery('#easypack_get_sticker_single_request').val('1');
		jQuery('#easypack_shipment_form').attr('target', '_blank');
		jQuery('#easypack_shipment_form').submit();
		jQuery('#easypack_shipment_form').attr('target', '_self');
		jQuery('#easypack_get_sticker_single_request').val('0');
		jQuery('#order_id').val('');

		return false;
	});

	jQuery('.get_sticker_return_action').click(function () {
		var parcel = jQuery(this).data('id');

		jQuery('#get_sticker_order_id').val(parcel);
		jQuery('#easypack_get_sticker_single_request_ret').val('1');
		jQuery('#easypack_shipment_form').attr('target', '_blank');
		jQuery('#easypack_shipment_form').submit();
		jQuery('#easypack_shipment_form').attr('target', '_self');
		jQuery('#easypack_get_sticker_single_request_ret').val('0');
		jQuery('#order_id').val('');

		return false;
	});

	jQuery('#get_return_stickers').click(function () {
		var parcels = [];
		var count_parcels = 0;
		jQuery('input.easypack_parcel').each(function (i) {
			if (jQuery(this).is(':checked')) {
				parcels[i] = jQuery(this).val();
				count_parcels++;
			}
		});
		if (count_parcels == 0) {
			alert('<?php esc_html_e( 'No parcels selected.', 'inpost-for-woocommerce' ); ?>');
			jQuery('#easypack_spinner_get_stickers').removeClass("is-active");
			return false;
		}

		jQuery('#easypack_get_stickers_ret_request').val('1');
		jQuery('#easypack_shipment_form').attr('target', '_blank');
		jQuery('#easypack_shipment_form').submit();
		jQuery('#easypack_shipment_form').attr('target', '_self');
		jQuery('#easypack_get_stickers_ret_request').val('0');

		return false;
	});

	jQuery('.easypack_parcel').change(function () {
		var easypack_get_courier_disabled = false;
		var easypack_get_courier_count = 0;
		jQuery('.easypack_parcel').each(function () {
			if (jQuery(this).is(':checked')) {
				easypack_get_courier_count++;
				if (jQuery(this).data('status') !== 'created'
					&& jQuery(this).data('status') !== 'confirmed') {
					easypack_get_courier_disabled = true;
				}
			}
		});
		if (easypack_get_courier_count == 0) easypack_get_courier_disabled = true;
		jQuery('#easypack_get_courier').attr('disabled', easypack_get_courier_disabled);
	});


	jQuery('#easypack_create_posting_confirmation').click(function (e) {
		e.preventDefault();
		let parcels = [];
		let count_parcels = 0;
		let first_order = '';
		jQuery('input.easypack_parcel').each(function (i) {
			if (0 === i ) {
				first_order = jQuery(this).val();
			}
			if (jQuery(this).is(':checked')) {
				parcels[i] = jQuery(this).val();
				count_parcels++;
			}
		});
		if (count_parcels === 0) {
			alert('<?php esc_html_e( 'No parcels selected to create manifest.', 'inpost-for-woocommerce' ); ?>');
			jQuery('#easypack_spinner_posting_confirmation').removeClass("is-active");
			return false;
		}

		jQuery('#inpost_confirmation_error_response').html('');

		let data = {
			action: 'posting_confirmation_request',
			nonce: easypack_settings.nonce,
			parcels: parcels
		};

		jQuery.ajax(
			{
				type: 'POST',
				url: easypack_settings.ajaxurl,
				data: data,
				xhrFields: {
					responseType: 'blob'  // This is key!
				},
				success: function (response) {
					console.log( 'posting_confirmation_request:' );

					try {
						let filename = 'inpost_posting_confirmation_' + first_order + '.pdf';
						let url = URL.createObjectURL(response);

						// Option A: Download.
						let link = document.createElement('a');
						link.href = url;
						link.download = filename;
						link.click();

						// Option B: View in new window
						// window.open(url);

						// Clean up.
						setTimeout(() => URL.revokeObjectURL(url), 1000);
					} catch (e) {
						let error                = "<span class='inpost_confirmation_error_response' style='font-weight:bold; color:#ff0000'>" +
							"Error:<br><pre>" + e + "<pre></span>";
						jQuery( '#inpost_confirmation_error_response' ).html( error );
					}


				},
				error: function (jqXHR, textStatus, errorThrown) {
					console.log( "Error response posting_confirmation" );
					console.log( textStatus );
					console.log( 'Error: ' + errorThrown + ' ' + jqXHR.responseText );
				}
			}
		);

		return false;
	});


</script>

<style>
	.optional {
		display: none;
	}
</style>
