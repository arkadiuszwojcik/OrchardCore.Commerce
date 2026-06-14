<?php
/**
 * My Account
 *
 * @author
 * @package 	EasyPack/Templates
 * @version
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}
?>
<br/>
<h2><?php esc_html_e( 'Returns', 'inpost-for-woocommerce' ); ?></h2>
<div style="text-align: center; width: 240px; float:left;">
<a href="<?php echo esc_url( $returns_page ); ?>"><img style="border: none;" src="<?php echo esc_url( $img_src ); ?>"></a><br/>
<a href="<?php echo esc_url( $returns_page ); ?>"><?php echo esc_html( $returns_page_title ); ?></a>
</div>
<div style="clear:both;"></div>
<br/><br/>