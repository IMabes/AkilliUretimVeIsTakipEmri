<?php
// Müşteri kayıtları — eklemek silmek filan burada
require_once __DIR__ . '/parcalar/baslangic.php';
require_roles(['yonetici', 'uretim_muduru']);

$pageTitle = 'Müşteriler';
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf'] ?? null)) {
        flash('danger', 'Oturum doğrulaması başarısız.');
        redirect('musteriler.php');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $firma = trim((string) $_POST['firma_adi']);
        $tel = trim((string) $_POST['telefon']);
        $mail = trim((string) $_POST['e_posta']);

        $stmt = $pdo->prepare(
            'INSERT INTO musteriler (firma_adi, telefon, e_posta) VALUES (?, ?, ?)'
        );
        $stmt->execute([
            $firma,
            $tel === '' ? null : $tel,
            $mail === '' ? null : $mail,
        ]);
        flash('success', 'Müşteri eklendi.');
    }

    if ($action === 'update') {
        $id = (int) $_POST['id'];
        $firma = trim((string) $_POST['firma_adi']);
        $tel = trim((string) $_POST['telefon']);
        $mail = trim((string) $_POST['e_posta']);

        $stmt = $pdo->prepare(
            'UPDATE musteriler SET firma_adi = ?, telefon = ?, e_posta = ? WHERE id = ?'
        );
        $stmt->execute([
            $firma,
            $tel === '' ? null : $tel,
            $mail === '' ? null : $mail,
            $id,
        ]);
        flash('success', 'Müşteri güncellendi.');
    }

    if ($action === 'delete') {
        $id = (int) $_POST['id'];
        try {
            $stmt = $pdo->prepare('DELETE FROM musteriler WHERE id = ?');
            $stmt->execute([$id]);
            flash('success', 'Müşteri silindi.');
        } catch (Throwable $e) {
            flash('danger', 'Bu müşteriye bağlı sipariş olduğu için silinemedi.');
        }
    }

    redirect('musteriler.php');
}

$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$editRow = null;
if ($editId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM musteriler WHERE id = ?');
    $stmt->execute([$editId]);
    $editRow = $stmt->fetch();
}

$sql = 'SELECT m.*,
        (SELECT COUNT(*) FROM siparisler s WHERE s.musteri_id = m.id) AS siparis_sayisi
        FROM musteriler m
        ORDER BY m.firma_adi';
$rows = $pdo->query($sql)->fetchAll();

require __DIR__ . '/parcalar/ust.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h1 class="h4 mb-0">Müşteri yönetimi</h1>
    <button class="btn btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#formCollapse">
        <?= $editRow ? 'Düzenlemeyi sürdür' : 'Yeni müşteri' ?>
    </button>
</div>

<div class="collapse <?= $editRow ? 'show' : '' ?> mb-4" id="formCollapse">
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="post" class="row g-3">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="<?= $editRow ? 'update' : 'create' ?>">
                <?php if ($editRow): ?>
                    <input type="hidden" name="id" value="<?= (int) $editRow['id'] ?>">
                <?php endif; ?>
                <div class="col-md-4">
                    <label class="form-label">Firma adı</label>
                    <input type="text" name="firma_adi" class="form-control" required
                           value="<?= e($editRow['firma_adi'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Telefon</label>
                    <input type="text" name="telefon" class="form-control"
                           value="<?= e($editRow['telefon'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">E-posta</label>
                    <input type="email" name="e_posta" class="form-control"
                           value="<?= e($editRow['e_posta'] ?? '') ?>">
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-success"><?= $editRow ? 'Kaydet' : 'Ekle' ?></button>
                    <?php if ($editRow): ?>
                        <a class="btn btn-outline-secondary" href="musteriler.php">İptal</a>
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
                <th>Firma</th>
                <th>Telefon</th>
                <th>E-posta</th>
                <th class="text-center">Sipariş</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td class="fw-medium"><?= e($r['firma_adi']) ?></td>
                    <td><?= e($r['telefon'] ?? '') ?></td>
                    <td><?= e($r['e_posta'] ?? '') ?></td>
                    <td class="text-center"><?= (int) $r['siparis_sayisi'] ?></td>
                    <td class="text-end text-nowrap">
                        <a class="btn btn-sm btn-outline-primary" href="musteriler.php?edit=<?= (int) $r['id'] ?>">Düzenle</a>
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
                <tr><td colspan="5" class="text-muted text-center py-4">Kayıt yok.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/parcalar/alt.php'; ?>
