<?php
$pageTitle = "Kelola Kategori";
include 'header.php';

$errors = [];

// Handle Add Category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $cat_title = trim($_POST['cat_title'] ?? '');
    if ($cat_title === '') {
        $errors[] = "Nama kategori tidak boleh kosong.";
    } else {
        $stmt = mysqli_prepare($con, "INSERT INTO categories (cat_title) VALUES (?)");
        mysqli_stmt_bind_param($stmt, "s", $cat_title);
        if (mysqli_stmt_execute($stmt)) {
            set_flash('success', "Kategori '$cat_title' berhasil ditambahkan.");
            header("Location: categories.php");
            exit;
        } else {
            $errors[] = "Gagal menambah kategori: " . mysqli_error($con);
        }
    }
}

// Handle Edit Category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $cat_id = filter_input(INPUT_POST, 'cat_id', FILTER_VALIDATE_INT);
    $cat_title = trim($_POST['cat_title'] ?? '');
    if (!$cat_id || $cat_title === '') {
        $errors[] = "ID atau nama kategori tidak valid.";
    } else {
        $stmt = mysqli_prepare($con, "UPDATE categories SET cat_title = ? WHERE cat_id = ?");
        mysqli_stmt_bind_param($stmt, "si", $cat_title, $cat_id);
        if (mysqli_stmt_execute($stmt)) {
            set_flash('success', "Kategori berhasil diperbarui menjadi '$cat_title'.");
            header("Location: categories.php");
            exit;
        } else {
            $errors[] = "Gagal memperbarui kategori: " . mysqli_error($con);
        }
    }
}

// Handle Delete Category - Move Products to another category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_move') {
    $catId = filter_input(INPUT_POST, 'cat_id', FILTER_VALIDATE_INT);
    $targetCatId = filter_input(INPUT_POST, 'target_cat_id', FILTER_VALIDATE_INT);

    if ($catId && $targetCatId && $catId !== $targetCatId) {
        $r1 = mysqli_query($con, "SELECT cat_title FROM categories WHERE cat_id = $catId");
        $srcTitle = mysqli_fetch_assoc($r1)['cat_title'] ?? 'Kategori';
        $r2 = mysqli_query($con, "SELECT cat_title FROM categories WHERE cat_id = $targetCatId");
        $tgtTitle = mysqli_fetch_assoc($r2)['cat_title'] ?? 'Kategori Tujuan';

        // Count affected products
        $chk = mysqli_query($con, "SELECT COUNT(*) as cnt FROM products WHERE product_cat = $catId");
        $cnt = (int)(mysqli_fetch_assoc($chk)['cnt'] ?? 0);

        // Reassign products to target category
        $moveStmt = mysqli_prepare($con, "UPDATE products SET product_cat = ? WHERE product_cat = ?");
        mysqli_stmt_bind_param($moveStmt, "ii", $targetCatId, $catId);
        mysqli_stmt_execute($moveStmt);

        // Delete source category
        $delStmt = mysqli_prepare($con, "DELETE FROM categories WHERE cat_id = ?");
        mysqli_stmt_bind_param($delStmt, "i", $catId);
        mysqli_stmt_execute($delStmt);

        set_flash('success', "Kategori '$srcTitle' berhasil dihapus. $cnt produk telah dipindahkan ke kategori '$tgtTitle'.");
    } else {
        set_flash('danger', "Gagal memproses: Kategori tujuan tidak valid.");
    }
    header("Location: categories.php");
    exit;
}

// Handle Delete Category - Direct or Force Cascade
if (isset($_GET['delete_id'])) {
    $delId = filter_input(INPUT_GET, 'delete_id', FILTER_VALIDATE_INT);
    $force = isset($_GET['force']) && $_GET['force'] == '1';

    if ($delId) {
        $chk = mysqli_query($con, "SELECT cat_title, (SELECT COUNT(*) FROM products WHERE product_cat = $delId) as cnt FROM categories WHERE cat_id = $delId");
        $catData = mysqli_fetch_assoc($chk);

        if ($catData) {
            $catTitle = $catData['cat_title'];
            $cnt = (int)$catData['cnt'];

            if ($cnt > 0 && !$force) {
                set_flash('warning', "Kategori '$catTitle' memiliki $cnt produk terhubung. Silakan gunakan opsi pindahkan produk atau hapus permanen.");
            } else {
                // Remove orphaned cart rows if any
                mysqli_query($con, "DELETE FROM cart WHERE p_id IN (SELECT product_id FROM products WHERE product_cat = $delId)");
                
                // Delete category (cascades to products in DB)
                mysqli_query($con, "DELETE FROM categories WHERE cat_id = $delId");

                if ($cnt > 0) {
                    set_flash('success', "Kategori '$catTitle' beserta $cnt produk di dalamnya berhasil dihapus.");
                } else {
                    set_flash('success', "Kategori '$catTitle' berhasil dihapus.");
                }
            }
        }
    }
    header("Location: categories.php");
    exit;
}

