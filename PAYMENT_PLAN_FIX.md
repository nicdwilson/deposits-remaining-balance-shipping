# Payment Plan Order Identification Fix

## Problem

The deposits-remaining-balance-shipping plugin was incorrectly identifying **payment plan orders** as **remaining balance orders**, causing shipping functionality to be applied to payment plan orders when it should not be.

## Root Cause

The original `is_remaining_balance_order()` method was checking for:
1. Follow-up orders (has parent order)
2. Deposit items (has `original_order_id`)
3. No payment plan

However, payment plan orders also have these characteristics, leading to false positives.

## Solution

Added a new method `is_payment_plan_order()` that specifically identifies payment plan orders using multiple detection methods:

### Detection Methods (in order of reliability)

1. **Status Check**: Orders with `scheduled-payment` status (most reliable)
2. **Parent Order Check**: Check if the parent order contains items with payment plan meta
3. **Current Order Check**: Check if the current order items have payment plan meta
4. **Created Via Check**: Conservative check using `created_via = 'wc_deposits'` combined with status

### Key Insight

Both payment plan orders and remaining balance orders are created using the same `create_order()` method, which sets `created_via` to `'wc_deposits'`. The key differences are:

- **Payment Plan Orders**: Created by `schedule_orders_for_plan()` with status `'scheduled-payment'`
- **Remaining Balance Orders**: Created by `invoice_remaining_balance` action with status `'pending-deposit'`

### Implementation

- Modified `is_remaining_balance_order()` to first check if the order is a payment plan order and exclude it
- Modified `needs_shipping_calculation()` to explicitly exclude payment plan orders
- Added comprehensive logging for debugging
- Added debug method for troubleshooting order identification

## Final Payment Plan Order Shipping

### New Feature

Added support for shipping on **final payment plan orders**. This allows customers to select shipping options only on the last payment of a payment plan.

### How It Works

1. **Payment Plan Structure**: Payment plans have a schedule with multiple payments
2. **Payment Numbering**: Each payment gets a number (2, 3, 4, etc. - since 1 is the deposit)
3. **Final Payment Detection**: Compare current payment number vs total payments in schedule
4. **Shipping Logic**: Only the final payment requires shipping selection

### Implementation Details

- Added `is_final_payment_plan_order()` method to identify final payments
- Extracts payment number from order item name ("Payment #X for [Product]")
- Compares against payment plan schedule to determine if it's the final payment
- Updated `needs_shipping_calculation()` to include final payment plan orders

## Testing

### Test Cases

1. **Payment Plan Order (Not Final)**: Should NOT show shipping options
   - Order with `scheduled-payment` status
   - Order with parent that has payment plan items
   - Order created via deposits plugin with payment plan
   - Payment number < total payments in plan

2. **Payment Plan Order (Final)**: Should show shipping options
   - Order with `scheduled-payment` status
   - Order with parent that has payment plan items
   - Payment number = total payments in plan
   - Has physical products that need shipping

3. **Remaining Balance Order**: Should show shipping options
   - Follow-up order for regular deposits (not payment plans)
   - Has parent order
   - Has items with `original_order_id`
   - No payment plan associated
   - Status `pending-deposit`

4. **Regular Order**: Should NOT show shipping options
   - Not a follow-up order
   - No parent order

### How to Test

1. Create a product with deposits enabled and a payment plan (e.g., 3 payments)
2. Place an order with the payment plan
3. Check that scheduled payment orders do NOT show shipping options (except final payment)
4. Check that the final payment order DOES show shipping options
5. Create a product with deposits enabled but no payment plan
6. Place an order with regular deposits
7. Check that the remaining balance order DOES show shipping options

## Debugging

The plugin now includes comprehensive debugging:

- Check WooCommerce logs for entries with source `'deposits-remaining-balance-shipping'`
- Look for debug messages about order identification
- Use the `debug_order_identification()` method to troubleshoot specific orders
- Look for messages about final payment plan order detection

## Files Modified

- `includes/class-wc-deposits-rbs-order-identifier.php`
  - Added `is_payment_plan_order()` method with refined detection logic
  - Added `is_final_payment_plan_order()` method for final payment detection
  - Modified `is_remaining_balance_order()` to exclude payment plan orders
  - Modified `needs_shipping_calculation()` to include final payment plan orders
  - Added `debug_order_identification()` method for troubleshooting
  - Added comprehensive logging

- `includes/class-wc-deposits-rbs-pay-order-integration.php`
  - Added debug call to help troubleshoot order identification

## Compatibility

This fix maintains backward compatibility and only affects the identification logic. Existing functionality for remaining balance orders remains unchanged.

## Version History

- **v1.0.1**: Initial fix that was too aggressive
- **v1.0.2**: Refined fix with more precise detection methods
- **v1.0.3**: Added final payment plan order shipping support
