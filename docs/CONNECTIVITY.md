# CONNECTIVITY.md - TriqHub Shipping & Radius Plugin

## Overview

The TriqHub Shipping & Radius plugin implements a sophisticated multi-layered connectivity architecture that integrates with external APIs while maintaining robust error handling and fallback mechanisms. This document details the connectivity patterns, API integrations, network strategies, and error recovery systems.

## External API Integrations

### 1. Google Maps API Integration

#### Configuration
- **API Key Storage**: Encrypted storage in WordPress options table (`woo_envios_google_maps_api_key`)
- **Required APIs**: Geocoding, Places Autocomplete, Distance Matrix
- **Rate Limits**: 40 requests per second, 25,000 requests per day (free tier)

#### Endpoints Used
```php
protected $api_urls = array(
    'geocode'        => 'https://maps.googleapis.com/maps/api/geocode/json',
    'places'         => 'https://maps.googleapis.com/maps/api/place/autocomplete/json',
    'place_details'  => 'https://maps.googleapis.com/maps/api/place/details/json',
    'distance'       => 'https://maps.googleapis.com/maps/api/distancematrix/json',
);
```

#### Request Parameters
```php
$params = array(
    'address'    => $address,           // For geocoding
    'components' => 'country:BR',       // Brazil restriction
    'key'        => $this->api_key,
    'language'   => 'pt-BR',            // Portuguese localization
    'region'     => 'br'                // Brazil region bias
);
```

#### Response Structure
```json
{
    "status": "OK",
    "results": [{
        "geometry": {
            "location": {
                "lat": -23.550520,
                "lng": -46.633308
            }
        },
        "formatted_address": "São Paulo, SP, Brazil"
    }]
}
```

### 2. TriqHub License API Integration

#### Authentication Flow
```php
// In TriqHub_Connector class
private function authenticate() {
    $response = wp_remote_post('https://api.triqhub.com/v1/license/validate', array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $this->license_key,
            'Content-Type'  => 'application/json',
            'X-Plugin-ID'   => $this->plugin_id
        ),
        'body' => json_encode(array(
            'site_url'    => home_url(),
            'plugin_ver'  => $this->get_plugin_version(),
            'php_version' => PHP_VERSION
        )),
        'timeout' => 15
    ));
}
```

#### License Validation Payload
```json
{
    "license_key": "TRQ-INVISIBLE-KEY",
    "site_url": "https://example.com",
    "plugin_id": "triqhub-shipping-radius",
    "timestamp": "2024-01-15T10:30:00Z",
    "signature": "hmac_sha256_signature"
}
```

### 3. OpenWeather API Integration

#### Weather Data Retrieval
```php
private const API_URL = 'https://api.openweathermap.org/data/2.5/weather';

private function get_current_weather(float $lat, float $lng, string $api_key): ?array {
    $url = add_query_arg(array(
        'lat'   => $lat,
        'lon'   => $lng,
        'appid' => $api_key,
        'units' => 'metric',
        'lang'  => 'pt_br',
    ), self::API_URL);
}
```

#### Weather Cache Strategy
- **Cache Duration**: 1 hour (3600 seconds)
- **Cache Key**: `woo_envios_weather_{md5(lat|lng)}`
- **Storage**: WordPress transients API

### 4. Correios/SuperFrete API Integration

#### Shipping Calculation
```php
class Woo_Envios_Correios {
    private const API_ENDPOINT = 'https://api.superfrete.com/api/v0/calculator';
    
    public function calculate(array $package): ?array {
        $payload = array(
            'from' => array(
                'postal_code' => $this->get_store_postcode()
            ),
            'to' => array(
                'postal_code' => $package['destination']['postcode']
            ),
            'package' => $this->build_package_dimensions($package),
            'services' => array('1', '2', '17') // PAC, SEDEX, Mini
        );
    }
}
```

## Webhook Structures

### 1. Plugin Update Webhooks

#### GitHub Update Checker
```php
// Plugin Update Checker integration
$myUpdateChecker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
    'https://github.com/gustavofullstack/triqhub-shipping-radius',
    __FILE__,
    'triqhub-shipping-radius'
);

// License injection into update requests
add_filter('puc_request_info_query_args-' . $myUpdateChecker->slug, function($queryArgs) {
    $license_key = get_option('triqhub_license_key');
    if (!empty($license_key)) {
        $queryArgs['license_key'] = $license_key;
        $queryArgs['site_url'] = home_url();
    }
    return $queryArgs;
});
```

### 2. Error Reporting Webhooks

#### Admin Notification System
```php
private static function notify_admin_api_failure(int $failures): void {
    $admin_email = get_option('admin_email');
    $subject = 'Woo Envios: Falhas na API do Google Maps';
    $message = sprintf(
        "O plugin Woo Envios detectou %d falhas consecutivas na API do Google Maps.\n\n" .
        "O sistema entrou em modo de proteção (circuit breaker)...",
        $failures
    );
    wp_mail($admin_email, $subject, $message);
}
```

