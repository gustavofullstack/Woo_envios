<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Woo_Envios_Updater
 *
 * Handles automatic updates via plugin-update.json.
 */
class Woo_Envios_Updater {

	private $slug;
	private $plugin_data;
	private $username;
	private $repo;
	private $plugin_file;
	private $json_url;

	/**
	 * Class constructor.
	 *
	 * @param string $plugin_file Absolute path to the main plugin file.
	 * @param string $username    GitHub username.
	 * @param string $repo        GitHub repository name.
	 */
	public function __construct( $plugin_file, $username, $repo ) {
		$this->plugin_file = $plugin_file;
		$this->username    = $username;
		$this->repo        = $repo;
		$this->slug        = plugin_basename( $this->plugin_file );
		
		// Fonte da verdade: JSON na raiz do repo (Raw)
		$this->json_url = "https://raw.githubusercontent.com/{$this->username}/{$this->repo}/main/plugin-update.json";

		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_update' ) );
		add_filter( 'plugins_api', array( $this, 'check_info' ), 10, 3 );
		add_filter( 'upgrader_process_complete', array( $this, 'track_update' ), 10, 2 );
	}

	/**
	 * Retrieve update info from the remote JSON file.
	 * 
	 * @return object|bool
	 */
	private function get_remote_info() {
		$request = wp_remote_get( $this->json_url );

		if ( is_wp_error( $request ) || 200 !== wp_remote_retrieve_response_code( $request ) ) {
			return false;
		}

		$body = wp_remote_retrieve_body( $request );
		$data = json_decode( $body );

		if ( empty( $data ) || ! is_object( $data ) ) {
			return false;
		}

		return $data;
	}

	/**
	 * Push update to the transient.
	 */
	public function check_update( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$remote = $this->get_remote_info();
		if ( ! $remote ) {
			return $transient;
		}

		$this->plugin_data = get_plugin_data( $this->plugin_file );
		$current_version   = $this->plugin_data['Version'];

		if ( version_compare( $current_version, $remote->version, '<' ) ) {
			$obj = new stdClass();
			$obj->slug        = $this->slug;
			$obj->new_version = $remote->version;
			$obj->url         = $this->json_url;
			$obj->package     = $remote->download_url;

			$transient->response[ $this->slug ] = $obj;
		}

		return $transient;
	}

	/**
	 * Push info to the "View Details" popup.
	 */
	public function check_info( $res, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $res;
		}

		if ( empty( $args->slug ) || $this->slug !== $args->slug ) {
			return $res;
		}

		$remote = $this->get_remote_info();
		if ( ! $remote ) {
			return $res;
		}

		$res = new stdClass();
		$res->name           = $remote->name;
		$res->slug           = $this->slug;
		$res->version        = $remote->version;
		$res->tested         = $remote->tested;
		$res->requires       = $remote->requires;
		$res->author         = $remote->author;
		$res->author_profile = $remote->author_profile;
		$res->download_link  = $remote->download_url;
		$res->trunk          = $remote->download_url;
		$res->requires_php   = $remote->requires_php;
		$res->last_updated   = $remote->last_updated;
		$res->sections       = (array) $remote->sections;

		if ( ! empty( $remote->banners ) ) {
			$res->banners = (array) $remote->banners;
		}

		return $res;
	}

	/**
	 * Opcional: Track update (simulado).
	 */
	public function track_update( $upgrader_object, $options ) {
		// Logica de tracking poderia vir aqui
	}
}
