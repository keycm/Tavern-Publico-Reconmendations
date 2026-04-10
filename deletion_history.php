<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['role']) || $_SESSION['role'] !== 'owner') {
    header('Location: login.php');
    exit;
}

// Helper function to get an icon for each item type
function getItemTypeIcon($type) {
    $icons = [
        'user' => 'person',
        'reservation' => 'event_note',
        'menu_item' => 'restaurant_menu',
        'gallery_image' => 'collections',
        'event' => 'event',
        'team_member' => 'group',
        'hero_slide' => 'view_carousel',
        'contact_message' => 'email',
        'testimonial' => 'star_rate',
        'blocked_date' => 'block',
        'coupon' => 'sell'
    ];
    return $icons[$type] ?? 'history'; 
}

// --- Sorting Logic ---
$sort_by = $_GET['sort'] ?? 'deleted_at';
$sort_order = $_GET['order'] ?? 'DESC';
$allowed_sort_columns = ['deleted_at', 'purge_date', 'action_by', 'item_type'];
if (!in_array($sort_by, $allowed_sort_columns)) {
    $sort_by = 'deleted_at';
}
if (!in_array(strtoupper($sort_order), ['ASC', 'DESC'])) {
    $sort_order = 'DESC';
}

// --- Fetching Logic ---
$deleted_items = [];
$item_types_result = mysqli_query($link, "SELECT DISTINCT item_type FROM deletion_history ORDER BY item_type ASC");
$item_types = [];
if ($item_types_result) {
    while($row = mysqli_fetch_assoc($item_types_result)) {
        $item_types[] = $row['item_type'];
    }
}

// First try to fetch including action_by
$sql = "SELECT log_id, item_type, item_id, item_data, action_by, deleted_at, purge_date FROM deletion_history ORDER BY $sort_by $sort_order";
$has_action_by_col = true;

if (!($result = mysqli_query($link, $sql))) {
    $has_action_by_col = false;
    // Fallback if action_by is missing in deletion_history DB
    $sql = "SELECT log_id, item_type, item_id, item_data, deleted_at, purge_date FROM deletion_history ORDER BY $sort_by $sort_order";
    $result = mysqli_query($link, $sql);
}

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        if (!$has_action_by_col) {
            $row['action_by'] = null;
        }
        
        // --- FIX: If "Deleted By" (action_by) is empty, fetch it from the reservations table ---
        if (empty($row['action_by']) && $row['item_type'] === 'reservation') {
            $item_id = (int)$row['item_id'];
            $rec_sql = "SELECT action_by FROM reservations WHERE reservation_id = $item_id";
            $rec_res = mysqli_query($link, $rec_sql);
            if ($rec_res && $rec_row = mysqli_fetch_assoc($rec_res)) {
                $row['action_by'] = $rec_row['action_by'];
            }
        }
        
        // Final fallback
        if (empty($row['action_by'])) {
            $row['action_by'] = 'Unknown';
        }
        
        $deleted_items[] = $row;
    }
    mysqli_free_result($result);
}
mysqli_close($link);

