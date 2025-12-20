<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

if(!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['user_type'] !== 'admin'){
    echo json_encode(['success'=>false,'error'=>'Unauthorized']);
    exit;
}

$user_barangay = $_SESSION['barangay'] ?? '';

$feedbacks = [];
try{
    $sql = "SELECT f.*, u.full_name AS user_name, u.mobile_number, u.barangay as user_barangay, u.city AS user_city FROM feedback f LEFT JOIN users u ON f.user_id = u.id WHERE LOWER(TRIM(u.barangay)) = LOWER(TRIM(:barangay)) OR u.barangay IS NULL ORDER BY f.created_at DESC";
    if($stmt = $pdo->prepare($sql)){
        $stmt->bindParam(':barangay', $user_barangay, PDO::PARAM_STR);
        if($stmt->execute()){
            $feedbacks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        $stmt->closeCursor();
    }
} catch(Exception $e){
    echo json_encode(['success'=>false,'error'=>'DB error','message'=>$e->getMessage()]);
    exit;
}

echo json_encode(['success'=>true,'feedbacks'=>$feedbacks]);

?>
