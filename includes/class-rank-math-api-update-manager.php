<?php
/**
 * Rank Math API Update Manager
 *
 * Handles automatic updates from GitHub releases
 *
 * @package Rank_Math_API_Manager
 * @since 1.0.7
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Rank Math API Update Manager Class
 *
 * @since 1.0.7
 */
class Rank_Math_API_Update_Manager {

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
     * Plugin information
     *
     * @var array
     */
    private $plugin_info = array();
    
    /**
     * Initialize plugin info
     */
    private function init_plugin_info() {
        // Get the actual plugin file path using WordPress core functions
        // This ensures we get the exact same path WordPress uses internally
        $main_plugin_file = dirname(__DIR__) . '/rank-math-api-manager.php';
        
        // Get plugin data to ensure we have the correct information
        if (!function_exists('get_plugin_data')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        $plugin_data = get_plugin_data($main_plugin_file);
        $plugin_file = plugin_basename($main_plugin_file);
        
        $this->plugin_info = array(
            'slug' => dirname($plugin_file),
            'plugin_file' => $plugin_file,
            'current_version' => $plugin_data['Version'],
            'plugin_uri' => $plugin_data['PluginURI'],
            'name' => $plugin_data['Name'],
            'main_file' => $main_plugin_file
        );
    }

    /**
     * Constructor
     */
    public function __construct() {
        $this->init_plugin_info();
        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Check for updates
        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_for_updates'));
        add_filter('plugins_api', array($this, 'plugin_info'), 10, 3);
        
        // Auto-update support
        add_filter('auto_update_plugin', array($this, 'auto_update_plugin'), 10, 2);
        
        // Admin hooks
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'handle_admin_actions'));
        add_action('admin_notices', array($this, 'admin_notices'));
        add_action('admin_notices', array($this, 'debug_plugin_info'));
        // AJAX handlers
        add_action('wp_ajax_rank_math_api_check_updates', array($this, 'ajax_check_updates'));
        add_action('wp_ajax_rank_math_api_force_update', array($this, 'ajax_force_update'));
        
