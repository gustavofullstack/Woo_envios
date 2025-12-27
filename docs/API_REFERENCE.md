# TriqHub Shipping & Radius - API Reference

## Overview

This document provides comprehensive technical documentation for the TriqHub Shipping & Radius WordPress plugin. It covers all public APIs, WordPress hooks, filters, and internal interfaces available for developers.

## WordPress Hooks & Filters

### Actions

#### `woocommerce_shipping_init`
**Priority:** Default (10)  
**Parameters:** None  
**Description:** Initializes the shipping method classes when WooCommerce is ready. Used by `TriqHub_Shipping_Plugin::load_shipping_class()` to load the main shipping class.

#### `plugins_loaded`
**Priority:** 20  
**Callback:** `woo_envios_bootstrap()`  
**Parameters:** None  
**Description:** Main plugin initialization hook. Verifies WooCommerce compatibility and loads the plugin instance.

#### `admin_notices`
**Priority:** Default (10)  
**Parameters:** None  
**Description:** Displays admin notices for missing dependencies:
- WooCommerce not active
- WooCommerce version too old
- Fatal errors during plugin initialization

#### `wp_enqueue_scripts`
**Priority:** Default (10)  
**Callback:** `TriqHub_Shipping_Plugin::enqueue_frontend_styles()`  
**Parameters:** None  
**Description:** Enqueues frontend CSS styles on checkout and cart pages.

#### `admin_enqueue_scripts`
**Priority:** Default (10)  
**Callback:** `triqhub_enqueue_admin_Woo_envios()`  
**Parameters:** None  
**Description:** Enqueues TriqHub admin styling.

#### `woocommerce_update_options_shipping_{$this->id}_{$this->instance_id}`
**Priority:** Default (10)  
**Callback:** `Woo_Envios_Shipping_Method::process_admin_options()`  
**Parameters:** None  
**Description:** Handles shipping method settings updates in WooCommerce admin.

#### `upgrader_process_complete`
**Priority:** Default (10)  
**Callback:** `Woo_Envios_Updater::track_update()`  
**Parameters:** 
- `$upgrader_object` (WP_Upgrader): The upgrader instance
- `$options` (array): Update options array  
**Description:** Optional tracking hook for plugin updates (currently placeholder implementation).

### Filters

#### `woocommerce_shipping_methods`
**Priority:** Default (10)  
**Callback:** `TriqHub_Shipping_Plugin::register_shipping_method()`  
**Parameters:** 
- `$methods` (array): Current shipping methods array  
**Returns:** array - Modified shipping methods array  
**Description:** Registers custom shipping methods with WooCommerce:
- `woo_envios_radius`: Local delivery by radius (Flash delivery)
- `woo_envios_superfrete`: SuperFrete integration (PAC/SEDEX/Mini)

#### `woocommerce_package_rates`
**Priority:** 10  
**Callback:** `TriqHub_Shipping_Plugin::sort_shipping_rates()`  
**Parameters:** 
- `$rates` (array): Shipping rates array
- `$package` (array): WooCommerce package data  
**Returns:** array - Sorted shipping rates array  
**Description:** Sorts shipping rates to prioritize Flash delivery (woo_envios_radius) over other methods.

#### `puc_request_info_query_args-{slug}`
**Priority:** Default (10)  
**Callback:** Anonymous function in `TriqHub_Shipping_Plugin::init_updater()`  
**Parameters:** 
- `$queryArgs` (array): Query arguments for update checker  
**Returns:** array - Modified query arguments  
**Description:** Injects license key and site URL into GitHub update requests for license validation.

#### `pre_set_site_transient_update_plugins`
**Priority:** Default (10)  
**Callback:** `Woo_Envios_Updater::check_update()`  
**Parameters:** 
- `$transient` (object): Update transient object  
**Returns:** object - Modified transient object  
**Description:** Checks for plugin updates from remote JSON file and adds update information to the transient.

#### `plugins_api`
**Priority:** 10  
**Callback:** `Woo_Envios_Updater::check_info()`  
**Parameters:** 
- `$res` (mixed): Original response
- `$action` (string): API action
- `$args` (object): API arguments  
**Returns:** object|mixed - Plugin information object or original response  
**Description:** Provides plugin information for the "View Details" popup in WordPress admin.

