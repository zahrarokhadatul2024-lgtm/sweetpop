<?php
// Query New & Trending Products
$newProductsQuery = "
    SELECT p.*, c.cat_title 
    FROM products p
    INNER JOIN categories c ON p.product_cat = c.cat_id
    WHERE p.product_status = 'active'
    ORDER BY p.created_at DESC
    LIMIT 8
";
$newProductsResult = mysqli_query($con, $newProductsQuery);

// Query Best Seller / Featured Products
$bestSellersQuery = "
    SELECT p.*, c.cat_title 
    FROM products p
    INNER JOIN categories c ON p.product_cat = c.cat_id
    WHERE p.product_status = 'active'
    ORDER BY p.product_stock ASC, p.product_id ASC
    LIMIT 4
";
$bestSellersResult = mysqli_query($con, $bestSellersQuery);

// Fetch Dynamic Product Images for Carousel Slides (ID 1, 9, 10)
$slideItems = [];
$sRes = mysqli_query($con, "SELECT product_id, product_title, product_price, product_image FROM products WHERE product_id IN (1, 9, 10)");
if ($sRes) {
    while ($sRow = mysqli_fetch_assoc($sRes)) {
        $slideItems[$sRow['product_id']] = $sRow;
    }
}
$slide1_img = !empty($slideItems[1]['product_image']) ? 'product_images/' . $slideItems[1]['product_image'] : 'product_images/strawberry-cloud.jpg';
$slide2_img = !empty($slideItems[9]['product_image']) ? 'product_images/' . $slideItems[9]['product_image'] : 'product_images/lotus-biscoff.jpg';
$slide3_img = !empty($slideItems[10]['product_image']) ? 'product_images/' . $slideItems[10]['product_image'] : 'product_images/matcha-cookies.jpg';
?>

