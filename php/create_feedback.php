<?php
require_once __DIR__ . '/../config.php';

if($_SERVER['REQUEST_METHOD'] !== 'POST'){
    echo json_encode(['success' => false, 'error' => 'Invalid method']);
    exit;
}

if(!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true){
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$type = $input['type'] ?? 'Other Issue';
$description = $input['description'] ?? '';
$address = $input['address'] ?? '';
$location = $input['location'] ?? '';
$category = $input['category'] ?? ($type ?? 'Other');

$user_id = $_SESSION['id'];
$user_barangay = $_SESSION['barangay'] ?? '';


try{
    $check1 = $pdo->query("SHOW COLUMNS FROM feedback LIKE 'address'")->fetch();
    if(!$check1) $pdo->exec("ALTER TABLE feedback ADD COLUMN address VARCHAR(255) NULL");
    $check2 = $pdo->query("SHOW COLUMNS FROM feedback LIKE 'location'")->fetch();
    if(!$check2) $pdo->exec("ALTER TABLE feedback ADD COLUMN location VARCHAR(255) NULL");
    
    $check3 = $pdo->query("SHOW COLUMNS FROM feedback LIKE 'category'")->fetch();
    if(!$check3) $pdo->exec("ALTER TABLE feedback ADD COLUMN category VARCHAR(100) NULL");
} catch(Exception $e){ }

$sql = "INSERT INTO feedback (user_id, type, description, status, created_at, address, location, category) VALUES (:user_id, :type, :description, 'Pending', NOW(), :address, :location, :category)";
if($stmt = $pdo->prepare($sql)){
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindParam(':type', $type, PDO::PARAM_STR);
    $stmt->bindParam(':description', $description, PDO::PARAM_STR);
    $stmt->bindParam(':address', $address, PDO::PARAM_STR);
    $stmt->bindParam(':location', $location, PDO::PARAM_STR);
    $stmt->bindParam(':category', $category, PDO::PARAM_STR);
    if($stmt->execute()){
        $feedback_id = $pdo->lastInsertId();
        
        $notif_sql = "INSERT INTO notifications (user_id, barangay, type, title, message, is_read, created_at) VALUES (NULL, :barangay, 'general', :title, :message, 0, NOW())";
        if($nstmt = $pdo->prepare($notif_sql)){
            $title = 'New Feedback: ' . $category;
            $message = substr($description,0,200);
            $nstmt->bindParam(':barangay', $user_barangay, PDO::PARAM_STR);
            $nstmt->bindParam(':title', $title, PDO::PARAM_STR);
            $nstmt->bindParam(':message', $message, PDO::PARAM_STR);
            $nstmt->execute();
        }

        echo json_encode(['success' => true, 'id' => $feedback_id]);
        exit;
    }
}

echo json_encode(['success' => false, 'error' => 'Failed to submit feedback']);
