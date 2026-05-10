<?php
// Müşteri tarafı — kendi siparişlerini görüyor burada
require_once __DIR__ . '/parcalar/baslangic.php';
require_login();

$u = current_user();
if ($u === null || $u['role'] !== 'musteri') {
    redirect('dashboard.php');
}

$musteriId = ensure_musteri_id_for_session();
if ($musteriId === null || $musteriId < 1) {
    flash('danger', 'Müşteri kaydınız eşleştirilemedi. Lütfen destek ile iletişime geçin.');
    redirect('logout.php');
}

$pageTitle = 'Siparişlerim';
$pdo = db();

$firmaRow = null;
$stmt = $pdo->prepare('SELECT firma_adi FROM musteriler WHERE id = ? LIMIT 1');
$stmt->execute([$musteriId]);
$firmaRow = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf'] ?? null)) {
        flash('danger', 'Oturum doğrulaması başarısız.');
        redirect('musteri_portal.php');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'create_order') {
        $teslim = trim((string) ($_POST['teslim_tarihi'] ?? ''));
        $notlar = trim((string) ($_POST['notlar'] ?? ''));

        if ($teslim === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $teslim)) {
            flash('danger', 'Geçerli bir termin tarihi seçin.');
        } else {
            try {
                $stmt = $pdo->prepare('SELECT id FROM siparis_durumlari WHERE kod = ? LIMIT 1');
                $stmt->execute(['beklemede']);
                $durumId = (int) $stmt->fetchColumn();

                $stmt = $pdo->prepare(
                    'INSERT INTO siparisler (musteri_id, siparis_tarihi, teslim_tarihi, siparis_durum_id, notlar)
                     VALUES (?, ?, ?, ?, ?)'
                );
                $stmt->execute([
                    $musteriId,
                    date('Y-m-d'),
                    $teslim,
                    $durumId,
                    $notlar === '' ? null : $notlar,
                ]);
                $sid = (int) $pdo->lastInsertId();

                $firmaAd = $firmaRow ? (string) $firmaRow['firma_adi'] : 'Müşteri';
                notify_message(sprintf('Portal: yeni sipariş talebi #%d (%s)', $sid, $firmaAd));
                flash('success', 'Sipariş talebiniz alındı. Ekibimiz en kısa sürede işleme alacaktır.');
            } catch (Throwable $e) {
                flash('danger', 'Sipariş oluşturulamadı.');
            }
        }
    }

    redirect('musteri_portal.php');
}

$stmt = $pdo->prepare(
    'SELECT s.id, s.siparis_tarihi, s.teslim_tarihi, s.notlar, s.kayit_tarihi,
            sd.kod AS siparis_durum_kod, sd.ad AS siparis_durum_ad
     FROM siparisler s
     INNER JOIN siparis_durumlari sd ON sd.id = s.siparis_durum_id
     WHERE s.musteri_id = ?
     ORDER BY s.kayit_tarihi DESC, s.id DESC'
);
$stmt->execute([$musteriId]);
$orderRows = $stmt->fetchAll();

$siparisIds = [];
foreach ($orderRows as $r) {
    $siparisIds[] = (int) $r['id'];
}

$workBySiparis = [];
$stepsByWo = [];

if (count($siparisIds) > 0) {
    $yerler = implode(',', array_fill(0, count($siparisIds), '?'));
    $sql = "SELECT wo.id, wo.siparis_id, wo.kod, wo.notlar,
                   idur.kod AS is_emri_durum_kod, idur.ad AS is_emri_durum_ad
            FROM is_emirleri wo
            INNER JOIN is_emri_durumlari idur ON idur.id = wo.is_emri_durum_id
            WHERE wo.siparis_id IN ($yerler)
            ORDER BY wo.id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($siparisIds);
    while ($w = $stmt->fetch()) {
        $sid = (int) $w['siparis_id'];
        $workBySiparis[$sid][] = $w;
    }

    $woIds = [];
    foreach ($workBySiparis as $satirlar) {
        foreach ($satirlar as $satir) {
            $woIds[] = (int) $satir['id'];
        }
    }

    if (count($woIds) > 0) {
        $yerler2 = implode(',', array_fill(0, count($woIds), '?'));
        $sql2 = "SELECT ua.is_emri_id, sua.sira, sua.ad AS asama_ad,
                        uad.kod AS asama_durum_kod, uad.ad AS asama_durum_ad
                 FROM uretim_asamalari ua
                 INNER JOIN standart_uretim_asamalari sua ON sua.id = ua.standart_asama_id
                 INNER JOIN uretim_asama_durumlari uad ON uad.id = ua.uretim_asama_durum_id
                 WHERE ua.is_emri_id IN ($yerler2)
                 ORDER BY ua.is_emri_id, sua.sira";
        $stmt = $pdo->prepare($sql2);
        $stmt->execute($woIds);
        while ($row = $stmt->fetch()) {
            $wid = (int) $row['is_emri_id'];
            $stepsByWo[$wid][] = $row;
        }
    }
}

