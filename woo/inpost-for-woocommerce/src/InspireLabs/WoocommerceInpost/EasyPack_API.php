<?php

namespace InspireLabs\WoocommerceInpost;

use Exception;
use InspireLabs\WoocommerceInpost\admin\Alerts;
use InspireLabs\WoocommerceInpost\shipx\models\courier_pickup\ShipX_Dispatch_Order_Point_Address_Model;
use InspireLabs\WoocommerceInpost\shipx\models\courier_pickup\ShipX_Dispatch_Order_Point_Model;

/**
 * EasyPack API
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'InspireLabs\WoocommerceInpost\EasyPack_API' ) ) :

	class EasyPack_API {

		const API_URL_PRODUCTION_PL = 'https://api-shipx-pl.easypack24.net';

		const API_URL_SANDBOX_PL = 'https://sandbox-api-shipx-pl.easypack24.net';

		const API_URL_PRODUCTION_UK = 'https://api-shipx-uk.easypack24.net/v1/';

		const API_URL_SANDBOX_UK = 'https://sandbox-api-shipx-uk.easypack24.net/v1/';

		const ENVIRONMENT_PRODUCTION = 'production';

		const ENVIRONMENT_SANDBOX = 'sandbox';

		const COUNTRY_PL = 'PL';

		const COUNTRY_UK = 'GB';

		/**
		 * @var self
		 */
		protected static $instance;

		/**
		 * @var string
		 */
		private $environment;

		/**
		 * @var string
		 */
		private $country;

		/**
		 * @var string
		 */
		private $api_url;

		protected $token;

		protected $cache_period = DAY_IN_SECONDS;

		protected $geo_widget_api_url;


		public function __construct() {
			$this->token = trim( get_option( 'easypack_token' ) );
			$this->setupEnvironment();
		}

		private function setupEnvironment() {
			if ( 'sandbox' === get_option( 'easypack_api_environment' ) ) {
				$this->environment = self::ENVIRONMENT_SANDBOX;
			} else {
				$this->environment = self::ENVIRONMENT_PRODUCTION;
			}

			$this->country = self::COUNTRY_PL;

			$this->api_url = $this->make_api_url();
		}

		/**
		 * @param string $country_code
		 *
		 * @return string
		 */
		public function normalize_country_code_for_geowidget( $country_code ) {
			return strtolower( $country_code );
		}

		/**
		 * @param string $country_code
		 *
		 * @return string
		 */
		public function normalize_country_code_for_inpost( $country_code ) {
			return strtoupper( $country_code );
		}

		/**
		 * @return bool
		 */
		public function is_sandbox_env() {
			return self::ENVIRONMENT_SANDBOX === $this->environment;
		}

		/**
		 * @return bool
		 */
		public function is_production_env() {
			return self::ENVIRONMENT_PRODUCTION === $this->environment;
		}

		/**
		 * @return bool
		 */
		public function is_uk() {
			return self::COUNTRY_UK === $this->country;
		}

		/**
		 * @return bool
		 */
		public function is_pl() {
			return self::COUNTRY_PL === $this->country;
		}

		public static function EasyPack_API() {
			if ( self::$instance === null ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * @param null $url
		 *
		 * @return null|string
		 */
		public function make_api_url( $url = null ) {

			if ( self::ENVIRONMENT_SANDBOX === $this->environment ) {
				if ( self::COUNTRY_PL === $this->country ) {
					$url                      = self::API_URL_SANDBOX_PL;
					$this->geo_widget_api_url
						= 'https://sandbox-api-pl-points.easypack24.net/v1';
				}

				if ( self::COUNTRY_UK === $this->country ) {
					$url                      = self::API_URL_SANDBOX_UK;
					$this->geo_widget_api_url
						= 'https://sandbox-api-uk-points.easypack24.net/v1';
				}
			}

			if ( self::ENVIRONMENT_PRODUCTION === $this->environment ) {
				if ( self::COUNTRY_PL === $this->country ) {
					$url                      = self::API_URL_PRODUCTION_PL;
					$this->geo_widget_api_url
						= 'https://api-pl-points.easypack24.net/v1';
				}

				if ( self::COUNTRY_UK === $this->country ) {
					$url                      = self::API_URL_PRODUCTION_UK;
					$this->geo_widget_api_url
						= 'https://api-uk-points.easypack24.net/v1';
				}
			}

			$url        = untrailingslashit( $url );
			$parsed_url = wp_parse_url( $url );
			if ( ! isset( $parsed_url['path'] ) || trim( $parsed_url['path'] ) == '' ) {
				$url .= '/v1';
			}

			return $url;
		}


		public function clear_cache() {
			$this->token   = trim( get_option( 'easypack_token' ) );
			$this->api_url = $this->make_api_url();

			// delete old plugin data.
			delete_option( 'easypack_cache_machines' );
			delete_option( 'easypack_cache_machines_options' );
			delete_option( 'easypack_cache_machines_cod_options' );
			delete_option( 'easypack_cache_machines_time' );
		}


		public function translate_error( $error ) {
			$errors = array(
				'end of week collection invalid end of week collection' => __( 'You are creating a Weekend Package service, which is available from Thursday 20:00 to Friday 18:00 - currently this is not a dedicated time slot', 'inpost-for-woocommerce' ),
				'receiver_email'                       => __( 'Recipient e-mail', 'inpost-for-woocommerce' ),
				'forbidden'                            => __( 'forbidden', 'inpost-for-woocommerce' ),
				'receiver_phone'                       => __( 'Recipient phone', 'inpost-for-woocommerce' ),
				'address'                              => __( 'Address', 'inpost-for-woocommerce' ),
				'phone'                                => __( 'Phone', 'inpost-for-woocommerce' ),
				'email'                                => __( 'Email', 'inpost-for-woocommerce' ),
				'post_code'                            => __( 'Post code', 'inpost-for-woocommerce' ),
				'postal_code'                          => __( 'Post code', 'inpost-for-woocommerce' ),
				'default_machine_id'                   => __( 'Default parcel locker', 'inpost-for-woocommerce' ),

				'not_an_email'                         => __( 'not valid', 'inpost-for-woocommerce' ),
				'invalid'                              => __( 'invalid', 'inpost-for-woocommerce' ),
				'not_found'                            => __( 'not found', 'inpost-for-woocommerce' ),
				'invalid_format'                       => __( 'invalid format', 'inpost-for-woocommerce' ),
				'required, invalid_format'             => __( 'required', 'inpost-for-woocommerce' ),
				'too_many_characters'                  => __( 'too many characters', 'inpost-for-woocommerce' ),
				'Action (cancel) can not be taken on shipment with status (confirmed).'
											=> __( 'Action (cancel) can not be taken on shipment with status (confirmed).', 'inpost-for-woocommerce' ),
				'There are some validation errors. Check details object for more info.'
											=> __( 'There are some validation errors.', 'inpost-for-woocommerce' ),

				'Access to this resource is forbidden' => __( 'Invalid login or token', 'inpost-for-woocommerce' ),
				'Sorry, access to this resource is forbidden' => __( 'Invalid login', 'inpost-for-woocommerce' ),
				'Token is missing or invalid.'         => __( 'Token is missing or invalid. Or service works on server, try later.', 'inpost-for-woocommerce' ),
				'Box machine name cannot be empty'     => __( 'Parcel Locker is empty. Please fill in this field.', 'inpost-for-woocommerce' ),
				'Default parcel machine'               => __( 'Default send parcel locker: ', 'inpost-for-woocommerce' ),
				'The transaction can not be completed due to the balance of your account' => __(
					'The transaction can not be completed due to the balance of your account',
					'inpost-for-woocommerce'
				),
				'You have not enough funds to pay for this parcel' => __(
					'Can not create sticker. You have not enough funds to pay for this parcel',
					'inpost-for-woocommerce'
				),
				'company_data_missing'                 => __(
					'The organization does not have agreement about service SmartCourier',
					'inpost-for-woocommerce'
				),
				'validation_failed'                    => __(
					'The organization does not have agreement about service SmartCourier',
					'inpost-for-woocommerce'
				),
			);

			if ( isset( $errors[ $error ] ) ) {
				return $errors[ $error ];
			}

			if ( strpos( $error, 'invalid end of week collection' ) !== false ) {
				return __( 'You are creating a Weekend Package service, which is available from Thursday 20:00 to Friday 18:00 - currently this is not a dedicated time slot', 'inpost-for-woocommerce' );
			}

			return $error;
		}

		public function get_error( $errors ) {
			return $this->get_error_recursive( $errors, 10 );
		}

		/**
		 * @param      $array
		 * @param int   $level
		 * @param null  $key_recursive
		 *
		 * @return string
		 */
		private function get_error_recursive(
			$array,
			$level = 1,
			$key_recursive = null
		) {

			$output = '';
			if ( null !== $key_recursive
				&& ! is_numeric( $key_recursive )
			) {
				$output .= $key_recursive . ' ';
			}
			foreach ( $array as $key => $value ) {

				if ( is_array( $value ) ) {

					$output .= $this->get_error_recursive(
						$value,
						$level + 1,
						$key
					);
				} elseif ( ! is_numeric( $key ) ) {
						$value   = str_replace( '_', ' ', $value );
						$output .= $key . ': ' . $value . PHP_EOL;
				} elseif ( ! is_array( $value ) ) {
						$output .= $value . PHP_EOL;
				}
			}

			return $output;
		}

		/**
		 * @param array $response
		 *
		 * @return bool
		 */
		private function is_binary_response( $response ) {
			if ( ! isset( $response['headers']['content-transfer-encoding'] ) ) {
				return false;
			}

			$headers = $response['headers'];
			$data    = $headers->getAll();

			return $data['content-transfer-encoding'] === 'binary';
		}


		public function post( $path, $args = array(), $method = 'POST' ) {
			$url          = untrailingslashit( $this->api_url ) . str_replace( ' ', '%20', str_replace( '@', '%40', $path ) );
			$request_args = array(
				'timeout' => 30,
				'method'  => $method,
			);

			$request_args['headers'] = array(
				'Authorization' => 'Bearer ' . $this->token,
				'Content-Type'  => 'application/json',
			);

			$request_args['body'] = json_encode( $args );

			$response = wp_remote_post( $url, $request_args );

			if ( is_wp_error( $response ) ) {

				throw new Exception( esc_html( $response->get_error_message() ) );

			} else {

				if ( $this->is_binary_response( $response ) ) {
					return array(
						'headers' => $response['headers'],
						'body'    => $response['body'],
					);
				}

				$ret = json_decode( $response['body'], true );

				if ( ! is_array( $ret ) ) {
					throw new Exception( esc_html__( 'Bad API response. Check API URL', 'inpost-for-woocommerce' ), 503 );

				} elseif ( isset( $ret['status'] ) ) {
					$errors = '';
					if ( isset( $ret['error'] ) && ! empty( $ret['error'] ) ) {
						if ( is_array( $ret['details'] ) ) {
							if ( count( $ret['details'] ) ) {
								$errors = $this->get_error( $ret['details'] );
							}
						} else {
							$errors = ': ' . $ret['details'];
						}
					} elseif ( isset( $ret['message'] ) ) {
							$errors = $this->translate_error( $ret['message'] );
					}

					// Unavailability reasons.
					if ( isset( $ret['unavailability_reasons'] ) ) {
						if ( is_array( $ret['unavailability_reasons'] ) && ! empty( $ret['unavailability_reasons'] ) ) {
							foreach ( $ret['unavailability_reasons'] as $arr ) {
								if ( is_array( $arr ) ) {
									foreach ( $arr as $k => $v ) {
										if ( 'sender' === $k && 'post_code_invalid' === $v ) {
											$errors .= esc_html__( 'Wrong sender postal code', 'inpost-for-woocommerce' );
										} elseif ( 'receiver' === $k && 'post_code_invalid' === $v ) {
											$errors .= esc_html__( 'Wrong receiver postal code', 'inpost-for-woocommerce' );
										} else {
											$errors .= $k . ': ' . $v . ' ';
										}
									}
								}
							}

							if ( ! empty( $errors ) ) {
								throw new Exception( esc_html( str_replace( '_', ' ', $errors ) ), 0 );
							}
						}
					}

					if ( isset( $ret['errors'] ) || isset( $ret['error'] ) ) {
						if ( empty( $errors ) ) {
							$errors = $ret['message'];
						}
						throw new Exception( esc_html( str_replace( '_', ' ', $errors ) ), esc_html( $ret['status'] ) );
					}
				}

				return $ret;
			}
		}


		/**
		 * @param  string $path
		 * @param array  $args
		 * @param string $method
		 *
		 * @return array|mixed|object
		 * @throws Exception
		 */
		public function delete( $path, $args = array(), $method = 'DELETE' ) {
			$url          = untrailingslashit( $this->api_url ) . str_replace( ' ', '%20', str_replace( '@', '%40', $path ) );
			$request_args = array(
				'timeout' => 30,
				'method'  => $method,
			);

			$request_args['headers'] = array(
				'Authorization' => 'Bearer ' . $this->token,
				'Content-Type'  => 'application/json',
			);

			$request_args['body'] = json_encode( $args );

			$response = wp_remote_post( $url, $request_args );
			if ( is_wp_error( $response ) ) {
				throw new Exception( esc_html( $response->get_error_message() ) );
			} else {

				if ( $this->is_binary_response( $response ) ) {
					return array(
						'headers' => $response['headers'],
						'body'    => $response['body'],
					);
				}

				$ret = json_decode( $response['body'], true );
				if ( ! is_array( $ret ) ) {
					throw new Exception( esc_html__( 'Bad API response. Check API URL', 'inpost-for-woocommerce' ), 503 );
				} elseif ( isset( $ret['status'] ) ) {
						$errors = '';

					if ( isset( $ret['error'] ) && is_array( $ret['error'] ) && count( $ret['error'] ) ) {
						if ( ! empty( $ret['message'] ) ) {
							$errors = $this->translate_error( $ret['message'] );
							throw new Exception( esc_html( $errors ), esc_html( $ret['status'] ) );
						}
						if ( is_array( $ret['details'] ) ) {
							if ( count( $ret['details'] ) ) {
								$errors = $this->get_error( $ret['details'] );
							}
						} else {
							$errors = ': ' . $ret['details'];
						}
					} else {
						$errors = $this->translate_error( $ret['message'] );
					}
					if ( isset( $ret['errors'] ) || isset( $ret['error'] ) ) {
						if ( empty( $errors ) ) {
							$errors = $ret['message'];
						}
						throw new Exception( esc_html( $errors ), esc_html( $ret['status'] ) );
					}
				}

				return $ret;
			}
		}


		public function put( $path, $args = array(), $method = 'PUT' ) {
			return $this->post( $path, $args, 'PUT' );
		}

		public function get(
			$path,
			$args = array(),
			$params = array(),
			$decode_url = false
		) {

			$ret = null;

			$url = untrailingslashit( $this->api_url ) . str_replace( ' ', '%20', str_replace( '@', '%40', $path ) );

			if ( ! empty( $params ) ) {
				$newUrl = $url;
				foreach ( $params as $k => $v ) {
					$newUrl = add_query_arg( $k, $v, $newUrl );
				}

				if ( true === $decode_url ) {
					$url = preg_replace( '/\[[^\[\]]*\]/', '[]', urldecode( $newUrl ) );
				} else {
					$url = $newUrl;
				}
			}

			if ( ! empty( $this->token ) ) {

				$request_args = array( 'timeout' => 30 );

				$request_args['headers'] = array(
					'Authorization' => 'Bearer ' . $this->token,
					'Content-Type'  => 'application/json',
				);

				$response = wp_remote_get( $url, $request_args );

				if ( is_wp_error( $response ) ) {
					$this->authorizationError( $response->get_error_message() . ' ( Endpoint: ' . $url . ' )', $response->get_error_code() );
				} else {

					if ( $this->is_binary_response( $response ) ) {
						return array(
							'headers' => $response['headers'],
							'body'    => $response['body'],
						);
					}

					$ret = json_decode( $response['body'], true );
					if ( ! is_array( $ret ) ) {
						// throw new Exception(__( 'Bad API response. Check API URL', 'inpost-for-woocommerce' ), 503 );.

						$alerts = new Alerts();
						$alerts->add_error( 'InPost PL: ' . __( 'Bad API response', 'inpost-for-woocommerce' ) );

					} elseif ( isset( $ret['status'] ) ) {
							$errors = '';
						if ( isset( $ret['error'] ) && ! empty( $ret['error'] ) ) {
							if ( is_array( $ret['details'] ) ) {
								if ( count( $ret['details'] ) ) {
									$errors = $this->get_error( $ret['details'] );
								}
							} else {
								if ( ! empty( $ret['details'] ) ) {
									$errors = ': ' . $ret['details'];
								}

								if ( ! empty( $ret['message'] ) ) {
									$errors = ': ' . $ret['message'];
								}
							}
						} elseif ( isset( $ret['message'] ) ) {
								$errors = $this->translate_error( $ret['message'] );
						}
						if ( isset( $ret['errors'] ) || isset( $ret['error'] ) ) {
							if ( empty( $errors ) ) {
								$errors = $ret['message'];
							}

							$this->authorizationError( $errors, $ret['status'] );
						}
					}
				}
			}

			return $ret;
		}

		/**
		 * @param null $id
		 *
		 * @return array|mixed|object
		 * @throws Exception
		 */
		public function ping( $id = null ) {
			$res = null;
			if ( ! empty( $this->token ) ) {
				$organizationId = ( null !== $id ) ? $id : get_option( 'easypack_organization_id' );
				$res            = $this->get( sprintf( '/organizations/%s', $organizationId ) );

				if ( $res && ! isset( $res['error'] ) ) {
					$alerts = new Alerts();
					$alerts->add_success( 'Inpost PL: ' . __( 'New API settings connection test passed.', 'inpost-for-woocommerce' ) );

					update_option( 'easypack_api_login_error', '0' );
				} else {
					update_option( 'easypack_api_login_error', '1' );
				}
			}

			return $res;
		}


		/**
		 * Retrieves organization details from the InPost API.
		 *
		 * Uses the stored authentication token to fetch organization information.
		 * If an organization ID is not provided, uses the one stored in WordPress options.
		 * Handles authorization errors and logs them when WooCommerce logging is available.
		 *
		 * @param int|null $id Optional organization ID to retrieve. If null, uses stored ID.
		 * @return array|null Organization data on success, null on failure.
		 */
		public function get_organization( $id = null ) {

			$res                       = null;
			static $request_is_running = false;

			if ( ! empty( $this->token ) && ! $request_is_running ) {

				$request_is_running = true;

				try {

					$organization_id = ( null !== $id ) ? $id : get_option( 'easypack_organization_id' );
					$res             = $this->get( sprintf( '/organizations/%d', $organization_id ) );

				} catch ( Exception $e ) {

					$request_is_running = false;
					$res['error']       = $e->getMessage();
				}

				if ( ! empty( $res['error'] ) ) {
					$status = isset( $res['status'] ) ? (int) $res['status'] : 401;
					$this->authorizationError( $res['error'], $status );

					if ( function_exists( 'wc_get_logger' ) ) {
						\wc_get_logger()->debug( 'Error:', array( 'source' => 'inpost-pl-services-error' ) );
						\wc_get_logger()->debug( print_r( $res, true ), array( 'source' => 'inpost-pl-services-error' ) );
					}

					$res = null;
				}
			}

			$request_is_running = false;

			return $res;
		}

		/**
		 * @return array|mixed|object
		 * @throws Exception
		 */
		public function getServicesGlobal() {
			$res = $this->get( '/services/' );

			if ( isset( $res['error'] ) ) {
				$status = isset( $res['status'] ) ? (int) $res['status'] : 401;
				$this->authorizationError( $res['error'], $status );
				return null;
			}

			return $res;
		}

		/**
		 * @param string $message
		 * @param int    $status
		 *
		 * @throws Exception
		 */
		private function authorizationError( $message, $status ) {
			$errors = $this->translate_error( $message );
			$errors = str_replace( '<br>', ' ', $errors );

			$alerts = new Alerts();
			/*
			$alerts->add_error( 'Woocommerce Inpost: ' . ( is_string( $errors )
					? $errors
					: serialize( $errors ) . $message . ' ( ' . $status . ' )' ) );*/
			$alerts->add_error( 'InPost PL: ' . __( 'Error is occured during connection to API', 'inpost-for-woocommerce' ) );
		}


		/**
		 * @param $dispatch_point_id
		 *
		 * @return array|mixed|object
		 * @throws Exception
		 *
		 * @depecated
		 */
		public function dispatch_point( $dispatch_point_id ) {
			return $this->get( '/dispatch_points/' . $dispatch_point_id );
		}


		/**
		 * @param $args
		 *
		 * @return array|mixed|object
		 * @throws Exception
		 */
		public function dispatch_order( $args ) {
			$organisationId = get_option( 'easypack_organization_id' );
			$response       = $this->post( sprintf( '/organizations/%d/dispatch_orders', $organisationId ), $args );

			return $response;
		}


		/**
		 * @param array $orders
		 *
		 * @return array|mixed|object
		 * @throws Exception
		 */
		public function get_post_confirmations( $orders ) {
			$organisationId = get_option( 'easypack_organization_id' );
			$args           = array(
				'format'       => 'pdf',
				'shipment_ids' => $orders,
			);

			return $this->get(
				sprintf(
					'/organizations/%d/dispatch_orders/printouts',
					$organisationId
				),
				null,
				$args,
				true
			);
		}


		public function customer_parcel_create( $args ) {
			$order_id = null;
			if ( is_array( $args )
				&& isset( $args['internal_data'] )
				&& is_array( $args['internal_data'] )
			) {
				if ( ! empty( $args['internal_data']['order_id'] ) ) {
					$order_id = absint( $args['internal_data']['order_id'] );
				}
			}

			/**
			 * Filter shipment payload sent to ShipX create endpoint.
			 *
			 * Allows customizing request data before API call, e.g. enriching
			 * receiver address fields from custom checkout meta.
			 *
			 * @param array $args Shipment payload.
			 * @param int|null $order_id Related WooCommerce order ID when available.
			 */
			$args = apply_filters( 'inpost_pl_customer_parcel_create_payload', $args, $order_id );

			$organizationId = get_option( 'easypack_organization_id' );
			$response       = $this->post( sprintf( '/organizations/%d/shipments', $organizationId ), $args );

			return $response;
		}


		public function customer_parcel_get_by_id( $id ) {

			$response = $this->get( sprintf( '/shipments/%d', $id ) );

			return $response;
		}

		/**
		 * @return string
		 * @throws Exception
		 */
		public function get_statuses() {
			$response = $this->get( '/statuses' );

			return $response;
		}

		/**
		 * @param $parcel_id
		 *
		 * @return array|mixed|object
		 * @throws Exception
		 */
		public function customer_parcel_cancel( $parcel_id ) {
			$response = $this->delete( '/shipments/' . $parcel_id );

			return $response;
		}

		public function customer_parcel_pay( $parcel_id ) {
			$args     = array();
			$response = $this->post( '/parcels/' . $parcel_id . '/pay', $args );

			return $response;
		}


		public function customer_parcel_sticker( $parcel_id ) {
			$labelFormat = get_option( 'easypack_label_format' );
			$type        = 'A6';
			$type        = $labelFormat === 'A4' ? 'normal' : 'A6';
			return $this->get( '/shipments/' . $parcel_id . '/label?format=Pdf&type=' . $type );
		}


		/**
		 * @param $shipment_ids
		 *
		 * @return array|mixed|object
		 * @throws Exception
		 */
		public function customer_shipments_labels( $shipment_ids ) {

			$result = array();
			if ( ! empty( $shipment_ids ) ) {
				$organizationId = get_option( 'easypack_organization_id' );
				$labelFormat    = get_option( 'easypack_label_format' );

				$args = array(
					'format'       => 'pdf',
					'shipment_ids' => $shipment_ids,
					'type'         => $labelFormat === 'A4' ? 'normal' : 'A6',
				);

				$result = $this->get( sprintf( '/organizations/%d/shipments/labels', $organizationId ), array(), $args, true );
			}

			return $result;
		}

		/**
		 * @param $shipment_ids
		 *
		 * @return array|mixed|object
		 * @throws Exception
		 */
		public function customer_shipments_return_labels( $shipment_ids ) {
			$organizationId = get_option( 'easypack_organization_id' );
			$args           = array(
				'format'       => 'pdf',
				'shipment_ids' => $shipment_ids,
			);

			return $this->get(
				sprintf(
					'/organizations/%d/shipments/return_labels',
					$organizationId
				),
				array(),
				$args,
				true
			);
		}

		/**
		 * @param $dispatch_order_id
		 *
		 * @return array|mixed|object
		 * @throws Exception
		 */
		public function dispatch_order_pdf( $dispatch_order_id ) {
			$organizationId = get_option( 'easypack_organization_id' );
			$args           = array(
				'format' => 'Pdf',
			);

			return $this->get(
				sprintf(
					'/dispatch_orders/%d/printout',
					$dispatch_order_id
				),
				null,
				$args,
				true
			);
		}

		public function customer_parcel( $parcel_id ) {
			$response = $this->get( '/parcels/' . $parcel_id );
			$parcel   = $response;

			return $parcel;
		}


		/**
		 * @return mixed
		 * @deprecated
		 */
		public function api_country() {
			return $this->getCountry();
		}

		public function validate_phone( $phone ) {

			if ( $this->getCountry() == self::COUNTRY_UK ) {
				if ( preg_match( '/\A\d{10}\z/', $phone ) ) {
					return true;
				} else {
					return __( 'Invalid phone number. Valid phone number must contains 10 digits.', 'inpost-for-woocommerce' );
				}
			}
			if ( $this->getCountry() == self::COUNTRY_PL ) {
				if ( preg_match( '/\A[1-9]\d{8}\z/', $phone ) ) {
					return true;
				} else {
					return __(
						'Invalid phone number. Valid phone number must contains 9 digits and must not begins with 0.',
						'inpost-for-woocommerce'
					);
				}
			}

			return __( 'Invalid phone number.', 'inpost-for-woocommerce' );
		}

		/**
		 * @param $shipments
		 *
		 * @return array|mixed|object
		 * @throws Exception
		 */
		public function calculate_shipments( $shipments ) {
			$organizationId = get_option( 'easypack_organization_id' );
			$response       = $this->post( sprintf( '/organizations/%d/shipments/calculate', $organizationId ), $shipments );

			return $response;
		}


		/**
		 * @return mixed
		 */
		public function getCountry() {
			return $this->country;
		}
	}


endif;