// Fetch edit target if requested
$editCat = null;
if (isset($_GET['edit_id'])) {
    $editId = filter_input(INPUT_GET, 'edit_id', FILTER_VALIDATE_INT);
    if ($editId) {
        $eRes = mysqli_query($con, "SELECT * FROM categories WHERE cat_id = $editId");
        $editCat = mysqli_fetch_assoc($eRes);
    }
}

// Fetch All Categories with product counts
$catQuery = "
    SELECT c.*, COUNT(p.product_id) as total_products 
    FROM categories c 
    LEFT JOIN products p ON c.cat_id = p.product_cat 
    GROUP BY c.cat_id 
    ORDER BY c.cat_id ASC
";
$categoriesRes = mysqli_query($con, $catQuery);
$categoriesList = [];
while ($row = mysqli_fetch_assoc($categoriesRes)) {
    $categoriesList[] = $row;
}
?>

<style>
.btn-action-edit, .btn-action-delete {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: none;
    cursor: pointer;
    font-size: 0.9rem;
    transition: all 0.2s ease;
    text-decoration: none;
}
.btn-action-edit {
    background: #eff6ff;
    color: #2563eb;
}
.btn-action-edit:hover {
    background: #2563eb;
    color: #ffffff;
    transform: translateY(-2px);
}
.btn-action-delete {
    background: #fff1f2;
    color: #e11d48;
}
.btn-action-delete:hover {
    background: #e11d48;
    color: #ffffff;
    transform: translateY(-2px);
}
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(15, 23, 42, 0.55);
    backdrop-filter: blur(4px);
    z-index: 99999;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 16px;
    animation: fadeInModal 0.2s ease;
}
@keyframes fadeInModal {
    from { opacity: 0; }
    to { opacity: 1; }
}
.modal-content-card {
    background: #ffffff;
    border-radius: 20px;
    max-width: 520px;
    width: 100%;
    padding: 28px;
    box-shadow: 0 20px 45px rgba(0,0,0,0.2);
    position: relative;
}
</style>

<div class="admin-header">
    <div>
        <span class="hero-badge" style="margin-bottom:6px;">✦ Klasifikasi Dessert</span>
        <h1 style="font-size: 2.2rem;">Kelola Kategori Produk</h1>
        <p style="color:var(--text-muted); margin-top:4px;">Tambah, ubah nama kategori, atau hapus kategori dengan pemindahan produk yang aman.</p>
    </div>
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

