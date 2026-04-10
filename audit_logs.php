<?php
session_start();
require_once 'db_connect.php';

// Authorization check: Only Owner or Managers with specific permissions should see logs ideally, 
// but we will keep it to 'owner' or 'admin' based on your previous structure.
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['role']) || !in_array($_SESSION['role'], ['owner', 'manager'])) {
    header('Location: login.php');
    exit;
}

$logs = [];
// Fetch the latest 500 logs to keep the page fast, allowing JS to paginate them
$sql = "SELECT a.*, u.username, u.avatar, u.role FROM audit_logs a 
        LEFT JOIN users u ON a.admin_id = u.user_id 
        ORDER BY a.created_at DESC LIMIT 500";

if ($result = mysqli_query($link, $sql)) {
    while ($row = mysqli_fetch_assoc($result)) {
        $logs[] = $row;
    }
    mysqli_free_result($result);
}

mysqli_close($link);

// Helper function to color-code and assign icons to different actions
function getActionStyling($action) {
    $action_lower = strtolower($action);
    if (strpos($action_lower, 'delete') !== false || strpos($action_lower, 'purge') !== false || strpos($action_lower, 'remove') !== false || strpos($action_lower, 'decline') !== false) {
        return ['icon' => 'delete', 'bg' => '#fee2e2', 'color' => '#991b1b']; // Red
    } elseif (strpos($action_lower, 'add') !== false || strpos($action_lower, 'create') !== false || strpos($action_lower, 'confirm') !== false) {
        return ['icon' => 'add_circle', 'bg' => '#dcfce7', 'color' => '#166534']; // Green
    } elseif (strpos($action_lower, 'update') !== false || strpos($action_lower, 'edit') !== false || strpos($action_lower, 'change') !== false || strpos($action_lower, 'promote') !== false || strpos($action_lower, 'demote') !== false) {
        return ['icon' => 'edit', 'bg' => '#e0f2fe', 'color' => '#0284c7']; // Blue
    } elseif (strpos($action_lower, 'restore') !== false) {
        return ['icon' => 'restore', 'bg' => '#fef3c7', 'color' => '#92400e']; // Yellow/Orange
    } elseif (strpos($action_lower, 'block') !== false) {
        return ['icon' => 'block', 'bg' => '#fee2e2', 'color' => '#991b1b']; // Red
    } else {
        return ['icon' => 'info', 'bg' => '#f1f5f9', 'color' => '#475569']; // Gray (Default)
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tavern Publico - Activity & Audit Logs</title>
    <link rel="stylesheet" href="CSS/admin.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        /* --- ENHANCED UI STYLING FOR LOGS --- */
        .reservation-page-header {
            display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px; background: #fff; padding: 20px 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); border: 1px solid #eaedf1;
        }
        .search-input {
            width: 350px; max-width: 100%; padding: 12px 20px; border: 1px solid #d1d5db; border-radius: 25px; font-size: 14px; outline: none; transition: border-color 0.3s, box-shadow 0.3s; background-color: #f8f9fa;
        }
        .search-input:focus { border-color: #007bff; box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.15); background-color: #fff; }

        .table-responsive { width: 100%; overflow-x: auto; background: #fff; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); border: 1px solid #eaedf1; }
        table { width: 100%; border-collapse: collapse; min-width: 800px; }
        table th, table td { padding: 18px 20px; text-align: left; border-bottom: 1px solid #f1f5f9; vertical-align: top; }
        table th { background-color: #f8fafc; font-weight: 600; color: #475569; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; }
        table tbody tr { transition: background-color 0.2s; }
        table tbody tr:hover { background-color: #f8fafc; }

        /* Admin Info Styling */
        .admin-info-cell { display: flex; align-items: center; gap: 12px; }
        .admin-avatar-small { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .admin-name { font-weight: 600; color: #1e293b; font-size: 14px; display: block; }
        .admin-role-badge { font-size: 11px; color: #64748b; text-transform: capitalize; background: #f1f5f9; padding: 2px 8px; border-radius: 10px; display: inline-block; margin-top: 4px; }

        /* Action Details Styling */
        .action-cell { display: flex; gap: 15px; align-items: flex-start; }
        .action-icon-wrapper { width: 42px; height: 42px; border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .action-icon-wrapper i { font-size: 22px; }
        .action-text-wrapper { flex-grow: 1; }
        .action-title { font-weight: 700; font-size: 14px; margin-bottom: 4px; display: block; }
        .action-details { color: #475569; font-size: 13px; line-height: 1.5; margin: 0; }

        /* Date Styling */
        .date-cell { color: #475569; }
        .date-main { font-weight: 500; font-size: 14px; color: #1e293b; display: block; }
        .date-time { font-size: 12px; color: #64748b; margin-top: 4px; display: block; }

        /* Pagination */
        .pagination-container { display: flex; justify-content: center; align-items: center; margin-top: 25px; padding: 10px 0; gap: 8px; flex-wrap: wrap; }
        #pageNumbers { display: flex; gap: 6px; flex-wrap: wrap; }
        .page-number { padding: 8px 14px; border: 1px solid #dee2e6; border-radius: 6px; cursor: pointer; transition: all 0.2s; background-color: #fff; color: #495057; font-weight: 500; font-size: 14px; }
        .page-number:hover { background-color: #e9ecef; color: #212529; }
        .page-number.active { background-color: #007bff; color: white; border-color: #007bff; font-weight: 600; }
        .pagination-container .btn { border: 1px solid #dee2e6; background: #fff; padding: 8px 14px; border-radius: 6px; cursor: pointer; color: #495057; font-weight: 500; transition: all 0.2s; }
        .pagination-container .btn:hover:not(:disabled) { background-color: #f8f9fa; }
        .pagination-container .btn:disabled { background-color: #f8f9fa; color: #adb5bd; cursor: not-allowed; }

        .no-items-row { text-align: center; display: none; }

        @media screen and (max-width: 768px) {
            .reservation-page-header { flex-direction: column; align-items: stretch; }
            .search-input { width: 100%; }
            .action-cell { flex-direction: column; gap: 8px; }
        }
    </style>
</head>
<body>

    <div class="page-wrapper">
        
        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'manager'): ?>
            <?php include 'partials/manager_sidebar.php'; ?>
        <?php else: ?>
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
                        <li class="menu-item"><a href="deletion_history"><i class="material-icons">history</i>Archive</a></li>
                    </ul>
                </nav>
            </aside>
        <?php endif; ?>

        <div class="admin-content-area">
            <header class="main-header">
                <div class="header-content">
                    <h1 class="header-page-title">Activity & Audit Logs</h1>
                    
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
                                <a href="audit_logs" class="admin-dropdown-item active" style="background:#f1f5f9; font-weight:600;"><i class="material-icons" style="color:#0ea5e9;">history_edu</i><span style="color:#0ea5e9;">Audit Logs</span></a>
                                <a href="logout" class="admin-dropdown-item"><i class="material-icons">logout</i><span>Log Out</span></a>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <main class="dashboard-main-content">
                <div class="reservation-page-header">
                    <div>
                        <h2 style="margin: 0; font-size: 18px; color: #1e293b;">System Activity Log</h2>
                        <p style="margin: 5px 0 0 0; font-size: 13px; color: #64748b;">Review recent changes, updates, and administrative actions.</p>
                    </div>
                    <input type="text" id="logSearch" class="search-input" placeholder="Search by admin name, action, or details...">
                </div>

                <section class="all-reservations-section">
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th style="width: 25%;">ADMINISTRATOR</th>
                                    <th style="width: 55%;">ACTION & DETAILS</th>
                                    <th style="width: 20%;">DATE & TIME</th>
                                </tr>
                            </thead>
                            <tbody id="logsTableBody">
                                <?php if (empty($logs)): ?>
                                    <tr class="no-items-row" style="display: table-row;">
                                        <td colspan="3" style="text-align: center; color: #94a3b8; padding: 40px;">
                                            <i class="material-icons" style="font-size: 48px; color: #cbd5e1; margin-bottom: 10px;">history_toggle_off</i><br>
                                            No activity recorded yet.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($logs as $log): 
                                        $style = getActionStyling($log['action']);
                                        $avatar = !empty($log['avatar']) && file_exists($log['avatar']) ? $log['avatar'] : 'images/default_avatar.png';
                                    ?>
                                        <tr>
                                            <td>
                                                <div class="admin-info-cell">
                                                    <img src="<?php echo htmlspecialchars($avatar); ?>" alt="Admin Avatar" class="admin-avatar-small">
                                                    <div>
                                                        <span class="admin-name"><?php echo htmlspecialchars($log['username'] ?? 'Unknown User'); ?></span>
                                                        <span class="admin-role-badge"><?php echo htmlspecialchars($log['role'] ?? 'Admin'); ?></span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="action-cell">
                                                    <div class="action-icon-wrapper" style="background-color: <?php echo $style['bg']; ?>; color: <?php echo $style['color']; ?>;">
                                                        <i class="material-icons"><?php echo $style['icon']; ?></i>
                                                    </div>
                                                    <div class="action-text-wrapper">
                                                        <span class="action-title" style="color: <?php echo $style['color']; ?>;">
                                                            <?php echo htmlspecialchars($log['action']); ?>
                                                        </span>
                                                        <p class="action-details"><?php echo htmlspecialchars($log['details']); ?></p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="date-cell">
                                                <span class="date-main"><?php echo date('M d, Y', strtotime($log['created_at'])); ?></span>
                                                <span class="date-time"><?php echo date('h:i A', strtotime($log['created_at'])); ?></span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <tr class="no-items-row" style="display: none;">
                                        <td colspan="3" style="text-align: center; color: #94a3b8; padding: 40px;">No logs match your search.</td>
                                    </tr>
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

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        // --- Admin Header Notifications Dropdown Logic ---
        const messageBtn = document.getElementById('adminMessageBtn');
        const reservationBtn = document.getElementById('adminReservationBtn');
        const messageDropdown = document.getElementById('adminMessageDropdown');
        const reservationDropdown = document.getElementById('adminReservationDropdown');
        const messageCountBadge = document.getElementById('adminMessageCount');
        const reservationCountBadge = document.getElementById('adminReservationCount');
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
            } catch (error) { console.error('Error fetching notifications:', error); }
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
                        }
                    } catch (error) { console.error(error); }
                }
            });
        });

        fetchAdminNotifications();
        setInterval(fetchAdminNotifications, 30000); 

        // --- Pagination and Search Logic ---
        const tableBody = document.getElementById('logsTableBody');
        const allRows = Array.from(tableBody.querySelectorAll('tr:not(.no-items-row)'));
        const rowsPerPage = 12; // Adjusted to fit nicely on the page
        let currentPage = 1;
        let currentFilteredRows = allRows;

        const logSearch = document.getElementById('logSearch');
        const prevPageBtn = document.getElementById('prevPageBtn');
        const nextPageBtn = document.getElementById('nextPageBtn');
        const pageNumbersContainer = document.getElementById('pageNumbers');
        const paginationContainer = document.querySelector('.pagination-container');
        const noItemsRow = tableBody.querySelector('.no-items-row'); 

        function displayPage(page) {
            currentPage = page;
            
            allRows.forEach(row => row.style.display = 'none');
            if (noItemsRow) noItemsRow.style.display = 'none';

            const start = (page - 1) * rowsPerPage;
            const end = start + rowsPerPage;
            const paginatedItems = currentFilteredRows.slice(start, end);

            if (paginatedItems.length > 0) {
                paginatedItems.forEach(row => row.style.display = ''); 
            } else if (noItemsRow) {
                noItemsRow.style.display = 'table-row'; 
            }
            updatePaginationUI();
        }

        function updatePaginationUI() {
            const pageCount = Math.ceil(currentFilteredRows.length / rowsPerPage);
            
            if (pageCount <= 1) {
                paginationContainer.style.display = 'none'; return;
            }
            
            paginationContainer.style.display = 'flex';
            prevPageBtn.disabled = currentPage === 1;
            nextPageBtn.disabled = currentPage === pageCount;

            pageNumbersContainer.innerHTML = '';
            
            // Limit page numbers to display to avoid clutter
            let startPage = Math.max(1, currentPage - 2);
            let endPage = Math.min(pageCount, currentPage + 2);
            
            if (currentPage <= 2) { endPage = Math.min(pageCount, 5); }
            if (currentPage >= pageCount - 1) { startPage = Math.max(1, pageCount - 4); }

            for (let i = startPage; i <= endPage; i++) {
                const pageBtn = document.createElement('button');
                pageBtn.textContent = i;
                pageBtn.className = 'page-number' + (i === currentPage ? ' active' : '');
                pageBtn.addEventListener('click', () => displayPage(i));
                pageNumbersContainer.appendChild(pageBtn);
            }
        }

        function applySearch() {
            const searchTerm = logSearch.value.toLowerCase();

            currentFilteredRows = allRows.filter(row => {
                return row.textContent.toLowerCase().includes(searchTerm);
            });

            displayPage(1);
        }
        
        if (logSearch) logSearch.addEventListener('keyup', applySearch);
        if (prevPageBtn) prevPageBtn.addEventListener('click', () => { if (currentPage > 1) displayPage(currentPage - 1); });
        if (nextPageBtn) nextPageBtn.addEventListener('click', () => {
            const pageCount = Math.ceil(currentFilteredRows.length / rowsPerPage);
            if (currentPage < pageCount) displayPage(currentPage + 1);
        });

        if (allRows.length > 0) { applySearch(); } 
    });
</script>
</body>
</html>