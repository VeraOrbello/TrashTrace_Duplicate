// js/driver/assignments.js - Driver Assignments JavaScript

document.addEventListener('DOMContentLoaded', function() {
    // Auto-refresh assignments every 60 seconds
    const refreshInterval = setInterval(refreshAssignments, 60000);
    
    // Setup event listeners
    setupEventListeners();
    
    // Initialize real-time features
    initializeRealTimeFeatures();
});

function refreshAssignments() {
    console.log('Refreshing assignments...');
    
    // You can implement AJAX refresh here
    // Example:
    // fetch('php/get_driver_assignments.php')
    //     .then(response => response.json())
    //     .then(data => updateAssignments(data));
}

function setupEventListeners() {
    // Start assignment button confirmation
    const startButtons = document.querySelectorAll('button[type="submit"][name="action"][value="start_assignment"]');
    startButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            if (!confirm('Are you ready to start this assignment?')) {
                e.preventDefault();
            }
        });
    });
    
    // Complete assignment button confirmation
    const completeButtons = document.querySelectorAll('button[type="submit"][name="action"][value="complete_assignment"]');
    completeButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            if (!confirm('Mark this assignment as complete? This action cannot be undone.')) {
                e.preventDefault();
            }
        });
    });
    
    // Assignment item click handlers
    const assignmentItems = document.querySelectorAll('.assignment-item');
    assignmentItems.forEach(item => {
        item.addEventListener('click', function() {
            const assignmentId = this.dataset.assignmentId;
            if (assignmentId) {
                window.location.href = `driver/collections.php?assignment=${assignmentId}`;
            }
        });
    });
    
    // Progress animation
    animateProgressBars();
}

function initializeRealTimeFeatures() {
    // Check if browser supports geolocation
    if ('geolocation' in navigator) {
        // Update driver location every 30 seconds
        setInterval(updateDriverLocation, 30000);
        
        // Get initial location
        updateDriverLocation();
    }
    
    // Setup WebSocket for real-time updates (if implemented)
    setupWebSocket();
}

function updateDriverLocation() {
    navigator.geolocation.getCurrentPosition(
        (position) => {
            const lat = position.coords.latitude;
            const lng = position.coords.longitude;
            const accuracy = position.coords.accuracy;
            
            console.log(`Location: ${lat}, ${lng} (Accuracy: ${accuracy}m)`);
            
            // Send to server
            // fetch('php/driver_location.php', {
            //     method: 'POST',
            //     headers: {
            //         'Content-Type': 'application/json',
            //     },
            //     body: JSON.stringify({
            //         latitude: lat,
            //         longitude: lng,
            //         accuracy: accuracy
            //     })
            // });
        },
        (error) => {
            console.error('Error getting location:', error.message);
        },
        {
            enableHighAccuracy: true,
            timeout: 5000,
            maximumAge: 0
        }
    );
}

function setupWebSocket() {
    // Example WebSocket implementation
    // const ws = new WebSocket('ws://your-server:port');
    
    // ws.onopen = function() {
    //     console.log('WebSocket connected');
    // };
    
    // ws.onmessage = function(event) {
    //     const data = JSON.parse(event.data);
    //     handleRealTimeUpdate(data);
    // };
    
    // ws.onclose = function() {
    //     console.log('WebSocket disconnected');
    // };
}

function handleRealTimeUpdate(data) {
    switch(data.type) {
        case 'new_assignment':
            showNotification('New assignment received!', 'info');
            refreshAssignments();
            break;
            
        case 'assignment_updated':
            showNotification('Assignment updated', 'warning');
            refreshAssignments();
            break;
            
        case 'emergency_alert':
            showNotification('EMERGENCY: ' + data.message, 'danger');
            break;
            
        default:
            console.log('Unknown update:', data);
    }
}

function showNotification(message, type = 'info') {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <div class="notification-content">
            <i class="fas fa-bell"></i>
            <span>${message}</span>
        </div>
        <button class="notification-close">&times;</button>
    `;
    
    // Add to page
    document.body.appendChild(notification);
    
    // Add styles if not already present
    if (!document.querySelector('#notification-styles')) {
        const style = document.createElement('style');
        style.id = 'notification-styles';
        style.textContent = `
            .notification {
                position: fixed;
                top: 20px;
                right: 20px;
                background: white;
                padding: 15px 20px;
                border-radius: 10px;
                box-shadow: 0 5px 20px rgba(0,0,0,0.2);
                display: flex;
                align-items: center;
                gap: 15px;
                z-index: 1000;
                animation: slideIn 0.3s ease;
                max-width: 400px;
            }
            
            .notification-info {
                border-left: 4px solid #2196F3;
            }
            
            .notification-warning {
                border-left: 4px solid #FFC107;
            }
            
            .notification-danger {
                border-left: 4px solid #F44336;
            }
            
            .notification-content {
                display: flex;
                align-items: center;
                gap: 10px;
                flex: 1;
            }
            
            .notification-close {
                background: none;
                border: none;
                font-size: 20px;
                cursor: pointer;
                color: #666;
            }
            
            @keyframes slideIn {
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
    }
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => notification.remove(), 300);
    }, 5000);
    
    // Close button
    notification.querySelector('.notification-close').addEventListener('click', () => {
        notification.remove();
    });
}

function animateProgressBars() {
    const progressBars = document.querySelectorAll('.progress-fill');
    
    progressBars.forEach(bar => {
        const width = bar.style.width;
        bar.style.width = '0%';
        
        setTimeout(() => {
            bar.style.transition = 'width 1s ease';
            bar.style.width = width;
        }, 100);
    });
}

// Utility function to format time
function formatTime(timeString) {
    const time = new Date(`2000-01-01T${timeString}`);
    return time.toLocaleTimeString('en-US', {
        hour: 'numeric',
        minute: '2-digit',
        hour12: true
    });
}

// Export functions for use in other files
window.driverAssignments = {
    refreshAssignments,
    updateDriverLocation,
    showNotification
};