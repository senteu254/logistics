        <nav class="sb-nav">

            <span class="sb-section-lbl">Main Menu</span>

            <a href="dashboard.php"
               class="sb-link <?php echo $current=='dashboard.php'?'active':''; ?>">
                <div class="sb-link-ico"><i class="fas fa-house"></i></div>
                <span>Dashboard</span>
                <i class="fas fa-chevron-right sb-link-arrow"></i>
            </a>

            <a href="add-bl.php"
               class="sb-link <?php echo $current=='add-bl.php'?'active':''; ?>">
                <div class="sb-link-ico"><i class="fas fa-circle-plus"></i></div>
                <span>Add B/L</span>
                <i class="fas fa-chevron-right sb-link-arrow"></i>
            </a>

            <a href="manage-bl.php"
               class="sb-link <?php echo $current=='manage-bl.php'?'active':''; ?>">
                <div class="sb-link-ico"><i class="fas fa-list-check"></i></div>
                <span>Manage B/L</span>
                <i class="fas fa-chevron-right sb-link-arrow"></i>
            </a>

            <a href="dispatches.php"
               class="sb-link <?php echo $current=='dispatches.php'?'active':''; ?>">
                <div class="sb-link-ico"><i class="fas fa-truck"></i></div>
                <span>Dispatches</span>
                <?php
                // Badge: count in-transit containers
                try {
                    $transitBadge = $pdo->query("SELECT COUNT(*) FROM dispatches WHERE status='transit'")->fetchColumn();
                    if ($transitBadge > 0):
                ?>
                <span style="margin-left:auto;background:var(--amber);color:white;font-size:0.65rem;font-weight:700;padding:2px 7px;border-radius:20px;min-width:20px;text-align:center;">
                    <?php echo $transitBadge; ?>
                </span>
                <?php endif; } catch(Exception $e) {} ?>
            </a>

            <a href="reports.php"
               class="sb-link <?php echo $current=='reports.php'?'active':''; ?>">
                <div class="sb-link-ico"><i class="fas fa-chart-bar"></i></div>
                <span>Reports</span>
                <i class="fas fa-chevron-right sb-link-arrow"></i>
            </a>

            <span class="sb-section-lbl" style="margin-top:1rem;">Account</span>

            <a href="profile.php"
               class="sb-link <?php echo $current=='profile.php'?'active':''; ?>">
                <div class="sb-link-ico"><i class="fas fa-user-gear"></i></div>
                <span>Profile</span>
                <i class="fas fa-chevron-right sb-link-arrow"></i>
            </a>

            <a href="logout.php" class="sb-link logout">
                <div class="sb-link-ico"><i class="fas fa-arrow-right-from-bracket"></i></div>
                <span>Logout</span>
            </a>

        </nav>