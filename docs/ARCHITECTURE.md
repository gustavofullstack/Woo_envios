# TriqHub Shipping & Radius - Architecture Documentation

## 1. System Overview

TriqHub Shipping & Radius is a sophisticated WooCommerce shipping plugin that provides intelligent, location-based delivery calculations for Brazilian e-commerce stores. The system combines multiple external APIs (Google Maps, OpenWeather, Correios/SuperFrete) with dynamic pricing algorithms to offer precise shipping rates based on real-time conditions.

### 1.1 Core Architecture Principles

- **Modular Design**: Each major functionality is encapsulated in dedicated classes
- **Singleton Pattern**: Main plugin class uses singleton for single-point initialization
- **Circuit Breaker Pattern**: API failure protection with graceful degradation
- **Caching Strategy**: Multi-layer caching (database, transients, session)
- **Event-Driven**: WordPress hooks and filters for extensibility

## 2. System Architecture Diagram

```mermaid
graph TB
    %% Main Components
    subgraph "WordPress Environment"
        WP[WordPress Core]
        WC[WooCommerce]
    end
    
    subgraph "TriqHub Shipping Plugin"
        MAIN[TriqHub_Shipping_Plugin<br/>Singleton Main Class]
        
        subgraph "Core Services"
            GEO[Geocoder Service]
            GMAPS[Google Maps API]
            WEATHER[Weather Service]
            CORREIOS[Correios/SuperFrete]
            LOGGER[Logger Service]
        end
        
        subgraph "Shipping Methods"
            RADIUS[Radius Shipping Method]
            SUPERFRETE[SuperFrete Shipping]
        end
        
        subgraph "Admin Interface"
            ADMIN[Admin Settings]
            GMAPS_ADMIN[Google Maps Admin]
        end
        
        subgraph "Frontend"
            CHECKOUT[Checkout Integration]
            ASSETS[Frontend Assets]
        end
        
        subgraph "Infrastructure"
            CACHE[Geocode Cache Table]
            UPDATER[GitHub Updater]
            CONNECTOR[TriqHub Connector]
        end
    end
    
    subgraph "External APIs"
        GOOGLE[Google Maps API]
        OPENWEATHER[OpenWeather API]
        SUPERFRETE_API[SuperFrete API]
        GITHUB[GitHub API]
        TRIQHUB_API[TriqHub License API]
    end
    
    %% Data Flow
    WP --> MAIN
    WC --> MAIN
    
    MAIN --> GEO
    MAIN --> GMAPS
    MAIN --> WEATHER
    MAIN --> CORREIOS
    MAIN --> LOGGER
    MAIN --> RADIUS
    MAIN --> SUPERFRETE
    MAIN --> ADMIN
    MAIN --> CHECKOUT
    MAIN --> CACHE
    MAIN --> UPDATER
    MAIN --> CONNECTOR
    
    GEO --> CACHE
    GMAPS --> GOOGLE
    WEATHER --> OPENWEATHER
    CORREIOS --> SUPERFRETE_API
    UPDATER --> GITHUB
    CONNECTOR --> TRIQHUB_API
    
    ADMIN --> GMAPS_ADMIN
    CHECKOUT --> ASSETS
    
    RADIUS --> GEO
    RADIUS --> GMAPS
    RADIUS --> WEATHER
    SUPERFRETE --> CORREIOS
    
    %% Styling
    classDef external fill:#f9f,stroke:#333,stroke-width:2px
    classDef core fill:#bbf,stroke:#333,stroke-width:2px
    classDef service fill:#bfb,stroke:#333,stroke-width:2px
    classDef infra fill:#ffb,stroke:#333,stroke-width:2px
    
    class GOOGLE,OPENWEATHER,SUPERFRETE_API,GITHUB,TRIQHUB_API external
    class MAIN,ADMIN,CHECKOUT,RADIUS,SUPERFRETE core
    class GEO,GMAPS,WEATHER,CORREIOS,LOGGER service
    class CACHE,UPDATER,CONNECTOR infra
```

## 3. Core Module Architecture

### 3.1 Main Plugin Bootstrap (`triqhub-shipping-radius.php`)

**Class**: `TriqHub_Shipping_Plugin`
**Pattern**: Singleton
**Responsibilities**:
- Plugin initialization and lifecycle management
- Dependency loading order management
- Hook registration
- Self-healing mechanisms

