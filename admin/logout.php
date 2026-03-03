<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';
logoutAdmin();
// Redirect to the public-facing admin entry point
header('Location: ' . BASE_URL . '/bfl-admin');
exit;
