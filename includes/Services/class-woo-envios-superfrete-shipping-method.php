<?php
/**
 * SuperFrete Shipping Method for WooCommerce.
 *
 * This shipping method handles PAC, SEDEX, and Mini Envios via SuperFrete API
 * for customers outside the local delivery radius.
 *
 * @package Woo_Envios
 */

namespace Woo_Envios\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Woo_Envios_Superfrete_Shipping_Method
 */
class Woo_Envios_Superfrete_Shipping_Method extends \WC_Shipping_Method {

	/**
	 * SuperFrete API handler.
	 *
	 * @var Woo_Envios_Correios
	 */
	private $api;

	/**
	 * Constructor.
	 *
	 * @param int $instance_id Instance ID.
	 */
	public function __construct( $instance_id = 0 ) {
		$this->id                 = 'woo_envios_superfrete';
		$this->instance_id        = absint( $instance_id );
		$this->method_title       = __( 'SuperFrete (PAC/SEDEX/Mini)', 'woo-envios' );
		$this->method_description = __( 'Cotações em tempo real via SuperFrete para clientes fora do raio de entrega local.', 'woo-envios' );
		$this->supports           = array(
			'shipping-zones',
			'instance-settings',
			'instance-settings-modal',
		);

		$this->init();

		$this->api = new Woo_Envios_Correios();
	}

	/**
	 * Initialize settings.
	 */
	public function init() {
		$this->init_form_fields();
		$this->init_settings();

		$this->title   = $this->get_option( 'title', __( 'Correios', 'woo-envios' ) );
		$this->enabled = $this->get_option( 'enabled', 'yes' );

		add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	/**
	 * Form fields for instance settings.
	 */
	public function init_form_fields() {
		$this->instance_form_fields = array(
			'title' => array(
				'title'       => __( 'Título', 'woo-envios' ),
				'type'        => 'text',
				'description' => __( 'Título exibido no checkout (os nomes dos serviços aparecerão automaticamente).', 'woo-envios' ),
				'default'     => __( 'Correios', 'woo-envios' ),
				'desc_tip'    => true,
			),
		);
	}

	/**
	 * Calculate shipping rates.
	 *
	 * @param array $package Package data.
	 */
	public function calculate_shipping( $package = array() ) {
		if ( ! $this->api->is_enabled() ) {
			return;
		}

		$rates = $this->api->calculate( $package );

		if ( empty( $rates ) || is_wp_error( $rates ) ) {
			return;
		}

		foreach ( $rates as $rate ) {
			$this->add_rate( array(
				'id'        => $this->get_rate_id( $rate['id'] ),
				'label'     => $rate['label'],
				'cost'      => $rate['cost'],
				'meta_data' => array(
					'deadline' => $rate['deadline'] ?? 0,
				),
			) );
		}
	}

	/**
	 * Check if available.
	 *
	 * @param array $package Package data.
	 * @return bool
	 */
	public function is_available( $package ) {
		// Only available if SuperFrete is configured
		if ( ! $this->api->is_enabled() ) {
			return false;
		}

		// Check if we have a valid postcode
		if ( empty( $package['destination']['postcode'] ) ) {
			return false;
		}

		return true;
	}
}
