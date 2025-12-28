# TriqHub: Shipping & Radius - Premium Documentation

## 🚀 Project Overview

**TriqHub: Shipping & Radius** is an enterprise-grade WooCommerce shipping plugin that revolutionizes local delivery by implementing intelligent radius-based shipping calculations with dynamic pricing. The plugin automatically collects Brazilian postal code coordinates during checkout and integrates with multiple shipping services to provide precise, real-time delivery cost calculations.

**Core Philosophy**: Transform static shipping methods into dynamic, location-aware delivery systems that adapt to real-world conditions including weather, peak hours, and distance.

---

## 🏗️ Technical Stack

### Backend Architecture
- **PHP**: 7.4+ (Strictly typed with modern OOP patterns)
- **WordPress**: 6.2+ (Fully compatible with latest WP standards)
- **WooCommerce**: 5.0+ (Deep integration with shipping zones and methods)
- **MySQL**: 5.7+ (Custom cache tables with optimized indexes)

### External API Integrations
- **Google Maps Platform**: Geocoding, Places Autocomplete, Distance Matrix
- **OpenWeather API**: Real-time weather conditions for dynamic pricing
- **SuperFrete API**: Brazilian shipping service integration (PAC/SEDEX/Mini)
- **TriqHub License API**: License validation and update management

### Frontend Technologies
- **JavaScript**: Vanilla JS with modern ES6+ features
- **CSS3**: Responsive design with mobile-first approach
- **HTML5**: Semantic markup with ARIA accessibility

### Development Tools
- **Composer**: Dependency management
- **GitHub Actions**: CI/CD pipeline with automated testing
- **Plugin Update Checker**: Seamless GitHub-based updates
- **DeepSeek API**: AI-powered documentation generation

---

## ✨ Key Features

### 🎯 Core Shipping Engine
- **Intelligent Radius Calculation**: Uses Google Maps Distance Matrix API for accurate route-based distance calculations
- **Multi-Tier Pricing**: Configurable distance tiers with escalating pricing models
- **Fallback Systems**: Graceful degradation from Google Maps to Haversine formula when APIs fail
- **Circuit Breaker Pattern**: Automatic API failure detection with graceful fallbacks

### 🌦️ Dynamic Pricing System
- **Weather-Based Adjustments**: Real-time rain detection with configurable multipliers
- **Peak Hour Pricing**: Time-based pricing adjustments for high-demand periods
- **Weekend Surcharges**: Automatic weekend pricing adjustments
- **Maximum Multiplier Limits**: Configurable caps to prevent excessive pricing

### 🔌 Multi-Service Integration
- **Local Flash Delivery**: Ultra-fast local delivery within configured radius
- **SuperFrete Integration**: National shipping via PAC, SEDEX, and Mini services
- **Automatic Service Selection**: Intelligent routing between local and national services
- **Rate Sorting**: Always displays Flash Delivery as primary option

### 🗺️ Advanced Geocoding
- **Brazilian CEP Optimization**: Specialized handling for Brazilian postal codes
- **Google Places Autocomplete**: Real-time address validation and completion
- **Server-Side Fallback**: Automatic geocoding when client-side fails
- **Intelligent Caching**: 30-day geocode cache with automatic cleanup

### 🛡️ Enterprise Features
- **Self-Healing Architecture**: Automatic table creation and repair
- **Comprehensive Logging**: Detailed shipping calculation logs with rotation
- **Admin Notifications**: Email alerts for API failures and system issues
- **License Management**: Secure license validation and update control

---

## 📦 Installation Instructions

### Prerequisites
1. **WordPress** 6.2 or higher
2. **WooCommerce** 5.0 or higher
3. **PHP** 7.4 or higher with following extensions:
   - cURL
   - JSON
   - MySQLi
4. **Google Maps API Key** with following APIs enabled:
   - Geocoding API
   - Places API
   - Distance Matrix API
5. **OpenWeather API Key** (optional, for weather-based pricing)

### Installation Methods

