# TriqHub: Shipping & Radius - User Guide

## Table of Contents
1. [Overview](#overview)
2. [System Requirements](#system-requirements)
3. [Installation](#installation)
4. [Detailed Configuration](#detailed-configuration)
5. [Shipping Methods](#shipping-methods)
6. [Common Use Cases](#common-use-cases)
7. [Troubleshooting & FAQ](#troubleshooting--faq)
8. [Changelog](#changelog)
9. [Technical Support](#technical-support)

---

## Overview

TriqHub: Shipping & Radius is a sophisticated WooCommerce shipping plugin designed for Brazilian e-commerce businesses. It automates coordinate collection during checkout using Brazilian postal codes (CEP) and integrates radius-based shipping rules with Google Maps API for maximum precision. The plugin offers two primary shipping methods:

1. **Flash Delivery (Radius-Based)**: Local delivery calculated by distance from your store
2. **SuperFrete Integration**: National shipping via Correios (PAC/SEDEX/Mini) for customers outside your delivery radius

### Key Features
- **Automatic Geocoding**: Converts Brazilian CEPs to precise coordinates using Google Maps API
- **Dynamic Pricing**: Adjusts delivery costs based on distance, weather conditions, and peak hours
- **Intelligent Fallback**: Seamlessly switches between local and national shipping methods
- **Real-time Distance Calculation**: Uses Google Distance Matrix API for accurate route distances
- **Weather Integration**: Adjusts pricing based on rain conditions via OpenWeather API
- **Self-Healing Architecture**: Automatic cache table creation and error recovery
- **GitHub Auto-Updates**: Seamless updates via GitHub repository

---

## System Requirements

### Minimum Requirements
- **WordPress**: 6.2 or higher
- **WooCommerce**: 5.0 or higher
- **PHP**: 7.4 or higher (8.0+ recommended)
- **MySQL**: 5.6 or higher
- **Memory Limit**: 128MB minimum (256MB recommended)
- **cURL**: Enabled with SSL support

### Required API Keys
1. **Google Maps API Key** (Required)
   - Enable: Maps JavaScript API, Geocoding API, Distance Matrix API
   - Daily quota: Minimum 1,000 requests/day recommended

2. **SuperFrete API Token** (Required for national shipping)
   - Obtain from: [SuperFrete Dashboard](https://app.superfrete.com)

3. **OpenWeather API Key** (Optional, for weather-based pricing)
   - Obtain from: [OpenWeather](https://openweathermap.org/api)

### Server Configuration
```
max_execution_time = 30
memory_limit = 256M
upload_max_filesize = 32M
post_max_size = 32M
```

---

## Installation

### Method 1: WordPress Admin Panel (Recommended)
1. Download the latest release from the GitHub repository
2. Navigate to **WordPress Admin → Plugins → Add New**
3. Click **Upload Plugin** and select the downloaded ZIP file
4. Click **Install Now** and then **Activate Plugin**
5. Verify WooCommerce is installed and active

### Method 2: Manual Installation via FTP
1. Download the latest release ZIP file
2. Extract the contents to your local machine
3. Connect to your server via FTP/SFTP
4. Upload the `triqhub-shipping-radius` folder to `/wp-content/plugins/`
5. Navigate to **WordPress Admin → Plugins**
6. Locate "TriqHub: Shipping & Radius" and click **Activate**

### Method 3: Git Clone (Developers)
```bash
cd /path/to/wp-content/plugins/
git clone https://github.com/gustavofullstack/triqhub-shipping-radius.git
cd triqhub-shipping-radius
composer install  # If using Composer dependencies
```

### Post-Installation Verification
1. Check **WooCommerce → Status → Logs** for any initialization errors
2. Verify the plugin appears in **WooCommerce → Settings → Shipping**
3. Run the built-in test: Navigate to `/wp-content/plugins/triqhub-shipping-radius/test-full-integration.php` via SSH

---

## Detailed Configuration

### Step 1: Configure Google Maps API
1. **Obtain API Key**:
   - Visit [Google Cloud Console](https://console.cloud.google.com)
   - Create a new project or select existing
   - Navigate to **APIs & Services → Library**
   - Enable: **Maps JavaScript API**, **Geocoding API**, **Distance Matrix API**
   - Go to **Credentials → Create Credentials → API Key**
   - Restrict the key to your domain and required APIs

2. **Configure in WordPress**:
   - Navigate to **WooCommerce → Settings → Shipping → Woo Envios**
   - Enter your Google Maps API Key in the designated field
   - Click **Save Changes**

### Step 2: Configure Store Coordinates
1. **Set Default Location**:
   - In the same settings page, enter your store's coordinates:
     - **Latitude**: -18.911 (default Uberlândia)
     - **Longitude**: -48.262 (default Uberlândia)
   - Alternatively, use the map picker to select your location visually

2. **Delivery Radius Configuration**:
   ```
   Distance Tiers (Example):
   ┌─────────────┬─────────────┬─────────────────────┐
   │ Distance (km) │ Base Price │ Label              │
   ├─────────────┼─────────────┼─────────────────────┤
   │ 0-10         │ R$ 5.00     │ Bairro             │
   │ 10-20        │ R$ 8.00     │ Zona Urbana        │
   │ 20-30        │ R$ 12.00    │ Região Metropolitana │
   │ 30+          │ Not Available │ Fora da área      │
   └─────────────┴─────────────┴─────────────────────┘
   ```

### Step 3: Configure SuperFrete Integration
1. **Obtain API Token**:
   - Log in to [SuperFrete Dashboard](https://app.superfrete.com)
   - Navigate to **Configurações → API**
   - Generate a new token or use existing
   - Token format: `eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...`

2. **Configure in Plugin**:
   - Navigate to **WooCommerce → Settings → Shipping → Woo Envios**
   - Enter your SuperFrete API Token
   - Configure default package dimensions:
     - Height: 10cm
     - Width: 15cm
     - Length: 20cm
     - Weight: 1kg

3. **Enable Services**:
   - Select which Correios services to offer:
     - **PAC** (Service code: 1)
     - **SEDEX** (Service code: 2)
     - **Mini Envios** (Service code: 17)

### Step 4: Dynamic Pricing Configuration
1. **Peak Hours Pricing**:
   ```
   Peak Hours Configuration:
   ┌──────────────┬─────────────┬─────────────────┐
   │ Day          │ Start Time  │ End Time        │ Multiplier │
   ├──────────────┼─────────────┼─────────────────┤
   │ Monday-Friday│ 17:00       │ 20:00           │ 1.3x       │
   │ Saturday     │ 09:00       │ 13:00           │ 1.5x       │
   │ Sunday       │ Disabled    │ Disabled        │ -          │
   └──────────────┴─────────────┴─────────────────┘
   ```

2. **Weekend Multiplier**:
   - Saturday: 1.2x base price
   - Sunday: 1.5x base price (if delivering)

3. **Weather-Based Pricing**:
   - Obtain OpenWeather API key
   - Configure rain multipliers:
     - Light rain (≤5mm/h): 1.2x
     - Heavy rain (>5mm/h): 1.5x
     - Thunderstorm: 1.5x

### Step 5: Advanced Settings
1. **Cache Configuration**:
   - Geocode cache duration: 30 days (recommended)
   - Weather cache duration: 1 hour
   - Clear cache: Available in admin tools

2. **Fallback Behavior**:
   - Enable/disable server-side geocoding fallback
   - Configure maximum fallback attempts
   - Set fallback timeout (default: 5 seconds)

3. **Debug Mode**:
   - Enable detailed logging
   - Log level: Debug, Info, Warning, Error
   - Log retention: 7 days (automatically purged)

---

## Shipping Methods

### Method 1: Flash Delivery (Radius-Based)
**Description**: Local delivery calculated by straight-line distance from your store using Google Maps API.

**Calculation Process**:
1. Customer enters CEP at checkout
2. Plugin geocodes CEP to coordinates (lat/lng)
3. Calculates route distance using Google Distance Matrix API
4. Applies distance tier pricing
5. Adjusts for dynamic factors (weather, peak hours, weekends)
6. Displays final price to customer

**Configuration Example**:
```php
// Distance tiers configuration
$tiers = [
    [
        'distance' => '10',    // Kilometers
        'price'    => '5.00',  // Base price in BRL
        'label'    => 'Até 10km'
    ],
    [
        'distance' => '20',
        'price'    => '8.00',
        'label'    => '10-20km'
    ],
    // Add more tiers as needed
];
```

### Method 2: SuperFrete Integration
**Description**: National shipping via Correios for customers outside your delivery radius.

**Supported Services**:
- **PAC** (Código: 1): Economical, 5-10 business days
- **SEDEX** (Código: 2): Express, 1-3 business days
- **Mini Envios** (Código: 17): Small packages, up to 1kg

**API Integration Flow**:
1. Plugin detects customer outside delivery radius
2. Sends package details to SuperFrete API
3. Receives real-time quotes for available services
4. Displays options sorted by price/delivery time
5. Customer selects preferred option

**Package Requirements**:
- Maximum weight: 30kg (PAC/SEDEX), 1kg (Mini)
- Maximum dimensions: 105cm (sum of dimensions)
- Prohibited items: Follow Correios regulations

---

## Common Use Cases

### Use Case 1: Local Restaurant Delivery
**Scenario**: Restaurant in São Paulo wants to deliver within 15km radius.

**Configuration**:
```
Store Coordinates: -23.5505, -46.6333 (São Paulo)
Distance Tiers:
- 0-5km: R$ 8.00 (Bairro)
- 5-10km: R$ 12.00 (Zona)
- 10-15km: R$ 18.00 (Distante)
- 15km+: Not available

Peak Hours: 18:00-21:00 (1.4x multiplier)
Weekend: Saturday (1.3x), Sunday (closed)
```

### Use Case 2: E-commerce Store with National Reach
**Scenario**: Online store in Uberlândia serves local customers with flash delivery and national customers via Correios.

**Configuration**:
```
Local Delivery (Flash):
- Radius: 30km
- Base price: R$ 10.00
- Max distance: 30km

National Shipping (SuperFrete):
- Origin CEP: 38405-320
- Services: PAC, SEDEX, Mini
- Insurance: Optional
- Delivery estimate: Real-time from API
```

### Use Case 3: Weather-Aware Delivery Service
**Scenario**: Delivery service that adjusts prices during rain.

**Configuration**:
```
OpenWeather API: Enabled
Rain Multipliers:
- Light rain: 1.2x
- Heavy rain: 1.5x
- Thunderstorm: No delivery (safety)

Cache Duration: 1 hour
Fallback: Use historical weather data if API fails
```

### Use Case 4: Multi-Zone Shipping
**Scenario**: Business with multiple pickup locations.

**Implementation**:
1. Create multiple shipping zones in WooCommerce
2. Assign different store coordinates to each zone
3. Configure unique distance tiers per zone
4. Use zone restrictions based on customer CEP prefix

---

## Troubleshooting & FAQ

### Common Issues and Solutions

#### Issue 1: "No shipping methods available" at checkout
**Possible Causes**:
1. Google Maps API key not configured
2. Store coordinates not set
3. WooCommerce shipping zone not configured
4. Customer CEP cannot be geocoded

**Solutions**:
1. Verify API key in **WooCommerce → Settings → Shipping → Woo Envios**
2. Check store coordinates are saved
3. Ensure shipping zone includes customer's region
4. Test geocoding with the customer's CEP using Google Maps Geocoding API directly

#### Issue 2: SuperFrete API returning errors
**Error Messages**:
- `401 Unauthorized`: Invalid or expired token
- `400 Bad Request`: Invalid package dimensions
- `404 Not Found`: Invalid CEP or service unavailable

**Solutions**:
1. Regenerate SuperFrete API token
2. Verify package dimensions are within limits
3. Check if CEP exists in Correios database
4. Test API directly using `test-superfrete.php`

#### Issue 3: Distance calculation inaccurate
**Causes**:
1. Using straight-line distance instead of route distance
2. Google Maps API quota exceeded
3. Incorrect store coordinates

**Solutions**:
1. Ensure Google Distance Matrix API is enabled
2. Check API usage in Google Cloud Console
3. Verify coordinates using Google Maps
4. Enable debug logging to see actual API responses

#### Issue 4: Plugin not updating automatically
**Causes**:
1. GitHub updater not configured
2. File permissions preventing updates
3. WordPress update filters blocking

**Solutions**:
1. Verify `plugin-update-checker` is included
2. Check `wp-content/plugins/` directory permissions (755)
3. Disable other update plugins temporarily
4. Manual update via GitHub releases

### Frequently Asked Questions

#### Q1: Can I use this plugin outside Brazil?
**A**: While optimized for Brazilian CEPs, the plugin can work with international addresses. However, SuperFrete integration is Brazil-specific. For international use, you would need to modify or replace the SuperFrete integration.

#### Q2: How accurate is the distance calculation?
**A**: With Google Maps API enabled, accuracy is within 5-10% of actual driving distance. Without API, it uses Haversine formula for straight-line distance (less accurate for route planning).

#### Q3: What happens if Google Maps API quota is exceeded?
**A**: The plugin automatically falls back to:
1. Cached geocode results (up to 30 days)
2. Haversine distance calculation
3. Server-side geocoding via alternative services

#### Q4: Can I add custom distance tiers?
**A**: Yes, via the admin interface. Navigate to **WooCommerce → Settings → Shipping → Woo Envios → Distance Tiers**. Add as many tiers as needed with custom labels and prices.

#### Q5: How does weather-based pricing work?
**A**: The plugin checks OpenWeather API every hour. If rain is detected at the delivery coordinates, it applies configured multipliers. Weather data is cached for 1 hour to reduce API calls.

#### Q6: Is there a weight limit for Flash Delivery?
**A**: No inherent limit, but consider your delivery capacity. The plugin doesn't restrict by weight for local delivery, but SuperFrete has strict weight limits.

#### Q7: Can I disable Flash Delivery on weekends?
**A**: Yes, configure weekend multipliers to very high values or disable the shipping method entirely for specific days using custom code hooks.

#### Q8: How do I clear the geocode cache?
**A**: Two methods:
1. Automatic: Cache expires after 30 days
2. Manual: Run SQL query: `TRUNCATE TABLE wp_woo_envios_geocode_cache`

### Debugging Tools

#### Built-in Test Scripts
1. **SuperFrete API Test**:
   ```bash
   php /path/to/plugin/test-superfrete.php
   ```

2. **Full Integration Test**:
   ```bash
   php /path/to/plugin/test-full-integration.php
   ```

3. **Plugin Loading Test**:
   ```bash
   php /path/to/plugin/test-plugin-loading.php
   ```

#### WordPress Debug Log
Enable in `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

Check logs at: `/wp-content/debug.log`

#### WooCommerce System Status
Navigate to: **WooCommerce → Status → Logs**
Look for: `woo-envios-*` log files

---

## Changelog

### Version 1.0.0 (Current Release)
**Release Date**: [Current Date]
**Status**: Stable Production Release

#### New Features
- **Complete Rewrite**: Modern PHP 7.4+ architecture with proper namespacing
- **Google Maps Integration**: Full integration with Geocoding, Distance Matrix, and Maps JavaScript APIs
- **SuperFrete API**: Replaced Melhor Envio with SuperFrete for Correios integration
- **Dynamic Pricing**: Weather, peak hours, and weekend multipliers
- **Self-Healing**: Automatic cache table creation and error recovery
- **GitHub Auto-Updates**: Seamless updates via GitHub repository

#### Technical Improvements
- **Singleton Pattern**: Proper plugin initialization with singleton instance
- **Dependency Management**: Explicit file loading order to prevent class not found errors
- **Session Management**: Optimized coordinate storage in WooCommerce session
- **Cache System**: Database table for geocode results with automatic expiration
- **Error Handling**: Comprehensive try-catch blocks with user-friendly error