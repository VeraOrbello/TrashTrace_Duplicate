document.addEventListener('DOMContentLoaded', function() {
    let currentDate = new Date();
    const calendarBody = document.getElementById('calendar-body');
    const currentMonthElement = document.getElementById('current-month');
    const prevMonthBtn = document.getElementById('prev-month');
    const nextMonthBtn = document.getElementById('next-month');
    const modal = document.getElementById('schedule-modal');
    const closeModal = document.querySelector('.close-modal');
    const addScheduleBtn = document.getElementById('add-schedule-btn');
    const bulkAddBtn = document.getElementById('bulk-add-btn');
    
    console.log('Initializing barangay schedule...');
    console.log('User Barangay:', userBarangay);
    console.log('User City:', userCity);
    console.log('Initial schedules count:', schedules.length);
    
    window.schedules = schedules || [];
    window.userBarangay = userBarangay;
    
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
            
            if (daySchedules.length === 1) {
                const schedule = daySchedules[0];
                const indicator = document.createElement('div');
                indicator.className = `schedule-indicator ${schedule.status.toLowerCase()}`;
                indicator.textContent = schedule.status;
                dayElement.appendChild(indicator);
            } else {
                const indicator = document.createElement('div');
                indicator.className = 'schedule-indicator multiple';
                indicator.textContent = `${daySchedules.length} schedules`;
                dayElement.appendChild(indicator);
            }
            
            dayElement.addEventListener('click', function() {
                showDayDetails(dateString, daySchedules);
            });
        } else {
            dayElement.addEventListener('click', function() {
                showEmptyDayDetails(dateString);
            });
        }
        
        calendarBody.appendChild(dayElement);
    }
    
    function formatDate(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }
    
    function showDayDetails(dateString, daySchedules) {
        const modalBody = document.getElementById('modal-body-content');
        
        let html = `<h4 style="margin-bottom: 1rem; color: #2e7d32;">Schedules for ${new Date(dateString).toLocaleDateString()}</h4>`;
        
        if (daySchedules.length === 0) {
            html += `
                <div style="text-align: center; padding: 2rem; color: #666;">
                    <p>No schedules for this day</p>
                    <button class="btn btn-action" id="add-empty-schedule-btn" data-date="${dateString}" style="margin-top: 1rem;">
                        Add Schedule
                    </button>
                </div>
            `;
        } else {
            daySchedules.forEach(schedule => {
                html += `
                    <div class="schedule-item">
                        <div class="schedule-item-header">
                            <div style="font-weight: 600; color: #555;">
                                Schedule ID: ${schedule.id}
                            </div>
                            <span class="status-badge ${schedule.status.toLowerCase()}">${schedule.status}</span>
                        </div>
                        ${schedule.notes ? `
                            <div style="margin-bottom: 0.5rem; color: #666;">
                                <strong>Notes:</strong> ${schedule.notes}
                            </div>
                        ` : ''}
                        <div style="display: flex; gap: 0.5rem; margin-top: 0.5rem;">
                            <button class="btn-edit edit-schedule-btn" data-id="${schedule.id}">
                                Edit
                            </button>
                            <button class="btn-delete delete-schedule-btn" data-id="${schedule.id}">
                                Delete
                            </button>
                        </div>
                    </div>
                `;
            });
        }
        
        html += `
            <div class="form-actions">
                <button class="btn btn-action" id="add-schedule-day-btn" data-date="${dateString}">
                    Add New Schedule
                </button>
            </div>
        `;
        
        modalBody.innerHTML = html;
        
        
        const addEmptyBtn = document.getElementById('add-empty-schedule-btn');
        if (addEmptyBtn) {
            addEmptyBtn.addEventListener('click', function() {
                const dateString = this.getAttribute('data-date');
                addScheduleForDay(dateString);
            });
        }
        
        document.getElementById('add-schedule-day-btn').addEventListener('click', function() {
            const dateString = this.getAttribute('data-date');
            addScheduleForDay(dateString);
        });
        
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
        
        modal.style.display = 'block';
    }
    
    function showEmptyDayDetails(dateString) {
        const modalBody = document.getElementById('modal-body-content');
        
        const html = `
            <div style="text-align: center; padding: 2rem; color: #666;">
                <h4 style="margin-bottom: 1rem; color: #2e7d32;">No schedules for ${new Date(dateString).toLocaleDateString()}</h4>
                <p>There are no pickup schedules for this day.</p>
                <button class="btn btn-action" id="add-schedule-from-empty-btn" data-date="${dateString}" style="margin-top: 1rem;">
                    Add Schedule
                </button>
            </div>
        `;
        
        modalBody.innerHTML = html;
        
        document.getElementById('add-schedule-from-empty-btn').addEventListener('click', function() {
            const dateString = this.getAttribute('data-date');
            addScheduleForDay(dateString);
        });
        
        modal.style.display = 'block';
    }
    
    function addScheduleForDay(dateString) {
        console.log('Adding schedule for:', dateString);
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
                            <label for="status">Status</label>
                            <select id="status" required>
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
                            <button type="submit" class="btn btn-action">Save Schedule</button>
                        </div>
                    </form>
                </div>
            </div>
        `;
        
        document.body.appendChild(addModal);
        
        addModal.querySelector('#add-schedule-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = 'Saving...';
            
            const formData = {
                schedule_date: dateString,
                barangay: userBarangay,
                status: document.getElementById('status').value,
                notes: document.getElementById('notes').value
            };
            
            console.log('Sending data to API:', formData);
            
            try {
                const response = await fetch('php/update_schedule.php?action=create', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(formData)
                });
                
                console.log('Response status:', response.status);
                const result = await response.json();
                console.log('API Response:', result);
                
                if(result.success) {
                    showToast('Schedule added successfully!', 'success');
                    addModal.remove();
                    updateSchedulesForMonth();
                } else {
                    showToast('Error: ' + (result.message || 'Unknown error'), 'error');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            } catch(error) {
                console.error('Fetch error:', error);
                showToast('Network error: ' + error.message, 'error');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
            
            return false;
        });
        
        addModal.querySelector('.close-modal').addEventListener('click', () => addModal.remove());
        addModal.style.display = 'block';
        
        
        addModal.addEventListener('click', function(e) {
            if (e.target === addModal) {
                addModal.remove();
            }
        });
    }
    
    function editSchedule(scheduleId) {
        const schedule = window.schedules.find(s => s.id == scheduleId);
        if (!schedule) {
            showToast('Schedule not found', 'error');
            return;
        }
        
        console.log('Editing schedule:', schedule);
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
                            <button type="submit" class="btn btn-action">Update Schedule</button>
                        </div>
                    </form>
                </div>
            </div>
        `;
        
        document.body.appendChild(editModal);
        
        editModal.querySelector('#edit-schedule-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = 'Updating...';
            
            const formData = {
                id: scheduleId,
                status: document.getElementById('edit-status').value,
                notes: document.getElementById('edit-notes').value
            };
            
            console.log('Updating schedule:', formData);
            
            try {
                const response = await fetch('php/update_schedule.php?action=update', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(formData)
                });
                
                console.log('Update response status:', response.status);
                const result = await response.json();
                console.log('Update API Response:', result);
                
                if(result.success) {
                    showToast('Schedule updated successfully!', 'success');
                    editModal.remove();
                    updateSchedulesForMonth();
                } else {
                    showToast('Error: ' + (result.message || 'Unknown error'), 'error');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            } catch(error) {
                console.error('Update error:', error);
                showToast('Network error: ' + error.message, 'error');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
            
            return false;
        });
        
        editModal.querySelector('.close-modal').addEventListener('click', () => editModal.remove());
        editModal.style.display = 'block';
        
        
        editModal.addEventListener('click', function(e) {
            if (e.target === editModal) {
                editModal.remove();
            }
        });
    }
    
    function deleteSchedule(scheduleId) {
        if (!confirm('Are you sure you want to delete this schedule? This action cannot be undone.')) return;
        
        console.log('Deleting schedule:', scheduleId);
        showToast('Deleting schedule...', 'info');
        
        const formData = { id: scheduleId };
        
        fetch('php/update_schedule.php?action=delete', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(formData)
        })
        .then(response => {
            console.log('Delete response status:', response.status);
            return response.json();
        })
        .then(result => {
            console.log('Delete result:', result);
            if(result.success) {
                showToast('Schedule deleted successfully!', 'success');
                modal.style.display = 'none';
                updateSchedulesForMonth();
            } else {
                showToast('Error: ' + (result.message || 'Unknown error'), 'error');
            }
        })
        .catch(error => {
            console.error('Delete error:', error);
            showToast('Network error: ' + error.message, 'error');
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
                            <label for="bulk-status">Status</label>
                            <select id="bulk-status">
                                <option value="Scheduled">Scheduled</option>
                                <option value="Completed">Completed</option>
                                <option value="Delayed">Delayed</option>
                                <option value="Cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="bulk-notes">Notes (optional)</label>
                            <textarea id="bulk-notes" placeholder="Add any notes about these schedules..."></textarea>
                        </div>
                        <div class="form-actions">
                            <button type="button" class="btn" onclick="this.closest('.modal').remove()">Cancel</button>
                            <button type="submit" class="btn btn-action">Generate Schedules</button>
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
            e.stopPropagation();
            
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = 'Generating...';
            
            const startDate = new Date(document.getElementById('start-date').value);
            const endDate = new Date(document.getElementById('end-date').value);
            const frequency = document.getElementById('frequency').value;
            const status = document.getElementById('bulk-status').value;
            const notes = document.getElementById('bulk-notes').value;
            
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
            
            console.log(`Generating ${dates.length} schedules from ${startDate.toDateString()} to ${endDate.toDateString()}`);
            showToast(`Generating ${dates.length} schedules...`, 'info');
            
            let created = 0;
            let failed = 0;
            const errors = [];
            
            for (const date of dates) {
                const formData = {
                    schedule_date: date,
                    barangay: userBarangay,
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
                        errors.push(`${date}: ${result.message}`);
                    }
                } catch(error) {
                    failed++;
                    errors.push(`${date}: ${error.message}`);
                }
            }
            
            let message = `Successfully created ${created} schedule${created !== 1 ? 's' : ''}`;
            if (failed > 0) {
                message += `, ${failed} failed`;
                console.error('Failed schedules:', errors);
            }
            
            showToast(message, 'success');
            bulkModal.remove();
            updateSchedulesForMonth();
            
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        });
        
        bulkModal.querySelector('.close-modal').addEventListener('click', () => bulkModal.remove());
        bulkModal.style.display = 'block';
        
        
        bulkModal.addEventListener('click', function(e) {
            if (e.target === bulkModal) {
                bulkModal.remove();
            }
        });
    }
    
    function updateSchedulesForMonth() {
        const year = currentDate.getFullYear();
        const month = String(currentDate.getMonth() + 1).padStart(2, '0');
        const monthString = `${year}-${month}`;
        
        console.log('Fetching schedules for:', monthString, 'Barangay:', userBarangay);
        showToast('Loading schedules...', 'info');
        
        fetch(`php/get_schedules.php?month=${monthString}&barangay=${userBarangay}`)
            .then(response => {
                console.log('Schedules response status:', response.status);
                return response.json();
            })
            .then(data => {
                console.log('Received schedules:', data);
                window.schedules = data;
                renderCalendar(currentDate);
                showToast('Schedules loaded', 'success');
            })
            .catch(error => {
                console.error('Error fetching schedules:', error);
                showToast('Error loading schedules: ' + error.message, 'error');
            });
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
    
    addScheduleBtn.addEventListener('click', function() {
        const today = new Date();
        const dateString = formatDate(today);
        addScheduleForDay(dateString);
    });
    
    bulkAddBtn.addEventListener('click', showBulkAddModal);
    
    
    renderCalendar(currentDate);
    console.log('Calendar initialized');
    
    
    const style = document.createElement('style');
    style.textContent = `
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
        @keyframes fadeOut {
            from {
                opacity: 1;
            }
            to {
                opacity: 0;
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
    `;
    document.head.appendChild(style);
});