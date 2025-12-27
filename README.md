# TriqHub: Shipping & Radius

## Overview

**TriqHub: Shipping & Radius** is a sophisticated WooCommerce shipping plugin that automates Brazilian postal code (CEP) coordinate collection at checkout and implements intelligent radius-based shipping rules. The plugin leverages Google Maps API for maximum geocoding precision and offers dynamic pricing based on distance, weather conditions, and peak hours.

**Version:** 1.2.8  
**Requires:** WordPress 6.2+, PHP 7.4+, WooCommerce 5.0+  
**License:** Proprietary (TriqHub)

## Technical Stack

### Core Technologies
- **PHP:** 7.4+ (with strict typing and modern OOP patterns)
- **WordPress:** 6.2+ (utilizing WordPress hooks, transients, and database APIs)
- **WooCommerce:** 5.0+ (extending WC_Shipping_Method and integrating with shipping zones)
- **JavaScript:** Vanilla JS with Google Maps Places API for frontend address autocomplete
- **Database:** MySQL with custom cache tables for geocoding results
- **Composer:** For dependency management (plugin-update-checker)

### External API Integrations
- **Google Maps Platform:**
  - Geocoding API (address to coordinates)
  - Places API (address autocomplete)
  - Distance Matrix API (route distance calculation)
- **OpenWeather API:** Real-time weather data for dynamic pricing
- **Correios/SuperFrete API:** Brazilian postal service integration
- **TriqHub License API:** License validation and update management

### Architecture Patterns
- Singleton pattern for plugin initialization
- Factory pattern for shipping method instantiation
- Circuit breaker pattern for API failure handling
- Strategy pattern for different shipping calculation methods
- Repository pattern for geocode cache management

## Key Features

### 1. Intelligent Radius-Based Shipping
- **Haversine Formula:** Calculates straight-line distance between store and customer coordinates
- **Google Distance Matrix:** Optional real-route distance calculation for accurate delivery estimates
- **Configurable Tiers:** Define multiple distance ranges with corresponding pricing
- **Automatic Fallback:** Falls back to Haversine calculation if Google API fails

### 2. Brazilian CEP Optimization
- **CEP Validation:** Brazilian postal code format validation and normalization
- **Automatic Geocoding:** Converts CEP to precise coordinates using Google Maps API
- **Session Caching:** Stores geocoded coordinates in WooCommerce session to reduce API calls
- **Server-Side Fallback:** Attempts server-side geocoding when session data is missing

### 3. Dynamic Pricing Engine
- **Peak Hour Multipliers:** Configure time-based pricing for busy periods
- **Weather-Based Adjustments:** Integrates with OpenWeather API to adjust prices during rain
- **Weekend Surcharges:** Optional weekend pricing multipliers
- **Maximum Multiplier Limits:** Prevent excessive price increases with configurable caps

### 4. Multi-Method Shipping Support
- **Flash Delivery:** Local radius-based delivery with dynamic pricing
- **Correios Integration:** National shipping via Brazilian postal service
- **SuperFrete Support:** Alternative shipping method for destinations outside local radius
- **Intelligent Sorting:** Automatically prioritizes Flash Delivery over other methods

### 5. Advanced Error Handling & Resilience
- **Circuit Breaker Pattern:** Automatically disables Google Maps API after consecutive failures
- **Comprehensive Logging:** Detailed log system with automatic cleanup (7-day retention)
- **Admin Notifications:** Email alerts for critical API failures
- **Self-Healing Database:** Automatically creates missing cache tables on plugin load

### 6. Google Maps Integration
- **Address Autocomplete:** Real-time address suggestions during checkout
- **Geocode Caching:** 30-day cache for geocoding results to reduce API costs
- **API Key Validation:** Format validation and circuit breaker protection
- **Distance Matrix:** Accurate route distance calculation for shipping

### 7. Administrative Features
- **Store Coordinate Management:** Set store location via Google Maps interface
- **Shipping Tier Configuration:** Visual interface for distance-based pricing tiers
- **Dynamic Pricing Settings:** Configure weather, peak hour, and weekend multipliers
- **Log Management:** Enable/disable logging and view shipping calculations
- **License Management:** TriqHub license key validation and update management

## Installation Instructions

### Prerequisites
1. WordPress 6.2 or higher
2. WooCommerce 5.0 or higher
3. PHP 7.4 or higher with the following extensions:
   - cURL
   - JSON
   - MySQLi
