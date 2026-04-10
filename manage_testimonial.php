<?php
session_start();
require_once 'db_connect.php';

header('Content-Type: application/json');
$response = ['success' => false, 'message' => 'An unknown error occurred.'];

if (!isset($_SESSION['loggedin']) || !$_SESSION['is_admin']) {
    $response['message'] = 'Unauthorized access.';
    echo json_encode($response);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';
    $testimonial_id = filter_input(INPUT_POST, 'testimonial_id', FILTER_SANITIZE_NUMBER_INT);

    if (empty($testimonial_id)) {
        $response['message'] = 'Invalid testimonial ID.';
        echo json_encode($response);
        exit;
    }

    if ($action === 'feature') {
        $sql = "UPDATE testimonials SET is_featured = !is_featured WHERE id = ?";
        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "i", $testimonial_id);
            if (mysqli_stmt_execute($stmt)) {
                $response['success'] = true;
                $response['message'] = 'Testimonial feature status updated.';

                if (isset($_SESSION['user_id'])) {
                    $admin_id = $_SESSION['user_id'];
                    $ip_address = $_SERVER['REMOTE_ADDR'];
                    $log_sql = "INSERT INTO audit_logs (admin_id, action, details, ip_address) VALUES (?, 'Updated Testimonial', 'Toggled feature status for Testimonial ID #$testimonial_id', ?)";
                    if ($log_stmt = mysqli_prepare($link, $log_sql)) {
                        mysqli_stmt_bind_param($log_stmt, "is", $admin_id, $ip_address);
                        mysqli_stmt_execute($log_stmt);
                        mysqli_stmt_close($log_stmt);
                    }
                }

            } else {
                $response['message'] = 'Error updating testimonial.';
            }
            mysqli_stmt_close($stmt);
        }
    } elseif ($action === 'delete') {
        
        $sql_select = "SELECT * FROM testimonials WHERE id = ?";
        $stmt_select = mysqli_prepare($link, $sql_select);
        mysqli_stmt_bind_param($stmt_select, "i", $testimonial_id);
        mysqli_stmt_execute($stmt_select);
        $result = mysqli_stmt_get_result($stmt_select);
        $item_data = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt_select);

        if ($item_data) {
            $item_data_json = json_encode($item_data);
            mysqli_begin_transaction($link);
            try {
                // FIXED: Removed the non-existent 'action_by' column
                $sql_log = "INSERT INTO deletion_history (item_type, item_id, item_data, purge_date) VALUES ('testimonial', ?, ?, DATE_ADD(CURDATE(), INTERVAL 30 DAY))";
                $stmt_log = mysqli_prepare($link, $sql_log);
                if($stmt_log) {
                    mysqli_stmt_bind_param($stmt_log, "is", $testimonial_id, $item_data_json);
                    mysqli_stmt_execute($stmt_log);
                    mysqli_stmt_close($stmt_log);
                }

                $sql_soft_delete = "UPDATE testimonials SET deleted_at = NOW() WHERE id = ?";
                $stmt_soft_delete = mysqli_prepare($link, $sql_soft_delete);
                if($stmt_soft_delete) {
                    mysqli_stmt_bind_param($stmt_soft_delete, "i", $testimonial_id);
                    mysqli_stmt_execute($stmt_soft_delete);
                    mysqli_stmt_close($stmt_soft_delete);
                }

                mysqli_commit($link);
                $response['success'] = true;
                $response['message'] = 'Testimonial moved to deletion history.';

                if (isset($_SESSION['user_id'])) {
                    $admin_id = $_SESSION['user_id'];
                    $ip_address = $_SERVER['REMOTE_ADDR'];
                    $log_sql = "INSERT INTO audit_logs (admin_id, action, details, ip_address) VALUES (?, 'Deleted Testimonial', 'Moved Testimonial ID #$testimonial_id to deletion history.', ?)";
                    if ($log_stmt = mysqli_prepare($link, $log_sql)) {
                        mysqli_stmt_bind_param($log_stmt, "is", $admin_id, $ip_address);
                        mysqli_stmt_execute($log_stmt);
                        mysqli_stmt_close($log_stmt);
                    }
                }

            } catch (Exception $e) {
                mysqli_rollback($link);
                $response['message'] = 'Error moving testimonial to history.';
            }
        } else {
             $response['message'] = 'Testimonial not found.';
        }

    } else {
        $response['message'] = 'Invalid action.';
    }
} else {
    $response['message'] = 'Invalid request method.';
}

mysqli_close($link);
echo json_encode($response);
?>