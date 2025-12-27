<?php
/**
 * Configurações do plugin Woo Envios.
 *
 * @package Woo_Envios
 */

if (!defined('ABSPATH')) {
	exit;
}

use Woo_Envios\Services\Geocoder;

final class Woo_Envios_Admin
{

	/**
	 * Slug da página de configurações.
	 */
	private const PAGE_SLUG = 'woo-envios';

	/**
	 * Option names.
	 */
	private const OPTION_STORE_LABEL = 'woo_envios_store_label';
	private const OPTION_STORE_LAT = 'woo_envios_store_lat';
	private const OPTION_STORE_LNG = 'woo_envios_store_lng';
	private const OPTION_TIERS = 'woo_envios_tiers';

	/**
	 * Construtor.
	 */
	public function __construct()
	{
		add_action('admin_menu', array($this, 'register_menu'));
		add_action('admin_init', array($this, 'register_settings'));
		add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
		add_action('add_meta_boxes', array($this, 'add_order_debug_metabox'));

		// AJAX Debug Tools
		add_action('wp_ajax_woo_envios_debug_geocode', array($this, 'ajax_debug_geocode'));
		add_action('wp_ajax_woo_envios_clear_cache', array($this, 'ajax_clear_cache'));

		// Handle checkbox arrays - they need special processing because unchecked boxes aren't sent
		add_filter('pre_update_option_woo_envios_superfrete_services', array($this, 'handle_services_save'), 10, 2);
	}

	/**
	 * Handle services checkbox save - ensure array is properly saved.
	 *
	 * @param mixed $value New value.
	 * @param mixed $old_value Old value.
	 * @return array
	 */
	public function handle_services_save($value, $old_value): array
	{
		// Check if the services section was submitted (hidden field present)
		$form_submitted = isset($_POST['woo_envios_superfrete_services_submitted']);

		// If empty or not array
		if (empty($value) || !is_array($value)) {
			// If form was submitted with no services checked, return empty array
			if ($form_submitted) {
				return array();
			}
			// Otherwise keep old value or default
			return is_array($old_value) ? $old_value : array('1', '2');
		}

		// Sanitize and validate
		$valid_services = array('1', '2', '17', '3', '31');
		$sanitized = array();

		foreach ($value as $service) {
			$service = sanitize_text_field($service);
			if (in_array($service, $valid_services, true)) {
				$sanitized[] = $service;
			}
		}

		return $sanitized;
	}

	/**
	 * AJAX: Testa geocodificação e cálculo de distância.
	 */
	public function ajax_debug_geocode(): void
	{
		check_ajax_referer('woo_envios_debug_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error('Permissão negada.');
		}

		$address = isset($_POST['address']) ? sanitize_text_field(wp_unslash($_POST['address'])) : '';

		if (empty($address)) {
			wp_send_json_error('Endereço inválido.');
		}

		// Use Google Maps class directly to get specific errors
		$google_maps = new Woo_Envios_Google_Maps();

		if (!$google_maps->is_configured()) {
			wp_send_json_error('API do Google Maps não configurada. Verifique a chave API nas configurações.');
		}

		$geocode_result = $google_maps->geocode_address($address);

		if (is_wp_error($geocode_result)) {
			wp_send_json_error('Erro na Geocodificação: ' . $geocode_result->get_error_message());
		}

		// Format coords for compatibility
		$coords = array(
			'lat' => $geocode_result['latitude'],
			'lng' => $geocode_result['longitude'],
		);

		$store = self::get_store_coordinates();

		// Try to use Distance Matrix API for real route distance
		$distance = 0;
		$distance_method = 'Haversine (linha reta)';

		if ($google_maps->is_configured()) {
			$origin = $store['lat'] . ',' . $store['lng'];
			$destination = $coords['lat'] . ',' . $coords['lng'];

			$distance_data = $google_maps->calculate_distance($origin, $destination);

			if (!is_wp_error($distance_data) && !empty($distance_data)) {
				$distance = round($distance_data['distance_value'] / 1000, 2);
				$distance_method = 'Google Maps (rota real)';
			} else {
				if (is_wp_error($distance_data)) {
					$distance_method = 'Haversine (fallback - Erro API: ' . $distance_data->get_error_message() . ')';
				}
			}
		}

		// Fallback to Haversine if Distance Matrix failed
		if ($distance === 0) {
			$earth_radius = 6371;
			$lat_from = deg2rad($store['lat']);
			$lng_from = deg2rad($store['lng']);
			$lat_to = deg2rad($coords['lat']);
			$lng_to = deg2rad($coords['lng']);

			$dlat = $lat_to - $lat_from;
			$dlng = $lng_to - $lng_from;

			$a = sin($dlat / 2) ** 2 +
				cos($lat_from) * cos($lat_to) *
				sin($dlng / 2) ** 2;

			$c = 2 * atan2(sqrt($a), sqrt(1 - $a));
			$distance = round($earth_radius * $c, 2);
		}

		// Check tier
		$tier = self::match_tier_by_distance($distance);

		// Build response
		$response = array(
			'coords' => $coords,
			'store' => $store,
			'distance' => $distance,
			'distance_method' => $distance_method,
			'tier' => $tier,
		);

		// If INSIDE radius, add dynamic pricing info
		if ($tier) {
			$base_price = (float) $tier['price'];
			$multipliers = $this->calculate_debug_multipliers();
			$final_price = $base_price * $multipliers['total'];

			$response['pricing'] = array(
				'base_price' => $base_price,
				'final_price' => round($final_price, 2),
				'multiplier' => $multipliers['total'],
				'breakdown' => $multipliers['breakdown'],
				'is_peak_hour' => $multipliers['is_peak'],
				'is_weekend' => $multipliers['is_weekend'],
				'weather' => $multipliers['weather'],
			);
		} else {
			// If OUTSIDE radius, get SuperFrete quotes
			$superfrete_quotes = $this->get_superfrete_debug_quotes($address, $geocode_result);
			if (!empty($superfrete_quotes)) {
				$response['superfrete'] = $superfrete_quotes;
			}
		}

		wp_send_json_success($response);
	}

