<?php
// Sipariş tablosu — müşteri seçip tarih termin giriyoruz
require_once __DIR__ . '/parcalar/baslangic.php';
require_roles(['yonetici', 'uretim_muduru']);

$pageTitle = 'Siparişler';
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf'] ?? null)) {
        flash('danger', 'Oturum doğrulaması başarısız.');
        redirect('siparisler.php');
    }

    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'create') {
            $stmt = $pdo->prepare('SELECT id FROM siparis_durumlari WHERE kod = ? LIMIT 1');
            $stmt->execute([(string) $_POST['siparis_durum_kod']]);
            $durumId = (int) $stmt->fetchColumn();

            $notlar = trim((string) ($_POST['notlar'] ?? ''));
            $stmt = $pdo->prepare(
                'INSERT INTO siparisler (musteri_id, siparis_tarihi, teslim_tarihi, siparis_durum_id, notlar)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                (int) $_POST['musteri_id'],
                $_POST['siparis_tarihi'],
                $_POST['teslim_tarihi'],
                $durumId,
                $notlar === '' ? null : $notlar,
            ]);
            flash('success', 'Sipariş oluşturuldu.');
        }

        if ($action === 'update') {
            $id = (int) $_POST['id'];
            $stmt = $pdo->prepare('SELECT id FROM siparis_durumlari WHERE kod = ? LIMIT 1');
            $stmt->execute([(string) $_POST['siparis_durum_kod']]);
            $durumId = (int) $stmt->fetchColumn();

            $notlar = trim((string) ($_POST['notlar'] ?? ''));
            $stmt = $pdo->prepare(
                'UPDATE siparisler SET musteri_id = ?, siparis_tarihi = ?, teslim_tarihi = ?, siparis_durum_id = ?, notlar = ?
                 WHERE id = ?'
            );
            $stmt->execute([
                (int) $_POST['musteri_id'],
                $_POST['siparis_tarihi'],
                $_POST['teslim_tarihi'],
                $durumId,
                $notlar === '' ? null : $notlar,
                $id,
            ]);
            flash('success', 'Sipariş güncellendi.');
        }

        if ($action === 'delete') {
            $id = (int) $_POST['id'];
            try {
                $stmt = $pdo->prepare('DELETE FROM siparisler WHERE id = ?');
                $stmt->execute([$id]);
                flash('success', 'Sipariş silindi.');
            } catch (Throwable $e) {
                flash('danger', 'Bu siparişe bağlı iş emri olduğu için silinemedi.');
            }
        }
    } catch (Throwable $e) {
        flash('danger', 'İşlem başarısız.');
    }

    redirect('siparisler.php');
}

$musteriler = $pdo->query('SELECT id, firma_adi FROM musteriler ORDER BY firma_adi')->fetchAll();
$durumSecenekleri = $pdo->query('SELECT kod, ad FROM siparis_durumlari ORDER BY sira')->fetchAll();

$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$editRow = null;
if ($editId > 0) {
    $stmt = $pdo->prepare(
        'SELECT s.*, sd.kod AS siparis_durum_kod
         FROM siparisler s
         INNER JOIN siparis_durumlari sd ON sd.id = s.siparis_durum_id
         WHERE s.id = ?'
    );
    $stmt->execute([$editId]);
    $editRow = $stmt->fetch();
}

$sql = 'SELECT s.*, m.firma_adi, sd.kod AS siparis_durum_kod,
        (SELECT COUNT(*) FROM is_emirleri ie WHERE ie.siparis_id = s.id) AS is_emri_sayisi
        FROM siparisler s
        INNER JOIN musteriler m ON m.id = s.musteri_id
        INNER JOIN siparis_durumlari sd ON sd.id = s.siparis_durum_id
        ORDER BY s.siparis_tarihi DESC, s.id DESC';
$rows = $pdo->query($sql)->fetchAll();

require __DIR__ . '/parcalar/ust.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h1 class="h4 mb-0">Sipariş yönetimi</h1>
    <button class="btn btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#orderForm">
        <?= $editRow ? 'Düzenleme' : 'Yeni sipariş' ?>
    </button>
</div>

<div class="collapse <?= $editRow ? 'show' : '' ?> mb-4" id="orderForm">
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="post" class="row g-3">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="<?= $editRow ? 'update' : 'create' ?>">
                <?php if ($editRow): ?>
                    <input type="hidden" name="id" value="<?= (int) $editRow['id'] ?>">
                <?php endif; ?>
                <div class="col-md-6">
                    <label class="form-label">Müşteri</label>
                    <select name="musteri_id" class="form-select" required>
                        <?php foreach ($musteriler as $m): ?>
                            <option value="<?= (int) $m['id'] ?>"
                                <?= ($editRow && (int) $editRow['musteri_id'] === (int) $m['id']) ? 'selected' : '' ?>>
                                <?= e($m['firma_adi']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Sipariş tarihi</label>
                    <input type="date" name="siparis_tarihi" class="form-control" required
                           value="<?= e($editRow['siparis_tarihi'] ?? date('Y-m-d')) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Termin</label>
                    <input type="date" name="teslim_tarihi" class="form-control" required
                           value="<?= e($editRow['teslim_tarihi'] ?? date('Y-m-d', strtotime('+14 days'))) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Durum</label>
                    <select name="siparis_durum_kod" class="form-select">
                        <?php foreach ($durumSecenekleri as $d): ?>
                            <option value="<?= e($d['kod']) ?>"
                                <?= ($editRow && ($editRow['siparis_durum_kod'] ?? '') === $d['kod']) ? 'selected' : (!$editRow && $d['kod'] === 'beklemede' ? 'selected' : '') ?>>
                                <?= e($d['ad']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-8">
                    <label class="form-label">Notlar</label>
                    <input type="text" name="notlar" class="form-control"
                           value="<?= e($editRow['notlar'] ?? '') ?>">
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-success"><?= $editRow ? 'Kaydet' : 'Oluştur' ?></button>
                    <?php if ($editRow): ?>
                        <a class="btn btn-outline-secondary" href="siparisler.php">İptal</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
            <tr>
                <th>#</th>
                <th>Müşteri</th>
                <th>Tarih</th>
                <th>Termin</th>
                <th>Durum</th>
                <th class="text-center">İş emri</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr class="<?= ($r['teslim_tarihi'] < date('Y-m-d') && !in_array($r['siparis_durum_kod'], ['teslim_edildi','tamamlandi'], true)) ? 'table-warning' : '' ?>">
                    <td><?= (int) $r['id'] ?></td>
                    <td><?= e($r['firma_adi']) ?></td>
                    <td><?= e($r['siparis_tarihi']) ?></td>
                    <td><?= e($r['teslim_tarihi']) ?></td>
                    <td><span class="badge text-bg-secondary"><?= e(order_status_label($r['siparis_durum_kod'])) ?></span></td>
                    <td class="text-center"><?= (int) $r['is_emri_sayisi'] ?></td>
                    <td class="text-end text-nowrap">
                        <a class="btn btn-sm btn-outline-primary" href="siparisler.php?edit=<?= (int) $r['id'] ?>">Düzenle</a>
                        <form method="post" class="d-inline" onsubmit="return confirm('Silmek istediğinize emin misiniz?');">
                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger">Sil</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?>
                <tr><td colspan="7" class="text-muted text-center py-4">Önce müşteri ekleyin.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/parcalar/alt.php'; ?>
