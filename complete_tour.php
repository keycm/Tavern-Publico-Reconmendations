<?php
session_start();
require_once 'db_connect.php';

header('Content-Type: application/json');

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    
    // Update the database so they don't see the tour again
    $sql = "UPDATE users SET has_seen_tour = 1 WHERE user_id = ?";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
    
    // Update the session so it stops showing on page reloads
    $_SESSION['show_tour'] = false;
    
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
}
mysqli_close($link);
?>