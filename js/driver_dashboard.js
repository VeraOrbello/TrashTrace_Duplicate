// Driver Dashboard JavaScript
document.addEventListener('DOMContentLoaded', function() {
    loadDriverStats();
    loadTodayAssignments();
});

function loadDriverStats() {
    fetch('php/get_driver_stats.php')
        .then(response => response.json())
        .then(data => {
            if(data.success) {
                document.getElementById('today-count').textContent = data.today_count;
                document.getElementById('completed-count').textContent = data.completed_count;
                document.getElementById('pending-count').textContent = data.pending_count;
            }
        })
        .catch(error => console.error('Error:', error));
}

function loadTodayAssignments() {
    fetch('php/get_driver_assignments.php')
        .then(response => response.json())
        .then(data => {
            if(data.success) {
                const list = document.getElementById('assignments-list');
                list.innerHTML = '';
                
                if(data.assignments.length === 0) {
                    list.innerHTML = '<p>No assignments for today.</p>';
                    return;
                }
                
                data.assignments.forEach(assignment => {
                    const item = document.createElement('div');
                    item.className = 'task-item';
                    item.innerHTML = `
                        <h4>${assignment.barangay} - Zone ${assignment.zone}</h4>
                        <p><strong>Time:</strong> ${assignment.schedule_time}</p>
                        <p><strong>Status:</strong> <span class="task-status status-${assignment.status}">${assignment.status}</span></p>
                        <button onclick="updateStatus(${assignment.id}, 'in_progress')" class="btn-small">Start</button>
                        <button onclick="updateStatus(${assignment.id}, 'completed')" class="btn-small">Complete</button>
                    `;
                    list.appendChild(item);
                });
            }
        });
}

function updateStatus(assignmentId, status) {
    fetch('php/update_assignment_status.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({id: assignmentId, status: status})
    })
    .then(response => response.json())
    .then(data => {
        if(data.success) {
            alert('Status updated!');
            loadTodayAssignments();
            loadDriverStats();
        }
    });
}

function startShift() {
    alert('Shift started! Tracking your location...');
    // Add location tracking here
}

function viewSchedule() {
    window.location.href = 'driver_schedule.php';
}

function reportIssue() {
    const issue = prompt('Describe the issue:');
    if(issue) {
        fetch('php/report_driver_issue.php', {
            method: 'POST',
            body: new URLSearchParams({issue: issue})
        });
    }
}