# TriqHub Shipping & Radius Plugin - API Reference

## Overview

This document provides comprehensive API reference for the TriqHub Shipping & Radius WordPress plugin. It covers all public functions, filters, actions, hooks, and API endpoints available for developers.

## Plugin Information

| Property | Value |
|----------|-------|
| **Plugin Name** | TriqHub: Shipping & Radius |
| **Version** | 1.0.0 |
| **Text Domain** | woo-envios |
| **Minimum WordPress** | 6.2 |
| **Minimum PHP** | 7.4 |
| **Main Class** | `Woo_Envios_Plugin` |

## Core Classes & Namespaces

### Main Plugin Class: `Woo_Envios_Plugin`

**Singleton Pattern** - Use `Woo_Envios_Plugin::instance()` to access the plugin instance.

#### Public Methods

| Method | Description | Parameters | Return Type |
|--------|-------------|------------|-------------|
| `instance()` | Get singleton instance | None | `Woo_Envios_Plugin` |
| `register_shipping_method()` | Register custom shipping methods with WooCommerce | `array $methods` | `array` |
| `sort_shipping_rates()` | Sort shipping rates (Flash delivery on top) | `array $rates`, `array $package` | `array` |
| `enqueue_frontend_styles()` | Enqueue frontend CSS styles | None | `void` |
| `activate()` | Plugin activation callback | None | `void` |

#### Hook Registration

| Hook | Method | Priority | Description |
|------|--------|----------|-------------|
| `plugins_loaded` | `woo_envios_bootstrap()` | 20 | Initialize plugin after WooCommerce loads |
| `register_activation_hook` | `activate()` | - | Create database tables on activation |
| `wp_enqueue_scripts` | `enqueue_frontend_styles()` | - | Load frontend CSS on checkout/cart |
| `woocommerce_package_rates` | `sort_shipping_rates()` | 10 | Sort shipping rates display order |

### Shipping Method Class: `Woo_Envios_Shipping_Method`

Extends `WC_Shipping_Method` - Provides radius-based delivery calculations.

#### Public Methods

| Method | Description | Parameters | Return Type |
|--------|-------------|------------|-------------|
| `__construct()` | Constructor | `int $instance_id = 0` | - |
| `init()` | Initialize form fields and hooks | None | `void` |
| `calculate_shipping()` | Main shipping calculation logic | `array $package = array()` | `void` |
| `init_form_fields()` | Define settings fields for WooCommerce | None | `void` |

#### Instance Settings Fields

| Field | Type | Default | Description |
|-------|------|---------|-------------|
| `enabled` | Checkbox | `yes` | Enable/disable method for zone |
| `title` | Text | `Entrega Flash` | Display title shown to customers |

### Google Maps Integration: `Woo_Envios_Google_Maps`

Handles geocoding and distance calculations using Google Maps API.

#### Public Methods

| Method | Description | Parameters | Return Type |
|--------|-------------|------------|-------------|
| `is_configured()` | Check if API key is configured | None | `bool` |
| `geocode_address()` | Convert address to coordinates | `string $address` | `array\|WP_Error` |
| `calculate_distance()` | Calculate route distance between points | `array $origin`, `array $destination` | `array\|WP_Error` |

### Weather Service: `Woo_Envios_Weather`

Integrates with OpenWeather API for dynamic pricing based on weather conditions.

#### Public Methods

| Method | Description | Parameters | Return Type |
|--------|-------------|------------|-------------|
| `get_weather_multiplier()` | Get price multiplier based on rain | `float $lat`, `float $lng` | `float` |
| `get_weather_description()` | Get human-readable weather description | `array $weather_data` | `string` |
| `clear_cache()` | Clear weather cache | None | `void` |

### Correios/SuperFrete Service: `Woo_Envios\Services\Woo_Envios_Correios`

Handles Brazilian postal service calculations.

#### Public Methods

| Method | Description | Parameters | Return Type |
|--------|-------------|------------|-------------|
| `is_enabled()` | Check if service is enabled | None | `bool` |
| `calculate()` | Calculate shipping rates | `array $package` | `array` |

### Geocoder Service: `Woo_Envios\Services\Geocoder`

Server-side geocoding service for address-to-coordinate conversion.

#### Public Methods

| Method | Description | Parameters | Return Type |
|--------|-------------|------------|-------------|
| `geocode()` | Geocode an address to coordinates | `string $address` | `array\|false` |

## WordPress Hooks (Filters & Actions)

### Filters

| Filter Hook | Callback | Priority | Description |
|-------------|----------|----------|-------------|
| `woocommerce_shipping_methods` | `Woo_Envios_Plugin::register_shipping_method()` | Default | Register custom shipping methods |
| `woocommerce_package_rates` | `Woo_Envios_Plugin::sort_shipping_rates()` | 10 | Sort shipping rates (Flash first) |
| `plugins_api` | `Woo_Envios_Updater::check_info()` | 10 | Plugin update information |
| `pre_set_site_transient_update_plugins` | `Woo_Envios_Updater::check_update()` | Default | Check for plugin updates |

### Actions

