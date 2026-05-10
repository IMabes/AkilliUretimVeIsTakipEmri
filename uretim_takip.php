<?php
// Hat adımları — başlat tamamla butonları burada
require_once __DIR__ . '/parcalar/baslangic.php';
require_login();
redirect_if_customer();

$pageTitle = 'Üretim takibi';
$pdo = db();
$u = current_user();

$woFilter = isset($_GET['wo']) ? (int) $_GET['wo'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf'] ?? null)) {
        flash('danger', 'Oturum doğrulaması başarısız.');
        redirect('uretim_takip.php');
    }
    $asamaId = (int) ($_POST['asama_id'] ?? 0);
    $action = $_POST['asama_aksiyon'] ?? '';

    $st = $pdo->prepare(
        'SELECT ua.*, ie.atanan_kullanici_id, ie.id AS is_emri_id, ie.siparis_id, uad.kod AS asama_durum_kod
         FROM uretim_asamalari ua
         INNER JOIN is_emirleri ie ON ie.id = ua.is_emri_id
         INNER JOIN uretim_asama_durumlari uad ON uad.id = ua.uretim_asama_durum_id
         WHERE ua.id = ?'
    );
    $st->execute([$asamaId]);
    $step = $st->fetch();
    if (!$step) {
        flash('danger', 'Adım bulunamadı.');
        redirect('uretim_takip.php');
    }
    if ($u['role'] === 'personel' && (int) $step['atanan_kullanici_id'] !== $u['id']) {
        flash('danger', 'Bu iş emrine atanmadınız.');
        redirect('uretim_takip.php');
    }

    $returnWo = (int) ($_POST['return_wo'] ?? 0);

    try {
        $stmt = $pdo->prepare('SELECT id FROM uretim_asama_durumlari WHERE kod = ? LIMIT 1');
        $stmt->execute(['uretimde']);
        $durUretimde = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare('SELECT id FROM uretim_asama_durumlari WHERE kod = ? LIMIT 1');
        $stmt->execute(['tamamlandi']);
        $durTamam = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare('SELECT id FROM is_emri_durumlari WHERE kod = ? LIMIT 1');
        $stmt->execute(['devam_ediyor']);
        $ieDevam = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare('SELECT id FROM is_emri_durumlari WHERE kod = ? LIMIT 1');
        $stmt->execute(['acik']);
        $ieAcik = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare('SELECT id FROM is_emri_durumlari WHERE kod = ? LIMIT 1');
        $stmt->execute(['bitti']);
        $ieBitti = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare('SELECT id FROM siparis_durumlari WHERE kod = ? LIMIT 1');
        $stmt->execute(['tamamlandi']);
        $sdTamam = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare('SELECT id FROM siparis_durumlari WHERE kod = ? LIMIT 1');
        $stmt->execute(['beklemede']);
        $sdBekle = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare('SELECT id FROM siparis_durumlari WHERE kod = ? LIMIT 1');
        $stmt->execute(['uretimde']);
        $sdUret = (int) $stmt->fetchColumn();

        if ($action === 'start') {
            $pdo->prepare(
                'UPDATE uretim_asamalari SET uretim_asama_durum_id=?, baslama_zamani=COALESCE(baslama_zamani, NOW()) WHERE id=?'
            )->execute([$durUretimde, $asamaId]);
            $pdo->prepare(
                'UPDATE is_emirleri SET is_emri_durum_id=? WHERE id=? AND is_emri_durum_id=?'
            )->execute([$ieDevam, (int) $step['is_emri_id'], $ieAcik]);
            flash('success', 'Aşama başlatıldı.');
        } elseif ($action === 'complete') {
            $pdo->prepare(
                'UPDATE uretim_asamalari SET uretim_asama_durum_id=?, bitis_zamani=NOW() WHERE id=?'
            )->execute([$durTamam, $asamaId]);

            $stc = $pdo->prepare(
                'SELECT COUNT(*) FROM uretim_asamalari WHERE is_emri_id=? AND uretim_asama_durum_id <> ?'
            );
            $stc->execute([(int) $step['is_emri_id'], $durTamam]);
            $pending = (int) $stc->fetchColumn();
            if ($pending === 0) {
                $pdo->prepare('UPDATE is_emirleri SET is_emri_durum_id=? WHERE id=?')->execute([$ieBitti, (int) $step['is_emri_id']]);
                $pdo->prepare(
                    'UPDATE siparisler SET siparis_durum_id=? WHERE id=? AND siparis_durum_id IN (?,?)'
                )->execute([$sdTamam, (int) $step['siparis_id'], $sdBekle, $sdUret]);
            }
            flash('success', 'Aşama tamamlandı.');
        }
    } catch (Throwable $e) {
        flash('danger', 'İşlem tamamlanamadı.');
    }

    redirect($returnWo > 0 ? ('uretim_takip.php?wo=' . $returnWo) : 'uretim_takip.php');
}

$staffWhere = '';
$params = [];
if ($u['role'] === 'personel') {
    $staffWhere = ' AND wo.atanan_kullanici_id = ? ';
    $params[] = $u['id'];
}

if ($woFilter > 0) {
    $staffWhere .= ' AND wo.id = ? ';
    $params[] = $woFilter;
}

