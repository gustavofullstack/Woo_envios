/* global jQuery, WooEnviosCheckout */
(function ($) {
    'use strict';

    const WooEnvios = {
        init: function () {
            // Listen to changes on BOTH billing and shipping address fields
            const addressFields = '#billing_address_1, #billing_number, #billing_neighborhood, #billing_city, #billing_state, #billing_postcode, ' +
                '#shipping_address_1, #shipping_number, #shipping_neighborhood, #shipping_city, #shipping_state, #shipping_postcode';

            $('form.checkout').on('change', addressFields, this.debounce(this.triggerGeocode, 800));
        },

        triggerGeocode: function () {
            // Prioritize shipping address if different shipping address checkbox is checked
            const useShipping = $('#ship-to-different-address-checkbox').is(':checked');
            const prefix = useShipping ? 'shipping' : 'billing';

            const address1 = $(`#${prefix}_address_1`).val();
            const number = $(`#${prefix}_number`).val();
            const neighborhood = $(`#${prefix}_neighborhood`).val();
            const city = $(`#${prefix}_city`).val();
            const state = $(`#${prefix}_state`).val();
            const postcode = $(`#${prefix}_postcode`).val();
            const country = $(`#${prefix}_country`).val() || 'BR';

            console.log('[Woo Envios] Geocoding triggered', {
                prefix: prefix,
                useShipping: useShipping,
                address1: address1,
                city: city,
                state: state,
                postcode: postcode
            });

            if (!address1 || !city || !state || !postcode) {
                console.log('[Woo Envios] Missing required fields, skipping geocode');
                return;
            }

            console.log('[Woo Envios] Sending AJAX request to geocode...');

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
                    console.log('[Woo Envios] AJAX Response:', response);
                    if (response.success) {
                        console.log('[Woo Envios] Coordinates saved! Triggering checkout update...');
                        // Trigger update_checkout to refresh shipping rates with new coordinates
                        // Added delay to ensure session is saved on server before recalculation
                        setTimeout(function () {
                            $(document.body).trigger('update_checkout');
                        }, 500); // Increased delay to 500ms to be safe
                    } else {
                        console.error('[Woo Envios] Geocode failed:', response.data);
                    }
                },
                error: function (xhr, status, error) {
                    console.error('[Woo Envios] AJAX Error:', error, xhr.responseText);
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
