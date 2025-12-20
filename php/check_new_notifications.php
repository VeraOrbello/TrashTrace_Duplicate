<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

if(!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true){
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'] ?? 0;
$user_barangay = $_SESSION['barangay'] ?? '';
$last_check = isset($_GET['last_check']) ? $_GET['last_check'] : null;

try {
    $query = "SELECT COUNT(*) as new_count FROM notifications WHERE (user_id = ? OR (user_id IS NULL AND barangay = ?)) AND is_read = 0";
    $params = [$user_id, $user_barangay];
    $types = "is";

    if ($last_check) {
        $query .= " AND created_at > ?";
        $params[] = $last_check;
        $types .= "s";
    }

    if($stmt = $pdo->prepare($query)){
        $stmt->bind_param($types, ...$params);
        if($stmt->execute()){
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            echo json_encode(['success' => true, 'new_count' => (int)$row['new_count']]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Query execution failed']);
        }
        $stmt->closeCursor();
    } else {
        echo json_encode(['success' => false, 'error' => 'Query preparation failed']);
    }
} catch(Exception $e){
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>