## Network Timeout Configuration

### Request Timeout Strategies

#### 1. Primary API Timeouts
```php
const REQUEST_TIMEOUT = 10; // Google Maps API timeout
const MAX_RETRIES = 3;      // Retry attempts for transient failures

$response = wp_remote_get($url, array(
    'timeout'     => self::REQUEST_TIMEOUT,
    'redirection' => 2,
    'httpversion' => '1.1',
    'user-agent'  => 'WooEnvios/' . self::VERSION,
    'blocking'    => true,
    'sslverify'   => true, // Verify SSL certificates
    'headers'     => array(
        'Accept' => 'application/json'
    )
));
```

#### 2. Progressive Backoff Strategy
```php
private function make_api_request_with_retry(string $url, array $args = array()): array {
    $retry_delays = array(1, 3, 5); // Seconds between retries
    
    for ($attempt = 0; $attempt < self::MAX_RETRIES; $attempt++) {
        if ($attempt > 0) {
            sleep($retry_delays[$attempt - 1]);
        }
        
        $response = wp_remote_get($url, $args);
        
        if (!is_wp_error($response)) {
            $status_code = wp_remote_retrieve_response_code($response);
            
            if ($status_code === 200) {
                return $response; // Success
            } elseif ($status_code === 429) { // Rate limited
                $retry_after = wp_remote_retrieve_header($response, 'Retry-After');
                if ($retry_after) {
                    sleep((int)$retry_after);
                    continue;
                }
            }
        }
    }
    
    return new WP_Error('api_request_failed', 'All retry attempts failed');
}
```

#### 3. Async Request Timeouts
```php
// For non-critical operations (weather, logging)
add_action('wp_ajax_nopriv_woo_envios_async_geocode', function() {
    set_time_limit(30); // Extend timeout for async operations
    // Async geocoding logic
});
```

## Error Handling Strategies

### 1. Circuit Breaker Pattern

#### Implementation
```php
class Woo_Envios_Google_Maps {
    private const MAX_CONSECUTIVE_FAILURES = 5;
    
    private function is_circuit_open(): bool {
        $failures = get_transient('woo_envios_api_failures');
        return $failures && $failures >= self::MAX_CONSECUTIVE_FAILURES;
    }
    
    private function record_failure(): void {
        $failures = get_transient('woo_envios_api_failures') ?: 0;
        $failures++;
        set_transient('woo_envios_api_failures', $failures, 3600);
        
        if ($failures >= self::MAX_CONSECUTIVE_FAILURES) {
            Woo_Envios_Logger::circuit_breaker_opened($failures);
        }
    }
    
    private function record_success(): void {
        // Reset failure count on successful request
        delete_transient('woo_envios_api_failures');
    }
}
```

### 2. Graceful Degradation

#### Fallback Mechanisms
```php
public function calculate_shipping($package = array()): void {
    // Primary: Google Distance Matrix API
    $distance_data = $this->calculate_route_distance($store_coords, $session_coords, $package);
    
    if (is_wp_error($distance_data) || empty($distance_data)) {
        // Fallback 1: Haversine formula (straight-line distance)
        $logger->debug('Distance Matrix failed, using Haversine fallback');
        $distance = $this->calculate_distance(
            (float)$store_coords['lat'],
            (float)$store_coords['lng'],
            (float)$session_coords['lat'],
            (float)$session_coords['lng']
        );
        
        if (!$distance) {
            // Fallback 2: Static distance tiers based on postal code zones
            $distance = $this->estimate_distance_by_postcode(
                $package['destination']['postcode']
            );
        }
    }
}
```

### 3. Geocoding Fallback Chain
```php
private function get_session_coordinates(string $signature): ?array {
    // 1. Check session cache
    $coords = WC()->session->get('woo_envios_coords');
    
    if (empty($coords['lat']) || empty($coords['lng'])) {
        // 2. Server-side geocoding fallback
        $fallback_coords = \Woo_Envios\Services\Geocoder::geocode($full_address);
        
        if ($fallback_coords) {
            // 3. Save to session for future requests
            WC()->session->set('woo_envios_coords', array(
                'lat'       => $fallback_coords['lat'],
                'lng'       => $fallback_coords['lng'],
                'signature' => $signature,
            ));
            return $fallback_coords;
        }
        
        // 4. Ultimate fallback: Default coordinates
        return array(
            'lat' => WOO_ENVIOS_DEFAULT_LAT,
            'lng' => WOO_ENVIOS_DEFAULT_LNG
        );
    }
    
    return $coords;
}
```

### 4. Error Classification System