<!-- Bab IX Implementation: FlexSlider Interactive Carousel Banner -->
<section class="banner-slider-section" style="padding-top: 24px; padding-bottom: 8px;">
    <div class="container">
        <div class="flexslider" style="border-radius: var(--radius-xl); overflow: hidden; box-shadow: var(--shadow-md); border: 2px solid var(--border-light); background: #ffffff; margin-bottom: 24px;">
            <ul class="slides">
                <!-- Slide 1: Strawberry Cloud Cake -->
                <li>
                    <div style="display:grid; grid-template-columns: 1.15fr 1fr; align-items:center; background: linear-gradient(135deg, #fff5f8 0%, #ffeaf1 100%); min-height: 340px; padding: 36px 50px; gap: 30px;">
                        <div>
                            <span class="hero-badge" style="background:#ff7597; color:#fff; border:none; margin-bottom:12px;">
                                ✦ PROMO SPESIAL • DISKON 20%
                            </span>
                            <h2 style="font-size: 2.3rem; line-height: 1.18; margin-bottom: 12px; color: #2b2353;">
                                Strawberry Cloud Dream Cake 🍰
                            </h2>
                            <p style="color: var(--text-muted); font-size: 1rem; margin-bottom: 20px; line-height: 1.5;">
                                Lapisan sponge vanilla super fluffy dengan whipped cream lembut dan potongan buah strawberry segar Ciwidey. Freshly baked daily!
                            </p>
                            <div style="display:flex; gap:16px; align-items:center; flex-wrap:wrap;">
                                <a href="store.php?category=1" class="btn-sweet btn-sweet-pink" style="padding:10px 24px; font-size:0.95rem;">
                                    Beli Sekarang <i class="fa-solid fa-arrow-right"></i>
                                </a>
                                <span style="font-size:1.35rem; font-weight:800; color:#ba007c;">Rp 45.000 <small style="text-decoration:line-through; font-size:0.85rem; color:#94a3b8; font-weight:400;">Rp 55.000</small></span>
                            </div>
                        </div>
                        <div style="text-align:center;">
                            <img src="<?= htmlspecialchars($slide1_img); ?>" alt="Strawberry Cloud Cake" style="max-height: 270px; width:auto; border-radius: var(--radius-lg); box-shadow: 0 10px 25px rgba(255,117,151,0.25); display:inline-block; object-fit:cover;">
                        </div>
                    </div>
                </li>

                <!-- Slide 2: Lotus Biscoff Dream Box -->
                <li>
                    <div style="display:grid; grid-template-columns: 1.15fr 1fr; align-items:center; background: linear-gradient(135deg, #fef8f0 0%, #fff0e2 100%); min-height: 340px; padding: 36px 50px; gap: 30px;">
                        <div>
                            <span class="hero-badge" style="background:#f59e0b; color:#fff; border:none; margin-bottom:12px;">
                                ★ BEST SELLER DESSERT BOX
                            </span>
                            <h2 style="font-size: 2.3rem; line-height: 1.18; margin-bottom: 12px; color: #2b2353;">
                                Lotus Biscoff Dream Box ✨
                            </h2>
                            <p style="color: var(--text-muted); font-size: 1rem; margin-bottom: 20px; line-height: 1.5;">
                                Kombinasi biskuit caramel renyah, krim mascarpone lembut, dan limpahan selai speculoos Belgia autentik yang meleleh di mulut.
                            </p>
                            <div style="display:flex; gap:16px; align-items:center; flex-wrap:wrap;">
                                <a href="store.php?category=2" class="btn-sweet btn-sweet-pink" style="padding:10px 24px; font-size:0.95rem; background:#d97706; border-color:#b45309;">
                                    Coba Sekarang <i class="fa-solid fa-arrow-right"></i>
                                </a>
                                <span style="font-size:1.35rem; font-weight:800; color:#b45309;">Rp 42.000</span>
                            </div>
                        </div>
                        <div style="text-align:center;">
                            <img src="<?= htmlspecialchars($slide2_img); ?>" alt="Lotus Biscoff Dream Box" style="max-height: 270px; width:auto; border-radius: var(--radius-lg); box-shadow: 0 10px 25px rgba(217,119,6,0.25); display:inline-block; object-fit:cover;">
                        </div>
                    </div>
                </li>

                <!-- Slide 3: Matcha White Choc Cookies -->
                <li>
                    <div style="display:grid; grid-template-columns: 1.15fr 1fr; align-items:center; background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); min-height: 340px; padding: 36px 50px; gap: 30px;">
                        <div>
                            <span class="hero-badge" style="background:#16a34a; color:#fff; border:none; margin-bottom:12px;">
                                🍵 BEST SELLER ARTISAN COOKIES
                            </span>
                            <h2 style="font-size: 2.3rem; line-height: 1.18; margin-bottom: 12px; color: #1e3a1e;">
                                Matcha White Choc Cookies 🍵
                            </h2>
                            <p style="color: var(--text-muted); font-size: 1rem; margin-bottom: 20px; line-height: 1.5;">
                                Kue kering lembut beraroma bubuk matcha Uji Kyoto autentik, dipadukan lelehan sweet Belgian white chocolate chips yang creamy di setiap gigitan.
                            </p>
                            <div style="display:flex; gap:16px; align-items:center; flex-wrap:wrap;">
                                <a href="store.php?category=3" class="btn-sweet btn-sweet-pink" style="padding:10px 24px; font-size:0.95rem; background:#16a34a; border-color:#15803d;">
                                    Beli Sekarang <i class="fa-solid fa-arrow-right"></i>
                                </a>
                                <span style="font-size:1.35rem; font-weight:800; color:#15803d;">Rp 24.000</span>
                            </div>
                        </div>
                        <div style="text-align:center;">
                            <img src="<?= htmlspecialchars($slide3_img); ?>" alt="Matcha White Choc Cookies" style="max-height: 270px; width:auto; border-radius: var(--radius-lg); box-shadow: 0 10px 25px rgba(22,163,74,0.25); display:inline-block; object-fit:cover;">
                        </div>
                    </div>
                </li>
            </ul>
        </div>
    </div>
</section>

