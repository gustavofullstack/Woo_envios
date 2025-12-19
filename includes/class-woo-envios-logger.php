<?php
/**
 * Logger for Woo Envios debugging.
 *
 * @package Woo_Envios
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Woo_Envios_Logger {

	/**
	 * Check if logging is enabled.
	 *
	 * @return bool
	 */
	private static function is_enabled(): bool {
		return (bool) get_option( 'woo_envios_enable_logs', false );
	}

	/**
	 * Write log entry to file.
	 *
	 * @param string $message Log message.
	 * @param string $level   Log level (info, warning, error).
	 */
	private static function log( string $message, string $level = 'info' ): void {
		if ( ! self::is_enabled() ) {
			return;
		}

		$upload_dir = wp_upload_dir();
		$log_dir    = $upload_dir['basedir'] . '/woo-envios-logs/';

		// Create directory if it doesn't exist.
		if ( ! file_exists( $log_dir ) ) {
			wp_mkdir_p( $log_dir );
			// Protect directory with .htaccess.
			file_put_contents( $log_dir . '.htaccess', 'Deny from all' );
		}

		$log_file = $log_dir . gmdate( 'Y-m-d' ) . '.log';

		$entry = sprintf(
			"[%s] [%s] %s\n",
			gmdate( 'Y-m-d H:i:s' ),
			strtoupper( $level ),
			$message
		);

		error_log( $entry, 3, $log_file );
	}

	/**
	 * Log shipping calculation.
	 *
	 * @param float  $distance    Distance in km.
	 * @param float  $base_price  Base price.
	 * @param float  $final_price Final price.
	 * @param array  $multipliers Applied multipliers.
	 * @param string $address     Customer address.
	 * @param array  $store_coords Store coordinates (lat, lng).
	 * @param array  $customer_coords Customer coordinates (lat, lng).
	 */
	public static function shipping_calculated( float $distance, float $base_price, float $final_price, array $multipliers, string $address = '', array $store_coords = array(), array $customer_coords = array() ): void {
		$message = sprintf(
			'FRETE CALCULADO | Distância: %.1f km | Base: R$ %.2f | Final: R$ %.2f | Multiplicadores: %s',
			$distance,
			$base_price,
			$final_price,
			empty( $multipliers ) ? 'Nenhum' : implode( ', ', $multipliers )
		);

		if ( ! empty( $address ) ) {
			$message .= ' | Endereço: ' . $address;
		}

		if ( ! empty( $store_coords ) && ! empty( $customer_coords ) ) {
			$message .= sprintf(
				' | Loja: [%s, %s] | Cliente: [%s, %s]',
				$store_coords['lat'] ?? '?',
				$store_coords['lng'] ?? '?',
				$customer_coords['lat'] ?? '?',
				$customer_coords['lng'] ?? '?'
			);
		}

		self::log( $message, 'info' );
	}

	/**
	 * Log API failure.
	 *
	 * @param string $api_name API name.
	 * @param string $error    Error message.
	 */
	public static function api_failure( string $api_name, string $error ): void {
		self::log(
			sprintf( 'FALHA API %s: %s', $api_name, $error ),
			'error'
		);
	}

	/**
	 * Log circuit breaker opened.
	 *
	 * @param int $failures Number of failures.
	 */
	public static function circuit_breaker_opened( int $failures ): void {
		self::log(
			sprintf( 'CIRCUIT BREAKER ABERTO após %d falhas consecutivas', $failures ),
			'warning'
		);

		// Send email to admin.
		self::notify_admin_api_failure( $failures );
	}

	/**
	 * Notify admin about API failures.
	 *
	 * @param int $failures Number of failures.
	 */
	private static function notify_admin_api_failure( int $failures ): void {
		// Check if already notified recently (avoid spam).
		$last_notification = get_transient( 'woo_envios_last_failure_notification' );
		if ( false !== $last_notification ) {
			return; // Don't send another email within 1 hour.
		}

		$admin_email = get_option( 'admin_email' );
		$subject     = 'Woo Envios: Falhas na API do Google Maps';
		$message     = sprintf(
			"O plugin Woo Envios detectou %d falhas consecutivas na API do Google Maps.\n\n" .
			"O sistema entrou em modo de proteção (circuit breaker) e está usando coordenadas padrão.\n\n" .
			"Possíveis causas:\n" .
			"- Chave da API inválida ou expirada\n" .
			"- Limite de requisições excedido\n" .
			"- Problema de conexão com o Google\n\n" .
			"Verifique as configurações em: WooCommerce → Woo Envios\n\n" .
			"Data/Hora: %s",
			$failures,
			current_time( 'Y-m-d H:i:s' )
		);

		wp_mail( $admin_email, $subject, $message );

		// Set transient to avoid spam (1 hour).
		set_transient( 'woo_envios_last_failure_notification', time(), 3600 ); // 1 hour
	}

	/**
	 * Clear old log files (keep last 7 days).
	 */
	public static function cleanup_old_logs(): void {
		if ( ! self::is_enabled() ) {
			return;
		}

		$upload_dir = wp_upload_dir();
		$log_dir    = $upload_dir['basedir'] . '/woo-envios-logs/';

		if ( ! file_exists( $log_dir ) ) {
			return;
		}

		$files = glob( $log_dir . '*.log' );
		$now   = time();

		foreach ( $files as $file ) {
			if ( is_file( $file ) ) {
				// Delete files older than 7 days.
				if ( $now - filemtime( $file ) >= 7 * 86400 ) { // 7 days in seconds
					unlink( $file );
				}
			}
		}
	}

	/**
	 * Log when customer distance is outside delivery range.
	 *
	 * @param float $distance           Calculated distance in km.
	 * @param array $destination_data   Destination address data.
	 */
	public static function distance_out_of_range( float $distance, array $destination_data ): void {
		$address_parts = array_filter( array(
			$destination_data['address_1'] ?? '',
			$destination_data['city'] ?? '',
			$destination_data['state'] ?? '',
			$destination_data['postcode'] ?? '',
		) );
		
		$address = implode( ', ', $address_parts );
		
		$message = sprintf(
			'DISTÂNCIA FORA DO ALCANCE | Distância: %.2f km | Endereço: %s | Ação: Método de envio não exibido',
			$distance,
			$address ?: 'Endereço não especificado'
		);

		self::log( $message, 'warning' );
	}
}
