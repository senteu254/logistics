<?php
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'BL Manager'; ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="assets/css/app.css" rel="stylesheet">
</head>
<body>

<div class="app-wrapper">

    <!-- ═══════════════════════════════════════════ -->
    <!--  SIDEBAR                                    -->
    <!-- ═══════════════════════════════════════════ -->
    <aside class="sidebar" id="sidebar">

        <!-- Logo -->
        <div class="sidebar-logo">
            <div class="logo-icon">
                <i class="fas fa-ship"></i>
            </div>
            <div class="logo-text">
                <span class="logo-title">BL Manager</span>
                <span class="logo-sub">v2.0</span>
            </div>
        </div>

        <!-- User Card -->
        <div class="sidebar-user">
            <div class="user-avatar">
                <?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?>
            </div>
            <div class="user-info">
                <span class="user-name"><?php echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']); ?></span>
                <span class="user-role">
                    <i class="fas fa-circle"></i> Administrator
                </span>
            </div>
        </div>

        <!-- Navigation -->
        <nav class="sidebar-nav">

            <div class="nav-section-label">Main Menu</div>

            <a href="dashboard.php"
               class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                <div class="nav-item-icon">
                    <i class="fas fa-house"></i>
                </div>
                <span>Dashboard</span>
                <div class="nav-item-arrow"><i class="fas fa-chevron-right"></i></div>
            </a>

            <a href="add-bl.php"
               class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'add-bl.php' ? 'active' : ''; ?>">
                <div class="nav-item-icon">
                    <i class="fas fa-circle-plus"></i>
                </div>
                <span>Add B/L</span>
                <div class="nav-item-arrow"><i class="fas fa-chevron-right"></i></div>
            </a>

            <a href="manage-bl.php"
               class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'manage-bl.php' ? 'active' : ''; ?>">
                <div class="nav-item-icon">
                    <i class="fas fa-list-check"></i>
                </div>
                <span>Manage B/L</span>
                <div class="nav-item-arrow"><i class="fas fa-chevron-right"></i></div>
            </a>

            <a href="reports.php"
               class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">
                <div class="nav-item-icon">
                    <i class="fas fa-chart-bar"></i>
                </div>
                <span>Reports</span>
                <div class="nav-item-arrow"><i class="fas fa-chevron-right"></i></div>
            </a>

            <div class="nav-section-label" style="margin-top: 1.5rem;">Account</div>

            <a href="profile.php"
               class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>">
                <div class="nav-item-icon">
                    <i class="fas fa-user-gear"></i>
                </div>
                <span>Profile</span>
                <div class="nav-item-arrow"><i class="fas fa-chevron-right"></i></div>
            </a>

            <a href="logout.php" class="nav-item nav-item-logout">
                <div class="nav-item-icon">
                    <i class="fas fa-arrow-right-from-bracket"></i>
                </div>
                <span>Logout</span>
            </a>

        </nav>

        <!-- Sidebar Footer -->
        <div class="sidebar-footer">
            <i class="fas fa-shield-halved"></i>
            Secure Session Active
        </div>

    </aside>

    <!-- Mobile Backdrop -->
    <div class="sidebar-backdrop" id="sidebarBackdrop" onclick="closeSidebar()"></div>

    <!-- ═══════════════════════════════════════════ -->
    <!--  MAIN CONTENT                               -->
    <!-- ═══════════════════════════════════════════ -->
    <div class="main-content">

        <!-- Top Navbar -->
        <header class="top-navbar">
            <div class="navbar-left">
                <button class="menu-toggle" onclick="toggleSidebar()">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="page-breadcrumb">
                    <span class="breadcrumb-home">
                        <i class="fas fa-house"></i>
                    </span>
                    <i class="fas fa-chevron-right breadcrumb-sep"></i>
                    <span class="breadcrumb-current"><?php echo $page_title ?? 'Dashboard'; ?></span>
                </div>
            </div>
            <div class="navbar-right">
                <!-- Notification Bell -->
                <button class="navbar-icon-btn">
                    <i class="fas fa-bell"></i>
                    <span class="notif-dot"></span>
                </button>
                <!-- Clock -->
                <div class="navbar-clock" id="navClock"></div>
                <!-- Avatar -->
                <div class="navbar-avatar">
                    <?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?>
                </div>
            </div>
        </header>

        <!-- Page Content Starts Here -->
        <div class="page-content">