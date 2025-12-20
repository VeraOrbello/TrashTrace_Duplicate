document.addEventListener('DOMContentLoaded', function() {
    
    
    let currentDate = new Date();
    const calendarBody = document.getElementById('calendar-body');
    const currentMonthElement = document.getElementById('current-month');
    const prevMonthBtn = document.getElementById('prev-month');
    const nextMonthBtn = document.getElementById('next-month');
    const modal = document.getElementById('schedule-modal');
    const closeModal = document.querySelector('.close-modal');
    
    window.schedules = schedules || [];
    window.userBarangay = userBarangay || '';
    window.userZone = userZone || '';
    window.userType = userType || 'user';
    
    function renderCalendar(date) {
        const year = date.getFullYear();
        const month = date.getMonth();
        
        currentMonthElement.textContent = new Date(year, month).toLocaleDateString('en-US', {
            month: 'long',
            year: 'numeric'
        });
        
        const firstDay = new Date(year, month, 1);
        const lastDay = new Date(year, month + 1, 0);
        const startingDay = firstDay.getDay();
        const daysInMonth = lastDay.getDate();
        
        calendarBody.innerHTML = '';
        
        for (let i = 0; i < startingDay; i++) {
            const prevMonthDay = new Date(year, month, 0 - (startingDay - i - 1));
            createDayElement(prevMonthDay, true);
        }
        
        for (let day = 1; day <= daysInMonth; day++) {
            const currentDate = new Date(year, month, day);
            createDayElement(currentDate, false);
        }
        
        const totalCells = 42;
        const cellsSoFar = startingDay + daysInMonth;
        const remainingCells = totalCells - cellsSoFar;
        
        for (let i = 1; i <= remainingCells; i++) {
            const nextMonthDay = new Date(year, month + 1, i);
            createDayElement(nextMonthDay, true);
        }
    }
    
    function createDayElement(date, isOtherMonth) {
        const dayElement = document.createElement('div');
        dayElement.className = 'calendar-day';
        
        if (isOtherMonth) {
            dayElement.classList.add('other-month');
        }
        
        const dayNumber = document.createElement('div');
        dayNumber.className = 'day-number';
        dayNumber.textContent = date.getDate();
        dayElement.appendChild(dayNumber);
        
        const dateString = formatDate(date);
        
        const daySchedules = window.schedules.filter(schedule => 
            schedule.schedule_date === dateString
        );
        
        if (daySchedules.length > 0) {
            dayElement.classList.add('has-schedule');
            
            const indicator = document.createElement('div');
            indicator.className = 'schedule-indicator';
            
            if (daySchedules.length === 1) {
                const schedule = daySchedules[0];
                indicator.classList.add(schedule.status.toLowerCase());
                indicator.textContent = schedule.status;
            } else {
                indicator.style.backgroundColor = '#9c27b0';
                indicator.textContent = `${daySchedules.length} schedules`;
            }
            
            dayElement.appendChild(indicator);
            
            dayElement.addEventListener('click', function() {
                if (userType === 'admin') {
                    showWorkerDayDetails(dateString, daySchedules);
                } else {
                    showScheduleDetails(dateString, daySchedules);
                }
            });
            
            dayElement.style.cursor = 'pointer';
        } else {
            dayElement.style.cursor = 'default';
        }
        
        calendarBody.appendChild(dayElement);
    }
    
    function formatDate(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }
    
    function showScheduleDetails(dateString, daySchedules) {
        const modalBody = document.getElementById('modal-body-content');
        
        let html = `<h4 style="margin-bottom: 1rem; color: #2e7d32;">Schedules for ${new Date(dateString).toLocaleDateString()}</h4>`;
        
        if (daySchedules.length === 0) {
            html += `<div class="empty-state">No schedules for this day</div>`;
        } else {
            daySchedules.forEach(schedule => {
                html += `
                    <div class="detail-item">
                        <label>Date:</label>
                        <span>${new Date(schedule.schedule_date).toLocaleDateString('en-US', {
                            weekday: 'long',
                            year: 'numeric',
                            month: 'long',
                            day: 'numeric'
                        })}</span>
                    </div>
                    <div class="detail-item">
                        <label>Status:</label>
                        <span class="status-badge ${schedule.status.toLowerCase()}">${schedule.status}</span>
                    </div>
                    <div class="detail-item">
                        <label>Barangay:</label>
                        <span>${schedule.barangay}</span>
                    </div>
                    ${schedule.zone ? `
                    <div class="detail-item">
                        <label>Zone:</label>
                        <span>${schedule.zone}</span>
                    </div>
                    ` : ''}
                    ${schedule.notes ? `
                    <div class="detail-item full-width">
                        <label>Notes:</label>
                        <p>${schedule.notes}</p>
                    </div>
                    ` : ''}
                    <hr style="margin: 1rem 0; border-color: #eee;">
                `;
            });
        }
        
        modalBody.innerHTML = html;
        modal.style.display = 'block';
    }
    
    function showWorkerDayDetails(dateString, daySchedules) {
        const modalBody = document.getElementById('modal-body-content');
        
        let html = `<h4 style="margin-bottom: 1rem; color: #2e7d32;">Schedules for ${new Date(dateString).toLocaleDateString()}</h4>`;
        
        if (daySchedules.length === 0) {
            html += `<div class="empty-state">No schedules for this day</div>`;
        } else {
            daySchedules.forEach(schedule => {
                html += `
                    <div class="schedule-item" style="background-color: #f9f9f9; padding: 1rem; margin-bottom: 1rem; border-radius: 5px;">
                        <div class="detail-item">
                            <label>Zone:</label>
                            <span>${schedule.zone || 'All Zones'}</span>
                        </div>
                        <div class="detail-item">
                            <label>Status:</label>
                            <span class="status-badge ${schedule.status.toLowerCase()}">${schedule.status}</span>
                        </div>
                        ${schedule.notes ? `
                            <div class="detail-item">
                                <label>Notes:</label>
                                <span>${schedule.notes}</span>
                            </div>
                        ` : ''}
                        <div style="margin-top: 0.5rem;">
                            <button class="btn btn-small edit-schedule-btn" data-id="${schedule.id}">Edit</button>
                            <button class="btn btn-small btn-delete delete-schedule-btn" data-id="${schedule.id}">Delete</button>
                        </div>
                    </div>
                `;
            });
        }
        
        html += `
            <div class="form-actions" style="margin-top: 1rem;">
                <button class="btn btn-add" id="add-schedule-day-btn" data-date="${dateString}">Add New Schedule</button>
            </div>
        `;
        
        modalBody.innerHTML = html;
        
        document.querySelectorAll('.edit-schedule-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const scheduleId = this.getAttribute('data-id');
                editSchedule(scheduleId);
            });
        });
        
        document.querySelectorAll('.delete-schedule-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const scheduleId = this.getAttribute('data-id');
                deleteSchedule(scheduleId);
            });
        });
        
        document.getElementById('add-schedule-day-btn').addEventListener('click', function() {
            const dateString = this.getAttribute('data-date');
            addScheduleForDay(dateString);
        });
        
        modal.style.display = 'block';
    }
    
    prevMonthBtn.addEventListener('click', function() {
        currentDate.setMonth(currentDate.getMonth() - 1);
        renderCalendar(currentDate);
        updateSchedulesForMonth();
    });
    
    nextMonthBtn.addEventListener('click', function() {
        currentDate.setMonth(currentDate.getMonth() + 1);
        renderCalendar(currentDate);
        updateSchedulesForMonth();
    });
    
    closeModal.addEventListener('click', function() {
        modal.style.display = 'none';
    });
    
    window.addEventListener('click', function(event) {
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    });
    
    function updateSchedulesForMonth() {
        const year = currentDate.getFullYear();
        const month = String(currentDate.getMonth() + 1).padStart(2, '0');
        const monthString = `${year}-${month}`;
        
        let url = `php/get_schedules.php?month=${monthString}`;
        if (userBarangay) url += `&barangay=${encodeURIComponent(userBarangay)}`;
        if (userZone) url += `&zone=${encodeURIComponent(userZone)}`;

        fetch(url)
            .then(response => response.json())
            .then(data => {
                window.schedules = data;
                renderCalendar(currentDate);
            })
            .catch(error => {
                console.error('Error fetching schedules:', error);
                showToast('Error loading schedules', 'error');
            });
    }
    
    if (userType === 'admin') {
        document.getElementById('add-schedule-btn').addEventListener('click', function() {
            const today = new Date();
            const dateString = formatDate(today);
            addScheduleForDay(dateString);
        });

        document.getElementById('bulk-add-btn').addEventListener('click', function() {
            showBulkAddModal();
        });
        
        function addScheduleForDay(dateString) {
            modal.style.display = 'none';
            
            const addModal = document.createElement('div');
            addModal.className = 'modal';
            addModal.innerHTML = `
                <div class="modal-content">
                    <div class="modal-header">
                        <h3>Add Schedule for ${new Date(dateString).toLocaleDateString()}</h3>
                        <span class="close-modal">&times;</span>
                    </div>
                    <div class="modal-body">
                        <form id="add-schedule-form">
                            <div class="form-group">
                                <label for="zone">Zone (optional)</label>
                                <select id="zone">
                                    <option value="">All Zones</option>
                                    <option value="Zone 1">Zone 1</option>
                                    <option value="Zone 2">Zone 2</option>
                                    <option value="Zone 3">Zone 3</option>
                                    <option value="Zone 4">Zone 4</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="status">Status</label>
                                <select id="status">
                                    <option value="Scheduled">Scheduled</option>
                                    <option value="Completed">Completed</option>
                                    <option value="Delayed">Delayed</option>
                                    <option value="Cancelled">Cancelled</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="notes">Notes (optional)</label>
                                <textarea id="notes" placeholder="Add any notes about this schedule..."></textarea>
                            </div>
                            <div class="form-actions">
                                <button type="button" class="btn" onclick="this.closest('.modal').remove()">Cancel</button>
                                <button type="submit" class="btn btn-add">Save Schedule</button>
                            </div>
                        </form>
                    </div>
                </div>
            `;
            
            document.body.appendChild(addModal);
            
            addModal.querySelector('#add-schedule-form').addEventListener('submit', async function(e) {
                e.preventDefault();
                
                const formData = {
                    schedule_date: dateString,
                    barangay: userBarangay,
                    zone: document.getElementById('zone').value,
                    status: document.getElementById('status').value,
                    notes: document.getElementById('notes').value
                };
                
                try {
                    const response = await fetch('php/update_schedule.php?action=create', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify(formData)
                    });
                    
                    const result = await response.json();
                    
                    if(result.success) {
                        showToast('Schedule added successfully!', 'success');
                        addModal.remove();
                        updateSchedulesForMonth();
                    } else {
                        showToast(result.message, 'error');
                    }
                } catch(error) {
                    showToast('Error saving schedule: ' + error.message, 'error');
                }
            });
            
            addModal.querySelector('.close-modal').addEventListener('click', () => addModal.remove());
            addModal.style.display = 'block';
        }
        
        function editSchedule(scheduleId) {
            const schedule = window.schedules.find(s => s.id == scheduleId);
            if (!schedule) return;
            
            modal.style.display = 'none';
            
            const editModal = document.createElement('div');
            editModal.className = 'modal';
            editModal.innerHTML = `
                <div class="modal-content">
                    <div class="modal-header">
                        <h3>Edit Schedule</h3>
                        <span class="close-modal">&times;</span>
                    </div>
                    <div class="modal-body">
                        <form id="edit-schedule-form">
                            <input type="hidden" id="schedule-id" value="${schedule.id}">
                            <div class="form-group">
                                <label>Date:</label>
                                <span>${new Date(schedule.schedule_date).toLocaleDateString()}</span>
                            </div>
                            <div class="form-group">
                                <label>Zone:</label>
                                <span>${schedule.zone || 'All Zones'}</span>
                            </div>
                            <div class="form-group">
                                <label for="edit-status">Status</label>
                                <select id="edit-status">
                                    <option value="Scheduled" ${schedule.status === 'Scheduled' ? 'selected' : ''}>Scheduled</option>
                                    <option value="Completed" ${schedule.status === 'Completed' ? 'selected' : ''}>Completed</option>
                                    <option value="Delayed" ${schedule.status === 'Delayed' ? 'selected' : ''}>Delayed</option>
                                    <option value="Cancelled" ${schedule.status === 'Cancelled' ? 'selected' : ''}>Cancelled</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="edit-notes">Notes</label>
                                <textarea id="edit-notes">${schedule.notes || ''}</textarea>
                            </div>
                            <div class="form-actions">
                                <button type="button" class="btn" onclick="this.closest('.modal').remove()">Cancel</button>
                                <button type="submit" class="btn btn-add">Update Schedule</button>
                            </div>
                        </form>
                    </div>
                </div>
            `;
            
            document.body.appendChild(editModal);
            
            editModal.querySelector('#edit-schedule-form').addEventListener('submit', async function(e) {
                e.preventDefault();
                
                const formData = {
                    id: scheduleId,
                    status: document.getElementById('edit-status').value,
                    notes: document.getElementById('edit-notes').value
                };
                
                try {
                    const response = await fetch('php/update_schedule.php?action=update', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify(formData)
                    });
                    
                    const result = await response.json();
                    
                    if(result.success) {
                        showToast('Schedule updated successfully!', 'success');
                        editModal.remove();
                        updateSchedulesForMonth();
                    } else {
                        showToast(result.message, 'error');
                    }
                } catch(error) {
                    showToast('Error updating schedule: ' + error.message, 'error');
                }
            });
            
            editModal.querySelector('.close-modal').addEventListener('click', () => editModal.remove());
            editModal.style.display = 'block';
        }
        
        function deleteSchedule(scheduleId) {
            if (!confirm('Are you sure you want to delete this schedule?')) return;
            
            const formData = { id: scheduleId };
            
            fetch('php/update_schedule.php?action=delete', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(formData)
            })
            .then(response => response.json())
            .then(result => {
                if(result.success) {
                    showToast('Schedule deleted successfully!', 'success');
                    modal.style.display = 'none';
                    updateSchedulesForMonth();
                } else {
                    showToast(result.message, 'error');
                }
            })
            .catch(error => {
                showToast('Error deleting schedule: ' + error.message, 'error');
            });
        }
        
        function showBulkAddModal() {
            const bulkModal = document.createElement('div');
            bulkModal.className = 'modal';
            bulkModal.innerHTML = `
                <div class="modal-content">
                    <div class="modal-header">
                        <h3>Bulk Add Schedules</h3>
                        <span class="close-modal">&times;</span>
                    </div>
                    <div class="modal-body">
                        <form id="bulk-add-form">
                            <div class="form-group">
                                <label for="start-date">Start Date</label>
                                <input type="date" id="start-date" required>
                            </div>
                            <div class="form-group">
                                <label for="end-date">End Date</label>
                                <input type="date" id="end-date" required>
                            </div>
                            <div class="form-group">
                                <label for="frequency">Frequency</label>
                                <select id="frequency">
                                    <option value="daily">Daily</option>
                                    <option value="weekly">Weekly</option>
                                    <option value="biweekly">Bi-weekly</option>
                                    <option value="monthly">Monthly</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="zone">Zone (optional)</label>
                                <select id="zone">
                                    <option value="">All Zones</option>
                                    <option value="Zone 1">Zone 1</option>
                                    <option value="Zone 2">Zone 2</option>
                                    <option value="Zone 3">Zone 3</option>
                                    <option value="Zone 4">Zone 4</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="status">Status</label>
                                <select id="status">
                                    <option value="Scheduled">Scheduled</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="notes">Notes (optional)</label>
                                <textarea id="notes" placeholder="Add any notes about these schedules..."></textarea>
                            </div>
                            <div class="form-actions">
                                <button type="button" class="btn" onclick="this.closest('.modal').remove()">Cancel</button>
                                <button type="submit" class="btn btn-add">Generate Schedules</button>
                            </div>
                        </form>
                    </div>
                </div>
            `;
            
            document.body.appendChild(bulkModal);
            
            const today = new Date();
            const nextWeek = new Date(today);
            nextWeek.setDate(today.getDate() + 7);
            
            document.getElementById('start-date').value = formatDate(today);
            document.getElementById('end-date').value = formatDate(nextWeek);
            
            bulkModal.querySelector('#bulk-add-form').addEventListener('submit', async function(e) {
                e.preventDefault();
                
                const startDate = new Date(document.getElementById('start-date').value);
                const endDate = new Date(document.getElementById('end-date').value);
                const frequency = document.getElementById('frequency').value;
                const zone = document.getElementById('zone').value;
                const status = document.getElementById('status').value;
                const notes = document.getElementById('notes').value;
                
                const dates = [];
                let currentDate = new Date(startDate);
                
                while (currentDate <= endDate) {
                    dates.push(formatDate(currentDate));
                    
                    switch(frequency) {
                        case 'daily':
                            currentDate.setDate(currentDate.getDate() + 1);
                            break;
                        case 'weekly':
                            currentDate.setDate(currentDate.getDate() + 7);
                            break;
                        case 'biweekly':
                            currentDate.setDate(currentDate.getDate() + 14);
                            break;
                        case 'monthly':
                            currentDate.setMonth(currentDate.getMonth() + 1);
                            break;
                    }
                }
                
                let created = 0;
                let failed = 0;
                
                for (const date of dates) {
                    const formData = {
                        schedule_date: date,
                        barangay: userBarangay,
                        zone: zone,
                        status: status,
                        notes: notes
                    };
                    
                    try {
                        const response = await fetch('php/update_schedule.php?action=create', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify(formData)
                        });
                        
                        const result = await response.json();
                        
                        if(result.success) {
                            created++;
                        } else {
                            failed++;
                        }
                    } catch(error) {
                        failed++;
                    }
                }
                
                showToast(`Created ${created} schedules${failed > 0 ? `, ${failed} failed` : ''}`, 'success');
                bulkModal.remove();
                updateSchedulesForMonth();
            });
            
            bulkModal.querySelector('.close-modal').addEventListener('click', () => bulkModal.remove());
            bulkModal.style.display = 'block';
        }
    }
    
    function showToast(message, type = 'info') {
        document.querySelectorAll('.toast').forEach(toast => toast.remove());
        
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.textContent = message;
        
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.remove();
        }, 3000);
    }
    
    const style = document.createElement('style');
    style.textContent = `
        @keyframes fadeOut {
            from {
                opacity: 1;
                transform: translateY(0);
            }
            to {
                opacity: 0;
                transform: translateY(20px);
            }
        }
        .toast {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 1rem 1.5rem;
            border-radius: 5px;
            color: white;
            font-weight: 500;
            z-index: 3000;
            animation: slideInRight 0.3s ease, fadeOut 0.3s ease 2.7s forwards;
        }
        .toast.success {
            background-color: #4caf7d;
        }
        .toast.error {
            background-color: #f44336;
        }
        .toast.info {
            background-color: #2196f3;
        }
        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
    `;
    document.head.appendChild(style);
    
    renderCalendar(currentDate);
    updateSchedulesForMonth();

    setInterval(function() {
        updateSchedulesForMonth();
    }, 10000);
});