<!-- Hero Section -->
<section class="hero-section">
    <div class="container">
        <div class="hero-grid">
            <div class="hero-text-content">
                <div class="hero-badge">
                    <span>✦</span> freshly baked happiness daily 🍰
                </div>
                <h1 class="hero-title">
                    Dessert so cute, <span>you'll wanna eat it twice.</span>
                </h1>
                <p class="hero-desc">
                    Artisanal cakes, creamy dessert boxes, chewy cookies, and dreamy sweet treats made especially for your main-character moments.
                </p>
                <div class="hero-cta-group">
                    <a href="store.php" class="btn-sweet btn-sweet-pink">
                        Shop the Sweetness <i class="fa-solid fa-arrow-right"></i>
                    </a>
                    <a href="store.php?category=1" class="btn-sweet btn-sweet-light">
                        <i class="fa-solid fa-cake-candles" style="color:var(--pink);"></i> Explore Cakes
                    </a>
                </div>
                <div class="hero-stats">
                    <div class="hero-stat-item">
                        <strong>100%</strong>
                        <span>Halal & Premium</span>
                    </div>
                    <div class="hero-stat-item">
                        <strong>5,000+</strong>
                        <span>Happy Cravings</span>
                    </div>
                    <div class="hero-stat-item">
                        <strong>4.9 ★</strong>
                        <span>Taste Rating</span>
                    </div>
                </div>
            </div>

            <div class="hero-media">
                <div class="floating-pill pill-left">
                    <span style="font-size:1.4rem;">🍓</span>
                    <div>
                        <div style="font-size:0.75rem; color:var(--text-muted);">Fresh From Oven</div>
                        <div>Strawberry Cloud</div>
                    </div>
                </div>
                <div class="floating-pill pill-right">
                    <span style="font-size:1.4rem;">✨</span>
                    <div>
                        <div style="font-size:0.75rem; color:var(--text-muted);">Trending Treat</div>
                        <div>Biscoff Dream Box</div>
                    </div>
                </div>
                <div class="hero-img-card">
                    <img src="assets/img/hero-dessert.png" alt="SweetPop Aesthetic Dessert Spread">
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Category Showcase Section -->
<section class="category-section">
    <div class="container">
        <div class="section-header">
            <span class="section-tag">pick your poison ♡</span>
            <h2 class="section-title">What's Making You Hungry?</h2>
            <p class="section-desc">Pilih kategori dessert favoritmu yang siap memanjakan lidah dan mood hari ini.</p>
        </div>

        <div class="category-cards-grid">
            <a href="store.php?category=1" class="category-card">
                <div class="category-thumb">
                    <img src="assets/img/category-cake.jpg" alt="Cake Collection">
                </div>
                <h3>Cake Collection</h3>
                <p>soft, dreamy & pretty</p>
            </a>

            <a href="store.php?category=2" class="category-card">
                <div class="category-thumb">
                    <img src="assets/img/category-dessert.jpg" alt="Dessert Box">
                </div>
                <h3>Dessert Box</h3>
                <p>tiny box, huge happiness</p>
            </a>

            <a href="store.php?category=3" class="category-card">
                <div class="category-thumb">
                    <img src="assets/img/category-cookies.jpg" alt="Chewy Cookies">
                </div>
                <h3>Chewy Cookies</h3>
                <p>crispy outside, chewy inside</p>
            </a>

            <a href="store.php?category=4" class="category-card">
                <div class="category-thumb">
                    <img src="assets/img/category-brownies.jpg" alt="Fudgy Brownies">
                </div>
                <h3>Fudgy Brownies</h3>
                <p>intense chocolate lovers</p>
            </a>

            <a href="store.php?category=5" class="category-card">
                <div class="category-thumb">
                    <img src="assets/img/category-pudding.jpg" alt="Silky Pudding">
                </div>
                <h3>Silky Pudding</h3>
                <p>melt-in-mouth sweetness</p>
            </a>

            <a href="store.php?category=6" class="category-card">
                <div class="category-thumb">
                    <img src="assets/img/category-drinks.jpg" alt="Artisan Drinks">
                </div>
                <h3>Artisan Drinks</h3>
                <p>refreshing sweet sips</p>
            </a>
        </div>
    </div>
</section>

