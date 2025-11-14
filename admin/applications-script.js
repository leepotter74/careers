/**
 * Application Manager Admin Scripts
 */

jQuery(document).ready(function($) {
    
    // Handle select all checkbox
    $('#select-all').on('change', function() {
        $('input[name="application_ids[]"]').prop('checked', this.checked);
    });
    
    // Handle individual checkboxes
    $('input[name="application_ids[]"]').on('change', function() {
        var allChecked = $('input[name="application_ids[]"]:checked').length === $('input[name="application_ids[]"]').length;
        $('#select-all').prop('checked', allChecked);
    });
    
    // Handle status changes
    $('.status-select').on('change', function() {
        var $select = $(this);
        var appId = $select.data('app-id');
        var newStatus = $select.val();
        var $row = $select.closest('tr');
        
        $select.prop('disabled', true);
        
        $.ajax({
            url: bbAppManager.ajax_url,
            type: 'POST',
            data: {
                action: 'bb_update_application_status',
                app_id: appId,
                status: newStatus,
                nonce: bbAppManager.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Update status badge
                    var $badge = $row.find('.status-badge');
                    $badge.removeClass('status-pending status-reviewed status-shortlisted status-rejected');
                    $badge.addClass('status-' + newStatus);
                    $badge.text(getStatusLabel(newStatus));
                    
                    // Show success message
                    showNotice(response.data, 'success');
                } else {
                    showNotice(response.data || 'Failed to update status', 'error');
                    // Revert the select
                    $select.val($select.data('original-value') || 'pending');
                }
            },
            error: function() {
                showNotice('Network error occurred', 'error');
                $select.val($select.data('original-value') || 'pending');
            },
            complete: function() {
                $select.prop('disabled', false);
            }
        });
    });
    
    // Store original values for revert functionality
    $('.status-select').each(function() {
        $(this).data('original-value', $(this).val());
    });
    
    // Handle view application button
    $('.view-application').on('click', function() {
        var $button = $(this);
        var appId = $button.data('app-id');
        
        // Show loading state
        $button.prop('disabled', true).text('Loading...');
        $('#application-details').html('<div class="loading-spinner"><p>Loading application details...</p></div>');
        $('#modal-title').text('Application Details');
        $('#application-modal').show();
        
        // Fetch application details via AJAX
        $.ajax({
            url: bbAppManager.ajax_url,
            type: 'POST',
            data: {
                action: 'bb_get_application_details',
                app_id: appId,
                nonce: bbAppManager.nonce
            },
            success: function(response) {
                if (response.success) {
                    var app = response.data;
                    var appliedDate = new Date(app.created_date).toLocaleDateString();
                    var updatedDate = new Date(app.updated_date).toLocaleDateString();
                    
                    var detailsHtml = '<div class="application-detail">' +
                        '<div class="detail-section">' +
                            '<h3>Applicant Information</h3>' +
                            '<p><strong>Name:</strong> ' + app.applicant_name + '</p>' +
                            '<p><strong>Email:</strong> <a href="mailto:' + app.applicant_email + '">' + app.applicant_email + '</a></p>';
                    
                    if (app.phone) {
                        detailsHtml += '<p><strong>Phone:</strong> <a href="tel:' + app.phone + '">' + app.phone + '</a></p>';
                    }
                    
                    detailsHtml += '</div>' +
                        '<div class="detail-section">' +
                            '<h3>Application Details</h3>' +
                            '<p><strong>Position:</strong> <a href="' + app.job_url + '" target="_blank">' + app.job_title + '</a></p>' +
                            '<p><strong>Status:</strong> ' + app.status_label + '</p>' +
                            '<p><strong>Applied:</strong> ' + appliedDate + '</p>' +
                            '<p><strong>Last Updated:</strong> ' + updatedDate + '</p>' +
                        '</div>' +
                        '<div class="detail-section">' +
                            '<h3>Form Submission Details</h3>' +
                            app.formatted_data +
                        '</div>' +
                    '</div>';
                    
                    $('#application-details').html(detailsHtml);
                    $('#modal-title').text('Application: ' + app.applicant_name);
                } else {
                    $('#application-details').html('<p class="error">Failed to load application details: ' + (response.data || 'Unknown error') + '</p>');
                }
            },
            error: function() {
                $('#application-details').html('<p class="error">Network error occurred while loading application details.</p>');
            },
            complete: function() {
                $button.prop('disabled', false).text('View');
            }
        });
    });
    
    // Handle modal close
    $('.close-modal').on('click', function() {
        $('#application-modal').hide();
    });
    
    // Close modal on outside click
    $(window).on('click', function(event) {
        if (event.target.id === 'application-modal') {
            $('#application-modal').hide();
        }
    });
    
    // Handle export all button
    $('#export-all-btn').on('click', function() {
        var currentUrl = new URL(window.location);
        var params = new URLSearchParams(currentUrl.search);
        params.set('action', 'bb_export_applications');
        
        window.location.href = bbAppManager.ajax_url + '?' + params.toString();
    });
    
    // Bulk actions confirmation
    window.confirmBulkAction = function() {
        var action = $('select[name="bulk_action"]').val();
        var selectedCount = $('input[name="application_ids[]"]:checked').length;
        
        if (!action) {
            alert('Please select an action.');
            return false;
        }
        
        if (selectedCount === 0) {
            alert('Please select at least one application.');
            return false;
        }
        
        var message = '';
        if (action === 'delete') {
            message = bbAppManager.confirm_delete.replace('%d', selectedCount);
        } else {
            message = bbAppManager.confirm_status.replace('%d', selectedCount);
        }
        
        return confirm(message);
    };
    
    // Helper function to get status label
    function getStatusLabel(status) {
        var labels = {
            'pending': 'Pending',
            'reviewed': 'Reviewed',
            'shortlisted': 'Shortlisted',
            'rejected': 'Rejected'
        };
        return labels[status] || status.charAt(0).toUpperCase() + status.slice(1);
    }
    
    // Helper function to show admin notices
    function showNotice(message, type) {
        var noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
        var $notice = $('<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>');
        
        $('.wrap h1').after($notice);
        
        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            $notice.fadeOut();
        }, 5000);
        
        // Handle manual dismiss
        $notice.find('.notice-dismiss').on('click', function() {
            $notice.remove();
        });
    }
    
    // Handle URL success/error messages
    var urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('success')) {
        showNotice(decodeURIComponent(urlParams.get('success')), 'success');
    }
    if (urlParams.get('error')) {
        var errorMessages = {
            'no_selection': 'Please select applications and an action.',
            'no_data': 'No applications found to export.'
        };
        var errorKey = urlParams.get('error');
        var message = errorMessages[errorKey] || decodeURIComponent(errorKey);
        showNotice(message, 'error');
    }
});

