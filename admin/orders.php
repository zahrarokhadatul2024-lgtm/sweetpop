<?php
$pageTitle = "Kelola Pesanan";
include 'header.php';

// Handle Status Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $orderId = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
    $newStatus = $_POST['status'] ?? 'pending';
    $newPStatus = $_POST['p_status'] ?? 'Pending';

    if ($orderId && in_array($newStatus, ['pending', 'processing', 'completed', 'cancelled'])) {
        $paidAtSql = ($newPStatus === 'Paid') ? ", paid_at = COALESCE(paid_at, NOW())" : "";
        $stmt = mysqli_prepare($con, "UPDATE orders SET status = ?, p_status = ? $paidAtSql WHERE order_id = ?");
        mysqli_stmt_bind_param($stmt, "ssi", $newStatus, $newPStatus, $orderId);
        if (mysqli_stmt_execute($stmt)) {
            set_flash('success', "Status Pesanan #SP-" . str_pad($orderId, 4, '0', STR_PAD_LEFT) . " berhasil diperbarui!");
        } else {
            set_flash('danger', "Gagal mengubah status: " . mysqli_error($con));
        }
    }
    header("Location: orders.php" . (isset($_GET['view_id']) ? '?view_id=' . (int)$_GET['view_id'] : ''));
    exit;
}

$statusFilter = $_GET['status'] ?? 'all';
$viewId = filter_input(INPUT_GET, 'view_id', FILTER_VALIDATE_INT);

// Build SQL
$sql = "SELECT * FROM orders WHERE 1=1";
if ($statusFilter !== 'all') {
    $sql .= " AND status = '" . mysqli_real_escape_string($con, $statusFilter) . "'";
}
$sql .= " ORDER BY created_at DESC";
$ordersRes = mysqli_query($con, $sql);

// If view_id is requested, fetch specific order detail
$viewOrder = null;
$viewItems = [];
if ($viewId) {
    $vStmt = mysqli_prepare($con, "SELECT * FROM orders WHERE order_id = ?");
    mysqli_stmt_bind_param($vStmt, "i", $viewId);
    mysqli_stmt_execute($vStmt);
    $viewOrder = mysqli_fetch_assoc(mysqli_stmt_get_result($vStmt));

    if ($viewOrder) {
        $viStmt = mysqli_prepare($con, "SELECT * FROM order_items WHERE order_id = ?");
        mysqli_stmt_bind_param($viStmt, "i", $viewId);
        mysqli_stmt_execute($viStmt);
        $viRes = mysqli_stmt_get_result($viStmt);
        while ($it = mysqli_fetch_assoc($viRes)) {
            $viewItems[] = $it;
        }
    }
}
?>

<div class="admin-header">
    <div>
        <span class="hero-badge" style="margin-bottom:6px;">✦ Logistik & Transaksi</span>
        <h1 style="font-size: 2.2rem;">Daftar Pesanan Pelanggan</h1>
    </div>
</div>

<!-- Status Filter Tabs -->
<div style="display:flex; gap:10px; margin-bottom:24px; overflow-x:auto; padding-bottom:8px;">
    <a href="orders.php" class="cat-pill <?= $statusFilter === 'all' ? 'active' : ''; ?>">Semua Pesanan</a>
    <a href="orders.php?status=pending" class="cat-pill <?= $statusFilter === 'pending' ? 'active' : ''; ?>">Pending</a>
    <a href="orders.php?status=processing" class="cat-pill <?= $statusFilter === 'processing' ? 'active' : ''; ?>">Processing</a>
    <a href="orders.php?status=completed" class="cat-pill <?= $statusFilter === 'completed' ? 'active' : ''; ?>">Completed</a>
    <a href="orders.php?status=cancelled" class="cat-pill <?= $statusFilter === 'cancelled' ? 'active' : ''; ?>">Cancelled</a>
</div>

