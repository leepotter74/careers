/**
 * Recruitment Manager Public JavaScript
 */

(function($) {
    'use strict';
    
    // Initialize when document is ready
    $(document).ready(function() {
        RecruitmentPublic.init();
    });
    
    // Main public object
    window.RecruitmentPublic = {
        
        // Initialize public functionality
        init: function() {
            this.initApplicationForm();
            this.initSocialSharing();
            this.initJobListings();
            this.initSaveAndReturn();
        },
        
        // Application form functionality
        initApplicationForm: function() {
            if (!$('#bb-job-application-form').length) return;
            
            // Form submission
            $('#bb-job-application-form').on('submit', function(e) {
                e.preventDefault();
                RecruitmentPublic.submitApplication($(this));
            });
            
            // Save application
            $('#bb-save-application').on('click', function(e) {
                e.preventDefault();
                RecruitmentPublic.saveApplication();
            });
            
            // Form validation
            this.initFormValidation();
            
            // Auto-save functionality
            if (bbRecruitmentPublic.settings && bbRecruitmentPublic.settings.autosave) {
                this.initAutoSave();
            }
            
            // Load saved data if available
            this.loadSavedData();
        },
        
        // Social sharing functionality
        initSocialSharing: function() {
            if (!$('.bb-social-sharing').length) return;
            
            // Share button handlers
            $('.bb-share-btn').on('click', function(e) {
                const platform = $(this).data('platform');
                
                if (platform === 'copy') {
                    e.preventDefault();
                    RecruitmentPublic.copyToClipboard($(this));
                } else if (platform !== 'email') {
                    e.preventDefault();
                    RecruitmentPublic.shareJob($(this), platform);
                }
                
                // Track share
                RecruitmentPublic.trackShare(platform);
            });
        },
        
        // Job listings functionality
        initJobListings: function() {
            // Job search/filter
            $('.bb-job-filter').on('change', function() {
                RecruitmentPublic.filterJobs();
            });
            
            // Load more jobs functionality
            $('.bb-load-more-jobs').on('click', function(e) {
                e.preventDefault();
                RecruitmentPublic.loadMoreJobs($(this));
            });
            
            // Job bookmarking (if logged in)
            $('.bb-bookmark-job').on('click', function(e) {
                e.preventDefault();
                RecruitmentPublic.toggleJobBookmark($(this));
            });
        },
        
        // Save and return functionality
        initSaveAndReturn: function() {
            // Check for continue application parameter
            const urlParams = new URLSearchParams(window.location.search);
            const continueToken = urlParams.get('continue_application');
            
            if (continueToken) {
                this.loadSavedApplication(continueToken);
            }
        },
        
        // Form validation
        initFormValidation: function() {
            const $form = $('#bb-job-application-form');
            
            // Real-time validation
            $form.find('input, textarea').on('blur', function() {
                RecruitmentPublic.validateField($(this));
            });
            
            // Email validation
            $form.find('input[type="email"]').on('input', function() {
                RecruitmentPublic.validateEmail($(this));
            });
            
            // Phone validation
            $form.find('input[type="tel"]').on('input', function() {
                RecruitmentPublic.validatePhone($(this));
            });
        },
        
        // Auto-save functionality
        initAutoSave: function() {
            let saveTimeout;
            const $form = $('#bb-job-application-form');
            
            $form.find('input, textarea').on('input', function() {
                clearTimeout(saveTimeout);
                saveTimeout = setTimeout(function() {
                    RecruitmentPublic.autoSaveForm();
                }, 30000); // Auto-save after 30 seconds of inactivity
            });
        },
        
        // Submit application
        submitApplication: function($form) {
            if (!this.validateForm($form)) {
                return;
            }
            
            const formData = $form.serialize();
            const $submitBtn = $('#bb-submit-application');
            
            $submitBtn.prop('disabled', true).text(bbRecruitmentPublic.strings.submitting || 'Submitting...');
            this.showMessage('info', bbRecruitmentPublic.strings.submitting || 'Submitting your application...');
            
            $.ajax({
                url: bbRecruitmentPublic.ajaxurl,
                type: 'POST',
                data: formData + '&action=submit_job_application',
                success: function(response) {
                    if (response.success) {
                        RecruitmentPublic.showMessage('success', response.data.message);
                        $form[0].reset();
                        
                        // Clear any saved data
                        localStorage.removeItem('bb_application_draft_' + $form.data('job-id'));
                        
                        // Scroll to message
                        $('html, body').animate({
                            scrollTop: $('#bb-application-messages').offset().top - 20
                        }, 500);
                        
                        // Hide form after successful submission
                        setTimeout(function() {
                            $form.slideUp();
                        }, 3000);
                        
                    } else {
                        RecruitmentPublic.showMessage('error', response.data || 'Submission failed');
                    }
                },
                error: function() {
                    RecruitmentPublic.showMessage('error', bbRecruitmentPublic.strings.error_occurred);
                },
                complete: function() {
                    $submitBtn.prop('disabled', false).text(bbRecruitmentPublic.strings.submit_application || 'Submit Application');
                }
            });
        },
        
        // Save application
        saveApplication: function() {
            const $form = $('#bb-job-application-form');
            const formData = $form.serialize();
            const $saveBtn = $('#bb-save-application');
            
            $saveBtn.prop('disabled', true).text(bbRecruitmentPublic.strings.saving || 'Saving...');
            
            $.ajax({
                url: bbRecruitmentPublic.ajaxurl,
                type: 'POST',
                data: formData + '&action=save_job_application',
                success: function(response) {
                    if (response.success) {
                        RecruitmentPublic.showMessage('success', response.data.message);
                        
                        // Show continue URL if provided
                        if (response.data.continue_url) {
                            const continueMessage = '<br><a href="' + response.data.continue_url + '" class="bb-continue-link">Continue this application later</a>';
                            $('#bb-application-messages .bb-message-success').append(continueMessage);
                        }
                        
                    } else {
                        RecruitmentPublic.showMessage('error', response.data || 'Save failed');
                    }
                },
                error: function() {
                    RecruitmentPublic.showMessage('error', bbRecruitmentPublic.strings.error_occurred);
                },
                complete: function() {
                    $saveBtn.prop('disabled', false).text(bbRecruitmentPublic.strings.save_continue || 'Save & Continue Later');
                }
            });
        },
        
        // Auto-save form
        autoSaveForm: function() {
            const $form = $('#bb-job-application-form');
            const jobId = $form.data('job-id');
            
            if (!jobId) return;
            
            const formData = {};
            $form.find('input, textarea').each(function() {
                const $field = $(this);
                if ($field.attr('type') === 'checkbox') {
                    formData[$field.attr('name')] = $field.is(':checked');
                } else {
                    formData[$field.attr('name')] = $field.val();
                }
            });
            
            // Save to localStorage as backup
            localStorage.setItem('bb_application_draft_' + jobId, JSON.stringify(formData));
            
            // Show auto-save indicator
            this.showAutoSaveIndicator();
        },
        
        // Load saved data
        loadSavedData: function() {
            const $form = $('#bb-job-application-form');
            const jobId = $form.data('job-id');
            
            if (!jobId) return;
            
            // Try to load from localStorage first
            const savedData = localStorage.getItem('bb_application_draft_' + jobId);
            if (savedData) {
                try {
                    const data = JSON.parse(savedData);
                    this.populateForm(data);
                } catch (e) {
                    console.warn('Failed to load saved application data');
                }
            }
        },
        
        // Load saved application via token
        loadSavedApplication: function(token) {
            $.ajax({
                url: bbRecruitmentPublic.ajaxurl,
                type: 'POST',
                data: {
                    action: 'bb_load_saved_application',
                    save_token: token,
                    nonce: bbRecruitmentPublic.nonce
                },
                success: function(response) {
                    if (response.success) {
                        RecruitmentPublic.populateForm(response.data.application_data);
                        RecruitmentPublic.showMessage('info', 'Your saved application has been loaded.');
                    } else {
                        RecruitmentPublic.showMessage('error', response.data || 'Could not load saved application');
                    }
                },
                error: function() {
                    RecruitmentPublic.showMessage('error', bbRecruitmentPublic.strings.error_occurred);
                }
            });
        },
        
        // Populate form with data
        populateForm: function(data) {
            const $form = $('#bb-job-application-form');
            
            Object.keys(data).forEach(function(key) {
                const $field = $form.find('[name="' + key + '"]');
                if ($field.length) {
                    if ($field.attr('type') === 'checkbox') {
                        $field.prop('checked', data[key]);
                    } else {
                        $field.val(data[key]);
                    }
                }
            });
        },
        
        // Share job on social media
        shareJob: function($button, platform) {
            const jobId = $('.bb-social-sharing').data('job-id');
            const url = $button.attr('href');
            
            // Open share window
            const shareWindow = window.open(
                url,
                'share_' + platform,
                'width=600,height=400,scrollbars=yes,resizable=yes'
            );
            
            // Focus the window
            if (shareWindow) {
                shareWindow.focus();
            }
        },
        
        // Copy to clipboard
        copyToClipboard: function($button) {
            const url = $button.data('url');
            
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(url).then(function() {
                    RecruitmentPublic.showCopySuccess($button);
                }).catch(function() {
                    RecruitmentPublic.fallbackCopyToClipboard(url, $button);
                });
            } else {
                this.fallbackCopyToClipboard(url, $button);
            }
        },
        
        // Fallback copy to clipboard
        fallbackCopyToClipboard: function(text, $button) {
            const textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.position = 'fixed';
            textArea.style.left = '-999999px';
            textArea.style.top = '-999999px';
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            
            try {
                document.execCommand('copy');
                this.showCopySuccess($button);
            } catch (err) {
                this.showMessage('error', 'Could not copy to clipboard');
            }
            
            document.body.removeChild(textArea);
        },
        
        // Show copy success
        showCopySuccess: function($button) {
            const originalText = $button.find('span').text();
            $button.find('span').text('Copied!');
            
            setTimeout(function() {
                $button.find('span').text(originalText);
            }, 2000);
            
            this.showMessage('success', 'Link copied to clipboard!');
        },
        
        // Track social share
        trackShare: function(platform) {
            const jobId = $('.bb-social-sharing').data('job-id');
            
            if (!jobId) return;
            
            $.ajax({
                url: bbRecruitmentPublic.ajaxurl,
                type: 'POST',
                data: {
                    action: 'bb_track_share',
                    job_id: jobId,
                    platform: platform,
                    nonce: bbRecruitmentPublic.nonce
                },
                success: function(response) {
                    if (response.success && response.data.total_shares) {
                        $('.bb-share-count').text(response.data.total_shares + ' shares');
                    }
                }
            });
        },
        
        // Filter jobs
        filterJobs: function() {
            const filters = {};
            
            $('.bb-job-filter').each(function() {
                const $filter = $(this);
                const value = $filter.val();
                if (value) {
                    filters[$filter.attr('name')] = value;
                }
            });
            
            // Update URL with filters
            const url = new URL(window.location);
            Object.keys(filters).forEach(key => {
                url.searchParams.set(key, filters[key]);
            });
            
            // Reload page with filters
            window.location.href = url.toString();
        },
        
        // Load more jobs
        loadMoreJobs: function($button) {
            const page = parseInt($button.data('page')) + 1;
            const $container = $('.bb-job-listings');
            
            $button.prop('disabled', true).text('Loading...');
            
            $.ajax({
                url: bbRecruitmentPublic.ajaxurl,
                type: 'POST',
                data: {
                    action: 'bb_load_more_jobs',
                    page: page,
                    nonce: bbRecruitmentPublic.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $container.append(response.data.html);
                        $button.data('page', page);
                        
                        if (!response.data.has_more) {
                            $button.hide();
                        }
                    } else {
                        RecruitmentPublic.showMessage('error', 'Could not load more jobs');
                    }
                },
                error: function() {
                    RecruitmentPublic.showMessage('error', bbRecruitmentPublic.strings.error_occurred);
                },
                complete: function() {
                    $button.prop('disabled', false).text('Load More Jobs');
                }
            });
        },
        
        // Toggle job bookmark
        toggleJobBookmark: function($button) {
            const jobId = $button.data('job-id');
            const isBookmarked = $button.hasClass('bookmarked');
            
            $.ajax({
                url: bbRecruitmentPublic.ajaxurl,
                type: 'POST',
                data: {
                    action: 'bb_toggle_job_bookmark',
                    job_id: jobId,
                    nonce: bbRecruitmentPublic.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $button.toggleClass('bookmarked');
                        const text = isBookmarked ? 'Bookmark' : 'Bookmarked';
                        $button.text(text);
                    }
                }
            });
        },
        
        // Form validation
        validateForm: function($form) {
            let isValid = true;
            const errors = [];
            
            // Check required fields
            $form.find('[required]').each(function() {
                const $field = $(this);
                if (!$field.val().trim()) {
                    isValid = false;
                    errors.push($field.prev('label').text() + ' is required');
                    $field.addClass('error');
                } else {
                    $field.removeClass('error');
                }
            });
            
            // Validate email
            const $email = $form.find('input[type="email"]');
            if ($email.length && $email.val() && !this.isValidEmail($email.val())) {
                isValid = false;
                errors.push('Please enter a valid email address');
                $email.addClass('error');
            }
            
            // Check consent
            const $consent = $form.find('#data_consent');
            if ($consent.length && !$consent.is(':checked')) {
                isValid = false;
                errors.push('You must consent to data processing to apply');
            }
            
            if (!isValid) {
                this.showMessage('error', errors.join('<br>'));
            }
            
            return isValid;
        },
        
        // Validate individual field
        validateField: function($field) {
            $field.removeClass('error');
            
            if ($field.prop('required') && !$field.val().trim()) {
                $field.addClass('error');
                return false;
            }
            
            return true;
        },
        
        // Validate email
        validateEmail: function($field) {
            const email = $field.val();
            $field.removeClass('error');
            
            if (email && !this.isValidEmail(email)) {
                $field.addClass('error');
                return false;
            }
            
            return true;
        },
        
        // Validate phone
        validatePhone: function($field) {
            const phone = $field.val();
            $field.removeClass('error');
            
            if (phone && phone.length < 10) {
                $field.addClass('error');
                return false;
            }
            
            return true;
        },
        
        // Show message
        showMessage: function(type, message) {
            const $container = $('#bb-application-messages');
            const $message = $('<div class="bb-message bb-message-' + type + '">' + message + '</div>');
            
            $container.empty().append($message);
            
            // Auto-hide success messages
            if (type === 'success') {
                setTimeout(function() {
                    $message.fadeOut();
                }, 5000);
            }
            
            // Scroll to message
            $('html, body').animate({
                scrollTop: $container.offset().top - 20
            }, 300);
        },
        
        // Show auto-save indicator
        showAutoSaveIndicator: function() {
            let $indicator = $('.bb-autosave-indicator');
            
            if (!$indicator.length) {
                $indicator = $('<div class="bb-autosave-indicator">Auto-saved</div>');
                $indicator.css({
                    position: 'fixed',
                    top: '20px',
                    right: '20px',
                    background: '#28a745',
                    color: 'white',
                    padding: '8px 12px',
                    borderRadius: '4px',
                    fontSize: '13px',
                    zIndex: 1000,
                    opacity: 0
                });
                $('body').append($indicator);
            }
            
            $indicator.animate({opacity: 1}, 200);
            setTimeout(function() {
                $indicator.animate({opacity: 0}, 200);
            }, 2000);
        },
        
        // Utility functions
        isValidEmail: function(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        },
        
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
        }
    };
    
})(jQuery);

// Add form error styles
const formStyles = `
    <style>
    .bb-form-row input.error,
    .bb-form-row textarea.error {
        border-color: #dc3545;
        box-shadow: 0 0 0 2px rgba(220, 53, 69, 0.25);
    }
    
    .bb-continue-link {
        color: #0073aa;
        text-decoration: underline;
        font-weight: 500;
    }
    
    .bb-continue-link:hover {
        color: #005177;
    }
    
    .bb-autosave-indicator {
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    }
    </style>
`;

if (!document.head.querySelector('#bb-form-styles')) {
    const styleElement = document.createElement('div');
    styleElement.id = 'bb-form-styles';
    styleElement.innerHTML = formStyles;
    document.head.appendChild(styleElement);
}