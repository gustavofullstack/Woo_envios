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
                    'dashicons-cloud', // Icon
                    59 // Position
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
            ?>
            <div class="wrap">
                <h1>TriqHub Dashboard</h1>
                <p>Bem-vindo ao centro de controle dos seus plugins TriqHub.</p>
                <h2 class="nav-tab-wrapper">
                    <a href="?page=triqhub" class="nav-tab nav-tab-active">Visão Geral</a>
                    <a href="?page=triqhub-license" class="nav-tab">Licença</a>
                </h2>
                <!-- Dashboard widgets or active plugins list here -->
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