	/**
	 * Calculate dynamic pricing multipliers for debug.
	 *
	 * @return array
	 */
	private function calculate_debug_multipliers(): array
	{
		$total = 1.0;
		$breakdown = array();
		$is_peak = false;
		$is_weekend = false;
		$weather = 'normal';

		// Check if dynamic pricing is enabled
		if (!get_option('woo_envios_dynamic_pricing_enabled', false)) {
			return array(
				'total' => 1.0,
				'breakdown' => array('Precificação dinâmica desativada'),
				'is_peak' => false,
				'is_weekend' => false,
				'weather' => 'disabled',
			);
		}

		// Weekend check
		$day_of_week = (int) current_time('N');
		if ($day_of_week >= 6) {
			$is_weekend = true;
			$weekend_mult = (float) get_option('woo_envios_weekend_multiplier', 1.1);
			if ($weekend_mult > 1) {
				$total *= $weekend_mult;
				$breakdown[] = sprintf('🗓️ Fim de semana: x%.2f', $weekend_mult);
			}
		}

		// Peak hours check
		$current_hour = (int) current_time('G');
		$current_minute = (int) current_time('i');
		$current_time_decimal = $current_hour + ($current_minute / 60);

		$peak_hours = get_option('woo_envios_peak_hours', array());
		if (is_array($peak_hours)) {
			foreach ($peak_hours as $period) {
				if (empty($period['start']) || empty($period['end'])) {
					continue;
				}

				$start_parts = explode(':', $period['start']);
				$end_parts = explode(':', $period['end']);

				$start_decimal = (int) $start_parts[0] + ((int) ($start_parts[1] ?? 0) / 60);
				$end_decimal = (int) $end_parts[0] + ((int) ($end_parts[1] ?? 0) / 60);

				if ($current_time_decimal >= $start_decimal && $current_time_decimal <= $end_decimal) {
					$is_peak = true;
					$peak_mult = (float) ($period['multiplier'] ?? 1.0);
					if ($peak_mult > 1) {
						$total *= $peak_mult;
						$period_name = $period['label'] ?? 'Pico';
						$breakdown[] = sprintf('⏰ %s (%s-%s): x%.2f', $period_name, $period['start'], $period['end'], $peak_mult);
					}
					break;
				}
			}
		}

		// Weather check
		$weather_api_key = get_option('woo_envios_openweather_api_key', '');
		if (!empty($weather_api_key)) {
			$store = self::get_store_coordinates();
			$weather_data = $this->check_weather_conditions($store['lat'], $store['lng'], $weather_api_key);

			if ($weather_data && !empty($weather_data['condition'])) {
				$weather = $weather_data['condition'];
				if ($weather_data['condition'] === 'rain_light') {
					$rain_mult = (float) get_option('woo_envios_rain_light_multiplier', 1.1);
					if ($rain_mult > 1) {
						$total *= $rain_mult;
						$breakdown[] = sprintf('🌧️ Chuva leve: x%.2f', $rain_mult);
					}
				} elseif ($weather_data['condition'] === 'rain_heavy') {
					$rain_mult = (float) get_option('woo_envios_rain_heavy_multiplier', 1.2);
					if ($rain_mult > 1) {
						$total *= $rain_mult;
						$breakdown[] = sprintf('⛈️ Chuva forte: x%.2f', $rain_mult);
					}
				}
			}
		}

		// Apply max multiplier cap
		$max_mult = (float) get_option('woo_envios_max_multiplier', 2.0);
		if ($total > $max_mult) {
			$breakdown[] = sprintf('🔒 Limite máximo aplicado: x%.2f → x%.2f', $total, $max_mult);
			$total = $max_mult;
		}

		if (empty($breakdown)) {
			$breakdown[] = '✅ Sem multiplicadores ativos';
		}

		return array(
			'total' => round($total, 2),
			'breakdown' => $breakdown,
			'is_peak' => $is_peak,
			'is_weekend' => $is_weekend,
			'weather' => $weather,
		);
	}

