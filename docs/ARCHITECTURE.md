# TriqHub Shipping & Radius - Architecture Documentation

## System Overview

TriqHub Shipping & Radius is a sophisticated WooCommerce extension that provides intelligent shipping calculations based on geographical radius, real-time weather conditions, and dynamic pricing. The system integrates multiple external APIs (Google Maps, OpenWeather, Correios/SuperFrete) to deliver precise shipping rates for Brazilian e-commerce operations.

### Core Architecture Principles

1. **Modular Design**: Separation of concerns with dedicated service classes
2. **Fault Tolerance**: Circuit breaker patterns and graceful degradation
3. **Performance Optimization**: Caching strategies at multiple levels
4. **Extensibility**: Hook-based architecture for third-party integrations
5. **Real-time Processing**: Dynamic pricing based on external conditions

## System Architecture Diagram

```mermaid
graph TB
    subgraph "WordPress Environment"
        WP[WordPress Core]
        WC[WooCommerce]
    end
    
    subgraph "TriqHub Shipping Plugin"
        TP[TriqHub_Shipping_Plugin<br/>Main Controller]
        
        subgraph "Core Services"
            GM[Google Maps Service]
            GC[Geocoder Service]
            WS[Weather Service]
            LOG[Logger Service]
            CONN[TriqHub Connector]
        end
        
        subgraph "Shipping Methods"
            RAD[Radius Shipping Method]
            COR[Correios/SuperFrete]
        end
        
        subgraph "Admin Interface"
            ADMIN[Admin Settings]
            GMAP[Google Maps Admin]
        end
        
        subgraph "Frontend Components"
            CHECK[Checkout Integration]
            ASSETS[Frontend Assets]
        end
        
        subgraph "Data Layer"
            CACHE[Geocode Cache Table]
            OPT[WordPress Options]
            SESS[WooCommerce Session]
        end
    end
    
    subgraph "External APIs"
        GOOGLE[Google Maps API]
        WEATHER[OpenWeather API]
        CORREIOS[Correios API]
        TRIQHUB[TriqHub License API]
    end
    
    %% Data Flow
    WP --> TP
    WC --> TP
    
    TP --> GM
    TP --> GC
    TP --> WS
    TP --> LOG
    TP --> CONN
    
    TP --> RAD
    TP --> COR
    TP --> ADMIN
    TP --> GMAP
    TP --> CHECK
    TP --> ASSETS
    
    GM --> GOOGLE
    WS --> WEATHER
    COR --> CORREIOS
    CONN --> TRIQHUB
    
    GM --> CACHE
    GC --> CACHE
    ADMIN --> OPT
    CHECK --> SESS
    RAD --> SESS
    
    %% Internal Dependencies
    RAD --> GM
    RAD --> WS
    COR --> GC
    ADMIN --> GM
    GMAP --> GM
    
    style TP fill:#e1f5fe
    style GM fill:#f3e5f5
    style RAD fill:#e8f5e8
    style COR fill:#fff3e0
    style GOOGLE fill:#ffebee
    style WEATHER fill:#e8f5e8
```

## Core Module Architecture

### 1. Main Plugin Controller (`TriqHub_Shipping_Plugin`)

**Responsibilities**:
- Singleton pattern for single instance management
- Plugin lifecycle management (activation/deactivation)
- Dependency loading and initialization order
- Hook registration and event handling
- Self-healing mechanisms (cache table creation)

**Key Methods**:
- `instance()`: Singleton accessor
- `define_constants()`: Defines plugin constants
- `include_files()`: Loads dependencies in correct order
- `load_components()`: Initializes service instances
- `register_shipping_method()`: Registers shipping methods with WooCommerce
- `sort_shipping_rates()`: Prioritizes Flash delivery over other methods

**Dependencies**:
- WordPress Core Functions
- WooCommerce Shipping Methods API
- All internal service classes

### 2. Google Maps Service (`Woo_Envios_Google_Maps`)

**Responsibilities**:
- Google Maps API integration management
- Geocoding (address to coordinates conversion)
- Distance Matrix calculations (route-based distances)
- Circuit breaker implementation for API failure handling
- Response caching and TTL management

