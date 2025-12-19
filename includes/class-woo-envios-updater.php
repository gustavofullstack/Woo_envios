<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Woo_Envios_Updater
 *
 * Handles automatic updates from GitHub.
 */
class Woo_Envios_Updater {

	private $slug;
	private $plugin_data;
	private $username;
	private $repo;
	private $plugin_file;
	private $github_api_result;
	private $access_token;

	/**
	 * Class constructor.
	 *
	 * @param string $plugin_file Absolute path to the main plugin file.
	 * @param string $username    GitHub username.
	 * @param string $repo        GitHub repository name.
	 * @param string $access_token Optional GitHub Private Access Token.
	 */
	public function __construct( $plugin_file, $username, $repo, $access_token = '' ) {
		$this->plugin_file  = $plugin_file;
		$this->username     = $username;
		$this->repo         = $repo;
		$this->access_token = $access_token;
		$this->slug         = plugin_basename( $this->plugin_file );

		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'set_transient' ) );
		add_filter( 'plugins_api', array( $this, 'set_plugin_info' ), 10, 3 );
		add_filter( 'upgrader_post_install', array( $this, 'post_install' ), 10, 3 );
	}

	/**
	 * Get the latest release from GitHub.
	 *
	 * @return object|bool JSON decoded object or false on failure.
	 */
	private function get_github_release_info() {
		if ( ! empty( $this->github_api_result ) ) {
			return $this->github_api_result;
		}

		$url = "https://api.github.com/repos/{$this->username}/{$this->repo}/releases/latest";

		// Add Access Token if provided
		$args = array();
		if ( ! empty( $this->access_token ) ) {
			$args['headers'] = array(
				'Authorization' => "token {$this->access_token}",
			);
		}

		$request = wp_remote_get( $url, $args );

		if ( is_wp_error( $request ) ) {
			return false;
		}

		$body = wp_remote_retrieve_body( $request );
		$data = json_decode( $body );

		if ( ! empty( $data ) && is_object( $data ) && isset( $data->tag_name ) ) {
			$this->github_api_result = $data;
			return $data;
		}

		return false;
	}

	/**
	 * Check for updates and set the transient.
	 *
	 * @param object $transient The update transient.
	 * @return object
	 */
	public function set_transient( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		// Get current version
		$this->plugin_data = get_plugin_data( $this->plugin_file );
		$current_version   = $this->plugin_data['Version'];

		// Get remote info
		$remote_info = $this->get_github_release_info();

		if ( ! $remote_info ) {
			return $transient;
		}

		// Check if remote version is newer
		// Clean v prefix if exists. Ex: v1.0.0 -> 1.0.0
		$remote_version = str_replace( 'v', '', $remote_info->tag_name );

		if ( version_compare( $current_version, $remote_version, '<' ) ) {
			$obj              = new stdClass();
			$obj->slug        = $this->slug;
			$obj->new_version = $remote_version;
			$obj->url         = $remote_info->html_url;
			$obj->package     = $remote_info->zipball_url;

			// Add to transient
			$transient->response[ $this->slug ] = $obj;
		}

		return $transient;
	}

	/**
	 * Display plugin details in the "View Details" popup.
	 *
	 * @param bool   $result The current result.
	 * @param string $action The action being performed.
	 * @param object $args   The arguments.
	 * @return object|bool
	 */
	public function set_plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}

		if ( empty( $args->slug ) || $this->slug !== $args->slug ) {
			return $result;
		}

		$remote_info = $this->get_github_release_info();

		if ( ! $remote_info ) {
			return $result;
		}

		$plugin_data = get_plugin_data( $this->plugin_file );

		$obj                = new stdClass();
		$obj->name          = $plugin_data['Name'];
		$obj->slug          = $this->slug;
		$obj->version       = str_replace( 'v', '', $remote_info->tag_name );
		$obj->author        = $plugin_data['AuthorName'];
		$obj->homepage      = $remote_info->html_url;
		$obj->requires      = '5.0'; // Default requirement
		$obj->tested        = get_bloginfo( 'version' ); // Tested up to current version
		$obj->downloaded    = 0;
		$obj->last_updated  = $remote_info->published_at;
		$obj->sections      = array(
			'description' => $remote_info->body, // Use release notes as description
		);
		$obj->download_link = $remote_info->zipball_url;

		return $obj;
	}

	/**
	 * Post install actions.
	 *
	 * @param bool   $true       True.
	 * @param array  $hook_extra Extra args.
	 * @param object $result     Result object.
	 * @return bool
	 */
	public function post_install( $true, $hook_extra, $result ) {
		// Can be used to rename folder if GitHub extracted it with a weird name
		// For now simple return true
		return $true;
	}
}
