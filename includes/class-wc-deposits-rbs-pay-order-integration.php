<?php
/**
 * Pay Order Integration for Deposits Remaining Balance Shipping
 *
 * @package deposits-remaining-balance-shipping
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Deposits_RBS_Pay_Order_Integration class
 */
class WC_Deposits_RBS_Pay_Order_Integration {

	/**
	 * Class instance
	 *
	 * @var WC_Deposits_RBS_Pay_Order_Integration
	 */
	private static $instance;

	/**
	 * Get class instance
	 *
	 * @return WC_Deposits_RBS_Pay_Order_Integration
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
		// Add shipping section to pay order page (at the very top)
		add_action( 'before_woocommerce_pay_form', array( $this, 'add_shipping_section' ) );
		
		// Handle shipping selection
		add_action( 'woocommerce_pay_order_after_submit', array( $this, 'add_shipping_hidden_fields' ) );
		
		// Validate shipping selection before payment
		add_action( 'woocommerce_pay_order_before_submit', array( $this, 'validate_shipping_selection' ) );
		
		// Process shipping on order payment
		add_action( 'woocommerce_pay_order_processed', array( $this, 'process_shipping_on_payment' ), 10, 2 );
		
		// Add scripts and styles
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Add shipping section to pay order page
	 */
	public function add_shipping_section() {
		$logger = wc_get_logger();
		
		$logger->info( 'Adding shipping section to pay order page', array( 'source' => 'deposits-remaining-balance-shipping' ) );
		
		// Debug: Check if we're on the right page
		if ( ! is_checkout_pay_page() ) {
			$logger->info( 'Not on checkout pay page, skipping shipping section', array( 'source' => 'deposits-remaining-balance-shipping' ) );
			return;
		}
		
		// Get order from URL parameters or global variable
		$order = null;
		
		// Try to get order from global variable first
		global $order;
		if ( $order && is_object( $order ) && is_a( $order, 'WC_Order' ) ) {
			$logger->info( 'Order found from global variable, ID: ' . $order->get_id(), array( 'source' => 'deposits-remaining-balance-shipping' ) );
		} else {
			// Try to get order from URL parameters or path
			$order_id = 0;
			
			// Check query parameters first
			if ( isset( $_GET['order_id'] ) ) {
				$order_id = intval( $_GET['order_id'] );
			} elseif ( isset( $_GET['order-pay'] ) ) {
				$order_id = intval( $_GET['order-pay'] );
			} else {
				// Extract order ID from URL path
				$current_url = $_SERVER['REQUEST_URI'];
				if ( preg_match( '/\/checkout\/order-pay\/(\d+)/', $current_url, $matches ) ) {
					$order_id = intval( $matches[1] );
					$logger->info( 'Order ID extracted from URL path: ' . $order_id, array( 'source' => 'deposits-remaining-balance-shipping' ) );
				}
			}
			
			if ( $order_id ) {
				$order = wc_get_order( $order_id );
				if ( $order ) {
					$logger->info( 'Order found from URL, ID: ' . $order->get_id(), array( 'source' => 'deposits-remaining-balance-shipping' ) );
				} else {
					$logger->warning( 'Order not found for ID: ' . $order_id, array( 'source' => 'deposits-remaining-balance-shipping' ) );
				}
			} else {
				$logger->warning( 'No order ID found in URL', array( 'source' => 'deposits-remaining-balance-shipping' ) );
			}
		}
		
		// Ensure we have a valid order object
		if ( ! $order || ! is_object( $order ) || ! is_a( $order, 'WC_Order' ) ) {
			$logger->warning( 'Invalid order object in add_shipping_section', array( 'source' => 'deposits-remaining-balance-shipping' ) );
			return;
		}
		
		$logger->info( 'Order found, checking if needs shipping calculation: ' . $order->get_id(), array( 'source' => 'deposits-remaining-balance-shipping' ) );
		
		if ( ! WC_Deposits_RBS_Order_Identifier::needs_shipping_calculation( $order ) ) {
			$logger->info( 'Order does not need shipping calculation', array( 'source' => 'deposits-remaining-balance-shipping' ) );
			return;
		}
		
		$logger->info( 'Order needs shipping calculation, proceeding with template', array( 'source' => 'deposits-remaining-balance-shipping' ) );

		// Get shipping rates
		$shipping_calculator = WC_Deposits_RBS_Shipping_Calculator::get_instance();
		$shipping_rates = $shipping_calculator->calculate_shipping_for_order( $order );

		$logger->info( 'Shipping rates count: ' . count( $shipping_rates ), array( 'source' => 'deposits-remaining-balance-shipping' ) );

		if ( empty( $shipping_rates ) ) {
			$logger->warning( 'No shipping rates available', array( 'source' => 'deposits-remaining-balance-shipping' ) );
			//return;
		}

		// Include shipping section template
		wc_get_template(
			'pay-order-shipping.php',
			array(
				'order' => $order,
				'shipping_rates' => $shipping_rates,
			),
			'',
			WC_DEPOSITS_RBS_PLUGIN_PATH . '/templates/'
		);
	}



