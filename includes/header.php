<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' | UCC IMS' : 'UCC Inventory Management System'; ?></title>

    <!-- Pusher for real-time notifications -->
    <script src="https://js.pusher.com/8.2/pusher.min.js"></script>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome 6 -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- DataTables -->
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

        <!-- jQuery first, then Bootstrap, then DataTables -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    
    <!-- Chart.js for analytics -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- SweetAlert2 for better alerts -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- Moment.js for date handling -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js"></script>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="../assets/UCC_Logo.ico">
    <link rel="shortcut icon" type="image/x-icon" href="../assets/UCC_Logo.ico">
    
    <!-- Custom CSS -->
    <?php if ($_SESSION['role'] == 'admin'): ?>
        <link href="../css/admin.css" rel="stylesheet">
    <?php else: ?>
        <link href="../css/user.css" rel="stylesheet">
    <?php endif; ?>
    
    <style>
        :root {
            --ucc-green-primary: #2E7D32;
            --ucc-green-secondary: #4CAF50;
            --ucc-green-light: #81C784;
            --ucc-green-soft: #E8F5E9;
            --ucc-green-mint: #C8E6C9;
            --ucc-green-dark: #1B5E20;
            --ucc-white: #FFFFFF;
            --ucc-off-white: #F8F9FA;
            --ucc-gray-light: #F1F8E9;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--ucc-off-white);
            overflow-x: hidden;
        }

        /* Sidebar Styles - Green Theme */
        .admin-sidebar {
            background: linear-gradient(180deg, var(--ucc-green-primary) 0%, var(--ucc-green-dark) 100%);
            min-height: 100vh;
            box-shadow: 4px 0 20px rgba(46, 125, 50, 0.2);
            position: fixed;
            width: 260px;
            z-index: 1000;
            transition: all 0.3s ease;
            overflow-x: hidden;
            white-space: nowrap;
        }

        .admin-sidebar.collapsed {
            width: 80px;
        }

        /* Hide text when collapsed - This is the key fix */
        .admin-sidebar.collapsed .logo-text,
        .admin-sidebar.collapsed .user-details,
        .admin-sidebar.collapsed .nav-link span,
        .admin-sidebar.collapsed .category-badge,
        .admin-sidebar.collapsed .nav-divider {
            display: none;
        }

        /* Center icons when collapsed */
        .admin-sidebar.collapsed .logo-wrapper {
            justify-content: center;
            padding: 0;
        }

        .admin-sidebar.collapsed .user-info {
            justify-content: center;
            padding: 0.5rem;
        }

        .admin-sidebar.collapsed .nav-link {
            justify-content: center;
            padding: 0.8rem 0;
        }

        .admin-sidebar.collapsed .nav-link i {
            margin: 0;
            font-size: 1.3rem;
        }

        /* Keep icons visible and centered */
        .admin-sidebar.collapsed .logo-icon {
            margin: 0 auto;
        }

        .admin-sidebar.collapsed .user-avatar {
            margin: 0 auto;
        }

        .sidebar-header {
            padding: 1.5rem 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.15);
            margin-bottom: 1rem;
        }

        .logo-wrapper {
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: all 0.3s ease;
        }

        .logo-icon {
            width: 45px;
            height: 45px;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .logo-icon img {
            width: 35px;
            height: 35px;
            object-fit: contain;
        }

        .logo-text {
            transition: opacity 0.3s ease;
        }

        .logo-text h5 {
            color: white;
            font-weight: 700;
            font-size: 1rem;
            margin: 0;
            line-height: 1.3;
            white-space: nowrap;
        }

        .logo-text small {
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.7rem;
            display: block;
        }

        .user-info {
            padding: 1rem;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            margin: 1rem;
            display: flex;
            align-items: center;
            gap: 0.8rem;
            border: 1px solid rgba(255, 255, 255, 0.15);
            transition: all 0.3s ease;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--ucc-green-light) 0%, var(--ucc-white) 100%);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--ucc-green-dark);
            font-weight: 700;
            font-size: 1.2rem;
            flex-shrink: 0;
        }

        .user-details {
            overflow: hidden;
            transition: opacity 0.3s ease;
        }

        .user-name {
            color: white;
            font-weight: 600;
            font-size: 0.9rem;
            margin: 0;
        }

        .user-role {
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .user-role i {
            color: var(--ucc-green-light) !important;
        }

        /* Navigation Menu */
        .nav-menu {
            padding: 0.5rem;
            list-style: none;
        }

        .nav-item {
            margin-bottom: 0.3rem;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.8rem 1rem;
            color: rgba(255, 255, 255, 0.8);
            border-radius: 12px;
            transition: all 0.3s ease;
            text-decoration: none;
            white-space: nowrap;
        }

        .nav-link:hover {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            transform: translateX(5px);
        }

        /* Adjust hover for collapsed state */
        .admin-sidebar.collapsed .nav-link:hover {
            transform: scale(1.1);
        }

        .nav-link.active {
            background: linear-gradient(135deg, var(--ucc-green-light) 0%, var(--ucc-white) 100%);
            color: var(--ucc-green-dark);
            box-shadow: 0 4px 15px rgba(76, 175, 80, 0.3);
        }

        .nav-link.active i {
            color: var(--ucc-green-dark);
        }

        .nav-link i {
            width: 20px;
            font-size: 1.1rem;
            text-align: center;
            transition: all 0.3s ease;
        }

        .nav-link span {
            font-size: 0.9rem;
            font-weight: 500;
            transition: opacity 0.3s ease;
        }

        .nav-divider {
            height: 1px;
            background: rgba(255, 255, 255, 0.15);
            margin: 1rem 0;
            transition: opacity 0.3s ease;
        }

        /* Main Content Area */
        .admin-main-content {
            margin-left: 260px;
            padding: 2rem;
            transition: all 0.3s ease;
            min-height: 100vh;
            background: var(--ucc-off-white);
        }

        .admin-main-content.expanded {
            margin-left: 80px;
        }

        /* Top Bar */
        .top-bar {
            background: white;
            border-radius: 16px;
            padding: 1rem 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 20px rgba(46, 125, 50, 0.08);
            display: flex;
            align-items: center;
            justify-content: space-between;
            border: 1px solid var(--ucc-green-mint);
        }

        .page-title {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .page-title h1 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--ucc-green-dark);
            margin: 0;
        }

        .page-title .badge {
            background: linear-gradient(135deg, var(--ucc-green-primary) 0%, var(--ucc-green-secondary) 100%);
            color: white;
            padding: 0.3rem 1rem;
            border-radius: 50px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        /* In header.php - Update the top-bar-actions container */
        .top-bar-actions {
            display: flex;
            align-items: center;
            gap: 0.8rem; /* Space between buttons */
        }

        /* Keep your existing .action-btn styles */
        .top-bar-actions .action-btn {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: var(--ucc-green-soft);
            border: 1px solid var(--ucc-green-mint);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--ucc-green-primary);
            transition: all 0.3s ease;
            cursor: pointer;
            text-decoration: none;
        }

        .top-bar-actions .action-btn:hover {
            background: var(--ucc-green-primary);
            border-color: var(--ucc-green-primary);
            color: white;
            transform: translateY(-2px);
        }

        /* Ensure links (settings) behave like buttons */
        .top-bar-actions a.action-btn {
            color: var(--ucc-green-primary);
        }

        .top-bar-actions a.action-btn:hover {
            color: white;
        }

        /* If there's any flex direction issue, force row */
        .top-bar-actions {
            flex-direction: row !important;
        }

        /* Toggle Sidebar Button */
        .sidebar-toggle {
            position: fixed;
            bottom: 2rem;
            left: 210px;
            width: 40px;
            height: 40px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 15px rgba(46, 125, 50, 0.2);
            cursor: pointer;
            transition: all 0.3s ease;
            z-index: 1001;
            border: 2px solid var(--ucc-green-primary);
        }

        .sidebar-toggle.collapsed {
            left: 30px;
        }

        .sidebar-toggle i {
            color: var(--ucc-green-primary);
            transition: transform 0.3s ease;
        }

        .sidebar-toggle.collapsed i {
            transform: rotate(180deg);
        }

        /* Tooltip for collapsed state */
        .admin-sidebar.collapsed .nav-link {
            position: relative;
        }

        .admin-sidebar.collapsed .nav-link:hover::after {
            content: attr(title);
            position: absolute;
            left: 100%;
            top: 50%;
            transform: translateY(-50%);
            background: var(--ucc-green-dark);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-size: 0.85rem;
            white-space: nowrap;
            margin-left: 10px;
            z-index: 1002;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            border: 1px solid var(--ucc-green-light);
        }

        /* Modal animations */
        .modal.fade .modal-dialog {
            transform: scale(0.8);
            transition: transform 0.3s ease-in-out;
        }

        .modal.show .modal-dialog {
            transform: scale(1);
        }

        /* Custom modal styling */
        #logoutModal .modal-content {
            border: none;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }

        #logoutModal .modal-header {
            border-bottom: none;
            padding: 1.5rem;
        }

        #logoutModal .modal-footer {
            border-top: none;
            padding: 1.5rem;
        }

        #logoutModal .btn {
            padding: 0.6rem 1.5rem;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        #logoutModal .btn-light {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
        }

        #logoutModal .btn-light:hover {
            background: #e9ecef;
            transform: translateY(-2px);
        }

        #logoutModal .btn-danger {
            background: linear-gradient(135deg, #DC3545 0%, #B02A37 100%);
            border: none;
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);
        }

        #logoutModal .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(220, 53, 69, 0.4);
        }

        /* Session timeout modal */
        #sessionTimeoutModal .modal-content {
            border-radius: 20px;
            border: 2px solid #FFC107;
        }

        #sessionTimeoutModal .modal-header {
            border-bottom: 1px solid #FFC107;
        }

        #timeoutCountdown {
            font-size: 1.5rem;
            font-weight: 700;
            color: #DC3545;
            padding: 0.2rem 0.5rem;
            background: #f8f9fa;
            border-radius: 8px;
            margin: 0 0.2rem;
        }

        /* Toast styling */
        .toast {
            border-radius: 12px;
            margin-bottom: 0.5rem;
            animation: slideInRight 0.3s ease;
        }

        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        /* Add keyboard shortcut hint */
        .logout-hint {
            position: fixed;
            bottom: 10px;
            right: 10px;
            background: rgba(0,0,0,0.7);
            color: white;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.7rem;
            z-index: 999;
            opacity: 0.5;
            transition: opacity 0.3s ease;
        }

        .logout-hint:hover {
            opacity: 1;
        }

        .logout-hint i {
            margin-right: 0.3rem;
            color: #FFC107;
        }

        /* Notification Dropdown Styles */
        .notification-dropdown {
            border: none;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
            padding: 0;
            overflow: hidden;
            animation: slideDown 0.3s ease;
        }

        .notification-list {
            max-height: 350px;
            overflow-y: auto;
        }

        .notification-item {
            padding: 12px 16px;
            border-bottom: 1px solid var(--ucc-green-mint);
            transition: all 0.3s ease;
            cursor: pointer;
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }

        .notification-item:hover {
            background: var(--ucc-green-soft);
        }

        .notification-item.unread {
            background: #F0F9FF;
            border-left: 3px solid var(--ucc-green-primary);
        }

        .notification-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .notification-icon.new-request { background: #E8F5E9; color: var(--ucc-green-primary); }
        .notification-icon.request-approved { background: #D1FAE5; color: #10B981; }
        .notification-icon.request-rejected { background: #FEE2E2; color: #DC2626; }

        .notification-content {
            flex: 1;
        }

        .notification-title {
            font-weight: 600;
            font-size: 0.9rem;
            color: #1F2937;
            margin-bottom: 3px;
        }

        .notification-message {
            font-size: 0.8rem;
            color: #6B7280;
            margin-bottom: 3px;
            line-height: 1.3;
        }

        .notification-time {
            font-size: 0.65rem;
            color: #9CA3AF;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .notification-time i {
            font-size: 0.5rem;
        }

        .notification-badge {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--ucc-green-primary);
            display: inline-block;
            margin-left: 5px;
        }

        /* Empty state */
        .notification-empty {
            text-align: center;
            padding: 30px 20px;
            color: #9CA3AF;
        }

        .notification-empty i {
            font-size: 3rem;
            margin-bottom: 10px;
            color: #E5E7EB;
        }

        .notification-empty p {
            font-size: 0.9rem;
            margin-bottom: 0;
        }

        /* Fix for Bootstrap dropdowns */
        .dropdown-toggle::after {
            display: none !important; /* Removes the default dropdown arrow */
        }

        /* CRITICAL FIX for dropdown */
        .dropdown-menu {
            display: none !important;
        }
        .dropdown-menu.show {
            display: block !important;
            position: absolute !important;
            transform: translate3d(-280px, 45px, 0px) !important;
            top: 0px !important;
            left: 0px !important;
            will-change: transform;
            z-index: 1050 !important;
        }

        .notification-dropdown {
            display: none; /* Hidden by default, shown by Bootstrap */
        }

        .notification-dropdown.show {
            display: block !important; /* Force show when Bootstrap adds the show class */
        }

        /* Ensure the dropdown appears */
        #notificationDropdown .dropdown-menu {
            position: absolute !important;
            inset: 0px auto auto 0px !important;
            transform: translate(-280px, 45px) !important; /* Adjust position */
        }

        @media (max-width: 768px) {
            #notificationDropdown .dropdown-menu {
                transform: translate(-250px, 45px) !important;
            }
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .admin-sidebar {
                transform: translateX(-100%);
                position: fixed;
                z-index: 1050;
            }

            .admin-sidebar.show {
                transform: translateX(0);
            }

            .admin-main-content {
                margin-left: 0;
                padding: 1rem;
            }

            .sidebar-toggle {
                left: 1rem;
            }

            .mobile-overlay {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(46, 125, 50, 0.5);
                z-index: 1040;
                display: none;
            }

            .mobile-overlay.show {
                display: block;
            }
        }

        /* Animations */
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .animate-slide-in {
            animation: slideIn 0.3s ease forwards;
        }

        /* Scrollbar Styling - Green Theme */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: var(--ucc-green-soft);
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--ucc-green-primary);
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--ucc-green-dark);
        }
    </style>
