<?php
/**
 * Order Identifier for Deposits Remaining Balance Shipping
 *
 * @package deposits-remaining-balance-shipping
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Deposits_RBS_Order_Identifier class
 */
class WC_Deposits_RBS_Order_Identifier {

	/**
	 * Check if the current order is a remaining balance order for deposits
	 *
	 * @param WC_Order $order Order object
	 * @return bool
	 */
	public static function is_remaining_balance_order( $order ) {
		$logger = wc_get_logger();
		
		if ( ! $order || ! is_object( $order ) || ! is_a( $order, 'WC_Order' ) ) {
			$logger->warning( 'Invalid order object in is_remaining_balance_order', array( 'source' => 'deposits-remaining-balance-shipping' ) );
			return false;
		}

		$logger->info( 'Checking if order is remaining balance order: ' . $order->get_id(), array( 'source' => 'deposits-remaining-balance-shipping' ) );

		// Check if this is a follow-up order (remaining balance order)
		// Use multiple methods to detect follow-up orders
		$is_follow_up = false;
		
		// Method 1: Check if WooCommerce Deposits plugin is available and use its method
		if ( class_exists( 'WC_Deposits_Order_Manager' ) ) {
			$is_follow_up = WC_Deposits_Order_Manager::is_follow_up_order( $order );
			$logger->info( 'Using WC_Deposits_Order_Manager::is_follow_up_order: ' . ( $is_follow_up ? 'yes' : 'no' ), array( 'source' => 'deposits-remaining-balance-shipping' ) );
		}
		
		// Method 2: Check for parent order ID
		if ( ! $is_follow_up && $order->get_parent_id() ) {
			$is_follow_up = true;
			$logger->info( 'Order has parent ID, treating as follow-up order', array( 'source' => 'deposits-remaining-balance-shipping' ) );
		}
		
		// Method 3: Check for original order ID in items
		if ( ! $is_follow_up ) {
			foreach ( $order->get_items() as $item ) {
				$original_order_id = $item->get_meta( '_original_order_id' );
				if ( $original_order_id ) {
					$is_follow_up = true;
					$logger->info( 'Found original_order_id in item, treating as follow-up order', array( 'source' => 'deposits-remaining-balance-shipping' ) );
					break;
				}
			}
		}
		
		if ( ! $is_follow_up ) {
			$logger->info( 'Order is not a follow-up order', array( 'source' => 'deposits-remaining-balance-shipping' ) );
			return false;
		}
		
		$logger->info( 'Order is a follow-up order', array( 'source' => 'deposits-remaining-balance-shipping' ) );

		// Check if this order has deposit items without payment plans
		$has_deposit_items = false;
		$has_payment_plan = false;
		$item_count = 0;

		foreach ( $order->get_items() as $item ) {
			$item_count++;
			
			// Debug item data
			$item_data = $item->get_data();
			$logger->info( 'Item ' . $item_count . ' data: ' . json_encode( $item_data ), array( 'source' => 'deposits-remaining-balance-shipping' ) );
			
			// Check for deposit meta using multiple possible keys
			$is_deposit_meta = $item->get_meta( 'is_deposit' ) || $item->get_meta( '_is_deposit' );
			$logger->info( 'Item ' . $item_count . ' is_deposit meta: ' . ( $is_deposit_meta ? 'yes' : 'no' ), array( 'source' => 'deposits-remaining-balance-shipping' ) );
			
			// Check for original order ID (indicates this is a remaining balance item)
			$original_order_id = $item->get_meta( '_original_order_id' );
			$logger->info( 'Item ' . $item_count . ' original_order_id: ' . ( $original_order_id ? $original_order_id : 'none' ), array( 'source' => 'deposits-remaining-balance-shipping' ) );
			
			// For remaining balance orders, consider items as deposits if they have an original order ID
			$is_deposit = false;
			if ( $original_order_id ) {
				$is_deposit = true;
				$logger->info( 'Item ' . $item_count . ': treating as deposit due to original_order_id', array( 'source' => 'deposits-remaining-balance-shipping' ) );
			} else {
				// Try to use WooCommerce Deposits plugin classes if available
				if ( class_exists( 'WC_Deposits_Order_Item_Manager' ) ) {
					// Convert item to array format expected by deposits plugin
					$item_array = array(
						'type' => $item->get_type(),
						'is_deposit' => $item->get_meta( 'is_deposit' ) || $item->get_meta( '_is_deposit' ),
						'payment_plan' => $item->get_meta( 'payment_plan' ) || $item->get_meta( '_payment_plan' ),
					);
					$logger->info( 'Item ' . $item_count . ' array: ' . json_encode( $item_array ), array( 'source' => 'deposits-remaining-balance-shipping' ) );
					
					$is_deposit = WC_Deposits_Order_Item_Manager::is_deposit( $item_array );
					$logger->info( 'Item ' . $item_count . ': is_deposit = ' . ( $is_deposit ? 'yes' : 'no' ), array( 'source' => 'deposits-remaining-balance-shipping' ) );
				} else {
					// Fallback: check for deposit meta directly
					$is_deposit = $is_deposit_meta;
					$logger->info( 'Item ' . $item_count . ': using fallback deposit detection: ' . ( $is_deposit ? 'yes' : 'no' ), array( 'source' => 'deposits-remaining-balance-shipping' ) );
				}
			}
			
			if ( $is_deposit ) {
				$has_deposit_items = true;
				
				// Check if this item has a payment plan
				$payment_plan = null;
				if ( class_exists( 'WC_Deposits_Order_Item_Manager' ) && isset( $item_array ) ) {
					$payment_plan = WC_Deposits_Order_Item_Manager::get_payment_plan( $item_array );
				} else {
					// For items with original_order_id, check payment plan meta directly
					$payment_plan_id = $item->get_meta( 'payment_plan' ) ?: $item->get_meta( '_payment_plan' );
					if ( $payment_plan_id && class_exists( 'WC_Deposits_Plans_Manager' ) ) {
						$payment_plan = WC_Deposits_Plans_Manager::get_plan( absint( $payment_plan_id ) );
					}
				}
				
				if ( $payment_plan ) {
					$has_payment_plan = true;
					$logger->info( 'Item ' . $item_count . ' has payment plan: ' . $payment_plan->get_id(), array( 'source' => 'deposits-remaining-balance-shipping' ) );
					break;
				} else {
					$logger->info( 'Item ' . $item_count . ' has no payment plan', array( 'source' => 'deposits-remaining-balance-shipping' ) );
				}
			}
		}
		
		$logger->info( 'Order has deposit items: ' . ( $has_deposit_items ? 'yes' : 'no' ) . ', has payment plan: ' . ( $has_payment_plan ? 'yes' : 'no' ), array( 'source' => 'deposits-remaining-balance-shipping' ) );

		// Return true only if we have deposit items but no payment plans
		$result = $has_deposit_items && ! $has_payment_plan;
		$logger->info( 'Order is remaining balance order: ' . ( $result ? 'yes' : 'no' ), array( 'source' => 'deposits-remaining-balance-shipping' ) );
		
		return $result;
	}

