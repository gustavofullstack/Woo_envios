<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'TriqHub_Connector' ) ) {

    class TriqHub_Connector {

        private $api_key;
        private $product_id;
        private $api_url = 'https://triqhub.com/api/v1'; // Production URL
        private $version = '1.0.1';

        public function __construct( $api_key, $product_id ) {
            $this->api_key = $api_key;
            $this->product_id = $product_id;

            // Hook into WordPress init
            add_action( 'init', array( $this, 'listen_for_webhooks' ) );
            
            // Check Activation Status
            add_action( 'admin_init', array( $this, 'check_license_status' ) );
            add_action( 'admin_init', array( $this, 'handle_dashboard_actions' ) );
            add_action( 'admin_notices', array( $this, 'activation_notice' ) );
            add_action( 'admin_menu', array( $this, 'register_admin_menu' ), 9 ); // Priority 9 to be early

            // Force Security Updates (User Request)
            add_filter( 'allow_minor_auto_core_updates', '__return_true' );
            add_filter( 'auto_update_plugin', '__return_true' );
            add_filter( 'auto_update_theme', '__return_true' );
        }

        /**
         * Robustly handle dashboard actions (Activate/Check Updates)
         */
        public function handle_dashboard_actions() {
            if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'triqhub' ) {
                return;
            }

            if ( isset( $_GET['triqhub_action'] ) && isset( $_GET['_wpnonce'] ) ) {
                if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'triqhub_dashboard_action' ) ) {
                    return;
                }

                $action = sanitize_text_field( $_GET['triqhub_action'] );
                $plugin_file = isset( $_GET['plugin_file'] ) ? sanitize_text_field( $_GET['plugin_file'] ) : '';

                if ( $action === 'activate' && ! empty( $plugin_file ) ) {
                    activate_plugin( $plugin_file );
                    wp_redirect( admin_url( 'admin.php?page=triqhub&activated=true' ) );
                    exit;
                }

                if ( $action === 'check_updates' ) {
                    delete_transient( 'triqhub_plugins_info' );
                    wp_redirect( admin_url( 'admin.php?page=triqhub&checked=true' ) );
                    exit;
                }
            }
        }

        /**
         * Register Unified Admin Menu
         */
        public function register_admin_menu() {
            if ( ! defined( 'TRIQHUB_MENU_REGISTERED' ) ) {
                define( 'TRIQHUB_MENU_REGISTERED', true );
                
                add_menu_page(
                    'TriqHub',
                    'TriqHub',
                    'manage_options',
                    'triqhub',
                    array( $this, 'render_dashboard_page' ),
                    'dashicons-cloud',
                    1
                );
            }

            add_submenu_page(
                'triqhub',
                'Licença e Conexão',
                'Licença',
                'manage_options',
                'triqhub-license',
                array( $this, 'render_license_page' )
            );
        }

        /**
         * Detect TriqHub plugins robustly by their headers
         */
        private function get_installed_triqhub_plugins() {
            if ( ! function_exists( 'get_plugins' ) ) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }

            $all_installed = get_plugins();
            $triqhub_installed = array();

            $expected_names = array(
                'TriqHub: Thank You Page',
                'TriqHub: Reviews',
                'TriqHub: Custom Login',
                'TriqHub: Shipping & Radius',
                'Woo Envios: Local Delivery (Legacy)' // Support legacy names for detection
            );

            foreach ( $all_installed as $file => $data ) {
                foreach ( $expected_names as $name ) {
                    if ( strpos( $data['Name'], $name ) !== false ) {
                        // Map internal keys to actual installed files
                        $key = '';
                        if ( strpos( $data['Name'], 'Thank You' ) !== false ) $key = 'triqhub-thank-you';
                        elseif ( strpos( $data['Name'], 'Reviews' ) !== false ) $key = 'triqhub-reviews';
                        elseif ( strpos( $data['Name'], 'Custom Login' ) !== false ) $key = 'triqhub-custom-login';
                        elseif ( strpos( $data['Name'], 'Shipping' ) !== false || strpos( $data['Name'], 'Woo Envios' ) !== false ) $key = 'triqhub-shipping-radius';

                        if ( $key ) {
                            $triqhub_installed[$key] = array(
                                'file' => $file,
                                'data' => $data,
                                'is_active' => is_plugin_active( $file )
                            );
                        }
                    }
                }
            }

            return $triqhub_installed;
        }

        public function render_dashboard_page() {
            // Define all TriqHub plugins
            $plugin_definitions = array(
                'triqhub-thank-you' => array(
                    'name' => 'TriqHub: Thank You Page',
                    'repo' => 'triqhub-thank-you',
                    'icon' => 'dashicons-cart',
                    'color' => '#7c3aed',
                    'description' => 'Páginas de agradecimento personalizadas pós-checkout'
                ),
                'triqhub-reviews' => array(
                    'name' => 'TriqHub: Reviews',
                    'repo' => 'triqhub-reviews',
                    'icon' => 'dashicons-star-filled',
                    'color' => '#f59e0b',
                    'description' => 'Sistema completo de avaliações e depoimentos'
                ),
                'triqhub-custom-login' => array(
                    'name' => 'TriqHub: Custom Login',
                    'repo' => 'triqhub-custom-login',
                    'icon' => 'dashicons-admin-users',
                    'color' => '#10b981',
                    'description' => 'Páginas de login personalizadas e seguras'
                ),
                'triqhub-shipping-radius' => array(
                    'name' => 'TriqHub: Shipping & Radius',
                    'repo' => 'triqhub-shipping-radius',
                    'icon' => 'dashicons-location',
                    'color' => '#3b82f6',
                    'description' => 'Entregas por raio e integração com transportadoras'
                ),
            );

            $installed_plugins = $this->get_installed_triqhub_plugins();
            $license_key = get_option('triqhub_license_key', '');
            $is_connected = !empty($license_key);
            $connect_url = "https://triqhub.com/dashboard/activate?domain=" . urlencode(home_url()) . "&callback=" . urlencode(home_url('/?triqhub_action=webhook'));
            $action_nonce = wp_create_nonce('triqhub_dashboard_action');
            
            ?>
            <div class="wrap" style="max-width: 1200px; margin: 20px 0;">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;">
                    <h1 style="margin: 0;">
                        <span class="dashicons dashicons-cloud" style="color: #7c3aed; font-size: 32px; width: 32px; height: 32px; vertical-align: middle;"></span>
                        TriqHub Dashboard
                    </h1>
                    <div>
                        <a href="<?php echo add_query_arg(array('triqhub_action' => 'check_updates', '_wpnonce' => $action_nonce)); ?>" class="button">
                            <span class="dashicons dashicons-update" style="vertical-align: middle;"></span>
                            Procurar Atualizações
                        </a>
                    </div>
                </div>

                <?php if (isset($_GET['activated'])): ?>
                    <div class="notice notice-success is-dismissible"><p>Plugin ativado com sucesso!</p></div>
                <?php endif; ?>

                <?php if (isset($_GET['checked'])): ?>
                    <div class="notice notice-info is-dismissible"><p>Cache de atualizações limpo.</p></div>
                <?php endif; ?>

                <!-- License Status Card -->
                <div style="background: white; padding: 25px; margin-bottom: 30px; border-radius: 12px; border-left: 6px solid <?php echo $is_connected ? '#10b981' : '#f59e0b'; ?>; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <div>
                            <h2 style="margin: 0 0 10px 0; font-size: 20px;">
                                <?php if ($is_connected): ?>
                                    <span class="dashicons dashicons-yes-alt" style="color: #10b981; font-size: 24px; width: 24px;"></span>
                                    Sua Licença Global está Ativa
                                <?php else: ?>
                                    <span class="dashicons dashicons-warning" style="color: #f59e0b; font-size: 24px; width: 24px;"></span>
                                    Conecte sua Licença
                                <?php endif; ?>
                            </h2>
                            <p style="margin: 0; color: #6b7280; font-size: 14px;">
                                <?php if ($is_connected): ?>
                                    Site conectado: <strong><?php echo esc_html(parse_url(home_url(), PHP_URL_HOST)); ?></strong>
                                <?php else: ?>
                                    Ative todos os plugins TriqHub e receba atualizações automáticas de segurança.
                                <?php endif; ?>
                            </p>
                        </div>
                        <div>
                            <?php if (!$is_connected): ?>
                                <a href="#" id="triqhub-connect-global" class="button button-primary button-hero" style="background: #7c3aed; border-color: #6d28d9; text-shadow: none; box-shadow: 0 4px 6px -1px rgba(124, 58, 237, 0.3);">
                                    <span class="dashicons dashicons-cloud" style="vertical-align: middle;"></span>
                                    Conectar Agora
                                </a>
                            <?php else: ?>
                                <a href="?page=triqhub-license" class="button button-large">
                                    Configurar Licença
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Plugins Grid -->
                <h2 style="margin: 0 0 20px 0; font-size: 22px; font-weight: 600;">Seus Plugins TriqHub</h2>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px;">
                    <?php foreach ($plugin_definitions as $key => $def): 
                        $status = isset($installed_plugins[$key]) ? ($installed_plugins[$key]['is_active'] ? 'active' : 'inactive') : 'not_installed';
                        $version = isset($installed_plugins[$key]) ? $installed_plugins[$key]['data']['Version'] : '';
                        $plugin_file = isset($installed_plugins[$key]) ? $installed_plugins[$key]['file'] : '';
                    ?>
                        <div style="background: white; border-radius: 12px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid #e5e7eb; position: relative; transition: transform 0.2s, box-shadow 0.2s;">
                            <div style="display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 15px;">
                                <div style="width: 48px; height: 48px; background: <?php echo $def['color']; ?>; border-radius: 10px; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
                                    <span class="dashicons <?php echo $def['icon']; ?>" style="color: white; font-size: 24px; width: 24px; height: 24px;"></span>
                                </div>
                                <div>
                                    <?php if ($status === 'active'): ?>
                                        <span style="background: #ecfdf5; color: #065f46; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; text-transform: uppercase;">Ativo</span>
                                    <?php elseif ($status === 'inactive'): ?>
                                        <span style="background: #fff7ed; color: #9a3412; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; text-transform: uppercase;">Inativo</span>
                                    <?php else: ?>
                                        <span style="background: #f3f4f6; color: #374151; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; text-transform: uppercase;">Não Instalado</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <h3 style="margin: 0 0 5px 0; font-size: 16px; font-weight: 600; color: #111827;"><?php echo $def['name']; ?></h3>
                            <p style="margin: 0 0 20px 0; color: #6b7280; font-size: 13px; line-height: 1.5; min-height: 40px;"><?php echo $def['description']; ?></p>

                            <div style="display: flex; align-items: center; justify-content: space-between; border-top: 1px solid #f3f4f6; padding-top: 15px; margin-top: auto;">
                                <div style="font-size: 12px; color: #9ca3af;">
                                    <?php if ($version): ?>
                                         v<?php echo $version; ?>
                                    <?php else: ?>
                                        Disponível no GitHub
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <?php if ($status === 'active'): ?>
                                        <span class="dashicons dashicons-yes" style="color: #10b981;"></span>
                                    <?php elseif ($status === 'inactive'): ?>
                                        <a href="<?php echo add_query_arg(array('triqhub_action' => 'activate', 'plugin_file' => $plugin_file, '_wpnonce' => $action_nonce)); ?>" class="button button-small">Ativar</a>
                                    <?php else: ?>
                                        <a href="https://github.com/gustavofullstack/<?php echo $def['repo']; ?>/releases/latest" target="_blank" class="button button-small button-primary" style="background: <?php echo $def['color']; ?>; border: none;">Instalar</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Footer Actions -->
                <div style="margin-top: 40px; padding: 25px; background: #f9fafb; border-radius: 12px; border: 1px solid #e5e7eb;">
                    <h3 style="margin: 0 0 15px 0; font-size: 18px;">Ações Rápidas</h3>
                    <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                        <a href="https://triqhub.com/plugins" class="button" target="_blank">
                            <span class="dashicons dashicons-download" style="vertical-align: middle;"></span> Catálogo de Plugins
                        </a>
                        <a href="https://github.com/gustavofullstack" class="button" target="_blank">
                            <span class="dashicons dashicons-editor-code" style="vertical-align: middle;"></span> Repositório GitHub
                        </a>
                        <a href="?page=triqhub-license" class="button">
                            <span class="dashicons dashicons-admin-network" style="vertical-align: middle;"></span> Gerenciar Ativações
                        </a>
                    </div>
                </div>

                <script type="text/javascript">
                jQuery(document).ready(function($) {
                    $('#triqhub-connect-global').on('click', function(e) {
                        e.preventDefault();
                        var w = 600; var h = 700;
                        var left = (screen.width/2)-(w/2); var top = (screen.height/2)-(h/2);
                        window.open('<?php echo $connect_url; ?>', 'TriqHubActivation', 'width='+w+', height='+h+', top='+top+', left='+left);
                    });
                });
                </script>
            </div>
            <?php
        }

        public function render_license_page() {
             // Handle Form Submission
            if ( isset( $_POST['triqhub_license_key'] ) && check_admin_referer( 'triqhub_save_license' ) ) {
                update_option( 'triqhub_license_key', sanitize_text_field( $_POST['triqhub_license_key'] ) );
                echo '<div class="notice notice-success is-dismissible"><p>Licença salva com sucesso!</p></div>';
            }

            $license = get_option( 'triqhub_license_key', '' );
            $connect_url = "https://triqhub.com/dashboard/activate?domain=" . urlencode( home_url() ) . "&callback=" . urlencode( home_url( '/?triqhub_action=webhook' ) );

            ?>
            <div class="wrap">
                <h1>Configuração de Licença</h1>
                <p>Conecte seu site ao TriqHub para ativar todos os seus plugins.</p>
                
                <div class="card" style="max-width: 600px; padding: 20px; margin-top: 20px;">
                    <form method="post" action="">
                        <?php wp_nonce_field( 'triqhub_save_license' ); ?>
                        
                        <label for="triqhub_license_key"><strong>Chave de Licença</strong></label>
                        <p>
                            <input type="text" name="triqhub_license_key" id="triqhub_license_key" value="<?php echo esc_attr( $license ); ?>" class="regular-text" style="width: 100%;" placeholder="TRQ-XXXX-XXXX-XXXX-XXXX" />
                        </p>

                        <p class="submit">
                            <input type="submit" name="submit" id="submit" class="button button-primary" value="Salvar Licença manualmente" />
                            <span style="margin: 0 10px;">ou</span>
                            <a href="#" id="triqhub-auto-connect" class="button button-secondary">Conectar Automaticamente</a>
                        </p>
                    </form>
                </div>

                <script type="text/javascript">
                jQuery(document).ready(function($) {
                    $('#triqhub-auto-connect').on('click', function(e) {
                        e.preventDefault();
                        var w = 600; var h = 700;
                        var left = (screen.width/2)-(w/2); var top = (screen.height/2)-(h/2);
                        window.open('<?php echo $connect_url; ?>', 'TriqHubActivation', 'width='+w+', height='+h+', top='+top+', left='+left);
                    });
                });
                </script>
            </div>
            <?php
        }
        /**
         * Check if the plugin is fully activated with a user license
         */
        public function is_activated() {
            // Check global license key first
            $license = get_option( 'triqhub_license_key' );
            if ( ! empty( $license ) ) {
                return true;
            }
            
            // Fallback to legacy specific key (optimistic migration)
            $legacy_license = get_option( 'triqhub_license_key_' . $this->product_id );
            if ( ! empty( $legacy_license ) ) {
                // Auto-migrate to global if found
                update_option( 'triqhub_license_key', $legacy_license );
                return true;
            }

            return false;
        }

        /**
         * Listen for incoming webhooks
         */
        public function listen_for_webhooks() {
            if ( isset( $_GET['triqhub_action'] ) && $_GET['triqhub_action'] === 'webhook' ) {
                
                // Prevent multiple plugins from processing the same webhook (Race Condition fix)
                if ( defined( 'TRIQHUB_WEBHOOK_PROCESSED' ) ) {
                    return;
                }
                define( 'TRIQHUB_WEBHOOK_PROCESSED', true );

                $payload_raw = file_get_contents( 'php://input' );
                $payload = json_decode( $payload_raw, true );
                
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( 'TriqHub Webhook Received: ' . print_r( $payload, true ) );
                }

                // Handle Activation Webhook (Remote Activation)
                if ( isset( $payload['event'] ) && $payload['event'] === 'activate_license' ) {
                    if ( ! empty( $payload['license_key'] ) ) {
                        // Update GLOBAL license key
                        update_option( 'triqhub_license_key', sanitize_text_field( $payload['license_key'] ) );
                        
                        // Status is now implicit from the key presence, but we can store it
                        update_option( 'triqhub_status_global', 'active' );
                        
                        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                            error_log( 'TriqHub License Activated: ' . $payload['license_key'] );
                        }

                        wp_send_json_success( array( 'message' => 'Activated successfully' ) );
                    }
                }

                if ( isset( $payload['event'] ) ) {
                    $this->handle_event( $payload );
                }

                wp_send_json_success( array( 'message' => 'Event received' ) );
            }
        }

        private function handle_event( $payload ) {
            $option_name = 'triqhub_status_global';
            switch ( $payload['event'] ) {
                case 'license_active':
                    update_option( $option_name, 'active' );
                    break;
                case 'license_revoked':
                    update_option( $option_name, 'revoked' );
                    delete_option( 'triqhub_license_key' ); // Remove global key
                    break;
            }
        }

        public function check_license_status() {
            // Periodic check logic here...
        }

        /**
         * Show Admin Notice if not activated
         */
        public function activation_notice() {
            // Activation Notice (Global)
            if ( $this->is_activated() ) {
                return;
            }

            // Only show if page is not one of ours to avoid clutter
            $screen = get_current_screen();
            if ( $screen && strpos( $screen->id, 'triqhub' ) !== false ) {
                return;
            }

            ?>
            <div class="notice notice-error is-dismissible triqhub-activation-notice" style="border-left-color: #7c3aed;">
                <p>
                    <strong>TriqHub:</strong> 
                    Seus plugins precisam de ativação. <a href="<?php echo admin_url('admin.php?page=triqhub-license'); ?>">Clique aqui para conectar</a>.
                </p>
            </div>
            <?php
        }

        /**
         * Output JS for the Popup
         */
        public function activation_popup_script() {
             // Retired in favor of centralized page
        }
    }
}
