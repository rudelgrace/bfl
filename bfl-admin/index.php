<?php
/**
 * Battle 3x3 — Admin Entry Point
 * Physical file at /bfl-admin/index.php
 *
 * This file exists so that the /bfl-admin URL works reliably
 * regardless of mod_rewrite or RewriteBase configuration.
 *
 * - Not logged in → show admin login page
 * - Logged in as scorer → redirect to scorer interface
 * - Logged in as admin/super_admin → redirect to admin dashboard
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';

if (!isLoggedIn()) {
    // Render the login page directly (no redirect loop)
    include __DIR__ . '/../admin/login.php';
    exit;
}

$admin = getCurrentAdmin();

if ($admin && $admin['role'] === 'scorer') {
    header('Location: ' . ADMIN_URL . '/scorer/index.php');
} else {
    header('Location: ' . ADMIN_URL . '/index.php');
}
exit;