	/**
	 * Validate shipping selection before payment
	 */
	public function validate_shipping_selection() {
		$logger = wc_get_logger();
		
		// Get order from global variable
		global $order;
		if ( ! $order || ! is_object( $order ) || ! is_a( $order, 'WC_Order' ) ) {
			return;
		}
		
		// Check if order needs shipping calculation
		if ( ! WC_Deposits_RBS_Order_Identifier::needs_shipping_calculation( $order ) ) {
			return;
		}
		
		// Check if shipping method is selected
		$shipping_method = sanitize_text_field( $_POST['wc_deposits_rbs_shipping_method'] ?? '' );
		$shipping_cost = floatval( $_POST['wc_deposits_rbs_shipping_cost'] ?? 0 );
		
		$logger->info( 'Validating shipping selection - Method: "' . $shipping_method . '", Cost: ' . $shipping_cost, array( 'source' => 'deposits-remaining-balance-shipping' ) );
		
		if ( empty( $shipping_method ) || $shipping_cost <= 0 ) {
			$logger->warning( 'No shipping method selected for order: ' . $order->get_id(), array( 'source' => 'deposits-remaining-balance-shipping' ) );
			wc_add_notice( __( 'Please select a shipping option before proceeding with payment.', 'deposits-remaining-balance-shipping' ), 'error' );
		} else {
			$logger->info( 'Shipping validation passed for order: ' . $order->get_id(), array( 'source' => 'deposits-remaining-balance-shipping' ) );
		}
	}

	/**
	 * Add hidden fields for shipping data
	 */
	public function add_shipping_hidden_fields() {
		$logger = wc_get_logger();
		
		// Get order from URL parameters or global variable
		$order = null;
		
		// Try to get order from global variable first
		global $order;
		if ( $order && is_object( $order ) && is_a( $order, 'WC_Order' ) ) {
			$logger->info( 'Order found from global variable for hidden fields, ID: ' . $order->get_id(), array( 'source' => 'deposits-remaining-balance-shipping' ) );
		} else {
			// Try to get order from URL parameters or path
			$order_id = 0;
			
			// Check query parameters first
			if ( isset( $_GET['order_id'] ) ) {
				$order_id = intval( $_GET['order_id'] );
			} elseif ( isset( $_GET['order-pay'] ) ) {
				$order_id = intval( $_GET['order-pay'] );
			} else {
				// Extract order ID from URL path
				$current_url = $_SERVER['REQUEST_URI'];
				if ( preg_match( '/\/checkout\/order-pay\/(\d+)/', $current_url, $matches ) ) {
					$order_id = intval( $matches[1] );
					$logger->info( 'Order ID extracted from URL path for hidden fields: ' . $order_id, array( 'source' => 'deposits-remaining-balance-shipping' ) );
				}
			}
			
			if ( $order_id ) {
				$order = wc_get_order( $order_id );
				if ( $order ) {
					$logger->info( 'Order found from URL for hidden fields, ID: ' . $order->get_id(), array( 'source' => 'deposits-remaining-balance-shipping' ) );
				}
			}
		}
		
		// Ensure we have a valid order object
		if ( ! $order || ! is_object( $order ) || ! is_a( $order, 'WC_Order' ) ) {
			return;
		}
		
		if ( ! WC_Deposits_RBS_Order_Identifier::needs_shipping_calculation( $order ) ) {
			return;
		}

		echo '<input type="hidden" name="wc_deposits_rbs_shipping_method" id="wc_deposits_rbs_shipping_method" value="" />';
		echo '<input type="hidden" name="wc_deposits_rbs_shipping_cost" id="wc_deposits_rbs_shipping_cost" value="0" />';
		wp_nonce_field( 'wc_deposits_rbs_shipping_nonce', 'wc_deposits_rbs_shipping_nonce' );
	}

