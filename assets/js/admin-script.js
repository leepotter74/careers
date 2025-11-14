/**
 * Recruitment Manager Admin JavaScript
 */

(function($) {
    'use strict';
    
    // Initialize when document is ready
    $(document).ready(function() {
        RecruitmentAdmin.init();
    });
    
    // Main admin object
    window.RecruitmentAdmin = {
        
        // Initialize admin functionality
        init: function() {
            this.initDashboard();
            this.initApplicationsPage();
            this.initSettingsPage();
            this.initJobPostType();
            this.initModals();
            this.initNotifications();
        },
        
        // Dashboard functionality
        initDashboard: function() {
            if (!$('.bb-recruitment-dashboard').length) return;
            
            // Quick action buttons
            $('.bb-quick-action').on('click', function(e) {
                const action = $(this).data('action');
                RecruitmentAdmin.handleQuickAction(action);
            });
            
            // Stat cards click handlers
            $('.bb-stat-card').on('click', function() {
                const url = $(this).data('url');
                if (url) {
                    window.location.href = url;
                }
            });
        },
        
        // Applications page functionality
        initApplicationsPage: function() {
            if (!$('.bb-applications-page').length) return;
            
            // Status change handlers
            $('.bb-status-select').on('change', function() {
                RecruitmentAdmin.updateApplicationStatus($(this));
            });
            
            // View application handlers
            $('.bb-view-application').on('click', function() {
                const applicationId = $(this).data('application-id');
                RecruitmentAdmin.viewApplication(applicationId);
            });
            
            // Delete application handlers
            $('.bb-delete-application').on('click', function() {
                const applicationId = $(this).data('application-id');
                RecruitmentAdmin.deleteApplication(applicationId, $(this));
            });
            
            // Export applications
            $('#bb-export-applications').on('click', function() {
                RecruitmentAdmin.exportApplications();
            });
            
            // Bulk actions
            $('.bb-bulk-action').on('change', function() {
                RecruitmentAdmin.handleBulkAction($(this));
            });
            
            // Search functionality
            let searchTimeout;
            $('#bb-application-search').on('input', function() {
                const $input = $(this);
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(function() {
                    RecruitmentAdmin.searchApplications($input.val());
                }, 500);
            });
        },
        
        // Settings page functionality
        initSettingsPage: function() {
            if (!$('.bb-recruitment-settings').length) return;
            
            // Tab switching
            $('.bb-tab-link').on('click', function(e) {
                e.preventDefault();
                RecruitmentAdmin.switchTab($(this));
            });
            
            // Field dependency handling
            RecruitmentAdmin.initFieldDependencies();
            
            // Settings validation
            $('form').on('submit', function(e) {
                if (!RecruitmentAdmin.validateSettings()) {
                    e.preventDefault();
                }
            });
        },
        
        // Job post type functionality
        initJobPostType: function() {
            // Application method change handler
            $('#application_method').on('change', function() {
                RecruitmentAdmin.toggleApplicationFields($(this).val());
            }).trigger('change');
            
            // Date validation
            $('#job_closing_date').on('change', function() {
                RecruitmentAdmin.validateClosingDate($(this));
            });
            
            // Featured job toggle
            $('#job_featured').on('change', function() {
                RecruitmentAdmin.toggleFeaturedJob($(this).is(':checked'));
            });
        },
        
        // Modal functionality
        initModals: function() {
            // Modal close handlers
            $('.bb-modal-close, .bb-modal-overlay').on('click', function() {
                RecruitmentAdmin.closeModal();
            });
            
            // Escape key to close modal
            $(document).on('keyup', function(e) {
                if (e.keyCode === 27) { // Escape key
                    RecruitmentAdmin.closeModal();
                }
            });
        },
        
        // Notification system
        initNotifications: function() {
            // Auto-dismiss notifications
            $('.bb-notice.is-dismissible').each(function() {
                const $notice = $(this);
                setTimeout(function() {
                    $notice.fadeOut();
                }, 5000);
            });
        },
        
        // Handle quick actions
        handleQuickAction: function(action) {
            switch(action) {
                case 'create_job':
                    window.location.href = bbRecruitment.urls.newJob;
                    break;
                case 'view_applications':
                    window.location.href = bbRecruitment.urls.applications;
                    break;
                case 'export_data':
                    this.exportAllData();
                    break;
            }
        },
        
        // Update application status
        updateApplicationStatus: function($select) {
            const applicationId = $select.data('application-id');
            const newStatus = $select.val();
            const originalStatus = $select.data('original-status') || $select.find('option:first').val();
            
            if (!confirm(bbRecruitment.strings.confirm_status_change)) {
                $select.val(originalStatus);
                return;
            }
            
            const $row = $select.closest('tr');
            $row.addClass('bb-loading');
            
            $.ajax({
                url: bbRecruitment.ajaxurl,
                type: 'POST',
                data: {
                    action: 'bb_update_application_status',
                    application_id: applicationId,
                    status: newStatus,
                    nonce: bbRecruitment.nonce
                },
                success: function(response) {
                    $row.removeClass('bb-loading');
                    if (response.success) {
                        $select.data('original-status', newStatus);
                        RecruitmentAdmin.showNotification('success', response.data);
                        
                        // Update status badge if it exists
                        const $badge = $row.find('.bb-status-badge');
                        if ($badge.length) {
                            $badge.removeClass().addClass('bb-status-badge bb-status-' + newStatus)
                                  .text(newStatus.replace('_', ' ').toUpperCase());
                        }
                    } else {
                        $select.val(originalStatus);
                        RecruitmentAdmin.showNotification('error', response.data || bbRecruitment.strings.error);
                    }
                },
                error: function() {
                    $row.removeClass('bb-loading');
                    $select.val(originalStatus);
                    RecruitmentAdmin.showNotification('error', bbRecruitment.strings.error);
                }
            });
        },
        
        // View application details
        viewApplication: function(applicationId) {
            $.ajax({
                url: bbRecruitment.ajaxurl,
                type: 'POST',
                data: {
                    action: 'bb_get_application_details',
                    application_id: applicationId,
                    nonce: bbRecruitment.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('#bb-application-details').html(response.data.html);
                        RecruitmentAdmin.openModal('#bb-application-modal');
                    } else {
                        RecruitmentAdmin.showNotification('error', response.data || bbRecruitment.strings.error);
                    }
                },
                error: function() {
                    RecruitmentAdmin.showNotification('error', bbRecruitment.strings.error);
                }
            });
        },
        
        // Delete application
        deleteApplication: function(applicationId, $button) {
            if (!confirm(bbRecruitment.strings.confirm_delete)) {
                return;
            }
            
            const $row = $button.closest('tr');
            $row.addClass('bb-loading');
            
            $.ajax({
                url: bbRecruitment.ajaxurl,
                type: 'POST',
                data: {
                    action: 'bb_delete_application',
                    application_id: applicationId,
                    nonce: bbRecruitment.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $row.fadeOut(function() {
                            $(this).remove();
                            RecruitmentAdmin.updateTableRowCount();
                        });
                        RecruitmentAdmin.showNotification('success', response.data);
                    } else {
                        $row.removeClass('bb-loading');
                        RecruitmentAdmin.showNotification('error', response.data || bbRecruitment.strings.error);
                    }
                },
                error: function() {
                    $row.removeClass('bb-loading');
                    RecruitmentAdmin.showNotification('error', bbRecruitment.strings.error);
                }
            });
        },
        
        // Export applications
        exportApplications: function() {
            const params = new URLSearchParams(window.location.search);
            params.set('action', 'bb_export_applications');
            params.set('nonce', bbRecruitment.nonce);
            
            // Create temporary download link
            const downloadUrl = bbRecruitment.ajaxurl + '?' + params.toString();
            const $link = $('<a>').attr('href', downloadUrl).attr('download', 'applications.csv');
            $('body').append($link);
            $link[0].click();
            $link.remove();
            
            RecruitmentAdmin.showNotification('success', bbRecruitment.strings.export_started);
        },
        
        // Search applications
        searchApplications: function(searchTerm) {
            const params = new URLSearchParams(window.location.search);
            if (searchTerm) {
                params.set('search', searchTerm);
            } else {
                params.delete('search');
            }
            params.delete('paged'); // Reset to first page
            
            window.location.search = params.toString();
        },
        
        // Switch tabs in settings
        switchTab: function($tabLink) {
            const targetTab = $tabLink.attr('href');
            
            // Update active states
            $('.bb-tab-link').removeClass('active');
            $tabLink.addClass('active');
            
            $('.bb-tab-content').removeClass('active');
            $(targetTab).addClass('active');
            
            // Update URL hash without scrolling
            if (history.replaceState) {
                history.replaceState(null, null, targetTab);
            }
        },
        
        // Initialize field dependencies
        initFieldDependencies: function() {
            const fieldDependencies = {
                'fields[phone][enabled]': 'fields[phone][required]',
                'fields[cover_letter][enabled]': 'fields[cover_letter][required]',
                'fields[experience][enabled]': 'fields[experience][required]',
                'fields[availability][enabled]': 'fields[availability][required]'
            };
            
            Object.keys(fieldDependencies).forEach(function(enabledField) {
                const requiredField = fieldDependencies[enabledField];
                const $enabled = $('input[name="' + enabledField + '"]');
                const $required = $('input[name="' + requiredField + '"]');
                
                $enabled.on('change', function() {
                    if (!this.checked) {
                        $required.prop('checked', false).prop('disabled', true);
                    } else {
                        $required.prop('disabled', false);
                    }
                }).trigger('change');
            });
        },
        
        // Validate settings form
        validateSettings: function() {
            let isValid = true;
            const errors = [];
            
            // Validate email addresses
            const emails = $('textarea[name="notification_emails"]').val();
            if (emails) {
                const emailList = emails.split('\n').filter(email => email.trim());
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                
                emailList.forEach(function(email) {
                    if (!emailRegex.test(email.trim())) {
                        errors.push('Invalid email address: ' + email.trim());
                        isValid = false;
                    }
                });
            }
            
            // Validate data retention days
            const retentionDays = parseInt($('input[name="data_retention_days"]').val());
            if (retentionDays < 30 || retentionDays > 3650) {
                errors.push('Data retention must be between 30 and 3650 days');
                isValid = false;
            }
            
            if (!isValid) {
                RecruitmentAdmin.showNotification('error', errors.join('<br>'));
            }
            
            return isValid;
        },
        
        // Toggle application method fields
        toggleApplicationFields: function(method) {
            $('#external_url_row').toggle(method === 'external');
            $('#contact_email_row').toggle(method === 'email');
        },
        
        // Validate closing date
        validateClosingDate: function($dateInput) {
            const selectedDate = new Date($dateInput.val());
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            
            if (selectedDate < today) {
                const confirmPast = confirm(bbRecruitment.strings.confirm_past_date);
                if (!confirmPast) {
                    $dateInput.val('');
                }
            }
        },
        
        // Toggle featured job
        toggleFeaturedJob: function(isFeatured) {
            if (isFeatured) {
                RecruitmentAdmin.showNotification('info', bbRecruitment.strings.featured_job_enabled);
            }
        },
        
        // Open modal
        openModal: function(modalSelector) {
            $(modalSelector).addClass('bb-fade-in').show();
            $('body').addClass('bb-modal-open');
        },
        
        // Close modal
        closeModal: function() {
            $('.bb-modal').removeClass('bb-fade-in').hide();
            $('body').removeClass('bb-modal-open');
        },
        
        // Show notification
        showNotification: function(type, message) {
            const $notification = $('<div class="bb-notice bb-notice-' + type + ' is-dismissible bb-fade-in">')
                .html('<p>' + message + '</p>');
            
            $('.wrap h1').after($notification);
            
            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                $notification.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        },
        
        // Update table row count
        updateTableRowCount: function() {
            const $table = $('.bb-applications-table tbody');
            if ($table.find('tr').length === 0) {
                $('.bb-applications-table-container').html(
                    '<div class="bb-no-applications">' +
                    '<h3>' + bbRecruitment.strings.no_applications + '</h3>' +
                    '<p>' + bbRecruitment.strings.no_applications_desc + '</p>' +
                    '</div>'
                );
            }
        },
        
        // Handle bulk actions
        handleBulkAction: function($select) {
            const action = $select.val();
            const $checkedBoxes = $('.bb-bulk-checkbox:checked');
            
            if (!action || $checkedBoxes.length === 0) {
                return;
            }
            
            const applicationIds = $checkedBoxes.map(function() {
                return $(this).val();
            }).get();
            
            switch(action) {
                case 'delete':
                    this.bulkDeleteApplications(applicationIds);
                    break;
                case 'export':
                    this.bulkExportApplications(applicationIds);
                    break;
                case 'update_status':
                    this.bulkUpdateStatus(applicationIds);
                    break;
            }
        },
        
        // Bulk delete applications
        bulkDeleteApplications: function(applicationIds) {
            if (!confirm(bbRecruitment.strings.confirm_bulk_delete.replace('%d', applicationIds.length))) {
                return;
            }
            
            $.ajax({
                url: bbRecruitment.ajaxurl,
                type: 'POST',
                data: {
                    action: 'bb_bulk_delete_applications',
                    application_ids: applicationIds,
                    nonce: bbRecruitment.nonce
                },
                success: function(response) {
                    if (response.success) {
                        applicationIds.forEach(function(id) {
                            $('.bb-application-row[data-application-id="' + id + '"]').fadeOut();
                        });
                        RecruitmentAdmin.showNotification('success', response.data);
                    } else {
                        RecruitmentAdmin.showNotification('error', response.data || bbRecruitment.strings.error);
                    }
                },
                error: function() {
                    RecruitmentAdmin.showNotification('error', bbRecruitment.strings.error);
                }
            });
        },
        
        // Export all data
        exportAllData: function() {
            if (!confirm(bbRecruitment.strings.confirm_export_all)) {
                return;
            }
            
            window.location.href = bbRecruitment.ajaxurl + '?action=bb_export_all_data&nonce=' + bbRecruitment.nonce;
        },
        
        // Utility functions
        utils: {
            // Debounce function
            debounce: function(func, wait, immediate) {
                let timeout;
                return function executedFunction() {
                    const context = this;
                    const args = arguments;
                    const later = function() {
                        timeout = null;
                        if (!immediate) func.apply(context, args);
                    };
                    const callNow = immediate && !timeout;
                    clearTimeout(timeout);
                    timeout = setTimeout(later, wait);
                    if (callNow) func.apply(context, args);
                };
            },
            
            // Format date
            formatDate: function(dateString) {
                const date = new Date(dateString);
                return date.toLocaleDateString();
            },
            
            // Format currency
            formatCurrency: function(amount, currency = '£') {
                return currency + parseFloat(amount).toLocaleString();
            },
            
            // Validate email
            isValidEmail: function(email) {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                return emailRegex.test(email);
            }
        }
    };
    
    // Make utilities globally available
    window.RecruitmentUtils = RecruitmentAdmin.utils;
    
})(jQuery);

// CSS for modal body lock
const modalStyles = `
    <style>
    .bb-modal-open {
        overflow: hidden;
    }
    .bb-fade-in {
        animation: bbFadeIn 0.3s ease-in;
    }
    @keyframes bbFadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    </style>
`;

if (!document.head.querySelector('#bb-modal-styles')) {
    const styleElement = document.createElement('div');
    styleElement.id = 'bb-modal-styles';
    styleElement.innerHTML = modalStyles;
    document.head.appendChild(styleElement);
}