</head>
<body>
    <!-- Mobile Overlay -->
    <div class="mobile-overlay" id="mobileOverlay"></div>

    <!-- Sidebar Toggle Button -->
    <div class="sidebar-toggle" id="sidebarToggle">
        <i class="fas fa-chevron-left"></i>
    </div>

    <!-- Sidebar -->
    <nav class="admin-sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="logo-wrapper">
                <div class="logo-icon">
                    <img src="../assets/UCC_Logo.png" alt="UCC Logo">
                </div>
                <div class="logo-text">
                    <h5>UCC Inventory<br>Management System</h5>
                    <small>v2.0</small>
                </div>
            </div>
        </div>

        <!-- User Info -->
        <div class="user-info">
            <div class="user-avatar">
                <?php echo strtoupper(substr($_SESSION['full_name'], 0, 1)); ?>
            </div>
            <div class="user-details" style="overflow: hidden; min-width: 0; flex: 1;">
                <p class="user-name" style="white-space: normal; word-wrap: break-word; line-height: 1.3; margin-bottom: 2px;">
                    <?php echo htmlspecialchars($_SESSION['full_name']); ?>
                </p>
                <div style="display: flex; flex-direction: column; gap: 2px;">
                    <span class="user-role" style="display: inline-flex; align-items: center; gap: 4px; white-space: nowrap;">
                        <i class="fas fa-circle" style="font-size: 0.4rem; color: var(--ucc-green-light);"></i>
                        <?php echo ucfirst($_SESSION['role']); ?>
                    </span>
                    <?php if (!empty($_SESSION['campus'])): ?>
                    <span class="user-campus" style="display: inline-flex; align-items: center; gap: 4px; font-size: 0.7rem; color: rgba(255,255,255,0.9);"
                        title="<?php echo htmlspecialchars($_SESSION['campus']); ?>">
                        <i class="fas fa-map-marker-alt" style="font-size: 0.6rem; color: var(--ucc-green-light);"></i>
                        <?php echo htmlspecialchars($_SESSION['campus']); ?>
                    </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Navigation Menu -->
        <ul class="nav-menu">
            <?php if ($_SESSION['role'] == 'admin'): ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php" title="Dashboard">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link <?php echo in_array(basename($_SERVER['PHP_SELF']), ['inventory_categories.php', 'inventory_rooms.php']) ? 'active' : ''; ?>" href="inventory_categories.php" title="Inventory">
                        <i class="fas fa-warehouse"></i>
                        <span>Inventory</span>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'all_equipment.php' ? 'active' : ''; ?>" href="all_equipment.php" title="All Equipment">
                        <i class="fas fa-list-alt"></i>
                        <span>All Equipment</span>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'locations.php' ? 'active' : ''; ?>" href="locations.php" title="Locations">
                        <i class="fas fa-map-marker-alt"></i>
                        <span>Locations</span>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'location_types.php' ? 'active' : ''; ?>" href="location_types.php" title="Categories">
                        <i class="fas fa-tags"></i>
                        <span>Categories</span>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'condemned.php' ? 'active' : ''; ?>" href="condemned.php" title="Condemned">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span>Condemned</span>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'assignment_history.php' ? 'active' : ''; ?>" href="assignment_history.php" title="History">
                        <i class="fas fa-history"></i>
                        <span>History</span>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'consumables.php' ? 'active' : ''; ?>" href="consumables.php" title="Consumables">
                        <i class="fas fa-tint"></i>
                        <span>Consumables</span>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>" href="users.php" title="Users">
                        <i class="fas fa-users"></i>
                        <span>Users</span>
                    </a>
                </li>
                
                <div class="nav-divider"></div>
                
            <?php else: ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php" title="Dashboard">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'my_assignments.php' ? 'active' : ''; ?>" href="my_assignments.php" title="My Assignments">
                        <i class="fas fa-clipboard-list"></i>
                        <span>My Assignments</span>
                    </a>
                </li>
            <?php endif; ?>
            
            <li class="nav-item mt-auto">
                <a class="nav-link text-danger" href="../auth/logout.php" title="Logout">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </li>
        </ul>
    </nav>

    <!-- Main Content -->
    <main class="admin-main-content" id="mainContent">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="page-title">
                <i class="fas fa-bars d-md-none" id="mobileMenuToggle" style="cursor: pointer; font-size: 1.5rem; color: var(--ucc-green-primary);"></i>
                <h1><?php echo isset($page_title) ? $page_title : 'Dashboard'; ?></h1>
                <?php if (isset($page_title)): ?>
                    <span class="badge"><?php echo ucfirst($_SESSION['role']); ?></span>
                <?php endif; ?>
            </div>
            
            <div class="top-bar-actions">
                <!-- Notification Dropdown - COMPLETELY FIXED VERSION -->
                <div class="dropdown" id="notificationDropdown">
                    <button class="action-btn position-relative dropdown-toggle" type="button" id="notificationBell" 
                            data-bs-toggle="dropdown" aria-expanded="false" title="Notifications" 
                            style="border: none; background: var(--ucc-green-soft);">
                        <i class="far fa-bell"></i>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" 
                            id="notificationBadge" style="font-size: 0.6rem; display: none;">0</span>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end p-0" aria-labelledby="notificationBell" 
                        style="width: 350px; max-height: 450px; overflow-y: auto; border-radius: 16px; margin-top: 10px; border: 1px solid var(--ucc-green-mint); box-shadow: 0 10px 40px rgba(0,0,0,0.15);">
                        <div class="dropdown-header d-flex justify-content-between align-items-center py-3 px-3" style="background: var(--ucc-green-soft); border-bottom: 1px solid var(--ucc-green-mint);">
                            <h6 class="mb-0 fw-bold" style="color: var(--ucc-green-dark);">Notifications</h6>
                            <div>
                                <button class="btn btn-sm btn-link text-success p-0 me-2" id="markAllRead" title="Mark all as read" style="text-decoration: none;">
                                    <i class="fas fa-check-double"></i>
                                </button>
                                <span class="small text-muted" id="notificationTime">just now</span>
                            </div>
                        </div>
                        <div id="notificationList" class="notification-list" style="max-height: 350px; overflow-y: auto; background: white;">
                            <!-- Notifications will be loaded here via AJAX -->
                            <div class="text-center py-4 text-muted">
                                <div class="spinner-border spinner-border-sm text-success me-2"></div>
                                Loading notifications...
                            </div>
                        </div>
                        <div class="dropdown-footer text-center py-2" style="border-top: 1px solid var(--ucc-green-mint); background: white;">
                            <a href="../admin/consumables.php" class="text-decoration-none small" style="color: var(--ucc-green-primary);">
                                <i class="fas fa-history me-1"></i>View All Requests
                            </a>
                        </div>
                    </div>
                </div>
                
                <a href="settings.php" class="action-btn" title="Settings">
                    <i class="fas fa-cog"></i>
                </a>
                <div class="action-btn" title="Fullscreen" onclick="toggleFullscreen()">
                    <i class="fas fa-expand"></i>
                </div>
            </div>
        </div>

        <!-- Content Area -->
        <div class="content-area">

