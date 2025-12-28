# TriqHub: Shipping & Radius - User Guide

## Table of Contents
1. [Overview](#overview)
2. [System Requirements](#system-requirements)
3. [Installation & Setup](#installation--setup)
4. [Configuration Guide](#configuration-guide)
5. [Shipping Methods](#shipping-methods)
6. [Dynamic Pricing Features](#dynamic-pricing-features)
7. [Troubleshooting](#troubleshooting)
8. [Advanced Usage](#advanced-usage)
9. [Maintenance](#maintenance)
10. [FAQs](#faqs)

## Overview

TriqHub: Shipping & Radius is a sophisticated WooCommerce shipping plugin that automates Brazilian postal code (CEP) coordinate collection at checkout and integrates radius-based shipping rules with Google Maps API precision. The plugin provides two primary shipping methods:

1. **Flash Delivery (Local Radius)**: Calculates shipping costs based on straight-line distance from your store location
2. **SuperFrete/Correios (National)**: Integrates with Brazilian postal services for destinations outside your delivery radius

### Key Features
- **Automatic Geocoding**: Converts Brazilian CEPs to precise coordinates using Google Maps API
- **Dynamic Pricing**: Adjusts shipping costs based on weather conditions, peak hours, and weekends
- **Real Route Calculation**: Uses Google Distance Matrix API for accurate route distances (not just straight-line)
- **Self-Healing Architecture**: Automatically creates required database tables if missing
- **Circuit Breaker Protection**: Prevents API failures from breaking your checkout process
- **Comprehensive Logging**: Detailed logs for debugging and monitoring

## System Requirements

### Minimum Requirements
- **WordPress**: 6.2 or higher
- **WooCommerce**: 5.0 or higher
- **PHP**: 7.4 or higher
- **MySQL**: 5.6 or higher
- **Memory Limit**: 128MB minimum (256MB recommended)
- **Execution Time**: 30 seconds minimum

### Required APIs
1. **Google Maps API Key** (Required for full functionality)
   - Enable: Geocoding API, Places API, Distance Matrix API
   - Billing account required (first $200/month free)

2. **OpenWeather API Key** (Optional, for weather-based pricing)
   - Free tier available (60 calls/minute, 1,000,000 calls/month)

3. **TriqHub License Key** (Optional, for automatic updates)
   - Available from your TriqHub dashboard

## Installation & Setup

### Step 1: Plugin Installation

#### Method A: WordPress Admin (Recommended)
1. Navigate to **Plugins → Add New**
2. Click **Upload Plugin**
3. Select the `triqhub-shipping-radius.zip` file
4. Click **Install Now**
5. Click **Activate Plugin**

#### Method B: Manual Installation
1. Download the plugin ZIP file
2. Extract to `/wp-content/plugins/`
3. Rename folder to `triqhub-shipping-radius`
4. Navigate to **Plugins → Installed Plugins**
5. Find "TriqHub: Shipping & Radius" and click **Activate**

### Step 2: Initial Configuration

After activation, follow these steps:

1. **Verify WooCommerce is Active**
   - The plugin requires WooCommerce. If not active, you'll see an admin notice.

2. **Configure Google Maps API**
   - Navigate to **WooCommerce → Settings → Shipping → Woo Envios**
   - Enter your Google Maps API key
   - Save changes

3. **Set Store Coordinates**
   - In the same settings page, enter your store's latitude and longitude
   - Use the map picker or enter coordinates manually

4. **Configure Shipping Tiers**
   - Define distance-based pricing tiers (e.g., 0-5km = R$10, 5-10km = R$15)

### Step 3: API Key Setup

#### Google Maps API Key
1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project or select existing
3. Enable the following APIs:
   - Geocoding API
   - Places API
   - Distance Matrix API
4. Create credentials → API Key
5. Restrict the key to:
   - HTTP referrers: Your domain
   - APIs: Only the three enabled above
6. Copy the API key and paste in plugin settings

#### OpenWeather API Key (Optional)
1. Register at [OpenWeather](https://openweathermap.org/api)
2. Get your API key from the dashboard
3. Enter in plugin settings under "Dynamic Pricing"

## Configuration Guide

### Main Settings Page

Access: **WooCommerce → Settings → Shipping → Woo Envios**

#### General Settings
| Setting | Description | Default | Required |
|---------|-------------|---------|----------|
| **Google Maps API Key** | Your Google Maps API key | Empty | Yes |
| **Store Latitude** | Your store's latitude coordinate | -18.911 | Yes |
| **Store Longitude** | Your store's longitude coordinate | -48.262 | Yes |
| **Enable Debug Logs** | Enable detailed logging for troubleshooting | Disabled | No |
| **Cache Duration** | How long to cache geocode results (days) | 30 | No |

#### Shipping Tiers Configuration
Configure distance-based pricing tiers:

1. Click **"Add Tier"** button
2. Configure each tier:
   - **Maximum Distance (km)**: Upper limit for this tier
   - **Price (R$)**: Shipping cost for this distance range
   - **Label**: Display name for admin reference

Example tier structure:
```
Tier 1: 0-5km = R$10.00
Tier 2: 5-10km = R$15.00
Tier 3: 10-15km = R$20.00
Tier 4: 15-20km = R$25.00
```

#### Dynamic Pricing Settings
| Setting | Description | Default |
|---------|-------------|---------|
| **Enable Dynamic Pricing** | Toggle all dynamic pricing features | Disabled |
| **Peak Hours** | Configure time-based price multipliers | None |
| **Weekend Multiplier** | Price increase on weekends | 1.0 (no increase) |
| **Rain Light Multiplier** | Price increase during light rain | 1.2 (+20%) |
| **Rain Heavy Multiplier** | Price increase during heavy rain | 1.5 (+50%) |
| **Maximum Multiplier** | Cap on total price increase | 2.0 (100% max) |

#### Peak Hours Configuration
1. Enable **"Enable Peak Hours"**
2. Click **"Add Peak Period"**
3. Configure each period:
   - **Name**: "Lunch Rush", "Evening Peak", etc.
   - **Start Time**: When the period begins (24h format)
   - **End Time**: When the period ends
   - **Multiplier**: Price multiplier (e.g., 1.3 = +30%)

### Shipping Zone Configuration

#### Step 1: Create Shipping Zones
1. Navigate to **WooCommerce → Settings → Shipping → Shipping Zones**
2. Click **"Add Shipping Zone"**
3. Configure:
   - **Zone Name**: "Local Delivery Area"
   - **Zone Regions**: Add countries/states/postcodes
   - **Shipping Methods**: Add "Woo Envios — Raio Escalonado"

#### Step 2: Configure Shipping Method
For each zone where you want Flash Delivery:

1. Click **"Add Shipping Method"**
2. Select **"Woo Envios — Raio Escalonado"**
3. Click **"Add Shipping Method"**
4. Configure instance settings:
   - **Enabled**: Yes/No toggle
   - **Title**: Display name at checkout (e.g., "Flash Delivery")

#### Step 3: Configure SuperFrete/Correios
1. In the same shipping zone, add **"Woo Envios Superfrete"**
2. Configure Correios settings:
   - **CEP Origin**: Your store's postal code
   - **Services**: Select PAC, SEDEX, Mini, etc.
   - **Default Dimensions**: Package dimensions for calculation
   - **Corporate Code**: If using corporate contract

## Shipping Methods

### Flash Delivery (Local Radius)

#### How It Works
1. Customer enters Brazilian CEP at checkout
2. Plugin geocodes CEP to coordinates using Google Maps
3. Calculates straight-line distance from store to customer
4. Matches distance to configured pricing tier
5. Applies dynamic pricing multipliers if enabled
6. Displays "Flash Delivery" option if within range

#### Customer Experience
- **Checkout Process**:
  ```
  1. Customer enters shipping address
  2. Plugin automatically detects CEP
  3. Coordinates are fetched in background
  4. Shipping options appear:
     - Flash Delivery: R$XX.XX (X km)
     - Correios PAC: R$XX.XX (X days)
     - Correios SEDEX: R$XX.XX (X days)
  ```

- **Visual Indicators**:
  - Flash Delivery appears first (priority sorting)
  - Distance shown in meta data (if enabled)
  - Real-time price adjustments based on conditions

#### Technical Details
- **Distance Calculation**: Google Distance Matrix API (real routes) with Haversine fallback
- **Cache Strategy**: 30-day geocode caching in custom database table
- **Fallback Logic**: Server-side geocoding if JavaScript fails
- **Session Management**: Coordinates stored in WooCommerce session

### SuperFrete/Correios (National Shipping)

#### Supported Services
- **PAC**: Economical service (3-10 business days)
- **SEDEX**: Express service (1-3 business days)
- **Mini**: Small packages up to 1kg
- **Custom**: Configure additional services

#### Configuration Options
| Setting | Description | Required |
|---------|-------------|----------|
| **CEP Origin** | Your store's postal code | Yes |
| **Company CEP** | Corporate CEP (if different) | No |
| **Service Contract** | Corporate contract number | No |
| **Password** | Contract password | No |
| **Default Weight** | Fallback weight (kg) | Yes |
| **Default Dimensions** | Length × Width × Height (cm) | Yes |
| **Declared Value** | Add product value to calculation | No |
| **Own Hand** | Additional "own hand" fee | No |
| **Receipt Notice** | Add receipt notice fee | No |

#### Calculation Process
1. Extracts destination CEP from checkout
2. Calculates package weight and dimensions
3. Queries Correios API with all parameters
4. Returns available services with costs and deadlines
5. Filters services based on configuration
6. Displays options at checkout

## Dynamic Pricing Features

### Weather-Based Pricing

#### Configuration
1. Get OpenWeather API key
2. Enable in plugin settings
3. Configure multipliers:
   - **Light Rain**: 1.2× (+20%)
   - **Heavy Rain/Storm**: 1.5× (+50%)

#### How It Works
```
1. Plugin checks weather at store coordinates
2. Detects rain conditions via OpenWeather API
3. Applies configured multiplier
4. Updates shipping price in real-time
5. Caches weather data for 1 hour
```

#### Weather Detection Logic
- **Conditions Checked**: Rain, Drizzle, Thunderstorm
- **Intensity Detection**: Uses 1-hour rainfall data
- **Fallback**: No adjustment if API fails
- **Cache**: 1-hour TTL to avoid API limits

### Time-Based Pricing

#### Peak Hours
Configure specific time periods with price increases:

```json
{
  "name": "Lunch Rush",
  "start": "11:30",
  "end": "13:30",
  "multiplier": 1.3
}
```

#### Weekend Pricing
- **Enabled**: Toggle in settings
- **Multiplier**: Configure percentage increase
- **Days**: Saturday and Sunday automatically detected

### Multiplier Stacking Logic

The plugin applies multipliers in this order:
1. **Base Tier Price**: From distance-based configuration
2. **Peak Hour Multiplier**: If current time matches configured period
3. **Weekend Multiplier**: If today is Saturday or Sunday
4. **Weather Multiplier**: If rain detected
5. **Maximum Cap**: Applied to final price

**Example Calculation**:
```
Base Price: R$15.00
Peak Hour: ×1.3 = R$19.50
Weekend: ×1.2 = R$23.40
Rain: ×1.5 = R$35.10
Maximum Cap (2.0): ×2.0 = R$30.00 (capped)
Final Price: R$30.00
```

## Troubleshooting

### Common Issues & Solutions

#### Issue 1: No Shipping Methods Appear
**Symptoms**: Checkout shows "No shipping options available"
**Possible Causes**:
1. Store coordinates not configured
2. Google Maps API key missing or invalid
3. Shipping zones not configured
4. No matching distance tiers

**Solutions**:
1. **Check Configuration**:
   ```php
   // Verify store coordinates
   WooCommerce → Settings → Shipping → Woo Envios
   ```
2. **Test API Key**:
   ```bash
   curl "https://maps.googleapis.com/maps/api/geocode/json?address=01001000&key=YOUR_API_KEY"
   ```
3. **Check Shipping Zones**:
   - Ensure zone includes customer's region
   - Verify method is enabled for the zone
4. **Review Distance Tiers**:
   - Ensure tiers cover expected distances
   - Check tier price configuration

#### Issue 2: Incorrect Distance Calculation
**Symptoms**: Shipping prices don't match actual distances
**Possible Causes**:
1. Incorrect store coordinates
2. Google API quota exceeded
3. Cache serving old coordinates
4. Haversine fallback being used

**Solutions**:
1. **Verify Coordinates**:
   - Use Google Maps to confirm store location
   - Update coordinates in settings
2. **Clear Cache**:
   ```sql
   -- Clear geocode cache
   TRUNCATE TABLE wp_woo_envios_geocode_cache;
   ```
3. **Check API Status**:
   - Monitor Google Cloud Console quotas
   - Enable debug logs to see API responses
4. **Force Distance Matrix**:
   ```php
   // Add to wp-config.php for testing
   define('WOO_ENVIOS_FORCE_DISTANCE_MATRIX', true);
   ```

#### Issue 3: Dynamic Pricing Not Working
**Symptoms**: Prices don't change with weather/time
**Possible Causes**:
1. Dynamic pricing disabled
2. API keys missing
3. Timezone misconfiguration
4. Cache issues

**Solutions**:
1. **Enable Feature**:
   - Check "Enable Dynamic Pricing" setting
2. **Verify API Keys**:
   - OpenWeather API key configured
   - Google Maps API key valid
3. **Check Timezone**:
   ```php
   // Verify WordPress timezone
   Settings → General → Timezone
   ```
4. **Clear Transients**:
   ```sql
   -- Clear weather cache
   DELETE FROM wp_options 
   WHERE option_name LIKE '_transient_woo_envios_weather_%';
   ```

#### Issue 4: Plugin Activation Errors
**Symptoms**: Fatal error on activation
**Possible Causes**:
1. PHP version incompatible
2. WooCommerce not active
3. Database permissions
4. Conflicting plugins

**Solutions**:
1. **Check Requirements**:
   - PHP ≥ 7.4
   - WordPress ≥ 6.2
   - WooCommerce ≥ 5.0
2. **Activation Order**:
   - Activate WooCommerce first
   - Then activate TriqHub plugin
3. **Database Permissions**:
   - Ensure CREATE TABLE permissions
   - Check `wp_woo_envios_geocode_cache` table
4. **Conflict Test**:
   - Deactivate other plugins
   - Switch to default theme
   - Reactivate one by one

### Debug Mode

#### Enabling Debug Logs
1. Go to **WooCommerce → Settings → Shipping → Woo Envios**
2. Check **"Enable Debug Logs"**
3. Save changes

#### Log File Location
```
/wp-content/uploads/woo-envios-logs/YYYY-MM-DD.log
```

#### Sample Log Output
```
[2024-01-15 14:30:45] [INFO] FRETE CALCULADO | Distância: 8.5 km | Base: R$ 15.00 | Final: R$ 19.50 | Multiplicadores: Pico +30%
[2024-01-15 14:30:46] [WARNING] DISTÂNCIA FORA DO ALCANCE | Distância: 25.2 km | Endereço: São Paulo, SP
[2024-01-15 14:30:47] [ERROR] FALHA API Google Maps: API key expired
```

#### Database Diagnostics
Check plugin tables:
```sql
-- Verify cache table exists
SHOW TABLES LIKE '%woo_envios_geocode_cache%';

-- Check table structure
DESCRIBE wp_woo_envios_geocode_cache;

-- View cached entries
SELECT COUNT(*) as total, 
       MIN(created_at) as oldest,
       MAX(created_at) as newest
FROM wp_woo_envios_geocode_cache;
```

### API Error Handling

#### Google Maps API Errors
| Error Code | Meaning | Solution |
|------------|---------|----------|
| `REQUEST_DENIED` | API key invalid or restricted | Verify API key and restrictions |
| `OVER_QUERY_LIMIT` | Daily quota exceeded | Check Google Cloud Console |
| `ZERO_RESULTS` | Address not found | Verify address format |
| `UNKNOWN_ERROR` | Server error | Retry with exponential backoff |

#### Circuit Breaker Status
The plugin includes circuit breaker protection:
- **Threshold**: 5 consecutive API failures
- **Action**: Falls back to default coordinates
- **Recovery**: Automatic after 5 minutes
- **Notification**: Email sent to admin

Check circuit breaker status:
```php
// Check current failure count
$failures = get_transient('woo_envios_api_failures');
$is_open = get_transient