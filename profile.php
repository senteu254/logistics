<?php
require_once 'config/database.php';
$page_title = 'Profile';

// Fetch current user
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

$success = '';
$errors  = [];

// ── Handle Profile Update ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    // ── Update Profile Info ──
    if ($_POST['action'] === 'update_profile') {
        $full_name = trim($_POST['full_name'] ?? '');
        $email     = trim($_POST['email']     ?? '');
        $username  = trim($_POST['username']  ?? '');

        if (empty($full_name)) $errors[] = 'Full name is required.';
        if (empty($email))     $errors[] = 'Email is required.';
        if (empty($username))  $errors[] = 'Username is required.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email address.';

        if (empty($errors)) {
            // Check email uniqueness
            $check = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $check->execute([$email, $_SESSION['user_id']]);
            if ($check->fetch()) {
                $errors[] = 'This email is already in use.';
            }

            // Check username uniqueness
            $check2 = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $check2->execute([$username, $_SESSION['user_id']]);
            if ($check2->fetch()) {
                $errors[] = 'This username is already taken.';
            }
        }

        if (empty($errors)) {
            $upd = $pdo->prepare("UPDATE users SET full_name=?, email=?, username=? WHERE id=?");
            $upd->execute([$full_name, $email, $username, $_SESSION['user_id']]);
            $_SESSION['username']  = $username;
            $_SESSION['full_name'] = $full_name;

            // Refresh user
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();

            $success = 'profile';
        }
    }

    // ── Change Password ──
    if ($_POST['action'] === 'change_password') {
        $current  = $_POST['current_password']  ?? '';
        $new      = $_POST['new_password']       ?? '';
        $confirm  = $_POST['confirm_password']   ?? '';

        if (empty($current)) $errors[] = 'Current password is required.';
        if (empty($new))     $errors[] = 'New password is required.';
        if (strlen($new) < 8) $errors[] = 'New password must be at least 8 characters.';
        if ($new !== $confirm) $errors[] = 'Passwords do not match.';

        if (empty($errors)) {
            if (!password_verify($current, $user['password'])) {
                $errors[] = 'Current password is incorrect.';
            } else {
                $hash = password_hash($new, PASSWORD_BCRYPT);
                $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([$hash, $_SESSION['user_id']]);
                $success = 'password';
            }
        }
    }
}

// ── Activity Stats ──
$myBLs       = $pdo->prepare("SELECT COUNT(*) FROM bills_of_lading WHERE user_id=?");
$myBLs->execute([$_SESSION['user_id']]);
$myBLs = $myBLs->fetchColumn();

$myActive = $pdo->prepare("SELECT COUNT(*) FROM bills_of_lading WHERE user_id=? AND status='active'");
$myActive->execute([$_SESSION['user_id']]);
$myActive = $myActive->fetchColumn();

