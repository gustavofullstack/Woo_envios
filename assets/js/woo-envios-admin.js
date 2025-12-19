(function () {
	'use strict';

	const data = window.WooEnviosAdminData || {};
	console.log('Woo Envios Admin Loaded', data);
	let mapInstance;
	let storeMarker;
	let latInput;
	let lngInput;
	let tierCircles = [];
	const tierColors = ['#d62828', '#f77f00', '#fcbf49', '#2a9d8f', '#457b9d', '#8e44ad', '#e056fd', '#00a8e8'];

	document.addEventListener('DOMContentLoaded', function () {
		latInput = document.getElementById('woo_envios_store_lat');
		lngInput = document.getElementById('woo_envios_store_lng');

		initMap();
		initTierControls();
		initSearch();
		initDebugTools();
	});

	function initMap() {
		const mapEl = document.getElementById('woo-envios-admin-map');

		if (!mapEl || !latInput || !lngInput || typeof L === 'undefined') {
			return;
		}

		const initialLat = parseFloat(mapEl.dataset.lat || data.storeLat || -18.911);
		const initialLng = parseFloat(mapEl.dataset.lng || data.storeLng || -48.262);

		mapInstance = L.map(mapEl).setView([initialLat, initialLng], data.zoom || 12);
		L.tileLayer(data.mapTileUrl, {
			attribution: data.mapAttr,
			maxZoom: 18,
		}).addTo(mapInstance);

		storeMarker = L.marker([initialLat, initialLng], { draggable: true }).addTo(mapInstance);
		if (data.strings && data.strings.setMarker) {
			storeMarker.bindTooltip(data.strings.setMarker);
		}

		renderTierCircles(storeMarker.getLatLng());

		storeMarker.on('dragend', () => {
			const latLng = storeMarker.getLatLng();
			updateCoords(latLng);
			updateTierCircleCenters(latLng);
		});

		mapInstance.on('click', (event) => {
			storeMarker.setLatLng(event.latlng);
			updateCoords(event.latlng);
			updateTierCircleCenters(event.latlng);
		});
	}

	function updateCoords(latlng) {
		if (!latInput || !lngInput) {
			return;
		}
		latInput.value = latlng.lat.toFixed(6);
		lngInput.value = latlng.lng.toFixed(6);
	}

	function focusOn(lat, lng) {
		if (!mapInstance || !storeMarker) {
			return;
		}
		const latLng = L.latLng(lat, lng);
		storeMarker.setLatLng(latLng);
		updateCoords(latLng);
		mapInstance.setView(latLng, 15);
		updateTierCircleCenters(latLng);
	}

	function initTierControls() {
		const rowsContainer = document.getElementById('woo-envios-tier-rows');
		const addButton = document.getElementById('woo-envios-add-tier');
		const template = document.getElementById('woo-envios-tier-template');

		if (!rowsContainer || !addButton || !template) {
			return;
		}

		let nextIndex = rowsContainer.querySelectorAll('tr').length;

		addButton.addEventListener('click', () => {
			addRow();
		});

		rowsContainer.addEventListener('click', (event) => {
			if (event.target.classList.contains('woo-envios-remove-tier')) {
				event.preventDefault();
				const row = event.target.closest('tr');
				if (row) {
					row.remove();
				}
			}
		});

		function addRow() {
			const fragment = template.content.cloneNode(true);
			const inputs = fragment.querySelectorAll('input');
			if (inputs.length < 3) {
				return;
			}

			inputs[0].name = `woo_envios_tiers[${nextIndex}][label]`;
			inputs[0].placeholder = data.strings ? data.strings.newTierLabel || 'Raio extra' : 'Raio extra';

			inputs[1].name = `woo_envios_tiers[${nextIndex}][distance]`;
			inputs[1].setAttribute('step', '0.1');

			inputs[2].name = `woo_envios_tiers[${nextIndex}][price]`;
			inputs[2].setAttribute('step', '0.01');

			rowsContainer.appendChild(fragment);
			nextIndex++;
		}
	}

	function renderTierCircles(center) {
		clearTierCircles();

		if (!data.tiers || !Array.isArray(data.tiers)) {
			return;
		}

		data.tiers.forEach((tier, index) => {
			const distance = parseFloat(tier.distance);
			if (!distance) {
				return;
			}

			const color = tierColors[index % tierColors.length];
			const circle = L.circle(center, {
				radius: distance * 1000,
				color,
				weight: 1.5,
				fillColor: color,
				fillOpacity: 0.08,
			}).addTo(mapInstance);

			circle.bindTooltip(`${tier.label} (${distance} km)`);
			tierCircles.push(circle);
		});
	}

	function clearTierCircles() {
		tierCircles.forEach((circle) => circle.remove());
		tierCircles = [];
	}

	function updateTierCircleCenters(center) {
		if (!tierCircles.length) {
			renderTierCircles(center);
			return;
		}

		tierCircles.forEach((circle) => circle.setLatLng(center));
	}

	function initSearch() {
		const searchInput = document.getElementById('woo-envios-search-input');
		const searchBtn = document.getElementById('woo-envios-search-btn');
		const resultsList = document.getElementById('woo-envios-search-results');

		if (!searchInput || !searchBtn || !resultsList) {
			return;
		}

		if (data.strings && data.strings.searchPlaceholder) {
			searchInput.placeholder = data.strings.searchPlaceholder;
		}

		const renderMessage = (text) => {
			resultsList.innerHTML = '';
			const li = document.createElement('li');
			li.textContent = text;
			li.classList.add('woo-envios-result-message');
			resultsList.appendChild(li);
		};

		const performSearch = () => {
			const query = searchInput.value.trim();
			if (!query) {
				return;
			}

			renderMessage(data.strings ? data.strings.searching : 'Buscando…');

			const url = `https://nominatim.openstreetmap.org/search?format=json&limit=5&addressdetails=1&countrycodes=br&q=${encodeURIComponent(
				query
			)}`;

			fetch(url, {
				headers: {
					'Accept-Language': 'pt-BR',
					'User-Agent': 'WooEnvios/2.0 (WordPress plugin)',
				},
			})
				.then((response) => response.json())
				.then((payload) => {
					resultsList.innerHTML = '';
					if (!payload.length) {
						renderMessage(data.strings ? data.strings.noResults : 'Sem resultados.');
						return;
					}

					payload.forEach((item) => {
						const li = document.createElement('li');
						li.textContent = item.display_name;
						li.dataset.lat = item.lat;
						li.dataset.lng = item.lon;
						resultsList.appendChild(li);
					});
				})
				.catch(() => {
					renderMessage(data.strings ? data.strings.noResults : 'Erro ao buscar endereço.');
				});
		};

		searchBtn.addEventListener('click', (event) => {
			event.preventDefault();
			performSearch();
		});

		searchInput.addEventListener('keydown', (event) => {
			if (event.key === 'Enter') {
				event.preventDefault();
				performSearch();
			}
		});

		resultsList.addEventListener('click', (event) => {
			const target = event.target;
			if (target && target.dataset.lat) {
				const lat = parseFloat(target.dataset.lat);
				const lng = parseFloat(target.dataset.lng);
				focusOn(lat, lng);
			}
		});
	}

	function initDebugTools() {
		const debugBtn = document.getElementById('woo-envios-debug-btn');
		const debugAddress = document.getElementById('woo-envios-debug-address');
		const debugResults = document.getElementById('woo-envios-debug-results');
		const clearCacheBtn = document.getElementById('woo-envios-clear-cache-btn');
		const cacheMsg = document.getElementById('woo-envios-cache-msg');

		if (debugBtn && debugAddress && debugResults) {
			debugBtn.addEventListener('click', () => {
				if (!data.ajaxUrl) {
					alert('Erro: URL do AJAX não encontrada. Recarregue a página.');
					console.error('WooEnviosAdminData:', data);
					return;
				}

				const address = debugAddress.value.trim();
				if (!address) {
					alert('Digite um endereço.');
					return;
				}

				debugBtn.disabled = true;
				debugBtn.textContent = 'Calculando...';
				debugResults.style.display = 'none';

				const formData = new FormData();
				formData.append('action', 'woo_envios_debug_geocode');
				formData.append('nonce', data.debugNonce);
				formData.append('address', address);

				fetch(data.ajaxUrl, {
					method: 'POST',
					body: formData,
				})
					.then((response) => response.json())
					.then((response) => {
						debugBtn.disabled = false;
						debugBtn.textContent = 'Testar Cálculo';
						debugResults.style.display = 'block';

						if (response.success) {
							const r = response.data;
							debugResults.innerHTML = `
								<p><strong>📍 Coordenadas Encontradas:</strong> ${r.coords.lat}, ${r.coords.lng}</p>
								<p><strong>🏬 Loja Configurada:</strong> ${r.store.lat}, ${r.store.lng}</p>
								<p><strong>📏 Distância Calculada:</strong> ${r.distance} km <em>(${r.distance_method})</em></p>
								<p><strong>💰 Faixa Detectada:</strong> ${r.tier ? r.tier.label + ' (R$ ' + r.tier.price + ')' : 'Nenhuma (Fora da área)'}</p>
								<p><a href="https://www.google.com/maps/dir/?api=1&origin=${r.store.lat},${r.store.lng}&destination=${r.coords.lat},${r.coords.lng}" target="_blank" class="button">Ver Rota no Maps</a></p>
							`;
						} else {
							let errorMsg = response.data;
							if (typeof errorMsg === 'object') {
								errorMsg = JSON.stringify(errorMsg);
							}
							debugResults.innerHTML = `<p style="color:red;">❌ Erro: ${errorMsg}</p>`;
						}
					})
					.catch((err) => {
						console.error(err);
						debugBtn.disabled = false;
						debugBtn.textContent = 'Testar Cálculo';
						alert('Erro na requisição: ' + err.message);
					});
			});
		}

		if (clearCacheBtn) {
			clearCacheBtn.addEventListener('click', () => {
				if (!confirm('Tem certeza? Isso apagará todos os endereços salvos no cache.')) {
					return;
				}

				clearCacheBtn.disabled = true;
				cacheMsg.textContent = 'Limpando...';

				const formData = new FormData();
				formData.append('action', 'woo_envios_clear_cache');
				formData.append('nonce', data.debugNonce);

				fetch(data.ajaxUrl, {
					method: 'POST',
					body: formData,
				})
					.then((response) => response.json())
					.then((response) => {
						clearCacheBtn.disabled = false;
						if (response.success) {
							cacheMsg.textContent = '✅ Cache limpo!';
							cacheMsg.style.color = 'green';
						} else {
							cacheMsg.textContent = '❌ Erro: ' + response.data;
							cacheMsg.style.color = 'red';
						}
					})
					.catch(() => {
						clearCacheBtn.disabled = false;
						cacheMsg.textContent = '❌ Erro na requisição.';
					});
			});
		}
	}
})();