#### Error Categories
```php
class Woo_Envios_Error_Types {
    const NETWORK_ERROR = 1;      // Connection failures, timeouts
    const API_ERROR = 2;          // API-specific errors (rate limits, invalid keys)
    const DATA_ERROR = 3;         // Invalid response data
    const CONFIG_ERROR = 4;       // Missing configuration
    const BUSINESS_ERROR = 5;     // Business logic errors (out of range, etc.)
}

public function handle_error(WP_Error $error, int $error_type): void {
    switch ($error_type) {
        case Woo_Envios_Error_Types::NETWORK_ERROR:
            $this->handle_network_error($error);
            break;
        case Woo_Envios_Error_Types::API_ERROR:
            $this->handle_api_error($error);
            break;
        // ... other error types
    }
}
```

## Cache Strategies

### 1. Geocode Cache Table
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

### 2. Cache Invalidation Rules
```php
private function get_cached_geocode(string $address): ?array {
    global $wpdb;
    
    $cache_key = md5($address);
    $table_name = $wpdb->prefix . 'woo_envios_geocode_cache';
    
    $result = $wpdb->get_row($wpdb->prepare(
        "SELECT result_data FROM {$table_name} 
         WHERE cache_key = %s AND expires_at > %s",
        $cache_key,
        current_time('mysql')
    ));
    
    if ($result) {
        return json_decode($result->result_data, true);
    }
    
    return null;
}
```

### 3. Cache Warming Strategy
```php
public function warm_cache_for_popular_areas(): void {
    $popular_postcodes = get_option('woo_envios_popular_postcodes', array());
    
    foreach ($popular_postcodes as $postcode) {
        $address = $this->build_address_from_postcode($postcode);
        $coords = $this->geocode($address);
        
        if ($coords) {
            $this->cache_geocode($address, $coords);
        }
    }
}
```

## Security Measures

### 1. API Key Protection
```php
private function get_api_key(): string {
    $api_key = get_option(self::API_KEY_OPTION, '');
    
    // Basic obfuscation for logs
    if (defined('WP_DEBUG') && WP_DEBUG) {
        return $api_key;
    }
    
    // In production, mask the key in logs
    return substr($api_key, 0, 8) . '...' . substr($api_key, -4);
}
```

### 2. Request Signing
```php
private function sign_request(array $data): string {
    $api_secret = get_option('woo_envios_api_secret');
    $payload = json_encode($data);
    $timestamp = time();
    
    $signature = hash_hmac('sha256', $timestamp . $payload, $api_secret);
    
    return base64_encode(json_encode(array(
        'signature' => $signature,
        'timestamp' => $timestamp,
        'payload'   => $payload
    )));
}
```

### 3. Input Validation
```php
private function validate_coordinates(float $lat, float $lng): bool {
    // Brazil coordinate bounds
    $brazil_bounds = array(
        'min_lat' => -33.75,
        'max_lat' => 5.27,
        'min_lng' => -73.99,
        'max_lng' => -34.79
    );
    
    return ($lat >= $brazil_bounds['min_lat'] && $lat <= $brazil_bounds['max_lat']) &&
           ($lng >= $brazil_bounds['min_lng'] && $lng <= $brazil_bounds['max_lng']);
}
```

## Monitoring & Analytics

### 1. Performance Metrics
```php
class Woo_Envios_Metrics {
    private static function record_api_latency(string $api_name, float $latency): void {
        $metrics = get_option('woo_envios_api_metrics', array());
        
        if (!isset($metrics[$api_name])) {
            $metrics[$api_name] = array(
                'count' => 0,
                'total_latency' => 0,
                'avg_latency' => 0
            );
        }
        
        $metrics[$api_name]['count']++;
        $metrics[$api_name]['total_latency'] += $latency;
        $metrics[$api_name]['avg_latency'] = 
            $metrics[$api_name]['total_latency'] / $metrics[$api_name]['count'];
        
        update_option('woo_envios_api_metrics', $metrics, false);
    }
}
```

### 2. Health Check Endpoint
```php
add_action('wp_ajax_woo_envios_health_check', function() {
    $health_status = array(
        'google_maps' => array(
            'configured' => $google_maps->is_configured(),
            'circuit_status' => $google_maps->get_circuit_status(),
            'last_success' => get_transient('woo_envios_last_success')
        ),
        'database' => array(
            'cache_table_exists' => $this->check_cache_table(),
            'cache_size' => $this->get_cache_size()
        ),
        'woocommerce' => array(
            'version' => WC_VERSION,
            'shipping_zones' => $this->get_shipping_zones_count()
        )
    );
    
    wp_send_json_success($health_status);
});
```

## Integration Flow Diagram

```mermaid
sequenceDiagram
    participant Customer as Customer (Browser)
    participant WC as WooCommerce
    participant Plugin as TriqHub Plugin
    participant Session as PHP Session
    participant Cache as Geocode Cache
    participant GMaps as Google Maps API
    participant Weather as OpenWeather API
    participant Correios as Correios API
    participant TriqHub as TriqHub License API

    Note over Customer,