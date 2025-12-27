<?php
/**
 * Google Maps Admin Panel.
 *
 * Provides configuration interface and wizard for Google Maps API setup.
 *
 * @package UDI_Custom_Login
 */

if (!defined('ABSPATH')) {
	exit;
}

if (!class_exists('Woo_Envios_Google_Maps_Admin')) {

	class Woo_Envios_Google_Maps_Admin
	{

		/**
		 * Google Maps instance.
		 *
		 * @var Woo_Envios_Google_Maps
		 */
		protected $google_maps;

		/**
		 * Constructor.
		 *
		 * @param Woo_Envios_Google_Maps $google_maps Google Maps instance.
		 */
		public function __construct(Woo_Envios_Google_Maps $google_maps)
		{
			$this->google_maps = $google_maps;

			// Don't create separate menu - will be integrated in main admin page
			add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
			add_action('wp_ajax_udi_test_google_maps', array($this, 'ajax_test_connection'));
			add_action('wp_ajax_udi_save_google_maps_key', array($this, 'ajax_save_api_key'));
			add_action('wp_ajax_udi_clear_geocode_cache', array($this, 'ajax_clear_cache'));
		}

		/**
		 * Enqueue admin assets.
		 *
		 * @param string $hook Current admin page hook.
		 * @return void
		 */
		public function enqueue_admin_assets($hook)
		{
			// Only load on main Woo Envios settings page
			if ('woocommerce_page_woo-envios' !== $hook) {
				return;
			}

			wp_enqueue_style(
				'udi-google-maps-admin',
				WOO_ENVIOS_URL . 'assets/css/admin-google-maps.css',
				array(),
				TriqHub_Shipping_Plugin::VERSION
			);

			wp_enqueue_script(
				'udi-google-maps-admin',
				WOO_ENVIOS_URL . 'assets/js/admin-google-maps.js',
				array('jquery'),
				TriqHub_Shipping_Plugin::VERSION,
				true
			);

			wp_localize_script(
				'udi-google-maps-admin',
				'udiGoogleMapsAdmin',
				array(
					'ajaxUrl' => admin_url('admin-ajax.php'),
					'nonce' => wp_create_nonce('woo_envios_google_maps_admin'),
					'strings' => array(
						'testing' => __('Testando conexão...', 'woo-envios'),
						'success' => __('Conexão estabelecida com sucesso!', 'woo-envios'),
						'error' => __('Erro ao conectar. Verifique sua API Key.', 'woo-envios'),
						'saving' => __('Salvando...', 'woo-envios'),
						'saved' => __('Configurações salvas!', 'woo-envios'),
						'clearingCache' => __('Limpando cache...', 'woo-envios'),
						'cacheCleared' => __('Cache limpo com sucesso!', 'woo-envios'),
					),
				)
			);
		}

		/**
		 * Render admin page.
		 *
		 * @return void
		 */
		public function render_admin_page()
		{
			$api_key = get_option('udi_google_maps_api_key', '');
			$is_configured = !empty($api_key);
			$show_wizard = !$is_configured && empty($_GET['skip_wizard']); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

			$template_path = WOO_ENVIOS_PATH . 'includes/admin-google-maps-settings.php';
			if (file_exists($template_path)) {
				include $template_path;
			} else {
				echo '<div class="error"><p>' . esc_html__('Erro: Arquivo de template de configurações não encontrado.', 'woo-envios') . '</p></div>';
			}
		}

		/**
		 * AJAX: Test Google Maps API connection.
		 *
		 * @return void
		 */
		public function ajax_test_connection()
		{
			check_ajax_referer('woo_envios_google_maps_admin', 'nonce');

			if (!current_user_can('manage_woocommerce')) {
				wp_send_json_error(array('message' => __('Permissão negada.', 'woo-envios')));
			}

			// Temporarily set API key if provided.
			$test_key = !empty($_POST['api_key']) ? sanitize_text_field(wp_unslash($_POST['api_key'])) : '';
			if ($test_key) {
				$old_key = get_option('udi_google_maps_api_key', '');
				update_option('udi_google_maps_api_key', $test_key);
				$this->google_maps = new Woo_Envios_Google_Maps(); // Reinitialize with new key.
			}

			$results = $this->google_maps->test_connection();

			// Restore old key if testing.
			if ($test_key && isset($old_key)) {
				update_option('udi_google_maps_api_key', $old_key);
			}

			$all_passed = $results['geocoding'] && $results['places'] && $results['distance'];

			wp_send_json_success(
				array(
					'connected' => $all_passed,
					'results' => $results,
				)
			);
		}

		/**
		 * AJAX: Save API Key.
		 *
		 * @return void
		 */
		public function ajax_save_api_key()
		{
			check_ajax_referer('woo_envios_google_maps_admin', 'nonce');

			if (!current_user_can('manage_woocommerce')) {
				wp_send_json_error(array('message' => __('Permissão negada.', 'woo-envios')));
			}

			$api_key = !empty($_POST['api_key']) ? sanitize_text_field(wp_unslash($_POST['api_key'])) : '';

			if (empty($api_key)) {
				wp_send_json_error(array('message' => __('API Key não pode estar vazia.', 'woo-envios')));
			}

			update_option('udi_google_maps_api_key', $api_key);
			update_option('udi_google_maps_enabled', true);

			// Save additional settings.
			$cache_ttl = !empty($_POST['cache_ttl']) ? absint($_POST['cache_ttl']) : 30;
			update_option('udi_google_maps_cache_ttl', $cache_ttl * 86400); // days to seconds

			$autocomplete_enabled = !empty($_POST['autocomplete_enabled']);
			update_option('udi_google_maps_autocomplete_enabled', $autocomplete_enabled);

			$validate_addresses = !empty($_POST['validate_addresses']);
			update_option('udi_google_maps_validate_addresses', $validate_addresses);

			wp_send_json_success(array('message' => __('Configurações salvas com sucesso!', 'woo-envios')));
		}

		/**
		 * AJAX: Clear geocode cache.
		 *
		 * @return void
		 */
		public function ajax_clear_cache()
		{
			check_ajax_referer('woo_envios_google_maps_admin', 'nonce');

			if (!current_user_can('manage_woocommerce')) {
				wp_send_json_error(array('message' => __('Permissão negada.', 'woo-envios')));
			}

			$type = !empty($_POST['type']) ? sanitize_text_field(wp_unslash($_POST['type'])) : 'expired';

			if ('all' === $type) {
				$deleted = $this->google_maps->clear_all_cache();
			} else {
				$deleted = $this->google_maps->clear_expired_cache();
			}

			wp_send_json_success(
				array(
					'message' => sprintf(
						/* translators: %d: number of cache entries deleted */
						_n('%d entrada removida do cache.', '%d entradas removidas do cache.', $deleted, 'woo-envios'),
						$deleted
					),
				)
			);
		}

		/**
		 * Get API usage stats.
		 *
		 * @return array Stats data.
		 */
		public function get_usage_stats()
		{
			global $wpdb;

			$table_name = $wpdb->prefix . 'udi_geocode_cache';

			// Check if table exists.
			if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
				return array(
					'total_cached' => 0,
					'cached_today' => 0,
					'cached_month' => 0,
					'expired_count' => 0,
				);
			}

			$total = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");

			$today = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM $table_name WHERE DATE(created_at) = %s",
					gmdate('Y-m-d')
				)
			);

			$month = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM $table_name WHERE created_at >= %s",
					gmdate('Y-m-01')
				)
			);

			$expired = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE expires_at < NOW()");

			return array(
				'total_cached' => (int) $total,
				'cached_today' => (int) $today,
				'cached_month' => (int) $month,
				'expired_count' => (int) $expired,
			);
		}

	}
}
