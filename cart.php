<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';

// Handle Cart Actions
$action = $_REQUEST['action'] ?? '';
$isAjax = isset($_REQUEST['ajax']) && $_REQUEST['ajax'] == '1';

if ($action === 'add') {
    $productId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
    if (!$productId && isset($_REQUEST['id'])) {
        $productId = (int)$_REQUEST['id'];
    }
    $qty = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT) ?: 1;

    if ($productId) {
        if (!isset($_SESSION['cart'][$productId])) {
            $_SESSION['cart'][$productId] = 0;
        }
        $_SESSION['cart'][$productId] += $qty;

        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'success', 'cartCount' => get_cart_count()]);
            exit;
        } else {
            set_flash('success', 'Produk berhasil ditambahkan ke keranjang belanja ♡');
        }
    }
    header("Location: cart.php");
    exit;
}

if ($action === 'update') {
    $quantities = $_POST['quantities'] ?? [];
    foreach ($quantities as $pId => $qty) {
        $pId = (int)$pId;
        $qty = (int)$qty;
        if ($qty <= 0) {
            unset($_SESSION['cart'][$pId]);
        } else {
            $_SESSION['cart'][$pId] = $qty;
        }
    }
    set_flash('success', 'Keranjang belanja berhasil diperbarui.');
    header("Location: cart.php");
    exit;
}

if ($action === 'remove') {
    $productId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if ($productId && isset($_SESSION['cart'][$productId])) {
        unset($_SESSION['cart'][$productId]);
        set_flash('info', 'Produk dihapus dari keranjang.');
    }
    header("Location: cart.php");
    exit;
}

if ($action === 'coupon') {
    $code = strtoupper(trim($_POST['coupon_code'] ?? ''));
    if ($code === 'SWEET20') {
        $_SESSION['coupon'] = ['code' => 'SWEET20', 'discount' => 0.20, 'desc' => 'Diskon 20% Sweet Tooth'];
        set_flash('success', 'Kupon SWEET20 berhasil diterapkan (Diskon 20%)! 🎉');
    } else {
        unset($_SESSION['coupon']);
        set_flash('danger', 'Kode kupon tidak valid atau telah kedaluwarsa.');
    }
    header("Location: cart.php");
    exit;
}

// Fetch Cart Products
$cartItems = [];
$subtotal = 0;

if (!empty($_SESSION['cart'])) {
    $ids = array_keys($_SESSION['cart']);
    $idsList = implode(',', array_map('intval', $ids));
    
    if (!empty($idsList)) {
        $cartQuery = "
            SELECT p.*, c.cat_title 
            FROM products p 
            INNER JOIN categories c ON p.product_cat = c.cat_id 
            WHERE p.product_id IN ($idsList)
        ";
        $cartRes = mysqli_query($con, $cartQuery);
        while ($row = mysqli_fetch_assoc($cartRes)) {
            $qty = $_SESSION['cart'][$row['product_id']];
            $itemSubtotal = $row['product_price'] * $qty;
            $subtotal += $itemSubtotal;

            $cartItems[] = [
                'product' => $row,
                'qty' => $qty,
                'subtotal' => $itemSubtotal
            ];
        }
    }
}

// Coupon discount calculation
$discountAmount = 0;
if (isset($_SESSION['coupon']) && $subtotal > 0) {
    $discountAmount = $subtotal * $_SESSION['coupon']['discount'];
}

$shippingCost = ($subtotal >= 50000 || $subtotal == 0) ? 0 : 15000;
$grandTotal = max(0, $subtotal - $discountAmount + $shippingCost);

$pageTitle = "Shopping Bag";
include 'includes/header.php';
?>

