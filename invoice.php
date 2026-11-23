<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';

$orderId = filter_input(INPUT_GET, 'order_id', FILTER_VALIDATE_INT);
if (!$orderId) {
    die("ID Pesanan tidak valid.");
}

// Fetch Order
$stmt = mysqli_prepare($con, "SELECT * FROM orders WHERE order_id = ?");
mysqli_stmt_bind_param($stmt, "i", $orderId);
mysqli_stmt_execute($stmt);
$order = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$order) {
    die("Data pesanan tidak ditemukan.");
}

// Check authorization: must be admin or the owner of order
if (!is_admin_logged_in()) {
    if (!is_logged_in() || current_user_id() != $order['user_id']) {
        // If guest order, verify with email
        if (isset($_GET['email']) && $_GET['email'] === $order['customer_email']) {
            // authorized guest
        } else {
            die("Akses ditolak. Silakan login terlebih dahulu.");
        }
    }
}

// Fetch Order Items
$itemStmt = mysqli_prepare($con, "SELECT * FROM order_items WHERE order_id = ?");
mysqli_stmt_bind_param($itemStmt, "i", $orderId);
mysqli_stmt_execute($itemStmt);
$itemsRes = mysqli_stmt_get_result($itemStmt);
$items = [];
while ($item = mysqli_fetch_assoc($itemsRes)) {
    $items[] = $item;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #SP-<?= str_pad($order['order_id'], 4, '0', STR_PAD_LEFT); ?> | SweetPop</title>
    <link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            background: #fff8fa;
            padding: 40px 20px;
        }
        .invoice-card {
            max-width: 760px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 10px 30px rgba(230, 76, 116, 0.08);
            border: 1px solid var(--border);
        }
        @media print {
            body {
                background: #ffffff !important;
                padding: 0 !important;
            }
            .invoice-card {
                box-shadow: none !important;
                border: none !important;
                padding: 0 !important;
                max-width: 100% !important;
            }
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>

<div class="invoice-card">
    <div style="display:flex; justify-content:space-between; align-items:flex-start; border-bottom: 2px dashed var(--border); padding-bottom: 24px; margin-bottom: 24px;">
        <div>
            <div class="logo" style="font-size:2rem; margin-bottom:4px;">
                <i class="fa-solid fa-cake-candles"></i> SWEET<span>POP</span>
            </div>
            <div style="color:var(--text-muted); font-size:0.88rem;">
                Aesthetic Dessert & Bakery Store<br>
                Jl. Malioboro No. 45, D.I. Yogyakarta<br>
                WhatsApp: <?= htmlspecialchars(get_store_settings()['whatsapp']); ?> • <?= htmlspecialchars(get_store_settings()['email_support']); ?>
            </div>
        </div>
        <div style="text-align:right;">
            <span class="hero-badge" style="margin-bottom:8px; display:inline-block;">BUKTI PEMBAYARAN RESMI</span>
            <h2 style="font-size:1.6rem; color:var(--pink-dark); margin:0;">
                #SP-<?= str_pad($order['order_id'], 4, '0', STR_PAD_LEFT); ?>
            </h2>
            <div style="font-size:0.85rem; color:var(--text-muted); margin-top:4px;">
                Tanggal: <?= date('d F Y, H:i', strtotime($order['created_at'])); ?> WIB
            </div>
            <?php if (!empty($order['trx_id'])): ?>
                <div style="font-size:0.78rem; color:var(--text-muted); font-family:monospace; margin-top:2px;">
                    Ref: <?= htmlspecialchars($order['trx_id']); ?>
                </div>
            <?php endif; ?>
            <div style="margin-top:10px;">
                <?php 
                $pSt = $order['p_status'] ?? 'Pending';
                if ($pSt === 'Paid' || $order['status'] === 'completed'): ?>
                    <div class="stamp-badge">
                        <div style="font-size:1.05rem; letter-spacing:1.5px; font-weight:800;"><i class="fa-solid fa-circle-check"></i> LUNAS / PAID</div>
                        <div style="font-size:0.72rem; font-weight:600; margin-top:2px;">
                            <?= !empty($order['paid_at']) ? date('d/m/Y H:i', strtotime($order['paid_at'])) . ' WIB' : 'TERVERIFIKASI OTOMATIS'; ?>
                        </div>
                    </div>
                <?php elseif ($pSt === 'Expired' || $order['status'] === 'cancelled'): ?>
                    <div class="stamp-badge expired">
                        <div style="font-size:1.05rem; letter-spacing:1.5px; font-weight:800;"><i class="fa-solid fa-circle-xmark"></i> BATAL / EXPIRED</div>
                        <div style="font-size:0.72rem; font-weight:600; margin-top:2px;">WAKTU HABIS</div>
                    </div>
                <?php elseif (stripos($order['payment_method'], 'COD') !== false): ?>
                    <div class="stamp-badge pending" style="border-color:#2563eb; color:#2563eb; background:#eff6ff;">
                        <div style="font-size:0.95rem; font-weight:800;"><i class="fa-solid fa-hand-holding-dollar"></i> BAYAR DI TEMPAT (COD)</div>
                        <div style="font-size:0.72rem; font-weight:600; margin-top:2px;">Tagihan saat Kurir Tiba</div>
                    </div>
                <?php else: ?>
                    <div class="stamp-badge pending">
                        <div style="font-size:0.95rem; font-weight:800;"><i class="fa-solid fa-hourglass-half"></i> MENUNGGU PEMBAYARAN</div>
                        <div style="font-size:0.72rem; font-weight:600; margin-top:2px;">Batas Waktu 15 Menit</div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Customer, Delivery & Customization Details -->
    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px; margin-bottom: 24px;">
        <div style="background:var(--cream); padding:16px 20px; border-radius:12px;">
            <div style="font-size:0.8rem; font-weight:700; color:var(--text-muted); text-transform:uppercase; margin-bottom:4px;">Informasi Pembeli</div>
            <strong style="font-size:1.05rem;"><?= htmlspecialchars($order['customer_name']); ?></strong>
            <div style="font-size:0.9rem; color:var(--text); margin-top:2px;">
                Email: <?= htmlspecialchars($order['customer_email']); ?><br>
                WhatsApp: <?= htmlspecialchars($order['customer_phone']); ?>
            </div>
        </div>

        <div style="background:var(--cream); padding:16px 20px; border-radius:12px;">
            <div style="font-size:0.8rem; font-weight:700; color:var(--text-muted); text-transform:uppercase; margin-bottom:4px;">Alamat Pengantaran</div>
            <div style="font-size:0.9rem; color:var(--text);">
                <?= nl2br(htmlspecialchars($order['shipping_address'])); ?>, <?= htmlspecialchars($order['shipping_city']); ?><br>
                <strong>Metode Bayar:</strong> <?= htmlspecialchars($order['payment_method']); ?>
            </div>
        </div>
    </div>

    <!-- Bakery Delivery Schedule & Notes -->
    <div style="background:#fffafc; border:1px solid var(--border); border-radius:12px; padding:16px 20px; margin-bottom:24px; display:grid; grid-template-columns: 1fr 1fr; gap:16px; font-size:0.9rem;">
        <div>
            <strong style="color:var(--pink-dark);"><i class="fa-regular fa-calendar"></i> Jadwal Pengantaran:</strong>
            <div><?= !empty($order['delivery_date']) ? date('l, d F Y', strtotime($order['delivery_date'])) : date('l, d F Y'); ?></div>
            <small style="color:var(--text-muted);"><?= htmlspecialchars($order['delivery_time'] ?? 'Slot 1 (Pagi)'); ?></small>
        </div>
        <div>
            <strong style="color:var(--blue-dark);"><i class="fa-solid fa-wand-magic-sparkles"></i> Lilin Ulang Tahun:</strong>
            <div><?= htmlspecialchars($order['candle_request'] ?? 'Standar'); ?></div>
        </div>
        <?php if (!empty($order['cake_message'])): ?>
            <div style="grid-column: span 2; border-top:1px dashed var(--border); padding-top:10px;">
                <strong style="color:#d97706;"><i class="fa-solid fa-pen-fancy"></i> Pesan / Tulisan di Atas Kue:</strong>
                <div style="font-style:italic; font-weight:600; color:var(--text);">"<?= htmlspecialchars($order['cake_message']); ?>"</div>
            </div>
        <?php endif; ?>
        <?php if (!empty($order['notes'])): ?>
            <div style="grid-column: span 2; border-top:1px dashed var(--border); padding-top:10px;">
                <strong><i class="fa-regular fa-note-sticky"></i> Catatan Khusus:</strong>
                <div style="color:var(--text-muted);"><?= htmlspecialchars($order['notes']); ?></div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Item Details Table -->
    <table class="cart-table" style="margin-bottom: 24px;">
        <thead>
            <tr style="background:#fff0f5;">
                <th>Item Dessert</th>
                <th style="text-align:center;">Harga Satuan</th>
                <th style="text-align:center;">Qty</th>
                <th style="text-align:right;">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars($item['product_name']); ?></strong>
                    </td>
                    <td style="text-align:center;"><?= format_rupiah($item['product_price'] ?? $item['price'] ?? 0); ?></td>
                    <td style="text-align:center;">x<?= $item['quantity']; ?></td>
                    <td style="text-align:right; font-weight:700;"><?= format_rupiah($item['subtotal']); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3" style="text-align:right; font-weight:600;">Biaya Pengiriman & Thermal Packing:</td>
                <td style="text-align:right; color:#10b981; font-weight:700;">GRATIS (Rp 0)</td>
            </tr>
            <tr style="background:#fff0f5; font-size:1.15rem; font-weight:800;">
                <td colspan="3" style="text-align:right; color:var(--text);">TOTAL PEMBAYARAN:</td>
                <td style="text-align:right; color:var(--pink-dark);"><?= format_rupiah($order['total_amount']); ?></td>
            </tr>
        </tfoot>
    </table>

    <!-- Payment Details Section in Official Invoice -->
    <?php
    $pm = $order['payment_method'];
    $isQris = stripos($pm, 'QRIS') !== false;
    $isBsi = stripos($pm, 'BSI') !== false || stripos($pm, 'Bank') !== false;
    $isCod = stripos($pm, 'COD') !== false;
    $isShopee = stripos($pm, 'Shopee') !== false;
    ?>

    <div style="background:#fff8fc; border:1.5px solid #fbcfe8; border-radius:14px; padding:18px 22px; margin-bottom: 24px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; flex-wrap:wrap; gap:10px;">
            <div>
                <small style="font-size:0.75rem; font-weight:700; color:var(--text-muted); text-transform:uppercase;">Instruksi Pembayaran</small>
                <div style="font-size:1.1rem; font-weight:800; color:var(--text);"><?= htmlspecialchars($order['payment_method']); ?></div>
            </div>
            <span class="hero-badge" style="background:#ffffff; color:var(--pink-dark); font-size:0.8rem;">
                Total Tagihan: <strong><?= format_rupiah($order['total_amount']); ?></strong>
            </span>
        </div>

        <?php if ($isQris): ?>
            <div style="display:flex; align-items:center; gap:20px; flex-wrap:wrap; margin-top:12px; background:#ffffff; border-radius:10px; padding:14px; border:1px solid #fce7f3;">
                <img src="assets/img/qris_sweetpop.png" alt="QRIS SweetPop" style="width:120px; height:120px; object-fit:contain; border-radius:8px; border:1px solid #fbcfe8;">
                <div style="font-size:0.9rem; line-height:1.5; color:#334155;">
                    <strong style="color:var(--text); display:block;">Scan Barcode QRIS SWEETPOP BAKERY</strong>
                    <div style="font-size:0.82rem; color:var(--text-muted); margin-bottom:6px;">NMID: ID1020021332450 • GoPay / OVO / ShopeePay / BCA / All Bank</div>
                    <small style="color:#64748b;">Silakan scan QRIS di samping melalui m-Banking atau e-Wallet favorit Anda sebesar nominal total tagihan.</small>
                </div>
            </div>
        <?php elseif ($isBsi): ?>
            <div style="background:#f0fdfa; border:1px solid #99f6e4; border-radius:10px; padding:14px 18px; margin-top:10px;">
                <div style="font-size:0.85rem; color:#0f766e; font-weight:700;">BANK SYARIAH INDONESIA (BSI) — KODE BANK 451</div>
                <div style="font-family:'Courier New', monospace; font-size:1.5rem; font-weight:800; color:#134e4a; margin:4px 0;">
                    7326007751
                </div>
                <div style="font-size:0.9rem; color:#047857; font-weight:600;">
                    Atas Nama: <strong>Zahra Rokhadatul Aisy</strong>
                </div>
                <small style="display:block; color:#475569; margin-top:4px;">Mohon transfer tepat sejumlah total tagihan dan simpan bukti transfer untuk verifikasi admin.</small>
            </div>
        <?php elseif ($isShopee): ?>
            <div style="background:#fff7ed; border:1px solid #fed7aa; border-radius:10px; padding:14px 18px; margin-top:10px;">
                <div style="font-size:0.85rem; color:#c2410c; font-weight:700;">TRANSFER SHOPEEPAY E-WALLET</div>
                <div style="font-family:'Courier New', monospace; font-size:1.5rem; font-weight:800; color:#9a3412; margin:4px 0;">
                    0895327517570
                </div>
                <div style="font-size:0.9rem; color:#ea580c; font-weight:600;">
                    Atas Nama: <strong>Zahra Rokhadatul Aisy</strong>
                </div>
                <small style="display:block; color:#475569; margin-top:4px;">Transfer via Shopee > ShopeePay > Transfer ke Kontak / No. HP tanpa biaya admin.</small>
            </div>
        <?php elseif ($isCod): ?>
            <div style="background:#fffbeb; border:1px solid #fde68a; border-radius:10px; padding:14px 18px; margin-top:10px;">
                <div style="font-size:0.9rem; color:#b45309; font-weight:700;">
                    <i class="fa-solid fa-triangle-exclamation"></i> Pengingat Uang Pas (CASH ON DELIVERY)
                </div>
                <div style="font-size:0.92rem; color:#78350f; margin-top:4px;">
                    Mohon siapkan <strong>uang pas</strong> senilai <strong><?= format_rupiah($order['total_amount']); ?></strong> ketika kurir motor SweetPop mengantarkan pesanan ke alamat Anda.
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div style="background:#f0f9ff; border:1px solid #bae6fd; border-radius:12px; padding:16px; font-size:0.88rem; color:#0369a1; margin-bottom: 24px;">
        <i class="fa-solid fa-circle-info"></i> Terima kasih telah memesan di SweetPop! Pesanan kamu dipanggang segar dengan bahan premium New Zealand butter & halal ingredients. Simpan faktur ini sebagai bukti pembelian resmi.
    </div>

    <?php
    $settings = get_store_settings();
    $adminWa = format_wa_number($settings['whatsapp']);
    $waText = "Halo SweetPop! 🍰 Saya ingin konfirmasi/menanyakan status pesanan Faktur #SP-" . str_pad($order['order_id'], 4, '0', STR_PAD_LEFT) . " atas nama " . $order['customer_name'] . ". Total " . format_rupiah($order['total_amount']) . ".";
    $waUrl = "https://api.whatsapp.com/send?phone=" . $adminWa . "&text=" . urlencode($waText);
    ?>

    <div class="no-print" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
        <a href="javascript:history.back()" class="btn-sweet btn-sweet-light">
            <i class="fa-solid fa-arrow-left"></i> Kembali
        </a>
        <div style="display:flex; gap:10px;">
            <a href="<?= $waUrl; ?>" target="_blank" class="btn-sweet" style="background:#25D366; color:#fff; border:none;">
                <i class="fa-brands fa-whatsapp"></i> Chat CS Toko
            </a>
            <button onclick="window.print()" class="btn-sweet btn-sweet-pink">
                <i class="fa-solid fa-print"></i> Cetak Struk / Invoice PDF
            </button>
        </div>
    </div>
</div>

</body>
</html>
