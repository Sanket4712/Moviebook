<?php 
require_once '../includes/session.php';

// Redirect if already logged in - based on active_role
if (isLoggedIn()) {
    $role = getActiveRole();
    if ($role === 'admin') {
        header('Location: ../Admin/dashboard.php');
    } elseif ($role === 'theater') {
        header('Location: ../Theater/dashboard.php');
    } else {
        header('Location: ../User/home.php');
    }
    exit();
}

// Get flash messages
$error = getFlashMessage('error');
$success = getFlashMessage('success');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In - MovieBook</title>
    <link rel="stylesheet" href="../assets/css/auth.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .alert-error {
            background-color: rgba(220, 53, 69, 0.1);
            border: 1px solid #dc3545;
            color: #dc3545;
        }
        .alert-success {
            background-color: rgba(40, 167, 69, 0.1);
            border: 1px solid #28a745;
            color: #28a745;
        }
        .role-selector {
            display: flex;
            gap: 16px;
            margin-bottom: 20px;
        }
        .role-option {
            flex: 1;
            position: relative;
        }
        .role-option input[type="radio"] {
            position: absolute;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }
        .role-option label {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 16px;
            border: 2px solid rgba(255,255,255,0.2);
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 14px;
            color: #ffffff;
            font-weight: 500;
            background: rgba(255,255,255,0.05);
        }
        .role-option input[type="radio"]:checked + label {
            border-color: #e50914;
            background: rgba(229, 9, 20, 0.1);
        }
        .role-option label:hover {
            border-color: rgba(255,255,255,0.4);
        }
        .role-option i {
            font-size: 18px;
        }
        .role-label {
            font-size: 13px;
            color: rgba(255,255,255,0.7);
            margin-bottom: 8px;
        }
    </style>
</head>
<body>
    <div class="auth-wrapper">
        <div class="auth-content">
            <div class="auth-box">
                <a href="../index.php">
                    <img src="../logo.png" alt="MOVIEBOOK" class="logo-img">
                </a>
                <h2>Sign In</h2>
                
                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>
                
                <form id="loginForm" class="auth-form" action="process_login.php" method="POST">
                    <div class="input-group">
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            placeholder="Email address"
                            required
                        >
                    </div>

                    <div class="input-group">
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            placeholder="Password"
                            required
                        >
                    </div>

                    <!-- Role Selector -->
                    <p class="role-label">Login as:</p>
                    <div class="role-selector">
                        <div class="role-option">
                            <input type="radio" id="role_user" name="login_as" value="user" checked>
                            <label for="role_user">
                                <i class="bi bi-person"></i>
                                <span>User</span>
                            </label>
                        </div>
                        <div class="role-option">
                            <input type="radio" id="role_theater" name="login_as" value="theater">
                            <label for="role_theater">
                                <i class="bi bi-film"></i>
                                <span>Theater</span>
                            </label>
                        </div>
                        <div class="role-option">
                            <input type="radio" id="role_admin" name="login_as" value="admin">
                            <label for="role_admin">
                                <i class="bi bi-shield-lock"></i>
                                <span>Admin</span>
                            </label>
                        </div>
                    </div>

                    <button type="submit" class="submit-btn">Login</button>

                    <div class="form-help">
                        <div class="remember-me">
                            <input type="checkbox" id="remember" name="remember">
                            <label for="remember">Remember me</label>
                        </div>
                        <a href="#" class="help-link">Need help?</a>
                    </div>
                </form>

                <div class="form-footer">
                    <p>New to MovieBook? <a href="signup.php">Sign up now</a></p>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/auth.js"></script>
</body>
</html>
