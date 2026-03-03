<?php
/**
 * Admin Login
 * Accessible at /admin/login.php  OR  /login  (via .htaccess rewrite)
 *
 * v1.2 — After successful login, redirects to /bfl-admin/dashboard
 *         (the clean admin URL, resolved via .htaccess → /admin/index.php).
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

if (isLoggedIn()) {
    header('Location: ' . ADMIN_URL . '/index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = trim($_POST['username'] ?? '');
    $p = $_POST['password'] ?? '';

    if (empty($u) || empty($p)) {
        $error = 'Please enter your username and password.';
    } elseif (!loginAdmin($u, $p)) {
        $error = 'Invalid username or password.';
    } else {
        $loginAdmin = getCurrentAdmin();
        // Scorers go straight to the scorer interface; everyone else → dashboard
        if ($loginAdmin && $loginAdmin['role'] === 'scorer') {
            header('Location: ' . ADMIN_URL . '/scorer/index.php');
        } else {
            header('Location: ' . ADMIN_URL . '/index.php');
        }
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body {
            background: #0f172a;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: ui-sans-serif, system-ui, -apple-system, sans-serif;
        }

        .login-card {
            width: 100%;
            max-width: 400px;
            padding: 0 20px;
        }

        .login-box {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 16px;
            padding: 36px;
        }

        .form-control {
            background: #0f172a;
            border: 1px solid #334155;
            color: #e2e8f0;
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 14px;
        }

        .form-control:focus {
            background: #0f172a;
            border-color: #f97316;
            box-shadow: 0 0 0 .2rem rgba(249, 115, 22, .25);
            color: #e2e8f0;
        }

        .form-control::placeholder { color: #475569; }

        .form-label {
            color: #94a3b8;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .06em;
        }

        .btn-login {
            background: #f97316;
            border: none;
            color: #fff;
            width: 100%;
            padding: 12px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: background .15s;
        }

        .btn-login:hover { background: #ea580c; }

        .error-box {
            background: rgba(239, 68, 68, .1);
            border: 1px solid rgba(239, 68, 68, .3);
            color: #fca5a5;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 20px;
        }

        .password-wrap { position: relative; }
        .password-wrap .form-control { padding-right: 42px; }

        .toggle-pw {
            position: absolute;
            right: 0; top: 0; bottom: 0;
            width: 42px;
            background: none;
            border: none;
            color: #475569;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            transition: color .15s;
        }

        .toggle-pw:hover { color: #94a3b8; }
    </style>
</head>

<body>
    <div class="login-card">
        <div class="text-center mb-4">
            <div style="font-size:48px;margin-bottom:12px">🏀</div>
            <h1 style="color:#fff;font-size:22px;font-weight:700;margin:0"><?= APP_NAME ?></h1>
            <p style="color:#64748b;font-size:13px;margin-top:4px">League Management</p>
        </div>

        <div class="login-box">
            <h2 style="color:#e2e8f0;font-size:17px;font-weight:600;margin-bottom:24px">
                Sign in to your account
            </h2>

            <?php if ($error): ?>
                <div class="error-box">
                    <i class="fas fa-circle-xmark me-2"></i><?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="mb-4">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-control" required autofocus
                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                           placeholder="Enter username">
                </div>
                <div class="mb-4">
                    <label class="form-label">Password</label>
                    <div class="password-wrap">
                        <input type="password" name="password" id="passwordInput"
                               class="form-control" required placeholder="Enter password">
                        <button type="button" class="toggle-pw" id="togglePw"
                                aria-label="Show password" tabindex="-1">
                            <i class="fas fa-eye" id="togglePwIcon"></i>
                        </button>
                    </div>
                </div>
                <button type="submit" class="btn-login">Sign In</button>
            </form>
        </div>

        <p class="text-center mt-3" style="color:#475569;font-size:12px">
            Issues? Contact
            <a href="mailto:basketballcapetown@gmail.com"
               style="color:#94a3b8">basketballcapetown@gmail.com</a>
        </p>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const pwInput  = document.getElementById('passwordInput');
        const toggleBtn  = document.getElementById('togglePw');
        const toggleIcon = document.getElementById('togglePwIcon');
        toggleBtn.addEventListener('click', () => {
            const show = pwInput.type === 'password';
            pwInput.type = show ? 'text' : 'password';
            toggleIcon.className = show ? 'fas fa-eye-slash' : 'fas fa-eye';
            toggleBtn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        });
    </script>
</body>

</html>
