<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';

if (!is_admin_logged_in()) {
    header("Location: login.php");
    exit;
}

$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate   = $_GET['end_date'] ?? date('Y-m-d');
$statusFilter = $_GET['status'] ?? 'all';

// Build Query
$sql = "SELECT * FROM orders WHERE DATE(created_at) BETWEEN ? AND ?";
$params = [$startDate, $endDate];
$types = "ss";

if ($statusFilter !== 'all') {
    $sql .= " AND status = ?";
    $params[] = $statusFilter;
    $types .= "s";
}
$sql .= " ORDER BY created_at DESC";

$stmt = mysqli_prepare($con, $sql);
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$reportRes = mysqli_stmt_get_result($stmt);

// Calculate Totals
$totalRevenue = 0;
$totalOrders = 0;
$completedOrders = 0;
$ordersList = [];

while ($row = mysqli_fetch_assoc($reportRes)) {
    $ordersList[] = $row;
    $totalOrders++;
    if ($row['status'] === 'completed') {
        $completedOrders++;
        $totalRevenue += $row['total_amount'];
    } elseif ($row['status'] !== 'cancelled') {
        $totalRevenue += $row['total_amount'];
    }
}
$avgTicket = $totalOrders > 0 ? ($totalRevenue / $totalOrders) : 0;

// Export CSV Handler
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Laporan_Penjualan_SweetPop_' . date('Ymd_His') . '.csv');
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($output, ['No Faktur', 'Tanggal Transaksi', 'Jadwal Kirim', 'Slot Waktu', 'Nama Pelanggan', 'Email', 'No Telepon', 'Kota', 'Metode Bayar', 'Status', 'Total Tagihan (Rp)', 'Ucapan Kue', 'Lilin', 'Catatan']);
    foreach ($ordersList as $o) {
        fputcsv($output, [
            '#SP-' . str_pad($o['order_id'], 4, '0', STR_PAD_LEFT),
            $o['created_at'],
            $o['delivery_date'] ?? '-',
            $o['delivery_time'] ?? '-',
            $o['customer_name'],
            $o['customer_email'],
            $o['customer_phone'],
            $o['shipping_city'],
            $o['payment_method'],
            strtoupper($o['status']),
            $o['total_amount'],
            $o['cake_message'] ?? '',
            $o['candle_request'] ?? '',
            $o['notes'] ?? ''
        ]);
    }
    fclose($output);
    exit;
}

$pageTitle = "Data Laporan Penjualan";
include 'header.php';
?>

<!-- Print Style -->
<style>
@media print {
    .admin-sidebar, .site-header, .top-banner, .btn-sweet, form, .no-print {
        display: none !important;
    }
    .admin-layout {
        display: block !important;
    }
    .admin-content {
        padding: 0 !important;
        margin: 0 !important;
    }
    .card-box {
        box-shadow: none !important;
        border: 1px solid #ddd !important;
    }
}
</style>

<div class="admin-header">
    <div>
        <span class="hero-badge" style="margin-bottom:6px;">✦ Laporan Keuangan & Penjualan</span>
        <h1 style="font-size: 2.2rem;">Laporan Rekapitulasi Transaksi</h1>
        <p style="color:var(--text-muted);">Pantau ringkasan omzet, volume pesanan dessert, dan cetak laporan resmi toko kue SweetPop.</p>
    </div>
    <div class="no-print" style="display:flex; gap:10px;">
        <a href="reports.php?start_date=<?= urlencode($startDate); ?>&end_date=<?= urlencode($endDate); ?>&status=<?= urlencode($statusFilter); ?>&export=csv" class="btn-sweet" style="background:#10b981; color:#fff; border:none;">
            <i class="fa-solid fa-file-excel"></i> Export CSV / Excel
        </a>
        <button onclick="window.print()" class="btn-sweet btn-sweet-pink">
            <i class="fa-solid fa-print"></i> Cetak / Simpan PDF
        </button>
    </div>
</div>

<!-- Date Filter Form -->
<div class="card-box no-print" style="margin-bottom: 24px; padding: 20px 24px;">
    <form method="GET" action="reports.php" style="display:flex; gap:16px; flex-wrap:wrap; align-items:flex-end;">
        <div>
            <label class="form-label" style="font-size:0.85rem;">Dari Tanggal</label>
            <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($startDate); ?>" required>
        </div>
        <div>
            <label class="form-label" style="font-size:0.85rem;">Sampai Tanggal</label>
            <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($endDate); ?>" required>
        </div>
        <div>
            <label class="form-label" style="font-size:0.85rem;">Status Transaksi</label>
            <select name="status" class="form-control">
                <option value="all" <?= $statusFilter === 'all' ? 'selected' : ''; ?>>Semua Status</option>
                <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                <option value="processing" <?= $statusFilter === 'processing' ? 'selected' : ''; ?>>Processing</option>
                <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
            </select>
        </div>
        <button type="submit" class="btn-sweet btn-sweet-pink" style="padding:10px 20px;">
            <i class="fa-solid fa-filter"></i> Filter Laporan
        </button>
        <a href="reports.php" class="btn-sweet btn-sweet-light" style="padding:10px 16px;">Reset</a>
    </form>
</div>

