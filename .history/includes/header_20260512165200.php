<?php
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'Bill of Lading System'; ?></title>
    
    <!-- Bootstrap 5.3.2 with latest features -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome 6.5 -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    
    <!-- Inter Font Family -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <!-- Custom Modern CSS -->
    <link href="assets/css/style.css" rel="stylesheet">
    
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzIiIGhlaWdodD0iMzIiIHZpZXdCb3g9IjAgMCAzMiAzMiIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHJlY3Qgd2lkdGg9IjMyIiBoZWlnaHQ9IjMyIiByeD0iOCIgZmlsbD0iIzI1NjNlYiIvPgo8cGF0aCBkPSJNOCAxMmg0djhIOHptNiAwaDR2OGgtNHptNiAwaDR2OGgtNHoiIGZpbGw9IndoaXRlIi8+Cjwvc3ZnPgo=">
</head>
<body>
    <!-- Mobile Toggle Button -->
    <button class="mobile-toggle d-lg-none" type="button" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Backdrop for mobile -->
    <div class="sidebar-backdrop d-lg-none" onclick="closeSidebar()"></div>

    <div class="dashboard-wrapper">
        <!-- Ultra Modern Sidebar -->
        <nav class="sidebar" id="sidebar">
            <!-- Sidebar Header -->
            <div class="sidebar-header">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h4 class="mb-0">
                            <i class="fas fa-ship gradient-icon"></i>
                            BL Manager
                        </h4>
                        <small class="text-muted">v2.0</small>
                    </div>
                    <button class="btn btn-sm btn-ghost d-lg-none" onclick="closeSidebar()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>

            <!-- User Profile Section -->
            <div class="sidebar-profile">
                <div class="profile-card">
                    <div class="profile-avatar">
                        <i class="fas fa-user-circle"></i>
                    </div>
                    <div class="profile-info">
                        <h6 class="mb-0"><?php echo htmlspecialchars($_SESSION['username']); ?></h6>
                        <small class="text-muted">Administrator</small>
                    </div>
                    <div class="profile-status">
                        <span class="status-dot online"></span>
                    </div>
                </div>
            </div>

            <!-- Navigation Menu -->
            <div class="sidebar-nav">
                <div class="nav-section">
                    <span class="nav-section-title">MAIN MENU</span>
                    
                    <a href="dashboard.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                        <div class="nav-icon">
                            <i class="fas fa-home"></i>
                        </div>
                        <span>Dashboard</span>
                        <div class="nav-badge">
                            <span class="badge bg-primary">3</span>
                        </div>
                    </a>

                    <a href="add-bl.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'add-bl.php' ? 'active' : ''; ?>">
                        <div class="nav-icon">
                            <i class="fas fa-plus-circle"></i>
                        </div>
                        <span>Add B/L</span>
                    </a>

                    <a href="manage-bl.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'manage-bl.php' ? 'active' : ''; ?>">
                        <div class="nav-icon">
                            <i class="fas fa-clipboard-list"></i>
                        </div>
                        <span>Manage B/L</span>
                    </a>

                    <a href="reports.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">
                        <div class="nav-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <span>Reports</span>
                    </a>
                </div>

                <div class="nav-section">
                    <span class="nav-section-title">SETTINGS</span>
                    
                    <a href="profile.php" class="nav-link">
                        <div class="nav-icon">
                            <i class="fas fa-user-cog"></i>
                        </div>
                        <span>Profile</span>
                    </a>

                    <a href="logout.php" class="nav-link text-danger">
                        <div class="nav-icon">
                            <i class="fas fa-sign-out-alt"></i>
                        </div>
                        <span>Logout</span>
                    </a>
                </div>
            </div>
        </nav>

        <!-- Main Content Area -->
        <main class="main-content">