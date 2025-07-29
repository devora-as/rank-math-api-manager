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
    private $plugin_info = array(
        'slug' => 'rank-math-api-manager',
        'plugin_file' => 'rank-math-api-manager/rank-math-api-manager.php',
        'current_version' => '1.0.6'
    );

    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Check for updates
        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_for_updates'));
        add_filter('plugins_api', array($this, 'plugin_info'), 10, 3);
        
        // Admin hooks
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'handle_admin_actions'));
        add_action('admin_notices', array($this, 'admin_notices'));
        
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
        // Only check if we're in admin or during cron
        if (!is_admin() && !wp_doing_cron()) {
            return $transient;
        }

        // Check if we should check for updates (rate limiting)
        $last_check = get_option('rank_math_api_last_update_check', 0);
        $check_interval = apply_filters('rank_math_api_update_check_interval', 3600); // 1 hour

        if (time() - $last_check < $check_interval) {
            return $transient;
        }

        // Get latest release from GitHub
        $latest_release = $this->get_latest_release();

        if (!$latest_release || is_wp_error($latest_release)) {
            update_option('rank_math_api_last_update_check', time());
            return $transient;
        }

        // Compare versions
        if (version_compare($latest_release['version'], $this->plugin_info['current_version'], '>')) {
            $transient->response[$this->plugin_info['plugin_file']] = (object) array(
                'slug' => $this->plugin_info['slug'],
                'new_version' => $latest_release['version'],
                'url' => $latest_release['url'],
                'package' => $latest_release['download_url'],
                'requires' => '5.0',
                'requires_php' => '7.4',
                'tested' => '6.4',
                'last_updated' => $latest_release['published_at'],
                'sections' => array(
                    'description' => $latest_release['description'],
                    'changelog' => $latest_release['changelog']
                )
            );

            // Store update information
            update_option('rank_math_api_latest_release', $latest_release);
        }

        update_option('rank_math_api_last_update_check', time());

        return $transient;
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
            !wp_verify_nonce($_POST['rank_math_api_update_nonce'], 'rank_math_api_update_action')) {
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
                <p><strong>Last Check:</strong> <?php echo $last_check ? esc_html(date('Y-m-d H:i:s', $last_check)) : 'Never'; ?></p>
                
                <?php if ($update_available): ?>
                    <div class="notice notice-success">
                        <p><strong>Update Available!</strong> Version <?php echo esc_html($latest_release['version']); ?> is available.</p>
                        <p><a href="<?php echo admin_url('update-core.php'); ?>" class="button button-primary">Update Now</a></p>
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
                <p><strong>Published:</strong> <?php echo esc_html(date('Y-m-d H:i:s', strtotime($latest_release['published_at']))); ?></p>
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

        if (isset($_POST['action']) && $_POST['action'] === 'check_updates') {
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
        if (!wp_verify_nonce($_POST['nonce'], 'rank_math_api_update_nonce')) {
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
        if (!wp_verify_nonce($_POST['nonce'], 'rank_math_api_update_nonce')) {
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
        error_log('Rank Math API Update Manager Error: ' . $message);
        
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
        error_log('Rank Math API Update Manager Info: ' . $message);
        
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
} 