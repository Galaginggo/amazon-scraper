<?php
session_start();

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Models/User.php';

$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Validation
    if (empty($username) || empty($email) || empty($password) || empty($confirmPassword)) {
        $error = "All fields are required";
    } elseif (strlen($username) < 3) {
        $error = "Username must be at least 3 characters long";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long";
    } elseif ($password !== $confirmPassword) {
        $error = "Passwords do not match";
    } else {
        $userModel = new User();
        $result = $userModel->register($username, $email, $password);
        
        if ($result['success']) {
            $success = "Account created successfully! You can now log in.";
            // Clear form
            $_POST = array();
        } else {
            $error = $result['error'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register – Amazon Price Tracker</title>
    <style>
        /* Global */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            transition: all 0.2s ease;
        }

        body {
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: #f3f4f6;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        .page-wrapper {
            max-width: 1100px;
            width: 100%;
            background: #ffffff;
            border-radius: 16px;
            padding: 32px;
            box-shadow: 0 10px 25px rgba(15,23,42,0.1);
            display: flex;
            gap: 40px;
            align-items: center;
        }

        .info-panel {
            flex: 1;
            min-width: 260px;
        }

        .info-title {
            font-size: 28px;
            font-weight: 700;
            color: #111827;
            margin-bottom: 8px;
        }

        .info-subtitle {
            font-size: 15px;
            color: #4b5563;
            line-height: 1.6;
        }

        .info-badge {
            display: inline-block;
            margin-bottom: 12px;
            padding: 4px 10px;
            border-radius: 999px;
            background: #f3f4f6;
            color: #4b5563;
            font-size: 12px;
        }

        .register-box {
            flex: 0 0 380px;
            background: #f9fafb;
            border-radius: 12px;
            padding: 24px 24px 28px;
            border: 1px solid #e5e7eb;
            box-shadow: 0 10px 20px rgba(15,23,42,0.08);
        }

        .register-title {
            font-size: 20px;
            font-weight: 600;
            color: #111827;
            margin-bottom: 4px;
        }

        .register-sub {
            font-size: 13px;
            color: #6b7280;
            margin-bottom: 16px;
        }

        .error {
            padding: 10px 12px;
            border-radius: 6px;
            margin-bottom: 14px;
            font-size: 13px;
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }

        .success {
            padding: 10px 12px;
            border-radius: 6px;
            margin-bottom: 14px;
            font-size: 13px;
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #6ee7b7;
        }

        .form-group {
            margin-bottom: 12px;
        }

        label {
            display: block;
            font-size: 13px;
            color: #374151;
            margin-bottom: 4px;
            font-weight: 500;
        }

        input[type="text"],
        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 10px 12px;
            border-radius: 8px;
            border: 1px solid #d1d5db;
            font-size: 14px;
            font-family: inherit;
            background: #ffffff;
        }

        input[type="text"]:focus,
        input[type="email"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
        }

        button[type="submit"] {
            width: 100%;
            padding: 10px 16px;
            border-radius: 8px;
            border: none;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            font-family: inherit;
            background: #2563eb;
            color: #ffffff;
            margin-top: 8px;
            box-shadow: 0 6px 15px rgba(37, 99, 235, 0.35);
        }

        button[type="submit"]:hover {
            background: #1d4ed8;
            transform: translateY(-1px);
            box-shadow: 0 10px 20px rgba(37, 99, 235, 0.45);
        }

        .login-link {
            font-size: 13px;
            color: #6b7280;
            margin-top: 16px;
            text-align: center;
        }

        .login-link a {
            color: #2563eb;
            text-decoration: none;
            font-weight: 500;
        }

        .login-link a:hover {
            text-decoration: underline;
        }

        .password-hint {
            font-size: 12px;
            color: #6b7280;
            margin-top: 4px;
        }

        @media (max-width: 768px) {
            .page-wrapper {
                flex-direction: column;
                padding: 24px;
            }
            .register-box {
                width: 100%;
            }
        }
    </style>
</head>
<body>

<div class="page-wrapper">
    <div class="info-panel">
        <div class="info-badge">🛒 Amazon Price Tracker</div>
        <h1 class="info-title">Create your account</h1>
        <p class="info-subtitle">
            Join us to start tracking Amazon product prices. Monitor your favorite products, 
            get price history insights, and never miss a deal. Your data is private and secure.
        </p>
    </div>

    <div class="register-box">
        <h2 class="register-title">Register</h2>
        <p class="register-sub">Create a new account to get started.</p>

        <?php if (!empty($error)): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="success">
                <?= htmlspecialchars($success) ?>
                <br><a href="login.php" style="color: #065f46; font-weight: 600;">Click here to login</a>
            </div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">
            <div class="form-group">
                <label for="username">Username</label>
                <input
                    type="text"
                    id="username"
                    name="username"
                    placeholder="Choose a username"
                    value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                    required
                    autofocus
                    minlength="3"
                >
                <div class="password-hint">At least 3 characters</div>
            </div>

            <div class="form-group">
                <label for="email">Email</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="your@email.com"
                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Create a strong password"
                    required
                    minlength="8"
                >
                <div class="password-hint">At least 8 characters</div>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input
                    type="password"
                    id="confirm_password"
                    name="confirm_password"
                    placeholder="Re-enter your password"
                    required
                    minlength="8"
                >
            </div>

            <button type="submit">Create Account</button>
        </form>

        <div class="login-link">
            Already have an account? <a href="login.php">Login here</a>
        </div>
    </div>
</div>

</body>
</html>