	/**
	 * Check weather conditions.
	 *
	 * @param float  $lat Latitude.
	 * @param float  $lng Longitude.
	 * @param string $api_key OpenWeather API key.
	 * @return array|null
	 */
	private function check_weather_conditions(float $lat, float $lng, string $api_key): ?array
	{
		$cache_key = 'woo_envios_weather_' . md5($lat . $lng);
		$cached = get_transient($cache_key);

		if (is_array($cached)) {
			return $cached;
		}

		$url = sprintf(
			'https://api.openweathermap.org/data/2.5/weather?lat=%s&lon=%s&appid=%s',
			$lat,
			$lng,
			$api_key
		);

		$response = wp_remote_get($url, array('timeout' => 5));

		if (is_wp_error($response)) {
			return null;
		}

		$data = json_decode(wp_remote_retrieve_body($response), true);

		if (empty($data['weather'][0]['main'])) {
			return null;
		}

		$weather_main = strtolower($data['weather'][0]['main']);
		$condition = 'normal';

		if (in_array($weather_main, array('rain', 'drizzle', 'thunderstorm'), true)) {
			// Check precipitation intensity
			$rain_1h = $data['rain']['1h'] ?? 0;
			$condition = $rain_1h > 5 ? 'rain_heavy' : 'rain_light';
		}

		$result = array(
			'condition' => $condition,
			'description' => $data['weather'][0]['description'] ?? '',
			'temp' => isset($data['main']['temp']) ? round($data['main']['temp'] - 273.15, 1) : null,
		);

		set_transient($cache_key, $result, 15 * MINUTE_IN_SECONDS);

		return $result;
	}

	/**
	 * Get SuperFrete quotes for debug.
	 *
	 * @param string $address Address.
	 * @param array  $geocode_result Geocode result with postal_code.
	 * @return array
	 */
	private function get_superfrete_debug_quotes(string $address, array $geocode_result): array
	{
		// Try to extract CEP from address or geocode result
		$destination_cep = '';

		// Try to extract from address first
		if (preg_match('/(\d{5})-?(\d{3})/', $address, $matches)) {
			$destination_cep = $matches[1] . $matches[2];
		}

		// If not found in address, try from geocode result (Google returns in address_components)
		if (empty($destination_cep) && !empty($geocode_result['address_components']['postal_code'])) {
			$destination_cep = preg_replace('/\D/', '', $geocode_result['address_components']['postal_code']);
		}

		if (empty($destination_cep) || strlen($destination_cep) !== 8) {
			return array('error' => 'CEP não encontrado. Google não retornou CEP para este endereço. Tente incluir o CEP no endereço (ex: "Rua X, 123 - 01310-100").');
		}

		// Call SuperFrete API
		$correios = new \Woo_Envios\Services\Woo_Envios_Correios();

		if (!$correios->is_enabled()) {
			return array('error' => 'SuperFrete não configurado. Configure o token nas configurações.');
		}

		// Use debug calculate method (doesn't require WooCommerce package)
		$rates = $correios->calculate_debug($destination_cep);

		if (empty($rates) || is_wp_error($rates)) {
			$error_msg = is_wp_error($rates) ? $rates->get_error_message() : 'Nenhuma cotação retornada';
			return array('error' => 'Erro SuperFrete: ' . $error_msg);
		}

		// Format for display
		$formatted = array();
		foreach ($rates as $rate) {
			$formatted[] = array(
				'service' => $rate['label'] ?? $rate['name'] ?? 'Serviço',
				'price' => $rate['cost'] ?? $rate['price'] ?? 0,
				'days' => $rate['deadline'] ?? $rate['delivery_time'] ?? $rate['days'] ?? '?',
				'company' => $rate['company'] ?? 'Correios',
			);
		}

		return array(
			'destination_cep' => $destination_cep,
			'quotes' => $formatted,
			'package_info' => 'Pacote teste: 1kg, 10x15x20cm, R$100',
		);
	}

