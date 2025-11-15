/**
 * Applicant Board (Kanban View) JavaScript
 */

(function($) {
    'use strict';

    // Drag and Drop State
    let draggedCard = null;
    let draggedCardId = null;
    let draggedFromStatus = null;

    $(document).ready(function() {
        initDragAndDrop();
        initModalHandlers();
    });

    /**
     * Initialize drag and drop functionality
     */
    function initDragAndDrop() {
        const cards = document.querySelectorAll('.kanban-card');
        const columns = document.querySelectorAll('.column-cards');

        // Card drag events
        cards.forEach(card => {
            card.addEventListener('dragstart', handleDragStart);
            card.addEventListener('dragend', handleDragEnd);
        });

        // Column drop events
        columns.forEach(column => {
            column.addEventListener('dragover', handleDragOver);
            column.addEventListener('drop', handleDrop);
            column.addEventListener('dragleave', handleDragLeave);
        });
    }

    /**
     * Handle drag start
     */
    function handleDragStart(e) {
        draggedCard = this;
        draggedCardId = this.dataset.id;
        draggedFromStatus = this.dataset.status;

        this.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/html', this.innerHTML);
    }

    /**
     * Handle drag end
     */
    function handleDragEnd(e) {
        this.classList.remove('dragging');

        // Remove drag-over class from all columns
        document.querySelectorAll('.column-cards').forEach(column => {
            column.classList.remove('drag-over');
        });
    }

    /**
     * Handle drag over column
     */
    function handleDragOver(e) {
        if (e.preventDefault) {
            e.preventDefault();
        }

        e.dataTransfer.dropEffect = 'move';
        this.classList.add('drag-over');

        return false;
    }

    /**
     * Handle drag leave column
     */
    function handleDragLeave(e) {
        this.classList.remove('drag-over');
    }

    /**
     * Handle drop on column
     */
    function handleDrop(e) {
        if (e.stopPropagation) {
            e.stopPropagation();
        }

        this.classList.remove('drag-over');

        const newStatus = this.dataset.status;

        // Don't do anything if dropped in the same column
        if (draggedFromStatus === newStatus) {
            return false;
        }

        // Update card status via AJAX
        updateCardStatus(draggedCardId, newStatus, draggedCard, this);

        return false;
    }

    /**
     * Update card status via AJAX
     */
    function updateCardStatus(applicationId, newStatus, cardElement, targetColumn) {
        // Show loading state
        const originalContent = cardElement.innerHTML;
        cardElement.style.opacity = '0.6';

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'bb_update_application_status',
                nonce: bbRecruitmentBoard.nonce,
                application_id: applicationId,
                status: newStatus
            },
            success: function(response) {
                if (response.success) {
                    // Move card to new column
                    targetColumn.insertBefore(cardElement, targetColumn.firstChild);

                    // Update card's data attribute
                    cardElement.dataset.status = newStatus;

                    // Restore opacity
                    cardElement.style.opacity = '1';

                    // Update column counts
                    updateColumnCounts();

                    // Show success feedback
                    showNotification('Status updated successfully', 'success');

                    // Remove empty column message if it exists
                    const emptyMessage = targetColumn.querySelector('.empty-column');
                    if (emptyMessage) {
                        emptyMessage.remove();
                    }

                    // Check if original column is now empty
                    checkEmptyColumn(draggedFromStatus);
                } else {
                    // Restore card
                    cardElement.style.opacity = '1';
                    showNotification(response.data || 'Failed to update status', 'error');
                }
            },
            error: function() {
                cardElement.style.opacity = '1';
                showNotification('Network error occurred', 'error');
            }
        });
    }

    /**
     * Update column counts
     */
    function updateColumnCounts() {
        document.querySelectorAll('.kanban-column').forEach(column => {
            const status = column.dataset.status;
            const cardsContainer = column.querySelector('.column-cards');
            const count = cardsContainer.querySelectorAll('.kanban-card').length;
            const countElement = column.querySelector('.column-count');

            if (countElement) {
                countElement.textContent = count;
            }
        });
    }

    /**
     * Check if column is empty and show message
     */
    function checkEmptyColumn(status) {
        const column = document.querySelector(`.column-cards[data-status="${status}"]`);
        if (!column) return;

        const cards = column.querySelectorAll('.kanban-card');

        if (cards.length === 0 && !column.querySelector('.empty-column')) {
            const emptyDiv = document.createElement('div');
            emptyDiv.className = 'empty-column';
            emptyDiv.innerHTML = '<p>No applications</p>';
            column.appendChild(emptyDiv);
        }
    }

    /**
     * Show notification
     */
    function showNotification(message, type) {
        // Use WordPress admin notices if available
        const notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');

        $('.applicant-board-wrap').prepend(notice);

        // Auto dismiss after 3 seconds
        setTimeout(function() {
            notice.fadeOut(function() {
                $(this).remove();
            });
        }, 3000);
    }

    /**
     * Initialize modal handlers
     */
    function initModalHandlers() {
        // View details button
        $(document).on('click', '.view-details-btn', function() {
            const applicationId = $(this).data('id');
            loadApplicationDetails(applicationId);
        });

        // Close modal
        $('.modal-close, .modal-overlay').on('click', function() {
            $('#application-modal').fadeOut();
        });

        // Prevent closing when clicking inside modal content
        $('.modal-content').on('click', function(e) {
            e.stopPropagation();
        });

        // Close on escape key
        $(document).on('keyup', function(e) {
            if (e.key === 'Escape') {
                $('#application-modal').fadeOut();
            }
        });
    }

    /**
     * Load application details via AJAX
     */
    function loadApplicationDetails(applicationId) {
        $('#modal-application-details').html('<p>Loading...</p>');
        $('#application-modal').fadeIn();

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'bb_get_application_details',
                nonce: bbRecruitmentBoard.nonce,
                application_id: applicationId
            },
            success: function(response) {
                if (response.success) {
                    const data = response.data;

                    let html = '<div class="application-details">';
                    html += '<h2>' + data.applicant_name + '</h2>';
                    html += '<div class="detail-row"><strong>Email:</strong> <a href="mailto:' + data.applicant_email + '">' + data.applicant_email + '</a></div>';

                    if (data.phone) {
                        html += '<div class="detail-row"><strong>Phone:</strong> <a href="tel:' + data.phone + '">' + data.phone + '</a></div>';
                    }

                    html += '<div class="detail-row"><strong>Job:</strong> <a href="' + data.job_url + '" target="_blank">' + data.job_title + '</a></div>';
                    html += '<div class="detail-row"><strong>Status:</strong> ' + data.status_label + '</div>';
                    html += '<div class="detail-row"><strong>Applied:</strong> ' + data.created_date + '</div>';
                    html += '<div class="detail-row"><strong>Last Updated:</strong> ' + data.updated_date + '</div>';

                    html += '<hr style="margin: 20px 0;">';
                    html += '<h3>Application Details</h3>';
                    html += data.formatted_data;

                    html += '</div>';

                    $('#modal-application-details').html(html);
                } else {
                    $('#modal-application-details').html('<p class="error">' + (response.data || 'Failed to load details') + '</p>');
                }
            },
            error: function() {
                $('#modal-application-details').html('<p class="error">Network error occurred</p>');
            }
        });
    }

})(jQuery);