<!-- Logout Confirmation Modal -->
<div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="logoutModalLabel">
                    <i class="fas fa-sign-out-alt me-2"></i>
                    Confirm Logout
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center py-4">
                <div class="mb-4">
                    <div class="text-danger mb-3">
                        <i class="fas fa-question-circle fa-4x"></i>
                    </div>
                    <h5 class="mb-3">Are you sure you want to logout?</h5>
                    <p class="text-muted mb-0">
                        You will be redirected to the login page and your session will be ended.
                    </p>
                </div>
                
                <div class="alert alert-info d-flex align-items-center" role="alert">
                    <i class="fas fa-info-circle me-2 fs-5"></i>
                    <div class="small">
                        <strong>Current Session:</strong><br>
                        Logged in as <strong><?php echo htmlspecialchars($_SESSION['full_name']); ?></strong> 
                        (<?php echo ucfirst($_SESSION['role']); ?>)
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Cancel
                </button>
                <a href="../auth/logout.php" class="btn btn-danger px-4">
                    <i class="fas fa-sign-out-alt me-2"></i>Yes, Logout
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Session Timeout Warning Modal (Optional) -->
<div class="modal fade" id="sessionTimeoutModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title text-dark">
                    <i class="fas fa-clock me-2"></i>
                    Session Timeout Warning
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center py-4">
                <div class="mb-3">
                    <i class="fas fa-hourglass-half fa-4x text-warning"></i>
                </div>
                <h5 class="mb-3">Your session is about to expire!</h5>
                <p class="text-muted mb-0">
                    You will be logged out in <span id="timeoutCountdown">60</span> seconds due to inactivity.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-warning" onclick="extendSession()">
                    <i class="fas fa-clock me-2"></i>Extend Session
                </button>
                <a href="../auth/logout.php" class="btn btn-danger">
                    <i class="fas fa-sign-out-alt me-2"></i>Logout Now
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Add keyboard shortcut hint (optional) -->
<div class="logout-hint">
    <i class="fas fa-keyboard"></i>
    Press <kbd>Ctrl</kbd> + <kbd>Q</kbd> to logout
