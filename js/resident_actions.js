document.addEventListener('DOMContentLoaded', function(){
    const reportBtn = document.getElementById('report-missed-btn');
    const trackBtn = document.getElementById('track-complaint-btn');
    const feedbackBtn = document.getElementById('feedback-btn');
    const modal = document.getElementById('user-modal');
    const modalClose = document.getElementById('modal-close');
    const modalCancel = document.getElementById('modal-cancel');
    const modalTitle = document.getElementById('modal-title');
    const form = document.getElementById('user-action-form');
    const actionTypeInput = document.getElementById('action-type');
    const reportTypeSelect = document.getElementById('report-type');
    const formFeedback = document.getElementById('form-feedback');

    function openModal(type){
        modal.style.display = 'block';
        if(type === 'report'){
            modalTitle.textContent = 'Report Missed Pickup';
            reportTypeSelect.value = 'Missed Pickup';
            actionTypeInput.value = 'Missed Pickup';
        } else if(type === 'track'){
            modalTitle.textContent = 'Track Complaint Status';
            reportTypeSelect.value = 'Complaint';
            actionTypeInput.value = 'Complaint';
        } else {
            modalTitle.textContent = 'Send Feedback';
            reportTypeSelect.value = 'Feedback';
            actionTypeInput.value = 'Feedback';
        }
        formFeedback.style.display = 'none';
        formFeedback.textContent = '';
    }

    function closeModal(){
        modal.style.display = 'none';
        form.reset();
    }

    reportBtn.addEventListener('click', ()=> openModal('report'));
    trackBtn.addEventListener('click', ()=> openModal('track'));
    feedbackBtn.addEventListener('click', ()=> openModal('feedback'));
    modalClose.addEventListener('click', closeModal);
    modalCancel.addEventListener('click', closeModal);

    window.addEventListener('click', function(e){
        if(e.target === modal) closeModal();
    });

    form.addEventListener('submit', function(e){
        e.preventDefault();
        const type = (document.getElementById('report-type').value || '').trim();
        const description = (document.getElementById('description').value || '').trim();
        const address = (document.getElementById('address').value || '').trim();
        const location = (document.getElementById('location').value || '').trim();

        if(!description || !address){
            formFeedback.style.display = 'block';
            formFeedback.style.color = 'red';
            formFeedback.textContent = 'Please provide description and address.';
            return;
        }

        formFeedback.style.display = 'block';
        formFeedback.style.color = '#333';
        formFeedback.textContent = 'Submitting...';

        fetch('php/create_feedback.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({ type, description, address, location })
        }).then(r => r.json()).then(data => {
            if(data && data.success){
                formFeedback.style.color = 'green';
                formFeedback.textContent = 'Submitted successfully.';
                setTimeout(()=>{
                    closeModal();
                    location.reload();
                },800);
            } else {
                formFeedback.style.color = 'red';
                formFeedback.textContent = data.error || 'Failed to submit.';
            }
        }).catch(err => {
            formFeedback.style.color = 'red';
            formFeedback.textContent = 'Network error';
        });
    });
});
