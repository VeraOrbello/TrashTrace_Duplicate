document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('user-modal');
    const modalClose = document.getElementById('modal-close');
    const modalCancel = document.getElementById('modal-cancel');
    const modalTitle = document.getElementById('modal-title');
    const actionType = document.getElementById('action-type');
    const reportType = document.getElementById('report-type');
    const userActionForm = document.getElementById('user-action-form');
    const formFeedback = document.getElementById('form-feedback');

    // Button event listeners
    const reportMissedBtn = document.getElementById('report-missed-btn');
    const trackComplaintBtn = document.getElementById('track-complaint-btn');
    const feedbackBtn = document.getElementById('feedback-btn');

    if (reportMissedBtn) {
        reportMissedBtn.addEventListener('click', function() {
            openModal('Report Missed Pickup', 'Missed Pickup');
        });
    }

    if (trackComplaintBtn) {
        trackComplaintBtn.addEventListener('click', function() {
            openModal('Track Complaint Status', 'Complaint');
        });
    }

    if (feedbackBtn) {
        feedbackBtn.addEventListener('click', function() {
            openModal('Submit Feedback', 'Feedback');
        });
    }

    function openModal(title, type) {
        modalTitle.textContent = title;
        actionType.value = type;
        reportType.value = type;
        modal.style.display = 'block';
        formFeedback.style.display = 'none';
        formFeedback.textContent = '';
    }

    // Close modal events
    if (modalClose) {
        modalClose.addEventListener('click', closeModal);
    }

    if (modalCancel) {
        modalCancel.addEventListener('click', closeModal);
    }

    window.addEventListener('click', function(event) {
        if (event.target === modal) {
            closeModal();
        }
    });

    function closeModal() {
        modal.style.display = 'none';
        userActionForm.reset();
    }

    // Form submission
    userActionForm.addEventListener('submit', function(e) {
        e.preventDefault();

        const formData = new FormData(userActionForm);
        const data = {
            type: formData.get('type'),
            description: formData.get('description'),
            address: formData.get('address'),
            location: formData.get('location'),
            user_id: user_id,
            barangay: user_barangay
        };

        // Show loading state
        const submitBtn = userActionForm.querySelector('button[type="submit"]');
        const originalText = submitBtn.textContent;
        submitBtn.textContent = 'Submitting...';
        submitBtn.disabled = true;

        fetch('php/create_feedback.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                formFeedback.style.display = 'block';
                formFeedback.style.color = 'green';
                formFeedback.textContent = 'Your report has been submitted successfully!';

                // Close modal after success
                setTimeout(() => {
                    closeModal();
                }, 2000);
            } else {
                formFeedback.style.display = 'block';
                formFeedback.style.color = 'red';
                formFeedback.textContent = result.error || 'Failed to submit report. Please try again.';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            formFeedback.style.display = 'block';
            formFeedback.style.color = 'red';
            formFeedback.textContent = 'Network error. Please check your connection and try again.';
        })
        .finally(() => {
            // Reset button state
            submitBtn.textContent = originalText;
            submitBtn.disabled = false;
        });
    });
});
