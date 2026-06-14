<?php

namespace InspireLabs\WoocommerceInpost\admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly.

use Automattic\WooCommerce\Utilities\OrderUtil;
use InspireLabs\WoocommerceInpost\EasyPack;
use InspireLabs\WoocommerceInpost\EasyPack_Helper;
use InspireLabs\WoocommerceInpost\shipx\models\shipment\ShipX_Shipment_Model;
use InspireLabs\WoocommerceInpost\shipx\services\shipment\ShipX_Shipment_Status_Service;
use WP_List_Table;
use WP_Query;

/**
 * EasyPack Shipment Manager List Table
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! class_exists( 'EasyPack_Shipment_Manager_List_Table' ) ) :

	if ( ! class_exists( 'WP_List_Table' ) ) {
		require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
	}

	/**
	 * EasyPack_Shipment_Manager_List_Table
	 */
	class EasyPack_Shipment_Manager_List_Table extends WP_List_Table {

		protected $data           = array();
		protected $found_data     = array();
		public $custom_pagination = null;

		public function __construct( $send_method ) {
			parent::__construct();

			if ( 'yes' === get_option( 'woocommerce_custom_orders_table_enabled' ) ) {
				// HPOS usage is enabled.
				$this->get_data_wc_orders();

			} else {

				$this->get_data_post_orders();
			}
		}

		private function getActions( ShipX_Shipment_Model $shipment ) {
			if ( null === $shipment->getInternalData()->getTrackingNumber() ) {
				return '';
			}

			$return = sprintf(
				'<a href="#" target="_blank" data-id="%s" class="get_sticker_action">
                    <span 
                    title="%s" 
                    class="dashicons dashicons-media-spreadsheet%s"></span>
                    </a>',
				$shipment->getInternalData()->getOrderId(),
				__( 'Print sticker', 'inpost-for-woocommerce' ),
				'',
				''
			);

			$shipmentSrv = EasyPack()->get_shipment_service();
			if ( true === $shipmentSrv->is_courier_service( $shipment ) ) {
				// Return label is available only for courier services.
				$return .= sprintf(
					'<a href="#" target="_blank" data-id="%s" class="get_sticker_return_action">
                    <span 
                    title="%s" 
                    class="dashicons dashicons-media-spreadsheet%s"></span>
                    </a>',
					$shipment->getInternalData()->getOrderId(),
					__( 'Print return sticker', 'inpost-for-woocommerce' ),
					'',
					''
				);
			}

			$return .= sprintf(
				'<br><a href="#" data-target="status_history_modal_%s" data-toggle="modal">Historia statusów</a>
                        <div id="status_history_modal_%s" class="modal">
                          <div class="modal-window small">
                              <p>%s</p>
                          </div>
                        </div>',
				$shipment->getInternalData()->getInpostId(),
				$shipment->getInternalData()->getInpostId(),
				EasyPack()->get_shipment_status_service()->formatStatusHistory( $shipment->getInternalData() )
			);

			return $return;
		}

		/**
		 * @param ShipX_Shipment_Model $shipment
		 *
		 * @return bool
		 */
		private function isShipmentMatchedToFilters( ShipX_Shipment_Model $shipment ) {
			$send_method = EasyPack_Shipment_Manager::getSendingMethodFilterFromRequest();

			if ( ( null !== $send_method && 'any' !== $send_method )
			     && $send_method !== $shipment->getCustomAttributes()->getSendingMethod()
			) {

				return false;
			}

			$status = EasyPack_Shipment_Manager::getStatusFilterFromRequest();

			if ( ( null !== $status && 'any' !== $status )
			     && $status !== $shipment->getInternalData()->getStatus()
			) {
				return false;
			}

			$service = EasyPack_Shipment_Manager::getServiceFilterFromRequest();

			if ( ( null !== $service && 'any' !== $service )
			     && $service !== $shipment->getService()
			) {
				return false;
			}

			$tracking_number = EasyPack_Shipment_Manager::getTrackingNumberFilterFromRequest();

			if ( null !== $tracking_number
			     && $tracking_number !== $shipment->getInternalData()->getTrackingNumber()
			) {
				return false;
			}

			$order_id = EasyPack_Shipment_Manager::getOrderIdFilterFromRequest();

			if ( null !== $order_id
			     && $order_id !== $shipment->getInternalData()->getOrderId()
			) {
				return false;
			}

			$reference_number = EasyPack_Shipment_Manager::getReferenceNumberFilterFromRequest();

			if ( null !== $reference_number
			     && (string) $reference_number
			        !== (string) $shipment->getReference()
			) {
				return false;
			}

			$receiver_email = EasyPack_Shipment_Manager::getReceiverEmailFilterFromRequest();

			if ( null !== $receiver_email
			     && $receiver_email !== $shipment->getReceiver()->getEmail()
			) {
				return false;
			}

			$receiver_phone = EasyPack_Shipment_Manager::getReceiverPhoneFilterFromRequest();

			if ( null !== $receiver_phone
			     && $receiver_phone !== $shipment->getReceiver()->getPhone()
			) {
				return false;
			}

			return true;
		}


		/**
		 * @param string $method
		 *
		 * @return string
		 */
		private function translateSendingMethod( $method ) {
			switch ( $method ) {
				case ShipX_Shipment_Model::SENDING_METHOD_PARCEL_LOCKER	=== $method:
					return esc_html__( 'Parcel Locker', 'inpost-for-woocommerce' );

				case ShipX_Shipment_Model::SENDING_METHOD_DISPATCH_ORDER === $method:
					return esc_html__( 'Courier', 'inpost-for-woocommerce' );

				case ShipX_Shipment_Model::SENDING_METHOD_POP === $method:
					return esc_html__( 'POP', 'inpost-for-woocommerce' );
				default:
					return '';
			}


		}

		public function column_cb( $item ) {
			/**
			 * @var ShipX_Shipment_Model $shipment
			 */
			$shipment = $item['shipment'];

			if ( is_object( $shipment ) ) {

				$srv = EasyPack()->get_shipment_service();

				return sprintf(
					'<input 
							data-allow_return_stickers="%s"
                            data-status="%s"
                            class="easypack_parcel" 
                            type="checkbox" 
                            name="easypack_parcel[]" 
                            value="%s" />',
					// $shipment->isCourier() ? '0' : '1',
					$srv->is_courier_service( $shipment ) ? '1' : '0',
					$shipment->getInternalData()->getStatus(),
					$item['order_id']
				);
			}
		}

		public function column_order( $item ) {
			$link  = '<a href="' . admin_url( 'post.php?post=' . $item['order'] . '&action=edit' ) . '" >';
			$link .= '#' . $item['order'];
			$link .= '</a>';

			$additional_packages_notice = '';

			$existed_additional_packages = EasyPack_Helper()->get_saved_additional_packages( $item['order'] );
			if ( is_array( $existed_additional_packages ) && ! empty( $existed_additional_packages ) ) {
				$additional_packages_notice .= esc_html__( 'An additional packages exists', 'inpost-for-woocommerce' );
				$additional_packages_notice .= ' (' . esc_attr( count( $existed_additional_packages ) ) . ')';
			}

			$link .= '<br>';
			$link .= $additional_packages_notice;

			return $link;
		}

		/**
		 * @param array  $item
		 * @param string $column_name
		 *
		 * @return mixed|void
		 */
		public function column_default( $item, $column_name ) {
			switch ( $column_name ) {
				case 'package_number':
				case 'send_method':
				case 'send_method_display':
				case 'status':
				case 'dispatch_order_status':
				case 'dispatch_point_name':
				case 'order':
				case 'shipping_address':
				case 'service':
				case 'attributes':
				case 'cod':
				case 'reference_number':
				case 'inpost_id':
				case 'actions':
				case 'created_timestamp':
				case 'status_timestamp':
					return $item[ $column_name ];
				default:
					print_r(
						$item,
						true
					); // Show the whole array for troubleshooting purposes.
			}
		}

		/**
		 * @return array
		 */
		public function get_columns() {
			$columns = array(
				'cb'                  => '<input type="checkbox" />',
				'package_number'      => __( 'Tracking number', 'inpost-for-woocommerce' ),
				'order'               => __( 'Order ID', 'inpost-for-woocommerce' ),
				'inpost_id'           => __( 'Inpost ID', 'inpost-for-woocommerce' ),
				'created_timestamp'   => __( 'Date created', 'inpost-for-woocommerce' ),
				'reference_number'    => __( 'Reference number', 'inpost-for-woocommerce' ),
				'status'              => __( 'Status', 'inpost-for-woocommerce' )
				                         . $this->get_refresh_statuses_btn(),
				'status_timestamp'    => __( 'Status change date', 'inpost-for-woocommerce' ),
				'service'             => __( 'Service', 'inpost-for-woocommerce' ),
				'attributes'          => __( 'Dimensions', 'inpost-for-woocommerce' ),
				'cod'                 => __( 'COD', 'inpost-for-woocommerce' ),
				'send_method_display' => __( 'Send method', 'inpost-for-woocommerce' ),
				'shipping_address'    => __( 'Shipping address', 'inpost-for-woocommerce' ),

			);

			if ( EasyPack_Shipment_Manager::is_courier_context() ) {
				$columns['dispatch_order_status'] = __( 'Dispatch order status', 'inpost-for-woocommerce' );
				$columns['dispatch_point_name']   = __( 'Dispatch point name', 'inpost-for-woocommerce' );
			}

			$columns['actions'] = __( 'Actions', 'inpost-for-woocommerce' );

			return $columns;
		}

		private function get_refresh_statuses_btn() {
			return '<a title="' . __( 'Refresh statuses now', 'inpost-for-woocommerce' ) . '" 
                id="refresh_statuses_btn"
                name="refresh_statuses"
                href="#">
                <span style="font-size: 10px" class="dashicons dashicons-image-rotate"></span>
                </a>';
		}

		private function get_tracking_number_link( ShipX_Shipment_Model $shipment ) {
			$srv                                       = EasyPack()->get_shipment_service();
			$tracking_number                           = $data['package_number']
				= $shipment->getInternalData()->getTrackingNumber();

			if ( null !== $tracking_number ) {
				return sprintf(
					'<a target="_blank" href="%s">%s</a>',
					$srv->getTrackingUrl( $shipment ),
					$tracking_number
				);
			}

			return '';
		}

		public function get_hidden_columns() {
			return array();
		}

		public function prepare_items() {

			$columns               = $this->get_columns();
			$hidden                = $this->get_hidden_columns();
			$sortable              = $this->get_sortable_columns();
			$this->_column_headers = array( $columns, $hidden, $sortable );

			$per_page     = 5000000;
			$current_page = $this->get_pagenum();
			$total_items  = count( $this->data );

			$this->found_data = $this->data;

			$this->set_pagination_args(
				array(
					'total_items' => $total_items,
					'per_page'    => $per_page,
				)
			);

			$this->items = $this->data;
		}


		public function get_data_post_orders() {
			global $post;


			$posts_per_page = 150;
			$paged          = isset( $_GET['shipments_page'] ) ? sanitize_text_field( $_GET['shipments_page'] ) : 1;


			$args = array(
				'post_type'      => 'shop_order',
				'post_status'    => 'any',
				'posts_per_page' => $posts_per_page,
				'paged'          => $paged,
				'meta_query'     => array(
					array(
						'key'     => '_easypack_status',
						'value'   => array(
							'prepared',
							'created',
							'ReadyToBeSent',
						),
						'compare' => 'IN',
					),
					array(
						'key'     => '_easypack_dispatched',
						'value'   => '',
						'compare' => 'NOT EXISTS',
					),
				),
			);

			$query = new WP_Query( $args );
			while ( $query->have_posts() ) {
				$query->the_post();
				if ( 'wc-cancelled' === $post->post_status ) {
					/* skip cancelled orders */
					continue;
				}

				$order    = wc_get_order( $post->ID );
				$order_id = $order->get_id();


				$shipment_service = EasyPack::EasyPack()->get_shipment_service();
				$pickup_service   = EasyPack::EasyPack()->get_courier_pickup_service();
				$status_service   = EasyPack::EasyPack()->get_shipment_status_service();
				$shipment         = $shipment_service->get_shipment_by_order_id( $order_id );

				if ( false === $shipment instanceof ShipX_Shipment_Model ) {
					continue;
				}

				// prevent api versions conflicts
				if ( false === $shipment_service->is_shipment_match_to_current_api( $shipment ) ) {
					continue;
				}

				if ( isset( $_POST['refresh_statuses'] ) && '1' === $_POST['refresh_statuses'] ) {
					$status_service->refreshStatus( $shipment );
				}

				if ( false === $this->isShipmentMatchedToFilters( $shipment ) ) {
					continue;
				}



				$easypack_parcels = $shipment->getParcels();

				if ( $easypack_parcels ) {
					$easypack_parcels_uniq = array();

					foreach($easypack_parcels as $key=> $parcel) {
						if($key === 0) {
							$easypack_parcels_uniq[$key] = $parcel;
						}
					}

					foreach ( $easypack_parcels_uniq as $key => $parcel ) {

						$data                   = array();
						$data['package_number'] = $this->get_tracking_number_link( $shipment );

						$data['parcel']      = $parcel;
						$data['send_method'] = $shipment->getCustomAttributes()->getSendingMethod();

						$data['service'] = $shipment_service->get_customer_service_name( $shipment );

						if ( $shipment->getInternalData()->getWeekend() ) {
							$data['service'] = __( 'Parcel locker Weekend', 'inpost-for-woocommerce' );
						}

						$data['attributes'] = Easypack_Helper()->convert_size_to_symbol( $shipment_service->get_table_attributes( $shipment ) );

						$data['cod'] = null === $shipment->getCod()
							? __( 'No', 'inpost-for-woocommerce' )
							: __( 'Yes', 'inpost-for-woocommerce' );

						$data['actions'] = $this->getActions( $shipment );

						$data['send_method_display'] = $this->translateSendingMethod(
							$shipment->getCustomAttributes()->getSendingMethod()
						);

						$current_inpost_api_status = $shipment->getInternalData()->getStatus();

						$maybe_update_statuses = array(
							'created',
							'offers_prepared',
							'offer_selected',
						);
						if( in_array( $current_inpost_api_status, $maybe_update_statuses, true ) ) {
							$status_service->refreshStatus( $shipment );
							$current_inpost_api_status = $shipment->getInternalData()->getStatus();
						}

						$data['status'] = $shipment->getInternalData()->getStatusTitle() . ' (' . $current_inpost_api_status . ')';
						/*
						$data['status_timestamp'] =gmdate( 'd-m-Y H:i:s',
							$shipment
								->getInternalData()
								->getStatusChangedTimestamp() );
						*/

						if ( is_numeric( $shipment->getInternalData()->getStatusChangedTimestamp() )
						     && (int) $shipment->getInternalData()->getStatusChangedTimestamp() == $shipment->getInternalData()->getStatusChangedTimestamp() ) {
							$data['status_timestamp'] =gmdate( 'd-m-Y H:i:s', (int) $shipment->getInternalData()->getStatusChangedTimestamp() + 7200 );
						} else {
							$data['status_timestamp'] =gmdate( 'd-m-Y H:i:s', (int) strtotime( $shipment->getInternalData()->getStatusChangedTimestamp() ) + 7200 );
						}

						/*
						$data['created_timestamp'] =gmdate( 'd-m-Y H:i:s',
							(int) $shipment
								->getInternalData()->getCreatedAt() );
						*/

						if ( is_numeric( $shipment->getInternalData()->getCreatedAt() )
						     && (int) $shipment->getInternalData()->getCreatedAt() == $shipment->getInternalData()->getCreatedAt() ) {
							$data['created_timestamp'] = gmdate( 'd-m-Y H:i:s', (int) $shipment->getInternalData()->getCreatedAt() + 7200 );
						} else {
							$data['created_timestamp'] = gmdate( 'd-m-Y H:i:s', (int) strtotime( $shipment->getInternalData()->getCreatedAt() ) + 7200 );
						}

						if ( EasyPack_Shipment_Manager::is_courier_context() ) {

							$dispatch_status = $shipment->getInternalData()
							                            ->getDispatchStatus();
							if ( null === $dispatch_status ) {
								$data['dispatch_point_name']   = '-';
								$data['dispatch_order_status'] = '-';
							} else {
								$data['dispatch_point_name'] = $pickup_service->getDispatchPointStr(
									$shipment->getInternalData()
									         ->getDispatchStatus()
									         ->getDispathOrderPointName()
								);

								$data['dispatch_order_status'] = $pickup_service->get_dispatch_order_status_string( $dispatch_status->getDispathOrderStatus() );
							}
						}

						$data['order']            = $order_id;
						$data['shipping_address']
						                          = $order->get_formatted_shipping_address();
						$data['reference_number'] = $shipment->getReference();
						$data['inpost_id']        = $shipment->getInternalData()->getInpostId();

						if ( null !== $shipment->getCustomAttributes()->getTargetPoint() ) {
							$data['shipping_address'] = __( 'Parcel Locker ', 'inpost-for-woocommerce' )
							                            . ' '
							                            . $shipment->getCustomAttributes()->getTargetPoint();
						}

						$data['parcel_id'] = $parcel->getId();
						$data['order_id']  = $order_id;
						$data['api']       = 'easypack';

						$data['shipment'] = $shipment;
						$this->data[]     = $data;
					}
				}
			}

			$total_pages = $query->max_num_pages;
			if ( $total_pages > 1 ) {
				$this->custom_pagination = $total_pages;
			}

			wp_reset_postdata();
		}


		public function get_data_wc_orders() {

			$posts_per_page = 150;
			$paged          = isset( $_GET['shipments_page'] ) ? sanitize_text_field( $_GET['shipments_page'] ) : 1;

			$args = array(
				'limit'      => $posts_per_page,
				'paged'      => $paged,
				'status'     => 'any',
				'meta_query' => array(
					array(
						'key'     => '_easypack_status',
						'value'   => array( 'prepared', 'created', 'ReadyToBeSent' ),
						'compare' => 'IN',
					),
					array(
						'key'     => '_easypack_dispatched',
						'compare' => 'NOT EXISTS',
					),
				),
				'orderby'    => 'date',
				'order'      => 'DESC',
				'return'     => 'ids',
			);

			$post_ids = wc_get_orders( $args );

			$total_orders = wc_get_orders(
				array_merge(
					$args,
					array(
						'limit'  => -1,
						'return' => 'ids',
					)
				)
			);

			$total_pages = ceil( count( $total_orders ) / $posts_per_page );

			if ( $total_pages > count( $post_ids ) ) {
				$this->custom_pagination = $total_pages;
			}

			if ( is_array( $post_ids ) && ! empty( $post_ids ) ) {

				foreach ( $post_ids as $order_id ) {

					$order = wc_get_order( $order_id );

					if ( ! $order || is_wp_error( $order ) ) {
						continue;
					}

					if ( $order->get_status() === 'cancelled' ) {
						/* skip cancelled orders */
						continue;
					}

					$shipment_service = EasyPack::EasyPack()->get_shipment_service();
					$pickup_service   = EasyPack::EasyPack()->get_courier_pickup_service();
					$status_service   = EasyPack::EasyPack()->get_shipment_status_service();
					$shipment         = $shipment_service->get_shipment_by_order_id( $order_id );

					if ( false === $shipment instanceof ShipX_Shipment_Model ) {
						continue;
					}

					// prevent api versions conflicts.
					if ( false === $shipment_service->is_shipment_match_to_current_api( $shipment ) ) {
						continue;
					}

					if ( isset( $_POST['refresh_statuses'] ) && '1' === $_POST['refresh_statuses'] ) {
						$status_service->refreshStatus( $shipment );
					}

					if ( false === $this->isShipmentMatchedToFilters( $shipment ) ) {
						continue;
					}

					$easypack_parcels = $shipment->getParcels();

					if ( $easypack_parcels ) {

						$easypack_parcels_uniq = array();

						foreach($easypack_parcels as $key => $parcel) {
							if($key === 0) {
								$easypack_parcels_uniq[$key] = $parcel;
							}
						}

						foreach ( $easypack_parcels_uniq as $key => $parcel ) {

							$data                   = array();
							$data['package_number'] = $this->get_tracking_number_link( $shipment );

							$data['parcel']      = $parcel;
							$data['send_method'] = $shipment->getCustomAttributes()->getSendingMethod();

							$data['service'] = $shipment_service->get_customer_service_name( $shipment );

							if ( $shipment->getInternalData()->getWeekend() ) {
								$data['service'] = __( 'Parcel locker Weekend', 'inpost-for-woocommerce' );
							}

							$data['attributes'] = Easypack_Helper()->convert_size_to_symbol( $shipment_service->get_table_attributes( $shipment ) );

							$data['cod'] = null === $shipment->getCod()
								? __( 'No', 'inpost-for-woocommerce' )
								: __( 'Yes', 'inpost-for-woocommerce' );

							$data['actions'] = $this->getActions( $shipment );

							$data['send_method_display'] = $this->translateSendingMethod(
								$shipment->getCustomAttributes()->getSendingMethod()
							);

							$current_inpost_api_status = $shipment->getInternalData()->getStatus();

							$maybe_update_statuses = array(
								'created',
								'offers_prepared',
								'offer_selected',
							);
							if( in_array( $current_inpost_api_status, $maybe_update_statuses, true ) ) {
								$status_service->refreshStatus( $shipment );
								$current_inpost_api_status = $shipment->getInternalData()->getStatus();
							}

							$data['status'] = $shipment->getInternalData()->getStatusTitle() . ' (' . $current_inpost_api_status . ')';

							if ( is_numeric( $shipment->getInternalData()->getStatusChangedTimestamp() )
							     && (int) $shipment->getInternalData()->getStatusChangedTimestamp() == $shipment->getInternalData()->getStatusChangedTimestamp() ) {
								$data['status_timestamp'] = gmdate( 'd-m-Y H:i:s', (int) $shipment->getInternalData()->getStatusChangedTimestamp() + 7200 );
							} else {
								$data['status_timestamp'] = gmdate( 'd-m-Y H:i:s', (int) strtotime( $shipment->getInternalData()->getStatusChangedTimestamp() ) + 7200 );
							}

							if ( is_numeric( $shipment->getInternalData()->getCreatedAt() )
							     && (int) $shipment->getInternalData()->getCreatedAt() == $shipment->getInternalData()->getCreatedAt() ) {
								$data['created_timestamp'] = gmdate( 'd-m-Y H:i:s', (int) $shipment->getInternalData()->getCreatedAt() + 7200 );
							} else {
								$data['created_timestamp'] = gmdate( 'd-m-Y H:i:s', (int) strtotime( $shipment->getInternalData()->getCreatedAt() ) + 7200 );
							}

							if ( EasyPack_Shipment_Manager::is_courier_context() ) {

								$dispatch_status = $shipment->getInternalData()
								                            ->getDispatchStatus();
								if ( null === $dispatch_status ) {
									$data['dispatch_point_name']   = '-';
									$data['dispatch_order_status'] = '-';
								} else {
									$data['dispatch_point_name'] = $pickup_service->getDispatchPointStr(
										$shipment->getInternalData()
										         ->getDispatchStatus()
										         ->getDispathOrderPointName()
									);

									$data['dispatch_order_status'] = $pickup_service->get_dispatch_order_status_string( $dispatch_status->getDispathOrderStatus() );
								}
							}

							$data['order']            = $order_id;
							$data['shipping_address']
							                          = $order->get_formatted_shipping_address();
							$data['reference_number'] = $shipment->getReference();
							$data['inpost_id']        = $shipment->getInternalData()->getInpostId();

							if ( null !== $shipment->getCustomAttributes()->getTargetPoint() ) {
								$data['shipping_address'] = __( 'Parcel Locker ', 'inpost-for-woocommerce' )
								                            . ' '
								                            . $shipment->getCustomAttributes()->getTargetPoint();
							}

							$data['parcel_id'] = $parcel->getId();
							$data['order_id']  = $order_id;
							$data['api']       = 'easypack';

							$data['shipment'] = $shipment;
							$this->data[]     = $data;
						}
					}
				}
			}
		}
	}

endif;