function get_sort_href($column, $current_sort, $current_order) {
    $order = ($current_sort === $column && $current_order === 'ASC') ? 'DESC' : 'ASC';
    return "?sort=$column&order=$order";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tavern Publico - Deletion History</title>
    <link rel="stylesheet" href="CSS/admin.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        .reservation-page-header { display: flex; justify-content: flex-start; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 20px; background: #fff; padding: 20px 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); border: 1px solid #eaedf1; }
        .search-input { width: 350px; flex-grow: 0; min-width: 250px; padding: 12px 20px; border: 1px solid #d1d5db; border-radius: 25px; font-size: 14px; outline: none; transition: border-color 0.3s, box-shadow 0.3s; background-color: #f8f9fa; }
        .search-input:focus { border-color: #007bff; box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.15); background-color: #fff; }
        .filter-group { display: flex; align-items: center; gap: 12px; }
        .filter-group label { font-weight: 600; color: #475569; margin-bottom: 0; white-space: nowrap; font-size: 14px; }
        .filter-group select { padding: 10px 18px; border: 1px solid #d1d5db; border-radius: 20px; background-color: #f8f9fa; font-size: 14px; cursor: pointer; outline: none; transition: border-color 0.3s, box-shadow 0.3s; font-family: inherit; }
        .filter-group select:focus { border-color: #007bff; box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.15); background-color: #fff; }
        .table-responsive { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; background: #fff; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); border: 1px solid #eaedf1; }
        table { width: 100%; border-collapse: collapse; min-width: 900px; }
        table th, table td { padding: 16px 20px; text-align: left; border-bottom: 1px solid #eaedf1; vertical-align: middle; }
        table th { background-color: #f8fafc; font-weight: 600; color: #475569; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; }
        table tbody tr { transition: background-color 0.2s; }
        table tbody tr:hover { background-color: #fcfdfd; }
        .sort-link { color: #475569; text-decoration: none; display: inline-flex; align-items: center; transition: color 0.2s; }
        .sort-link:hover { color: #0ea5e9; }
        .sort-link .material-icons { font-size: 16px; margin-left: 4px; color: #94a3b8;}
        .actions { display: flex; gap: 8px; flex-wrap: wrap; }
        .btn { border: none; padding: 10px 18px; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 500; transition: all 0.2s; display: inline-flex; align-items: center; justify-content: center; text-decoration: none; }
        .btn-small { padding: 6px 12px; font-size: 13px; }
        .btn-small i { font-size: 16px; margin-right: 4px; }
        .restore-btn { background-color: #dcfce7; color: #166534; }
        .restore-btn:hover { background-color: #bbf7d0; }
        .purge-btn { background-color: #fee2e2; color: #991b1b; }
        .purge-btn:hover { background-color: #fecaca; }
        .pagination-container { display: flex; justify-content: center; align-items: center; margin-top: 25px; padding: 10px 0; gap: 8px; flex-wrap: wrap; }
        #pageNumbers { display: flex; gap: 6px; flex-wrap: wrap; }
        .page-number { padding: 8px 14px; border: 1px solid #dee2e6; border-radius: 6px; cursor: pointer; transition: all 0.2s; background-color: #fff; color: #495057; font-weight: 500; font-size: 14px; }
        .page-number:hover { background-color: #e9ecef; color: #212529; }
        .page-number.active { background-color: #007bff; color: white; border-color: #007bff; font-weight: 600; }
        .pagination-container .btn:disabled { background-color: #f8f9fa; color: #adb5bd; border: 1px solid #dee2e6; cursor: not-allowed; }
        .modal { display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(15, 23, 42, 0.6); align-items: center; justify-content: center; padding: 15px; backdrop-filter: blur(4px); }
        .modal-content { background-color: #fff; border-radius: 12px; width: 100%; max-width: 450px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); display: flex; flex-direction: column; overflow: hidden; max-height: 90vh; }
        .modal-header { padding: 20px 25px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; background-color: #f8fafc; }
        .modal-header h2 { margin: 0; font-size: 18px; color: #1e293b; font-weight: 700; }
        .modal-body { padding: 25px; overflow-y: auto; }
        .modal-actions { padding: 20px 25px; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; gap: 10px; background-color: #f8fafc; }
        .close-button { font-size: 24px; color: #94a3b8; cursor: pointer; background: none; border: none; padding: 0; line-height: 1; transition: color 0.2s; }
        .close-button:hover { color: #334155; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #475569; font-size: 14px; }
        .form-group input[type="password"] { width: 100%; padding: 12px 15px; border: 1px solid #d1d5db; border-radius: 6px; box-sizing: border-box; transition: border-color 0.2s, box-shadow 0.2s; font-family: inherit; font-size: 14px; background: #fdfdfd; }
        .form-group input[type="password"]:focus { border-color: #0ea5e9; outline: none; box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.15); background: #fff; }
        .no-items-row { text-align: center; display: none; }
        @media screen and (max-width: 768px) {
            .reservation-page-header { flex-direction: column; align-items: stretch; gap: 15px; }
            .search-input { width: 100%; min-width: unset; }
            .filter-group { flex-direction: column; align-items: stretch; }
            .filter-group select { width: 100%; }
            .actions { flex-direction: column; }
            .btn.btn-small { width: 100%; justify-content: flex-start; }
        }
    </style>
</head>
<body>

    <div class="page-wrapper">
        <aside class="admin-sidebar">
            <div class="sidebar-header"><img src="Tavern.png" alt="Home Icon" class="home-icon"></div>
            <nav>
                <ul class="sidebar-menu">
                    <li class="menu-item"><a href="admin"><i class="material-icons">dashboard</i> Dashboard</a></li>
                    <li class="menu-item"><a href="reservation"><i class="material-icons">event_note</i> Reservation</a></li>
                    <li class="menu-item"><a href="update"><i class="material-icons">file_upload</i> Upload Management</a></li>
                </ul>
                <div class="user-management-title">User Management</div>
                <ul class="sidebar-menu user-management-menu">
                    <li class="menu-item"><a href="customer_database"><i class="material-icons">people</i> Customer Database</a></li>
                    <li class="menu-item"><a href="notification_control"><i class="material-icons">notifications</i> Notification Control</a></li>
                    <li class="menu-item"><a href="table_management"><i class="material-icons">table_chart</i>Calendar Management</a></li>
                    <li class="menu-item"><a href="reports"><i class="material-icons">analytics</i>Reservation Reports</a></li>
                    <li class="menu-item active"><a href="deletion_history"><i class="material-icons">history</i>Archive</a></li>
                </ul>
            </nav>
        </aside>

        <div class="admin-content-area">
            <header class="main-header">
                <div class="header-content">
                    <h1 class="header-page-title">Archive & Deletion History</h1>
                    
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
                <div class="reservation-page-header">
                    <input type="text" id="historySearch" class="search-input" placeholder="Search deleted items...">
                    
                    <div class="filter-group">
                        <label for="itemTypeFilter">Filter by Type:</label>
                        <select id="itemTypeFilter">
                            <option value="all">All Items</option>
                            <?php foreach($item_types as $type): ?>
                                <option value="<?= htmlspecialchars($type) ?>">
                                    <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $type))) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <section class="all-reservations-section">
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th><a href="<?= get_sort_href('item_type', $sort_by, $sort_order) ?>" class="sort-link">Item Type <i class="material-icons">sort</i></a></th>
                                    <th>ITEM DETAILS</th>
                                    <th><a href="<?= get_sort_href('action_by', $sort_by, $sort_order) ?>" class="sort-link">Deleted By <i class="material-icons">sort</i></a></th>
                                    <th><a href="<?= get_sort_href('deleted_at', $sort_by, $sort_order) ?>" class="sort-link">Deleted At <i class="material-icons">sort</i></a></th>
                                    <th><a href="<?= get_sort_href('purge_date', $sort_by, $sort_order) ?>" class="sort-link">Purge Date <i class="material-icons">sort</i></a></th>
                                    <th>ACTIONS</th>
                                </tr>
                            </thead>
                            <tbody id="historyTableBody">
                                <?php if (empty($deleted_items)): ?>
                                    <tr class="no-items-row" style="display: table-row;"><td colspan="6" style="text-align: center; color: #777;">No deleted items found.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($deleted_items as $item): 
                                        $item_data = json_decode($item['item_data'], true);
                                        $details = 'ID: ' . htmlspecialchars($item['item_id']);
                                        if (is_array($item_data)) {
                                            if (isset($item_data['username'])) $details = "User: " . htmlspecialchars($item_data['username']);
                                            elseif (isset($item_data['res_name'])) $details = "Reservation: " . htmlspecialchars($item_data['res_name']);
                                            elseif (isset($item_data['name'])) $details = "Name: " . htmlspecialchars($item_data['name']);
                                            elseif (isset($item_data['title'])) $details = "Title: " . htmlspecialchars($item_data['title']);
                                            elseif (isset($item_data['subject'])) $details = "Subject: " . htmlspecialchars($item_data['subject']);
                                            elseif (isset($item_data['block_date'])) $details = "Date: " . htmlspecialchars($item_data['block_date']);
                                            elseif (isset($item_data['code'])) $details = "Coupon: " . htmlspecialchars($item_data['code']);
                                        }
                                    ?>
                                        <tr data-log-id="<?= $item['log_id']; ?>" data-item-type="<?= htmlspecialchars($item['item_type']); ?>">
                                            <td>
                                                <div style="display: flex; align-items: center; gap: 8px;">
                                                    <i class="material-icons" style="color: #64748b; font-size: 18px;"><?= getItemTypeIcon($item['item_type']); ?></i>
                                                    <span style="font-weight: 500; color: #334155;"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $item['item_type']))); ?></span>
                                                </div>
                                            </td>
                                            <td style="color: #475569;"><?= $details; ?></td>
                                            <td style="color: #475569; font-weight: 500;"><?= htmlspecialchars($item['action_by'] ?? 'Unknown'); ?></td>
                                            <td style="color: #64748b; font-size: 13px;"><?= htmlspecialchars(date('M d, Y H:i', strtotime($item['deleted_at']))); ?></td>
                                            <td style="color: #64748b; font-size: 13px;"><?= htmlspecialchars(date('M d, Y', strtotime($item['purge_date']))); ?></td>
                                            <td class="actions">
                                                <button class="btn btn-small restore-btn"><i class="material-icons">restore</i> Restore</button>
                                                <button class="btn btn-small purge-btn"><i class="material-icons">delete_forever</i> Purge</button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <tr class="no-items-row" style="display: none;"><td colspan="6" style="text-align: center; color: #777;">No items found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="pagination-container">
                        <button class="btn" id="prevPageBtn" disabled>&laquo; Prev</button>
                        <div id="pageNumbers"></div>
                        <button class="btn" id="nextPageBtn">Next &raquo;</button>
                    </div>
                </section>
            </main>
        </div>
    </div>

    <div id="alertModal" class="modal">
        <div class="modal-content" style="max-width: 400px; text-align: center;">
            <div class="modal-header" style="padding: 20px 20px 0; border: none; justify-content: flex-end;">
                <button class="close-button">&times;</button>
            </div>
            <div class="modal-body" style="padding: 0 30px 20px;">
                <div id="modalHeaderIcon" class="modal-header-icon" style="margin-bottom: 15px;"></div>
                <h2 id="alertModalTitle" style="margin-top: 0; margin-bottom: 12px; font-size: 22px; color: #1e293b;"></h2>
                <p id="alertModalMessage" style="color: #64748b; margin-bottom: 0;"></p>
            </div>
            <div id="alertModalActions" class="modal-actions" style="justify-content: center; padding: 20px 30px 30px; border-top: none; background: #fff;">
            </div>
        </div>
    </div>

    <div id="passwordConfirmModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="material-icons" style="color: #ef4444; vertical-align: middle; margin-right: 8px;">warning</i> Confirm Archive Purge</h2>
                <button class="close-button">&times;</button>
            </div>
            <form id="passwordConfirmForm">
                <div class="modal-body">
                    <p style="color: #475569; margin-top: 0; margin-bottom: 20px; line-height: 1.5;">To remove this item from the Archive list, please verify your administrator password. <br><br><i>Note: The original record will remain securely soft-deleted in the main database.</i></p>
                    <input type="hidden" id="purgeLogId" name="log_id">
                    <div class="form-group">
                        <label for="adminPassword">Administrator Password:</label>
                        <input type="password" id="adminPassword" name="admin_password" placeholder="Enter your password" required>
                    </div>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn" id="cancelPurgeBtn" style="background-color: #f1f5f9; color: #475569;">Cancel</button>
                    <button type="submit" class="btn purge-btn" style="background-color: #ef4444; color: white;"><i class="material-icons">delete_forever</i> Purge Archive Record</button>
                </div>
            </form>
        </div>
    </div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {

        // --- Shared UI Functions (Alerts & Confirmations) ---
        const notificationModal = $('#alertModal');
        const modalHeaderIcon = $('#modalHeaderIcon');
        const modalTitle = $('#alertModalTitle');
        const modalMessage = $('#alertModalMessage');
        const modalActions = $('#alertModalActions');
        
        function showNotification(type, title, message, callback = null) {
            modalHeaderIcon.html(type === 'success' 
                ? '<i class="material-icons" style="color: #10b981; font-size: 60px;">check_circle_outline</i>' 
                : '<i class="material-icons" style="color: #ef4444; font-size: 60px;">error_outline</i>');
            modalTitle.text(title); 
            modalMessage.text(message);
            modalActions.html('<button class="btn modal-close-btn" style="background-color: #0ea5e9; color: white; padding: 10px 30px; border-radius: 20px;">OK</button>');
            
            notificationModal.css('display', 'flex');

            $(document).off('click', '.modal-close-btn, #alertModal .close-button').on('click', '.modal-close-btn, #alertModal .close-button', function() {
                notificationModal.css('display', 'none');
                if (callback) callback();
            });
        }

        function showConfirm(title, message, callback) {
            modalTitle.text(title);
            modalMessage.text(message);
            modalHeaderIcon.html('<i class="material-icons" style="color: #f5b301; font-size: 60px;">help_outline</i>');
            
            modalActions.html(
                '<button class="btn" id="confirmCancelBtn" style="background-color: #f8f9fa; color: #333; border: 1px solid #ccc;">Cancel</button>' +
                '<button class="btn" id="confirmOkBtn" style="background-color: #dc3545; color: white;">Yes, Proceed</button>'
            );
            notificationModal.css('display', 'flex');

            $(document).off('click', '#confirmOkBtn').on('click', '#confirmOkBtn', function() {
                notificationModal.css('display', 'none');
                callback(true);
            });
            $(document).off('click', '#confirmCancelBtn, #alertModal .close-button').on('click', '#confirmCancelBtn, #alertModal .close-button', function() {
                notificationModal.css('display', 'none');
                callback(false);
            });
        }

        // --- Admin Header Notifications Dropdown Logic ---
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

        window.addEventListener('click', () => {
            if (messageDropdown) messageDropdown.classList.remove('show');
            if (reservationDropdown) reservationDropdown.classList.remove('show');
            if (adminProfileDropdown) adminProfileDropdown.classList.remove('show'); 
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
                        } else { showNotification('error', 'Action Failed', result.message); }
                    } catch (error) { showNotification('error', 'Error', 'An unexpected error occurred.'); }
                }
            });
        });

        fetchAdminNotifications();
        setInterval(fetchAdminNotifications, 30000); 

        // --- Pagination and Filtering Logic ---
        const tableBody = document.getElementById('historyTableBody');
        const allRows = Array.from(tableBody.querySelectorAll('tr:not(.no-items-row)'));
        const rowsPerPage = 8;
        let currentPage = 1;
        let currentFilteredRows = allRows;

        function displayPage(page) {
            currentPage = page;
            const noItemsRow = tableBody.querySelector('.no-items-row');
            
            allRows.forEach(row => row.style.display = 'none');
            if (noItemsRow) noItemsRow.style.display = 'none';

            const start = (page - 1) * rowsPerPage;
            const end = start + rowsPerPage;
            const paginatedItems = currentFilteredRows.slice(start, end);

            if (paginatedItems.length > 0) {
                paginatedItems.forEach(row => { row.style.display = ''; });
            } else if (noItemsRow) {
                noItemsRow.style.display = 'table-row'; 
            }
            updatePaginationUI();
        }

        function updatePaginationUI() {
            const pageCount = Math.ceil(currentFilteredRows.length / rowsPerPage);
            const paginationContainer = document.querySelector('.pagination-container');
            const pageNumbersContainer = document.getElementById('pageNumbers');
            
            if (pageCount <= 1) {
                paginationContainer.style.display = 'none'; return;
            }
            
            paginationContainer.style.display = 'flex';
            document.getElementById('prevPageBtn').disabled = currentPage === 1;
            document.getElementById('nextPageBtn').disabled = currentPage === pageCount;

            pageNumbersContainer.innerHTML = '';
            for (let i = 1; i <= pageCount; i++) {
                const pageBtn = document.createElement('button');
                pageBtn.textContent = i;
                pageBtn.className = 'page-number' + (i === currentPage ? ' active' : '');
                pageBtn.addEventListener('click', () => displayPage(i));
                pageNumbersContainer.appendChild(pageBtn);
            }
        }

        function applyFilters() {
            const searchTerm = document.getElementById('historySearch').value.toLowerCase();
            const filterType = document.getElementById('itemTypeFilter').value;

            currentFilteredRows = allRows.filter(row => {
                if(row.classList.contains('no-items-row')) return false;
                const rowText = row.textContent.toLowerCase();
                const rowType = row.dataset.itemType;
                const matchesSearch = rowText.includes(searchTerm);
                const matchesType = (filterType === 'all') || (rowType === filterType);
                return matchesSearch && matchesType;
            });
            displayPage(1);
        }
        
        $('#historySearch').on('keyup', applyFilters);
        $('#itemTypeFilter').on('change', applyFilters);
        $('#prevPageBtn').on('click', () => { if (currentPage > 1) displayPage(currentPage - 1); });
        $('#nextPageBtn').on('click', () => {
            const pageCount = Math.ceil(currentFilteredRows.length / rowsPerPage);
            if (currentPage < pageCount) displayPage(currentPage + 1);
        });

        if (allRows.length > 0) { applyFilters(); } 

        // --- Restore & Purge Logic ---
        
        // Restore
        $('#historyTableBody').on('click', '.restore-btn', function() {
            const row = $(this).closest('tr');
            const logId = row.attr('data-log-id');
            
            showConfirm('Confirm Restore', 'Are you sure you want to restore this item back to the database?', function(confirmed) {
                if(confirmed) {
                    $.ajax({
                        url: 'manage_deletion', // FIXED URL (No .php)
                        type: 'POST',
                        data: { action: 'restore', log_id: logId },
                        dataType: 'json',
                        success: function(response) {
                            if(response.success) {
                                row.fadeOut(400, function() {
                                    row.remove();
                                    // Remove from allRows array
                                    const index = allRows.indexOf(row[0]);
                                    if (index > -1) allRows.splice(index, 1);
                                    applyFilters();
                                });
                                showNotification('success', 'Success', response.message);
                            } else {
                                showNotification('error', 'Error', response.message);
                            }
                        },
                        error: function() { showNotification('error', 'Error', 'A server error occurred.'); }
                    });
                }
            });
        });

        // Purge Modal
        $('#historyTableBody').on('click', '.purge-btn', function() {
            const row = $(this).closest('tr');
            const logId = row.attr('data-log-id');
            $('#purgeLogId').val(logId);
            $('#adminPassword').val(''); 
            $('#passwordConfirmModal').css('display', 'flex');
        });

        $('#cancelPurgeBtn, #passwordConfirmModal .close-button').on('click', function() {
            $('#passwordConfirmModal').css('display', 'none');
        });

        // Purge Submit
        $('#passwordConfirmForm').on('submit', function(e) {
            e.preventDefault();
            const form = $(this);
            const logId = $('#purgeLogId').val();
            const password = $('#adminPassword').val();
            const submitBtn = form.find('.purge-btn');
            
            submitBtn.prop('disabled', true).css('opacity', '0.7');
            
            $.ajax({
                url: 'manage_deletion', // FIXED URL (No .php)
                type: 'POST',
                data: { action: 'purge', log_id: logId, admin_password: password },
                dataType: 'json',
                success: function(response) {
                    if(response.success) {
                        $('#passwordConfirmModal').css('display', 'none');
                        const row = $(`tr[data-log-id="${logId}"]`);
                        row.fadeOut(400, function() {
                            row.remove();
                            const index = allRows.indexOf(row[0]);
                            if (index > -1) allRows.splice(index, 1);
                            applyFilters();
                        });
                        showNotification('success', 'Success', response.message);
                    } else {
                        showNotification('error', 'Error', response.message);
                    }
                },
                error: function() { showNotification('error', 'Error', 'A server error occurred.'); },
                complete: function() { submitBtn.prop('disabled', false).css('opacity', '1'); }
            });
        });

    });
</script>
</body>
</html>