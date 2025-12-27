<?php
/**
 * Weather service for dynamic pricing.
 *
 * Integrates with OpenWeather API to detect rain and adjust delivery prices.
 *
 * @package Woo_Envios
 */

if (!defined('ABSPATH')) {
	exit;
}

if (!class_exists('Woo_Envios_Weather')) {

	class Woo_Envios_Weather
	{

		/**
		 * OpenWeather API endpoint.
		 */
		private const API_URL = 'https://api.openweathermap.org/data/2.5/weather';

		/**
		 * Cache duration (1 hour = 3600 seconds).
		 */
		private const CACHE_DURATION = 3600;

		/**
		 * Get current weather multiplier based on rain conditions.
		 *
		 * @param float $lat Latitude.
		 * @param float $lng Longitude.
		 * @return float Weather multiplier (1.0 = no rain, 1.2 = light rain, 1.5 = heavy rain).
		 */
		public function get_weather_multiplier(float $lat, float $lng): float
		{
			$api_key = get_option('woo_envios_weather_api_key', '');

			// If no API key configured, return 1.0 (no weather adjustment)
			if (empty($api_key)) {
				return 1.0;
			}

			$weather_data = $this->get_current_weather($lat, $lng, $api_key);

			if (!$weather_data) {
				return 1.0; // Fallback to no adjustment on error
			}

			return $this->calculate_rain_multiplier($weather_data);
		}

		/**
		 * Get current weather data from OpenWeather API.
		 *
		 * @param float  $lat     Latitude.
		 * @param float  $lng     Longitude.
		 * @param string $api_key OpenWeather API key.
		 * @return array|null Weather data or null on failure.
		 */
		private function get_current_weather(float $lat, float $lng, string $api_key): ?array
		{
			// Check cache first
			$cache_key = 'woo_envios_weather_' . md5($lat . '|' . $lng);
			$cached = get_transient($cache_key);

			if (is_array($cached)) {
				return $cached;
			}

			// Make API request
			$url = add_query_arg(
				array(
					'lat' => $lat,
					'lon' => $lng,
					'appid' => $api_key,
					'units' => 'metric',
					'lang' => 'pt_br',
				),
				self::API_URL
			);

			$response = wp_remote_get(
				$url,
				array(
					'timeout' => 5,
				)
			);

			if (is_wp_error($response)) {
				error_log('Woo Envios Weather: API request failed - ' . $response->get_error_message());
				return null;
			}

			$body = wp_remote_retrieve_body($response);
			$data = json_decode($body, true);

			if (!is_array($data) || !isset($data['weather'])) {
				error_log('Woo Envios Weather: Invalid API response');
				return null;
			}

			// Cache for 1 hour
			set_transient($cache_key, $data, self::CACHE_DURATION);

			return $data;
		}

		/**
		 * Calculate rain multiplier from weather data.
		 *
		 * @param array $weather_data Weather data from OpenWeather API.
		 * @return float Multiplier based on rain intensity.
		 */
		private function calculate_rain_multiplier(array $weather_data): float
		{
			if (empty($weather_data['weather'][0]['main'])) {
				return 1.0;
			}

			$condition = strtolower($weather_data['weather'][0]['main']);

			// Rain detected
			if ('rain' === $condition || 'drizzle' === $condition) {
				// Check rain intensity if available
				$rain_1h = $weather_data['rain']['1h'] ?? 0;

				if ($rain_1h > 5) {
					// Heavy rain (>5mm/h)
					return (float) get_option('woo_envios_rain_heavy_multiplier', 1.5);
				}

				// Light to moderate rain
				return (float) get_option('woo_envios_rain_light_multiplier', 1.2);
			}

			// Thunderstorm
			if ('thunderstorm' === $condition) {
				return (float) get_option('woo_envios_rain_heavy_multiplier', 1.5);
			}

			// No rain
			return 1.0;
		}

		/**
		 * Get human-readable weather condition description.
		 *
		 * @param array $weather_data Weather data from OpenWeather API.
		 * @return string Weather description.
		 */
		public function get_weather_description(array $weather_data): string
		{
			if (empty($weather_data['weather'][0]['description'])) {
				return '';
			}

			return ucfirst($weather_data['weather'][0]['description']);
		}

		/**
		 * Clear weather cache.
		 *
		 * @return void
		 */
		public function clear_cache(): void
		{
			global $wpdb;

			$wpdb->query(
				"DELETE FROM {$wpdb->options} 
			WHERE option_name LIKE '_transient_woo_envios_weather_%' 
			OR option_name LIKE '_transient_timeout_woo_envios_weather_%'"
			);
		}

	}
}