**Key Methods**:
```php
public static function instance(): TriqHub_Shipping_Plugin
private function define_constants(): void
private function include_files(): void
private function load_components(): void
private function register_hooks(): void
public function activate(): void
private function create_google_cache_table(): void
private function maybe_create_cache_table(): void
public function register_shipping_method(array $methods): array
public function sort_shipping_rates(array $rates, array $package): array
```

**Dependency Loading Order**:
1. Logger (`class-woo-envios-logger.php`)
2. Google Maps API (`class-woo-envios-google-maps.php`)
3. Geocoder Service (`Services/Geocoder.php`)
4. Correios Service (`Services/class-woo-envios-correios.php`)
5. SuperFrete Shipping (`Services/class-woo-envios-superfrete-shipping-method.php`)
6. Admin Interfaces (`class-woo-envios-google-maps-admin.php`, `class-woo-envios-admin.php`)
7. Weather Service (`class-woo-envios-weather.php`)
8. Checkout Integration (`class-woo-envios-checkout.php`)

### 3.2 Shipping Method Architecture

```mermaid
classDiagram
    class WC_Shipping_Method {
        +string $id
        +string $method_title
        +string $method_description
        +int $instance_id
        +array $supports
        +string $enabled
        +string $title
        +get_option($key, $default)
        +init_instance_settings()
        +add_rate($rate)
        +calculate_shipping($package)
    }
    
    class Woo_Envios_Shipping_Method {
        -array $instance_form_fields
        +__construct($instance_id)
        +init() void
        +calculate_shipping($package) void
        -get_session_coordinates($signature) array|null
        -calculate_route_distance($store_coords, $customer_coords, $package) array|WP_Error
        -calculate_distance($lat_from, $lng_from, $lat_to, $lng_to) float
        -build_destination_signature($package) string
        -calculate_dynamic_multiplier($package) array
        -get_peak_hour_multiplier() array
        -get_weather_multiplier($package) float
        -is_weekend() bool
        -calculate_correios_shipping($package) void
    }
    
    class Woo_Envios_Superfrete_Shipping_Method {
        +__construct($instance_id)
        +init() void
        +calculate_shipping($package) void
        -get_correios_rates($package) array
        -validate_package_dimensions($package) bool
        -calculate_deadline($service_code, $origin_cep, $destination_cep) int
    }
    
    WC_Shipping_Method <|-- Woo_Envios_Shipping_Method
    WC_Shipping_Method <|-- Woo_Envios_Superfrete_Shipping_Method
```

### 3.3 Service Layer Architecture

#### 3.3.1 Geocoder Service (`Services/Geocoder.php`)
**Responsibilities**:
- Address-to-coordinate conversion
- Multi-provider fallback (Google Maps primary, manual fallback)
- Cache management
- Brazilian CEP validation and formatting

**Key Methods**:
```php
public static function geocode(string $address): ?array
public static function reverse_geocode(float $lat, float $lng): ?array
private static function geocode_via_google(string $address): ?array
private static function geocode_via_fallback(string $address): ?array
private static function normalize_brazilian_address(array $components): array
```

#### 3.3.2 Google Maps API Service (`class-woo-envios-google-maps.php`)
**Responsibilities**:
- Google Maps API integration
- Circuit breaker implementation
- Request retry logic
- Response caching

**Circuit Breaker Implementation**:
```php
private function check_circuit_breaker(): bool
private function record_failure(): void
private function record_success(): void
private function is_circuit_open(): bool
```

**API Endpoints**:
- Geocoding: `https://maps.googleapis.com/maps/api/geocode/json`
- Places Autocomplete: `https://maps.googleapis.com/maps/api/place/autocomplete/json`
- Place Details: `https://maps.googleapis.com/maps/api/place/details/json`
- Distance Matrix: `https://maps.googleapis.com/maps/api/distancematrix/json`

#### 3.3.3 Weather Service (`class-woo-envios-weather.php`)
**Responsibilities**:
- OpenWeather API integration
- Rain detection and intensity classification
- Dynamic pricing multiplier calculation
- Weather data caching

**Key Methods**:
```php
public function get_weather_multiplier(float $lat, float $lng): float
private function get_current_weather(float $lat, float $lng, string $api_key): ?array
private function calculate_rain_multiplier(array $weather_data): float
public function get_weather_description(array $weather_data): string
public function clear_cache(): void
```

