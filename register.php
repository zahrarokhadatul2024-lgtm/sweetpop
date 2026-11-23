<?php
$pageTitle = "Register";
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    header("Location: index.php");
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name  = trim($_POST['last_name'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $password   = $_POST['password'] ?? '';
    $confirm    = $_POST['confirm_password'] ?? '';
    $mobile     = trim($_POST['mobile'] ?? '');
    $address1   = trim($_POST['address1'] ?? '');
    $address2   = trim($_POST['address2'] ?? '');

    if ($first_name === '') $errors[] = "Nama depan wajib diisi.";
    if ($last_name === '')  $errors[] = "Nama belakang wajib diisi.";
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Email tidak valid.";
    if (strlen($password) < 4) $errors[] = "Password minimal 4 karakter.";
    if ($password !== $confirm) $errors[] = "Konfirmasi password tidak cocok.";

    if (empty($errors)) {
        $checkStmt = mysqli_prepare($con, "SELECT user_id FROM user_info WHERE email = ?");
        mysqli_stmt_bind_param($checkStmt, "s", $email);
        mysqli_stmt_execute($checkStmt);
        $res = mysqli_stmt_get_result($checkStmt);

        if (mysqli_num_rows($res) > 0) {
            $errors[] = "Email tersebut sudah terdaftar. Silakan gunakan email lain atau login.";
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = mysqli_prepare($con, "INSERT INTO user_info (first_name, last_name, email, password, mobile, address1, address2) VALUES (?, ?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "sssssss", $first_name, $last_name, $email, $hashed, $mobile, $address1, $address2);

            if (mysqli_stmt_execute($stmt)) {
                $newUserId = mysqli_insert_id($con);
                $_SESSION['user_id'] = $newUserId;
                $_SESSION['user_name'] = $first_name;
                $_SESSION['user_email'] = $email;

                set_flash('success', 'Selamat datang di SweetPop, ' . $first_name . '! Akun kamu berhasil dibuat ♡');
                $redirect = !empty($_GET['redirect']) ? $_GET['redirect'] : 'index.php';
                header("Location: " . $redirect);
                exit;
            } else {
                $errors[] = "Gagal mendaftarkan akun: " . mysqli_error($con);
            }
        }
    }
}

include 'includes/header.php';
?>

<div class="auth-wrapper">
    <div class="auth-card auth-card-wide">
        <!-- Tab Switcher: Login / Register -->
        <div style="display:flex; background:#fdf2f8; padding:5px; border-radius:12px; margin-bottom:24px; gap:6px;">
            <a href="login.php<?= isset($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : ''; ?>" style="flex:1; text-align:center; padding:10px 14px; border-radius:8px; font-weight:700; font-size:0.9rem; text-decoration:none; color:var(--text-muted); transition:0.2s;">
                <i class="fa-solid fa-arrow-right-to-bracket"></i> Masuk (Login)
            </a>
            <a href="register.php<?= isset($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : ''; ?>" style="flex:1; text-align:center; padding:10px 14px; border-radius:8px; font-weight:700; font-size:0.9rem; text-decoration:none; background:#ffffff; color:var(--pink-dark); box-shadow:0 2px 8px rgba(0,0,0,0.06);">
                <i class="fa-solid fa-user-plus"></i> Daftar (Register)
            </a>
        </div>

        <div class="auth-header">
            <span class="hero-badge">✦ Join the Sweet Club</span>
            <h2>Create Your Account</h2>
            <p style="color:var(--text-muted); font-size:0.95rem;">Nikmati kemudahan pemesanan dessert, diskon spesial, dan checkout cepat.</p>
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

        <form action="register.php<?= isset($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : ''; ?>" method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Nama Depan *</label>
                    <input type="text" name="first_name" class="form-control" placeholder="Contoh: Sarah" value="<?= htmlspecialchars($_POST['first_name'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Nama Belakang *</label>
                    <input type="text" name="last_name" class="form-control" placeholder="Contoh: Putri" value="<?= htmlspecialchars($_POST['last_name'] ?? ''); ?>" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Alamat Email *</label>
                    <input type="email" name="email" class="form-control" placeholder="kamu@email.com" value="<?= htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Nomor WhatsApp / HP</label>
                    <input type="text" name="mobile" class="form-control" placeholder="08123456789" value="<?= htmlspecialchars($_POST['mobile'] ?? ''); ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Password *</label>
                    <input type="password" name="password" class="form-control" placeholder="Minimal 4 karakter" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Konfirmasi Password *</label>
                    <input type="password" name="confirm_password" class="form-control" placeholder="Ulangi password" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Alamat Lengkap</label>
                <input type="text" name="address1" class="form-control" placeholder="Nama Jalan, Nomor Rumah, RT/RW, Kecamatan" value="<?= htmlspecialchars($_POST['address1'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label class="form-label">Kota / Patokan Khusus</label>
                <input type="text" name="address2" class="form-control" placeholder="Kota / Detail Patokan Rumah" value="<?= htmlspecialchars($_POST['address2'] ?? ''); ?>">
            </div>

            <button type="submit" class="btn-sweet btn-sweet-pink" style="width: 100%; margin-top: 10px;">
                Daftar Akun SweetPop <i class="fa-solid fa-arrow-right"></i>
            </button>
        </form>

        <div style="text-align: center; margin-top: 24px; font-size: 0.95rem; color: var(--text-muted);">
            Sudah punya akun? <a href="login.php<?= isset($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : ''; ?>" style="font-weight: 700; color: var(--pink-dark);">Login di sini ♡</a>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