4. SSL certificate (recommended for production)
5. Google Maps API key with the following APIs enabled:
   - Geocoding API
   - Places API
   - Distance Matrix API

### Step 1: Plugin Installation

#### Method A: WordPress Admin (Recommended)
1. Navigate to **Plugins → Add New** in your WordPress admin
2. Click **Upload Plugin**
3. Upload the `triqhub-shipping-radius.zip` file
4. Click **Install Now**
5. After installation, click **Activate**

#### Method B: Manual Installation via FTP
1. Download the plugin ZIP file
2. Extract the contents to a folder named `triqhub-shipping-radius`
3. Upload the folder to `/wp-content/plugins/` on your server
4. Navigate to **Plugins** in WordPress admin
5. Find **TriqHub: Shipping & Radius** and click **Activate**

### Step 2: Google Maps API Configuration

1. **Create Google Cloud Project:**
   - Go to [Google Cloud Console](https://console.cloud.google.com/)
   - Create a new project or select existing one
   - Enable billing (required for Google Maps APIs)

2. **Enable Required APIs:**
   - Geocoding API
   - Places API
   - Distance Matrix API

3. **Create API Key:**
   - Navigate to **APIs & Services → Credentials**
   - Click **Create Credentials → API Key**
   - Restrict the key to:
     - HTTP referrers (your website domain)
     - Enabled APIs (only the three listed above)

4. **Configure in WordPress:**
   - Go to **WooCommerce → Woo Envios → Google Maps**
   - Paste your API key in the designated field
   - Click **Save Changes**

### Step 3: Plugin Configuration

#### Basic Setup
1. **Store Location:**
   - Navigate to **WooCommerce → Woo Envios → Configurações**
   - Enter your store address or use the map to set coordinates
   - Click **Save Coordinates**

2. **Shipping Tiers:**
   - Go to **WooCommerce → Woo Envios → Faixas de Distância**
   - Add distance ranges (e.g., 0-5km, 5-10km, 10-15km)
   - Set prices for each tier
   - Configure maximum delivery radius

3. **Shipping Zones (WooCommerce):**
   - Go to **WooCommerce → Settings → Shipping → Shipping Zones**
   - Create or edit a shipping zone
   - Add **"Woo Envios — Raio Escalonado"** as a shipping method
   - Configure method settings (title, enabled status)

#### Advanced Configuration
1. **Dynamic Pricing:**
   - Navigate to **WooCommerce → Woo Envios → Preços Dinâmicos**
   - Configure peak hours and multipliers
   - Set weekend pricing if desired
   - Enter OpenWeather API key for weather-based pricing
   - Set maximum multiplier limit

2. **Logging:**
   - Go to **WooCommerce → Woo Envios → Logs**
   - Enable/disable logging
   - View recent shipping calculations
   - Manually clear logs if needed

3. **Correios Integration:**
   - Navigate to **WooCommerce → Woo Envios → Correios**
   - Enter Correios credentials (if using official API)
   - Configure package dimensions and weights
   - Set fallback behavior for addresses outside radius

### Step 4: License Activation (Optional)

1. **Obtain License Key:**
   - Purchase license from TriqHub website
   - Receive license key via email

2. **Activate License:**
   - Go to **WooCommerce → Woo Envios → Licença**
   - Enter your license key
   - Click **Ativar Licença**
   - Automatic updates will now be enabled

### Step 5: Testing

1. **Test Checkout Flow:**
   - Add a product to cart
   - Proceed to checkout
   - Enter a Brazilian CEP (e.g., 30130-005 for Belo Horizonte)
   - Verify address autocomplete works
   - Check that Flash Delivery appears with correct pricing

2. **Verify Shipping Calculations:**
   - Test addresses at different distances from store
   - Verify tier-based pricing works correctly
   - Test during peak hours to see dynamic pricing
   - Check logs for calculation details

3. **Admin Verification:**
   - Check **Woo Envios → Logs** for any errors
   - Verify cache table exists in database
   - Test Google Maps API connectivity

## File Structure

```
triqhub-shipping-radius/
├── triqhub-shipping-radius.php          # Main plugin file
├── woo-envios.php                       # Legacy main file (deprecated)
├── plugin-update.json                   # Update manifest
├── composer.json                        # Dependency management
├── assets/
│   ├── css/
│   │   ├── woo-envios-frontend.css     # Frontend styles
│   │   └── triqhub-admin.css           # Admin styles
│   └── js/
│       └── woo-envios-checkout.js      # Checkout integration
├── includes/
│   ├── core/
│   │   └── class-triqhub-connector.php # TriqHub license connector
│   ├── Services/
│   │   ├── Geocoder.php                # Geocoding service
│   │   ├── class-woo-envios-correios.php
│   │   └── class-woo-envios-superfrete-shipping-method.php
│   ├── class-woo-envios-logger.php     # Logging system
│   ├── class-woo-envios-google-maps.php # Google Maps integration
│   ├── class-woo-envios-google-maps-admin.php
│   ├── class-woo-envios-weather.php    # Weather service
│   ├── class-woo-envios-admin.php      # Admin interface
│   ├── class-woo-envios-checkout.php   # Checkout integration
│   ├── class-woo-envios-shipping.php   # Main shipping method
│   └── class-woo-envios-updater.php    # Update handler
└── vendor/                             # Composer dependencies
```

## Database Schema

### Custom Tables
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
) CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### WordPress Options
- `woo_envios_google_maps_api_key` - Google Maps API key
- `woo_envios_store_lat` - Store latitude
- `woo_envios_store_lng` - Store longitude
- `woo_envios_distance_tiers` - Configured distance tiers
- `woo_envios_dynamic_pricing_enabled` - Dynamic pricing toggle
- `woo_envios_peak_hours` - Peak hour configurations
- `woo_envios_weather_api_key` - OpenWeather API key
- `triqhub_license_key` - License key for updates
- `woo_envios_enable_logs` - Logging enable/disable

