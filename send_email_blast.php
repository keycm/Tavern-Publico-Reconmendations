<?php
session_start();
require_once 'db_connect.php';

// Include your perfectly configured mail config
require_once 'mail_config.php';

// Include PHPMailer library files
require_once 'PHPMailer/Exception.php';
require_once 'PHPMailer/PHPMailer.php';
require_once 'PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json');

// Security check: Only the 'owner' can trigger email blasts
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['role']) || $_SESSION['role'] !== 'owner') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

// Ensure it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$target = $_POST['emailTarget'] ?? '';
$subject = trim($_POST['subject'] ?? '');
$body = trim($_POST['body'] ?? '');
$specificEmailsStr = $_POST['specificEmails'] ?? '';

// Basic Validation
if (empty($subject) || empty($body)) {
    echo json_encode(['success' => false, 'message' => 'Subject and message body are required.']);
    exit;
}

$recipientEmails = [];

// Determine who to send to based on dropdown selection
if ($target === 'all') {
    // Fetch all verified users from the database
    $sql = "SELECT email FROM users WHERE is_verified = 1 AND deleted_at IS NULL";
    $result = mysqli_query($link, $sql);
    
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            if (!empty($row['email'])) {
                $recipientEmails[] = $row['email'];
            }
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error while fetching users.']);
        exit;
    }
} elseif ($target === 'specific') {
    // Parse the comma-separated emails
    $emails = explode(',', $specificEmailsStr);
    foreach ($emails as $email) {
        $cleanEmail = filter_var(trim($email), FILTER_SANITIZE_EMAIL);
        // Validate if it's a real email format
        if (filter_var($cleanEmail, FILTER_VALIDATE_EMAIL)) {
            $recipientEmails[] = $cleanEmail;
        }
    }
    
    if (empty($recipientEmails)) {
        echo json_encode(['success' => false, 'message' => 'No valid emails were provided.']);
        exit;
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid target selection.']);
    exit;
}

// Remove any duplicate emails
$recipientEmails = array_unique($recipientEmails);

if (empty($recipientEmails)) {
    echo json_encode(['success' => false, 'message' => 'No recipients found.']);
    exit;
}

// --- INITIALIZE PHPMAILER USING YOUR CONFIG CONSTANTS ---
$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;           // Uses 'smtp.gmail.com' from mail_config.php
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USERNAME;       // Uses 'publicotavern@gmail.com'
    $mail->Password   = SMTP_PASSWORD;       // Uses your 16-digit App Password
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // usually 'tls'
    $mail->Port       = SMTP_PORT;           // Uses 587

    // Sender Info
    $mail->setFrom(SMTP_USERNAME, 'Tavern Publico');
    $mail->addReplyTo(SMTP_USERNAME, 'Tavern Publico');

    // Content Settings
    $mail->isHTML(true);                               
    $mail->Subject = $subject;
    $mail->Body    = $body;
    // Strip HTML tags for plain text fallback
    $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '</p>'], "\r\n", $body));

    $successCount = 0;
    $failCount = 0;

    // Send emails individually to protect user privacy (so they don't see each other's emails)
    foreach ($recipientEmails as $email) {
        try {
            $mail->clearAllRecipients(); // Clear previous recipient
            $mail->addAddress($email);
            $mail->send();
            $successCount++;
        } catch (Exception $e) {
            error_log("Failed to send email blast to $email. Error: {$mail->ErrorInfo}");
            $failCount++;
        }
    }

    echo json_encode([
        'success' => true, 
        'message' => "Successfully sent $successCount emails." . ($failCount > 0 ? " ($failCount failed.)" : "")
    ]);

} catch (Exception $e) {
    error_log("PHPMailer setup error in send_email_blast.php: {$mail->ErrorInfo}");
    echo json_encode(['success' => false, 'message' => "Mailer Error: {$mail->ErrorInfo}"]);
}

mysqli_close($link);
?>