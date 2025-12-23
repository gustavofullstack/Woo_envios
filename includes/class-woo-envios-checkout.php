<?php
/**
 * Integrações de checkout para captura de coordenadas via CEP.
 *
 * @package Woo_Envios
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Woo_Envios\Services\Geocoder;

class Woo_Envios_Checkout {

	/**
	 * Construtor.
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_ajax_woo_envios_geocode_address', array( $this, 'geocode_address' ) );
		add_action( 'wp_ajax_nopriv_woo_envios_geocode_address', array( $this, 'geocode_address' ) );
	}

	/**
	 * Enqueue checkout scripts.
	 */
	public function enqueue_scripts(): void {
		if ( ! is_checkout() ) {
			return;
		}

		wp_enqueue_script(
			'woo-envios-checkout',
			WOO_ENVIOS_ASSETS . 'js/woo-envios-checkout.js',
			array( 'jquery', 'wc-checkout' ),
			Woo_Envios_Plugin::VERSION,
			true
		);

		wp_localize_script(
			'woo-envios-checkout',
			'WooEnviosCheckout',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'woo-envios-geocode-nonce' ),
			)
		);
	}

	/**
	 * Handle AJAX request to geocode address.
	 */
	public function geocode_address(): void {
		// Log entry
		error_log( 'Woo Envios Checkout: geocode_address called' );
		
		check_ajax_referer( 'woo-envios-geocode-nonce', 'nonce' );

		$address_1    = isset( $_POST['address_1'] ) ? sanitize_text_field( wp_unslash( $_POST['address_1'] ) ) : '';
		$number       = isset( $_POST['number'] ) ? sanitize_text_field( wp_unslash( $_POST['number'] ) ) : '';
		$neighborhood = isset( $_POST['neighborhood'] ) ? sanitize_text_field( wp_unslash( $_POST['neighborhood'] ) ) : '';
		$city         = isset( $_POST['city'] ) ? sanitize_text_field( wp_unslash( $_POST['city'] ) ) : '';
		$state        = isset( $_POST['state'] ) ? sanitize_text_field( wp_unslash( $_POST['state'] ) ) : '';
		$postcode     = isset( $_POST['postcode'] ) ? sanitize_text_field( wp_unslash( $_POST['postcode'] ) ) : '';
		$country      = isset( $_POST['country'] ) ? sanitize_text_field( wp_unslash( $_POST['country'] ) ) : '';

		// Validate Brazilian CEP format.
		if ( 'BR' === $country && ! empty( $postcode ) ) {
			$clean_cep = preg_replace( '/[^0-9]/', '', $postcode );
			if ( strlen( $clean_cep ) !== 8 ) {
				wp_send_json_error( array(
					'message' => sprintf(
						'CEP %s inválido. Digite 8 dígitos no formato 00000-000',
						$postcode
					)
				) );
			}
		}

		// Build full address string for better accuracy.
		// Format: Street, Number, Neighborhood, City, State, Postcode, Country
		$full_address_parts = array_filter( array( $address_1, $number, $neighborhood, $city, $state, $postcode, $country ) );
		$full_address       = implode( ', ', $full_address_parts );

		if ( empty( $full_address ) ) {
			wp_send_json_error( array(
				'message' => 'Por favor, preencha todos os campos do endereço para calcular o frete.'
			) );
		}

		// Use the new Geocoder service with components for fallback.
		$coords = Geocoder::geocode( $full_address, array(
			'address_1'    => $address_1,
			'number'       => $number,
			'neighborhood' => $neighborhood,
			'city'         => $city,
			'state'        => $state,
			'postcode'     => $postcode,
			'country'      => $country,
		) );

		if ( ! $coords ) {
			wp_send_json_error( array(
				'message' => sprintf(
					'Não encontramos o endereço: %s. Verifique se está correto.',
					$full_address
				)
			) );
		}

		// Store in session.
		if ( isset( WC()->session ) ) {
			// Relaxed signature: Postcode + City + State + Country (excludes address_1/number to avoid "Av" vs "Avenida" mismatches)
			$signature = md5( strtolower( implode( '|', array( $city, $state, $postcode, $country ) ) ) );
			WC()->session->set(
				'woo_envios_coords',
				array(
					'lat'       => $coords['lat'],
					'lng'       => $coords['lng'],
					'signature' => $signature,
				)
			);
			WC()->session->save_data(); // Force save to avoid race conditions.
			error_log( 'Woo Envios Checkout: Coordinates saved to session: ' . print_r( $coords, true ) . ' Sig: ' . $signature );
		} else {
			error_log( 'Woo Envios Checkout: WC Session not available' );
		}

		wp_send_json_success( $coords );
	}
}
