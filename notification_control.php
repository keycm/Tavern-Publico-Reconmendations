<?php
session_start();
require_once 'db_connect.php';

$is_authorized = false;
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'owner') {
        $is_authorized = true;
    } elseif (isset($_SESSION['role']) && $_SESSION['role'] === 'manager') {
        if (isset($_SESSION['permissions']) && is_array($_SESSION['permissions']) && in_array('access_notifications', $_SESSION['permissions'])) {
            $is_authorized = true;
        }
    }
}

if (!$is_authorized) {
    header('Location: login'); 
    exit;
}

$currentPage = basename($_SERVER['SCRIPT_NAME']);

$messages = [];
$sql_messages = "SELECT * FROM contact_messages WHERE deleted_at IS NULL ORDER BY created_at DESC";
if ($result = mysqli_query($link, $sql_messages)) {
    while ($row = mysqli_fetch_assoc($result)) {
        $messages[] = $row;
    }
}

$testimonials = [];
$sql_testimonials = "SELECT t.*, u.username FROM testimonials t JOIN users u ON t.user_id = u.user_id WHERE t.deleted_at IS NULL ORDER BY t.created_at DESC";
if ($result = mysqli_query($link, $sql_testimonials)) {
    while ($row = mysqli_fetch_assoc($result)) {
        $testimonials[] = $row;
    }
}

// Fetch verified users for the Email Blast Multi-Select Dropdown
$verified_users = [];
$sql_verified = "SELECT email, username FROM users WHERE is_verified = 1 AND deleted_at IS NULL ORDER BY username ASC";
if ($result = mysqli_query($link, $sql_verified)) {
    while ($row = mysqli_fetch_assoc($result)) {
        $verified_users[] = $row;
    }
}

