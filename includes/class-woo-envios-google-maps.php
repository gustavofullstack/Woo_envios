<?php
/**
 * Google Maps API Integration.
 *
 * Handles all interactions with Google Maps APIs:
 * - Geocoding API: Convert addresses to coordinates
 * - Places API: Autocomplete and address validation
 * - Distance Matrix API: Calculate distances for shipping
 *
 * @package UDI_Custom_Login
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Woo_Envios_Google_Maps {

	/**
	 * Google Maps API Key option name.
	 */
	private const API_KEY_OPTION = 'woo_envios_google_maps_api_key';

	/**
	 * Circuit breaker: Max consecutive failures before disabling API calls.
	 */
	private const MAX_CONSECUTIVE_FAILURES = 5;

	/**
	 * Retry attempts for API calls.
	 */
	private const MAX_RETRIES = 3;

	/**
	 * Request timeout in seconds.
	 */
	private const REQUEST_TIMEOUT = 10;

	/**
	 * Google Maps API Key.
	 *
	 * @var string
	 */
	protected $api_key;

	/**
	 * Cache TTL in seconds (default 30 days).
	 *
	 * @var int
	 */
	protected $cache_ttl;

	/**
	 * API base URLs.
	 *
	 * @var array
	 */
	protected $api_urls = array(
		'geocode'        => 'https://maps.googleapis.com/maps/api/geocode/json',
		'places'         => 'https://maps.googleapis.com/maps/api/place/autocomplete/json',
		'place_details'  => 'https://maps.googleapis.com/maps/api/place/details/json',
		'distance'       => 'https://maps.googleapis.com/maps/api/distancematrix/json',
	);

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->api_key   = get_option( self::API_KEY_OPTION, '' );
		// Use numeric value (30 days in seconds) since DAY_IN_SECONDS may not be defined yet
		$this->cache_ttl = (int) get_option( 'udi_google_maps_cache_ttl', 30 * 86400 );
	}

	/**
	 * Get the Google Maps API Key.
	 *
	 * @return string
	 */
	private function get_api_key() {
		return $this->api_key;
	}

	/**
	 * Check if Google Maps is properly configured.
	 *
	 * @return bool
	 */
	public function is_configured() {
		$api_key = $this->get_api_key();
		return ! empty( $api_key ) && $this->validate_api_key_format( $api_key );
	}

	/**
	 * Validate API key format.
	 *
	 * @param string $api_key API key to validate.
	 * @return bool True if format is valid.
	 */
	private function validate_api_key_format( $api_key ) {
		// Google API keys are 39 characters starting with AIza
		if ( empty( $api_key ) ) {
			return false;
		}

		// Basic format validation
		if ( strlen( $api_key ) !== 39 || strpos( $api_key, 'AIza' ) !== 0 ) {
			error_log( 'Woo Envios: Invalid Google Maps API key format' );
			return false;
		}

		return true;
	}

	/**
	 * Check if circuit breaker is open (too many failures).
	 *
	 * @return bool True if circuit is open (API calls disabled).
	 */
	private function is_circuit_open() {
		$failures = get_transient( 'woo_envios_api_failures' );
		return $failures && $failures >= self::MAX_CONSECUTIVE_FAILURES;
	}

	/**
	 * Record API failure for circuit breaker.
	 */
	private function record_failure() {
		$failures = get_transient( 'woo_envios_api_failures' ) ?: 0;
		$failures++;
		set_transient( 'woo_envios_api_failures', $failures, 3600 ); // 1 hour
	
		if ( $failures >= self::MAX_CONSECUTIVE_FAILURES ) {
			error_log( sprintf( 'Woo Envios: Circuit breaker opened after %d failures', $failures ) );
			
			// Notify via logger (sends email to admin)
			if ( class_exists( 'Woo_Envios_Logger' ) ) {
				Woo_Envios_Logger::circuit_breaker_opened( $failures );
			}
		}
	}

	/**
	 * Record API success and reset circuit breaker.
	 */
	private function record_success() {
		delete_transient( 'woo_envios_api_failures' );
	}

	/**
	 * Geocode an address to get coordinates and components.
	 *
	 * @param string $address Full address string.
	 * @return array|WP_Error Array with lat, lng, and address components, or error.
	 */
	public function geocode_address( $address ) {
		if ( ! $this->is_configured() ) {
			return new WP_Error( 'not_configured', __( 'Google Maps API não está configurada.', 'woo-envios' ) );
		}

		// Check circuit breaker.
		if ( $this->is_circuit_open() ) {
			error_log( 'Woo Envios: Circuit breaker is open, using fallback coordinates' );
			return $this->get_fallback_coordinates();
		}

		// Check cache first.
		$cache_key = $this->get_cache_key( $address );
		$cached    = $this->get_cached_result( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		// Retry logic with exponential backoff.
		$attempt = 0;
		$result  = null;

		while ( $attempt < self::MAX_RETRIES ) {
			$attempt++;

			// Make API request.
			$url = add_query_arg(
				array(
					'address' => rawurlencode( $address ),
					'key'     => $this->api_key,
					'region'  => 'br', // Prioritize Brazilian results.
				),
				$this->api_urls['geocode']
			);

			$response = wp_remote_get(
				$url,
				array(
					'timeout' => self::REQUEST_TIMEOUT,
				)
			);

			if ( is_wp_error( $response ) ) {
				// Network error - retry with backoff.
				if ( $attempt < self::MAX_RETRIES ) {
					$backoff = min( 1000 * pow( 2, $attempt - 1 ), 5000 ); // Max 5 seconds.
					usleep( $backoff * 1000 ); // Convert to microseconds.
					continue;
				}
				$this->record_failure();
				return $response;
			}

			$body = wp_remote_retrieve_body( $response );
			$data = json_decode( $body, true );

			// Handle rate limiting.
			if ( ! empty( $data['status'] ) && 'OVER_QUERY_LIMIT' === $data['status'] ) {
				if ( $attempt < self::MAX_RETRIES ) {
					sleep( 2 * $attempt ); // Longer backoff for rate limiting.
					continue;
				}
				$this->record_failure();
				return new WP_Error( 'rate_limit', __( 'Limite de requisições excedido. Tente novamente em alguns instantes.', 'woo-envios' ) );
			}

			if ( empty( $data['status'] ) || 'OK' !== $data['status'] ) {
				$error_message = ! empty( $data['error_message'] ) ? $data['error_message'] : __( 'Erro ao validar endereço.', 'woo-envios' );
				
				// Don't retry for invalid inputs.
				if ( in_array( $data['status'], array( 'INVALID_REQUEST', 'ZERO_RESULTS' ), true ) ) {
					return new WP_Error( 'geocode_failed', $error_message );
				}

				// Retry for other errors.
				if ( $attempt < self::MAX_RETRIES ) {
					usleep( 500000 ); // 500ms backoff.
					continue;
				}

				$this->record_failure();
				return new WP_Error( 'geocode_failed', $error_message );
			}

			if ( empty( $data['results'][0] ) ) {
				return new WP_Error( 'no_results', __( 'Nenhum resultado encontrado para este endereço.', 'woo-envios' ) );
			}

			// Success!
			$this->record_success();
			$result = $data['results'][0];
			$output = array(
				'latitude'           => $result['geometry']['location']['lat'],
				'longitude'          => $result['geometry']['location']['lng'],
				'formatted_address'  => $result['formatted_address'],
				'address_components' => $this->parse_address_components( $result['address_components'] ),
				'place_id'           => $result['place_id'],
			);

			// Cache the result.
			$this->cache_result( $cache_key, $output );

			return $output;
		}

		// If we get here, all retries failed.
		$this->record_failure();
		return $this->get_fallback_coordinates();
	}

	/**
	 * Get fallback coordinates when geocoding fails.
	 *
	 * @return array Default coordinates for Uberlândia, MG.
	 */
	private function get_fallback_coordinates() {
		if ( defined( 'WOO_ENVIOS_DEFAULT_LAT' ) && defined( 'WOO_ENVIOS_DEFAULT_LNG' ) ) {
			return array(
				'latitude'           => WOO_ENVIOS_DEFAULT_LAT,
				'longitude'          => WOO_ENVIOS_DEFAULT_LNG,
				'formatted_address'  => 'Uberlândia, MG, Brasil',
				'address_components' => array(),
				'place_id'           => '',
				'is_fallback'        => true,
			);
		}

		return new WP_Error( 'fallback_failed', __( 'Não foi possível obter coordenadas.', 'woo-envios' ) );
	}

	/**
	 * Get address autocomplete suggestions.
	 *
	 * @param string $input User input for autocomplete.
	 * @param array  $options Optional. Additional options like types, location bias.
	 * @return array|WP_Error Array of predictions or error.
	 */
	public function autocomplete_address( $input, $options = array() ) {
		if ( ! $this->is_configured() ) {
			return new WP_Error( 'not_configured', __( 'Google Maps API não está configurada.', 'woo-envios' ) );
		}

		if ( strlen( $input ) < 3 ) {
			return array(); // Too short, no suggestions.
		}

		$args = array_merge(
			array(
				'input'      => $input,
				'key'        => $this->api_key,
				'language'   => 'pt-BR',
				'components' => 'country:br', // Restrict to Brazil.
			),
			$options
		);

		$url = add_query_arg( $args, $this->api_urls['places'] );

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 5,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( empty( $data['status'] ) || ! in_array( $data['status'], array( 'OK', 'ZERO_RESULTS' ), true ) ) {
			$error_message = ! empty( $data['error_message'] ) ? $data['error_message'] : __( 'Erro ao buscar sugestões.', 'woo-envios' );
			return new WP_Error( 'autocomplete_failed', $error_message );
		}

		if ( 'ZERO_RESULTS' === $data['status'] || empty( $data['predictions'] ) ) {
			return array();
		}

		return $data['predictions'];
	}

	/**
	 * Get detailed information about a place using place_id.
	 *
	 * @param string $place_id Google Place ID.
	 * @return array|WP_Error Place details or error.
	 */
	public function get_place_details( $place_id ) {
		if ( ! $this->is_configured() ) {
			return new WP_Error( 'not_configured', __( 'Google Maps API não está configurada.', 'woo-envios' ) );
		}

		// Check cache.
		$cache_key = 'place_' . md5( $place_id );
		$cached    = $this->get_cached_result( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		$url = add_query_arg(
			array(
				'place_id' => $place_id,
				'key'      => $this->api_key,
				'language' => 'pt-BR',
				'fields'   => 'address_components,formatted_address,geometry,place_id',
			),
			$this->api_urls['place_details']
		);

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( empty( $data['status'] ) || 'OK' !== $data['status'] ) {
			$error_message = ! empty( $data['error_message'] ) ? $data['error_message'] : __( 'Erro ao buscar detalhes do endereço.', 'woo-envios' );
			return new WP_Error( 'place_details_failed', $error_message );
		}

		$result = $data['result'];
		$output = array(
			'latitude'           => $result['geometry']['location']['lat'],
			'longitude'          => $result['geometry']['location']['lng'],
			'formatted_address'  => $result['formatted_address'],
			'address_components' => $this->parse_address_components( $result['address_components'] ),
			'place_id'           => $result['place_id'],
		);

		$this->cache_result( $cache_key, $output );

		return $output;
	}

	/**
	 * Calculate distance between two addresses.
	 *
	 * @param string $origin      Origin address or coordinates.
	 * @param string $destination Destination address or coordinates.
	 * @return array|WP_Error Distance data or error.
	 */
	public function calculate_distance( $origin, $destination ) {
		if ( ! $this->is_configured() ) {
			return new WP_Error( 'not_configured', __( 'Google Maps API não está configurada.', 'woo-envios' ) );
		}

		// Check cache.
		$cache_key = 'distance_' . md5( $origin . '|' . $destination );
		$cached    = $this->get_cached_result( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		$url = add_query_arg(
			array(
				'origins'      => rawurlencode( $origin ),
				'destinations' => rawurlencode( $destination ),
				'key'          => $this->api_key,
				'language'     => 'pt-BR',
			),
			$this->api_urls['distance']
		);

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( empty( $data['status'] ) || 'OK' !== $data['status'] ) {
			$error_message = ! empty( $data['error_message'] ) ? $data['error_message'] : __( 'Erro ao calcular distância.', 'woo-envios' );
			return new WP_Error( 'distance_failed', $error_message );
		}

		if ( empty( $data['rows'][0]['elements'][0] ) ) {
			return new WP_Error( 'no_route', __( 'Não foi possível calcular a rota.', 'woo-envios' ) );
		}

		$element = $data['rows'][0]['elements'][0];

		if ( 'OK' !== $element['status'] ) {
			return new WP_Error( 'route_error', __( 'Rota não encontrada.', 'woo-envios' ) );
		}

		$output = array(
			'distance_text'  => $element['distance']['text'],
			'distance_value' => $element['distance']['value'], // In meters.
			'duration_text'  => $element['duration']['text'],
			'duration_value' => $element['duration']['value'], // In seconds.
		);

		$this->cache_result( $cache_key, $output, 604800 ); // Cache for 7 days (7 * 86400)

		return $output;
	}

	/**
	 * Validate a Brazilian CEP (postal code).
	 *
	 * @param string $cep CEP to validate.
	 * @return array|WP_Error Address data or error.
	 */
	public function validate_cep( $cep ) {
		// Clean CEP.
		$cep = preg_replace( '/[^0-9]/', '', $cep );

		if ( strlen( $cep ) !== 8 ) {
			return new WP_Error( 'invalid_cep', __( 'CEP inválido. Digite 8 dígitos.', 'woo-envios' ) );
		}

		// Format CEP.
		$formatted_cep = substr( $cep, 0, 5 ) . '-' . substr( $cep, 5 );

		// Use geocoding to validate.
		return $this->geocode_address( $formatted_cep . ', Brasil' );
	}

	/**
	 * Test API connection and permissions.
	 *
	 * @return array Test results with status for each API.
	 */
	public function test_connection() {
		$results = array(
			'geocoding' => false,
			'places'    => false,
			'distance'  => false,
			'errors'    => array(),
		);

		// Test Geocoding API.
		$geocode_test = $this->geocode_address( 'Avenida Paulista, 1578, São Paulo - SP' );
		if ( ! is_wp_error( $geocode_test ) ) {
			$results['geocoding'] = true;
		} else {
			$results['errors']['geocoding'] = $geocode_test->get_error_message();
		}

		// Test Places API.
		$places_test = $this->autocomplete_address( 'Avenida Paulista' );
		if ( ! is_wp_error( $places_test ) && ! empty( $places_test ) ) {
			$results['places'] = true;
		} else {
			$results['errors']['places'] = is_wp_error( $places_test ) ? $places_test->get_error_message() : __( 'Nenhum resultado.', 'woo-envios' );
		}

		// Test Distance Matrix API.
		$distance_test = $this->calculate_distance(
			'Avenida Paulista, São Paulo',
			'Praça da Sé, São Paulo'
		);
		if ( ! is_wp_error( $distance_test ) ) {
			$results['distance'] = true;
		} else {
			$results['errors']['distance'] = $distance_test->get_error_message();
		}

		return $results;
	}

	/**
	 * Parse address components from Google response.
	 *
	 * @param array $components Raw address components from Google.
	 * @return array Parsed components.
	 */
	protected function parse_address_components( $components ) {
		$parsed = array(
			'street_number' => '',
			'route'         => '',
			'neighborhood'  => '',
			'city'          => '',
			'state'         => '',
			'state_code'    => '',
			'country'       => '',
			'postal_code'   => '',
		);

		foreach ( $components as $component ) {
			$types = $component['types'];

			if ( in_array( 'street_number', $types, true ) ) {
				$parsed['street_number'] = $component['long_name'];
			} elseif ( in_array( 'route', $types, true ) ) {
				$parsed['route'] = $component['long_name'];
			} elseif ( in_array( 'sublocality', $types, true ) || in_array( 'sublocality_level_1', $types, true ) ) {
				$parsed['neighborhood'] = $component['long_name'];
			} elseif ( in_array( 'administrative_area_level_2', $types, true ) ) {
				$parsed['city'] = $component['long_name'];
			} elseif ( in_array( 'administrative_area_level_1', $types, true ) ) {
				$parsed['state']      = $component['long_name'];
				$parsed['state_code'] = $component['short_name'];
			} elseif ( in_array( 'country', $types, true ) ) {
				$parsed['country'] = $component['long_name'];
			} elseif ( in_array( 'postal_code', $types, true ) ) {
				$parsed['postal_code'] = $component['long_name'];
			}
		}

		return $parsed;
	}

	/**
	 * Get cache key for an address.
	 *
	 * @param string $address Address string.
	 * @return string Cache key.
	 */
	protected function get_cache_key( $address ) {
		return 'geocode_' . md5( strtolower( trim( $address ) ) );
	}

	/**
	 * Get cached result from database.
	 *
	 * @param string $cache_key Cache key.
	 * @return mixed|false Cached data or false if not found/expired.
	 */
	protected function get_cached_result( $cache_key ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'woo_envios_geocode_cache';

		// Check if table exists.
		if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) ) !== $table_name ) {
			return false;
		}

		$cached = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table_name WHERE cache_key = %s AND expires_at > NOW()",
				$cache_key
			)
		);

		if ( ! $cached ) {
			return false;
		}

		return maybe_unserialize( $cached->result_data );
	}

	/**
	 * Cache a result in database.
	 *
	 * @param string $cache_key Cache key.
	 * @param mixed  $data      Data to cache.
	 * @param int    $ttl       Optional. Time to live in seconds. Default uses class TTL.
	 * @return bool Success status.
	 */
	protected function cache_result( $cache_key, $data, $ttl = null ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'woo_envios_geocode_cache';

		// Check if table exists.
		if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) ) !== $table_name ) {
			return false;
		}

		$ttl        = $ttl ?? $this->cache_ttl;
		$expires_at = gmdate( 'Y-m-d H:i:s', time() + $ttl );

		$wpdb->replace(
			$table_name,
			array(
				'cache_key'   => $cache_key,
				'result_data' => maybe_serialize( $data ),
				'created_at'  => current_time( 'mysql' ),
				'expires_at'  => $expires_at,
			),
			array( '%s', '%s', '%s', '%s' )
		);

		return true;
	}

	/**
	 * Clear expired cache entries.
	 *
	 * @return int Number of deleted entries.
	 */
	public function clear_expired_cache() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'woo_envios_geocode_cache';

		return $wpdb->query( "DELETE FROM $table_name WHERE expires_at < NOW()" );
	}

	/**
	 * Clear all cache entries.
	 *
	 * @return int Number of deleted entries.
	 */
	public function clear_all_cache() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'woo_envios_geocode_cache';

		return $wpdb->query( "TRUNCATE TABLE $table_name" );
	}
}
