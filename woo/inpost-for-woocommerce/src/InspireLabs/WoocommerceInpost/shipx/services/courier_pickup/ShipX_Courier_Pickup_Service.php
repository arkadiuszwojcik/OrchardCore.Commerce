<?php

namespace InspireLabs\WoocommerceInpost\shipx\services\courier_pickup;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly.

use Exception;
use InspireLabs\WoocommerceInpost\admin\Alerts;
use InspireLabs\WoocommerceInpost\EasyPack;
use InspireLabs\WoocommerceInpost\shipx\models\courier_pickup\ShipX_Dispatch_Order_Model;
use InspireLabs\WoocommerceInpost\shipx\models\courier_pickup\ShipX_Dispatch_Order_Point_Address_Model;
use InspireLabs\WoocommerceInpost\shipx\models\courier_pickup\ShipX_Dispatch_Order_Point_Model;
use InspireLabs\WoocommerceInpost\shipx\models\courier_pickup\ShipX_Dispatch_Order_Shipment_Model;
use InspireLabs\WoocommerceInpost\shipx\models\shipment\ShipX_Shipment_Dispatch_Status;
use InspireLabs\WoocommerceInpost\shipx\models\shipment\ShipX_Shipment_Model;


class ShipX_Courier_Pickup_Service {

	/**
	 * @return array
	 * @throws Exception
	 */
	public function getDispatchPointsStrArray(): array {
		$return  = array();
		$dpoints = (array) get_option( EasyPack::ATTRIBUTE_PREFIX . '_dpoint' );

		if ( empty( $dpoints['street'] ) ) {
			$alerts = new Alerts();
			$alerts->add_error(
				'InPost PL: ' . esc_html__( 'No dispatch points or wrong configuration. Add dispatch points on the InPost options page.', 'inpost-for-woocommerce' )
			);
			$alerts->print_alerts();

			return array();
		}

		if ( isset( $dpoints['street'] ) && is_array( $dpoints['street'] ) ) {
			foreach ( $dpoints['street'] as $i => $v ) {

				if ( ! isset( $dpoints['point_name'][ $i ] ) ) {
					$this->dpoint_error(
						esc_html__( 'point name', 'inpost-for-woocommerce' ),
						$i
					);
					$point_name = '';
				} else {
					$point_name = $dpoints['point_name'][ $i ];
				}

				if ( ! isset( $dpoints['postal_code'][ $i ] ) ) {
					$this->dpoint_error(
						esc_html__( 'postal code', 'inpost-for-woocommerce' ),
						$i
					);
					$postal_code = '';
				} else {
					$postal_code = $dpoints['postal_code'][ $i ];
				}

				if ( ! isset( $dpoints['building_number'][ $i ] ) ) {
					$this->dpoint_error( esc_html__( 'building number', 'inpost-for-woocommerce' ), $i );
					$building_number = '';
				} else {
					$building_number = $dpoints['building_number'][ $i ];
				}

				if ( ! isset( $dpoints['city'][ $i ] ) ) {
					$this->dpoint_error( esc_html__( 'city', 'inpost-for-woocommerce' ), $i );
					$city = '';
				} else {
					$city = $dpoints['city'][ $i ];
				}

				$return[] = $point_name . ': ' . $dpoints['street'][ $i ]
							. ' ' . $building_number
							. ' ' . $postal_code
							. ' ' . $city;
			}
		}

		return $return;
	}

	private function dpoint_error( $name, $key ) {
		$alerts = new Alerts();
		$alerts->add_error(
			'InPost PL: '
			. sprintf(
			/* translators: %1$s: Field name that is missing, %2$s: Pickup point number */
				__( 'The configuration of the dispatch points is incomplete. The field %1$s is missing at pickup point #%2$s', 'inpost-for-woocommerce' ),
				$name,
				$key + 1
			)
		);
		$alerts->print_alerts();
	}

	/**
	 * @param string|array|null $dpoint
	 *
	 * @return string
	 */
	public function getDispatchPointStr( $dpoint = null ): string {
		return is_array( $dpoint ) ? $dpoint['street']
									. ' ' . $dpoint['building_number']
									. '<br>' . $dpoint['post_code']
									. ' ' . $dpoint['city'] : (string) $dpoint;
	}

