<?php
/**
 * Geocoder Service - Google Maps Edition.
 *
 * @package Woo_Envios
 */

namespace Woo_Envios\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Geocoder - Now powered by Google Maps API
 */
class Geocoder {

	/**
	 * Geocode an address string using Google Maps API.
	 *
	 * @param string $address Full address string.
	 * @param array  $components Individual address components (optional).
	 * @return array|null Array with 'lat' and 'lng' keys, or null on failure.
	 */
	public static function geocode( string $address, array $components = array() ): ?array {
		if ( empty( $address ) ) {
			return null;
		}

		// Get Google Maps instance.
		$google_maps = new \Woo_Envios_Google_Maps();

		if ( ! $google_maps->is_configured() ) {
			// Fallback to default coordinates if Google Maps not configured.
			// REMOVED: Fallback causing confusion.
			return null;
			return null;
		}

		// Try geocoding with Google Maps.
		$result = $google_maps->geocode_address( $address );

		if ( is_wp_error( $result ) ) {
			// Log error.
			if ( class_exists( 'WC_Logger' ) ) {
				wc_get_logger()->error(
					'Woo Envios Google Maps Error: ' . $result->get_error_message(),
					array( 'source' => 'woo-envios' )
				);
			}

			// Fallback to default coordinates.
			// REMOVED: Fallback causing confusion. Better to fail than to give wrong coordinates.
			return null;

			return null;
		}

		// Return in the format expected by the plugin.
		return array(
			'lat' => $result['latitude'],
			'lng' => $result['longitude'],
		);
	}

	/**
	 * Get full address details using Google Maps API.
	 *
	 * @param string $address Full address string.
	 * @return array|null Full geocoding result or null.
	 */
	public static function geocode_full( string $address ): ?array {
		if ( empty( $address ) ) {
			return null;
		}

		$google_maps = new \Woo_Envios_Google_Maps();

		if ( ! $google_maps->is_configured() ) {
			return null;
		}

		$result = $google_maps->geocode_address( $address );

		if ( is_wp_error( $result ) ) {
			return null;
		}

		return $result;
	}

	/**
	 * Calculate distance between two coordinates using Google Maps API.
	 *
	 * @param string $origin      Origin address or coordinates.
	 * @param string $destination Destination address or coordinates.
	 * @return array|null Distance data or null.
	 */
	public static function calculate_distance( string $origin, string $destination ): ?array {
		$google_maps = new \Woo_Envios_Google_Maps();

		if ( ! $google_maps->is_configured() ) {
			return null;
		}

		$result = $google_maps->calculate_distance( $origin, $destination );

		if ( is_wp_error( $result ) ) {
			return null;
		}

		return $result;
	}
}
