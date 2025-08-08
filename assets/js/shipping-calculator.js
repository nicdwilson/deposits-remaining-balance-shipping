/**
 * Shipping Calculator JavaScript for Deposits Remaining Balance Shipping
 *
 * @package deposits-remaining-balance-shipping
 */

(function($) {
    'use strict';

    var WC_Deposits_RBS_Shipping = {
            init: function() {
        this.bindEvents();
        this.initializeStateSelect();
        this.initializeDefaultSelection();
    },

        bindEvents: function() {
            // Calculate shipping button
            $(document).on('click', '#wc-deposits-rbs-calculate-shipping', this.calculateShipping);
            
            // Shipping method selection
            $(document).on('change', '.wc-deposits-rbs-shipping-method-radio', this.updateShippingTotal);
            
            // Country change
            $(document).on('change', '#wc-deposits-rbs-shipping_country', this.onCountryChange);
        },

        initializeStateSelect: function() {
            var country = $('#wc-deposits-rbs-shipping_country').val();
            if (country) {
                this.loadStates(country);
            }
        },

        initializeDefaultSelection: function() {
            // Set default values if a shipping method is already selected
            var $selectedMethod = $('.wc-deposits-rbs-shipping-method-radio:checked');
            if ($selectedMethod.length > 0) {
                console.log('Found default selected method:', $selectedMethod.val());
                this.updateShippingTotal();
            } else {
                console.log('No default selected method found');
            }
        },

        onCountryChange: function() {
            var country = $(this).val();
            WC_Deposits_RBS_Shipping.loadStates(country);
        },

        loadStates: function(country) {
            var $stateSelect = $('#wc-deposits-rbs-shipping_state');
            
            if (!country) {
                $stateSelect.html('<option value="">' + wc_deposits_rbs_shipping.strings.select_state + '</option>');
                return;
            }

            $.ajax({
                url: wc_deposits_rbs_shipping.ajax_url,
                type: 'POST',
                data: {
                    action: 'wc_deposits_rbs_get_states',
                    country: country,
                    nonce: wc_deposits_rbs_shipping.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $stateSelect.html(response.data);
                    }
                }
            });
        },

        calculateShipping: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $section = $('#wc-deposits-rbs-shipping-section');
            
            // Show loading state
            $button.prop('disabled', true).text(wc_deposits_rbs_shipping.strings.calculating);
            
            // Collect shipping address data
            var shippingData = {
                action: 'wc_deposits_rbs_calculate_shipping',
                order_id: wc_deposits_rbs_shipping.order_id,
                nonce: wc_deposits_rbs_shipping.nonce,
                shipping_country: $('#wc-deposits-rbs-shipping_country').val(),
                shipping_state: $('#wc-deposits-rbs-shipping_state').val(),
                shipping_postcode: $('#wc-deposits-rbs-shipping_postcode').val(),
                shipping_city: $('#wc-deposits-rbs-shipping_city').val(),
                shipping_address_1: $('#wc-deposits-rbs-shipping_address_1').val(),
                shipping_address_2: $('#wc-deposits-rbs-shipping_address_2').val()
            };

            $.ajax({
                url: wc_deposits_rbs_shipping.ajax_url,
                type: 'POST',
                data: shippingData,
                success: function(response) {
                    if (response.success) {
                        WC_Deposits_RBS_Shipping.updateShippingRates(response.data.rates);
                        WC_Deposits_RBS_Shipping.updateOrderTotal(response.data.rates);
                    } else {
                        alert(wc_deposits_rbs_shipping.strings.error);
                    }
                },
                error: function() {
                    alert(wc_deposits_rbs_shipping.strings.error);
                },
                complete: function() {
                    $button.prop('disabled', false).text(wc_deposits_rbs_shipping.strings.calculate_shipping);
                }
            });
        },

        updateShippingRates: function(rates) {
            var $ratesContainer = $('.wc-deposits-rbs-shipping-rates');
            var $methodsList = $('.wc-deposits-rbs-shipping-methods');
            
            if (rates.length === 0) {
                $ratesContainer.html('<p class="wc-deposits-rbs-no-shipping">' + wc_deposits_rbs_shipping.strings.no_shipping + '</p>');
                return;
            }

            var html = '<ul class="wc-deposits-rbs-shipping-methods">';
            rates.forEach(function(rate) {
                html += '<li class="wc-deposits-rbs-shipping-method">';
                html += '<label>';
                html += '<input type="radio" name="wc_deposits_rbs_shipping_method_radio" value="' + rate.id + '" data-cost="' + rate.cost + '" class="wc-deposits-rbs-shipping-method-radio" />';
                html += '<span class="wc-deposits-rbs-shipping-method-label">' + rate.label + '</span>';
                html += '<span class="wc-deposits-rbs-shipping-method-cost">' + rate.formatted_cost + '</span>';
                html += '</label>';
                html += '</li>';
            });
            html += '</ul>';
            
            $ratesContainer.html(html);
        },

        updateShippingTotal: function() {
            var $selectedMethod = $('.wc-deposits-rbs-shipping-method-radio:checked');
            var cost = $selectedMethod.length ? parseFloat($selectedMethod.data('cost')) : 0;
            var methodId = $selectedMethod.val() || '';
            
            var $methodField = $('#wc-deposits_rbs_shipping_method');
            var $costField = $('#wc-deposits_rbs_shipping_cost');
            
            if ($methodField.length === 0) {
                console.error('Shipping method hidden field not found');
                return;
            }
            
            if ($costField.length === 0) {
                console.error('Shipping cost hidden field not found');
                return;
            }
            
            $methodField.val(methodId);
            $costField.val(cost);
            
            console.log('Shipping updated - Method:', methodId, 'Cost:', cost);
            
            // Update order total via AJAX with a small delay to ensure fields are set
            setTimeout(function() {
                WC_Deposits_RBS_Shipping.updateOrderTotalViaAjax(cost);
            }, 100);
        },

        updateOrderTotalViaAjax: function(shippingCost) {
            var methodField = $('#wc-deposits_rbs_shipping_method');
            var methodValue = methodField.val();
            
            var data = {
                action: 'wc_deposits_rbs_update_order_total',
                order_id: wc_deposits_rbs_shipping.order_id,
                shipping_cost: shippingCost,
                shipping_method: methodValue,
                nonce: wc_deposits_rbs_shipping.nonce
            };

            console.log('Sending AJAX data:', data);

            // Show loading overlay
            this.showLoadingOverlay();

            $.ajax({
                url: wc_deposits_rbs_shipping.ajax_url,
                type: 'POST',
                data: data,
                success: function(response) {
                    console.log('AJAX response:', response);
                    if (response.success) {
                        // Hide loading overlay and allow form submission
                        WC_Deposits_RBS_Shipping.hideLoadingOverlay();
                        console.log('Shipping updated successfully');
                    } else {
                        console.error('Failed to update order total:', response.data);
                        alert('Failed to update order. Please try again.');
                        WC_Deposits_RBS_Shipping.hideLoadingOverlay();
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error updating order total:', status, error);
                    console.error('Response:', xhr.responseText);
                    alert('Error updating order. Please try again.');
                    WC_Deposits_RBS_Shipping.hideLoadingOverlay();
                }
            });
        },

        showLoadingOverlay: function() {
            // Create overlay if it doesn't exist
            if ($('#wc-deposits-rbs-loading-overlay').length === 0) {
                $('body').append('<div id="wc-deposits-rbs-loading-overlay" class="wc-deposits-rbs-loading-overlay"><div class="wc-deposits-rbs-spinner"></div><div class="wc-deposits-rbs-loading-text">Updating order...</div></div>');
            }
            $('#wc-deposits-rbs-loading-overlay').show();
        },

        hideLoadingOverlay: function() {
            $('#wc-deposits-rbs-loading-overlay').hide();
        },

        formatPrice: function(price) {
            // This is a basic implementation - you might want to use WooCommerce's price formatting
            return '$' + price.toFixed(2);
        }
    };

    $(document).ready(function() {
        WC_Deposits_RBS_Shipping.init();
    });

})(jQuery);