</div>

<script>
// Real-time Notification System with Pusher
let notificationSound = null;
let lastNotificationCount = 0;

// Initialize Pusher
function initPusher() {
    // Enable Pusher logging for debugging
    Pusher.logToConsole = false;
    
    // Initialize Pusher with your credentials
    const pusher = new Pusher('196af0f1dd479f60be9c', { // Replace with your key
        cluster: 'ap1', // Replace with your cluster
        forceTLS: true
    });
    
    // Subscribe to user-specific channel
    const userId = <?php echo $_SESSION['user_id']; ?>;
    const userChannel = pusher.subscribe('user-' + userId);
    
    // Listen for new notifications
    userChannel.bind('new-notification', function(data) {
        console.log('🔔 Real-time notification received:', data);
        
        // Play notification sound
        playNotificationSound();
        
        // Show browser notification
        showBrowserNotification(data);
        
        // Add notification to dropdown
        addRealtimeNotification(data);
        
        // Update badge count
        updateNotificationBadge(parseInt($('#notificationBadge').text() || '0') + 1);
        
        // Show toast notification
        showToast(data.title, data.message, data.link);
    });
    
    // Subscribe to admin broadcast channel
    if (<?php echo $_SESSION['role'] == 'admin' ? 'true' : 'false'; ?>) {
        const adminChannel = pusher.subscribe('admin-notifications');
        adminChannel.bind('new-notification', function(data) {
            console.log('🔔 Admin broadcast received:', data);
            
            // Play notification sound
            playNotificationSound();
            
            // Show browser notification
            showBrowserNotification(data);
            
            // Add notification to dropdown
            addRealtimeNotification(data);
            
            // Update badge count
            updateNotificationBadge(parseInt($('#notificationBadge').text() || '0') + 1);
            
            // Show toast notification
            showToast(data.title, data.message, data.link);
        });
    }
    
    // Check online status (optional)
    const presenceChannel = pusher.subscribe('presence-notifications');
    presenceChannel.bind('user-update', function(data) {
        console.log('User status update:', data);
    });
}

