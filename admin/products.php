<?php
$pageTitle = "Kelola Produk";
include 'header.php';

$search = trim($_GET['search'] ?? '');
$catFilter = filter_input(INPUT_GET, 'cat', FILTER_VALIDATE_INT);

// Build Query
$sql = "
    SELECT p.*, c.cat_title 
    FROM products p 
    INNER JOIN categories c ON p.product_cat = c.cat_id 
    WHERE 1=1
";
$params = [];
$types = "";

if ($search !== '') {
    $sql .= " AND p.product_title LIKE ?";
    $types .= "s";
    $params[] = '%' . $search . '%';
}

if ($catFilter) {
    $sql .= " AND p.product_cat = ?";
    $types .= "i";
    $params[] = $catFilter;
}

$sql .= " ORDER BY p.created_at DESC";

$stmt = mysqli_prepare($con, $sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$productsRes = mysqli_stmt_get_result($stmt);

// Categories for filter
$catsRes = mysqli_query($con, "SELECT * FROM categories ORDER BY cat_title ASC");
?>

<div class="admin-header">
    <div>
        <span class="hero-badge" style="margin-bottom:6px;">✦ Katalog SweetPop</span>
        <h1 style="font-size: 2.2rem;">Daftar Produk Dessert</h1>
    </div>
    <a href="add_product.php" class="btn-sweet btn-sweet-pink">
        <i class="fa-solid fa-plus"></i> Tambah Produk Baru
    </a>
</div>

<!-- Filters -->
<div class="card-box" style="margin-bottom: 24px; padding: 18px 24px;">
    <form method="GET" action="products.php" style="display:flex; gap:16px; flex-wrap:wrap; align-items:center;">
        <input type="text" name="search" class="form-control" placeholder="Cari nama produk..." value="<?= htmlspecialchars($search); ?>" style="max-width:300px;">
        <select name="cat" class="form-control" style="max-width:200px;" onchange="this.form.submit()">
            <option value="">Semua Kategori</option>
            <?php while ($c = mysqli_fetch_assoc($catsRes)): ?>
                <option value="<?= $c['cat_id']; ?>" <?= $catFilter == $c['cat_id'] ? 'selected' : ''; ?>><?= htmlspecialchars($c['cat_title']); ?></option>
            <?php endwhile; ?>
        </select>
        <button type="submit" class="btn-sweet btn-sweet-pink" style="padding:10px 20px;">Cari</button>
        <?php if ($search !== '' || $catFilter): ?>
            <a href="products.php" class="btn-sweet btn-sweet-light" style="padding:10px 16px;">Reset</a>
        <?php endif; ?>
    </form>
</div>

<!-- Product Table -->
<div class="card-box">
    <div style="overflow-x:auto;">
        <table class="cart-table">
            <thead>
                <tr>
                    <th>Gambar</th>
                    <th>Nama Dessert</th>
                    <th>Kategori</th>
                    <th>Harga</th>
                    <th>Stok</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (mysqli_num_rows($productsRes) == 0): ?>
                    <tr>
                        <td colspan="7" style="text-align:center; padding:30px; color:var(--text-muted);">Tidak ada produk yang ditemukan.</td>
                    </tr>
                <?php else: ?>
                    <?php while ($p = mysqli_fetch_assoc($productsRes)): ?>
                        <tr>
                            <td>
                                <img src="../product_images/<?= htmlspecialchars($p['product_image'] ?: 'strawberry-cloud.jpg'); ?>" alt="<?= htmlspecialchars($p['product_title']); ?>" style="width:60px; height:60px; border-radius:var(--radius-md); object-fit:cover;">
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($p['product_title']); ?></strong>
                                <small style="display:block; color:var(--text-muted); max-width:240px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                                    <?= htmlspecialchars($p['product_description']); ?>
                                </small>
                            </td>
                            <td><span style="background:var(--pink-light); color:var(--pink-dark); padding:4px 10px; border-radius:8px; font-weight:700; font-size:0.8rem;"><?= htmlspecialchars($p['cat_title']); ?></span></td>
                            <td><strong style="color:var(--pink-dark);"><?= format_rupiah($p['product_price']); ?></strong></td>
                            <td>
                                <?php if ($p['product_stock'] <= 5): ?>
                                    <span style="color:#ef4444; font-weight:700;"><?= $p['product_stock']; ?> pcs (Low)</span>
                                <?php else: ?>
                                    <span><?= $p['product_stock']; ?> pcs</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($p['product_status'] === 'active'): ?>
                                    <span style="background:#ecfdf5; color:#047857; padding:4px 10px; border-radius:12px; font-weight:700; font-size:0.75rem;">ACTIVE</span>
                                <?php else: ?>
                                    <span style="background:#fef2f2; color:#b91c1c; padding:4px 10px; border-radius:12px; font-weight:700; font-size:0.75rem;">INACTIVE</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="display:flex; gap:8px;">
                                    <a href="edit_product.php?id=<?= $p['product_id']; ?>" class="btn-sweet btn-sweet-light" style="padding:6px 12px; font-size:0.82rem;" title="Edit">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </a>
                                    <a href="delete_product.php?id=<?= $p['product_id']; ?>" class="btn-remove" style="width:32px; height:32px; display:inline-flex; align-items:center; justify-content:center;" onclick="return confirm('Yakin ingin menghapus produk <?= htmlspecialchars(addslashes($p['product_title'])); ?>?');" title="Hapus">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'footer.php'; ?>
