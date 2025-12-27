# TriqHub: Shipping & Radius

## Overview

**TriqHub: Shipping & Radius** is a sophisticated WooCommerce shipping plugin that automates Brazilian postal code (CEP) coordinate collection at checkout and integrates radius-based shipping rules with Google Maps API for maximum precision. The plugin provides intelligent local delivery calculations, dynamic pricing based on multiple factors, and seamless integration with Brazilian shipping services.

**Version:** 1.2.9  
**Requires:** WordPress 6.2+, PHP 7.4+, WooCommerce 5.0+  
**License:** Proprietary (TriqHub)

## Technical Stack

### Core Technologies
- **PHP 7.4+** - Modern PHP with strict typing and OOP patterns
- **WordPress 6.2+** - Latest WordPress standards and APIs
- **WooCommerce 5.0+** - Full integration with WooCommerce shipping ecosystem
- **MySQL 5.6+** - Custom database tables for caching and performance

### External API Integrations
- **Google Maps Platform** - Geocoding, Places Autocomplete, Distance Matrix
- **OpenWeather API** - Real-time weather data for dynamic pricing
- **SuperFrete/Correios API** - Brazilian shipping service integration
- **TriqHub License API** - License validation and update management

### Development Tools
- **Composer** - Dependency management (plugin-update-checker)
- **GitHub Actions** - CI/CD and automated releases
- **DeepSeek API** - AI-powered documentation generation
- **Mermaid.js** - Architecture and flow diagram generation

## Key Features

### 🚀 Intelligent Shipping Calculation
- **Radius-Based Delivery**: Calculate shipping costs based on straight-line distance from store location
- **Google Maps Integration**: Real route distance calculation using Distance Matrix API
- **Multi-Tier Pricing**: Configurable distance tiers with custom pricing
- **Dynamic Pricing**: Adjust prices based on weather, peak hours, and weekends

### 📍 Advanced Address Handling
- **Brazilian CEP Support**: Specialized handling for Brazilian postal codes
- **Google Places Autocomplete**: Address validation and auto-completion at checkout
- **Server-Side Geocoding**: Fallback geocoding when JavaScript fails
- **Coordinate Caching**: Persistent cache for geocoding results (30-day TTL)

### ⚡ Performance & Reliability
- **Circuit Breaker Pattern**: Automatic API failure detection and graceful degradation
- **Intelligent Caching**: Multi-layer caching for API responses and calculations
- **Self-Healing Database**: Automatic table creation and repair
- **Error Logging**: Comprehensive logging system with file rotation

### 🎯 Dynamic Pricing Engine
- **Weather-Based Pricing**: Adjust delivery costs based on rain conditions
- **Peak Hour Multipliers**: Configurable pricing for busy periods
- **Weekend Surcharges**: Additional charges for weekend deliveries
- **Maximum Multiplier Limits**: Prevent excessive price increases

### 🔧 Administrative Features
- **Google Maps API Management**: Centralized API key configuration
- **Shipping Tier Configuration**: Visual interface for distance-based pricing
- **Real-Time Debugging**: Detailed logging and error reporting
- **Bulk Operations**: Clear cache, reset coordinates, and system diagnostics

### 🔄 Integration Ecosystem
- **WooCommerce Native**: Full compatibility with WooCommerce zones and methods
- **SuperFrete Integration**: Seamless fallback to Brazilian shipping services
- **TriqHub Connector**: License management and automatic updates
- **GitHub Updater**: Direct updates from GitHub repository

## Installation Instructions

### Prerequisites
1. WordPress 6.2 or higher
2. WooCommerce 5.0 or higher
3. PHP 7.4 or higher with the following extensions:
   - cURL
   - JSON
   - MySQLi
4. MySQL 5.6 or higher
5. SSL certificate (recommended for production)

### Installation Methods

#### Method 1: WordPress Admin (Recommended)
1. Navigate to **Plugins → Add New** in your WordPress admin
2. Click **Upload Plugin**
3. Select the `triqhub-shipping-radius.zip` file
4. Click **Install Now**
5. After installation, click **Activate Plugin**

