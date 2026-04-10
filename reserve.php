<?php
session_start();
require_once 'db_connect.php';

// If the user is not logged in, redirect them to the homepage.
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: index.php');
    exit;
}

// Fetch logged-in user's details to pre-fill the form
$user_details = null;
$has_previous_reservations = false; // Flag for our new guide!

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    
    // 1. Get user info
    $sql_user = "SELECT username, email FROM users WHERE user_id = ?";
    if ($stmt_user = mysqli_prepare($link, $sql_user)) {
        mysqli_stmt_bind_param($stmt_user, "i", $user_id);
        if (mysqli_stmt_execute($stmt_user)) {
            $result_user = mysqli_stmt_get_result($stmt_user);
            $user_details = mysqli_fetch_assoc($result_user);
        }
        mysqli_stmt_close($stmt_user);
    }
    
    // 2. Check if this is their first time making a reservation
    $sql_check_res = "SELECT 1 FROM reservations WHERE user_id = ? LIMIT 1";
    if ($stmt_check = mysqli_prepare($link, $sql_check_res)) {
        mysqli_stmt_bind_param($stmt_check, "i", $user_id);
        mysqli_stmt_execute($stmt_check);
        mysqli_stmt_store_result($stmt_check);
        if (mysqli_stmt_num_rows($stmt_check) > 0) {
            $has_previous_reservations = true; // They have booked before
        }
        mysqli_stmt_close($stmt_check);
    }
}


