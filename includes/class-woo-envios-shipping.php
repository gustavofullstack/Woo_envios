<?php
/**
 * Método de entrega Woo Envios baseado em raio.
 *
 * @package Woo_Envios
 */

if (!defined('ABSPATH')) {
	exit;
}

if (!class_exists('Woo_Envios_Shipping_Method')) {

	class Woo_Envios_Shipping_Method extends WC_Shipping_Method
	{

		/**
		 * Construtor.
		 */
		public function __construct($instance_id = 0)
		{
			$this->id = 'woo_envios_radius';
			$this->method_title = __('Woo Envios — Raio Escalonado', 'woo-envios');
			$this->method_description = __('Calcula o frete local por distância em linha reta a partir da base configurada.', 'woo-envios');
			$this->instance_id = absint($instance_id);
			$this->supports = array(
				'shipping-zones',
				'instance-settings',
				'instance-settings-modal',
			);

			$this->init();
		}

		/**
		 * Inicializa campos e hooks.
		 */
		public function init(): void
		{
			$this->instance_form_fields = array(
				'enabled' => array(
					'title' => __('Ativar', 'woo-envios'),
					'type' => 'checkbox',
					'label' => __('Habilitar este método para a zona', 'woo-envios'),
					'default' => 'yes',
				),
				'title' => array(
					'title' => __('Título exibido ao cliente', 'woo-envios'),
					'type' => 'text',
					'default' => __('Entrega Flash', 'woo-envios'),
					'desc_tip' => true,
					'description' => __('Texto mostrado no checkout.', 'woo-envios'),
				),
			);

			$this->init_instance_settings();

			$this->enabled = $this->get_option('enabled', 'yes');
			$this->title = $this->get_option('title', __('Entrega Flash', 'woo-envios'));

			add_action(
				'woocommerce_update_options_shipping_' . $this->id . '_' . $this->instance_id,
				array($this, 'process_admin_options')
			);
		}

		/**
		 * Campos visíveis na tela de zonas do WooCommerce.
		 */
		public function init_form_fields(): void
		{
		}

		/**
		 * Calcula o frete com base nos dados em sessão.
		 *
		 * @param array $package Pacote do WooCommerce.
		 *
		 * @return void
		 */
		public function calculate_shipping($package = array()): void
		{
			$logger = wc_get_logger();
			$context = array('source' => 'woo-envios-shipping');

			$logger->debug('=== CALCULATE_SHIPPING CALLED ===', $context);
			$logger->debug('Enabled: ' . $this->enabled, $context);

			if ('yes' !== $this->enabled) {
				$logger->debug('Method disabled, returning', $context);
				return;
			}

			$store_coords = Woo_Envios_Admin::get_store_coordinates();
			$logger->debug('Store coordinates: ' . print_r($store_coords, true), $context);

			if (empty($store_coords['lat']) || empty($store_coords['lng'])) {
				$logger->warning('Store coordinates not configured!', $context);
				return;
			}

			$signature = $this->build_destination_signature($package);
			$session_coords = $this->get_session_coordinates($signature);

			$logger->debug('Signature: ' . $signature, $context);
			$logger->debug('Session coords: ' . print_r($session_coords, true), $context);

			// If no coordinates in session, try Correios directly based on CEP.
			// If no coordinates in session, try to Geocode on the fly (Server-Side Fallback)
			if (empty($session_coords)) {
				$logger->warning('NO SESSION COORDINATES! Attempting server-side fallback geocode.', $context);

				// Build address from package
				$destination = $package['destination'] ?? array();
				$address_parts = array(
					$destination['address_1'] ?? '',
					$destination['city'] ?? '',
					$destination['state'] ?? '',
					$destination['postcode'] ?? '',
					$destination['country'] ?? ''
				);
				$full_address = implode(', ', array_filter($address_parts));

				// Try to geocode
				$fallback_coords = \Woo_Envios\Services\Geocoder::geocode($full_address);

				if ($fallback_coords) {
					$logger->info('Fallback Geocoding SUCCESS! Saved to session.', $context);
					$session_coords = $fallback_coords;

					// Save to session to avoid re-geocoding on every calculation
					if (isset(WC()->session)) {
						WC()->session->set('woo_envios_coords', array(
							'lat' => $session_coords['lat'],
							'lng' => $session_coords['lng'],
							'signature' => $signature,
						));
						// WC()->session->save_data(); // Do not force save here to avoid session lock issues during calc
					}
				} else {
					$logger->warning('Fallback Geocoding FAILED. Only showing Correios.', $context);
					// Try Correios as fallback when we can't geocode.
					$this->calculate_correios_shipping($package);
					return;
				}
			}

			// Use Google Distance Matrix API for real route distance instead of Haversine
			$distance_data = $this->calculate_route_distance($store_coords, $session_coords, $package);

			if (is_wp_error($distance_data) || empty($distance_data)) {
				$logger->debug('Distance Matrix failed, using Haversine fallback', $context);
				// Fallback to Haversine if Distance Matrix fails
				$distance = $this->calculate_distance(
					(float) $store_coords['lat'],
					(float) $store_coords['lng'],
					(float) $session_coords['lat'],
					(float) $session_coords['lng']
				);
			} else {
				// Convert meters to kilometers
				$distance = round($distance_data['distance_value'] / 1000, 2);
			}

			$logger->debug('Calculated distance: ' . $distance . ' km', $context);

			$tier = Woo_Envios_Admin::match_tier_by_distance($distance);

			// ALWAYS calculate Correios as an option (SuperFrete/PAC/SEDEX)
			// This allows customers to choose between Flash and Correios
			$this->calculate_correios_shipping($package);

			// If customer is outside local delivery range, don't add Flash option
			if (!$tier) {
				$logger->warning('Distance ' . $distance . 'km is OUTSIDE configured tiers! Only Correios shown.', $context);
				Woo_Envios_Logger::distance_out_of_range($distance, $package['destination']);
				return;
			}

			$logger->info('Distance ' . $distance . 'km matched tier: ' . $tier['label'] . ' @ R$' . $tier['price'], $context);

			// Apply dynamic pricing if enabled
			$base_price = (float) $tier['price'];
			$multiplier_data = $this->calculate_dynamic_multiplier($package);
			$final_price = $base_price * $multiplier_data['total'];

			// Build label - simple title only
			$label = $this->title;

			$rate = array(
				'id' => $this->id,
				'label' => $label,
				'cost' => round($final_price, 2),
				'package' => $package,
				'meta_data' => array(
					'distance' => $distance,
					'base_price' => $base_price,
					'multiplier' => $multiplier_data['total'],
					'breakdown' => $multiplier_data['reasons'],
					'debug_info' => array(
						'store' => $store_coords,
						'customer' => $session_coords,
						'distance' => $distance,
					),
				),
			);

			$this->add_rate($rate);

			$logger->info('Flash delivery rate ADDED: ' . $label . ' @ R$' . round($final_price, 2), $context);

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
		 * Calculate shipping via Correios for destinations outside local radius.
		 *
		 * @param array $package WooCommerce package.
		 * @return void
		 */
		private function calculate_correios_shipping(array $package): void
		{
			// Check if SuperFrete/Correios is enabled.
			$correios = new \Woo_Envios\Services\Woo_Envios_Correios();

			if (!$correios->is_enabled()) {
				return;
			}

			$rates = $correios->calculate($package);

			if ($rates && is_array($rates)) {
				foreach ($rates as $rate_data) {
					$this->add_rate(array(
						'id' => $this->id . '_' . $rate_data['id'],
						'label' => $rate_data['label'],
						'cost' => $rate_data['cost'],
						'package' => $package,
						'meta_data' => array(
							'service_code' => $rate_data['code'] ?? '',
							'deadline' => $rate_data['deadline'] ?? 0,
							'method' => 'correios',
						),
					));
				}
			}
		}

		/**
		 * Recupera coordenadas do cliente em sessão.
		 *
		 * @return array<string,float>|null
		 */
		private function get_session_coordinates(string $signature): ?array
		{
			if (!function_exists('WC') || !WC()->session) {
				return null;
			}

			$coords = WC()->session->get('woo_envios_coords');
			if (empty($coords['lat']) || empty($coords['lng'])) {
				return null;
			}

			if (empty($coords['signature']) || $coords['signature'] !== $signature) {
				return null;
			}

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
		private function calculate_route_distance(array $store_coords, array $customer_coords, array $package)
		{
			// Build address strings for Distance Matrix API
			$origin = $store_coords['lat'] . ',' . $store_coords['lng'];
			$destination = $customer_coords['lat'] . ',' . $customer_coords['lng'];

			// Use Google Maps Distance Matrix API
			$google_maps = new Woo_Envios_Google_Maps();

			if (!$google_maps->is_configured()) {
				return new WP_Error('not_configured', 'Google Maps API not configured');
			}

			return $google_maps->calculate_distance($origin, $destination);
		}

		/**
		 * Calcula distância em KM com Haversine (fallback).
		 */
		private function calculate_distance(float $lat_from, float $lng_from, float $lat_to, float $lng_to): float
		{
			$earth_radius = 6371;

			$dlat = deg2rad($lat_to - $lat_from);
			$dlng = deg2rad($lng_to - $lng_from);

			$a = sin($dlat / 2) ** 2 +
				cos(deg2rad($lat_from)) * cos(deg2rad($lat_to)) *
				sin($dlng / 2) ** 2;

			$c = 2 * atan2(sqrt($a), sqrt(1 - $a));

			return round($earth_radius * $c, 2);
		}

		/**
		 * Cria uma assinatura exclusiva para o endereço atual.
		 *
		 * @param array $package Pacote atual.
		 *
		 * @return string
		 */
		private function build_destination_signature(array $package): string
		{
			$destination = $package['destination'] ?? array();

			// Normalize postcode: remove non-digits to match session signature robustly
			$postcode = preg_replace('/\D/', '', $destination['postcode'] ?? '');

			$parts = array(
				// Removed address_1 to match relaxed session logic (avoids "Av" vs "Avenida" issues)
				sanitize_text_field($destination['city'] ?? ''),
				sanitize_text_field($destination['state'] ?? ''),
				$postcode, // Normalized
				sanitize_text_field($destination['country'] ?? ''),
			);

			$normalized = strtolower(implode('|', $parts));

			return md5($normalized);
		}

		/**
		 * Calculate dynamic pricing multiplier.
		 *
		 * @param array $package Package data.
		 * @return array Array with 'total' multiplier and 'reasons' breakdown.
		 */
		private function calculate_dynamic_multiplier(array $package): array
		{
			// If dynamic pricing is disabled, return 1.0
			if (!get_option('woo_envios_dynamic_pricing_enabled', false)) {
				return array(
					'total' => 1.0,
					'reasons' => array(),
				);
			}

			$multiplier = 1.0;
			$reasons = array();

			// Peak hour multiplier
			$peak_data = $this->get_peak_hour_multiplier();
			if ($peak_data['multiplier'] > 1.0) {
				$multiplier *= $peak_data['multiplier'];
				$reasons[] = $peak_data['label'];
			}

			// Weekend multiplier
			if ($this->is_weekend()) {
				$weekend_mult = (float) get_option('woo_envios_weekend_multiplier', 1.0);
				if ($weekend_mult > 1.0) {
					$multiplier *= $weekend_mult;
					$reasons[] = sprintf(__('Fim de semana +%d%%', 'woo-envios'), (int) (($weekend_mult - 1) * 100));
				}
			}

			// Weather multiplier (rain)
			$weather_mult = $this->get_weather_multiplier($package);
			if ($weather_mult > 1.0) {
				$multiplier *= $weather_mult;
				$reasons[] = sprintf(__('Chuva +%d%%', 'woo-envios'), (int) (($weather_mult - 1) * 100));
			}

			// Apply maximum multiplier limit
			$max_multiplier = (float) get_option('woo_envios_max_multiplier', 2.0);
			if ($multiplier > $max_multiplier) {
				$multiplier = $max_multiplier;
			}

			return array(
				'total' => $multiplier,
				'reasons' => $reasons,
			);
		}

		/**
		 * Check if current time is within peak hours.
		 *
		 * @return array Array with 'multiplier' and 'label'.
		 */
		private function get_peak_hour_multiplier(): array
		{
			$peak_hours = get_option('woo_envios_peak_hours', array());
			if (empty($peak_hours)) {
				return array('multiplier' => 1.0, 'label' => '');
			}

			$current_time = current_time('H:i');

			foreach ($peak_hours as $period) {
				if (empty($period['start']) || empty($period['end'])) {
					continue;
				}

				// Check if current time is within this peak period
				if ($current_time >= $period['start'] && $current_time <= $period['end']) {
					$multiplier = (float) ($period['multiplier'] ?? 1.0);
					$percentage = (int) (($multiplier - 1) * 100);
					$label = sprintf(
						__('%s +%d%%', 'woo-envios'),
						$period['name'] ?? __('Pico', 'woo-envios'),
						$percentage
					);

					return array(
						'multiplier' => $multiplier,
						'label' => $label,
					);
				}
			}

			return array('multiplier' => 1.0, 'label' => '');
		}

		/**
		 * Get weather-based multiplier.
		 *
		 * @param array $package Package data.
		 * @return float Weather multiplier.
		 */
		private function get_weather_multiplier(array $package): float
		{
			// Get store coordinates for weather check
			$store_coords = Woo_Envios_Admin::get_store_coordinates();
			if (empty($store_coords['lat']) || empty($store_coords['lng'])) {
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
		private function is_weekend(): bool
		{
			$day_of_week = (int) current_time('w'); // 0 = Sunday, 6 = Saturday
			return $day_of_week === 0 || $day_of_week === 6;
		}

	}
}
