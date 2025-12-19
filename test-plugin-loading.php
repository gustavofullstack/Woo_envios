<?php
/**
 * Test script to identify plugin loading issues
 */

// Simulate WordPress environment
define( 'ABSPATH', __DIR__ . '/' );
define( 'WPINC', 'wp-includes' );

// Mock WordPress functions
if ( ! function_exists( 'plugin_dir_path' ) ) {
	function plugin_dir_path( $file ) {
		return dirname( $file ) . '/';
	}
}

if ( ! function_exists( 'plugin_dir_url' ) ) {
	function plugin_dir_url( $file ) {
		return 'http://localhost/wp-content/plugins/' . basename( dirname( $file ) ) . '/';
	}
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
		// Mock
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
		// Mock
	}
}

if ( ! function_exists( 'register_activation_hook' ) ) {
	function register_activation_hook( $file, $callback ) {
		// Mock
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $option, $default = false ) {
		return $default;
	}
}

if ( ! function_exists( 'wp_create_nonce' ) ) {
	function wp_create_nonce( $action ) {
		return 'test_nonce';
	}
}

if ( ! function_exists( 'current_time' ) ) {
	function current_time( $format ) {
		return date( $format );
	}
}

if ( ! function_exists( 'is_admin' ) ) {
	function is_admin() {
		return true;
	}
}

if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( $path ) {
		return 'http://localhost/wp-admin/' . $path;
	}
}

// Mock WC_Shipping_Method class
class WC_Shipping_Method {
	public $id;
	public $method_title;
	public $method_description;
	public $instance_id;
	public $supports = array();
	public $enabled;
	public $title;
	
	public function get_option( $key, $default = '' ) {
		return $default;
	}
	
	public function init_instance_settings() {}
	public function add_rate( $rate ) {}
}

// Mock WP_Error class
class WP_Error {
	private $message;
	public function __construct( $code, $message ) {
		$this->message = $message;
	}
	public function get_error_message() {
		return $this->message;
	}
}

// Mock is_wp_error function
if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}
}

// Mock WooCommerce class
class WooCommerce {
	public $session;
	public function __construct() {
		$this->session = new stdClass();
	}
}

function WC() {
	static $instance = null;
	if ( null === $instance ) {
		$instance = new WooCommerce();
	}
	return $instance;
}

// Check if WooCommerce class exists (mock it)
class_alias( 'WooCommerce', 'WooCommerce' );

echo "✓ WordPress environment mocked\n";

// Now try to load the plugin
try {
	require_once __DIR__ . '/woo-envios.php';
	echo "✓ Plugin file loaded successfully\n";
	echo "✓ NO FATAL ERRORS DETECTED!\n";
	echo "\nThis means the issue might be:\n";
	echo "1. WordPress-specific environment issue\n";
	echo "2. Plugin conflict with another plugin\n";
	echo "3. PHP version mismatch\n";
	echo "4. Missing WordPress/WooCommerce functions not mocked here\n";
} catch ( Throwable $e ) {
	echo "✗ FATAL ERROR DETECTED:\n";
	echo "Error: " . $e->getMessage() . "\n";
	echo "File: " . $e->getFile() . "\n";
	echo "Line: " . $e->getLine() . "\n";
	echo "\nStack Trace:\n";
	echo $e->getTraceAsString() . "\n";
}
