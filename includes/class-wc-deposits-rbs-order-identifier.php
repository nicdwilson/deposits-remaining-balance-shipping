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

		// First, check if this is a payment plan order - these should be excluded
		if ( self::is_payment_plan_order( $order ) ) {
			$logger->info( 'Order is a payment plan order, excluding from remaining balance functionality', array( 'source' => 'deposits-remaining-balance-shipping' ) );
			return false;
		}

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
	 * Check if the order is a payment plan order (scheduled order created by deposits plugin)
	 *
	 * @param WC_Order $order Order object
	 * @return bool
	 */
	public static function is_payment_plan_order( $order ) {
		$logger = wc_get_logger();
		
		if ( ! $order || ! is_object( $order ) || ! is_a( $order, 'WC_Order' ) ) {
			return false;
		}

		$logger->info( 'Checking if order is payment plan order: ' . $order->get_id(), array( 'source' => 'deposits-remaining-balance-shipping' ) );

		// Method 1: Check if order has scheduled-payment status (this is the most reliable indicator)
		$order_status = $order->get_status();
		$logger->info( 'Order status: ' . $order_status, array( 'source' => 'deposits-remaining-balance-shipping' ) );
		
		if ( 'scheduled-payment' === $order_status ) {
			$logger->info( 'Order has scheduled-payment status, treating as payment plan order', array( 'source' => 'deposits-remaining-balance-shipping' ) );
			return true;
		}

		// Method 2: Check if parent order has payment plan items (more reliable than created_via)
		$parent_order_id = $order->get_parent_id();
		if ( $parent_order_id ) {
			$parent_order = wc_get_order( $parent_order_id );
			if ( $parent_order ) {
				$logger->info( 'Checking parent order for payment plan items: ' . $parent_order_id, array( 'source' => 'deposits-remaining-balance-shipping' ) );
				
				foreach ( $parent_order->get_items() as $item ) {
					$payment_plan_id = $item->get_meta( 'payment_plan' ) ?: $item->get_meta( '_payment_plan' );
					if ( $payment_plan_id ) {
						$logger->info( 'Parent order has payment plan item with ID: ' . $payment_plan_id . ', treating as payment plan order', array( 'source' => 'deposits-remaining-balance-shipping' ) );
						return true;
					}
				}
				
				$logger->info( 'Parent order has no payment plan items', array( 'source' => 'deposits-remaining-balance-shipping' ) );
			} else {
				$logger->warning( 'Parent order not found: ' . $parent_order_id, array( 'source' => 'deposits-remaining-balance-shipping' ) );
			}
		} else {
			$logger->info( 'Order has no parent order ID', array( 'source' => 'deposits-remaining-balance-shipping' ) );
		}

		// Method 3: Check if current order items have payment plan meta
		$logger->info( 'Checking current order items for payment plan meta', array( 'source' => 'deposits-remaining-balance-shipping' ) );
		foreach ( $order->get_items() as $item ) {
			$payment_plan_id = $item->get_meta( 'payment_plan' ) ?: $item->get_meta( '_payment_plan' );
			if ( $payment_plan_id ) {
				$logger->info( 'Current order has payment plan item with ID: ' . $payment_plan_id . ', treating as payment plan order', array( 'source' => 'deposits-remaining-balance-shipping' ) );
				return true;
			}
		}

		// Method 4: Only use created_via as a last resort, and be more specific
		$created_via = $order->get_created_via();
		$logger->info( 'Order created_via: ' . ( $created_via ? $created_via : 'none' ), array( 'source' => 'deposits-remaining-balance-shipping' ) );
		
		// Only treat as payment plan order if created_via is 'wc_deposits' AND order has pending-deposit status
		// (This is a more conservative approach to avoid false positives)
		if ( 'wc_deposits' === $created_via && 'pending-deposit' === $order_status ) {
			// Double-check that this is actually a payment plan order by checking parent
			if ( $parent_order_id ) {
				$parent_order = wc_get_order( $parent_order_id );
				if ( $parent_order ) {
					foreach ( $parent_order->get_items() as $item ) {
						$payment_plan_id = $item->get_meta( 'payment_plan' ) ?: $item->get_meta( '_payment_plan' );
						if ( $payment_plan_id ) {
							$logger->info( 'Order created via deposits with pending-deposit status and parent has payment plan, treating as payment plan order', array( 'source' => 'deposits-remaining-balance-shipping' ) );
							return true;
						}
					}
				}
			}
		}

		$logger->info( 'Order is not a payment plan order', array( 'source' => 'deposits-remaining-balance-shipping' ) );
		return false;
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
		
		// Check if this is a final payment plan order - these should get shipping
		if ( self::is_final_payment_plan_order( $order ) ) {
			$logger->info( 'Order is a final payment plan order, shipping calculation needed', array( 'source' => 'deposits-remaining-balance-shipping' ) );
			
			// Check if shipping was already paid with deposit
			if ( self::was_shipping_paid_with_deposit( $order ) ) {
				$logger->info( 'Shipping was already paid with deposit', array( 'source' => 'deposits-remaining-balance-shipping' ) );
				return false;
			}
			
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
			
			$logger->info( 'Final payment plan order has ' . $item_count . ' items, physical products: ' . ( $has_physical_products ? 'yes' : 'no' ), array( 'source' => 'deposits-remaining-balance-shipping' ) );
			
			return $has_physical_products;
		}
		
		// Check if this is a regular payment plan order (not final) - these should not get shipping
		if ( self::is_payment_plan_order( $order ) ) {
			$logger->info( 'Order is a regular payment plan order (not final), shipping calculation not needed', array( 'source' => 'deposits-remaining-balance-shipping' ) );
			return false;
		}
		
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

	/**
	 * Debug method to help troubleshoot order identification
	 *
	 * @param WC_Order $order Order object
	 * @return array Debug information
	 */
	public static function debug_order_identification( $order ) {
		$logger = wc_get_logger();
		
		if ( ! $order || ! is_object( $order ) || ! is_a( $order, 'WC_Order' ) ) {
			return array( 'error' => 'Invalid order object' );
		}

		$debug_info = array(
			'order_id' => $order->get_id(),
			'order_status' => $order->get_status(),
			'created_via' => $order->get_created_via(),
			'parent_order_id' => $order->get_parent_id(),
			'is_payment_plan_order' => self::is_payment_plan_order( $order ),
			'is_final_payment_plan_order' => self::is_final_payment_plan_order( $order ),
			'is_remaining_balance_order' => self::is_remaining_balance_order( $order ),
			'needs_shipping_calculation' => self::needs_shipping_calculation( $order ),
			'items' => array(),
		);

		// Debug order items
		foreach ( $order->get_items() as $item ) {
			$item_debug = array(
				'item_id' => $item->get_id(),
				'product_id' => $item->get_product_id(),
				'original_order_id' => $item->get_meta( '_original_order_id' ),
				'payment_plan' => $item->get_meta( 'payment_plan' ) ?: $item->get_meta( '_payment_plan' ),
				'is_deposit' => $item->get_meta( 'is_deposit' ) ?: $item->get_meta( '_is_deposit' ),
			);
			$debug_info['items'][] = $item_debug;
		}

		// Debug parent order if exists
		if ( $order->get_parent_id() ) {
			$parent_order = wc_get_order( $order->get_parent_id() );
			if ( $parent_order ) {
				$debug_info['parent_order'] = array(
					'order_id' => $parent_order->get_id(),
					'order_status' => $parent_order->get_status(),
					'has_deposit' => class_exists( 'WC_Deposits_Order_Manager' ) ? WC_Deposits_Order_Manager::has_deposit( $parent_order ) : 'unknown',
					'items' => array(),
				);

				foreach ( $parent_order->get_items() as $item ) {
					$parent_item_debug = array(
						'item_id' => $item->get_id(),
						'product_id' => $item->get_product_id(),
						'payment_plan' => $item->get_meta( 'payment_plan' ) ?: $item->get_meta( '_payment_plan' ),
						'is_deposit' => $item->get_meta( 'is_deposit' ) ?: $item->get_meta( '_is_deposit' ),
					);
					$debug_info['parent_order']['items'][] = $parent_item_debug;
				}
			}
		}

		$logger->info( 'Order identification debug info: ' . json_encode( $debug_info ), array( 'source' => 'deposits-remaining-balance-shipping' ) );
		
		return $debug_info;
	}

	/**
	 * Check if the order is the final payment in a payment plan
	 *
	 * @param WC_Order $order Order object
	 * @return bool
	 */
	public static function is_final_payment_plan_order( $order ) {
		$logger = wc_get_logger();
		
		if ( ! $order || ! is_object( $order ) || ! is_a( $order, 'WC_Order' ) ) {
			return false;
		}

		$logger->info( 'Checking if order is final payment plan order: ' . $order->get_id(), array( 'source' => 'deposits-remaining-balance-shipping' ) );

		// First, check if this is a payment plan order
		if ( ! self::is_payment_plan_order( $order ) ) {
			$logger->info( 'Order is not a payment plan order', array( 'source' => 'deposits-remaining-balance-shipping' ) );
			return false;
		}

		// Get the parent order
		$parent_order_id = $order->get_parent_id();
		if ( ! $parent_order_id ) {
			$logger->info( 'Order has no parent order ID', array( 'source' => 'deposits-remaining-balance-shipping' ) );
			return false;
		}

		$parent_order = wc_get_order( $parent_order_id );
		if ( ! $parent_order ) {
			$logger->warning( 'Parent order not found: ' . $parent_order_id, array( 'source' => 'deposits-remaining-balance-shipping' ) );
			return false;
		}

		// Get the payment plan from the parent order
		$payment_plan_id = null;
		foreach ( $parent_order->get_items() as $item ) {
			$plan_id = $item->get_meta( 'payment_plan' ) ?: $item->get_meta( '_payment_plan' );
			if ( $plan_id ) {
				$payment_plan_id = $plan_id;
				break;
			}
		}

		if ( ! $payment_plan_id ) {
			$logger->info( 'No payment plan found in parent order', array( 'source' => 'deposits-remaining-balance-shipping' ) );
			return false;
		}

		$logger->info( 'Payment plan ID: ' . $payment_plan_id, array( 'source' => 'deposits-remaining-balance-shipping' ) );

		// Get the payment plan object
		if ( ! class_exists( 'WC_Deposits_Plans_Manager' ) ) {
			$logger->warning( 'WC_Deposits_Plans_Manager class not found', array( 'source' => 'deposits-remaining-balance-shipping' ) );
			return false;
		}

		$payment_plan = WC_Deposits_Plans_Manager::get_plan( absint( $payment_plan_id ) );
		if ( ! $payment_plan ) {
			$logger->warning( 'Payment plan not found: ' . $payment_plan_id, array( 'source' => 'deposits-remaining-balance-shipping' ) );
			return false;
		}

		// Get the schedule to determine total number of payments
		$schedule = $payment_plan->get_schedule();
		$total_payments = count( $schedule );
		$logger->info( 'Payment plan has ' . $total_payments . ' total payments', array( 'source' => 'deposits-remaining-balance-shipping' ) );

		// Extract the current payment number from the order item name
		$current_payment_number = null;
		foreach ( $order->get_items() as $item ) {
			$item_name = $item->get_name();
			$logger->info( 'Order item name: ' . $item_name, array( 'source' => 'deposits-remaining-balance-shipping' ) );
			
			// Look for "Payment #X for" pattern
			if ( preg_match( '/Payment #(\d+) for/', $item_name, $matches ) ) {
				$current_payment_number = intval( $matches[1] );
				$logger->info( 'Extracted payment number: ' . $current_payment_number, array( 'source' => 'deposits-remaining-balance-shipping' ) );
				break;
			}
		}

		if ( $current_payment_number === null ) {
			$logger->warning( 'Could not extract payment number from order items', array( 'source' => 'deposits-remaining-balance-shipping' ) );
			return false;
		}

		// Check if this is the final payment
		// Payment numbers start at 2 (since 1 is the deposit), so final payment = total_payments
		$is_final_payment = ( $current_payment_number === $total_payments );
		
		$logger->info( 'Current payment: ' . $current_payment_number . ', Total payments: ' . $total_payments . ', Is final: ' . ( $is_final_payment ? 'yes' : 'no' ), array( 'source' => 'deposits-remaining-balance-shipping' ) );
		
		return $is_final_payment;
	}
}