<!-- Report Summary Cards -->
<div class="stats-grid" style="margin-bottom: 24px;">
    <div class="stat-card">
        <div>
            <div style="color:var(--text-muted); font-size:0.85rem; font-weight:600;">TOTAL OMZET PERIODE</div>
            <div class="stat-val" style="color:var(--pink-dark);"><?= format_rupiah($totalRevenue); ?></div>
        </div>
        <div style="width:48px; height:48px; border-radius:var(--radius-md); background:var(--pink-light); color:var(--pink-dark); display:flex; align-items:center; justify-content:center; font-size:1.4rem;">
            <i class="fa-solid fa-coins"></i>
        </div>
    </div>

    <div class="stat-card">
        <div>
            <div style="color:var(--text-muted); font-size:0.85rem; font-weight:600;">TOTAL TRANSAKSI</div>
            <div class="stat-val" style="color:var(--blue-dark);"><?= $totalOrders; ?> Pesanan</div>
        </div>
        <div style="width:48px; height:48px; border-radius:var(--radius-md); background:var(--blue-light); color:var(--blue-dark); display:flex; align-items:center; justify-content:center; font-size:1.4rem;">
            <i class="fa-solid fa-receipt"></i>
        </div>
    </div>

    <div class="stat-card">
        <div>
            <div style="color:var(--text-muted); font-size:0.85rem; font-weight:600;">PESANAN BERHASIL</div>
            <div class="stat-val" style="color:#10b981;"><?= $completedOrders; ?> Selesai</div>
        </div>
        <div style="width:48px; height:48px; border-radius:var(--radius-md); background:#ecfdf5; color:#10b981; display:flex; align-items:center; justify-content:center; font-size:1.4rem;">
            <i class="fa-solid fa-circle-check"></i>
        </div>
    </div>

    <div class="stat-card">
        <div>
            <div style="color:var(--text-muted); font-size:0.85rem; font-weight:600;">RATA-RATA ORDER</div>
            <div class="stat-val" style="color:#8b5cf6;"><?= format_rupiah($avgTicket); ?></div>
        </div>
        <div style="width:48px; height:48px; border-radius:var(--radius-md); background:#f5f3ff; color:#8b5cf6; display:flex; align-items:center; justify-content:center; font-size:1.4rem;">
            <i class="fa-solid fa-calculator"></i>
        </div>
    </div>
</div>

<!-- Report Table -->
<div class="card-box">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
        <h3 style="font-size:1.2rem;">
            <i class="fa-solid fa-table-list" style="color:var(--pink-dark);"></i> Rincian Transaksi (<?= date('d M Y', strtotime($startDate)); ?> s/d <?= date('d M Y', strtotime($endDate)); ?>)
        </h3>
        <span style="font-size:0.85rem; color:var(--text-muted);">Dicetak pada: <?= date('d M Y, H:i'); ?></span>
    </div>

    <div style="overflow-x:auto;">
        <table class="cart-table">
            <thead>
                <tr>
                    <th>No. Order</th>
                    <th>Tanggal</th>
                    <th>Nama Pembeli</th>
                    <th>No. Kontak</th>
                    <th>Metode Pembayaran</th>
                    <th>Status</th>
                    <th style="text-align:right;">Total Tagihan</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($ordersList)): ?>
                    <tr>
                        <td colspan="7" style="text-align:center; padding:30px; color:var(--text-muted);">
                            Tidak ada transaksi pada periode yang dipilih.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($ordersList as $ord): ?>
                        <tr>
                            <td><strong>#SP-<?= str_pad($ord['order_id'], 4, '0', STR_PAD_LEFT); ?></strong></td>
                            <td><small><?= date('d M Y, H:i', strtotime($ord['created_at'])); ?></small></td>
                            <td><strong><?= htmlspecialchars($ord['customer_name']); ?></strong></td>
                            <td><?= htmlspecialchars($ord['customer_phone']); ?></td>
                            <td><span style="background:var(--blue-light); color:var(--blue-dark); padding:3px 8px; border-radius:6px; font-size:0.75rem; font-weight:700;"><?= htmlspecialchars($ord['payment_method']); ?></span></td>
                            <td>
                                <?php if ($ord['status'] === 'completed'): ?>
                                    <span style="background:#ecfdf5; color:#047857; padding:3px 8px; border-radius:10px; font-weight:700; font-size:0.75rem;">COMPLETED</span>
                                <?php elseif ($ord['status'] === 'processing'): ?>
                                    <span style="background:#eff6ff; color:#2563eb; padding:3px 8px; border-radius:10px; font-weight:700; font-size:0.75rem;">PROCESSING</span>
                                <?php elseif ($ord['status'] === 'cancelled'): ?>
                                    <span style="background:#fef2f2; color:#b91c1c; padding:3px 8px; border-radius:10px; font-weight:700; font-size:0.75rem;">CANCELLED</span>
                                <?php else: ?>
                                    <span style="background:#fffbeb; color:#d97706; padding:3px 8px; border-radius:10px; font-weight:700; font-size:0.75rem;">PENDING</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:right;">
                                <strong style="color:var(--pink-dark);"><?= format_rupiah($ord['total_amount']); ?></strong>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr style="background:#fff0f5; font-weight:bold;">
                    <td colspan="6" style="text-align:right;">TOTAL KESELURUHAN OMZET:</td>
                    <td style="text-align:right; color:var(--pink-dark); font-size:1.1rem;"><?= format_rupiah($totalRevenue); ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<?php include 'footer.php'; ?>
