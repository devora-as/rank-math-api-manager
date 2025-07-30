<?php
/**
 * Plugin Name: Rank Math API Manager
 * Plugin URI: https://devora.no/plugins/rankmath-api-manager
 * Description: A WordPress extension that manages the update of Rank Math metadata (SEO Title, SEO Description, Canonical URL, Focus Keyword) via the REST API for WordPress posts and WooCommerce products.
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
		// Check dependencies first
		add_action( 'plugins_loaded', [ $this, 'check_dependencies' ], 5 );
		
		// Monitor plugin activation/deactivation
		add_action( 'activated_plugin', [ $this, 'on_plugin_activated' ] );
		add_action( 'deactivated_plugin', [ $this, 'on_plugin_deactivated' ] );
		
		// Always initialize update manager (independent of other dependencies)
		add_action( 'plugins_loaded', [ $this, 'init_update_manager' ] );
		

		
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
		// Clear any cached data
		delete_transient( 'rank_math_api_latest_release_cache' );
		
		// Note: Dependency issue detected (logging removed for production)
		
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
	 * Initialize update manager
	 */
	public function init_update_manager() {
		// Load the update manager class file
		$update_manager_file = plugin_dir_path( __FILE__ ) . 'includes/class-rank-math-api-update-manager.php';
		
		if ( ! file_exists( $update_manager_file ) ) {
			return;
		}
		
		require_once $update_manager_file;
		
		// Check if class was loaded successfully
		if ( ! class_exists( 'Rank_Math_API_Update_Manager' ) ) {
			return;
		}
		
		// Initialize the update manager
		$this->update_manager = new Rank_Math_API_Update_Manager();
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
	
	// Clear caches
	delete_transient('rank_math_api_latest_release_cache');
	delete_option('rank_math_api_last_update_check');
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
	delete_option('rank_math_api_last_update_check');
	delete_option('rank_math_api_latest_release');
	delete_option('rank_math_api_update_logs');
	delete_option('rank_math_api_dependencies_status');
	
	// Remove any transients
	delete_transient('rank_math_api_latest_release_cache');
	
	// Clear any scheduled events
	wp_clear_scheduled_hook('rank_math_api_update_check');
}

// Uninstall hook
register_uninstall_hook(__FILE__, 'rank_math_api_manager_uninstall');
