<?php
$pageTitle = "Login";
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    header("Location: index.php");
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $errors[] = "Email dan password wajib diisi.";
    } else {
        $stmt = mysqli_prepare($con, "SELECT user_id, first_name, last_name, email, password FROM user_info WHERE email = ?");
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if ($user = mysqli_fetch_assoc($result)) {
            // Support both modern password_hash and plain fallback if any
            $valid = password_verify($password, $user['password']) || ($password === $user['password']);
            if ($valid) {
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['user_name'] = $user['first_name'];
                $_SESSION['user_email'] = $user['email'];

                set_flash('success', 'Selamat datang kembali, ' . $user['first_name'] . '! ♡');
                $redirect = isset($_GET['redirect']) ? $_GET['redirect'] : 'index.php';
                header("Location: " . $redirect);
                exit;
            } else {
                $errors[] = "Email atau password yang kamu masukkan salah.";
            }
        } else {
            $errors[] = "Akun dengan email tersebut tidak ditemukan.";
        }
    }
}

include 'includes/header.php';
?>

<div class="auth-wrapper">
    <div class="auth-card">
        <!-- Tab Switcher: Login / Register -->
        <div style="display:flex; background:#fdf2f8; padding:5px; border-radius:12px; margin-bottom:24px; gap:6px;">
            <a href="login.php<?= isset($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : ''; ?>" style="flex:1; text-align:center; padding:10px 14px; border-radius:8px; font-weight:700; font-size:0.9rem; text-decoration:none; background:#ffffff; color:var(--pink-dark); box-shadow:0 2px 8px rgba(0,0,0,0.06);">
                <i class="fa-solid fa-arrow-right-to-bracket"></i> Masuk (Login)
            </a>
            <a href="register.php<?= isset($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : ''; ?>" style="flex:1; text-align:center; padding:10px 14px; border-radius:8px; font-weight:700; font-size:0.9rem; text-decoration:none; color:var(--text-muted); transition:0.2s;">
                <i class="fa-solid fa-user-plus"></i> Daftar (Register)
            </a>
        </div>

        <div class="auth-header">
            <span class="hero-badge">✦ Welcome Back</span>
            <h2>Sign in to SweetPop</h2>
            <p style="color:var(--text-muted); font-size:0.95rem;">Siap untuk treats manis hari ini? Belum punya akun? Klik tab <strong>Daftar</strong> di atas.</p>
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

        <form action="login.php<?= isset($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : ''; ?>" method="POST">
            <div class="form-group">
                <label class="form-label">Alamat Email</label>
                <input type="email" name="email" class="form-control" placeholder="kamu@email.com" value="<?= htmlspecialchars($_POST['email'] ?? ''); ?>" required autofocus>
            </div>

            <div class="form-group">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" placeholder="Masukkan password kamu" required>
            </div>

            <button type="submit" class="btn-sweet btn-sweet-pink" style="width: 100%; margin-top: 10px;">
                Masuk Sekarang <i class="fa-solid fa-arrow-right"></i>
            </button>
        </form>

        <div style="text-align: center; margin-top: 24px; font-size: 0.95rem; color: var(--text-muted);">
            Belum punya akun? <a href="register.php" style="font-weight: 700; color: var(--pink-dark);">Daftar di sini ♡</a>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
