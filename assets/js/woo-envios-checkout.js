/* global jQuery, WooEnviosCheckout */
(function ($) {
    'use strict';

    const WooEnvios = {
        init: function () {
            // $(document.body).on('updated_checkout', this.triggerGeocode); // Removed to prevent loop
            $('form.checkout').on('change', '#billing_address_1, #billing_number, #billing_neighborhood, #billing_city, #billing_state, #billing_postcode', this.debounce(this.triggerGeocode, 800));
        },

        triggerGeocode: function () {
            const address1 = $('#billing_address_1').val();
            const number = $('#billing_number').val();
            const neighborhood = $('#billing_neighborhood').val();
            const city = $('#billing_city').val();
            const state = $('#billing_state').val();
            const postcode = $('#billing_postcode').val();
            const country = $('#billing_country').val() || 'BR';

            if (!address1 || !city || !state || !postcode) {
                return;
            }

            $.ajax({
                type: 'POST',
                url: WooEnviosCheckout.ajaxUrl,
                data: {
                    action: 'woo_envios_geocode_address',
                    nonce: WooEnviosCheckout.nonce,
                    address_1: address1,
                    number: number,
                    neighborhood: neighborhood,
                    city: city,
                    state: state,
                    postcode: postcode,
                    country: country
                },
                success: function (response) {
                    if (response.success) {
                        // Trigger update_checkout to refresh shipping rates with new coordinates
                        // Added delay to ensure session is saved on server before recalculation
                        setTimeout(function () {
                            $(document.body).trigger('update_checkout');
                        }, 500); // Increased delay to 500ms to be safe
                    }
                }
            });
        },

        debounce: function (func, wait) {
            let timeout;
            return function () {
                const context = this, args = arguments;
                clearTimeout(timeout);
                timeout = setTimeout(function () {
                    func.apply(context, args);
                }, wait);
            };
        }
    };

    $(document).ready(function () {
        WooEnvios.init();
    });

})(jQuery);
