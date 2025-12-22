<?php
session_start();
require_once "../config.php";

if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["user_type"] !== 'driver'){
    header("location: ../login.php");
    exit;
}

$driver_id = $_SESSION["id"];
$driver_name = $_SESSION["full_name"];

// Get POST data
$stop_id = $_POST['stop_id'] ?? null;
$route_id = $_POST['route_id'] ?? null;
$latitude = $_POST['lat'] ?? null;
$longitude = $_POST['lng'] ?? null;
$location_name = $_POST['location_name'] ?? 'Collection Point';
$estimated_time = $_POST['estimated_time'] ?? '10-15 minutes';

if(!$stop_id || !$route_id) {
    echo json_encode(['success' => false, 'message' => 'Missing required data']);
    exit;
}

try {
    // 1. Get nearby residents within 500m radius
    $query = "
        SELECT r.id, r.full_name, r.email, r.phone, r.device_token 
        FROM residents r 
        WHERE 
            ST_Distance_Sphere(
                point(r.home_longitude, r.home_latitude),
                point(?, ?)
            ) <= 500
            AND r.notification_preferences LIKE '%collection_alerts%'
    ";
    
    $stmt = mysqli_prepare($link, $query);
    mysqli_stmt_bind_param($stmt, "dd", $longitude, $latitude);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $residents = [];
    while($row = mysqli_fetch_assoc($result)) {
        $residents[] = $row;
    }
    
    // 2. Create notification record
    $notification_sql = "
        INSERT INTO notifications (
            type, 
            title, 
            message, 
            recipient_type,
            driver_id,
            stop_id,
            route_id,
            location_lat,
            location_lng,
            sent_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ";
    
    $notification_type = 'collection_stop';
    $title = "Garbage Collection Update";
    $message = "Trash collection truck is at {$location_name}. ETA: {$estimated_time}";
    $recipient_type = 'resident';
    
    $stmt2 = mysqli_prepare($link, $notification_sql);
    mysqli_stmt_bind_param($stmt2, "ssssiiidd", 
        $notification_type,
        $title,
        $message,
        $recipient_type,
        $driver_id,
        $stop_id,
        $route_id,
        $latitude,
        $longitude
    );
    mysqli_stmt_execute($stmt2);
    $notification_id = mysqli_insert_id($link);
    
    // 3. Send push notifications to residents
    $device_tokens = [];
    foreach($residents as $resident) {
        $device_tokens[] = $resident['device_token'];
        
        // Store individual notification for each resident
        $user_notif_sql = "
            INSERT INTO user_notifications (
                notification_id,
                user_id,
                user_type,
                status,
                created_at
            ) VALUES (?, ?, 'resident', 'sent', NOW())
        ";
        
        $stmt3 = mysqli_prepare($link, $user_notif_sql);
        mysqli_stmt_bind_param($stmt3, "ii", $notification_id, $resident['id']);
        mysqli_stmt_execute($stmt3);
    }
    
    // 4. Send SMS notifications (optional)
    if(count($residents) > 0) {
        sendSMSNotifications($residents, $message);
    }
    
    // 5. Send push notifications via FCM
    if(count($device_tokens) > 0) {
        sendPushNotifications($device_tokens, $title, $message, [
            'type' => 'collection_stop',
            'stop_id' => $stop_id,
            'route_id' => $route_id,
            'driver_name' => $driver_name,
            'location' => $location_name,
            'estimated_time' => $estimated_time,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Notifications sent successfully',
        'residents_notified' => count($residents)
    ]);
    
} catch(Exception $e) {
    error_log("Notification error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error sending notifications']);
}
?>

<?php
function sendPushNotifications($device_tokens, $title, $body, $data = []) {
    $fcmUrl = 'https://fcm.googleapis.com/fcm/send';
    
    $notification = [
        'title' => $title,
        'body' => $body,
        'sound' => 'default',
        'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
    ];
    
    $extraNotificationData = ['data' => $data];
    
    $fcmNotification = [
        'registration_ids' => $device_tokens,
        'notification' => $notification,
        'data' => $extraNotificationData
    ];
    
    $headers = [
        'Authorization: key=' . FCM_SERVER_KEY, // Define this in config
        'Content-Type: application/json'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $fcmUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fcmNotification));
    $result = curl_exec($ch);
    curl_close($ch);
    
    return $result;
}

function sendSMSNotifications($residents, $message) {
    // Implement SMS gateway integration (Twilio, Nexmo, etc.)
    foreach($residents as $resident) {
        if($resident['phone'] && $resident['sms_enabled']) {
            // Send SMS code here
            // Example with Twilio:
            // $client->messages->create($resident['phone'], ['from' => '+1234567890', 'body' => $message]);
        }
    }
}