<!-- Trending & New Arrivals (Dynamic from MySQL DB) -->
<section class="products-section">
    <div class="container">
        <div style="display:flex; justify-content:space-between; align-items:flex-end; margin-bottom: 36px; flex-wrap:wrap; gap:16px;">
            <div>
                <span class="section-tag">fresh from the kitchen ✦</span>
                <h2 class="section-title" style="margin-bottom:0;">Trending & New Treats</h2>
            </div>
            <a href="store.php" class="btn-sweet btn-sweet-light">
                View All Treats <i class="fa-solid fa-arrow-right"></i>
            </a>
        </div>

        <div class="products-grid">
            <?php if ($newProductsResult && mysqli_num_rows($newProductsResult) > 0): ?>
                <?php while ($prod = mysqli_fetch_assoc($newProductsResult)): ?>
                    <div class="product-card">
                        <span class="product-badge">NEW ✦</span>
                        <a href="product.php?id=<?= $prod['product_id']; ?>" class="product-media">
                            <img src="product_images/<?= htmlspecialchars($prod['product_image'] ?: 'strawberry-cloud.jpg'); ?>" alt="<?= htmlspecialchars($prod['product_title']); ?>" loading="lazy">
                        </a>
                        <div class="product-details">
                            <div class="product-cat"><?= htmlspecialchars($prod['cat_title']); ?></div>
                            <h3 class="product-title">
                                <a href="product.php?id=<?= $prod['product_id']; ?>">
                                    <?= htmlspecialchars($prod['product_title']); ?>
                                </a>
                            </h3>
                            <div class="product-rating">
                                <i class="fa-solid fa-star"></i>
                                <i class="fa-solid fa-star"></i>
                                <i class="fa-solid fa-star"></i>
                                <i class="fa-solid fa-star"></i>
                                <i class="fa-solid fa-star"></i>
                                <span>(5.0)</span>
                            </div>
                            <div class="product-footer">
                                <div class="product-price">
                                    <?= format_rupiah($prod['product_price']); ?>
                                </div>
                                <button class="btn-add-cart" data-id="<?= $prod['product_id']; ?>" data-title="<?= htmlspecialchars($prod['product_title']); ?>" title="Add to Bag" aria-label="Add to cart">
                                    <i class="fa-solid fa-plus"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div style="grid-column: 1 / -1; text-align:center; padding: 40px;">
                    <p style="color:var(--text-muted);">Belum ada produk yang aktif di database.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Hot Deal Promo Banner -->
<section class="deal-section">
    <div class="container">
        <div class="deal-card">
            <div>
                <span class="hero-badge" style="background: rgba(255,255,255,0.25); color:#ffffff; border:none;">
                    🍓 LIMITED TIME TREAT
                </span>
                <h2>Sweet Tooth Week: Extra 20% OFF!</h2>
                <p>Gunakan kupon <strong>SWEET20</strong> saat checkout untuk mendapatkan potongan langsung pada semua dessert box dan cookies.</p>
                <div style="display:flex; gap:16px; align-items:center; flex-wrap:wrap;">
                    <a href="store.php?category=2" class="btn-sweet btn-sweet-light" style="font-weight:800; color:var(--pink-dark);">
                        Claim Discount Now ♡
                    </a>
                </div>
            </div>
            <div class="deal-img-wrapper" style="text-align:center;">
                <img src="assets/img/deal-banner.jpg" alt="Sweet Deal Banner">
            </div>
        </div>
    </div>
</section>

<!-- Best Sellers & Favorites -->
<section class="products-section" style="padding-top: 10px;">
    <div class="container">
        <div class="section-header">
            <span class="section-tag">customer all-time favorites 👑</span>
            <h2 class="section-title">SweetPop Best Sellers</h2>
            <p class="section-desc">Pilihan dessert paling sering di-checkout dan viral di media sosial.</p>
        </div>

        <div class="products-grid">
            <?php if ($bestSellersResult && mysqli_num_rows($bestSellersResult) > 0): ?>
                <?php while ($prod = mysqli_fetch_assoc($bestSellersResult)): ?>
                    <div class="product-card">
                        <span class="product-badge" style="background: linear-gradient(135deg, #f59e0b, #ef4444);">BEST SELLER 🔥</span>
                        <a href="product.php?id=<?= $prod['product_id']; ?>" class="product-media">
                            <img src="product_images/<?= htmlspecialchars($prod['product_image'] ?: 'choco-oreo.jpg'); ?>" alt="<?= htmlspecialchars($prod['product_title']); ?>" loading="lazy">
                        </a>
                        <div class="product-details">
                            <div class="product-cat"><?= htmlspecialchars($prod['cat_title']); ?></div>
                            <h3 class="product-title">
                                <a href="product.php?id=<?= $prod['product_id']; ?>">
                                    <?= htmlspecialchars($prod['product_title']); ?>
                                </a>
                            </h3>
                            <div class="product-rating">
                                <i class="fa-solid fa-star"></i>
                                <i class="fa-solid fa-star"></i>
                                <i class="fa-solid fa-star"></i>
                                <i class="fa-solid fa-star"></i>
                                <i class="fa-solid fa-star"></i>
                                <span>(5.0)</span>
                            </div>
                            <div class="product-footer">
                                <div class="product-price">
                                    <?= format_rupiah($prod['product_price']); ?>
                                </div>
                                <button class="btn-add-cart" data-id="<?= $prod['product_id']; ?>" data-title="<?= htmlspecialchars($prod['product_title']); ?>" title="Add to Bag">
                                    <i class="fa-solid fa-plus"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Why SweetPop Features -->
