<?php
$pageTitle = "Edit Produk";
include 'header.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    header("Location: products.php");
    exit;
}

// Fetch Product Data
$stmt = mysqli_prepare($con, "SELECT * FROM products WHERE product_id = ?");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$product = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$product) {
    set_flash('danger', 'Produk tidak ditemukan.');
    header("Location: products.php");
    exit;
}

$catsRes = mysqli_query($con, "SELECT * FROM categories ORDER BY cat_title ASC");
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title       = trim($_POST['product_title'] ?? '');
    $category    = filter_input(INPUT_POST, 'product_cat', FILTER_VALIDATE_INT);
    $price       = filter_input(INPUT_POST, 'product_price', FILTER_VALIDATE_FLOAT);
    $stock       = filter_input(INPUT_POST, 'product_stock', FILTER_VALIDATE_INT) ?? 0;
    $description = trim($_POST['product_description'] ?? '');
    $status      = $_POST['product_status'] ?? 'active';

    if ($title === '') $errors[] = "Nama produk wajib diisi.";
    if (!$category) $errors[] = "Kategori produk wajib dipilih.";
    if ($price === false || $price < 0) $errors[] = "Harga produk tidak valid.";

    $imageName = $product['product_image'];
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
        $fileTmp = $_FILES['product_image']['tmp_name'];
        $fileName = time() . '_' . preg_replace("/[^a-zA-Z0-9._-]/", "", basename($_FILES['product_image']['name']));
        $targetPath = __DIR__ . '/../product_images/' . $fileName;

        if (move_uploaded_file($fileTmp, $targetPath)) {
            $imageName = $fileName;
        }
    }

    if (empty($errors)) {
        $upStmt = mysqli_prepare($con, "
            UPDATE products 
            SET product_cat = ?, product_title = ?, product_price = ?, product_description = ?, product_image = ?, product_stock = ?, product_status = ?
            WHERE product_id = ?
        ");
        mysqli_stmt_bind_param($upStmt, "isdssisi", $category, $title, $price, $description, $imageName, $stock, $status, $id);

        if (mysqli_stmt_execute($upStmt)) {
            set_flash('success', "Produk '$title' berhasil diperbarui!");
            header("Location: products.php");
            exit;
        } else {
            $errors[] = "Gagal memperbarui produk: " . mysqli_error($con);
        }
    }
}
?>

<div class="admin-header">
    <div>
        <span class="hero-badge" style="margin-bottom:6px;">✦ Edit Produk</span>
        <h1 style="font-size: 2.2rem;">Edit Dessert #<?= $product['product_id']; ?></h1>
    </div>
    <a href="products.php" class="btn-sweet btn-sweet-light">
        <i class="fa-solid fa-arrow-left"></i> Kembali ke Daftar
    </a>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <i class="fa-solid fa-triangle-exclamation"></i>
        <div>
            <?php foreach ($errors as $err): ?>
                <div>• <?= htmlspecialchars($err); ?></div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<div class="card-box" style="max-width: 800px;">
    <form action="edit_product.php?id=<?= $id; ?>" method="POST" enctype="multipart/form-data">
        <div class="form-group">
            <label class="form-label">Nama Produk Dessert *</label>
            <input type="text" name="product_title" class="form-control" value="<?= htmlspecialchars($_POST['product_title'] ?? $product['product_title']); ?>" required>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Kategori *</label>
                <select name="product_cat" class="form-control" required>
                    <?php 
                    mysqli_data_seek($catsRes, 0);
                    $selectedCat = $_POST['product_cat'] ?? $product['product_cat'];
                    while ($c = mysqli_fetch_assoc($catsRes)): 
                    ?>
                        <option value="<?= $c['cat_id']; ?>" <?= $selectedCat == $c['cat_id'] ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($c['cat_title']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Harga (Rupiah) *</label>
                <input type="number" name="product_price" class="form-control" value="<?= htmlspecialchars($_POST['product_price'] ?? $product['product_price']); ?>" required min="0">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Jumlah Stok (Pcs) *</label>
                <input type="number" name="product_stock" class="form-control" value="<?= htmlspecialchars($_POST['product_stock'] ?? $product['product_stock']); ?>" required min="0">
            </div>

            <div class="form-group">
                <label class="form-label">Status Produk *</label>
                <select name="product_status" class="form-control">
                    <?php $stat = $_POST['product_status'] ?? $product['product_status']; ?>
                    <option value="active" <?= $stat == 'active' ? 'selected' : ''; ?>>Active (Tampil di Toko)</option>
                    <option value="inactive" <?= $stat == 'inactive' ? 'selected' : ''; ?>>Inactive (Disembunyikan)</option>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">Deskripsi & Detail Dessert</label>
            <textarea name="product_description" class="form-control" rows="4"><?= htmlspecialchars($_POST['product_description'] ?? $product['product_description']); ?></textarea>
        </div>

        <div class="form-group">
            <label class="form-label">Foto Produk Saat Ini</label>
            <div style="display:flex; align-items:center; gap:16px; margin-bottom:10px;">
                <img src="../product_images/<?= htmlspecialchars($product['product_image'] ?: 'strawberry-cloud.jpg'); ?>" style="width:80px; height:80px; border-radius:var(--radius-md); object-fit:cover; border:1px solid var(--border);">
                <div>
                    <strong><?= htmlspecialchars($product['product_image']); ?></strong>
                    <small style="color:var(--text-muted); display:block;">Pilih file baru jika ingin mengganti gambar produk.</small>
                </div>
            </div>
            <input type="file" name="product_image" class="form-control" accept="image/*">
        </div>

        <button type="submit" class="btn-sweet btn-sweet-pink" style="margin-top:14px; font-size:1.05rem;">
            <i class="fa-solid fa-floppy-disk"></i> Simpan Perubahan ♡
        </button>
    </form>
</div>

<?php include 'footer.php'; ?>
