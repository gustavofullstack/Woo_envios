<?php
/**
 * Correios Service - Brazilian Postal Service Integration.
 *
 * @package Woo_Envios
 */

namespace Woo_Envios\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Correios - Handles shipping calculations via Correios API.
 */
class Correios {

	/**
	 * CEP de origem (configurado no admin).
	 *
	 * @var string
	 */
	private string $origin_cep;

	/**
	 * Serviços ativos (códigos).
	 *
	 * @var array
	 */
	private array $services;

	/**
	 * Margem de lucro em porcentagem.
	 *
	 * @var float
	 */
	private float $profit_margin;

	/**
	 * Código de contrato (opcional).
	 *
	 * @var string
	 */
	private string $contract_code;

	/**
	 * Senha do contrato (opcional).
	 *
	 * @var string
	 */
	private string $contract_password;

	/**
	 * Serviços disponíveis dos Correios.
	 */
	private const AVAILABLE_SERVICES = array(
		'04510' => 'PAC',
		'04014' => 'SEDEX',
		'04782' => 'SEDEX 10',
		'04790' => 'SEDEX Hoje',
	);

	/**
	 * Dimensões mínimas exigidas pelos Correios (em cm).
	 */
	private const MIN_HEIGHT = 2;
	private const MIN_WIDTH  = 11;
	private const MIN_LENGTH = 16;

	/**
	 * Limites máximos dos Correios.
	 */
	private const MAX_WEIGHT = 30; // kg
	private const MAX_DIMENSION = 105; // cm (soma das dimensões)

	/**
	 * Tempo de cache em segundos (12 horas).
	 */
	private const CACHE_DURATION = 43200;

	/**
	 * URL do WebService dos Correios.
	 */
	private const CORREIOS_WS_URL = 'http://ws.correios.com.br/calculador/CalcPrecoPrazo.asmx?WSDL';

