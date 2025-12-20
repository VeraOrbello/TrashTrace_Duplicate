<?php
require_once __DIR__ . '/../config.php';

if(!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['user_type'] !== 'admin'){
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if($id <= 0){
    echo json_encode(['success' => false, 'error' => 'Invalid id']);
    exit;
}

$user_barangay = $_SESSION['barangay'] ?? '';

$sql = "SELECT wa.*, u.full_name, u.email, u.mobile_number, u.user_type as user_type FROM worker_applications wa JOIN users u ON wa.user_id = u.id WHERE wa.id = :id LIMIT 1";
if($stmt = $pdo->prepare($sql)){
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    if($stmt->execute()){
        $app = $stmt->fetch(PDO::FETCH_ASSOC);
        if($app){
            if(strtolower(trim($app['barangay'] ?? '')) !== strtolower(trim($user_barangay))){
                echo json_encode(['success'=>false,'error'=>'Forbidden']);
                exit;
            }

            echo json_encode(['success' => true, 'application' => $app]);
            exit;
        }
    }
    $stmt->closeCursor();
}

echo json_encode(['success' => false, 'error' => 'Not found']);
