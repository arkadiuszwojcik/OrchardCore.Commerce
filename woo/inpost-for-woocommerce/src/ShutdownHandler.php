<?php
/**
 * Shutdown handler for the InPost PL plugin (test-fatal build).
 *
 * @package InPost PL
 */

declare( strict_types=1 );

namespace InspireLabs\WoocommerceInpost;

/**
	 * Registers a PHP shutdown function that disables the plugin on fatal errors.
 *
	 * Must be registered before any plugin bootstrap code runs, so that even a fatal
	 * error during early loading is caught.
 *
	 * Skipped for AJAX, REST API and WP-Cron requests — a fatal in those contexts
	 * should not disable the plugin for all subsequent page loads.
 */
class ShutdownHandler {
	/**
	 * Absolute path to the main plugin file.
	 *
	 * @var string
	 */
	private string $plugin_file;

	/**
	 * Normalize file paths for cross-platform comparisons.
	 *
	 * @param string $path File path.
	 * @return string Normalized path.
	 */
	private function normalize_path( string $path ): string {
		return str_replace( '\\', '/', $path );
	}

	/**
	 * Try to log error details via WooCommerce logger (if available).
	 *
	 * @param string $message Message to log.
	 */
	private function maybe_log_with_wc_logger( string $message ): void {
		if ( ! function_exists( 'wc_get_logger' ) ) {
			return;
		}

		try {
			$logger = \wc_get_logger();
			if ( is_object( $logger ) && method_exists( $logger, 'critical' ) ) {
				$logger->critical( $message, array( 'source' => 'inpost-shutdown-handler' ) );
				return;
			}
			if ( is_object( $logger ) && method_exists( $logger, 'error' ) ) {
				$logger->error( $message, array( 'source' => 'inpost-shutdown-handler' ) );
			}
		} catch ( \Throwable $e ) {
			// Intentionally ignore logger failures.
		}
	}

	/**
	 * @param string $plugin_file Absolute path to the main plugin file (__FILE__ from woocommerce-inpost.php).
	 */
	private function __construct( string $plugin_file ) {
		$this->plugin_file = $plugin_file;
	}

	/**
	 * Register the shutdown handler.
	 *
	 * @param string $plugin_file Absolute path to the main plugin file.
	 */
	public static function register( string $plugin_file ): void {
		$handler = new self( $plugin_file );
		register_shutdown_function( array( $handler, 'handle' ) );
	}

	/**
	 * Shutdown callback. Disables the plugin if a fatal error originated in its directory.
	 */
	public function handle(): void {
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return;
		}
		if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
			return;
		}

		$error = error_get_last();

		if ( ! $error ) {
			return;
		}

		$fatal_types = array( E_ERROR, E_PARSE, E_COMPILE_ERROR );
		$is_fatal    = in_array( $error['type'], $fatal_types, true );
		$is_ours     = false !== strpos(
			$this->normalize_path( $error['file'] ),
			$this->normalize_path( plugin_dir_path( $this->plugin_file ) )
		);

		if ( ! $is_fatal || ! $is_ours ) {
			return;
		}

		if ( ! function_exists( 'deactivate_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$detail_message = 'Wtyczka InPost PL została dezaktywowana z powodu błędu: ' . $error['message'];
		$user_message   = 'Wystąpił błąd. Odśwież stronę i spróbuj ponownie.';
		$show_details   = function_exists( 'current_user_can' ) && current_user_can( 'activate_plugins' );
		$die_message    = $show_details ? $detail_message : $user_message;

		$this->maybe_log_with_wc_logger( $detail_message );
		deactivate_plugins( plugin_basename( $this->plugin_file ) );
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( $detail_message );
		wp_die( esc_html( $die_message ), '', array( 'response' => 500 ) );
	}
}

