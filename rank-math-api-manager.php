<?php
/**
 * Plugin Name: Rank Math API Manager
 * Plugin URI: https://devora.no/plugins/rankmath-api-manager
 * Description: A WordPress extension that manages the update of Rank Math metadata (SEO Title, SEO Description, Canonical URL, Focus Keyword) via the REST API for WordPress posts and WooCommerce products.
 * Version: 1.0.8
 * Author: Devora AS
 * Author URI: https://devora.no
 * License: GPL v3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: rank-math-api-manager
 * Update URI: https://github.com/devora-as/rank-math-api-manager
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
define('RANK_MATH_API_VERSION', '1.0.8');
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
	 * Plugin data
	 *
	 * @var array
	 */
	private $plugin_data = null;

	/**
	 * GitHub repository information
	 *
	 * @var array
	 */
	private $github_repo = array(
		'owner' => 'devora-as',
		'repo'  => 'rank-math-api-manager',
		'api_url' => 'https://api.github.com/repos/devora-as/rank-math-api-manager/releases/latest'
	);

	/**
	 * GitHub API authentication token
	 *
	 * @var string|null
	 */
	private $github_token = null;

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
		$this->init_plugin_data();
		$this->init_hooks();
	}

	/**
	 * Initialize plugin data
	 *
	 * @since 1.0.7
	 */
	private function init_plugin_data() {
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		
		$this->plugin_data = get_plugin_data( RANK_MATH_API_PLUGIN_FILE );
		
		// Initialize GitHub token for higher rate limits
		$this->init_github_auth();
	}

	/**
	 * Initialize GitHub authentication
	 *
	 * @since 1.0.8
	 */
	private function init_github_auth() {
		// Check for GitHub token in WordPress options (secure storage)
		$this->github_token = get_option( 'rank_math_api_github_token' );
		
		// If no token, check for environment variable
		if ( ! $this->github_token && defined( 'RANK_MATH_GITHUB_TOKEN' ) ) {
			$this->github_token = RANK_MATH_GITHUB_TOKEN;
		}
	}

	/**
	 * Initialize WordPress hooks
	 */
	private function init_hooks() {
		// Check dependencies first
		add_action( 'plugins_loaded', [ $this, 'check_dependencies' ], 5 );
		
		// Monitor plugin activation/deactivation
		add_action( 'activated_plugin', [ $this, 'on_plugin_activated' ] );
		add_action( 'deactivated_plugin', [ $this, 'on_plugin_deactivated' ] );
		
		// Auto-update system hooks
		add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_for_update' ] );
		add_filter( 'plugins_api', [ $this, 'plugin_info' ], 10, 3 );
		
		// Only register core functionality hooks if dependencies are met
		if ( $this->are_dependencies_met() ) {
			add_action( 'rest_api_init', [ $this, 'register_meta_fields' ] );
			add_action( 'rest_api_init', [ $this, 'register_api_routes' ] );
			add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
		}
		
		// Admin notices for dependency issues
		add_action( 'admin_notices', [ $this, 'display_dependency_notices' ] );
	}

	/**
	 * Check if required plugins are active
	 *
	 * @since 1.0.7
	 * @return bool True if all dependencies are met
	 */
	public function are_dependencies_met() {
		$status = $this->get_dependency_status();
		return $status['dependencies_met'];
	}

	/**
	 * Get list of required plugins
	 *
	 * @since 1.0.7
	 * @return array Array of required plugins
	 */
	private function get_required_plugins() {
		return array(
			array(
				'name' => 'Rank Math SEO',
				'file' => 'seo-by-rank-math/rank-math.php',
				'version' => '1.0.0',
				'url' => 'https://wordpress.org/plugins/seo-by-rank-math/',
				'description' => 'Required for SEO metadata management'
			)
		);
	}

	/**
	 * Check if a specific plugin is active
	 *
	 * @since 1.0.7
	 * @param string $plugin_file Plugin file path
	 * @return bool True if plugin is active
	 */
	private function is_plugin_active( $plugin_file ) {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		
		return is_plugin_active( $plugin_file );
	}

	/**
	 * Check plugin dependencies and store status
	 *
	 * @since 1.0.7
	 */
	public function check_dependencies() {
		$dependencies_met = $this->are_dependencies_met();
		$current_status = get_option( 'rank_math_api_dependencies_status', false );
		
		// Update status if it changed
		if ( $current_status !== $dependencies_met ) {
			update_option( 'rank_math_api_dependencies_status', $dependencies_met );
			
			// If dependencies are no longer met, deactivate functionality
			if ( ! $dependencies_met ) {
				$this->handle_dependencies_missing();
			}
		}
	}

	/**
	 * Handle missing dependencies
	 *
	 * @since 1.0.7
	 */
	private function handle_dependencies_missing() {
		// Add admin notice
		add_action( 'admin_notices', function() {
			echo '<div class="notice notice-error"><p>';
			echo '<strong>Rank Math API Manager:</strong> ';
			echo esc_html__( 'Required dependencies are missing. Please install and activate Rank Math SEO plugin.', 'rank-math-api-manager' );
			echo '</p></div>';
		});
	}

	/**
	 * Display dependency notices in admin
	 *
	 * @since 1.0.7
	 */
	public function display_dependency_notices() {
		$status = $this->get_dependency_status();
		
		if ( ! $status['dependencies_met'] ) {
			echo '<div class="notice notice-error">';
			echo '<p><strong>' . esc_html__( 'Rank Math API Manager - Dependency Issues', 'rank-math-api-manager' ) . '</strong></p>';
			
			// Show missing plugins
			if ( ! empty( $status['missing_plugins'] ) ) {
				echo '<p>' . esc_html__( 'The following required plugins are missing or inactive:', 'rank-math-api-manager' ) . '</p>';
				echo '<ul>';
				
				foreach ( $status['missing_plugins'] as $plugin ) {
					echo '<li>';
					echo '<strong>' . esc_html( $plugin['name'] ) . '</strong> - ';
					echo esc_html( $plugin['description'] );
					
					if ( $this->is_plugin_installed( $plugin['file'] ) ) {
						echo ' <a href="' . esc_url( admin_url( 'plugins.php' ) ) . '">' . esc_html__( 'Activate Plugin', 'rank-math-api-manager' ) . '</a>';
					} else {
						echo ' <a href="' . esc_url( $plugin['url'] ) . '" target="_blank">' . esc_html__( 'Install Plugin', 'rank-math-api-manager' ) . '</a>';
					}
					
					echo '</li>';
				}
				
				echo '</ul>';
			}
			
			// Show configuration issues
			if ( ! empty( $status['configuration_issues'] ) ) {
				echo '<p>' . esc_html__( 'Configuration issues detected:', 'rank-math-api-manager' ) . '</p>';
				echo '<ul>';
				
				foreach ( $status['configuration_issues'] as $issue ) {
					echo '<li>';
					echo '<strong>' . esc_html( $issue['plugin'] ) . '</strong>: ';
					echo esc_html( $issue['issue'] );
					
					// Show debug information if available
					if ( isset( $issue['debug'] ) && is_array( $issue['debug'] ) ) {
						echo '<br><small><strong>Debug Info:</strong> ';
						$debug_parts = array();
						foreach ( $issue['debug'] as $key => $value ) {
							$debug_parts[] = $key . ': ' . ( is_bool( $value ) ? ( $value ? 'Yes' : 'No' ) : $value );
						}
						echo esc_html( implode( ', ', $debug_parts ) );
						echo '</small>';
					}
					
					echo '</li>';
				}
				
				echo '</ul>';
			}
			
			// Show recommendations
			if ( ! empty( $status['recommendations'] ) ) {
				echo '<p><strong>' . esc_html__( 'Recommendations:', 'rank-math-api-manager' ) . '</strong></p>';
				echo '<ul>';
				
				foreach ( $status['recommendations'] as $recommendation ) {
					echo '<li>' . esc_html( $recommendation ) . '</li>';
				}
				
				echo '</ul>';
			}
			
			echo '<p>' . esc_html__( 'Rank Math API Manager functionality is currently disabled until all dependencies are met.', 'rank-math-api-manager' ) . '</p>';
			echo '</div>';
		}
	}

	/**
	 * Check if a plugin is installed (but not necessarily active)
	 *
	 * @since 1.0.7
	 * @param string $plugin_file Plugin file path
	 * @return bool True if plugin is installed
	 */
	private function is_plugin_installed( $plugin_file ) {
		$plugins = get_plugins();
		return isset( $plugins[ $plugin_file ] );
	}

	/**
	 * Check if Rank Math is properly configured
	 *
	 * @since 1.0.7
	 * @return bool True if Rank Math is configured
	 */
	private function is_rank_math_configured() {
		// Basic check: if Rank Math class exists and function is available, consider it configured
		// This is more lenient and should work for most Rank Math installations
		if ( class_exists( 'RankMath' ) && function_exists( 'rank_math' ) ) {
			return true;
		}
		
		// Fallback: check if Rank Math meta fields are registered
		// This indicates Rank Math has been initialized
		global $wp_meta_keys;
		if ( isset( $wp_meta_keys ) && is_array( $wp_meta_keys ) ) {
			foreach ( $wp_meta_keys as $post_type => $meta_keys ) {
				if ( isset( $meta_keys['rank_math_title'] ) || isset( $meta_keys['rank_math_description'] ) ) {
					return true;
				}
			}
		}
		
		return false;
	}

	/**
	 * Get detailed dependency status
	 *
	 * @since 1.0.7
	 * @return array Array with dependency status details
	 */
	public function get_dependency_status() {
		$status = array(
			'dependencies_met' => false,
			'missing_plugins' => array(),
			'configuration_issues' => array(),
			'recommendations' => array(),
			'debug_info' => array()
		);
		
		$dependencies = $this->get_required_plugins();
		
		foreach ( $dependencies as $plugin ) {
			if ( ! $this->is_plugin_active( $plugin['file'] ) ) {
				$status['missing_plugins'][] = $plugin;
			}
		}
		
		// Check Rank Math configuration with detailed debugging
		if ( $this->is_plugin_active( 'seo-by-rank-math/rank-math.php' ) ) {
			$rank_math_debug = $this->get_rank_math_debug_info();
			$status['debug_info']['rank_math'] = $rank_math_debug;
			
			if ( ! $this->is_rank_math_configured() ) {
				$status['configuration_issues'][] = array(
					'plugin' => 'Rank Math SEO',
					'issue' => 'Plugin is active but not properly configured',
					'debug' => $rank_math_debug
				);
			}
		}
		
		$status['dependencies_met'] = empty( $status['missing_plugins'] ) && empty( $status['configuration_issues'] );
		
		// Add recommendations
		if ( ! empty( $status['missing_plugins'] ) ) {
			$status['recommendations'][] = 'Install and activate all required plugins';
		}
		
		if ( ! empty( $status['configuration_issues'] ) ) {
			$status['recommendations'][] = 'Configure Rank Math SEO plugin properly';
		}
		
		return $status;
	}

	/**
	 * Get detailed debug information about Rank Math
	 *
	 * @since 1.0.7
	 * @return array Debug information
	 */
	private function get_rank_math_debug_info() {
		$debug = array();
		
		// Check if RankMath class exists
		$debug['class_exists'] = class_exists( 'RankMath' );
		
		// Check if rank_math function exists
		$debug['function_exists'] = function_exists( 'rank_math' );
		
		// Try to get Rank Math instance
		if ( $debug['function_exists'] ) {
			try {
				$rank_math = rank_math();
				$debug['instance_created'] = is_object( $rank_math );
				$debug['instance_type'] = get_class( $rank_math );
				
				if ( is_object( $rank_math ) ) {
					$debug['has_get_settings'] = method_exists( $rank_math, 'get_settings' );
					$debug['has_get_helper'] = method_exists( $rank_math, 'get_helper' );
					$debug['has_get_admin'] = method_exists( $rank_math, 'get_admin' );
				}
			} catch ( Exception $e ) {
				$debug['exception'] = $e->getMessage();
			}
		}
		
		// Check if Rank Math is in the global scope
		global $rank_math;
		$debug['global_exists'] = isset( $rank_math );
		
		return $debug;
	}

	/**
	 * Handle plugin activation
	 *
	 * @since 1.0.7
	 * @param string $plugin_file Activated plugin file
	 */
	public function on_plugin_activated( $plugin_file ) {
		// Check if the activated plugin is one of our dependencies
		$dependencies = $this->get_required_plugins();
		$is_dependency = false;
		
		foreach ( $dependencies as $dependency ) {
			if ( $dependency['file'] === $plugin_file ) {
				$is_dependency = true;
				break;
			}
		}
		
		if ( $is_dependency ) {
			// Re-check dependencies after a short delay
			add_action( 'admin_init', function() {
				$this->check_dependencies();
			});
			
			// Show success notice
			add_action( 'admin_notices', function() {
				echo '<div class="notice notice-success is-dismissible">';
				echo '<p><strong>' . esc_html__( 'Rank Math API Manager', 'rank-math-api-manager' ) . '</strong>: ';
				echo esc_html__( 'Dependencies are now met! Plugin functionality is enabled.', 'rank-math-api-manager' );
				echo '</p>';
				echo '</div>';
			});
		}
	}

	/**
	 * Handle plugin deactivation
	 *
	 * @since 1.0.7
	 * @param string $plugin_file Deactivated plugin file
	 */
	public function on_plugin_deactivated( $plugin_file ) {
		// Check if the deactivated plugin is one of our dependencies
		$dependencies = $this->get_required_plugins();
		$is_dependency = false;
		
		foreach ( $dependencies as $dependency ) {
			if ( $dependency['file'] === $plugin_file ) {
				$is_dependency = true;
				break;
			}
		}
		
		if ( $is_dependency ) {
			// Re-check dependencies
			$this->check_dependencies();
			
			// Show warning notice
			add_action( 'admin_notices', function() {
				echo '<div class="notice notice-warning is-dismissible">';
				echo '<p><strong>' . esc_html__( 'Rank Math API Manager', 'rank-math-api-manager' ) . '</strong>: ';
				echo esc_html__( 'A required dependency has been deactivated. Plugin functionality is now disabled.', 'rank-math-api-manager' );
				echo '</p>';
				echo '</div>';
			});
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
			'nonce' => wp_create_nonce('rank_math_api_update_nonce')
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
	 * Log debug messages
	 *
	 * @since 1.0.7
	 * @param string $message Debug message to log
	 */
	private function log_debug( $message ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Rank Math API Manager: ' . $message );
		}
	}

	/**
	 * Check for plugin updates from GitHub
	 *
	 * @since 1.0.7
	 * @param object $transient WordPress update transient
	 * @return object Modified transient
	 */
	public function check_for_update( $transient ) {
		$this->log_debug( 'check_for_update called' );
		
		// Ensure we have a valid transient object
		if ( empty( $transient->checked ) ) {
			$this->log_debug( 'Transient checked is empty, returning early' );
			return $transient;
		}

		// Get plugin basename
		$plugin_slug = plugin_basename( RANK_MATH_API_PLUGIN_FILE );
		
		// Only check if our plugin is in the checked list
		if ( ! isset( $transient->checked[ $plugin_slug ] ) ) {
			$this->log_debug( 'Plugin not in checked list: ' . $plugin_slug );
			return $transient;
		}
		
		$this->log_debug( 'Plugin found in checked list, proceeding with update check' );

		// Get current version
		$current_version = $this->plugin_data['Version'];

		// Check for cached release data first
		$cache_key = 'rank_math_api_github_release';
		$release_data = get_transient( $cache_key );

		if ( false === $release_data ) {
			$this->log_debug( 'No cached release data, fetching from GitHub API' );
			// Get latest release from GitHub with rate limiting
			$release_data = $this->get_latest_github_release();
			
			if ( is_wp_error( $release_data ) ) {
				$this->log_debug( 'GitHub API error: ' . $release_data->get_error_message() );
				return $transient;
			}
			$this->log_debug( 'Successfully fetched release data from GitHub' );
		} else {
			$this->log_debug( 'Using cached release data' );

			// Cache the release data for 1 hour
			set_transient( $cache_key, $release_data, 3600 );
		}

		if ( ! $release_data || ! isset( $release_data['version'] ) ) {
			$this->log_debug( 'Invalid release data or missing version info' );
			return $transient;
		}

		$this->log_debug( 'Comparing versions: Current=' . $current_version . ', Remote=' . $release_data['version'] );

		// Compare versions
		if ( version_compare( $release_data['version'], $current_version, '>' ) ) {
			$this->log_debug( 'Update available! Adding to transient' );
			// Update available - add to response
			$plugin_data = (object) array(
				'slug'         => dirname( $plugin_slug ),
				'plugin'       => $plugin_slug,
				'new_version'  => $release_data['version'],
				'url'          => $this->plugin_data['PluginURI'],
				'package'      => $release_data['download_url'],
				'icons'        => array(),
				'banners'      => array(),
				'banners_rtl'  => array(),
				'tested'       => '6.4',
				'requires_php' => '7.4',
			);

			$transient->response[ $plugin_slug ] = $plugin_data;
			$this->log_debug( 'Successfully added update to WordPress transient' );
		} else {
			$this->log_debug( 'No update needed - version is current' );
		}

		return $transient;
	}

	/**
	 * Get latest release information from GitHub
	 *
	 * @since 1.0.7
	 * @return array|WP_Error Release data or error
	 */
	private function get_latest_github_release() {
		$this->log_debug( 'Starting GitHub API request' );
		
		// Rate limiting check
		$last_check = get_option( 'rank_math_api_last_github_check', 0 );
		$check_interval = 300; // 5 minutes minimum between checks

		if ( time() - $last_check < $check_interval ) {
			$this->log_debug( 'Rate limited - too many recent requests' );
			return new WP_Error( 'rate_limited', 'Rate limited: too many requests' );
		}

		// Prepare headers with optional authentication
		$headers = array(
			'Accept'     => 'application/vnd.github.v3+json',
			'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' )
		);

		// Add authentication header if token is available
		if ( $this->github_token ) {
			$headers['Authorization'] = 'token ' . $this->github_token;
			$this->log_debug( 'Using authenticated GitHub API request (5000/hour limit)' );
		} else {
			$this->log_debug( 'Using unauthenticated GitHub API request (60/hour limit)' );
		}

		// Make API request
		$response = wp_remote_get( $this->github_repo['api_url'], array(
			'timeout' => 15,
			'headers' => $headers
		) );

		// Update last check time
		update_option( 'rank_math_api_last_github_check', time() );

		if ( is_wp_error( $response ) ) {
			$this->log_debug( 'GitHub API request failed: ' . $response->get_error_message() );
			return new WP_Error( 'api_error', 'GitHub API request failed: ' . $response->get_error_message() );
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$this->log_debug( 'GitHub API response code: ' . $response_code );
		
		if ( 200 !== $response_code ) {
			$this->log_debug( 'GitHub API error - status: ' . $response_code );
			return new WP_Error( 'api_error', 'GitHub API returned status: ' . $response_code );
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! $data || ! isset( $data['tag_name'] ) ) {
			$this->log_debug( 'Invalid GitHub API response - no tag_name found' );
			return new WP_Error( 'invalid_response', 'Invalid GitHub API response' );
		}

		// Parse version from tag (remove 'v' prefix if present)
		$version = ltrim( $data['tag_name'], 'v' );
		$this->log_debug( 'Found GitHub release version: ' . $version );

		// Look for custom ZIP asset first
		$download_url = null;
		$assets_count = isset( $data['assets'] ) ? count( $data['assets'] ) : 0;
		$this->log_debug( 'Release has ' . $assets_count . ' assets' );
		
		if ( isset( $data['assets'] ) && is_array( $data['assets'] ) ) {
			foreach ( $data['assets'] as $asset ) {
				$this->log_debug( 'Found asset: ' . $asset['name'] );
				if ( 'rank-math-api-manager.zip' === $asset['name'] ) {
					$download_url = $asset['browser_download_url'];
					$this->log_debug( 'Using custom ZIP asset: ' . $download_url );
					break;
				}
			}
		}

		// Fallback to zipball_url if no custom asset found
		if ( ! $download_url && isset( $data['zipball_url'] ) ) {
			$download_url = $data['zipball_url'];
			$this->log_debug( 'No custom ZIP found, using zipball: ' . $download_url );
		}

		if ( ! $download_url ) {
			$this->log_debug( 'ERROR: No download URL found in release' );
			return new WP_Error( 'no_download', 'No download URL found in release' );
		}

		return array(
			'version'      => $version,
			'url'          => $data['html_url'],
			'download_url' => $download_url,
			'published_at' => $data['published_at'],
			'description'  => isset( $data['body'] ) ? $data['body'] : '',
		);
	}

	/**
	 * Provide plugin information for the "View Details" modal
	 *
	 * @since 1.0.7
	 * @param object $res    Plugin information result
	 * @param string $action Action being performed
	 * @param object $args   Additional arguments
	 * @return object|false Modified result or false
	 */
	public function plugin_info( $res, $action, $args ) {
		// Only handle plugin_information requests for our plugin
		if ( 'plugin_information' !== $action || 'rank-math-api-manager' !== $args->slug ) {
			return false;
		}

		// Get cached release data
		$cache_key = 'rank_math_api_github_release';
		$release_data = get_transient( $cache_key );

		if ( false === $release_data ) {
			$release_data = $this->get_latest_github_release();
			
			if ( is_wp_error( $release_data ) ) {
				return false;
			}

			set_transient( $cache_key, $release_data, 3600 );
		}

		if ( ! $release_data ) {
			return false;
		}

		// Format changelog
		$changelog = '';
		if ( ! empty( $release_data['description'] ) ) {
			$changelog = wp_kses_post( nl2br( $release_data['description'] ) );
		}

		// Return plugin information object
		return (object) array(
			'name'           => $this->plugin_data['Name'],
			'slug'           => 'rank-math-api-manager',
			'version'        => $release_data['version'],
			'author'         => '<a href="' . esc_url( $this->plugin_data['AuthorURI'] ) . '">' . $this->plugin_data['AuthorName'] . '</a>',
			'homepage'       => $this->plugin_data['PluginURI'],
			'requires'       => '5.0',
			'tested'         => '6.4',
			'requires_php'   => '7.4',
			'last_updated'   => $release_data['published_at'],
			'download_link'  => $release_data['download_url'],
			'sections'       => array(
				'description' => '<p>' . esc_html( $this->plugin_data['Description'] ) . '</p>',
				'changelog'   => $changelog,
			),
		);
	}
}

