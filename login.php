<?php
// Giriş formu — başarılı olunca panele atıyorum
require_once __DIR__ . '/parcalar/baslangic.php';

if ($cu = current_user()) {
    redirect($cu['role'] === 'musteri' ? 'musteri_portal.php' : 'dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf'] ?? null)) {
        $error = 'Oturum doğrulaması başarısız. Sayfayı yenileyip tekrar deneyin.';
    } else {
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        $pdo = db();
        $stmt = $pdo->prepare(
            'SELECT k.id, k.ad_soyad, k.e_posta, k.sifre_hash, r.kod AS rol_kod
             FROM kullanicilar k
             INNER JOIN roller r ON r.id = k.rol_id
             WHERE k.e_posta = ? LIMIT 1'
        );
        $stmt->execute([$email]);
        $row = $stmt->fetch();

        if ($row && password_verify($password, $row['sifre_hash'])) {
            login_user_from_row([
                'id' => (int) $row['id'],
                'ad_soyad' => (string) $row['ad_soyad'],
                'e_posta' => (string) $row['e_posta'],
                'rol_kod' => (string) $row['rol_kod'],
            ]);
            redirect($row['rol_kod'] === 'musteri' ? 'musteri_portal.php' : 'dashboard.php');
        }

        $error = 'E-posta veya şifre hatalı.';
    }
}
?>
<!DOCTYPE html>
<html lang="tr" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Giriş · Akıllı Üretim Takip</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="dosyalar/css/style.css">
</head>
<body class="login-gradient d-flex align-items-center min-vh-100">
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-5 col-lg-4">
            <div class="card shadow-lg border-0">
                <div class="card-body p-4 p-md-5">
                    <h1 class="h4 mb-1 text-center">Akıllı Üretim Takip</h1>
                    <p class="text-muted text-center small mb-4">İş emri ve üretim yönetimi</p>
                    <?php if ($error): ?>
                        <div class="alert alert-danger py-2"><?= e($error) ?></div>
                    <?php endif; ?>
                    <form method="post" autocomplete="off">
                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                        <div class="mb-3">
                            <label class="form-label">E-posta</label>
                            <input type="email" name="email" class="form-control" required autofocus>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Şifre</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Giriş yap</button>
                    </form>
                    <p class="small text-muted mt-3 mb-0 text-center">
                        Demo personel: admin@demo.local / <strong>password</strong>
                    </p>
                    <p class="small text-center mt-2 mb-0">
                        <a href="register.php">Kayıt ol</a>
                        <span class="text-muted"> · </span>
                        <a href="index.php">Ana sayfa</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="dosyalar/js/uygulama.js"></script>
</body>
</html>
