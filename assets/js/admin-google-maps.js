/**
 * Google Maps Admin JavaScript
 *
 * @package UDI_Custom_Login
 */

(function ($) {
	'use strict';

	const UDI_GoogleMaps_Admin = {
		currentStep: 1,
		totalSteps: 5,

		/**
		 * Initialize
		 */
		init() {
			this.bindEvents();
		},

		/**
		 * Bind event listeners
		 */
		bindEvents() {
			// Wizard navigation
			$('.udi-gm-next').on('click', () => this.nextStep());
			$('.udi-gm-prev').on('click', () => this.prevStep());

			// Test connection
			$('#udi-gm-test-btn, #udi-gm-test-btn-main').on('click', (e) => {
				e.preventDefault();
				this.testConnection();
			});

			// Save settings (wizard)
			$('#udi-gm-wizard-form').on('submit', (e) => {
				e.preventDefault();
				this.saveSettings();
			});

			// Save settings (main form)
			$('#udi-gm-settings-form').on('submit', (e) => {
				e.preventDefault();
				this.saveSettings();
			});

			// Clear cache
			$('#udi-gm-clear-expired').on('click', () => this.clearCache('expired'));
			$('#udi-gm-clear-all').on('click', () => this.clearCache('all'));
		},

		/**
		 * Go to next wizard step
		 */
		nextStep() {
			if (this.currentStep < this.totalSteps) {
				this.currentStep++;
				this.updateWizard();
			}
		},

		/**
		 * Go to previous wizard step
		 */
		prevStep() {
			if (this.currentStep > 1) {
				this.currentStep--;
				this.updateWizard();
			}
		},

		/**
		 * Update wizard UI
		 */
		updateWizard() {
			// Update steps
			$('.udi-gm-wizard-step').removeClass('active');
			$(`.udi-gm-wizard-step[data-step="${this.currentStep}"]`).addClass('active');

			// Update progress bar
			const progress = (this.currentStep / this.totalSteps) * 100;
			$('.udi-gm-wizard-progress-fill').css('width', `${progress}%`);

			// Update step indicators
			$('.udi-gm-wizard-steps span').removeClass('active completed');
			$('.udi-gm-wizard-steps span').each((index, el) => {
				const stepNum = parseInt($(el).data('step'));
				if (stepNum < this.currentStep) {
					$(el).addClass('completed');
				} else if (stepNum === this.currentStep) {
					$(el).addClass('active');
				}
			});

			// Scroll to top
			$('.udi-gm-wizard')[0].scrollIntoView({ behavior: 'smooth', block: 'start' });
		},

		/**
		 * Test Google Maps API connection
		 */
		testConnection() {
			const apiKey =
				$('#udi_api_key').val() || $('#udi_api_key_main').val();

			if (!apiKey || apiKey.trim() === '') {
				this.showTestResult(
					false,
					udiGoogleMapsAdmin.strings.error,
					['API Key é obrigatória.']
				);
				return;
			}

			const $btn = $('#udi-gm-test-btn, #udi-gm-test-btn-main');
			const originalText = $btn.html();

			// Show loading state
			$btn
				.html(
					'<span class="dashicons dashicons-update udi-gm-loading"></span> ' +
						udiGoogleMapsAdmin.strings.testing
				)
				.prop('disabled', true);

			// Make AJAX request
			$.ajax({
				url: udiGoogleMapsAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'udi_test_google_maps',
					nonce: udiGoogleMapsAdmin.nonce,
					api_key: apiKey,
				},
				success: (response) => {
					if (response.success && response.data.connected) {
						this.showTestResult(
							true,
							udiGoogleMapsAdmin.strings.success,
							[
								'✓ Geocoding API',
								'✓ Places API',
								'✓ Distance Matrix API',
							]
						);

						// Enable save button in wizard
						$('#udi-gm-save-btn').prop('disabled', false);
					} else {
						const errors = [];
						if (response.data && response.data.results) {
							const results = response.data.results;
							if (!results.geocoding) {
								errors.push(
									`✗ Geocoding API: ${
										results.errors.geocoding || 'Erro'
									}`
								);
							}
							if (!results.places) {
								errors.push(
									`✗ Places API: ${
										results.errors.places || 'Erro'
									}`
								);
							}
							if (!results.distance) {
								errors.push(
									`✗ Distance Matrix API: ${
										results.errors.distance || 'Erro'
									}`
								);
							}
						}

						this.showTestResult(
							false,
							udiGoogleMapsAdmin.strings.error,
							errors.length ? errors : ['Verifique sua API Key e as APIs habilitadas.']
						);
					}
				},
				error: () => {
					this.showTestResult(
						false,
						'Erro de Conexão',
						['Não foi possível conectar ao servidor.']
					);
				},
				complete: () => {
					$btn.html(originalText).prop('disabled', false);
				},
			});
		},

		/**
		 * Show test result
		 */
		showTestResult(success, title, messages) {
			const $result = $('#udi-gm-test-result, .udi-gm-test-result');

			let html = `<h4><span class="dashicons dashicons-${
				success ? 'yes-alt' : 'warning'
			}"></span> ${title}</h4>`;

			if (messages.length) {
				html += '<ul>';
				messages.forEach((msg) => {
					html += `<li>${msg}</li>`;
				});
				html += '</ul>';
			}

			$result
				.removeClass('success error')
				.addClass(success ? 'success' : 'error')
				.addClass('show')
				.html(html);
		},

		/**
		 * Save settings
		 */
		saveSettings() {
			const apiKey = $('#udi_api_key, #udi_api_key_main').val();
			const cacheTtl = $('#udi_cache_ttl, #udi_cache_ttl_main').val();
			const autocompleteEnabled = $(
				'input[name="autocomplete_enabled"]'
			).is(':checked');
			const validateAddresses = $('input[name="validate_addresses"]').is(
				':checked'
			);

			const $btn = $('#udi-gm-save-btn, #udi-gm-settings-form button[type="submit"]');
			const originalText = $btn.html();

			$btn
				.html(
					'<span class="dashicons dashicons-update udi-gm-loading"></span> ' +
						udiGoogleMapsAdmin.strings.saving
				)
				.prop('disabled', true);

			$.ajax({
				url: udiGoogleMapsAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'udi_save_google_maps_key',
					nonce: udiGoogleMapsAdmin.nonce,
					api_key: apiKey,
					cache_ttl: cacheTtl,
					autocomplete_enabled: autocompleteEnabled,
					validate_addresses: validateAddresses,
				},
				success: (response) => {
					if (response.success) {
						// Show success message
						$btn.html(
							'<span class="dashicons dashicons-yes-alt"></span> ' +
								udiGoogleMapsAdmin.strings.saved
						);

						// Redirect after wizard completion
						if ($('#udi-gm-wizard-form').length) {
							setTimeout(() => {
								window.location.href =
									'?page=udi-google-maps&skip_wizard=1';
							}, 1500);
						} else {
							// Reset button after delay
							setTimeout(() => {
								$btn.html(originalText).prop('disabled', false);
							}, 2000);
						}
					} else {
						alert(
							response.data.message ||
								'Erro ao salvar configurações.'
						);
						$btn.html(originalText).prop('disabled', false);
					}
				},
				error: () => {
					alert('Erro de conexão ao salvar.');
					$btn.html(originalText).prop('disabled', false);
				},
			});
		},

		/**
		 * Clear cache
		 */
		clearCache(type) {
			if (
				type === 'all' &&
				!confirm(
					'Tem certeza que deseja limpar TODO o cache? Isto irá aumentar temporariamente as chamadas à API.'
				)
			) {
				return;
			}

			const $btn = $(`#udi-gm-clear-${type}`);
			const originalText = $btn.text();

			$btn
				.text(udiGoogleMapsAdmin.strings.clearingCache)
				.prop('disabled', true);

			$.ajax({
				url: udiGoogleMapsAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'udi_clear_geocode_cache',
					nonce: udiGoogleMapsAdmin.nonce,
					type: type,
				},
				success: (response) => {
					if (response.success) {
						alert(response.data.message);
						location.reload();
					} else {
						alert(response.data.message || 'Erro ao limpar cache.');
					}
				},
				error: () => {
					alert('Erro de conexão.');
				},
				complete: () => {
					$btn.text(originalText).prop('disabled', false);
				},
			});
		},
	};

	// Initialize on document ready
	$(document).ready(() => {
		UDI_GoogleMaps_Admin.init();
	});
})(jQuery);