$myBags = $pdo->prepare("
    SELECT COALESCE(SUM(i.number_of_bags),0)
    FROM bl_items i
    JOIN bills_of_lading b ON b.id = i.bl_id
    WHERE b.user_id = ?
");
$myBags->execute([$_SESSION['user_id']]);
$myBags = $myBags->fetchColumn();

$myGW = $pdo->prepare("
    SELECT COALESCE(SUM(i.gross_weight),0)
    FROM bl_items i
    JOIN bills_of_lading b ON b.id = i.bl_id
    WHERE b.user_id = ?
");
$myGW->execute([$_SESSION['user_id']]);
$myGW = $myGW->fetchColumn();

// ── Recent Activity ──
$recentActivity = $pdo->prepare("
    SELECT b.*,
           COUNT(i.id)                       AS containers,
           COALESCE(SUM(i.number_of_bags),0) AS bags
    FROM bills_of_lading b
    LEFT JOIN bl_items i ON b.id = i.bl_id
    WHERE b.user_id = ?
    GROUP BY b.id
    ORDER BY b.created_at DESC
    LIMIT 5
");
$recentActivity->execute([$_SESSION['user_id']]);
$recentActivity = $recentActivity->fetchAll();

require_once 'includes/header.php';
?>

<style>
/* ── Profile Hero ── */
.profile-hero {
    background: linear-gradient(135deg, #0d1117 0%, #1e1b4b 50%, #0d1117 100%);
    border-radius: var(--r-lg);
    padding: 2.5rem 2rem;
    margin-bottom: 1.5rem;
    position: relative;
    overflow: hidden;
    border: 1px solid rgba(99,102,241,0.2);
}

.profile-hero::before {
    content: '';
    position: absolute;
    top: -80px; right: -80px;
    width: 280px; height: 280px;
    background: radial-gradient(circle, rgba(99,102,241,0.2) 0%, transparent 70%);
    border-radius: 50%;
}

.profile-hero::after {
    content: '';
    position: absolute;
    bottom: -60px; left: -60px;
    width: 200px; height: 200px;
    background: radial-gradient(circle, rgba(139,92,246,0.15) 0%, transparent 70%);
    border-radius: 50%;
}

.hero-inner {
    position: relative;
    z-index: 1;
    display: flex;
    align-items: center;
    gap: 1.75rem;
    flex-wrap: wrap;
}

.hero-avatar {
    width: 90px; height: 90px;
    background: linear-gradient(135deg, var(--indigo), var(--violet));
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.2rem;
    font-weight: 800;
    color: white;
    flex-shrink: 0;
    border: 4px solid rgba(255,255,255,0.15);
    box-shadow: 0 8px 32px rgba(99,102,241,0.4);
    position: relative;
}

.hero-online {
    position: absolute;
    bottom: 4px; right: 4px;
    width: 16px; height: 16px;
    background: var(--emerald);
    border-radius: 50%;
    border: 2.5px solid #0d1117;
}

.hero-info { flex: 1; }

.hero-name {
    font-size: 1.6rem;
    font-weight: 800;
    color: white;
    margin-bottom: 4px;
    letter-spacing: -0.3px;
}

.hero-username {
    font-size: 0.88rem;
    color: rgba(255,255,255,0.5);
    margin-bottom: 10px;
    font-family: 'Courier New', monospace;
}

.hero-username span {
    color: rgba(165,180,252,0.8);
}

.hero-badges {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.hero-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    backdrop-filter: blur(10px);
}

.hb-admin {
    background: rgba(99,102,241,0.2);
    color: #a5b4fc;
    border: 1px solid rgba(99,102,241,0.3);
}

.hb-joined {
    background: rgba(16,185,129,0.15);
    color: #6ee7b7;
    border: 1px solid rgba(16,185,129,0.25);
}

.hero-stats {
    display: flex;
    gap: 1.5rem;
    flex-wrap: wrap;
}

.hero-stat {
    text-align: center;
    padding: 0.75rem 1.25rem;
    background: rgba(255,255,255,0.05);
    border-radius: var(--r-md);
    border: 1px solid rgba(255,255,255,0.08);
    backdrop-filter: blur(10px);
    min-width: 90px;
}

.hero-stat-val {
    font-size: 1.5rem;
    font-weight: 800;
    color: white;
    line-height: 1;
    margin-bottom: 4px;
}

.hero-stat-lbl {
    font-size: 0.7rem;
    font-weight: 600;
    color: rgba(255,255,255,0.4);
    text-transform: uppercase;
    letter-spacing: 0.06em;
}

/* ── Tab Navigation ── */
.profile-tabs {
    display: flex;
    gap: 4px;
    background: white;
    border: 1px solid var(--border);
    border-radius: var(--r-lg);
    padding: 6px;
    margin-bottom: 1.5rem;
    overflow-x: auto;
    box-shadow: var(--shadow-sm);
}

.profile-tab {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    border-radius: var(--r-md);
    font-size: 0.855rem;
    font-weight: 600;
    color: var(--txt-2);
    cursor: pointer;
    transition: var(--t);
    border: none;
    background: none;
    white-space: nowrap;
    font-family: 'Inter', sans-serif;
}

.profile-tab:hover {
    background: var(--bg);
    color: var(--txt-1);
}

.profile-tab.active {
    background: linear-gradient(135deg, var(--indigo), var(--violet));
    color: white;
    box-shadow: 0 3px 12px rgba(99,102,241,0.3);
}

/* ── Tab Content ── */
.tab-pane { display: none; }
.tab-pane.active { display: block; }

/* ── Form Card ── */
.profile-card {
    background: white;
    border: 1px solid var(--border);
    border-radius: var(--r-lg);
    overflow: hidden;
    box-shadow: var(--shadow-sm);
    margin-bottom: 1.5rem;
}

.profile-card-head {
    padding: 1.25rem 1.75rem;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    gap: 12px;
    background: linear-gradient(to right, #fafbff, white);
}

.profile-card-ico {
    width: 38px; height: 38px;
    background: linear-gradient(135deg, var(--indigo), var(--violet));
    border-radius: var(--r-sm);
    display: flex; align-items: center; justify-content: center;
    color: white; font-size: 0.9rem;
    box-shadow: 0 3px 10px rgba(99,102,241,0.25);
    flex-shrink: 0;
}

.profile-card-head h5 {
    font-size: 0.97rem;
    font-weight: 700;
    color: var(--txt-1);
    margin: 0;
}

.profile-card-head p {
    color: var(--txt-2);
    font-size: 0.78rem;
    margin: 0;
}

.profile-card-body {
    padding: 1.75rem;
}

/* ── Password Strength ── */
.pw-strength-wrap {
    margin-top: 8px;
}

.pw-strength-bar {
    height: 4px;
    background: var(--border);
    border-radius: 99px;
    overflow: hidden;
    margin-bottom: 5px;
}

.pw-strength-fill {
    height: 100%;
    border-radius: 99px;
    transition: width 0.4s ease, background 0.4s ease;
    width: 0%;
}

.pw-strength-txt {
    font-size: 0.73rem;
    font-weight: 600;
}

/* ── Input with icon ── */
.input-icon-wrap {
    position: relative;
}

.input-icon-wrap .f-input {
    padding-right: 44px;
}

.input-eye {
    position: absolute;
    right: 13px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: var(--txt-3);
    cursor: pointer;
    font-size: 0.88rem;
    transition: var(--t);
    padding: 0;
}

.input-eye:hover { color: var(--indigo); }

/* ── Activity timeline ── */
.activity-item {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    padding: 14px 0;
    border-bottom: 1px solid var(--border-l);
    transition: var(--t);
}

.activity-item:last-child { border-bottom: none; }

.activity-item:hover { background: rgba(99,102,241,0.02); }

.activity-dot {
    width: 34px; height: 34px;
    border-radius: 50%;
    background: rgba(99,102,241,0.1);
    color: var(--indigo);
    display: flex; align-items: center; justify-content: center;
    font-size: 0.82rem;
    flex-shrink: 0;
    margin-top: 2px;
}

.activity-bl {
    font-weight: 700;
    color: var(--indigo);
    font-family: 'Courier New', monospace;
    font-size: 0.88rem;
    margin-bottom: 3px;
}

.activity-meta {
    font-size: 0.78rem;
    color: var(--txt-2);
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.activity-time {
    margin-left: auto;
    font-size: 0.73rem;
    color: var(--txt-3);
    white-space: nowrap;
    padding-top: 3px;
}

/* ── Info row (read-only view) ── */
.info-row {
    display: flex;
    align-items: center;
    padding: 14px 0;
    border-bottom: 1px solid var(--border-l);
    gap: 12px;
}

.info-row:last-child { border-bottom: none; }

.info-row-label {
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--txt-3);
    text-transform: uppercase;
    letter-spacing: 0.06em;
    min-width: 120px;
    flex-shrink: 0;
}

.info-row-value {
    font-size: 0.9rem;
    font-weight: 500;
    color: var(--txt-1);
    flex: 1;
}

.info-row-value.mono {
    font-family: 'Courier New', monospace;
    color: var(--indigo);
}

/* ── Success/Error alerts ── */
.form-alert {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 12px 16px;
    border-radius: var(--r-md);
    margin-bottom: 1.25rem;
    font-size: 0.855rem;
    font-weight: 500;
    animation: slideDown 0.3s ease;
}

@keyframes slideDown {
    from { opacity:0; transform:translateY(-6px); }
    to   { opacity:1; transform:translateY(0); }
}

.form-alert-success {
    background: rgba(16,185,129,0.08);
    border: 1px solid rgba(16,185,129,0.25);
    color: #065f46;
}

.form-alert-error {
    background: rgba(244,63,94,0.08);
    border: 1px solid rgba(244,63,94,0.25);
    color: #9f1239;
}
</style>

<!-- Page Header -->
<div class="page-header d-flex align-items-start justify-content-between flex-wrap gap-3">
    <div>
        <h1 class="page-title">My Profile</h1>
        <p class="page-sub">Manage your account information and security settings</p>
    </div>
    <a href="dashboard.php" class="btn-m btn-ghost">
        <i class="fas fa-arrow-left"></i> Dashboard
    </a>
</div>

<!-- ══ PROFILE HERO ══ -->
<div class="profile-hero">
    <div class="hero-inner">

        <!-- Avatar -->
        <div class="hero-avatar">
            <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
            <div class="hero-online"></div>
        </div>

        <!-- Info -->
        <div class="hero-info">
            <div class="hero-name"><?php echo htmlspecialchars($user['full_name'] ?? $user['username']); ?></div>
            <div class="hero-username">@<span><?php echo htmlspecialchars($user['username']); ?></span> &nbsp;·&nbsp; <?php echo htmlspecialchars($user['email']); ?></div>
            <div class="hero-badges">
                <span class="hero-badge hb-admin">
                    <i class="fas fa-shield-halved"></i> Administrator
                </span>
                <span class="hero-badge hb-joined">
                    <i class="fas fa-calendar-check"></i>
                    Joined <?php echo date('M Y', strtotime($user['created_at'])); ?>
                </span>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="hero-stats">
            <div class="hero-stat">
                <div class="hero-stat-val"><?php echo number_format($myBLs); ?></div>
                <div class="hero-stat-lbl">B/Ls Created</div>
            </div>
            <div class="hero-stat">
                <div class="hero-stat-val"><?php echo number_format($myActive); ?></div>
                <div class="hero-stat-lbl">Active</div>
            </div>
            <div class="hero-stat">
                <div class="hero-stat-val"><?php echo number_format($myBags); ?></div>
                <div class="hero-stat-lbl">Total Bags</div>
            </div>
            <div class="hero-stat">
                <div class="hero-stat-val"><?php echo number_format($myGW/1000,1); ?>t</div>
                <div class="hero-stat-lbl">Gross Wt.</div>
            </div>
        </div>

    </div>
</div>

<!-- ══ TABS ══ -->
<div class="profile-tabs">
    <button class="profile-tab active" onclick="switchTab('overview', this)">
        <i class="fas fa-user"></i> Overview
    </button>
    <button class="profile-tab" onclick="switchTab('edit', this)">
        <i class="fas fa-pen-to-square"></i> Edit Profile
    </button>
    <button class="profile-tab" onclick="switchTab('security', this)">
        <i class="fas fa-lock"></i> Security
    </button>
    <button class="profile-tab" onclick="switchTab('activity', this)">
        <i class="fas fa-clock-rotate-left"></i> Recent Activity
    </button>
</div>

<!-- ══════════════════════════════════════
     TAB: OVERVIEW
══════════════════════════════════════ -->
<div class="tab-pane active" id="tab-overview">
    <div class="row g-3">

        <!-- Account Info -->
        <div class="col-lg-7">
            <div class="profile-card">
                <div class="profile-card-head">
                    <div class="profile-card-ico"><i class="fas fa-id-card"></i></div>
                    <div>
                        <h5>Account Information</h5>
                        <p>Your personal details and account info</p>
                    </div>
                </div>
                <div class="profile-card-body">

                    <div class="info-row">
                        <div class="info-row-label">Full Name</div>
                        <div class="info-row-value"><?php echo htmlspecialchars($user['full_name'] ?? '—'); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-row-label">Username</div>
                        <div class="info-row-value mono">@<?php echo htmlspecialchars($user['username']); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-row-label">Email</div>
                        <div class="info-row-value"><?php echo htmlspecialchars($user['email']); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-row-label">Role</div>
                        <div class="info-row-value">
                            <span class="bdg bdg-active">
                                <i class="fas fa-circle" style="font-size:0.35rem;"></i>
                                Administrator
                            </span>
                        </div>
                    </div>
                    <div class="info-row">
                        <div class="info-row-label">Member Since</div>
                        <div class="info-row-value"><?php echo date('F d, Y', strtotime($user['created_at'])); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-row-label">Account ID</div>
                        <div class="info-row-value mono">#<?php echo str_pad($user['id'], 6, '0', STR_PAD_LEFT); ?></div>
                    </div>

                </div>
            </div>
        </div>

        <!-- My Stats -->
        <div class="col-lg-5">
            <div class="profile-card">
                <div class="profile-card-head">
                    <div class="profile-card-ico"><i class="fas fa-chart-bar"></i></div>
                    <div>
                        <h5>My Statistics</h5>
                        <p>Your personal activity summary</p>
                    </div>
                </div>
                <div class="profile-card-body">

                    <div style="display:flex;flex-direction:column;gap:1rem;">

                        <div style="display:flex;align-items:center;justify-content:space-between;padding:12px;background:rgba(99,102,241,0.06);border-radius:var(--r-sm);border:1px solid rgba(99,102,241,0.15);">
                            <div style="display:flex;align-items:center;gap:10px;">
                                <div style="width:34px;height:34px;background:rgba(99,102,241,0.12);border-radius:8px;display:flex;align-items:center;justify-content:center;color:var(--indigo);">
                                    <i class="fas fa-file-invoice"></i>
                                </div>
                                <div>
                                    <div style="font-size:0.82rem;font-weight:600;color:var(--txt-1);">Bills of Lading</div>
                                    <div style="font-size:0.72rem;color:var(--txt-3);">Total created by me</div>
                                </div>
                            </div>
                            <div style="font-size:1.4rem;font-weight:800;color:var(--indigo);"><?php echo number_format($myBLs); ?></div>
                        </div>

                        <div style="display:flex;align-items:center;justify-content:space-between;padding:12px;background:rgba(16,185,129,0.06);border-radius:var(--r-sm);border:1px solid rgba(16,185,129,0.15);">
                            <div style="display:flex;align-items:center;gap:10px;">
                                <div style="width:34px;height:34px;background:rgba(16,185,129,0.12);border-radius:8px;display:flex;align-items:center;justify-content:center;color:var(--emerald);">
                                    <i class="fas fa-circle-check"></i>
                                </div>
                                <div>
                                    <div style="font-size:0.82rem;font-weight:600;color:var(--txt-1);">Active B/Ls</div>
                                    <div style="font-size:0.72rem;color:var(--txt-3);">Currently active</div>
                                </div>
                            </div>
                            <div style="font-size:1.4rem;font-weight:800;color:var(--emerald);"><?php echo number_format($myActive); ?></div>
                        </div>

                        <div style="display:flex;align-items:center;justify-content:space-between;padding:12px;background:rgba(245,158,11,0.06);border-radius:var(--r-sm);border:1px solid rgba(245,158,11,0.15);">
                            <div style="display:flex;align-items:center;gap:10px;">
                                <div style="width:34px;height:34px;background:rgba(245,158,11,0.12);border-radius:8px;display:flex;align-items:center;justify-content:center;color:var(--amber);">
                                    <i class="fas fa-boxes-stacked"></i>
                                </div>
                                <div>
                                    <div style="font-size:0.82rem;font-weight:600;color:var(--txt-1);">Total Bags</div>
                                    <div style="font-size:0.72rem;color:var(--txt-3);">Across all containers</div>
                                </div>
                            </div>
                            <div style="font-size:1.4rem;font-weight:800;color:var(--amber);"><?php echo number_format($myBags); ?></div>
                        </div>

                        <div style="display:flex;align-items:center;justify-content:space-between;padding:12px;background:rgba(244,63,94,0.06);border-radius:var(--r-sm);border:1px solid rgba(244,63,94,0.15);">
                            <div style="display:flex;align-items:center;gap:10px;">
                                <div style="width:34px;height:34px;background:rgba(244,63,94,0.12);border-radius:8px;display:flex;align-items:center;justify-content:center;color:var(--rose);">
                                    <i class="fas fa-weight-hanging"></i>
                                </div>
                                <div>
                                    <div style="font-size:0.82rem;font-weight:600;color:var(--txt-1);">Gross Weight</div>
                                    <div style="font-size:0.72rem;color:var(--txt-3);">Total processed</div>
                                </div>
                            </div>
                            <div style="font-size:1.4rem;font-weight:800;color:var(--rose);"><?php echo number_format($myGW/1000,1); ?>t</div>
                        </div>

                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- ══════════════════════════════════════
     TAB: EDIT PROFILE
══════════════════════════════════════ -->
<div class="tab-pane" id="tab-edit">

    <?php if ($success === 'profile'): ?>
    <div class="form-alert form-alert-success">
        <i class="fas fa-circle-check" style="color:var(--emerald);margin-top:1px;"></i>
        <div>Profile updated successfully!</div>
    </div>
    <?php endif; ?>

    <?php if (!empty($errors) && isset($_POST['action']) && $_POST['action']==='update_profile'): ?>
    <div class="form-alert form-alert-error">
        <i class="fas fa-circle-exclamation" style="color:var(--rose);margin-top:1px;"></i>
        <div>
            <?php foreach ($errors as $e): ?>
                <?php echo htmlspecialchars($e); ?><br>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="profile-card">
        <div class="profile-card-head">
            <div class="profile-card-ico"><i class="fas fa-pen-to-square"></i></div>
            <div>
                <h5>Edit Profile Information</h5>
                <p>Update your personal details below</p>
            </div>
        </div>
        <div class="profile-card-body">
            <form method="POST" novalidate>
                <input type="hidden" name="action" value="update_profile">
                <div class="row g-3">

                    <div class="col-md-6">
                        <label class="f-label">Full Name <span class="req">*</span></label>
                        <input type="text" name="full_name" class="f-input"
                               value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>"
                               placeholder="e.g. John Smith" required>
                    </div>

                    <div class="col-md-6">
                        <label class="f-label">Username <span class="req">*</span></label>
                        <input type="text" name="username" class="f-input"
                               value="<?php echo htmlspecialchars($user['username']); ?>"
                               placeholder="e.g. johnsmith" required>
                    </div>

                    <div class="col-md-12">
                        <label class="f-label">Email Address <span class="req">*</span></label>
                        <input type="email" name="email" class="f-input"
                               value="<?php echo htmlspecialchars($user['email']); ?>"
                               placeholder="e.g. john@example.com" required>
                    </div>

                    <div class="col-12">
                        <div style="padding-top:0.5rem;border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:10px;margin-top:0.5rem;">
                            <button type="reset" class="btn-m btn-ghost">
                                <i class="fas fa-rotate-left"></i> Reset
                            </button>
                            <button type="submit" class="btn-m btn-indigo">
                                <i class="fas fa-floppy-disk"></i> Save Changes
                            </button>
                        </div>
                    </div>

                </div>
            </form>
        </div>
    </div>

</div>

<!-- ══════════════════════════════════════
     TAB: SECURITY
══════════════════════════════════════ -->
<div class="tab-pane" id="tab-security">

    <?php if ($success === 'password'): ?>
    <div class="form-alert form-alert-success">
        <i class="fas fa-circle-check" style="color:var(--emerald);margin-top:1px;"></i>
        <div>Password changed successfully! Please use your new password next time you log in.</div>
    </div>
    <?php endif; ?>

    <?php if (!empty($errors) && isset($_POST['action']) && $_POST['action']==='change_password'): ?>
    <div class="form-alert form-alert-error">
        <i class="fas fa-circle-exclamation" style="color:var(--rose);margin-top:1px;"></i>
        <div>
            <?php foreach ($errors as $e): ?>
                <?php echo htmlspecialchars($e); ?><br>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="row g-3">

        <!-- Change Password -->
        <div class="col-lg-7">
            <div class="profile-card">
                <div class="profile-card-head">
                    <div class="profile-card-ico"><i class="fas fa-lock"></i></div>
                    <div>
                        <h5>Change Password</h5>
                        <p>Use a strong password with at least 8 characters</p>
                    </div>
                </div>
                <div class="profile-card-body">
                    <form method="POST" novalidate id="pwForm">
                        <input type="hidden" name="action" value="change_password">

                        <div class="row g-3">

                            <div class="col-12">
                                <label class="f-label">Current Password <span class="req">*</span></label>
                                <div class="input-icon-wrap">
                                    <input type="password" name="current_password" id="curPw"
                                           class="f-input" placeholder="Enter current password" required>
                                    <button type="button" class="input-eye" onclick="togglePw('curPw','eyeCur')">
                                        <i class="fas fa-eye" id="eyeCur"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="col-12">
                                <label class="f-label">New Password <span class="req">*</span></label>
                                <div class="input-icon-wrap">
                                    <input type="password" name="new_password" id="newPw"
                                           class="f-input" placeholder="Enter new password"
                                           required oninput="checkStrength(this.value)">
                                    <button type="button" class="input-eye" onclick="togglePw('newPw','eyeNew')">
                                        <i class="fas fa-eye" id="eyeNew"></i>
                                    </button>
                                </div>
                                <!-- Strength Bar -->
                                <div class="pw-strength-wrap">
                                    <div class="pw-strength-bar">
                                        <div class="pw-strength-fill" id="strengthFill"></div>
                                    </div>
                                    <div class="pw-strength-txt" id="strengthTxt" style="color:var(--txt-3);">
                                        Enter a password
                                    </div>
                                </div>
                            </div>

                            <div class="col-12">
                                <label class="f-label">Confirm New Password <span class="req">*</span></label>
                                <div class="input-icon-wrap">
                                    <input type="password" name="confirm_password" id="confPw"
                                           class="f-input" placeholder="Repeat new password"
                                           required oninput="checkMatch()">
                                    <button type="button" class="input-eye" onclick="togglePw('confPw','eyeConf')">
                                        <i class="fas fa-eye" id="eyeConf"></i>
                                    </button>
                                </div>
                                <div id="matchTxt" style="font-size:0.73rem;margin-top:5px;font-weight:600;"></div>
                            </div>

                            <div class="col-12">
                                <div style="padding-top:0.5rem;border-top:1px solid var(--border);display:flex;justify-content:flex-end;margin-top:0.5rem;">
                                    <button type="submit" class="btn-m btn-indigo">
                                        <i class="fas fa-key"></i> Update Password
                                    </button>
                                </div>
                            </div>

                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Security Tips -->
        <div class="col-lg-5">
            <div class="profile-card">
                <div class="profile-card-head">
                    <div class="profile-card-ico" style="background:linear-gradient(135deg,var(--emerald),var(--cyan));">
                        <i class="fas fa-shield-halved"></i>
                    </div>
                    <div>
                        <h5>Security Tips</h5>
                        <p>Keep your account safe</p>
                    </div>
                </div>
                <div class="profile-card-body">
                    <div style="display:flex;flex-direction:column;gap:12px;">

                        <?php
                        $tips = [
                            ['fas fa-check-circle','var(--emerald)','Use 8+ characters','Mix letters, numbers and symbols'],
                            ['fas fa-check-circle','var(--emerald)','Avoid common words','Don\'t use "password", "123456" etc.'],
                            ['fas fa-check-circle','var(--emerald)','Keep it unique','Don\'t reuse passwords from other sites'],
                            ['fas fa-check-circle','var(--emerald)','Change regularly','Update your password every 90 days'],
                            ['fas fa-check-circle','var(--emerald)','Never share it','Your password is private to you only'],
                        ];
                        foreach ($tips as $tip): ?>
                        <div style="display:flex;align-items:flex-start;gap:10px;padding:10px;background:var(--bg);border-radius:var(--r-sm);">
                            <i class="<?php echo $tip[0]; ?>" style="color:<?php echo $tip[1]; ?>;margin-top:2px;font-size:0.88rem;flex-shrink:0;"></i>
                            <div>
                                <div style="font-size:0.82rem;font-weight:600;color:var(--txt-1);"><?php echo $tip[2]; ?></div>
                                <div style="font-size:0.75rem;color:var(--txt-2);"><?php echo $tip[3]; ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>

                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- ══════════════════════════════════════
     TAB: RECENT ACTIVITY
══════════════════════════════════════ -->
<div class="tab-pane" id="tab-activity">
    <div class="profile-card">
        <div class="profile-card-head">
            <div class="profile-card-ico"><i class="fas fa-clock-rotate-left"></i></div>
            <div>
                <h5>Recent Activity</h5>
                <p>Your last 5 Bills of Lading</p>
            </div>
            <a href="manage-bl.php" class="btn-m btn-ghost btn-sm" style="margin-left:auto;">
                View All <i class="fas fa-arrow-right"></i>
            </a>
        </div>
        <div class="profile-card-body">

            <?php if (empty($recentActivity)): ?>
            <div class="empty">
                <div class="empty-ico"><i class="fas fa-file-circle-xmark"></i></div>
                <h5>No Activity Yet</h5>
                <p>You haven't created any Bills of Lading yet.</p>
                <a href="add-bl.php" class="btn-m btn-indigo mt-3" style="width:fit-content;margin:1rem auto 0;">
                    <i class="fas fa-circle-plus"></i> Create First B/L
                </a>
            </div>
            <?php else: ?>

            <?php foreach ($recentActivity as $act): ?>
            <div class="activity-item">
                <div class="activity-dot"><i class="fas fa-file-invoice"></i></div>
                <div style="flex:1;min-width:0;">
                    <div class="activity-bl"><?php echo htmlspecialchars($act['bl_number']); ?></div>
                    <div class="activity-meta">
                        <span>
                            <i class="fas fa-ship" style="font-size:0.7rem;color:var(--indigo);"></i>
                            <?php echo $act['containers']; ?> container<?php echo $act['containers']!=1?'s':''; ?>
                        </span>
                        <span>
                            <i class="fas fa-boxes-stacked" style="font-size:0.7rem;color:var(--amber);"></i>
                            <?php echo number_format($act['bags']); ?> bags
                        </span>
                        <span class="bdg bdg-<?php echo $act['status']; ?>" style="font-size:0.68rem;padding:2px 7px;">
                            <?php echo ucfirst($act['status']); ?>
                        </span>
                    </div>
                </div>
                <div class="activity-time">
                    <?php
                    $ts   = strtotime($act['created_at']);
                    $diff = time() - $ts;
                    if ($diff < 60)          echo 'Just now';
                    elseif ($diff < 3600)    echo floor($diff/60) . 'm ago';
                    elseif ($diff < 86400)   echo floor($diff/3600) . 'h ago';
                    elseif ($diff < 604800)  echo floor($diff/86400) . 'd ago';
                    else                     echo date('M d, Y', $ts);
                    ?>
                </div>
            </div>
            <?php endforeach; ?>

            <?php endif; ?>
        </div>
    </div>
</div>

<script>
/* ── Tab Switching ── */
function switchTab(id, btn) {
    document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.profile-tab').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + id).classList.add('active');
    btn.classList.add('active');
}

/* ── Toggle Password Visibility ── */
function togglePw(fieldId, iconId) {
    const f = document.getElementById(fieldId);
    const i = document.getElementById(iconId);
    f.type  = f.type === 'password' ? 'text' : 'password';
    i.className = f.type === 'password' ? 'fas fa-eye' : 'fas fa-eye-slash';
}

/* ── Password Strength ── */
function checkStrength(pw) {
    const fill = document.getElementById('strengthFill');
    const txt  = document.getElementById('strengthTxt');
    if (!fill || !txt) return;

    let score = 0;
    if (pw.length >= 8)                        score++;
    if (pw.length >= 12)                       score++;
    if (/[A-Z]/.test(pw))                      score++;
    if (/[0-9]/.test(pw))                      score++;
    if (/[^A-Za-z0-9]/.test(pw))              score++;

    const levels = [
        { w:'0%',   c:'var(--border)', t:'Enter a password',     tc:'var(--txt-3)' },
        { w:'20%',  c:'var(--rose)',   t:'Very Weak',             tc:'var(--rose)' },
        { w:'40%',  c:'#f97316',       t:'Weak',                  tc:'#f97316' },
        { w:'60%',  c:'var(--amber)',  t:'Fair',                  tc:'var(--amber)' },
        { w:'80%',  c:'var(--cyan)',   t:'Strong',                tc:'var(--cyan)' },
        { w:'100%', c:'var(--emerald)','t':'Very Strong ✓',       tc:'var(--emerald)' },
    ];

    const lvl   = pw.length === 0 ? levels[0] : levels[Math.min(score, 5)];
    fill.style.width      = lvl.w;
    fill.style.background = lvl.c;
    txt.textContent       = lvl.t;
    txt.style.color       = lvl.tc;
}

/* ── Password Match Check ── */
function checkMatch() {
    const np = document.getElementById('newPw').value;
    const cp = document.getElementById('confPw').value;
    const mt = document.getElementById('matchTxt');
    if (!mt) return;

    if (cp.length === 0) {
        mt.textContent = '';
    } else if (np === cp) {
        mt.textContent = '✓ Passwords match';
        mt.style.color = 'var(--emerald)';
    } else {
        mt.textContent = '✗ Passwords do not match';
        mt.style.color = 'var(--rose)';
    }
}

/* ── Auto open correct tab if form was submitted ── */
document.addEventListener('DOMContentLoaded', () => {
    const action = '<?php echo $_POST["action"] ?? ""; ?>';
    if (action === 'update_profile') {
        document.querySelectorAll('.profile-tab')[1].click();
    } else if (action === 'change_password') {
        document.querySelectorAll('.profile-tab')[2].click();
    }

    /* Auto-dismiss alerts after 5s */
    document.querySelectorAll('.form-alert').forEach(el => {
        setTimeout(() => {
            el.style.transition = 'opacity 0.5s';
            el.style.opacity    = '0';
            setTimeout(() => el.remove(), 500);
        }, 5000);
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>