#### Method 2: Manual Installation via FTP
1. Download the latest release from GitHub
2. Extract the ZIP file to your local computer
3. Connect to your server via FTP/SFTP
4. Upload the `triqhub-shipping-radius` folder to `/wp-content/plugins/`
5. Navigate to **Plugins** in WordPress admin
6. Find **TriqHub: Shipping & Radius** and click **Activate**

#### Method 3: Command Line (WP-CLI)
```bash
# Navigate to WordPress directory
cd /path/to/wordpress

# Install plugin from GitHub
wp plugin install https://github.com/gustavofullstack/triqhub-shipping-radius/archive/main.zip --activate

# Or install from local file
wp plugin install /path/to/triqhub-shipping-radius.zip --activate
```

### Initial Configuration

#### Step 1: Configure Google Maps API
1. Go to **WooCommerce → Settings → Shipping → Woo Envios**
2. Click on **Google Maps Configuration**
3. Obtain a Google Maps API key with the following APIs enabled:
   - Geocoding API
   - Places API
   - Distance Matrix API
4. Enter your API key and save settings

#### Step 2: Set Store Coordinates
1. Navigate to **WooCommerce → Settings → Shipping → Woo Envios**
2. Enter your store's address or coordinates
3. Click **Geocode Address** to automatically fetch coordinates
4. Verify the coordinates on the embedded map

#### Step 3: Configure Shipping Tiers
1. Go to **Shipping Tiers** tab
2. Add distance ranges and corresponding prices
3. Example configuration:
   - 0-5 km: R$ 10.00
   - 5-10 km: R$ 15.00
   - 10-15 km: R$ 20.00
4. Save your configuration

#### Step 4: Enable Dynamic Pricing (Optional)
1. Navigate to **Dynamic Pricing** tab
2. Enable weather-based pricing and configure OpenWeather API key
3. Set peak hour multipliers (e.g., 17:00-19:00: +20%)
4. Configure weekend surcharges if desired
5. Set maximum multiplier limit (recommended: 2.0x)

#### Step 5: Configure SuperFrete Integration
1. Go to **SuperFrete Configuration** tab
2. Enter your SuperFrete API credentials
3. Select which shipping services to offer (PAC, SEDEX, Mini)
4. Configure fallback behavior for addresses outside delivery radius

### Verification Steps

1. **Check System Status:**
   - Navigate to **WooCommerce → Status → Woo Envios**
   - Verify all components are operational
   - Check for any warnings or errors

2. **Test Checkout Flow:**
   - Add a product to cart
   - Proceed to checkout
   - Enter a Brazilian CEP
   - Verify shipping options appear correctly

3. **Validate API Connections:**
   - Test Google Maps geocoding
   - Verify weather API connectivity
   - Check SuperFrete API response

### Troubleshooting Common Issues

#### Issue: Plugin not appearing in WooCommerce shipping methods
**Solution:**
1. Verify WooCommerce is active and version 5.0+
2. Check PHP error logs for fatal errors
3. Ensure the plugin is activated in WordPress
4. Clear WordPress object cache: `wp cache flush`

#### Issue: Google Maps not working
**Solution:**
1. Verify API key is valid and has required APIs enabled
2. Check billing is enabled on Google Cloud Console
3. Verify API key restrictions (IP, HTTP referrers)
4. Test API key directly: `https://maps.googleapis.com/maps/api/geocode/json?address=São+Paulo&key=YOUR_KEY`

#### Issue: No shipping rates calculated
**Solution:**
1. Enable debug logging in plugin settings
2. Check WooCommerce shipping zones configuration
3. Verify store coordinates are set
4. Test with a CEP within configured delivery radius

#### Issue: Performance problems
**Solution:**
1. Enable caching in plugin settings
2. Increase PHP memory limit to 256M
3. Optimize database tables
4. Consider using a CDN for static assets

### Updating the Plugin

