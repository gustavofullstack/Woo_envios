# TriqHub: Shipping & Radius

## Project Overview

**TriqHub: Shipping & Radius** is a sophisticated WooCommerce shipping plugin that revolutionizes local delivery by implementing intelligent radius-based shipping calculations with dynamic pricing. The plugin automatically collects Brazilian postal code (CEP) coordinates during checkout and integrates with multiple external APIs to provide precise, real-time shipping calculations with weather-aware pricing adjustments.

**Version:** 1.2.12  
**Minimum Requirements:** WordPress 6.2+, PHP 7.4+, WooCommerce 5.0+  
**License:** Proprietary (TriqHub)  
**Primary Language:** Portuguese (Brazilian market focus)

## Technical Stack

### Core Technologies
- **PHP 7.4+** - Modern PHP with strict typing and OOP patterns
- **WordPress 6.2+** - Plugin architecture and WordPress hooks system
- **WooCommerce 5.0+** - Shipping method integration and e-commerce framework
- **MySQL 5.7+** - Custom cache tables and session storage

### External API Integrations
- **Google Maps Platform** - Geocoding, Places Autocomplete, Distance Matrix
- **OpenWeather API** - Real-time weather data for dynamic pricing
- **SuperFrete/Correios API** - National shipping calculations (PAC/SEDEX)
- **TriqHub License API** - License validation and update management

### Development Tools & Libraries
- **Plugin Update Checker** - GitHub-based automatic updates
- **Composer** - Dependency management (vendor/autoload.php)
- **WC_Shipping_Method** - WooCommerce shipping method abstraction
- **WP_Error** - WordPress error handling system

## Key Features

### 1. Intelligent Radius-Based Shipping
- **Haversine Formula** - Calculates straight-line distance between store and customer
- **Google Distance Matrix** - Real route distance calculations (primary method)
- **Configurable Delivery Tiers** - Multiple distance ranges with custom pricing
- **Automatic Zone Detection** - Determines if customer is within delivery radius

### 2. Dynamic Pricing Engine
- **Weather-Aware Pricing** - Adjusts prices based on real-time rain conditions
- **Peak Hour Multipliers** - Configurable time-based pricing adjustments
- **Weekend Surcharges** - Automatic weekend pricing increases
- **Maximum Multiplier Limits** - Prevents excessive price inflation

### 3. Multi-API Geocoding System
- **Google Maps Geocoding** - Primary address-to-coordinate conversion
- **Server-Side Fallback** - Automatic fallback when session data is missing
- **Geocode Caching** - 30-day cache table (`woo_envios_geocode_cache`)
- **Circuit Breaker Pattern** - Automatic API failure protection

### 4. Dual Shipping Method Support
- **Flash Delivery (Local)** - Radius-based same-day/next-day delivery
- **SuperFrete/Correios (National)** - Integrated PAC/SEDEX shipping options
- **Intelligent Rate Sorting** - Always displays Flash Delivery first
- **Automatic Fallback** - Shows Correios when outside delivery radius

### 5. Advanced Administration
- **Google Maps Integration Panel** - API key management and geocoding controls
- **Delivery Tier Configuration** - Visual distance/price matrix
- **Dynamic Pricing Settings** - Weather, peak hours, weekend multipliers
- **Comprehensive Logging** - Daily log files with rotation and cleanup

### 6. Robust Error Handling
- **Self-Healing Architecture** - Automatic cache table creation
- **Graceful Degradation** - Falls back to simpler calculations when APIs fail
- **Admin Notifications** - Email alerts for critical API failures
- **Debug Logging** - Detailed calculation logs for troubleshooting

## Installation Instructions

### Prerequisites
1. **WordPress** 6.2 or higher
2. **WooCommerce** 5.0 or higher
3. **PHP** 7.4 or higher with cURL extension
4. **MySQL** 5.7 or higher
5. **SSL Certificate** (recommended for production)

### Installation Methods

#### Method 1: WordPress Admin (Recommended)
1. Navigate to **Plugins → Add New** in WordPress admin
2. Click **Upload Plugin**
3. Select the `triqhub-shipping-radius.zip` file
4. Click **Install Now**
5. After installation, click **Activate Plugin**

#### Method 2: Manual Installation via FTP/SFTP
1. Download the plugin ZIP file
2. Extract the contents to your local machine
3. Connect to your server via FTP/SFTP
4. Upload the `triqhub-shipping-radius` folder to `/wp-content/plugins/`
5. Navigate to **Plugins** in WordPress admin
6. Find **TriqHub: Shipping & Radius** and click **Activate**

#### Method 3: Command Line (WP-CLI)
```bash
# Navigate to WordPress root directory
cd /path/to/wordpress

# Install plugin from ZIP
wp plugin install /path/to/triqhub-shipping-radius.zip --activate

# Or install from GitHub
wp plugin install https://github.com/gustavofullstack/triqhub-shipping-radius/archive/main.zip --activate
```

