<?php
/**
 * Shipping Calculator for Deposits Remaining Balance Shipping
 *
 * @package deposits-remaining-balance-shipping
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Deposits_RBS_Shipping_Calculator class
 */
class WC_Deposits_RBS_Shipping_Calculator {

	/**
	 * Class instance
	 *
	 * @var WC_Deposits_RBS_Shipping_Calculator
	 */
	private static $instance;

	/**
	 * Get class instance
	 *
	 * @return WC_Deposits_RBS_Shipping_Calculator
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
		// Add AJAX handlers for shipping calculations
		add_action( 'wp_ajax_wc_deposits_rbs_calculate_shipping', array( $this, 'ajax_calculate_shipping' ) );
		add_action( 'wp_ajax_nopriv_wc_deposits_rbs_calculate_shipping', array( $this, 'ajax_calculate_shipping' ) );
		
		// Add AJAX handler for updating order totals
		add_action( 'wp_ajax_wc_deposits_rbs_update_order_total', array( $this, 'ajax_update_order_total' ) );
		add_action( 'wp_ajax_nopriv_wc_deposits_rbs_update_order_total', array( $this, 'ajax_update_order_total' ) );
	}

	/**
	 * Calculate shipping for a remaining balance order
	 *
	 * @param WC_Order $order Order object
	 * @param array $shipping_address Shipping address
	 * @return array
	 */
	public function calculate_shipping_for_order( $order, $shipping_address = array() ) {
		$logger = wc_get_logger();
		
		if ( ! $order || ! is_object( $order ) || ! is_a( $order, 'WC_Order' ) ) {
			return array();
		}
		
		if ( ! WC_Deposits_RBS_Order_Identifier::needs_shipping_calculation( $order ) ) {
			return array();
		}

		// Build shipping package from order items
		$package = $this->build_shipping_package( $order );

		// If no shipping address provided, use order's shipping address
		if ( empty( $shipping_address ) ) {
			$shipping_address = array(
				'country'  => $order->get_shipping_country(),
				'state'    => $order->get_shipping_state(),
				'postcode' => $order->get_shipping_postcode(),
				'city'     => $order->get_shipping_city(),
				'address_1' => $order->get_shipping_address_1(),
				'address_2' => $order->get_shipping_address_2(),
			);
			
			// Log shipping address for debugging
			$logger->info( 'Shipping address from order: ' . json_encode( $shipping_address ), array( 'source' => 'deposits-remaining-balance-shipping' ) );
			
			// If shipping address is empty, try billing address
			if ( empty( $shipping_address['country'] ) ) {
				$shipping_address = array(
					'country'  => $order->get_billing_country(),
					'state'    => $order->get_billing_state(),
					'postcode' => $order->get_billing_postcode(),
					'city'     => $order->get_billing_city(),
					'address_1' => $order->get_billing_address_1(),
					'address_2' => $order->get_billing_address_2(),
				);
				$logger->info( 'Using billing address as shipping address: ' . json_encode( $shipping_address ), array( 'source' => 'deposits-remaining-balance-shipping' ) );
			}
		}

		// Calculate shipping rates
		$rates = $this->get_shipping_rates( $package, $shipping_address );
		
		// Log the results
		$logger->info( 'Shipping rates calculated: ' . count( $rates ) . ' rates found', array( 'source' => 'deposits-remaining-balance-shipping' ) );

		return $rates;
	}

	/**
	 * Build shipping package from order items
	 *
	 * @param WC_Order $order Order object
	 * @return array
	 */
	private function build_shipping_package( $order ) {
		if ( ! $order || ! is_object( $order ) || ! is_a( $order, 'WC_Order' ) ) {
			return array();
		}
		$package = array(
			'contents' => array(),
			'contents_cost' => 0,
			'applied_coupons' => array(),
			'user' => array(
				'ID' => $order->get_customer_id(),
			),
			'destination' => array(
				'country'  => $order->get_shipping_country(),
				'state'    => $order->get_shipping_state(),
				'postcode' => $order->get_shipping_postcode(),
				'city'     => $order->get_shipping_city(),
				'address_1' => $order->get_shipping_address_1(),
				'address_2' => $order->get_shipping_address_2(),
			),
		);

		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();
			if ( ! $product || ! $product->needs_shipping() ) {
				continue;
			}

			$package['contents'][] = array(
				'product_id' => $product->get_id(),
				'variation_id' => $product->get_type() === 'variation' ? $product->get_id() : 0,
				'quantity' => $item->get_quantity(),
				'data' => $product,
				'line_tax_data' => array(
					'subtotal' => $item->get_subtotal_tax(),
					'total' => $item->get_total_tax(),
				),
				'line_subtotal' => $item->get_subtotal(),
				'line_subtotal_tax' => $item->get_subtotal_tax(),
				'line_total' => $item->get_total(),
				'line_tax' => $item->get_total_tax(),
			);

			$package['contents_cost'] += $item->get_total();
		}

