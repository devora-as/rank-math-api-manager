<?php
/**
 * Plugin Name: Rank Math API Manager
 * Plugin URI: https://devora.no/plugins/rankmath-api-manager
 * Description: A WordPress plugin that manages the update of Rank Math metadata (SEO Title, SEO Description, Canonical URL, Focus Keyword) via the REST API for WordPress posts and WooCommerce products.
 * Version: 1.0.7
 * Author: Devora AS
 * Author URI: https://devora.no
 * License: GPL v3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: rank-math-api-manager
 * GitHub Plugin URI: https://github.com/devora-as/rank-math-api-manager
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants
define('RANK_MATH_API_VERSION', '1.0.7');
define('RANK_MATH_API_PLUGIN_FILE', __FILE__);
define('RANK_MATH_API_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('RANK_MATH_API_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Main Plugin Class
 *
 * @since 1.0.7
 */
class Rank_Math_API_Manager_Extended {
	
	/**
	 * Plugin instance
	 *
	 * @var Rank_Math_API_Manager_Extended
	 */
	private static $instance = null;

	/**
	 * Update manager instance
	 *
	 * @var Rank_Math_API_Update_Manager
	 */
	private $update_manager = null;

	/**
	 * Get plugin instance
	 *
	 * @return Rank_Math_API_Manager_Extended
	 */
	public static function get_instance() {
		if (null === self::$instance) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		$this->init_hooks();
		$this->load_dependencies();
	}

	/**
	 * Initialize WordPress hooks
	 */
	private function init_hooks() {
		add_action( 'rest_api_init', [ $this, 'register_meta_fields' ] );
		add_action( 'rest_api_init', [ $this, 'register_api_routes' ] );
		add_action( 'plugins_loaded', [ $this, 'init_update_manager' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
	}

	/**
	 * Load plugin dependencies
	 */
	private function load_dependencies() {
		// Load update manager
		$update_manager_file = RANK_MATH_API_PLUGIN_DIR . 'includes/class-rank-math-api-update-manager.php';
		if (file_exists($update_manager_file)) {
			require_once $update_manager_file;
		}
	}

	/**
	 * Initialize update manager
	 */
	public function init_update_manager() {
		if (class_exists('Rank_Math_API_Update_Manager')) {
			$this->update_manager = new Rank_Math_API_Update_Manager();
		}
	}

	/**
	 * Enqueue admin scripts and styles
	 */
	public function enqueue_admin_scripts($hook) {
		// Only load on our admin pages
		if (strpos($hook, 'rank-math-api') === false) {
			return;
		}

		wp_enqueue_script(
			'rank-math-api-admin',
			RANK_MATH_API_PLUGIN_URL . 'assets/js/admin.js',
			array('jquery'),
			RANK_MATH_API_VERSION,
			true
		);

		wp_enqueue_style(
			'rank-math-api-admin',
			RANK_MATH_API_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			RANK_MATH_API_VERSION
		);

		// Localize script with AJAX URL and nonce
		wp_localize_script('rank-math-api-admin', 'rankMathApi', array(
			'ajaxUrl' => admin_url('admin-ajax.php'),
			'nonce' => wp_create_nonce('rank_math_api_update_nonce'),
			'strings' => array(
				'checkingUpdates' => __('Checking for updates...', 'rank-math-api-manager'),
				'updateAvailable' => __('Update available!', 'rank-math-api-manager'),
				'noUpdateAvailable' => __('No updates available.', 'rank-math-api-manager'),
				'errorChecking' => __('Error checking for updates.', 'rank-math-api-manager')
			)
		));
	}

	/**
	 * Register meta fields for REST API
	 */
	public function register_meta_fields() {
		$meta_fields = [
			'rank_math_title'         => 'SEO Title',
			'rank_math_description'   => 'SEO Description',
			'rank_math_canonical_url' => 'Canonical URL',
			'rank_math_focus_keyword' => 'Focus Keyword',
		];

		$post_types = [ 'post' ];
		if ( class_exists( 'WooCommerce' ) ) {
			$post_types[] = 'product';
		}

		foreach ( $post_types as $post_type ) {
			foreach ( $meta_fields as $meta_key => $description ) {
				$args = [
					'show_in_rest'  => true,
					'single'        => true,
					'type'          => 'string',
					'description'   => $description,
					'auth_callback' => [ $this, 'check_update_permission' ],
				];

				register_post_meta( $post_type, $meta_key, $args );
			}
		}
	}

	/**
	 * Register REST API routes
	 */
	public function register_api_routes() {
		register_rest_route( 'rank-math-api/v1', '/update-meta', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'update_rank_math_meta' ],
			'permission_callback' => [ $this, 'check_update_permission' ],
			'args'                => [
				'post_id' => [
					'required'          => true,
					'validate_callback' => function ( $param ) {
						return is_numeric( $param ) && get_post( $param );
					}
				],
				'rank_math_title'         => [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
				'rank_math_description'   => [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
				'rank_math_canonical_url' => [ 'type' => 'string', 'sanitize_callback' => 'esc_url_raw' ],
				'rank_math_focus_keyword' => [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
			],
		] );
	}

	/**
	 * Update Rank Math meta data
	 *
	 * @param WP_REST_Request $request Request object
	 * @return WP_REST_Response|WP_Error Response object
	 */
	public function update_rank_math_meta( WP_REST_Request $request ) {
		$post_id = $request->get_param( 'post_id' );
		$fields  = [
			'rank_math_title',
			'rank_math_description',
			'rank_math_focus_keyword',
			'rank_math_canonical_url',
		];

		$result = [];

		foreach ( $fields as $field ) {
			$value = $request->get_param( $field );

			if ( $value !== null ) {
				$update_result = update_post_meta( $post_id, $field, $value );
				$result[ $field ] = $update_result ? 'updated' : 'failed';
			}
		}

		if ( empty( $result ) ) {
			return new WP_Error( 'no_update', 'No metadata was updated', [ 'status' => 400 ] );
		}

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * Check update permission
	 *
	 * @return bool True if user can edit posts
	 */
	public function check_update_permission() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Get plugin version
	 *
	 * @return string Plugin version
	 */
	public function get_version() {
		return RANK_MATH_API_VERSION;
	}

	/**
	 * Get update manager instance
	 *
	 * @return Rank_Math_API_Update_Manager|null
	 */
	public function get_update_manager() {
		return $this->update_manager;
	}
}

// Initialize the plugin
function rank_math_api_manager_init() {
	return Rank_Math_API_Manager_Extended::get_instance();
}

// Start the plugin
add_action('plugins_loaded', 'rank_math_api_manager_init');

// Activation hook
register_activation_hook(__FILE__, function() {
	// Create necessary directories
	$upload_dir = wp_upload_dir();
	$plugin_dir = $upload_dir['basedir'] . '/rank-math-api-manager';
	
	if (!file_exists($plugin_dir)) {
		wp_mkdir_p($plugin_dir);
	}
	
	// Add activation timestamp
	update_option('rank_math_api_activated', current_time('mysql'));
});

// Deactivation hook
register_deactivation_hook(__FILE__, function() {
	// Clear scheduled events
	wp_clear_scheduled_hook('rank_math_api_update_check');
	
	// Clear caches
	delete_transient('rank_math_api_latest_release_cache');
	delete_option('rank_math_api_last_update_check');
});

// Uninstall hook
register_uninstall_hook(__FILE__, function() {
	// Remove all plugin options
	delete_option('rank_math_api_activated');
	delete_option('rank_math_api_last_update_check');
	delete_option('rank_math_api_latest_release');
	delete_option('rank_math_api_update_logs');
});
