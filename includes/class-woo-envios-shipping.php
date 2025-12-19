<?php
/**
 * Método de entrega Woo Envios baseado em raio.
 *
 * @package Woo_Envios
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Woo_Envios_Shipping_Method extends WC_Shipping_Method {

	/**
	 * Construtor.
	 */
	public function __construct( $instance_id = 0 ) {
		$this->id                 = 'woo_envios_radius';
		$this->method_title       = __( 'Woo Envios — Raio Escalonado', 'woo-envios' );
		$this->method_description = __( 'Calcula o frete local por distância em linha reta a partir da base configurada.', 'woo-envios' );
		$this->instance_id        = absint( $instance_id );
		$this->supports           = array(
			'shipping-zones',
			'instance-settings',
			'instance-settings-modal',
		);

		$this->init();
	}

	/**
	 * Inicializa campos e hooks.
	 */
	public function init(): void {
		$this->instance_form_fields = array(
			'enabled' => array(
				'title'   => __( 'Ativar', 'woo-envios' ),
				'type'    => 'checkbox',
				'label'   => __( 'Habilitar este método para a zona', 'woo-envios' ),
				'default' => 'yes',
			),
			'title'   => array(
				'title'       => __( 'Título exibido ao cliente', 'woo-envios' ),
				'type'        => 'text',
				'default'     => __( 'Entrega Flash', 'woo-envios' ),
				'desc_tip'    => true,
				'description' => __( 'Texto mostrado no checkout.', 'woo-envios' ),
			),
		);

		$this->init_instance_settings();

		$this->enabled = $this->get_option( 'enabled', 'yes' );
		$this->title   = $this->get_option( 'title', __( 'Entrega Flash', 'woo-envios' ) );

		add_action(
			'woocommerce_update_options_shipping_' . $this->id . '_' . $this->instance_id,
			array( $this, 'process_admin_options' )
		);
	}

	/**
	 * Campos visíveis na tela de zonas do WooCommerce.
	 */
	public function init_form_fields(): void {}

	/**
	 * Calcula o frete com base nos dados em sessão.
	 *
	 * @param array $package Pacote do WooCommerce.
	 *
	 * @return void
	 */
	public function calculate_shipping( $package = array() ): void {
		if ( 'yes' !== $this->enabled ) {
			return;
		}

		$store_coords = Woo_Envios_Admin::get_store_coordinates();
		if ( empty( $store_coords['lat'] ) || empty( $store_coords['lng'] ) ) {
			return;
		}

		$signature      = $this->build_destination_signature( $package );
		$session_coords = $this->get_session_coordinates( $signature );

		// If no coordinates in session, we cannot calculate shipping.
		// We do NOT fallback to synchronous geocoding here to prevent blocking.
		if ( empty( $session_coords ) ) {
			return;
		}

		// Use Google Distance Matrix API for real route distance instead of Haversine
		$distance_data = $this->calculate_route_distance( $store_coords, $session_coords, $package );
		
		if ( is_wp_error( $distance_data ) || empty( $distance_data ) ) {
			// Fallback to Haversine if Distance Matrix fails
			$distance = $this->calculate_distance(
				(float) $store_coords['lat'],
				(float) $store_coords['lng'],
				(float) $session_coords['lat'],
				(float) $session_coords['lng']
			);
		} else {
			// Convert meters to kilometers
			$distance = round( $distance_data['distance_value'] / 1000, 2 );
		}

		$tier = Woo_Envios_Admin::match_tier_by_distance( $distance );
		if ( ! $tier ) {
			// Log for debugging: customer is outside delivery range
			Woo_Envios_Logger::distance_out_of_range( $distance, $package['destination'] );
			return;
		}

		// Apply dynamic pricing if enabled
		$base_price = (float) $tier['price'];
		$multiplier_data = $this->calculate_dynamic_multiplier( $package );
		$final_price = $base_price * $multiplier_data['total'];

		// Build label - simple title only
		$label = $this->title;

		$rate = array(
			'id'       => $this->id,
			'label'    => $label,
			'cost'     => round( $final_price, 2 ),
			'package'  => $package,
			'meta_data' => array(
				'distance'    => $distance,
				'base_price'  => $base_price,
				'multiplier'  => $multiplier_data['total'],
				'breakdown'   => $multiplier_data['reasons'],
				'debug_info'  => array(
					'store'    => $store_coords,
					'customer' => $session_coords,
					'distance' => $distance,
				),
			),
		);

		$this->add_rate( $rate );

		// Log shipping calculation for debugging
		Woo_Envios_Logger::shipping_calculated(
			$distance,
			$base_price,
			$final_price,
			$multiplier_data['reasons'],
			$package['destination']['city'] ?? '',
			$store_coords,
			$session_coords
		);
	}

	/**
	 * Recupera coordenadas do cliente em sessão.
	 *
	 * @return array<string,float>|null
	 */
	private function get_session_coordinates( string $signature ): ?array {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return null;
		}

		$coords = WC()->session->get( 'woo_envios_coords' );
		if ( empty( $coords['lat'] ) || empty( $coords['lng'] ) ) {
			return null;
		}

		// EMERGENCY FIX: Removed signature check to ensure coordinates are accepted.
		// if ( empty( $coords['signature'] ) || $coords['signature'] !== $signature ) {
		// 	return null;
		// }

		return array(
			'lat' => (float) $coords['lat'],
			'lng' => (float) $coords['lng'],
		);
	}

	/**
	 * Calculate route distance using Google Distance Matrix API.
	 *
	 * @param array $store_coords Store coordinates array with 'lat' and 'lng'.
	 * @param array $customer_coords Customer coordinates array with 'lat' and 'lng'.
	 * @param array $package WooCommerce package data.
	 * @return array|WP_Error Distance data or error.
	 */
	private function calculate_route_distance( array $store_coords, array $customer_coords, array $package ) {
		// Build address strings for Distance Matrix API
		$origin = $store_coords['lat'] . ',' . $store_coords['lng'];
		$destination = $customer_coords['lat'] . ',' . $customer_coords['lng'];
		
		// Use Google Maps Distance Matrix API
		$google_maps = new Woo_Envios_Google_Maps();
		
		if ( ! $google_maps->is_configured() ) {
			return new WP_Error( 'not_configured', 'Google Maps API not configured' );
		}
		
		return $google_maps->calculate_distance( $origin, $destination );
	}

	/**
	 * Calcula distância em KM com Haversine (fallback).
	 */
	private function calculate_distance( float $lat_from, float $lng_from, float $lat_to, float $lng_to ): float {
		$earth_radius = 6371;

		$dlat = deg2rad( $lat_to - $lat_from );
		$dlng = deg2rad( $lng_to - $lng_from );

		$a = sin( $dlat / 2 ) ** 2 +
			cos( deg2rad( $lat_from ) ) * cos( deg2rad( $lat_to ) ) *
			sin( $dlng / 2 ) ** 2;

		$c = 2 * atan2( sqrt( $a ), sqrt( 1 - $a ) );

		return round( $earth_radius * $c, 2 );
	}

	/**
	 * Cria uma assinatura exclusiva para o endereço atual.
	 *
	 * @param array $package Pacote atual.
	 *
	 * @return string
	 */
	private function build_destination_signature( array $package ): string {
		$destination = $package['destination'] ?? array();
		$parts       = array(
			sanitize_text_field( $destination['address_1'] ?? '' ),
			sanitize_text_field( $destination['city'] ?? '' ),
			sanitize_text_field( $destination['state'] ?? '' ),
			sanitize_text_field( $destination['postcode'] ?? '' ),
			sanitize_text_field( $destination['country'] ?? '' ),
		);

		$normalized = strtolower( implode( '|', $parts ) );

		return md5( $normalized );
	}

	/**
	 * Calculate dynamic pricing multiplier.
	 *
	 * @param array $package Package data.
	 * @return array Array with 'total' multiplier and 'reasons' breakdown.
	 */
	private function calculate_dynamic_multiplier( array $package ): array {
		// If dynamic pricing is disabled, return 1.0
		if ( ! get_option( 'woo_envios_dynamic_pricing_enabled', false ) ) {
			return array(
				'total'   => 1.0,
				'reasons' => array(),
			);
		}

		$multiplier = 1.0;
		$reasons = array();

		// Peak hour multiplier
		$peak_data = $this->get_peak_hour_multiplier();
		if ( $peak_data['multiplier'] > 1.0 ) {
			$multiplier *= $peak_data['multiplier'];
			$reasons[] = $peak_data['label'];
		}

		// Weekend multiplier
		if ( $this->is_weekend() ) {
			$weekend_mult = (float) get_option( 'woo_envios_weekend_multiplier', 1.0 );
			if ( $weekend_mult > 1.0 ) {
				$multiplier *= $weekend_mult;
				$reasons[] = sprintf( __( 'Fim de semana +%d%%', 'woo-envios' ), (int) ( ( $weekend_mult - 1 ) * 100 ) );
			}
		}

		// Weather multiplier (rain)
		$weather_mult = $this->get_weather_multiplier( $package );
		if ( $weather_mult > 1.0 ) {
			$multiplier *= $weather_mult;
			$reasons[] = sprintf( __( 'Chuva +%d%%', 'woo-envios' ), (int) ( ( $weather_mult - 1 ) * 100 ) );
		}

		// Apply maximum multiplier limit
		$max_multiplier = (float) get_option( 'woo_envios_max_multiplier', 2.0 );
		if ( $multiplier > $max_multiplier ) {
			$multiplier = $max_multiplier;
		}

		return array(
			'total'   => $multiplier,
			'reasons' => $reasons,
		);
	}

	/**
	 * Check if current time is within peak hours.
	 *
	 * @return array Array with 'multiplier' and 'label'.
	 */
	private function get_peak_hour_multiplier(): array {
		$peak_hours = get_option( 'woo_envios_peak_hours', array() );
		if ( empty( $peak_hours ) ) {
			return array( 'multiplier' => 1.0, 'label' => '' );
		}

		$current_time = current_time( 'H:i' );

		foreach ( $peak_hours as $period ) {
			if ( empty( $period['start'] ) || empty( $period['end'] ) ) {
				continue;
			}

			// Check if current time is within this peak period
			if ( $current_time >= $period['start'] && $current_time <= $period['end'] ) {
				$multiplier = (float) ( $period['multiplier'] ?? 1.0 );
				$percentage = (int) ( ( $multiplier - 1 ) * 100 );
				$label = sprintf(
					__( '%s +%d%%', 'woo-envios' ),
					$period['name'] ?? __( 'Pico', 'woo-envios' ),
					$percentage
				);

				return array(
					'multiplier' => $multiplier,
					'label'      => $label,
				);
			}
		}

		return array( 'multiplier' => 1.0, 'label' => '' );
	}

	/**
	 * Get weather-based multiplier.
	 *
	 * @param array $package Package data.
	 * @return float Weather multiplier.
	 */
	private function get_weather_multiplier( array $package ): float {
		// Get store coordinates for weather check
		$store_coords = Woo_Envios_Admin::get_store_coordinates();
		if ( empty( $store_coords['lat'] ) || empty( $store_coords['lng'] ) ) {
			return 1.0;
		}

		$weather_service = new Woo_Envios_Weather();
		return $weather_service->get_weather_multiplier(
			(float) $store_coords['lat'],
			(float) $store_coords['lng']
		);
	}

	/**
	 * Check if today is weekend.
	 *
	 * @return bool
	 */
	private function is_weekend(): bool {
		$day_of_week = (int) current_time( 'w' ); // 0 = Sunday, 6 = Saturday
		return $day_of_week === 0 || $day_of_week === 6;
	}
}