	/**
	 * Contingency rates by region (used when API fails).
	 * Format: region_key => ['pac' => price, 'sedex' => price, 'deadline_pac' => days, 'deadline_sedex' => days]
	 */
	private const CONTINGENCY_RATES = array(
		// Minas Gerais (estado de origem)
		'MG' => array( 'pac' => 18.00, 'sedex' => 28.00, 'deadline_pac' => 5, 'deadline_sedex' => 2 ),
		// Sudeste
		'SP' => array( 'pac' => 22.00, 'sedex' => 35.00, 'deadline_pac' => 6, 'deadline_sedex' => 2 ),
		'RJ' => array( 'pac' => 24.00, 'sedex' => 38.00, 'deadline_pac' => 7, 'deadline_sedex' => 3 ),
		'ES' => array( 'pac' => 22.00, 'sedex' => 35.00, 'deadline_pac' => 6, 'deadline_sedex' => 3 ),
		// Sul
		'PR' => array( 'pac' => 28.00, 'sedex' => 42.00, 'deadline_pac' => 8, 'deadline_sedex' => 3 ),
		'SC' => array( 'pac' => 30.00, 'sedex' => 45.00, 'deadline_pac' => 9, 'deadline_sedex' => 4 ),
		'RS' => array( 'pac' => 32.00, 'sedex' => 48.00, 'deadline_pac' => 10, 'deadline_sedex' => 4 ),
		// Centro-Oeste
		'GO' => array( 'pac' => 20.00, 'sedex' => 32.00, 'deadline_pac' => 5, 'deadline_sedex' => 2 ),
		'DF' => array( 'pac' => 22.00, 'sedex' => 35.00, 'deadline_pac' => 5, 'deadline_sedex' => 2 ),
		'MT' => array( 'pac' => 32.00, 'sedex' => 50.00, 'deadline_pac' => 10, 'deadline_sedex' => 5 ),
		'MS' => array( 'pac' => 28.00, 'sedex' => 45.00, 'deadline_pac' => 8, 'deadline_sedex' => 4 ),
		// Nordeste
		'BA' => array( 'pac' => 35.00, 'sedex' => 55.00, 'deadline_pac' => 12, 'deadline_sedex' => 5 ),
		'SE' => array( 'pac' => 38.00, 'sedex' => 58.00, 'deadline_pac' => 12, 'deadline_sedex' => 5 ),
		'AL' => array( 'pac' => 40.00, 'sedex' => 60.00, 'deadline_pac' => 13, 'deadline_sedex' => 6 ),
		'PE' => array( 'pac' => 42.00, 'sedex' => 62.00, 'deadline_pac' => 14, 'deadline_sedex' => 6 ),
		'PB' => array( 'pac' => 44.00, 'sedex' => 65.00, 'deadline_pac' => 14, 'deadline_sedex' => 6 ),
		'RN' => array( 'pac' => 45.00, 'sedex' => 68.00, 'deadline_pac' => 15, 'deadline_sedex' => 7 ),
		'CE' => array( 'pac' => 48.00, 'sedex' => 70.00, 'deadline_pac' => 15, 'deadline_sedex' => 7 ),
		'PI' => array( 'pac' => 50.00, 'sedex' => 72.00, 'deadline_pac' => 16, 'deadline_sedex' => 7 ),
		'MA' => array( 'pac' => 52.00, 'sedex' => 75.00, 'deadline_pac' => 18, 'deadline_sedex' => 8 ),
		// Norte
		'TO' => array( 'pac' => 45.00, 'sedex' => 68.00, 'deadline_pac' => 15, 'deadline_sedex' => 7 ),
		'PA' => array( 'pac' => 55.00, 'sedex' => 80.00, 'deadline_pac' => 18, 'deadline_sedex' => 8 ),
		'AP' => array( 'pac' => 60.00, 'sedex' => 85.00, 'deadline_pac' => 20, 'deadline_sedex' => 10 ),
		'AM' => array( 'pac' => 65.00, 'sedex' => 90.00, 'deadline_pac' => 22, 'deadline_sedex' => 10 ),
		'RR' => array( 'pac' => 68.00, 'sedex' => 95.00, 'deadline_pac' => 25, 'deadline_sedex' => 12 ),
		'RO' => array( 'pac' => 55.00, 'sedex' => 80.00, 'deadline_pac' => 18, 'deadline_sedex' => 8 ),
		'AC' => array( 'pac' => 70.00, 'sedex' => 100.00, 'deadline_pac' => 25, 'deadline_sedex' => 12 ),
	);

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->origin_cep        = $this->sanitize_cep( get_option( 'woo_envios_correios_origin_cep', '38400-000' ) );
		$this->services          = get_option( 'woo_envios_correios_services', array( '04510', '04014' ) );
		$this->profit_margin     = (float) get_option( 'woo_envios_correios_profit_margin', 0 );
		$this->contract_code     = get_option( 'woo_envios_correios_contract_code', '' );
		$this->contract_password = get_option( 'woo_envios_correios_contract_password', '' );