**Multiplier Logic**:
- No rain: 1.0
- Light rain/drizzle: 1.2 (configurable)
- Heavy rain (>5mm/h): 1.5 (configurable)
- Thunderstorm: 1.5 (configurable)

#### 3.3.4 Correios/SuperFrete Service (`Services/class-woo-envios-correios.php`)
**Responsibilities**:
- Brazilian postal service integration
- Multiple service types (PAC, SEDEX, Mini)
- Package dimension validation
- Deadline calculation

**Supported Services**:
- PAC (Ágil)
- SEDEX (Expresso)
- SEDEX 10 (Prioritário)
- SEDEX 12 (Urgente)
- SEDEX Hoje (Same-day)
- Mini Envios (Small packages)

### 3.4 Data Storage Architecture

#### 3.4.1 Geocode Cache Table
```sql
CREATE TABLE wp_woo_envios_geocode_cache (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    cache_key VARCHAR(64) NOT NULL,
    result_data LONGTEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY cache_key (cache_key),
    KEY expires_at (expires_at)
) CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Cache Strategy**:
- **Cache Key**: MD5 hash of normalized address
- **TTL**: Configurable (default 30 days)
- **Cleanup**: Automatic via `expires_at` index
- **Self-healing**: Table creation on plugin activation and missing table detection

#### 3.4.2 WordPress Options
**Plugin Settings**:
- `woo_envios_google_maps_api_key`: Google Maps API key
- `woo_envios_store_lat` / `woo_envios_store_lng`: Store coordinates
- `woo_envios_radius_tiers`: Distance-based pricing tiers
- `woo_envios_dynamic_pricing_enabled`: Dynamic pricing toggle
- `woo_envios_peak_hours`: Peak hour configurations
- `woo_envios_weather_api_key`: OpenWeather API key
- `woo_envios_enable_logs`: Debug logging toggle

#### 3.4.3 Session Storage
**Session Variables**:
- `woo_envios_coords`: Customer coordinates with signature
- `woo_envios_address_validated`: Address validation status
- `woo_envios_geocode_attempts`: Geocoding attempt counter

### 3.5 Admin Interface Architecture

#### 3.5.1 Main Admin Class (`class-woo-envios-admin.php`)
**Responsibilities**:
- Plugin settings management
- Store coordinate configuration
- Radius tier management
- System status reporting

**Admin Pages**:
1. **General Settings**: API keys, store location
2. **Radius Tiers**: Distance-based pricing configuration
3. **Dynamic Pricing**: Peak hours, weather multipliers
4. **Logs & Debug**: System logs and diagnostics

#### 3.5.2 Google Maps Admin (`class-woo-envios-google-maps-admin.php`)
**Responsibilities**:
- Google Maps API key management
- Geocoding cache management
- API usage statistics
- Circuit breaker status

### 3.6 Checkout Integration Architecture

#### 3.6.1 Checkout Class (`class-woo-envios-checkout.php`)
**Responsibilities**:
- Frontend JavaScript integration
- Address autocomplete
- Real-time coordinate validation
- Shipping method sorting

**Frontend Components**:
1. **Address Autocomplete**: Google Places integration
2. **Coordinate Validation**: Real-time geocoding
3. **Shipping Method Display**: Custom CSS for method ordering
4. **Error Handling**: User-friendly error messages

### 3.7 Update System Architecture

#### 3.7.1 GitHub Updater Integration
**Components**:
- Plugin Update Checker (YahnisElsts library)
- GitHub Release Asset integration
- License key validation
- Update notification system

**Update Flow**:
```mermaid
sequenceDiagram
    participant WP as WordPress
    participant Plugin as TriqHub Plugin
    participant PUC as Plugin Update Checker
    participant GitHub as GitHub API
    participant TriqHub as TriqHub License API
    
    WP->>Plugin: Check for updates
    Plugin->>PUC: Initiate update check
    PUC->>GitHub: Request plugin-update.json
    GitHub-->>PUC: Return version info
    PUC->>TriqHub: Validate license (with key)
    TriqHub-->>PUC: License validation result
    PUC-->>Plugin: Update available/not available
    Plugin-->>WP: Display update notification
