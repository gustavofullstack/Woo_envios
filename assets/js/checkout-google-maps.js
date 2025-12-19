/**
 * Checkout Google Maps Integration JavaScript
 *
 * @package UDI_Custom_Login
 */

(function ($) {
    'use strict';

    const UDI_Checkout_Maps = {
        autocompleteInstances: {},
        selectedPlaceIds: {},

        /**
         * Initialize
         */
        init() {
            // Wait for Google Maps API to load
            if (typeof google === 'undefined' || !google.maps) {
                console.warn('Google Maps API not loaded');
                return;
            }

            this.initAutocomplete();
            this.bindEvents();
        },

        /**
         * Initialize autocomplete on address fields
         */
        initAutocomplete() {
            const fieldPrefixes = ['billing', 'shipping'];

            fieldPrefixes.forEach((prefix) => {
                const addressField = document.getElementById(
                    `${prefix}_address_1`
                );

                if (!addressField) {
                    return;
                }

                // Initialize Places Autocomplete
                const autocomplete = new google.maps.places.Autocomplete(
                    addressField,
                    {
                        componentRestrictions: { country: 'br' },
                        fields: ['place_id', 'address_components', 'geometry'],
                        types: ['address'],
                    }
                );

                // Store instance
                this.autocompleteInstances[prefix] = autocomplete;

                // Listen for place selection
                autocomplete.addListener('place_changed', () => {
                    this.handlePlaceSelected(prefix, autocomplete);
                });

                // Add visual indicator
                this.addAutocompleteIndicator(addressField);
            });
        },

        /**
         * Handle place selection from autocomplete
         */
        handlePlaceSelected(prefix, autocomplete) {
            const place = autocomplete.getPlace();

            if (!place.place_id) {
                console.warn('No place_id found');
                return;
            }

            // Store place_id
            this.selectedPlaceIds[prefix] = place.id;

            // Show loading state
            this.setFieldState(`${prefix}_address_1`, 'loading');

            // Get detailed place info via AJAX
            $.ajax({
                url: udiCheckoutMaps.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'udi_get_place_details',
                    nonce: udiCheckoutMaps.nonce,
                    place_id: place.place_id,
                },
                success: (response) => {
                    if (response.success) {
                        this.fillAddressFields(prefix, response.data);
                        this.setFieldState(`${prefix}_address_1`, 'valid');

                        // Trigger WooCommerce update
                        $('body').trigger('update_checkout');
                    } else {
                        this.setFieldState(`${prefix}_address_1`, 'error');
                    }
                },
                error: () => {
                    this.setFieldState(`${prefix}_address_1`, 'error');
                },
            });
        },

        /**
         * Fill address fields with Google Maps data
         */
        fillAddressFields(prefix, data) {
            const components = data.address_components;

            // Fill fields
            const fieldMap = {
                route: `${prefix}_address_1`,
                street_number: `${prefix}_number`,
                neighborhood: `${prefix}_neighborhood`,
                city: `${prefix}_city`,
                state_code: `${prefix}_state`,
                postal_code: `${prefix}_postcode`,
            };

            Object.keys(fieldMap).forEach((component) => {
                const fieldId = fieldMap[component];
                const value = components[component] || '';

                if (value) {
                    const $field = $(`#${fieldId}`);

                    if ($field.length) {
                        // Different handling for select vs input
                        if ($field.is('select')) {
                            // For state dropdown
                            if (component === 'state_code') {
                                $field.val(value).trigger('change');
                            }
                        } else {
                            $field.val(value).trigger('change');
                        }

                        // Visual feedback
                        this.setFieldState(fieldId, 'valid');
                    }
                }
            });

            // For street with number combined in address_1
            if (components.route && components.street_number) {
                $(`#${prefix}_address_1`).val(
                    `${components.route}, ${components.street_number}`
                );
            }
        },

        /**
         * Bind event listeners
         */
        bindEvents() {
            // Validate on blur if addresses validation is enabled
            if (udiCheckoutMaps.validateAddresses) {
                $('#billing_postcode, #shipping_postcode').on(
                    'blur',
                    (e) => {
                        const $field = $(e.target);
                        const prefix = $field.attr('id').startsWith('billing')
                            ? 'billing'
                            : 'shipping';

                        this.validateCEP(prefix);
                    }
                );
            }

            // Listen for WooCommerce checkout updates
            $(document.body).on('updated_checkout', () => {
                // Re-initialize if needed
                if (
                    Object.keys(this.autocompleteInstances).length === 0
                ) {
                    this.initAutocomplete();
                }
            });
        },

        /**
         * Validate CEP (Brazilian postal code)
         */
        validateCEP(prefix) {
            const $postcodeField = $(`#${prefix}_postcode`);
            const postcode = $postcodeField.val();

            if (!postcode || postcode.length < 8) {
                return;
            }

            this.setFieldState(`${prefix}_postcode`, 'loading');

            $.ajax({
                url: udiCheckoutMaps.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'udi_validate_address',
                    nonce: udiCheckoutMaps.nonce,
                    postcode: postcode,
                    city: $(`#${prefix}_city`).val(),
                    state: $(`#${prefix}_state`).val(),
                },
                success: (response) => {
                    if (response.success && response.data.valid) {
                        this.setFieldState(`${prefix}_postcode`, 'valid');

                        // Optionally auto-fill city and state
                        const components = response.data.address_components;
                        if (components.city && !$(`#${prefix}_city`).val()) {
                            $(`#${prefix}_city`).val(components.city);
                        }
                        if (
                            components.state_code &&
                            !$(`#${prefix}_state`).val()
                        ) {
                            $(`#${prefix}_state`)
                                .val(components.state_code)
                                .trigger('change');
                        }
                    } else {
                        this.setFieldState(`${prefix}_postcode`, 'error');
                    }
                },
                error: () => {
                    this.setFieldState(`${prefix}_postcode`, 'error');
                },
            });
        },

        /**
         * Add autocomplete indicator to field
         */
        addAutocompleteIndicator(field) {
            const $field = $(field);
            const $wrapper = $field.parent();

            if (!$wrapper.find('.udi-gm-indicator').length) {
                $wrapper.css('position', 'relative');
                $field.after(
                    '<span class="udi-gm-indicator"><span class="dashicons"></span></span>'
                );
            }
        },

        /**
         * Set field validation state
         */
        setFieldState(fieldId, state) {
            const $field = $(`#${fieldId}`);
            const $indicator = $field.siblings('.udi-gm-indicator');

            if (!$indicator.length) {
                return;
            }

            // Remove all states
            $indicator
                .removeClass('loading valid error')
                .find('.dashicons')
                .removeClass(
                    'dashicons-update dashicons-yes-alt dashicons-warning'
                );

            // Add new state
            switch (state) {
                case 'loading':
                    $indicator
                        .addClass('loading')
                        .find('.dashicons')
                        .addClass('dashicons-update');
                    break;
                case 'valid':
                    $indicator
                        .addClass('valid')
                        .find('.dashicons')
                        .addClass('dashicons-yes-alt');
                    break;
                case 'error':
                    $indicator
                        .addClass('error')
                        .find('.dashicons')
                        .addClass('dashicons-warning');
                    break;
            }
        },
    };

    // Initialize when page is ready and after a short delay for WooCommerce
    $(document).ready(() => {
        setTimeout(() => {
            UDI_Checkout_Maps.init();
        }, 500);
    });

    // Re-initialize after AJAX checkout updates
    $(document.body).on('updated_checkout', () => {
        setTimeout(() => {
            UDI_Checkout_Maps.init();
        }, 300);
    });
})(jQuery);
