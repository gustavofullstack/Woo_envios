# TriqHub: Shipping & Radius - Premium WooCommerce Delivery Solution

## 📋 Project Overview

**TriqHub: Shipping & Radius** is an enterprise-grade WordPress/WooCommerce plugin that revolutionizes local delivery management by implementing intelligent radius-based shipping calculations with real-time geocoding, dynamic pricing, and seamless integration with Brazilian postal services. The plugin transforms standard WooCommerce shipping into a sophisticated delivery management system capable of handling complex logistics scenarios.

**Version:** 1.2.7  
**Requires:** WordPress 6.2+, WooCommerce 5.0+, PHP 7.4+  
**License:** Proprietary (TriqHub)  
**GitHub Repository:** `gustavofullstack/triqhub-shipping-radius`

## 🏗️ Technical Stack

### Core Technologies
- **PHP:** 7.4+ (with strict typing and modern OOP patterns)
- **WordPress:** 6.2+ (fully compliant with WordPress coding standards)
- **WooCommerce:** 5.0+ (deep integration with shipping zones and methods)
- **MySQL:** 5.6+ (custom cache tables and optimized queries)

### External API Integrations
- **Google Maps Platform:** Geocoding, Places Autocomplete, Distance Matrix
- **OpenWeather API:** Real-time weather-based pricing adjustments
- **Correios/SuperFrete API:** Brazilian postal service integration
- **TriqHub License API:** License validation and update management

### Development Architecture
- **Singleton Pattern:** Main plugin class for single instance management
- **Service-Oriented Design:** Modular services for geocoding, weather, shipping
- **Circuit Breaker Pattern:** API failure protection with graceful degradation
- **Caching Strategy:** Multi-layer caching (transients, custom database tables)
- **Error Handling:** Comprehensive logging with file-based and WooCommerce logging

## 🚀 Key Features

### 1. Intelligent Radius-Based Shipping
- **Dynamic Distance Calculation:** Real-time distance calculation using Google Maps Distance Matrix API
- **Haversine Fallback:** Mathematical distance calculation when API is unavailable
- **Configurable Delivery Tiers:** Multiple distance-based pricing tiers with custom labels
- **Geofencing:** Automatic exclusion of addresses outside delivery radius

### 2. Advanced Geocoding System
- **Brazilian CEP Optimization:** Specialized handling for Brazilian postal codes
- **Google Maps Integration:** High-precision address-to-coordinate conversion
- **Caching Layer:** 30-day geocode caching with custom database table
- **Session Management:** Persistent coordinate storage in WooCommerce sessions

### 3. Dynamic Pricing Engine
- **Peak Hour Pricing:** Configurable time-based price multipliers
- **Weekend Surcharges:** Automatic weekend pricing adjustments
- **Weather-Based Pricing:** Real-time rain detection via OpenWeather API
- **Multiplier Limits:** Configurable maximum price increase caps

### 4. Multi-Carrier Integration
- **Flash Delivery:** Local radius-based delivery with dynamic pricing
- **Correios Integration:** National shipping via Brazilian postal service
- **SuperFrete Support:** Alternative carrier integration for extended coverage
- **Intelligent Rate Sorting:** Automatic prioritization of local delivery options

### 5. Enterprise-Grade Reliability
- **Circuit Breaker Pattern:** Automatic API failure detection and fallback
- **Self-Healing Architecture:** Automatic cache table creation and repair
- **Comprehensive Logging:** Detailed shipping calculation logs with rotation
- **Admin Notifications:** Email alerts for critical system failures

### 6. Developer-Friendly Architecture
- **Extensible Hook System:** WordPress actions and filters throughout
- **Type-Safe Code:** PHP 7.4+ type declarations and return types
- **PSR-Compatible:** Clean separation of concerns and dependency management
- **GitHub Updater:** Seamless updates via GitHub releases with license validation

## 📦 Installation Instructions

### Prerequisites
1. **WordPress:** Version 6.2 or higher
2. **WooCommerce:** Version 5.0 or higher (active and configured)
3. **PHP:** Version 7.4 or higher (8.0+ recommended)
4. **MySQL:** Version 5.6 or higher
5. **SSL Certificate:** Required for Google Maps API and secure checkout

