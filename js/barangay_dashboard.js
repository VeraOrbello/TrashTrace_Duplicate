const user_id = <?php echo json_encode($user_id); ?>;
const user_barangay = <?php echo json_encode($user_barangay); ?>;
const initial_pending_count = <?php echo $pending_applications; ?>;
const initial_unread_count = <?php echo $unread_count; ?>;


function showLiveIndicator(show = true) {
    const indicator = document.getElementById('liveUpdateIndicator');
    if (indicator) {
        indicator.style.display = show ? 'flex' : 'none';
    }
}

function highlightUpdate(elementId) {
    const element = document.getElementById(elementId);
    if (element) {
        element.classList.add('real-time-update');
        setTimeout(() => {
            element.classList.remove('real-time-update');
        }, 2000);
    }
}

function updateDashboardData() {
    showLiveIndicator(true);
    
    fetch('php/get_barangay_data.php?user_id=' + user_id + '&barangay=' + user_barangay + '&t=' + new Date().getTime())
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                
                if (data.todays_stats) {
                    const wasteType = document.querySelector('.waste-type');
                    if (wasteType) {
                        wasteType.textContent = `Total: ${data.todays_stats.total} | Completed: ${data.todays_stats.completed} | Delayed: ${data.todays_stats.delayed}`;
                        highlightUpdate('todaysStats');
                    }
                    
                    const completionRate = data.todays_stats.total > 0 ? 
                        Math.round((data.todays_stats.completed / data.todays_stats.total) * 100) : 0;
                    const progressFill = document.getElementById('progressFill');
                    const progressText = document.getElementById('progressText');
                    const pickupStatusText = document.getElementById('pickupStatusText');
                    
                    if (progressFill) {
                        progressFill.style.width = completionRate + '%';
                        highlightUpdate('progressFill');
                    }
                    if (progressText) {
                        progressText.textContent = completionRate + '% of today\'s pickups completed.';
                        highlightUpdate('progressText');
                    }
                    
                    if (pickupStatusText) {
                        pickupStatusText.textContent = data.todays_stats.total > 0 ? 'Pickup in Progress' : 'No scheduled pickups';
                        highlightUpdate('pickupStatusText');
                    }
                }
                
                const applicationsCount = document.getElementById('applicationsCount');
                if (applicationsCount) {
                    applicationsCount.textContent = data.pending_applications + ' pending';
                    highlightUpdate('applicationsCount');
                }
                
                if (data.new_notifications && data.new_notifications.length > 0) {
                    showNewNotificationAlert(data.new_notifications);
                }
                
                if (data.last_updated) {
                    console.log('Dashboard updated at:', data.last_updated);
                }
            }
            
            showLiveIndicator(false);
        })
        .catch(error => {
            console.error('Error updating dashboard:', error);
            showLiveIndicator(false);
        });
}

function showNewNotificationAlert(notifications) {
    notifications.forEach(notification => {
        const alert = document.createElement('div');
        alert.className = 'new-notification-alert';
        alert.innerHTML = `
            <strong>${notification.title}</strong>
            <p style="margin: 5px 0 0 0; font-size: 12px;">${notification.message}</p>
            <small style="display: block; margin-top: 3px; opacity: 0.9;">Just now</small>
        `;
        
        alert.addEventListener('click', function() {
            window.location.href = 'barangay_notifications.php';
        });
        
        document.body.appendChild(alert);
        
        setTimeout(() => {
            if (alert.parentNode) {
                alert.style.animation = 'slideOut 0.3s ease-out forwards';
                setTimeout(() => {
                    if (alert.parentNode) alert.remove();
                }, 300);
            }
        }, 5000);
    });
}

setInterval(updateDashboardData, 5000);

setTimeout(updateDashboardData, 2000);