// Play notification sound
function playNotificationSound() {
    try {
        // Create a simple beep sound using Web Audio API
        const audioContext = new (window.AudioContext || window.webkitAudioContext)();
        const oscillator = audioContext.createOscillator();
        const gainNode = audioContext.createGain();
        
        oscillator.connect(gainNode);
        gainNode.connect(audioContext.destination);
        
        oscillator.frequency.setValueAtTime(440, audioContext.currentTime); // 440 Hz = A4 note
        gainNode.gain.setValueAtTime(0.1, audioContext.currentTime);
        
        oscillator.start();
        oscillator.stop(audioContext.currentTime + 0.1); // 100ms beep
        
        // Clean up
        setTimeout(() => {
            audioContext.close();
        }, 200);
    } catch (e) {
        console.log('Audio not supported:', e);
    }
}

// Show browser notification
function showBrowserNotification(data) {
    if (Notification.permission === 'granted') {
        const notification = new Notification(data.title || 'New Notification', {
            body: data.message,
            icon: '../assets/UCC_Logo.png',
            badge: '../assets/UCC_Logo.png',
            tag: 'notification-' + Date.now(),
            renotify: true
        });
        
        notification.onclick = function() {
            window.focus();
            if (data.link) {
                window.location.href = data.link;
            }
            this.close();
        };
        
        // Auto close after 5 seconds
        setTimeout(() => notification.close(), 5000);
    }
}

