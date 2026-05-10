<?php
// Müşteri kaydı — kullanıcı + firma tek seferde
require_once __DIR__ . '/parcalar/baslangic.php';

if ($cu = current_user()) {
    redirect($cu['role'] === 'musteri' ? 'musteri_portal.php' : 'dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf'] ?? null)) {
        $error = 'Oturum doğrulaması başarısız. Sayfayı yenileyip tekrar deneyin.';
    } else {
        $firma = trim((string) ($_POST['firma_adi'] ?? ''));
        $adSoyad = trim((string) ($_POST['ad_soyad'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $telefon = trim((string) ($_POST['telefon'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $password2 = (string) ($_POST['password_confirm'] ?? '');

        if ($firma === '' || $adSoyad === '' || $email === '') {
            $error = 'Firma adı, ad soyad ve e-posta zorunludur.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Geçerli bir e-posta girin.';
        } elseif (strlen($password) < 8) {
            $error = 'Şifre en az 8 karakter olmalıdır.';
        } elseif ($password !== $password2) {
            $error = 'Şifreler eşleşmiyor.';
        } else {
            $pdo = db();

            $stmt = $pdo->prepare('SELECT id FROM kullanicilar WHERE e_posta = ? LIMIT 1');
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = 'Bu e-posta ile kayıtlı bir hesap zaten var.';
            } else {
                $stmt = $pdo->prepare('SELECT id FROM roller WHERE kod = ? LIMIT 1');
                $stmt->execute(['musteri']);
                $rolId = (int) $stmt->fetchColumn();

                if ($rolId < 1) {
                    $error = 'Sunucu yapılandırması güncel değil. Yöneticinizden veritabanı güncellemesini isteyin.';
                } elseif ($rolId > 0) {
                    $pdo->beginTransaction();
                    try {
                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare(
                            'INSERT INTO kullanicilar (ad_soyad, e_posta, sifre_hash, rol_id) VALUES (?, ?, ?, ?)'
                        );
                        $stmt->execute([$adSoyad, $email, $hash, $rolId]);
                        $uid = (int) $pdo->lastInsertId();

                        $stmt = $pdo->prepare(
                            'INSERT INTO musteriler (firma_adi, telefon, e_posta, kullanici_id) VALUES (?, ?, ?, ?)'
                        );
                        $stmt->execute([
                            $firma,
                            $telefon === '' ? null : $telefon,
                            $email,
                            $uid,
                        ]);

                        $pdo->commit();

                        login_user_from_row([
                            'id' => $uid,
                            'ad_soyad' => $adSoyad,
                            'e_posta' => $email,
                            'rol_kod' => 'musteri',
                        ]);
                        flash('success', 'Hesabınız oluşturuldu. Sipariş oluşturabilirsiniz.');
                        redirect('musteri_portal.php');
                    } catch (Throwable $e) {
                        $pdo->rollBack();
                        $error = 'Kayıt sırasında bir hata oluştu. Lütfen tekrar deneyin.';
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kayıt ol · Akıllı Üretim Takip</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="dosyalar/css/style.css">
</head>
<body class="login-gradient d-flex align-items-center min-vh-100 py-4">
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-7 col-lg-6 col-xl-5">
            <div class="card shadow-lg border-0">
                <div class="card-body p-4 p-md-5">
                    <h1 class="h4 mb-1 text-center">Müşteri kaydı</h1>
                    <p class="text-muted text-center small mb-4">Firma bilgilerinizle hesap oluşturun</p>
                    <?php if ($error): ?>
                        <div class="alert alert-danger py-2"><?= e($error) ?></div>
                    <?php endif; ?>
                    <form method="post" autocomplete="off">
                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                        <div class="mb-3">
                            <label class="form-label">Firma / kurum adı</label>
                            <input type="text" name="firma_adi" class="form-control" required maxlength="200"
                                   value="<?= e(trim((string) ($_POST['firma_adi'] ?? ''))) ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Yetkili ad soyad</label>
                            <input type="text" name="ad_soyad" class="form-control" required maxlength="120"
                                   value="<?= e(trim((string) ($_POST['ad_soyad'] ?? ''))) ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">E-posta (giriş için)</label>
                            <input type="email" name="email" class="form-control" required maxlength="190"
                                   value="<?= e(trim((string) ($_POST['email'] ?? ''))) ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Telefon <span class="text-muted">(isteğe bağlı)</span></label>
                            <input type="text" name="telefon" class="form-control" maxlength="40"
                                   value="<?= e(trim((string) ($_POST['telefon'] ?? ''))) ?>">
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Şifre</label>
                                <input type="password" name="password" class="form-control" required minlength="8"
                                       autocomplete="new-password">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Şifre tekrar</label>
                                <input type="password" name="password_confirm" class="form-control" required minlength="8"
                                       autocomplete="new-password">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 mt-4">Kayıt ol</button>
                    </form>
                    <p class="small text-center mt-3 mb-0">
                        Zaten hesabınız var mı?
                        <a href="giris.php">Giriş yap</a>
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
