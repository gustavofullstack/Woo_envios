# TriqHub Shipping & Radius - API Reference

## Overview

This document provides comprehensive technical documentation for the TriqHub Shipping & Radius plugin's public API, including WordPress hooks, filters, class methods, and internal API routes. The plugin extends WooCommerce with Brazilian CEP-based coordinate collection, radius-based shipping rules, and Google Maps integration.

## WordPress Hooks & Filters

### Actions

#### `woo_envios_bootstrap`
**Priority:** 20 (runs after WooCommerce loads)  
**Description:** Main plugin initialization hook. Verifies WooCommerce compatibility and loads the plugin instance.  
**Location:** `triqhub-shipping-radius.php`  
**Parameters:** None  
**Returns:** `void`

#### `admin_notices`
**Triggered by:** `woo_envios_bootstrap` when WooCommerce is missing or outdated  
**Description:** Displays admin warnings when WooCommerce is not active or version is incompatible.  
**Parameters:** None  
**Returns:** `void`

#### `plugins_loaded`
**Priority:** 20  
**Description:** Triggers plugin bootstrap after all plugins are loaded.  
**Parameters:** None  
**Returns:** `void`

#### `wp_enqueue_scripts`
**Hook:** `wp_enqueue_scripts`  
**Callback:** `TriqHub_Shipping_Plugin::enqueue_frontend_styles()`  
**Description:** Enqueues frontend CSS styles on checkout and cart pages.  
**Parameters:** None  
**Returns:** `void`

#### `admin_enqueue_scripts`
**Hook:** `admin_enqueue_scripts`  
**Callback:** `triqhub_enqueue_admin_Woo_envios()`  
**Description:** Enqueues TriqHub admin styling.  
**Parameters:** None  
**Returns:** `void`

#### `woocommerce_shipping_init`
**Hook:** `woocommerce_shipping_init`  
**Callback:** `TriqHub_Shipping_Plugin::load_shipping_class()`  
**Description:** Loads shipping class when WooCommerce shipping is initialized.  
**Parameters:** None  
**Returns:** `void`

#### `woocommerce_update_options_shipping_woo_envios_radius_{instance_id}`
**Hook:** Dynamic hook based on instance ID  
**Callback:** `Woo_Envios_Shipping_Method::process_admin_options()`  
**Description:** Processes admin options for shipping method instances.  
**Parameters:** None  
**Returns:** `void`

### Filters

#### `woocommerce_shipping_methods`
**Hook:** `woocommerce_shipping_methods`  
**Callback:** `TriqHub_Shipping_Plugin::register_shipping_method()`  
**Description:** Registers custom shipping methods with WooCommerce.  
**Parameters:**
- `array $methods`: Existing shipping methods array
- **Returns:** `array` Modified shipping methods array

#### `woocommerce_package_rates`
**Hook:** `woocommerce_package_rates`  
**Callback:** `TriqHub_Shipping_Plugin::sort_shipping_rates()`  
**Description:** Sorts shipping rates to display Flash Delivery (radius-based) on top.  
**Parameters:**
- `array $rates`: Current shipping rates
- `array $package`: WooCommerce package data
- **Returns:** `array` Sorted shipping rates

#### `puc_request_info_query_args-{slug}`
**Hook:** `puc_request_info_query_args-triqhub-shipping-radius`  
**Callback:** Anonymous function in `TriqHub_Shipping_Plugin::init_updater()`  
**Description:** Injects license key and site URL into update requests for GitHub updater.  
**Parameters:**
- `array $queryArgs`: Existing query arguments
- **Returns:** `array` Modified query arguments with license data

#### `pre_set_site_transient_update_plugins`
**Hook:** `pre_set_site_transient_update_plugins`  
**Callback:** `Woo_Envios_Updater::check_update()`  
**Description:** Checks for plugin updates from remote JSON.  
**Parameters:**
- `object $transient`: Update transient object
- **Returns:** `object` Modified transient with update data

#### `plugins_api`
**Hook:** `plugins_api`  
**Callback:** `Woo_Envios_Updater::check_info()`  
**Description:** Provides plugin information for "View Details" popup.  
**Parameters:**
- `mixed $res`: Existing result
- `string $action`: API action
- `object $args`: Request arguments
- **Returns:** `object|mixed` Plugin information object or original result

