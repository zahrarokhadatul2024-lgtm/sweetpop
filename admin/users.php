<?php
$pageTitle = "Data Pengguna";
include 'header.php';

// Handle Delete User if requested
if (isset($_GET['delete_id'])) {
    $delId = (int)$_GET['delete_id'];
    $stmt = mysqli_prepare($con, "DELETE FROM user_info WHERE user_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $delId);
    if (mysqli_stmt_execute($stmt)) {
        set_flash('success', "Data pengguna ID #$delId berhasil dihapus.");
    } else {
        set_flash('danger', "Gagal menghapus pengguna: " . mysqli_error($con));
    }
    header("Location: users.php");
    exit;
}

$search = trim($_GET['search'] ?? '');
$sql = "
    SELECT u.*, COUNT(o.order_id) as total_orders, COALESCE(SUM(o.total_amount), 0) as total_spent
    FROM user_info u
    LEFT JOIN orders o ON u.user_id = o.user_id
    WHERE 1=1
";
$params = [];
$types = "";

if ($search !== '') {
    $sql .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.mobile LIKE ?)";
    $types .= "ssss";
    $kw = '%' . $search . '%';
    $params = [$kw, $kw, $kw, $kw];
}

$sql .= " GROUP BY u.user_id ORDER BY u.user_id DESC";
$stmt = mysqli_prepare($con, $sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$usersRes = mysqli_stmt_get_result($stmt);
?>

<div class="admin-header">
    <div>
        <span class="hero-badge" style="margin-bottom:6px;">✦ Manajemen Pengguna</span>
        <h1 style="font-size: 2.2rem;">Data Pengguna Terdaftar</h1>
        <p style="color:var(--text-muted);">Kelola data akun pelanggan, riwayat belanja, dan kontak pembeli toko kue SweetPop.</p>
    </div>
</div>

<!-- Search & Action Bar -->
<div class="card-box" style="margin-bottom: 24px; padding: 18px 24px;">
    <form method="GET" action="users.php" style="display:flex; gap:16px; flex-wrap:wrap; align-items:center;">
        <input type="text" name="search" class="form-control" placeholder="Cari nama, email, atau no HP..." value="<?= htmlspecialchars($search); ?>" style="max-width:320px;">
        <button type="submit" class="btn-sweet btn-sweet-pink" style="padding:10px 20px;">
            <i class="fa-solid fa-magnifying-glass"></i> Cari User
        </button>
        <?php if ($search !== ''): ?>
            <a href="users.php" class="btn-sweet btn-sweet-light" style="padding:10px 16px;">Reset</a>
        <?php endif; ?>
    </form>
</div>

<!-- Users Table -->
<div class="card-box">
    <div style="overflow-x:auto;">
        <table class="cart-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nama Pelanggan</th>
                    <th>Email</th>
                    <th>No. WhatsApp</th>
                    <th>Alamat Pengiriman</th>
                    <th>Total Transaksi</th>
                    <th>Total Belanja</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (mysqli_num_rows($usersRes) == 0): ?>
                    <tr>
                        <td colspan="8" style="text-align:center; padding: 30px; color:var(--text-muted);">
                            Tidak ada data pelanggan yang cocok dengan pencarian.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php while ($u = mysqli_fetch_assoc($usersRes)): ?>
                        <tr>
                            <td><strong>#USR-<?= $u['user_id']; ?></strong></td>
                            <td>
                                <div style="display:flex; align-items:center; gap:10px;">
                                    <div style="width:36px; height:36px; border-radius:50%; background:var(--pink-light); color:var(--pink-dark); display:flex; align-items:center; justify-content:center; font-weight:700; font-size:0.9rem;">
                                        <?= strtoupper(substr($u['first_name'], 0, 1)); ?>
                                    </div>
                                    <div>
                                        <strong><?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']); ?></strong>
                                    </div>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($u['email']); ?></td>
                            <td><?= htmlspecialchars($u['mobile'] ?: '-'); ?></td>
                            <td>
                                <small style="display:block; max-width:240px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                                    <?= htmlspecialchars($u['address1'] ?: '-'); ?>
                                </small>
                            </td>
                            <td>
                                <span style="background:var(--blue-light); color:var(--blue-dark); padding:4px 10px; border-radius:12px; font-weight:700; font-size:0.8rem;">
                                    <?= $u['total_orders']; ?> Order
                                </span>
                            </td>
                            <td>
                                <strong style="color:var(--pink-dark);"><?= format_rupiah($u['total_spent']); ?></strong>
                            </td>
                            <td>
                                <div style="display:flex; gap:8px;">
                                    <a href="orders.php?search=<?= urlencode($u['email']); ?>" class="btn-sweet btn-sweet-light" style="padding:4px 10px; font-size:0.8rem;" title="Lihat Pesanan User">
                                        <i class="fa-solid fa-receipt"></i> Pesanan
                                    </a>
                                    <a href="users.php?delete_id=<?= $u['user_id']; ?>" class="btn-sweet" style="background:#fee2e2; color:#ef4444; padding:4px 10px; font-size:0.8rem;" onclick="return confirm('Apakah Anda yakin ingin menghapus data pengguna ini?')" title="Hapus User">
                                        <i class="fa-solid fa-trash"></i>
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