#### Method 1: WordPress Admin (Recommended)
1. Navigate to **Plugins → Add New**
2. Click **Upload Plugin**
3. Select the `triqhub-shipping-radius.zip` file
4. Click **Install Now**
5. Activate the plugin

#### Method 2: Manual Installation
```bash
# Download the latest release
wget https://github.com/gustavofullstack/triqhub-shipping-radius/releases/latest/download/triqhub-shipping-radius.zip

# Extract to WordPress plugins directory
unzip triqhub-shipping-radius.zip -d /path/to/wordpress/wp-content/plugins/

# Activate via WP-CLI
wp plugin activate triqhub-shipping-radius
```

#### Method 3: Git Clone (Development)
```bash
cd /path/to/wordpress/wp-content/plugins/
git clone https://github.com/gustavofullstack/triqhub-shipping-radius.git
cd triqhub-shipping-radius
composer install
```

### Configuration Steps

#### Step 1: API Key Configuration
1. Go to **WooCommerce → Settings → Woo Envios**
2. Enter your **Google Maps API Key**
3. (Optional) Enter your **OpenWeather API Key**
4. Click **Save Changes**

#### Step 2: Store Coordinates Setup
1. Navigate to **Woo Envios → Google Maps**
2. Enter your store's address or coordinates
3. Click **Geocode** to verify location
4. Save the coordinates

#### Step 3: Shipping Tiers Configuration
1. Go to **Woo Envios → Shipping Tiers**
2. Configure distance ranges and prices:
   - Tier 1: 0-5km = R$ 10.00
   - Tier 2: 5-10km = R$ 15.00
   - Tier 3: 10-15km = R$ 20.00
3. Set maximum delivery radius

#### Step 4: Dynamic Pricing Setup
1. Navigate to **Woo Envios → Dynamic Pricing**
2. Configure multipliers:
   - Peak hours: 1.2x (20% increase)
   - Weekend: 1.1x (10% increase)
   - Heavy rain: 1.5x (50% increase)
3. Set maximum overall multiplier

#### Step 5: Shipping Zone Assignment
1. Go to **WooCommerce → Settings → Shipping**
2. Create or edit a shipping zone
3. Add **"Woo Envios — Raio Escalonado"** method
4. Configure instance settings

### Verification Steps

#### Test 1: Plugin Activation
```php
// Check plugin is active
if (is_plugin_active('triqhub-shipping-radius/triqhub-shipping-radius.php')) {
    echo "✓ Plugin is active";
}
```

#### Test 2: Database Tables
```sql
-- Verify cache table exists
SHOW TABLES LIKE '%woo_envios_geocode_cache%';

-- Check table structure
DESCRIBE wp_woo_envios_geocode_cache;
```

#### Test 3: API Connectivity
1. Add a product to cart
2. Proceed to checkout
3. Enter a Brazilian CEP
4. Verify coordinates are captured
5. Check shipping options appear

### Troubleshooting

#### Common Issues & Solutions

**Issue**: "Google Maps API not configured" error
```bash
# Solution: Verify API key permissions
1. Ensure Geocoding, Places, and Distance Matrix APIs are enabled
2. Check API key restrictions (HTTP referrers, IP addresses)
3. Verify billing is enabled on Google Cloud Console
```

**Issue**: No shipping methods appearing
```php
// Enable debug logging
update_option('woo_envios_enable_logs', true);

// Check WooCommerce logs
wc_get_logger()->debug('Shipping calculation debug', ['source' => 'woo-envios']);
```

**Issue**: Coordinates not saving to session
```javascript
// Check browser console for JavaScript errors
console.log('Woo Envios Debug:', window.wooEnviosData);

// Verify CORS headers
// Response should include: Access-Control-Allow-Origin: *
```

### Performance Optimization

#### Database Indexing
```sql
-- Add performance indexes
ALTER TABLE wp_woo_envios_geocode_cache 
ADD INDEX idx_cache_key (cache_key),
ADD INDEX idx_expires_at (expires_at);
```

#### Caching Strategy
- Geocode results: 30 days cache
- Weather data: 1 hour cache
- Distance calculations: 24 hours cache
- Shipping rates: Session-based cache