		return $package;
	}

	/**
	 * Get shipping rates for a package
	 *
	 * @param array $package Shipping package
	 * @param array $destination Shipping destination
	 * @return array
	 */
	private function get_shipping_rates( $package, $destination ) {
		$logger = wc_get_logger();
		
		// Check if WooCommerce is available
		if ( ! function_exists( 'WC' ) || ! WC() || ! WC()->shipping() ) {
			$logger->error( 'WooCommerce shipping not available', array( 'source' => 'deposits-remaining-balance-shipping' ) );
			return array();
		}

		// Log package details for debugging
		$logger->info( 'Package contents: ' . count( $package['contents'] ) . ' items', array( 'source' => 'deposits-remaining-balance-shipping' ) );
		$logger->info( 'Package destination: ' . json_encode( $destination ), array( 'source' => 'deposits-remaining-balance-shipping' ) );

		// Update package destination
		$package['destination'] = $destination;

		// Calculate shipping using WooCommerce's shipping system
		$packages = array( $package );
		$calculated_packages = WC()->shipping()->calculate_shipping( $packages );

		if ( empty( $calculated_packages ) ) {
			$logger->warning( 'No calculated packages returned', array( 'source' => 'deposits-remaining-balance-shipping' ) );
			return array();
		}

		if ( empty( $calculated_packages[0]['rates'] ) ) {
			$logger->warning( 'No shipping rates found for package', array( 'source' => 'deposits-remaining-balance-shipping' ) );
			return array();
		}

		$logger->info( 'Found ' . count( $calculated_packages[0]['rates'] ) . ' shipping rates', array( 'source' => 'deposits-remaining-balance-shipping' ) );
		return $calculated_packages[0]['rates'];
	}

	/**
	 * AJAX handler for shipping calculation
	 */
	public function ajax_calculate_shipping() {
		$logger = wc_get_logger();
		
		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['nonce'], 'wc_deposits_rbs_shipping_nonce' ) ) {
			$logger->warning( 'Security check failed for shipping calculation', array( 'source' => 'deposits-remaining-balance-shipping' ) );
			wp_die( __( 'Security check failed', 'deposits-remaining-balance-shipping' ) );
		}

		$order_id = intval( $_POST['order_id'] );
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			$logger->error( 'Order not found for shipping calculation: ' . $order_id, array( 'source' => 'deposits-remaining-balance-shipping' ) );
			wp_send_json_error( __( 'Order not found', 'deposits-remaining-balance-shipping' ) );
		}

		// Get shipping address from POST data
		$shipping_address = array(
			'country'  => sanitize_text_field( $_POST['shipping_country'] ?? '' ),
			'state'    => sanitize_text_field( $_POST['shipping_state'] ?? '' ),
			'postcode' => sanitize_text_field( $_POST['shipping_postcode'] ?? '' ),
			'city'     => sanitize_text_field( $_POST['shipping_city'] ?? '' ),
			'address_1' => sanitize_text_field( $_POST['shipping_address_1'] ?? '' ),
			'address_2' => sanitize_text_field( $_POST['shipping_address_2'] ?? '' ),
		);

		// Calculate shipping rates
		$rates = $this->calculate_shipping_for_order( $order, $shipping_address );

		wp_send_json_success( array(
			'rates' => $rates,
			'formatted_rates' => $this->format_shipping_rates( $rates ),
		) );
	}

	/**
	 * Format shipping rates for display
	 *
	 * @param array $rates Shipping rates
	 * @return array
	 */
	private function format_shipping_rates( $rates ) {
		$formatted_rates = array();
		
		foreach ( $rates as $rate ) {
			$formatted_rates[] = array(
				'id' => $rate->id,
				'label' => $rate->label,
				'cost' => $rate->cost,
				'formatted_cost' => wc_price( $rate->cost ),
			);
		}

		return $formatted_rates;
	}

	/**
	 * AJAX handler for updating order total
	 */
	public function ajax_update_order_total() {
		$logger = wc_get_logger();
		
		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['nonce'], 'wc_deposits_rbs_shipping_nonce' ) ) {
			$logger->warning( 'Security check failed for order total update', array( 'source' => 'deposits-remaining-balance-shipping' ) );
			wp_send_json_error( __( 'Security check failed', 'deposits-remaining-balance-shipping' ) );
		}

		$order_id = intval( $_POST['order_id'] );
		$shipping_cost = floatval( $_POST['shipping_cost'] );
		$shipping_method = sanitize_text_field( $_POST['shipping_method'] );

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			$logger->error( 'Order not found for total update: ' . $order_id, array( 'source' => 'deposits-remaining-balance-shipping' ) );
			wp_send_json_error( __( 'Order not found', 'deposits-remaining-balance-shipping' ) );
		}

		try {
			// Add debugging to see what values we're receiving
			$logger->info( 'AJAX received - Order ID: ' . $order_id . ', Method: "' . $shipping_method . '", Cost: ' . $shipping_cost, array( 'source' => 'deposits-remaining-balance-shipping' ) );
			
			// Just validate the shipping data without saving the order
			// The order will be saved during the normal form submission process
			
			if ( empty( $shipping_method ) ) {
				$logger->warning( 'Shipping method is empty', array( 'source' => 'deposits-remaining-balance-shipping' ) );
				wp_send_json_error( __( 'Shipping method is required', 'deposits-remaining-balance-shipping' ) );
			}
			
			if ( $shipping_cost < 0 ) {
				$logger->warning( 'Shipping cost is negative: ' . $shipping_cost, array( 'source' => 'deposits-remaining-balance-shipping' ) );
				wp_send_json_error( __( 'Invalid shipping cost', 'deposits-remaining-balance-shipping' ) );
			}
			
			$logger->info( 'Shipping data validated successfully: ' . $order_id . ' - Shipping method: ' . $shipping_method . ' - Cost: ' . $shipping_cost, array( 'source' => 'deposits-remaining-balance-shipping' ) );
			
			wp_send_json_success( array(
				'message' => 'Shipping data validated successfully',
				'shipping_method' => $shipping_method,
				'shipping_cost' => $shipping_cost,
			) );
			
		} catch ( Exception $e ) {
			$logger->error( 'Error validating shipping data: ' . $e->getMessage(), array( 'source' => 'deposits-remaining-balance-shipping' ) );
			wp_send_json_error( __( 'Error validating shipping data: ', 'deposits-remaining-balance-shipping' ) . $e->getMessage() );
		}
	}
}