$sql =
    "SELECT wo.id, wo.kod, idur.kod AS is_emri_durum_kod, uo.kod AS oncelik_kod,
            m.firma_adi, s.teslim_tarihi,
            (SELECT COUNT(*) FROM uretim_asamalari ua
             INNER JOIN uretim_asama_durumlari x ON x.id = ua.uretim_asama_durum_id
             WHERE ua.is_emri_id = wo.id AND x.kod = 'tamamlandi') AS tamamlanan_adim,
            (SELECT COUNT(*) FROM uretim_asamalari ua2 WHERE ua2.is_emri_id = wo.id) AS toplam_adim
     FROM is_emirleri wo
     INNER JOIN siparisler s ON s.id = wo.siparis_id
     INNER JOIN musteriler m ON m.id = s.musteri_id
     INNER JOIN is_emri_durumlari idur ON idur.id = wo.is_emri_durum_id
     INNER JOIN oncelik_seviyeleri uo ON uo.id = wo.oncelik_id
     WHERE idur.kod <> 'iptal' $staffWhere
     ORDER BY FIELD(uo.kod,'yuksek','normal','dusuk'), s.teslim_tarihi ASC, wo.id DESC";

$st = $pdo->prepare($sql);
$st->execute($params);
$workOrders = $st->fetchAll();

$stepsByWo = [];
if ($workOrders) {
    $ids = array_map(static fn ($r) => (int) $r['id'], $workOrders);
    $in = implode(',', array_fill(0, count($ids), '?'));
    $st = $pdo->prepare(
        "SELECT ua.*, sta.ad AS asama_adi, sta.sira AS asama_sira, uad.kod AS asama_durum_kod
         FROM uretim_asamalari ua
         INNER JOIN standart_uretim_asamalari sta ON sta.id = ua.standart_asama_id
         INNER JOIN uretim_asama_durumlari uad ON uad.id = ua.uretim_asama_durum_id
         WHERE ua.is_emri_id IN ($in)
         ORDER BY sta.sira"
    );
    $st->execute($ids);
    while ($row = $st->fetch()) {
        $wid = (int) $row['is_emri_id'];
        $stepsByWo[$wid][] = $row;
    }
}

require __DIR__ . '/parcalar/ust.php';
?>

<h1 class="h4 mb-3">Üretim aşamaları</h1>
<p class="text-muted small">Kesim → Montaj → Boya → Paketleme sırasıyla ilerleyin. Bir önceki adım tamamlanmadan sonrakine geçebilirsiniz (demo esnekliği).</p>

<?php foreach ($workOrders as $wo):
    $wid = (int) $wo['id'];
    $steps = $stepsByWo[$wid] ?? [];
    $pct = ((int) $wo['toplam_adim']) > 0
        ? round(((int) $wo['tamamlanan_adim'] / (int) $wo['toplam_adim']) * 100)
        : 0;
    ?>
    <div class="card border-0 shadow-sm mb-4" id="wo-<?= $wid ?>">
        <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <span class="fw-semibold"><?= e($wo['kod']) ?></span>
                <span class="text-muted">· <?= e($wo['firma_adi']) ?></span>
                <span class="badge text-bg-secondary ms-1"><?= e(work_order_status_label($wo['is_emri_durum_kod'])) ?></span>
            </div>
            <div class="small text-muted">Termin: <?= e($wo['teslim_tarihi']) ?> · <?= $pct ?>%</div>
        </div>
        <div class="card-body">
            <div class="progress mb-3" style="height: 8px;">
                <div class="progress-bar" role="progressbar" style="width: <?= $pct ?>%"></div>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                    <tr>
                        <th>Aşama</th>
                        <th>Durum</th>
                        <th>Başlangıç</th>
                        <th>Bitiş</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($steps as $s): ?>
                        <tr>
                            <td><?= e($s['asama_adi']) ?></td>
                            <td><?= e(step_status_label($s['asama_durum_kod'])) ?></td>
                            <td><?= e($s['baslama_zamani'] ?? '—') ?></td>
                            <td><?= e($s['bitis_zamani'] ?? '—') ?></td>
                            <td class="text-end">
                                <?php if ($s['asama_durum_kod'] === 'beklemede'): ?>
                                    <form method="post" class="d-inline">
                                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="asama_id" value="<?= (int) $s['id'] ?>">
                                        <input type="hidden" name="asama_aksiyon" value="start">
                                        <?php if ($woFilter > 0): ?>
                                            <input type="hidden" name="return_wo" value="<?= (int) $woFilter ?>">
                                        <?php endif; ?>
                                        <button type="submit" class="btn btn-sm btn-primary">Başlat</button>
                                    </form>
                                <?php elseif ($s['asama_durum_kod'] === 'uretimde'): ?>
                                    <form method="post" class="d-inline">
                                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="asama_id" value="<?= (int) $s['id'] ?>">
                                        <input type="hidden" name="asama_aksiyon" value="complete">
                                        <?php if ($woFilter > 0): ?>
                                            <input type="hidden" name="return_wo" value="<?= (int) $woFilter ?>">
                                        <?php endif; ?>
                                        <button type="submit" class="btn btn-sm btn-success">Tamamla</button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-success small">Tamamlandı</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<?php if (!$workOrders): ?>
    <div class="alert alert-info">Gösterilecek iş emri yok.</div>
<?php endif; ?>

<?php require __DIR__ . '/parcalar/alt.php'; ?>
