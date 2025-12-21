<?php
require_once "config.php";
session_start();

if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

if($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id = $_POST['user_id'] ?? '';
    $type = $_POST['type'] ?? '';
    $description = $_POST['description'] ?? '';
    $address = $_POST['address'] ?? '';
    $location = $_POST['location'] ?? '';
    $user_name = $_POST['user_name'] ?? '';
    $barangay = $_POST['barangay'] ?? '';
    
    // Validation
    if(empty($description) || empty($address) || empty($type)) {
        echo json_encode(['success' => false, 'message' => 'Please fill all required fields']);
        exit;
    }
    
    // Insert into database
    $sql = "INSERT INTO user_actions (user_id, user_name, barangay, type, description, address, location, status, created_at) 
            VALUES (:user_id, :user_name, :barangay, :type, :description, :address, :location, 'Pending', NOW())";
    
    if($stmt = $pdo->prepare($sql)){
        $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
        $stmt->bindParam(":user_name", $user_name, PDO::PARAM_STR);
        $stmt->bindParam(":barangay", $barangay, PDO::PARAM_STR);
        $stmt->bindParam(":type", $type, PDO::PARAM_STR);
        $stmt->bindParam(":description", $description, PDO::PARAM_STR);
        $stmt->bindParam(":address", $address, PDO::PARAM_STR);
        $stmt->bindParam(":location", $location, PDO::PARAM_STR);
        
        if($stmt->execute()){
            echo json_encode(['success' => true, 'message' => 'Your ' . strtolower($type) . ' has been submitted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error']);
        }
        
        $stmt->closeCursor();
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>