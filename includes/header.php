<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

// Fetch active categories for navigation
$catQuery = "SELECT * FROM categories ORDER BY cat_id ASC";
$catResult = mysqli_query($con, $catQuery);
$categoriesList = [];
if ($catResult) {
    while ($c = mysqli_fetch_assoc($catResult)) {
        $categoriesList[] = $c;
    }
}
$cartCount = get_cart_count();
$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' | SweetPop' : 'SweetPop | Dessert & Sweet Treats for Your Main-Character Era 🍰🩵🎀'; ?></title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    
    <!-- Custom Design System -->
    <link rel="stylesheet" href="assets/css/style.css">
    
    <!-- Bab IX Stylesheets (FlexSlider Carousel & Mega Menu) -->
    <link rel="stylesheet" href="assets/css/flexslider.css">
    <link rel="stylesheet" href="assets/css/menu.css">

    <!-- Sweetie AI Chatbot Styling -->
    <link rel="stylesheet" href="assets/css/chatbot.css">
</head>
<body>

    <!-- Top Announcement Banner -->
    <div class="top-banner">
        🍓 Free delivery min. order Rp50K! • Freshly baked daily in Yogyakarta with premium ingredients ♡
    </div>

    <!-- Main Navigation Header -->
    <header class="site-header">
        <div class="container">
            <div class="nav-main">
                <!-- Logo -->
                <a href="index.php" class="logo">
                    <i class="fa-solid fa-cake-candles"></i> SWEET<span>POP</span>
                </a>

                <!-- Search Bar -->
                <form action="store.php" method="GET" class="search-form">
                    <div class="search-input-wrapper">
                        <input type="text" name="search" placeholder="Search cakes, cookies, dessert box..." value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                        <button type="submit" aria-label="Search">
                            <i class="fa-solid fa-magnifying-glass"></i>
                        </button>
                    </div>
                </form>

                <!-- Actions -->
                <div class="nav-actions">
                    <!-- Shopping Cart Bag -->
                    <a href="cart.php" class="action-btn" title="Shopping Bag" aria-label="Cart">
                        <i class="fa-solid fa-bag-shopping"></i>
                        <span class="badge-count"><?= $cartCount; ?></span>
                    </a>

                    <!-- User Authentication -->
                    <?php if (is_logged_in()): ?>
                        <a href="profile.php" class="user-menu-btn" title="My Account">
                            <i class="fa-regular fa-face-smile-wink"></i>
                            <span>Hi, <?= htmlspecialchars(current_user_name()); ?></span>
                        </a>
                        <a href="logout.php" class="action-btn" title="Logout" style="background:#fee2e2; color:#ef4444;">
                            <i class="fa-solid fa-arrow-right-from-bracket"></i>
                        </a>
                    <?php else: ?>
                        <a href="login.php" class="btn-auth-outline">
                            <i class="fa-regular fa-user"></i> Login
                        </a>
                        <a href="register.php" class="btn-sweet btn-sweet-pink" style="padding:8px 18px; font-size:0.9rem;">
                            Register
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Category Pills Bar -->
            <nav class="category-nav">
                <a href="index.php" class="cat-pill <?= (!isset($_GET['category']) && basename($_SERVER['PHP_SELF']) == 'index.php') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-house"></i> Home
                </a>
                <a href="store.php" class="cat-pill <?= (!isset($_GET['category']) && basename($_SERVER['PHP_SELF']) == 'store.php') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-store"></i> All Treats
                </a>
                <?php foreach ($categoriesList as $cat): ?>
                    <?php 
                    $isActive = (isset($_GET['category']) && $_GET['category'] == $cat['cat_id']);
                    $icon = 'fa-cookie';
                    $lowerTitle = strtolower($cat['cat_title']);
                    if (strpos($lowerTitle, 'cake') !== false) $icon = 'fa-cake-candles';
                    elseif (strpos($lowerTitle, 'box') !== false) $icon = 'fa-box-open';
                    elseif (strpos($lowerTitle, 'drink') !== false) $icon = 'fa-mug-hot';
                    elseif (strpos($lowerTitle, 'pudding') !== false) $icon = 'fa-ice-cream';
                    elseif (strpos($lowerTitle, 'hamper') !== false) $icon = 'fa-gift';
                    ?>
                    <a href="store.php?category=<?= $cat['cat_id']; ?>" class="cat-pill <?= $isActive ? 'active' : ''; ?>">
                        <i class="fa-solid <?= $icon; ?>"></i> <?= htmlspecialchars($cat['cat_title']); ?>
                    </a>
                <?php endforeach; ?>
                <a href="index.php#informasi" class="cat-pill">
                    <i class="fa-solid fa-circle-info"></i> Informasi & Layanan
                </a>
                <a href="admin/login.php" class="cat-pill" style="margin-left:auto; background:var(--blue-light); color:var(--blue-dark); border-color:#bfdbfe;">
                    <i class="fa-solid fa-shield-halved"></i> Portal Admin
                </a>
            </nav>
        </div>
    </header>

    <!-- Global Flash Notification -->
    <?php if ($flash): ?>
        <div class="container" style="margin-top: 20px;">
            <div class="alert alert-<?= $flash['type']; ?>">
                <i class="fa-solid fa-circle-info"></i>
                <div><?= htmlspecialchars($flash['message']); ?></div>
            </div>
        </div>
    <?php endif; ?>