// Add notification to dropdown in real-time
function addRealtimeNotification(data) {
    const list = $('#notificationList');
    const timeAgo = 'just now';
    
    // Remove empty state if present
    if (list.find('.notification-empty').length) {
        list.empty();
    }
    
    const notificationHtml = `
        <div class="notification-item unread" data-id="${data.id || 'temp-' + Date.now()}" onclick="handleNotificationClick(${data.id || 0}, '${data.link || '#'}')" style="cursor: pointer; border-bottom: 1px solid var(--ucc-green-mint); padding: 12px 16px; display: flex; align-items: flex-start; gap: 12px; background: #F0F9FF; border-left: 3px solid var(--ucc-green-primary);">
            <div class="notification-icon new-request" style="width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; background: #E8F5E9; color: var(--ucc-green-primary);">
                <i class="fas fa-bell"></i>
            </div>
            <div class="notification-content" style="flex: 1;">
                <div class="notification-title fw-bold" style="font-size: 0.9rem; color: #1F2937; margin-bottom: 3px;">${escapeHtml(data.title)}</div>
                <div class="notification-message" style="font-size: 0.8rem; color: #6B7280; margin-bottom: 3px; line-height: 1.3;">${escapeHtml(data.message)}</div>
                <div class="notification-time" style="font-size: 0.65rem; color: #9CA3AF; display: flex; align-items: center; gap: 4px;">
                    <i class="fas fa-circle" style="font-size: 0.5rem;"></i>
                    ${timeAgo}
                    <span class="notification-badge" style="width: 8px; height: 8px; border-radius: 50%; background: var(--ucc-green-primary); display: inline-block; margin-left: 5px;"></span>
                </div>
            </div>
        </div>
    `;
    
    // Prepend to list (newest first)
    list.prepend(notificationHtml);
    
    // Limit to 50 notifications
    if (list.children().length > 50) {
        list.children().last().remove();
    }
}

