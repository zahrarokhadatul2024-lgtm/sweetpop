<?php
$pageTitle = "Shop All Sweet Treats";
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';

// Filter parameters
$search = trim($_GET['search'] ?? '');
$category = filter_input(INPUT_GET, 'category', FILTER_VALIDATE_INT);
$sort = $_GET['sort'] ?? 'newest';

// Build SQL Query
$sql = "
    SELECT p.*, c.cat_title 
    FROM products p
    INNER JOIN categories c ON p.product_cat = c.cat_id
    WHERE p.product_status = 'active'
";

$types = "";
$params = [];

if ($search !== '') {
    $sql .= " AND (p.product_title LIKE ? OR p.product_description LIKE ?)";
    $types .= "ss";
    $searchWild = '%' . $search . '%';
    $params[] = $searchWild;
    $params[] = $searchWild;
}

if ($category) {
    $sql .= " AND p.product_cat = ?";
    $types .= "i";
    $params[] = $category;
}

// Sorting
switch ($sort) {
    case 'price_asc':
        $sql .= " ORDER BY p.product_price ASC";
        break;
    case 'price_desc':
        $sql .= " ORDER BY p.product_price DESC";
        break;
    case 'name':
        $sql .= " ORDER BY p.product_title ASC";
        break;
    default:
        $sql .= " ORDER BY p.created_at DESC, p.product_id DESC";
        break;
}

$stmt = mysqli_prepare($con, $sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$productsResult = mysqli_stmt_get_result($stmt);

// Category Title Helper
$activeCatTitle = "All Sweet Treats";
if ($category) {
    $cStmt = mysqli_prepare($con, "SELECT cat_title FROM categories WHERE cat_id = ?");
    mysqli_stmt_bind_param($cStmt, "i", $category);
    mysqli_stmt_execute($cStmt);
    $cRes = mysqli_stmt_get_result($cStmt);
    if ($cRow = mysqli_fetch_assoc($cRes)) {
        $activeCatTitle = $cRow['cat_title'];
    }
}

include 'includes/header.php';
?>

<div class="container" style="padding: 40px 20px 80px;">
    <!-- Store Header -->
    <div style="margin-bottom: 36px; display:flex; justify-content:space-between; align-items:flex-end; flex-wrap:wrap; gap:20px;">
        <div>
            <span class="section-tag">SweetPop Store Collection ✿</span>
            <h1 style="font-size:2.6rem;"><?= htmlspecialchars($activeCatTitle); ?></h1>
            <?php if ($search !== ''): ?>
                <p style="color:var(--text-muted);">Menampilkan hasil pencarian untuk: "<strong><?= htmlspecialchars($search); ?></strong>"</p>
            <?php endif; ?>
        </div>

        <!-- Sort Form -->
        <form method="GET" action="store.php" style="display:flex; align-items:center; gap:12px;">
            <?php if ($search !== ''): ?>
                <input type="hidden" name="search" value="<?= htmlspecialchars($search); ?>">
            <?php endif; ?>
            <?php if ($category): ?>
                <input type="hidden" name="category" value="<?= $category; ?>">
            <?php endif; ?>
            <label style="font-size:0.9rem; font-weight:600; color:var(--text-muted);">Urutkan:</label>
            <select name="sort" class="form-control" style="width: auto; padding: 8px 16px; border-radius: var(--radius-pill);" onchange="this.form.submit()">
                <option value="newest" <?= $sort == 'newest' ? 'selected' : ''; ?>>Terbaru</option>
                <option value="price_asc" <?= $sort == 'price_asc' ? 'selected' : ''; ?>>Harga: Rendah ke Tinggi</option>
                <option value="price_desc" <?= $sort == 'price_desc' ? 'selected' : ''; ?>>Harga: Tinggi ke Rendah</option>
                <option value="name" <?= $sort == 'name' ? 'selected' : ''; ?>>Nama A-Z</option>
            </select>
        </form>
    </div>

    <!-- Product Grid -->
    <div class="products-grid">
        <?php if ($productsResult && mysqli_num_rows($productsResult) > 0): ?>
            <?php while ($prod = mysqli_fetch_assoc($productsResult)): ?>
                <div class="product-card">
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
                        <p style="font-size:0.85rem; color:var(--text-muted); margin-bottom:12px; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden;">
                            <?= htmlspecialchars($prod['product_description']); ?>
                        </p>
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
        <?php else: ?>
            <div style="grid-column: 1 / -1; text-align:center; padding: 60px 20px; background:#fff; border-radius:var(--radius-lg); border:1px solid var(--border);">
                <div style="font-size: 3.5rem; margin-bottom: 12px;">🧁</div>
                <h3 style="font-size: 1.6rem; margin-bottom: 8px;">Tidak Ada Produk yang Ditemukan</h3>
                <p style="color:var(--text-muted); margin-bottom: 24px;">Coba gunakan kata kunci lain atau lihat seluruh koleksi manis kami.</p>
                <a href="store.php" class="btn-sweet btn-sweet-pink">Lihat Semua Produk</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
