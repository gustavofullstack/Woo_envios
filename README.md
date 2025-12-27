# TriqHub: Shipping & Radius

[![WordPress](https://img.shields.io/badge/WordPress-6.2+-blue.svg)](https://wordpress.org/)
[![WooCommerce](https://img.shields.io/badge/WooCommerce-5.0+-96588A.svg)](https://woocommerce.com/)
[![PHP](https://img.shields.io/badge/PHP-7.4+-777BB4.svg)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPL--3.0-orange.svg)](LICENSE)
[![GitHub Release](https://img.shields.io/github/v/release/gustavofullstack/triqhub-shipping-radius)](https://github.com/gustavofullstack/triqhub-shipping-radius/releases)
[![Build Status](https://img.shields.io/badge/Tested-Passing-brightgreen.svg)](#)

## Introduction

**TriqHub: Shipping & Radius** is a professional, enterprise-grade WooCommerce shipping plugin designed for Brazilian e-commerce. It automates Brazilian postal code (CEP) coordinate collection at checkout and integrates sophisticated radius-based shipping rules with Google Maps API for maximum precision. The plugin intelligently combines local "Flash Delivery" (raio escalonado) with national carrier integrations (SuperFrete) to provide a complete, dynamic shipping solution.

## Features List

### Core Shipping Engine
- **Automatic Brazilian CEP Geocoding**: Automatically converts customer postal codes into precise latitude/longitude coordinates using Google Maps Geocoding API.
- **Dynamic Radius-Based Delivery**: Configures multiple delivery tiers (e.g., 0-30km, 30-50km) with custom pricing per distance range.
- **Real Route Distance Calculation**: Uses Google Maps Distance Matrix API to calculate actual driving distance (not straight-line), providing accurate delivery estimates and costs.
- **Intelligent Shipping Method Selection**: Automatically determines whether a customer qualifies for local "Flash Delivery" or should be offered national carrier options.

### Advanced Integrations
- **SuperFrete API Integration**: Seamlessly integrates with SuperFrete for PAC, SEDEX, and Mini Envios shipping quotes for customers outside the local delivery radius.
- **Google Maps Platform**: Leverages Google Maps Geocoding, Distance Matrix, and optional Maps JavaScript API for checkout map visualization.
- **OpenWeather API Integration**: Optional dynamic pricing adjustments based on real-time weather conditions (rain multipliers).
- **WooCommerce Native Integration**: Fully integrates with WooCommerce shipping zones, methods, and settings.

### Smart Features & Optimization
- **Performance Caching**: Implements a dedicated MySQL geocode cache table (`wp_woo_envios_geocode_cache`) to minimize API calls and improve response times.
- **Session-Based Coordinate Storage**: Stores geocoded coordinates in WooCommerce session to prevent redundant API requests during a single checkout session.
- **Self-Healing Architecture**: Automatically creates required database tables if missing during plugin updates.
- **Automatic Updates**: Built-in GitHub-powered update system with version checking and one-click updates.
- **Admin Dashboard**: Comprehensive settings panel for configuring store coordinates, radius tiers, API keys, and weather adjustments.

### Operational Intelligence
- **Peak Hours Pricing**: Configurable price multipliers for delivery during peak business hours.
- **Weekend/Weather Adjustments**: Dynamic pricing based on weekends and current weather conditions.
- **Intelligent Rate Sorting**: Automatically prioritizes "Flash Delivery" rates above other shipping methods at checkout.
- **Comprehensive Logging**: Detailed WooCommerce system logging for debugging and audit trails.
- **Fallback Mechanisms**: Graceful degradation when APIs are unavailable, with Haversine formula fallback for distance calculations.

### Developer & Deployment Ready
- **Professional Code Architecture**: Singleton pattern, proper namespacing, and separation of concerns.
- **Comprehensive Testing Suite**: Includes integration tests for SuperFrete API, plugin loading, and full simulation.
- **GitHub Actions Ready**: Pre-configured for automated testing and deployment workflows.
- **Multi-environment Support**: Properly handles development, staging, and production environments.

## Quick Start

For complete installation, configuration, and usage instructions, please refer to the comprehensive **[User Guide](docs/USER_GUIDE.md)**.

**Basic Installation:**
1. Upload the `triqhub-shipping-radius` folder to `/wp-content/plugins/`
2. Activate the plugin through WordPress Plugins menu
3. Configure your Google Maps API key in WooCommerce → Settings → Shipping → Woo Envios
4. Set your store coordinates and radius pricing tiers
5. Configure SuperFrete API credentials for national shipping options

**Minimum Requirements:**
- WordPress 6.2+
- WooCommerce 5.0+
- PHP 7.4+
- MySQL 5.6+
- SSL Certificate (for Google Maps API)

## License

This project is licensed under the **GNU General Public License v3.0**. See the [LICENSE](LICENSE) file for complete terms.

**Key License Points:**
- Free to use, modify, and distribute
- Must maintain original copyright notices
- Derivative works must be released under same license
- Commercial use permitted
- No warranty provided

**Third-Party Services:**
- Google Maps Platform: Requires separate API key and compliance with Google's Terms of Service
- SuperFrete API: Requires valid SuperFrete account and API credentials
- OpenWeather API: Optional integration requiring API key

---

*TriqHub: Shipping & Radius is maintained by GUSTAVO_EDC and designed for Brazilian e-commerce businesses seeking professional, automated shipping solutions.*