// Show toast notification
function showToast(title, message, link) {
    // Create toast container if it doesn't exist
    if (!$('#toastContainer').length) {
        $('body').append('<div id="toastContainer" style="position: fixed; top: 80px; right: 20px; z-index: 9999;"></div>');
    }
    
    const toastId = 'toast_' + Date.now();
    const toastHtml = `
        <div id="${toastId}" class="toast show align-items-center text-white bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true" style="margin-bottom: 10px; cursor: pointer;" onclick="window.location.href='${link}'">
            <div class="d-flex">
                <div class="toast-body">
                    <strong>${escapeHtml(title)}</strong><br>
                    <small>${escapeHtml(message)}</small>
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    `;
    
    $('#toastContainer').append(toastHtml);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        $(`#${toastId}`).fadeOut(300, function() { $(this).remove(); });
    }, 5000);
}

// Keep your existing functions but modify fetchNotifications to also check for new ones
let notificationInterval;

function initNotifications() {
    console.log('Initializing notification system...');
    
    // Request notification permission
    if (window.Notification && Notification.permission !== 'denied') {
        Notification.requestPermission();
    }
    
    // Initialize Pusher for real-time
    initPusher();
    
    // Also keep polling as backup (every 30 seconds instead of 10)
    fetchNotifications();
    notificationInterval = setInterval(fetchNotifications, 30000);
}

