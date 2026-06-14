<?php /** @var ShipX_Shipment_Model $shipment */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
} // Exit if accessed directly.

use InspireLabs\WoocommerceInpost\EasyPack;
use InspireLabs\WoocommerceInpost\shipx\models\shipment\ShipX_Shipment_Model;

?>
<p>
	<?php if ( $shipment instanceof ShipX_Shipment_Model && ! $additional_package ) : ?>
		<label disabled style="display: block" for="reference_number"
				class="graytext">
			<?php esc_html_e( 'Reference number: ', 'inpost-for-woocommerce' ); ?>
		</label>
	<?php else : ?>
		<label disabled style="display: block" for="reference_number" class="">
			<?php esc_html_e( 'Reference number: ', 'inpost-for-woocommerce' ); ?>
		</label>
	<?php endif ?>

	<?php
	if ( $shipment instanceof ShipX_Shipment_Model
	&& null !== $shipment->getReference() && ! $additional_package
	) :
		?>
		<textarea disabled class="reference_number" rows="5" cols="27"
					style=""
					id="reference_number"
					name="reference_number"><?php echo esc_html( $shipment->getReference() ); ?></textarea>
		<?php
	else :

		$default_ref_number = EasyPack_Helper()->get_maybe_custom_reference_number( $order_id );

		$is_order_note_exists = false;

		$ref_number = get_post_meta( $order_id, '_reference_number', true )
		? get_post_meta( $order_id, '_reference_number', true )
		: $default_ref_number;

		if ( 'yes' === get_option( 'easypack_add_order_note' ) ) {
			$order_note = '';
			$order      = wc_get_order( $order_id );
			if ( $order && ! is_wp_error( $order ) && is_object( $order ) ) {
				$order_note = $order->get_customer_note();
				if ( ! empty( $order_note ) ) {
					$is_order_note_exists = true;
				}
			}

			$ref_number = $ref_number . ' ' . $order_note;
		}
		?>
	<textarea class="reference_number inpost-pl-charcount" rows="5" cols="27"
				style="max-width: 100%;"
				id="reference_number"
				data-id="<?php echo esc_attr( trim( $ref_number ) ); ?>"
				maxlength="100"
				name="reference_number"><?php echo esc_attr( trim( $ref_number ) ); ?></textarea>
	<p class="i-pl-charcount-label">
		<span class="label label-info" id="ipl-char-num">100 znaków do końca. Maksimum 100 znaków.</span><br>
		<span class="label i-pl-charcount-limit-exceeded">Długość wiadomości przekracza już limit znaków.</span>
	</p>
	<style>
		.i-pl-charcount-label {
			background: #d0ebde;
			padding: 4px;
			border-radius: 5px;
		}
		.i-pl-charcount-label.limit {
			background: #edb8af;
		}
		.i-pl-charcount-limit-exceeded {
			display: none;
		}
		.i-pl-charcount-limit-exceeded.show {
			display: block;
		}
	</style>
	<script>
		(function($) {
			$(document).ready(function(){
				var maxChars = 100;
				var textLength = 0;
				var comment = "";
				var outOfChars = 'Osiągnąłeś limit znaków: ' + maxChars;
				let order_note = "<?php echo esc_html( $is_order_note_exists ); ?>";

				textLength = $('.inpost-pl-charcount').val().length;

				var count = maxChars - textLength;
				if (count >= maxChars) {
					$('#ipl-char-num').text(outOfChars);
					$('.i-pl-charcount-label').addClass('limit');
					if( order_note ) {
						$('.i-pl-charcount-limit-exceeded').addClass('show');
					}
				} else {
					$('#ipl-char-num').text(count + ' znaków do końca. Maksimum 100 znaków.');
					$('.i-pl-charcount-label').removeClass('limit');
					$('.i-pl-charcount-limit-exceeded').removeClass('show');
				}

				/* fix val so it counts carriage returns */
				$.valHooks.textarea = {
					get: function(e) {
						return e.value.replace(/\r?\n/g, "\r\n");
					}
				};

				function checkCount() {
					textLength = $('.inpost-pl-charcount').val().length;
					if (textLength >= maxChars) {
						$('#ipl-char-num').text(outOfChars);
						$('.i-pl-charcount-label').addClass('limit');
					}
					else {
						count = maxChars - textLength;
						$('#ipl-char-num').text(count + ' znaków do końca. Maksimum 100 znaków.');
						$('.i-pl-charcount-label').removeClass('limit');
					}
				}

				$('.inpost-pl-charcount').keyup(function() {
					checkCount();
					comment = $(this).val();
				});
			});
		})(jQuery);
	</script>
<?php endif; ?>
</p>
