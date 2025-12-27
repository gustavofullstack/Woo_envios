# TriqHub: Shipping & Radius - Plugin Documentation

## Introduction

TriqHub: Shipping & Radius is a sophisticated WooCommerce plugin that automates Brazilian postal code (CEP) coordinate collection during checkout and integrates radius-based shipping rules. The plugin leverages Google Maps API for maximum geocoding precision and provides intelligent shipping calculations based on distance, weather conditions, and peak hours.

This plugin offers two primary shipping methods:
1. **Flash Delivery (Radius-based)**: Local delivery within a configurable radius from your store location
2. **SuperFrete Integration**: National shipping services (PAC, SEDEX, Mini Envios) for customers outside the delivery radius

## Features

### Core Functionality
- **Automatic Geocoding**: Converts Brazilian postal codes (CEP) to precise latitude/longitude coordinates using Google Maps API
- **Radius-Based Shipping**: Configurable distance-based shipping zones with tiered pricing
- **Dual Shipping Methods**: Combines local flash delivery with national SuperFrete shipping options
- **Intelligent Rate Sorting**: Automatically prioritizes Flash Delivery over other shipping methods

### Advanced Features
- **Google Maps Integration**: High-precision geocoding with caching system for performance optimization
- **Dynamic Pricing**: Adjusts shipping costs based on distance, weather conditions, and time factors
- **Weather-Aware Calculations**: Considers weather conditions that might affect delivery times
- **Peak Hour Multipliers**: Configurable pricing adjustments for busy periods
- **Self-Healing Architecture**: Automatic cache table creation and error recovery
- **GitHub Auto-Updater**: Seamless plugin updates via GitHub releases

### Technical Features
- **Object-Oriented Architecture**: Clean, maintainable codebase with proper namespacing
- **Comprehensive Logging**: Detailed shipping calculation logs for debugging and analytics
- **Session-Based Caching**: Stores geocoded coordinates in WooCommerce session for performance
- **Multi-Layer Validation**: Robust error handling and dependency checking

## Installation & Usage

### Prerequisites
- WordPress 6.2 or higher
- WooCommerce 5.0 or higher
- PHP 7.4 or higher
- Google Maps API key (for geocoding functionality)
- SuperFrete API token (for national shipping calculations)

### Installation Steps

1. **Upload Plugin Files**
   - Download the latest release from GitHub
   - Upload the `triqhub-shipping-radius` folder to `/wp-content/plugins/`
   - Alternatively, upload the ZIP file via WordPress admin > Plugins > Add New

2. **Activate the Plugin**
   - Navigate to WordPress admin > Plugins
   - Find "TriqHub: Shipping & Radius" and click "Activate"

3. **Configure Required Settings**
   - Go to WooCommerce > Settings > Shipping
   - Configure both "Entrega Flash" and "SuperFrete" shipping methods
   - Enter your Google Maps API key in the plugin settings
   - Add your SuperFrete API token for national shipping calculations

4. **Set Up Shipping Tiers**
   - Configure distance-based pricing tiers in the plugin settings
   - Define your store's base coordinates (latitude/longitude)
   - Set up peak hour schedules and multipliers

### Basic Usage

Once configured, the plugin automatically:
- Captures customer postal codes during checkout
- Converts CEP to coordinates using Google Maps
- Calculates distance from your store location
- Shows appropriate shipping options based on distance
- Sorts Flash Delivery to the top of available options

## Configuration & Architecture

### Plugin Structure

```
triqhub-shipping-radius/
├── includes/
│   ├── class-woo-envios-admin.php          # Admin interface
│   ├── class-woo-envios-checkout.php       # Checkout integration
│   ├── class-woo-envios-google-maps.php    # Google Maps API wrapper
│   ├── class-woo-envios-google-maps-admin.php # Maps admin settings
│   ├── class-woo-envios-logger.php         # Logging system
│   ├── class-woo-envios-shipping.php       # Main shipping method
│   ├── class-woo-envios-weather.php        # Weather integration
│   └── Services/
│       ├── Geocoder.php                    # Geocoding service
│       ├── class-woo-envios-correios.php   # SuperFrete integration
│       └── class-woo-envios-superfrete-shipping-method.php # SuperFrete shipping method
├── assets/
│   ├── css/
│   │   ├── triqhub-admin.css              # Admin styling
│   │   └── woo-envios-frontend.css        # Frontend styling
├── includes/core/
│   └── class-triqhub-connector.php        # TriqHub integration
└── triqhub-shipping-radius.php            # Main plugin file
```

