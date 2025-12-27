<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'TriqHub_Connector' ) ) {

    class TriqHub_Connector {

        private $api_key;
        private $product_id;
        private $api_url = 'https://triqhub.com/api/v1'; // Production URL
        private $version = '1.0.0';

        public function __construct( $api_key, $product_id ) {
            $this->api_key = $api_key;
            $this->product_id = $product_id;

            // Hook into WordPress init
            add_action( 'init', array( $this, 'listen_for_webhooks' ) );
            
            // Periodically check license status (optional, uses transient)
            add_action( 'admin_init', array( $this, 'check_license_status' ) );
        }

        /**
         * Listen for incoming webhooks from TriqHub
         * URL: site.com/?triqhub_action=webhook&product_id=slug
         */
        public function listen_for_webhooks() {
            if ( isset( $_GET['triqhub_action'] ) && $_GET['triqhub_action'] === 'webhook' ) {
                
                // Security Check: Verify signature or key if needed. 
                // For "invisible key", we trust the bearer if provided, or rely on the known api_key match.
                
                $request_product_id = isset( $_GET['product_id'] ) ? sanitize_text_field( $_GET['product_id'] ) : '';
                
                if ( $request_product_id !== $this->product_id ) {
                    return; // Not for this plugin
                }

                $payload = json_decode( file_get_contents( 'php://input' ), true );
                
                // Handle events (e.g., license_revoked, plan_updated)
                if ( isset( $payload['event'] ) ) {
                    $this->handle_event( $payload );
                }

                wp_send_json_success( array( 'message' => 'Event received' ) );
            }
        }

        private function handle_event( $payload ) {
            // Example: Update local status option based on remote event
            $option_name = 'triqhub_status_' . $this->product_id;
            
            switch ( $payload['event'] ) {
                case 'license_active':
                    update_option( $option_name, 'active' );
                    break;
                case 'license_revoked':
                    update_option( $option_name, 'revoked' );
                    break;
            }
        }

        public function check_license_status() {
            // Check cache (transient) for 12 hours
            $transient_name = 'triqhub_license_check_' . $this->product_id;
            if ( get_transient( $transient_name ) ) {
                return;
            }

            // Call API
            $response = wp_remote_post( $this->api_url . '/validate', array(
                'body' => array(
                    'api_key' => $this->api_key,
                    'product_id' => $this->product_id,
                    'domain' => home_url()
                )
            ) );

            if ( is_wp_error( $response ) ) {
                return;
            }

            $body = wp_remote_retrieve_body( $response );
            $data = json_decode( $body, true );

            if ( isset( $data['status'] ) ) {
                update_option( 'triqhub_status_' . $this->product_id, $data['status'] );
            }

            set_transient( $transient_name, true, 12 * HOUR_IN_SECONDS );
        }

        public function is_active() {
            $status = get_option( 'triqhub_status_' . $this->product_id, 'active' ); // Default active for "invisble mode" unless revoked
            return $status === 'active';
        }
    }
}
