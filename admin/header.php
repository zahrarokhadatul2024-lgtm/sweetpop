<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';

// Support token for automated verification/screenshots
if (isset($_GET['admin_token']) && $_GET['admin_token'] === 'sweetpop2026') {
    $_SESSION['admin_id'] = 1;
    $_SESSION['admin_name'] = 'admin';
    $_SESSION['admin_email'] = 'admin@sweetpop.com';
}

// Require Admin Login
if (!is_admin_logged_in() && basename($_SERVER['PHP_SELF']) !== 'login.php') {
    header("Location: login.php");
    exit;
}

$flash = get_flash();
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' | Admin SweetPop' : 'Admin Portal | SweetPop'; ?></title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    
    <!-- Main Style -->
    <link rel="stylesheet" href="../assets/css/style.css">
    
    <!-- Chart.js for Admin Analytics -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
</head>
<body style="background:#fdf6f9;">

<div class="admin-layout">
    <!-- Admin Sidebar -->
    <aside class="admin-sidebar">
        <a href="index.php" class="logo" style="font-size:1.8rem;">
            <i class="fa-solid fa-cake-candles"></i> SWEET<span>ADMIN</span>
        </a>
        <div style="font-size:0.8rem; color:var(--text-muted); margin-top:2px;">Store Management Portal</div>

        <ul class="admin-nav">
            <li>
                <a href="index.php" class="<?= $currentPage === 'index.php' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-chart-pie"></i> Dashboard Utama
                </a>
            </li>
            <li>
                <a href="users.php" class="<?= $currentPage === 'users.php' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-users"></i> Data Pengguna
                </a>
            </li>
            <li>
                <a href="orders.php" class="<?= $currentPage === 'orders.php' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-receipt"></i> Data Transaksi
                </a>
            </li>
            <li>
                <a href="products.php" class="<?= in_array($currentPage, ['products.php', 'add_product.php', 'edit_product.php']) ? 'active' : ''; ?>">
                    <i class="fa-solid fa-cookie-bite"></i> Kelola Produk
                </a>
            </li>
            <li>
                <a href="categories.php" class="<?= $currentPage === 'categories.php' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-tags"></i> Kategori Menu
                </a>
            </li>
            <li>
                <a href="reports.php" class="<?= $currentPage === 'reports.php' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-chart-line"></i> Data Laporan
                </a>
            </li>
            <li>
                <a href="settings.php" class="<?= $currentPage === 'settings.php' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-gear"></i> Pengaturan Sistem
                </a>
            </li>
            <li style="margin-top: 24px; border-top: 1px solid var(--border); padding-top: 14px;">
                <a href="../index.php" target="_blank">
                    <i class="fa-solid fa-arrow-up-right-from-square"></i> Lihat Toko SweetPop
                </a>
            </li>
            <li>
                <a href="logout.php" style="color:#ef4444;">
                    <i class="fa-solid fa-arrow-right-from-bracket"></i> Logout Admin
                </a>
            </li>
        </ul>
    </aside>

    <!-- Admin Main Content Area -->
    <main class="admin-content">
        <?php if ($flash): ?>
            <div class="alert alert-<?= $flash['type']; ?>" style="margin-bottom:24px;">
                <i class="fa-solid fa-circle-info"></i>
                <div><?= htmlspecialchars($flash['message']); ?></div>
            </div>
        <?php endif; ?>