**API Endpoints Managed**:
- Geocoding API: `https://maps.googleapis.com/maps/api/geocode/json`
- Places Autocomplete: `https://maps.googleapis.com/maps/api/place/autocomplete/json`
- Place Details: `https://maps.googleapis.com/maps/api/place/details/json`
- Distance Matrix: `https://maps.googleapis.com/maps/api/distancematrix/json`

**Circuit Breaker Pattern**:
```php
private function check_circuit_breaker(): bool
{
    $failures = get_transient('woo_envios_google_maps_failures') ?: 0;
    $last_failure = get_transient('woo_envios_google_maps_last_failure');
    
    if ($failures >= self::MAX_CONSECUTIVE_FAILURES) {
        // Circuit is open - check if cooldown period has passed
        if (time() - $last_failure < 300) { // 5 minute cooldown
            return false; // Circuit is open
        }
        // Cooldown passed, reset circuit
        $this->reset_circuit_breaker();
    }
    
    return true; // Circuit is closed, allow requests
}
```

### 3. Shipping Method (`Woo_Envios_Shipping_Method`)

**Responsibilities**:
- Shipping rate calculation based on distance
- Dynamic pricing with multiple multipliers
- Session-based coordinate management
- Fallback mechanisms for geocoding failures
- Integration with Correios/SuperFrete for out-of-range deliveries

**Calculation Flow**:
```mermaid
sequenceDiagram
    participant WC as WooCommerce
    participant SM as Shipping Method
    participant GM as Google Maps
    participant WS as Weather Service
    participant SESS as Session
    participant COR as Correios
    
    WC->>SM: calculate_shipping()
    SM->>SESS: get_session_coordinates()
    alt Coordinates in Session
        SESS-->>SM: Return coordinates
    else No Coordinates
        SM->>GM: geocode_address()
        GM-->>SM: Return coordinates
        SM->>SESS: store_coordinates()
    end
    
    SM->>GM: calculate_route_distance()
    GM-->>SM: Return distance
    
    SM->>SM: match_tier_by_distance()
    
    par Dynamic Pricing
        SM->>SM: get_peak_hour_multiplier()
        SM->>WS: get_weather_multiplier()
        SM->>SM: check_weekend_multiplier()
    end
    
    SM->>SM: apply_max_multiplier_limit()
    
    alt Within Delivery Radius
        SM->>WC: add_rate(Flash Delivery)
    else Outside Radius
        SM->>COR: calculate_shipping()
        COR-->>SM: Return Correios rates
        SM->>WC: add_rate(Correios only)
    end
```

### 4. Weather Service (`Woo_Envios_Weather`)

**Responsibilities**:
- OpenWeather API integration
- Rain detection and intensity classification
- Weather-based pricing multipliers
- Response caching (1-hour TTL)
- Cache invalidation and cleanup

**Multiplier Logic**:
- No rain: 1.0x (no adjustment)
- Light rain/drizzle: 1.2x (configurable)
- Heavy rain (>5mm/h): 1.5x (configurable)
- Thunderstorm: 1.5x (configurable)

### 5. Logger Service (`Woo_Envios_Logger`)

**Responsibilities**:
- Structured logging with rotation (7-day retention)
- Log level management (info, warning, error)
- Admin notification system for critical failures
- API failure tracking and circuit breaker integration
- Shipping calculation audit trails

**Log Structure**:
```
[2024-01-15 14:30:45] [INFO] FRETE CALCULADO | Distância: 5.2 km | Base: R$ 15.00 | Final: R$ 18.00 | Multiplicadores: Pico +20%, Chuva +20%
```

### 6. Geocoder Service (`Woo_Envios\Services\Geocoder`)

**Responsibilities**:
- Address normalization and validation
- Multi-source geocoding (primary: Google Maps, fallback: manual)
- Cache management with database table
- Coordinate validation and error handling