function fetchNotifications() {
    $.ajax({
        url: '../consumable/get_notifications.php',
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            console.log('Notifications fetched (polling):', response);
            if (response.success) {
                updateNotificationBadge(response.unread_count);
                renderNotifications(response.notifications);
                lastNotificationCount = response.unread_count;
            }
        },
        error: function(xhr, status, error) {
            console.error('Error fetching notifications:', error);
        }
    });
}

function updateNotificationBadge(count) {
    const badge = $('#notificationBadge');
    if (count > 0) {
        badge.text(count > 99 ? '99+' : count).show();
        // Update document title
        document.title = '(' + count + ') ' + document.title.replace(/^\(\d+\)\s/, '');
    } else {
        badge.hide();
        document.title = document.title.replace(/^\(\d+\)\s/, '');
    }
}

function renderNotifications(notifications) {
    const list = $('#notificationList');
    
    if (!notifications || notifications.length === 0) {
        list.html(`
            <div class="notification-empty text-center py-5">
                <i class="far fa-bell-slash fa-3x mb-3" style="color: #ccc;"></i>
                <p class="text-muted mb-0">No notifications yet</p>
            </div>
        `);
        return;
    }
    
    let html = '';
    notifications.forEach(notif => {
        const timeAgo = moment(notif.created_at).fromNow();
        const unreadClass = notif.is_read == 0 ? 'unread' : '';
        
        html += `
            <div class="notification-item ${unreadClass}" data-id="${notif.id}" onclick="handleNotificationClick(${notif.id}, '${notif.link || '#'}')">
                <div class="notification-icon new-request">
                    <i class="fas fa-bell"></i>
                </div>
                <div class="notification-content">
                    <div class="notification-title">${escapeHtml(notif.title)}</div>
                    <div class="notification-message">${escapeHtml(notif.message)}</div>
                    <div class="notification-time">
                        <i class="fas fa-circle"></i>
                        ${timeAgo}
                        ${notif.is_read == 0 ? '<span class="notification-badge"></span>' : ''}
                    </div>
                </div>
            </div>
        `;
    });
    
    list.html(html);
}

function handleNotificationClick(id, link) {
    markNotificationAsRead(id);
    if (link && link !== '#') {
        window.location.href = link;
    }
}

function markNotificationAsRead(id) {
    $.ajax({
        url: '../consumable/mark_notification_read.php',
        method: 'POST',
        data: JSON.stringify({ notification_id: id }),
        contentType: 'application/json',
        success: function(response) {
            if (response.success) {
                $(`.notification-item[data-id="${id}"]`).removeClass('unread');
                fetchNotifications();
            }
        }
    });
}

function markAllAsRead() {
    $.ajax({
        url: '../consumable/mark_notification_read.php',
        method: 'POST',
        data: JSON.stringify({ notification_id: 'all' }),
        contentType: 'application/json',
        success: function(response) {
            if (response.success) {
                $('.notification-item').removeClass('unread');
                fetchNotifications();
                Swal.fire({
                    icon: 'success',
                    title: 'Success',
                    text: 'All notifications marked as read',
                    timer: 1500,
                    showConfirmButton: false
                });
            }
        }
    });
}

function escapeHtml(text) {
    if (!text) return '';
    const map = {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'};
    return text.toString().replace(/[&<>"']/g, m => map[m]);
}

// Add this to handle any dynamic content if needed
$(document).ready(function() {
    // Ensure user name wraps properly
    $('.user-name').each(function() {
        $(this).attr('title', $(this).text()); // Add tooltip with full name
    });
    
    // Add tooltip for campus if truncated
    $('.user-campus').each(function() {
        $(this).attr('title', $(this).text());
    });
});

// Initialize everything
$(document).ready(function() {
    console.log('Document ready, initializing real-time notifications...');
    
    initNotifications();
    
    $('#markAllRead').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        markAllAsRead();
    });
    
    $('#notificationBell').on('click', function() {
        fetchNotifications();
    });
    
    $(window).on('beforeunload', function() {
        if (notificationInterval) {
            clearInterval(notificationInterval);
        }
    });
});
</script>