        // Update hooks
        add_action('upgrader_process_complete', array($this, 'upgrade_complete'), 10, 2);
    }

    /**
     * Check for updates from GitHub
     *
     * @param object $transient WordPress update transient
     * @return object Modified transient
     */
    public function check_for_updates($transient) {
        // CRITICAL: Always ensure our plugin is registered in the transient
        // This is what makes the auto-update toggle appear
        $this->ensure_plugin_in_transient($transient);

        // Only check GitHub if we're in admin or during cron
        if (!is_admin() && !wp_doing_cron()) {
            return $transient;
        }

        // Check if we should check for updates (rate limiting)
        $last_check = get_option('rank_math_api_last_update_check', 0);
        $check_interval = apply_filters('rank_math_api_update_check_interval', 3600); // 1 hour

        if (time() - $last_check < $check_interval) {
            return $transient;
        }

        // Check for actual GitHub updates
        $this->check_github_updates($transient);

        return $transient;
    }

    /**
     * CRITICAL: Ensure plugin is always registered in transient for auto-update support
     *
     * @param object $transient Update transient
     */
    private function ensure_plugin_in_transient($transient) {
        // Ensure transient has the required properties
        if (!isset($transient->response)) {
            $transient->response = array();
        }
        if (!isset($transient->no_update)) {
            $transient->no_update = array();
        }

        // If plugin is not in either array, add it to no_update by default
        // This ensures WordPress always knows about our plugin for auto-updates
        if (!isset($transient->response[$this->plugin_info['plugin_file']]) && 
            !isset($transient->no_update[$this->plugin_info['plugin_file']])) {
            
            $transient->no_update[$this->plugin_info['plugin_file']] = $this->get_plugin_update_object($this->plugin_info['current_version']);
        }
    }

    /**
     * Check for actual updates from GitHub
     *
     * @param object $transient Update transient
     */
    private function check_github_updates($transient) {
        // Get latest release from GitHub
        $latest_release = $this->get_latest_release();

        if (!$latest_release || is_wp_error($latest_release)) {
            update_option('rank_math_api_last_update_check', time());
            return;
        }

        // Remove from both arrays first
        unset($transient->response[$this->plugin_info['plugin_file']]);
        unset($transient->no_update[$this->plugin_info['plugin_file']]);

        // Compare versions and add to appropriate array
        if (version_compare($latest_release['version'], $this->plugin_info['current_version'], '>')) {
            // Update available
            $update_object = $this->get_plugin_update_object($latest_release['version'], $latest_release['download_url']);
            $update_object->sections = array(
                'description' => $latest_release['description'],
                'changelog' => $latest_release['changelog']
            );
            
            $transient->response[$this->plugin_info['plugin_file']] = $update_object;

            // Store update information
            update_option('rank_math_api_latest_release', $latest_release);
        } else {
            // No update available
            $transient->no_update[$this->plugin_info['plugin_file']] = $this->get_plugin_update_object($this->plugin_info['current_version']);
        }

        update_option('rank_math_api_last_update_check', time());
    }

    /**
     * Get standardized plugin update object that matches WordPress.org format
     *
     * @param string $version Plugin version
     * @param string $package Download URL (optional)
     * @return object Plugin update object
     */
    private function get_plugin_update_object($version, $package = '') {
        return (object) array(
            'id' => $this->plugin_info['plugin_file'],
            'slug' => $this->plugin_info['slug'],
            'plugin' => $this->plugin_info['plugin_file'],
            'new_version' => $version,
            'url' => $this->plugin_info['plugin_uri'],
            'package' => $package,
            'icons' => array(),
            'banners' => array(),
            'banners_rtl' => array(),
            'requires' => '5.0',
            'tested' => '6.8',
            'requires_php' => '7.4',
            'compatibility' => new stdClass(),
            'auto_update' => null, // Let WordPress handle this
        );
    }

    /**
     * Get latest release from GitHub
     *
     * @return array|WP_Error Release data or error
     */
    private function get_latest_release() {
        // Check cache first
        $cached = get_transient('rank_math_api_latest_release_cache');
        if ($cached !== false) {
            return $cached;
        }

        // Make API request
        $response = wp_remote_get($this->github_repo['api_url'], array(
            'timeout' => 15,
            'headers' => array(
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url')
            )
        ));

        if (is_wp_error($response)) {
            $this->log_error('Failed to fetch GitHub release: ' . $response->get_error_message());
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!$data || !isset($data['tag_name'])) {
            $this->log_error('Invalid GitHub API response: ' . $body);
            return new WP_Error('invalid_response', 'Invalid GitHub API response');
        }

        // Parse version from tag (remove 'v' prefix if present)
        $version = ltrim($data['tag_name'], 'v');

        // Get download URL from assets
        $download_url = '';
        if (isset($data['assets']) && is_array($data['assets'])) {
            foreach ($data['assets'] as $asset) {
                if (strpos($asset['name'], '.zip') !== false) {
                    $download_url = $asset['browser_download_url'];
                    break;
                }
            }
        }

        // If no zip asset found, create download URL from repository
        if (empty($download_url)) {
            $download_url = sprintf(
                'https://github.com/%s/%s/archive/refs/tags/%s.zip',
                $this->github_repo['owner'],
                $this->github_repo['repo'],
                $data['tag_name']
            );
        }

        $release_data = array(
            'version' => $version,
            'url' => $data['html_url'],
            'download_url' => $download_url,
            'published_at' => $data['published_at'],
            'description' => $this->parse_markdown_description($data['body']),
            'changelog' => $this->parse_changelog($data['body'])
        );

        // Cache for 1 hour
        set_transient('rank_math_api_latest_release_cache', $release_data, 3600);

        return $release_data;
    }

    /**
     * Parse markdown description
     *
     * @param string $body Release body
     * @return string Parsed description
     */
    private function parse_markdown_description($body) {
        // Simple markdown to HTML conversion
        $description = wp_kses_post($body);
        $description = preg_replace('/^#\s+(.+)$/m', '<h1>$1</h1>', $description);
        $description = preg_replace('/^##\s+(.+)$/m', '<h2>$1</h2>', $description);
        $description = preg_replace('/^###\s+(.+)$/m', '<h3>$1</h3>', $description);
        $description = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $description);
        $description = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $description);
        
        return $description;
    }

    /**
     * Parse changelog from release body
     *
     * @param string $body Release body
     * @return string Changelog
     */
    private function parse_changelog($body) {
        // Extract changelog section if it exists
        if (preg_match('/##\s*Changelog\s*\n(.*?)(?=\n##|$)/s', $body, $matches)) {
            return $this->parse_markdown_description($matches[1]);
        }
        
        // If no changelog section, return the full body
        return $this->parse_markdown_description($body);
    }

    /**
     * Plugin info for WordPress update system
     *
     * @param object $result Plugin info result
     * @param string $action Action being performed
     * @param object $args Additional arguments
     * @return object Modified result
     */
    public function plugin_info($result, $action, $args) {
        if ($action !== 'plugin_information' || $args->slug !== $this->plugin_info['slug']) {
            return $result;
        }

        $latest_release = get_option('rank_math_api_latest_release');
        if (!$latest_release) {
            $latest_release = $this->get_latest_release();
        }

        if (is_wp_error($latest_release)) {
            return $result;
        }

        $result = new stdClass();
        $result->name = 'Rank Math API Manager';
        $result->slug = $this->plugin_info['slug'];
        $result->version = $latest_release['version'];
        $result->last_updated = $latest_release['published_at'];
        $result->requires = '5.0';
        $result->requires_php = '7.4';
        $result->tested = '6.4';
        $result->download_link = $latest_release['download_url'];
        $result->sections = array(
            'description' => $latest_release['description'],
            'changelog' => $latest_release['changelog']
        );

        return $result;
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'options-general.php',
            'Rank Math API Updates',
            'Rank Math API Updates',
            'manage_options',
            'rank-math-api-updates',
            array($this, 'admin_page')
        );
    }

    /**
     * Admin page content
     */
    public function admin_page() {
        // Verify nonce for security
        if (isset($_POST['rank_math_api_update_nonce']) && 
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['rank_math_api_update_nonce'])), 'rank_math_api_update_action')) {
            wp_die('Security check failed');
        }

        $latest_release = get_option('rank_math_api_latest_release');
        $current_version = $this->plugin_info['current_version'];
        $last_check = get_option('rank_math_api_last_update_check', 0);
        $update_available = $latest_release && version_compare($latest_release['version'], $current_version, '>');

        ?>
        <div class="wrap">
            <h1>Rank Math API Manager - Updates</h1>
            
            <div class="card">
                <h2>Current Status</h2>
                <p><strong>Current Version:</strong> <?php echo esc_html($current_version); ?></p>
                <p><strong>Last Check:</strong> <?php echo $last_check ? esc_html(gmdate('Y-m-d H:i:s', $last_check)) : 'Never'; ?></p>
                
                <?php if ($update_available): ?>
                    <div class="notice notice-success">
                        <p><strong>Update Available!</strong> Version <?php echo esc_html($latest_release['version']); ?> is available.</p>
                        <p><a href="<?php echo esc_url(admin_url('update-core.php')); ?>" class="button button-primary">Update Now</a></p>
                    </div>
                <?php else: ?>
                    <div class="notice notice-info">
                        <p>You are running the latest version.</p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="card">
                <h2>Manual Update Check</h2>
                <form method="post" action="">
                    <?php wp_nonce_field('rank_math_api_update_action', 'rank_math_api_update_nonce'); ?>
                    <input type="hidden" name="action" value="check_updates">
                    <p><input type="submit" class="button button-secondary" value="Check for Updates"></p>
                </form>
            </div>

            <?php if ($latest_release): ?>
            <div class="card">
                <h2>Latest Release Information</h2>
                <p><strong>Version:</strong> <?php echo esc_html($latest_release['version']); ?></p>
                <p><strong>Published:</strong> <?php echo esc_html(gmdate('Y-m-d H:i:s', strtotime($latest_release['published_at']))); ?></p>
                <p><strong>Download:</strong> <a href="<?php echo esc_url($latest_release['url']); ?>" target="_blank">View on GitHub</a></p>
                
                <?php if (!empty($latest_release['description'])): ?>
                <h3>Description</h3>
                <div class="description">
                    <?php echo wp_kses_post($latest_release['description']); ?>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($latest_release['changelog'])): ?>
                <h3>Changelog</h3>
                <div class="changelog">
                    <?php echo wp_kses_post($latest_release['changelog']); ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <style>
        .card {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            padding: 20px;
            margin: 20px 0;
        }
        .description, .changelog {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 3px;
            margin-top: 10px;
        }
        </style>
        <?php
    }

    /**
     * Handle admin actions
     */
    public function handle_admin_actions() {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (isset($_POST['action']) && sanitize_text_field(wp_unslash($_POST['action'])) === 'check_updates') {
            // Verify nonce for security
            if (!isset($_POST['rank_math_api_update_nonce']) || 
                !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['rank_math_api_update_nonce'])), 'rank_math_api_update_action')) {
                wp_die('Security check failed');
            }
            
            // Clear cache and force update check
            delete_transient('rank_math_api_latest_release_cache');
            delete_option('rank_math_api_last_update_check');
            
            // Trigger update check
            $this->check_for_updates(get_site_transient('update_plugins'));
            
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success"><p>Update check completed successfully.</p></div>';
            });
        }
    }

    /**
     * AJAX handler for checking updates
     */
    public function ajax_check_updates() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'rank_math_api_update_nonce')) {
            wp_die('Security check failed');
        }

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        // Clear cache and check for updates
        delete_transient('rank_math_api_latest_release_cache');
        delete_option('rank_math_api_last_update_check');
        
        $latest_release = $this->get_latest_release();
        
        if (is_wp_error($latest_release)) {
            wp_send_json_error(array(
                'message' => 'Failed to check for updates: ' . $latest_release->get_error_message()
            ));
        }

        $update_available = version_compare($latest_release['version'], $this->plugin_info['current_version'], '>');
        
        wp_send_json_success(array(
            'update_available' => $update_available,
            'current_version' => $this->plugin_info['current_version'],
            'latest_version' => $latest_release['version'],
            'release_info' => $latest_release
        ));
    }

    /**
     * AJAX handler for forcing update
     */
    public function ajax_force_update() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'rank_math_api_update_nonce')) {
            wp_die('Security check failed');
        }

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        // Trigger WordPress update process
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/class-plugin-upgrader.php';

        $upgrader = new Plugin_Upgrader(new WP_Ajax_Upgrader_Skin());
        $result = $upgrader->upgrade($this->plugin_info['plugin_file']);

        if (is_wp_error($result)) {
            wp_send_json_error(array(
                'message' => 'Update failed: ' . $result->get_error_message()
            ));
        }

        wp_send_json_success(array(
            'message' => 'Plugin updated successfully!'
        ));
    }

    /**
     * Admin notices
     */
    public function admin_notices() {
        // Only show to administrators
        if (!current_user_can('manage_options')) {
            return;
        }

        $latest_release = get_option('rank_math_api_latest_release');
        if (!$latest_release) {
            return;
        }

        $update_available = version_compare($latest_release['version'], $this->plugin_info['current_version'], '>');
        
        if ($update_available) {
            $message = sprintf(
                'A new version of Rank Math API Manager (%s) is available. <a href="%s">Update now</a> or <a href="%s">view details</a>.',
                esc_html($latest_release['version']),
                admin_url('update-core.php'),
                admin_url('options-general.php?page=rank-math-api-updates')
            );
            
            echo '<div class="notice notice-warning is-dismissible"><p>' . wp_kses_post($message) . '</p></div>';
        }
    }

    /**
     * Handle upgrade completion
     *
     * @param WP_Upgrader $upgrader Upgrader instance
     * @param array $hook_extra Extra hook data
     */
    public function upgrade_complete($upgrader, $hook_extra) {
        if ($hook_extra['type'] !== 'plugin' || 
            !isset($hook_extra['plugins']) || 
            !in_array($this->plugin_info['plugin_file'], $hook_extra['plugins'])) {
            return;
        }

        // Clear caches after successful update
        delete_transient('rank_math_api_latest_release_cache');
        delete_option('rank_math_api_last_update_check');
        delete_option('rank_math_api_latest_release');

        // Log successful update
        $this->log_info('Plugin updated successfully to version ' . $this->plugin_info['current_version']);

        // Send notification email to admin
        $this->send_update_notification();
    }

    /**
     * Send update notification email
     */
    private function send_update_notification() {
        $admin_email = get_option('admin_email');
        $site_name = get_bloginfo('name');
        
        $subject = sprintf('[%s] Rank Math API Manager Updated', $site_name);
        $message = sprintf(
            'The Rank Math API Manager plugin has been successfully updated on %s.',
            $site_name
        );

        wp_mail($admin_email, $subject, $message);
    }

    /**
     * Log error message
     *
     * @param string $message Error message
     */
    private function log_error($message) {
        // Note: Direct logging removed for production
        
        // Store in WordPress options for admin viewing
        $logs = get_option('rank_math_api_update_logs', array());
        $logs[] = array(
            'timestamp' => current_time('mysql'),
            'level' => 'error',
            'message' => $message
        );
        
        // Keep only last 100 log entries
        if (count($logs) > 100) {
            $logs = array_slice($logs, -100);
        }
        
        update_option('rank_math_api_update_logs', $logs);
    }

    /**
     * Log info message
     *
     * @param string $message Info message
     */
    private function log_info($message) {
        // Note: Direct logging removed for production
        
        // Store in WordPress options for admin viewing
        $logs = get_option('rank_math_api_update_logs', array());
        $logs[] = array(
            'timestamp' => current_time('mysql'),
            'level' => 'info',
            'message' => $message
        );
        
        // Keep only last 100 log entries
        if (count($logs) > 100) {
            $logs = array_slice($logs, -100);
        }
        
        update_option('rank_math_api_update_logs', $logs);
    }

    /**
     * Enable auto-updates for this plugin
     *
     * @param bool $update Whether to auto-update
     * @param object $item Plugin data
     * @return bool Whether to auto-update
     */
    public function auto_update_plugin($update, $item) {
        // Check if this is our plugin
        if (isset($item->plugin) && $item->plugin === $this->plugin_info['plugin_file']) {
            // Check if auto-updates are enabled for this plugin in WordPress settings
            $auto_updates = get_site_option('auto_update_plugins', array());
            return in_array($this->plugin_info['plugin_file'], $auto_updates, true);
        }
        
        return $update;
    }

    /**
     * Debug method to check plugin registration
     * Add ?rank_math_debug=1 to any admin page to see debug info
     */
    public function debug_plugin_info() {
        if (isset($_GET['rank_math_debug']) && current_user_can('manage_options')) {
            echo '<div style="background: #fff; border: 1px solid #ccc; padding: 15px; margin: 10px; font-family: monospace;">';
            echo '<h3 style="margin-top: 0; color: #0073aa;">🔧 Rank Math API Manager Debug Info</h3>';
            
            echo '<h4>Plugin Information:</h4>';
            echo '<p><strong>Plugin File:</strong> ' . esc_html($this->plugin_info['plugin_file']) . '</p>';
            echo '<p><strong>Plugin Slug:</strong> ' . esc_html($this->plugin_info['slug']) . '</p>';
            echo '<p><strong>Current Version:</strong> ' . esc_html($this->plugin_info['current_version']) . '</p>';
            echo '<p><strong>Plugin Name:</strong> ' . esc_html($this->plugin_info['name']) . '</p>';
            echo '<p><strong>Plugin URI:</strong> ' . esc_html($this->plugin_info['plugin_uri']) . '</p>';
            
            echo '<h4>Update Transient Status:</h4>';
            $transient = get_site_transient('update_plugins');
            if ($transient && isset($transient->no_update[$this->plugin_info['plugin_file']])) {
                echo '<p style="color: green;"><strong>✓ Status:</strong> Found in no_update array (Good for auto-updates)</p>';
            } elseif ($transient && isset($transient->response[$this->plugin_info['plugin_file']])) {
                echo '<p style="color: blue;"><strong>✓ Status:</strong> Found in response array (Update available)</p>';
            } else {
                echo '<p style="color: red;"><strong>✗ Status:</strong> NOT found in transient (Problem!)</p>';
            }
            
            echo '<h4>Auto-Update Status:</h4>';
            $auto_updates = get_site_option('auto_update_plugins', array());
            if (in_array($this->plugin_info['plugin_file'], $auto_updates, true)) {
                echo '<p style="color: green;"><strong>✓ Auto-updates:</strong> Enabled</p>';
            } else {
                echo '<p><strong>Auto-updates:</strong> Disabled (This is normal - user can enable manually)</p>';
            }
            
            echo '<h4>WordPress Plugin Detection:</h4>';
            if (!function_exists('get_plugins')) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }
            $all_plugins = get_plugins();
            if (isset($all_plugins[$this->plugin_info['plugin_file']])) {
                echo '<p style="color: green;"><strong>✓ WordPress Detection:</strong> Plugin found in get_plugins()</p>';
            } else {
                echo '<p style="color: red;"><strong>✗ WordPress Detection:</strong> Plugin NOT found in get_plugins() (Problem!)</p>';
            }
            
            echo '<h4>Force Update Check:</h4>';
            echo '<p><a href="' . esc_url(add_query_arg('force_update_check', '1')) . '" style="color: #0073aa;">🔄 Force Update Check Now</a></p>';
            
            echo '</div>';
            
            // Handle force update check
            if (isset($_GET['force_update_check'])) {
                delete_site_transient('update_plugins');
                echo '<div style="background: #d4edda; border: 1px solid #c3e6cb; padding: 10px; margin: 10px; color: #155724;">';
                echo '<strong>Update check forced!</strong> Refresh the page to see updated status.';
                echo '</div>';
            }
        }
    }
} 