```

### 3.8 Logging & Monitoring Architecture

#### 3.8.1 Logger Service (`class-woo-envios-logger.php`)
**Log Levels**:
- `INFO`: Normal operations, shipping calculations
- `WARNING`: Non-critical issues, out-of-range distances
- `ERROR`: API failures, critical errors

**Log Rotation**:
- Daily log files (YYYY-MM-DD.log)
- 7-day retention policy
- Automatic cleanup via WordPress cron
- Protected directory with .htaccess

**Notification System**:
- Admin email alerts for circuit breaker activation
- Rate limiting to prevent spam (1-hour cooldown)
- Detailed error context in notifications

## 4. Data Flow Diagrams

### 4.1 Shipping Calculation Flow

```mermaid
sequenceDiagram
    participant Customer
    participant Checkout as WooCommerce Checkout
    participant Session as WordPress Session
    participant Shipping as Radius Shipping Method
    participant Geocoder as Geocoder Service
    participant GMaps as Google Maps API
    participant Weather as Weather Service
    participant Correios as Correios Service
    participant Cache as Geocode Cache
    
    Customer->>Checkout: Enters address
    Checkout->>Session: Store address data
    Checkout->>Shipping: Trigger shipping calculation
    
    Shipping->>Session: Get cached coordinates
    alt Coordinates cached
        Session-->>Shipping: Return coordinates
    else No coordinates
        Shipping->>Geocoder: Geocode address
        Geocoder->>Cache: Check cache
        alt Cached result
            Cache-->>Geocoder: Return cached coordinates
        else Not cached
            Geocoder->>GMaps: API geocode request
            GMaps-->>Geocoder: Return coordinates
            Geocoder->>Cache: Store in cache
        end
        Geocoder-->>Shipping: Return coordinates
        Shipping->>Session: Cache coordinates
    end
    
    Shipping->>GMaps: Distance Matrix request
    GMaps-->>Shipping: Return route distance
    
    Shipping->>Weather: Get weather multiplier
    Weather->>OpenWeather: API request
    OpenWeather-->>Weather: Return weather data
    Weather-->>Shipping: Return multiplier
    
    Shipping->>Shipping: Calculate dynamic pricing
    Shipping->>Correios: Get Correios rates
    Correios-->>Shipping: Return shipping options
    
    Shipping->>Checkout: Return shipping rates
    Checkout->>Customer: Display shipping options
```

### 4.2 Dynamic Pricing Calculation Flow

```mermaid
flowchart TD
    Start[Start Dynamic Pricing] --> CheckEnabled{Dynamic Pricing Enabled?}
    
    CheckEnabled -- No --> ReturnDefault[Return 1.0 Multiplier]
    CheckEnabled -- Yes --> CheckPeakHours{Check Peak Hours}
    
    CheckPeakHours -- In Peak Hours --> ApplyPeak[Apply Peak Multiplier]
    CheckPeakHours -- Not Peak Hours --> CheckWeekend{Is Weekend?}
    
    CheckWeekend -- Yes --> ApplyWeekend[Apply Weekend Multiplier]
    CheckWeekend -- No --> CheckWeather{Check Weather}
    
    ApplyPeak --> CheckWeekend
    ApplyWeekend --> CheckWeather
    
    CheckWeather -- Rain Detected --> ApplyWeather[Apply Weather Multiplier]
    CheckWeather -- No Rain --> CalculateTotal[Calculate Total Multiplier]
    
    ApplyWeather --> CalculateTotal
    CalculateTotal --> CheckMax{Exceeds Max Multiplier?}
    
    CheckMax -- Yes --> ApplyMax[Apply Maximum Multiplier]
    CheckMax -- No --> ReturnResult[Return Final Multiplier]
    
    ApplyMax --> ReturnResult
    ReturnDefault --> End[End]
    ReturnResult --> End
```

## 5. Error Handling & Resilience

### 5.1 Circuit Breaker Pattern
**Implementation Details**:
- **Failure Threshold**: 5 consecutive API failures
- **Half-Open State**: After 5 minutes, allow single test request
- **Reset**: After successful test request, close circuit
- **Fallback**: Use default coordinates when circuit is open

### 5.2 Graceful Degradation
**Fallback Strategies**:
1. **Google Maps API Failure**: Use Havers