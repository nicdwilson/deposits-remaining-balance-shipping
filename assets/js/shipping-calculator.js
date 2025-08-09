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
        },

        bindEvents: function() {
            // Shipping method selection - update form data
            $(document).on('change', '.wc-deposits-rbs-shipping-method-radio', this.updateFormData);
        },

        updateFormData: function() {
            var $selectedMethod = $('.wc-deposits-rbs-shipping-method-radio:checked');
            var cost = $selectedMethod.length ? parseFloat($selectedMethod.data('cost')) : 0;
            var methodId = $selectedMethod.val() || '';
            
            console.log('Shipping method selected - Method:', methodId, 'Cost:', cost);
        }
    };

    $(document).ready(function() {
        WC_Deposits_RBS_Shipping.init();
    });

})(jQuery);
