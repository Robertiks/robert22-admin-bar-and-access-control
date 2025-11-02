<?php
/**
 * Plugin Name: Robert22 Admin Bar and Access Control
 * Plugin URI: https://wordpress.org/plugins/robert22-admin-bar-and-access-control
 * Description: Control admin bar visibility and wp-admin access for specific user roles with customizable redirect options.
 * Version: 1.0.0
 * Author: Robertiks
 * Author URI: https://github.com/Robertiks/robert22-admin-bar-and-access-control/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: robert22-admin-bar-and-access-control
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants with unique prefix
define('ROBERT22ABC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ROBERT22ABC_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('ROBERT22ABC_VERSION', '1.0.0');
define('ROBERT22ABC_OPTION_NAME', 'robert22abc_admin_bar_control_options');

class Robert22ABC_AdminBarControl {
    
    private $options;
    private $option_name = 'robert22abc_admin_bar_control_options';
    
    public function __construct() {
        add_action('init', array($this, 'init'));
    }
    
    public function init() {
        // Load plugin options with unique option name
        $this->options = get_option($this->option_name, array());
        
        // Admin hooks
        if (is_admin()) {
            add_action('admin_menu', array($this, 'add_admin_menu'));
            add_action('admin_init', array($this, 'admin_init'));
        }
        
        // Frontend and admin bar hooks - use multiple hooks to ensure it works
        add_action('init', array($this, 'hide_admin_bar'));
        add_action('wp_before_admin_bar_render', array($this, 'hide_admin_bar'));
        add_action('wp_loaded', array($this, 'hide_admin_bar'));
        add_action('admin_init', array($this, 'block_wp_admin_access'));
    }
    
    /**
     * Add admin menu under Tools
     */
    public function add_admin_menu() {
        add_management_page(
            'Robert22 Admin Bar and Access Control',
            'Admin Bar Control',
            'manage_options',
            'robert22abc-admin-bar-and-access-control',
            array($this, 'admin_page')
        );
    }
    
    /**
     * Initialize admin settings
     */
    public function admin_init() {
        register_setting('robert22abc_admin_bar_control_group', $this->option_name, array(
            'sanitize_callback' => array($this, 'sanitize_options')
        ));
        
        // Enqueue admin styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_styles'));
    }
    
    /**
     * Sanitize plugin options
     */
    public function sanitize_options($input) {
        $sanitized = array();
        
        $sanitized['enabled'] = isset($input['enabled']) ? 1 : 0;
        
        // Handle role assignments for advanced redirects first
        $current_options = get_option($this->option_name, array());
        $role_assignments = isset($current_options['role_assignments']) ? $current_options['role_assignments'] : array();
        
        // Handle removal of assignments
        if (isset($_POST['remove_assignment']) && isset($_POST['_wpnonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'robert22abc_admin_bar_control_group-options')) {
            $remove_index = intval($_POST['remove_assignment']);
            if (isset($role_assignments[$remove_index])) {
                unset($role_assignments[$remove_index]);
                $role_assignments = array_values($role_assignments); // Re-index array
            }
        }
        
        // Handle new assignments
        if (isset($input['role_assignments']) && is_array($input['role_assignments'])) {
            $new_restricted_roles = isset($input['restricted_roles']) ? array_map('sanitize_text_field', $input['restricted_roles']) : array();
            
            foreach ($input['role_assignments'] as $assignment_json) {
                $assignment = json_decode(urldecode($assignment_json), true);
                if ($assignment && isset($assignment['roles']) && !empty($assignment['roles'])) {
                    // Check if all roles in this assignment are still in restricted roles
                    $invalid_roles = array_diff($assignment['roles'], $new_restricted_roles);
                    
                    if (!empty($invalid_roles)) {
                        // Add error for roles that are not in restricted roles anymore
                        add_settings_error(
                            $this->option_name,
                            'invalid_assignment_roles',
                            sprintf(
                                'Cannot save role assignment because the following roles are not selected in Role Restrictions: %s. Please check the roles in Role Restrictions first.',
                                implode(', ', $invalid_roles)
                            ),
                            'error'
                        );
                        continue; // Skip this assignment
                    }
                    
                    $role_assignments[] = array(
                        'roles' => array_map('sanitize_text_field', $assignment['roles']),
                        'redirect_type' => sanitize_text_field($assignment['redirect_type']),
                        'custom_path' => $this->validate_custom_path($assignment['custom_path']),
                        'full_url' => $this->validate_full_url($assignment['full_url'])
                    );
                }
            }
        }
        
        // Get all roles used in advanced redirects
        $roles_in_assignments = array();
        foreach ($role_assignments as $assignment) {
            if (isset($assignment['roles']) && is_array($assignment['roles'])) {
                $roles_in_assignments = array_merge($roles_in_assignments, $assignment['roles']);
            }
        }
        $roles_in_assignments = array_unique($roles_in_assignments);
        
        // Validate restricted roles - prevent unchecking roles used in advanced redirects
        $new_restricted_roles = isset($input['restricted_roles']) ? array_map('sanitize_text_field', $input['restricted_roles']) : array();
        $current_restricted_roles = isset($current_options['restricted_roles']) ? $current_options['restricted_roles'] : array();
        
        // Check if any roles used in assignments are being unchecked
        $roles_being_unchecked = array_diff($current_restricted_roles, $new_restricted_roles);
        $conflicting_roles = array_intersect($roles_being_unchecked, $roles_in_assignments);
        
        if (!empty($conflicting_roles)) {
            // Add error notice and keep the current restricted roles
            add_settings_error(
                $this->option_name,
                'roles_in_use',
                sprintf(
                    'Cannot uncheck the following roles as they are currently used in advanced redirect configurations: %s. Please remove them from the advanced redirects first.',
                    implode(', ', $conflicting_roles)
                ),
                'error'
            );
            $sanitized['restricted_roles'] = $current_restricted_roles;
        } else {
            $sanitized['restricted_roles'] = $new_restricted_roles;
        }
        
        $sanitized['redirect_type'] = isset($input['redirect_type']) ? sanitize_text_field($input['redirect_type']) : 'home';
        $sanitized['custom_path'] = isset($input['custom_path']) ? $this->validate_custom_path($input['custom_path']) : '';
        $sanitized['full_url'] = isset($input['full_url']) ? $this->validate_full_url($input['full_url']) : '';
        
        $sanitized['role_assignments'] = $role_assignments;
        
        return $sanitized;
    }
    
    /**
     * Enqueue admin styles and scripts
     */
    public function enqueue_admin_styles($hook) {
        if ($hook !== 'tools_page_robert22abc-admin-bar-and-access-control') {
            return;
        }
        wp_enqueue_style('robert22abc-admin-bar-and-access-control-admin', ROBERT22ABC_PLUGIN_URL . 'assets/admin-style.css', array(), ROBERT22ABC_VERSION);
        wp_enqueue_script('jquery');
        wp_enqueue_script('robert22abc-admin-bar-and-access-control-admin', ROBERT22ABC_PLUGIN_URL . 'assets/admin-main-js.js', array('jquery'), ROBERT22ABC_VERSION, true);
        
        // Localize script with PHP variables
        wp_localize_script('robert22abc-admin-bar-and-access-control-admin', 'robert22abcAdminVars', array(
            'optionName' => $this->option_name,
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('robert22abc_admin_nonce')
        ));
    }
    
    /**
     * Admin page content
     */
    public function admin_page() {
        // Always reload options to ensure we have the latest data
        $this->options = get_option($this->option_name, array());
        
        $enabled = isset($this->options['enabled']) ? $this->options['enabled'] : 0;
        $restricted_roles = isset($this->options['restricted_roles']) ? $this->options['restricted_roles'] : array();
        $redirect_type = isset($this->options['redirect_type']) ? $this->options['redirect_type'] : 'home';
        $custom_path = isset($this->options['custom_path']) ? $this->options['custom_path'] : '';
        $full_url = isset($this->options['full_url']) ? $this->options['full_url'] : '';
        $role_assignments = isset($this->options['role_assignments']) ? $this->options['role_assignments'] : array();
        ?>
        <div class="wrap rbts-admin-bar-and-roles-control-wrap">
            <h1>Admin Bar and Roles Control</h1>
            
            <?php settings_errors($this->option_name); ?>
            
            <div class="rbts-admin-bar-and-roles-control-status <?php echo $enabled ? 'enabled' : 'disabled'; ?>">
                <strong>Status:</strong> <?php echo $enabled ? 'Enabled' : 'Disabled'; ?>
                <?php if ($enabled && !empty($restricted_roles)): ?>
                    - Restricting <?php echo count($restricted_roles); ?> role(s)
                <?php endif; ?>
            </div>
            
            <form method="post" action="options.php">
                <?php settings_fields('robert22abc_admin_bar_control_group'); ?>
                <?php do_settings_sections('robert22abc_admin_bar_control_group'); ?>
                
                <div class="rbts-admin-bar-and-roles-control-section">
                    <h2>General Settings</h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">Enable Plugin</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[enabled]" value="1" <?php checked(1, $enabled); ?> />
                                    Enable Admin Bar and Roles Control and wp-admin restrictions
                                </label>
                                <p class="description">When enabled, selected user roles will have restricted access to wp-admin and hidden admin bar.</p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <div class="rbts-admin-bar-and-roles-control-section">
                    <h2>Role Restrictions</h2>
                    <p>Select which user roles should be restricted from accessing wp-admin:</p>
                    <div class="rbts-role-selection-grid">
                        <?php
                        $roles = wp_roles()->get_names();
                        foreach ($roles as $role_key => $role_name) {
                            // Skip administrator role - they should never be restricted
                            if ($role_key === 'administrator') {
                                continue;
                            }
                            $checked = in_array($role_key, $restricted_roles) ? 'checked="checked"' : '';
                            echo '<label><input type="checkbox" name="' . esc_attr($this->option_name) . '[restricted_roles][]" value="' . esc_attr($role_key) . '" ' . esc_attr($checked) . ' /> ' . esc_html($role_name) . '</label>';
                        }
                        ?>
                    </div>
                    <p class="description">Users with these roles will be redirected when trying to access wp-admin and will not see the admin bar. <strong>Note:</strong> Administrator role is excluded from restrictions for security reasons.</p>
                </div>
                
                <div class="rbts-admin-bar-and-roles-control-section">
                    <h2>Redirect Settings</h2>
                    <p>Choose where restricted users should be redirected when they try to access wp-admin:</p>
                    
                    <div class="rbts-redirect-options">
                        <label>
                            <input type="radio" name="<?php echo esc_attr($this->option_name); ?>[redirect_type]" value="home" <?php checked('home', $redirect_type); ?> />
                            <strong>Redirect to Home Page</strong>
                        </label>
                        <p class="description">Users will be redirected to your site's home page.</p>
                        
                        <label>
                            <input type="radio" name="<?php echo esc_attr($this->option_name); ?>[redirect_type]" value="custom_path" <?php checked('custom_path', $redirect_type); ?> />
                            <strong>Redirect to Custom Path</strong>
                        </label>
                        <div class="rbts-custom-path-input" style="<?php echo ($redirect_type === 'custom_path') ? 'display: block;' : 'display: none;'; ?>">
                            <strong><?php echo esc_html(get_site_url()); ?>/</strong>
                            <input type="text" name="<?php echo esc_attr($this->option_name); ?>[custom_path]" value="<?php echo esc_attr($custom_path); ?>" placeholder="my-profile" style="width: 300px;" />
                        </div>
                        <p class="description">Enter the path after your domain (e.g., "my-profile" will redirect to <?php echo esc_html(get_site_url()); ?>/my-profile/)</p>
                        
                        <label>
                            <input type="radio" name="<?php echo esc_attr($this->option_name); ?>[redirect_type]" value="full_url" <?php checked('full_url', $redirect_type); ?> />
                            <strong>Redirect to Full URL</strong>
                        </label>
                        <div class="rbts-full-url-input" style="<?php echo ($redirect_type === 'full_url') ? 'display: block;' : 'display: none;'; ?>">
                            <input type="url" name="<?php echo esc_attr($this->option_name); ?>[full_url]" value="<?php echo esc_attr($full_url); ?>" placeholder="https://example.com/custom-page" style="width: 400px;" />
                        </div>
                        <p class="description">Enter the complete URL where users should be redirected (e.g., https://example.com/custom-page)</p>
                    </div>
                    
                    <!-- Advanced Role Assignment Section -->
                    <div class="rbts-role-assignment-section" style="margin-top: 30px; padding: 20px; border: 1px solid #ddd; border-radius: 5px; background: #f9f9f9;">
                        <h3>Advanced Role-Based Redirects</h3>
                        <p class="description">Assign different redirect settings to specific roles for more granular control. Roles which are not assigned to any specific configuration will use the global redirect settings configured above.</p>
                        
                        <button type="button" id="rbts-assign-roles-btn" class="button button-secondary" style="margin-top: 10px;">
                            Assign Selected Roles
                        </button>
                        
                        <div id="rbts-role-assignment-notice" style="display: none; margin-top: 10px; padding: 10px; border-left: 4px solid #ffba00; background: #fff8e1;">
                            <p><strong>Notice:</strong> You need to select at least 2 roles in the "Role Restrictions" section above to use advanced role assignment.</p>
                        </div>
                        
                        <div id="rbts-role-assignment-form" style="display: none; margin-top: 15px;">
                            <h4>Select Roles for This Redirect Configuration:</h4>
                            <div id="rbts-selected-roles-container" style="margin: 10px 0; min-height: 40px; border: 1px solid #ddd; border-radius: 3px; padding: 10px; background: white; position: relative;">
                                <div id="rbts-selected-roles-tags"></div>
                                <div id="rbts-role-selector" style="position: relative;">
                                    <button type="button" id="rbts-add-role-btn" class="button button-secondary" style="margin-top: 5px;">
                                        + Add Role
                                    </button>
                                </div>
                                <div id="rbts-available-roles" style="display: none; position: absolute; left: 0; right: 0; border: 1px solid #ddd; max-height: 150px; overflow-y: auto; background: white; z-index: 1000; box-shadow: 0 2px 5px rgba(0,0,0,0.1);"></div>
                            </div>
                            
                            <p class="description" style="margin-top: 10px;">
                                <strong>How it works:</strong> Click "Add Role" to see available roles and select specific roles that should use the redirect settings configured above. 
                                Roles are displayed as tags for easy management - click the × to remove a role. 
                                After selecting roles, click "Save Assignment" to create a new redirect configuration for those roles.
                            </p>
                            
                            <button type="button" id="rbts-save-assignment" class="button button-primary" style="margin-top: 10px; display: none;">
                                Save Assignment
                            </button>
                        </div>
                        
                        <div id="rbts-additional-redirects" style="margin-top: 20px;">
                            <?php if (!empty($role_assignments)): ?>
                                <h4>Saved Role Assignments:</h4>
                                <?php foreach ($role_assignments as $index => $assignment): ?>
                                    <div class="rbts-saved-assignment" style="padding: 10px; border: 1px solid #ccc; border-radius: 4px; margin-bottom: 10px; background: #fff;">
                                        <strong>Configuration <?php echo esc_html($index + 1); ?>:</strong>
                                        <p><strong>Roles:</strong> <?php echo esc_html(implode(', ', $assignment['roles'])); ?></p>
                                        <p><strong>Redirect:</strong> 
                                            <?php if ($assignment['redirect_type'] === 'home'): ?>
                                                Home Page
                                            <?php elseif ($assignment['redirect_type'] === 'custom_path'): ?>
                                                Custom Path: <?php echo esc_html($assignment['custom_path']); ?>
                                            <?php elseif ($assignment['redirect_type'] === 'full_url'): ?>
                                                Full URL: <?php echo esc_html($assignment['full_url']); ?>
                                            <?php endif; ?>
                                        </p>
                                        <button type="button" class="button rbts-remove-assignment" data-index="<?php echo esc_attr($index); ?>" style="background: #d63638; color: white; border-color: #d63638;">Remove</button>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
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
        
        // Remove leading/trailing slashes and whitespace
        $path = trim($path, '/ \\');
        
        // Only allow alphanumeric characters, hyphens, underscores, and forward slashes
        if (!preg_match('/^[a-zA-Z0-9\/_-]+$/', $path)) {
            // Log security attempt only if WP_DEBUG is enabled
            if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log('Admin Bar and Roles Control: Invalid characters in custom path attempt: ' . $path);
            }
            return '';
        }
        
        // Limit length to prevent buffer overflow
        if (strlen($path) > 100) {
            if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log('Admin Bar and Roles Control: Path too long: ' . strlen($path) . ' characters');
            }
            return '';
        }
        
        // Prevent certain dangerous paths
        $dangerous_paths = ['wp-admin', 'wp-includes', 'wp-content', 'admin', 'login', 'wp-login'];
        foreach ($dangerous_paths as $dangerous) {
            if (stripos($path, $dangerous) !== false) {
                if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                    error_log('Admin Bar and Roles Control: Dangerous path blocked: ' . $path);
                }
                return '';
            }
        }
        
        return sanitize_text_field($path);
    }
    
    /**
     * Validate full URL input with basic security checks
     */
    private function validate_full_url($url) {
        // Return empty if no URL provided
        if (empty($url)) {
            return '';
        }
        
        // Basic URL validation
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log('Admin Bar and Roles Control: Invalid URL format: ' . $url);
            }
            return '';
        }
        
        // Parse URL components
        $parsed_url = wp_parse_url($url);
        if (!$parsed_url || !isset($parsed_url['host'])) {
            if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log('Admin Bar and Roles Control: Could not parse URL: ' . $url);
            }
            return '';
        }
        
        // Only block dangerous protocols - allow any domain since admin controls this
        $scheme = isset($parsed_url['scheme']) ? strtolower($parsed_url['scheme']) : '';
        if (!in_array($scheme, ['http', 'https'])) {
            if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log('Admin Bar and Roles Control: Blocked non-HTTP redirect: ' . $scheme);
            }
            return '';
        }
        
        return esc_url_raw($url);
    }
    
    /**
     * Hide admin bar for restricted roles
     */
    public function hide_admin_bar() {
        // Reload options to ensure we have the latest settings
        $this->options = get_option($this->option_name, array());
        
        if (!isset($this->options['enabled']) || !$this->options['enabled']) {
            return;
        }
        
        if (!isset($this->options['restricted_roles']) || empty($this->options['restricted_roles'])) {
            return;
        }
        
        $current_user = wp_get_current_user();
        if (!$current_user->ID) {
            return;
        }
        
        // Never restrict administrators
        if (in_array('administrator', $current_user->roles)) {
            return;
        }
        
        $user_roles = $current_user->roles;
        $restricted_roles = $this->options['restricted_roles'];
        
        // Check if user has any restricted role
        if (array_intersect($user_roles, $restricted_roles)) {
            show_admin_bar(false);
            // Also add CSS to hide admin bar completely
            add_action('wp_head', array($this, 'hide_admin_bar_css'));
            add_action('admin_head', array($this, 'hide_admin_bar_css'));
        }
    }
    
    /**
     * Add CSS to completely hide admin bar
     */
    public function hide_admin_bar_css() {
        $css = '
            #wpadminbar { display: none !important; }
            html { margin-top: 0 !important; }
            * html body { margin-top: 0 !important; }
        ';
        wp_add_inline_style('wp-admin', $css);
    }
    
    /**
     * Block wp-admin access for restricted roles
     */
    public function block_wp_admin_access() {
        // Reload options to ensure we have the latest settings
        $this->options = get_option($this->option_name, array());
        
        if (!isset($this->options['enabled']) || !$this->options['enabled']) {
            return;
        }
        
        if (!isset($this->options['restricted_roles']) || empty($this->options['restricted_roles'])) {
            return;
        }
        
        // Don't block AJAX requests
        if (defined('DOING_AJAX') && DOING_AJAX) {
            return;
        }
        
        $current_user = wp_get_current_user();
        if (!$current_user->ID) {
            return;
        }
        
        // Never restrict administrators
        if (in_array('administrator', $current_user->roles)) {
            return;
        }
        
        $user_roles = $current_user->roles;
        $restricted_roles = $this->options['restricted_roles'];
        
        // Check if user has any restricted role
        if (array_intersect($user_roles, $restricted_roles)) {
            // First check for advanced role-based redirects
            $redirect_url = $this->get_role_specific_redirect_url($user_roles);
            
            // If no specific redirect found, use general settings
            if (!$redirect_url) {
                $redirect_url = $this->get_redirect_url();
            }
            
            wp_redirect($redirect_url);
            exit;
        }
    }
    
    /**
     * Get role-specific redirect URL from advanced assignments
     */
    private function get_role_specific_redirect_url($user_roles) {
        $role_assignments = isset($this->options['role_assignments']) ? $this->options['role_assignments'] : array();
        
        if (empty($role_assignments)) {
            return false;
        }
        
        // Check each assignment to see if user's roles match
        foreach ($role_assignments as $assignment) {
            if (isset($assignment['roles']) && is_array($assignment['roles'])) {
                // Check if user has any of the roles in this assignment
                if (array_intersect($user_roles, $assignment['roles'])) {
                    // Found a matching assignment, get the redirect URL
                    return $this->get_assignment_redirect_url($assignment);
                }
            }
        }
        
        return false;
    }
    
    /**
     * Get redirect URL from a specific role assignment
     */
    private function get_assignment_redirect_url($assignment) {
        $redirect_type = isset($assignment['redirect_type']) ? $assignment['redirect_type'] : 'home';
        
        switch ($redirect_type) {
            case 'custom_path':
                $custom_path = isset($assignment['custom_path']) ? trim($assignment['custom_path']) : '';
                if (!empty($custom_path)) {
                    $validated_path = $this->validate_custom_path($custom_path);
                    if (!empty($validated_path)) {
                        $redirect_url = home_url('/' . ltrim($validated_path, '/'));
                        if (!preg_match('/\.[a-zA-Z0-9]+$/', $validated_path)) {
                            $redirect_url = rtrim($redirect_url, '/') . '/';
                        }
                        return $redirect_url;
                    }
                }
                break;
                
            case 'full_url':
                $full_url = isset($assignment['full_url']) ? trim($assignment['full_url']) : '';
                if (!empty($full_url)) {
                    $validated_url = $this->validate_full_url($full_url);
                    if (!empty($validated_url)) {
                        return $validated_url;
                    }
                }
                break;
        }
        
        // Default to home if validation fails
        return home_url('/');
    }

    /**
     * Get redirect URL based on settings with enhanced validation
     */
    private function get_redirect_url() {
        $redirect_type = isset($this->options['redirect_type']) ? $this->options['redirect_type'] : 'home';
        
        switch ($redirect_type) {
            case 'custom_path':
                $custom_path = isset($this->options['custom_path']) ? trim($this->options['custom_path']) : '';
                if (!empty($custom_path)) {
                    // Re-validate the path at runtime for extra security
                    $validated_path = $this->validate_custom_path($custom_path);
                    if (!empty($validated_path)) {
                        // Ensure proper URL construction without double slashes
                        $redirect_url = home_url('/' . ltrim($validated_path, '/'));
                        // Add trailing slash only if the path doesn't end with a file extension
                        if (!preg_match('/\.[a-zA-Z0-9]+$/', $validated_path)) {
                            $redirect_url = rtrim($redirect_url, '/') . '/';
                        }
                        return $redirect_url;
                    }
                }
                break;
                
            case 'full_url':
                $full_url = isset($this->options['full_url']) ? trim($this->options['full_url']) : '';
                if (!empty($full_url)) {
                    // Re-validate the URL at runtime for extra security
                    $validated_url = $this->validate_full_url($full_url);
                    if (!empty($validated_url)) {
                        return $validated_url;
                    }
                }
                break;
        }
        
        // Default to home page if validation fails
        return home_url('/');
    }
}

// Initialize the plugin with unique class name
new Robert22ABC_AdminBarControl();