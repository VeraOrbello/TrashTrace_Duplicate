<?php
require_once "config.php";

if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: login.php");
    exit;
}

if($_SESSION["user_type"] !== 'admin'){
    header("location: res_schedule.php");
    exit;
}

$user_id = $_SESSION["id"];
$user_name = $_SESSION["full_name"] ?? 'User';

$sql = "SELECT barangay, city FROM users WHERE id = :id";
$stmt = $pdo->prepare($sql);
$stmt->bindParam(":id", $user_id, PDO::PARAM_INT);
$stmt->execute();
$user_data = $stmt->fetch(PDO::FETCH_ASSOC);

$user_barangay = $user_data['barangay'] ?? 'Unknown';
$user_city = $user_data['city'] ?? 'Unknown';
$_SESSION['barangay'] = $user_barangay;
$_SESSION['city'] = $user_city;

$current_month = date('Y-m');
$schedules = [];

$sql = "SELECT * FROM pickup_schedules WHERE barangay = :barangay AND DATE_FORMAT(schedule_date, '%Y-%m') = :current_month ORDER BY schedule_date";
if($stmt = $pdo->prepare($sql)){
    $stmt->bindParam(":barangay", $user_barangay, PDO::PARAM_STR);
    $stmt->bindParam(":current_month", $current_month, PDO::PARAM_STR);
    
    if($stmt->execute()){
        $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    unset($stmt);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barangay Schedule - TrashTrace</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/barangay_schedule.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <main class="schedule-main">
        <div class="container">
            <div class="schedule-header-section">
                <h1><i class="far fa-calendar-alt"></i> Pickup Schedule Management</h1>
            </div>
            
            <!-- Stats Row -->
            <div class="schedule-stats">
                <div class="stat-card">
                    <div class="stat-icon" style="background: #e8f5e9; color: #4caf50;">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo htmlspecialchars($user_barangay); ?></h3>
                        <p>Barangay</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background: #e3f2fd; color: #1976d2;">
                        <i class="fas fa-city"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo htmlspecialchars($user_city); ?></h3>
                        <p>City</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background: #fff3e0; color: #ff9800;">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo count($schedules); ?></h3>
                        <p>This Month</p>
                    </div>
                </div>
            </div>
            
            <!-- Calendar Card -->
            <div class="calendar-card">
                <div class="card-header">
                    <div class="month-navigation">
                        <button id="prev-month" class="nav-btn"><i class="fas fa-chevron-left"></i></button>
                        <h2 id="current-month"><?php echo date('F Y'); ?></h2>
                        <button id="next-month" class="nav-btn"><i class="fas fa-chevron-right"></i></button>
                    </div>
                    <div class="header-actions">
                        <button id="add-schedule-btn" class="btn-primary">
                            <i class="fas fa-plus"></i> Add Schedule
                        </button>
                        <button id="bulk-add-btn" class="btn-outline">
                            <i class="fas fa-calendar-plus"></i> Bulk Add
                        </button>
                    </div>
                </div>
                
                <div class="card-body">
                    <div class="calendar-container">
                        <div class="calendar-header">
                            <div class="day-header">Sun</div>
                            <div class="day-header">Mon</div>
                            <div class="day-header">Tue</div>
                            <div class="day-header">Wed</div>
                            <div class="day-header">Thu</div>
                            <div class="day-header">Fri</div>
                            <div class="day-header">Sat</div>
                        </div>
                        <div class="calendar-body" id="calendar-body">
                        </div>
                    </div>
                </div>
                
                <div class="card-footer">
                    <div class="schedule-legend">
                        <div class="legend-item">
                            <div class="color-box scheduled"></div>
                            <span>Scheduled</span>
                        </div>
                        <div class="legend-item">
                            <div class="color-box completed"></div>
                            <span>Completed</span>
                        </div>
                        <div class="legend-item">
                            <div class="color-box delayed"></div>
                            <span>Delayed</span>
                        </div>
                        <div class="legend-item">
                            <div class="color-box cancelled"></div>
                            <span>Cancelled</span>
                        </div>
                        <div class="legend-item">
                            <div class="color-box multiple"></div>
                            <span>Multiple</span>
                        </div>
                    </div>
                </div>
            </div>
            </div>
        </div>
    </main>

    <div id="schedule-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Schedule Details</h3>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body" id="modal-body-content">
            </div>
        </div>
    </div>

    <script>
        const schedules = <?php echo json_encode($schedules); ?>;
        const userBarangay = "<?php echo addslashes($user_barangay); ?>";
        const userCity = "<?php echo addslashes($user_city); ?>";
        const userId = <?php echo $user_id; ?>;
        
        console.log('User Barangay:', userBarangay);
        console.log('User City:', userCity);
        console.log('Number of schedules:', schedules.length);
        console.log('Schedules:', schedules);
        // Add this to your existing JavaScript in barangay_schedule.php

// Auto-sync functionality
let autoSyncInterval;
let lastSyncTime = null;

function startAutoSync() {
    // Clear any existing interval
    if(autoSyncInterval) {
        clearInterval(autoSyncInterval);
    }
    
    // Sync every 30 seconds
    autoSyncInterval = setInterval(syncWithDrivers, 30000);
    
    // Initial sync
    syncWithDrivers();
}

function syncWithDrivers() {
    console.log('Syncing with drivers...');
    
    fetch('admin_sync.php?action=get_assignment_stats')
        .then(response => response.json())
        .then(data => {
            if(data.success) {
                lastSyncTime = new Date();
                updateSyncStatus(data);
                
                // Update calendar if needed
                if(shouldUpdateCalendar(data)) {
                    updateSchedulesForMonth();
                }
            }
        })
        .catch(error => {
            console.error('Sync error:', error);
        });
}

function updateSyncStatus(data) {
    const syncIndicator = document.getElementById('sync-indicator') || createSyncIndicator();
    
    syncIndicator.innerHTML = `
        <span style="color: #4caf50;">
            <i class="fas fa-sync-alt"></i> Synced ${formatTimeAgo(lastSyncTime)}
        </span>
        <small style="color: #666; margin-left: 10px;">
            ${data.today?.total || 0} assignments today
        </small>
    `;
}

function createSyncIndicator() {
    const indicator = document.createElement('div');
    indicator.id = 'sync-indicator';
    indicator.style.cssText = `
        position: fixed;
        bottom: 20px;
        right: 20px;
        background: white;
        padding: 10px 15px;
        border-radius: 10px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        z-index: 1000;
        font-size: 0.9rem;
    `;
    document.body.appendChild(indicator);
    return indicator;
}

function formatTimeAgo(date) {
    if(!date) return 'just now';
    
    const seconds = Math.floor((new Date() - date) / 1000);
    
    if(seconds < 60) return `${seconds} sec ago`;
    if(seconds < 3600) return `${Math.floor(seconds / 60)} min ago`;
    return `${Math.floor(seconds / 3600)} hour${Math.floor(seconds / 3600) > 1 ? 's' : ''} ago`;
}

function shouldUpdateCalendar(data) {
    // Check if calendar needs update based on changes
    // This is a simplified check - you might want to implement more sophisticated logic
    return false;
}

// Add driver assignment functionality to day details
function showDriverAssignmentOptions(dateString) {
    // Fetch available drivers for this barangay
    fetch(`get_available_drivers.php?date=${dateString}&barangay=${userBarangay}`)
        .then(response => response.json())
        .then(drivers => {
            if(drivers.length > 0) {
                showDriverAssignmentModal(dateString, drivers);
            } else {
                showToast('No drivers available for this date', 'warning');
            }
        })
        .catch(error => {
            console.error('Error fetching drivers:', error);
            showToast('Error loading drivers', 'error');
        });
}

function showDriverAssignmentModal(dateString, drivers) {
    const modal = document.createElement('div');
    modal.className = 'modal';
    modal.innerHTML = `
        <div class="modal-content" style="max-width: 400px;">
            <div class="modal-header">
                <h3><i class="fas fa-user-plus"></i> Assign Driver</h3>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body">
                <p>Assign a driver for ${new Date(dateString).toLocaleDateString()}</p>
                <select id="driver-select" class="form-select" style="width: 100%; margin: 10px 0;">
                    ${drivers.map(driver => `
                        <option value="${driver.id}">${driver.full_name} (${driver.vehicle || 'No vehicle'})</option>
                    `).join('')}
                </select>
                <div class="form-actions">
                    <button class="btn" onclick="this.closest('.modal').remove()">Cancel</button>
                    <button class="btn btn-action" onclick="assignDriver('${dateString}')">Assign</button>
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    modal.querySelector('.close-modal').addEventListener('click', () => modal.remove());
    modal.style.display = 'block';
    
    modal.addEventListener('click', function(e) {
        if(e.target === modal) modal.remove();
    });
}

function assignDriver(dateString) {
    const driverId = document.getElementById('driver-select').value;
    const driverName = document.getElementById('driver-select').selectedOptions[0].text;
    
    fetch('admin_sync.php?action=assign_driver_to_schedule', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            schedule_date: dateString,
            driver_id: driverId,
            barangay: userBarangay
        })
    })
    .then(response => response.json())
    .then(data => {
        if(data.success) {
            showToast(`Assigned ${driverName} successfully`, 'success');
            document.querySelector('.modal').remove();
            updateSchedulesForMonth();
        } else {
            showToast('Error: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Network error', 'error');
    });
}

// Start auto-sync when page loads
document.addEventListener('DOMContentLoaded', function() {
    // Add a small delay to ensure everything is loaded
    setTimeout(startAutoSync, 2000);
    
    // Add sync button to calendar header
    const calendarHeader = document.querySelector('.calendar-header');
    if(calendarHeader) {
        const syncBtn = document.createElement('button');
        syncBtn.className = 'btn btn-outline';
        syncBtn.innerHTML = '<i class="fas fa-sync-alt"></i> Sync Now';
        syncBtn.style.marginLeft = '10px';
        syncBtn.onclick = syncWithDrivers;
        calendarHeader.querySelector('.header-actions').appendChild(syncBtn);
    }
});
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="js/barangay_schedule.js"></script>
</body>
</html>