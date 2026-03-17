<?php
require_once 'config.php';

$error = '';
$success = '';

// Redirect if already logged in
if (isLoggedIn()) {
    if (isAdmin()) {
        header('Location: admin_dashboard.php');
    } else {
        header('Location: manager_dashboard.php');
    }
    exit();
}

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        $stmt = $conn->prepare("SELECT id, username, password, full_name, role, branch_id, status FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            if ($user['status'] === 'inactive') {
                $error = 'Your account has been deactivated. Please contact the administrator.';
            } elseif (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['branch_id'] = $user['branch_id'];
                
                logActivity($conn, $user['id'], 'User logged in');
                
                if ($user['role'] === 'admin') {
                    header('Location: admin_dashboard.php');
                } else {
                    header('Location: manager_dashboard.php');
                }
                exit();
            } else {
                $error = 'Invalid username or password.';
            }
        } else {
            $error = 'Invalid username or password.';
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Penongs Inventory System</title>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #F5F5F5;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            padding: 0;
            margin: 0;
        }
        
        /* Top Brand Header */
        .brand-header {
            background: white;
            padding: 15px 0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .brand-header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .brand-logo {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            text-decoration: none;
        }
        
        .brand-name {
            font-size: 24px;
            font-weight: 900;
            color: #E74C3C;
            font-style: italic;
            line-height: 1.2;
        }
        
        .brand-tagline {
            font-size: 11px;
            font-weight: 700;
            color: #1a1a1a;
            letter-spacing: 1.5px;
            line-height: 1.2;
        }
        
        /* Main Container */
        .login-wrapper {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }
        
        .login-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 50px rgba(0,0,0,0.12);
            overflow: hidden;
            max-width: 500px;
            width: 100%;
        }
        
        .login-header {
            background: linear-gradient(135deg, #E74C3C 0%, #C0392B 100%);
            padding: 50px 40px;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            position: relative;
            min-height: 200px;
        }
        
        .btn-back {
            position: absolute;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            padding: 10px 20px;
            background: rgba(255,255,255,0.2);
            color: white;
            border: 2px solid white;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .btn-back:hover {
            background: white;
            color: #E74C3C;
            transform: translateX(-50%) translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .login-header h1 {
            color: white;
            font-size: 42px;
            font-weight: 900;
            font-style: italic;
            margin: 10px 0 8px 0;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
            letter-spacing: -0.5px;
            line-height: 1;
        }
        
        .login-header p {
            color: white;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 2px;
            margin: 0;
            opacity: 0.95;
        }
        
        .login-body {
            padding: 45px 40px;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
            font-size: 14px;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #E8E8E8;
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.3s ease;
        }

        .password-group {
            position: relative;
        }

        .password-group input {
            padding-right: 56px;
        }

        .toggle-password {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            border: none;
            background: #FFF8E1;
            color: #F39C12;
            font-size: 18px;
            cursor: pointer;
            width: 34px;
            height: 34px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }

        .toggle-password:hover {
            color: #E67E22;
            background: #FEF3C7;
        }

        .toggle-password:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(244, 208, 63, 0.2);
        }

        .toggle-password .eye-closed {
            display: none;
        }

        .toggle-password.is-visible .eye-open {
            display: none;
        }

        .toggle-password.is-visible .eye-closed {
            display: inline;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #F4D03F;
            box-shadow: 0 0 0 3px rgba(244, 208, 63, 0.1);
        }
        
        .btn-login {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #E74C3C 0%, #C0392B 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(231, 76, 60, 0.3);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }

        .btn-login.is-loading {
            pointer-events: none;
            opacity: 0.9;
            animation: loginPulse 0.8s ease-in-out infinite;
        }

        @keyframes loginPulse {
            0% { transform: scale(1); }
            50% { transform: scale(0.98); }
            100% { transform: scale(1); }
        }
        
        .alert {
            padding: 12px 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .alert-danger {
            background-color: #FADBD8;
            color: #C0392B;
            border-left: 4px solid #E74C3C;
        }
        
        .alert-success {
            background-color: #D5F4E6;
            color: #27AE60;
            border-left: 4px solid #27AE60;
        }
        
        .login-footer {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #E8E8E8;
            color: #7F8C8D;
            font-size: 13px;
        }
        
        .demo-credentials {
            background: #FEF9E7;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #F4D03F;
        }
        
        .demo-credentials h4 {
            color: #F39C12;
            font-size: 13px;
            margin-bottom: 8px;
            font-weight: 600;
        }
        
        .demo-credentials p {
            font-size: 12px;
            color: #666;
            margin: 5px 0;
            line-height: 1.5;
        }
        
        .forgot-password {
            text-align: center;
            margin-top: 15px;
        }
        
        .forgot-password a {
            color: #F39C12;
            text-decoration: none;
            font-size: 14px;
            cursor: pointer;
            transition: color 0.3s ease;
        }
        
        .forgot-password a:hover {
            color: #E67E22;
            text-decoration: underline;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.6);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal.active { display: flex; }
        
        .modal-content {
            background: white;
            border-radius: 15px;
            padding: 30px;
            max-width: 450px;
            width: 90%;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            animation: slideUp 0.3s ease;
        }
        
        .modal-header {
            font-size: 22px;
            font-weight: 600;
            margin-bottom: 20px;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .modal-message {
            font-size: 15px;
            color: #555;
            margin-bottom: 20px;
            line-height: 1.6;
        }
        
        .modal-info {
            background: #FEF9E7;
            padding: 15px;
            border-radius: 10px;
            border-left: 4px solid #F39C12;
            margin-bottom: 20px;
        }
        
        .modal-info p {
            font-size: 14px;
            color: #666;
            margin: 0;
        }
        
        .btn-modal {
            padding: 12px 25px;
            background: linear-gradient(135deg, #F4D03F 0%, #F39C12 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
        }
        
        .btn-modal:hover {
            opacity: 0.9;
        }
        
        @keyframes slideUp {
            from { transform: translateY(50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        @media (max-width: 480px) {
            .login-wrapper {
                padding: 20px 15px;
            }
            
            .login-container {
                margin: 0;
            }
            
            .login-header {
                padding: 40px 25px;
                min-height: 180px;
            }
            
            .login-header h1 {
                font-size: 32px;
            }
            
            .btn-back {
                padding: 8px 16px;
                font-size: 12px;
            }
            
            .login-body {
                padding: 30px 25px;
            }
            
            .brand-name {
                font-size: 20px;
            }
        }
    </style>
</head>
<body>
    <!-- Top Brand Header -->
    <div class="brand-header">
        <div class="brand-header-content">
            <a href="index.php" class="brand-logo">
                <div>
                    <div class="brand-name">Penongs</div>
                </div>
            </a>
        </div>
    </div>

    <!-- Login Wrapper -->
    <div class="login-wrapper">
        <div class="login-container">
            <div class="login-header">
                <h1>Penongs</h1>
            </div>
        
        <div class="login-body">
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <div class="demo-credentials">
                <h4>📌 Demo Credentials:</h4>
                <p><strong>Admin:</strong> admin / password</p>
                <p><strong>Manager:</strong> Create via Admin panel</p>
            </div>
            
            <form method="POST" action="" id="loginForm">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required autofocus>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="password-group">
                        <input type="password" id="password" name="password" required>
                        <button type="button" id="togglePassword" class="toggle-password" aria-label="Show password" title="Show password">
                            <span class="eye-open">👁</span>
                            <span class="eye-closed">🙈</span>
                        </button>
                    </div>
                </div>
                
                <button type="submit" class="btn-login" id="loginButton">Login</button>
            </form>
            
            <div class="forgot-password">
                <a onclick="showForgotPasswordModal()">Forgot Password?</a>
            </div>
            
            <div class="login-footer">
                &copy; 2026 Penongs Inventory System. All rights reserved.
            </div>
        </div>
        </div>
    </div>
    
    <!-- Forgot Password Modal -->
    <div id="forgotPasswordModal" class="modal">
        <div class="modal-content">
            <h2 class="modal-header">
                <span>🔒</span>
                <span>Forgot Password?</span>
            </h2>
            <p class="modal-message">
                If you have forgotten your password, please contact the system administrator for assistance.
            </p>
            <div class="modal-info">
                <p><strong>📧 Contact Information:</strong></p>
                <p>Please reach out to your system administrator to reset your password.</p>
            </div>
            <button onclick="closeForgotPasswordModal()" class="btn-modal">OK, Got it!</button>
        </div>
    </div>
    
    <script>
        function showForgotPasswordModal() {
            document.getElementById('forgotPasswordModal').classList.add('active');
        }
        
        function closeForgotPasswordModal() {
            document.getElementById('forgotPasswordModal').classList.remove('active');
        }
        
        document.getElementById('forgotPasswordModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeForgotPasswordModal();
            }
        });

        const passwordInput = document.getElementById('password');
        const togglePasswordButton = document.getElementById('togglePassword');
        const loginForm = document.getElementById('loginForm');
        const loginButton = document.getElementById('loginButton');

        togglePasswordButton.addEventListener('click', function() {
            const isPassword = passwordInput.type === 'password';
            passwordInput.type = isPassword ? 'text' : 'password';

            if (isPassword) {
                togglePasswordButton.classList.add('is-visible');
                togglePasswordButton.setAttribute('aria-label', 'Hide password');
                togglePasswordButton.setAttribute('title', 'Hide password');
            } else {
                togglePasswordButton.classList.remove('is-visible');
                togglePasswordButton.setAttribute('aria-label', 'Show password');
                togglePasswordButton.setAttribute('title', 'Show password');
            }
        });

        loginForm.addEventListener('submit', function() {
            loginButton.classList.add('is-loading');
            loginButton.textContent = 'Logging in...';
        });
    </script>
</body>
</html>