/* Additional CSS for application details modal */
jQuery(document).ready(function($) {
    $('head').append(`
        <style>
        .application-detail .detail-section {
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .application-detail .detail-section:last-child {
            border-bottom: none;
        }
        
        .application-detail h3 {
            margin: 0 0 15px 0;
            color: #333;
            font-size: 16px;
        }
        
        .application-detail p {
            margin: 8px 0;
            line-height: 1.5;
        }
        
        .application-detail strong {
            display: inline-block;
            min-width: 100px;
            color: #555;
        }
        
        .notice {
            margin: 15px 0;
        }
        
        .loading-spinner {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        
        .error {
            color: #dc3232;
            font-style: italic;
        }
        
        .form-info, .form-fields {
            margin: 15px 0;
        }
        
        .form-info h4, .form-fields h4 {
            margin: 0 0 10px 0;
            color: #0073aa;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .form-field {
            margin: 10px 0;
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .form-field:last-child {
            border-bottom: none;
        }
        
        .form-field strong {
            display: inline-block;
            min-width: 140px;
            color: #333;
            font-weight: 600;
        }
        
        .field-value {
            color: #555;
        }
        
        .textarea-value {
            background: #f9f9f9;
            padding: 10px;
            border-radius: 4px;
            margin-top: 5px;
            font-family: inherit;
        }
        
        .form-data-raw pre {
            background: #f4f4f4;
            padding: 15px;
            border-radius: 4px;
            font-size: 12px;
            max-height: 300px;
            overflow-y: auto;
        }
        </style>
    `);
});