### Installation Methods

#### Method 1: WordPress Admin (Recommended)
1. Navigate to **Plugins → Add New** in your WordPress admin
2. Click **Upload Plugin**
3. Select the `triqhub-shipping-radius.zip` file
4. Click **Install Now**
5. After installation, click **Activate Plugin**

#### Method 2: Manual Installation via FTP/SFTP
1. Download the latest release from GitHub
2. Extract the ZIP file to your local machine
3. Upload the `triqhub-shipping-radius` folder to `/wp-content/plugins/`
4. Navigate to **Plugins** in WordPress admin
5. Locate "TriqHub: Shipping & Radius" and click **Activate**

#### Method 3: Composer (Advanced)
```bash
composer require triqhub/shipping-radius
```

### Post-Installation Configuration

#### Step 1: API Key Configuration
1. Navigate to **WooCommerce → Settings → Shipping → Woo Envios**
2. Enter your **Google Maps API Key** (required for geocoding)
3. Optional: Configure **OpenWeather API Key** for weather-based pricing
4. Save changes

#### Step 2: Store Coordinates Setup
1. In the Woo Envios settings, set your **Store Base Coordinates**
   - Use the map picker or manually enter latitude/longitude
   - Default: Uberlândia, Brazil (-18.911, -48.262)

#### Step 3: Delivery Tiers Configuration
1. Define your **Delivery Radius Tiers**:
   - Example: 0-5km: R$10, 5-10km: R$15, 10-15km: R$20
2. Configure **Dynamic Pricing Multipliers**:
   - Peak hours, weekends, weather conditions
3. Set **Maximum Delivery Distance**

#### Step 4: Shipping Zone Assignment
1. Navigate to **WooCommerce → Settings → Shipping → Shipping Zones**
2. Create or edit a shipping zone
3. Add **"Woo Envios — Raio Escalonado"** as a shipping method
4. Configure zone restrictions and method settings

### API Key Requirements