| Action Hook | Callback | Priority | Description |
|-------------|----------|----------|-------------|
| `plugins_loaded` | `woo_envios_bootstrap()` | 20 | Initialize plugin |
| `admin_notices` | Anonymous function | Default | Show WooCommerce dependency warning |
| `wp_enqueue_scripts` | `Woo_Envios_Plugin::enqueue_frontend_styles()` | Default | Load frontend CSS |
| `admin_enqueue_scripts` | `triqhub_enqueue_admin_Woo_envios()` | Default | Load admin CSS |
| `woocommerce_shipping_init` | `Woo_Envios_Plugin::load_shipping_class()` | Default | Load shipping class |
| `woocommerce_update_options_shipping_{id}_{instance}` | `Woo_Envios_Shipping_Method::process_admin_options()` | Default | Save shipping method settings |
| `upgrader_process_complete` | `Woo_Envios_Updater::track_update()` | 10 | Track plugin updates |

## API Endpoints

### Google Maps API Integration

**Base URL:** `https://maps.googleapis.com/maps/api/`

| Endpoint | Method | Purpose | Required Parameters |
|----------|--------|---------|---------------------|
| `/geocode/json` | GET | Address geocoding | `address`, `key` |
| `/distancematrix/json` | GET | Distance calculation | `origins`, `destinations`, `key` |

**Example Usage:**
```php
$google_maps = new Woo_Envios_Google_Maps();
$coordinates = $google_maps->geocode_address('Av. Rondon Pacheco, Uberlândia, MG');
$distance = $google_maps->calculate_distance($origin_coords, $destination_coords);
```

### OpenWeather API Integration

**Base URL:** `https://api.openweathermap.org/data/2.5/weather`

| Parameter | Required | Description |
|-----------|----------|-------------|
| `lat` | Yes | Latitude |
| `lon` | Yes | Longitude |
| `appid` | Yes | API Key |
| `units` | No | `metric` (default) |
| `lang` | No | `pt_br` (default) |

**Example Usage:**
```php
$weather = new Woo_Envios_Weather();
$multiplier = $weather->get_weather_multiplier(-18.911, -48.262);
```

### SuperFrete API Integration

**Base URL:** `https://api.superfrete.com/api/v0/calculator`

| Endpoint | Method | Purpose | Authentication |
|----------|--------|---------|----------------|
| `/calculator` | POST | Shipping calculation | Bearer Token |

**Request Body Structure:**
```json
{
  "from": {
    "postal_code": "38405-320"
  },
  "to": {
    "postal_code": "01310-100"
  },
  "services": "1,2,17",
  "options": {
    "own_hand": false,
    "receipt": false,
    "insurance_value": 0,
    "use_insurance_value": false
  },
  "package": {
    "height": 10,
    "width": 15,
    "length": 20,
    "weight": 1
  }
}
```

**Example Usage:**
```php
$correios = new Woo_Envios\Services\Woo_Envios_Correios();
$rates = $correios->calculate($package_data);
```

## Database Schema

### Table: `{prefix}_woo_envios_geocode_cache`

| Column | Type | Description |
|--------|------|-------------|
| `id` | BIGINT(20) UNSIGNED | Primary key |
| `cache_key` | VARCHAR(64) | MD5 hash of request |
| `result_data` | LONGTEXT | Cached API response |
| `created_at` | DATETIME | Cache creation timestamp |
| `expires_at` | DATETIME | Cache expiration timestamp |

**Indexes:**
- PRIMARY KEY (`id`)
- UNIQUE KEY `cache_key` (`cache_key`)
- KEY `expires_at` (`expires_at`)

## Constants

| Constant | Value | Description |
|----------|-------|-------------|
| `WOO_ENVIOS_FILE` | `__FILE__` | Main plugin file path |
| `WOO_ENVIOS_PATH` | `plugin_dir_path(__FILE__)` | Plugin directory path |
| `WOO_ENVIOS_URL` | `plugin_dir_url(__FILE__)` | Plugin URL |
| `WOO_ENVIOS_ASSETS` | `WOO_ENVIOS_URL . 'assets/'` | Assets directory URL |
| `WOO_ENVIOS_DEFAULT_LAT` | `-18.911` | Default latitude (Uberlândia) |
| `WOO_ENVIOS_DEFAULT_LNG` | `-48.262` | Default longitude (Uberlândia) |
| `WOO_ENVIOS_VERSION` | `1.0.0` | Plugin version |

## Session Data Structure

The plugin stores coordinates in WooCommerce session:

```php
WC()->session->set('woo_envios_coords', [
    'lat'       => -18.9127749,    // Latitude
    'lng'       => -48.2755227,    // Longitude
    'signature' => 'md5_hash'      // Address signature for validation
]);
```

**Signature Generation:**
```php
$signature = md5(strtolower(implode('|', [
    $city,
    $state,
    $postcode,
    $country
])));
```

## Shipping Rate Structure

### Flash Delivery (Radius-based)

```php
$rate = [
    'id'        => $this->get_rate_id(),
    'label'     => $this->title . ' (' . $distance_km . ' km)',
    'cost'      => $calculated_price,
    'package'   => $package,
    'meta_data' => [
        'distance_km' => $distance_km,
        'base_price'  => $base_price,
        'multipliers' => $multipliers_applied
    ]
];
```