#### `upgrader_process_complete`
**Hook:** `upgrader_process_complete`  
**Callback:** `Woo_Envios_Updater::track_update()`  
**Description:** Tracks plugin update completion (currently placeholder).  
**Parameters:**
- `object $upgrader_object`: Upgrader object
- `array $options`: Update options
- **Returns:** `void`

## Core Classes & Public Methods

### TriqHub_Shipping_Plugin (Main Plugin Class)

#### `TriqHub_Shipping_Plugin::instance(): TriqHub_Shipping_Plugin`
**Description:** Singleton pattern - retrieves the single plugin instance.  
**Parameters:** None  
**Returns:** `TriqHub_Shipping_Plugin` Plugin instance

#### `TriqHub_Shipping_Plugin::register_shipping_method(array $methods): array`
**Description:** Registers custom shipping methods with WooCommerce.  
**Parameters:**
- `array $methods`: Current shipping methods
- **Returns:** `array` Methods with added `woo_envios_radius` and `woo_envios_superfrete`

#### `TriqHub_Shipping_Plugin::sort_shipping_rates(array $rates, array $package): array`
**Description:** Sorts shipping rates to prioritize Flash Delivery.  
**Parameters:**
- `array $rates`: Current shipping rates
- `array $package`: WooCommerce package data
- **Returns:** `array` Sorted rates with Flash Delivery first

#### `TriqHub_Shipping_Plugin::enqueue_frontend_styles(): void`
**Description:** Enqueues frontend CSS for checkout and cart pages.  
**Parameters:** None  
**Returns:** `void`

#### `TriqHub_Shipping_Plugin::activate(): void`
**Description:** Plugin activation callback - creates Google Maps cache table.  
**Parameters:** None  
**Returns:** `void`

### Woo_Envios_Shipping_Method (Radius-Based Shipping)

#### `Woo_Envios_Shipping_Method::__construct(int $instance_id = 0)`
**Description:** Constructor for radius-based shipping method.  
**Parameters:**
- `int $instance_id`: Shipping method instance ID (default: 0)
- **Returns:** `void`

#### `Woo_Envios_Shipping_Method::init(): void`
**Description:** Initializes shipping method fields and hooks.  
**Parameters:** None  
**Returns:** `void`

#### `Woo_Envios_Shipping_Method::calculate_shipping(array $package = []): void`
**Description:** Calculates shipping based on distance from store coordinates.  
**Parameters:**
- `array $package`: WooCommerce package data
- **Returns:** `void`

#### `Woo_Envios_Shipping_Method::init_form_fields(): void`
**Description:** Defines shipping method form fields (empty in this implementation).  
**Parameters:** None  
**Returns:** `void`

### Woo_Envios_Google_Maps (Google Maps API Integration)

#### `Woo_Envios_Google_Maps::is_configured(): bool`
**Description:** Checks if Google Maps API is properly configured.  
**Parameters:** None  
**Returns:** `bool` True if API key is valid

#### `Woo_Envios_Google_Maps::calculate_distance(string $origin, string $destination): array|WP_Error`
**Description:** Calculates route distance using Google Distance Matrix API.  
**Parameters:**
- `string $origin`: Origin coordinates "lat,lng"
- `string $destination`: Destination coordinates "lat,lng"
- **Returns:** `array|WP_Error` Distance data or error object

### Woo_Envios_Weather (Weather Service)

#### `Woo_Envios_Weather::get_weather_multiplier(float $lat, float $lng): float`
**Description:** Gets weather-based price multiplier for dynamic pricing.  
**Parameters:**
- `float $lat`: Latitude
- `float $lng`: Longitude
- **Returns:** `float` Multiplier (1.0 = no rain, 1.2 = light rain, 1.5 = heavy rain)

#### `Woo_Envios_Weather::get_weather_description(array $weather_data): string`
**Description:** Gets human-readable weather description.  
**Parameters:**
- `array $weather_data`: Weather data from OpenWeather API
- **Returns:** `string` Weather description