## Public Classes & Methods

### TriqHub_Shipping_Plugin (Main Plugin Class)

#### `TriqHub_Shipping_Plugin::instance(): TriqHub_Shipping_Plugin`
**Access:** public static  
**Returns:** `TriqHub_Shipping_Plugin` - Singleton instance  
**Description:** Returns the singleton instance of the main plugin class.

#### `TriqHub_Shipping_Plugin::register_shipping_method(array $methods): array`
**Access:** public  
**Parameters:** 
- `$methods` (array): Current shipping methods  
**Returns:** array - Modified shipping methods  
**Description:** Registers custom shipping methods with WooCommerce.

#### `TriqHub_Shipping_Plugin::load_shipping_class(): void`
**Access:** public  
**Parameters:** None  
**Returns:** void  
**Description:** Loads the main shipping class file when WooCommerce is ready.

#### `TriqHub_Shipping_Plugin::sort_shipping_rates(array $rates, array $package): array`
**Access:** public  
**Parameters:** 
- `$rates` (array): Shipping rates
- `$package` (array): Package data  
**Returns:** array - Sorted rates  
**Description:** Sorts shipping rates to display Flash delivery first.

#### `TriqHub_Shipping_Plugin::enqueue_frontend_styles(): void`
**Access:** public  
**Parameters:** None  
**Returns:** void  
**Description:** Enqueues frontend CSS styles.

#### `TriqHub_Shipping_Plugin::activate(): void`
**Access:** public  
**Parameters:** None  
**Returns:** void  
**Description:** Plugin activation callback. Creates the geocode cache table.

### Woo_Envios_Shipping_Method (Shipping Method Class)

#### `Woo_Envios_Shipping_Method::__construct($instance_id = 0)`
**Access:** public  
**Parameters:** 
- `$instance_id` (int): Shipping method instance ID  
**Description:** Constructor for the radius-based shipping method.

#### `Woo_Envios_Shipping_Method::init(): void`
**Access:** public  
**Parameters:** None  
**Returns:** void  
**Description:** Initializes form fields and hooks.

#### `Woo_Envios_Shipping_Method::calculate_shipping($package = array()): void`
**Access:** public  
**Parameters:** 
- `$package` (array): WooCommerce package data  
**Returns:** void  
**Description:** Main shipping calculation logic. Calculates distance-based pricing with dynamic multipliers.

#### `Woo_Envios_Shipping_Method::init_form_fields(): void`
**Access:** public  
**Parameters:** None  
**Returns:** void  
**Description:** Initializes settings form fields (empty implementation for modal support).

### Woo_Envios_Google_Maps (Google Maps API)

#### `Woo_Envios_Google_Maps::is_configured(): bool`
**Access:** public  
**Parameters:** None  
**Returns:** bool - True if API is properly configured  
**Description:** Checks if Google Maps API is properly configured with valid API key.

#### `Woo_Envios_Google_Maps::calculate_distance(string $origin, string $destination): array|WP_Error`
**Access:** public  
**Parameters:** 
- `$origin` (string): Origin coordinates "lat,lng"
- `$destination` (string): Destination coordinates "lat,lng"  
**Returns:** array|WP_Error - Distance data or error  
**Description:** Calculates route distance using Google Distance Matrix API.

### Woo_Envios_Weather (Weather Service)

#### `Woo_Envios_Weather::get_weather_multiplier(float $lat, float $lng): float`
**Access:** public  
**Parameters:** 
- `$lat` (float): Latitude
- `$lng` (float): Longitude  
**Returns:** float - Weather multiplier (1.0-1.5)  
**Description:** Gets weather-based price multiplier using OpenWeather API.

#### `Woo_Envios_Weather::get_weather_description(array $weather_data): string`
**Access:** public  
**Parameters:** 
- `$weather_data` (array): Weather data from OpenWeather API  
**Returns:** string - Human-readable weather description  
**Description:** Extracts weather description from API response.

#### `Woo_Envios_Weather::clear_cache(): void`
**Access:** public  
**Parameters:** None  
**Returns:** void  
**Description:** Clears weather API cache.

