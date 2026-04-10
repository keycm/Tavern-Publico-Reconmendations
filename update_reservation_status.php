<?php
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors, it breaks JSON
ini_set('log_errors', 1);

session_start();
require_once 'db_connect.php';
require_once 'mail_config.php'; 
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/Exception.php';
require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

// Authorization check
$is_authorized = false;
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'owner') {
        $is_authorized = true;
    } elseif (isset($_SESSION['role']) && $_SESSION['role'] === 'manager') {
        $manager_permissions = $_SESSION['permissions'] ?? [];
        if (is_array($manager_permissions) && in_array('manage_reservations', $manager_permissions)) {
            $is_authorized = true;
        }
    }
}

if (!$is_authorized) {
    $response['message'] = 'Unauthorized access. You do not have permission to perform this action.';
    echo json_encode($response);
    exit;
}

$action_by_username = $_SESSION['username'] ?? 'System'; 

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $reservation_id = $_POST['reservation_id'] ?? null;
    $newStatus = $_POST['status'] ?? null;
    $action = $_POST['action'] ?? null;

    if ($reservation_id === null || ($action === 'update' && $newStatus === null) || empty($action)) {
        $response['message'] = 'Missing required fields.';
        echo json_encode($response);
        exit;
    }

    if ($action === 'update') {
        
        // --- FIX: Added 'reservation_type' to the query ---
        $sql_select = "SELECT user_id, res_name, res_email, res_date, res_time, reservation_type FROM reservations WHERE reservation_id = ?";
        $stmt_select = mysqli_prepare($link, $sql_select);
        mysqli_stmt_bind_param($stmt_select, "i", $reservation_id);
        mysqli_stmt_execute($stmt_select);
        $result = mysqli_stmt_get_result($stmt_select);
        $reservation_data = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt_select);

        if (!$reservation_data) {
            $response['message'] = 'Reservation not found.';
            echo json_encode($response);
            exit;
        }

        // --- FIX: Set 'is_notified = 0' to flag this as a new notification ---
        $sql_update = "UPDATE reservations SET status = ?, action_by = ?, is_notified = 0 WHERE reservation_id = ?";
        if ($stmt_update = mysqli_prepare($link, $sql_update)) {
            mysqli_stmt_bind_param($stmt_update, "ssi", $newStatus, $action_by_username, $reservation_id);
            
            if (mysqli_stmt_execute($stmt_update) && mysqli_stmt_affected_rows($stmt_update) > 0) {
                $response['success'] = true;
                $response['message'] = 'Reservation status updated successfully.';

                // --- START AUDIT LOG: STATUS UPDATE ---
                if (isset($_SESSION['user_id'])) {
                    $admin_id = $_SESSION['user_id'];
                    $action_type = "Updated Reservation Status";
                    $log_details = "Changed Reservation ID #" . $reservation_id . " status to: " . $newStatus;
                    $ip_address = $_SERVER['REMOTE_ADDR'];
                    
                    $log_sql = "INSERT INTO audit_logs (admin_id, action, details, ip_address) VALUES (?, ?, ?, ?)";
                    if ($log_stmt = mysqli_prepare($link, $log_sql)) {
                        mysqli_stmt_bind_param($log_stmt, "isss", $admin_id, $action_type, $log_details, $ip_address);
                        mysqli_stmt_execute($log_stmt);
                        mysqli_stmt_close($log_stmt);
                    }
                }
                // --- END AUDIT LOG ---

                // --- Send Email Notification ---
                $mail = new PHPMailer(true);
                try {
                    $mail->isSMTP();
                    $mail->Host       = SMTP_HOST;
                    $mail->SMTPAuth   = true;
                    $mail->Username   = SMTP_USERNAME;
                    $mail->Password   = SMTP_PASSWORD;
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port       = SMTP_PORT;

                    $mail->setFrom(SMTP_USERNAME, 'Tavern Publico');
                    $mail->addAddress($reservation_data['res_email'], $reservation_data['res_name']);
                    $mail->isHTML(true);
                    
                    // Format reservation details
                    $res_date_formatted = htmlspecialchars($reservation_data['res_date']);
                    $res_time_formatted = htmlspecialchars(date('g:i A', strtotime($reservation_data['res_time'])));
                    $customer_name = htmlspecialchars($reservation_data['res_name']);

                    if ($newStatus === 'Confirmed') {
                        $mail->Subject = 'Your Reservation is Confirmed!';
                        
                        $reservation_type = $reservation_data['reservation_type'] ?? 'Dine-in';
                        
                        // --- NEW: Generate Google Calendar Link ---
                        $gcal_title = urlencode("Reservation at Tavern Publico ($reservation_type)");
                        
                        // Calculate start and end times (Assuming a standard 2-hour reservation)
                        $start_timestamp = strtotime($reservation_data['res_date'] . ' ' . $reservation_data['res_time']);
                        $end_timestamp = $start_timestamp + (2 * 3600); // +2 hours
                        
                        // Format for Google Calendar (Ymd\THis - Local timezone format)
                        $gcal_start = date('Ymd\THis', $start_timestamp);
                        $gcal_end = date('Ymd\THis', $end_timestamp);
                        
                        $gcal_details = urlencode("Confirmed reservation for " . $customer_name . ".\nReservation Type: " . $reservation_type . "\n\nWe look forward to hosting you!");
                        $gcal_location = urlencode("Tavern Publico"); // You can put your full address here if you prefer
                        
                        $gcal_url = "https://calendar.google.com/calendar/render?action=TEMPLATE&text={$gcal_title}&dates={$gcal_start}/{$gcal_end}&details={$gcal_details}&location={$gcal_location}";
                        
                        // Styled Button HTML
                        $gcal_button = "<br><br><a href='{$gcal_url}' style='background-color: #4285F4; color: #ffffff; padding: 12px 20px; text-decoration: none; border-radius: 5px; display: inline-block; font-family: sans-serif; font-weight: bold;'>📅 Add to Google Calendar</a><br><br>";
                        // --- END NEW ---

                        if ($reservation_type === 'Dine-in') {
                            // Standard Dine-in Email
                            $mail->Body    = "Dear " . $customer_name . ",<br><br>" .
                                             "Your <strong>Dine-in</strong> reservation for <strong>" . $res_date_formatted . "</strong> at <strong>" . $res_time_formatted . "</strong> has been confirmed.<br>" .
                                             $gcal_button . 
                                             "Please note: Your table will be held for a 30-minute grace period past your reservation time. If you are running late, please give us a call.<br><br>" .
                                             "We look forward to seeing you!<br>Tavern Publico";
                        } else {
                            // Formal Event / Special Occasion Email
                            $mail->Body    = "Dear " . $customer_name . ",<br><br>" .
                                             "We are pleased to confirm your <strong>" . htmlspecialchars($reservation_type) . "</strong> reservation for <strong>" . $res_date_formatted . "</strong> at <strong>" . $res_time_formatted . "</strong>.<br>" .
                                             $gcal_button . 
                                             "Our team is excited to help you celebrate. If you have any further requirements or need to coordinate details for your event, please do not hesitate to reply to this email or contact us directly.<br><br>" .
                                             "We look forward to welcoming you!<br>Tavern Publico";
                        }

                    } elseif ($newStatus === 'Declined') {
                        $mail->Subject = 'Your Reservation has been Declined';
                        $mail->Body    = "Dear " . $customer_name . ",<br><br>We regret to inform you that your reservation for " . $res_date_formatted . " at " . $res_time_formatted . " has been declined due to unavailability.<br><br>Please try booking for another date or time.<br>Tavern Publico";
                    }
                    
                    // Only send if status is Confirmed or Declined
                    if (in_array($newStatus, ['Confirmed', 'Declined'])) {
                        $mail->send();
                        $response['message'] .= ' Email notification sent.';
                    }

                } catch (Exception $e) {
                    $response['message'] .= " Email could not be sent. Mailer Error: {$mail->ErrorInfo}";
                }
                // --- END: Send Email Notification ---

            } else {
                $response['message'] = 'No reservation found or status is the same.';
            }
            mysqli_stmt_close($stmt_update);
        } else {
            $response['message'] = 'Database error.';
        }
    }
}

mysqli_close($link);
echo json_encode($response);
?>