// Initialize the plugin
function rank_math_api_manager_init() {
	return Rank_Math_API_Manager_Extended::get_instance();
}

// Start the plugin
add_action('plugins_loaded', 'rank_math_api_manager_init');

/**
 * Plugin activation function
 * 
 * Sets up necessary directories and options when the plugin is activated
 * 
 * @since 1.0.0
 */
function rank_math_api_manager_activate() {
	// Create necessary directories
	$upload_dir = wp_upload_dir();
	$plugin_dir = $upload_dir['basedir'] . '/rank-math-api-manager';
	
	if (!file_exists($plugin_dir)) {
		wp_mkdir_p($plugin_dir);
	}
	
	// Add activation timestamp
	update_option('rank_math_api_activated', current_time('mysql'));
	
	// Check dependencies on activation
	$plugin_instance = Rank_Math_API_Manager_Extended::get_instance();
	if ( method_exists( $plugin_instance, 'check_dependencies' ) ) {
		$plugin_instance->check_dependencies();
	}
	
	// Show admin notice if dependencies are missing
	if ( method_exists( $plugin_instance, 'are_dependencies_met' ) && ! $plugin_instance->are_dependencies_met() ) {
		add_action( 'admin_notices', function() {
			echo '<div class="notice notice-warning is-dismissible">';
			echo '<p><strong>' . esc_html__( 'Rank Math API Manager', 'rank-math-api-manager' ) . '</strong>: ';
			echo esc_html__( 'Plugin activated but required dependencies are missing. Please install and activate Rank Math SEO plugin for full functionality.', 'rank-math-api-manager' );
			echo '</p>';
			echo '</div>';
		});
	}
}