	/**
	 * @param int $pointId
	 *
	 * @return array
	 */
	public function getDispatchPoint( int $pointId ): array {

		$return  = array();
		$dpoints = (array) get_option( EasyPack::ATTRIBUTE_PREFIX . '_dpoint' );

		$return['point_name']          = ! empty( $dpoints['point_name'][ $pointId ] ) ? $dpoints['point_name'][ $pointId ] : '';
		$return['street']          = ! empty( $dpoints['street'][ $pointId ] ) ? $dpoints['street'][ $pointId ] : '';
		$return['building_number'] = ! empty( $dpoints['building_number'][ $pointId ] ) ? $dpoints['building_number'][ $pointId ] : '';
		$return['post_code']       = ! empty( $dpoints['postal_code'][ $pointId ] ) ? $dpoints['postal_code'][ $pointId ] : '';
		$return['city']            = ! empty( $dpoints['city'][ $pointId ] ) ? $dpoints['city'][ $pointId ] : '';
		$return['country_code']    = 'PL';
		$return['id']              = md5(
			$dpoints['street'][ $pointId ]
											. $dpoints['building_number'][ $pointId ]
											. $dpoints['postal_code'][ $pointId ]
											. $dpoints['city'][ $pointId ]
		);

		return $return;
	}


	/**
	 * @param ShipX_Dispatch_Order_Model $dispath_order
	 */
	public function createDispatchOrderPost( $dispath_order ) {
		$post               = array(
			'post_type'   => 'dispatch_order',
			'post_status' => 'publish',
		);
		$post['post_title'] = strtoupper( EasyPack_API()->getCountry() )
								. ' - '
								. date_i18n(
									get_option( 'date_format' )
											. ' ' . get_option( 'time_format' ),
									current_time( 'timestamp' )
								);
		$post_id            = wp_insert_post( $post );
		$post               = get_post( $post_id );
		$post->post_title   = $post_id . ' - ' . $post->post_title;
		wp_update_post( $post );
		update_post_meta( $post_id, '_dispath_order', $dispath_order );
	}

	/**
	 * @param int $post_id
	 *
	 * @return ShipX_Dispatch_Order_Model
	 */
	public function get_dispatch_order( $post_id ) {
		$ret = get_post_meta( $post_id, '_dispath_order' );
		if ( ! isset( $ret[0] ) ) {
			return null;
		}

		return $ret[0];
	}


	/**
	 * @param int $id
	 *
	 * @return ShipX_Dispatch_Order_Model
	 */
	public function getDispatchOrderById( $id ) {
		$orders = get_option( 'easypack_dispatch_orders' );

		return $orders[ $id ];
	}

	/**
	 * @param $id
	 *
	 * @return ShipX_Dispatch_Order_Point_Model
	 * @throws Exception
	 */
	public function getDispatchPointById( $id ) {
		$point = EasyPack_API()->dispatch_point( $id );

		$pointModel = new ShipX_Dispatch_Order_Point_Model();

		$pointModel->setHref( $point['href'] );
		$pointModel->setId( $point['id'] );
		$pointModel->setName( $point['name'] );
		$pointModel->setOfficeHours( $point['office_hours'] );
		$pointModel->setPhone( $point['phone'] );
		$pointModel->setEmail( $point['email'] );
		$pointModel->setComments( $point['comments'] );
		$pointModel->setStatus( $point['comments'] );

		switch ( $point['status'] ) {
			case 'created':
				$pointModel->setStatus( $pointModel::STATUS_CREATED );
				break;
			case 'activated':
				$pointModel->setStatus( $pointModel::STATUS_ACTIVATED );
				break;
			case 'suspended':
				$pointModel->setStatus( $pointModel::STATUS_SUSPENDED );
				break;
		}

		$address = new ShipX_Dispatch_Order_Point_Address_Model();
		$address->setId( $point['address']['id'] );
		$address->setStreet( $point['address']['street'] );
		$address->setBuildingNumber( $point['address']['building_number'] );
		$address->setCity( $point['address']['city'] );
		$address->setPostCode( $point['address']['post_code'] );
		$address->setCountryCode( $point['address']['country_code'] );
		$pointModel->setAddress( $address );

		return $pointModel;
	}

