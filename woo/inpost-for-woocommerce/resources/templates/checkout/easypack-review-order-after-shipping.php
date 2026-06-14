<?php
/**
 * Review Order After Shipping EasyPack
 *
 * @author
 * @package    EasyPack/Templates
 * @version
 */

use InspireLabs\WoocommerceInpost\Geowidget_v5;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$parcel_machine_selected = false;
$selected                = '';


// double fields for DIVI templates.
add_action(
	'woocommerce_review_order_before_submit',
	function () {
		?>
	<input
			type="hidden"
			id="divi_parcel_machine_id"
			name="parcel_machine_id"
			value=""
	/>
	<input
			type="hidden"
			id="divi_parcel_machine_desc"
			name="parcel_machine_desc"
			value=""
	/>
		<?php
	}
);


?>

<tr class="easypack-parcel-machine">
	<th class="easypack-parcel-machine-label">
		<?php // echo __( 'Select Parcel Locker', 'inpost-for-woocommerce' ); ?>
	</th>
	<td class="easypack-parcel-machine-select">
		<?php if ( defined( 'DOING_AJAX' ) && true === DOING_AJAX ) : ?>

			<div class="easypack_show_geowidget" id="easypack_show_geowidget">
				<?php echo esc_html__( 'Select parcel locker', 'inpost-for-woocommerce' ); ?>
			</div>

			<div id="selected-parcel-machine" class="hidden-paczkomat-data">
				<div><span class="easypack-visible-point-header italic" style="font-weight: bold">
				<?php echo esc_html__( 'Selected parcel locker:', 'inpost-for-woocommerce' ); ?>
				</span></div>
				<span class="easypack-visible-point-description" id="selected-parcel-machine-id"></span>

				<input type="hidden" id="parcel_machine_id"
						name="parcel_machine_id" class="parcel_machine_id"/>
				<input type="hidden" id="parcel_machine_desc"
						name="parcel_machine_desc" class="parcel_machine_desc"/>
			</div>        

		<?php endif ?>
	</td>
</tr>