### Woo_Envios_Logger (Logging System)

#### `Woo_Envios_Logger::shipping_calculated(float $distance, float $base_price, float $final_price, array $multipliers, string $address = '', array $store_coords = array(), array $customer_coords = array()): void`
**Access:** public static  
**Parameters:** 
- `$distance` (float): Distance in km
- `$base_price` (float): Base price
- `$final_price` (float): Final price
- `$multipliers` (array): Applied multipliers
- `$address` (string): Customer address
- `$store_coords` (array): Store coordinates
- `$customer_coords` (array): Customer coordinates  
**Returns:** void  
**Description:** Logs shipping calculation details.

#### `Woo_Envios_Logger::error(string $message): void`
**Access:** public static  
**Parameters:** 
- `$message` (string): Error message  
**Returns:** void  
**Description:** Logs error message.

#### `Woo_Envios_Logger::info(string $message): void`
**Access:** public static  
**Parameters:** 
- `$message` (string): Info message  
**Returns:** void  
**Description:** Logs info message.

#### `Woo_Envios_Logger::warning(string $message): void`
**Access:** public static  
**Parameters:** 
- `$message` (string): Warning message  
**Returns:** void  
**Description:** Logs warning message.

#### `Woo_Envios_Logger::api_failure(string $api_name, string $error): void`
**Access:** public static  
**Parameters:** 
- `$api_name` (string): API name
- `$error` (string): Error message  
**Returns:** void  
**Description:** Logs API failure.

#### `Woo_Envios_Logger::circuit_breaker_opened(int $failures): void`
**Access:** public static  
**Parameters:** 
- `$failures` (int): Number of failures  
**Returns:** void  
**Description:** Logs circuit breaker activation and notifies admin.

#### `Woo_Envios_Logger::distance_out_of_range(float $distance, array $destination_data): void`
**Access:** public static  
**Parameters:** 
- `$distance` (float): Calculated distance
- `$destination_data` (array): Destination address data  
**Returns:** void  
**Description:** Logs when customer distance is outside delivery range.

#### `Woo_Envios_Logger::cleanup_old_logs(): void`
**Access:** public static  
**Parameters:** None  
**Returns:** void  
**Description:** Cleans up log files older than 7 days.

### Woo_Envios_Admin (Admin Interface)

#### `Woo_Envios_Admin::get_store_coordinates(): array`
**Access:** public static  
**Parameters:** None  
**Returns:** array - Store coordinates with 'lat' and 'lng' keys  
**Description:** Retrieves store coordinates from plugin settings.

#### `Woo_Envios_Admin::match_tier_by_distance(float $distance): array|null`
**Access:** public static  
**Parameters:** 
- `$distance` (float): Distance in km  
**Returns:** array|null - Tier configuration or null if no match  
**Description:** Matches distance to configured pricing tier.

### Woo_Envios_Updater (Update System)

#### `Woo_Envios_Updater::__construct(string $plugin_file, string $username, string $repo)`
**Access:** public  
**Parameters:** 
- `$plugin_file` (string): Main plugin file path
- `$username` (string): GitHub username
- `$repo` (string): GitHub repository name  
**Description:** Initializes the update checker.

## Internal API Routes & Services

### Geocoder Service (`\Woo_Envios\Services\Geocoder`)

#### `Geocoder::geocode(string $address): array|null`
**Access:** public static  
**Parameters:** 
- `$address` (string): Full address string  
**Returns:** array|null - Coordinates array with 'lat' and 'lng' or null  
**Description:** Geocodes address to coordinates using Google Maps API with caching.

### Correios Service (`\Woo_Envios\Services\Woo_Envios_Correios`)

#### `Woo_Envios_Correios::is_enabled(): bool`
**Access:** public  
**Parameters:** None  
**Returns:** bool - True if Correios/SuperFrete is enabled  
**Description:** Checks if Correios integration is enabled.

#### `Woo_Envios_Correios::calculate(array $package): array|null`
**Access:** public  
**Parameters:** 
- `$package` (array): WooCommerce package data  
**Returns:** array|null - Array of shipping rates or null  
**Description:** Calculates Correios/SuperFrete shipping rates.

