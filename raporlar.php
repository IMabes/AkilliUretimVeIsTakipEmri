<?php
// Tarih aralığına göre liste + CSV indir
require_once __DIR__ . '/parcalar/baslangic.php';
require_roles(['yonetici', 'uretim_muduru']);

$pageTitle = 'Raporlar';
$pdo = db();

$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-d');
$export = $_GET['export'] ?? '';

$sqlBase =
    "SELECT s.id, m.firma_adi, s.siparis_tarihi, s.teslim_tarihi, sd.kod AS siparis_durum_kod,
            ie.kod AS is_emri_kodu, uo.kod AS oncelik_kod, idur.kod AS is_emri_durum_kod
     FROM siparisler s
     INNER JOIN musteriler m ON m.id = s.musteri_id
     INNER JOIN siparis_durumlari sd ON sd.id = s.siparis_durum_id
     LEFT JOIN is_emirleri ie ON ie.siparis_id = s.id
     LEFT JOIN oncelik_seviyeleri uo ON uo.id = ie.oncelik_id
     LEFT JOIN is_emri_durumlari idur ON idur.id = ie.is_emri_durum_id
     WHERE s.siparis_tarihi BETWEEN ? AND ?
     ORDER BY s.id DESC";

if ($export === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="siparis_raporu_' . date('Ymd') . '.csv"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Sipariş No', 'Müşteri', 'Sipariş Tarihi', 'Termin', 'Durum', 'İş Emri Kodu', 'Öncelik', 'İş Emri Durum'], ';');
    $st = $pdo->prepare($sqlBase);
    $st->execute([$from, $to]);
    while ($row = $st->fetch()) {
        fputcsv($out, [
            $row['id'],
            $row['firma_adi'],
            $row['siparis_tarihi'],
            $row['teslim_tarihi'],
            order_status_label($row['siparis_durum_kod']),
            $row['is_emri_kodu'] ?? '',
            $row['oncelik_kod'] ? priority_label($row['oncelik_kod']) : '',
            $row['is_emri_durum_kod'] ? work_order_status_label($row['is_emri_durum_kod']) : '',
        ], ';');
    }
    fclose($out);
    exit;
}

$st = $pdo->prepare($sqlBase);
$st->execute([$from, $to]);
$rows = $st->fetchAll();

require __DIR__ . '/parcalar/ust.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h1 class="h4 mb-0">Raporlar</h1>
    <a class="btn btn-outline-success btn-sm"
       href="raporlar.php?from=<?= e(urlencode($from)) ?>&to=<?= e(urlencode($to)) ?>&export=csv">Excel (CSV) indir</a>
</div>

<form class="row g-2 align-items-end mb-4" method="get">
    <div class="col-auto">
        <label class="form-label small mb-0">Başlangıç</label>
        <input type="date" name="from" class="form-control form-control-sm" value="<?= e($from) ?>">
    </div>
    <div class="col-auto">
        <label class="form-label small mb-0">Bitiş</label>
        <input type="date" name="to" class="form-control form-control-sm" value="<?= e($to) ?>">
    </div>
    <div class="col-auto">
        <button type="submit" class="btn btn-primary btn-sm">Filtrele</button>
    </div>
</form>

<p class="text-muted small">Yazdır veya PDF için tarayıcıdan <kbd>Ctrl+P</kbd> kullanabilirsiniz (bu sayfa yazdırmaya uyarlanmıştır).</p>

<div class="card border-0 shadow-sm print-friendly">
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
            <tr>
                <th>#</th>
                <th>Müşteri</th>
                <th>Tarih</th>
                <th>Termin</th>
                <th>Sipariş</th>
                <th>İş emri</th>
                <th>Öncelik</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= (int) $r['id'] ?></td>
                    <td><?= e($r['firma_adi']) ?></td>
                    <td><?= e($r['siparis_tarihi']) ?></td>
                    <td><?= e($r['teslim_tarihi']) ?></td>
                    <td><?= e(order_status_label($r['siparis_durum_kod'])) ?></td>
                    <td><?= e($r['is_emri_kodu'] ?? '—') ?></td>
                    <td><?= $r['oncelik_kod'] ? e(priority_label($r['oncelik_kod'])) : '—' ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">Bu aralıkta kayıt yok.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/parcalar/alt.php'; ?>