<?php if ($viewOrder): ?>
    <!-- Order Detail Card Modal/Box -->
    <div class="card-box" style="margin-bottom:32px; border:2px solid var(--pink); background:#fffafd;">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:20px; border-bottom:1px solid var(--border); padding-bottom:14px; flex-wrap:wrap; gap:12px;">
            <div>
                <span class="hero-badge" style="margin-bottom:4px;">Detail Pesanan</span>
                <h2 style="font-size:1.8rem; margin:0;">#SP-<?= str_pad($viewOrder['order_id'], 4, '0', STR_PAD_LEFT); ?></h2>
                <small style="color:var(--text-muted);">Dibuat pada <?= date('d M Y, H:i', strtotime($viewOrder['created_at'])); ?></small>
            </div>
            <a href="orders.php" class="btn-sweet btn-sweet-light" style="padding:6px 14px; font-size:0.85rem;">
                <i class="fa-solid fa-xmark"></i> Tutup Detail
            </a>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:24px;" class="form-row">
            <div>
                <h4 style="font-size:1.1rem; margin-bottom:10px; color:var(--text);"><i class="fa-solid fa-user" style="color:var(--pink);"></i> Penerima</h4>
                <p style="margin:0 0 4px; font-weight:700;"><?= htmlspecialchars($viewOrder['customer_name']); ?></p>
                <p style="margin:0 0 4px; color:var(--text-muted); font-size:0.9rem;"><?= htmlspecialchars($viewOrder['customer_email']); ?> • <?= htmlspecialchars($viewOrder['customer_phone']); ?></p>
                <p style="margin:0; font-size:0.9rem;"><strong>Alamat:</strong> <?= nl2br(htmlspecialchars($viewOrder['shipping_address'])); ?>, <?= htmlspecialchars($viewOrder['shipping_city']); ?></p>
                
                <?php
                $custWa = format_wa_number($viewOrder['customer_phone']);
                $waAdminText = "Halo kak " . $viewOrder['customer_name'] . ", kami dari Admin Bakehouse SweetPop terkait pesanan #SP-" . str_pad($viewOrder['order_id'], 4, '0', STR_PAD_LEFT) . " ♡";
                $waAdminUrl = "https://api.whatsapp.com/send?phone=" . $custWa . "&text=" . urlencode($waAdminText);
                ?>
                <div style="margin-top:10px;">
                    <a href="<?= $waAdminUrl; ?>" target="_blank" class="btn-sweet" style="background:#25D366; color:#fff; padding:6px 14px; font-size:0.82rem; border:none; display:inline-flex; align-items:center; gap:6px;">
                        <i class="fa-brands fa-whatsapp"></i> Chat Pelanggan via WhatsApp
                    </a>
                </div>

                <!-- Bakery Customization Box -->
                <div style="margin-top:14px; background:#fff0f5; padding:12px 14px; border-radius:10px; border:1px solid #fed7e2; font-size:0.88rem;">
                    <div><strong style="color:var(--pink-dark);"><i class="fa-regular fa-calendar"></i> Jadwal Kirim:</strong> <?= !empty($viewOrder['delivery_date']) ? date('d M Y', strtotime($viewOrder['delivery_date'])) : '-'; ?> (<?= htmlspecialchars($viewOrder['delivery_time'] ?? '-'); ?>)</div>
                    <?php if (!empty($viewOrder['cake_message'])): ?>
                        <div style="margin-top:4px;"><strong style="color:#d97706;"><i class="fa-solid fa-pen-fancy"></i> Tulisan Kue:</strong> "<?= htmlspecialchars($viewOrder['cake_message']); ?>"</div>
                    <?php endif; ?>
                    <?php if (!empty($viewOrder['candle_request'])): ?>
                        <div style="margin-top:4px;"><strong style="color:#10b981;"><i class="fa-solid fa-wand-magic-sparkles"></i> Lilin:</strong> <?= htmlspecialchars($viewOrder['candle_request']); ?></div>
                    <?php endif; ?>
                </div>

                <?php if (!empty($viewOrder['notes'])): ?>
                    <p style="margin-top:8px; font-size:0.88rem; background:#fff; padding:8px 12px; border-radius:8px; border:1px dashed var(--border);"><strong>Catatan:</strong> <?= htmlspecialchars($viewOrder['notes']); ?></p>
                <?php endif; ?>
            </div>

            <div>
                <h4 style="font-size:1.1rem; margin-bottom:10px; color:var(--text);"><i class="fa-solid fa-credit-card" style="color:var(--blue-dark);"></i> Pembayaran & Status</h4>
                <p style="margin:0 0 8px;"><strong>Metode:</strong> <?= htmlspecialchars($viewOrder['payment_method']); ?></p>
                <p style="margin:0 0 16px;"><strong>Total:</strong> <span style="font-family:'Baloo 2', cursive; font-size:1.4rem; font-weight:800; color:var(--pink-dark);"><?= format_rupiah($viewOrder['total_amount']); ?></span></p>

                <form action="orders.php" method="POST" style="display:flex; flex-direction:column; gap:12px; align-items:flex-start;">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="order_id" value="<?= $viewOrder['order_id']; ?>">
                    
                    <div style="display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
                        <div>
                            <label style="font-size:0.8rem; font-weight:700; color:var(--text-muted); display:block; margin-bottom:4px;">Status Pesanan:</label>
                            <select name="status" class="form-control" style="width:auto; padding:8px 14px;">
                                <option value="pending" <?= $viewOrder['status'] === 'pending' ? 'selected' : ''; ?>>Pending (Menunggu)</option>
                                <option value="processing" <?= $viewOrder['status'] === 'processing' ? 'selected' : ''; ?>>Processing (Dipanggang/Dikirim)</option>
                                <option value="completed" <?= $viewOrder['status'] === 'completed' ? 'selected' : ''; ?>>Completed (Selesai)</option>
                                <option value="cancelled" <?= $viewOrder['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled (Batal)</option>
                            </select>
                        </div>
                        <div>
                            <label style="font-size:0.8rem; font-weight:700; color:var(--text-muted); display:block; margin-bottom:4px;">Status Pembayaran:</label>
                            <select name="p_status" class="form-control" style="width:auto; padding:8px 14px;">
                                <option value="Pending" <?= ($viewOrder['p_status'] ?? 'Pending') === 'Pending' ? 'selected' : ''; ?>>Pending (Belum Bayar)</option>
                                <option value="Paid" <?= ($viewOrder['p_status'] ?? '') === 'Paid' ? 'selected' : ''; ?>>Paid (Lunas)</option>
                                <option value="Expired" <?= ($viewOrder['p_status'] ?? '') === 'Expired' ? 'selected' : ''; ?>>Expired (Waktu Habis)</option>
                                <option value="Failed" <?= ($viewOrder['p_status'] ?? '') === 'Failed' ? 'selected' : ''; ?>>Failed (Dibatalkan)</option>
                            </select>
                        </div>
                        <div style="align-self:flex-end;">
                            <button type="submit" class="btn-sweet btn-sweet-pink" style="padding:9px 18px; font-size:0.9rem;">Simpan Perubahan</button>
                        </div>
                    </div>
                    
                    <div style="display:flex; gap:10px; margin-top:4px;">
                        <a href="../invoice.php?order_id=<?= $viewOrder['order_id']; ?>" target="_blank" class="btn-sweet btn-sweet-light" style="padding:6px 14px; font-size:0.85rem;">
                            <i class="fa-solid fa-print"></i> Cetak Faktur
                        </a>
                        <a href="../order_success.php?id=<?= $viewOrder['order_id']; ?>" target="_blank" class="btn-sweet btn-sweet-light" style="padding:6px 14px; font-size:0.85rem;">
                            <i class="fa-solid fa-arrow-up-right-from-square"></i> Buka Halaman Pelanggan
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <div style="margin-top:24px; border-top:1px solid var(--border); padding-top:16px;">
            <h4 style="font-size:1.1rem; margin-bottom:12px;">Item Pesanan:</h4>
            <table class="cart-table">
                <thead>
                    <tr>
                        <th>Dessert</th>
                        <th>Harga Satuan</th>
                        <th>Jumlah</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($viewItems as $it): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($it['product_name']); ?></strong></td>
                            <td><?= format_rupiah($it['product_price']); ?></td>
                            <td><?= $it['quantity']; ?> pcs</td>
                            <td><strong style="color:var(--pink-dark);"><?= format_rupiah($it['subtotal']); ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<!-- Orders List Table -->
<div class="card-box">
    <?php if (mysqli_num_rows($ordersRes) == 0): ?>
        <p style="text-align:center; padding:30px; color:var(--text-muted);">Tidak ada pesanan dengan filter yang dipilih.</p>
    <?php else: ?>
        <div style="overflow-x:auto;">
            <table class="cart-table">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Pelanggan</th>
                        <th>Kota</th>
                        <th>Metode</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Tanggal</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($ord = mysqli_fetch_assoc($ordersRes)): ?>
                        <tr>
                            <td>
                                <strong>#SP-<?= str_pad($ord['order_id'], 4, '0', STR_PAD_LEFT); ?></strong>
                                <?php if (!empty($ord['delivery_date'])): ?>
                                    <small style="display:block; color:var(--pink-dark); font-weight:700; margin-top:2px;">
                                        <i class="fa-regular fa-calendar"></i> <?= date('d M', strtotime($ord['delivery_date'])); ?> (<?= htmlspecialchars(explode(':', $ord['delivery_time'] ?? '')[0] ?: 'Pagi'); ?>)
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($ord['customer_name']); ?></strong>
                                <small style="display:block; color:var(--text-muted);"><?= htmlspecialchars($ord['customer_phone']); ?></small>
                                <?php if (!empty($ord['cake_message'])): ?>
                                    <small style="display:block; color:#d97706; font-style:italic;">"<?= htmlspecialchars(mb_strimwidth($ord['cake_message'], 0, 24, '...')); ?>"</small>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($ord['shipping_city']); ?></td>
                            <td>
                                <span style="background:var(--blue-light); color:var(--blue-dark); padding:3px 8px; border-radius:8px; font-size:0.8rem; font-weight:700;">
                                    <?= htmlspecialchars($ord['payment_method']); ?>
                                </span>
                                <?php
                                $pSt = $ord['p_status'] ?? 'Pending';
                                if ($pSt === 'Paid'): ?>
                                    <small style="display:block; color:#059669; font-weight:700; margin-top:3px;"><i class="fa-solid fa-circle-check"></i> Lunas</small>
                                <?php elseif ($pSt === 'Expired'): ?>
                                    <small style="display:block; color:#dc2626; font-weight:700; margin-top:3px;"><i class="fa-solid fa-clock"></i> Expired</small>
                                <?php elseif ($pSt === 'Failed'): ?>
                                    <small style="display:block; color:#dc2626; font-weight:700; margin-top:3px;"><i class="fa-solid fa-circle-xmark"></i> Gagal</small>
                                <?php elseif (stripos($ord['payment_method'], 'COD') !== false): ?>
                                    <small style="display:block; color:#2563eb; font-weight:700; margin-top:3px;"><i class="fa-solid fa-hand-holding-dollar"></i> Bayar di Tempat</small>
                                <?php else: ?>
                                    <small style="display:block; color:#d97706; font-weight:700; margin-top:3px;"><i class="fa-solid fa-hourglass-half"></i> Menunggu Bayar</small>
                                <?php endif; ?>
                            </td>
                            <td><strong style="color:var(--pink-dark);"><?= format_rupiah($ord['total_amount']); ?></strong></td>
                            <td>
                                <?php if ($ord['status'] === 'completed'): ?>
                                    <span style="background:#ecfdf5; color:#047857; padding:4px 10px; border-radius:12px; font-weight:700; font-size:0.75rem;">COMPLETED</span>
                                <?php elseif ($ord['status'] === 'processing'): ?>
                                    <span style="background:#eff6ff; color:#2563eb; padding:4px 10px; border-radius:12px; font-weight:700; font-size:0.75rem;">PROCESSING</span>
                                <?php elseif ($ord['status'] === 'cancelled'): ?>
                                    <span style="background:#fef2f2; color:#b91c1c; padding:4px 10px; border-radius:12px; font-weight:700; font-size:0.75rem;">CANCELLED</span>
                                <?php else: ?>
                                    <span style="background:#fffbeb; color:#d97706; padding:4px 10px; border-radius:12px; font-weight:700; font-size:0.75rem;">PENDING</span>
                                <?php endif; ?>
                            </td>
                            <td><small><?= date('d M Y, H:i', strtotime($ord['created_at'])); ?></small></td>
                            <td>
                                <a href="orders.php?view_id=<?= $ord['order_id']; ?>" class="btn-sweet btn-sweet-light" style="padding:4px 12px; font-size:0.8rem;">
                                    Detail
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>
