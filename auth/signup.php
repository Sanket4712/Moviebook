<?php 
require_once '../includes/session.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: ../User/home.php');
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
    <title>Sign Up - MovieBook</title>
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
    </style>
</head>
<body>
    <div class="auth-wrapper">
        <div class="auth-content">
            <div class="auth-box">
                <a href="../index.php">
                    <img src="../logo.png" alt="MOVIEBOOK" class="logo-img">
                </a>
                <h2>Sign Up</h2>
                
                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>
                
                <form id="signupForm" class="auth-form" action="process_signup.php" method="POST">
                    <div class="input-group">
                        <input 
                            type="text" 
                            id="fullName" 
                            name="fullName" 
                            placeholder="Full Name"
                            required
                        >
                    </div>

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
                            type="tel" 
                            id="phone" 
                            name="phone" 
                            placeholder="Phone number"
                            required
                        >
                    </div>

                    <div class="input-group">
                        <input 
                            type="password" 
                            id="signupPassword" 
                            name="password" 
                            placeholder="Password"
                            required
                            minlength="8"
                        >
                    </div>

                    <div class="input-group">
                        <input 
                            type="password" 
                            id="confirmPassword" 
                            name="confirmPassword" 
                            placeholder="Confirm Password"
                            required
                        >
                    </div>

                    <button type="submit" class="submit-btn">Sign Up</button>

                    <div class="form-terms">
                        <input type="checkbox" id="terms" name="terms" required>
                        <label for="terms">I agree to the Terms and Conditions</label>
                    </div>
                </form>

                <div class="form-footer">
                    <p>Already have an account? <a href="login.php">Sign in</a></p>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/auth.js"></script>
</body>
</html>