<section class="features-section">
    <div class="container">
        <div class="features-grid">
            <div class="feature-box">
                <div class="feature-icon">
                    <i class="fa-solid fa-wand-magic-sparkles"></i>
                </div>
                <div class="feature-text">
                    <h4>Artisanal Quality</h4>
                    <p>Dibuat handmade dari bahan premium New Zealand butter & Belgian chocolate.</p>
                </div>
            </div>

            <div class="feature-box">
                <div class="feature-icon">
                    <i class="fa-solid fa-truck-fast"></i>
                </div>
                <div class="feature-text">
                    <h4>Same-Day Delivery</h4>
                    <p>Dikirim dengan kurir instan/sameday dengan ice gel pack agar tetap segar.</p>
                </div>
            </div>

            <div class="feature-box">
                <div class="feature-icon">
                    <i class="fa-solid fa-gift"></i>
                </div>
                <div class="feature-text">
                    <h4>Cute Packaging</h4>
                    <p>Setiap paket dilengkapi box estetik, pita pastel, dan greeting card gratis.</p>
                </div>
            </div>

            <div class="feature-box">
                <div class="feature-icon">
                    <i class="fa-solid fa-shield-heart"></i>
                </div>
                <div class="feature-text">
                    <h4>100% Halal Ingredients</h4>
                    <p>Seluruh bahan bebas alkohol dan gelatin non-halal, aman dinikmati keluarga.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Informasi & Tentang Sistem Section -->
