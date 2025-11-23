<?php
session_start();

$valid_username = "admin";
$valid_password = "12345";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if ($username === $valid_username && $password === $valid_password) {
        $_SESSION['user'] = $username;
        header("Location: index.php");
        exit();
    } else {
        $error = "Invalid username or password!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login – Amazon Price Tracker</title>
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

        /* Background “card” like dashboard */
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

        /* Left side text / branding */
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

        /* Login card (right) – similar feel to dashboard .container / forms */
        .login-box {
            flex: 0 0 340px;
            background: #f9fafb;
            border-radius: 12px;
            padding: 24px 24px 28px;
            border: 1px solid #e5e7eb;
            box-shadow: 0 10px 20px rgba(15,23,42,0.08);
        }

        .login-title {
            font-size: 20px;
            font-weight: 600;
            color: #111827;
            margin-bottom: 4px;
        }

        .login-sub {
            font-size: 13px;
            color: #6b7280;
            margin-bottom: 16px;
        }

        /* Error message – same style family as dashboard messages */
        .error {
            padding: 10px 12px;
            border-radius: 6px;
            margin-bottom: 14px;
            font-size: 13px;
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }

        /* Inputs */
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
        input[type="password"]:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
        }

        /* Button – same look as dashboard .btn .btn-primary */
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

        .hint {
            font-size: 12px;
            color: #9ca3af;
            margin-top: 10px;
            text-align: center;
        }

        .hint code {
            background: #111827;
            color: #f9fafb;
            padding: 2px 6px;
            border-radius: 4px;
        }

        /* Responsive for smaller screens */
        @media (max-width: 768px) {
            .page-wrapper {
                flex-direction: column;
                padding: 24px;
            }
            .login-box {
                width: 100%;
            }
        }
    </style>
</head>
<body>

<div class="page-wrapper">
    <!-- Left side: branding text to match dashboard feel -->
    <div class="info-panel">
        <div class="info-badge">🛒 Amazon Price Tracker</div>
        <h1 class="info-title">Welcome back, Admin</h1>
        <p class="info-subtitle">
            Log in to access your dashboard, monitor products, and track price history over time.
            Your account is secured and only authorized users can view the tracker.
        </p>
    </div>

 
    <div class="login-box">
        <h2 class="login-title">Sign in</h2>
        <p class="login-sub">Use your login credentials to continue.</p>

        <?php if (!empty($error)): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">
            <div class="form-group">
                <label for="username">Username</label>
                <input
                    type="text"
                    id="username"
                    name="username"
                    placeholder="Enter username"
                    required
                >
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Enter password"
                    required
                >
            </div>

            <button type="submit">Log In</button>

          
        </form>
    </div>
</div>

</body>
</html>
