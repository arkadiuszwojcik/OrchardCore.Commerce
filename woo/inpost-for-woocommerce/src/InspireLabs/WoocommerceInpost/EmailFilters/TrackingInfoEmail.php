<?php

namespace InspireLabs\WoocommerceInpost\EmailFilters;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
} // Exit if accessed directly.

use Exception;
use InspireLabs\WoocommerceInpost\EasyPack;

class TrackingInfoEmail {


	/**
	 * Sends tracking information email to customer.
	 *
	 * This function checks if delivery notices are enabled, retrieves the order details,
	 * and sends a tracking information email to the customer's billing email address.
	 *
	 * @param int   $order_id        WooCommerce order ID.
	 * @param array $tracking_numbers Array of tracking numbers for the order.
	 *
	 * @return void
	 *
	 * @uses wc_get_order()
	 * @uses EasyPack_Helper()->get_tracking_url()
	 * @uses WC()->mailer()
	 * @uses get_tracking_info_email_html()
	 *
	 * @since 1.0.0
	 * @access public
	 */
	public function send_tracking_info_email( $order_id ) {

		if ( 'yes' === get_option( 'easypack_delivery_notice' ) ) {

			$order_email = '';

			$inpost_id = EasyPack_Helper()->get_inpost_id_by_order( $order_id );
			$order     = wc_get_order( $order_id );
			if ( $inpost_id && $order && ! is_wp_error( $order ) ) {
				$tracking_numbers = array();

				try {
					$response = EasyPack_API()->customer_parcel_get_by_id( $inpost_id );
					if ( ! empty( $response['parcels'] ) && is_array( $response['parcels'] ) ) {
						foreach ( $response['parcels'] as $parcel ) {
							if ( ! empty( $parcel['tracking_number'] ) ) {
								$tracking_numbers[] = $parcel['tracking_number'];
							}
						}
					}
				} catch ( Exception $e ) {
					\wc_get_logger()->debug( 'Exception when trying to get package data for order_id: ' . $order_id, array( 'source' => 'inpost-pl-log' ) );
					\wc_get_logger()->debug( print_r( $e->getMessage(), true ), array( 'source' => 'inpost-pl-log' ) );
				}

				$order_email = $order->get_billing_email();
                $is_email_sent = $order->get_meta( 'inpost_pl_tracking_number_sent' );
				if ( ! $is_email_sent && ! empty( $order_email ) && ! empty( $tracking_numbers ) ) {
					$tracking_url = EasyPack_Helper()->get_tracking_url();
					$mailer       = WC()->mailer();
					$recipient    = $order_email;
					$subject      = esc_html__( 'Your order has been given a tracking number', 'inpost-for-woocommerce' );
					$content      = $this->get_tracking_info_email_html(
						$tracking_url,
						$tracking_numbers,
						$order,
						$mailer,
						$subject
					);
					$headers      = "Content-Type: text/html\r\n";
					$mailer->send( $recipient, $subject, $content, $headers );
                    $order->update_meta_data( 'inpost_pl_tracking_number_sent', 1 );
                    $order->save();
				}
			}
		}
	}

	/**
	 * Generates HTML content for tracking information email.
	 *
	 * Retrieves and renders the email template with tracking details and order information.
	 *
	 * @param string       $tracking_link    URL for tracking shipment.
	 * @param array        $tracking_numbers Array of tracking numbers.
	 * @param mixed        $order           WooCommerce order object.
	 * @param mixed        $mailer          WooCommerce mailer instance.
	 * @param string|false $heading         Optional. Email heading text. Default false.
	 *
	 * @return string HTML content of the email
	 *
	 * @uses wc_get_template_html()
	 *
	 * @access private
	 * @since 1.0.0
	 */
	private function get_tracking_info_email_html( $tracking_link, $tracking_numbers, $order, $mailer, $heading = false ) {

		$template = wc_get_template_html(
			'emails/send-tracking-to-buyer.php',
			array(
				'tracking_link'    => $tracking_link,
				'tracking_numbers' => $tracking_numbers,
				'order'            => $order,
				'email_heading'    => $heading,
				'sent_to_admin'    => false,
				'plain_text'       => false,
				'email'            => $mailer,
			)
		);

		return $template;
	}
}
