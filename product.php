<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    header("Location: store.php");
    exit;
}

$stmt = mysqli_prepare($con, "
    SELECT p.*, c.cat_title 
    FROM products p 
    INNER JOIN categories c ON p.product_cat = c.cat_id 
    WHERE p.product_id = ?
");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$product = mysqli_fetch_assoc($result);

if (!$product) {
    set_flash('danger', 'Produk tidak ditemukan atau telah diarsipkan.');
    header("Location: store.php");
    exit;
}

$pageTitle = $product['product_title'];

// Query Related Products in same category
$relStmt = mysqli_prepare($con, "
    SELECT p.*, c.cat_title 
    FROM products p 
    INNER JOIN categories c ON p.product_cat = c.cat_id 
    WHERE p.product_cat = ? AND p.product_id != ? AND p.product_status = 'active'
    LIMIT 4
");
mysqli_stmt_bind_param($relStmt, "ii", $product['product_cat'], $id);
mysqli_stmt_execute($relStmt);
$relatedResult = mysqli_stmt_get_result($relStmt);

include 'includes/header.php';
?>

<div class="container" style="padding: 30px 20px 80px;">
    <!-- Breadcrumbs -->
    <div style="margin-bottom: 24px; font-size: 0.9rem; color: var(--text-muted); display:flex; align-items:center; gap:8px;">
        <a href="index.php">Home</a>
        <i class="fa-solid fa-chevron-right" style="font-size:0.7rem;"></i>
        <a href="store.php">Shop</a>
        <i class="fa-solid fa-chevron-right" style="font-size:0.7rem;"></i>
        <a href="store.php?category=<?= $product['product_cat']; ?>"><?= htmlspecialchars($product['cat_title']); ?></a>
        <i class="fa-solid fa-chevron-right" style="font-size:0.7rem;"></i>
        <span style="color:var(--text); font-weight:600;"><?= htmlspecialchars($product['product_title']); ?></span>
    </div>

    <!-- Product Main Grid -->
    <div style="display:grid; grid-template-columns: 1fr 1.15fr; gap: 48px; margin-bottom: 60px;" class="hero-grid">
        <!-- Media Image Column -->
        <div style="background:#ffffff; border-radius:var(--radius-xl); border:1px solid var(--border); padding:24px; box-shadow:var(--shadow-sm); text-align:center;">
            <div style="border-radius:var(--radius-lg); overflow:hidden; background:#fff6fa;">
                <img src="product_images/<?= htmlspecialchars($product['product_image'] ?: 'strawberry-cloud.jpg'); ?>" alt="<?= htmlspecialchars($product['product_title']); ?>" style="width:100%; height:auto; max-height:480px; object-fit:cover; display:block;">
            </div>
        </div>

        <!-- Product Info Column -->
        <div>
            <span class="hero-badge" style="margin-bottom:12px;">
                <i class="fa-solid fa-tag"></i> <?= htmlspecialchars($product['cat_title']); ?>
            </span>
            <h1 style="font-size: 2.8rem; margin-bottom: 12px; line-height: 1.15;"><?= htmlspecialchars($product['product_title']); ?></h1>
            
            <div style="display:flex; align-items:center; gap:16px; margin-bottom:20px;">
                <div class="product-rating" style="margin-bottom:0; font-size:1rem;">
                    <i class="fa-solid fa-star"></i>
                    <i class="fa-solid fa-star"></i>
                    <i class="fa-solid fa-star"></i>
                    <i class="fa-solid fa-star"></i>
                    <i class="fa-solid fa-star"></i>
                    <span style="font-weight:700; color:var(--text); margin-left:4px;">5.0</span>
                </div>
                <span style="color:var(--border);">|</span>
                <span style="font-size:0.9rem; color:var(--text-muted);">Tersedia: <strong><?= $product['product_stock']; ?> pcs</strong></span>
                <span style="color:var(--border);">|</span>
                <?php if ($product['product_stock'] > 0): ?>
                    <span style="background:#ecfdf5; color:#047857; font-size:0.75rem; font-weight:700; padding:4px 10px; border-radius:var(--radius-pill);">IN STOCK</span>
                <?php else: ?>
                    <span style="background:#fef2f2; color:#b91c1c; font-size:0.75rem; font-weight:700; padding:4px 10px; border-radius:var(--radius-pill);">SOLD OUT</span>
                <?php endif; ?>
            </div>

            <div style="font-size: 2.4rem; font-family:'Baloo 2', cursive; font-weight:800; color:var(--pink-dark); margin-bottom:20px;">
                <?= format_rupiah($product['product_price']); ?>
            </div>

            <p style="color:var(--text-muted); font-size: 1.05rem; line-height: 1.7; margin-bottom: 30px;">
                <?= nl2br(htmlspecialchars($product['product_description'])); ?>
            </p>

            <!-- Add to Cart Form -->
            <form action="cart.php" method="POST" style="margin-bottom: 32px;">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="product_id" value="<?= $product['product_id']; ?>">
                
                <div style="display:flex; align-items:center; gap:16px; flex-wrap:wrap; margin-bottom:20px;">
                    <label style="font-weight:700; font-size:0.95rem;">Jumlah:</label>
                    <div class="qty-control" style="background:#ffffff; border-width:2px;">
                        <button type="button" class="qty-btn qty-minus">−</button>
                        <input type="number" name="quantity" class="qty-input" value="1" min="1" max="<?= max(1, $product['product_stock']); ?>">
                        <button type="button" class="qty-btn qty-plus">+</button>
                    </div>
                    <span style="font-size:0.85rem; color:var(--text-muted);">Sisa stok <?= $product['product_stock']; ?> pcs</span>
                </div>

                <div style="display:flex; gap:16px; flex-wrap:wrap;">
                    <button type="submit" class="btn-sweet btn-sweet-pink" <?= $product['product_stock'] <= 0 ? 'disabled style="opacity:0.6; cursor:not-allowed;"' : ''; ?>>
                        <i class="fa-solid fa-bag-shopping"></i> Tambah ke Keranjang
                    </button>
                    <a href="checkout.php?direct_id=<?= $product['product_id']; ?>" class="btn-sweet btn-sweet-blue" <?= $product['product_stock'] <= 0 ? 'style="pointer-events:none; opacity:0.6;"' : ''; ?>>
                        Beli Langsung ♡
                    </a>
                </div>
            </form>

            <!-- Features Quick Box -->
            <div style="background:#ffffff; border-radius:var(--radius-md); border:1px solid var(--border); padding:20px; display:grid; grid-template-columns:1fr 1fr; gap:14px; font-size:0.88rem;">
                <div><i class="fa-solid fa-temperature-arrow-down" style="color:var(--blue-dark); margin-right:6px;"></i> Simpan suhu chiller 4°C</div>
                <div><i class="fa-regular fa-clock" style="color:var(--pink); margin-right:6px;"></i> Tahan 3-4 hari di kulkas</div>
                <div><i class="fa-solid fa-box-heart" style="color:var(--pink-dark); margin-right:6px;"></i> Free aesthetic box & ribbon</div>
                <div><i class="fa-solid fa-leaf" style="color:#10b981; margin-right:6px;"></i> 100% Halal Ingredients</div>
            </div>
        </div>
    </div>

    <!-- Related Products -->
    <?php if ($relatedResult && mysqli_num_rows($relatedResult) > 0): ?>
        <div style="margin-top: 60px; padding-top: 40px; border-top: 1px solid var(--border);">
            <h3 style="font-size:2rem; margin-bottom: 24px;">Kamu Mungkin Juga Suka ♡</h3>
            <div class="products-grid">
                <?php while ($rel = mysqli_fetch_assoc($relatedResult)): ?>
                    <div class="product-card">
                        <a href="product.php?id=<?= $rel['product_id']; ?>" class="product-media">
                            <img src="product_images/<?= htmlspecialchars($rel['product_image'] ?: 'strawberry-cloud.jpg'); ?>" alt="<?= htmlspecialchars($rel['product_title']); ?>">
                        </a>
                        <div class="product-details">
                            <div class="product-cat"><?= htmlspecialchars($rel['cat_title']); ?></div>
                            <h3 class="product-title">
                                <a href="product.php?id=<?= $rel['product_id']; ?>"><?= htmlspecialchars($rel['product_title']); ?></a>
                            </h3>
                            <div class="product-footer">
                                <div class="product-price"><?= format_rupiah($rel['product_price']); ?></div>
                                <button class="btn-add-cart" data-id="<?= $rel['product_id']; ?>" data-title="<?= htmlspecialchars($rel['product_title']); ?>" title="Add to Bag">
                                    <i class="fa-solid fa-plus"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
