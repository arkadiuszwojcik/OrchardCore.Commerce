<?php

namespace InspireLabs\WoocommerceInpost\shipx\services\shipment;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
} // Exit if accessed directly.

use InspireLabs\WoocommerceInpost\EasyPack;
use Exception;
use InspireLabs\WoocommerceInpost\shipx\models\shipment\ShipX_Shipment_Internal_Data;
use InspireLabs\WoocommerceInpost\shipx\models\shipment\ShipX_Shipment_Model;
use InspireLabs\WoocommerceInpost\shipx\models\shipment\ShipX_Shipment_Status_History_Item_Model;

/**
 * ShipX_Shipment_Status_Service
 */
class ShipX_Shipment_Status_Service {

	/**
	 * SHIPX_STATUSES_OPTION_KEY
	 */
	const SHIPX_STATUSES_OPTION_KEY = 'shipx_statuses';

	/**
	 * Shipment_service
	 *
	 * @var ShipX_Shipment_Service
	 */
	private $shipment_service;

	/**
	 * Statuses
	 *
	 * @var array
	 */
	private $statuses;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->shipment_service = EasyPack()->get_shipment_service();
		$statuses               = $this->get_statuses_from_db();
		if ( empty( $statuses ) ) {
			$this->synchronise_statuses();
		} else {
			$this->statuses = $statuses;
		}
	}

	/**
	 * Synchronise statuses
	 *
	 * @return void
	 */
	private function synchronise_statuses() {
		$statuses = $this->get_statuses_from_api();

		if ( is_array( $statuses ) && ! empty( $statuses ) ) {
			$this->statuses = $statuses['items'];
			$this->update_statuses();
		}
	}

	/**
	 * Get statuses from db
	 *
	 * @return array
	 */
	public function get_statuses_from_db() {
		return get_option( self::SHIPX_STATUSES_OPTION_KEY );
	}

	/**
	 * Get statuses key value
	 *
	 * @return array
	 */
	public function get_statuses_key_value() {
		$return = array(
			'any' => __( 'Any status', 'inpost-for-woocommerce' ),
		);

		$statuses = $this->get_statuses_from_db();
		if ( is_array( $statuses ) && ! empty( $statuses ) ) {
			foreach ( $this->get_statuses_from_db() as $item ) {
				$return[ $item['name'] ] = $item['name'];
			}
		}

		return $return;
	}

	/**
	 * Get statuses from api
	 *
	 * @return string
	 */
	private function get_statuses_from_api() {
		try {
			return EasyPack_API()->get_statuses();
		} catch ( Exception $exception ) {
			return null;
		}
	}

	/**
	 * Update statuses
	 *
	 * @return void
	 */
	private function update_statuses() {
		update_option( self::SHIPX_STATUSES_OPTION_KEY, $this->statuses );
	}

	/**
	 * Get status title
	 *
	 * @param string $search $search.
	 *
	 * @return string|null
	 */
	public function getStatusTitle( $search ) {
		if ( is_array( $this->statuses ) ) {
			foreach ( $this->statuses as $status ) {
				if ( $search === $status['name'] ) {
					return $status['title'];
				}
			}
		}

		return null;
	}

	/**
	 * Get status description
	 *
	 * @param $search $search.
	 *
	 * @return mixed|null
	 */
	public function getStatusDescription( $search ) {
		foreach ( $this->statuses as $status ) {
			if ( $search === $status['name'] ) {
				return $status['description'];
			}
		}

		return null;
	}

	/**
	 * Refresh status
	 *
	 * @param ShipX_Shipment_Model $shipment ShipX_Shipment_Model.
	 *
	 * @return array|null
	 */
	public function refreshStatus( ShipX_Shipment_Model $shipment ) {

		$search_results = array();

		try {
			$search_results = EasyPack_API()->customer_parcel_get_by_id( $shipment->getInternalData()->getInpostId() );
		} catch ( Exception $e ) {
			if ( function_exists( 'wc_get_logger' ) ) {
				$logger = wc_get_logger();
				$logger->log(
					'debug',
					'Inpost PL - refresh status API error',
					array(
						'source'        => 'InPost PL refresh status',
						'error_details' => array(
							'debug_place'   => __METHOD__ . ': ' . __LINE__,
							'error_type'    => 'Exception',
							'error_message' => $e->getMessage(),
						),
					)
				);
			}
			$search_results['items'] = array();
		}

		if ( empty( $search_results['id'] ) ) {
			$shipment->getInternalData()->setStatus( 'new' );
			$shipment->getInternalData()->setStatusChangedTimestamp( time() );

			$shipment->getInternalData()->setStatusTitle( __( 'Not created yet', 'inpost-for-woocommerce' ) );
			$shipment->getInternalData()->setStatusDescription( null );
		} else {
			$shipment->getInternalData()->setTrackingNumber( $search_results['tracking_number'] );

			$status_id = $search_results['status'];
			$shipment->getInternalData()->setStatus( $search_results['status'] );
			$shipment->getInternalData()->setStatusChangedTimestamp( time() );
			$shipment->getInternalData()->setStatusTitle( $this->getStatusTitle( $status_id ) );
			$shipment->getInternalData()->setStatusDescription( $this->getStatusDescription( $status_id ) );
		}

		$this->shipment_service->update_shipment_to_db( $shipment );

		return $search_results;
	}


	/**
	 * Format status history
	 *
	 * @param ShipX_Shipment_Internal_Data $internalData $internalData.
	 *
	 * @return string
	 */
	public function formatStatusHistory( ShipX_Shipment_Internal_Data $internalData ) {

		$return = '';
		foreach ( $internalData->get_status_history() as $item ) {
			$return .= $item->get_name() . ' ' . gmdate( 'd-m-Y H:i:s', (int) $item->get_timestamp() + 7200 );
			$return .= '<br>';
		}

			return $return;
	}
}