// Fetch blocked dates
$blocked_dates = [];
$sql_blocked = "SELECT block_date FROM blocked_dates";
if ($result = mysqli_query($link, $sql_blocked)) {
    while ($row = mysqli_fetch_assoc($result)) {
        $blocked_dates[] = $row['block_date'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tavern Publico - Reservation</title>
    <link rel="stylesheet" href="CSS/main.css">
    <link rel="stylesheet" href="CSS/dark-theme.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/intro.js/7.2.0/introjs.min.css">

    <style>
        /* --- ALL STYLES FOR RESERVE.PHP ARE NOW INLINED HERE --- */
        
        /* 1. Main Layout & Centering */
        .reservation-hero-section {
            position: relative;
            width: 100%;
            min-height: 100vh;
            padding: 40px 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-sizing: border-box;
            background-color: #000;
        }

        .reservation-bg-image {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            opacity: 0.65;
            z-index: 0;
        }

        .reservation-overlay {
            position: relative;
            z-index: 1;
            width: 100%;
        }

        .reservation-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 30px;
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            align-items: stretch;
        }
        
        /* 2. Form Card Styling */
        .reservation-form-card {
            background-color: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(0,0,0,0.1);
            padding: 35px 40px;
            position: relative;
            overflow: hidden;
            border-radius: 15px;
        }
        
        .reservation-form-card::before {
            content: ''; position: absolute; bottom: -20px; left: -20px;
            width: 180px; height: 180px; background-image: url('Tavern.png');
            background-size: contain; background-repeat: no-repeat;
            opacity: 0.04; z-index: 0;
        }
        .reservation-form { position: relative; z-index: 1; }
        
        /* Updated header layout for the Guide Button */
        .form-header-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            position: relative;
            z-index: 1;
        }
        .form-header-row h2 { font-size: 2.2em; color: #2c3e50; margin: 0; text-align: left; }
        .guide-btn {
            background-color: transparent;
            color: #2c3e50;
            border: 1px solid #2c3e50;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .guide-btn:hover { background-color: #2c3e50; color: #fff; }

        .reservation-form .form-group,
        .reservation-form .form-group-inline { margin-bottom: 15px; }
        .reservation-form .form-group-inline { display: flex; gap: 20px; }
        .reservation-form .form-group-inline .form-group { flex: 1; margin-bottom: 0; }
        
        .reservation-form .form-group label { font-weight: 600; color: #555; font-size: 0.9rem; display: block; margin-bottom: 8px; }
        .reservation-form .form-group input,
        .reservation-form .form-group select,
        .reservation-form .form-group textarea {
            width: 100%;
            background-color: #f0f2f5; border: 1px solid #ddd; border-radius: 8px;
            padding: 12px; font-size: 0.95rem; transition: all 0.3s ease; box-sizing: border-box;
        }
        .reservation-form .form-group textarea {
            min-height: 80px;
            font-family: 'Mada', sans-serif;
        }
        .reservation-form .form-group input:read-only { background-color: #e9ecef; cursor: not-allowed; }
        .reservation-form .form-group input:focus,
        .reservation-form .form-group select:focus,
        .reservation-form .form-group textarea:focus {
            background-color: #fff; border-color: #FFD700;
            box-shadow: 0 0 0 3px rgba(255, 215, 0, 0.3); outline: none;
        }

        .confirm-reservation-btn {
            width: 100%; padding: 12px; font-size: 1.1em; margin-top: 10px; background-color: #1a1a1a;
            color: #fff; border-radius: 8px; border: 1px solid #1a1a1a;
            display: flex; align-items: center; justify-content: center; gap: 8px; cursor: pointer;
            transition: all 0.3s ease;
        }
        .confirm-reservation-btn:hover:not(:disabled) { background-color: #FFD700; color: #1a1a1a; border-color: #FFD700; }
        
        .confirm-reservation-btn:disabled {
            background-color: #555;
            border-color: #555;
            color: #aaa;
            cursor: not-allowed;
            opacity: 0.8;
        }
        
        /* 3. "Visit Us" Card Styling */
        .hours-card {
            background-color: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);
            text-align: left; padding: 40px; display: flex;
            flex-direction: column; border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 15px;
            color: #fff;
        }
        .hours-card h3 { text-align: center; color: #FFD700; font-size: 1.8em; margin-bottom: 25px; }
        .hours-card .map-container { height: 180px; margin-bottom: 25px; border-radius: 8px; overflow: hidden; flex-shrink: 0; border: 1px solid rgba(255,255,255,0.2); }
        .hours-card .map-container iframe { width: 100%; height: 100%; border: 0; }
        .hours-card p, .hours-card h4 { color: #eee; line-height: 1.6; }
        .hours-card p { font-size: 1.05em; margin-bottom: 15px; }
        .hours-card p strong { color: #FFD700; }

        /* 4. Notification Modal Styles */
        #notificationModal .modal-content { background-color: #ffffff; padding: 40px; border-radius: 15px; max-width: 500px; box-shadow: 0 8px 30px rgba(0,0,0,0.3); animation: fadeIn 0.4s ease-out; text-align: center; }
        #notificationModal .close-button { position: absolute; top: 15px; right: 20px; color: #aaa; font-size: 2.2em; font-weight: lighter; cursor: pointer; transition: color 0.3s ease; }
        #notificationModal .close-button:hover { color: #555; }
        #notificationModal .modal-header-icon { font-size: 4.5em; margin-bottom: 20px; animation: bounceIn 0.8s ease-out; }
        #notificationModal .modal-header-icon.success { color: #28a745; }
        #notificationModal .modal-header-icon.error { color: #dc3545; }
        #notificationModal h2 { font-size: 2.2em; margin-bottom: 15px; color: #333; font-weight: 700; }
        #notificationModal p { font-size: 1.1em; color: #666; margin-bottom: 35px; line-height: 1.6; }
        #notificationModal .modal-close-btn { background-color: #FFD700; color: #333; border: none; padding: 12px 30px; border-radius: 8px; cursor: pointer; font-size: 1.1em; font-weight: 600; transition: all 0.2s ease; min-width: 120px; }
        #notificationModal .modal-close-btn:hover { background-color: #e6c200; transform: translateY(-2px); }

        @keyframes fadeIn { from { opacity: 0; transform: translateY(-20px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes bounceIn { 0% { transform: scale(0.3); opacity: 0; } 50% { transform: scale(1.1); opacity: 1; } 70% { transform: scale(0.9); } 100% { transform: scale(1); } }

        /* 5. Dark Theme Overrides */
        body.dark-theme .reservation-form-card { background-color: rgba(30, 30, 30, 0.85); border-color: rgba(255, 255, 255, 0.15); }
        body.dark-theme .reservation-form-card::before { filter: invert(1); opacity: 0.03; }
        body.dark-theme .form-header-row h2 { color: #e0e0e0; }
        body.dark-theme .guide-btn { border-color: #FFD700; color: #FFD700; }
        body.dark-theme .guide-btn:hover { background-color: #FFD700; color: #1a1a1a; }
        body.dark-theme .reservation-form .form-group label, body.dark-theme .form-group small { color: #a0a0a0; }
        body.dark-theme .reservation-form .form-group input, 
        body.dark-theme .reservation-form .form-group select,
        body.dark-theme .reservation-form .form-group textarea {
            background-color: #121212; color: #e0e0e0; border-color: #555; 
        }
        body.dark-theme .reservation-form .form-group input[type="date"]::-webkit-calendar-picker-indicator { filter: invert(1); }
        body.dark-theme .confirm-reservation-btn { background-color: #FFD700; color: #1a1a1a; border-color: #FFD700; }
        body.dark-theme .confirm-reservation-btn:hover:not(:disabled) { background-color: #e6c200; }
        body.dark-theme #notificationModal .modal-content { background-color: #2c2c2c; }
        body.dark-theme #notificationModal h2, body.dark-theme #notificationModal p { color: #e0e0e0; }
        body.dark-theme #notificationModal .close-button { color: #a0a0a0; }
        body.dark-theme #notificationModal .close-button:hover { color: #fff; }

        /* --- INTRO.JS (GUIDE) DARK MODE OVERRIDES --- */
        body.dark-theme .introjs-tooltip {
            background-color: #2c2c2c;
            color: #e0e0e0;
            border: 1px solid #444;
            box-shadow: 0 8px 25px rgba(0,0,0,0.6);
        }
        body.dark-theme .introjs-tooltip-title { color: #FFD700; }
        
        body.dark-theme .introjs-arrow.top { border-bottom-color: #2c2c2c; }
        body.dark-theme .introjs-arrow.bottom { border-top-color: #2c2c2c; }
        body.dark-theme .introjs-arrow.left { border-right-color: #2c2c2c; }
        body.dark-theme .introjs-arrow.right { border-left-color: #2c2c2c; }
        
        body.dark-theme .introjs-button {
            background-color: #1a1a1a;
            color: #e0e0e0;
            border: 1px solid #555;
            text-shadow: none;
            background-image: none;
        }
        body.dark-theme .introjs-button:hover,
        body.dark-theme .introjs-button:focus {
            background-color: #FFD700;
            color: #1a1a1a;
            border-color: #FFD700;
        }
        body.dark-theme .introjs-button.introjs-disabled {
            background-color: #222;
            color: #666;
            border-color: #333;
            cursor: not-allowed;
        }
        
        body.dark-theme .introjs-progress { background-color: #444; }
        body.dark-theme .introjs-progressbar { background-color: #FFD700; }

        @media (max-width: 480px) {
            .reservation-container { grid-template-columns: 1fr; }
            .reservation-form-card, .hours-card { padding: 25px; }
            .form-group-inline { flex-direction: column; gap: 15px; }
            .form-header-row { flex-direction: column; align-items: flex-start; gap: 10px; }
        }
    </style>
</head>
<body>

    <?php include 'partials/header.php'; ?>

    <section class="reservation-hero-section">
        <img src="images/1st.jpg" alt="Tavern Publico exterior at night" class="reservation-bg-image">
        <div class="reservation-overlay">
            <div class="reservation-container">
                <div class="reservation-form-card">
                    <div class="form-header-row">
                        <h2>Book Your Table</h2>
                        <button type="button" id="startTourBtn" class="guide-btn"><i class="fas fa-magic"></i> Guide Me</button>
                    </div>
                    <form id="reservationForm" class="reservation-form" action="/process_reservation" method="POST" enctype="multipart/form-data">
                        <div class="form-group-inline">
                            <div class="form-group" id="tour-date">
                                <label for="resDate">Date</label>
                                <input type="date" id="resDate" name="resDate" required>
                            </div>
                            <div class="form-group" id="tour-time">
                                <label for="resTime">Time</label>
                                <select id="resTime" name="resTime" required></select>
                            </div>
                        </div>
                        <div class="form-group-inline">
                            <div class="form-group" id="tour-guests">
                                <label for="numGuests">Number of Guests</label>
                                <input type="number" id="numGuests" name="numGuests" min="1" max="50" placeholder="e.g., 4 (Max 50)" required>
                            </div>
                            <div class="form-group" id="tour-type">
                                <label for="reservationType">Reservation Type</label>
                                <select id="reservationType" name="reservation_type" required>
                                    <option value="Dine-in" selected>Dine-in</option>
                                    <option value="Private Event">Private Event</option>
                                    <option value="Special Occasion">Special Occasion</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="resName">Full Name</label>
                            <input type="text" id="resName" name="resName" placeholder="Enter your full name" required value="<?php echo htmlspecialchars($user_details['username'] ?? ''); ?>">
                        </div>
                        <div class="form-group-inline">
                            <div class="form-group">
                                <label for="resPhone">Phone Number</label>
                                <input type="tel" id="resPhone" name="resPhone" placeholder="e.g., 09123456789" required maxlength="11" oninput="this.value = this.value.replace(/[^0-9]/g, '').substring(0, 11);">
                            </div>
                            <div class="form-group">
                                <label for="resEmail">Email Address</label>
                                <input type="email" id="resEmail" name="resEmail" required value="<?php echo htmlspecialchars($user_details['email'] ?? ''); ?>" readonly>
                            </div>
                        </div>
                        <div class="form-group" id="tour-id">
                            <label for="validId">Upload Photo of Valid ID</label>
                            <input type="file" id="validId" name="valid_id" accept="image/png, image/jpeg, image/jpg" required>
                            <small style="display: block; margin-top: 5px;">For verification purposes. Max size: 10MB.</small>
                            <img id="idPreview" src="#" alt="ID Preview" style="display: none; max-width: 100px; margin-top: 10px; border-radius: 5px;"/>
                        </div>
                        
                        <div class="form-group">
                            <label for="specialRequests">Special Requests (Optional)</label>
                            <textarea id="specialRequests" name="special_requests" rows="3" placeholder="e.g., birthday celebration, dietary restrictions, specific seating..."></textarea>
                        </div>
                        <div id="tour-submit">
                            <button type="submit" id="confirmBtn" class="btn confirm-reservation-btn">
                                <span class="btn-text">Confirm Reservation</span>
                                <i class="fas fa-spinner fa-spin loading-spinner" style="display: none;"></i>
                            </button>
                        </div>
                    </form>
                </div>

                <div class="hours-card">
                    <h3>Visit Us</h3>
                    
                    <div class="map-container">
                        <iframe src="https://maps.google.com/maps?q=269%20Floridablanca%20Road,%20Jose%20Abad%20Santos,%20Guagua,%20Pampanga&t=&z=18&ie=UTF8&iwloc=&output=embed" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                    </div>
                    
                    <p><strong><i class="fas fa-map-marker-alt"></i> Address:</strong><br>269 Floridablanca Road, Jose Abad Santos, Guagua, Pampanga</p>
                    
                    <h4 style="margin-top: 15px;"><i class="fas fa-clock"></i> Hours of Operation</h4>
                    <p><strong>Monday - Saturday</strong><br>11:00 AM - 9:00 PM</p>
                    <p><strong>Sunday</strong><br>12:00 PM - 9:00 PM</p>
                </div>
            </div>
        </div>
    </section>
    
    <div id="notificationModal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close-button">×</span>
            <div id="modalHeaderIcon" class="modal-header-icon"></div>
            <h2 id="modalTitle"></h2>
            <p id="modalMessage"></p>
            <button class="btn modal-close-btn">OK</button>
        </div>
    </div>

    <?php include 'partials/footer.php'; ?>
    <?php include 'partials/Signin-Signup.php'; ?>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/intro.js/7.2.0/intro.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Check PHP variable to see if this is their first ever reservation
            const isFirstTime = <?php echo $has_previous_reservations ? 'false' : 'true'; ?>;
            
            function startReservationTour() {
                const intro = introJs();
                intro.setOptions({
                    steps: [
                        {
                            title: 'Reservation Guide 🍽️',
                            intro: 'Welcome to the booking page! Let me quickly show you how to secure your table.'
                        },
                        {
                            element: document.querySelector('#tour-date'),
                            title: 'Step 1: Choose a Date',
                            intro: 'Select the day you wish to visit. Dates that are fully booked will not be available.',
                            position: 'bottom'
                        },
                        {
                            element: document.querySelector('#tour-time'),
                            title: 'Step 2: Pick a Time',
                            intro: 'Choose your arrival time from the available slots generated here.',
                            position: 'bottom'
                        },
                        {
                            element: document.querySelector('#tour-guests'),
                            title: 'Step 3: Headcount',
                            intro: 'Enter the number of guests. We can accommodate up to 50 people.',
                            position: 'bottom'
                        },
                        {
                            element: document.querySelector('#tour-type'),
                            title: 'Step 4: Event Type',
                            intro: 'Let us know if this is a standard Dine-in, a Private Event, or a Special Occasion.',
                            position: 'bottom'
                        },
                        {
                            element: document.querySelector('#tour-id'),
                            title: 'Step 5: Identity Check',
                            intro: 'Please upload a photo of a Valid ID to verify and secure your booking.',
                            position: 'top'
                        },
                        {
                            element: document.querySelector('#tour-submit'),
                            title: 'Step 6: Confirm',
                            intro: 'Double-check your details and click here! We will notify you once an admin approves it.',
                            position: 'top'
                        }
                    ],
                    showProgress: true,
                    showBullets: false,
                    overlayOpacity: 0.7,
                    exitOnOverlayClick: true
                });
                intro.start();
            }

            // Auto-start if it's their first time!
            if (isFirstTime) {
                setTimeout(startReservationTour, 600);
            }

            // Trigger manually from the 'Guide Me' button
            document.getElementById('startTourBtn').addEventListener('click', startReservationTour);
        });
    </script>

    <script src="JS/main.js"></script>
    <script src="JS/theme-switcher.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const resDateInput = document.getElementById('resDate');
            const resTimeSelect = document.getElementById('resTime');
            const blockedDates = <?php echo json_encode($blocked_dates); ?>;
            const validIdInput = document.getElementById('validId');
            const idPreview = document.getElementById('idPreview');
            const reservationForm = document.getElementById('reservationForm');
            const confirmBtn = document.getElementById('confirmBtn');
            
            // Add Loading State on Form Submission
            if (reservationForm) {
                reservationForm.addEventListener('submit', function(e) {
                    if (confirmBtn) {
                        const btnText = confirmBtn.querySelector('.btn-text');
                        const spinner = confirmBtn.querySelector('.loading-spinner');
                        
                        // Disable the button to prevent double-clicks
                        confirmBtn.disabled = true;
                        
                        // Update text and show spinner
                        if (btnText) btnText.textContent = 'Processing...';
                        if (spinner) spinner.style.display = 'inline-block';
                    }
                });
            }
            
            const operatingHours = {
                0: { start: 12, end: 21 }, // Sun
                1: { start: 11, end: 21 }, // Mon-Sat
                2: { start: 11, end: 21 }, 3: { start: 11, end: 21 },
                4: { start: 11, end: 21 }, 5: { start: 11, end: 21 },
                6: { start: 11, end: 21 }
            };

            function populateTimeSlots(selectedDate) {
                resTimeSelect.innerHTML = ''; 

                if (!selectedDate) {
                    const option = document.createElement('option');
                    option.value = "";
                    option.textContent = "Please select a date";
                    option.disabled = true;
                    resTimeSelect.appendChild(option);
                    return;
                }
                
                const date = new Date(selectedDate + 'T00:00:00');
                const dayOfWeek = date.getDay();
                const now = new Date();
                const isToday = date.toDateString() === now.toDateString();
                const hours = operatingHours[dayOfWeek];
                if (!hours) return;
                let startHour = isToday ? Math.max(hours.start, now.getHours() + 1) : hours.start;

                for (let i = startHour; i < hours.end; i++) {
                    const option = document.createElement('option');
                    const time24 = `${i.toString().padStart(2, '0')}:00`;
                    const time12 = new Date(`1970-01-01T${time24}:00`).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
                    option.value = time24;
                    option.textContent = time12;
                    resTimeSelect.appendChild(option);
                }
                 if(resTimeSelect.innerHTML === '') {
                    const option = document.createElement('option');
                    option.value = "";
                    option.textContent = "No slots available";
                    option.disabled = true;
                    resTimeSelect.appendChild(option);
                }
            }
            
            if (validIdInput) {
                validIdInput.addEventListener('change', function() {
                    const file = this.files[0];
                    if (file) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            idPreview.src = e.target.result;
                            idPreview.style.display = 'block';
                        }
                        reader.readAsDataURL(file);
                    } else {
                        idPreview.style.display = 'none';
                    }
                });
            }

            const notificationModal = document.getElementById('notificationModal');
            const modalHeaderIcon = document.getElementById('modalHeaderIcon');
            const modalTitle = document.getElementById('modalTitle');
            const modalMessage = document.getElementById('modalMessage');
            
            const closeModal = () => {
                if(notificationModal) notificationModal.style.display = 'none';
            };
            
            if (notificationModal) {
                const closeButton = notificationModal.querySelector('.close-button');
                const okButton = notificationModal.querySelector('.modal-close-btn');

                if(closeButton) closeButton.addEventListener('click', closeModal);
                if(okButton) okButton.addEventListener('click', closeModal);
                window.addEventListener('click', (event) => {
                    if (event.target == notificationModal) closeModal();
                });
            }

            function showModal(type, title, message) {
                if (!notificationModal || !modalHeaderIcon || !modalTitle || !modalMessage) {
                    console.warn('Notification modal elements not found. Using simple alert.');
                    alert(title + "\n" + message);
                    return;
                }
                modalHeaderIcon.innerHTML = type === 'success' ? '<i class="fas fa-check-circle"></i>' : '<i class="fas fa-times-circle"></i>';
                modalHeaderIcon.className = 'modal-header-icon ' + type;
                modalTitle.textContent = title;
                modalMessage.textContent = message;
                notificationModal.style.display = 'flex';
            }

            if (resDateInput) {
                const today = new Date();
                const todayString = today.toISOString().split('T')[0];
                resDateInput.min = todayString;
                resDateInput.value = todayString;

                resDateInput.addEventListener('change', function() {
                    if (blockedDates.includes(this.value)) {
                        showModal('error', 'Date Not Available', 'The selected date is fully booked or unavailable. Please choose a different date.');
                        this.value = '';
                        populateTimeSlots(''); 
                    } else {
                        populateTimeSlots(this.value); 
                    }
                });
                populateTimeSlots(todayString); 
            }
            
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('status')) {
                const status = urlParams.get('status');
                const message = urlParams.get('message') || 'An unknown error occurred.';
                if (status === 'success') {
                    showModal('success', 'Reservation Submitted!', 'Your request has been received. Please wait for the administrator to confirm your booking.');
                } else if (status === 'error') {
                    showModal('error', 'Reservation Failed', message);
                }
                history.replaceState(null, '', window.location.pathname);
            }

        });
    </script>
</body>
</html>