	/**
	 * AJAX: Limpa cache de geocodificação.
	 */
	public function ajax_clear_cache(): void
	{
		check_ajax_referer('woo_envios_debug_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error('Permissão negada.');
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'woo_envios_geocode_cache';

		// Check if table exists
		$table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) === $table_name;

		if (!$table_exists) {
			// Create the table if it doesn't exist (self-healing)
			$charset_collate = $wpdb->get_charset_collate();
			$sql = "CREATE TABLE IF NOT EXISTS $table_name (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				cache_key varchar(64) NOT NULL,
				result_data longtext NOT NULL,
				created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				expires_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY cache_key (cache_key),
				KEY expires_at (expires_at)
			) $charset_collate;";

			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta($sql);

			wp_send_json_success('Tabela de cache criada. Nenhum cache para limpar.');
		} else {
			$wpdb->query("TRUNCATE TABLE $table_name");
			wp_send_json_success('Cache limpo com sucesso.');
		}
	}

	/**
	 * Adiciona metabox de debug na edição do pedido.
	 */
	public function add_order_debug_metabox(): void
	{
		add_meta_box(
			'woo_envios_debug',
			__('Woo Envios — Debug de Entrega', 'woo-envios'),
			array($this, 'render_order_debug_metabox'),
			'shop_order',
			'side',
			'default'
		);
		// Support for HPOS
		add_meta_box(
			'woo_envios_debug',
			__('Woo Envios — Debug de Entrega', 'woo-envios'),
			array($this, 'render_order_debug_metabox'),
			'woocommerce_page_wc-orders',
			'side',
			'default'
		);
	}

	/**
	 * Renderiza o conteúdo da metabox de debug.
	 *
	 * @param WP_Post|WC_Order $post_or_order_object Post object or Order object.
	 */
	public function render_order_debug_metabox($post_or_order_object): void
	{
		$order = ($post_or_order_object instanceof WC_Order) ? $post_or_order_object : wc_get_order($post_or_order_object->ID);

		if (!$order) {
			return;
		}

		$shipping_methods = $order->get_shipping_methods();
		$is_woo_envios = false;
		$saved_debug = array();

		foreach ($shipping_methods as $shipping_method) {
			if ('woo_envios_radius' === $shipping_method->get_method_id()) {
				$is_woo_envios = true;
				$saved_debug = $shipping_method->get_meta('debug_info');
				break;
			}
		}

		if (!$is_woo_envios) {
			echo '<p>' . esc_html__('Este pedido não utilizou o método Woo Envios.', 'woo-envios') . '</p>';
			return;
		}

		if (empty($saved_debug)) {
			// Try legacy/fallback if needed, or just show message
			echo '<p>' . esc_html__('Dados de debug não disponíveis para este pedido.', 'woo-envios') . '</p>';
			// Fallback to showing current store config
			$store = self::get_store_coordinates();
			echo '<p><strong>' . esc_html__('Configuração Atual da Loja:', 'woo-envios') . '</strong><br>';
			echo sprintf('Lat: %s<br>Lng: %s', esc_html((string) $store['lat']), esc_html((string) $store['lng']));
			echo '</p>';
			return;
		}

		$store_lat = $saved_debug['store']['lat'] ?? '';
		$store_lng = $saved_debug['store']['lng'] ?? '';
		$cust_lat = $saved_debug['customer']['lat'] ?? '';
		$cust_lng = $saved_debug['customer']['lng'] ?? '';
		$distance = $saved_debug['distance'] ?? 0;

		echo '<div class="woo-envios-debug-info">';

		echo '<p><strong>' . esc_html__('Distância Calculada:', 'woo-envios') . '</strong> ' . esc_html($distance) . ' km</p>';

		if ($store_lat && $store_lng) {
			echo '<p><strong>' . esc_html__('Ponto da Loja (Origem):', 'woo-envios') . '</strong><br>';
			echo sprintf('<a href="https://www.google.com/maps?q=%1$s,%2$s" target="_blank">%1$s, %2$s</a>', esc_attr($store_lat), esc_attr($store_lng));
			echo '</p>';
		}

		if ($cust_lat && $cust_lng) {
			echo '<p><strong>' . esc_html__('Ponto do Cliente (Destino):', 'woo-envios') . '</strong><br>';
			echo sprintf('<a href="https://www.google.com/maps?q=%1$s,%2$s" target="_blank">%1$s, %2$s</a>', esc_attr($cust_lat), esc_attr($cust_lng));
			echo '</p>';
		}

		if ($store_lat && $store_lng && $cust_lat && $cust_lng) {
			echo '<p><a href="https://www.google.com/maps/dir/?api=1&origin=' . esc_attr("$store_lat,$store_lng") . '&destination=' . esc_attr("$cust_lat,$cust_lng") . '" target="_blank" class="button button-primary">' . esc_html__('Ver Rota no Google Maps', 'woo-envios') . '</a></p>';
		}

		echo '</div>';
	}


	/**
	 * Registra submenu em WooCommerce.
	 */
	public function register_menu(): void
	{
		add_submenu_page(
			'triqhub',
			__('Woo Envios', 'woo-envios'),
			__('Woo Envios', 'woo-envios'),
			'manage_options',
			self::PAGE_SLUG,
			array($this, 'render_settings_page')
		);
	}

	/**
	 * Registra opções utilizadas pelo plugin.
	 */
	public function register_settings(): void
	{
		register_setting(
			'woo_envios_settings',
			self::OPTION_STORE_LABEL,
			array(
				'type' => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default' => __('Base Uberlândia', 'woo-envios'),
			)
		);

		register_setting(
			'woo_envios_settings',
			self::OPTION_STORE_LAT,
			array(
				'type' => 'string',
				'sanitize_callback' => array($this, 'sanitize_coordinate'),
				'default' => (string) WOO_ENVIOS_DEFAULT_LAT,
			)
		);

		register_setting(
			'woo_envios_settings',
			self::OPTION_STORE_LNG,
			array(
				'type' => 'string',
				'sanitize_callback' => array($this, 'sanitize_coordinate'),
				'default' => (string) WOO_ENVIOS_DEFAULT_LNG,
			)
		);

		register_setting(
			'woo_envios_settings',
			self::OPTION_TIERS,
			array(
				'type' => 'array',
				'sanitize_callback' => array($this, 'sanitize_tiers'),
				'default' => self::get_default_tiers(),
			)
		);

		// Google Maps API Key
		register_setting(
			'woo_envios_settings',
			'woo_envios_google_maps_api_key',
			array(
				'type' => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default' => '',
			)
		);

		// Dynamic Pricing Settings
		register_setting('woo_envios_settings', 'woo_envios_dynamic_pricing_enabled', array('type' => 'boolean', 'default' => false));
		register_setting('woo_envios_settings', 'woo_envios_peak_hours', array('type' => 'array'));
		register_setting('woo_envios_settings', 'woo_envios_weekend_multiplier', array('type' => 'number', 'default' => 1.2));
		register_setting('woo_envios_settings', 'woo_envios_max_multiplier', array('type' => 'number', 'default' => 2.0));
		register_setting('woo_envios_settings', 'woo_envios_weather_api_key', array('type' => 'string', 'default' => ''));
		register_setting('woo_envios_settings', 'woo_envios_rain_light_multiplier', array('type' => 'number', 'default' => 1.2));
		register_setting('woo_envios_settings', 'woo_envios_rain_heavy_multiplier', array('type' => 'number', 'default' => 1.5));

		// Logging Settings
		register_setting('woo_envios_settings', 'woo_envios_enable_logs', array('type' => 'boolean', 'default' => false));

		// SuperFrete Settings (shipping quotes API)
		register_setting('woo_envios_settings', 'woo_envios_superfrete_enabled', array('type' => 'boolean', 'default' => true));
		register_setting('woo_envios_settings', 'woo_envios_superfrete_token', array('type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field'));
		register_setting('woo_envios_settings', 'woo_envios_superfrete_origin_cep', array('type' => 'string', 'default' => '38405320', 'sanitize_callback' => 'sanitize_text_field'));
		register_setting('woo_envios_settings', 'woo_envios_superfrete_services', array(
			'type' => 'array',
			'default' => array('1', '2'),
			'sanitize_callback' => array($this, 'sanitize_superfrete_services'),
		));
		register_setting('woo_envios_settings', 'woo_envios_superfrete_profit_margin', array('type' => 'number', 'default' => 0));
		register_setting('woo_envios_settings', 'woo_envios_superfrete_sandbox', array('type' => 'boolean', 'default' => false));
	}

	/**
	 * Carrega scripts específicos da página do plugin.
	 *
	 * @param string $hook Página atual.
	 */
	public function enqueue_assets(string $hook): void
	{
		if ('woocommerce_page_' . self::PAGE_SLUG !== $hook) {
			return;
		}

		wp_enqueue_style(
			'leaflet',
			'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
			array(),
			'1.9.4'
		);

		wp_enqueue_style(
			'woo-envios-admin',
			WOO_ENVIOS_ASSETS . 'css/admin.css',
			array('leaflet'),
			'2.1.' . filemtime(WOO_ENVIOS_PATH . 'assets/css/admin.css') // Cache bust on file change
		);

		wp_enqueue_script(
			'leaflet',
			'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
			array(),
			'1.9.4',
			true
		);

		wp_enqueue_script(
			'woo-envios-admin',
			WOO_ENVIOS_ASSETS . 'js/woo-envios-admin.js',
			array('leaflet'),
			time(), // Force cache busting
			true
		);

		wp_localize_script(
			'woo-envios-admin',
			'WooEnviosAdminData',
			array(
				'storeLat' => (float) self::get_store_coordinates()['lat'],
				'storeLng' => (float) self::get_store_coordinates()['lng'],
				'tiers' => self::get_tiers(),
				'mapTileUrl' => 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
				'mapAttr' => '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">OpenStreetMap</a>',
				'zoom' => 12,
				'strings' => array(
					'setMarker' => __('Arraste o marcador para definir o ponto de origem das entregas.', 'woo-envios'),
					'addTier' => __('Adicionar faixa', 'woo-envios'),
					'remove' => __('Remover', 'woo-envios'),
					'newTierLabel' => __('Novo raio', 'woo-envios'),
					'searchPlaceholder' => __('Ex: Rua Iguaçu, 1400 - Uberlândia', 'woo-envios'),
					'searching' => __('Pesquisando endereço…', 'woo-envios'),
					'noResults' => __('Nenhum resultado encontrado para este termo.', 'woo-envios'),
				),
				'debugNonce' => wp_create_nonce('woo_envios_debug_nonce'),
				'ajaxUrl' => admin_url('admin-ajax.php'),
			)
		);
	}

	/**
	 * Sanitiza coordenadas.
	 *
	 * @param string $value Valor enviado.
	 */
	public function sanitize_coordinate(string $value): string
	{
		$value = str_replace(',', '.', $value);
		return is_numeric($value) ? (string) $value : '0';
	}

	/**
	 * Sanitize SuperFrete services array.
	 *
	 * @param mixed $services Services array from form.
	 * @return array Sanitized array of service codes.
	 */
	public function sanitize_superfrete_services($services): array
	{
		if (!is_array($services)) {
			return array('1', '2'); // Default
		}

		$valid_services = array('1', '2', '17', '3', '31');
		$sanitized = array();

		foreach ($services as $service) {
			$service = sanitize_text_field($service);
			if (in_array($service, $valid_services, true)) {
				$sanitized[] = $service;
			}
		}

		return !empty($sanitized) ? $sanitized : array('1', '2');
	}

	/**
	 * Sanitiza faixas de distância.
	 *
	 * @param mixed $tiers Dados recebidos.
	 *
	 * @return array
	 */
	public function sanitize_tiers($tiers): array
	{
		if (!is_array($tiers)) {
			return self::get_default_tiers();
		}

		$sanitized = array();

		foreach ($tiers as $tier) {
			$distance = isset($tier['distance']) ? (float) str_replace(',', '.', (string) $tier['distance']) : 0;
			$price = isset($tier['price']) ? (float) wc_format_decimal($tier['price']) : 0;
			$label = isset($tier['label']) ? sanitize_text_field($tier['label']) : '';

			if ($distance <= 0 || $price < 0) {
				continue;
			}

			$sanitized[] = array(
				'label' => $label ?: sprintf(__('Raio %.1f km', 'woo-envios'), $distance),
				'distance' => $distance,
				'price' => $price,
			);
		}

		if (empty($sanitized)) {
			return self::get_default_tiers();
		}

		usort(
			$sanitized,
			static function ($a, $b) {
				return $a['distance'] <=> $b['distance'];
			}
		);

		return $sanitized;
	}

	/**
	 * Renderiza página de configurações.
	 */
	public function render_settings_page(): void
	{
		if (!current_user_can('manage_options')) {
			return;
		}

		$connector = new \TriqHub_Connector('TRQ-SHIPPING-SESSIONS', 'triqhub-shipping-radius');

		$connector->render_admin_header(
			__('TriqHub: Shipping & Radius', 'woo-envios'),
			__('Configure suas regras de entrega local por raio e integre com SuperFrete.', 'woo-envios'),
			array(
				array(
					'label' => 'Limpar Cache de CEPs',
					'link' => '#',
					'icon' => 'dashicons-trash',
					'primary' => false,
					'id' => 'woo-envios-clear-cache-btn'
				)
			)
		);

		$store_label = get_option(self::OPTION_STORE_LABEL, __('Base Uberlândia', 'woo-envios'));
		$coords = self::get_store_coordinates();
		$tiers = self::get_tiers();
		?>
		<form method="post" action="options.php">
			<?php settings_fields('woo_envios_settings'); ?>

			<div style="display: grid; grid-template-columns: 1.5fr 1fr; gap: 30px;">
				<!-- Left Column -->
				<div>
					<!-- Map & Origin Section -->
					<div class="triqhub-card"
						style="margin-bottom: 30px; background: white; border-radius: 12px; padding: 25px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
						<h3 style="margin-top: 0; font-size: 18px; font-weight: 600;">
							<?php esc_html_e('Ponto de Origem', 'woo-envios'); ?></h3>
						<p class="description" style="margin-bottom: 15px;">
							<?php esc_html_e('Defina o ponto central das entregas locais.', 'woo-envios'); ?></p>

						<div class="woo-envios-search" style="margin-bottom: 15px;">
							<div style="display: flex; gap: 10px;">
								<input type="text" id="woo-envios-search-input"
									placeholder="<?php esc_attr_e('Rua Iguaçu, 1400 - Uberlândia', 'woo-envios'); ?>"
									class="regular-text" style="flex-grow: 1; border-radius: 8px;">
								<button type="button" class="button"
									id="woo-envios-search-btn"><?php esc_html_e('Buscar', 'woo-envios'); ?></button>
							</div>
							<ul id="woo-envios-search-results"
								style="margin-top: 5px; background: white; border: 1px solid #ddd; display: none;"></ul>
						</div>

						<div id="woo-envios-admin-map" data-lat="<?php echo esc_attr((string) $coords['lat']); ?>"
							data-lng="<?php echo esc_attr((string) $coords['lng']); ?>"
							style="height: 400px; border-radius: 10px; margin-bottom: 20px; border: 1px solid #cbd5e1;"></div>

						<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
							<div>
								<label
									style="display: block; font-size: 12px; font-weight: 600; color: #64748b; margin-bottom: 5px;">Latitude</label>
								<input type="text" id="woo_envios_store_lat"
									name="<?php echo esc_attr(self::OPTION_STORE_LAT); ?>"
									value="<?php echo esc_attr((string) $coords['lat']); ?>" readonly
									style="width: 100%; border-radius: 6px; background: #f1f5f9; border: 1px solid #e2e8f0;">
							</div>
							<div>
								<label
									style="display: block; font-size: 12px; font-weight: 600; color: #64748b; margin-bottom: 5px;">Longitude</label>
								<input type="text" id="woo_envios_store_lng"
									name="<?php echo esc_attr(self::OPTION_STORE_LNG); ?>"
									value="<?php echo esc_attr((string) $coords['lng']); ?>" readonly
									style="width: 100%; border-radius: 6px; background: #f1f5f9; border: 1px solid #e2e8f0;">
							</div>
						</div>
					</div>

					<!-- Tiers Section -->
					<div class="triqhub-card"
						style="margin-bottom: 30px; background: white; border-radius: 12px; padding: 25px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
						<h3 style="margin-top: 0; font-size: 18px; font-weight: 600;">
							<?php esc_html_e('Faixas de Entrega Local', 'woo-envios'); ?></h3>
						<p class="description" style="margin-bottom: 15px;">
							<?php esc_html_e('Defina os preços baseados na distância em linha reta.', 'woo-envios'); ?></p>

						<table class="widefat woo-envios-tiers" style="border: none;">
							<thead>
								<tr style="background: #f8fafc;">
									<th style="border-radius: 8px 0 0 0;"><?php esc_html_e('Nome/Label', 'woo-envios'); ?>
									</th>
									<th><?php esc_html_e('Raio Máx (km)', 'woo-envios'); ?></th>
									<th><?php esc_html_e('Preço (R$)', 'woo-envios'); ?></th>
									<th style="border-radius: 0 8px 0 0;"></th>
								</tr>
							</thead>
							<tbody id="woo-envios-tier-rows">
								<?php foreach ($tiers as $index => $tier): ?>
									<tr>
										<td><input type="text"
												name="<?php echo esc_attr(self::OPTION_TIERS); ?>[<?php echo esc_attr((string) $index); ?>][label]"
												value="<?php echo esc_attr($tier['label']); ?>"
												style="width: 100%; border-radius: 6px;"></td>
										<td><input type="number" step="0.1"
												name="<?php echo esc_attr(self::OPTION_TIERS); ?>[<?php echo esc_attr((string) $index); ?>][distance]"
												value="<?php echo esc_attr((string) $tier['distance']); ?>"
												style="width: 100%; border-radius: 6px;"></td>
										<td><input type="number" step="0.01"
												name="<?php echo esc_attr(self::OPTION_TIERS); ?>[<?php echo esc_attr((string) $index); ?>][price]"
												value="<?php echo esc_attr((string) $tier['price']); ?>"
												style="width: 100%; border-radius: 6px;"></td>
										<td><button type="button"
												class="button button-link-delete woo-envios-remove-tier">×</button></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
						<div style="margin-top: 15px;">
							<button type="button" class="button"
								id="woo-envios-add-tier"><?php esc_html_e('+ Adicionar Faixa', 'woo-envios'); ?></button>
						</div>
					</div>
				</div>

				<!-- Right Column: Settings -->
				<div>
					<!-- Integrations Section -->
					<div class="triqhub-card"
						style="margin-bottom: 25px; background: white; border-radius: 12px; padding: 25px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
						<h3 style="margin-top: 0; font-size: 16px; font-weight: 600;">
							<?php esc_html_e('Configurações de API', 'woo-envios'); ?></h3>

						<div style="margin-bottom: 20px;">
							<label style="display: block; font-weight: 500; margin-bottom: 8px;">Google Maps API Key</label>
							<input type="password" name="woo_envios_google_maps_api_key"
								value="<?php echo esc_attr(get_option('woo_envios_google_maps_api_key')); ?>"
								class="regular-text" style="width: 100%; border-radius: 8px; border: 1px solid #cbd5e1;">
							<p class="description" style="font-size: 11px; margin-top: 5px;">
								<?php esc_html_e('Necessário para Geocodificação precisa.', 'woo-envios'); ?></p>
						</div>

						<div style="border-top: 1px solid #f1f5f9; padding-top: 15px; margin-top: 15px;">
							<label style="display: flex; align-items: flex-start; gap: 12px; cursor: pointer;">
								<input type="checkbox" name="woo_envios_superfrete_enabled" value="1" <?php checked(get_option('woo_envios_superfrete_enabled', true), 1); ?>
									style="width: 18px; height: 18px; margin-top: 2px;">
								<div>
									<strong style="display: block; color: #1e293b;">Habilitar SuperFrete</strong>
									<span style="display: block; font-size: 12px; color: #64748b;">Ativa cotações automáticas de
										PAC/SEDEX quando fora do raio.</span>
								</div>
							</label>
						</div>
					</div>

					<!-- SuperFrete Config (Conditional) -->
					<div id="superfrete-settings"
						style="margin-bottom: 25px; background: #f8fafc; border-radius: 12px; padding: 25px; border: 1px solid #e2e8f0; <?php echo get_option('woo_envios_superfrete_enabled', true) ? '' : 'display:none;'; ?>">
						<h3 style="margin-top: 0; font-size: 15px; font-weight: 600;">Credenciais SuperFrete</h3>
						<div style="margin-bottom: 15px;">
							<label style="display: block; font-size: 12px; margin-bottom: 4px;">Token da API</label>
							<input type="password" name="woo_envios_superfrete_token"
								value="<?php echo esc_attr(get_option('woo_envios_superfrete_token')); ?>"
								style="width: 100%; border-radius: 6px; border: 1px solid #cbd5e1;">
						</div>
						<div style="margin-bottom: 15px;">
							<label style="display: block; font-size: 12px; margin-bottom: 4px;">CEP de Origem</label>
							<input type="text" name="woo_envios_superfrete_origin_cep"
								value="<?php echo esc_attr(get_option('woo_envios_superfrete_origin_cep', '38405320')); ?>"
								style="width: 100%; border-radius: 6px; border: 1px solid #cbd5e1;">
						</div>
						<div style="margin-bottom: 15px;">
							<label style="display: block; font-size: 12px; margin-bottom: 4px;">Margem de Lucro (%)</label>
							<input type="number" step="0.1" name="woo_envios_superfrete_profit_margin"
								value="<?php echo esc_attr(get_option('woo_envios_superfrete_profit_margin', 0)); ?>"
								style="width: 100%; border-radius: 6px; border: 1px solid #cbd5e1;">
						</div>
					</div>

					<!-- Debug Tool -->
					<div class="triqhub-card"
						style="background: #0f172a; border-radius: 12px; padding: 25px; color: white; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);">
						<h3 style="margin: 0 0 15px 0; font-size: 16px; font-weight: 600; color: white;">Testar Cálculo</h3>
						<input type="text" id="woo-envios-debug-address" placeholder="Digite um endereço para testar..."
							style="width: 100%; background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: white; border-radius: 8px; padding: 10px; margin-bottom: 10px;">
						<button type="button" class="button button-primary" id="woo-envios-debug-btn"
							style="width: 100%; border-radius: 8px; height: 40px; font-weight: 600;">Calcular Agora</button>
						<div id="woo-envios-debug-results"
							style="margin-top: 15px; font-size: 12px; color: #94a3b8; display: none; padding: 10px; background: rgba(0,0,0,0.2); border-radius: 6px;">
						</div>
					</div>
				</div>
			</div>

			<div style="margin-top: 30px;">
				<?php submit_button('Salvar Configurações', 'button button-primary button-hero', 'submit', true, array('style' => 'border-radius: 10px; background: #0f172a; border: none; height: 50px; padding: 0 40px; font-weight: 600;')); ?>
			</div>
		</form>

		<template id="woo-envios-tier-template">
			<tr>
				<td><input type="text" style="width: 100%; border-radius: 6px;"></td>
				<td><input type="number" step="0.1" style="width: 100%; border-radius: 6px;"></td>
				<td><input type="number" step="0.01" style="width: 100%; border-radius: 6px;"></td>
				<td><button type="button" class="button button-link-delete woo-envios-remove-tier">×</button></td>
			</tr>
		</template>
		<?php
		$connector->render_admin_footer();
	}

	/**
	 * Retorna coordenadas configuradas.
	 *
	 * @return array{lat:float,lng:float}
	 */
	public static function get_store_coordinates(): array
	{
		$lat = (float) get_option(self::OPTION_STORE_LAT, (string) WOO_ENVIOS_DEFAULT_LAT);
		$lng = (float) get_option(self::OPTION_STORE_LNG, (string) WOO_ENVIOS_DEFAULT_LNG);

		return array(
			'lat' => $lat,
			'lng' => $lng,
		);
	}

	/**
	 * Retorna as faixas salvas.
	 *
	 * @return array<int,array{label:string,distance:float,price:float}>
	 */
	public static function get_tiers(): array
	{
		$tiers = get_option(self::OPTION_TIERS, self::get_default_tiers());

		if (empty($tiers) || !is_array($tiers)) {
			return self::get_default_tiers();
		}

		return array_values($tiers);
	}

	/**
	 * Recupera a faixa correspondente a uma distância.
	 *
	 * @param float $distance Distância calculada.
	 *
	 * @return array|null
	 */
	public static function match_tier_by_distance(float $distance): ?array
	{
		foreach (self::get_tiers() as $tier) {
			if ($distance <= (float) $tier['distance']) {
				return $tier;
			}
		}

		return null;
	}

	/**
	 * Faixas padrão com CURVA REGRESSIVA.
	 *
	 * Lógica de precificação baseada em mercado Uber/99 de Uberlândia:
	 * - 1-3 km: Tarifa mínima R$ 7,90 (cobre custo mínimo do entregador)
	 * - 4-6 km: +R$ 1,00/km (zona quente - maioria das entregas)
	 * - 7-12 km: +R$ 0,60/km (curva 1 - desacelera o aumento)
	 * - 13-30 km: +R$ 0,50/km (curva 2 - distâncias longas mais atrativas)
	 *
	 * Vantagens:
	 * - Protege margem nas entregas curtas (onde Uber cobra mínimo)
	 * - Mantém competitividade nas longas (onde cliente compara com concorrência)
	 * - Evita "assalto" quando combinado com multiplicadores dinâmicos
	 *
	 * @return array
	 */
	private static function get_default_tiers(): array
	{
		return array(
			array('label' => 'Raio 1.0 km', 'distance' => 1, 'price' => 7.50),
			array('label' => 'Raio 2.0 km', 'distance' => 2, 'price' => 8.00),
			array('label' => 'Raio 3.0 km', 'distance' => 3, 'price' => 8.50),
			array('label' => 'Raio 4.0 km', 'distance' => 4, 'price' => 9.00),
			array('label' => 'Raio 5.0 km', 'distance' => 5, 'price' => 9.50),
			array('label' => 'Raio 6.0 km', 'distance' => 6, 'price' => 10.00),
			array('label' => 'Raio 7.0 km', 'distance' => 7, 'price' => 10.90),
			array('label' => 'Raio 8.0 km', 'distance' => 8, 'price' => 11.80),
			array('label' => 'Raio 9.0 km', 'distance' => 9, 'price' => 12.70),
			array('label' => 'Raio 10.0 km', 'distance' => 10, 'price' => 13.60),
			array('label' => 'Raio 11.0 km', 'distance' => 11, 'price' => 14.50),
			array('label' => 'Raio 12.0 km', 'distance' => 12, 'price' => 15.00),
			array('label' => 'Raio 13.0 km', 'distance' => 13, 'price' => 15.50),
			array('label' => 'Raio 14.0 km', 'distance' => 14, 'price' => 16.00),
			array('label' => 'Raio 15.0 km', 'distance' => 15, 'price' => 16.50),
			array('label' => 'Raio 16.0 km', 'distance' => 16, 'price' => 17.00),
			array('label' => 'Raio 17.0 km', 'distance' => 17, 'price' => 17.50),
			array('label' => 'Raio 18.0 km', 'distance' => 18, 'price' => 18.00),
			array('label' => 'Raio 19.0 km', 'distance' => 19, 'price' => 18.50),
			array('label' => 'Raio 20.0 km', 'distance' => 20, 'price' => 19.00),
			array('label' => 'Raio 21.0 km', 'distance' => 21, 'price' => 19.50),
			array('label' => 'Raio 22.0 km', 'distance' => 22, 'price' => 20.00),
			array('label' => 'Raio 23.0 km', 'distance' => 23, 'price' => 20.50),
			array('label' => 'Raio 24.0 km', 'distance' => 24, 'price' => 21.00),
			array('label' => 'Raio 25.0 km', 'distance' => 25, 'price' => 21.50),
			array('label' => 'Raio 26.0 km', 'distance' => 26, 'price' => 22.00),
			array('label' => 'Raio 27.0 km', 'distance' => 27, 'price' => 22.50),
			array('label' => 'Raio 28.0 km', 'distance' => 28, 'price' => 23.00),
			array('label' => 'Raio 29.0 km', 'distance' => 29, 'price' => 23.50),
			array('label' => 'Raio 30.0 km', 'distance' => 30, 'price' => 24.00),
		);
	}
}
