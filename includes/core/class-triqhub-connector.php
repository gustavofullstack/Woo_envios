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
            // Check Activation Status
            add_action( 'admin_init', array( $this, 'check_license_status' ) );
            add_action( 'admin_notices', array( $this, 'activation_notice' ) );
            add_action( 'admin_menu', array( $this, 'register_admin_menu' ), 9 ); // Priority 9 to be early

            // Force Security Updates (User Request)
            add_filter( 'allow_minor_auto_core_updates', '__return_true' );
            add_filter( 'auto_update_plugin', '__return_true' );
            add_filter( 'auto_update_theme', '__return_true' );
        }

        /**
         * Register Unified Admin Menu
         */
        public function register_admin_menu() {
            // Check if main menu exists (global variable or check menu structure)
            // Simpler: Just call add_menu_page. WordPress handles duplicates by slug if we are consistent.
            // But we only want ONE plugin to register the PARENT. 
            // We use a global check.
            
            if ( ! defined( 'TRIQHUB_MENU_REGISTERED' ) ) {
                define( 'TRIQHUB_MENU_REGISTERED', true );
                
                add_menu_page(
                    'TriqHub',
                    'TriqHub',
                    'manage_options',
                    'triqhub',
                    array( $this, 'render_dashboard_page' ),
                    array( $this, 'render_dashboard_page' ),
                    'dashicons-cloud', // Icon
                    2 // Position: Top of menu
                );
            }

            // Register Submenu for this specific plugin settings (optional, or just keep them under their own menus?)
            // The user wants "Configuração minha... todas junto". 
            // So maybe a Licenses Page?
            
            add_submenu_page(
                'triqhub',
                'Licença e Conexão',
                'Licença',
                'manage_options',
                'triqhub-license',
                array( $this, 'render_license_page' )
            );
        }


        public function render_dashboard_page() {
            // Define all TriqHub plugins
            $all_plugins = array(
                'triqhub-thank-you/triqhub-thank-you.php' => array(
                    'name' => 'TriqHub: Thank You Page',
                    'icon' => 'dashicons-cart',
                    'color' => '#7c3aed',
                    'description' => 'Páginas de agradecimento personalizadas pós-checkout'
                ),
                'triqhub-reviews/triqhub-reviews.php' => array(
                    'name' => 'TriqHub: Reviews',
                    'icon' => 'dashicons-star-filled',
                    'color' => '#f59e0b',
                    'description' => 'Sistema completo de avaliações e depoimentos'
                ),
                'triqhub-custom-login/triqhub-custom-login.php' => array(
                    'name' => 'TriqHub: Custom Login',
                    'icon' => 'dashicons-admin-users',
                    'color' => '#10b981',
                    'description' => 'Páginas de login personalizadas e seguras'
                ),
                'triqhub-shipping-radius/triqhub-shipping-radius.php' => array(
                    'name' => 'TriqHub: Shipping & Radius',
                    'icon' => 'dashicons-location',
                    'color' => '#3b82f6',
                    'description' => 'Entregas por raio e integração com transportadoras'
                ),
            );

            // Get all active plugins
            $active_plugins = get_option('active_plugins', array());
            
            // Get global license
            $license_key = get_option('triqhub_license_key', '');
            $is_connected = !empty($license_key);
            $connect_url = "https://triqhub.com/dashboard/activate?domain=" . urlencode(home_url()) . "&callback=" . urlencode(home_url('/?triqhub_action=webhook'));
            
            ?>
            <div class="wrap">
                <h1 class="wp-heading-inline">
                    <span class="dashicons dashicons-cloud" style="color: #7c3aed; font-size: 32px; width: 32px; height: 32px; vertical-align: middle;"></span>
                    TriqHub Dashboard
                </h1>
                <hr class="wp-header-end">

                <!-- License Status Card -->
                <div style="background: white; padding: 20px; margin: 20px 0; border-left: 4px solid <?php echo $is_connected ? '#10b981' : '#f59e0b'; ?>; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <div>
                            <h2 style="margin: 0 0 10px 0; font-size: 18px;">
                                <?php if ($is_connected): ?>
                                    <span class="dashicons dashicons-yes-alt" style="color: #10b981;"></span>
                                    Licença Global Conectada
                                <?php else: ?>
                                    <span class="dashicons dashicons-warning" style="color: #f59e0b;"></span>
                                    Licença Não Conectada
                                <?php endif; ?>
                            </h2>
                            <?php if ($is_connected): ?>
                                <p style="margin: 0; color: #6b7280;">
                                    <strong>Chave:</strong> <code style="background: #f3f4f6; padding: 4px 8px; border-radius: 4px;"><?php echo esc_html(substr($license_key, 0, 8)) . '...'; ?></code>
                                </p>
                            <?php else: ?>
                                <p style="margin: 0; color: #6b7280;">
                                    Conecte sua licença para ativar todos os plugins TriqHub de uma vez.
                                </p>
                            <?php endif; ?>
                        </div>
                        <div>
                            <?php if (!$is_connected): ?>
                                <a href="#" id="triqhub-connect-global" class="button button-primary button-hero" style="background: #7c3aed; border-color: #6d28d9; text-shadow: none; box-shadow: none;">
                                    <span class="dashicons dashicons-cloud" style="vertical-align: middle;"></span>
                                    Conectar Licença
                                </a>
                            <?php else: ?>
                                <a href="?page=triqhub-license" class="button">
                                    Gerenciar Licença
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Plugins Grid -->
                <h2 style="margin-top: 30px;">Seus Plugins TriqHub</h2>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; margin-top: 20px;">
                    <?php foreach ($all_plugins as $plugin_file => $plugin_data): 
                        $is_installed = in_array($plugin_file, $active_plugins);
                        $plugin_info = null;
                        if ($is_installed && function_exists('get_plugin_data')) {
                            $plugin_path = WP_PLUGIN_DIR . '/' . $plugin_file;
                            if (file_exists($plugin_path)) {
                                $plugin_info = get_plugin_data($plugin_path);
                            }
                        }
                    ?>
                        <div style="background: white; border: 2px solid <?php echo $is_installed ? $plugin_data['color'] : '#e5e7eb'; ?>; border-radius: 8px; padding: 20px; position: relative; transition: all 0.2s;">
                            <!-- Status Badge -->
                            <div style="position: absolute; top: 15px; right: 15px;">
                                <?php if ($is_installed): ?>
                                    <span style="background: <?php echo $plugin_data['color']; ?>; color: white; padding: 4px 12px; border-radius: 12px; font-size: 11px; font-weight: 600; text-transform: uppercase;">
                                        Instalado
                                    </span>
                                <?php else: ?>
                                    <span style="background: #e5e7eb; color: #6b7280; padding: 4px 12px; border-radius: 12px; font-size: 11px; font-weight: 600; text-transform: uppercase;">
                                        Não Instalado
                                    </span>
                                <?php endif; ?>
                            </div>

                            <!-- Plugin Icon -->
                            <div style="width: 50px; height: 50px; background: <?php echo $plugin_data['color']; ?>; border-radius: 8px; display: flex; align-items: center; justify-content: center; margin-bottom: 15px;">
                                <span class="dashicons <?php echo $plugin_data['icon']; ?>" style="color: white; font-size: 28px; width: 28px; height: 28px;"></span>
                            </div>

                            <!-- Plugin Info -->
                            <h3 style="margin: 0 0 8px 0; font-size: 16px; color: #111827;">
                                <?php echo esc_html($plugin_data['name']); ?>
                            </h3>
                            <p style="margin: 0 0 12px 0; color: #6b7280; font-size: 13px; line-height: 1.5;">
                                <?php echo esc_html($plugin_data['description']); ?>
                            </p>

                            <?php if ($is_installed && $plugin_info): ?>
                                <div style="padding-top: 12px; border-top: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center;">
                                    <span style="color: #9ca3af; font-size: 12px;">
                                        Versão <?php echo esc_html($plugin_info['Version']); ?>
                                    </span>
                                    <span class="dashicons dashicons-yes" style="color: <?php echo $plugin_data['color']; ?>;"></span>
                                </div>
                            <?php else: ?>
                                <div style="padding-top: 12px; border-top: 1px solid #e5e7eb;">
                                    <a href="https://triqhub.com/plugins" target="_blank" style="color: #7c3aed; text-decoration: none; font-size: 13px;">
                                        Baixar Plugin →
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Quick Actions -->
                <div style="margin-top: 30px; background: #f9fafb; padding: 20px; border-radius: 8px;">
                    <h3 style="margin: 0 0 15px 0;">Ações Rápidas</h3>
                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <a href="?page=triqhub-license" class="button">
                            <span class="dashicons dashicons-admin-network" style="vertical-align: middle;"></span>
                            Gerenciar Licenças
                        </a>
                        <a href="https://triqhub.com/plugins" class="button" target="_blank">
                            <span class="dashicons dashicons-download" style="vertical-align: middle;"></span>
                            Baixar Plugins
                        </a>
                        <a href="https://github.com/gustavofullstack" class="button" target="_blank">
                            <span class="dashicons dashicons-editor-code" style="vertical-align: middle;"></span>
                            Ver no GitHub
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
