<?php

namespace InspireLabs\WoocommerceInpost\admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
} // Exit if accessed directly.

use InspireLabs\WoocommerceInpost\EasyPack;
use WP_List_Table;
use WP_Query;

/**
 * EasyPack_Custom_Product_List_Table
 */
class EasyPack_Custom_Product_List_Table extends WP_List_Table {

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'product',
				'plural'   => 'products',
				'ajax'     => false,
			)
		);
	}


	/**
	 * Prepare items
	 *
	 * @return void
	 */
	public function prepare_items() {
		$columns               = $this->get_columns();
		$hidden                = array();
		$sortable              = $this->get_sortable_columns();
		$this->_column_headers = array( $columns, $hidden, $sortable );

		$per_page = 20;

		if ( isset( $_REQUEST['per_page'] ) && is_numeric( $_REQUEST['per_page'] ) ) {
			$per_page = intval( $_REQUEST['per_page'] );
		}

		$current_page = $this->get_pagenum();
		$total_items  = $this->get_total_products();

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
			)
		);

		$this->items = $this->get_products( $per_page, $current_page );
	}

	/**
	 * Get columns
	 *
	 * @return array
	 */
	public function get_columns() {
		$columns = array(
			'cb'               => '<input type="checkbox" />',
			'title'            => __( 'Product', 'inpost-for-woocommerce' ),
			'img'              => __( 'Img', 'inpost-for-woocommerce' ),
			'sku'              => __( 'SKU', 'inpost-for-woocommerce' ),
			'categories'       => __( 'Categories', 'inpost-for-woocommerce' ),
			'shipping_methods' => __( 'Allowed Shipping Methods', 'inpost-for-woocommerce' ),
			'locker'           => __( 'Locker size', 'inpost-for-woocommerce' ),
			'action_result'    => __( 'Action', 'inpost-for-woocommerce' ),
		);
		return $columns;
	}

	/**
	 * Get sortable columns
	 *
	 * @return array[]
	 */
	public function get_sortable_columns() {
		$sortable_columns = array(
			'title' => array( 'title', false ),
			'sku'   => array( 'sku', false ),
		);
		return $sortable_columns;
	}


	/**
	 * Get totalproducts
	 *
	 * @return int
	 */
	private function get_total_products() {
		$args = array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		);

		// Polylang (and Polylang Pro) filters queries in admin by current language.
		// For this bulk-edit table we want to list ALL product translations (all post IDs).
		if ( function_exists( 'pll_languages_list' ) ) {
			$langs        = pll_languages_list( array( 'fields' => 'slug' ) );
			$args['lang'] = is_array( $langs ) && ! empty( $langs ) ? implode( ',', $langs ) : '';
		}

		if ( ! empty( $_REQUEST['s'] ) ) {
			$args['s'] = $_REQUEST['s'];
		}

		if ( ! empty( $_REQUEST['product_cat'] ) ) {

			$args['tax_query'][] = array(
				'taxonomy' => 'product_cat',
				'field'    => 'name',
				'terms'    => sanitize_text_field( wp_unslash( $_REQUEST['product_cat'] ) ),
			);
		}

		if ( ! empty( $_REQUEST['product_type'] ) ) {
			$args['tax_query'][] = array(
				'taxonomy' => 'product_type',
				'field'    => 'slug',
				'terms'    => sanitize_text_field( wp_unslash( $_REQUEST['product_type'] ) ),
			);
		}
		
		if ( ! empty( $_REQUEST['product_shipping_class'] ) ) {
            $shipping_class_slug = sanitize_text_field( wp_unslash( $_REQUEST['product_shipping_class'] ) );
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'product_shipping_class',
                    'field'    => 'slug',
                    'terms'    => $shipping_class_slug,
                ),
            );
        }

		$query = new WP_Query( $args );
		return $query->found_posts;
	}

	/**
	 * Get products
	 *
	 * @param int $per_page $per_page.
	 * @param int $page_number $page_number.
	 * @return array
	 */
	private function get_products( int $per_page = 20, int $page_number = 1 ) {

		if ( ! empty( $_REQUEST['product_qty'] ) ) {
			$per_page = sanitize_text_field( $_REQUEST['product_qty'] );
		}

		$orderby = 'date';
		if ( isset( $_REQUEST['orderby'] ) ) {
			$orderby = 'title';
		}

		$args = array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'orderby'        => $orderby,
			'order'          => isset( $_REQUEST['order'] ) ? sanitize_text_field( $_REQUEST['order'] ) : 'DESC',
			'paged'          => $page_number,
		);

		// Polylang (and Polylang Pro) filters queries in admin by current language.
		// For this bulk-edit table we want to list ALL product translations (all post IDs).
		if ( function_exists( 'pll_languages_list' ) ) {
			$langs        = pll_languages_list( array( 'fields' => 'slug' ) );
			$args['lang'] = is_array( $langs ) && ! empty( $langs ) ? implode( ',', $langs ) : '';
		}

		if ( ! empty( $_REQUEST['s'] ) ) {
			$args['s'] = sanitize_text_field( $_REQUEST['s'] );
		}

		if ( ! empty( $_REQUEST['product_cat'] ) ) {

			$args['tax_query'][] = array(
				'taxonomy' => 'product_cat',
				'field'    => 'name',
				'terms'    => sanitize_text_field( $_REQUEST['product_cat'] ),
			);
		}

		if ( ! empty( $_REQUEST['product_type'] ) ) {
			$args['tax_query'][] = array(
				'taxonomy' => 'product_type',
				'field'    => 'slug',
				'terms'    => sanitize_text_field( $_REQUEST['product_type'] ),
			);
		}
		
		if ( ! empty( $_REQUEST['product_shipping_class'] ) ) {
            $shipping_class_slug = sanitize_text_field( wp_unslash( $_REQUEST['product_shipping_class'] ) );
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'product_shipping_class',
                    'field'    => 'slug',
                    'terms'    => $shipping_class_slug,
                ),
            );
        }

		$product_query = new WP_Query( $args );
		return array_map( 'wc_get_product', $product_query->posts );
	}


	/**
	 * Column content
	 *
	 * @param \WC_Product $item product.
	 * @param string      $column_name $column_name.
	 * @return bool|string|void
	 */
	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'sku':
				return $item->get_sku();
			case 'img':
				return $this->get_product_preview_html( $item->get_id() );
			case 'categories':
				return wp_strip_all_tags( wc_get_product_category_list( $item->get_id() ) );
			case 'shipping_methods':
				return $this->get_product_inpost_shipping_methods( $item->get_id() );
			case 'locker':
				return $this->get_product_inpost_locker_size( $item->get_id() );
			case 'action_result':
				return $this->get_action_result_html( $item->get_id() );
			default:
				return '';
		}
	}

	/**
	 * Row checkbox
	 *
	 * @param \WC_Product $item $item.
	 * @return string|void
	 */
	public function column_cb( $item ) {
		return sprintf(
			'<input class="inpost_pl_check_row" type="checkbox" name="product[]" value="%s" />',
			$item->get_id()
		);
	}

	/**
	 * Column title
	 *
	 * @param \WC_Product $item $item.
	 * @return string
	 */
	public function column_title( $item ) {
		$actions = array(
			'edit' => sprintf( '<a href="%s">Edit</a>', get_edit_post_link( $item->get_id() ) ),
			'view' => sprintf( '<a href="%s">View</a>', get_permalink( $item->get_id() ) ),
		);

		return sprintf( '%1$s %2$s', $item->get_name(), $this->row_actions( $actions ) );
	}

	/**
	 * Bulk actions
	 *
	 * @return array
	 */
	public function get_bulk_actions() {
		$actions = array();
		return $actions;
	}

	/**
	 * Process bulk action
	 *
	 * @return void
	 */
	public function process_bulk_action() {
	}




	/**
	 * Product thumbnail
	 *
	 * @param int $product_id $product_id.
	 * @return string
	 */
	public function get_product_preview_html( $product_id ) {
		$product_image = get_the_post_thumbnail( $product_id );
		return $product_image;
	}

	/**
	 * Get Inpost methods based on product configuration
	 *
	 * @param int $product_id $product_id.
	 * @return false|string
	 */
	public function get_product_inpost_shipping_methods( $product_id ) {
		$product_settings  = new EasyPack_Product_Shipping_Method_Selector();
		$config_by_product = $product_settings->get_config_by_product_id( $product_id );

		$inpost_shipping_methods = EasyPack_Helper()->get_inpost_methods();

		\ob_start();?>
		<div class="inpost_pl_allowed_methods_<?php echo esc_attr( $product_id ); ?>" data-product-id="<?php echo esc_attr( $product_id ); ?>">
		<ul class="inpost_pl_allowed_methods_<?php echo esc_attr( $product_id ); ?>" data-product-id="<?php echo esc_attr( $product_id ); ?>">
		<?php
		foreach ( $inpost_shipping_methods as $key => $method ) {
			$value   = 1;
			$checked = 'checked';
			if ( is_array( $config_by_product ) ) {
				$value   = in_array( $method['method_title_with_id'], $config_by_product, true ) ? 1 : '';
				$checked = in_array( $method['method_title_with_id'], $config_by_product, true ) ? 'checked' : '';
			}
			?>
			<li>
				<input type="checkbox"
					data-id="<?php echo esc_attr( $product_id ); ?>"
					data-method="<?php echo esc_attr( $method['method_title_with_id'] ); ?>"
					id="inpost_pl_allowed_methods[<?php echo esc_attr( $product_id ); ?>][<?php echo esc_attr( $method['method_title_with_id'] ); ?>]"
					name="inpost_pl_allowed_methods[<?php echo esc_attr( $product_id ); ?>][<?php echo esc_attr( $method['method_title_with_id'] ); ?>]"
					value="<?php echo esc_attr( $value ); ?>"
					class="checkbox"
					<?php echo esc_attr( $checked ); ?>
				>
				<label for="inpost_pl_allowed_methods[<?php echo esc_attr( $product_id ); ?>][<?php echo esc_attr( $method['method_title_with_id'] ); ?>]">
					<?php echo esc_html( $method['user_title'] ); ?>
				</label>
			</li>
		<?php } ?>
		</ul>
		</div>
		<?php
		$output = \ob_get_contents();
		\ob_end_clean();
		return $output;
	}


	/**
	 * Get Inpost locker size based on product configuration
	 *
	 * @param int $product_id $product_id.
	 * @return false|string
	 */
	public function get_product_inpost_locker_size( $product_id ) {

		$meta_key = EasyPack::ATTRIBUTE_PREFIX . '_parcel_dimensions';

		$options = array(
			'small'  => __( 'Size A', 'inpost-for-woocommerce' ),
			'medium' => __( 'Size B', 'inpost-for-woocommerce' ),
			'large'  => __( 'Size C', 'inpost-for-woocommerce' ),
		);

		ob_start();

		$saved_value = get_post_meta( $product_id, $meta_key, true );

		// Set default to 'small' if $saved_value is empty.
		$selected_radio = ! empty( $saved_value ) ? $saved_value : 'small';

		$size_status_args = array(
			'id'            => 'easypack_parcel_dimensions_' . $product_id,
			'value'         => $selected_radio,
			'wrapper_class' => 'easypack_parcel_dimensions_grouped_' . $product_id,
			'label'         => '',
			'options'       => $options,
			'desc_tip'      => false,
			'description'   => '',
		);

		woocommerce_wp_radio(
			$size_status_args
		);

		$output = ob_get_contents();
		ob_end_clean();
		return $output;
	}


	/**
	 * Action preloader
	 *
	 * @param int $product_id $product_id.
	 * @return string
	 */
	public function get_action_result_html( $product_id ) {
		return '<span class="inpost_action_result" data-id="' . esc_attr( $product_id ) . '">
		<input type="button" data-product-id="' . esc_attr( $product_id ) . '" class="button button-secondary button-large inpost-pl-update-single-product" value="' . esc_html__( 'Update settings', 'inpost-for-woocommerce' ) . '">
        <span data-action-id="' . esc_attr( $product_id ) . '" style="display:none;" class="status inpost-pl-action-status" title="OK"></span>
        <img data-id="' . esc_attr( $product_id ) . '" class="inpost_action_result_preloader" style="display:none;" src="' . esc_url( home_url() ) . '/wp-includes/js/tinymce/skins/lightgray/img/loader.gif">
        </span>';
	}


	/**
	 * Render table and filters
	 *
	 * @return void
	 */
	public static function render_custom_product_settings_page() {
		$product_table = new EasyPack_Custom_Product_List_Table();

		$product_table->prepare_items();
		$current_rel_uri = add_query_arg( '', '' );
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<?php
			$plugin_data = new EasyPack();
			$img         = $plugin_data->getPluginImages() . '/gabarytABC.jpg';
			?>

				<div class="inpost_pl_row">
					<div class="inpost_pl_row_item item_left" style="padding:5px 5px 5px 5px;" id="inpost-pl-dimensions-wrap">
						<img style="max-height:200px;display:flex;margin-left:auto;margin-right:auto;margin-bottom:20px;" id="inpost-pl-dimensions" src="<?php echo esc_url( $img ); ?>" alt="gabaryt Inpost">
					</div>
					<div class="inpost_pl_row_item item_right" style="padding:5px 5px 5px 5px;">
						<?php echo esc_html__( 'By changing these settings you change the settings for all products on the page in a similar way.', 'inpost-for-woocommerce' ); ?>
						<div class="inpost_pl_row">
							<span class="inpost_pl_row_item item_left">
								<?php

								$options = array(
									'small'  => __( 'Size A', 'inpost-for-woocommerce' ),
									'medium' => __( 'Size B', 'inpost-for-woocommerce' ),
									'large'  => __( 'Size C', 'inpost-for-woocommerce' ),
								);

								$size_status_args = array(
									'id'            => 'easypack_all_parcel_dimensions',
									'value'         => '',
									'wrapper_class' => 'easypack_parcel_dimensions_grouped_all',
									'label'         => '',
									'options'       => $options,
									'desc_tip'      => false,
									'description'   => '',
								);

								woocommerce_wp_radio(
									$size_status_args
								);
								?>

							</span>
							<span class="inpost_pl_row_item item_right" style="margin-bottom: 40px;">
								<?php
								$inpost_shipping_methods = EasyPack_Helper()->get_inpost_methods();
								?>
							<div class="inpost_pl_all_allowed_methods">
							<ul class="inpost_pl_all_allowed_methods_list">
							<?php
							foreach ( $inpost_shipping_methods as $key => $method ) {
								?>
								<li>
									<input type="checkbox"
											data-method="<?php echo esc_attr( $method['method_title_with_id'] ); ?>"
											id="<?php echo esc_attr( $method['method_title_with_id'] ); ?>"
											value=""
											class="checkbox inpost_pl_rule_checkbox"
									>
									<label for="<?php echo esc_attr( $method['method_title_with_id'] ); ?>">
										<?php echo esc_html( $method['user_title'] ); ?>
									</label>
								</li>
							<?php } ?>
							</ul>
							</div>
							</span>
						</div>
						<div class="tablenav top">
							<div class="alignleft actions bulkactions">
								<?php // $product_table->bulk_actions(); ?>
							</div>
							<div class="alignright actions">
								<input type="button" class="button button-primary button-large inpost-pl-update-product-bulk" value="<?php echo esc_html__( 'Update all products on page', 'inpost-for-woocommerce' ); ?>">
								<input type="button" class="button button-secondary button-large inpost-pl-update-reload" value="<?php echo esc_html__( 'Re-load page', 'inpost-for-woocommerce' ); ?>">
							</div>
						</div>
					</div>
				</div>

			<form method="get">
				<input type="hidden" name="page" value="<?php echo esc_attr( $_REQUEST['page'] ); ?>" />
				<?php
				$product_table->search_box( 'Szukaj produktów', 'search_id' );

				// Add product type dropdown.
				wc_product_dropdown_categories(
					array(
						'show_count'         => 0,
						'hierarchical'       => 1,
						'show_uncategorized' => 0,
						'name'               => 'product_cat',
						'selected'           => isset( $_GET['product_cat'] ) ? sanitize_text_field( $_GET['product_cat'] ) : '',
						'menu_order'         => false,
					)
				);

				// Add product type filter.
				$product_types = wc_get_product_types();
				echo '<select name="product_type" id="dropdown_product_type">';
				echo '<option value="">' . esc_html__( 'Filter by product type', 'inpost-for-woocommerce' ) . '</option>';
				foreach ( $product_types as $value => $label ) {
					echo '<option value="' . esc_attr( $value ) . '"';
					if ( isset( $_GET['product_type'] ) && $_GET['product_type'] === $value ) {
						echo ' selected="selected"';
					}
					echo '>' . esc_html( $label ) . '</option>';
				}
				echo '</select>';

				$product_qtys = array(
					'5'   => '5',
					'10'  => '10',
					'20'  => '20',
					'50'  => '50',
					'100' => '100',
					'200' => '200',
					'500' => '500',
				);
				
				// Get all shipping classes.
                $shipping_classes = array();
                if( is_object( WC()->shipping() ) ) {
                    $all_shipping_classes = WC()->shipping()->get_shipping_classes();
                    foreach ($all_shipping_classes as $shipping_class) {
                        $shipping_classes[$shipping_class->slug] = $shipping_class->name;
                    }
                }

                echo '<select name="product_shipping_class" id="dropdown_shipping_classes">';
                echo '<option value="">' . esc_html__( 'Per shipping class', 'inpost-for-woocommerce' ) . '</option>';
                if( ! empty( $shipping_classes ) && is_array( $shipping_classes ) ) {
                    foreach ( $shipping_classes as $value => $label ) {
                        echo '<option value="' . esc_attr($value) . '"';
                        if ( isset( $_GET['product_shipping_class'] ) && $_GET['product_shipping_class'] === $value ) {
                            echo ' selected="selected"';
                        }
                        echo '>' . esc_html( $label ) . '</option>';
                    }
                }
                echo '</select>';
				
				
				echo '<select name="per_page" id="dropdown_product_qty">';
				echo '<option value="">' . esc_html__( 'Per page', 'inpost-for-woocommerce' ) . '</option>';
				foreach ( $product_qtys as $value => $label ) {
					echo '<option value="' . esc_attr( $value ) . '"';
					if ( isset( $_GET['per_page'] ) && $_GET['per_page'] == $value ) {
						echo ' selected="selected"';
					}
					echo '>' . esc_html( $label ) . '</option>';
				}
				echo '</select>';

				submit_button( 'Filter', 'secondary', 'filter_action', false );
				?>

			</form>

			<form method="post" action="<?php echo esc_url( $current_rel_uri ); ?>">
				<?php
				$product_table->display();
				?>
				<div class="tablenav bottom">
					<div class="alignleft actions bulkactions">
					</div>
					<div class="alignleft actions">
						<input type="button" class="button button-primary button-large inpost-pl-update-product-bulk" value="<?php echo esc_html__( 'Update all products on page', 'inpost-for-woocommerce' ); ?>">
						<input type="button" class="button button-secondary button-large inpost-pl-update-reload" value="<?php echo esc_html__( 'Re-load page', 'inpost-for-woocommerce' ); ?>">
					</div>
				</div>
			</form>

		</div>
		<?php
	}
}

