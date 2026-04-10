<?php
session_start();
require_once 'db_connect.php';

header('Content-Type: application/json');
$response = ['success' => false, 'message' => 'An unknown error occurred.'];

// Only the 'owner' can change roles or permissions
if (!isset($_SESSION['loggedin']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'owner') {
    $response['message'] = 'Unauthorized access.';
    echo json_encode($response);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $userId = filter_var($_POST['user_id'] ?? 0, FILTER_SANITIZE_NUMBER_INT);
    $action = $_POST['action'] ?? ''; // 'promote', 'edit_perms', or 'demote'

    if ($userId <= 0) {
        $response['message'] = 'Invalid user ID.';
        echo json_encode($response);
        exit;
    }

    $stmt_update = null;
    $log_action_type = "";
    $log_details_text = "";
    
    if ($action === 'promote' || $action === 'edit_perms') {
        // Promote to manager or Edit a manager's permissions
        $permissions = $_POST['permissions'] ?? [];
        $permissions_json = json_encode($permissions);
        $new_role = 'manager';
        
        $sql_update = "UPDATE users SET role = ?, permissions = ? WHERE user_id = ?";
        $stmt_update = mysqli_prepare($link, $sql_update);
        mysqli_stmt_bind_param($stmt_update, "ssi", $new_role, $permissions_json, $userId);
        
        $response['newRole'] = $new_role;

        if ($action === 'promote') {
            $log_action_type = "Promoted User";
            $log_details_text = "Promoted User ID #" . $userId . " to Manager role.";
        } else {
            $log_action_type = "Edited Manager Permissions";
            $log_details_text = "Updated permissions for Manager ID #" . $userId . ".";
        }

    } elseif ($action === 'demote') {
        // Demote to user
        $new_role = 'user';
        $sql_update = "UPDATE users SET role = ?, permissions = NULL WHERE user_id = ?";
        $stmt_update = mysqli_prepare($link, $sql_update);
        mysqli_stmt_bind_param($stmt_update, "si", $new_role, $userId);
        
        $response['newRole'] = $new_role;

        $log_action_type = "Demoted User";
        $log_details_text = "Demoted Manager ID #" . $userId . " back to standard Customer role.";

    } else {
        $response['message'] = 'Invalid action specified.';
    }

    if ($stmt_update) {
        if (mysqli_stmt_execute($stmt_update)) {
            $response['success'] = true;
            $response['message'] = 'User role updated successfully.';

            // --- START AUDIT LOG: ROLE CHANGES ---
            if (isset($_SESSION['user_id']) && !empty($log_action_type)) {
                $admin_id = $_SESSION['user_id'];
                $ip_address = $_SERVER['REMOTE_ADDR'];
                
                $log_sql = "INSERT INTO audit_logs (admin_id, action, details, ip_address) VALUES (?, ?, ?, ?)";
                if ($log_stmt = mysqli_prepare($link, $log_sql)) {
                    mysqli_stmt_bind_param($log_stmt, "isss", $admin_id, $log_action_type, $log_details_text, $ip_address);
                    mysqli_stmt_execute($log_stmt);
                    mysqli_stmt_close($log_stmt);
                }
            }
            // --- END AUDIT LOG ---

        } else {
            $response['message'] = 'Error updating user role. ' . mysqli_error($link);
        }
        mysqli_stmt_close($stmt_update);
    }

} else {
    $response['message'] = 'Invalid request method.';
}

mysqli_close($link);
echo json_encode($response);
?>