<?php
/**
 * Configurações do plugin Woo Envios.
 *
 * @package Woo_Envios
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Woo_Envios\Services\Geocoder;

final class Woo_Envios_Admin {

	/**
	 * Slug da página de configurações.
	 */
	private const PAGE_SLUG = 'woo-envios';

	/**
	 * Option names.
	 */
	private const OPTION_STORE_LABEL = 'woo_envios_store_label';
	private const OPTION_STORE_LAT   = 'woo_envios_store_lat';
	private const OPTION_STORE_LNG   = 'woo_envios_store_lng';
	private const OPTION_TIERS       = 'woo_envios_tiers';

	/**
	 * Construtor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_order_debug_metabox' ) );

		// AJAX Debug Tools
		add_action( 'wp_ajax_woo_envios_debug_geocode', array( $this, 'ajax_debug_geocode' ) );
		add_action( 'wp_ajax_woo_envios_clear_cache', array( $this, 'ajax_clear_cache' ) );
	}

	/**
	 * AJAX: Testa geocodificação e cálculo de distância.
	 */
	public function ajax_debug_geocode(): void {
		// Log entry
		error_log( 'Woo Envios Debug: ajax_debug_geocode called' );
		
		check_ajax_referer( 'woo_envios_debug_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permissão negada.' );
		}

		$address = isset( $_POST['address'] ) ? sanitize_text_field( wp_unslash( $_POST['address'] ) ) : '';

		if ( empty( $address ) ) {
			wp_send_json_error( 'Endereço inválido.' );
		}

		// Use Google Maps class directly to get specific errors
		$google_maps = new Woo_Envios_Google_Maps();
		
		if ( ! $google_maps->is_configured() ) {
			wp_send_json_error( 'API do Google Maps não configurada. Verifique a chave API nas configurações.' );
		}

		$geocode_result = $google_maps->geocode_address( $address );

		if ( is_wp_error( $geocode_result ) ) {
			wp_send_json_error( 'Erro na Geocodificação: ' . $geocode_result->get_error_message() );
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
		
		if ( $google_maps->is_configured() ) {
			$origin = $store['lat'] . ',' . $store['lng'];
			$destination = $coords['lat'] . ',' . $coords['lng'];
			
			$distance_data = $google_maps->calculate_distance( $origin, $destination );
			
			if ( ! is_wp_error( $distance_data ) && ! empty( $distance_data ) ) {
				// Use real route distance
				$distance = round( $distance_data['distance_value'] / 1000, 2 );
				$distance_method = 'Google Maps (rota real)';
			} else {
				// Log the error if it failed
				if ( is_wp_error( $distance_data ) ) {
					error_log( 'Woo Envios Debug Error: ' . $distance_data->get_error_message() );
					$distance_method = 'Haversine (fallback - Erro API: ' . $distance_data->get_error_message() . ')';
				}
			}
		}
		
		// Fallback to Haversine if Distance Matrix failed
		if ( $distance === 0 ) {
			$earth_radius = 6371;
			$lat_from = deg2rad( $store['lat'] );
			$lng_from = deg2rad( $store['lng'] );
			$lat_to   = deg2rad( $coords['lat'] );
			$lng_to   = deg2rad( $coords['lng'] );

			$dlat = $lat_to - $lat_from;
			$dlng = $lng_to - $lng_from;

			$a = sin( $dlat / 2 ) ** 2 +
				cos( $lat_from ) * cos( $lat_to ) *
				sin( $dlng / 2 ) ** 2;

			$c = 2 * atan2( sqrt( $a ), sqrt( 1 - $a ) );
			$distance = round( $earth_radius * $c, 2 );
		}

		// Check tier
		$tier = self::match_tier_by_distance( $distance );

		wp_send_json_success( array(
			'coords'          => $coords,
			'store'           => $store,
			'distance'        => $distance,
			'distance_method' => $distance_method,
			'tier'            => $tier,
		) );
	}

	/**
	 * AJAX: Limpa cache de geocodificação.
	 */
	public function ajax_clear_cache(): void {
		check_ajax_referer( 'woo_envios_debug_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permissão negada.' );
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'woo_envios_geocode_cache';
		
		// Check if table exists
		if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) ) === $table_name ) {
			$wpdb->query( "TRUNCATE TABLE $table_name" );
			wp_send_json_success( 'Cache limpo com sucesso.' );
		} else {
			wp_send_json_error( 'Tabela de cache não encontrada.' );
		}
	}

	/**
	 * Adiciona metabox de debug na edição do pedido.
	 */
	public function add_order_debug_metabox(): void {
		add_meta_box(
			'woo_envios_debug',
			__( 'Woo Envios — Debug de Entrega', 'woo-envios' ),
			array( $this, 'render_order_debug_metabox' ),
			'shop_order',
			'side',
			'default'
		);
		// Support for HPOS
		add_meta_box(
			'woo_envios_debug',
			__( 'Woo Envios — Debug de Entrega', 'woo-envios' ),
			array( $this, 'render_order_debug_metabox' ),
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
	public function render_order_debug_metabox( $post_or_order_object ): void {
		$order = ( $post_or_order_object instanceof WC_Order ) ? $post_or_order_object : wc_get_order( $post_or_order_object->ID );

		if ( ! $order ) {
			return;
		}

		$shipping_methods = $order->get_shipping_methods();
		$is_woo_envios = false;
		$saved_debug = array();

		foreach ( $shipping_methods as $shipping_method ) {
			if ( 'woo_envios_radius' === $shipping_method->get_method_id() ) {
				$is_woo_envios = true;
				$saved_debug = $shipping_method->get_meta( 'debug_info' );
				break;
			}
		}

		if ( ! $is_woo_envios ) {
			echo '<p>' . esc_html__( 'Este pedido não utilizou o método Woo Envios.', 'woo-envios' ) . '</p>';
			return;
		}

		if ( empty( $saved_debug ) ) {
			// Try legacy/fallback if needed, or just show message
			echo '<p>' . esc_html__( 'Dados de debug não disponíveis para este pedido.', 'woo-envios' ) . '</p>';
			// Fallback to showing current store config
			$store = self::get_store_coordinates();
			echo '<p><strong>' . esc_html__( 'Configuração Atual da Loja:', 'woo-envios' ) . '</strong><br>';
			echo sprintf( 'Lat: %s<br>Lng: %s', esc_html( (string) $store['lat'] ), esc_html( (string) $store['lng'] ) );
			echo '</p>';
			return;
		}

		$store_lat = $saved_debug['store']['lat'] ?? '';
		$store_lng = $saved_debug['store']['lng'] ?? '';
		$cust_lat  = $saved_debug['customer']['lat'] ?? '';
		$cust_lng  = $saved_debug['customer']['lng'] ?? '';
		$distance  = $saved_debug['distance'] ?? 0;

		echo '<div class="woo-envios-debug-info">';
		
		echo '<p><strong>' . esc_html__( 'Distância Calculada:', 'woo-envios' ) . '</strong> ' . esc_html( $distance ) . ' km</p>';

		if ( $store_lat && $store_lng ) {
			echo '<p><strong>' . esc_html__( 'Ponto da Loja (Origem):', 'woo-envios' ) . '</strong><br>';
			echo sprintf( '<a href="https://www.google.com/maps?q=%1$s,%2$s" target="_blank">%1$s, %2$s</a>', esc_attr( $store_lat ), esc_attr( $store_lng ) );
			echo '</p>';
		}

		if ( $cust_lat && $cust_lng ) {
			echo '<p><strong>' . esc_html__( 'Ponto do Cliente (Destino):', 'woo-envios' ) . '</strong><br>';
			echo sprintf( '<a href="https://www.google.com/maps?q=%1$s,%2$s" target="_blank">%1$s, %2$s</a>', esc_attr( $cust_lat ), esc_attr( $cust_lng ) );
			echo '</p>';
		}

		if ( $store_lat && $store_lng && $cust_lat && $cust_lng ) {
			echo '<p><a href="https://www.google.com/maps/dir/?api=1&origin=' . esc_attr( "$store_lat,$store_lng" ) . '&destination=' . esc_attr( "$cust_lat,$cust_lng" ) . '" target="_blank" class="button button-primary">' . esc_html__( 'Ver Rota no Google Maps', 'woo-envios' ) . '</a></p>';
		}

		echo '</div>';
	}


	/**
	 * Registra submenu em WooCommerce.
	 */
	public function register_menu(): void {
		add_submenu_page(
			'woocommerce',
			__( 'Woo Envios', 'woo-envios' ),
			__( 'Woo Envios', 'woo-envios' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Registra opções utilizadas pelo plugin.
	 */
	public function register_settings(): void {
		register_setting(
			'woo_envios_settings',
			self::OPTION_STORE_LABEL,
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => __( 'Base Uberlândia', 'woo-envios' ),
			)
		);

		register_setting(
			'woo_envios_settings',
			self::OPTION_STORE_LAT,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_coordinate' ),
				'default'           => (string) WOO_ENVIOS_DEFAULT_LAT,
			)
		);

		register_setting(
			'woo_envios_settings',
			self::OPTION_STORE_LNG,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_coordinate' ),
				'default'           => (string) WOO_ENVIOS_DEFAULT_LNG,
			)
		);

		register_setting(
			'woo_envios_settings',
			self::OPTION_TIERS,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_tiers' ),
				'default'           => self::get_default_tiers(),
			)
		);

		// Google Maps API Key
		register_setting(
			'woo_envios_settings',
			'woo_envios_google_maps_api_key',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			)
		);

		// Dynamic Pricing Settings
		register_setting( 'woo_envios_settings', 'woo_envios_dynamic_pricing_enabled', array( 'type' => 'boolean', 'default' => false ) );
		register_setting( 'woo_envios_settings', 'woo_envios_peak_hours', array( 'type' => 'array' ) );
		register_setting( 'woo_envios_settings', 'woo_envios_weekend_multiplier', array( 'type' => 'number', 'default' => 1.2 ) );
		register_setting( 'woo_envios_settings', 'woo_envios_max_multiplier', array( 'type' => 'number', 'default' => 2.0 ) );
		register_setting( 'woo_envios_settings', 'woo_envios_weather_api_key', array( 'type' => 'string', 'default' => '' ) );
		register_setting( 'woo_envios_settings', 'woo_envios_rain_light_multiplier', array( 'type' => 'number', 'default' => 1.2 ) );
		register_setting( 'woo_envios_settings', 'woo_envios_rain_heavy_multiplier', array( 'type' => 'number', 'default' => 1.5 ) );
		
		// Logging Settings
		register_setting( 'woo_envios_settings', 'woo_envios_enable_logs', array( 'type' => 'boolean', 'default' => false ) );
	}

	/**
	 * Carrega scripts específicos da página do plugin.
	 *
	 * @param string $hook Página atual.
	 */
	public function enqueue_assets( string $hook ): void {
		if ( 'woocommerce_page_' . self::PAGE_SLUG !== $hook ) {
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
			array( 'leaflet' ),
			Woo_Envios_Plugin::VERSION
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
			array( 'leaflet' ),
			time(), // Force cache busting
			true
		);

		wp_localize_script(
			'woo-envios-admin',
			'WooEnviosAdminData',
			array(
				'storeLat'      => (float) self::get_store_coordinates()['lat'],
				'storeLng'      => (float) self::get_store_coordinates()['lng'],
				'tiers'         => self::get_tiers(),
				'mapTileUrl'    => 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
				'mapAttr'       => '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">OpenStreetMap</a>',
				'zoom'          => 12,
				'strings'       => array(
					'setMarker'    => __( 'Arraste o marcador para definir o ponto de origem das entregas.', 'woo-envios' ),
					'addTier'      => __( 'Adicionar faixa', 'woo-envios' ),
					'remove'       => __( 'Remover', 'woo-envios' ),
					'newTierLabel' => __( 'Novo raio', 'woo-envios' ),
					'searchPlaceholder' => __( 'Ex: Rua Iguaçu, 1400 - Uberlândia', 'woo-envios' ),
					'searching'   => __( 'Pesquisando endereço…', 'woo-envios' ),
					'noResults'   => __( 'Nenhum resultado encontrado para este termo.', 'woo-envios' ),
				),
				'debugNonce'    => wp_create_nonce( 'woo_envios_debug_nonce' ),
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
			)
		);
	}

	/**
	 * Sanitiza coordenadas.
	 *
	 * @param string $value Valor enviado.
	 */
	public function sanitize_coordinate( string $value ): string {
		$value = str_replace( ',', '.', $value );
		return is_numeric( $value ) ? (string) $value : '0';
	}

	/**
	 * Sanitiza faixas de distância.
	 *
	 * @param mixed $tiers Dados recebidos.
	 *
	 * @return array
	 */
	public function sanitize_tiers( $tiers ): array {
		if ( ! is_array( $tiers ) ) {
			return self::get_default_tiers();
		}

		$sanitized = array();

		foreach ( $tiers as $tier ) {
			$distance = isset( $tier['distance'] ) ? (float) str_replace( ',', '.', (string) $tier['distance'] ) : 0;
			$price    = isset( $tier['price'] ) ? (float) wc_format_decimal( $tier['price'] ) : 0;
			$label    = isset( $tier['label'] ) ? sanitize_text_field( $tier['label'] ) : '';

			if ( $distance <= 0 || $price < 0 ) {
				continue;
			}

			$sanitized[] = array(
				'label'    => $label ?: sprintf( __( 'Raio %.1f km', 'woo-envios' ), $distance ),
				'distance' => $distance,
				'price'    => $price,
			);
		}

		if ( empty( $sanitized ) ) {
			return self::get_default_tiers();
		}

		usort(
			$sanitized,
			static function ( $a, $b ) {
				return $a['distance'] <=> $b['distance'];
			}
		);

		return $sanitized;
	}

	/**
	 * Renderiza página de configurações.
	 */
	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$store_label = get_option( self::OPTION_STORE_LABEL, __( 'Base Uberlândia', 'woo-envios' ) );
		$coords      = self::get_store_coordinates();
		$tiers       = self::get_tiers();
		?>
		<div class="wrap woo-envios-wrap">
			<h1><?php esc_html_e( 'Woo Envios — Configuração de Raio', 'woo-envios' ); ?></h1>

			<form method="post" action="options.php">
				<?php settings_fields( 'woo_envios_settings' ); ?>

				<section class="woo-envios-card">
					<h2><?php esc_html_e( 'Origem das Entregas', 'woo-envios' ); ?></h2>
					<p><?php esc_html_e( 'Defina o ponto central das entregas locais. Use o mapa gratuito (OpenStreetMap) para posicionar a base na cidade de Uberlândia.', 'woo-envios' ); ?></p>

					<?php if ( (string) $coords['lat'] === (string) WOO_ENVIOS_DEFAULT_LAT && (string) $coords['lng'] === (string) WOO_ENVIOS_DEFAULT_LNG ) : ?>
						<div style="padding: 10px; background: #f8d7da; border-left: 4px solid #dc3545; margin-bottom: 15px; color: #721c24;">
							<strong><?php esc_html_e( '⚠️ Atenção: Coordenadas Padrão Detectadas', 'woo-envios' ); ?></strong><br>
							<?php esc_html_e( 'Você está usando as coordenadas padrão do plugin. Isso pode causar cálculos de distância incorretos. Por favor, arraste o marcador no mapa abaixo para a localização real da sua loja.', 'woo-envios' ); ?>
						</div>
					<?php endif; ?>

					<label class="woo-envios-field">
						<span><?php esc_html_e( 'Nome da base', 'woo-envios' ); ?></span>
						<input
							type="text"
							name="<?php echo esc_attr( self::OPTION_STORE_LABEL ); ?>"
							value="<?php echo esc_attr( (string) $store_label ); ?>"
							class="regular-text"
						/>
					</label>

					<div class="woo-envios-search">
						<span><?php esc_html_e( 'Pesquisar endereço da base', 'woo-envios' ); ?></span>
						<div class="woo-envios-search-controls">
							<input
								type="text"
								id="woo-envios-search-input"
								placeholder="<?php esc_attr_e( 'Rua Iguaçu, 1400 - Uberlândia', 'woo-envios' ); ?>"
							/>
							<button type="button" class="button" id="woo-envios-search-btn">
								<?php esc_html_e( 'Buscar', 'woo-envios' ); ?>
							</button>
						</div>
						<p class="description"><?php esc_html_e( 'Busca gratuita usando o Nominatim/OpenStreetMap. Clique em um resultado para fixar o marcador.', 'woo-envios' ); ?></p>
						<ul id="woo-envios-search-results"></ul>
					</div>

					<div id="woo-envios-admin-map" data-lat="<?php echo esc_attr( (string) $coords['lat'] ); ?>" data-lng="<?php echo esc_attr( (string) $coords['lng'] ); ?>"></div>
					<p class="description"><?php esc_html_e( 'Clique ou arraste o marcador para ajustar a localização.', 'woo-envios' ); ?></p>

					<div class="woo-envios-grid">
						<label class="woo-envios-field">
							<span><?php esc_html_e( 'Latitude', 'woo-envios' ); ?></span>
							<input type="text" id="woo_envios_store_lat" name="<?php echo esc_attr( self::OPTION_STORE_LAT ); ?>" value="<?php echo esc_attr( (string) $coords['lat'] ); ?>" readonly />
						</label>

						<label class="woo-envios-field">
							<span><?php esc_html_e( 'Longitude', 'woo-envios' ); ?></span>
							<input type="text" id="woo_envios_store_lng" name="<?php echo esc_attr( self::OPTION_STORE_LNG ); ?>" value="<?php echo esc_attr( (string) $coords['lng'] ); ?>" readonly />
						</label>
					</div>
				</section>

				<section class="woo-envios-card">
					<h2><?php esc_html_e( 'Configuração Google Maps API', 'woo-envios' ); ?></h2>
					<p><?php esc_html_e( 'Configure a API do Google Maps para geocodificação precisa de endereços brasileiros. O sistema usa cache inteligente para economizar requisições.', 'woo-envios' ); ?></p>

					<label class="woo-envios-field">
						<span><?php esc_html_e( 'Google Maps API Key', 'woo-envios' ); ?></span>
						<input
							type="text"
							name="woo_envios_google_maps_api_key"
							value="<?php echo esc_attr( get_option( 'woo_envios_google_maps_api_key', '' ) ); ?>"
							class="regular-text"
							placeholder="AIzaSy..."
						/>
						<p class="description">
							<?php
							printf(
								/* translators: %s: Link to Google Cloud Console */
								esc_html__( 'Obtenha sua chave gratuita no %s. Você tem $200 USD/mês grátis (~40.000 requisições).', 'woo-envios' ),
								'<a href="https://console.cloud.google.com/apis/credentials" target="_blank" rel="noopener">Google Cloud Console</a>'
							);
							?>
						</p>
					</label>

					<?php
					$api_key_configured = ! empty( get_option( 'woo_envios_google_maps_api_key', '' ) );
					if ( $api_key_configured ) :
						?>
						<div style="padding: 10px; background: #d4edda; border-left: 4px solid #28a745; margin-top: 10px;">
							<strong>✓ Google Maps Configurado</strong><br>
							<small>O sistema está usando Google Maps API para geocodificação. O mapa acima usa OpenStreetMap apenas para visualização (gratuito).</small>
						</div>
					<?php else : ?>
						<div style="padding: 10px; background: #fff3cd; border-left: 4px solid #ffc107; margin-top: 10px;">
							<strong>⚠ Google Maps Não Configurado</strong><br>
							<small>Adicione uma API Key acima para usar geocodificação precisa do Google Maps. Coordenadas padrão serão usadas enquanto não configurado.</small>
						</div>
					<?php endif; ?>
				</section>

				<section class="woo-envios-card">
					<h2><?php esc_html_e( '⚡ Precificação Dinâmica', 'woo-envios' ); ?></h2>
					<p><?php esc_html_e( 'Ajuste automaticamente os preços baseado em horários de pico, fim de semana e condições climáticas - similar ao Uber/99.', 'woo-envios' ); ?></p>

					<label class="woo-envios-field">
						<input type="checkbox" name="woo_envios_dynamic_pricing_enabled" value="1" <?php checked( get_option( 'woo_envios_dynamic_pricing_enabled', false ) ); ?> />
						<span><?php esc_html_e( 'Habilitar precificação dinâmica', 'woo-envios' ); ?></span>
					</label>

					<div id="dynamic-pricing-settings" style="<?php echo get_option( 'woo_envios_dynamic_pricing_enabled' ) ? '' : 'display:none;'; ?>">
						<h3><?php esc_html_e( '🕐 Horários de Pico', 'woo-envios' ); ?></h3>
						<p class="description"><?php esc_html_e( 'Configure os períodos de maior demanda e seus multiplicadores.', 'woo-envios' ); ?></p>

						<table class="widefat">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Período', 'woo-envios' ); ?></th>
									<th><?php esc_html_e( 'Início', 'woo-envios' ); ?></th>
									<th><?php esc_html_e( 'Fim', 'woo-envios' ); ?></th>
					<th><?php esc_html_e( 'Multiplicador', 'woo-envios' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php
								$peak_hours = get_option( 'woo_envios_peak_hours', array(
									array( 'name' => 'Manhã', 'start' => '07:00', 'end' => '09:00', 'multiplier' => 1.15 ),
									array( 'name' => 'Almoço', 'start' => '11:30', 'end' => '13:30', 'multiplier' => 1.20 ),
									array( 'name' => 'Noite', 'start' => '18:00', 'end' => '20:00', 'multiplier' => 1.25 ),
								) );
								foreach ( $peak_hours as $index => $period ) :
								?>
									<tr>
										<td><input type="text" name="woo_envios_peak_hours[<?php echo esc_attr( $index ); ?>][name]" value="<?php echo esc_attr( $period['name'] ); ?>" /></td>
										<td><input type="time" name="woo_envios_peak_hours[<?php echo esc_attr( $index ); ?>][start]" value="<?php echo esc_attr( $period['start'] ); ?>" /></td>
										<td><input type="time" name="woo_envios_peak_hours[<?php echo esc_attr( $index ); ?>][end]" value="<?php echo esc_attr( $period['end'] ); ?>" /></td>
										<td><input type="number" step="0.1" min="1" max="3" name="woo_envios_peak_hours[<?php echo esc_attr( $index ); ?>][multiplier]" value="<?php echo esc_attr( $period['multiplier'] ); ?>" /></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>

						<div class="woo-envios-grid" style="margin-top: 20px;">
							<label class="woo-envios-field">
								<span><?php esc_html_e( '📅 Multiplicador Fim de Semana', 'woo-envios' ); ?></span>
								<input type="number" step="0.1" min="1" max="2" name="woo_envios_weekend_multiplier" value="<?php echo esc_attr( get_option( 'woo_envios_weekend_multiplier', 1.10 ) ); ?>" />
								<p class="description"><?php esc_html_e( 'Aplicado aos sábados e domingos', 'woo-envios' ); ?></p>
							</label>

							<label class="woo-envios-field">
								<span><?php esc_html_e( '🔥 Multiplicador Máximo', 'woo-envios' ); ?></span>
								<input type="number" step="0.1" min="1" max="3" name="woo_envios_max_multiplier" value="<?php echo esc_attr( get_option( 'woo_envios_max_multiplier', 2.0 ) ); ?>" />
								<p class="description"><?php esc_html_e( 'Limite de segurança para não cobrar demais', 'woo-envios' ); ?></p>
							</label>
						</div>

						<h3><?php esc_html_e( '🌧️ Condições Climáticas (Opcional)', 'woo-envios' ); ?></h3>
						<label class="woo-envios-field">
							<span><?php esc_html_e( 'OpenWeather API Key', 'woo-envios' ); ?></span>
							<input type="text" name="woo_envios_weather_api_key" value="<?php echo esc_attr( get_option( 'woo_envios_weather_api_key', '' ) ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Opcional - deixe vazio para desabilitar', 'woo-envios' ); ?>" />
							<p class="description">
								<?php
								printf(
									esc_html__( 'Obtenha grátis em %s (1.000 chamadas/dia)', 'woo-envios' ),
									'<a href="https://openweathermap.org/api" target="_blank">OpenWeather</a>'
								);
								?>
							</p>
						</label>

						<div class="woo-envios-grid">
							<label class="woo-envios-field">
								<span><?php esc_html_e( 'Chuva Leve', 'woo-envios' ); ?></span>
								<input type="number" step="0.1" min="1" max="2" name="woo_envios_rain_light_multiplier" value="<?php echo esc_attr( get_option( 'woo_envios_rain_light_multiplier', 1.15 ) ); ?>" />
							</label>

							<label class="woo-envios-field">
								<span><?php esc_html_e( 'Chuva Forte', 'woo-envios' ); ?></span>
								<input type="number" step="0.1" min="1" max="2.5" name="woo_envios_rain_heavy_multiplier" value="<?php echo esc_attr( get_option( 'woo_envios_rain_heavy_multiplier', 1.30 ) ); ?>" />
							</label>
						</div>
					</div>
				</section>

				<section class="woo-envios-card">
					<h2><?php esc_html_e( '🔧 Configurações Avançadas', 'woo-envios' ); ?></h2>
					
					<label class="woo-envios-field">
						<input type="checkbox" name="woo_envios_enable_logs" value="1" <?php checked( get_option( 'woo_envios_enable_logs', false ) ); ?> />
						<span><?php esc_html_e( 'Habilitar logs de debug', 'woo-envios' ); ?></span>
						<p class="description"><?php esc_html_e( 'Salva logs detalhados de cálculos de frete e erros em wp-content/uploads/woo-envios-logs/', 'woo-envios' ); ?></p>
					</label>
				</section>

				<section class="woo-envios-card" style="border-left: 4px solid #007cba;">
					<h2><?php esc_html_e( '🛠️ Ferramentas de Debug', 'woo-envios' ); ?></h2>
					<p><?php esc_html_e( 'Use estas ferramentas para diagnosticar problemas de cálculo de distância.', 'woo-envios' ); ?></p>

					<div class="woo-envios-debug-tool">
						<h3><?php esc_html_e( 'Testar Geocodificação e Distância', 'woo-envios' ); ?></h3>
						<div class="woo-envios-search-controls">
							<input type="text" id="woo-envios-debug-address" class="regular-text" placeholder="<?php esc_attr_e( 'Digite um endereço completo para testar...', 'woo-envios' ); ?>" />
							<button type="button" class="button button-primary" id="woo-envios-debug-btn">
								<?php esc_html_e( 'Testar Cálculo', 'woo-envios' ); ?>
							</button>
						</div>
						<div id="woo-envios-debug-results" style="margin-top: 15px; display: none; padding: 15px; background: #f0f0f1; border-radius: 4px;"></div>
					</div>

					<hr style="margin: 20px 0;">

					<div class="woo-envios-debug-tool">
						<h3><?php esc_html_e( 'Limpar Cache de Endereços', 'woo-envios' ); ?></h3>
						<p><?php esc_html_e( 'Se você mudou o endereço da loja recentemente ou está tendo problemas com endereços antigos, limpe o cache.', 'woo-envios' ); ?></p>
						<button type="button" class="button" id="woo-envios-clear-cache-btn">
							<?php esc_html_e( 'Limpar Cache de Geocodificação', 'woo-envios' ); ?>
						</button>
						<span id="woo-envios-cache-msg" style="margin-left: 10px;"></span>
					</div>
				</section>

				<section class="woo-envios-card">
					<h2><?php esc_html_e( 'Faixas de Raio e Preço', 'woo-envios' ); ?></h2>
					<p><?php esc_html_e( 'Configure faixas de distância (em km) e seus valores. O WooCommerce aplicará automaticamente a faixa mais curta que contemple o cliente.', 'woo-envios' ); ?></p>

					<table class="widefat woo-envios-tiers">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Nome', 'woo-envios' ); ?></th>
								<th><?php esc_html_e( 'Distância máxima (km)', 'woo-envios' ); ?></th>
								<th><?php esc_html_e( 'Preço (R$)', 'woo-envios' ); ?></th>
								<th></th>
							</tr>
						</thead>
						<tbody id="woo-envios-tier-rows">
							<?php foreach ( $tiers as $index => $tier ) : ?>
								<tr>
									<td>
										<input type="text" name="<?php echo esc_attr( self::OPTION_TIERS ); ?>[<?php echo esc_attr( (string) $index ); ?>][label]" value="<?php echo esc_attr( $tier['label'] ); ?>" />
									</td>
									<td>
										<input type="number" min="0" step="0.1" name="<?php echo esc_attr( self::OPTION_TIERS ); ?>[<?php echo esc_attr( (string) $index ); ?>][distance]" value="<?php echo esc_attr( (string) $tier['distance'] ); ?>" />
									</td>
									<td>
										<input type="number" min="0" step="0.01" name="<?php echo esc_attr( self::OPTION_TIERS ); ?>[<?php echo esc_attr( (string) $index ); ?>][price]" value="<?php echo esc_attr( (string) $tier['price'] ); ?>" />
									</td>
									<td>
										<button type="button" class="button woo-envios-remove-tier">&times;</button>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>

					<button type="button" class="button button-secondary" id="woo-envios-add-tier">
						<?php esc_html_e( 'Adicionar faixa', 'woo-envios' ); ?>
					</button>
				</section>

				<?php submit_button(); ?>
			</form>

			<template id="woo-envios-tier-template">
				<tr>
					<td><input type="text" /></td>
					<td><input type="number" min="0" step="0.1" /></td>
					<td><input type="number" min="0" step="0.01" /></td>
					<td><button type="button" class="button woo-envios-remove-tier">&times;</button></td>
				</tr>
			</template>
		</div>
		<?php
	}

	/**
	 * Retorna coordenadas configuradas.
	 *
	 * @return array{lat:float,lng:float}
	 */
	public static function get_store_coordinates(): array {
		$lat = (float) get_option( self::OPTION_STORE_LAT, (string) WOO_ENVIOS_DEFAULT_LAT );
		$lng = (float) get_option( self::OPTION_STORE_LNG, (string) WOO_ENVIOS_DEFAULT_LNG );

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
	public static function get_tiers(): array {
		$tiers = get_option( self::OPTION_TIERS, self::get_default_tiers() );

		if ( empty( $tiers ) || ! is_array( $tiers ) ) {
			return self::get_default_tiers();
		}

		return array_values( $tiers );
	}

	/**
	 * Recupera a faixa correspondente a uma distância.
	 *
	 * @param float $distance Distância calculada.
	 *
	 * @return array|null
	 */
	public static function match_tier_by_distance( float $distance ): ?array {
		foreach ( self::get_tiers() as $tier ) {
			if ( $distance <= (float) $tier['distance'] ) {
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
	private static function get_default_tiers(): array {
		return array(
			array( 'label' => 'Raio 1.0 km', 'distance' => 1, 'price' => 7.50 ),
			array( 'label' => 'Raio 2.0 km', 'distance' => 2, 'price' => 8.00 ),
			array( 'label' => 'Raio 3.0 km', 'distance' => 3, 'price' => 8.50 ),
			array( 'label' => 'Raio 4.0 km', 'distance' => 4, 'price' => 9.00 ),
			array( 'label' => 'Raio 5.0 km', 'distance' => 5, 'price' => 9.50 ),
			array( 'label' => 'Raio 6.0 km', 'distance' => 6, 'price' => 10.00 ),
			array( 'label' => 'Raio 7.0 km', 'distance' => 7, 'price' => 10.90 ),
			array( 'label' => 'Raio 8.0 km', 'distance' => 8, 'price' => 11.80 ),
			array( 'label' => 'Raio 9.0 km', 'distance' => 9, 'price' => 12.70 ),
			array( 'label' => 'Raio 10.0 km', 'distance' => 10, 'price' => 13.60 ),
			array( 'label' => 'Raio 11.0 km', 'distance' => 11, 'price' => 14.50 ),
			array( 'label' => 'Raio 12.0 km', 'distance' => 12, 'price' => 15.00 ),
			array( 'label' => 'Raio 13.0 km', 'distance' => 13, 'price' => 15.50 ),
			array( 'label' => 'Raio 14.0 km', 'distance' => 14, 'price' => 16.00 ),
			array( 'label' => 'Raio 15.0 km', 'distance' => 15, 'price' => 16.50 ),
			array( 'label' => 'Raio 16.0 km', 'distance' => 16, 'price' => 17.00 ),
			array( 'label' => 'Raio 17.0 km', 'distance' => 17, 'price' => 17.50 ),
			array( 'label' => 'Raio 18.0 km', 'distance' => 18, 'price' => 18.00 ),
			array( 'label' => 'Raio 19.0 km', 'distance' => 19, 'price' => 18.50 ),
			array( 'label' => 'Raio 20.0 km', 'distance' => 20, 'price' => 19.00 ),
			array( 'label' => 'Raio 21.0 km', 'distance' => 21, 'price' => 19.50 ),
			array( 'label' => 'Raio 22.0 km', 'distance' => 22, 'price' => 20.00 ),
			array( 'label' => 'Raio 23.0 km', 'distance' => 23, 'price' => 20.50 ),
			array( 'label' => 'Raio 24.0 km', 'distance' => 24, 'price' => 21.00 ),
			array( 'label' => 'Raio 25.0 km', 'distance' => 25, 'price' => 21.50 ),
			array( 'label' => 'Raio 26.0 km', 'distance' => 26, 'price' => 22.00 ),
			array( 'label' => 'Raio 27.0 km', 'distance' => 27, 'price' => 22.50 ),
			array( 'label' => 'Raio 28.0 km', 'distance' => 28, 'price' => 23.00 ),
			array( 'label' => 'Raio 29.0 km', 'distance' => 29, 'price' => 23.50 ),
			array( 'label' => 'Raio 30.0 km', 'distance' => 30, 'price' => 24.00 ),
		);
	}
}
