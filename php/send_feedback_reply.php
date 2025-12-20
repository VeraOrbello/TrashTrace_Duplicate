<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

if(!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['user_type'] !== 'admin'){
    echo json_encode(['success'=>false,'error'=>'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$feedback_id = isset($input['feedback_id']) ? (int)$input['feedback_id'] : 0;
$message = trim($input['message'] ?? '');
$new_category = trim($input['category'] ?? '');
$mark_resolved = !empty($input['mark_resolved']);

if($feedback_id <= 0 || $message === ''){
    echo json_encode(['success'=>false,'error'=>'Invalid parameters']);
    exit;
}

try{
    
    $stmt = $pdo->prepare("SELECT f.*, u.full_name, u.id AS uid FROM feedback f LEFT JOIN users u ON f.user_id = u.id WHERE f.id = :id LIMIT 1");
    $stmt->bindParam(':id', $feedback_id, PDO::PARAM_INT);
    $stmt->execute();
    $fb = $stmt->fetch(PDO::FETCH_ASSOC);
    if(!$fb){
        echo json_encode(['success'=>false,'error'=>'Not found']);
        exit;
    }

    $user_id = $fb['user_id'] ?? null;


    if($new_category !== '' || $mark_resolved){
        $parts = [];
        $sql = "UPDATE feedback SET ";
        $updates = [];
        if($new_category !== ''){ $updates[] = "category = :category"; }
        if($mark_resolved){ $updates[] = "status = 'Resolved'"; }
        $sql .= implode(', ', $updates) . " WHERE id = :id";
        $ustmt = $pdo->prepare($sql);
        if($new_category !== '') $ustmt->bindParam(':category', $new_category, PDO::PARAM_STR);
        $ustmt->bindParam(':id', $feedback_id, PDO::PARAM_INT);
        $ustmt->execute();
    }


    if($user_id){
        $notif_sql = "INSERT INTO notifications (user_id, barangay, type, title, message, is_read, created_at) VALUES (:user_id, NULL, 'general', :title, :message, 0, NOW())";
        $nstmt = $pdo->prepare($notif_sql);
        $title = 'Response to your report';
        $full_message = $message;
        $nstmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $nstmt->bindParam(':title', $title, PDO::PARAM_STR);
        $nstmt->bindParam(':message', $full_message, PDO::PARAM_STR);
        $nstmt->execute();
    }

    echo json_encode(['success'=>true]);
    exit;

} catch(Exception $e){
    echo json_encode(['success'=>false,'error'=>'Server error','message'=>$e->getMessage()]);
    exit;
}

?>
