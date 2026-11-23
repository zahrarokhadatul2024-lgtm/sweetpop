<?php
$pageTitle = "Tambah Produk Baru";
include 'header.php';

$errors = [];

// Fetch Categories
$catsRes = mysqli_query($con, "SELECT * FROM categories ORDER BY cat_title ASC");

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

    $imageName = 'strawberry-cloud.jpg'; // default fallback
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
        $fileTmp = $_FILES['product_image']['tmp_name'];
        $fileName = time() . '_' . preg_replace("/[^a-zA-Z0-9._-]/", "", basename($_FILES['product_image']['name']));
        $targetPath = __DIR__ . '/../product_images/' . $fileName;

        if (move_uploaded_file($fileTmp, $targetPath)) {
            $imageName = $fileName;
        }
    }

    if (empty($errors)) {
        $brandId = 1; // Default Sweetpop Signature
        $stmt = mysqli_prepare($con, "
            INSERT INTO products (product_cat, product_brand, product_title, product_price, product_description, product_image, product_stock, product_status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        mysqli_stmt_bind_param($stmt, "iisdssis", $category, $brandId, $title, $price, $description, $imageName, $stock, $status);

        if (mysqli_stmt_execute($stmt)) {
            set_flash('success', "Produk '$title' berhasil ditambahkan ke katalog!");
            header("Location: products.php");
            exit;
        } else {
            $errors[] = "Gagal menyimpan ke database: " . mysqli_error($con);
        }
    }
}
?>

<div class="admin-header">
    <div>
        <span class="hero-badge" style="margin-bottom:6px;">✦ Produk Baru</span>
        <h1 style="font-size: 2.2rem;">Tambah Dessert Baru</h1>
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
    <form action="add_product.php" method="POST" enctype="multipart/form-data">
        <div class="form-group">
            <label class="form-label">Nama Produk Dessert *</label>
            <input type="text" name="product_title" class="form-control" placeholder="Contoh: Blueberry Cheesecake Dream" value="<?= htmlspecialchars($_POST['product_title'] ?? ''); ?>" required>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Kategori *</label>
                <select name="product_cat" class="form-control" required>
                    <option value="">-- Pilih Kategori --</option>
                    <?php 
                    mysqli_data_seek($catsRes, 0);
                    while ($c = mysqli_fetch_assoc($catsRes)): 
                    ?>
                        <option value="<?= $c['cat_id']; ?>" <?= (isset($_POST['product_cat']) && $_POST['product_cat'] == $c['cat_id']) ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($c['cat_title']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Harga (Rupiah) *</label>
                <input type="number" name="product_price" class="form-control" placeholder="Contoh: 45000" value="<?= htmlspecialchars($_POST['product_price'] ?? ''); ?>" required min="0">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Jumlah Stok (Pcs) *</label>
                <input type="number" name="product_stock" class="form-control" placeholder="20" value="<?= htmlspecialchars($_POST['product_stock'] ?? '20'); ?>" required min="0">
            </div>

            <div class="form-group">
                <label class="form-label">Status Produk *</label>
                <select name="product_status" class="form-control">
                    <option value="active" <?= (isset($_POST['product_status']) && $_POST['product_status'] == 'active') ? 'selected' : ''; ?>>Active (Tampil di Toko)</option>
                    <option value="inactive" <?= (isset($_POST['product_status']) && $_POST['product_status'] == 'inactive') ? 'selected' : ''; ?>>Inactive (Disembunyikan)</option>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">Deskripsi & Detail Dessert</label>
            <textarea name="product_description" class="form-control" rows="4" placeholder="Ceritakan rasa, layer cream, tekstur, dan kelezatannya..."><?= htmlspecialchars($_POST['product_description'] ?? ''); ?></textarea>
        </div>

        <div class="form-group">
            <label class="form-label">Foto Produk (JPG / PNG / WebP)</label>
            <input type="file" name="product_image" class="form-control" accept="image/*">
            <small style="color:var(--text-muted);">Foto akan disimpan di folder <code>product_images/</code>.</small>
        </div>

        <button type="submit" class="btn-sweet btn-sweet-pink" style="margin-top:14px; font-size:1.05rem;">
            <i class="fa-solid fa-cloud-arrow-up"></i> Simpan Produk ke Toko ♡
        </button>
    </form>
</div>

<?php include 'footer.php'; ?>
