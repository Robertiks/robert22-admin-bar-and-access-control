
jQuery(document).ready(function($) {
    // Show/hide redirect options based on selection
    $('input[name="' + r22AdminVars.optionName + '[redirect_type]"]').change(function() {
        $('.rbts-custom-path-input, .rbts-full-url-input').hide();
        if ($(this).val() === 'custom_path') {
            $('.rbts-custom-path-input').show();
        } else if ($(this).val() === 'full_url') {
            $('.rbts-full-url-input').show();
        }
    });
    
    // Initialize visibility based on current selection without triggering change
    var currentSelection = $('input[name="' + r22AdminVars.optionName + '[redirect_type]"]:checked').val();
    if (currentSelection === 'custom_path') {
        $('.rbts-custom-path-input').show();
        $('.rbts-full-url-input').hide();
    } else if (currentSelection === 'full_url') {
        $('.rbts-full-url-input').show();
        $('.rbts-custom-path-input').hide();
    } else {
        $('.rbts-custom-path-input, .rbts-full-url-input').hide();
    }
    
    // Advanced Role Assignment Functionality
    var selectedRoles = [];
    var availableRoles = [];
    
    // Get all available roles (excluding administrator)
    $('input[name="' + r22AdminVars.optionName + '[restricted_roles][]"]').each(function() {
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
                var checkedRoles = $('input[name="' + r22AdminVars.optionName + '[restricted_roles][]"]:checked');
                
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
                $('input[name="' + r22AdminVars.optionName + '[restricted_roles][]"]:checked').each(function() {
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
                var redirectType = $('input[name="' + r22AdminVars.optionName + '[redirect_type]"]:checked').val();
                var customPath = $('input[name="' + r22AdminVars.optionName + '[custom_path]"]').val();
                var fullUrl = $('input[name="' + r22AdminVars.optionName + '[full_url]"]').val();
                
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
                formData.append('option_page', 'r22_admin_bar_control_group');
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
                formData.append(r22AdminVars.optionName + '[role_assignments][]', encodeURIComponent(JSON.stringify(assignment)));
                
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
                    formData.append('option_page', 'r22_admin_bar_control_group');
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
