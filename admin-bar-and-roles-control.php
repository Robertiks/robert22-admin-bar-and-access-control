<?php
/**
 * Plugin Name: Robert22 Admin Bar and Access Control
 * Plugin URI: https://github.com/Robertiks/robert22-admin-bar-and-access-control
 * Description: Control WordPress admin bar visibility and redirect users based on their roles with advanced role-based redirect configurations.
 * Version: 1.0.0
 * Author: Robertas
 * Author URI: https://github.com/Robertiks
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: robert22-admin-bar-and-access-control
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: true
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('RBTS_ABC_VERSION', '1.0.0');
define('RBTS_ABC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('RBTS_ABC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('RBTS_ABC_PLUGIN_FILE', __FILE__);

/**
 * Main plugin class
 */
class RBTS_Robert22_Admin_Bar_And_Access_Control {
    
    /**
     * Plugin option name
     *
     * @var string
     */
    private $option_name = 'rbts_robert22_admin_bar_access_control';
    
    /**
     * Plugin options
     *
     * @var array
     */
    private $options;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->options = get_option($this->option_name, array());
        
        // Admin hooks
        if (is_admin()) {
            add_action('admin_menu', array($this, 'add_admin_menu'));
            add_action('admin_init', array($this, 'admin_init'));
            add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        }
        
