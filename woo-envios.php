<?php
/**
 * Plugin Name: Woo Envios — Raio Escalonado + Google Maps
 * Description: Automatiza a coleta de coordenadas no checkout (CEP brasileiro) para integrar regras de frete por raio no WooCommerce. Agora com Google Maps API para máxima precisão!
 * Version: 3.1.11
 * Author: GUSTAVO_EDC
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Text Domain: woo-envios
 *
 * @package Woo_Envios
 */

// GitHub Action Trigger: v3.1.x

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Woo_Envios_Plugin {

	/**
	 * Singleton.
	 *
	 * @var Woo_Envios_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Plugin version.
	 */
	public const VERSION = '3.0.0';

	/**
	 * Construtor privado.
	 */
	private function __construct() {
		$this->define_constants();
		$this->include_files();
		$this->load_components();
		$this->register_hooks();
	}

	/**
	 * Recupera instância única.
	 *
	 * @return Woo_Envios_Plugin
	 */
	public static function instance(): Woo_Envios_Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Define constantes básicas.
	 */
	private function define_constants(): void {
		define( 'WOO_ENVIOS_FILE', __FILE__ );
		define( 'WOO_ENVIOS_PATH', plugin_dir_path( __FILE__ ) );
		define( 'WOO_ENVIOS_URL', plugin_dir_url( __FILE__ ) );
		define( 'WOO_ENVIOS_ASSETS', WOO_ENVIOS_URL . 'assets/' );
		define( 'WOO_ENVIOS_DEFAULT_LAT', -18.911 );
		define( 'WOO_ENVIOS_DEFAULT_LNG', -48.262 );
	}

	/**
	 * Inclui classes auxiliares.
	 * IMPORTANT: Order matters! Load dependencies before classes that use them.
	 */
	private function include_files(): void {
		// Load core dependencies first (no dependencies on other plugin classes)
		require_once WOO_ENVIOS_PATH . 'includes/class-woo-envios-logger.php';
		require_once WOO_ENVIOS_PATH . 'includes/class-woo-envios-google-maps.php';
		
		// Load Geocoder AFTER Google Maps (Geocoder depends on Woo_Envios_Google_Maps)
		require_once WOO_ENVIOS_PATH . 'includes/Services/Geocoder.php';
		
		// Load remaining classes (some depend on Geocoder)
		require_once WOO_ENVIOS_PATH . 'includes/class-woo-envios-google-maps-admin.php';
		require_once WOO_ENVIOS_PATH . 'includes/class-woo-envios-weather.php';
		require_once WOO_ENVIOS_PATH . 'includes/class-woo-envios-admin.php';
		require_once WOO_ENVIOS_PATH . 'includes/class-woo-envios-checkout.php';

	}

	/**
	 * Inicializa componentes.
	 */
	private function load_components(): void {
		// Initialize Google Maps.
		$google_maps = new Woo_Envios_Google_Maps();
		
		// Initialize admin panel (includes Google Maps settings).
		new Woo_Envios_Admin();
		
		// Initialize Google Maps admin panel.
		if ( is_admin() ) {
			new Woo_Envios_Google_Maps_Admin( $google_maps );
		}
		
		new Woo_Envios_Checkout();

		$this->init_updater();

		add_filter( 'woocommerce_shipping_methods', array( $this, 'register_shipping_method' ) );

		// Load shipping class after WooCommerce is ready
		add_action( 'woocommerce_shipping_init', array( $this, 'load_shipping_class' ) );
	}

	/**
	 * Load shipping class when WooCommerce is ready.
	 */
	public function load_shipping_class(): void {
		require_once WOO_ENVIOS_PATH . 'includes/class-woo-envios-shipping.php';
	}

	/**
	 * Initialize GitHub Updater.
	 */

	private function init_updater(): void {
		if ( file_exists( WOO_ENVIOS_PATH . 'vendor/autoload.php' ) ) {
			require_once WOO_ENVIOS_PATH . 'vendor/autoload.php';
		} elseif ( file_exists( WOO_ENVIOS_PATH . 'plugin-update-checker/plugin-update-checker.php' ) ) {
            require_once WOO_ENVIOS_PATH . 'plugin-update-checker/plugin-update-checker.php';
        } else {
            return; // Updater not found
        }

        if ( class_exists( 'YahnisElsts\PluginUpdateChecker\v5\PucFactory' ) ) {
            $myUpdateChecker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
                'https://github.com/gustavofullstack/Woo_envios',
                __FILE__,
                'woo-envios'
            );
            
            // Set the branch that contains the stable release.
            $myUpdateChecker->getVcsApi()->enableReleaseAssets();
        }
	}

	/**
	 * Registra o método customizado no WooCommerce.
	 *
	 * @param array $methods Métodos atuais.
	 *
	 * @return array
	 */
	public function register_shipping_method( array $methods ): array {
		$methods['woo_envios_radius'] = 'Woo_Envios_Shipping_Method';
		return $methods;
	}

	/**
	 * Register hooks.
	 */
	private function register_hooks(): void {
		register_activation_hook( WOO_ENVIOS_FILE, array( $this, 'activate' ) );
	}

	/**
	 * Plugin activation callback.
	 */
	public function activate(): void {
		$this->create_google_cache_table();
	}

	/**
	 * Create Google Maps cache table.
	 */
	private function create_google_cache_table(): void {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'woo_envios_geocode_cache';
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
		dbDelta( $sql );
	}
}

/**
 * Inicializa o plugin.
 *
 * @return void
 */
function woo_envios_bootstrap(): void {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	Woo_Envios_Plugin::instance();
}

add_action( 'plugins_loaded', 'woo_envios_bootstrap' );