<div style="display:grid; grid-template-columns:1fr 1.6fr; gap:32px;" class="cart-grid">
    <!-- Left: Add or Edit Category Form -->
    <div class="card-box" id="formCategoryCard">
        <?php if ($editCat): ?>
            <!-- Mode Edit Kategori -->
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; border-bottom:1px solid var(--border); padding-bottom:10px;">
                <h3 style="font-size:1.3rem; margin:0; color:var(--blue-dark);">
                    <i class="fa-solid fa-pen-to-square"></i> Edit Kategori #<?= $editCat['cat_id']; ?>
                </h3>
                <a href="categories.php" class="btn-sweet btn-sweet-light" style="padding:4px 10px; font-size:0.8rem;">
                    Batal Edit
                </a>
            </div>
            <form action="categories.php" method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="cat_id" value="<?= $editCat['cat_id']; ?>">
                <div class="form-group">
                    <label class="form-label">Nama Kategori *</label>
                    <input type="text" name="cat_title" class="form-control" value="<?= htmlspecialchars($editCat['cat_title']); ?>" required autofocus>
                    <small style="color:var(--text-muted); display:block; margin-top:4px;">Perubahan nama akan langsung diterapkan pada semua produk terkait.</small>
                </div>
                <div style="display:flex; gap:10px;">
                    <button type="submit" class="btn-sweet btn-sweet-pink" style="flex:1;">
                        <i class="fa-solid fa-floppy-disk"></i> Simpan Perubahan
                    </button>
                    <a href="categories.php" class="btn-sweet btn-sweet-light" style="padding:10px 18px;">
                        Batal
                    </a>
                </div>
            </form>
        <?php else: ?>
            <!-- Mode Tambah Kategori -->
            <h3 style="font-size:1.3rem; margin-bottom:16px; border-bottom:1px solid var(--border); padding-bottom:10px;">
                <i class="fa-solid fa-plus-circle" style="color:var(--pink);"></i> Tambah Kategori Baru
            </h3>
            <form action="categories.php" method="POST">
                <input type="hidden" name="action" value="add">
                <div class="form-group">
                    <label class="form-label">Nama Kategori *</label>
                    <input type="text" name="cat_title" class="form-control" placeholder="Contoh: Cupcakes / Tart" required>
                </div>
                <button type="submit" class="btn-sweet btn-sweet-pink" style="width:100%;">
                    Tambah Kategori ♡
                </button>
            </form>
        <?php endif; ?>
    </div>

    <!-- Right: Categories List -->
    <div class="card-box">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; border-bottom:1px solid var(--border); padding-bottom:10px;">
            <h3 style="font-size:1.3rem; margin:0;">
                <i class="fa-solid fa-tags" style="color:var(--blue-dark);"></i> Daftar Kategori Aktif
            </h3>
            <span style="font-size:0.85rem; color:var(--text-muted); font-weight:600;">
                Total: <?= count($categoriesList); ?> Kategori
            </span>
        </div>
        <table class="cart-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nama Kategori</th>
                    <th>Total Produk</th>
                    <th style="text-align:center;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($categoriesList)): ?>
                    <tr>
                        <td colspan="4" style="text-align:center; padding:30px; color:var(--text-muted);">
                            Belum ada kategori yang ditambahkan.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($categoriesList as $c): ?>
                        <tr>
                            <td><strong>#<?= $c['cat_id']; ?></strong></td>
                            <td><strong style="color:var(--text);"><?= htmlspecialchars($c['cat_title']); ?></strong></td>
                            <td>
                                <span style="background:var(--blue-light); color:var(--blue-dark); padding:4px 10px; border-radius:8px; font-weight:700; font-size:0.8rem;">
                                    <?= $c['total_products']; ?> items
                                </span>
                            </td>
                            <td style="text-align:center;">
                                <div style="display:inline-flex; gap:8px; align-items:center; justify-content:center;">
                                    <!-- EDIT BUTTON -->
                                    <a href="categories.php?edit_id=<?= $c['cat_id']; ?>" class="btn-action-edit" title="Edit Nama Kategori">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </a>
                                    
                                    <!-- DELETE BUTTON -->
                                    <?php if ($c['total_products'] > 0): ?>
                                        <button type="button" class="btn-action-delete" onclick="openDeleteModal(<?= $c['cat_id']; ?>, '<?= htmlspecialchars(addslashes($c['cat_title'])); ?>', <?= $c['total_products']; ?>)" title="Hapus Kategori (Memiliki <?= $c['total_products']; ?> produk)">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    <?php else: ?>
                                        <a href="categories.php?delete_id=<?= $c['cat_id']; ?>" class="btn-action-delete" onclick="return confirm('Yakin ingin menghapus kategori \'<?= htmlspecialchars(addslashes($c['cat_title'])); ?>\'?');" title="Hapus Kategori">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL HAPUS KATEGORI BERISI PRODUK -->