### Correios/SuperFrete Rates

```php
$rate = [
    'id'        => 'woo_envios_superfrete_' . $service_code,
    'label'     => $service_name . ' (' . $delivery_days . ' dias)',
    'cost'      => $price,
    'package'   => $package,
    'meta_data' => [
        'service_code'   => $service_code,
        'delivery_days'  => $delivery_days,
        'carrier'        => 'Correios'
    ]
];
```

## Error Handling

### WP_Error Codes

| Code | Message | Context |
|------|---------|---------|
| `google_maps_not_configured` | Google Maps API key not configured | Geocoding failure |
| `google_maps_api_error` | Google Maps API request failed | API communication error |
| `address_geocode_failed` | Could not geocode address | Invalid address |
| `distance_calculation_failed` | Could not calculate distance | Route calculation error |
| `superfrete_api_error` | SuperFrete API error | Shipping calculation failure |

### Logging Context

The plugin uses WooCommerce logging with context `'woo-envios-shipping'`:

```php
$logger = wc_get_logger();
$context = ['source' => 'woo-envios-shipping'];
$logger->debug('Message', $context);
$logger->info('Message', $context);
$logger->warning('Message', $context);
$logger->error('Message', $context);
```

## Update System

### GitHub Updater Integration

**Update Check URL:** `https://github.com/gustavofullstack/triqhub-shipping-radius`

**Metadata File:** `plugin-update.json` in repository root

**Example plugin-update.json:**
```json
{
  "name": "TriqHub: Shipping & Radius",
  "version": "1.0.0",
  "download_url": "https://github.com/gustavofullstack/triqhub-shipping-radius/releases/download/v1.0.0/triqhub-shipping-radius.zip",
  "requires": "6.2",
  "requires_php": "7.4",
  "tested": "6.5",
  "author": "GUSTAVO_EDC",
  "author_profile": "https://github.com/gustavofullstack",
  "last_updated": "2024-01-01",
  "sections": {
    "description": "Automatiza a coleta de coordenadas no checkout (CEP brasileiro) para integrar regras de frete por raio no WooCommerce.",
    "changelog": "Initial release with Google Maps integration"
  }
}
```

## Helper Functions

### Distance Calculation

```php
/**
 * Calculate Haversine distance between two coordinates
 * 
 * @param float $lat1 Origin latitude
 * @param float $lng1 Origin longitude
 * @param float $lat2 Destination latitude
 * @param float $lng2 Destination longitude
 * @return float Distance in kilometers
 */
function calculate_haversine_distance($lat1, $lng1, $lat2, $lng2): float {
    $earth_radius = 6371; // Earth's radius in kilometers
    
    $lat_diff = deg2rad($lat2 - $lat1);
    $lng_diff = deg2rad($lng2 - $lng1);
    
    $a = sin($lat_diff / 2) * sin($lat_diff / 2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($lng_diff / 2) * sin($lng_diff / 2);
    
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    
    return $earth_radius * $c;
}
```

### Price Calculation

```php
/**
 * Calculate final price with multipliers
 * 
 * @param float $base_price Base delivery price
 * @param float $distance_km Distance in kilometers
 * @param array $tiers Distance tiers configuration
 * @param float $weather_multiplier Weather adjustment
 * @param float $peak_multiplier Peak hours adjustment
 * @param float $weekend_multiplier Weekend adjustment
 * @return array [final_price, applied_multipliers]
 */
function calculate_final_price(
    float $base_price,
    float $distance_km,
    array $tiers,
    float $weather_multiplier = 1.0,
    float $peak_multiplier = 1.0,
    float $weekend_multiplier = 1.0
): array {
    // Implementation details
}
```

## Integration Examples

### Custom Shipping Method Registration

```php
add_filter('woocommerce_shipping_methods', function($methods) {
    // Add custom shipping method
    $methods['custom_shipping'] = 'Custom_Shipping_Method';
    return $methods;
});
```

### Extending Distance Calculation

```php
add_filter('woo_envios_distance_calculation', function($distance, $origin, $destination) {
    // Apply custom distance calculation logic
    return $custom_distance;
}, 10, 3);
```

### Custom Price Multipliers

```php
add_filter('woo_envios_price_multipliers', function($multipliers, $context) {
    // Add custom multiplier based on business logic
    $multipliers['custom'] = 1.1;
    return $multipliers;
}, 10, 2);
```

## Testing & Debugging

### Test Scripts Location

| Script | Purpose | Usage |
|--------|---------|-------|
| `test-superfrete.php` | Test SuperFrete API connection | `php test-superfrete.php` |
| `test-simulation.php` | Simulate shipping calculations | `php test-simulation.php` |
| `test-plugin-loading.php` | Test plugin loading sequence | `php test-plugin-loading.php` |
| `test-full-integration.php` | Full integration test | `php test-full-integration.php` |

### Debug Mode

Enable debug logging in `wp-config.php`:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

Plugin-specific debug messages