<?php
/**
 * Woo Envios Shipping Service - SuperFrete Integration.
 * 
 * Simplified shipping calculation using SuperFrete API.
 * Provides PAC, SEDEX, Mini Envios, Jadlog and Loggi quotes.
 *
 * @package Woo_Envios
 */

namespace Woo_Envios\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Woo_Envios_Correios - Shipping calculation via SuperFrete.
 */
class Woo_Envios_Correios {

	/**
	 * SuperFrete Production API URL.
	 */
	private const API_URL = 'https://api.superfrete.com/api/v0/calculator';

	/**
	 * SuperFrete Sandbox API URL.
	 */
	private const SANDBOX_URL = 'https://sandbox.superfrete.com/api/v0/calculator';

	/**
	 * Cache duration (12 hours).
	 */
	private const CACHE_DURATION = 43200;

	/**
	 * Request timeout in seconds.
	 */
	private const TIMEOUT = 5;

	/**
	 * Maximum package weight in kg.
	 */
	private const MAX_WEIGHT = 30.0;

	/**
	 * API Token.
	 *
	 * @var string
	 */
	private string $api_token;

	/**
	 * Origin postal code.
	 *
	 * @var string
	 */
	private string $origin_cep;

	/**
	 * Profit margin percentage.
	 *
	 * @var float
	 */
	private float $profit_margin;

	/**
	 * Enabled services (1=PAC, 2=SEDEX, 17=Mini, 3=Jadlog).
	 *
	 * @var array
	 */
	private array $services;

	/**
	 * Use sandbox mode.
	 *
	 * @var bool
	 */
	private bool $sandbox_mode;

	/**
	 * Whether the service is enabled.
	 *
	 * @var bool
	 */
	private bool $enabled;

	/**
	 * Service names mapping.
	 */
	private const SERVICE_NAMES = array(
		1  => 'PAC',
		2  => 'SEDEX',
		17 => 'Mini Envios',
		3  => 'Jadlog',
		31 => 'Loggi',
	);

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->api_token     = get_option( 'woo_envios_superfrete_token', '' );
		$this->origin_cep    = preg_replace( '/[^0-9]/', '', get_option( 'woo_envios_superfrete_origin_cep', '38405320' ) );
		$this->profit_margin = (float) get_option( 'woo_envios_superfrete_profit_margin', 0 );
		$this->sandbox_mode  = (bool) get_option( 'woo_envios_superfrete_sandbox', false );
		$this->enabled       = (bool) get_option( 'woo_envios_superfrete_enabled', true );
		