#### `Woo_Envios_Weather::clear_cache(): void`
**Description:** Clears weather cache transients.  
**Parameters:** None  
**Returns:** `void`

### Woo_Envios_Logger (Logging System)

#### `Woo_Envios_Logger::shipping_calculated(float $distance, float $base_price, float $final_price, array $multipliers, string $address = '', array $store_coords = [], array $customer_coords = []): void`
**Description:** Logs shipping calculation details.  
**Parameters:**
- `float $distance`: Distance in km
- `float $base_price`: Base shipping price
- `float $final_price`: Final price after multipliers
- `array $multipliers`: Applied multiplier reasons
- `string $address`: Customer address (optional)
- `array $store_coords`: Store coordinates (optional)
- `array $customer_coords`: Customer coordinates (optional)
- **Returns:** `void`

#### `Woo_Envios_Logger::error(string $message): void`
**Description:** Logs error message.  
**Parameters:**
- `string $message`: Error message
- **Returns:** `void`

#### `Woo_Envios_Logger::info(string $message): void`
**Description:** Logs info message.  
**Parameters:**
- `string $message`: Info message
- **Returns:** `void`

#### `Woo_Envios_Logger::warning(string $message): void`
**Description:** Logs warning message.  
**Parameters:**
- `string $message`: Warning message
- **Returns:** `void`

#### `Woo_Envios_Logger::api_failure(string $api_name, string $error): void`
**Description:** Logs API failure.  
**Parameters:**
- `string $api_name`: API name (e.g., "Google Maps")
- `string $error`: Error message
- **Returns:** `void`

#### `Woo_Envios_Logger::circuit_breaker_opened(int $failures): void`
**Description:** Logs circuit breaker activation and notifies admin.  
**Parameters:**
- `int $failures`: Number of consecutive failures
- **Returns:** `void`

#### `Woo_Envios_Logger::distance_out_of_range(float $distance, array $destination_data): void`
**Description:** Logs when customer distance is outside delivery range.  
**Parameters:**
- `float $distance`: Calculated distance in km
- `array $destination_data`: Destination address data
- **Returns:** `void`

#### `Woo_Envios_Logger::cleanup_old_logs(): void`
**Description:** Cleans up log files older than 7 days.  
**Parameters:** None  
**Returns:** `void`

### Woo_Envios_Admin (Admin Interface)

#### `Woo_Envios_Admin::get_store_coordinates(): array`
**Description:** Retrieves store coordinates from plugin settings.  
**Parameters:** None  
**Returns:** `array` with keys 'lat' and 'lng'

#### `Woo_Envios_Admin::match_tier_by_distance(float $distance): array|null`
**Description:** Matches distance to configured shipping tier.  
**Parameters:**
- `float $distance`: Distance in kilometers
- **Returns:** `array|null` Tier configuration or null if no match

### Services\Geocoder (Geocoding Service)

#### `Woo_Envios\Services\Geocoder::geocode(string $address): array|false`
**Description:** Geocodes address to coordinates using Google Maps API.  
**Parameters:**
- `string $address`: Full address string
- **Returns:** `array|false` Coordinates array with 'lat' and 'lng' or false on failure

### Services\Woo_Envios_Correios (Correios/Shipping Service)

#### `Woo_Envios\Services\Woo_Envios_Correios::is_enabled(): bool`
**Description:** Checks if Correios shipping is enabled.  
**Parameters:** None  
**Returns:** `bool` True if enabled

#### `Woo_Envios\Services\Woo_Envios_Correios::calculate(array $package): array|false`
**Description:** Calculates Correios shipping rates.  
**Parameters:**
- `array $package`: WooCommerce package data
- **Returns:** `array|false` Array of rate data or false on failure

## Internal API Routes & Endpoints

### Google Maps API Integration Endpoints

#### Geocoding API
**URL:** `https://maps.googleapis.com/maps/api/geocode/json`  
**Method:** GET  
**Parameters:**
- `address`: Address to geocode
- `key`: Google Maps API key
- `language`: Language code (default: pt_br)

