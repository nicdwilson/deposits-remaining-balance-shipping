# Deposits Remaining Balance Shipping

A WordPress plugin that handles shipping costs for remaining balance payments on WooCommerce deposits and payment plans.

## Description

This plugin calculates and applies shipping costs when customers pay their remaining balance on deposit orders or payment plans. It integrates with WooCommerce Deposits to provide a seamless shipping experience for customers who have placed deposits.

## Requirements

- WordPress 6.0 or higher
- WooCommerce 9.7 or higher
- WooCommerce Deposits plugin
- PHP 7.4 or higher

## Features

- Compatible with HPOS (High-Performance Order Storage)
- Compatible with Cart and Checkout Blocks
- Compatible with Cost of Goods
- Compatible with Product Block Editor
- Compatible with Product Variation Form

## Installation

1. Upload the plugin files to the `/wp-content/plugins/deposits-remaining-balance-shipping` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Ensure WooCommerce and WooCommerce Deposits are installed and activated

## Changelog

### Version 1.0.3
- **NEW**: Added support for shipping on final payment plan orders
- **ADDED**: `is_final_payment_plan_order()` method to identify final payments in payment plans
- **ENHANCED**: Shipping logic now distinguishes between regular and final payment plan orders
- **IMPROVED**: Better payment plan schedule analysis and payment number extraction

### Version 1.0.2
- **FIXED**: Refined payment plan order detection to prevent false positives on remaining balance orders
- **IMPROVED**: More precise detection methods prioritizing order status over created_via
- **ADDED**: Debug method for troubleshooting order identification issues
- **ENHANCED**: Better logging and debugging capabilities

### Version 1.0.1
- **FIXED**: Payment plan orders are now correctly excluded from remaining balance shipping functionality
- **ADDED**: New `is_payment_plan_order()` method to properly identify payment plan orders
- **IMPROVED**: Enhanced logging for better debugging of order identification
- **ENHANCED**: More robust detection of payment plan orders using multiple methods

### Version 1.0.0
- Initial release

## Development

This plugin is currently in development. The main plugin file is located at:
`deposits-remaining-balance-shipping.php`

The plugin structure includes:
- `includes/` - Directory for additional PHP classes and functions
- `languages/` - Directory for translation files (to be created)
- `assets/` - Directory for CSS, JS, and other assets (to be created)

## License

GNU General Public License v3.0