		// Load enabled services (default: PAC + SEDEX)
		$saved_services = get_option( 'woo_envios_superfrete_services', array( '1', '2' ) );
		$this->services = is_array( $saved_services ) ? $saved_services : array( '1', '2' );
	}

	/**
	 * Check if SuperFrete is enabled and configured.
	 *
	 * @return bool
	 */
	public function is_enabled(): bool {
		return $this->enabled && ! empty( $this->api_token ) && ! empty( $this->origin_cep );
	}

	/**
	 * Get API URL based on environment.
	 *
	 * @return string
	 */
	private function get_api_url(): string {
		return $this->sandbox_mode ? self::SANDBOX_URL : self::API_URL;
	}

	/**
	 * Calculate shipping rates.
	 *
	 * @param array $package WooCommerce package data.
	 * @return array|false Array of rates or false on failure.
	 */
	public function calculate( array $package ) {
		if ( ! $this->is_enabled() ) {
			$this->log_error( 'SuperFrete não configurado. Verifique token e CEP origem.' );
			return false;
		}

		$destination_cep = $this->sanitize_cep( $package['destination']['postcode'] ?? '' );

		if ( empty( $destination_cep ) || strlen( $destination_cep ) !== 8 ) {
			$this->log_error( 'CEP destino inválido: ' . ( $package['destination']['postcode'] ?? 'vazio' ) );
			return false;
		}

		// Get package dimensions and weight
		$weight     = $this->get_total_weight( $package );
		$dimensions = $this->get_package_dimensions( $package );

		// Validate weight
		if ( $weight > self::MAX_WEIGHT ) {
			$this->log_error( "Peso excede limite: {$weight}kg > " . self::MAX_WEIGHT . 'kg' );
			return false;
		}

		// Calculate cart value for insurance
		$cart_value = $this->get_cart_value( $package );

		// Check cache
		$cache_key = $this->build_cache_key( $destination_cep, $weight, $dimensions );
		$cached    = get_transient( $cache_key );
		
		if ( false !== $cached ) {
			$this->log_info( 'Usando cache para CEP: ' . $destination_cep );
			return $this->apply_profit_margin( $cached );
		}

		// Call SuperFrete API
		$rates = $this->call_superfrete_api( $destination_cep, $weight, $dimensions, $cart_value );

		if ( empty( $rates ) ) {
			$this->log_error( 'Nenhuma taxa retornada para CEP: ' . $destination_cep );
			return false;
		}

		// Cache results
		set_transient( $cache_key, $rates, self::CACHE_DURATION );

		return $this->apply_profit_margin( $rates );
	}

	/**
	 * Call SuperFrete API.
	 *
	 * @param string $destination_cep Destination CEP.
	 * @param float  $weight          Weight in kg.
	 * @param array  $dimensions      Dimensions.
	 * @param float  $value           Cart value.
	 * @return array Rates or empty array.
	 */
	private function call_superfrete_api( string $destination_cep, float $weight, array $dimensions, float $value ): array {
		$body = array(
			'from' => array(
				'postal_code' => $this->origin_cep,
			),
			'to' => array(
				'postal_code' => $destination_cep,
			),
			'services' => implode( ',', $this->services ),
			'options' => array(
				'own_hand'            => false,
				'receipt'             => false,
				'insurance_value'     => $value > 0 ? $value : 0,
				'use_insurance_value' => $value > 0,
			),
			'package' => array(
				'height' => max( (int) $dimensions['height'], 2 ),
				'width'  => max( (int) $dimensions['width'], 11 ),
				'length' => max( (int) $dimensions['length'], 16 ),
				'weight' => max( $weight, 0.1 ),
			),
		);

		$response = wp_remote_post( $this->get_api_url(), array(
			'timeout' => self::TIMEOUT,
			'headers' => array(
				'Content-Type'  => 'application/json',
				'Accept'        => 'application/json',
				'Authorization' => 'Bearer ' . $this->api_token,
				'User-Agent'    => 'WooEnvios/1.0 (woo-envios@plugin.com)',
			),
			'body' => wp_json_encode( $body ),
		) );

		if ( is_wp_error( $response ) ) {
			$this->log_error( 'API error: ' . $response->get_error_message() );
			return array();
		}

		$status = wp_remote_retrieve_response_code( $response );
		$body   = wp_remote_retrieve_body( $response );

		if ( $status !== 200 ) {
			$this->log_error( "API returned $status: $body" );
			return array();
		}

		$data = json_decode( $body, true );

		if ( json_last_error() !== JSON_ERROR_NONE || empty( $data ) ) {
			$this->log_error( 'Invalid JSON response' );
			return array();
		}

		return $this->parse_response( $data );
	}

	/**
	 * Parse API response.
	 *
	 * @param array $response API response.
	 * @return array Parsed rates.
	 */
	private function parse_response( array $response ): array {
		$rates = array();

		foreach ( $response as $service ) {
			if ( ! empty( $service['error'] ) ) {
				continue;
			}

			$service_id = (int) ( $service['id'] ?? 0 );
			$price      = (float) ( $service['price'] ?? 0 );
			$deadline   = (int) ( $service['delivery_time'] ?? 0 );
			$company    = $service['company']['name'] ?? '';
			$name       = $service['name'] ?? self::SERVICE_NAMES[ $service_id ] ?? 'Frete';

			if ( $price <= 0 ) {
				continue;
			}

			// Build label
			$label = $name;
			if ( ! empty( $company ) && stripos( $name, $company ) === false ) {
				$label = "$company - $name";
			}
			if ( $deadline > 0 ) {
				$label .= " ($deadline dias úteis)";
			}

			$rates[] = array(
				'id'       => 'superfrete_' . $service_id,
				'code'     => (string) $service_id,
				'label'    => $label,
				'cost'     => round( $price, 2 ),
				'deadline' => $deadline,
			);
		}

		// Sort by price
		usort( $rates, fn( $a, $b ) => $a['cost'] <=> $b['cost'] );

		return $rates;
	}

	/**
	 * Sanitize CEP.
	 *
	 * @param string $cep CEP to sanitize.
	 * @return string Sanitized CEP (8 digits only).
	 */
	private function sanitize_cep( string $cep ): string {
		return preg_replace( '/[^0-9]/', '', $cep );
	}

	/**
	 * Get total weight from package.
	 *
	 * @param array $package Package data.
	 * @return float Weight in kg.
	 */
	private function get_total_weight( array $package ): float {
		$weight = 0;

		if ( ! empty( $package['contents'] ) ) {
			foreach ( $package['contents'] as $item ) {
				$product = $item['data'] ?? null;
				if ( $product && method_exists( $product, 'get_weight' ) ) {
					$item_weight = (float) $product->get_weight();
					$weight += $item_weight * ( $item['quantity'] ?? 1 );
				}
			}
		}

		// Minimum weight
		return max( $weight, 0.1 );
	}

	/**
	 * Get package dimensions.
	 *
	 * @param array $package Package data.
	 * @return array Dimensions (height, width, length in cm).
	 */
	private function get_package_dimensions( array $package ): array {
		$height = 0;
		$width  = 0;
		$length = 0;

		if ( ! empty( $package['contents'] ) ) {
			foreach ( $package['contents'] as $item ) {
				$product = $item['data'] ?? null;
				if ( $product && method_exists( $product, 'get_height' ) ) {
					$qty = $item['quantity'] ?? 1;
					$h   = (float) $product->get_height();
					$w   = (float) $product->get_width();
					$l   = (float) $product->get_length();

					$height += $h * $qty;
					$width   = max( $width, $w );
					$length  = max( $length, $l );
				}
			}
		}

		// Minimum dimensions
		return array(
			'height' => max( $height, 2 ),
			'width'  => max( $width, 11 ),
			'length' => max( $length, 16 ),
		);
	}

	/**
	 * Get cart value.
	 *
	 * @param array $package Package data.
	 * @return float Cart value.
	 */
	private function get_cart_value( array $package ): float {
		$value = 0;
		
		if ( ! empty( $package['contents'] ) ) {
			foreach ( $package['contents'] as $item ) {
				$value += (float) ( $item['line_total'] ?? 0 );
			}
		}

		return $value;
	}

	/**
	 * Apply profit margin.
	 *
	 * @param array $rates Rates array.
	 * @return array Rates with margin applied.
	 */
	private function apply_profit_margin( array $rates ): array {
		if ( $this->profit_margin <= 0 ) {
			return $rates;
		}

		$multiplier = 1 + ( $this->profit_margin / 100 );

		foreach ( $rates as &$rate ) {
			$rate['cost'] = round( $rate['cost'] * $multiplier, 2 );
		}

		return $rates;
	}

	/**
	 * Build cache key.
	 *
	 * @param string $cep        Destination CEP.
	 * @param float  $weight     Weight.
	 * @param array  $dimensions Dimensions.
	 * @return string Cache key.
	 */
	private function build_cache_key( string $cep, float $weight, array $dimensions ): string {
		$data = array(
			'o' => $this->origin_cep,
			'd' => $cep,
			'w' => round( $weight, 1 ),
			's' => implode( ',', $this->services ),
		);
		return 'woo_envios_sf_' . md5( wp_json_encode( $data ) );
	}

	/**
	 * Log error.
	 *
	 * @param string $message Error message.
	 */
	private function log_error( string $message ): void {
		if ( class_exists( 'Woo_Envios_Logger' ) ) {
			\Woo_Envios_Logger::error( '[SuperFrete] ' . $message );
		}
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[WooEnvios/SuperFrete] ' . $message );
		}
	}

	/**
	 * Log info.
	 *
	 * @param string $message Info message.
	 */
	private function log_info( string $message ): void {
		if ( class_exists( 'Woo_Envios_Logger' ) ) {
			\Woo_Envios_Logger::info( '[SuperFrete] ' . $message );
		}
	}
}