**Cache Table Schema**:
```sql
CREATE TABLE wp_woo_envios_geocode_cache (
    id BIGINT UNSIGNED AUTO_INCREMENT,
    cache_key VARCHAR(64) NOT NULL,
    result_data LONGTEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY cache_key (cache_key),
    KEY expires_at (expires_at)
);
```

## Data Flow Architecture

### 1. Checkout Process Flow

```mermaid
sequenceDiagram
    participant C as Customer
    participant FE as Frontend (JS)
    participant BE as Backend (PHP)
    participant GM as Google Maps
    participant SESS as Session
    participant WC as WooCommerce
    
    C->>FE: Enters address in checkout
    FE->>GM: Places Autocomplete API
    GM-->>FE: Returns address suggestions
    C->>FE: Selects address
    FE->>GM: Geocoding API
    GM-->>FE: Returns coordinates
    FE->>BE: AJAX: save_coordinates()
    BE->>SESS: Store coordinates with signature
    BE-->>FE: Success response
    
    C->>WC: Proceeds to shipping calculation
    WC->>BE: calculate_shipping()
    BE->>SESS: Retrieve coordinates
    BE->>GM: Distance Matrix API
    GM-->>BE: Returns route distance
    BE->>BE: Calculate dynamic pricing
    BE->>WC: Return shipping rates
    WC-->>C: Display shipping options
```

### 2. Admin Configuration Flow

```mermaid
sequenceDiagram
    participant A as Admin User
    participant WP as WordPress Admin
    participant PL as Plugin Admin
    participant GM as Google Maps Admin
    participant DB as Database
    participant API as External APIs
    
    A->>WP: Accesses WooCommerce → Woo Envios
    WP->>PL: Load admin settings page
    PL->>DB: Load existing configuration
    DB-->>PL: Return settings
    PL-->>A: Display settings form
    
    A->>PL: Updates Google Maps API key
    PL->>GM: Validate API key
    GM->>API: Test API connection
    API-->>GM: Return validation result
    GM-->>PL: Validation status
    PL->>DB: Save validated key
    DB-->>PL: Confirmation
    
    A->>PL: Configures delivery tiers
    PL->>DB: Save tier configuration
    A->>PL: Configures dynamic pricing rules
    PL->>DB: Save pricing rules
```

## Class Hierarchy and Relationships

```mermaid
classDiagram
    class TriqHub_Shipping_Plugin {
        -static $instance
        +VERSION
        +instance() TriqHub_Shipping_Plugin
        -define_constants() void
        -include_files() void
        -load_components() void
        -register_hooks() void
        +register_shipping_method(array) array
        +sort_shipping_rates(array, array) array
        -create_google_cache_table() void
    }
    
    class Woo_Envios_Shipping_Method {
        -id
        -method_title
        -method_description
        +__construct(int)
        +init() void
        +calculate_shipping(array) void
        -get_session_coordinates(string) array|null
        -calculate_distance(float, float, float, float) float
        -calculate_route_distance(array, array, array) array|WP_Error
        -calculate_dynamic_multiplier(array) array
        -get_peak_hour_multiplier() array
        -get_weather_multiplier(array) float
    }
    
    class Woo_Envios_Google_Maps {
        -api_key
        -cache_ttl
        -api_urls
        +__construct()
        +is_configured() bool
        -get_api_key() string
        -validate_api_key_format(string) bool
        +geocode(string) array|WP_Error
        +calculate_distance(string, string) array|WP_Error
        -make_api_request(string, array) array|WP_Error
        -check_circuit_breaker() bool
        -reset_circuit_breaker() void
    }
    
    class Woo_Envios_Weather {
        -API_URL
        -CACHE_DURATION
        +get_weather_multiplier(float, float) float
        -get_current_weather(float, float, string) array|null
        -calculate_rain_multiplier(array) float
        +get_weather_description(array) string
        +clear_cache() void
    }
    
    class Woo_Envios_Logger {
        -static is_enabled() bool
        -static log(string, string) void
        +static shipping_calculated(float, float, float, array, string, array, array) void
        +static error(string) void
        +static info(string) void
        +static warning(string) void
        +static api_failure(string, string) void
        +static circuit_breaker_opened(int) void
        +static cleanup_old_logs() void
    }
    
    class Woo_Envios_Admin {
        +static get_store_coordinates() array
        +static match_tier_by_distance(float) array|false
        +static get_delivery_tiers() array
        +static save_settings(array) bool
    }
    
    class Woo_Envios_Checkout {
        +__construct()
        +enqueue_scripts() void
        +save_coordinates_ajax() void
        +validate_checkout_fields(array, WP_Error) void
    }
    
    class TriqHub_Connector {
        -license_key
        -plugin_slug
        +__construct(string, string)
        -validate_license() bool
        -send_telemetry(array) void
        -check_for_updates() void
    }
    
    %% Relationships
    TriqHub_Shipping_Plugin --> Woo_Envios_Shipping_Method : creates
    TriqHub_Shipping_Plugin --> Woo_Envios_Google_Maps : creates
    TriqHub_Shipping_Plugin --> Woo_Envios_Weather : creates
    TriqHub_Shipping_Plugin --> Woo_Envios_Logger : creates
    TriqHub_Shipping_Plugin --> Woo_Envios_Admin : creates
    TriqHub_Shipping_Plugin --> Woo_Envios_Checkout : creates
    TriqHub_Shipping_Plugin --> TriqHub_Connector : creates
    
    Woo_Envios_Shipping_Method --> Woo_Envios_Google_Maps : uses
    Woo_Envios_Shipping_Method --> Woo_Envios_Weather : uses
    Woo_Envios_Shipping_Method --> Woo_Envios_Logger : uses
    Woo_Envios_Shipping_Method --> Woo_Envios_Admin : uses
    
    Woo_Envios_Checkout --> Woo_Envios_Google_Maps : uses
    Woo_Envios_Admin --> Woo_Envios_Google_Maps : uses
```

