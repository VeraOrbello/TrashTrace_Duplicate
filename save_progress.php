<?php
session_start();

if(isset($_POST['route_id']) && isset($_POST['progress'])) {
    $route_id = $_POST['route_id'];
    $progress = $_POST['progress'];
    
    // Initialize if not exists
    if(!isset($_SESSION['route_progress'])) {
        $_SESSION['route_progress'] = [];
    }
    
    // Save progress
    $_SESSION['route_progress'][$route_id] = $progress;
    
    echo 'Progress saved';
}
?>