/**
 * Plugin deactivation function
 * 
 * Cleans up scheduled events and caches when the plugin is deactivated
 * 
 * @since 1.0.0
 */
function rank_math_api_manager_deactivate() {
	// Clear scheduled events
	wp_clear_scheduled_hook('rank_math_api_update_check');
	
	// Clear update-related caches
	delete_transient('rank_math_api_github_release');
	delete_option('rank_math_api_last_github_check');
}

// Activation hook
register_activation_hook(__FILE__, 'rank_math_api_manager_activate');

// Deactivation hook
register_deactivation_hook(__FILE__, 'rank_math_api_manager_deactivate');

/**
 * Plugin uninstall function
 * 
 * Removes all plugin data when the plugin is uninstalled
 * 
 * @since 1.0.0
 */
function rank_math_api_manager_uninstall() {
	// Remove all plugin options
	delete_option('rank_math_api_activated');
	delete_option('rank_math_api_dependencies_status');
	delete_option('rank_math_api_last_github_check');
	
	// Remove transients
	delete_transient('rank_math_api_github_release');
	
	// Clear any scheduled events
	wp_clear_scheduled_hook('rank_math_api_update_check');
}

// Uninstall hook
register_uninstall_hook(__FILE__, 'rank_math_api_manager_uninstall');