        // Frontend hooks
        add_action('wp_loaded', array($this, 'handle_admin_bar_control'));
        add_action('wp_login', array($this, 'handle_login_redirect'), 10, 2);
    }

    /**
     * Enqueue admin assets properly
     */
    public function enqueue_admin_assets($hook) {
        // Only load on our plugin's admin page
        if ($hook !== 'tools_page_rbts-robert22-admin-bar-and-access-control') {
            return;
        }

        // Enqueue jQuery (dependency)
        wp_enqueue_script('jquery');
        
        // Enqueue our admin script
        wp_enqueue_script(
            'rbts-admin-script',
            RBTS_ABC_PLUGIN_URL . 'assets/admin-script.js',
            array('jquery'),
            RBTS_ABC_VERSION,
            true
        );
        
        // Enqueue admin styles
        wp_enqueue_style(
            'rbts-admin-style',
            RBTS_ABC_PLUGIN_URL . 'assets/admin-style.css',
            array(),
            RBTS_ABC_VERSION
        );
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_management_page(
            __('Robert22 Admin Bar & Access Control', 'robert22-admin-bar-and-access-control'),
            __('Robert22 Admin Bar & Access Control', 'robert22-admin-bar-and-access-control'),
            'manage_options',
            'rbts-robert22-admin-bar-and-access-control',
            array($this, 'admin_page')
        );
    }
    
    /**
     * Initialize admin settings
     */
    public function admin_init() {
        register_setting(
            'rbts_robert22_admin_bar_access_control_group',
            $this->option_name,
            array($this, 'sanitize_options')
        );
        
        add_settings_section(
            'rbts_robert22_admin_bar_access_control_section',
            __('Robert22 Admin Bar and Access Control Settings', 'robert22-admin-bar-and-access-control'),
            array($this, 'section_callback'),
            'rbts_robert22_admin_bar_access_control'
        );
    }
    
    /**
     * Section callback
     */
    public function section_callback() {
        echo '<p>' . esc_html__('Configure admin bar visibility and user redirects based on roles.', 'robert22-admin-bar-and-access-control') . '</p>';
    }
    
    /**
     * Sanitize and validate options
     */
    public function sanitize_options($input) {
        $sanitized = array();
        
        // Sanitize admin bar control
        $sanitized['admin_bar_control'] = isset($input['admin_bar_control']) ? sanitize_text_field($input['admin_bar_control']) : 'show';
        
        // Sanitize restricted roles
        if (isset($input['restricted_roles']) && is_array($input['restricted_roles'])) {
            $sanitized['restricted_roles'] = array_map('sanitize_text_field', $input['restricted_roles']);
        } else {
            $sanitized['restricted_roles'] = array();
        }
        
        // Sanitize redirect type
        $sanitized['redirect_type'] = isset($input['redirect_type']) ? sanitize_text_field($input['redirect_type']) : 'default';
        
        // Sanitize custom path
        $sanitized['custom_path'] = isset($input['custom_path']) ? $this->validate_custom_path($input['custom_path']) : '';
        
        // Sanitize full URL
        $sanitized['full_url'] = isset($input['full_url']) ? esc_url_raw($input['full_url']) : '';
        
        // Sanitize confirmation message
        $sanitized['confirmation_message'] = isset($input['confirmation_message']) ? sanitize_textarea_field($input['confirmation_message']) : '';
        
        // Validate role conflicts
        $this->validate_role_conflicts($sanitized);
        
        return $sanitized;
    }
    
    /**
     * Validate role conflicts
     */
    private function validate_role_conflicts($options) {
        // Check for conflicts between main restrictions and advanced assignments
        if (!empty($options['restricted_roles'])) {
            $restricted_roles = $options['restricted_roles'];
            
            // Get existing advanced assignments
            $advanced_assignments = get_option($this->option_name . '_advanced_assignments', array());
            
            foreach ($advanced_assignments as $assignment) {
                if (!empty($assignment['roles'])) {
                    $assignment_roles = explode(',', $assignment['roles']);
                    $conflicts = array_intersect($assignment_roles, $restricted_roles);
                    
                    if (!empty($conflicts)) {
                        $conflicted_roles = implode(', ', $conflicts);
                        add_settings_error(
                            $this->option_name,
                            'role_conflict',
                            sprintf(
                                /* translators: %s: comma-separated list of conflicted role names */
                                __('Cannot uncheck roles (%s) that are currently used in advanced redirect configurations.', 'robert22-admin-bar-and-access-control'),
                                $conflicted_roles
                            )
                        );
                        break;
                    }
                }
            }
        }
    }
    
    /**
     * Admin page content
     */
    public function admin_page() {
        // Display settings errors
        settings_errors($this->option_name);
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('rbts_robert22_admin_bar_access_control_group');
                do_settings_sections('rbts_robert22_admin_bar_access_control');
                ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Admin Bar Control', 'robert22-admin-bar-and-access-control'); ?></th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="radio" name="<?php echo esc_attr($this->option_name); ?>[admin_bar_control]" value="show" <?php checked($this->get_option('admin_bar_control', 'show'), 'show'); ?>>
                                    <?php esc_html_e('Show admin bar for all users', 'robert22-admin-bar-and-access-control'); ?>
                                </label><br>
                                
                                <label>
                                    <input type="radio" name="<?php echo esc_attr($this->option_name); ?>[admin_bar_control]" value="hide_selected" <?php checked($this->get_option('admin_bar_control'), 'hide_selected'); ?>>
                                    <?php esc_html_e('Hide admin bar for selected roles', 'robert22-admin-bar-and-access-control'); ?>
                                </label><br>
                                
                                <label>
                                    <input type="radio" name="<?php echo esc_attr($this->option_name); ?>[admin_bar_control]" value="hide_all" <?php checked($this->get_option('admin_bar_control'), 'hide_all'); ?>>
                                    <?php esc_html_e('Hide admin bar for all users', 'robert22-admin-bar-and-access-control'); ?>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                    
                    <tr class="rbts-role-selection" <?php echo ($this->get_option('admin_bar_control') !== 'hide_selected') ? 'style="display:none;"' : ''; ?>>
                        <th scope="row"><?php esc_html_e('Select Roles', 'robert22-admin-bar-and-access-control'); ?></th>
                        <td>
                            <fieldset>
                                <?php
                                $roles = wp_roles()->get_names();
                                $selected_roles = $this->get_option('restricted_roles', array());
                                
                                foreach ($roles as $role_key => $role_name) {
                                    if ($role_key === 'administrator') continue; // Skip administrator
                                    ?>
                                    <label>
                                        <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[restricted_roles][]" value="<?php echo esc_attr($role_key); ?>" <?php checked(in_array($role_key, $selected_roles)); ?>>
                                        <?php echo esc_html($role_name); ?>
                                    </label><br>
                                    <?php
                                }
                                ?>
                            </fieldset>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php esc_html_e('Redirect After Login', 'robert22-admin-bar-and-access-control'); ?></th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="radio" name="<?php echo esc_attr($this->option_name); ?>[redirect_type]" value="default" <?php checked($this->get_option('redirect_type', 'default'), 'default'); ?>>
                                    <?php esc_html_e('Default WordPress behavior', 'robert22-admin-bar-and-access-control'); ?>
                                </label><br>
                                
                                <label>
                                    <input type="radio" name="<?php echo esc_attr($this->option_name); ?>[redirect_type]" value="custom_path" <?php checked($this->get_option('redirect_type'), 'custom_path'); ?>>
                                    <?php esc_html_e('Custom path (relative to site URL)', 'robert22-admin-bar-and-access-control'); ?>
                                </label><br>
                                
                                <div class="rbts-custom-path-input" style="margin-left: 25px; margin-top: 10px; <?php echo ($this->get_option('redirect_type') !== 'custom_path') ? 'display:none;' : ''; ?>">
                                    <input type="text" name="<?php echo esc_attr($this->option_name); ?>[custom_path]" value="<?php echo esc_attr($this->get_option('custom_path')); ?>" placeholder="/my-custom-page" class="regular-text">
                                    <p class="description"><?php esc_html_e('Enter a path relative to your site URL (e.g., /dashboard, /profile)', 'robert22-admin-bar-and-access-control'); ?></p>
                                </div>
                                
                                <label>
                                    <input type="radio" name="<?php echo esc_attr($this->option_name); ?>[redirect_type]" value="full_url" <?php checked($this->get_option('redirect_type'), 'full_url'); ?>>
                                    <?php esc_html_e('Full URL (external or internal)', 'robert22-admin-bar-and-access-control'); ?>
                                </label><br>
                                
                                <div class="rbts-full-url-input" style="margin-left: 25px; margin-top: 10px; <?php echo ($this->get_option('redirect_type') !== 'full_url') ? 'display:none;' : ''; ?>">
                                    <input type="url" name="<?php echo esc_attr($this->option_name); ?>[full_url]" value="<?php echo esc_attr($this->get_option('full_url')); ?>" placeholder="https://example.com/page" class="regular-text">
                                    <p class="description"><?php esc_html_e('Enter a complete URL including http:// or https://', 'robert22-admin-bar-and-access-control'); ?></p>
                                </div>
                            </fieldset>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php esc_html_e('Confirmation Message', 'robert22-admin-bar-and-access-control'); ?></th>
                        <td>
                            <textarea name="<?php echo esc_attr($this->option_name); ?>[confirmation_message]" rows="3" cols="50" class="large-text"><?php echo esc_textarea($this->get_option('confirmation_message')); ?></textarea>
                            <p class="description"><?php esc_html_e('Optional message to display to users before redirect (leave empty for no message)', 'robert22-admin-bar-and-access-control'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('Save Settings', 'primary', 'submit'); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Validate custom path input with enhanced security
     */
    private function validate_custom_path($path) {
        // Return empty if no path provided
        if (empty($path)) {
            return '';
        }
        
        // Remove any path traversal attempts
        $path = str_replace(['../', '..\\', '../', '..\\'], '', $path);
        
        // Ensure path starts with /
        if (substr($path, 0, 1) !== '/') {
            $path = '/' . $path;
        }
        
        // Remove any dangerous characters
        $path = preg_replace('/[^a-zA-Z0-9\/_-]/', '', $path);
        
        // Validate path length
        if (strlen($path) > 200) {
            $path = substr($path, 0, 200);
        }
        
        return $path;
    }
    
    /**
     * Get option value with default
     */
    private function get_option($key, $default = '') {
        return isset($this->options[$key]) ? $this->options[$key] : $default;
    }
    
    /**
     * Handle admin bar control
     */
    public function handle_admin_bar_control() {
        $admin_bar_control = $this->get_option('admin_bar_control', 'show');
        
        if ($admin_bar_control === 'hide_all') {
            add_filter('show_admin_bar', '__return_false');
            add_action('wp_head', array($this, 'hide_admin_bar_css'));
        } elseif ($admin_bar_control === 'hide_selected') {
            $restricted_roles = $this->get_option('restricted_roles', array());
            
            if (!empty($restricted_roles) && $this->user_has_restricted_role($restricted_roles)) {
                add_filter('show_admin_bar', '__return_false');
                add_action('wp_head', array($this, 'hide_admin_bar_css'));
            }
        }
    }
    
    /**
     * Add CSS to completely hide admin bar
     */
    public function hide_admin_bar_css() {
        $hide_css = '
            #wpadminbar { display: none !important; }
            html { margin-top: 0 !important; }
            * html body { margin-top: 0 !important; }
        ';
        wp_add_inline_style('wp-admin', $hide_css);
    }
    
    /**
     * Check if user has restricted role
     */
    private function user_has_restricted_role($restricted_roles) {
        if (!is_user_logged_in()) {
            return false;
        }
        
        $user = wp_get_current_user();
        $user_roles = $user->roles;
        
        return !empty(array_intersect($user_roles, $restricted_roles));
    }
    
    /**
     * Handle login redirect
     */
    public function handle_login_redirect($user_login, $user) {
        $redirect_type = $this->get_option('redirect_type', 'default');
        
        if ($redirect_type === 'default') {
            return;
        }
        
        $restricted_roles = $this->get_option('restricted_roles', array());
        
        // Check if user has restricted role
        if (!empty($restricted_roles) && !empty(array_intersect($user->roles, $restricted_roles))) {
            $redirect_url = $this->get_redirect_url($redirect_type);
            
            if ($redirect_url) {
                $confirmation_message = $this->get_option('confirmation_message');
                
                if (!empty($confirmation_message)) {
                    // Store message in session/transient for display
                    set_transient('rbts_redirect_message_' . $user->ID, $confirmation_message, 300);
                }
                
                wp_redirect($redirect_url);
                exit;
            }
        }
    }
    
    /**
     * Get redirect URL based on type
     */
    private function get_redirect_url($redirect_type) {
        switch ($redirect_type) {
            case 'custom_path':
                $custom_path = $this->get_option('custom_path');
                return !empty($custom_path) ? home_url($custom_path) : false;
                
            case 'full_url':
                $full_url = $this->get_option('full_url');
                return !empty($full_url) ? $full_url : false;
                
            default:
                return false;
        }
    }
}

// Initialize the plugin
new RBTS_Robert22_Admin_Bar_And_Access_Control();