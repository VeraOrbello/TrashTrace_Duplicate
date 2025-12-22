<?php
require_once "config.php";

header('Content-Type: application/json');

if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

if($_SESSION["user_type"] !== 'admin'){
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$admin_id = $_SESSION["id"];

// Get action
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch($action) {
    case 'get_driver_activities':
        getDriverActivities();
        break;
    case 'update_schedule_status':
        updateScheduleStatus();
        break;
    case 'assign_driver_to_schedule':
        assignDriverToSchedule();
        break;
    case 'get_assignment_stats':
        getAssignmentStats();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function getDriverActivities() {
    global $pdo, $admin_id;
    
    try {
        // Get admin barangay
        $sql = "SELECT barangay FROM users WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $admin_id]);
        $admin_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if(!$admin_data || empty($admin_data['barangay'])) {
            echo json_encode(['success' => false, 'message' => 'Admin barangay not set']);
            return;
        }
        
        $barangay = $admin_data['barangay'];
        $date = $_GET['date'] ?? date('Y-m-d');
        
        // Get driver activities for the date
        $sql = "SELECT 
                    a.*,
                    u.full_name as driver_name,
                    u.phone as driver_phone,
                    ps.schedule_date,
                    ps.notes as schedule_notes
                FROM assignments a
                LEFT JOIN users u ON a.driver_id = u.id
                LEFT JOIN pickup_schedules ps ON a.schedule_id = ps.id
                WHERE a.barangay = :barangay 
                AND DATE(a.assignment_date) = :date
                ORDER BY a.start_time ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':barangay' => $barangay,
            ':date' => $date
        ]);
        
        $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get schedule for the day
        $sql = "SELECT * FROM pickup_schedules 
                WHERE barangay = :barangay 
                AND DATE(schedule_date) = :date";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':barangay' => $barangay,
            ':date' => $date
        ]);
        
        $schedule = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'date' => $date,
            'barangay' => $barangay,
            'schedule' => $schedule,
            'activities' => $activities,
            'count' => count($activities)
        ]);
        
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function updateScheduleStatus() {
    global $pdo;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if(!$data) {
        echo json_encode(['success' => false, 'message' => 'No data received']);
        return;
    }
    
    $schedule_id = $data['schedule_id'] ?? null;
    $status = $data['status'] ?? null;
    $notes = $data['notes'] ?? '';
    
    if(!$schedule_id || !$status) {
        echo json_encode(['success' => false, 'message' => 'Missing required data']);
        return;
    }
    
    try {
        // Update schedule status
        $sql = "UPDATE pickup_schedules 
                SET status = :status, 
                    notes = :notes,
                    updated_at = NOW()
                WHERE id = :id";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':status' => $status,
            ':notes' => $notes,
            ':id' => $schedule_id
        ]);
        
        // Update related assignments
        $sql = "UPDATE assignments 
                SET status = :status,
                    updated_at = NOW()
                WHERE schedule_id = :schedule_id";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':status' => $status,
            ':schedule_id' => $schedule_id
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Schedule status updated',
            'status' => $status
        ]);
        
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function assignDriverToSchedule() {
    global $pdo;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if(!$data) {
        echo json_encode(['success' => false, 'message' => 'No data received']);
        return;
    }
    
    $schedule_id = $data['schedule_id'] ?? null;
    $driver_id = $data['driver_id'] ?? null;
    
    if(!$schedule_id || !$driver_id) {
        echo json_encode(['success' => false, 'message' => 'Missing required data']);
        return;
    }
    
    try {
        // Get schedule details
        $sql = "SELECT schedule_date, barangay FROM pickup_schedules WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $schedule_id]);
        $schedule = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if(!$schedule) {
            echo json_encode(['success' => false, 'message' => 'Schedule not found']);
            return;
        }
        
        // Get driver details
        $sql = "SELECT full_name, barangay as driver_barangay FROM users WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $driver_id]);
        $driver = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if(!$driver) {
            echo json_encode(['success' => false, 'message' => 'Driver not found']);
            return;
        }
        
        // Check if driver is already assigned to this schedule
        $sql = "SELECT id FROM assignments 
                WHERE schedule_id = :schedule_id 
                AND driver_id = :driver_id";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':schedule_id' => $schedule_id,
            ':driver_id' => $driver_id
        ]);
        
        $existing_assignment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if($existing_assignment) {
            echo json_encode(['success' => false, 'message' => 'Driver already assigned to this schedule']);
            return;
        }
        
        // Create assignment
        $sql = "INSERT INTO assignments 
                (driver_id, zone_name, assignment_date, start_time, end_time, status, area, barangay, schedule_id, created_at) 
                VALUES (:driver_id, :zone_name, :assignment_date, :start_time, :end_time, :status, :area, :barangay, :schedule_id, NOW())";
        
        $zone_name = "Zone - " . $schedule['barangay'];
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':driver_id' => $driver_id,
            ':zone_name' => $zone_name,
            ':assignment_date' => $schedule['schedule_date'],
            ':start_time' => '08:00:00',
            ':end_time' => '17:00:00',
            ':status' => 'scheduled',
            ':area' => $schedule['barangay'],
            ':barangay' => $schedule['barangay'],
            ':schedule_id' => $schedule_id
        ]);
        
        // Send notification to driver
        sendDriverAssignmentNotification($driver_id, $schedule_id, $schedule['schedule_date'], $schedule['barangay']);
        
        echo json_encode([
            'success' => true,
            'message' => 'Driver assigned successfully',
            'driver_name' => $driver['full_name'],
            'schedule_date' => $schedule['schedule_date']
        ]);
        
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function getAssignmentStats() {
    global $pdo, $admin_id;
    
    try {
        // Get admin barangay
        $sql = "SELECT barangay FROM users WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $admin_id]);
        $admin_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if(!$admin_data || empty($admin_data['barangay'])) {
            echo json_encode(['success' => false, 'message' => 'Admin barangay not set']);
            return;
        }
        
        $barangay = $admin_data['barangay'];
        
        // Get statistics
        $today = date('Y-m-d');
        $week_start = date('Y-m-d', strtotime('monday this week'));
        $week_end = date('Y-m-d', strtotime('sunday this week'));
        
        // Today's stats
        $sql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
                    SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) as scheduled
                FROM assignments 
                WHERE barangay = :barangay 
                AND DATE(assignment_date) = :today";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':barangay' => $barangay, ':today' => $today]);
        $today_stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Week's stats
        $sql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
                FROM assignments 
                WHERE barangay = :barangay 
                AND DATE(assignment_date) BETWEEN :week_start AND :week_end";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':barangay' => $barangay,
            ':week_start' => $week_start,
            ':week_end' => $week_end
        ]);
        $week_stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Driver performance
        $sql = "SELECT 
                    u.full_name as driver_name,
                    COUNT(a.id) as total_assignments,
                    SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) as completed_assignments,
                    ROUND(SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) * 100.0 / COUNT(a.id), 1) as completion_rate
                FROM assignments a
                JOIN users u ON a.driver_id = u.id
                WHERE a.barangay = :barangay
                AND a.assignment_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                GROUP BY a.driver_id, u.full_name
                ORDER BY completion_rate DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':barangay' => $barangay]);
        $driver_performance = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'barangay' => $barangay,
            'today' => $today_stats,
            'this_week' => $week_stats,
            'driver_performance' => $driver_performance,
            'updated' => date('Y-m-d H:i:s')
        ]);
        
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function sendDriverAssignmentNotification($driver_id, $schedule_id, $schedule_date, $barangay) {
    global $pdo;
    
    try {
        $message = "You have been assigned to a new pickup schedule in {$barangay} on " . date('M d, Y', strtotime($schedule_date));
        
        $sql = "INSERT INTO driver_notifications 
                (driver_id, schedule_id, message, is_read, created_at) 
                VALUES (:driver_id, :schedule_id, :message, 0, NOW())";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':driver_id' => $driver_id,
            ':schedule_id' => $schedule_id,
            ':message' => $message
        ]);
        
    } catch(PDOException $e) {
        // Silently fail
    }
}
?>