	/**
	 * @param array $dispatch_point_address
	 * @param ShipX_Shipment_Model[] $shipments
	 *
	 * @throws Exception
	 */
	public function createDispatchOrder(
		array $dispatch_point_address,
		$shipments
	) {

		$api = EasyPack_API();

		$shipmentsToDispath = array();
		foreach ( $shipments as $k => $shipment ) {
			$shipmentsToDispath[] = $shipment->getInternalData()->getInpostId();
		}

		$point_name = '';

		if( isset( $dispatch_point_address['point_name'] ) ) {
			$point_name = $dispatch_point_address['point_name'];
			unset( $dispatch_point_address['point_name'] );
		}

		if( isset( $dispatch_point_address['id'] ) ) {
			unset( $dispatch_point_address['id'] );
		}

		$data_to_api = array(
			'address'   => $dispatch_point_address,
			'shipments' => $shipmentsToDispath,
			'name' => $point_name,
			'phone' => get_option( 'easypack_sender_phone', '' ),
			'email' => get_option( 'easypack_sender_email', '' ),
		);

		if ( function_exists( 'wc_get_logger' ) ) {
			\wc_get_logger()->debug( 'DATA to API', array( 'source' => 'inpost-pl-dispatch_order' ) );
			\wc_get_logger()->debug( print_r( $data_to_api, true ), array( 'source' => 'inpost-pl-dispatch_order' ) );
		}

		//die();

		$result = $api->dispatch_order( $data_to_api );

		if ( function_exists( 'wc_get_logger' ) ) {
			\wc_get_logger()->debug( 'API result', array( 'source' => 'inpost-pl-dispatch_order' ) );
			\wc_get_logger()->debug( print_r( $result, true ), array( 'source' => 'inpost-pl-dispatch_order' ) );
		}

		$dispatch_order        = $this->mapDispathOrder( $result );
		$dispatch_order_status = new ShipX_Shipment_Dispatch_Status();

		$dispatch_order_status->setDispathOrderPointName( $dispatch_point_address );
		$dispatch_order_status->setDispathOrderId( $dispatch_order->getId() );
		$dispatch_order_status->setDispathOrderPointId(	$dispatch_order->getAddress()->getId() );
		$dispatch_order_status->setDispathOrderStatus( $dispatch_order->getStatus() );

		$shipment_service = EasyPack()->get_shipment_service();

		foreach ( $shipments as $shipment ) {
			$internal_data = $shipment->getInternalData();
			$internal_data->setDispatchStatus( $dispatch_order_status );
			$shipment->setInternalData( $internal_data );
			$shipment_service->update_shipment_to_db( $shipment );
		}

		$this->createDispatchOrderPost( $dispatch_order );
	}


	/**
	 * @param array $dispatch_order_response
	 *
	 * @return ShipX_Dispatch_Order_Model
	 */
	private function mapDispathOrder( $dispatch_order_response ) {
		$dispatch_order = new ShipX_Dispatch_Order_Model();
		$dispatch_order->setId( $dispatch_order_response['id'] );
		$dispatch_order->setHref( $dispatch_order_response['href'] );
		$dispatch_order->setStatus(
			$this->get_dispath_order_status_int( $dispatch_order_response['status'] )
		);
		$dispatch_order->setCreatedAt( $dispatch_order_response['created_at'] );
		$address = new ShipX_Dispatch_Order_Point_Address_Model();

		$address->setId( $dispatch_order_response['address']['id'] );
		$address->setBuildingNumber( $dispatch_order_response['address']['building_number'] );
		$address->setPostCode( $dispatch_order_response['address']['post_code'] );
		$address->setCity( $dispatch_order_response['address']['post_code'] );
		$address->setCountryCode( $dispatch_order_response['address']['country_code'] );
		$address->setStreet( $dispatch_order_response['address']['street'] );
		$dispatch_order->setAddress( $address );
		$shipments = array();
		foreach ( $dispatch_order_response['shipments'] as $shipment ) {
			$shipment_model = new ShipX_Dispatch_Order_Shipment_Model();
			$shipment_model->setId( $shipment['id'] );
			$shipment_model->setHref( $shipment['href'] );
			$shipment_model->setTrackingNumber( $shipment['tracking_number'] );
			$shipments[] = $shipment_model;
		}
		$dispatch_order->setShipments( $shipments );

		return $dispatch_order;
	}

	/**
	 * @param string $status
	 *
	 * @return int
	 */
	private function get_dispath_order_status_int( $status ) {
		switch ( $status ) {
			case 'sent':
				return ShipX_Dispatch_Order_Model::STATUS_SENT;

			case 'new':
				return ( ShipX_Dispatch_Order_Model::STATUS_NEW );

			case 'accepted':
				return ( ShipX_Dispatch_Order_Model::STATUS_ACCEPTED );

			case 'done':
				return ( ShipX_Dispatch_Order_Model::STATUS_DONE );

			case 'rejected':
				return ( ShipX_Dispatch_Order_Model::STATUS_REJECTED );

			case 'canceled':
				return ( ShipX_Dispatch_Order_Model::STATUS_CANCELED );
		}
	}

	/**
	 * @param int $status
	 *
	 * @return string
	 */
	public function get_dispatch_order_status_string( $status ) {
		switch ( $status ) {
			case ShipX_Dispatch_Order_Model::STATUS_SENT:
				return 'sent';

			case ShipX_Dispatch_Order_Model::STATUS_NEW:
				return 'new';

			case ShipX_Dispatch_Order_Model::STATUS_ACCEPTED:
				return 'accepted';

			case ShipX_Dispatch_Order_Model::STATUS_DONE:
				return 'done';

			case ShipX_Dispatch_Order_Model::STATUS_REJECTED:
				return 'rejected';

			case ShipX_Dispatch_Order_Model::STATUS_CANCELED:
				return 'canceled';
		}
	}
}
