jQuery(document).ready(function($) {
    // Show/hide redirect options based on selection
    $('input[name*="[redirect_type]"]').change(function() {
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
    $('input[name*="[restricted_roles][]"]').each(function() {
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
        var checkedRoles = $('input[name*="[restricted_roles][]"]:checked');
        
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
    
    // Handle role dropdown selection
    $(document).on('change', '#rbts-available-roles', function() {
        var selectedRole = $(this).val();
        var selectedRoleLabel = $(this).find('option:selected').text();
        
        if (selectedRole && selectedRoles.indexOf(selectedRole) === -1) {
            selectedRoles.push(selectedRole);
            updateRoleTags();
            updateAddRoleButton();
            
            // Hide dropdown after selection
            $('#rbts-role-dropdown').hide();
        }
    });
    
    // Handle role tag removal
    $(document).on('click', '.rbts-role-tag .rbts-remove-role', function(e) {
        e.preventDefault();
        var roleToRemove = $(this).data('role');
        var index = selectedRoles.indexOf(roleToRemove);
        
        if (index > -1) {
            selectedRoles.splice(index, 1);
            updateRoleTags();
            updateAddRoleButton();
        }
    });
    
    // Handle assignment form submission
    $('#rbts-assignment-form').submit(function(e) {
        e.preventDefault();
        
        var formData = {
            roles: selectedRoles,
            redirect_type: $('input[name="rbts_redirect_type"]:checked').val(),
            custom_path: $('#rbts_custom_path').val(),
            full_url: $('#rbts_full_url').val(),
            confirmation_message: $('#rbts_confirmation_message').val()
        };
        
        // Add assignment to the list
        addAssignmentToList(formData);
        
        // Reset form
        resetAssignmentForm();
    });
    
    // Handle assignment removal
    $(document).on('click', '.rbts-remove-assignment', function(e) {
        e.preventDefault();
        $(this).closest('.rbts-assignment-item').remove();
        updateAssignmentIndices();
        updateAddRoleButton();
    });
    
    // Helper functions
    function updateRoleTags() {
        var $container = $('#rbts-selected-roles');
        $container.empty();
        
        selectedRoles.forEach(function(roleValue) {
            var roleLabel = getRoleLabel(roleValue);
            var $tag = $('<span class="rbts-role-tag">' + roleLabel + ' <a href="#" class="rbts-remove-role" data-role="' + roleValue + '">×</a></span>');
            $container.append($tag);
        });
        
        // Update hidden input
        $('#rbts-selected-roles-input').val(selectedRoles.join(','));
    }
    
    function updateAddRoleButton() {
        var $button = $('#rbts-add-role-btn');
        var availableForSelection = getAvailableRoles();
        
        if (availableForSelection.length === 0) {
            $button.text('All roles assigned').prop('disabled', true);
        } else {
            $button.text('Add Role').prop('disabled', false);
        }
    }
    
    function getAvailableRoles() {
        var assignedRoles = getAssignedRoles();
        return availableRoles.filter(function(role) {
            return selectedRoles.indexOf(role.value) === -1 && 
                   assignedRoles.indexOf(role.value) === -1;
        });
    }
    
    function getAssignedRoles() {
        var assigned = [];
        $('.rbts-assignment-item').each(function() {
            var roles = $(this).find('.rbts-assignment-roles').data('roles');
            if (roles) {
                assigned = assigned.concat(roles.split(','));
            }
        });
        return assigned;
    }
    
    function showRoleDropdown(availableRoles) {
        var $dropdown = $('#rbts-role-dropdown');
        var $select = $('#rbts-available-roles');
        
        $select.empty().append('<option value="">Select a role...</option>');
        
        availableRoles.forEach(function(role) {
            $select.append('<option value="' + role.value + '">' + role.label + '</option>');
        });
        
        $dropdown.show();
    }
    
    function getRoleLabel(roleValue) {
        var role = availableRoles.find(function(r) {
            return r.value === roleValue;
        });
        return role ? role.label : roleValue;
    }
    
    function addAssignmentToList(formData) {
        var roleLabels = formData.roles.map(function(role) {
            return getRoleLabel(role);
        }).join(', ');
        
        var redirectText = '';
        if (formData.redirect_type === 'custom_path') {
            redirectText = 'Custom Path: ' + formData.custom_path;
        } else if (formData.redirect_type === 'full_url') {
            redirectText = 'Full URL: ' + formData.full_url;
        } else {
            redirectText = 'Default redirect';
        }
        
        var assignmentHtml = '<div class="rbts-assignment-item">' +
            '<div class="rbts-assignment-roles" data-roles="' + formData.roles.join(',') + '">' +
                '<strong>Roles:</strong> ' + roleLabels +
            '</div>' +
            '<div class="rbts-assignment-redirect">' +
                '<strong>Redirect:</strong> ' + redirectText +
            '</div>' +
            (formData.confirmation_message ? 
                '<div class="rbts-assignment-message"><strong>Message:</strong> ' + formData.confirmation_message + '</div>' : '') +
            '<div class="rbts-assignment-actions">' +
                '<a href="#" class="rbts-remove-assignment">Remove</a>' +
            '</div>' +
            // Hidden inputs for form submission
            '<input type="hidden" name="rbts_assignments[roles][]" value="' + formData.roles.join(',') + '">' +
            '<input type="hidden" name="rbts_assignments[redirect_type][]" value="' + formData.redirect_type + '">' +
            '<input type="hidden" name="rbts_assignments[custom_path][]" value="' + formData.custom_path + '">' +
            '<input type="hidden" name="rbts_assignments[full_url][]" value="' + formData.full_url + '">' +
            '<input type="hidden" name="rbts_assignments[confirmation_message][]" value="' + formData.confirmation_message + '">' +
        '</div>';
        
        $('#rbts-assignments-list').append(assignmentHtml);
    }
    
    function resetAssignmentForm() {
        selectedRoles = [];
        updateRoleTags();
        updateAddRoleButton();
        
        $('input[name="rbts_redirect_type"]').prop('checked', false);
        $('#rbts_custom_path, #rbts_full_url, #rbts_confirmation_message').val('');
        $('.rbts-custom-path-input, .rbts-full-url-input').hide();
        $('#rbts-role-dropdown').hide();
    }
    
    function updateAssignmentIndices() {
        // Update indices if needed for proper form submission
        $('#rbts-assignments-list .rbts-assignment-item').each(function(index) {
            $(this).find('input[type="hidden"]').each(function() {
                var name = $(this).attr('name');
                if (name) {
                    // Update array indices in name attributes
                    var newName = name.replace(/\[\d+\]/, '[' + index + ']');
                    $(this).attr('name', newName);
                }
            });
        });
    }
    
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