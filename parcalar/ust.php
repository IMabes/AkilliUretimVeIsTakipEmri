<?php
/** @var string $pageTitle */
$u = current_user();
if ($u === null) {
    redirect('login.php');
}

// bildirimleri çekiyorum (hata olursa boş liste)
$notifs = [];
try {
    $stmt = db()->prepare(
        'SELECT id, mesaj, okundu, olusturulma_tarihi FROM bildirimler WHERE kullanici_id = ? ORDER BY olusturulma_tarihi DESC LIMIT 10'
    );
    $stmt->execute([$u['id']]);
    $notifs = $stmt->fetchAll();
} catch (Throwable $e) {
    $notifs = [];
}
$unread = 0;
foreach ($notifs as $n) {
    if (!(int) $n['okundu']) {
        $unread++;
    }
}

$isCustomer = $u['role'] === 'musteri';
$canCustomers = in_array($u['role'], ['yonetici', 'uretim_muduru'], true);
$canOrders = $canCustomers;
$canReports = $canCustomers;
$canUsers = $u['role'] === 'yonetici';
$homeApp = $isCustomer ? 'portal_siparisler.php' : 'dashboard.php';
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
<body class="app-body">
<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
    <div class="container-fluid">
        <a class="navbar-brand fw-semibold" href="<?= e($homeApp) ?>">Üretim Takip</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navMain">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link" href="ana_sayfa.php">Kurumsal</a></li>
                <?php if ($isCustomer): ?>
                    <li class="nav-item"><a class="nav-link" href="musteri_portal.php">Siparişlerim</a></li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link" href="dashboard.php">Panel</a></li>
                    <?php if ($canCustomers): ?>
                        <li class="nav-item"><a class="nav-link" href="musteriler.php">Müşteriler</a></li>
                        <li class="nav-item"><a class="nav-link" href="siparisler.php">Siparişler</a></li>
                    <?php endif; ?>
                    <li class="nav-item"><a class="nav-link" href="is_emirleri.php">İş Emirleri</a></li>
                    <li class="nav-item"><a class="nav-link" href="uretim_takip.php">Üretim Takibi</a></li>
                    <?php if ($canReports): ?>
                        <li class="nav-item"><a class="nav-link" href="raporlar.php">Raporlar</a></li>
                    <?php endif; ?>
                    <?php if ($canUsers): ?>
                        <li class="nav-item"><a class="nav-link" href="kullanicilar.php">Personel</a></li>
                    <?php endif; ?>
                <?php endif; ?>
            </ul>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <div class="dropdown">
                    <button class="btn btn-outline-light btn-sm position-relative" type="button" data-bs-toggle="dropdown">
                        Bildirimler
                        <?php if ($unread > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"><?= (int) $unread ?></span>
                        <?php endif; ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow" style="min-width: 280px;">
                        <?php if (!$notifs): ?>
                            <li><span class="dropdown-item-text text-muted">Bildirim yok</span></li>
                        <?php else: ?>
                            <?php foreach ($notifs as $n): ?>
                                <li>
                                    <a class="dropdown-item small<?= (int) $n['okundu'] ? ' text-muted' : '' ?>"
                                       href="bildirimler.php?id=<?= (int) $n['id'] ?>">
                                        <?= e($n['mesaj']) ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>
                <button type="button" class="btn btn-outline-light btn-sm" id="themeToggle" title="Tema">Gece</button>
                <span class="text-white-50 small d-none d-md-inline"><?= e($u['name']) ?> · <?= e(role_label($u['role'])) ?></span>
                <a class="btn btn-light btn-sm" href="logout.php">Çıkış</a>
            </div>
        </div>
    </div>
</nav>

<main class="container py-4">
<?php
$f = flash_pull();
if ($f):
    $cls = $f['type'] === 'danger' ? 'danger' : ($f['type'] === 'success' ? 'success' : 'info');
    ?>
    <div class="alert alert-<?= e($cls) ?> alert-dismissible fade show" role="alert">
        <?= e($f['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