#### Distance Matrix API
**URL:** `https://maps.googleapis.com/maps/api/distancematrix/json`  
**Method:** GET  
**Parameters:**
- `origins`: Origin coordinates "lat,lng"
- `destinations`: Destination coordinates "lat,lng"
- `key`: Google Maps API key
- `units`: Distance units (metric/imperial)

#### Places Autocomplete API
**URL:** `https://maps.googleapis.com/maps/api/place/autocomplete/json`  
**Method:** GET  
**Parameters:**
- `input`: User input for autocomplete
- `key`: Google Maps API key
- `types`: Address type filters
- `components`: Country restrictions

#### Place Details API
**URL:** `https://maps.googleapis.com/maps/api/place/details/json`  
**Method:** GET  
**Parameters:**
- `place_id`: Google Place ID
- `key`: Google Maps API key
- `fields`: Requested data fields

### OpenWeather API Integration

#### Current Weather API
**URL:** `https://api.openweathermap.org/data/2.5/weather`  
**Method:** GET  
**Parameters:**
- `lat`: Latitude
- `lon`: Longitude
- `appid`: OpenWeather API key
- `units`: Units (metric/imperial)
- `lang`: Language code

### GitHub Update API

#### Plugin Update JSON
**URL:** `https://raw.githubusercontent.com/{username}/{repo}/main/plugin-update.json`  
**Method:** GET  
**Response Format:**
```json
{
  "name": "Plugin Name",
  "version": "1.2.8",
  "download_url": "https://github.com/.../plugin.zip",
  "requires": "6.2",
  "requires_php": "7.4",
  "tested": "6.5",
  "sections": {
    "description": "Plugin description",
    "changelog": "Version changes"
  }
}
```

## Database Schema

### Cache Tables

#### `{prefix}woo_envios_geocode_cache`
**Purpose:** Caches Google Maps geocoding results  
**Schema:**
```sql
CREATE TABLE IF NOT EXISTS {prefix}woo_envios_geocode_cache (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  cache_key varchar(64) NOT NULL,
  result_data longtext NOT NULL,
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at datetime NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY cache_key (cache_key),
  KEY expires_at (expires_at)
)
```

### WordPress Options (Settings)

#### Plugin Configuration Options
- `woo_envios_google_maps_api_key`: Google Maps API key
- `woo_envios_weather_api_key`: OpenWeather API key
- `woo_envios_enable_logs`: Enable/disable logging
- `woo_envios_dynamic_pricing_enabled`: Dynamic pricing toggle
- `woo_envios_rain_light_multiplier`: Light rain multiplier (default: 1.2)
- `woo_envios_rain_heavy_multiplier`: Heavy rain multiplier (default: 1.5)
- `woo_envios_weekend_multiplier`: Weekend multiplier
- `woo_envios_max_multiplier`: Maximum price multiplier (default: 2.0)
- `woo_envios_peak_hours`: Array of peak hour configurations
- `triqhub_license_key`: TriqHub license key for updates

#### Transients (Temporary Cache)
- `woo_envios_weather_{hash}`: Weather data cache (1 hour)
- `woo_envios_api_failures`: API failure count for circuit breaker
- `woo_envios_last_failure_notification`: Last admin notification timestamp
- `_transient_woo_envios_weather_*`: Weather API cache entries

## Session Data Structure

### WooCommerce Session Variables

#### `woo_envios_coords`
**Type:** `array`  
**Structure:**
```php
[
  'lat' => float,      // Latitude
  'lng' => float,      // Longitude
  'signature' => string // Address signature for validation
]
```

**Purpose:** Stores customer coordinates retrieved during checkout to avoid repeated geocoding.

## Error Handling & Status Codes

### WordPress Error Codes

#### Google Maps API Errors
- `not_configured`: Google Maps API key not configured
- `api_failure`: Google Maps API request failed
- `invalid_response`: Invalid API response format
- `circuit_open`: Circuit breaker active (too many failures)

#### Shipping Calculation Errors
- `no_store_coords`: Store coordinates not configured
- `no_customer_coords`: Customer coordinates not available
- `distance_out_of_range`: Customer outside delivery radius
- `geocode_failed`: Address geocoding failed

### HTTP Status Codes (External APIs)

#### Google Maps API
