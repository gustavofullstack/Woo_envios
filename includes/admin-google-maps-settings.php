<?php
/**
 * Google Maps Admin Settings Page.
 *
 * @package UDI_Custom_Login
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$usage_stats = $this->get_usage_stats();
?>

<div class="wrap udi-google-maps-admin">
	<h1><?php esc_html_e( 'Configuração Google Maps API', 'woo-envios' ); ?></h1>

	<?php if ( $show_wizard ) : ?>
		<!-- WIZARD DE CONFIGURAÇÃO -->
		<div class="udi-gm-wizard">
			<div class="udi-gm-wizard-header">
				<h2><?php esc_html_e( 'Bem-vindo ao Assistente de Configuração', 'woo-envios' ); ?></h2>
				<p><?php esc_html_e( 'Vamos configurar o Google Maps API em 5 passos simples!', 'woo-envios' ); ?></p>
				<div class="udi-gm-wizard-progress">
					<div class="udi-gm-wizard-progress-bar">
						<div class="udi-gm-wizard-progress-fill" style="width: 20%;"></div>
					</div>
					<div class="udi-gm-wizard-steps">
						<span class="active" data-step="1">1</span>
						<span data-step="2">2</span>
						<span data-step="3">3</span>
						<span data-step="4">4</span>
						<span data-step="5">5</span>
					</div>
				</div>
			</div>

			<!-- PASSO 1: Introdução -->
			<div class="udi-gm-wizard-step active" data-step="1">
				<div class="udi-gm-wizard-content">
					<span class="dashicons dashicons-location-alt udi-gm-wizard-icon"></span>
					<h3><?php esc_html_e( 'Por que Google Maps?', 'woo-envios' ); ?></h3>
					<p><?php esc_html_e( 'Google Maps é a API de geolocalização mais robusta e confiável do mercado, garantindo:', 'woo-envios' ); ?></p>
					<ul class="udi-gm-benefits">
						<li><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'Validação precisa de endereços brasileiros', 'woo-envios' ); ?></li>
						<li><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'Autocomplete inteligente no checkout', 'woo-envios' ); ?></li>
						<li><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'Cálculo exato de distâncias para frete', 'woo-envios' ); ?></li>
						<li><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'Eliminação de erros de "shipping not available"', 'woo-envios' ); ?></li>
						<li><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( '$200 USD/mês GRÁTIS (~40.000 requisições)', 'woo-envios' ); ?></li>
					</ul>
					<div class="udi-gm-wizard-actions">
						<button type="button" class="button button-primary button-large udi-gm-next"><?php esc_html_e( 'Começar →', 'woo-envios' ); ?></button>
						<a href="?page=udi-google-maps&skip_wizard=1" class="button button-link"><?php esc_html_e( 'Pular Assistente', 'woo-envios' ); ?></a>
					</div>
				</div>
			</div>

			<!-- PASSO 2: Criar Conta Google Cloud -->
			<div class="udi-gm-wizard-step" data-step="2">
				<div class="udi-gm-wizard-content">
					<span class="dashicons dashicons-cloud udi-gm-wizard-icon"></span>
					<h3><?php esc_html_e( 'Criar Conta Google Cloud Platform', 'woo-envios' ); ?></h3>
					<p><?php esc_html_e( 'Se você ainda não tem uma conta, é rápido e gratuito:', 'woo-envios' ); ?></p>
					<ol class="udi-gm-instructions">
						<li>
							<?php esc_html_e( 'Acesse:', 'woo-envios' ); ?>
							<a href="https://console.cloud.google.com/" target="_blank" class="button button-secondary">
								Google Cloud Console <span class="dashicons dashicons-external"></span>
							</a>
						</li>
						<li><?php esc_html_e( 'Faça login com sua conta Google (ou crie uma)', 'woo-envios' ); ?></li>
						<li><?php esc_html_e( 'Aceite os termos de serviço', 'woo-envios' ); ?></li>
						<li><?php esc_html_e( 'Clique em "Criar Projeto" e dê um nome (ex: "Minha Loja")', 'woo-envios' ); ?></li>
					</ol>
					<div class="udi-gm-note">
						<span class="dashicons dashicons-info"></span>
						<p><strong><?php esc_html_e( 'Nota:', 'woo-envios' ); ?></strong> <?php esc_html_e( 'Você pode precisar adicionar um cartão de crédito para validação, mas NÃO será cobrado sem sua autorização. Os $200/mês de créditos gratuitos são suficientes para a maioria das lojas.', 'woo-envios' ); ?></p>
					</div>
					<div class="udi-gm-wizard-actions">
						<button type="button" class="button udi-gm-prev">← <?php esc_html_e( 'Voltar', 'woo-envios' ); ?></button>
						<button type="button" class="button button-primary button-large udi-gm-next"><?php esc_html_e( 'Próximo →', 'woo-envios' ); ?></button>
					</div>
				</div>
			</div>

			<!-- PASSO 3: Habilitar APIs -->
			<div class="udi-gm-wizard-step" data-step="3">
				<div class="udi-gm-wizard-content">
					<span class="dashicons dashicons-admin-plugins udi-gm-wizard-icon"></span>
					<h3><?php esc_html_e( 'Habilitar as APIs Necessárias', 'woo-envios' ); ?></h3>
					<p><?php esc_html_e( 'Você precisa habilitar estas 3 APIs (links diretos abaixo):', 'woo-envios' ); ?></p>
					<div class="udi-gm-api-checklist">
						<div class="udi-gm-api-item">
							<input type="checkbox" id="api-geocoding">
							<label for="api-geocoding">
								<strong>Geocoding API</strong> - <?php esc_html_e( 'Validação de endereços', 'woo-envios' ); ?>
							</label>
							<a href="https://console.cloud.google.com/apis/library/geocoding-backend.googleapis.com" target="_blank" class="button button-small">
								<?php esc_html_e( 'Habilitar', 'woo-envios' ); ?> <span class="dashicons dashicons-external"></span>
							</a>
						</div>
						<div class="udi-gm-api-item">
							<input type="checkbox" id="api-places">
							<label for="api-places">
								<strong>Places API</strong> - <?php esc_html_e( 'Autocomplete de endereços', 'woo-envios' ); ?>
							</label>
							<a href="https://console.cloud.google.com/apis/library/places-backend.googleapis.com" target="_blank" class="button button-small">
								<?php esc_html_e( 'Habilitar', 'woo-envios' ); ?> <span class="dashicons dashicons-external"></span>
							</a>
						</div>
						<div class="udi-gm-api-item">
							<input type="checkbox" id="api-distance">
							<label for="api-distance">
								<strong>Distance Matrix API</strong> - <?php esc_html_e( 'Cálculo de distâncias', 'woo-envios' ); ?>
							</label>
							<a href="https://console.cloud.google.com/apis/library/distance-matrix-backend.googleapis.com" target="_blank" class="button button-small">
								<?php esc_html_e( 'Habilitar', 'woo-envios' ); ?> <span class="dashicons dashicons-external"></span>
							</a>
						</div>
					</div>
					<div class="udi-gm-note">
						<span class="dashicons dashicons-info"></span>
						<p><?php esc_html_e( 'Para cada API, clique em "Habilitar". Aguarde alguns segundos até a API ser ativada.', 'woo-envios' ); ?></p>
					</div>
					<div class="udi-gm-wizard-actions">
						<button type="button" class="button udi-gm-prev">← <?php esc_html_e( 'Voltar', 'woo-envios' ); ?></button>
						<button type="button" class="button button-primary button-large udi-gm-next"><?php esc_html_e( 'Próximo →', 'woo-envios' ); ?></button>
					</div>
				</div>
			</div>

			<!-- PASSO 4: Gerar API Key -->
			<div class="udi-gm-wizard-step" data-step="4">
				<div class="udi-gm-wizard-content">
					<span class="dashicons dashicons-admin-network udi-gm-wizard-icon"></span>
					<h3><?php esc_html_e( 'Gerar API Key', 'woo-envios' ); ?></h3>
					<p><?php esc_html_e( 'Agora vamos criar sua chave de API:', 'woo-envios' ); ?></p>
					<ol class="udi-gm-instructions">
						<li>
							<?php esc_html_e( 'Acesse:', 'woo-envios' ); ?>
							<a href="https://console.cloud.google.com/apis/credentials" target="_blank" class="button button-secondary">
								API Credentials <span class="dashicons dashicons-external"></span>
							</a>
						</li>
						<li><?php esc_html_e( 'Clique em "Criar Credenciais" → "Chave de API"', 'woo-envios' ); ?></li>
						<li><?php esc_html_e( 'Sua API Key será gerada. Copie-a!', 'woo-envios' ); ?></li>
						<li><strong><?php esc_html_e( 'Recomendado:', 'woo-envios' ); ?></strong> <?php esc_html_e( 'Clique em "Restringir chave" e limite às 3 APIs que habilitamos', 'woo-envios' ); ?></li>
					</ol>
					<div class="udi-gm-note udi-gm-warning">
						<span class="dashicons dashicons-warning"></span>
						<p><strong><?php esc_html_e( 'Segurança:', 'woo-envios' ); ?></strong> <?php esc_html_e( 'Sempre restrinja sua API Key para usar apenas as 3 APIs necessárias. Isso evita uso indevido e cobranças inesperadas.', 'woo-envios' ); ?></p>
					</div>
					<div class="udi-gm-wizard-actions">
						<button type="button" class="button udi-gm-prev">← <?php esc_html_e( 'Voltar', 'woo-envios' ); ?></button>
						<button type="button" class="button button-primary button-large udi-gm-next"><?php esc_html_e( 'Próximo →', 'woo-envios' ); ?></button>
					</div>
				</div>
			</div>

			<!-- PASSO 5: Configurar e Testar -->
			<div class="udi-gm-wizard-step" data-step="5">
				<div class="udi-gm-wizard-content">
					<span class="dashicons dashicons-yes-alt udi-gm-wizard-icon"></span>
					<h3><?php esc_html_e( 'Configurar e Testar', 'woo-envios' ); ?></h3>
					<p><?php esc_html_e( 'Cole sua API Key abaixo e vamos testar a conexão:', 'woo-envios' ); ?></p>
					
					<form id="udi-gm-wizard-form" class="udi-gm-config-form">
						<table class="form-table">
							<tr>
								<th scope="row">
									<label for="udi_api_key">
										<?php esc_html_e( 'Google Maps API Key', 'woo-envios' ); ?>
										<span class="required">*</span>
									</label>
								</th>
								<td>
									<input type="text" id="udi_api_key" name="api_key" class="regular-text" placeholder="AIza..." required>
									<p class="description"><?php esc_html_e( 'Cole aqui a API Key que você copiou do Google Cloud Console.', 'woo-envios' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="udi_cache_ttl"><?php esc_html_e( 'Cache de Endereços (dias)', 'woo-envios' ); ?></label>
								</th>
								<td>
									<input type="number" id="udi_cache_ttl" name="cache_ttl" value="30" min="1" max="365" class="small-text">
									<p class="description"><?php esc_html_e( 'Quanto tempo manter endereços em cache (reduz chamadas à API).', 'woo-envios' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Recursos', 'woo-envios' ); ?></th>
								<td>
									<label>
										<input type="checkbox" name="autocomplete_enabled" value="1" checked>
										<?php esc_html_e( 'Habilitar autocomplete de endereços no checkout', 'woo-envios' ); ?>
									</label>
									<br>
									<label>
										<input type="checkbox" name="validate_addresses" value="1" checked>
										<?php esc_html_e( 'Validar endereços em tempo real', 'woo-envios' ); ?>
									</label>
								</td>
							</tr>
						</table>

						<div class="udi-gm-test-section">
							<button type="button" id="udi-gm-test-btn" class="button button-secondary button-large">
								<span class="dashicons dashicons-update"></span> <?php esc_html_e( 'Testar Conexão', 'woo-envios' ); ?>
							</button>
							<div id="udi-gm-test-result" class="udi-gm-test-result"></div>
						</div>

						<div class="udi-gm-wizard-actions">
							<button type="button" class="button udi-gm-prev">← <?php esc_html_e( 'Voltar', 'woo-envios' ); ?></button>
							<button type="submit" class="button button-primary button-large" disabled id="udi-gm-save-btn">
								<span class="dashicons dashicons-saved"></span> <?php esc_html_e( 'Salvar e Concluir', 'woo-envios' ); ?>
							</button>
						</div>
					</form>
				</div>
			</div>
		</div>

	<?php else : ?>
		<!-- CONFIGURAÇÕES NORMAIS (após wizard) -->
		<div class="udi-gm-settings">
			<div class="udi-gm-status-card <?php echo $is_configured ? 'connected' : 'disconnected'; ?>">
				<div class="udi-gm-status-icon">
					<?php if ( $is_configured ) : ?>
						<span class="dashicons dashicons-yes-alt"></span>
					<?php else : ?>
						<span class="dashicons dashicons-warning"></span>
					<?php endif; ?>
				</div>
				<div class="udi-gm-status-content">
					<h3>
						<?php echo $is_configured ? esc_html__( 'Google Maps Conectado', 'woo-envios' ) : esc_html__( 'Google Maps Não Configurado', 'woo-envios' ); ?>
					</h3>
					<p>
						<?php
						echo $is_configured
							? esc_html__( 'Sua loja está usando Google Maps para validação de endereços e cálculo de frete.', 'woo-envios' )
							: esc_html__( 'Configure o Google Maps API para melhorar a experiência de checkout.', 'woo-envios' );
						?>
					</p>
				</div>
			</div>

			<form method="post" id="udi-gm-settings-form" class="udi-gm-config-form">
				<h2><?php esc_html_e( 'Configurações', 'woo-envios' ); ?></h2>
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="udi_api_key_main"><?php esc_html_e( 'API Key', 'woo-envios' ); ?></label>
						</th>
						<td>
							<input type="text" id="udi_api_key_main" name="api_key" value="<?php echo esc_attr( $api_key ); ?>" class="regular-text">
							<p class="description"><?php esc_html_e( 'Sua Google Maps API Key.', 'woo-envios' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="udi_cache_ttl_main"><?php esc_html_e( 'Cache (dias)', 'woo-envios' ); ?></label>
						</th>
						<td>
							<input type="number" id="udi_cache_ttl_main" name="cache_ttl" value="<?php echo esc_attr( get_option( 'udi_google_maps_cache_ttl', 30 * 86400 ) / 86400 ); ?>" min="1" max="365" class="small-text">
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Recursos', 'woo-envios' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="autocomplete_enabled" value="1" <?php checked( get_option( 'udi_google_maps_autocomplete_enabled', true ) ); ?>>
								<?php esc_html_e( 'Autocomplete de endereços', 'woo-envios' ); ?>
							</label>
							<br>
							<label>
								<input type="checkbox" name="validate_addresses" value="1" <?php checked( get_option( 'udi_google_maps_validate_addresses', true ) ); ?>>
								<?php esc_html_e( 'Validar endereços', 'woo-envios' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<p class="submit">
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Salvar Alterações', 'woo-envios' ); ?></button>
					<button type="button" id="udi-gm-test-btn-main" class="button button-secondary">
						<span class="dashicons dashicons-update"></span> <?php esc_html_e( 'Testar Conexão', 'woo-envios' ); ?>
					</button>
				</p>
			</form>

			<div class="udi-gm-stats-section">
				<h2><?php esc_html_e( 'Estatísticas de Uso', 'woo-envios' ); ?></h2>
				<div class="udi-gm-stats-grid">
					<div class="udi-gm-stat-card">
						<span class="dashicons dashicons-database"></span>
						<div class="udi-gm-stat-value"><?php echo esc_html( number_format_i18n( $usage_stats['total_cached'] ) ); ?></div>
						<div class="udi-gm-stat-label"><?php esc_html_e( 'Endereços em Cache', 'woo-envios' ); ?></div>
					</div>
					<div class="udi-gm-stat-card">
						<span class="dashicons dashicons-calendar-alt"></span>
						<div class="udi-gm-stat-value"><?php echo esc_html( number_format_i18n( $usage_stats['cached_today'] ) ); ?></div>
						<div class="udi-gm-stat-label"><?php esc_html_e( 'Hoje', 'woo-envios' ); ?></div>
					</div>
					<div class="udi-gm-stat-card">
						<span class="dashicons dashicons-chart-line"></span>
						<div class="udi-gm-stat-value"><?php echo esc_html( number_format_i18n( $usage_stats['cached_month'] ) ); ?></div>
						<div class="udi-gm-stat-label"><?php esc_html_e( 'Este Mês', 'woo-envios' ); ?></div>
					</div>
					<div class="udi-gm-stat-card">
						<span class="dashicons dashicons-trash"></span>
						<div class="udi-gm-stat-value"><?php echo esc_html( number_format_i18n( $usage_stats['expired_count'] ) ); ?></div>
						<div class="udi-gm-stat-label"><?php esc_html_e( 'Expirados', 'woo-envios' ); ?></div>
					</div>
				</div>

				<div class="udi-gm-cache-actions">
					<button type="button" id="udi-gm-clear-expired" class="button">
						<?php esc_html_e( 'Limpar Cache Expirado', 'woo-envios' ); ?>
					</button>
					<button type="button" id="udi-gm-clear-all" class="button">
						<?php esc_html_e( 'Limpar Todo Cache', 'woo-envios' ); ?>
					</button>
				</div>
			</div>
		</div>
	<?php endif; ?>
</div>
