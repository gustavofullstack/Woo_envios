# API Reference: TriqHub Shipping & Radius Plugin

## Overview
This document provides comprehensive technical documentation for the TriqHub Shipping & Radius WordPress plugin (version 1.2.14). It covers all public APIs, WordPress hooks, internal classes, and integration points.

## Table of Contents
1. [WordPress Hooks](#wordpress-hooks)
2. [Public Classes & Methods](#public-classes--methods)
3. [Shipping Methods](#shipping-methods)
4. [Service Classes](#service-classes)
5. [Database Schema](#database-schema)
6. [External API Integrations](#external-api-integrations)
7. [JavaScript API](#javascript-api)

## WordPress Hooks

### Actions

#### `plugins_loaded` (Priority: 20)
**Description:** Initializes the plugin after WooCommerce is loaded
**Callback:** `woo_envios_bootstrap()`
**Parameters:** None
**Returns:** `void`
**Usage:**
```php
// Plugin automatically hooks itself
add_action('plugins_loaded', 'woo_envios_bootstrap', 20);
```

#### `admin_notices`
**Description:** Displays admin warnings when WooCommerce is not active or version is incompatible
**Callback:** Anonymous function in `woo_envios_bootstrap()`
**Parameters:** None
**Returns:** `void`
**Conditions:**
- Triggered when WooCommerce is not installed/activated
- Triggered when WooCommerce version < 5.0
- Triggered when fatal error occurs during initialization

#### `wp_enqueue_scripts`
**Description:** Enqueues frontend styles for checkout and cart pages
**Callback:** `TriqHub_Shipping_Plugin::enqueue_frontend_styles()`
**Parameters:** None
**Returns:** `void`
**Assets Loaded:**
- `woo-envios-frontend.css` from `assets/css/woo-envios-frontend.css`

#### `admin_enqueue_scripts`
**Description:** Enqueues TriqHub admin styling
**Callback:** `triqhub_enqueue_admin_Woo_envios()`
**Parameters:** None
**Returns:** `void`
**Assets Loaded:**
- `triqhub-admin-style.css` from `assets/css/triqhub-admin.css`

#### `woocommerce_shipping_init`
**Description:** Loads shipping class when WooCommerce is ready
**Callback:** `TriqHub_Shipping_Plugin::load_shipping_class()`
**Parameters:** None
**Returns:** `void`
**Loads:** `includes/class-woo-envios-shipping.php`

#### `woocommerce_update_options_shipping_woo_envios_radius_{instance_id}`
**Description:** Processes admin options for shipping method instance
**Callback:** `Woo_Envios_Shipping_Method::process_admin_options()`
**Parameters:** None
**Returns:** `void`
**Trigger:** When shipping method settings are saved in WooCommerce admin

### Filters

#### `woocommerce_shipping_methods`
**Description:** Registers custom shipping methods with WooCommerce
**Callback:** `TriqHub_Shipping_Plugin::register_shipping_method()`
**Parameters:**
- `array $methods`: Current shipping methods array
**Returns:** `array` Updated shipping methods array
**Registered Methods:**
1. `woo_envios_radius` → `Woo_Envios_Shipping_Method`
2. `woo_envios_superfrete` → `Woo_Envios\Services\Woo_Envios_Superfrete_Shipping_Method`

#### `woocommerce_package_rates`
**Description:** Sorts shipping rates to display Flash Delivery on top
**Callback:** `TriqHub_Shipping_Plugin::sort_shipping_rates()`
**Parameters:**
- `array $rates`: Current shipping rates
- `array $package`: WooCommerce package data
**Returns:** `array` Sorted shipping rates with Flash Delivery first
**Sorting Logic:**
- Groups rates by `woo_envios_radius` method_id
- Places Flash Delivery rates before other shipping methods

#### `puc_request_info_query_args-{slug}`
**Description:** Injects license key into update requests for GitHub Updater
**Callback:** Anonymous function in `TriqHub_Shipping_Plugin::init_updater()`
**Parameters:**
- `array $queryArgs`: Current query arguments
**Returns:** `array` Updated query arguments with license key
**Added Parameters:**
- `license_key`: From `triqhub_license_key` option
- `site_url`: Current site URL from `home_url()`

#### `pre_set_site_transient_update_plugins`
**Description:** Checks for plugin updates via GitHub
**Callback:** `Woo_Envios_Updater::check_update()`
**Parameters:**
- `object $transient`: Update transient object
**Returns:** `object` Updated transient with plugin update info

#### `plugins_api`
**Description:** Provides plugin information for "View Details" popup
**Callback:** `Woo_Envios_Updater::check_info()`
**Parameters:**
- `mixed $res`: Original result
- `string $action`: API action ('plugin_information')
- `object $args`: Request arguments
**Returns:** `object|mixed` Plugin information object or original result

#### `upgrader_process_complete`
**Description:** Tracks plugin updates (placeholder for future implementation)
**Callback:** `Woo_Envios_Updater::track_update()`
**Parameters:**
- `WP_Upgrader $upgrader_object`: Upgrader instance
- `array $options`: Update options
**Returns:** `void`

## Public Classes & Methods

### TriqHub_Shipping_Plugin (Main Plugin Class)

#### Singleton Pattern
```php
public static function instance(): TriqHub_Shipping_Plugin
```
**Description:** Returns singleton instance of the plugin
**Returns:** `TriqHub_Shipping_Plugin` instance

#### Constants
- `VERSION = '1.2.14'`: Plugin version constant

#### Public Methods

##### `register_shipping_method(array $methods): array`
**Description:** Registers shipping methods with WooCommerce
**Parameters:**
- `array $methods`: Current shipping methods
**Returns:** `array` Updated methods array

##### `sort_shipping_rates(array $rates, array $package): array`
**Description:** Sorts shipping rates to prioritize Flash Delivery
**Parameters:**
- `array $rates`: Current shipping rates
- `array $package`: Package data
**Returns:** `array` Sorted rates

##### `enqueue_frontend_styles(): void`
**Description:** Enqueues frontend CSS for checkout/cart pages
**Returns:** `void`

##### `activate(): void`
**Description:** Plugin activation hook - creates cache table
**Returns:** `void`

##### `load_shipping_class(): void`
**Description:** Loads shipping class when WooCommerce is ready
**Returns:** `void`

#### Private Methods

##### `define_constants(): void`
**Description:** Defines plugin constants
**Constants Defined:**
- `WOO_ENVIOS_FILE`: Plugin main file path
- `WOO_ENVIOS_PATH`: Plugin directory path
- `WOO_ENVIOS_URL`: Plugin URL
- `WOO_ENVIOS_ASSETS`: Assets URL
- `WOO_ENVIOS_DEFAULT_LAT`: Default latitude (-18.911)
- `WOO_ENVIOS_DEFAULT_LNG`: Default longitude (-48.262)

##### `include_files(): void`
**Description:** Includes required plugin files in dependency order
**Files Included:**
1. `class-woo-envios-logger.php`
2. `class-woo-envios-google-maps.php`
3. `Services/Geocoder.php`
4. `class-woo-envios-correios.php`
5. `class-woo-envios-superfrete-shipping-method.php`
6. `class-woo-envios-google-maps-admin.php`
7. `class-woo-envios-weather.php`
8. `class-woo-envios-admin.php`
9. `class-woo-envios-checkout.php`

##### `load_components(): void`
**Description:** Initializes plugin components
**Components Initialized:**
- Google Maps service
- Admin panel
- Checkout handler
- GitHub updater
- Shipping method registration

##### `init_updater(): void`
**Description:** Initializes GitHub-based update checker
**Dependencies:** Plugin Update Checker library
**Update Source:** `https://github.com/gustavofullstack/triqhub-shipping-radius`

##### `create_google_cache_table(): void`
**Description:** Creates geocode cache table in database
**Table Schema:** `wp_woo_envios_geocode_cache`
**Returns:** `void`

##### `maybe_create_cache_table(): void`
**Description:** Self-healing function to create cache table if missing
**Returns:** `void`

### Woo_Envios_Shipping_Method

#### Constructor
```php
public function __construct($instance_id = 0)
```
**Parameters:**
- `int $instance_id`: Shipping method instance ID (default: 0)

#### Properties
- `$id = 'woo_envios_radius'`: Shipping method ID
- `$method_title = 'Woo Envios — Raio Escalonado'`: Method title
- `$method_description`: Description for admin
- `$supports = ['shipping-zones', 'instance-settings', 'instance-settings-modal']`: Supported features

#### Public Methods

##### `init(): void`
**Description:** Initializes shipping method fields and hooks
**Instance Fields:**
- `enabled`: Checkbox to enable/disable method
- `title`: Display title for customers

##### `calculate_shipping(array $package = []): void`
**Description:** Main shipping calculation logic
**Parameters:**
- `array $package`: WooCommerce package data
**Returns:** `void`
**Calculation Flow:**
1. Checks if method is enabled
2. Retrieves store coordinates
3. Gets customer coordinates from session
4. Falls back to server-side geocoding if needed
5. Calculates distance (Google Maps API with Haversine fallback)
6. Matches distance to pricing tier
7. Calculates Correios shipping as alternative
8. Applies dynamic pricing multipliers
9. Adds shipping rate to WooCommerce

##### `init_form_fields(): void`
**Description:** Initializes form fields (empty implementation for modal support)
**Returns:** `void`

#### Private Methods

##### `get_session_coordinates(string $signature): ?array`
**Description:** Retrieves coordinates from WooCommerce session
**Parameters:**
- `string $signature`: Address signature for validation
**Returns:** `array|null` Coordinates array with 'lat' and 'lng' or null

##### `calculate_route_distance(array $store_coords, array $customer_coords, array $package): array|WP_Error`
**Description:** Calculates route distance using Google Distance Matrix API
**Parameters:**
- `array $store_coords`: Store coordinates ['lat', 'lng']
- `array $customer_coords`: Customer coordinates ['lat', 'lng']
- `array $package`: Package data
**Returns:** `array|WP_Error` Distance data or error

##### `calculate_distance(float $lat_from, float $lng_from, float $lat_to, float $lng_to): float`
**Description:** Calculates Haversine distance (fallback method)
**Parameters:**
- `float $lat_from`: Origin latitude
- `float $lng_from`: Origin longitude
- `float $lat_to`: Destination latitude
- `float $lng_to`: Destination longitude
**Returns:** `float` Distance in kilometers

##### `build_destination_signature(array $package): string`
**Description:** Creates unique signature for destination address
**Parameters:**
- `array $package`: Package data
**Returns:** `string` MD5 hash of normalized address components
**Components Used:**
- City
- State
- Normalized postcode (digits only)
- Country

##### `calculate_correios_shipping(array $package): void`
**Description:** Calculates Correios shipping rates
**Parameters:**
- `array $package`: Package data
**Returns:** `void`
**Dependencies:** `Woo_Envios\Services\Woo_Envios_Correios`

##### `calculate_dynamic_multiplier(array $package): array`
**Description:** Calculates dynamic pricing multipliers
**Parameters:**
- `array $package`: Package data
**Returns:** `array` Multiplier data with 'total' and 'reasons'
**Multipliers Applied:**
1. Peak hour multiplier
2. Weekend multiplier
3. Weather (rain) multiplier
4. Maximum multiplier limit

##### `get_peak_hour_multiplier(): array`
**Description:** Checks if current time is within peak hours
**Returns:** `array` Multiplier data with 'multiplier' and 'label'
**Configuration:** `woo_envios_peak_hours` option

##### `get_weather_multiplier(array $package): float`
**Description:** Gets weather-based price multiplier
**Parameters:**
- `array $package`: Package data
**Returns:** `float` Weather multiplier (1.0-1.5)
**Dependencies:** `Woo_Envios_Weather` class

##### `is_weekend(): bool`
**Description:** Checks if current day is weekend
**Returns:** `bool` True if Saturday or Sunday

### Woo_Envios_Logger

#### Static Methods

##### `shipping_calculated(float $distance, float $base_price, float $final_price, array $multipliers, string $address = '', array $store_coords = [], array $customer_coords = []): void`
**Description:** Logs shipping calculation details
**Parameters:**
- `float $distance`: Distance in km
- `float $base_price`: Base shipping price
- `float $final_price`: Final price after multipliers
- `array $multipliers`: Applied multipliers
- `string $address`: Customer address (optional)
- `array $store_coords`: Store coordinates (optional)
- `array $customer_coords`: Customer coordinates (optional)
**Returns:** `void`

##### `error(string $message): void`
**Description:** Logs error message
**Parameters:**
- `string $message`: Error message
**Returns:** `void`

##### `info(string $message): void`
**Description:** Logs info message
**Parameters:**
- `string $message`: Info message
**Returns:** `void`

##### `warning(string $message): void`
**Description:** Logs warning message
**Parameters:**
- `string $message`: Warning message
**Returns:** `void`

##### `api_failure(string $api_name, string $error): void`
**Description:** Logs API failure
**Parameters:**
- `string $api_name`: API name (e.g., 'Google Maps')
- `string $error`: Error message
**Returns:** `void`

##### `circuit_breaker_opened(int $failures): void`
**Description:** Logs circuit breaker activation and notifies admin
**Parameters:**
- `int $failures`: Number of consecutive failures
**Returns:** `void`
**Side Effects:** Sends email notification to admin

##### `distance_out_of_range(float $distance, array $destination_data): void`
**Description:** Logs when customer is outside delivery range
**Parameters:**
- `float $distance`: Calculated distance
- `array $destination_data`: Destination address data
**Returns:** `void`

##### `cleanup_old_logs(): void`
**Description:** Cleans up log files older than 7 days
**Returns:** `void`

#### Private Methods

##### `is_enabled(): bool`
**Description:** Checks if logging is enabled
**Returns:** `bool` True if `woo_envios_enable_logs` option is true

##### `log(string $message, string $level = 'info'): void`
**Description:** Writes log entry to file
**Parameters:**
- `string $message`: Log message
- `string $level`: Log level (info/warning/error)
**Returns:** `void`
**Log Location:** `wp-content/uploads/woo-envios-logs/YYYY-MM-DD.log`

##### `notify_admin_api_failure(int $failures): void`
**Description:** Sends email notification to admin about API failures
**Parameters:**
- `int $failures`: Number of failures
**Returns:** `void`
**Rate Limiting:** 1 email per hour maximum

### Woo_Envios_Google_Maps

#### Constants
- `API_KEY_OPTION = 'woo_envios_google_maps_api_key'`
- `MAX_CONSECUTIVE_FAILURES = 5`
- `MAX_RETRIES = 3`
- `REQUEST_TIMEOUT = 10`

#### Properties
- `$api_key`: Google Maps API key
- `$cache_ttl`: Cache TTL in seconds (default: 30 days)
- `$api_urls`: Array of API endpoints

#### Public Methods

##### `is_configured(): bool`
**Description:** Checks if Google Maps is properly configured
**Returns:** `bool` True if API key is valid

##### `calculate_distance(string $origin, string $destination): array|WP_Error`
**Description:** Calculates distance using Distance Matrix API
**Parameters:**
- `string $origin`: Origin coordinates "lat,lng"
- `string $destination`: Destination coordinates "lat,lng"
**Returns:** `array|WP_Error` Distance data or error

#### Private Methods

##### `get_api_key(): string`
**Description:** Retrieves API key from options
**Returns:** `string` API key

##### `validate_api_key_format(string $api_key): bool`
**Description:** Validates API key format
**Parameters:**
- `string $api_key`: API key to validate
**Returns:** `bool` True if format is valid
**Validation:** 39 characters starting with "AIza"

### Woo_Envios_Weather

#### Constants
- `API_URL = 'https://api.openweathermap.org/data/2.5/weather'`
- `CACHE_DURATION = 3600` (1 hour)

#### Public Methods

##### `get_weather_multiplier(float $lat, float $lng): float`
**Description:** Gets weather multiplier based on rain conditions
**Parameters:**
- `float $