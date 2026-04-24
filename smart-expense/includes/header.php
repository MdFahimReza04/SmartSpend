<?php require_once __DIR__ . '/../includes/session.php'; requireLogin(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle ?? 'SmartSpend') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="/smart-expense/assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="/smart-expense/assets/js/charts.js" defer></script>
</head>
<body>
<div class="app-shell">

<!-- ── Sidebar ──────────────────────────────────── -->
<aside class="sidebar">
    <div class="sidebar-brand">
        <div class="brand-icon">💰</div>
        <div>
            <div class="brand-name">SmartSpend</div>
            <div class="brand-tagline">Finance Dashboard</div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section-label">Overview</div>
        <a href="/smart-expense/dashboard.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : '' ?>">
            <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0h6"/>
            </svg>
            Dashboard
        </a>
        <a href="/smart-expense/reports/index.php" class="nav-item <?= strpos($_SERVER['PHP_SELF'], 'reports') !== false ? 'active' : '' ?>">
            <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
            </svg>
            Reports
        </a>

        <div class="nav-section-label">Spending</div>
        <a href="/smart-expense/expenses/list.php" class="nav-item <?= strpos($_SERVER['PHP_SELF'], 'expenses/list') !== false ? 'active' : '' ?>">
            <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
            </svg>
            Expenses
        </a>
        <a href="/smart-expense/expenses/add.php" class="nav-item <?= strpos($_SERVER['PHP_SELF'], 'expenses/add') !== false ? 'active' : '' ?>">
            <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
            </svg>
            Add Expense
        </a>
        <a href="/smart-expense/categories/manage.php" class="nav-item <?= strpos($_SERVER['PHP_SELF'], 'categories') !== false ? 'active' : '' ?>">
            <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z"/>
            </svg>
            Categories
        </a>

        <div class="nav-section-label">Budget</div>
        <a href="/smart-expense/budget/set.php" class="nav-item <?= strpos($_SERVER['PHP_SELF'], 'budget/set') !== false ? 'active' : '' ?>">
            <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            Set Budget
        </a>
        <a href="/smart-expense/budget/track.php" class="nav-item <?= strpos($_SERVER['PHP_SELF'], 'budget/track') !== false ? 'active' : '' ?>">
            <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
            </svg>
            Track Budget
        </a>

        <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
        <div class="nav-section-label">Admin</div>
        <a href="/smart-expense/admin/index.php" class="nav-item <?= strpos($_SERVER['PHP_SELF'], 'admin') !== false ? 'active' : '' ?>">
            <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            Admin Panel
        </a>
        <?php endif; ?>
    </nav>

    <div class="sidebar-user">
        <?php
        $initials = strtoupper(substr($_SESSION['name'] ?? 'U', 0, 1));
        if (strpos($_SESSION['name'] ?? '', ' ') !== false) {
            $parts = explode(' ', $_SESSION['name']);
            $initials = strtoupper($parts[0][0] . end($parts)[0]);
        }
        ?>
        <div class="user-avatar"><?= $initials ?></div>
        <div class="user-info">
            <div class="user-name"><?= htmlspecialchars($_SESSION['name'] ?? 'User') ?></div>
            <div class="user-role"><?= ucfirst($_SESSION['role'] ?? 'user') ?></div>
        </div>
        <a href="/smart-expense/auth/profile.php" class="logout-btn" title="Edit Profile">
            <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
            </svg>
        </a>
        <a href="/smart-expense/auth/logout.php" class="logout-btn" title="Logout">
            <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
            </svg>
        </a>
    </div>
</aside>

<!-- ── Main ─────────────────────────────────────── -->
<div class="main-content">
<div class="topbar">
    <div class="topbar-left">
        <h1><?= htmlspecialchars($pageTitle ?? 'SmartSpend') ?></h1>
        <p><?= date('l, F j, Y') ?></p>
    </div>
    <div class="topbar-right">
        <div class="topbar-chip">
            <span class="dot"></span>
            <?= htmlspecialchars($_SESSION['name'] ?? 'User') ?>
        </div>
    </div>
</div>
<div class="page-body">