### Database Schema

The plugin creates a caching table for Google Maps geocoding results:

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

### Configuration Options

#### Store Settings
- **Base Coordinates**: Latitude and longitude of your store location
- **Default Coordinates**: Fallback coordinates if geocoding fails
- **Google Maps API Key**: Required for geocoding functionality

#### Shipping Tiers
Configure multiple distance-based pricing tiers:
- Distance range (in kilometers)
- Base price for each tier
- Custom label for each tier

#### Dynamic Pricing
- **Peak Hours**: Define time ranges for busier periods
- **Weekend Multiplier**: Price adjustment for weekend deliveries
- **Maximum Multiplier**: Cap for dynamic price adjustments
- **Weather Integration**: Enable/disable weather-based pricing

#### SuperFrete Configuration
- **API Token**: Your SuperFrete authentication token
- **Default Package Dimensions**: Height, width, length, and weight
- **Available Services**: PAC, SEDEX, Mini Envios selection

### Shipping Calculation Flow

1. **Checkout Initiation**: Customer enters postal code
2. **Geocoding**: Plugin converts CEP to coordinates (cached when possible)
3. **Distance Calculation**: Haversine formula calculates distance from store
4. **Tier Matching**: Finds appropriate pricing tier based on distance
5. **Dynamic Adjustments**: Applies weather, time, and peak hour multipliers
6. **Rate Generation**: Creates shipping rate for Flash Delivery
7. **Fallback Check**: If outside radius, calculates SuperFrete options
8. **Rate Sorting**: Prioritizes Flash Delivery in display

## API Reference & Hooks

### Available Filters

#### `woocommerce_shipping_methods`
**Purpose**: Register custom shipping methods with WooCommerce
**Usage**:
```php
add_filter('woocommerce_shipping_methods', function($methods) {
    $methods['woo_envios_radius'] = 'Woo_Envios_Shipping_Method';
    $methods['woo_envios_superfrete'] = 'Woo_Envios\Services\Woo_Envios_Superfrete_Shipping_Method';
    return $methods;
});
```

#### `woocommerce_package_rates`
**Purpose**: Sort shipping rates to prioritize Flash Delivery
**Usage**:
```php
add_filter('woocommerce_package_rates', function($rates, $package) {
    // Custom sorting logic
    return $sorted_rates;
}, 10, 2);
```

### Available Actions

#### `woo_envios_geocode_complete`
**Purpose**: Triggered after successful geocoding
**Parameters**: `$coordinates` (array), `$postal_code` (string)
**Usage**:
```php
add_action('woo_envios_geocode_complete', function($coordinates, $postal_code) {
    // Custom logic after geocoding
}, 10, 2);
```

#### `woo_envios_shipping_calculated`
**Purpose**: Triggered after shipping calculation
**Parameters**: `$distance`, `$base_price`, `$final_price`, `$adjustments`
**Usage**:
```php
add_action('woo_envios_shipping_calculated', function($distance, $base_price, $final_price, $adjustments) {
    // Log or process calculation results
}, 10, 4);
```

### Class Reference

#### `Woo_Envios_Shipping_Method`
Main shipping method class for radius-based delivery.

**Properties**:
- `$id`: 'woo_envios_radius'
- `$method_title`: 'Entrega Flash'
- `$method_description`: 'Entrega rápida baseada em distância'

**Methods**:
- `calculate_shipping($package)`: Main calculation method
- `validate_postal_code($postal_code)`: Validates Brazilian CEP format
- `get_distance_tier($distance)`: Finds appropriate pricing tier

#### `Woo_Envios\Services\Woo_Envios_Superfrete_Shipping_Method`
SuperFrete integration shipping method.

**Properties**:
- `$id`: 'woo_envios_superfrete'
- `$method_title`: 'SuperFrete'
- `$method_description`: 'Entregas nacionais via SuperFrete'

