<?php
/**
 * Plugin Name: WooCommerce Deposits Remaining Balance Shipping
 * Requires Plugins: woocommerce, woocommerce-deposits
 * Plugin URI: https://example.com/deposits-remaining-balance-shipping
 * Description: Handles shipping costs for remaining balance payments on WooCommerce deposits and payment plans. Calculates and applies shipping costs when customers pay their remaining balance.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://example.com
 * Text Domain: deposits-remaining-balance-shipping
 * Domain Path: /languages
 * Requires at least: 6.0
 * Tested up to: 6.8
 * WC tested up to: 9.9
 * WC requires at least: 9.7
 * Requires PHP: 7.4
 * PHP tested up to: 8.3
 *
 * Copyright: © 2024 Your Name
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package deposits-remaining-balance-shipping
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants
define( 'WC_DEPOSITS_RBS_VERSION', '1.0.0' );
define( 'WC_DEPOSITS_RBS_FILE', __FILE__ );
define( 'WC_DEPOSITS_RBS_PLUGIN_URL', untrailingslashit( plugins_url( '', WC_DEPOSITS_RBS_FILE ) ) );
define( 'WC_DEPOSITS_RBS_PLUGIN_PATH', untrailingslashit( plugin_dir_path( WC_DEPOSITS_RBS_FILE ) ) );

/**
 * Main plugin class
 */
class WC_Deposits_Remaining_Balance_Shipping {

	/**
	 * Plugin instance
	 *
	 * @var WC_Deposits_Remaining_Balance_Shipping
	 */
	private static $instance;

	/**
	 * Get plugin instance
	 *
	 * @return WC_Deposits_Remaining_Balance_Shipping
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	public function __construct() {
		// HPOS Compatibility
		add_action( 'before_woocommerce_init', array( $this, 'declare_woocommerce_feature_compatibility' ) );

		// Initialize plugin after WooCommerce is loaded
		add_action( 'woocommerce_loaded', array( $this, 'init' ) );
	}

	/**
	 * Declare WooCommerce feature compatibility
	 */
	public function declare_woocommerce_feature_compatibility() {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			// HPOS (High-Performance Order Storage) compatibility
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			
			// Cart and checkout blocks compatibility
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
			
			// Cost of Goods compatibility
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cost_of_goods', __FILE__, true );
			
			// Product blocks compatibility
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'product_block_editor', __FILE__, true );
			
			// Product variation form compatibility
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'product_variation_form', __FILE__, true );
		}
	}

	/**
	 * Initialize plugin
	 */
	public function init() {
		// Load plugin files
		$this->includes();

		// Initialize components
		$this->init_components();

		// Load text domain
		add_action( 'init', array( $this, 'load_textdomain' ) );
	}

	/**
	 * Include required files
	 */
	private function includes() {
		$logger = wc_get_logger();
		
		// Include order identifier
		$order_identifier_file = WC_DEPOSITS_RBS_PLUGIN_PATH . '/includes/class-wc-deposits-rbs-order-identifier.php';
		if ( ! file_exists( $order_identifier_file ) ) {
			$logger->error( 'Order identifier file not found: ' . $order_identifier_file, array( 'source' => 'deposits-remaining-balance-shipping' ) );
			return;
		}
		require_once $order_identifier_file;
		
		// Include shipping calculator
		$shipping_calculator_file = WC_DEPOSITS_RBS_PLUGIN_PATH . '/includes/class-wc-deposits-rbs-shipping-calculator.php';
		if ( ! file_exists( $shipping_calculator_file ) ) {
			$logger->error( 'Shipping calculator file not found: ' . $shipping_calculator_file, array( 'source' => 'deposits-remaining-balance-shipping' ) );
			return;
		}
		require_once $shipping_calculator_file;
		
		// Include pay order integration
		$pay_order_integration_file = WC_DEPOSITS_RBS_PLUGIN_PATH . '/includes/class-wc-deposits-rbs-pay-order-integration.php';
		if ( ! file_exists( $pay_order_integration_file ) ) {
			$logger->error( 'Pay order integration file not found: ' . $pay_order_integration_file, array( 'source' => 'deposits-remaining-balance-shipping' ) );
			return;
		}
		require_once $pay_order_integration_file;
	}

	/**
	 * Initialize plugin components
	 */
	private function init_components() {
		// Check if classes are loaded
		if ( ! class_exists( 'WC_Deposits_RBS_Shipping_Calculator' ) ) {
			$logger = wc_get_logger();
			$logger->error( 'WC_Deposits_RBS_Shipping_Calculator class not found', array( 'source' => 'deposits-remaining-balance-shipping' ) );
			return;
		}
		
		if ( ! class_exists( 'WC_Deposits_RBS_Pay_Order_Integration' ) ) {
			$logger = wc_get_logger();
			$logger->error( 'WC_Deposits_RBS_Pay_Order_Integration class not found', array( 'source' => 'deposits-remaining-balance-shipping' ) );
			return;
		}
		
		// Initialize shipping calculator
		WC_Deposits_RBS_Shipping_Calculator::get_instance();
		
		// Initialize pay order integration
		WC_Deposits_RBS_Pay_Order_Integration::get_instance();
	}

	/**
	 * Load plugin text domain
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'deposits-remaining-balance-shipping',
			false,
			dirname( plugin_basename( WC_DEPOSITS_RBS_FILE ) ) . '/languages'
		);
	}


}

// Initialize the plugin
WC_Deposits_Remaining_Balance_Shipping::get_instance();
