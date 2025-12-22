<?php
require_once "config.php";

echo "<h2>Checking Table Structure</h2>";

// Check all relevant tables
$tables = [
    'pickup_assignments',
    'driver_daily_assignments', 
    'pickup_schedules',
    'driver_profiles',
    'users',
    'assignment_logs',
    'admin_notifications',
    'driver_notifications'
];

foreach($tables as $table) {
    echo "<h3>Table: $table</h3>";
    
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if($stmt->rowCount() > 0) {
            echo "✓ Table exists<br>";
            
            // Show columns
            $stmt = $pdo->query("DESCRIBE $table");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "<table border='1' cellpadding='5'>";
            echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
            foreach($columns as $column) {
                echo "<tr>";
                echo "<td>{$column['Field']}</td>";
                echo "<td>{$column['Type']}</td>";
                echo "<td>{$column['Null']}</td>";
                echo "<td>{$column['Key']}</td>";
                echo "<td>{$column['Default']}</td>";
                echo "<td>{$column['Extra']}</td>";
                echo "</tr>";
            }
            echo "</table>";
            
            // Show sample data
            if(in_array($table, ['pickup_assignments', 'driver_daily_assignments', 'pickup_schedules'])) {
                $stmt = $pdo->query("SELECT * FROM $table LIMIT 3");
                $sample = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo "<h4>Sample Data (First 3 rows):</h4>";
                echo "<pre>" . print_r($sample, true) . "</pre>";
            }
        } else {
            echo "✗ Table does not exist<br>";
        }
    } catch(PDOException $e) {
        echo "Error: " . $e->getMessage() . "<br>";
    }
    
    echo "<hr>";
}

// Test driver assignments
echo "<h2>Test Driver Assignments Sync</h2>";

// Get current user info
if(isset($_SESSION["id"])) {
    $driver_id = $_SESSION["id"];
    $user_type = $_SESSION["user_type"] ?? 'unknown';
    
    echo "Current User ID: $driver_id<br>";
    echo "User Type: $user_type<br>";
    
    // Test getDriverBarangay function
    $sql = "SELECT barangay FROM users WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $driver_id]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "User Barangay from users table: " . ($user_data['barangay'] ?? 'Not set') . "<br>";
}

echo "<h2>Test Sync Endpoint</h2>";
echo '<a href="sync_driver_assignments.php?action=test_connection" target="_blank">Test Connection</a><br>';
echo '<a href="sync_driver_assignments.php?action=get_driver_assignments" target="_blank">Get Driver Assignments</a><br>';
echo '<a href="sync_driver_assignments.php?action=get_sync_status" target="_blank">Get Sync Status</a><br>';
?>