mysqli_close($link);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tavern Publico - Notification Control</title>
    <link rel="stylesheet" href="CSS/admin.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        .tabs-container { display: flex; border-bottom: 2px solid #e0e0e0; margin-bottom: 25px; gap: 5px; }
        .tab-button { padding: 12px 24px; cursor: pointer; border: none; background-color: transparent; font-size: 15px; font-weight: 600; color: #666; text-decoration: none; border-top-left-radius: 8px; border-top-right-radius: 8px; margin-bottom: -2px; display: flex; align-items: center; gap: 8px; transition: all 0.3s ease; }
        .tab-button:hover { background-color: #f4f6f8; color: #007bff; }
        .tab-button.active { background-color: #007bff; color: #fff; box-shadow: 0 -3px 10px rgba(0,123,255,0.2); }
        .tab-content { display: none; }
        .tab-content.active { display: block; animation: fadeIn 0.4s ease-in-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
        .reservation-page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px; }
        .reservation-page-header h2 { font-size: 22px; margin: 0; color: #2c3e50; font-weight: 700; }
        .search-input { padding: 10px 18px; border: 1px solid #d1d5db; border-radius: 20px; width: 320px; max-width: 100%; font-size: 14px; outline: none; transition: border-color 0.3s, box-shadow 0.3s; }
        .search-input:focus { border-color: #007bff; box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.15); }
        .table-responsive { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; background: #fff; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); border: 1px solid #eaedf1; }
        table { width: 100%; border-collapse: collapse; min-width: 750px; }
        table th, table td { padding: 16px; text-align: left; border-bottom: 1px solid #eaedf1; vertical-align: middle; }
        table th { background-color: #f8f9fa; font-weight: 600; color: #495057; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; }
        table tbody tr { transition: background-color 0.2s; }
        table tbody tr:hover { background-color: #fcfdfd; }
        .truncate-text { display: block; max-width: 250px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .status-badge { padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; display: inline-block; }
        .status-badge.confirmed { background-color: #d1e7dd; color: #0f5132; }
        .status-badge.pending { background-color: #fff3cd; color: #856404; }
        .actions { display: flex; gap: 8px; flex-wrap: wrap; }
        .btn { border: none; padding: 8px 14px; border-radius: 6px; cursor: pointer; font-size: 13px; font-weight: 500; transition: all 0.2s; display: inline-flex; align-items: center; justify-content: center; }
        .btn-small { padding: 6px 12px; font-size: 12px; }
        .btn.view-full-text-btn { background-color: #e0f2fe; color: #0284c7; }
        .btn.view-full-text-btn:hover { background-color: #bae6fd; }
        .btn.reply-message-btn { background-color: #dcfce7; color: #166534; }
        .btn.reply-message-btn:hover { background-color: #bbf7d0; }
        .btn.delete-message-btn, .btn.delete-testimonial-btn { background-color: #fee2e2; color: #991b1b; }
        .btn.delete-message-btn:hover, .btn.delete-testimonial-btn:hover { background-color: #fecaca; }
        .btn.feature-btn[data-featured="1"] { background-color: #0ea5e9; color: white; }
        .btn.feature-btn[data-featured="0"] { background-color: #94a3b8; color: white; }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); align-items: center; justify-content: center; padding: 15px; }
        .modal-content { background-color: #fff; border-radius: 12px; width: 100%; max-width: 500px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); display: flex; flex-direction: column; overflow: hidden; max-height: 90vh; }
        .modal-header { padding: 20px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; background-color: #fafbfc; }
        .modal-header h2 { margin: 0; font-size: 18px; color: #333; }
        .modal-body { padding: 20px; overflow-y: auto; }
        .modal-actions { padding: 20px; border-top: 1px solid #eee; display: flex; justify-content: flex-end; gap: 10px; background-color: #fafbfc; }
        .close-button { font-size: 24px; color: #999; cursor: pointer; background: none; border: none; padding: 0; line-height: 1; transition: color 0.2s; }
        .close-button:hover { color: #333; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #444; font-size: 14px; }
        .form-group textarea, .form-group input, .form-group select { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 6px; font-family: inherit; font-size: 14px; resize: vertical; box-sizing: border-box; }
        .form-group textarea:focus, .form-group input:focus, .form-group select:focus { border-color: #007bff; outline: none; }
        #readMoreBody { white-space: pre-wrap; text-align: left; background-color: #f8f9fa; padding: 15px; border-radius: 8px; border: 1px solid #e9ecef; line-height: 1.6; color: #333; margin: 0; }
        .modal-save-btn { background-color: #007bff; color: white; padding: 10px 20px; font-size: 14px; }
        .modal-save-btn:hover { background-color: #0056b3; }
        .btn-loading { position: relative; color: transparent !important; cursor: wait; pointer-events: none; }
        .btn-loading::after { content: ''; position: absolute; left: 50%; top: 50%; width: 18px; height: 18px; margin-left: -9px; margin-top: -9px; border: 2px solid rgba(255,255,255,0.5); border-top-color: #fff; border-radius: 50%; animation: spin 0.8s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .pagination-container { display: flex; justify-content: center; align-items: center; margin-top: 25px; padding: 10px 0; gap: 8px; flex-wrap: wrap; }
        #pageNumbersMessages, #pageNumbersTestimonials { display: flex; gap: 6px; flex-wrap: wrap; }
        .page-number { padding: 8px 14px; border: 1px solid #dee2e6; border-radius: 6px; cursor: pointer; transition: all 0.2s; background-color: #fff; color: #495057; font-weight: 500; font-size: 14px; }
        .page-number:hover { background-color: #e9ecef; color: #212529; }
        .page-number.active { background-color: #007bff; color: white; border-color: #007bff; font-weight: 600; }
        .pagination-container .btn:disabled { background-color: #f8f9fa; color: #adb5bd; border: 1px solid #dee2e6; cursor: not-allowed; }

        /* Quill Editor overrides */
        #editor-container { background-color: #fff; border-bottom-left-radius: 6px; border-bottom-right-radius: 6px; }
        .ql-toolbar.ql-snow { border-top-left-radius: 6px; border-top-right-radius: 6px; background: #f8f9fa; }

        /* Select2 Theme Overrides to Match UI */
        .select2-container--default .select2-selection--multiple {
            border: 1px solid #ccc;
            border-radius: 6px;
            padding: 4px;
            min-height: 44px;
        }
        .select2-container--default.select2-container--focus .select2-selection--multiple {
            border-color: #007bff;
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.15);
        }
        .select2-container--default .select2-selection--multiple .select2-selection__choice {
            background-color: #e0f2fe;
            border: 1px solid #bae6fd;
            color: #0284c7;
            border-radius: 4px;
            padding: 4px 8px;
            margin-top: 4px;
            font-size: 13px;
        }
        .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
            color: #0284c7;
            margin-right: 6px;
            border-right: none;
        }
        .select2-container--default .select2-selection--multiple .select2-selection__choice__remove:hover {
            background-color: transparent;
            color: #0369a1;
        }

        @media screen and (max-width: 768px) {
            .tabs-container { flex-direction: column; background: #f8f9fa; border-radius: 8px; padding: 5px; border-bottom: none; }
            .tab-button { width: 100%; justify-content: center; border-radius: 6px; margin-bottom: 2px; }
            .reservation-page-header { flex-direction: column; align-items: flex-start; gap: 15px; }
            .search-input { width: 100%; }
            .actions { flex-direction: column; }
            .btn.btn-small { width: 100%; }
        }
    </style>
</head>
<body>

    <div class="page-wrapper">
        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'manager') { include 'partials/manager_sidebar.php'; } else { ?>
            <aside class="admin-sidebar">
                <div class="sidebar-header"> <img src="Tavern.png" alt="Home Icon" class="home-icon"> </div>
                <nav>
                     <ul class="sidebar-menu">
                        <li class="menu-item"><a href="admin"><i class="material-icons">dashboard</i> Dashboard</a></li>
                        <li class="menu-item"><a href="reservation"><i class="material-icons">event_note</i> Reservation</a></li>
                        <li class="menu-item"><a href="update"><i class="material-icons">file_upload</i> Upload Management</a></li>
                    </ul>
                    <div class="user-management-title">User Management</div>
                    <ul class="sidebar-menu user-management-menu">
                        <li class="menu-item"><a href="customer_database"><i class="material-icons">people</i> Customer Database</a></li>
                        <li class="menu-item active"><a href="notification_control"><i class="material-icons">notifications</i> Notification Control</a></li>
                        <li class="menu-item"><a href="table_management"><i class="material-icons">table_chart</i>Calendar Management</a></li>
                        <li class="menu-item"><a href="reports"><i class="material-icons">analytics</i>Reservation Reports</a></li>
                        <li class="menu-item"><a href="deletion_history"><i class="material-icons">history</i>Archive</a></li>
                    </ul>
                </nav>
            </aside>
        <?php } ?>

        <div class="admin-content-area">
            <header class="main-header">
                <div class="header-content">
                    <h1 class="header-page-title">Notification Control</h1>
                    <div class="admin-header-right">
                        <div class="admin-notification-area">
                            <div class="admin-notification-item">
                                <button class="admin-notification-button" id="adminMessageBtn" title="Messages">
                                    <i class="material-icons">email</i>
                                    <span class="admin-notification-badge" id="adminMessageCount" style="display: none;">0</span>
                                </button>
                                <div class="admin-notification-dropdown" id="adminMessageDropdown"></div>
                            </div>
                            <div class="admin-notification-item">
                                <button class="admin-notification-button" id="adminReservationBtn" title="Reservations">
                                    <i class="material-icons">notifications</i> <span class="admin-notification-badge" id="adminReservationCount" style="display: none;">0</span>
                                </button>
                                <div class="admin-notification-dropdown" id="adminReservationDropdown"></div>
                            </div>
                        </div>
                        <div class="header-separator"></div>
                        <div class="admin-profile-dropdown">
                            <div class="admin-profile-area" id="adminProfileBtn">
                                <?php $admin_avatar_path = isset($_SESSION['avatar']) && file_exists($_SESSION['avatar']) ? htmlspecialchars($_SESSION['avatar']) : 'images/default_avatar.png'; ?>
                                <img src="<?php echo $admin_avatar_path; ?>" alt="Admin Avatar" class="admin-avatar">
                                <div class="admin-user-info">
                                    <span class="admin-username"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                                    <span class="admin-role"><?php echo ucfirst(htmlspecialchars($_SESSION['role'])); ?></span>
                                </div>
                                <i class="material-icons" style="color: #666; margin-left: 5px;">arrow_drop_down</i>
                            </div>
                            <div class="admin-dropdown" id="adminProfileDropdown">
                                <a href="index" class="admin-dropdown-item"><i class="material-icons">home</i><span>Homepage</span></a>
                                <a href="audit_logs" class="admin-dropdown-item"><i class="material-icons">history_edu</i><span>Audit Logs</span></a>
                                <a href="logout" class="admin-dropdown-item"><i class="material-icons">logout</i><span>Log Out</span></a>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <main class="dashboard-main-content">
                <div class="tabs-container">
                    <button class="tab-button active" onclick="openTab(event, 'messages')"><i class="material-icons">email</i><span>Contact Messages</span></button>
                    <button class="tab-button" onclick="openTab(event, 'testimonials')"><i class="material-icons">star_rate</i><span>Guest Testimonials</span></button>
                    <button class="tab-button" onclick="openTab(event, 'email_blast')"><i class="material-icons">forward_to_inbox</i><span>Email Blast</span></button>
                </div>

                <div id="messages" class="tab-content active">
                    <div class="reservation-page-header">
                        <h2>Contact Form Messages</h2>
                        <input type="text" id="messageSearch" class="search-input" placeholder="Search messages...">
                    </div>
                    <section class="all-reservations-section">
                        <div class="table-responsive">
                            <table id="messagesTable">
                                <thead>
                                    <tr><th>CUSTOMER</th> <th>SUBJECT</th> <th>MESSAGE</th> <th>RECEIVED</th> <th>STATUS</th> <th>ACTIONS</th></tr>
                                </thead>
                                <tbody id="messagesTableBody">
                                    <?php if (empty($messages)): ?>
                                        <tr><td colspan="6" style="text-align: center; color: #777;">No messages found.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($messages as $message): ?>
                                            <tr data-id="<?php echo $message['id']; ?>" 
                                                data-email="<?php echo htmlspecialchars($message['email']); ?>"
                                                data-subject="<?php echo htmlspecialchars($message['subject']); ?>"
                                                data-messagebody="<?php echo htmlspecialchars($message['message']); ?>">
                                                <td>
                                                    <strong style="color: #333; font-size: 14px;"><?php echo htmlspecialchars($message['name']); ?></strong><br>
                                                    <small style="color: #777;"><?php echo htmlspecialchars($message['email']); ?></small>
                                                </td>
                                                <td style="font-weight: 500; color: #444;"><?php echo htmlspecialchars($message['subject']); ?></td>
                                                <td><span class="truncate-text" title="<?php echo htmlspecialchars($message['message']); ?>"><?php echo htmlspecialchars($message['message']); ?></span></td>
                                                <td style="font-size: 13px; color: #666;"><?php echo htmlspecialchars($message['created_at']); ?></td>
                                                <td><span class="status-badge <?php echo !empty($message['replied_at']) ? 'confirmed' : 'pending'; ?>"><?php echo !empty($message['replied_at']) ? 'Replied' : 'New'; ?></span></td>
                                                <td class="actions">
                                                    <button class="btn btn-small view-full-text-btn"><i class="material-icons" style="font-size: 16px; margin-right: 4px;">visibility</i> View</button>
                                                    <button class="btn btn-small reply-message-btn"><i class="material-icons" style="font-size: 16px; margin-right: 4px;">reply</i> Reply</button>
                                                    <button class="btn btn-small delete-message-btn"><i class="material-icons" style="font-size: 16px; margin-right: 4px;">delete</i> Delete</button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="pagination-container" id="paginationMessages">
                            <button class="btn" id="prevPageMessages" disabled>&laquo; Prev</button>
                            <div id="pageNumbersMessages"></div>
                            <button class="btn" id="nextPageMessages">Next &raquo;</button>
                        </div>
                    </section>
                </div>

                <div id="testimonials" class="tab-content">
                    <div class="reservation-page-header">
                        <h2>Guest Testimonials</h2>
                        <input type="text" id="testimonialSearch" class="search-input" placeholder="Search testimonials...">
                    </div>
                    <section class="all-reservations-section">
                        <div class="table-responsive">
                            <table id="testimonialsTable">
                                <thead>
                                    <tr><th>USERNAME</th> <th>RATING</th> <th>COMMENT</th> <th>FEATURED</th> <th>ACTIONS</th></tr>
                                </thead>
                                <tbody id="testimonialsTableBody">
                                    <?php if (empty($testimonials)): ?>
                                        <tr><td colspan="5" style="text-align: center; color: #777;">No testimonials found.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($testimonials as $testimonial): ?>
                                            <tr data-id="<?php echo $testimonial['id']; ?>" 
                                                data-comment="<?php echo htmlspecialchars($testimonial['comment'], ENT_QUOTES); ?>" 
                                                data-username="<?php echo htmlspecialchars($testimonial['username'], ENT_QUOTES); ?>">
                                                <td style="font-weight: 500; color: #333;"><?php echo htmlspecialchars($testimonial['username']); ?></td>
                                                <td style="color: #f5b301; font-size: 16px;"><?php echo str_repeat('★', $testimonial['rating']) . '<span style="color:#e0e0e0;">'.str_repeat('★', 5 - $testimonial['rating']).'</span>'; ?></td>
                                                <td><span class="truncate-text" title="<?php echo htmlspecialchars($testimonial['comment']); ?>"><?php echo htmlspecialchars($testimonial['comment']); ?></span></td>
                                                <td>
                                                    <button class="btn btn-small feature-btn" data-featured="<?php echo $testimonial['is_featured']; ?>">
                                                        <?php echo $testimonial['is_featured'] ? 'Yes' : 'No'; ?>
                                                    </button>
                                                </td>
                                                <td class="actions">
                                                    <button class="btn btn-small view-full-text-btn"><i class="material-icons" style="font-size: 16px; margin-right: 4px;">visibility</i> View</button>
                                                    <button class="btn btn-small delete-testimonial-btn"><i class="material-icons" style="font-size: 16px; margin-right: 4px;">delete</i> Delete</button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                         <div class="pagination-container" id="paginationTestimonials">
                            <button class="btn" id="prevPageTestimonials" disabled>&laquo; Prev</button>
                            <div id="pageNumbersTestimonials"></div>
                            <button class="btn" id="nextPageTestimonials">Next &raquo;</button>
                        </div>
                    </section>
                </div>
                
                <div id="email_blast" class="tab-content">
                    <div class="reservation-page-header">
                        <h2>Send Email Update / Promo</h2>
                    </div>
                    <section class="all-reservations-section" style="padding: 20px; background: transparent; border: none; box-shadow: none;">
                        <form id="emailBlastForm" style="max-width: 800px; margin: 0 auto; background: #fff; padding: 25px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); border: 1px solid #eaedf1;">
                            <div class="form-group">
                                <label for="emailTarget">Send To:</label>
                                <select id="emailTarget" name="emailTarget">
                                    <option value="all">All Verified Customers</option>
                                    <option value="specific">Specific Customer(s)</option>
                                </select>
                            </div>
                            <div class="form-group" id="specificEmailGroup" style="display: none;">
                                <label for="specificEmails">Select Specific Customer(s):</label>
                                <select id="specificEmails" name="specificEmails[]" multiple="multiple" style="width: 100%;">
                                    <?php foreach ($verified_users as $vu): ?>
                                        <option value="<?= htmlspecialchars($vu['email']) ?>"><?= htmlspecialchars($vu['username']) ?> (<?= htmlspecialchars($vu['email']) ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                                <small style="color: #777; display:block; margin-top:5px;">Click to select multiple customers. You can also type to search.</small>
                            </div>
                            <div class="form-group">
                                <label for="emailSubject">Subject:</label>
                                <input type="text" id="emailSubject" name="subject" placeholder="e.g. Special Promo: 20% Off This Weekend!" required>
                            </div>
                            <div class="form-group">
                                <label>Message Content:</label>
                                <div id="editor-container" style="height: 300px;"></div>
                                <input type="hidden" id="emailBody" name="body">
                            </div>
                            <div class="form-actions" style="margin-top: 20px; text-align: right;">
                                <button type="submit" class="btn btn-info" id="emailSubmitBtn" style="background-color: #0284c7; color: white; padding: 10px 25px; font-size: 15px; border-radius: 6px;"><i class="material-icons" style="font-size: 18px; margin-right: 5px; vertical-align: bottom;">send</i> Send Email</button>
                            </div>
                        </form>
                    </section>
                </div>
            </main>
        </div>
    </div>

    <div id="replyModal" class="modal">
        <div class="modal-content">
            <div class="modal-header"><h2>Reply to Message</h2><button class="close-button">&times;</button></div>
            <div class="modal-body">
                <form id="replyMessageForm">
                    <input type="hidden" id="replyMessageId" name="message_id">
                    <input type="hidden" id="replyCustomerEmail" name="customer_email">
                    <div class="form-group">
                        <label>Original Message:</label>
                        <div id="originalMessage" style="background-color: #f8f9fa; padding: 12px; border-radius: 6px; min-height: 80px; max-height: 120px; overflow-y: auto; border: 1px solid #e9ecef; font-size: 14px; color: #555;"></div>
                    </div>
                    <div class="form-group">
                        <label for="replyText">Your Reply:</label>
                        <textarea id="replyText" name="reply_text" rows="5" placeholder="Type your response here..." required></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-actions">
                <button type="submit" class="btn modal-save-btn" form="replyMessageForm"><i class="material-icons" style="font-size: 18px; margin-right: 5px; vertical-align: bottom;">send</i> Send Reply</button>
            </div>
        </div>
    </div>
    
    <div id="readMoreModal" class="modal">
        <div class="modal-content">
            <div class="modal-header"><h2 id="readMoreTitle">Full Text</h2><button class="close-button">&times;</button></div>
            <div class="modal-body"><p id="readMoreBody"></p></div>
        </div>
    </div>

    <div id="alertModal" class="modal">
        <div class="modal-content" style="max-width: 400px; text-align: center;">
            <div class="modal-header" style="padding: 30px 20px 0; border: none; justify-content: flex-end;">
                <button class="close-button" style="position: absolute; top: 15px; right: 15px;">&times;</button>
            </div>
            <div class="modal-body" style="padding: 0 30px 20px;">
                <div id="modalHeaderIcon" class="modal-header-icon" style="margin-bottom: 15px;"></div>
                <h2 id="alertModalTitle" style="margin-top: 0; margin-bottom: 12px; font-size: 22px; color: #333;"></h2>
                <p id="alertModalMessage" style="margin-bottom: 0; color: #666; font-size: 15px;"></p>
            </div>
            <div id="alertModalActions" class="modal-actions" style="justify-content: center; padding: 20px 30px 30px; border-top: none; background: #fff;"></div>
        </div>
    </div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    // Tab Logic
    function openTab(evt, tabName) {
        var i, tabcontent, tablinks;
        tabcontent = document.getElementsByClassName("tab-content");
        for (i = 0; i < tabcontent.length; i++) {
            tabcontent[i].style.display = "none";
            tabcontent[i].classList.remove("active");
        }
        tablinks = document.getElementsByClassName("tab-button");
        for (i = 0; i < tablinks.length; i++) {
            tablinks[i].className = tablinks[i].className.replace(" active", "");
        }
        document.getElementById(tabName).style.display = "block";
        document.getElementById(tabName).classList.add("active");
        evt.currentTarget.className += " active";
    }

    document.addEventListener('DOMContentLoaded', () => {
        // Initialize Select2 for the dynamic email chooser
        $('#specificEmails').select2({
            placeholder: "Search and select customers...",
            allowClear: true
        });

        // Init Quill Editor
        var quill = new Quill('#editor-container', {
            theme: 'snow',
            placeholder: 'Compose your email message here...',
            modules: {
                toolbar: [
                    [{ 'font': [] }, { 'size': ['small', false, 'large', 'huge'] }],
                    [{ 'color': [] }, { 'background': [] }],
                    ['bold', 'italic', 'underline', 'strike'],
                    [{ 'header': 1 }, { 'header': 2 }],
                    [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                    [{ 'align': [] }],
                    ['link', 'image'],
                    ['clean']
                ]
            }
        });

        // Email Blast Form Logic
        const emailTarget = document.getElementById('emailTarget');
        const specificEmailGroup = document.getElementById('specificEmailGroup');
        const emailBlastForm = document.getElementById('emailBlastForm');

        if(emailTarget) {
            emailTarget.addEventListener('change', (e) => {
                if(e.target.value === 'specific') {
                    specificEmailGroup.style.display = 'block';
                } else {
                    specificEmailGroup.style.display = 'none';
                    $('#specificEmails').val(null).trigger('change'); // Clear selections when hiding
                }
            });
        }

        if(emailBlastForm) {
            emailBlastForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                
                const htmlContent = document.querySelector('#editor-container .ql-editor').innerHTML;
                document.getElementById('emailBody').value = htmlContent;

                if (quill.getText().trim().length === 0) {
                    showAlert('Error', 'The email message body cannot be empty.');
                    return;
                }

                const formData = new FormData(emailBlastForm);
                
                // MULTI-SELECT FORMATTING: Ensure it parses seamlessly with your backend
                if(emailTarget.value === 'specific') {
                    const selectedEmails = $('#specificEmails').val();
                    if(!selectedEmails || selectedEmails.length === 0) {
                        showAlert('Error', 'Please select at least one customer.');
                        return;
                    }
                    formData.delete('specificEmails[]');
                    formData.append('specificEmails', selectedEmails.join(','));
                }

                const submitBtn = document.getElementById('emailSubmitBtn');
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="material-icons" style="font-size: 18px; margin-right: 5px; vertical-align: bottom;">autorenew</i> Sending...';
                submitBtn.disabled = true;

                try {
                    // FIXED URL: Removed .php extension here so .htaccess doesn't trigger a 301 redirect and wipe the POST data
                    const response = await fetch('send_email_blast', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        showAlert('Success', result.message || 'Your update/promo was sent successfully.', () => {
                            emailBlastForm.reset();
                            quill.setContents([]);
                            $('#specificEmails').val(null).trigger('change'); // Reset Select2 UI
                            emailTarget.dispatchEvent(new Event('change'));
                        });
                    } else {
                        showAlert('Error', result.message || 'Failed to send emails.');
                    }
                } catch (error) {
                    console.error('Email action error:', error);
                    showAlert('Error', 'Failed to communicate with the server.');
                } finally {
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }
            });
        }

        // -------------------------------------------------------------
        // COMBINED AND CLEANED MODAL & NOTIFICATION JAVASCRIPT
        // -------------------------------------------------------------
        const alertModal = $('#alertModal');
        const alertModalTitle = $('#alertModalTitle');
        const alertModalMessage = $('#alertModalMessage');
        const alertModalActions = $('#alertModalActions');
        const alertModalIcon = $('#modalHeaderIcon'); 

        function showAlert(title, message, callback = null) {
            alertModalTitle.text(title);
            alertModalMessage.text(message);
            
            if (title.toLowerCase().includes('error') || title.toLowerCase().includes('failed')) {
                alertModalIcon.html('<i class="material-icons" style="color: #dc3545; font-size: 60px;">error_outline</i>');
            } else if (title.toLowerCase().includes('success')) {
                alertModalIcon.html('<i class="material-icons" style="color: #28a745; font-size: 60px;">check_circle_outline</i>');
            } else {
                 alertModalIcon.html(''); 
            }

            alertModalActions.html('<button class="btn" id="alertOkBtn" style="background-color: #007bff; color: white; padding: 10px 30px; border-radius: 20px;">OK</button>');
            alertModal.css('display', 'flex');
            
            $(document).off('click', '#alertOkBtn').on('click', '#alertOkBtn', function() {
                alertModal.css('display', 'none');
                if (callback) callback();
            });
        }

        function showConfirm(title, message, callback) {
            alertModalTitle.text(title);
            alertModalMessage.text(message);
            alertModalIcon.html('<i class="material-icons" style="color: #f5b301; font-size: 60px;">help_outline</i>');
            
            alertModalActions.html(
                '<button class="btn" id="confirmCancelBtn" style="background-color: #f8f9fa; color: #333; border: 1px solid #ccc;">Cancel</button>' +
                '<button class="btn" id="confirmOkBtn" style="background-color: #dc3545; color: white;">Yes, Proceed</button>'
            );
            alertModal.css('display', 'flex');

            $(document).off('click', '#confirmOkBtn').on('click', '#confirmOkBtn', function() {
                alertModal.css('display', 'none');
                callback(true);
            });
            $(document).off('click', '#confirmCancelBtn').on('click', '#confirmCancelBtn', function() {
                alertModal.css('display', 'none');
                callback(false);
            });
        }

        alertModal.on('click', '.close-button', function() { alertModal.css('display', 'none'); });
        
        // --- Admin Top Dropdown Notifications ---
        const messageBtn = document.getElementById('adminMessageBtn');
        const reservationBtn = document.getElementById('adminReservationBtn');
        const messageDropdown = document.getElementById('adminMessageDropdown');
        const reservationDropdown = document.getElementById('adminReservationDropdown');
        const adminProfileBtn = document.getElementById('adminProfileBtn');
        const adminProfileDropdown = document.getElementById('adminProfileDropdown');

        async function fetchAdminNotifications() {
            try {
                const response = await fetch('get_admin_notifications'); 
                const data = await response.json();
                if (data.success) {
                    const msgBadge = document.getElementById('adminMessageCount');
                    if (data.new_messages > 0) { msgBadge.textContent = data.new_messages; msgBadge.style.display = 'block'; } 
                    else { msgBadge.style.display = 'none'; }
                    messageDropdown.innerHTML = data.messages_html;

                    const resBadge = document.getElementById('adminReservationCount');
                    if (data.pending_reservations > 0) { resBadge.textContent = data.pending_reservations; resBadge.style.display = 'block'; } 
                    else { resBadge.style.display = 'none'; }
                    reservationDropdown.innerHTML = data.reservations_html;
                }
            } catch (error) { console.error('Error fetching admin notifications:', error); }
        }

        if (messageBtn) messageBtn.addEventListener('click', (e) => { e.stopPropagation(); if (reservationDropdown) reservationDropdown.classList.remove('show'); if (adminProfileDropdown) adminProfileDropdown.classList.remove('show'); messageDropdown.classList.toggle('show'); });
        if (reservationBtn) reservationBtn.addEventListener('click', (e) => { e.stopPropagation(); if (messageDropdown) messageDropdown.classList.remove('show'); if (adminProfileDropdown) adminProfileDropdown.classList.remove('show'); reservationDropdown.classList.toggle('show'); });
        if (adminProfileBtn) adminProfileBtn.addEventListener('click', (e) => { e.stopPropagation(); if (messageDropdown) messageDropdown.classList.remove('show'); if (reservationDropdown) reservationDropdown.classList.remove('show'); adminProfileDropdown.classList.toggle('show'); });

        window.addEventListener('click', (event) => {
            if (messageDropdown) messageDropdown.classList.remove('show');
            if (reservationDropdown) reservationDropdown.classList.remove('show');
            if (adminProfileDropdown) adminProfileDropdown.classList.remove('show'); 
            
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        });

        [messageDropdown, reservationDropdown].forEach(dropdown => {
            if (dropdown) dropdown.addEventListener('click', async (e) => {
                if (e.target.classList.contains('admin-notification-dismiss')) {
                    e.preventDefault(); e.stopPropagation(); 
                    const formData = new FormData();
                    formData.append('id', e.target.dataset.id);
                    formData.append('type', e.target.dataset.type);
                    try {
                        const response = await fetch('clear_admin_notification', { method: 'POST', body: formData }); 
                        const result = await response.json();
                        if (result.success) {
                            e.target.parentElement.style.opacity = '0';
                            setTimeout(() => { e.target.parentElement.remove(); fetchAdminNotifications(); }, 300);
                        } else { showAlert('Error', result.message); }
                    } catch (error) { showAlert('Error', 'An unexpected error occurred.'); }
                }
            });
        });

        fetchAdminNotifications();
        setInterval(fetchAdminNotifications, 30000); 

        // -------------------------------------------------------------
        // Testimonials Table Actions
        // -------------------------------------------------------------
        $('#testimonialsTableBody').on('click', '.feature-btn', function() {
            var btn = $(this);
            var testimonialId = btn.closest('tr').attr('data-id'); 
            var isCurrentlyFeatured = btn.data('featured') == 1;
            var actionText = isCurrentlyFeatured ? 'un-feature' : 'feature';

            showConfirm('Confirm Action', `Are you sure you want to ${actionText} this testimonial?`, function(confirmed) {
                if (confirmed) {
                    $.ajax({
                        url: 'manage_testimonial',
                        type: 'POST',
                        data: { action: 'feature', testimonial_id: testimonialId },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                var isFeatured = btn.data('featured') == 1;
                                btn.data('featured', isFeatured ? 0 : 1);
                                btn.attr('data-featured', isFeatured ? 0 : 1);
                                btn.text(isFeatured ? 'No' : 'Yes');
                                btn.css('background-color', isFeatured ? '#94a3b8' : '#0ea5e9'); 
                            } else { showAlert('Error', response.message); }
                        },
                        error: function() { showAlert('Error', 'An unexpected server error occurred.'); }
                    });
                }
            });
        });

        $('#testimonialsTableBody').on('click', '.delete-testimonial-btn', function() {
            var row = $(this).closest('tr');
            var testimonialId = row.attr('data-id');
            showConfirm('Confirm Deletion', 'Move this testimonial to the deletion history?', function(confirmed) {
                if (confirmed) {
                    $.ajax({
                        url: 'manage_testimonial',
                        type: 'POST',
                        data: { action: 'delete', testimonial_id: testimonialId },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                row.fadeOut(400, function() { $(this).remove(); initializePagination('testimonials'); });
                            } else { showAlert('Error', response.message); }
                        },
                        error: function() { showAlert('Error', 'An unexpected server error occurred.'); }
                    });
                }
            });
        });
        
        // -------------------------------------------------------------
        // Messages Table Actions
        // -------------------------------------------------------------
        const replyModal = document.getElementById('replyModal');
        
        $('#messagesTable').on('click', '.reply-message-btn', function() {
            var row = $(this).closest('tr');
            $('#replyMessageId').val(row.attr('data-id'));
            $('#replyCustomerEmail').val(row.attr('data-email'));
            $('#originalMessage').text(row.attr('data-messagebody'));
            replyModal.style.display = 'flex';
        });

        $('#messagesTable').on('click', '.delete-message-btn', function() {
            var row = $(this).closest('tr');
            var messageId = row.attr('data-id');
            showConfirm('Confirm Deletion', 'Move this message to the deletion history?', function(confirmed) {
                if (confirmed) {
                    $.ajax({
                        url: 'manage_message',
                        type: 'POST',
                        data: { action: 'delete', message_id: messageId },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                               row.fadeOut(400, function() { $(this).remove(); initializePagination('messages'); });
                            } else { showAlert('Error', response.message); }
                        },
                        error: function() { showAlert('Error', 'An unexpected server error occurred.'); }
                    });
                }
            });
        });

        $('#replyModal .close-button').on('click', function() { replyModal.style.display = 'none'; });

        $('#replyMessageForm').on('submit', function(e) {
            e.preventDefault();
            var form = $(this);
            var submitBtn = $('button[form="replyMessageForm"]');
            var formData = form.serialize() + '&action=reply';
            
            submitBtn.addClass('btn-loading');

            $.ajax({
                url: 'manage_message', 
                type: 'POST', 
                data: formData, 
                dataType: 'json',
                success: function(response) {
                    showAlert(response.success ? 'Success!' : 'Error', response.message, function() {
                        if (response.success) {
                            replyModal.style.display = 'none';
                            location.reload();
                        }
                    });
                },
                error: function() { showAlert('Error', 'An unexpected server error occurred.'); },
                complete: function() { submitBtn.removeClass('btn-loading'); }
            });
        });

        // -------------------------------------------------------------
        // Read More Logic
        // -------------------------------------------------------------
        const readMoreModal = document.getElementById('readMoreModal');
        const readMoreTitle = document.getElementById('readMoreTitle');
        const readMoreBody = document.getElementById('readMoreBody');
        
        $('#readMoreModal .close-button').on('click', function() { readMoreModal.style.display = 'none'; });
        
        document.body.addEventListener('click', function(e) {
            if (e.target.classList.contains('view-full-text-btn') || e.target.closest('.view-full-text-btn')) {
                const row = e.target.closest('tr');
                if (row) {
                    if ($(row).closest('#messagesTable').length) {
                        readMoreTitle.textContent = `Message from ${row.querySelector('strong').textContent}`;
                        readMoreBody.textContent = row.getAttribute('data-messagebody');
                    } else if ($(row).closest('#testimonialsTable').length) {
                        readMoreTitle.textContent = `Comment from ${row.getAttribute('data-username')}`;
                        readMoreBody.textContent = row.getAttribute('data-comment');
                    }
                    readMoreModal.style.display = 'flex';
                }
            }
        });

        // -------------------------------------------------------------
        // Pagination Logic
        // -------------------------------------------------------------
        const paginationConfig = {
            messages: {
                tableBody: document.getElementById('messagesTableBody'),
                searchInput: document.getElementById('messageSearch'),
                paginationContainer: document.getElementById('paginationMessages'),
                prevPageBtn: document.getElementById('prevPageMessages'),
                nextPageBtn: document.getElementById('nextPageMessages'),
                pageNumbersContainer: document.getElementById('pageNumbersMessages'),
                rowsPerPage: 8, allRows: [], filteredRows: [], currentPage: 1
            },
            testimonials: {
                tableBody: document.getElementById('testimonialsTableBody'),
                searchInput: document.getElementById('testimonialSearch'),
                paginationContainer: document.getElementById('paginationTestimonials'),
                prevPageBtn: document.getElementById('prevPageTestimonials'),
                nextPageBtn: document.getElementById('nextPageTestimonials'),
                pageNumbersContainer: document.getElementById('pageNumbersTestimonials'),
                rowsPerPage: 8, allRows: [], filteredRows: [], currentPage: 1
            }
        };

        function initializePagination(key) {
            const config = paginationConfig[key];
            if (!config.tableBody) return; 
            config.allRows = Array.from(config.tableBody.querySelectorAll('tr'));
            config.filteredRows = config.allRows;

            if (config.allRows.length <= config.rowsPerPage) { config.paginationContainer.style.display = 'none'; } 
            else { config.paginationContainer.style.display = 'flex'; }

            $(config.searchInput).off('keyup').on('keyup', () => {
                const filter = config.searchInput.value.toLowerCase();
                config.filteredRows = config.allRows.filter(row => {
                    if (row.querySelector('td[colspan]')) return false; 
                    return row.textContent.toLowerCase().includes(filter);
                });
                displayPage(key, 1);
            });
            
            $(config.prevPageBtn).off('click').on('click', () => { if (config.currentPage > 1) displayPage(key, config.currentPage - 1); });
            $(config.nextPageBtn).off('click').on('click', () => {
                const pageCount = Math.ceil(config.filteredRows.length / config.rowsPerPage);
                if (config.currentPage < pageCount) displayPage(key, config.currentPage + 1);
            });
            
            displayPage(key, 1);
        }

        function displayPage(key, page) {
            const config = paginationConfig[key];
            if (!config.tableBody) return;
            config.currentPage = page;
            config.tableBody.innerHTML = ''; 

            const start = (page - 1) * config.rowsPerPage;
            const paginatedItems = config.filteredRows.slice(start, start + config.rowsPerPage);
            
            if (paginatedItems.length > 0) {
                paginatedItems.forEach(row => config.tableBody.appendChild(row));
            } else {
                const colSpan = (key === 'messages') ? 6 : 5;
                config.tableBody.innerHTML = `<tr><td colspan="${colSpan}" style="text-align: center; color: #777;">No items found.</td></tr>`;
            }
            updatePaginationUI(key);
        }

        function updatePaginationUI(key) {
            const config = paginationConfig[key];
            const pageCount = Math.ceil(config.filteredRows.length / config.rowsPerPage);
            
             if (pageCount <= 1) { config.paginationContainer.style.display = 'none'; return; }
            config.paginationContainer.style.display = 'flex';

            config.prevPageBtn.disabled = config.currentPage === 1;
            config.nextPageBtn.disabled = config.currentPage === pageCount;

            config.pageNumbersContainer.innerHTML = '';
            for (let i = 1; i <= pageCount; i++) {
                const pageBtn = document.createElement('button');
                pageBtn.textContent = i;
                pageBtn.className = 'page-number' + (i === config.currentPage ? ' active' : '');
                pageBtn.addEventListener('click', () => displayPage(key, i));
                config.pageNumbersContainer.appendChild(pageBtn);
            }
        }
    });
</script>
</body>
</html>
