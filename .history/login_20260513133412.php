<?php
require_once 'config/database.php';

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input    = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($input) || empty($password)) {
        $error = 'Please fill in all fields.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1");
        $stmt->execute([$input, $input]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['username']  = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            header('Location: dashboard.php');
            exit();
        } else {
            $error = 'Invalid username or password. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #0f172a;
            overflow: hidden;
            position: relative;
        }

        /* Animated Background */
        .bg-animated {
            position: fixed;
            inset: 0;
            z-index: 0;
            overflow: hidden;
        }

        .bg-animated::before {
            content: '';
            position: absolute;
            width: 800px;
            height: 800px;
            background: radial-gradient(circle, rgba(99, 102, 241, 0.3) 0%, transparent 70%);
            top: -200px;
            left: -200px;
            animation: float1 8s ease-in-out infinite;
        }

        .bg-animated::after {
            content: '';
            position: absolute;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(139, 92, 246, 0.25) 0%, transparent 70%);
            bottom: -100px;
            right: -100px;
            animation: float2 10s ease-in-out infinite;
        }

        .bg-orb {
            position: absolute;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(59, 130, 246, 0.2) 0%, transparent 70%);
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            animation: pulse 6s ease-in-out infinite;
        }

        @keyframes float1 {
            0%, 100% { transform: translate(0, 0) scale(1); }
            50% { transform: translate(40px, 30px) scale(1.1); }
        }

        @keyframes float2 {
            0%, 100% { transform: translate(0, 0) scale(1); }
            50% { transform: translate(-30px, -40px) scale(1.15); }
        }

        @keyframes pulse {
            0%, 100% { opacity: 0.5; transform: translate(-50%, -50%) scale(1); }
            50% { opacity: 0.8; transform: translate(-50%, -50%) scale(1.1); }
        }

        /* Grid Lines */
        .bg-grid {
            position: fixed;
            inset: 0;
            z-index: 0;
            background-image:
                linear-gradient(rgba(255,255,255,0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.03) 1px, transparent 1px);
            background-size: 50px 50px;
        }

        /* Login Card */
        .login-wrapper {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 460px;
            padding: 1.5rem;
            animation: slideUp 0.7s cubic-bezier(0.22, 1, 0.36, 1);
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(40px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .login-card {
            background: rgba(255, 255, 255, 0.04);
            backdrop-filter: blur(40px);
            -webkit-backdrop-filter: blur(40px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 28px;
            padding: 3rem 2.5rem;
            box-shadow:
                0 0 0 1px rgba(255,255,255,0.05),
                0 20px 60px rgba(0,0,0,0.5),
                0 0 100px rgba(99,102,241,0.1);
        }

        /* Brand */
        .brand {
            text-align: center;
            margin-bottom: 2.5rem;
        }

        .brand-icon {
            width: 72px;
            height: 72px;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.25rem;
            font-size: 1.8rem;
            color: white;
            box-shadow: 0 8px 32px rgba(99, 102, 241, 0.4);
        }

        .brand h1 {
            color: white;
            font-size: 1.75rem;
            font-weight: 800;
            letter-spacing: -0.5px;
            margin-bottom: 0.35rem;
        }

        .brand p {
            color: rgba(255,255,255,0.45);
            font-size: 0.9rem;
            font-weight: 400;
        }

        /* Form Labels */
        .field-label {
            display: block;
            color: rgba(255,255,255,0.7);
            font-size: 0.82rem;
            font-weight: 600;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            margin-bottom: 0.5rem;
        }

        /* Input Fields */
        .input-wrapper {
            position: relative;
            margin-bottom: 1.25rem;
        }

        .input-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255,255,255,0.35);
            font-size: 0.9rem;
            z-index: 2;
            pointer-events: none;
        }

        .form-input {
            width: 100%;
            background: rgba(255,255,255,0.07);
            border: 1.5px solid rgba(255,255,255,0.1);
            border-radius: 14px;
            padding: 14px 16px 14px 44px;
            color: white;
            font-family: 'Inter', sans-serif;
            font-size: 0.95rem;
            font-weight: 400;
            transition: all 0.3s ease;
            outline: none;
        }

        .form-input::placeholder {
            color: rgba(255,255,255,0.25);
        }

        .form-input:focus {
            border-color: rgba(99, 102, 241, 0.8);
            background: rgba(255,255,255,0.09);
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.15);
        }

        .toggle-password {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: rgba(255,255,255,0.35);
            cursor: pointer;
            padding: 0;
            font-size: 0.9rem;
            transition: color 0.2s;
        }

        .toggle-password:hover {
            color: rgba(255,255,255,0.7);
        }

        /* Error Alert */
        .alert-error {
            background: rgba(239, 68, 68, 0.12);
            border: 1px solid rgba(239, 68, 68, 0.3);
            border-radius: 14px;
            padding: 14px 16px;
            color: #fca5a5;
            font-size: 0.88rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 1.5rem;
            animation: shake 0.4s ease;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-6px); }
            75% { transform: translateX(6px); }
        }

        /* Sign In Button */
        .btn-signin {
            width: 100%;
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            color: white;
            border: none;
            border-radius: 14px;
            padding: 15px;
            font-family: 'Inter', sans-serif;
            font-size: 1rem;
            font-weight: 700;
            letter-spacing: 0.025em;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 24px rgba(99, 102, 241, 0.4);
            margin-top: 0.75rem;
            position: relative;
            overflow: hidden;
        }

        .btn-signin::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, #818cf8 0%, #a78bfa 100%);
            opacity: 0;
            transition: opacity 0.3s;
        }

        .btn-signin:hover::before {
            opacity: 1;
        }

        .btn-signin:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 32px rgba(99, 102, 241, 0.55);
        }

        .btn-signin:active {
            transform: translateY(0);
        }

        .btn-signin span {
            position: relative;
            z-index: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        /* Divider */
        .divider {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 1.5rem 0;
        }

        .divider-line {
            flex: 1;
            height: 1px;
            background: rgba(255,255,255,0.08);
        }

        .divider-text {
            color: rgba(255,255,255,0.3);
            font-size: 0.8rem;
            font-weight: 500;
        }

        /* Footer text */
        .login-footer {
            text-align: center;
            margin-top: 2rem;
            color: rgba(255,255,255,0.25);
            font-size: 0.8rem;
        }

        /* Credentials hint */
        .credentials-hint {
            background: rgba(99, 102, 241, 0.08);
            border: 1px solid rgba(99, 102, 241, 0.2);
            border-radius: 12px;
            padding: 12px 16px;
            margin-bottom: 1.5rem;
            font-size: 0.82rem;
            color: rgba(255,255,255,0.5);
            text-align: center;
        }

        .credentials-hint strong {
            color: rgba(165, 180, 252, 0.9);
        }
    </style>
</head>
<body>

    <!-- Animated Background -->
    <div class="bg-animated">
        <div class="bg-orb"></div>
    </div>
    <div class="bg-grid"></div>

    <!-- Login Card -->
    <div class="login-wrapper">
        <div class="login-card">

            <!-- Brand -->
            <div class="brand">
                <div class="brand-icon">
                    <i class="fas fa-ship"></i>
                </div>
                <h1>RVP Logistics</h1>
                <p>Shipping Management System</p>
            </div>

            <!-- Error Alert -->
            <?php if ($error): ?>
            <div class="alert-error">
                <i class="fas fa-circle-exclamation"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>

            <!-- Credentials Hint -->
            <!-- <div class="credentials-hint">
                <i class="fas fa-info-circle me-1"></i>
                Default: <strong>admin</strong> / <strong>admin123</strong>
            </div> -->

            <!-- Login Form -->
            <form method="POST" action="" novalidate>

                <div style="margin-bottom: 1.25rem;">
                    <label class="field-label">Username or Email</label>
                    <div class="input-wrapper">
                        <i class="fas fa-user input-icon"></i>
                        <input
                            type="text"
                            name="username"
                            class="form-input"
                            placeholder="Enter your username"
                            value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                            required
                            autofocus
                        >
                    </div>
                </div>

                <div style="margin-bottom: 0.5rem;">
                    <label class="field-label">Password</label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock input-icon"></i>
                        <input
                            type="password"
                            name="password"
                            id="passwordField"
                            class="form-input"
                            placeholder="Enter your password"
                            required
                        >
                        <button
                            type="button"
                            class="toggle-password"
                            onclick="togglePassword()"
                            id="toggleBtn"
                        >
                            <i class="fas fa-eye" id="eyeIcon"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-signin">
                    <span>
                        <i class="fas fa-arrow-right-to-bracket"></i>
                        Sign In
                    </span>
                </button>

            </form>

            <!-- Footer -->
            <!-- <div class="login-footer">
                <i class="fas fa-shield-halved me-1"></i>
                Secure Login &nbsp;·&nbsp; BL Manager v2.0
            </div>

        </div>
    </div> -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function togglePassword() {
            const field   = document.getElementById('passwordField');
            const icon    = document.getElementById('eyeIcon');
            const isHidden = field.type === 'password';
            field.type    = isHidden ? 'text' : 'password';
            icon.className = isHidden ? 'fas fa-eye-slash' : 'fas fa-eye';
        }

        // Add loading state on submit
        document.querySelector('form').addEventListener('submit', function () {
            const btn = document.querySelector('.btn-signin');
            btn.innerHTML = `<span><i class="fas fa-spinner fa-spin"></i> Signing in...</span>`;
            btn.disabled = true;
        });
    </script>
</body>
</html>