#### Memory Optimization
```php
// Recommended PHP settings
memory_limit = 256M
max_execution_time = 30
opcache.enable = 1
opcache.memory_consumption = 128
```

### Security Considerations

#### API Key Protection
- Store API keys in WordPress options (encrypted)
- Never expose keys in client-side code
- Use environment variables in production

#### Data Validation
```php
// All user inputs are sanitized
$clean_input = sanitize_text_field($_POST['address']);
$validated_cep = preg_replace('/\D/', '', $cep);

// Nonce verification for all AJAX requests
check_ajax_referer('woo_envios_nonce', 'security');
```

#### SQL Injection Prevention
```php
// Use WordPress $wpdb prepared statements
$wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}table WHERE id = %d AND status = %s",
    $id,
    $status
);
```

### Update Procedure

#### Automatic Updates
The plugin supports GitHub-based automatic updates. Ensure:
1. License key is entered in settings
2. GitHub repository is accessible
3. WordPress filesystem permissions allow updates

#### Manual Update
```bash
# Backup current installation
cp -r triqhub-shipping-radius triqhub-shipping-radius-backup-$(date +%Y%m%d)

# Download and extract new version
wget https://github.com/gustavofullstack/triqhub-shipping-radius/archive/refs/heads/main.zip
unzip main.zip

# Replace files
rsync -av triqhub-shipping-radius-main/ triqhub-shipping-radius/ --delete
```

### Support & Resources

#### Documentation
- **Architecture**: `/docs/ARCHITECTURE.md`
- **API Reference**: `/docs/API_REFERENCE.md`
- **User Guide**: `/docs/USER_GUIDE.md`
- **Connectivity**: `/docs/CONNECTIVITY.md`

#### Debug Tools
```php
// Enable comprehensive logging
add_filter('woo_envios_debug_mode', '__return_true');

// View cached data
$cached = get_transient('woo_envios_geocode_' . md5($address));

// Test API connectivity
$google_maps = new Woo_Envios_Google_Maps();
$is_configured = $google_maps->is_configured();
```

#### Community & Support
- **GitHub Issues**: Bug reports and feature requests
- **WordPress.org Forum**: Community support
- **Email Support**: support@triqhub.com
- **Documentation**: https://docs.triqhub.com

---

## 📊 System Requirements Matrix

| Component | Minimum | Recommended | Notes |
|-----------|---------|-------------|-------|
| PHP | 7.4 | 8.1+ | Strict typing enabled |
| WordPress | 6.2 | 6.4+ | Gutenberg compatibility |
| WooCommerce | 5.0 | 8.0+ | Shipping zones required |
| MySQL | 5.7 | 8.0+ | InnoDB required |
| Memory | 128MB | 256MB+ | For complex calculations |
| Storage | 10MB | 50MB+ | Logs and cache growth |

---

## 🔄 Update Channels

| Channel | Branch | Stability | Auto-update |
|---------|--------|-----------|-------------|
| Stable | `main` | Production-ready | Yes |
| Beta | `develop` | Testing features | Manual only |
| Alpha | `feature/*` | Experimental | No |

---

## 🎯 Quick Start Checklist

- [ ] Install and activate plugin
- [ ] Configure Google Maps API key
- [ ] Set store coordinates
- [ ] Configure shipping tiers
- [ ] Test with Brazilian CEP
- [ ] Verify shipping methods appear
- [ ] Configure dynamic pricing (optional)
- [ ] Set up logging for debugging
- [ ] Add license key for updates

---

**Version**: 1.2.15  
**Last Updated**: $(date +%Y-%m-%d)  
**Compatibility**: WordPress 6.2+, WooCommerce 5.0+  
**License**: GPL v2 or later  
**Support Period**: 12 months from purchase  
**Update Policy**: Security updates for 24 months, feature updates for 12 months

---

> **Pro Tip**: For production deployments, always test in staging first and monitor the `woo-envios-logs` directory for the first 48 hours to ensure proper functionality.