#### Automatic Updates (Recommended)
The plugin includes a GitHub updater that checks for new releases automatically. Updates will appear in **Dashboard → Updates**.

#### Manual Update
1. Deactivate the current version
2. Delete the plugin via WordPress admin
3. Install the new version following installation instructions
4. Reactivate the plugin

**Note:** Manual updates preserve settings stored in the database.

### Uninstallation

#### Complete Removal
1. Deactivate the plugin
2. Navigate to **WooCommerce → Settings → Woo Envios → Advanced**
3. Check "Remove all data on uninstall"
4. Delete the plugin via WordPress admin

#### Partial Removal (Preserve Settings)
1. Simply deactivate and delete the plugin
2. Settings will remain in database for future reinstallation

### Support Resources

- **Documentation:** `/docs/` directory in plugin files
- **GitHub Issues:** https://github.com/gustavofullstack/triqhub-shipping-radius/issues
- **System Status:** WooCommerce → Status → Woo Envios
- **Debug Logs:** `/wp-content/uploads/woo-envios-logs/`

## Security Considerations

### API Key Management
- Store Google Maps API keys in WordPress options (encrypted)
- Implement IP restrictions on API keys
- Regular key rotation recommended every 90 days
- Never commit API keys to version control

### Data Protection
- Customer coordinates stored in WordPress session (encrypted)
- Geocoding cache encrypted at rest
- GDPR-compliant data handling for EU customers
- Automatic data purge after 30 days

### Rate Limiting
- Built-in circuit breaker prevents API abuse
- Configurable retry logic with exponential backoff
- Request throttling based on server load
- Automatic fallback to cached data during outages

## Performance Optimization

### Recommended Server Configuration
```nginx
# Nginx configuration
location ~* \.(php)$ {
    fastcgi_buffer_size 128k;
    fastcgi_buffers 256 16k;
    fastcgi_busy_buffers_size 256k;
    fastcgi_temp_file_write_size 256k;
}

# Enable gzip compression
gzip on;
gzip_vary on;
gzip_min_length 1024;
gzip_types text/plain text/css text/xml text/javascript application/javascript application/xml+rss application/json;
```

### WordPress Configuration
```php
// wp-config.php optimizations
define('WP_MEMORY_LIMIT', '256M');
define('WP_MAX_MEMORY_LIMIT', '512M');
define('WP_CACHE', true);
```

### Plugin-Specific Optimizations
1. Enable geocoding cache (default: 30 days)
2. Use transient caching for weather data
3. Implement lazy loading for Google Maps assets
4. Enable database query optimization

## Contributing

### Development Setup
1. Fork the repository on GitHub
2. Clone your fork locally
3. Install dependencies: `composer install`
4. Set up a local WordPress environment
5. Activate the plugin in development mode

### Coding Standards
- Follow WordPress PHP coding standards
- Use type hints and return type declarations
- Document all public methods with PHPDoc
- Include unit tests for new functionality

### Pull Request Process
1. Create a feature branch from `main`
2. Implement changes with tests
3. Update documentation
4. Submit pull request with detailed description
5. Await code review and CI checks

## License & Compliance

### Commercial License
This plugin requires a valid TriqHub license for production use. License keys can be obtained from the TriqHub customer portal.

### Compliance Requirements
- Brazilian LGPD compliance for data handling
- PCI DSS compliance for payment processing
- GDPR compliance for European customers
- Accessibility standards (WCAG 2.1 AA)

## Changelog

### Version 1.2.9 (Current)
- Enhanced Google Maps integration with circuit breaker
- Improved Brazilian CEP handling
- Dynamic pricing with weather integration
- Self-healing database architecture
- Comprehensive logging system

### Version 1.2.8
- Initial public release
- Basic radius-based shipping
- Google Maps geocoding
- SuperFrete integration

---

**Maintained by:** TriqHub Development Team  
**Support:** support@triqhub.com  
**Documentation:** https://docs.triqhub.com/shipping-radius  
**GitHub:** https://github.com/gustavofullstack/triqhub-shipping-radius