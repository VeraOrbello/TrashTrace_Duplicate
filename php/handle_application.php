<?php
require_once __DIR__ . '/../config.php';

if($_SERVER['REQUEST_METHOD'] !== 'POST'){
    echo json_encode(['success'=>false,'error'=>'Invalid method']);
    exit;
}

if(!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['user_type'] !== 'admin'){
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$id = isset($input['id']) ? (int)$input['id'] : 0;
$action = isset($input['action']) ? $input['action'] : '';

if($id <= 0 || !in_array($action, ['accept','reject'])){
    echo json_encode(['success'=>false,'error'=>'Invalid parameters']);
    exit;
}

$user_barangay = $_SESSION['barangay'] ?? '';

try{
    $pdo->beginTransaction();

    $sql = "SELECT * FROM worker_applications WHERE id = :id FOR UPDATE";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $app = $stmt->fetch(PDO::FETCH_ASSOC);
    if(!$app){
        $pdo->rollBack();
        echo json_encode(['success'=>false,'error'=>'Not found']);
        exit;
    }

    if(strtolower(trim($app['barangay'] ?? '')) !== strtolower(trim($user_barangay))){
        $pdo->rollBack();
        echo json_encode(['success'=>false,'error'=>'Forbidden']);
        exit;
    }

    $newStatus = $action === 'accept' ? 'accepted' : 'rejected';
    $reviewedAt = date('Y-m-d H:i:s');

    $updateApp = $pdo->prepare("UPDATE worker_applications SET status = :status, reviewed_at = :reviewed_at WHERE id = :id");
    $updateApp->bindParam(':status', $newStatus, PDO::PARAM_STR);
    $updateApp->bindParam(':reviewed_at', $reviewedAt, PDO::PARAM_STR);
    $updateApp->bindParam(':id', $id, PDO::PARAM_INT);
    $updateApp->execute();

    if($action === 'accept'){
        $updateUser = $pdo->prepare("UPDATE users SET user_type = 'admin' WHERE id = :uid");
        $updateUser->bindParam(':uid', $app['user_id'], PDO::PARAM_INT);
        $updateUser->execute();
    } else {
        $updateUser = $pdo->prepare("UPDATE users SET user_type = 'user' WHERE id = :uid");
        $updateUser->bindParam(':uid', $app['user_id'], PDO::PARAM_INT);
        $updateUser->execute();
    }

    $pdo->commit();
    echo json_encode(['success'=>true,'status'=>$newStatus]);
    exit;
} catch(Exception $e){
    if($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success'=>false,'error'=>'Server error']);
    exit;
}
