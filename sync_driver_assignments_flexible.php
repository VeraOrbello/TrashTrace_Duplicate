<?php
require_once "config.php";

header('Content-Type: application/json');

// Check if user is logged in
if(!isset($_SESSION["loggedin"])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

// Get user type
$user_type = $_SESSION["user_type"] ?? '';
$user_id = $_SESSION["id"] ?? 0;

// Get action from request
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Handle different sync actions
switch($action) {
    case 'get_driver_assignments':
        getDriverAssignments();
        break;
    case 'get_admin_schedules':
        getAdminSchedules();
        break;
    case 'update_assignment_status':
        updateAssignmentStatus();
        break;
    case 'get_sync_status':
        getSyncStatus();
        break;
    case 'sync_all':
        syncAllData();
        break;
    case 'test_connection':
        testConnection();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function testConnection() {
    global $pdo;
    
    try {
        // Test table existence
        $tables = ['pickup_assignments', 'driver_daily_assignments', 'pickup_schedules', 'users'];
        $table_status = [];
        
        foreach($tables as $table) {
            $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
            $exists = $stmt->rowCount() > 0;
            $table_status[$table] = $exists;
        }
        
        // Test pickup_assignments columns
        if($table_status['pickup_assignments']) {
            $stmt = $pdo->query("DESCRIBE pickup_assignments");
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $table_status['pickup_assignments_columns'] = $columns;
        }
        
        echo json_encode([
            'success' => true,
            'tables' => $table_status,
            'driver_id' => $_SESSION["id"] ?? null,
            'user_type' => $_SESSION["user_type"] ?? null
        ]);
        
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function getDriverAssignments() {
    global $pdo, $user_id;
    
    $driver_barangay = getDriverBarangay($user_id);
    
    if(!$driver_barangay) {
        echo json_encode(['success' => false, 'message' => 'Driver not assigned to any barangay']);
        return;
    }
    
    try {
        $today = date('Y-m-d');
        $all_assignments = [];
        
        // 1. Get assignments from pickup_schedules (admin calendar)
        $sql = "SELECT 
                    ps.id,
                    ps.schedule_date,
                    ps.schedule_date as assignment_date,
                    ps.status,
                    ps.notes,
                    ps.barangay,
                    'pickup_schedule' as source,
                    CONCAT('Zone - ', ps.barangay) as zone_name,
                    ps.barangay as area,
                    '08:00:00' as start_time,
                    '17:00:00' as end_time,
                    '150-200 kg' as estimated_weight,
                    15 as stops,
                    'Truck #TR-001' as vehicle,
                    0 as progress,
                    NULL as driver_id
                FROM pickup_schedules ps
                WHERE ps.barangay = :barangay 
                AND DATE(ps.schedule_date) >= :today
                AND ps.status IN ('Scheduled', 'scheduled')
                ORDER BY ps.schedule_date ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':barangay' => $driver_barangay,
            ':today' => $today
        ]);
        
        $schedule_assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $all_assignments = array_merge($all_assignments, $schedule_assignments);
        
        // 2. Get from pickup_assignments table - check columns first
        $stmt = $pdo->query("DESCRIBE pickup_assignments");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $has_barangay = in_array('barangay', $columns);
        $has_assignment_date = in_array('assignment_date', $columns) || in_array('schedule_date', $columns);
        
        if($has_assignment_date) {
            $date_field = in_array('assignment_date', $columns) ? 'assignment_date' : 'schedule_date';
            
            $sql = "SELECT 
                        pa.*,
                        'pickup_assignment' as source,
                        pa.zone_name,
                        pa.area,
                        pa.estimated_weight,
                        pa.stops,
                        pa.vehicle,
                        pa.progress
                    FROM pickup_assignments pa
                    WHERE pa.driver_id = :driver_id ";
            
            // Add barangay filter if column exists
            if($has_barangay) {
                $sql .= " AND pa.barangay = :barangay ";
            }
            
            $sql .= " AND DATE(pa.$date_field) >= :today
                    ORDER BY pa.$date_field ASC";
            
            $params = [
                ':driver_id' => $user_id,
                ':today' => $today
            ];
            
            if($has_barangay) {
                $params[':barangay'] = $driver_barangay;
            }
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            $pickup_assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $all_assignments = array_merge($all_assignments, $pickup_assignments);
        }
        
        // 3. Get from driver_daily_assignments table
        $stmt = $pdo->query("DESCRIBE driver_daily_assignments");
        $daily_columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $has_daily_assignment_date = in_array('assignment_date', $daily_columns);
        $has_daily_barangay = in_array('barangay', $daily_columns);
        
        if($has_daily_assignment_date) {
            $sql = "SELECT 
                        dda.*,
                        'daily_assignment' as source,
                        dda.zones as zone_name,
                        dda.barangay as area,
                        '100-200 kg' as estimated_weight,
                        10 as stops,
                        'Assigned Truck' as vehicle,
                        0 as progress
                    FROM driver_daily_assignments dda
                    WHERE dda.driver_id = :driver_id ";
            
            if($has_daily_barangay) {
                $sql .= " AND dda.barangay = :barangay ";
            }
            
            $sql .= " AND DATE(dda.assignment_date) >= :today
                    ORDER BY dda.assignment_date ASC";
            
            $params = [
                ':driver_id' => $user_id,
                ':today' => $today
            ];
            
            if($has_daily_barangay) {
                $params[':barangay'] = $driver_barangay;
            }
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            $daily_assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $all_assignments = array_merge($all_assignments, $daily_assignments);
        }
        
        echo json_encode([
            'success' => true,
            'assignments' => $all_assignments,
            'barangay' => $driver_barangay,
            'count' => count($all_assignments),
            'timestamp' => date('Y-m-d H:i:s'),
            'table_info' => [
                'pickup_assignments_has_barangay' => $has_barangay ?? false,
                'pickup_assignments_has_date' => $has_assignment_date ?? false,
                'daily_assignments_has_barangay' => $has_daily_barangay ?? false
            ]
        ]);
        
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function getAdminSchedules() {
    global $pdo, $user_id;
    
    if(!isset($_SESSION["user_type"]) || $_SESSION["user_type"] !== 'admin') {
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        return;
    }
    
    try {
        // Get barangay from admin profile
        $sql = "SELECT barangay FROM users WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $user_id]);
        $admin_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if(!$admin_data || empty($admin_data['barangay'])) {
            echo json_encode(['success' => false, 'message' => 'Admin barangay not set']);
            return;
        }
        
        $barangay = $admin_data['barangay'];
        $month = $_GET['month'] ?? date('Y-m');
        
        // Get schedules for the month
        $sql = "SELECT 
                    ps.*,
                    DATE(ps.schedule_date) as schedule_date_only
                FROM pickup_schedules ps
                WHERE ps.barangay = :barangay 
                AND DATE_FORMAT(ps.schedule_date, '%Y-%m') = :month
                ORDER BY ps.schedule_date ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':barangay' => $barangay,
            ':month' => $month
        ]);
        
        $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get driver assignments for this barangay from pickup_assignments
        $stmt = $pdo->query("DESCRIBE pickup_assignments");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $has_barangay = in_array('barangay', $columns);
        
        if($has_barangay) {
            $sql = "SELECT 
                        pa.*,
                        u.full_name as driver_name
                    FROM pickup_assignments pa
                    LEFT JOIN users u ON pa.driver_id = u.id
                    WHERE pa.barangay = :barangay 
                    AND DATE_FORMAT(pa.assignment_date, '%Y-%m') = :month
                    ORDER BY pa.assignment_date ASC";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':barangay' => $barangay,
                ':month' => $month
            ]);
            
            $driver_assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $driver_assignments = [];
        }
        
        // Get daily assignments
        $stmt = $pdo->query("DESCRIBE driver_daily_assignments");
        $daily_columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $has_daily_barangay = in_array('barangay', $daily_columns);
        
        if($has_daily_barangay) {
            $sql = "SELECT 
                        dda.*,
                        u.full_name as driver_name
                    FROM driver_daily_assignments dda
                    LEFT JOIN users u ON dda.driver_id = u.id
                    WHERE dda.barangay = :barangay 
                    AND DATE_FORMAT(dda.assignment_date, '%Y-%m') = :month
                    ORDER BY dda.assignment_date ASC";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':barangay' => $barangay,
                ':month' => $month
            ]);
            
            $daily_assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $daily_assignments = [];
        }
        
        echo json_encode([
            'success' => true,
            'schedules' => $schedules,
            'driver_assignments' => $driver_assignments,
            'daily_assignments' => $daily_assignments,
            'barangay' => $barangay,
            'month' => $month,
            'sync_status' => 'updated'
        ]);
        
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function updateAssignmentStatus() {
    global $pdo;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if(!$data) {
        echo json_encode(['success' => false, 'message' => 'No data received']);
        return;
    }
    
    $assignment_id = $data['assignment_id'] ?? null;
    $status = $data['status'] ?? null;
    $assignment_type = $data['assignment_type'] ?? 'pickup_assignment';
    $driver_id = $data['driver_id'] ?? $_SESSION["id"] ?? null;
    
    if(!$assignment_id || !$status || !$driver_id) {
        echo json_encode(['success' => false, 'message' => 'Missing required data']);
        return;
    }
    
    try {
        if($assignment_type === 'pickup_schedule') {
            // Update pickup_schedules table
            $sql = "UPDATE pickup_schedules SET status = :status, updated_at = NOW() WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':status' => $status,
                ':id' => $assignment_id
            ]);
            
            // Get schedule details
            $sql = "SELECT schedule_date, barangay FROM pickup_schedules WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':id' => $assignment_id]);
            $schedule = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if($schedule) {
                // Check if pickup_assignments table has barangay column
                $stmt = $pdo->query("DESCRIBE pickup_assignments");
                $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
                $has_barangay = in_array('barangay', $columns);
                $has_schedule_id = in_array('schedule_id', $columns);
                
                // Create or update pickup_assignment record
                if($has_barangay && $has_schedule_id) {
                    $sql = "INSERT INTO pickup_assignments 
                            (driver_id, zone_name, assignment_date, start_time, end_time, status, area, barangay, schedule_id, created_at) 
                            VALUES (:driver_id, :zone_name, :assignment_date, :start_time, :end_time, :status, :area, :barangay, :schedule_id, NOW())
                            ON DUPLICATE KEY UPDATE 
                            status = VALUES(status),
                            updated_at = NOW()";
                    
                    $zone_name = "Zone - " . $schedule['barangay'];
                    
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        ':driver_id' => $driver_id,
                        ':zone_name' => $zone_name,
                        ':assignment_date' => $schedule['schedule_date'],
                        ':start_time' => '08:00:00',
                        ':end_time' => '17:00:00',
                        ':status' => $status,
                        ':area' => $schedule['barangay'],
                        ':barangay' => $schedule['barangay'],
                        ':schedule_id' => $assignment_id
                    ]);
                } else {
                    // Simplified insert without barangay and schedule_id
                    $sql = "INSERT INTO pickup_assignments 
                            (driver_id, assignment_date, status, created_at) 
                            VALUES (:driver_id, :assignment_date, :status, NOW())
                            ON DUPLICATE KEY UPDATE 
                            status = VALUES(status),
                            updated_at = NOW()";
                    
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        ':driver_id' => $driver_id,
                        ':assignment_date' => $schedule['schedule_date'],
                        ':status' => $status
                    ]);
                }
                
                // Log the status change
                logStatusChange($driver_id, $assignment_id, 'pickup_schedule', $status, $schedule['barangay']);
            }
            
        } elseif($assignment_type === 'pickup_assignment') {
            // Check if status column exists
            $stmt = $pdo->query("DESCRIBE pickup_assignments");
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $has_status = in_array('status', $columns);
            
            if($has_status) {
                // Update pickup_assignments table
                $sql = "UPDATE pickup_assignments SET status = :status, updated_at = NOW() WHERE id = :id AND driver_id = :driver_id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':status' => $status,
                    ':id' => $assignment_id,
                    ':driver_id' => $driver_id
                ]);
                
                // Log the status change
                logStatusChange($driver_id, $assignment_id, 'pickup_assignment', $status);
            }
            
        } elseif($assignment_type === 'daily_assignment') {
            // Update driver_daily_assignments table
            $sql = "UPDATE driver_daily_assignments SET status = :status, updated_at = NOW() WHERE id = :id AND driver_id = :driver_id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':status' => $status,
                ':id' => $assignment_id,
                ':driver_id' => $driver_id
            ]);
            
            // Log the status change
            logStatusChange($driver_id, $assignment_id, 'daily_assignment', $status);
        }
        
        // Send notification to admin if status is completed or issue
        if(in_array($status, ['completed', 'cancelled', 'delayed'])) {
            sendStatusNotification($assignment_id, $assignment_type, $status, $driver_id);
        }
        
        echo json_encode([
            'success' => true, 
            'message' => 'Assignment status updated',
            'status' => $status,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function getSyncStatus() {
    global $pdo, $user_id;
    
    try {
        $user_type = $_SESSION["user_type"] ?? '';
        $barangay = '';
        
        if($user_type === 'driver') {
            $barangay = getDriverBarangay($user_id);
        } elseif($user_type === 'admin') {
            $sql = "SELECT barangay FROM users WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':id' => $user_id]);
            $admin_data = $stmt->fetch(PDO::FETCH_ASSOC);
            $barangay = $admin_data['barangay'] ?? '';
        }
        
        // Get last sync times
        $sync_data = [];
        
        // Check pickup_schedules
        $sql = "SELECT 
                    MAX(updated_at) as last_schedule_update,
                    COUNT(*) as schedule_count
                FROM pickup_schedules 
                WHERE barangay = :barangay";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':barangay' => $barangay]);
        $schedule_data = $stmt->fetch(PDO::FETCH_ASSOC);
        $sync_data['schedules'] = $schedule_data;
        
        echo json_encode([
            'success' => true,
            'user_type' => $user_type,
            'barangay' => $barangay,
            'sync_data' => $sync_data,
            'server_time' => date('Y-m-d H:i:s')
        ]);
        
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function syncAllData() {
    global $pdo, $user_id;
    
    $user_type = $_SESSION["user_type"] ?? '';
    
    if($user_type === 'driver') {
        getDriverAssignments();
    } elseif($user_type === 'admin') {
        getAdminSchedules();
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid user type for sync']);
    }
}

// Helper functions
function getDriverBarangay($driver_id) {
    global $pdo;
    
    try {
        // Check user table first
        $sql = "SELECT barangay FROM users WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $driver_id]);
        $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if($user_data && !empty($user_data['barangay'])) {
            return $user_data['barangay'];
        }
        
        // Check if driver_profiles table exists and has barangay column
        $stmt = $pdo->query("SHOW TABLES LIKE 'driver_profiles'");
        if($stmt->rowCount() > 0) {
            $stmt = $pdo->query("DESCRIBE driver_profiles");
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if(in_array('barangay', $columns)) {
                $sql = "SELECT barangay FROM driver_profiles WHERE driver_id = :driver_id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':driver_id' => $driver_id]);
                $driver_data = $stmt->fetch(PDO::FETCH_ASSOC);
                
                return $driver_data['barangay'] ?? '';
            }
        }
        
        return '';
        
    } catch(PDOException $e) {
        return '';
    }
}

function logStatusChange($driver_id, $assignment_id, $type, $status, $barangay = '') {
    global $pdo;
    
    try {
        // Check if assignment_logs table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'assignment_logs'");
        if($stmt->rowCount() > 0) {
            $sql = "INSERT INTO assignment_logs 
                    (driver_id, assignment_id, assignment_type, status, barangay, created_at) 
                    VALUES (:driver_id, :assignment_id, :assignment_type, :status, :barangay, NOW())";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':driver_id' => $driver_id,
                ':assignment_id' => $assignment_id,
                ':assignment_type' => $type,
                ':status' => $status,
                ':barangay' => $barangay
            ]);
        }
        
    } catch(PDOException $e) {
        // Silently fail logging
    }
}

function sendStatusNotification($assignment_id, $type, $status, $driver_id) {
    global $pdo;
    
    try {
        // Get driver info
        $sql = "SELECT full_name FROM users WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $driver_id]);
        $driver = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if(!$driver) return;
        
        // Get barangay info
        $barangay = getDriverBarangay($driver_id);
        
        // Create notification for admin
        $message = "Driver {$driver['full_name']} marked assignment as {$status}" . 
                  ($barangay ? " for {$barangay}" : "") . 
                  " on " . date('M d, Y');
        
        // Check if admin_notifications table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'admin_notifications'");
        if($stmt->rowCount() > 0) {
            $sql = "INSERT INTO admin_notifications 
                    (driver_id, assignment_id, assignment_type, notification_type, message, is_read, created_at) 
                    VALUES (:driver_id, :assignment_id, :assignment_type, :notification_type, :message, 0, NOW())";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':driver_id' => $driver_id,
                ':assignment_id' => $assignment_id,
                ':assignment_type' => $type,
                ':notification_type' => 'assignment_status',
                ':message' => $message
            ]);
        }
        
    } catch(PDOException $e) {
        // Silently fail notification
    }
}

function checkSyncRequired($last_update) {
    if(!$last_update) return true;
    
    $last_update_time = strtotime($last_update);
    $current_time = time();
    $minutes_since_update = ($current_time - $last_update_time) / 60;
    
    // Sync required if more than 5 minutes since last update
    return $minutes_since_update > 5;
}
?>