**Methods**:
- `calculate_shipping($package)`: Calculates SuperFrete rates
- `get_available_services()`: Returns configured services
- `parse_api_response($response)`: Processes SuperFrete API response

#### `Woo_Envios_Google_Maps`
Google Maps API integration handler.

**Methods**:
- `geocode_address($address)`: Converts address to coordinates
- `calculate_distance($origin, $destination)`: Calculates route distance
- `is_configured()`: Checks if API key is set
- `get_cached_result($key)`: Retrieves cached geocoding results

### Constants

The plugin defines several constants for configuration:

```php
define('WOO_ENVIOS_FILE', __FILE__);
define('WOO_ENVIOS_PATH', plugin_dir_path(__FILE__));
define('WOO_ENVIOS_URL', plugin_dir_url(__FILE__));
define('WOO_ENVIOS_ASSETS', WOO_ENVIOS_URL . 'assets/');
define('WOO_ENVIOS_DEFAULT_LAT', -18.911);  // Default latitude
define('WOO_ENVIOS_DEFAULT_LNG', -48.262);  // Default longitude
```

## Troubleshooting

### Common Issues & Solutions

#### 1. Plugin Not Appearing in Shipping Methods
**Symptoms**: No "Entrega Flash" or "SuperFrete" options in WooCommerce shipping settings
**Solutions**:
- Verify WooCommerce is active and version 5.0+
- Check PHP error logs for initialization errors
- Ensure all required plugin files are present
- Verify no fatal errors during plugin bootstrap

#### 2. Geocoding Failures
**Symptoms**: "Unable to calculate shipping" or default coordinates always used
**Solutions**:
- Verify Google Maps API key is valid and has Geocoding API enabled
- Check API usage limits in Google Cloud Console
- Enable plugin logging to see detailed geocoding errors
- Test API connectivity using the Google Maps Geocoding API directly

#### 3. SuperFrete API Errors
**Symptoms**: National shipping options not appearing
**Solutions**:
- Verify SuperFrete API token is valid and not expired
- Check that the token has necessary permissions
- Test API connectivity using the provided test scripts
- Verify package dimensions are within SuperFrete limits

#### 4. Cache Table Issues
**Symptoms**: "Table doesn't exist" errors or performance degradation
**Solutions**:
- The plugin includes self-healing - try reactivating
- Manually run the SQL creation script if needed
- Check database user permissions
- Clear old cache entries if table grows too large

#### 5. Shipping Calculation Errors
**Symptoms**: Incorrect shipping prices or no prices calculated
**Solutions**:
- Verify store coordinates are correctly set
- Check distance tier configurations
- Enable debug logging to see calculation steps
- Test with known postal codes to verify accuracy

### Debugging Tools

The plugin includes several test scripts for troubleshooting:

1. **`test-superfrete.php`**: Tests SuperFrete API connectivity
2. **`test-simulation.php`**: Simulates shipping calculations
3. **`test-plugin-loading.php`**: Checks plugin initialization
4. **`test-full-integration.php`**: Comprehensive integration test

To use these tools:
```bash
# Navigate to plugin directory
cd /path/to/wp-content/plugins/triqhub-shipping-radius/

# Run SuperFrete test
php test-superfrete.php

# Run simulation test
php test-simulation.php
```

### Logging System

The plugin includes a comprehensive logging system. Enable debug logging in `wp-config.php`:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

Logs will be available in `/wp-content/debug.log` and include:
- Geocoding requests and responses
- Shipping calculations with all variables
- API call details and responses
- Error conditions and recovery attempts

### Performance Optimization

If experiencing performance issues:

1. **Enable Caching**: Ensure geocoding cache is working properly
2. **Limit API Calls**: Configure appropriate cache expiration times
3. **Optimize Distance Calculations**: Consider pre-calculating common routes
4. **Database Indexing**: Ensure cache table has proper indexes
5. **Session Optimization**: Review session storage configuration

### Getting Support

For additional support:
1. Check the GitHub repository issues for known problems
2. Review the WooCommerce system status report
3. Enable debug logging and share relevant sections
4. Test with default WordPress theme and minimal plugins
5. Verify server meets all requirements (PHP, WordPress, WooCommerce)

**Note**: Always backup your site before making significant changes to shipping configuration or updating the plugin.