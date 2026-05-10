<?php
// Personel hesapları — rol atama şifre işleri burada
require_once __DIR__ . '/parcalar/baslangic.php';
require_roles(['yonetici']);

$pageTitle = 'Personel';
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf'] ?? null)) {
        flash('danger', 'Oturum doğrulaması başarısız.');
        redirect('kullanicilar.php');
    }

    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'create') {
            $pw = (string) ($_POST['sifre'] ?? '');
            if (strlen($pw) < 6) {
                flash('danger', 'Şifre en az 6 karakter olmalı.');
                redirect('kullanicilar.php');
            }
            $stmt = $pdo->prepare('SELECT id FROM roller WHERE kod = ? LIMIT 1');
            $stmt->execute([(string) $_POST['rol_kod']]);
            $rolId = (int) $stmt->fetchColumn();

            $stmt = $pdo->prepare(
                'INSERT INTO kullanicilar (ad_soyad, e_posta, sifre_hash, rol_id) VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([
                trim((string) $_POST['ad_soyad']),
                trim((string) $_POST['e_posta']),
                password_hash($pw, PASSWORD_DEFAULT),
                $rolId,
            ]);
            flash('success', 'Kullanıcı eklendi.');
        }

        if ($action === 'update') {
            $id = (int) $_POST['id'];
            $stmt = $pdo->prepare('SELECT id FROM roller WHERE kod = ? LIMIT 1');
            $stmt->execute([(string) $_POST['rol_kod']]);
            $rolId = (int) $stmt->fetchColumn();

            $pw = (string) ($_POST['sifre'] ?? '');

            if ($pw !== '') {
                $stmt = $pdo->prepare(
                    'UPDATE kullanicilar SET ad_soyad = ?, e_posta = ?, rol_id = ?, sifre_hash = ? WHERE id = ?'
                );
                $stmt->execute([
                    trim((string) $_POST['ad_soyad']),
                    trim((string) $_POST['e_posta']),
                    $rolId,
                    password_hash($pw, PASSWORD_DEFAULT),
                    $id,
                ]);
            } else {
                $stmt = $pdo->prepare(
                    'UPDATE kullanicilar SET ad_soyad = ?, e_posta = ?, rol_id = ? WHERE id = ?'
                );
                $stmt->execute([
                    trim((string) $_POST['ad_soyad']),
                    trim((string) $_POST['e_posta']),
                    $rolId,
                    $id,
                ]);
            }
            flash('success', 'Kullanıcı güncellendi.');
        }

        if ($action === 'delete') {
            $id = (int) $_POST['id'];
            if ($id === current_user()['id']) {
                flash('danger', 'Kendi hesabınızı silemezsiniz.');
            } else {
                try {
                    $stmt = $pdo->prepare('DELETE FROM kullanicilar WHERE id = ?');
                    $stmt->execute([$id]);
                    flash('success', 'Kullanıcı silindi.');
                } catch (Throwable $e) {
                    flash('danger', 'Bu kullanıcıya bağlı kayıtlar olduğu için silinemedi.');
                }
            }
        }
    } catch (Throwable $e) {
        flash('danger', 'İşlem başarısız.');
    }

    redirect('kullanicilar.php');
}

$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$editRow = null;
if ($editId > 0) {
    $stmt = $pdo->prepare(
        'SELECT k.id, k.ad_soyad, k.e_posta, k.kayit_tarihi, r.kod AS rol_kod
         FROM kullanicilar k
         INNER JOIN roller r ON r.id = k.rol_id
         WHERE k.id = ?'
    );
    $stmt->execute([$editId]);
    $editRow = $stmt->fetch();
}

$sql = 'SELECT k.id, k.ad_soyad, k.e_posta, k.kayit_tarihi, r.kod AS rol_kod
        FROM kullanicilar k
        INNER JOIN roller r ON r.id = k.rol_id
        ORDER BY k.ad_soyad';
$rows = $pdo->query($sql)->fetchAll();

require __DIR__ . '/parcalar/ust.php';

$rolSecenekleri = $pdo->query('SELECT kod, ad FROM roller ORDER BY id')->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h1 class="h4 mb-0">Personel / kullanıcılar</h1>
    <button class="btn btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#userForm">
        <?= $editRow ? 'Düzenleme' : 'Yeni kullanıcı' ?>
    </button>
</div>

<div class="collapse <?= $editRow ? 'show' : '' ?> mb-4" id="userForm">
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="post" class="row g-3">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="<?= $editRow ? 'update' : 'create' ?>">
                <?php if ($editRow): ?>
                    <input type="hidden" name="id" value="<?= (int) $editRow['id'] ?>">
                <?php endif; ?>
                <div class="col-md-4">
                    <label class="form-label">Ad soyad</label>
                    <input type="text" name="ad_soyad" class="form-control" required
                           value="<?= e($editRow['ad_soyad'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">E-posta</label>
                    <input type="email" name="e_posta" class="form-control" required
                           value="<?= e($editRow['e_posta'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Rol</label>
                    <select name="rol_kod" class="form-select">
                        <?php foreach ($rolSecenekleri as $rol): ?>
                            <option value="<?= e($rol['kod']) ?>"
                                <?= ($editRow && ($editRow['rol_kod'] ?? '') === $rol['kod']) ? 'selected' : (!$editRow && $rol['kod'] === 'personel' ? 'selected' : '') ?>>
                                <?= e($rol['ad']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Şifre <?= $editRow ? '<span class="text-muted">(boş bırakılırsa değişmez)</span>' : '' ?></label>
                    <input type="password" name="sifre" class="form-control" <?= $editRow ? '' : 'required minlength="6"' ?>
                           autocomplete="new-password">
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-success"><?= $editRow ? 'Kaydet' : 'Ekle' ?></button>
                    <?php if ($editRow): ?>
                        <a class="btn btn-outline-secondary" href="kullanicilar.php">İptal</a>
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
                <th>Ad</th>
                <th>E-posta</th>
                <th>Rol</th>
                <th>Kayıt</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= e($r['ad_soyad']) ?></td>
                    <td><?= e($r['e_posta']) ?></td>
                    <td><?= e(role_label($r['rol_kod'])) ?></td>
                    <td class="small text-muted"><?= e($r['kayit_tarihi']) ?></td>
                    <td class="text-end text-nowrap">
                        <a class="btn btn-sm btn-outline-primary" href="kullanicilar.php?edit=<?= (int) $r['id'] ?>">Düzenle</a>
                        <?php if ((int) $r['id'] !== current_user()['id']): ?>
                            <form method="post" class="d-inline" onsubmit="return confirm('Silmek istediğinize emin misiniz?');">
                                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger">Sil</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/parcalar/alt.php'; ?>
