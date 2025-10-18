<?php
/**
 * Plugin Name: Admin Bar and Roles Control
 * Plugin URI: https://wordpress.org/plugins/admin-bar-and-roles-control
 * Description: Control admin bar visibility and wp-admin access for specific user roles with customizable redirect options.
 * Version: 1.0.0
 * Author: Robertiks
 * Author URI: https://github.com/Robertiks/admin-bar-and-roles-control/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: admin-bar-and-roles-control
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants with unique prefix
define('RBTS_ABC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('RBTS_ABC_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('RBTS_ABC_VERSION', '1.0.0');
define('RBTS_ABC_OPTION_NAME', 'rbts_admin_bar_control_options');

class RBTS_AdminBarControl {
    
    private $options;
    private $option_name = 'rbts_admin_bar_control_options';
    
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
            'Admin Bar and Roles Control',
            'Admin Bar and Roles Control',
            'manage_options',
            'rbts-admin-bar-and-roles-control',
            array($this, 'admin_page')
        );
    }
    
    /**
     * Initialize admin settings
     */
    public function admin_init() {
        register_setting('rbts_admin_bar_control_group', $this->option_name, array(
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
        if (isset($_POST['remove_assignment']) && isset($_POST['_wpnonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'rbts_admin_bar_control_group-options')) {
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
     * Enqueue admin styles
     */
    public function enqueue_admin_styles($hook) {
        if ($hook !== 'tools_page_rbts-admin-bar-and-roles-control') {
            return;
        }
        wp_enqueue_style('rbts-admin-bar-and-roles-control-admin', RBTS_ABC_PLUGIN_URL . 'assets/admin-style.css', array(), RBTS_ABC_VERSION);
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
                <?php settings_fields('rbts_admin_bar_control_group'); ?>
                <?php do_settings_sections('rbts_admin_bar_control_group'); ?>
                
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
                        <div class="rbts-custom-path-input">
                            <strong><?php echo esc_html(get_site_url()); ?>/</strong>
                            <input type="text" name="<?php echo esc_attr($this->option_name); ?>[custom_path]" value="<?php echo esc_attr($custom_path); ?>" placeholder="my-profile" style="width: 300px;" />
                        </div>
                        <p class="description">Enter the path after your domain (e.g., "my-profile" will redirect to <?php echo esc_html(get_site_url()); ?>/my-profile/)</p>
                        
                        <label>
                            <input type="radio" name="<?php echo esc_attr($this->option_name); ?>[redirect_type]" value="full_url" <?php checked('full_url', $redirect_type); ?> />
                            <strong>Redirect to Full URL</strong>
                        </label>
                        <div class="rbts-full-url-input">
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
        
        <script>
        jQuery(document).ready(function($) {
            // Show/hide redirect options based on selection
            $('input[name="<?php echo esc_js($this->option_name); ?>[redirect_type]"]').change(function() {
                $('.rbts-custom-path-input, .rbts-full-url-input').hide();
                if ($(this).val() === 'custom_path') {
                    $('.rbts-custom-path-input').show();
                } else if ($(this).val() === 'full_url') {
                    $('.rbts-full-url-input').show();
                }
            }).trigger('change');
            
            // Advanced Role Assignment Functionality
            var selectedRoles = [];
            var availableRoles = [];
            
            // Get all available roles (excluding administrator)
            $('input[name="<?php echo esc_js($this->option_name); ?>[restricted_roles][]"]').each(function() {
                var roleValue = $(this).val();
                var roleLabel = $(this).parent().text().trim();
                availableRoles.push({
                    value: roleValue,
                    label: roleLabel
                });
            });
            
            // Handle Assign Selected Roles button click
            $('#rbts-assign-roles-btn').click(function() {
                var $button = $(this);
                var checkedRoles = $('input[name="<?php echo esc_js($this->option_name); ?>[restricted_roles][]"]:checked');
                
                if ($button.text().trim() === 'Go Back') {
                    // Close and reset the role assignment form
                    $('#rbts-role-assignment-form').hide();
                    $('#rbts-role-assignment-notice').hide();
                    $button.text('Assign Selected Roles');
                    
                    // Clear selected roles
                    selectedRoles = [];
                    updateRoleTags();
                    
                    return;
                }
                
                if (checkedRoles.length < 2) {
                    $('#rbts-role-assignment-notice').show();
                    $('#rbts-role-assignment-form').hide();
                } else {
                    $('#rbts-role-assignment-notice').hide();
                    $('#rbts-role-assignment-form').show();
                    $button.text('Go Back');
                    
                    // Reset selected roles and update button state
                    selectedRoles = [];
                    updateRoleTags();
                    updateAddRoleButton();
                }
            });
            
            // Handle Add Role button click
            $('#rbts-add-role-btn').click(function(e) {
                e.preventDefault();
                
                // Check if button is disabled
                if ($(this).prop('disabled')) {
                    return;
                }
                
                var availableForSelection = getAvailableRoles();
                
                if (availableForSelection.length > 0) {
                    showRoleDropdown(availableForSelection);
                } else {
                    // Show message that all roles are selected
                    $(this).text('All roles assigned').prop('disabled', true);
                }
            });
            
            // Hide dropdown when clicking outside
            $(document).click(function(e) {
                if (!$(e.target).closest('#rbts-role-selector, #rbts-available-roles').length) {
                    $('#rbts-available-roles').hide();
                }
            });
            
            // Handle role selection
            $(document).on('click', '.rbts-role-option', function() {
                var roleValue = $(this).data('value');
                var roleLabel = $(this).text();
                
                if (selectedRoles.indexOf(roleValue) === -1) {
                    selectedRoles.push(roleValue);
                    updateRoleTags();
                    
                    // Update the Add Role button to reflect available roles
                    updateAddRoleButton();
                    
                    // Refresh the dropdown to remove the selected role from options
                    var availableForSelection = getAvailableRoles();
                    if (availableForSelection.length > 0) {
                        showRoleDropdown(availableForSelection);
                    } else {
                        $('#rbts-available-roles').hide();
                    }
                }
            });
            
            // Handle role removal
            $(document).on('click', '.rbts-role-tag-remove', function() {
                var roleValue = $(this).data('value');
                selectedRoles = selectedRoles.filter(function(role) {
                    return role !== roleValue;
                });
                updateRoleTags();
                
                // Update the Add Role button to reflect available roles
                updateAddRoleButton();
                
                // If dropdown is visible, refresh it to show the newly available role
                if ($('#rbts-available-roles').is(':visible')) {
                    var availableForSelection = getAvailableRoles();
                    showRoleDropdown(availableForSelection);
                }
            });
            
            // Update Add Role button state
            function updateAddRoleButton() {
                var availableForSelection = getAvailableRoles();
                var $button = $('#rbts-add-role-btn');
                
                if (availableForSelection.length === 0) {
                    $button.text('All roles assigned').prop('disabled', true);
                } else {
                    $button.text('+ Add Role').prop('disabled', false);
                }
            }
            
            // Get available roles for selection
            function getAvailableRoles() {
                var checkedRoles = [];
                $('input[name="<?php echo esc_js($this->option_name); ?>[restricted_roles][]"]:checked').each(function() {
                    checkedRoles.push($(this).val());
                });
                
                // Get all roles that are already assigned in saved assignments
                var assignedRoleValues = [];
                $('.rbts-saved-assignment').each(function() {
                    var rolesText = $(this).find('p:first').text();
                    // Extract role values from "Roles: role1, role2, role3" format
                    if (rolesText.indexOf('Roles:') !== -1) {
                        var rolesPart = rolesText.split('Roles:')[1].trim();
                        var roleValues = rolesPart.split(',').map(function(role) {
                            return role.trim();
                        });
                        assignedRoleValues = assignedRoleValues.concat(roleValues);
                    }
                });
                
                var filtered = availableRoles.filter(function(role) {
                    var isNotSelected = selectedRoles.indexOf(role.value) === -1;
                    var isChecked = checkedRoles.indexOf(role.value) !== -1;
                    var isNotAssigned = assignedRoleValues.indexOf(role.value) === -1;
                    return isNotSelected && isChecked && isNotAssigned;
                });
                
                return filtered;
            }
            
            // Show role dropdown
            function showRoleDropdown(roles) {
                var html = '';
                if (roles.length === 0) {
                    html = '<div style="padding: 8px 12px; color: #666; font-style: italic;">No available roles to assign</div>';
                } else {
                    roles.forEach(function(role) {
                        html += '<div class="rbts-role-option" data-value="' + role.value + '" style="padding: 8px 12px; cursor: pointer; border-bottom: 1px solid #eee;">' + role.label + '</div>';
                    });
                }
                
                // Position dropdown at the bottom of the container
                var $container = $('#rbts-selected-roles-container');
                
                $('#rbts-available-roles')
                    .html(html)
                    .css({
                        'top': $container.innerHeight() - 10 + 'px'
                    })
                    .show();
            }
            
            // Update role tags display
            function updateRoleTags() {
                var html = '';
                selectedRoles.forEach(function(roleValue) {
                    var role = availableRoles.find(function(r) { return r.value === roleValue; });
                    if (role) {
                        html += '<span class="rbts-role-tag" style="display: inline-block; background: #0073aa; color: white; padding: 4px 8px; margin: 2px; border-radius: 3px; font-size: 12px;">' +
                                role.label + 
                                '<span class="rbts-role-tag-remove" data-value="' + roleValue + '" style="margin-left: 5px; cursor: pointer; font-weight: bold;">×</span>' +
                                '</span>';
                    }
                });
                $('#rbts-selected-roles-tags').html(html);
                
                // Show/hide save button based on selected roles
                if (selectedRoles.length > 0) {
                    $('#rbts-save-assignment').show();
                } else {
                    $('#rbts-save-assignment').hide();
                }
            }
            
            // Handle Save Assignment button
            $('#rbts-save-assignment').click(function() {
                if (selectedRoles.length === 0) {
                    // Show notification for no roles selected
                    var notification = '<div id="rbts-no-roles-notice" style="margin-top: 10px; padding: 10px; border-left: 4px solid #dc3232; background: #fbeaea; color: #a00;">' +
                                     '<p><strong>Error:</strong> Please select at least one role before saving the assignment.</p>' +
                                     '</div>';
                    
                    // Remove existing notice if any
                    $('#rbts-no-roles-notice').remove();
                    
                    // Add notice after the save button
                    $(this).after(notification);
                    
                    // Auto-hide after 5 seconds
                    setTimeout(function() {
                        $('#rbts-no-roles-notice').fadeOut();
                    }, 5000);
                    
                    return;
                }
                
                // Remove any existing error notices
                $('#rbts-no-roles-notice').remove();
                
                // Get current redirect settings
                var redirectType = $('input[name="<?php echo esc_js($this->option_name); ?>[redirect_type]"]:checked').val();
                var customPath = $('input[name="<?php echo esc_js($this->option_name); ?>[custom_path]"]').val();
                var fullUrl = $('input[name="<?php echo esc_js($this->option_name); ?>[full_url]"]').val();
                
                // Create assignment object
                var assignment = {
                    roles: selectedRoles.slice(), // Copy array
                    redirect_type: redirectType,
                    custom_path: customPath,
                    full_url: fullUrl
                };
                
                // Create a proper form data structure
                var formData = new FormData();
                formData.append('action', 'options.php');
                formData.append('option_page', 'rbts_admin_bar_control_group');
                formData.append('_wpnonce', $('input[name="_wpnonce"]').val());
                formData.append('_wp_http_referer', $('input[name="_wp_http_referer"]').val());
                
                // Add existing form data
                $('form input, form select').each(function() {
                    var $input = $(this);
                    var name = $input.attr('name');
                    var value = $input.val();
                    
                    if (name && name !== '_wpnonce' && name !== '_wp_http_referer') {
                        if ($input.attr('type') === 'checkbox' || $input.attr('type') === 'radio') {
                            if ($input.is(':checked')) {
                                formData.append(name, value);
                            }
                        } else {
                            formData.append(name, value);
                        }
                    }
                });
                
                // Add the new assignment
                formData.append('<?php echo esc_js($this->option_name); ?>[role_assignments][]', encodeURIComponent(JSON.stringify(assignment)));
                
                // Submit via AJAX to avoid page reload issues
                $.ajax({
                    url: 'options.php',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        // Show success message
                        var successNotice = '<div id="rbts-success-notice" style="margin-top: 10px; padding: 10px; border-left: 4px solid #00a32a; background: #f0f6fc; color: #00a32a;">' +
                                          '<p><strong>Success:</strong> Role assignment saved successfully! The page will reload to show your changes.</p>' +
                                          '</div>';
                        
                        $('#rbts-save-assignment').after(successNotice);
                        
                        // Reload page after short delay to show updated assignments
                        setTimeout(function() {
                            window.location.reload();
                        }, 2000);
                    },
                    error: function() {
                        // Show error message
                        var errorNotice = '<div id="rbts-error-notice" style="margin-top: 10px; padding: 10px; border-left: 4px solid #dc3232; background: #fbeaea; color: #a00;">' +
                                        '<p><strong>Error:</strong> Failed to save assignment. Please try again.</p>' +
                                        '</div>';
                        
                        $('#rbts-save-assignment').after(errorNotice);
                        
                        setTimeout(function() {
                            $('#rbts-error-notice').fadeOut();
                        }, 5000);
                    }
                });
            });
            
            // Handle Remove Assignment button
            $(document).on('click', '.rbts-remove-assignment', function() {
                var index = $(this).data('index');
                var $assignmentDiv = $(this).closest('.rbts-saved-assignment');
                
                // Create custom confirmation dialog
                var confirmDialog = '<div id="rbts-confirm-dialog" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 10000; display: flex; align-items: center; justify-content: center;">' +
                                  '<div style="background: white; padding: 20px; border-radius: 5px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); max-width: 400px; width: 90%;">' +
                                  '<h3 style="margin-top: 0; color: #23282d;">Confirm Removal</h3>' +
                                  '<p>Are you sure you want to remove this role assignment configuration?</p>' +
                                  '<div style="text-align: right; margin-top: 20px;">' +
                                  '<button type="button" id="rbts-confirm-cancel" class="button" style="margin-right: 10px;">Cancel</button>' +
                                  '<button type="button" id="rbts-confirm-remove" class="button" style="background: #d63638; color: white; border-color: #d63638;">Remove</button>' +
                                  '</div>' +
                                  '</div>' +
                                  '</div>';
                
                $('body').append(confirmDialog);
                
                // Handle cancel
                $('#rbts-confirm-cancel, #rbts-confirm-dialog').click(function(e) {
                    if (e.target === this) {
                        $('#rbts-confirm-dialog').remove();
                    }
                });
                
                // Handle confirm remove
                $('#rbts-confirm-remove').click(function() {
                    $('#rbts-confirm-dialog').remove();
                    
                    // Show loading state
                    $assignmentDiv.css('opacity', '0.5');
                    
                    // Create form data for removal
                    var formData = new FormData();
                    formData.append('action', 'options.php');
                    formData.append('option_page', 'rbts_admin_bar_control_group');
                    formData.append('_wpnonce', $('input[name="_wpnonce"]').val());
                    formData.append('_wp_http_referer', $('input[name="_wp_http_referer"]').val());
                    formData.append('remove_assignment', index);
                    
                    // Add existing form data
                    $('form input, form select').each(function() {
                        var $input = $(this);
                        var name = $input.attr('name');
                        var value = $input.val();
                        
                        if (name && name !== '_wpnonce' && name !== '_wp_http_referer') {
                            if ($input.attr('type') === 'checkbox' || $input.attr('type') === 'radio') {
                                if ($input.is(':checked')) {
                                    formData.append(name, value);
                                }
                            } else {
                                formData.append(name, value);
                            }
                        }
                    });
                    
                    // Submit removal via AJAX
                    $.ajax({
                        url: 'options.php',
                        type: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        success: function(response) {
                            // Show success message
                            var successNotice = '<div id="rbts-remove-success" style="margin: 10px 0; padding: 10px; border-left: 4px solid #00a32a; background: #f0f6fc; color: #00a32a;">' +
                                              '<p><strong>Success:</strong> Role assignment removed successfully! The page will reload to show your changes.</p>' +
                                              '</div>';
                            
                            $assignmentDiv.before(successNotice);
                            
                            // Reload page after short delay
                            setTimeout(function() {
                                window.location.reload();
                            }, 1500);
                        },
                        error: function() {
                            // Show error message and restore opacity
                            $assignmentDiv.css('opacity', '1');
                            var errorNotice = '<div id="rbts-remove-error" style="margin: 10px 0; padding: 10px; border-left: 4px solid #dc3232; background: #fbeaea; color: #a00;">' +
                                            '<p><strong>Error:</strong> Failed to remove assignment. Please try again.</p>' +
                                            '</div>';
                            
                            $assignmentDiv.before(errorNotice);
                            
                            setTimeout(function() {
                                $('#rbts-remove-error').fadeOut();
                            }, 5000);
                        }
                    });
                });
            });
            
            // Refresh available roles when assignments change
            function refreshAvailableRoles() {
                // This function can be called after assignments are added/removed
                // to update the dropdown options
                if ($('#rbts-available-roles').is(':visible')) {
                    var availableForSelection = getAvailableRoles();
                    showRoleDropdown(availableForSelection);
                }
            }
        });
        </script>
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
        echo '<style type="text/css">
            #wpadminbar { display: none !important; }
            html { margin-top: 0 !important; }
            * html body { margin-top: 0 !important; }
        </style>';
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
            $redirect_url = $this->get_redirect_url();
            wp_redirect($redirect_url);
            exit;
        }
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
                        return home_url('/' . ltrim($validated_path, '/') . '/');
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
new RBTS_AdminBarControl();