### SuperFrete Shipping Method (`\Woo_Envios\Services\Woo_Envios_Superfrete_Shipping_Method`)

#### `Woo_Envios_Superfrete_Shipping_Method::calculate_shipping($package = array()): void`
**Access:** public  
**Parameters:** 
- `$package` (array): WooCommerce package data  
**Returns:** void  
**Description:** Calculates SuperFrete shipping rates for destinations outside local radius.

## Session Data Structure

### Coordinate Storage
```php
WC()->session->set('woo_envios_coords', array(
    'lat'       => float,      // Latitude
    'lng'       => float,      // Longitude
    'signature' => string,     // MD5 hash of normalized address
));
```

### Session Signature Generation
The signature is generated from normalized address components:
```php
$parts = array(
    sanitize_text_field($destination['city'] ?? ''),
    sanitize_text_field($destination['state'] ?? ''),
    preg_replace('/\D/', '', $destination['postcode'] ?? ''),
    sanitize_text_field($destination['country'] ?? ''),
);
$signature = md5(strtolower(implode('|', $parts)));
```

## Database Schema

### Geocode Cache Table
```sql
CREATE TABLE wp_woo_envios_geocode_cache (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    cache_key varchar(64) NOT NULL,
    result_data longtext NOT NULL,
    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at datetime NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY cache_key (cache_key),
    KEY expires_at (expires_at)
);
```

## Configuration Options

### Plugin Settings (via WooCommerce → Woo Envios)
- `woo_envios_google_maps_api_key`: Google Maps API key
- `woo_envios_weather_api_key`: OpenWeather API key
- `woo_envios_dynamic_pricing_enabled`: Enable/disable dynamic pricing
- `woo_envios_peak_hours`: Array of peak hour configurations
- `woo_envios_weekend_multiplier`: Weekend price multiplier
- `woo_envios_rain_light_multiplier`: Light rain multiplier
- `woo_envios_rain_heavy_multiplier`: Heavy rain multiplier
- `woo_envios_max_multiplier`: Maximum price multiplier
- `woo_envios_enable_logs`: Enable/disable logging
- `triqhub_license_key`: TriqHub license key

### Shipping Method Settings
- `enabled`: Enable/disable shipping method
- `title`: Display title for customers
- Distance tiers configuration (via admin interface)

## Error Handling

### WP_Error Codes
- `not_configured`: Google Maps API not configured
- `circuit_open`: API circuit breaker is open
- `api_failure`: General API failure

### Circuit Breaker Pattern
The plugin implements a circuit breaker pattern for Google Maps API calls:
1. Tracks consecutive failures in transient `woo_envios_api_failures`
2. Opens circuit after 5 consecutive failures
3. Circuit remains open for 1 hour
4. Admin is notified via email when circuit opens
5. System falls back to default coordinates when circuit is open

## Constants

### Plugin Constants
```php
define('WOO_ENVIOS_FILE', __FILE__);
define('WOO_ENVIOS_PATH', plugin_dir_path(__FILE__));
define('WOO_ENVIOS_URL', plugin_dir_url(__FILE__));
define('WOO_ENVIOS_ASSETS', WOO_ENVIOS_URL . 'assets/');
define('WOO_ENVIOS_DEFAULT_LAT', -18.911);
define('WOO_ENVIOS_DEFAULT_LNG', -48.262);
```

### Class Constants
- `Woo_Envios_Weather::API_URL`: OpenWeather API endpoint
- `Woo_Envios_Weather::CACHE_DURATION`: 3600 seconds (1 hour)
- `Woo_Envios_Google_Maps::API_KEY_OPTION`: 'woo_envios_google_maps_api_key'
- `Woo_Envios_Google_Maps::MAX_CONSECUTIVE_FAILURES`: 5
- `Woo_Envios_Google_Maps::MAX_RETRIES`: 3
- `Woo_Envios_Google_Maps::REQUEST_TIMEOUT`: 10

## Integration Points

### WooCommerce Integration
- Shipping method registration via `woocommerce_shipping_methods` filter
- Shipping calculation via `WC_Shipping_Method` extension
- Session-based coordinate storage
- Checkout field integration for address