require __DIR__ . '/parcalar/ust.php';
?>

<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
    <div>
        <h1 class="h4 mb-1">Siparişlerim</h1>
        <?php if ($firmaRow): ?>
            <p class="text-muted small mb-0"><?= e((string) $firmaRow['firma_adi']) ?></p>
        <?php endif; ?>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <h2 class="h6 fw-semibold mb-3">Yeni sipariş talebi</h2>
        <form method="post" class="row g-3 align-items-end">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="create_order">
            <div class="col-md-3">
                <label class="form-label">İstenen termin</label>
                <input type="date" name="teslim_tarihi" class="form-control" required
                       value="<?= e(date('Y-m-d', strtotime('+14 days'))) ?>">
            </div>
            <div class="col-md-7">
                <label class="form-label">Sipariş notu <span class="text-muted fw-normal">(ürün, miktar, ölçü)</span></label>
                <input type="text" name="notlar" class="form-control" maxlength="2000"
                       placeholder="Örn. Özel ölçü dolap, 2 adet, renk kodu …">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">Gönder</button>
            </div>
        </form>
        <p class="small text-muted mb-0 mt-2">
            Talebiniz “Bekliyor” durumunda kaydedilir; üretim planlaması sonrası iş emri ve hat aşamaları burada görünür.
        </p>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white fw-semibold">Geçmiş ve durum</div>
    <div class="card-body p-0">
        <?php if (!$orderRows): ?>
            <p class="text-muted p-4 mb-0">Henüz sipariş kaydı yok. Yukarıdan ilk talebinizi oluşturabilirsiniz.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Talep</th>
                            <th>Termin</th>
                            <th>Genel durum</th>
                            <th class="no-print">İlerleme</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orderRows as $o): ?>
                            <?php
                            $oid = (int) $o['id'];
                            $works = $workBySiparis[$oid] ?? [];
                            $collapseId = 'detay-' . $oid;
                            ?>
                            <tr>
                                <td class="text-muted"><?= $oid ?></td>
                                <td><?= e((string) $o['siparis_tarihi']) ?></td>
                                <td><?= e((string) $o['teslim_tarihi']) ?></td>
                                <td>
                                    <span class="badge text-bg-secondary"><?= e(order_status_label((string) $o['siparis_durum_kod'])) ?></span>
                                </td>
                                <td class="no-print">
                                    <?php if (!$works): ?>
                                        <span class="text-muted small">İş emri henüz oluşturulmadı</span>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-outline-primary" type="button"
                                                data-bs-toggle="collapse" data-bs-target="#<?= e($collapseId) ?>">
                                            Aşamaları göster
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php if ($works): ?>
                                <tr class="collapse-row">
                                    <td colspan="5" class="p-0 border-0">
                                        <div class="collapse px-3 pb-3" id="<?= e($collapseId) ?>">
                                            <div class="border rounded-3 bg-body-secondary bg-opacity-25 p-3 mt-0">
                                                <?php foreach ($works as $w): ?>
                                                    <?php $wid = (int) $w['id']; ?>
                                                    <div class="mb-3">
                                                        <div class="fw-semibold small mb-1">
                                                            <?= e((string) $w['kod']) ?>
                                                            <span class="text-muted fw-normal">
                                                                · <?= e(work_order_status_label((string) $w['is_emri_durum_kod'])) ?>
                                                            </span>
                                                        </div>
                                                        <?php if (!empty($w['notlar'])): ?>
                                                            <p class="small text-muted mb-2"><?= e((string) $w['notlar']) ?></p>
                                                        <?php endif; ?>
                                                        <?php $steps = $stepsByWo[$wid] ?? []; ?>
                                                        <?php if (!$steps): ?>
                                                            <p class="small text-muted mb-0">Bu iş emri için üretim satırı henüz tanımlanmadı.</p>
                                                        <?php else: ?>
                                                            <ul class="list-group list-group-flush small mb-0 portal-step-list">
                                                                <?php foreach ($steps as $step): ?>
                                                                    <li class="list-group-item d-flex justify-content-between align-items-center px-2 py-2">
                                                                        <span><?= e((string) $step['asama_ad']) ?></span>
                                                                        <span class="badge text-bg-light border">
                                                                            <?= e(step_status_label((string) $step['asama_durum_kod'])) ?>
                                                                        </span>
                                                                    </li>
                                                                <?php endforeach; ?>
                                                            </ul>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/parcalar/alt.php'; ?>
