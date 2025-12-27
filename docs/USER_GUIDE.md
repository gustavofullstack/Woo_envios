# TriqHub: Shipping & Radius - User Guide

## Table of Contents
1. [Introduction](#introduction)
2. [System Requirements](#system-requirements)
3. [Installation & Activation](#installation--activation)
4. [Initial Configuration](#initial-configuration)
5. [Shipping Configuration](#shipping-configuration)
6. [Google Maps Integration](#google-maps-integration)
7. [Dynamic Pricing Features](#dynamic-pricing-features)
8. [Weather-Based Pricing](#weather-based-pricing)
9. [Troubleshooting](#troubleshooting)
10. [Advanced Usage](#advanced-usage)
11. [Maintenance & Best Practices](#maintenance--best-practices)

## Introduction

TriqHub: Shipping & Radius is a sophisticated WooCommerce shipping plugin that automates Brazilian postal code (CEP) coordinate collection at checkout and integrates radius-based shipping rules. The plugin leverages Google Maps API for maximum precision in distance calculations and offers dynamic pricing based on multiple factors including weather conditions, peak hours, and weekends.

### Key Features
- **Radius-Based Shipping**: Calculate delivery costs based on straight-line distance from your store
- **Google Maps Integration**: Precise geocoding and distance calculations using real route data
- **Dynamic Pricing**: Adjust shipping costs based on weather, time of day, and day of week
- **Brazilian CEP Support**: Specialized handling for Brazilian postal codes
- **Multiple Shipping Methods**: Local "Flash Delivery" + Correios/SuperFrete for outside radius
- **Weather Integration**: Real-time weather data from OpenWeather API
- **Automatic Updates**: GitHub-based update system with license validation
- **Comprehensive Logging**: Detailed logging for debugging and monitoring

## System Requirements

### Minimum Requirements
- **WordPress**: 6.2 or higher
- **WooCommerce**: 5.0 or higher
- **PHP**: 7.4 or higher
- **MySQL**: 5.6 or higher
- **Memory Limit**: 128MB minimum (256MB recommended)
- **Execution Time**: 30 seconds minimum

### Required PHP Extensions
- cURL (for API calls)
- JSON (for data processing)
- OpenSSL (for secure connections)
- mbstring (for string operations)

### Recommended Server Configuration
```apache
# Apache .htaccess recommendations
php_value max_execution_time 60
php_value memory_limit 256M
php_value upload_max_filesize 64M
php_value post_max_size 64M
```

## Installation & Activation

### Method 1: WordPress Admin Dashboard
1. Navigate to **Plugins → Add New**
2. Click **Upload Plugin**
3. Select the `triqhub-shipping-radius.zip` file
4. Click **Install Now**
5. After installation, click **Activate**

### Method 2: Manual Installation via FTP
1. Download the plugin ZIP file
2. Extract the contents to your computer
3. Connect to your server via FTP/SFTP
4. Navigate to `/wp-content/plugins/`
5. Upload the `triqhub-shipping-radius` folder
6. Go to **Plugins** in WordPress admin
7. Find "TriqHub: Shipping & Radius" and click **Activate**

### Method 3: Command Line (WP-CLI)
```bash
wp plugin install /path/to/triqhub-shipping-radius.zip --activate
```

### Post-Installation Verification
After activation, verify the plugin is working correctly:

1. Check for any error messages in the WordPress admin area
2. Navigate to **WooCommerce → Settings → Shipping**
3. Verify that "Woo Envios — Raio Escalonado" appears in available shipping methods
4. Check the plugin status in **WooCommerce → Status → Logs** for any initialization errors

## Initial Configuration

### Step 1: Configure Store Coordinates
1. Navigate to **WooCommerce → Settings → Woo Envios**
2. Enter your store's base coordinates:
   - **Latitude**: Your store's latitude (e.g., -18.911)
   - **Longitude**: Your store's longitude (e.g., -48.262)
3. Click **Save Changes**

> **Tip**: Use Google Maps to find your exact coordinates. Right-click on your store location and select "What's here?" to see coordinates.

### Step 2: Set Up Shipping Tiers
Configure distance-based pricing tiers:

1. In the **Woo Envios** settings page, locate "Shipping Tiers"
2. Add tiers with the following format:
   ```
   Tier 1: 0-5km = R$ 10.00
   Tier 2: 5-10km = R$ 15.00
   Tier 3: 10-15km = R$ 20.00
   ```
3. Configure unlimited tiers as needed
4. Set a default tier for distances beyond your configured range

### Step 3: Enable Shipping Method
1. Go to **WooCommerce → Settings → Shipping → Shipping Zones**
2. Create a new shipping zone or edit an existing one
3. Click **Add shipping method**
4. Select "Woo Envios — Raio Escalonado"
5. Configure the method:
   - **Enabled**: Check to activate
   - **Title**: Customize the display name (e.g., "Flash Delivery")
6. Click **Save changes**

## Shipping Configuration

### Local Delivery (Flash Delivery)
The plugin's primary shipping method calculates costs based on distance from your store:

#### Configuration Options
- **Base Price per Tier**: Set different prices for distance ranges
- **Maximum Delivery Distance**: Define the maximum radius for local delivery
- **Fallback Method**: Configure Correios/SuperFrete for addresses outside your radius

#### Customer Experience
1. Customer enters their CEP (Brazilian postal code) at checkout
2. Plugin geocodes the address using Google Maps API
3. Distance is calculated from store to customer location
4. Appropriate tier price is applied
5. Dynamic multipliers (weather, time, etc.) are calculated
6. Final price is displayed to customer

### Correios/SuperFrete Integration
For addresses outside your delivery radius, the plugin can automatically offer Correios shipping options:

#### Configuration
1. Navigate to **WooCommerce → Settings → Woo Envios → Correios Settings**
2. Enable Correios integration
3. Configure your Correios contract details:
   - **CEP de Origem**: Your store's postal code
   - **Código Administrativo**: Your Correios administrative code
   - **Senha**: Your Correios password
4. Select which services to offer:
   - PAC
   - SEDEX
   - Mini Envios
5. Configure default dimensions and weights

#### Service Prioritization
The plugin automatically sorts shipping methods:
1. Local Flash Delivery (if within radius)
2. Correios options (PAC, SEDEX, Mini)

## Google Maps Integration

### Obtaining a Google Maps API Key
1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project or select an existing one
3. Enable the following APIs:
   - Maps JavaScript API
   - Geocoding API
   - Places API
   - Distance Matrix API
4. Create credentials (API Key)
5. Restrict the API key to your domain
6. Copy the API key

### Configuring Google Maps in Plugin
1. Navigate to **WooCommerce → Settings → Woo Envios → Google Maps**
2. Paste your Google Maps API key
3. Configure cache settings:
   - **Cache Duration**: How long to cache geocoding results (default: 30 days)
   - **Enable Circuit Breaker**: Automatically disable API calls after consecutive failures
4. Test the configuration using the "Test Connection" button

### API Usage and Limits
- **Free Tier**: $200 monthly credit (approximately 40,000 geocoding requests)
- **Monitoring**: Check usage in Google Cloud Console
- **Optimization**: The plugin implements caching to minimize API calls

### Troubleshooting Google Maps Issues
| Symptom | Possible Cause | Solution |
|---------|---------------|----------|
| "Invalid API Key" error | API key not configured or restricted | Verify API key in settings and check restrictions |
| No coordinates returned | Address format issues | Ensure Brazilian CEP format (XXXXX-XXX) |
| Slow geocoding | API quota exceeded | Check Google Cloud Console for quota limits |
| "Request denied" | Billing not enabled | Enable billing in Google Cloud Console |

## Dynamic Pricing Features

### Peak Hour Pricing
Configure higher prices during busy periods:

1. Navigate to **WooCommerce → Settings → Woo Envios → Dynamic Pricing**
2. Enable "Peak Hour Pricing"
3. Add peak periods:
   ```
   Name: Lunch Rush
   Start: 11:30
   End: 13:30
   Multiplier: 1.2 (20% increase)
   
   Name: Dinner Rush
   Start: 18:00
   End: 20:00
   Multiplier: 1.3 (30% increase)
   ```
4. Configure unlimited periods as needed

### Weekend Pricing
Charge different rates on weekends:

1. Enable "Weekend Pricing"
2. Set weekend multiplier (e.g., 1.15 for 15% weekend surcharge)
3. Configure which days are considered weekends (default: Saturday & Sunday)

### Maximum Multiplier Limit
Prevent excessive price increases:
- Set a maximum overall multiplier (e.g., 2.0 = 100% maximum increase)
- This applies to the combined effect of all multipliers

## Weather-Based Pricing

### OpenWeather API Configuration
1. Sign up at [OpenWeatherMap](https://openweathermap.org/api)
2. Obtain an API key (free tier available)
3. Navigate to **WooCommerce → Settings → Woo Envios → Weather**
4. Enter your OpenWeather API key
5. Configure rain multipliers:
   - **Light Rain**: 1.2x (20% increase)
   - **Heavy Rain**: 1.5x (50% increase)
6. Enable/disable weather-based pricing

### How Weather Detection Works
1. Plugin checks current weather at store location
2. Detects rain conditions:
   - **Light Rain**: < 5mm/hour
   - **Heavy Rain**: ≥ 5mm/hour or thunderstorm
3. Applies configured multiplier
4. Results cached for 1 hour to minimize API calls

### Weather Cache Management
- Cache duration: 1 hour
- Manual cache clearing available in settings
- Automatic cache invalidation after configuration changes

## Troubleshooting

### Common Issues and Solutions

#### Issue: Shipping Method Not Appearing
**Symptoms**: No "Flash Delivery" option at checkout
**Diagnosis Steps**:
1. Check if plugin is activated
2. Verify WooCommerce version (5.0+ required)
3. Check shipping zone configuration
4. Review error logs

**Solutions**:
```php
// Enable debug logging in wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

#### Issue: Incorrect Distance Calculations
**Symptoms**: Wrong shipping prices or delivery area detection
**Diagnosis**:
1. Verify store coordinates
2. Check Google Maps API key
3. Review geocoding cache

**Solutions**:
1. Clear geocoding cache: **Woo Envios → Tools → Clear Cache**
2. Test coordinates with Google Maps
3. Verify API key permissions

#### Issue: Slow Checkout Performance
**Symptoms**: Checkout page loads slowly
**Diagnosis**:
1. Check server response times
2. Review API call counts
3. Examine caching effectiveness

**Solutions**:
1. Enable object caching (Redis/Memcached)
2. Optimize database indexes
3. Implement CDN for static assets

### Error Logs and Monitoring

#### Accessing Logs
1. **WordPress Debug Log**: `wp-content/debug.log`
2. **Plugin-Specific Logs**: `wp-content/uploads/woo-envios-logs/`
3. **WooCommerce Logs**: WooCommerce → Status → Logs

#### Log File Structure
```
[2024-01-15 14:30:45] [INFO] FRETE CALCULADO | Distância: 3.5 km | Base: R$ 10.00 | Final: R$ 12.00 | Multiplicadores: Pico +20%
[2024-01-15 14:31:22] [WARNING] DISTÂNCIA FORA DO ALCANCE | Distância: 25.2 km | Endereço: Rua Exemplo, São Paulo, SP
[2024-01-15 14:32:10] [ERROR] FALHA API Google Maps: API key invalid
```

#### Automated Monitoring
- Daily log rotation (keeps 7 days of logs)
- Automatic cleanup of old cache entries
- Email notifications for critical errors (circuit breaker activation)

### Debug Mode
Enable detailed debugging for troubleshooting:

1. Navigate to **WooCommerce → Settings → Woo Envios → Advanced**
2. Enable "Debug Mode"
3. Select log level (Info, Warning, Error, Debug)
4. Configure email notifications for errors

## Advanced Usage

### Custom Hooks and Filters
The plugin provides extensive WordPress hooks for customization:

#### Actions
```php
// Triggered after shipping calculation
add_action('woo_envios_after_shipping_calculation', function($distance, $price, $multipliers) {
    // Custom logic here
}, 10, 3);

// Triggered before geocoding
add_action('woo_envios_before_geocode', function($address) {
    // Modify address or add logging
});
```

#### Filters
```php
// Modify calculated distance
add_filter('woo_envios_calculated_distance', function($distance, $store_coords, $customer_coords) {
    return $distance * 1.1; // Add 10% buffer
}, 10, 3);

// Customize shipping label
add_filter('woo_envios_shipping_label', function($label, $distance, $price) {
    return sprintf('Entrega Flash (%s km) - R$ %.2f', $distance, $price);
}, 10, 3);

// Override weather multiplier
add_filter('woo_envios_weather_multiplier', function($multiplier, $weather_data) {
    if ($weather_data['temp'] > 30) {
        return $multiplier * 1.1; // Extra charge on hot days
    }
    return $multiplier;
}, 10, 2);
```

### Custom Shipping Tiers Programmatically
```php
add_filter('woo_envios_shipping_tiers', function($tiers) {
    // Add custom tier
    $tiers[] = [
        'min_distance' => 0,
        'max_distance' => 3,
        'price' => 8.00,
        'label' => 'Ultra Local'
    ];
    
    // Modify existing tiers
    foreach ($tiers as &$tier) {
        if ($tier['max_distance'] > 10) {
            $tier['price'] *= 1.05; // 5% increase for distant deliveries
        }
    }
    
    return $tiers;
});
```

### Integration with Other Plugins

#### WooCommerce Subscriptions
```php
// Special pricing for subscription customers
add_filter('woo_envios_final_price', function($price, $customer_id) {
    if (wcs_user_has_subscription($customer_id)) {
        return $price * 0.9; // 10% discount for subscribers
    }
    return $price;
}, 10, 2);
```

#### Advanced Custom Fields
```php
// Store-specific delivery settings
add_filter('woo_envios_store_coordinates', function($coords) {
    if (function_exists('get_field')) {
        $custom_lat = get_field('delivery_latitude', 'option');
        $custom_lng = get_field('delivery_longitude', 'option');
        
        if ($custom_lat && $custom_lng) {
            return ['lat' => $custom_lat, 'lng' => $custom_lng];
        }
    }
    return $coords;
});
```

### Batch Processing for Existing Orders
For migrating existing orders or processing in bulk:

```php
// Example: Recalculate shipping for all pending orders
function recalculate_pending_orders_shipping() {
    $orders = wc_get_orders([
        'status' => 'pending',
        'limit' => -1
    ]);
    
    foreach ($orders as $order) {
        $shipping = new Woo_Envios_Shipping_Method();
        $package = [
            'destination' => [
                'country'  => $order->get_shipping_country(),
                'state'    => $order->get_shipping_state(),
                'postcode' => $order->get_shipping_postcode(),
                'city'     => $order->get_shipping_city(),
                'address'  => $order->get_shipping_address_1(),
            ]
        ];
        
        $shipping->calculate_shipping($package);
        // Update order with new shipping costs
    }
}
```

## Maintenance & Best Practices

### Regular Maintenance Tasks

#### Weekly
1. Review error logs in `wp-content/uploads/woo-envios-logs/`
2. Check Google Maps API usage in Google Cloud Console
3. Verify OpenWeather API is functioning
4. Test checkout process with different addresses

#### Monthly
1. Clear old cache entries
2. Update API keys if necessary
3. Review and adjust shipping tiers based on performance data
4. Backup shipping configuration

#### Quarterly
1. Review and update dynamic pricing multipliers
2. Analyze delivery radius effectiveness
3. Check for plugin updates
4. Review server performance metrics

### Performance Optimization

#### Database Optimization
```sql
-- Optimize cache table
OPTIMIZE TABLE wp_woo_envios_geocode_cache;

-- Check index usage
SHOW INDEX FROM wp_woo_envios_geocode_cache;

-- Remove expired cache entries (automatically done by plugin)
DELETE FROM wp_woo_envios_geocode_cache WHERE expires_at < NOW();
```

#### Caching Strategy
1. **Geocoding Cache**: 30-day TTL for address coordinates
2. **Weather Cache**: 1-hour TTL for weather data
3. **