<?php
require_once "../config.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["user_type"] !== 'admin'){
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true);

if(!$input) {
    echo json_encode(['success' => false, 'message' => 'No data received']);
    exit;
}

$user_barangay = $_SESSION["barangay"] ?? '';

if (empty($user_barangay)) {
    echo json_encode(['success' => false, 'message' => 'Barangay not set']);
    exit;
}

try {
    switch($action) {
        case 'create':
            if(empty($input['schedule_date'])) {
                echo json_encode(['success' => false, 'message' => 'Schedule date is required']);
                exit;
            }
            
                $barangay_param = trim($user_barangay);
                $sql = "INSERT INTO pickup_schedules (barangay, zone, schedule_date, status, notes, created_at) 
                    VALUES (:barangay, :zone, :schedule_date, :status, :notes, NOW())";
            
                $stmt = $pdo->prepare($sql);
                $zone_val = isset($input['zone']) ? trim($input['zone']) : null;
                $stmt->bindParam(":barangay", $barangay_param);
                $stmt->bindParam(":zone", $zone_val);
                $stmt->bindParam(":schedule_date", $input['schedule_date']);
                $stmt->bindParam(":status", $input['status']);
                $notes = $input['notes'] ?? '';
                $stmt->bindParam(":notes", $notes);
            
            if($stmt->execute()) {
                $lastId = $pdo->lastInsertId();
                
                try {
                    $notif_sql = "INSERT INTO notifications (user_id, barangay, type, title, message, is_read, created_at) VALUES (NULL, :barangay, 'pickup_scheduled', :title, :message, 0, NOW())";
                    $nstmt = $pdo->prepare($notif_sql);
                    $title = 'Pickup Scheduled';
                    $message = 'A pickup has been scheduled on ' . $input['schedule_date'];
                    $nstmt->bindParam(':barangay', $barangay_param, PDO::PARAM_STR);
                    $nstmt->bindParam(':title', $title, PDO::PARAM_STR);
                    $nstmt->bindParam(':message', $message, PDO::PARAM_STR);
                    $nstmt->execute();
                } catch(Exception $e){ }
                echo json_encode([
                    'success' => true, 
                    'message' => 'Schedule created successfully',
                    'id' => $lastId
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to create schedule']);
            }
            break;
            
        case 'update':
            if(empty($input['id'])) {
                echo json_encode(['success' => false, 'message' => 'Schedule ID is required']);
                exit;
            }
            
                $sql = "UPDATE pickup_schedules SET 
                    status = :status, 
                    notes = :notes 
                    WHERE id = :id AND LOWER(TRIM(barangay)) = :barangay";
            
                $stmt = $pdo->prepare($sql);
                $barangay_param = mb_strtolower(trim($user_barangay));
                $stmt->bindParam(":id", $input['id']);
                $stmt->bindParam(":status", $input['status']);
                $notes = $input['notes'] ?? '';
                $stmt->bindParam(":notes", $notes);
                $stmt->bindParam(":barangay", $barangay_param);
            
            if($stmt->execute()) {
                    
                try {
                    $notif_sql = "INSERT INTO notifications (user_id, barangay, type, title, message, is_read, created_at) VALUES (NULL, :barangay, :type, :title, :message, 0, NOW())";
                    $nstmt = $pdo->prepare($notif_sql);
                    $type = 'pickup_updated';
                    $title = 'Pickup Update';
                    $message = 'Pickup status updated to ' . ($input['status'] ?? 'Updated');
                    
                    if(isset($input['status'])){
                        $s = strtolower(trim($input['status']));
                        if($s === 'delayed'){
                            $type = 'pickup_delayed';
                            $title = 'Pickup Delayed';
                        } elseif($s === 'completed'){
                            $type = 'pickup_completed';
                            $title = 'Pickup Completed';
                        } elseif($s === 'cancelled' || $s === 'cancel'){
                            $type = 'pickup_cancelled';
                            $title = 'Pickup Cancelled';
                        }
                    }
                    $nstmt->bindParam(':barangay', $barangay_param, PDO::PARAM_STR);
                    $nstmt->bindParam(':type', $type, PDO::PARAM_STR);
                    $nstmt->bindParam(':title', $title, PDO::PARAM_STR);
                    $nstmt->bindParam(':message', $message, PDO::PARAM_STR);
                    $nstmt->execute();
                } catch(Exception $e){ }

                echo json_encode(['success' => true, 'message' => 'Schedule updated successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update schedule']);
            }
            break;
            
        case 'delete':
            if(empty($input['id'])) {
                echo json_encode(['success' => false, 'message' => 'Schedule ID is required']);
                exit;
            }
            
            $sql = "DELETE FROM pickup_schedules WHERE id = :id AND LOWER(TRIM(barangay)) = :barangay";
            $stmt = $pdo->prepare($sql);
            $barangay_param = mb_strtolower(trim($user_barangay));
            $stmt->bindParam(":id", $input['id']);
            $stmt->bindParam(":barangay", $barangay_param);
            
            if($stmt->execute()) {
                    
                try {
                    $notif_sql = "INSERT INTO notifications (user_id, barangay, type, title, message, is_read, created_at) VALUES (NULL, :barangay, 'pickup_cancelled', :title, :message, 0, NOW())";
                    $nstmt = $pdo->prepare($notif_sql);
                    $title = 'Pickup Cancelled';
                    $message = 'A scheduled pickup has been cancelled.';
                    $nstmt->bindParam(':barangay', $barangay_param, PDO::PARAM_STR);
                    $nstmt->bindParam(':title', $title, PDO::PARAM_STR);
                    $nstmt->bindParam(':message', $message, PDO::PARAM_STR);
                    $nstmt->execute();
                } catch(Exception $e){ }

                echo json_encode(['success' => true, 'message' => 'Schedule deleted successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to delete schedule']);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
} catch(PDOException $e) {
    error_log("Database error in update_schedule.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
} catch(Exception $e) {
    error_log("General error in update_schedule.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred']);
}

exit;
?>