<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

if(!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['user_type'] !== 'admin'){
    echo json_encode(['success'=>false,'error'=>'Unauthorized']);
    exit;
}

$user_barangay = $_SESSION['barangay'] ?? '';

if(empty($user_barangay)){
    echo json_encode(['success'=>false,'error'=>'Barangay not set in session']);
    exit;
}

$feedbacks = [];
try{
    $sql = "SELECT f.*, u.full_name AS user_name, u.mobile_number, u.barangay as user_barangay, u.city AS user_city FROM feedback f LEFT JOIN users u ON f.user_id = u.id WHERE LOWER(TRIM(u.barangay)) = LOWER(TRIM(:barangay)) ORDER BY f.created_at DESC";
    if($stmt = $pdo->prepare($sql)){
        $stmt->bindParam(':barangay', $user_barangay, PDO::PARAM_STR);
        if($stmt->execute()){
            $feedbacks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            echo json_encode(['success'=>false,'error'=>'Query execution failed']);
            exit;
        }
        $stmt->closeCursor();
    } else {
        echo json_encode(['success'=>false,'error'=>'Query preparation failed']);
        exit;
    }
} catch(Exception $e){
    error_log("Error in get_feedback.php: " . $e->getMessage());
    echo json_encode(['success'=>false,'error'=>'Database error occurred']);
    exit;
}

echo json_encode(['success'=>true,'feedbacks'=>$feedbacks]);

?>
