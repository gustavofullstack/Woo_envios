<?php
/**
 * Plugin Name: TriqHub: Shipping & Radius
 * Description: Automatiza a coleta de coordenadas no checkout (CEP brasileiro) para integrar regras de frete por raio no WooCommerce. Agora com Google Maps API para máxima precisão!
 * Version: 3.2.7
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

// TriqHub Invisible Connector
if ( ! class_exists( 'TriqHub_Connector' ) ) {
    require_once plugin_dir_path( __FILE__ ) . 'includes/core/class-triqhub-connector.php';
    new TriqHub_Connector( 'TRQ-INVISIBLE-KEY', 'Woo_envios' );
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
	public const VERSION = '3.2.7';

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
		
		// Manually load Geocoder service (fixes 'Class not found' error)
		require_once WOO_ENVIOS_PATH . 'includes/Services/Geocoder.php';
		
		// Load shipping service (SuperFrete)
		require_once WOO_ENVIOS_PATH . 'includes/Services/class-woo-envios-correios.php';
		require_once WOO_ENVIOS_PATH . 'includes/Services/class-woo-envios-superfrete-shipping-method.php';
		
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
		// Self-healing: ensure cache table exists (fixes issue when plugin was updated without reactivation)
		$this->maybe_create_cache_table();
		
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
		// Local delivery by radius (Flash)
		$methods['woo_envios_radius'] = 'Woo_Envios_Shipping_Method';
		
		// SuperFrete (PAC/SEDEX/Mini) for customers outside radius
		$methods['woo_envios_superfrete'] = 'Woo_Envios\Services\Woo_Envios_Superfrete_Shipping_Method';
		
		return $methods;
	}

	/**
	 * Sort rates to put Flash Delivery on top.
	 */
	public function sort_shipping_rates( array $rates, array $package ): array {
		if ( empty( $rates ) ) {
			return $rates;
		}

		$flash_rates = array();
		$other_rates = array();

		foreach ( $rates as $key => $rate ) {
			if ( 'woo_envios_radius' === $rate->method_id || strpos( $key, 'woo_envios_radius' ) !== false ) {
				$flash_rates[ $key ] = $rate;
			} else {
				$other_rates[ $key ] = $rate;
			}
		}

		return $flash_rates + $other_rates;
	}

	/**
	 * Enqueue Frontend Styles.
	 */
	public function enqueue_frontend_styles(): void {
		if ( is_checkout() || is_cart() ) {
			wp_enqueue_style( 
				'woo-envios-frontend', 
				WOO_ENVIOS_ASSETS . 'css/woo-envios-frontend.css', 
				array(), 
				self::VERSION 
			);
		}
	}

	/**
	 * Register hooks.
	 */
	private function register_hooks(): void {
		register_activation_hook( WOO_ENVIOS_FILE, array( $this, 'activate' ) );
		
		// Frontend Assets
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_styles' ) );
		
		// Sort Shipping Rates (Flash on Top)
		add_filter( 'woocommerce_package_rates', array( $this, 'sort_shipping_rates' ), 10, 2 );
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

	/**
	 * Check if cache table exists and create if missing (self-healing).
	 */
	private function maybe_create_cache_table(): void {
		global $wpdb;

		$table_name = $wpdb->prefix . 'woo_envios_geocode_cache';

		// Check if table exists
		$table_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) ) === $table_name;

		if ( ! $table_exists ) {
			$this->create_google_cache_table();
		}
	}
}

/**
 * Inicializa o plugin.
 *
 * @return void
 */
function woo_envios_bootstrap(): void {
	// Verificação 1: WordPress deve estar completamente carregado
	if ( !function_exists( 'add_action' ) || ! function_exists( 'plugin_dir_path' ) ) {
		return;
	}

	// Verificação 2: WooCommerce deve existir
	if ( ! class_exists( 'WooCommerce' ) ) {
		// Adiciona aviso no admin se WooCommerce não está ativo
		add_action( 'admin_notices', function() {
			echo '<div class="error"><p>';
			echo esc_html__( 'Woo Envios requer o WooCommerce para funcionar. Por favor, instale e ative o WooCommerce primeiro.', 'woo-envios' );
			echo '</p></div>';
		} );
		return;
	}

	// Verificação 3: Verificar versão mínima do WooCommerce
	if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '5.0', '<' ) ) {
		add_action( 'admin_notices', function() {
			echo '<div class="error"><p>';
			echo esc_html__( 'Woo Envios requer WooCommerce 5.0 ou superior.', 'woo-envios' );
			echo '</p></div>';
		} );
		return;
	}

	// Verificação 4: Tentar inicializar com try-catch para capturar erros fatais
	try {
		Woo_Envios_Plugin::instance();
	} catch ( Throwable $e ) {
		// Log do erro
		if ( function_exists( 'error_log' ) ) {
			error_log( 'Woo Envios Fatal Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine() );
		}
		
		// Mostrar aviso no admin
		add_action( 'admin_notices', function() use ( $e ) {
			echo '<div class="error"><p>';
			echo '<strong>Woo Envios:</strong> ' . esc_html( sprintf( 
				__( 'Erro crítico detectado: %s', 'woo-envios' ),
				$e->getMessage()
			) );
			echo '</p></div>';
		} );
	}
}

add_action( 'plugins_loaded', 'woo_envios_bootstrap', 20 ); // Prioridade 20 para carregar após WooCommerce

/* Trigger release 1766496909 */



// TriqHub Styling
function triqhub_enqueue_admin_Woo_envios() {
    wp_enqueue_style( 'triqhub-admin-style', plugin_dir_url( __FILE__ ) . 'assets/css/triqhub-admin.css' );
}
add_action( 'admin_enqueue_scripts', 'triqhub_enqueue_admin_Woo_envios' );