<div id="deleteCategoryModal" class="modal-overlay" style="display:none;">
    <div class="modal-content-card">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; border-bottom:1px solid #f1f5f9; padding-bottom:10px;">
            <h3 style="margin:0; font-size:1.25rem; color:#e11d48; display:flex; align-items:center; gap:8px;">
                <i class="fa-solid fa-triangle-exclamation"></i> Konfirmasi Hapus Kategori
            </h3>
            <button type="button" onclick="closeDeleteModal()" style="background:none; border:none; font-size:1.4rem; cursor:pointer; color:#94a3b8;">&times;</button>
        </div>
        
        <p style="margin-bottom:16px; color:#475569; font-size:0.92rem; line-height:1.5;">
            Kategori <strong id="modalCatTitle" style="color:#0f172a;"></strong> saat ini masih terhubung dengan <strong id="modalProdCount" style="color:#e11d48;"></strong> produk dessert di katalog.
        </p>

        <!-- Opsi 1: Pindahkan Produk -->
        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:14px; padding:16px; margin-bottom:14px;">
            <h4 style="margin:0 0 6px; font-size:0.95rem; color:#1e293b; display:flex; align-items:center; gap:6px;">
                <i class="fa-solid fa-arrows-rotate" style="color:#3b82f6;"></i> Opsi 1: Pindahkan Produk ke Kategori Lain (Disarankan)
            </h4>
            <p style="margin:0 0 10px; font-size:0.82rem; color:#64748b;">
                Produk tetap aman di etalase dan dipindahkan ke kategori tujuan yang Anda pilih:
            </p>
            <form action="categories.php" method="POST" id="formMoveDelete">
                <input type="hidden" name="action" value="delete_move">
                <input type="hidden" name="cat_id" id="modalMoveCatId" value="">
                <div style="display:flex; gap:8px;">
                    <select name="target_cat_id" id="modalTargetCatSelect" class="form-control" style="font-size:0.9rem;" required>
                        <!-- Options generated by JS -->
                    </select>
                    <button type="submit" class="btn-sweet btn-sweet-pink" style="white-space:nowrap; padding:8px 16px; font-size:0.85rem;">
                        Pindahkan & Hapus
                    </button>
                </div>
            </form>
        </div>

        <!-- Opsi 2: Hapus Permanen Cascade -->
        <div style="background:#fff1f2; border:1px solid #fecdd3; border-radius:14px; padding:16px;">
            <h4 style="margin:0 0 6px; font-size:0.95rem; color:#9f1239; display:flex; align-items:center; gap:6px;">
                <i class="fa-solid fa-trash-can" style="color:#e11d48;"></i> Opsi 2: Hapus Kategori Beserta Seluruh Produknya
            </h4>
            <p style="margin:0 0 10px; font-size:0.82rem; color:#be123c;">
                Seluruh produk dessert di dalam kategori ini akan dihapus permanen dari sistem toko.
            </p>
            <a id="modalForceDeleteBtn" href="#" class="btn-sweet" style="background:#e11d48; color:#fff; display:block; text-align:center; padding:10px; font-size:0.85rem;" onclick="return confirm('PERINGATAN TERAKHIR: Seluruh produk dalam kategori ini akan dihapus permanen dan tidak bisa dikembalikan. Lanjutkan?');">
                <i class="fa-solid fa-skull-crossbones"></i> Hapus Permanen Kategori & Seluruh Produk
            </a>
        </div>
        
        <div style="margin-top:16px; text-align:right;">
            <button type="button" onclick="closeDeleteModal()" class="btn-sweet btn-sweet-light" style="padding:8px 20px;">
                Batal
            </button>
        </div>
    </div>
</div>

<script>
// Data master kategori untuk dropdown modal
const allCategories = <?= json_encode(array_map(function($c) {
    return ['id' => (int)$c['cat_id'], 'title' => $c['cat_title']];
}, $categoriesList)); ?>;

function openDeleteModal(catId, catTitle, prodCount) {
    document.getElementById('modalCatTitle').textContent = catTitle;
    document.getElementById('modalProdCount').textContent = prodCount + ' items';
    document.getElementById('modalMoveCatId').value = catId;
    
    // Set URL untuk force delete
    document.getElementById('modalForceDeleteBtn').href = 'categories.php?delete_id=' + catId + '&force=1';

    // Populate dropdown target kategori (kecuali kategori yang akan dihapus)
    const select = document.getElementById('modalTargetCatSelect');
    select.innerHTML = '';
    
    let hasTarget = false;
    allCategories.forEach(cat => {
        if (cat.id !== catId) {
            const opt = document.createElement('option');
            opt.value = cat.id;
            opt.textContent = '➡️ ' + cat.title;
            select.appendChild(opt);
            hasTarget = true;
        }
    });

    if (!hasTarget) {
        select.innerHTML = '<option value="">(Tidak ada kategori lain)</option>';
        select.disabled = true;
    } else {
        select.disabled = false;
    }

    document.getElementById('deleteCategoryModal').style.display = 'flex';
}

function closeDeleteModal() {
    document.getElementById('deleteCategoryModal').style.display = 'none';
}

// Tutup modal jika klik di luar card
window.addEventListener('click', function(e) {
    const modal = document.getElementById('deleteCategoryModal');
    if (e.target === modal) {
        closeDeleteModal();
    }
});

// Tutup modal jika tombol Esc ditekan
window.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeDeleteModal();
    }
});
</script>

<?php include 'footer.php'; ?>
