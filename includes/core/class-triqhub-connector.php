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
            add_action( 'admin_notices', array( $this, 'activation_notice' ) );
            add_action( 'admin_footer', array( $this, 'activation_popup_script' ) );
        }

        /**
         * Check if the plugin is fully activated with a user license
         */
        public function is_activated() {
            $license = get_option( 'triqhub_license_key_' . $this->product_id );
            // In "Invisible Key" mode only, we might consider it active, 
            // but for the "User Popup" flow, we want a Real User Key.
            return ! empty( $license );
        }

        /**
         * Listen for incoming webhooks
         */
        public function listen_for_webhooks() {
            if ( isset( $_GET['triqhub_action'] ) && $_GET['triqhub_action'] === 'webhook' ) {
                $request_product_id = isset( $_GET['product_id'] ) ? sanitize_text_field( $_GET['product_id'] ) : '';
                
                if ( $request_product_id !== $this->product_id ) {
                    return; 
                }

                $payload = json_decode( file_get_contents( 'php://input' ), true );
                
                // Handle Activation Webhook (Remote Activation)
                if ( isset( $payload['event'] ) && $payload['event'] === 'activate_license' ) {
                    if ( ! empty( $payload['license_key'] ) ) {
                        update_option( 'triqhub_license_key_' . $this->product_id, sanitize_text_field( $payload['license_key'] ) );
                        update_option( 'triqhub_status_' . $this->product_id, 'active' );
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
            $option_name = 'triqhub_status_' . $this->product_id;
            switch ( $payload['event'] ) {
                case 'license_active':
                    update_option( $option_name, 'active' );
                    break;
                case 'license_revoked':
                    update_option( $option_name, 'revoked' );
                    delete_option( 'triqhub_license_key_' . $this->product_id );
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
            if ( $this->is_activated() ) {
                return;
            }

            // Don't show on all pages, maybe just dashboard and plugins?
            // For now, persistent to force activation as requested.
            $connect_url = "https://triqhub.com/connect?plugin=" . $this->product_id . "&domain=" . urlencode( home_url() );
            ?>
            <div class="notice notice-error is-dismissible triqhub-activation-notice" style="border-left-color: #7c3aed;">
                <p>
                    <strong><?php echo esc_html( $this->product_id ); ?>:</strong> 
                    Action Required! Please connect to TriqHub to activate your license and enable features.
                </p>
                <p>
                    <button id="triqhub-connect-btn-<?php echo esc_attr( $this->product_id ); ?>" class="button button-primary" style="background-color: #7c3aed; border-color: #6d28d9;">
                        Connect Account & Activate
                    </button>
                    <a href="#" style="margin-left: 10px; text-decoration: none; color: #666;">I have a key manually</a>
                </p>
            </div>
            <?php
        }

        /**
         * Output JS for the Popup
         */
        public function activation_popup_script() {
            if ( $this->is_activated() ) {
                return;
            }
            $connect_url = "https://triqhub.com/dashboard/activate?plugin=" . $this->product_id . "&domain=" . urlencode( home_url() ) . "&callback=" . urlencode( home_url( '/?triqhub_action=webhook' ) );
            ?>
            <script type="text/javascript">
            jQuery(document).ready(function($) {
                $('#triqhub-connect-btn-<?php echo esc_js( $this->product_id ); ?>').on('click', function(e) {
                    e.preventDefault();
                    
                    // Simple Popup Center
                    var w = 600;
                    var h = 700;
                    var left = (screen.width/2)-(w/2);
                    var top = (screen.height/2)-(h/2);
                    
                    window.open('<?php echo $connect_url; ?>', 'TriqHubActivation', 'toolbar=no, location=no, directories=no, status=no, menubar=no, scrollbars=yes, resizable=no, copyhistory=no, width='+w+', height='+h+', top='+top+', left='+left);
                    
                    // Polling for success (optional UI enhancement)
                    // setInterval(function() { checkStatus(); }, 2000);
                });
            });
            </script>
            <?php
        }
    }
}
