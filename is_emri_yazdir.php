<?php
// Yazdırılabilir iş emri + QR (telefondan okutunca bu sayfa açılsın diye)
require_once __DIR__ . '/parcalar/baslangic.php';
require_login();
redirect_if_customer();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$pdo = db();
$u = current_user();

$st = $pdo->prepare(
    'SELECT wo.*, m.firma_adi, m.telefon, s.teslim_tarihi, sd.kod AS siparis_durum_kod
     FROM is_emirleri wo
     INNER JOIN siparisler s ON s.id = wo.siparis_id
     INNER JOIN musteriler m ON m.id = s.musteri_id
     INNER JOIN siparis_durumlari sd ON sd.id = s.siparis_durum_id
     WHERE wo.id = ?'
);
$st->execute([$id]);
$wo = $st->fetch();
if (!$wo) {
    http_response_code(404);
    echo 'İş emri bulunamadı.';
    exit;
}
if ($u['role'] === 'personel' && (int) $wo['atanan_kullanici_id'] !== $u['id']) {
    http_response_code(403);
    echo 'Bu iş emrine erişim yetkiniz yok.';
    exit;
}

$st = $pdo->prepare(
    'SELECT sta.ad AS asama_adi, uad.kod AS asama_durum_kod
     FROM uretim_asamalari ua
     INNER JOIN standart_uretim_asamalari sta ON sta.id = ua.standart_asama_id
     INNER JOIN uretim_asama_durumlari uad ON uad.id = ua.uretim_asama_durum_id
     WHERE ua.is_emri_id = ?
     ORDER BY sta.sira'
);
$st->execute([$id]);
$steps = $st->fetchAll();

$qrPayload = app_base_url() . '/is_emri_yazdir.php?id=' . $id;
$pageTitle = 'İş emri ' . $wo['kod'];
?>
<!DOCTYPE html>
<html lang="tr" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="dosyalar/css/style.css">
    <style>
        @media print {
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>
<div class="container py-4">
    <p class="no-print"><a href="is_emirleri.php" class="btn btn-sm btn-outline-secondary">← Listeye dön</a></p>
    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            <div class="row align-items-start">
                <div class="col-md-8">
                    <h1 class="h3 mb-1"><?= htmlspecialchars($wo['kod'], ENT_QUOTES, 'UTF-8') ?></h1>
                    <p class="text-muted mb-2"><?= htmlspecialchars($wo['firma_adi'], ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="small mb-0">Termin: <?= htmlspecialchars($wo['teslim_tarihi'], ENT_QUOTES, 'UTF-8') ?>
                        · Sipariş durumu: <?= htmlspecialchars(order_status_label($wo['siparis_durum_kod']), ENT_QUOTES, 'UTF-8') ?></p>
                    <?php if ($wo['notlar']): ?>
                        <p class="mt-3 small"><strong>Not:</strong> <?= nl2br(htmlspecialchars($wo['notlar'], ENT_QUOTES, 'UTF-8')) ?></p>
                    <?php endif; ?>
                </div>
                <div class="col-md-4 text-md-end mt-3 mt-md-0">
                    <div class="d-inline-block p-2 bg-white border rounded">
                        <canvas id="qrCanvas" width="160" height="160" aria-label="QR"></canvas>
                    </div>
                    <div class="small text-muted mt-1">Tarama ile bu sayfayı açın</div>
                </div>
            </div>
            <hr>
            <h2 class="h6">Üretim aşamaları</h2>
            <ul class="list-group list-group-flush">
                <?php foreach ($steps as $s): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span><?= htmlspecialchars($s['asama_adi'], ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="badge text-bg-light border"><?= htmlspecialchars(step_status_label($s['asama_durum_kod']), ENT_QUOTES, 'UTF-8') ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <p class="text-center small text-muted mt-3 no-print">
        <button type="button" class="btn btn-sm btn-outline-primary" onclick="window.print()">Yazdır</button>
    </p>
</div>
<script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
<script>
    QRCode.toCanvas(document.getElementById('qrCanvas'), <?= json_encode($qrPayload, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>, { width: 160, margin: 1 }, function (err) {
        if (err) console.error(err);
    });
</script>
<script src="dosyalar/js/uygulama.js"></script>
</body>
</html>
