<?php
/**
 * Seller create the parcel and tracking number - plain email template
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/plain/send-tracking-to-buyer.php
 *
 * @see https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates\Emails\Plain
 * @version 3.7.0
 *
 * @param string $tracking_link URL for tracking shipment
 * @param array $tracking_numbers Array of tracking numbers
 * @param mixed $order WooCommerce order object
 * @param mixed $mailer WooCommerce mailer instance
 * @param string|false $heading Optional. Email heading text. Default false
 */

defined( 'ABSPATH' ) || exit;

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
echo esc_html( wp_strip_all_tags( $email_heading ) );
echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

if (!empty($tracking_numbers)) {
    /* translators: %s: Customer first name */
    echo sprintf(esc_html__('Hi %s,', 'inpost-for-woocommerce'), esc_html($order->get_billing_first_name())) . "\n\n";

    if (count($tracking_numbers) === 1) {
        $tracking_number = reset($tracking_numbers);
        echo esc_html__('A tracking number has been given for your order. It will soon move on its journey', 'inpost-for-woocommerce') . "\n\n";
        echo esc_html__('Tracking link:', 'inpost-for-woocommerce') . "\n";
        echo esc_url($tracking_link . $tracking_number) . "\n\n";
    } elseif (count($tracking_numbers) > 1) {
        echo esc_html__('For your order was created multi-package. It will soon move on its journey', 'inpost-for-woocommerce') . "\n\n";
        echo esc_html__('Tracking links:', 'inpost-for-woocommerce') . "\n";
        foreach ($tracking_numbers as $tracking_number) {
            echo esc_url($tracking_link . $tracking_number) . "\n";
        }
        echo "\n";
    }
}

echo "----------------------------------------\n\n";

do_action('woocommerce_email_order_details', $order, $sent_to_admin, true, $email);

echo "\n----------------------------------------\n\n";

do_action('woocommerce_email_order_meta', $order, $sent_to_admin, true, $email);

do_action('woocommerce_email_customer_details', $order, $sent_to_admin, true, $email);

echo "\n----------------------------------------\n\n";

if (isset($additional_content)) {
    echo esc_html(wp_strip_all_tags(wptexturize($additional_content)));
    echo "\n\n----------------------------------------\n\n";
}

echo wp_kses_post(apply_filters('woocommerce_email_footer_text', get_option('woocommerce_email_footer_text')));