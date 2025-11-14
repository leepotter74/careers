/**
 * User Profile Frontend JavaScript
 */

jQuery(document).ready(function($) {
    
    // Tab switching for auth forms
    $('.bb-tab-button').on('click', function() {
        var tab = $(this).data('tab');
        
        // Update tab buttons
        $('.bb-tab-button').removeClass('active');
        $(this).addClass('active');
        
        // Update forms
        $('.bb-auth-form').removeClass('active');
        $('.bb-' + tab + '-form').addClass('active');
    });
    
    // Handle login form submission
    $('#bb-login-form').on('submit', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $button = $form.find('.bb-submit-btn');
        var $messages = $('.bb-auth-messages');
        
        // Show loading state
        $button.addClass('loading').prop('disabled', true);
        $messages.empty();
        
        // Create a temporary form and submit it to wp-login.php
        var tempForm = $('<form>', {
            method: 'POST',
            action: bbUserProfile.login_url,
            style: 'display: none;'
        });
        
        // Add form fields
        tempForm.append($('<input>', {type: 'hidden', name: 'log', value: $form.find('[name="log"]').val()}));
        tempForm.append($('<input>', {type: 'hidden', name: 'pwd', value: $form.find('[name="pwd"]').val()}));
        tempForm.append($('<input>', {type: 'hidden', name: 'wp-submit', value: 'Log In'}));
        tempForm.append($('<input>', {type: 'hidden', name: 'redirect_to', value: window.location.href}));
        tempForm.append($('<input>', {type: 'hidden', name: 'testcookie', value: '1'}));
        
        if ($form.find('[name="rememberme"]').is(':checked')) {
            tempForm.append($('<input>', {type: 'hidden', name: 'rememberme', value: 'forever'}));
        }
        
        // Append to body and submit
        $('body').append(tempForm);
        tempForm.submit();
    });
    
    // Handle registration form submission
    $('#bb-register-form').on('submit', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $button = $form.find('.bb-submit-btn');
        var $messages = $('.bb-auth-messages');
        
        // Show loading state
        $button.addClass('loading').prop('disabled', true);
        $messages.empty();
        
        // Collect form data
        var formData = $form.serialize();
        formData += '&action=bb_register_user';
        
        $.ajax({
            url: bbUserProfile.ajax_url,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    showMessage(response.data, 'success');
                    $form[0].reset();
                    
                    // Show login tab after successful registration
                    setTimeout(function() {
                        $('.bb-tab-button[data-tab="login"]').click();
                        showMessage('Please check your email for login details, then log in above.', 'info');
                    }, 2000);
                } else {
                    showMessage(response.data || 'Registration failed. Please try again.', 'error');
                }
            },
            error: function() {
                showMessage('Network error occurred. Please try again.', 'error');
            },
            complete: function() {
                $button.removeClass('loading').prop('disabled', false);
            }
        });
    });
    
    // Handle profile update form submission
    $('#bb-user-profile-form').on('submit', function(e) {
        
        e.preventDefault();
        
        var $form = $(this);
        var $button = $form.find('.bb-submit-btn');
        var $messages = $('.bb-profile-messages');
        
        // Show loading state
        $button.addClass('loading').prop('disabled', true);
        $messages.empty();
        
        // Collect form data
        var formData = $form.serialize();
        formData += '&action=bb_update_user_profile';
        
        $.ajax({
            url: bbUserProfile.ajax_url,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    showMessage(response.data, 'success', 'profile');
                } else {
                    showMessage(response.data || 'Failed to update profile. Please try again.', 'error', 'profile');
                }
            },
            error: function() {
                showMessage('Network error occurred. Please try again.', 'error', 'profile');
            },
            complete: function() {
                $button.removeClass('loading').prop('disabled', false);
            }
        });
    });
    
    // Helper function to show messages
    function showMessage(message, type, container) {
        var $container = container === 'profile' ? $('.bb-profile-messages') : $('.bb-auth-messages');
        var $message = $('<div class="bb-message ' + type + '">' + message + '</div>');
        
        $container.empty().append($message);
        
        // Auto-hide success messages after 5 seconds
        if (type === 'success') {
            setTimeout(function() {
                $message.fadeOut();
            }, 5000);
        }
        
        // Scroll to message
        $('html, body').animate({
            scrollTop: $message.offset().top - 100
        }, 300);
    }
    
    // Auto-populate form fields if user is logged in (for custom forms)
    if (bbUserProfile.is_logged_in && window.bbAutoPopulate) {
        populateFormsWithUserData();
    }
    
    function populateFormsWithUserData() {
        $.ajax({
            url: bbUserProfile.ajax_url,
            type: 'POST',
            data: {
                action: 'bb_get_user_application_data',
                nonce: bbUserProfile.nonce
            },
            success: function(response) {
                if (response.success) {
                    var userData = response.data;
                    
                    // Map common field names to user data
                    var fieldMappings = {
                        'first-name': userData.first_name,
                        'last-name': userData.last_name,
                        'your-name': userData.name,
                        'name': userData.name,
                        'your-email': userData.email,
                        'email': userData.email,
                        'your-phone': userData.phone,
                        'phone': userData.phone,
                        'address': userData.address,
                        'city': userData.city,
                        'county': userData.county,
                        'postcode': userData.postcode,
                        'country': userData.country,
                        'current-position': userData.current_position,
                        'position': userData.current_position,
                        'experience': userData.experience,
                        'qualifications': userData.qualifications,
                        'availability': userData.availability
                    };
                    
                    // Fill form fields
                    for (var fieldName in fieldMappings) {
                        if (fieldMappings[fieldName]) {
                            var $field = $('input[name="' + fieldName + '"], textarea[name="' + fieldName + '"], select[name="' + fieldName + '"]');
                            if ($field.length && !$field.val()) {
                                $field.val(fieldMappings[fieldName]);
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Validation helpers
    function validateEmail(email) {
        var re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }
    
    function validatePhone(phone) {
        var re = /^[\d\s\-\+\(\)\.]{10,}$/;
        return re.test(phone.replace(/\s/g, ''));
    }
    
    // Form validation
    $('input[type="email"]').on('blur', function() {
        var $this = $(this);
        var email = $this.val();
        
        if (email && !validateEmail(email)) {
            $this.css('border-color', '#dc3232');
            if (!$this.siblings('.validation-error').length) {
                $this.after('<span class="validation-error" style="color: #dc3232; font-size: 12px;">Please enter a valid email address</span>');
            }
        } else {
            $this.css('border-color', '#ddd');
            $this.siblings('.validation-error').remove();
        }
    });
    
    $('input[type="tel"]').on('blur', function() {
        var $this = $(this);
        var phone = $this.val();
        
        if (phone && !validatePhone(phone)) {
            $this.css('border-color', '#dc3232');
            if (!$this.siblings('.validation-error').length) {
                $this.after('<span class="validation-error" style="color: #dc3232; font-size: 12px;">Please enter a valid phone number</span>');
            }
        } else {
            $this.css('border-color', '#ddd');
            $this.siblings('.validation-error').remove();
        }
    });
    
    // Admin application viewing
    $(document).on('click', '.bb-view-application', function() {
        var $button = $(this);
        var applicationId = $button.data('application-id');
        var nonce = typeof ajaxurl !== 'undefined' ? $('input[name="_wpnonce"]').val() : bbUserProfile.nonce;
        var url = typeof ajaxurl !== 'undefined' ? ajaxurl : bbUserProfile.ajax_url;
        
        $.ajax({
            url: url,
            type: 'POST',
            data: {
                action: 'bb_get_application_details',
                nonce: nonce,
                application_id: applicationId
            },
            success: function(response) {
                if (response.success) {
                    showApplicationModal(response.data);
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function() {
                alert('Network error occurred. Please try again.');
            }
        });
    });
    
    // Handle job alert creation form submission
    $(document).on('submit', '#bb-create-alert-form', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $button = $form.find('.bb-submit-btn');
        var $messages = $('.bb-alert-messages');
        
        // Show loading state
        $button.addClass('loading').prop('disabled', true);
        $messages.empty();
        
        // Collect form data
        var formData = $form.serialize();
        formData += '&action=bb_create_simple_alert';
        
        $.ajax({
            url: bbUserProfile.ajax_url,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    showAlertMessage(response.data.message, 'success');
                    $form[0].reset();
                    
                    // Add new alert to the existing alerts list
                    if (response.data.alert) {
                        addAlertToList(response.data.alert);
                    }
                } else {
                    showAlertMessage(response.data || 'Failed to create job alert.', 'error');
                }
            },
            error: function() {
                showAlertMessage('Network error occurred. Please try again.', 'error');
            },
            complete: function() {
                $button.removeClass('loading').prop('disabled', false);
            }
        });
    });
    
    // Handle job alert management actions (delete, toggle)
    $(document).on('click', '.bb-alert-action-btn', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var $card = $button.closest('.bb-alert-card');
        var alertId = $button.data('alert-id');
        var actionType = $button.data('action');
        var $messages = $('.bb-alert-messages');
        
        // Confirmation for delete
        if (actionType === 'delete') {
            if (!confirm('Are you sure you want to delete this job alert?')) {
                return;
            }
        }
        
        // Show loading state
        $button.prop('disabled', true);
        
        $.ajax({
            url: bbUserProfile.ajax_url,
            type: 'POST',
            data: {
                action: 'bb_manage_simple_alert',
                nonce: bbUserProfile.alert_nonce,
                alert_id: alertId,
                action_type: actionType
            },
            success: function(response) {
                if (response.success) {
                    showAlertMessage(response.data.message, 'success');
                    
                    if (actionType === 'delete') {
                        // Remove the alert card
                        $card.fadeOut(300, function() {
                            $(this).remove();
                            // Show empty state if no alerts left
                            if ($('.bb-alert-card').length === 0) {
                                $('.bb-existing-alerts').html('<p><em>No job alerts created yet.</em></p>');
                            }
                        });
                    } else if (actionType === 'toggle') {
                        // Toggle the inactive class
                        $card.toggleClass('inactive');
                        var $toggleBtn = $card.find('.btn-toggle');
                        $toggleBtn.text($card.hasClass('inactive') ? 'Enable' : 'Disable');
                    }
                } else {
                    showAlertMessage(response.data || 'Action failed.', 'error');
                }
            },
            error: function() {
                showAlertMessage('Network error occurred. Please try again.', 'error');
            },
            complete: function() {
                $button.prop('disabled', false);
            }
        });
    });
    
    // Helper function to show alert messages
    function showAlertMessage(message, type) {
        var $container = $('.bb-alert-messages');
        if ($container.length === 0) {
            $container = $('<div class="bb-alert-messages"></div>');
            $('.bb-simple-alerts').prepend($container);
        }
        
        var $message = $('<div class="bb-message ' + type + '">' + message + '</div>');
        $container.empty().append($message);
        
        // Auto-hide success messages after 5 seconds
        if (type === 'success') {
            setTimeout(function() {
                $message.fadeOut();
            }, 5000);
        }
        
        // Scroll to message
        $('html, body').animate({
            scrollTop: $message.offset().top - 100
        }, 300);
    }
    
    // Helper function to add new alert to the list
    function addAlertToList(alert) {
        var $existingAlerts = $('.bb-existing-alerts');
        
        // If no existing alerts section, create it
        if ($existingAlerts.length === 0) {
            var alertsSection = '<div class="bb-existing-alerts">' +
                '<h5>Your Active Alerts</h5>' +
                '</div>';
            $('.bb-create-simple-alert').before(alertsSection);
            $existingAlerts = $('.bb-existing-alerts');
        }
        
        // Format current date for display
        var now = new Date();
        var dateStr = now.toLocaleDateString('en-GB', { 
            day: 'numeric', 
            month: 'short', 
            year: 'numeric' 
        });
        
        var alertHtml = '<div class="bb-alert-card ' + (alert.is_active ? 'active' : 'inactive') + '" data-alert-id="' + alert.ID + '">' +
            '<div class="alert-details">' +
                '<h6>' + alert.post_title + '</h6>';
                
        // Only show keywords if they exist
        if (alert.keywords && alert.keywords.trim()) {
            alertHtml += '<p><strong>Keywords:</strong> ' + alert.keywords + '</p>';
        }
        
        alertHtml += '<p><strong>Frequency:</strong> ' + alert.frequency.charAt(0).toUpperCase() + alert.frequency.slice(1) + '</p>' +
                '<p><small>Created: ' + dateStr + '</small></p>' +
            '</div>' +
            '<div class="alert-actions">' +
                '<button type="button" class="btn-toggle bb-alert-action-btn" data-alert-id="' + alert.ID + '" data-action="toggle">' +
                    (alert.is_active ? 'Disable' : 'Enable') +
                '</button>' +
                '<button type="button" class="btn-delete bb-alert-action-btn" data-alert-id="' + alert.ID + '" data-action="delete">Delete</button>' +
            '</div>' +
        '</div>';
        
        $existingAlerts.append(alertHtml);
    }
    
    // Helper function to show messages
    function showMessage(message, type, container) {
        var $container = container === 'profile' ? $('.bb-profile-messages') : $('.bb-auth-messages');
        var $message = $('<div class="bb-message ' + type + '">' + message + '</div>');
        
        $container.empty().append($message);
        
        // Auto-hide success messages after 5 seconds
        if (type === 'success') {
            setTimeout(function() {
                $message.fadeOut();
            }, 5000);
        }
        
        // Scroll to message
        $('html, body').animate({
            scrollTop: $message.offset().top - 100
        }, 300);
    }
});

// Application viewing functionality (global scope for onclick)
function viewMyApplication(button) {
    var applicationId = jQuery(button).data('application-id');
    
    console.log('Sending AJAX with:', {
        url: bbUserProfile.ajax_url,
        nonce: bbUserProfile.nonce,
        application_id: applicationId
    });
    
    jQuery.ajax({
        url: bbUserProfile.ajax_url,
        type: 'POST',
        data: {
            action: 'bb_get_application_details',
            nonce: bbUserProfile.nonce,
            application_id: applicationId
        },
        success: function(response) {
            console.log('AJAX Response:', response);
            if (response.success) {
                showApplicationModal(response.data);
            } else {
                alert('Error: ' + (response.data || 'Unknown error'));
            }
        },
        error: function(xhr, status, error) {
            console.log('AJAX Error:', xhr, status, error);
            alert('Network error occurred. Please try again.');
        }
    });
}

function showApplicationModal(data) {
    var title = data.status ? 'Application Details' : 'Your Application'; // Admin vs user title
    var modalHtml = '<div id="bb-application-modal" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 10000; display: flex; align-items: center; justify-content: center;">' +
        '<div style="background: white; padding: 30px; border-radius: 8px; width: 90%; max-width: 800px; max-height: 80vh; overflow-y: auto; position: relative;">' +
            '<button onclick="closeApplicationModal()" style="position: absolute; top: 15px; right: 20px; background: none; border: none; font-size: 28px; cursor: pointer; color: #666;">&times;</button>' +
            '<h3 style="margin: 0 0 20px 0; color: #333; font-size: 24px;">' + title + '</h3>' +
            '<div style="margin-bottom: 15px; font-size: 16px;"><strong>Job:</strong> ' + data.job_title + '</div>' +
            '<div style="margin-bottom: 20px; font-size: 16px;"><strong>Applied:</strong> ' + data.created_date + '</div>';
    
    // Only show status for admin users (not for frontend users)
    if (data.status && typeof ajaxurl !== 'undefined') {
        modalHtml += '<div style="margin-bottom: 20px; font-size: 16px;"><strong>Status:</strong> <span style="text-transform: uppercase; font-weight: bold;">' + data.status + '</span></div>';
    }
    
    if (data.application_data && data.application_data.fields) {
        modalHtml += '<h4 style="margin: 20px 0 15px 0; color: #333; font-size: 20px; border-bottom: 2px solid #0073aa; padding-bottom: 10px;">Application Details</h4>';
        
        // Show Gravity Forms field data properly - filter out system fields
        var fields = data.application_data.fields;
        for (var fieldId in fields) {
            var field = fields[fieldId];
            
            // Skip system/meta fields and empty values
            if (!field.value || !field.label) continue;
            if (field.label.toLowerCase().includes('form') || 
                field.label.toLowerCase().includes('id') || 
                field.label.toLowerCase().includes('title') ||
                field.label.toLowerCase().includes('meta')) continue;
            
            modalHtml += '<div style="margin-bottom: 15px; padding: 10px; background: #f8f9fa; border-radius: 4px;">';
            modalHtml += '<strong style="color: #333; display: block; margin-bottom: 5px;">' + field.label + ':</strong>';
            modalHtml += '<div style="color: #555; line-height: 1.5;">' + field.value + '</div>';
            modalHtml += '</div>';
        }
    }
    
    modalHtml += '<div style="text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;"><button onclick="closeApplicationModal()" style="background: #0073aa; color: white; border: none; padding: 12px 30px; border-radius: 4px; cursor: pointer; font-size: 16px;">Close</button></div>' +
        '</div></div>';
    
    jQuery('body').append(modalHtml);
}

function closeApplicationModal() {
    jQuery('#bb-application-modal').remove();
}

// Direct application viewing (no AJAX needed)
function viewMyApplicationDirect(button) {
    var applicationData = JSON.parse(jQuery(button).attr('data-application-data'));
    showApplicationModal(applicationData);
}