<div class="container" style="padding: 40px 20px 80px;">
    <div style="margin-bottom: 30px;">
        <span class="section-tag">your sweet selections 🛍️</span>
        <h1 style="font-size:2.6rem;">Shopping Bag (<?= count($cartItems); ?> item)</h1>
    </div>

    <?php if (empty($cartItems)): ?>
        <div class="card-box" style="text-align:center; padding: 60px 20px;">
            <div style="font-size: 4rem; margin-bottom: 12px;">🧁</div>
            <h2 style="font-size: 1.8rem; margin-bottom: 8px;">Keranjang Belanjamu Masih Kosong</h2>
            <p style="color:var(--text-muted); margin-bottom: 28px; font-size:1.05rem;">Yuk temukan dessert manis impianmu untuk hari yang lebih ceria!</p>
            <a href="store.php" class="btn-sweet btn-sweet-pink">Mulai Belanja Treats <i class="fa-solid fa-arrow-right"></i></a>
        </div>
    <?php else: ?>
        <!-- Free Shipping Progress -->
        <div class="card-box" style="margin-bottom: 24px; padding: 18px 24px; background: #fff5f9; border-color: #ffd6e6;">
            <?php if ($subtotal >= 50000): ?>
                <div style="display:flex; align-items:center; gap:10px; color:#047857; font-weight:700;">
                    <i class="fa-solid fa-circle-check" style="font-size:1.3rem;"></i>
                    <span>Selamat! Kamu mendapatkan <strong>GRATIS ONGKIR</strong> untuk pesanan ini! 🎉</span>
                </div>
            <?php else: ?>
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; font-size:0.95rem;">
                    <span>Tambah <strong><?= format_rupiah(50000 - $subtotal); ?></strong> lagi untuk mendapatkan <strong>GRATIS ONGKIR</strong>!</span>
                    <strong style="color:var(--pink-dark);"><?= round(($subtotal / 50000) * 100); ?>%</strong>
                </div>
                <div style="width:100%; height:8px; background:#ffd6e6; border-radius:10px; overflow:hidden;">
                    <div style="width:<?= min(100, round(($subtotal / 50000) * 100)); ?>%; height:100%; background:var(--pink-gradient);"></div>
                </div>
            <?php endif; ?>
        </div>

        <div class="cart-grid">
            <!-- Left: Cart Items Table -->
            <div class="card-box">
                <form action="cart.php" method="POST" class="cart-update-form">
                    <input type="hidden" name="action" value="update">
                    <div style="overflow-x:auto;">
                        <table class="cart-table">
                            <thead>
                                <tr>
                                    <th>Produk</th>
                                    <th>Harga</th>
                                    <th>Jumlah</th>
                                    <th>Subtotal</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cartItems as $item): ?>
                                    <?php $p = $item['product']; ?>
                                    <tr>
                                        <td>
                                            <div class="cart-item-info">
                                                <img src="product_images/<?= htmlspecialchars($p['product_image'] ?: 'strawberry-cloud.jpg'); ?>" alt="<?= htmlspecialchars($p['product_title']); ?>" class="cart-thumb">
                                                <div>
                                                    <small style="color:var(--blue-dark); font-weight:600;"><?= htmlspecialchars($p['cat_title']); ?></small>
                                                    <div style="font-weight:700; color:var(--text); font-size:1.05rem;">
                                                        <a href="product.php?id=<?= $p['product_id']; ?>"><?= htmlspecialchars($p['product_title']); ?></a>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td><strong><?= format_rupiah($p['product_price']); ?></strong></td>
                                        <td>
                                            <div class="qty-control">
                                                <button type="button" class="qty-btn qty-minus">−</button>
                                                <input type="number" name="quantities[<?= $p['product_id']; ?>]" class="qty-input" value="<?= $item['qty']; ?>" min="1" max="<?= $p['product_stock']; ?>">
                                                <button type="button" class="qty-btn qty-plus">+</button>
                                            </div>
                                        </td>
                                        <td><strong style="color:var(--pink-dark);"><?= format_rupiah($item['subtotal']); ?></strong></td>
                                        <td>
                                            <a href="cart.php?action=remove&id=<?= $p['product_id']; ?>" class="btn-remove" title="Hapus item" onclick="return confirm('Hapus produk ini dari keranjang?');" style="display:inline-flex; align-items:center; justify-content:center;">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div style="display:flex; justify-content:space-between; align-items:center; margin-top: 24px; flex-wrap:wrap; gap:12px;">
                        <a href="store.php" class="btn-sweet btn-sweet-light">
                            <i class="fa-solid fa-arrow-left"></i> Lanjut Belanja
                        </a>
                        <button type="submit" class="btn-sweet btn-sweet-light" style="border-color:var(--blue-dark); color:var(--blue-dark);">
                            <i class="fa-solid fa-rotate"></i> Update Keranjang
                        </button>
                    </div>
                </form>
            </div>

            <!-- Right: Order Summary Box -->
            <div>
                <div class="card-box" style="position:sticky; top:100px;">
                    <h3 style="font-size:1.4rem; margin-bottom: 20px; border-bottom:1px solid var(--border); padding-bottom:12px;">
                        Ringkasan Pesanan
                    </h3>

                    <!-- Coupon Code -->
                    <form action="cart.php" method="POST" style="margin-bottom: 24px;">
                        <input type="hidden" name="action" value="coupon">
                        <label style="font-size:0.85rem; font-weight:700; color:var(--text-muted); display:block; margin-bottom:6px;">PUNYA KODE PROMO?</label>
                        <div style="display:flex; gap:8px;">
                            <input type="text" name="coupon_code" class="form-control" placeholder="Contoh: SWEET20" value="<?= isset($_SESSION['coupon']) ? $_SESSION['coupon']['code'] : ''; ?>" style="text-transform:uppercase;">
                            <button type="submit" class="btn-sweet btn-sweet-pink" style="padding:8px 18px; font-size:0.9rem;">
                                Pakai
                            </button>
                        </div>
                    </form>

                    <!-- Breakdown -->
                    <div style="display:flex; justify-content:space-between; margin-bottom:12px; font-size:0.95rem;">
                        <span style="color:var(--text-muted);">Total Belanja:</span>
                        <strong><?= format_rupiah($subtotal); ?></strong>
                    </div>

                    <?php if ($discountAmount > 0): ?>
                        <div style="display:flex; justify-content:space-between; margin-bottom:12px; font-size:0.95rem; color:#047857;">
                            <span>Diskon Kupon (<?= $_SESSION['coupon']['code']; ?>):</span>
                            <strong>-<?= format_rupiah($discountAmount); ?></strong>
                        </div>
                    <?php endif; ?>

                    <div style="display:flex; justify-content:space-between; margin-bottom:18px; font-size:0.95rem;">
                        <span style="color:var(--text-muted);">Ongkos Kirim:</span>
                        <strong><?= $shippingCost === 0 ? '<span style="color:#047857;">GRATIS</span>' : format_rupiah($shippingCost); ?></strong>
                    </div>

                    <div style="display:flex; justify-content:space-between; margin-bottom:24px; padding-top:14px; border-top:2px dashed var(--border); font-size:1.2rem;">
                        <strong>Total Pembayaran:</strong>
                        <strong style="color:var(--pink-dark); font-family:'Baloo 2', cursive; font-size:1.6rem;"><?= format_rupiah($grandTotal); ?></strong>
                    </div>

                    <a href="checkout.php" class="btn-sweet btn-sweet-pink" style="width:100%; font-size:1.1rem; padding:14px 28px;">
                        Lanjut ke Checkout <i class="fa-solid fa-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