## Database Schema

### 1. Geocode Cache Table (`wp_woo_envios_geocode_cache`)
```sql
CREATE TABLE wp_woo_envios_geocode_cache (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    cache_key VARCHAR(64) NOT NULL,
    result_data LONGTEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY cache_key (cache_key),
    KEY expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 2. WordPress Options (Plugin Settings)
- `woo_envios_google_maps_api_key`: Google Maps API key
- `woo_envios_store_latitude`: Store base latitude
- `woo_envios_store_longitude`: Store base longitude
- `woo_envios_delivery_tiers`: JSON-encoded delivery tiers
- `woo_envios_dynamic_pricing_enabled`: Boolean flag
- `woo_envios_peak_hours`: JSON-encoded peak hour configurations
- `woo_envios_weather_api_key`: OpenWeather API key
- `woo_envios_rain_light_multiplier`: Light rain multiplier
- `woo_envios_rain_heavy_multiplier`: Heavy rain multiplier
- `woo_envios_weekend_multiplier`: Weekend multiplier
- `woo_envios_max_multiplier`: Maximum allowed multiplier
- `woo_envios_enable_logs`: Enable/disable logging
- `triqhub_license_key`: TriqHub license key

### 3. WooCommerce Session Data
- `woo_envios_coords`: Customer coordinates with signature
  - `lat`: Latitude (float)
  - `lng`: Longitude (float)
  - `signature`: MD5 hash of normalized address

## External API Integration Architecture

### 1. Google Maps API Integration
**Authentication**: API Key-based authentication
**Rate Limiting**: Implemented via circuit breaker pattern
**Caching Strategy**: 30-day TTL for geocode results
**Error Handling**: Graceful degradation to Haversine formula

### 2. OpenWeather API Integration
**Authentication**: API Key-based authentication
**Cache Strategy**: 1-hour TTL for weather data
**Data Processing**: Rain intensity classification
**Fallback**: No weather adjustment if API fails

### 3. Correios/SuperFrete Integration
**Authentication**: Token-based authentication
**Service Types**: PAC, SEDEX, Mini Envios
**Fallback Strategy**: Primary shipping method when outside radius
**Error Handling**: Silent failure with logging

### 4. TriqHub License API
**Authentication**: License key validation