		// Ensure services is an array.
		if ( ! is_array( $this->services ) || empty( $this->services ) ) {
			$this->services = array( '04510', '04014' );
		}
	}

	/**
	 * Check if Correios is enabled and configured.
	 *
	 * @return bool
	 */
	public function is_enabled(): bool {
		return (bool) get_option( 'woo_envios_correios_enabled', false );
	}

	/**
	 * Get available services for admin settings.
	 *
	 * @return array
	 */
	public static function get_available_services(): array {
		return self::AVAILABLE_SERVICES;
	}

	/**
	 * Calculate shipping rates via Correios.
	 *
	 * @param array $package WooCommerce package data.
	 * @return array|false Array of rates or false on failure.
	 */
	public function calculate( array $package ) {
		if ( ! $this->is_enabled() ) {
			return false;
		}

		$destination_cep = $this->sanitize_cep( $package['destination']['postcode'] ?? '' );

		if ( empty( $destination_cep ) || strlen( $destination_cep ) !== 8 ) {
			$this->log_error( 'CEP de destino inválido: ' . ( $package['destination']['postcode'] ?? 'vazio' ) );
			return false;
		}

		// Get package dimensions and weight.
		$weight     = $this->get_total_weight( $package );
		$dimensions = $this->get_package_dimensions( $package );

		// Validate weight.
		if ( $weight > self::MAX_WEIGHT ) {
			$this->log_error( "Peso excede limite dos Correios: {$weight}kg > " . self::MAX_WEIGHT . 'kg' );
			return false;
		}

		// Build cache key.
		$cache_key = $this->build_cache_key( $destination_cep, $weight, $dimensions );

		// Check cache first.
		$cached_rates = get_transient( $cache_key );
		if ( false !== $cached_rates ) {
			$this->log_info( 'Usando cache para CEP: ' . $destination_cep );
			return $this->apply_profit_margin( $cached_rates );
		}

		// Call Correios API.
		$response = $this->call_correios_api( $destination_cep, $weight, $dimensions );

		if ( is_wp_error( $response ) ) {
			$this->log_error( 'Erro na API dos Correios: ' . $response->get_error_message() );
			
			// CONTINGENCY MODE: Use fallback rates when API fails
			if ( $this->is_contingency_enabled() ) {
				$state = $package['destination']['state'] ?? '';
				$contingency_rates = $this->get_contingency_rates( $state, $weight );
				
				if ( ! empty( $contingency_rates ) ) {
					$this->log_info( 'Ativado modo contingência para estado: ' . $state );
					return $this->apply_profit_margin( $contingency_rates );
				}
			}
			
			return false;
		}

		// Parse response.
		$rates = $this->parse_response( $response );

		if ( empty( $rates ) ) {
			$this->log_error( 'Nenhuma taxa válida retornada para CEP: ' . $destination_cep );
			
			// CONTINGENCY MODE: Use fallback rates when no valid rates returned
			if ( $this->is_contingency_enabled() ) {
				$state = $package['destination']['state'] ?? '';
				$contingency_rates = $this->get_contingency_rates( $state, $weight );
				
				if ( ! empty( $contingency_rates ) ) {
					$this->log_info( 'Ativado modo contingência (sem taxas) para estado: ' . $state );
					return $this->apply_profit_margin( $contingency_rates );
				}
			}
			
			return false;
		}

		// Cache the rates.
		set_transient( $cache_key, $rates, self::CACHE_DURATION );

		return $this->apply_profit_margin( $rates );
	}

	/**
	 * Call Correios API.
	 * 
	 * NOTE: The legacy SOAP WebService (ws.correios.com.br) was deprecated on August 31, 2023.
	 * The new REST API requires a contract and credentials from Correios.
	 * 
	 * For now, we use contingency rates (fixed regional pricing) as a reliable fallback.
	 * Users with Correios contracts can configure the new REST API credentials.
	 *
	 * @param string $cep_destino Destination CEP.
	 * @param float  $weight      Package weight in kg.
	 * @param array  $dimensions  Package dimensions.
	 * @return object|\WP_Error API response or error.
	 */
	private function call_correios_api( string $cep_destino, float $weight, array $dimensions ) {
		// Check if we have new REST API credentials configured
		if ( ! empty( $this->contract_code ) && ! empty( $this->contract_password ) ) {
			// Try the new REST API (requires contract)
			$result = $this->call_correios_rest_api( $cep_destino, $weight, $dimensions );
			if ( ! is_wp_error( $result ) ) {
				return $result;
			}
			// Log the REST API error but continue to try legacy as fallback
			$this->log_error( 'REST API error: ' . $result->get_error_message() . ' - Tentando API legada...' );
		}

		// Try legacy SOAP API (may not work - deprecated August 2023)
		if ( ! class_exists( 'SoapClient' ) ) {
			return new \WP_Error( 'soap_not_available', 'Extensão SOAP não está disponível e credenciais REST não configuradas.' );
		}

		try {
			// Aggressive timeout - 3 seconds max to avoid blocking checkout
			$client = new \SoapClient( self::CORREIOS_WS_URL, array(
				'trace'              => true,
				'exceptions'         => true,
				'connection_timeout' => 3,
				'cache_wsdl'         => WSDL_CACHE_BOTH,
			) );

			$params = array(
				'nCdEmpresa'          => $this->contract_code,
				'sDsSenha'            => $this->contract_password,
				'nCdServico'          => implode( ',', $this->services ),
				'sCepOrigem'          => $this->origin_cep,
				'sCepDestino'         => $cep_destino,
				'nVlPeso'             => $weight,
				'nCdFormato'          => 1, // 1 = Caixa/Pacote.
				'nVlComprimento'      => $dimensions['length'],
				'nVlAltura'           => $dimensions['height'],
				'nVlLargura'          => $dimensions['width'],
				'nVlDiametro'         => 0,
				'sCdMaoPropria'       => 'N',
				'nVlValorDeclarado'   => 0,
				'sCdAvisoRecebimento' => 'N',
			);

			$result = $client->CalcPrecoPrazo( $params );

			if ( isset( $result->CalcPrecoPrazoResult->Servicos->cServico ) ) {
				return $result->CalcPrecoPrazoResult->Servicos->cServico;
			}

			return new \WP_Error( 'invalid_response', 'Resposta inválida da API dos Correios.' );

		} catch ( \SoapFault $e ) {
			return new \WP_Error( 'soap_fault', 'API Correios indisponível (WebService SOAP foi descontinuado em 2023). Usando tabela de preços.' );
		} catch ( \Exception $e ) {
			return new \WP_Error( 'correios_error', $e->getMessage() );
		}
	}

	/**
	 * Call the new Correios REST API (requires contract).
	 *
	 * @param string $cep_destino Destination CEP.
	 * @param float  $weight      Package weight in kg.
	 * @param array  $dimensions  Package dimensions.
	 * @return object|\WP_Error API response or error.
	 */
	private function call_correios_rest_api( string $cep_destino, float $weight, array $dimensions ) {
		// New Correios REST API endpoint
		$api_url = 'https://cws.correios.com.br/preco-prazo/v1/preco';
		
		// First, get authorization token
		$token = $this->get_correios_auth_token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$body = array(
			'cepOrigem'    => $this->origin_cep,
			'cepDestino'   => $cep_destino,
			'psObjeto'     => (int) ( $weight * 1000 ), // Weight in grams
			'comprimento'  => (int) $dimensions['length'],
			'altura'       => (int) $dimensions['height'],
			'largura'      => (int) $dimensions['width'],
			'tpObjeto'     => 2, // 2 = Pacote/Caixa
		);

		$services_result = array();

		foreach ( $this->services as $service_code ) {
			$body['coProduto'] = $service_code;

			$response = wp_remote_post( $api_url, array(
				'timeout' => 5,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
					'Accept'        => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
			) );

			if ( is_wp_error( $response ) ) {
				continue;
			}

			$status = wp_remote_retrieve_response_code( $response );
			if ( $status !== 200 ) {
				continue;
			}

			$data = json_decode( wp_remote_retrieve_body( $response ) );
			if ( ! empty( $data ) && isset( $data->pcFinal ) ) {
				$services_result[] = (object) array(
					'Codigo'        => $service_code,
					'Valor'         => number_format( $data->pcFinal, 2, ',', '.' ),
					'PrazoEntrega'  => $data->prazoEntrega ?? 0,
					'Erro'          => '0',
					'MsgErro'       => '',
				);
			}
		}

		if ( empty( $services_result ) ) {
			return new \WP_Error( 'rest_api_failed', 'Nenhum serviço retornou preço válido.' );
		}

		return $services_result;
	}

	/**
	 * Get Correios API authorization token.
	 *
	 * @return string|\WP_Error Token or error.
	 */
	private function get_correios_auth_token() {
		// Check cached token
		$cached_token = get_transient( 'woo_envios_correios_token' );
		if ( ! empty( $cached_token ) ) {
			return $cached_token;
		}

		$auth_url = 'https://cws.correios.com.br/token/v1/autentica';
		
		$response = wp_remote_post( $auth_url, array(
			'timeout' => 5,
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( $this->contract_code . ':' . $this->contract_password ),
				'Content-Type'  => 'application/json',
			),
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status = wp_remote_retrieve_response_code( $response );
		if ( $status !== 200 && $status !== 201 ) {
			return new \WP_Error( 'auth_failed', 'Falha na autenticação com Correios. Verifique suas credenciais.' );
		}

		$data = json_decode( wp_remote_retrieve_body( $response ) );
		if ( empty( $data->token ) ) {
			return new \WP_Error( 'no_token', 'Token não retornado pela API dos Correios.' );
		}

		// Cache token for 5 hours (tokens are valid for ~6 hours)
		set_transient( 'woo_envios_correios_token', $data->token, 5 * 3600 ); // 5 hours

		return $data->token;
	}

	/**
	 * Parse Correios API response.
	 *
	 * @param object|array $response API response.
	 * @return array Parsed rates.
	 */
	private function parse_response( $response ): array {
		$rates = array();

		// Ensure we have an array of services.
		$services = is_array( $response ) ? $response : array( $response );

		foreach ( $services as $service ) {
			// Check for errors.
			$error_code = isset( $service->Erro ) ? (string) $service->Erro : '0';
			if ( '0' !== $error_code ) {
				$error_msg = isset( $service->MsgErro ) ? $service->MsgErro : 'Erro desconhecido';
				$this->log_error( "Correios serviço {$service->Codigo}: Erro {$error_code} - {$error_msg}" );
				continue;
			}

			// Parse price (Brazilian format: 1.234,56).
			$price_str = isset( $service->Valor ) ? $service->Valor : '0';
			$price     = (float) str_replace( array( '.', ',' ), array( '', '.' ), $price_str );

			if ( $price <= 0 ) {
				continue;
			}

			$service_code = (string) $service->Codigo;
			$service_name = self::AVAILABLE_SERVICES[ $service_code ] ?? 'Correios';
			$deadline     = isset( $service->PrazoEntrega ) ? (int) $service->PrazoEntrega : 0;

			$rates[] = array(
				'id'       => 'correios_' . $service_code,
				'code'     => $service_code,
				'label'    => $deadline > 0 
					? sprintf( '%s (%d dias úteis)', $service_name, $deadline )
					: $service_name,
				'cost'     => $price,
				'deadline' => $deadline,
			);
		}

		return $rates;
	}

	/**
	 * Apply profit margin to rates.
	 *
	 * @param array $rates Original rates.
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
	 * Get total weight from package.
	 *
	 * @param array $package WooCommerce package.
	 * @return float Weight in kg.
	 */
	private function get_total_weight( array $package ): float {
		$weight = 0;

		if ( empty( $package['contents'] ) ) {
			return 0.3; // Default minimum weight.
		}

		foreach ( $package['contents'] as $item ) {
			$product = $item['data'] ?? null;
			if ( ! $product ) {
				continue;
			}

			$item_weight = (float) $product->get_weight();
			$quantity    = (int) $item['quantity'];

			// Default weight for products without weight configured (300g per item).
			if ( $item_weight <= 0 ) {
				$item_weight = 0.3; // 300g default per item.
			}

			// Convert to kg if needed (WooCommerce weight unit).
			$weight_unit = get_option( 'woocommerce_weight_unit', 'kg' );
			switch ( $weight_unit ) {
				case 'g':
					$item_weight = $item_weight / 1000;
					break;
				case 'lbs':
					$item_weight = $item_weight * 0.453592;
					break;
				case 'oz':
					$item_weight = $item_weight * 0.0283495;
					break;
			}

			$weight += $item_weight * $quantity;
		}

		// Minimum weight for Correios.
		return max( $weight, 0.3 );
	}

	/**
	 * Get package dimensions using volumetric cubic root calculation.
	 *
	 * @param array $package WooCommerce package.
	 * @return array Dimensions with height, width, length.
	 */
	private function get_package_dimensions( array $package ): array {
		if ( empty( $package['contents'] ) ) {
			return array(
				'height' => self::MIN_HEIGHT,
				'width'  => self::MIN_WIDTH,
				'length' => self::MIN_LENGTH,
			);
		}

		$total_volume = 0;
		$max_length   = 0;
		$max_width    = 0;

		// Get dimension unit factor.
		$dim_unit = get_option( 'woocommerce_dimension_unit', 'cm' );
		$factor   = 1;
		switch ( $dim_unit ) {
			case 'm':
				$factor = 100;
				break;
			case 'mm':
				$factor = 0.1;
				break;
			case 'in':
				$factor = 2.54;
				break;
			case 'yd':
				$factor = 91.44;
				break;
		}

		foreach ( $package['contents'] as $item ) {
			$product = $item['data'] ?? null;
			if ( ! $product ) {
				continue;
			}

			$quantity = (int) $item['quantity'];

			// Get dimensions with smart defaults for products without data.
			$item_height = (float) $product->get_height();
			$item_width  = (float) $product->get_width();
			$item_length = (float) $product->get_length();

			// Apply defaults for products without dimensions (e.g., digital or misconfigured).
			if ( $item_height <= 0 ) $item_height = 5;  // 5cm default.
			if ( $item_width <= 0 )  $item_width  = 10; // 10cm default.
			if ( $item_length <= 0 ) $item_length = 15; // 15cm default.

			// Convert to cm.
			$item_height *= $factor;
			$item_width  *= $factor;
			$item_length *= $factor;

			// Calculate volume for this item.
			$item_volume = $item_height * $item_width * $item_length * $quantity;
			$total_volume += $item_volume;

			// Track max dimensions for non-stackable items.
			$max_length = max( $max_length, $item_length );
			$max_width  = max( $max_width, $item_width );
		}

		// Use cubic root for volumetric dimension estimation.
		// This creates a virtual "box" that could contain all items.
		$cubic_dim = pow( $total_volume, 1 / 3 );

		// Smart dimension calculation:
		// - Length: max of individual lengths or cubic estimate.
		// - Width: max of individual widths or cubic estimate.
		// - Height: calculated from volume / (length * width).
		$final_length = max( $max_length, $cubic_dim );
		$final_width  = max( $max_width, min( $cubic_dim, $final_length ) );
		$final_height = $total_volume > 0 ? $total_volume / ( $final_length * $final_width ) : $cubic_dim;

		// Apply minimum dimensions required by Correios.
		return array(
			'height' => max( round( $final_height, 1 ), self::MIN_HEIGHT ),
			'width'  => max( round( $final_width, 1 ), self::MIN_WIDTH ),
			'length' => max( round( $final_length, 1 ), self::MIN_LENGTH ),
		);
	}

	/**
	 * Build cache key.
	 *
	 * @param string $cep        Destination CEP.
	 * @param float  $weight     Package weight.
	 * @param array  $dimensions Package dimensions.
	 * @return string Cache key.
	 */
	private function build_cache_key( string $cep, float $weight, array $dimensions ): string {
		$key_data = array(
			'o' => $this->origin_cep,
			'd' => $cep,
			'w' => round( $weight, 1 ),
			'h' => (int) $dimensions['height'],
			'l' => (int) $dimensions['width'],
			'c' => (int) $dimensions['length'],
			's' => implode( '-', $this->services ),
		);

		return 'woo_envios_correios_' . md5( wp_json_encode( $key_data ) );
	}

	/**
	 * Sanitize CEP.
	 *
	 * @param string $cep CEP to sanitize.
	 * @return string Sanitized CEP (only digits).
	 */
	private function sanitize_cep( string $cep ): string {
		return preg_replace( '/[^0-9]/', '', $cep );
	}

	/**
	 * Log error message.
	 *
	 * @param string $message Error message.
	 */
	private function log_error( string $message ): void {
		if ( class_exists( 'Woo_Envios_Logger' ) ) {
			\Woo_Envios_Logger::error( '[Correios] ' . $message );
		}
	}

	/**
	 * Log info message.
	 *
	 * @param string $message Info message.
	 */
	private function log_info( string $message ): void {
		if ( class_exists( 'Woo_Envios_Logger' ) ) {
			\Woo_Envios_Logger::info( '[Correios] ' . $message );
		}
	}

	/**
	 * Check if contingency mode is enabled.
	 *
	 * @return bool
	 */
	private function is_contingency_enabled(): bool {
		return (bool) get_option( 'woo_envios_correios_contingency_enabled', true );
	}

	/**
	 * Get contingency rates based on destination state.
	 *
	 * @param string $state Destination state (UF).
	 * @param float  $weight Package weight in kg.
	 * @return array Array of fallback rates.
	 */
	private function get_contingency_rates( string $state, float $weight ): array {
		$state = strtoupper( trim( $state ) );
		
		// Check for custom rates first (from admin settings).
		$custom_rates = get_option( 'woo_envios_correios_contingency_rates', array() );
		if ( ! empty( $custom_rates[ $state ] ) ) {
			$region_rates = $custom_rates[ $state ];
		} elseif ( isset( self::CONTINGENCY_RATES[ $state ] ) ) {
			$region_rates = self::CONTINGENCY_RATES[ $state ];
		} else {
			// Default fallback for unknown states (use highest prices).
			$region_rates = array(
				'pac'           => 60.00,
				'sedex'         => 85.00,
				'deadline_pac'  => 20,
				'deadline_sedex' => 10,
			);
		}

		// Apply weight multiplier for packages over 1kg.
		$weight_multiplier = 1.0;
		if ( $weight > 1 ) {
			// Add 10% per additional kg (simplified).
			$weight_multiplier = 1 + ( ( $weight - 1 ) * 0.10 );
			$weight_multiplier = min( $weight_multiplier, 3.0 ); // Cap at 3x.
		}

		$rates = array();

		// Only add services that are enabled.
		if ( in_array( '04510', $this->services, true ) && isset( $region_rates['pac'] ) ) {
			$pac_price = round( $region_rates['pac'] * $weight_multiplier, 2 );
			$rates[] = array(
				'id'       => 'correios_04510',
				'code'     => '04510',
				'label'    => sprintf( 'PAC (%d dias úteis)*', $region_rates['deadline_pac'] ),
				'cost'     => $pac_price,
				'deadline' => $region_rates['deadline_pac'],
			);
		}

		if ( in_array( '04014', $this->services, true ) && isset( $region_rates['sedex'] ) ) {
			$sedex_price = round( $region_rates['sedex'] * $weight_multiplier, 2 );
			$rates[] = array(
				'id'       => 'correios_04014',
				'code'     => '04014',
				'label'    => sprintf( 'SEDEX (%d dias úteis)*', $region_rates['deadline_sedex'] ),
				'cost'     => $sedex_price,
				'deadline' => $region_rates['deadline_sedex'],
			);
		}

		return $rates;
	}

	/**
	 * Get default contingency rates for admin display.
	 *
	 * @return array
	 */
	public static function get_default_contingency_rates(): array {
		return self::CONTINGENCY_RATES;
	}
}