## Troubleshooting

### Common Issues

#### 1. Plugin Not Appearing in Shipping Methods
- **Cause:** WooCommerce not activated or version too old
- **Solution:** Ensure WooCommerce 5.0+ is installed and activated

#### 2. Google Maps Not Working
- **Cause:** Invalid API key or APIs not enabled
- **Solution:**
  1. Verify API key format starts with "AIza"
  2. Check all three APIs are enabled in Google Cloud
  3. Verify API key restrictions allow your domain
  4. Check billing is enabled on Google Cloud project

#### 3. No Shipping Rates Calculated
- **Cause:** Store coordinates not set or invalid address
- **Solution:**
  1. Set store coordinates in Woo Envios settings
  2. Test with a valid Brazilian CEP
  3. Check Woo Envios logs for errors
  4. Verify shipping zone includes the address

#### 4. Performance Issues
- **Cause:** Excessive API calls or missing cache
- **Solution:**
  1. Enable geocode caching (default: 30 days)
  2. Check cache table exists and is populated
  3. Consider increasing cache TTL for stable addresses
  4. Monitor Google Maps API usage in Cloud Console

### Debug Mode
Enable detailed logging for troubleshooting:
1. Go to **WooCommerce → Woo Envios → Logs**
2. Enable "Ativar Logs"
3. Reproduce the issue
4. Check log files at `/wp-content/uploads/woo-envios-logs/`

## Support

### Documentation
- Full documentation available at [docs.triqhub.com](https://docs.triqhub.com)
- API reference and developer guides included

### Support Channels
- **Email:** support@triqhub.com
- **Website:** [triqhub.com](https://triqhub.com)
- **GitHub Issues:** For bug reports and feature requests

### System Requirements Updates
Always check the [TriqHub website](https://triqhub.com/requirements) for the latest system requirements and compatibility information.

## Changelog

### Version 1.2.8 (Current)
- Enhanced circuit breaker pattern for API failure handling
- Improved server-side geocoding fallback
- Added weather-based dynamic pricing
- Fixed session signature matching issues
- Enhanced logging with automatic cleanup

### Version 1.2.7
- Added Google Distance Matrix integration
- Improved address autocomplete accuracy
- Enhanced admin interface for tier management
- Fixed compatibility with WooCommerce 8.0+

### Version 1.2.6
- Initial public release
- Brazilian CEP geocoding
- Radius-based shipping tiers
- Basic dynamic pricing engine

## License & Updates

This plugin requires a valid TriqHub license for:
- Automatic updates via GitHub
- Priority support
- Access to premium features

License keys can be purchased at [triqhub.com/pricing](https://triqhub.com/pricing).

---

**TriqHub: Shipping & Radius** is developed and maintained by **TriqHub**. For custom development or enterprise solutions, contact sales@triqhub.com.