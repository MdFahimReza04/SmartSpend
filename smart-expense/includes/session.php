<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function requireLogin(): void {
    if (empty($_SESSION['user_id'])) {
        header('Location: /smart-expense/auth/login.php');
        exit;
    }
}

function requireAdmin(): void {
    requireLogin();
    if ($_SESSION['role'] !== 'admin') {
        header('Location: /smart-expense/dashboard.php');
        exit;
    }
}

function currentUserId(): int {
    return (int)($_SESSION['user_id'] ?? 0);
}