	/**
	 * Check if shipping was already paid during deposit
	 *
	 * @param WC_Order $order Order object
	 * @return bool
	 */
	public static function was_shipping_paid_with_deposit( $order ) {
		$logger = wc_get_logger();
		
		if ( ! $order || ! is_object( $order ) || ! is_a( $order, 'WC_Order' ) ) {
			return false;
		}

		// Get the parent order
		$parent_order_id = $order->get_parent_id();
		if ( ! $parent_order_id ) {
			$logger->info( 'Order has no parent order ID', array( 'source' => 'deposits-remaining-balance-shipping' ) );
			return false;
		}
		
		$logger->info( 'Parent order ID: ' . $parent_order_id, array( 'source' => 'deposits-remaining-balance-shipping' ) );

		$parent_order = wc_get_order( $parent_order_id );
		if ( ! $parent_order ) {
			$logger->warning( 'Parent order not found: ' . $parent_order_id, array( 'source' => 'deposits-remaining-balance-shipping' ) );
			return false;
		}

		// Check if shipping was charged on the parent order
		$shipping_total = $parent_order->get_shipping_total();
		$logger->info( 'Parent order shipping total: ' . $shipping_total, array( 'source' => 'deposits-remaining-balance-shipping' ) );
		
		$result = $shipping_total > 0;
		$logger->info( 'Shipping was paid with deposit: ' . ( $result ? 'yes' : 'no' ), array( 'source' => 'deposits-remaining-balance-shipping' ) );
		
		return $result;
	}

	/**
	 * Get the parent order for a remaining balance order
	 *
	 * @param WC_Order $order Order object
	 * @return WC_Order|false
	 */
	public static function get_parent_order( $order ) {
		if ( ! $order || ! is_object( $order ) || ! is_a( $order, 'WC_Order' ) ) {
			return false;
		}

		$parent_order_id = $order->get_parent_id();
		if ( ! $parent_order_id ) {
			return false;
		}

		return wc_get_order( $parent_order_id );
	}

	/**
	 * Check if the order needs shipping calculation
	 *
	 * @param WC_Order $order Order object
	 * @return bool
	 */
	public static function needs_shipping_calculation( $order ) {
		$logger = wc_get_logger();
		
		$logger->info( 'Checking if order needs shipping calculation: ' . $order->get_id(), array( 'source' => 'deposits-remaining-balance-shipping' ) );
		
		// Check if this is a remaining balance order
		if ( ! self::is_remaining_balance_order( $order ) ) {
			$logger->info( 'Order is not a remaining balance order', array( 'source' => 'deposits-remaining-balance-shipping' ) );
			return false;
		}
		
		$logger->info( 'Order is a remaining balance order', array( 'source' => 'deposits-remaining-balance-shipping' ) );

		// Check if shipping was already paid with deposit
		if ( self::was_shipping_paid_with_deposit( $order ) ) {
			$logger->info( 'Shipping was already paid with deposit', array( 'source' => 'deposits-remaining-balance-shipping' ) );
			return false;
		}
		
		$logger->info( 'Shipping was not paid with deposit', array( 'source' => 'deposits-remaining-balance-shipping' ) );

		// Check if the order has physical products that need shipping
		$has_physical_products = false;
		$item_count = 0;
		foreach ( $order->get_items() as $item ) {
			$item_count++;
			$product = $item->get_product();
			if ( $product ) {
				$logger->info( 'Item ' . $item_count . ': Product ID ' . $product->get_id() . ', needs shipping: ' . ( $product->needs_shipping() ? 'yes' : 'no' ), array( 'source' => 'deposits-remaining-balance-shipping' ) );
				if ( $product->needs_shipping() ) {
					$has_physical_products = true;
				}
			} else {
				$logger->warning( 'Item ' . $item_count . ': Product not found', array( 'source' => 'deposits-remaining-balance-shipping' ) );
			}
		}
		
		$logger->info( 'Order has ' . $item_count . ' items, physical products: ' . ( $has_physical_products ? 'yes' : 'no' ), array( 'source' => 'deposits-remaining-balance-shipping' ) );

		return $has_physical_products;
	}
}