	/**
	 * Process shipping on order payment
	 *
	 * @param int $order_id Order ID
	 * @param array $posted_data Posted data
	 */
	public function process_shipping_on_payment( $order_id, $posted_data ) {
		$logger = wc_get_logger();
		$order = wc_get_order( $order_id );
		
		if ( ! $order ) {
			$logger->error( 'Order not found for shipping processing: ' . $order_id, array( 'source' => 'deposits-remaining-balance-shipping' ) );
			return;
		}
		
		if ( ! WC_Deposits_RBS_Order_Identifier::needs_shipping_calculation( $order ) ) {
			return;
		}

		// Check if shipping method was selected
		$shipping_method = sanitize_text_field( $_POST['wc_deposits_rbs_shipping_method'] ?? '' );
		$shipping_cost = floatval( $_POST['wc_deposits_rbs_shipping_cost'] ?? 0 );

		if ( empty( $shipping_method ) || $shipping_cost <= 0 ) {
			return;
		}

		// Add shipping line item to order
		$shipping_item = new WC_Order_Item_Shipping();
		$shipping_item->set_method_title( __( 'Remaining Balance Shipping', 'deposits-remaining-balance-shipping' ) );
		$shipping_item->set_method_id( $shipping_method );
		$shipping_item->set_total( $shipping_cost );

		$order->add_item( $shipping_item );
		$order->calculate_totals();
		$order->save();

		// Add order note
		$order_note = sprintf(
			/* translators: %s: shipping cost */
			__( 'Shipping cost added for remaining balance payment: %s', 'deposits-remaining-balance-shipping' ),
			wc_price( $shipping_cost )
		);
		$order->add_order_note( $order_note );
		
		// Log successful shipping processing
		$logger->info( 'Shipping cost added to order: ' . $order_id . ' - Cost: ' . $shipping_cost, array( 'source' => 'deposits-remaining-balance-shipping' ) );
	}

	/**
	 * Enqueue scripts and styles
	 */
	public function enqueue_scripts() {
		if ( ! is_checkout_pay_page() ) {
			return;
		}

		$logger = wc_get_logger();
		
		// Get order from URL parameters or global variable
		$order = null;
		
		// Try to get order from global variable first
		global $order;
		if ( $order && is_object( $order ) && is_a( $order, 'WC_Order' ) ) {
			$logger->info( 'Order found from global variable for scripts, ID: ' . $order->get_id(), array( 'source' => 'deposits-remaining-balance-shipping' ) );
		} else {
			// Try to get order from URL parameters or path
			$order_id = 0;
			
			// Check query parameters first
			if ( isset( $_GET['order_id'] ) ) {
				$order_id = intval( $_GET['order_id'] );
			} elseif ( isset( $_GET['order-pay'] ) ) {
				$order_id = intval( $_GET['order-pay'] );
			} else {
				// Extract order ID from URL path
				$current_url = $_SERVER['REQUEST_URI'];
				if ( preg_match( '/\/checkout\/order-pay\/(\d+)/', $current_url, $matches ) ) {
					$order_id = intval( $matches[1] );
					$logger->info( 'Order ID extracted from URL path for scripts: ' . $order_id, array( 'source' => 'deposits-remaining-balance-shipping' ) );
				}
			}
			
			if ( $order_id ) {
				$order = wc_get_order( $order_id );
				if ( $order ) {
					$logger->info( 'Order found from URL for scripts, ID: ' . $order->get_id(), array( 'source' => 'deposits-remaining-balance-shipping' ) );
				}
			}
		}
		
		// Ensure we have a valid order object
		if ( ! $order || ! is_object( $order ) || ! is_a( $order, 'WC_Order' ) ) {
			return;
		}
		
		if ( ! WC_Deposits_RBS_Order_Identifier::needs_shipping_calculation( $order ) ) {
			return;
		}

		// Get file modification times for cache busting
		$js_file = WC_DEPOSITS_RBS_PLUGIN_PATH . '/assets/js/shipping-calculator.js';
		$css_file = WC_DEPOSITS_RBS_PLUGIN_PATH . '/assets/css/shipping-calculator.css';
		
		$js_version = file_exists( $js_file ) ? filemtime( $js_file ) : WC_DEPOSITS_RBS_VERSION;
		$css_version = file_exists( $css_file ) ? filemtime( $css_file ) : WC_DEPOSITS_RBS_VERSION;

		wp_enqueue_script(
			'wc-deposits-rbs-shipping',
			WC_DEPOSITS_RBS_PLUGIN_URL . '/assets/js/shipping-calculator.js',
			array( 'jquery' ),
			$js_version,
			true
		);

		wp_localize_script(
			'wc-deposits-rbs-shipping',
			'wc_deposits_rbs_shipping',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'wc_deposits_rbs_shipping_nonce' ),
				'order_id' => $order->get_id(),
				'strings' => array(
					'calculating' => __( 'Calculating shipping...', 'deposits-remaining-balance-shipping' ),
					'error' => __( 'Error calculating shipping', 'deposits-remaining-balance-shipping' ),
					'calculate_shipping' => __( 'Calculate Shipping', 'deposits-remaining-balance-shipping' ),
					'no_shipping' => __( 'No shipping options available for your location.', 'deposits-remaining-balance-shipping' ),
				),
			)
		);

		wp_enqueue_style(
			'wc-deposits-rbs-shipping',
			WC_DEPOSITS_RBS_PLUGIN_URL . '/assets/css/shipping-calculator.css',
			array(),
			$css_version
		);
	}
}
