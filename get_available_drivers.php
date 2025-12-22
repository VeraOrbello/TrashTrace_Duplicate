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

$date = $_GET['date'] ?? date('Y-m-d');
$barangay = $_GET['barangay'] ?? '';

if(empty($barangay)) {
    // Get admin barangay
    $sql = "SELECT barangay FROM users WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $_SESSION["id"]]);
    $admin_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $barangay = $admin_data['barangay'] ?? '';
}

if(empty($barangay)) {
    echo json_encode(['success' => false, 'message' => 'Barangay not set']);
    exit;
}

try {
    // Get drivers assigned to this barangay
    $sql = "SELECT 
                u.id,
                u.full_name,
                u.phone,
                u.email,
                d.vehicle,
                d.zone_name,
                COUNT(a.id) as assignment_count
            FROM users u
            LEFT JOIN driver_details d ON u.id = d.driver_id
            LEFT JOIN assignments a ON u.id = a.driver_id AND DATE(a.assignment_date) = :date
            WHERE u.user_type = 'driver'
            AND u.barangay = :barangay
            AND u.status = 'active'
            GROUP BY u.id
            HAVING assignment_count = 0
            ORDER BY u.full_name";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':date' => $date,
        ':barangay' => $barangay
    ]);
    
    $drivers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($drivers);
    
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>