### Post-Installation Configuration

#### Step 1: Configure Google Maps API
1. Go to **WooCommerce → Settings → Shipping → Woo Envios**
2. Click on **Google Maps Configuration**
3. Obtain a Google Maps API key with these APIs enabled:
   - Geocoding API
   - Places API
   - Distance Matrix API
4. Enter your API key and save settings

#### Step 2: Set Store Coordinates
1. In the same settings page, locate **Store Base Coordinates**
2. Enter your store's latitude and longitude
3. Use the **"Geocode My Address"** button to automatically find coordinates
4. Save changes

#### Step 3: Configure Delivery Tiers
1. Navigate to **Delivery Tiers** tab
2. Add distance ranges (in kilometers) with corresponding prices
3. Example configuration:
   - 0-5 km: R$ 10.00
   - 5-10 km: R$ 15.00
   - 10-15 km: R$ 20.00
4. Save tiers

#### Step 4: Enable Shipping Zones
1. Go to **WooCommerce → Settings → Shipping → Shipping Zones**
2. Add a new zone or edit existing zone
3. Add shipping method: **Woo Envios — Raio Escalonado**
4. Configure zone restrictions as needed

#### Step 5: Optional Advanced Configuration
1. **Dynamic Pricing**: Enable weather and peak hour multipliers
2. **OpenWeather API**: Add API key for weather-based pricing
3. **Logging**: Enable debug logs for troubleshooting
4. **SuperFrete**: Configure Correios API credentials for national shipping

### Verification Steps

1. **Check System Status**:
   - Navigate to **WooCommerce → Status → Logs**
   - Select `woo-envios` from the dropdown
   - Verify no critical errors are present

2. **Test Checkout Process**:
   - Add a product to cart
   - Proceed to checkout
   - Enter a Brazilian CEP within your delivery radius
   - Verify Flash Delivery option appears with correct pricing

3. **Verify Admin Functions**:
   - Check that all settings pages load correctly
   - Verify Google Maps geocoding works
   - Test cache table creation

### Troubleshooting Common Issues

#### Issue: Plugin not appearing in shipping methods
**Solution:** 
1. Verify WooCommerce is active and version 5.0+
2. Check PHP error logs for class conflicts
3. Ensure `class-woo-envios-shipping.php` is loading correctly

#### Issue: Google Maps not working
**Solution:**
1. Verify API key is valid and has required APIs enabled
2. Check billing is enabled on Google Cloud account
3. Test API key directly via Google's API tester

#### Issue: No shipping rates calculated
**Solution:**
1. Enable debug logging in plugin settings
2. Check `wp-content/uploads/woo-envios-logs/` for error messages
3. Verify store coordinates are configured
4. Test with a CEP known to be within delivery radius

#### Issue: Performance problems
**Solution:**
1. Reduce cache TTL if using high-traffic site
2. Enable opcache in PHP configuration
3. Consider using a CDN for static assets
4. Monitor API usage limits

### Update Instructions

The plugin supports automatic updates via GitHub:
1. Updates are delivered through the WordPress updates screen
2. Manual updates can be performed by replacing plugin files
3. Always backup your database before updating
4. Test updates on staging environment first

### Uninstallation

To completely remove the plugin:
1. Deactivate the plugin from WordPress admin
2. Delete the plugin files
3. Optional: Remove database tables:
   ```sql
   DROP TABLE IF EXISTS wp_woo_envios_geocode_cache;
   ```
4. Optional: Remove options:
   ```sql
   DELETE FROM wp_options WHERE option_name LIKE 'woo_envios_%';
   DELETE FROM wp_options WHERE option_name LIKE 'triqhub_%';
   ```

## Support & Resources

- **Documentation:** Complete documentation available in `/docs/` directory
- **GitHub Repository:** https://github.com/gustavofullstack/triqhub-shipping-radius
- **Support:** Contact TriqHub support for licensed users
- **Community:** WordPress.org plugin directory (if published)

## Security Notes

- All API keys are stored encrypted in the database
- Google Maps API requests use HTTPS exclusively
- Cache table uses proper indexing and cleanup routines
- Session data is validated and sanitized
- Admin functions include capability checks
- Regular security updates via GitHub integration

## Performance Considerations

- Geocode caching reduces API calls by 90%+
- Circuit breaker prevents API overload during outages
- Log rotation prevents disk space issues
- Asynchronous JavaScript for checkout improvements
- Database indexes optimized for frequent queries

## License & Compliance

This is a proprietary plugin developed by TriqHub. Usage requires a valid license key. The plugin complies with:
- Brazilian data protection regulations (LGPD)
- WooCommerce extension guidelines
- WordPress coding standards
- Google Maps API terms of service

---

*For advanced configuration, API documentation, and developer guides, refer to the `/docs/` directory included with the plugin.*