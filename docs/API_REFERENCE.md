# TriqHub Shipping & Radius - API Reference

## Overview

This document provides comprehensive technical documentation for all public APIs, WordPress hooks, filters, and internal methods available in the TriqHub Shipping & Radius plugin (version 1.2.15). The plugin integrates Brazilian CEP-based coordinate collection with Google Maps API for precise radius-based shipping calculations in WooCommerce.

## Table of Contents

1. [WordPress Hooks & Filters](#wordpress-hooks--filters)
2. [Core Classes & Methods](#core-classes--methods)
3. [Shipping Methods API](#shipping-methods-api)
4. [External API Integrations](#external-api-integrations)
5. [Session & Data Management](#session--data-management)
6. [Utility Functions](#utility-functions)

## WordPress Hooks & Filters

### Actions

#### `plugins_loaded` (Priority: 20)
**Description:** Initializes the plugin after WooCommerce is loaded
**Callback:** `woo_envios_bootstrap()`
**Parameters:** None
**Returns:** `void`

#### `admin_notices`
**Description:** Displays admin notices for missing dependencies
**Callback:** Anonymous function in `woo_envios_bootstrap()`
**Parameters:** None
**Returns:** `void`

#### `wp_enqueue_scripts`
**Description:** Enqueues frontend styles for checkout and cart pages
**Callback:** `TriqHub_Shipping_Plugin::enqueue_frontend_styles()`
**Parameters:** None
**Returns:** `void`

#### `admin_enqueue_scripts`
**Description:** Enqueues TriqHub admin styling
**Callback:** `triqhub_enqueue_admin_Woo_envios()`
**Parameters:** None
**Returns:** `void`

#### `woocommerce_shipping_init`
**Description:** Loads shipping class when WooCommerce is ready
**Callback:** `TriqHub_Shipping_Plugin::load_shipping_class()`
**Parameters:** None
**Returns:** `void`

#### `woocommerce_update_options_shipping_{$this->id}_{$this->instance_id}`
**Description:** Processes admin options for shipping method instances
**Callback:** `Woo_Envios_Shipping_Method::process_admin_options()`
**Parameters:** None
**Returns:** `void`

### Filters

#### `woocommerce_shipping_methods`
**Description:** Registers custom shipping methods with WooCommerce
**Callback:** `TriqHub_Shipping_Plugin::register_shipping_method()`
**Parameters:**
- `array $methods`: Current shipping methods array
**Returns:** `array` Updated shipping methods array

#### `woocommerce_package_rates`
**Description:** Sorts shipping rates to prioritize Flash Delivery
**Callback:** `TriqHub_Shipping_Plugin::sort_shipping_rates()`
**Parameters:**
- `array $rates`: Current shipping rates
- `array $package`: WooCommerce package data
**Returns:** `array` Sorted shipping rates

#### `puc_request_info_query_args-{$slug}`
**Description:** Injects license key into update requests for GitHub Updater
**Callback:** Anonymous function in `TriqHub_Shipping_Plugin::init_updater()`
**Parameters:**
- `array $queryArgs`: Current query arguments
**Returns:** `array` Modified query arguments with license key

#### `pre_set_site_transient_update_plugins`
**Description:** Checks for plugin updates via GitHub
**Callback:** `Woo_Envios_Updater::check_update()`
**Parameters:**
- `object $transient`: Update transient object
**Returns:** `object` Modified transient with update data

#### `plugins_api`
**Description:** Provides plugin information for "View Details" popup
**Callback:** `Woo_Envios_Updater::check_info()`
**Parameters:**
- `mixed $res`: Current response
- `string $action`: API action
- `object $args`: Request arguments
**Returns:** `object|mixed` Plugin information object

#### `upgrader_process_complete`
**Description:** Tracks plugin updates (optional)
**Callback:** `Woo_Envios_Updater::track_update()`
**Parameters:**
- `object $upgrader_object`: Upgrader object
- `array $options`: Update options
**Returns:** `void`

## Core Classes & Methods

### TriqHub_Shipping_Plugin

Main plugin singleton class that orchestrates all components.

#### Public Methods

##### `instance(): TriqHub_Shipping_Plugin`
**Description:** Returns singleton instance of the plugin
**Parameters:** None
**Returns:** `TriqHub_Shipping_Plugin` Singleton instance

##### `register_shipping_method(array $methods): array`
**Description:** Registers custom shipping methods with WooCommerce
**Parameters:**
- `array $methods`: Current shipping methods
**Returns:** `array` Methods array with added:
  - `woo_envios_radius`: Local delivery by radius (Flash)
  - `woo_envios_superfrete`: SuperFrete (PAC/SEDEX/Mini) for outside radius

##### `load_shipping_class(): void`
**Description:** Loads shipping class when WooCommerce is ready
**Parameters:** None
**Returns:** `void`

##### `sort_shipping_rates(array $rates, array $package): array`
**Description:** Sorts shipping rates to put Flash Delivery on top
**Parameters:**
- `array $rates`: Current shipping rates
- `array $package`: WooCommerce package data
**Returns:** `array` Sorted rates with Flash Delivery first

##### `enqueue_frontend_styles(): void`
**Description:** Enqueues frontend CSS for checkout and cart pages
**Parameters:** None
**Returns:** `void`

##### `activate(): void`
**Description:** Plugin activation callback - creates cache table
**Parameters:** None
**Returns:** `void`

#### Private Methods

##### `define_constants(): void`
**Description:** Defines plugin constants:
- `WOO_ENVIOS_FILE`: Main plugin file path
- `WOO_ENVIOS_PATH`: Plugin directory path
- `WOO_ENVIOS_URL`: Plugin URL
- `WOO_ENVIOS_ASSETS`: Assets URL
- `WOO_ENVIOS_DEFAULT_LAT`: Default latitude (-18.911)
- `WOO_ENVIOS_DEFAULT_LNG`: Default longitude (-48.262)

##### `include_files(): void`
**Description:** Includes required class files in dependency order

##### `load_components(): void`
**Description:** Initializes all plugin components

##### `init_updater(): void`
**Description:** Initializes GitHub-based update checker

##### `register_hooks(): void`
**Description:** Registers all WordPress hooks

##### `create_google_cache_table(): void`
**Description:** Creates geocode cache table in database

##### `maybe_create_cache_table(): void`
**Description:** Self-healing method to create cache table if missing

### Woo_Envios_Shipping_Method

Main shipping method class for radius-based delivery calculations.

#### Public Methods

##### `__construct(int $instance_id = 0)`
**Description:** Constructor for shipping method instance
**Parameters:**
- `int $instance_id`: Shipping method instance ID

##### `init(): void`
**Description:** Initializes form fields and hooks
**Parameters:** None
**Returns:** `void`

##### `init_form_fields(): void`
**Description:** Defines settings fields for WooCommerce zones
**Parameters:** None
**Returns:** `void`

##### `calculate_shipping(array $package = []): void`
**Description:** Main shipping calculation method
**Parameters:**
- `array $package`: WooCommerce package data containing:
  - `destination`: Address information
  - `contents`: Cart items
  - `cart_subtotal`: Cart subtotal
**Returns:** `void` (Adds rates via `add_rate()`)

#### Protected/Private Methods

##### `get_session_coordinates(string $signature): ?array`
**Description:** Retrieves customer coordinates from session
**Parameters:**
- `string $signature`: Address signature for validation
**Returns:** `array|null` Coordinates array with `lat` and `lng` keys, or null

##### `calculate_route_distance(array $store_coords, array $customer_coords, array $package): array|WP_Error`
**Description:** Calculates route distance using Google Distance Matrix API
**Parameters:**
- `array $store_coords`: Store coordinates with `lat` and `lng`
- `array $customer_coords`: Customer coordinates with `lat` and `lng`
- `array $package`: WooCommerce package data
**Returns:** `array|WP_Error` Distance data or error object

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
- `array $package`: WooCommerce package data
**Returns:** `string` MD5 hash of normalized address components

##### `calculate_dynamic_multiplier(array $package): array`
**Description:** Calculates dynamic pricing multipliers
**Parameters:**
- `array $package`: WooCommerce package data
**Returns:** `array` With keys:
  - `total`: Final multiplier value
  - `reasons`: Array of multiplier descriptions

##### `get_peak_hour_multiplier(): array`
**Description:** Checks if current time is within peak hours
**Parameters:** None
**Returns:** `array` With keys:
  - `multiplier`: Peak hour multiplier
  - `label`: Human-readable label

##### `get_weather_multiplier(array $package): float`
**Description:** Gets weather-based price multiplier
**Parameters:**
- `array $package`: WooCommerce package data
**Returns:** `float` Weather multiplier (1.0-1.5)

##### `is_weekend(): bool`
**Description:** Checks if current day is weekend
**Parameters:** None
**Returns:** `bool` True if Saturday or Sunday

##### `calculate_correios_shipping(array $package): void`
**Description:** Calculates Correios shipping for outside-radius destinations
**Parameters:**
- `array $package`: WooCommerce package data
**Returns:** `void`

## Shipping Methods API

### Woo_Envios_Shipping_Method (Radius-based)

**ID:** `woo_envios_radius`
**Type:** Local delivery by distance
**Supports:** Shipping zones, instance settings, instance settings modal

#### Instance Settings Fields

| Field | Type | Default | Description |
|-------|------|---------|-------------|
| `enabled` | Checkbox | `yes` | Enable/disable method for zone |
| `title` | Text | `Entrega Flash` | Display title shown to customers |

#### Rate Metadata

When a rate is added via `add_rate()`, the following metadata is included:

```php
$rate['meta_data'] = [
    'distance' => float,      // Distance in km
    'base_price' => float,    // Base price before multipliers
    'multiplier' => float,    // Total multiplier applied
    'breakdown' => array,     // Array of multiplier reasons
    'debug_info' => array,    // Debug information including coordinates
];
```

### Woo_Envios_Superfrete_Shipping_Method

**ID:** `woo_envios_superfrete`
**Type:** Correios/PAC/SEDEX shipping
**Class:** `Woo_Envios\Services\Woo_Envios_Superfrete_Shipping_Method`

#### Rate Metadata

```php
$rate['meta_data'] = [
    'service_code' => string, // Correios service code
    'deadline' => int,        // Delivery deadline in days
    'method' => 'correios',   // Shipping method identifier
];
```

## External API Integrations

### Google Maps API

#### Configuration
- **API Key Option:** `woo_envios_google_maps_api_key`
- **Cache TTL:** `udi_google_maps_cache_ttl` (default: 30 days)

#### Available Endpoints

##### Geocoding API
**URL:** `https://maps.googleapis.com/maps/api/geocode/json`
**Method:** `GET`
**Parameters:**
- `address`: Address to geocode
- `key`: Google Maps API key
- `language`: `pt-BR` (Portuguese Brazil)

##### Places Autocomplete API
**URL:** `https://maps.googleapis.com/maps/api/place/autocomplete/json`
**Method:** `GET`
**Parameters:**
- `input`: Search query
- `key`: Google Maps API key
- `types`: `address`
- `components`: `country:br` (Brazil only)

##### Place Details API
**URL:** `https://maps.googleapis.com/maps/api/place/details/json`
**Method:** `GET`
**Parameters:**
- `place_id`: Google Places ID
- `key`: Google Maps API key
- `fields`: `address_component,geometry`

##### Distance Matrix API
**URL:** `https://maps.googleapis.com/maps/api/distancematrix/json`
**Method:** `GET`
**Parameters:**
- `origins`: Origin coordinates
- `destinations`: Destination coordinates
- `key`: Google Maps API key
- `mode`: `driving`
- `language`: `pt-BR`

### OpenWeather API

#### Configuration
- **API Key Option:** `woo_envios_weather_api_key`
- **Endpoint:** `https://api.openweathermap.org/data/2.5/weather`
- **Cache Duration:** 3600 seconds (1 hour)

#### Request Parameters
```php
[
    'lat' => float,      // Latitude
    'lon' => float,      // Longitude
    'appid' => string,   // API Key
    'units' => 'metric', // Metric units
    'lang' => 'pt_br',   // Portuguese language
]
```

#### Weather Multipliers
- **Light Rain:** `woo_envios_rain_light_multiplier` (default: 1.2)
- **Heavy Rain:** `woo_envios_rain_heavy_multiplier` (default: 1.5)

### GitHub Updater API

#### Configuration
- **Repository:** `https://github.com/gustavofullstack/triqhub-shipping-radius`
- **Branch:** `main`
- **Update JSON:** `https://raw.githubusercontent.com/gustavofullstack/triqhub-shipping-radius/main/plugin-update.json`

#### Authentication Parameters
```php
[
    'license_key' => string, // From option: triqhub_license_key
    'site_url' => string,    // WordPress site URL
]
```

## Session & Data Management

### Session Data Structure

#### Coordinate Storage
```php
WC()->session->set('woo_envios_coords', [
    'lat' => float,       // Latitude
    'lng' => float,       // Longitude
    'signature' => string, // Address signature for validation
]);
```

#### Session Signature Generation
The signature is generated from normalized address components:
```php
$parts = [
    sanitize_text_field($destination['city'] ?? ''),
    sanitize_text_field($destination['state'] ?? ''),
    preg_replace('/\D/', '', $destination['postcode'] ?? ''), // Digits only
    sanitize_text_field($destination['country'] ?? ''),
];
$signature = md5(strtolower(implode('|', $parts)));
```

### Cache Management

#### Geocode Cache Table
**Table Name:** `{$wpdb->prefix}woo_envios_geocode_cache`
**Structure:**
```sql
CREATE TABLE IF NOT EXISTS $table_name (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    cache_key varchar(64) NOT NULL,
    result_data longtext NOT NULL,
    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at datetime NOT NULL,
    PRIMARY KEY  (id),
    UNIQUE KEY cache_key (cache_key),
    KEY expires_at (expires_at)
)
```

#### Cache Key Generation
```php
$cache_key = 'woo_envios_weather_' . md5($lat . '|' . $lng);
// or for geocoding:
$cache_key = md5($address . '|' . $api_key);
```

#### Transient Cache
- **Weather Data:** `_transient_woo_envios_weather_{hash}`
- **Failure Notifications:** `_transient_woo_envios_last_failure_notification`

## Utility Functions

### Woo_Envios_Logger

#### Public Methods

##### `shipping_calculated(float $distance, float $base_price, float $final_price, array $multipliers, string $address = '', array $store_coords = [], array $customer_coords = []): void`
**Description:** Logs shipping calculation details
**Parameters:**
- `float $distance`: Distance in km
- `float $base_price`: Base price before multipliers
- `float $final_price`: Final price after multipliers
- `array $multipliers`: Applied multiplier descriptions
- `string $address`: Customer address (optional)
- `array $store_coords`: Store coordinates (optional)
- `array $customer_coords`: Customer coordinates (optional)

##### `error(string $message): void`
**Description:** Logs error message
**Parameters:** `string $message` Error message

##### `info(string $message): void`
**Description:** Logs info message
**Parameters:** `string $message` Info message

##### `warning(string $message): void`
**Description:** Logs warning message
**Parameters:** `string $message` Warning message

##### `api_failure(string $api_name, string $error): void`
**Description:** Logs API failure
**Parameters:**
- `string $api_name`: API name (e.g., "Google Maps")
- `string $error`: Error message

##### `circuit_breaker_opened(int $failures): void`
**Description:** Logs circuit breaker activation and notifies admin
**Parameters:** `int $failures` Number of consecutive failures

##### `distance_out_of_range(float $distance, array $destination