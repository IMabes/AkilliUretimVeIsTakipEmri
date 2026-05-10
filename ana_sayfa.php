<?php
// Şirketin tanıtım sayfası — giriş yapmadan herkes görebilir
require_once __DIR__ . '/parcalar/baslangic.php';

$u = current_user();
$pageTitle = 'Ana sayfa';
?>
<!DOCTYPE html>
<html lang="tr" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> · Akıllı Üretim Takip</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="dosyalar/css/style.css">
</head>
<body class="landing-body">
<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm landing-nav">
    <div class="container">
        <span class="navbar-brand fw-semibold mb-0">Akıllı Üretim Takip</span>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#landingNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="landingNav">
            <ul class="navbar-nav ms-auto gap-lg-2 align-items-lg-center">
                <li class="nav-item"><a class="nav-link" href="#hizmetler">Hizmetler</a></li>
                <li class="nav-item"><a class="nav-link" href="#surec">Süreç</a></li>
                <?php if ($u): ?>
                    <?php if ($u['role'] === 'musteri'): ?>
                        <li class="nav-item"><a class="btn btn-light btn-sm ms-lg-2" href="musteri_portal.php">Siparişlerim</a></li>
                    <?php else: ?>
                        <li class="nav-item"><a class="btn btn-light btn-sm ms-lg-2" href="dashboard.php">Panel</a></li>
                    <?php endif; ?>
                    <li class="nav-item"><a class="btn btn-outline-light btn-sm" href="logout.php">Çıkış</a></li>
                <?php else: ?>
                    <li class="nav-item"><a class="btn btn-outline-light btn-sm ms-lg-2" href="login.php">Giriş yap</a></li>
                    <li class="nav-item"><a class="btn btn-light btn-sm" href="register.php">Kayıt ol</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<header class="landing-hero text-white bg-primary">
    <div class="container py-5">
        <div class="row align-items-center g-4">
            <div class="col-lg-7">
                <p class="text-white-50 text-uppercase small fw-semibold mb-2 letter-spacing">Üretimde şeffaflık</p>
                <h1 class="display-5 fw-bold mb-3">Siparişinizi verin, üretim aşamalarını tek ekrandan izleyin</h1>
                <p class="lead text-white text-opacity-75 mb-4">
                    Firmamız; özel üretim, iş emri takibi ve hat bazlı ilerleme yönetiminde uzmanlaşmış bir üretim ortağıdır.
                    Müşteri hesabınızla talep oluşturun, ekibimiz siparişinizi üretim hattına aldığında durumu anlık görün.
                </p>
                <div class="d-flex flex-wrap gap-2">
                    <?php if ($u): ?>
                        <?php if ($u['role'] === 'musteri'): ?>
                            <a class="btn btn-light btn-lg" href="musteri_portal.php">Siparişlerime git</a>
                        <?php else: ?>
                            <a class="btn btn-light btn-lg" href="dashboard.php">Yönetim paneli</a>
                        <?php endif; ?>
                    <?php else: ?>
                        <a class="btn btn-light btn-lg" href="register.php">Hemen kayıt ol</a>
                        <a class="btn btn-outline-light btn-lg" href="login.php">Giriş yap</a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="card border-0 shadow-lg landing-card text-dark">
                    <div class="card-body p-4">
                        <h2 class="h5 fw-semibold mb-3">Neden bizi tercih etmelisiniz?</h2>
                        <ul class="list-unstyled mb-0 small">
                            <li class="d-flex gap-2 mb-2"><span class="text-primary">✓</span> Standart üretim aşamalarında net izlenebilirlik</li>
                            <li class="d-flex gap-2 mb-2"><span class="text-primary">✓</span> Termin ve sipariş durumu tek yerde</li>
                            <li class="d-flex gap-2 mb-2"><span class="text-primary">✓</span> Güvenli müşteri portalı ile sipariş oluşturma</li>
                            <li class="d-flex gap-2"><span class="text-primary">✓</span> Üretim müdürü ve hat ekibiyle uyumlu süreç</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>

<main>
    <section id="hizmetler" class="py-5 bg-body">
        <div class="container py-lg-3">
            <h2 class="h3 fw-bold text-center mb-5">Hizmetlerimiz</h2>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body">
                            <div class="rounded-circle bg-primary bg-opacity-10 text-primary fw-bold d-inline-flex align-items-center justify-content-center mb-3 landing-icon-ball">1</div>
                            <h3 class="h5">Özel üretim</h3>
                            <p class="text-muted small mb-0">Ölçü, malzeme ve termin gereksinimlerinize göre üretim planlaması.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body">
                            <div class="rounded-circle bg-primary bg-opacity-10 text-primary fw-bold d-inline-flex align-items-center justify-content-center mb-3 landing-icon-ball">2</div>
                            <h3 class="h5">İş emri takibi</h3>
                            <p class="text-muted small mb-0">Her sipariş iş emrine bağlanır; öncelik ve atama net şekilde yönetilir.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body">
                            <div class="rounded-circle bg-primary bg-opacity-10 text-primary fw-bold d-inline-flex align-items-center justify-content-center mb-3 landing-icon-ball">3</div>
                            <h3 class="h5">Üretim hattı görünürlüğü</h3>
                            <p class="text-muted small mb-0">Kesimden paketlemeye kadar aşamaları sistem üzerinden takip edebilirsiniz.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="surec" class="py-5 border-top">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8 text-center mb-4">
                    <h2 class="h3 fw-bold">Süreç nasıl işler?</h2>
                    <p class="text-muted">Kayıt olduktan sonra birkaç adımda sipariş oluşturur, ekibimiz onay ve üretim adımlarını güncelledikçe portalınızda görürsünüz.</p>
                </div>
            </div>
            <div class="row g-3 justify-content-center">
                <div class="col-6 col-md-3 text-center">
                    <div class="p-3 rounded-3 bg-primary bg-opacity-10 text-primary fw-semibold small">Kayıt</div>
                </div>
                <div class="col-6 col-md-3 text-center">
                    <div class="p-3 rounded-3 bg-primary bg-opacity-10 text-primary fw-semibold small">Sipariş talebi</div>
                </div>
                <div class="col-6 col-md-3 text-center">
                    <div class="p-3 rounded-3 bg-primary bg-opacity-10 text-primary fw-semibold small">Üretim &amp; aşamalar</div>
                </div>
                <div class="col-6 col-md-3 text-center">
                    <div class="p-3 rounded-3 bg-primary bg-opacity-10 text-primary fw-semibold small">Teslim</div>
                </div>
            </div>
        </div>
    </section>

    <section class="py-5 bg-primary text-white">
        <div class="container text-center py-lg-2">
            <h2 class="h4 fw-bold mb-3">Hazır mısınız?</h2>
            <p class="text-white-50 mb-4 mx-auto" style="max-width: 520px;">
                Müşteri hesabı oluşturarak sipariş verebilir veya mevcut hesabınızla giriş yaparak taleplerinizi yönetebilirsiniz.
            </p>
            <?php if (!$u): ?>
                <a class="btn btn-light btn-lg me-2 mb-2" href="register.php">Kayıt ol</a>
                <a class="btn btn-outline-light btn-lg mb-2" href="login.php">Giriş yap</a>
            <?php endif; ?>
        </div>
    </section>
</main>

<footer class="py-4 bg-body border-top">
    <div class="container small text-muted text-center">
        © <?= date('Y') ?> Akıllı Üretim Takip · İş emri ve müşteri portalı
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="dosyalar/js/uygulama.js"></script>
</body>
</html>
