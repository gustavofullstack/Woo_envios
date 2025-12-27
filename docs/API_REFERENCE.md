# TriqHub Shipping & Radius - API Reference

## Overview

This document provides comprehensive technical documentation for all public APIs, WordPress hooks, filters, and internal methods available in the TriqHub Shipping & Radius plugin (version 1.2.7). The plugin extends WooCommerce with Brazilian CEP-based coordinate collection, radius-based shipping calculations, and Google Maps integration.

## Table of Contents

1. [WordPress Hooks & Filters](#wordpress-hooks--filters)
2. [Public Classes & Methods](#public-classes--methods)
3. [Shipping Methods API](#shipping-methods-api)
4. [Geocoding Services API](#geocoding-services-api)
5. [Weather Service API](#weather-service-api)
6. [Google Maps Integration API](#google-maps-integration-api)
7. [Logger Service API](#logger-service-api)
8. [Updater & Connectivity API](#updater--connectivity-api)
9. [Session & Cache Management](#session--cache-management)
10. [Internal API Routes](#internal-api-routes)

## WordPress Hooks & Filters

### Actions

#### `woocommerce_shipping_init`
**Priority:** Default (10)  
**Parameters:** None  
**Description:** Initializes shipping classes when WooCommerce is ready. Used by `Woo_Envios_Plugin::load_shipping_class()` to load the main shipping method class.

#### `plugins_loaded`
**Priority:** 20  
**Parameters:** None  
**Description:** Bootstraps the plugin after WooCommerce is loaded. Calls `woo_envios_bootstrap()` function.

#### `wp_enqueue_scripts`
**Priority:** Default (10)  
**Parameters:** None  
**Description:** Enqueues frontend styles on checkout and cart pages via `Woo_Envios_Plugin::enqueue_frontend_styles()`.

#### `admin_enqueue_scripts`
**Priority:** Default (10)  
**Parameters:** None  
**Description:** Enqueues TriqHub admin styling via `triqhub_enqueue_admin_Woo_envios()` function.

#### `admin_notices`
**Priority:** Default (10)  
**Parameters:** None  
**Description:** Displays admin warnings when WooCommerce is not active or version is incompatible.

#### `woocommerce_update_options_shipping_{$this->id}_{$this->instance_id}`
**Priority:** Default (10)  
**Parameters:** None  
**Description:** Processes admin options for shipping method instances. Hooked by `Woo_Envios_Shipping_Method::init()`.

#### `register_activation_hook`
**Priority:** Default (10)  
**Parameters:** 
- `WOO_ENVIOS_FILE` (string): Plugin main file path
- `array($this, 'activate')` (callable): Activation callback  
**Description:** Executes plugin activation tasks including cache table creation.

### Filters

#### `woocommerce_shipping_methods`
**Priority:** Default (10)  
**Parameters:** 
- `$methods` (array): Current shipping methods array  
**Returns:** `array` - Modified shipping methods array  
**Description:** Registers custom shipping methods (`woo_envios_radius` and `woo_envios_superfrete`) via `Woo_Envios_Plugin::register_shipping_method()`.

#### `woocommerce_package_rates`
**Priority:** 10  
**Parameters:** 
- `$rates` (array): Current shipping rates
- `$package` (array): WooCommerce package data  
**Returns:** `array` - Sorted shipping rates with Flash delivery on top  
**Description:** Sorts shipping rates to prioritize Flash delivery via `Woo_Envios_Plugin::sort_shipping_rates()`.

#### `puc_request_info_query_args-{$slug}`
**Priority:** Default (10)  
**Parameters:** 
- `$queryArgs` (array): Current query arguments  
**Returns:** `array` - Modified query arguments with license key  
**Description:** Injects license key into GitHub update requests for the plugin updater.

#### `pre_set_site_transient_update_plugins`
**Priority:** Default (10)  
**Parameters:** 
- `$transient` (object): Update transient object  
**Returns:** `object` - Modified transient with plugin update info  
**Description:** Checks for plugin updates via `Woo_Envios_Updater::check_update()`.

#### `plugins_api`
**Priority:** 10  
**Parameters:** 
- `$res` (mixed): Current plugin information
- `$action` (string): API action
- `$args` (object): API arguments  
**Returns:** `object` - Plugin information for "View Details" popup  
**Description:** Provides plugin information for update details via `Woo_Envios_Updater::check_info()`.

#### `upgrader_process_complete`
**Priority:** 10  
**Parameters:** 
- `$upgrader_object` (object): Upgrader instance
- `$options` (array): Upgrade options  
**Returns:** `void`  
**Description:** Tracks plugin updates via `Woo_Envios_Updater::track_update()`.

## Public Classes & Methods

### Woo_Envios_Plugin (Main Plugin Class)

#### `Woo_Envios_Plugin::instance(): Woo_Envios_Plugin`
**Returns:** `Woo_Envios_Plugin` - Singleton instance  
**Description:** Retrieves the singleton instance of the plugin.

#### `Woo_Envios_Plugin::register_shipping_method(array $methods): array`
**Parameters:** 
- `$methods` (array): Current shipping methods  
**Returns:** `array` - Modified shipping methods array  
**Description:** Registers custom shipping methods with WooCommerce.

#### `Woo_Envios_Plugin::sort_shipping_rates(array $rates, array $package): array`
**Parameters:** 
- `$rates` (array): Current shipping rates
- `$package` (array): WooCommerce package data  
**Returns:** `array` - Sorted shipping rates  
**Description:** Sorts shipping rates to display Flash delivery first.

#### `Woo_Envios_Plugin::enqueue_frontend_styles(): void`
**Returns:** `void`  
**Description:** Enqueues frontend CSS styles for checkout and cart pages.

#### `Woo_Envios_Plugin::activate(): void`
**Returns:** `void`  
**Description:** Plugin activation callback that creates necessary database tables.

### Woo_Envios_Shipping_Method

#### `Woo_Envios_Shipping_Method::__construct($instance_id = 0)`
**Parameters:** 
- `$instance_id` (int): Shipping method instance ID (default: 0)  
**Description:** Constructor for the radius-based shipping method.

#### `Woo_Envios_Shipping_Method::init(): void`
**Returns:** `void`  
**Description:** Initializes form fields and hooks for the shipping method.

#### `Woo_Envios_Shipping_Method::calculate_shipping(array $package = []): void`
**Parameters:** 
- `$package` (array): WooCommerce package data (default: empty array)  
**Returns:** `void`  
**Description:** Main shipping calculation method that computes distance-based rates.

#### `Woo_Envios_Shipping_Method::calculate_correios_shipping(array $package): void`
**Parameters:** 
- `$package` (array): WooCommerce package data  
**Returns:** `void`  
**Description:** Calculates Correios shipping rates for destinations outside local radius.

#### `Woo_Envios_Shipping_Method::get_session_coordinates(string $signature): ?array`
**Parameters:** 
- `$signature` (string): Destination signature for session validation  
**Returns:** `?array` - Customer coordinates or null  
**Description:** Retrieves customer coordinates from WooCommerce session.

#### `Woo_Envios_Shipping_Method::calculate_route_distance(array $store_coords, array $customer_coords, array $package): array|WP_Error`
**Parameters:** 
- `$store_coords` (array): Store coordinates with 'lat' and 'lng'
- `$customer_coords` (array): Customer coordinates with 'lat' and 'lng'
- `$package` (array): WooCommerce package data  
**Returns:** `array|WP_Error` - Distance data or error  
**Description:** Calculates route distance using Google Distance Matrix API.

#### `Woo_Envios_Shipping_Method::calculate_distance(float $lat_from, float $lng_from, float $lat_to, float $lng_to): float`
**Parameters:** 
- `$lat_from` (float): Origin latitude
- `$lng_from` (float): Origin longitude
- `$lat_to` (float): Destination latitude
- `$lng_to` (float): Destination longitude  
**Returns:** `float` - Distance in kilometers  
**Description:** Calculates Haversine distance as fallback when Google API fails.

#### `Woo_Envios_Shipping_Method::build_destination_signature(array $package): string`
**Parameters:** 
- `$package` (array): WooCommerce package data  
**Returns:** `string` - MD5 signature  
**Description:** Creates unique signature for destination address validation.

#### `Woo_Envios_Shipping_Method::calculate_dynamic_multiplier(array $package): array`
**Parameters:** 
- `$package` (array): Package data  
**Returns:** `array` - Multiplier data with 'total' and 'reasons'  
**Description:** Calculates dynamic pricing multipliers based on time, weather, and other factors.

#### `Woo_Envios_Shipping_Method::get_peak_hour_multiplier(): array`
**Returns:** `array` - Peak hour multiplier data  
**Description:** Checks if current time is within configured peak hours.

#### `Woo_Envios_Shipping_Method::get_weather_multiplier(array $package): float`
**Parameters:** 
- `$package` (array): Package data  
**Returns:** `float` - Weather multiplier  
**Description:** Retrieves weather-based multiplier for dynamic pricing.

#### `Woo_Envios_Shipping_Method::is_weekend(): bool`
**Returns:** `bool` - True if current day is weekend  
**Description:** Checks if current day is Saturday or Sunday.

### Woo_Envios_Admin

#### `Woo_Envios_Admin::get_store_coordinates(): array`
**Returns:** `array` - Store coordinates with 'lat' and 'lng'  
**Description:** Retrieves configured store coordinates from plugin settings.

#### `Woo_Envios_Admin::match_tier_by_distance(float $distance): ?array`
**Parameters:** 
- `$distance` (float): Distance in kilometers  
**Returns:** `?array` - Matching tier configuration or null  
**Description:** Matches distance to configured shipping tiers.

### Woo_Envios_Checkout

#### `Woo_Envios_Checkout::__construct()`
**Description:** Constructor that initializes checkout integration hooks.

#### `Woo_Envios_Checkout::enqueue_checkout_scripts(): void`
**Returns:** `void`  
**Description:** Enqueues JavaScript for checkout address validation and geocoding.

#### `Woo_Envios_Checkout::validate_checkout_address(array $data, WP_Error $errors): void`
**Parameters:** 
- `$data` (array): Checkout form data
- `$errors` (WP_Error): Validation errors object  
**Returns:** `void`  
**Description:** Validates checkout address and performs geocoding.

## Shipping Methods API

### Radius-Based Shipping (`woo_envios_radius`)

**Class:** `Woo_Envios_Shipping_Method`  
**Description:** Local delivery method based on straight-line distance from store coordinates.

**Configuration Options:**
- `enabled` (checkbox): Enable/disable method
- `title` (text): Display title for customers (default: "Entrega Flash")

**Rate Metadata:**
- `distance` (float): Calculated distance in km
- `base_price` (float): Base price before multipliers
- `multiplier` (float): Total dynamic multiplier applied
- `breakdown` (array): List of multiplier reasons
- `debug_info` (array): Debug information including coordinates

### SuperFrete/Correios Shipping (`woo_envios_superfrete`)

**Class:** `Woo_Envios\Services\Woo_Envios_Superfrete_Shipping_Method`  
**Description:** Brazilian postal service integration for destinations outside local radius.

**Supported Services:**
- PAC (Postal Package)
- SEDEX (Express Delivery)
- Mini (Small Package)

**Rate Metadata:**
- `service_code` (string): Correios service code
- `deadline` (int): Estimated delivery days
- `method` (string): Always "correios"

## Geocoding Services API

### \Woo_Envios\Services\Geocoder

#### `Geocoder::geocode(string $address): ?array`
**Parameters:** 
- `$address` (string): Full address string  
**Returns:** `?array` - Coordinates array with 'lat' and 'lng' or null  
**Description:** Primary geocoding method that converts addresses to coordinates.

#### `Geocoder::reverse_geocode(float $lat, float $lng): ?array`
**Parameters:** 
- `$lat` (float): Latitude
- `$lng` (float): Longitude  
**Returns:** `?array` - Address components or null  
**Description:** Reverse geocoding from coordinates to address.

#### `Geocoder::get_cache_key(string $input): string`
**Parameters:** 
- `$input` (string): Geocoding input  
**Returns:** `string` - Cache key  
**Description:** Generates cache key for geocoding results.

#### `Geocoder::get_cached_result(string $cache_key): ?array`
**Parameters:** 
- `$cache_key` (string): Cache key  
**Returns:** `?array` - Cached result or null  
**Description:** Retrieves cached geocoding result.

#### `Geocoder::cache_result(string $cache_key, array $result): void`
**Parameters:** 
- `$cache_key` (string): Cache key
- `$result` (array): Geocoding result  
**Returns:** `void`  
**Description:** Caches geocoding result in database.

### Woo_Envios_Correios

#### `Woo_Envios_Correios::__construct()`
**Description:** Constructor for Correios shipping service integration.

#### `Woo_Envios_Correios::is_enabled(): bool`
**Returns:** `bool` - True if Correios service is enabled  
**Description:** Checks if Correios shipping is configured and enabled.

#### `Woo_Envios_Correios::calculate(array $package): ?array`
**Parameters:** 
- `$package` (array): WooCommerce package data  
**Returns:** `?array` - Array of shipping rates or null  
**Description:** Calculates Correios shipping rates for package.

#### `Woo_Envios_Correios::validate_cep(string $cep): bool`
**Parameters:** 
- `$cep` (string): Brazilian postal code  
**Returns:** `bool` - True if CEP is valid  
**Description:** Validates Brazilian postal code format.

#### `Woo_Envios_Correios::get_service_name(string $code): string`
**Parameters:** 
- `$code` (string): Correios service code  
**Returns:** `string` - Human-readable service name  
**Description:** Converts service code to display name.

## Weather Service API

### Woo_Envios_Weather

#### `Woo_Envios_Weather::get_weather_multiplier(float $lat, float $lng): float`
**Parameters:** 
- `$lat` (float): Latitude
- `$lng` (float): Longitude  
**Returns:** `float` - Weather multiplier (1.0-1.5)  
**Description:** Retrieves weather-based price multiplier from OpenWeather API.

#### `Woo_Envios_Weather::get_current_weather(float $lat, float $lng, string $api_key): ?array`
**Parameters:** 
- `$lat` (float): Latitude
- `$lng` (float): Longitude
- `$api_key` (string): OpenWeather API key  
**Returns:** `?array` - Weather data or null  
**Description:** Fetches current weather data from OpenWeather API.

#### `Woo_Envios_Weather::calculate_rain_multiplier(array $weather_data): float`
**Parameters:** 
- `$weather_data` (array): Weather data from API  
**Returns:** `float` - Rain intensity multiplier  
**Description:** Calculates multiplier based on rain conditions.

#### `Woo_Envios_Weather::get_weather_description(array $weather_data): string`
**Parameters:** 
- `$weather_data` (array): Weather data from API  
**Returns:** `string` - Human-readable weather description  
**Description:** Extracts weather description from API response.

#### `Woo_Envios_Weather::clear_cache(): void`
**Returns:** `void`  
**Description:** Clears all weather cache transients.

## Google Maps Integration API

### Woo_Envios_Google_Maps

#### `Woo_Envios_Google_Maps::is_configured(): bool`
**Returns:** `bool` - True if Google Maps API is properly configured  
**Description:** Validates API key and configuration.

#### `Woo_Envios_Google_Maps::geocode(string $address): ?array`
**Parameters:** 
- `$address` (string): Address to geocode  
**Returns:** `?array` - Coordinates array or null  
**Description:** Converts address to coordinates using Google Geocoding API.

#### `Woo_Envios_Google_Maps::reverse_geocode(float $lat, float $lng): ?array`
**Parameters:** 
- `$lat` (float): Latitude
- `$lng` (float): Longitude  
**Returns:** `?array` - Address components or null  
**Description:** Converts coordinates to address using Google Geocoding API.

#### `Woo_Envios_Google_Maps::autocomplete(string $input, string $country = 'BR'): ?array`
**Parameters:** 
- `$input` (string): Partial address input
- `$country` (string): Country code restriction (default: 'BR')  
**Returns:**