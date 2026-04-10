<?php
session_start();
require_once 'db_connect.php';

header('Content-Type: application/json');
$response = ['success' => false, 'message' => 'An unknown error occurred.'];

if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    $response['message'] = 'You must be logged in to submit a rating.';
    echo json_encode($response);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id = $_SESSION['user_id'];
    
    // FIX: Retrieve data directly from $_POST instead of filter_input 
    // because fetch() with FormData uses multipart/form-data, which can break filter_input
    $reservation_id = isset($_POST['reservation_id']) ? (int)$_POST['reservation_id'] : 0;
    $rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
    $comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';

    // Validate that required values exist 
    // (Using $comment === '' prevents "0" from being falsely rejected as empty)
    if (empty($reservation_id) || empty($rating) || $comment === '') {
        $response['message'] = 'Please provide a rating and a comment.';
        echo json_encode($response);
        exit;
    }

    // Sanitize the comment for database insertion
    $comment = htmlspecialchars($comment, ENT_QUOTES, 'UTF-8');

    $sql = "INSERT INTO testimonials (user_id, reservation_id, rating, comment) VALUES (?, ?, ?, ?)";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "iiis", $user_id, $reservation_id, $rating, $comment);
        
        if (mysqli_stmt_execute($stmt)) {
            $response['success'] = true;
            $response['message'] = 'Thank you for your feedback!';
        } else {
            $response['message'] = 'You have already submitted a rating for this reservation.';
        }
        mysqli_stmt_close($stmt);
    } else {
        $response['message'] = 'Database error.';
    }
} else {
    $response['message'] = 'Invalid request method.';
}

mysqli_close($link);
echo json_encode($response);
?>