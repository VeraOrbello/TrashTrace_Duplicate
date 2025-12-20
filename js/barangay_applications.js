document.addEventListener('DOMContentLoaded', function(){
    // Worker applications search
    const searchInput = document.getElementById('searchInput');
    const table = document.getElementById('applicationsTable');

    if(searchInput){
        searchInput.addEventListener('input', function(e){
            const q = e.target.value.trim().toLowerCase();
            const rows = table.querySelectorAll('tbody tr');
            rows.forEach(r => {
                const name = (r.querySelector('.app-name') || {textContent:''}).textContent.toLowerCase();
                const idnum = (r.children[1] || {textContent:''}).textContent.toLowerCase();
                const contact = (r.children[2] || {textContent:''}).textContent.toLowerCase();
                if(q === '' || name.includes(q) || idnum.includes(q) || contact.includes(q)){
                    r.style.display = '';
                } else {
                    r.style.display = 'none';
                }
            });
        });
    }

    // Driver applications search
    const searchDriverInput = document.getElementById('searchDriverInput');
    const driverTable = document.getElementById('driverApplicationsTable');

    if(searchDriverInput){
        searchDriverInput.addEventListener('input', function(e){
            const q = e.target.value.trim().toLowerCase();
            const rows = driverTable.querySelectorAll('tbody tr');
            rows.forEach(r => {
                const name = (r.querySelector('.app-name') || {textContent:''}).textContent.toLowerCase();
                const license = (r.children[1] || {textContent:''}).textContent.toLowerCase();
                const vehicle = (r.children[2] || {textContent:''}).textContent.toLowerCase();
                if(q === '' || name.includes(q) || license.includes(q) || vehicle.includes(q)){
                    r.style.display = '';
                } else {
                    r.style.display = 'none';
                }
            });
        });
    }

    // Admin applications search
    const searchAdminInput = document.getElementById('searchAdminInput');
    const adminTable = document.getElementById('adminApplicationsTable');

    if(searchAdminInput){
        searchAdminInput.addEventListener('input', function(e){
            const q = e.target.value.trim().toLowerCase();
            const rows = adminTable.querySelectorAll('tbody tr');
            rows.forEach(r => {
                const name = (r.querySelector('.app-name') || {textContent:''}).textContent.toLowerCase();
                const idnum = (r.children[1] || {textContent:''}).textContent.toLowerCase();
                const contact = (r.children[2] || {textContent:''}).textContent.toLowerCase();
                if(q === '' || name.includes(q) || idnum.includes(q) || contact.includes(q)){
                    r.style.display = '';
                } else {
                    r.style.display = 'none';
                }
            });
        });
    }

    
    
    const modal = document.getElementById('applicationModal');
    const modalClose = modal ? modal.querySelector('.modal-close') : null;
    const approveBtn = document.getElementById('approveBtn');
    const rejectBtn = document.getElementById('rejectBtn');

    const driverModal = document.getElementById('driverApplicationModal');
    const driverModalClose = driverModal ? driverModal.querySelector('.modal-close') : null;
    const driverApproveBtn = document.getElementById('driverApproveBtn');
    const driverRejectBtn = document.getElementById('driverRejectBtn');

    const adminModal = document.getElementById('adminApplicationModal');
    const adminModalClose = adminModal ? adminModal.querySelector('.modal-close') : null;
    const adminApproveBtn = document.getElementById('adminApproveBtn');
    const adminRejectBtn = document.getElementById('adminRejectBtn');

    function showModal(modalElement){ if(modalElement) modalElement.style.display = 'block'; }
    function hideModal(modalElement){ if(modalElement) modalElement.style.display = 'none'; }

    document.addEventListener('click', function(e){
        const btn = e.target.closest && e.target.closest('.view-app-btn');
        if(btn){
            const id = btn.getAttribute('data-id');
            if(id) openApplication(parseInt(id,10));
        }

        const driverBtn = e.target.closest && e.target.closest('.view-driver-btn');
        if(driverBtn){
            const id = driverBtn.getAttribute('data-id');
            if(id) openDriverApplication(parseInt(id,10));
        }

        const adminBtn = e.target.closest && e.target.closest('.view-admin-btn');
        if(adminBtn){
            const id = adminBtn.getAttribute('data-id');
            if(id) openAdminApplication(parseInt(id,10));
        }
    });

    if(modalClose) modalClose.addEventListener('click', hideModal);
    if(modal) modal.querySelector('.modal-overlay').addEventListener('click', hideModal);

    function openApplication(id){
        fetch('php/get_application.php?id=' + encodeURIComponent(id))
            .then(r => r.json())
            .then(data => {
                if(!data || !data.success) return alert(data && data.error ? data.error : 'Unable to load');
                const app = data.application;
                document.getElementById('modalName').textContent = app.full_name || 'Application Details';
                document.getElementById('modalIdNumber').textContent = app.id_number || '';
                document.getElementById('modalContact').innerHTML = (app.contact_number||'') + '<br>' + (app.email||'');
                document.getElementById('modalLocation').textContent = (app.city||'') + ' / ' + (app.barangay||'') + ' / ' + (app.zone||'');
                document.getElementById('modalExperience').textContent = app.experience_years || '';
                document.getElementById('modalAvailability').textContent = app.availability || '';
                document.getElementById('modalVehicle').textContent = app.vehicle_access || '';
                document.getElementById('modalHealth').textContent = app.health_conditions || '<em>None</em>';
                document.getElementById('modalReason').textContent = app.reason_application || '';
                document.getElementById('modalSubmitted').textContent = app.submitted_at ? new Date(app.submitted_at).toLocaleString() : '';
                const docWrap = document.getElementById('modalDoc');
                docWrap.innerHTML = '';
                if(app.id_proof_path){
                    const link = document.createElement('a');
                    link.href = app.id_proof_path;
                    link.target = '_blank';
                    link.textContent = 'Open Document';
                    docWrap.appendChild(link);
                    if(/\.(jpg|jpeg|png)$/i.test(app.id_proof_path)){
                        const img = document.createElement('img');
                        img.src = app.id_proof_path;
                        img.style.maxWidth = '100%';
                        img.style.marginTop = '8px';
                        docWrap.appendChild(img);
                    }
                } else {
                    docWrap.textContent = 'No document uploaded.';
                }

                approveBtn.setAttribute('data-id', app.id);
                rejectBtn.setAttribute('data-id', app.id);
                showModal(modal);
            })
            .catch(()=> alert('Network error'));
    }

    function handleAction(action, id){
        fetch('php/handle_application.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({id: id, action: action})
        }).then(r=>r.json()).then(data => {
            if(!data || !data.success) return alert(data && data.error ? data.error : 'Action failed');
            
            const row = document.querySelector('.view-app-btn[data-id="'+id+'"]').closest('tr');
            if(row){
                const statusCell = row.querySelector('.status-cell');
                if(statusCell) statusCell.textContent = data.status ? data.status.charAt(0).toUpperCase()+data.status.slice(1) : '';
            }
            
            hideModal(modal);
        }).catch(()=> alert('Server error'));
    }

    if(approveBtn) approveBtn.addEventListener('click', function(){ const id = parseInt(this.getAttribute('data-id')||0,10); if(id) handleAction('accept', id); });
    if(rejectBtn) rejectBtn.addEventListener('click', function(){ const id = parseInt(this.getAttribute('data-id')||0,10); if(id) handleAction('reject', id); });

    // Driver modal buttons
    if(driverApproveBtn) driverApproveBtn.addEventListener('click', function(){ const id = parseInt(this.getAttribute('data-id')||0,10); if(id) handleDriverAction('approve', id); });
    if(driverRejectBtn) driverRejectBtn.addEventListener('click', function(){ const id = parseInt(this.getAttribute('data-id')||0,10); if(id) handleDriverAction('reject', id); });

    // Admin modal buttons
    if(adminApproveBtn) adminApproveBtn.addEventListener('click', function(){ const id = parseInt(this.getAttribute('data-id')||0,10); if(id) handleAdminAction('approve', id); });
    if(adminRejectBtn) adminRejectBtn.addEventListener('click', function(){ const id = parseInt(this.getAttribute('data-id')||0,10); if(id) handleAdminAction('reject', id); });

    function openDriverApplication(id){
        fetch('php/get_driver_application.php?id=' + encodeURIComponent(id))
            .then(r => r.json())
            .then(data => {
                if(!data || !data.success) return alert(data && data.error ? data.error : 'Unable to load');
                const app = data.application;
                document.getElementById('driverModalName').textContent = app.full_name || 'Driver Application Details';
                document.getElementById('driverModalNameValue').textContent = app.full_name || '';
                document.getElementById('driverModalEmail').textContent = app.email || '';
                document.getElementById('driverModalMobile').textContent = app.mobile_number || '';
                document.getElementById('driverModalLocation').textContent = app.barangay || '';
                document.getElementById('driverModalLicense').textContent = app.license_number || '';
                document.getElementById('driverModalVehicleType').textContent = app.vehicle_type || '';
                document.getElementById('driverModalVehiclePlate').textContent = app.vehicle_plate || '';
                document.getElementById('driverModalDate').textContent = app.application_date ? new Date(app.application_date).toLocaleString() : '';
                document.getElementById('driverModalStatus').textContent = app.status ? app.status.charAt(0).toUpperCase() + app.status.slice(1) : '';

                driverApproveBtn.setAttribute('data-id', app.id);
                driverRejectBtn.setAttribute('data-id', app.id);
                showModal(driverModal);
            })
            .catch(()=> alert('Network error'));
    }

    function openAdminApplication(id){
        fetch('php/get_admin_application.php?id=' + encodeURIComponent(id))
            .then(r => r.json())
            .then(data => {
                if(!data || !data.success) return alert(data && data.error ? data.error : 'Unable to load');
                const app = data.application;
                document.getElementById('adminModalName').textContent = app.full_name || 'Admin Application Details';
                document.getElementById('adminModalNameValue').textContent = app.full_name || '';
                document.getElementById('adminModalEmail').textContent = app.email || '';
                document.getElementById('adminModalMobile').textContent = app.mobile_number || '';
                document.getElementById('adminModalLocation').textContent = app.barangay || '';
                document.getElementById('adminModalIdNumber').textContent = app.id_number || '';
                document.getElementById('adminModalExperience').textContent = app.experience_years || '';
                document.getElementById('adminModalAvailability').textContent = app.availability || '';
                document.getElementById('adminModalVehicle').textContent = app.vehicle_access || '';
                document.getElementById('adminModalDate').textContent = app.created_at ? new Date(app.created_at).toLocaleString() : '';
                document.getElementById('adminModalStatus').textContent = 'Pending';

                adminApproveBtn.setAttribute('data-id', app.id);
                adminRejectBtn.setAttribute('data-id', app.id);
                showModal(adminModal);
            })
            .catch(()=> alert('Network error'));
    }

    function handleDriverAction(action, id){
        fetch('php/handle_driver_application.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({id: id, action: action})
        }).then(r=>r.json()).then(data => {
            if(!data || !data.success) return alert(data && data.error ? data.error : 'Action failed');

            // Update status in table without reload
            const row = document.querySelector('.view-driver-btn[data-id="'+id+'"]').closest('tr');
            if(row){
                const statusCell = row.querySelector('.status-cell');
                if(statusCell){
                    const statusBadge = statusCell.querySelector('.status-badge');
                    if(statusBadge){
                        const newStatus = data.status ? data.status.charAt(0).toUpperCase() + data.status.slice(1) : 'Pending';
                        statusBadge.textContent = newStatus;
                        statusBadge.className = 'status-badge status-' + (data.status || 'pending').toLowerCase();
                    }
                }
            }

            hideModal(driverModal);
        }).catch(()=> alert('Server error'));
    }

    function handleAdminAction(action, id){
        fetch('php/handle_admin_application.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({id: id, action: action})
        }).then(r=>r.json()).then(data => {
            if(!data || !data.success) return alert(data && data.error ? data.error : 'Action failed');
            hideModal(adminModal);
            location.reload(); // Reload to update the table
        }).catch(()=> alert('Server error'));
    }
});