<section id="informasi" class="info-section" style="padding: 70px 0; background: linear-gradient(180deg, #fffaf5 0%, #fff0f5 100%); border-top: 1px solid var(--border-light); border-bottom: 1px solid var(--border-light);">
    <div class="container">
        <div class="section-header" style="text-align: center; max-width: 720px; margin: 0 auto 50px;">
            <span class="section-tag">✦ Informasi & Layanan Sistem ✦</span>
            <h2 class="section-title">Tentang Sistem Toko Kue SweetPop</h2>
            <p class="section-desc">
                SweetPop adalah sistem informasi e-commerce berbasis web yang dirancang khusus untuk mempermudah pelanggan dalam menjelajahi, memesan kue artisan, dessert box, cookies renyah, hingga brownies lumer secara cepat, aman, dan terintegrasi dengan pencatatan transaksi real-time.
            </p>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 24px; margin-bottom: 50px;">
            <div class="card-box" style="text-align: center; padding: 32px 24px;">
                <div style="width: 64px; height: 64px; margin: 0 auto 16px; border-radius: 50%; background: var(--pink-light); color: var(--pink-dark); display: flex; align-items: center; justify-content: center; font-size: 1.6rem;">
                    <i class="fa-solid fa-cake-candles"></i>
                </div>
                <h3 style="font-size: 1.25rem; margin-bottom: 10px;">1. Pilih Dessert Favorit</h3>
                <p style="color: var(--text-muted); font-size: 0.92rem; margin: 0;">
                    Jelajahi beragam kategori menu kue freshly baked daily mulai dari strawberry cloud cake, dessert box, cookies renyah, hingga brownies lumer.
                </p>
            </div>

            <div class="card-box" style="text-align: center; padding: 32px 24px;">
                <div style="width: 64px; height: 64px; margin: 0 auto 16px; border-radius: 50%; background: var(--blue-light); color: var(--blue-dark); display: flex; align-items: center; justify-content: center; font-size: 1.6rem;">
                    <i class="fa-solid fa-cart-shopping"></i>
                </div>
                <h3 style="font-size: 1.25rem; margin-bottom: 10px;">2. Keranjang & Checkout</h3>
                <p style="color: var(--text-muted); font-size: 0.92rem; margin: 0;">
                    Tambahkan pesanan ke keranjang belanja cerdas, tentukan jumlah porsi, serta input alamat pengiriman dan instruksi kartu ucapan dengan mudah.
                </p>
            </div>

            <div class="card-box" style="text-align: center; padding: 32px 24px;">
                <div style="width: 64px; height: 64px; margin: 0 auto 16px; border-radius: 50%; background: #fef3c7; color: #d97706; display: flex; align-items: center; justify-content: center; font-size: 1.6rem;">
                    <i class="fa-solid fa-qrcode"></i>
                </div>
                <h3 style="font-size: 1.25rem; margin-bottom: 10px;">3. Pembayaran Digital</h3>
                <p style="color: var(--text-muted); font-size: 0.92rem; margin: 0;">
                    Dukungan multi-metode pembayaran modern melalui QRIS, Transfer Bank, hingga E-Wallet instan dengan konfirmasi otomatis dan invoice digital.
                </p>
            </div>

            <div class="card-box" style="text-align: center; padding: 32px 24px;">
                <div style="width: 64px; height: 64px; margin: 0 auto 16px; border-radius: 50%; background: #ecfdf5; color: #059669; display: flex; align-items: center; justify-content: center; font-size: 1.6rem;">
                    <i class="fa-solid fa-box-tissue"></i>
                </div>
                <h3 style="font-size: 1.25rem; margin-bottom: 10px;">4. Fresh Sameday Delivery</h3>
                <p style="color: var(--text-muted); font-size: 0.92rem; margin: 0;">
                    Kue dikemas secara higienis menggunakan thermal safety box dan ice-gel pack khusus demi menjaga kesegaran rasa sampai di depan pintu kamu.
                </p>
            </div>
        </div>

        <div class="card-box" style="background: white; border: 2px dashed var(--pink); border-radius: var(--radius-lg); padding: 30px; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 20px;">
            <div>
                <h3 style="font-size: 1.4rem; color: var(--pink-dark); margin-bottom: 6px;">
                    <i class="fa-solid fa-bell-concierge"></i> Butuh Pesanan Khusus atau Konsultasi Catering?
                </h3>
                <p style="color: var(--text-muted); margin: 0; font-size: 0.95rem;">
                    Kami siap melayani kebutuhan dessert table ulang tahun, wedding pastry package, dan corporate sweet gifts dengan kustomisasi logo & pita.
                </p>
            </div>
            <a href="https://api.whatsapp.com/send?phone=<?= format_wa_number(get_store_settings()['whatsapp']); ?>&text=<?= urlencode('Halo SweetPop, saya ingin konsultasi pesanan khusus/catering kue ♡'); ?>" target="_blank" class="btn-sweet btn-sweet-pink" style="white-space: nowrap;">
                <i class="fa-brands fa-whatsapp"></i> Hubungi Customer Care
            </a>
        </div>
    </div>
</section>

<!-- Newsletter Subscription -->
<section class="newsletter-section">
    <div class="container">
        <div class="newsletter-card">
            <span class="hero-badge" style="margin-bottom:12px;">✉ sweet letters in your inbox</span>
            <h2>Get the Secret Menu & Sweet Discounts</h2>
            <p>Berlangganan newsletter SweetPop untuk resep bulanan, promo rahasia, dan kabar drop rasa terbaru.</p>
            <form action="subscribe.php" method="POST" class="newsletter-form">
                <input type="email" name="email" placeholder="kamu@email.com" required>
                <button type="submit" class="btn-sweet btn-sweet-pink">
                    Subscribe ♡
                </button>
            </form>
        </div>
    </div>
</section>