#### Google Maps API Key
1. Create a project in [Google Cloud Console](https://console.cloud.google.com)
2. Enable the following APIs:
   - Geocoding API
   - Places API
   - Distance Matrix API
3. Create API key with appropriate restrictions
4. Minimum billing account required (Google offers free tier)

#### OpenWeather API Key (Optional)
1. Register at [OpenWeatherMap](https://openweathermap.org/api)
2. Obtain free API key (1,000 calls/day)
3. Enable "Current Weather Data" API

### Verification Steps

1. **Plugin Activation Check:**
   - Verify plugin appears in **Plugins → Installed Plugins**
   - Check for any activation errors in WordPress debug log

2. **WooCommerce Integration:**
   - Confirm shipping method appears in **WooCommerce → Settings → Shipping**
   - Verify shipping zones can be configured

3. **API Connectivity Test:**
   - Use the built-in **Test Connection** button in settings
   - Check WooCommerce System Status for any API warnings

4. **Checkout Functionality:**
   - Add a product to cart and proceed to checkout
   - Enter a Brazilian CEP and verify coordinate detection
   - Confirm shipping rates calculate correctly

### Troubleshooting Common Issues

#### Issue: Plugin Not Appearing in WooCommerce Settings
**Solution:** Ensure WooCommerce is active and meets minimum version requirements. Check WordPress debug log for activation errors.

#### Issue: Google Maps API Errors
**Solution:**
1. Verify API key is correctly entered
2. Check Google Cloud Console for API enablement
3. Ensure billing is configured
4. Verify API key restrictions allow your domain

#### Issue: No Shipping Rates Calculated
**Solution:**
1. Check store coordinates are configured
2. Verify delivery tiers are set up
3. Ensure customer address includes valid Brazilian CEP
4. Enable debug logging in plugin settings

#### Issue: Performance Problems
**Solution:**
1. Enable geocode caching
2. Increase cache TTL settings
3. Consider upgrading hosting plan
4. Implement CDN for static assets

### Updating the Plugin

#### Automatic Updates (Recommended)
The plugin includes GitHub Updater integration. When a new version is available:
1. Navigate to **Dashboard → Updates**
2. Check for "TriqHub: Shipping & Radius" update
3. Click **Update Now**

#### Manual Update
1. Deactivate current version
2. Delete plugin via WordPress admin
3. Install new version using preferred method
4. Reactivate plugin (settings preserved)

### Development Installation

For developers contributing to the project:

```bash
# Clone repository
git clone https://github.com/gustavofullstack/triqhub-shipping-radius.git

# Install dependencies
composer install

# Set up development environment
cp .env.example .env

# Configure local WordPress instance
# Ensure WooCommerce is installed and active
```

### System Requirements Verification

Run the following checks to ensure compatibility:

```php
// Check PHP version
version_compare(PHP_VERSION, '7.4.0', '>=');

// Check WordPress version
version_compare($wp_version, '6.2', '>=');

// Check WooCommerce version
defined('WC_VERSION') && version_compare(WC_VERSION, '5.0', '>=');

// Check required extensions
extension_loaded('curl');
extension_loaded('json');
extension_loaded('mbstring');
```

## 🔧 Configuration Examples

### Basic Configuration Snippet
```php
// Example: Setting up delivery tiers via code
add_filter('woo_envios_delivery_tiers', function($tiers) {
    return [
        [
            'min' => 0,
            'max' => 5,
            'price' => 10.00,
            'label' => 'Entrega Flash (0-5km)'
        ],
        [
            'min' => 5,
            'max' => 10,
            'price' => 15.00,
            'label' => 'Entrega Padrão (5-10km)'
        ]
    ];
});
```

### Dynamic Pricing Configuration
```php
// Example: Custom peak hour configuration
add_filter('woo_envios_peak_hours', function($hours) {
    return [
        [
            'name' => 'Almoço',
            'start' => '11:30',
            'end' => '13:30',
            'multiplier' => 1.2
        ],
        [
            'name' => 'Jantar',
            'start' => '18:00',
            'end' => '20:00',
            'multiplier' => 1.3
        ]
    ];
});
```

## 📊 Performance Optimization

### Recommended Server Configuration
- **PHP Memory Limit:** 256MB minimum
- **Max Execution Time:** 30 seconds
- **MySQL Query Cache:** Enabled
- **OPCache:** Enabled with sufficient memory

### Caching Strategy
1. **Geocode Cache:** 30-day TTL in custom database table
2. **Weather Cache:** 1-hour TTL via WordPress transients
3. **Session Cache:** WooCommerce session-based coordinate storage
4. **Object Cache:** Consider Redis or Memcached for high-traffic sites

## 🔒 Security Considerations

1. **API Key Protection:** Never commit API keys to version control
2. **Input Validation:** All user inputs are sanitized and validated
3. **Nonce Verification:** All AJAX requests include WordPress nonces
4. **SQL Injection Protection:** Prepared statements for all database queries
5. **XSS Prevention:** Output escaping throughout template rendering

## 📈 Monitoring and Maintenance

### Regular Checks
1. **API Key Validity:** Monthly verification of Google Maps API key
2. **Cache Performance:** Monitor cache hit rates and storage usage
3. **Error Logs:** Review Woo Envios logs for shipping calculation issues
4. **Update Compliance:** Ensure compatibility with latest WooCommerce versions

### Backup Strategy
- **Configuration Backup:** Export plugin settings before major updates
- **Database Backup:** Include `wp_woo_envios_geocode_cache` table in backups
- **Log Rotation:** Implement log rotation for shipping calculation logs

## 🤝 Support and Resources

### Documentation
- **Technical Documentation:** `/docs/` directory in plugin
- **API Reference:** Complete hook and filter documentation
- **Architecture Guide:** System design and data flow diagrams

### Support Channels
- **GitHub Issues:** Bug reports and feature requests
- **Email Support:** support@triqhub.com
- **Developer Documentation:** Inline code documentation

### Community
- **WordPress Plugin Directory:** User reviews and ratings
- **GitHub Discussions:** Community support and Q&A
- **Contributor Guidelines:** Available in repository

---

**Note:** This plugin is actively maintained by TriqHub with regular updates for security, performance, and feature enhancements. Always test updates in a staging environment before deploying to production.