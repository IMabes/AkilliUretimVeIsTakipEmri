<?php
// İş emirleri listesi — siparişe bağlı WO kodları buradan
require_once __DIR__ . '/parcalar/baslangic.php';
require_login();
redirect_if_customer();

$pageTitle = 'İş emirleri';
$pdo = db();
$u = current_user();
$canManage = in_array($u['role'], ['yonetici', 'uretim_muduru'], true);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canManage) {
    if (!csrf_verify($_POST['csrf'] ?? null)) {
        flash('danger', 'Oturum doğrulaması başarısız.');
        redirect('is_emirleri.php');
    }
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $siparisId = (int) ($_POST['siparis_id'] ?? 0);
        $assign = (int) ($_POST['atanan_kullanici_id'] ?? 0) ?: null;
        $oncelikKod = (string) ($_POST['oncelik_kod'] ?? 'normal');
        $notes = trim((string) ($_POST['notlar'] ?? '')) ?: null;

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT id FROM oncelik_seviyeleri WHERE kod = ? LIMIT 1');
            $stmt->execute([$oncelikKod]);
            $oncelikId = (int) $stmt->fetchColumn();

            $stmt = $pdo->prepare('SELECT id FROM is_emri_durumlari WHERE kod = ? LIMIT 1');
            $stmt->execute(['acik']);
            $acikId = (int) $stmt->fetchColumn();

            $stmt = $pdo->prepare('SELECT id FROM uretim_asama_durumlari WHERE kod = ? LIMIT 1');
            $stmt->execute(['beklemede']);
            $beklemedeAsamaId = (int) $stmt->fetchColumn();

            $tmp = 'TMP-' . bin2hex(random_bytes(5));
            $st = $pdo->prepare(
                'INSERT INTO is_emirleri (siparis_id, kod, atanan_kullanici_id, oncelik_id, is_emri_durum_id, notlar)
                 VALUES (?,?,?,?,?,?)'
            );
            $st->execute([$siparisId, $tmp, $assign, $oncelikId, $acikId, $notes]);
            $wid = (int) $pdo->lastInsertId();
            $code = sprintf('WO-%s-%05d', date('Y'), $wid);
            $pdo->prepare('UPDATE is_emirleri SET kod=? WHERE id=?')->execute([$code, $wid]);

            $ins = $pdo->prepare(
                'INSERT INTO uretim_asamalari (is_emri_id, standart_asama_id, uretim_asama_durum_id)
                 VALUES (?,?,?)'
            );
            $std = $pdo->query('SELECT id FROM standart_uretim_asamalari ORDER BY sira');
            while ($row = $std->fetch()) {
                $ins->execute([$wid, (int) $row['id'], $beklemedeAsamaId]);
            }

            $stmt = $pdo->prepare('SELECT id FROM siparis_durumlari WHERE kod = ? LIMIT 1');
            $stmt->execute(['uretimde']);
            $uretimdeSid = (int) $stmt->fetchColumn();

            $stmt = $pdo->prepare('SELECT id FROM siparis_durumlari WHERE kod = ? LIMIT 1');
            $stmt->execute(['beklemede']);
            $beklemedeSid = (int) $stmt->fetchColumn();

            $pdo->prepare(
                'UPDATE siparisler s
                 INNER JOIN siparis_durumlari sd ON sd.id = s.siparis_durum_id AND sd.id = ?
                 SET s.siparis_durum_id = ?
                 WHERE s.id = ?'
            )->execute([$beklemedeSid, $uretimdeSid, $siparisId]);

            $pdo->commit();

            notify_message('Yeni iş emri oluşturuldu: ' . $code, $assign);
            flash('success', 'İş emri oluşturuldu: ' . $code);
        } catch (Throwable $e) {
            $pdo->rollBack();
            flash('danger', 'İş emri oluşturulamadı.');
        }
    } elseif ($action === 'update') {
        $id = (int) ($_POST['id'] ?? 0);
        $assign = (int) ($_POST['atanan_kullanici_id'] ?? 0) ?: null;
        try {
            $stmt = $pdo->prepare('SELECT id FROM oncelik_seviyeleri WHERE kod = ? LIMIT 1');
            $stmt->execute([(string) ($_POST['oncelik_kod'] ?? 'normal')]);
            $oncelikId = (int) $stmt->fetchColumn();

            $stmt = $pdo->prepare('SELECT id FROM is_emri_durumlari WHERE kod = ? LIMIT 1');
            $stmt->execute([(string) ($_POST['is_emri_durum_kod'] ?? 'acik')]);
            $durumId = (int) $stmt->fetchColumn();

            $notes = trim((string) ($_POST['notlar'] ?? '')) ?: null;
            $st = $pdo->prepare(
                'UPDATE is_emirleri SET atanan_kullanici_id=?, oncelik_id=?, is_emri_durum_id=?, notlar=? WHERE id=?'
            );
            $st->execute([$assign, $oncelikId, $durumId, $notes, $id]);
            flash('success', 'İş emri güncellendi.');
        } catch (Throwable $e) {
            flash('danger', 'Güncelleme başarısız.');
        }
    }
    redirect('is_emirleri.php');
}

$personel = $pdo->query(
    "SELECT k.id, k.ad_soyad FROM kullanicilar k
     INNER JOIN roller r ON r.id = k.rol_id
     WHERE r.kod IN ('personel','uretim_muduru')
     ORDER BY k.ad_soyad"
)->fetchAll();

$siparisler = $pdo->query(
    'SELECT s.id, sd.kod AS siparis_durum_kod, m.firma_adi,
            (SELECT COUNT(*) FROM is_emirleri ie WHERE ie.siparis_id = s.id) AS is_emri_var
     FROM siparisler s
     INNER JOIN musteriler m ON m.id = s.musteri_id
     INNER JOIN siparis_durumlari sd ON sd.id = s.siparis_durum_id
     ORDER BY s.id DESC'
)->fetchAll();

$staffOnly = '';
$params = [];
if ($u['role'] === 'personel') {
    $staffOnly = ' AND wo.atanan_kullanici_id = ? ';
    $params[] = $u['id'];
}

$sql =
    "SELECT wo.id, wo.siparis_id, wo.kod, wo.notlar,
            uo.kod AS oncelik_kod, idur.kod AS is_emri_durum_kod,
            s.siparis_tarihi, s.teslim_tarihi, sd.kod AS siparis_durum_kod,
            m.firma_adi, ku.ad_soyad AS atanan_ad
     FROM is_emirleri wo
     INNER JOIN siparisler s ON s.id = wo.siparis_id
     INNER JOIN musteriler m ON m.id = s.musteri_id
     INNER JOIN siparis_durumlari sd ON sd.id = s.siparis_durum_id
     INNER JOIN oncelik_seviyeleri uo ON uo.id = wo.oncelik_id
     INNER JOIN is_emri_durumlari idur ON idur.id = wo.is_emri_durum_id
     LEFT JOIN kullanicilar ku ON ku.id = wo.atanan_kullanici_id
     WHERE 1=1 $staffOnly
     ORDER BY wo.id DESC";
$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();

$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$editRow = null;
if ($editId > 0 && $canManage) {
    $st = $pdo->prepare(
        'SELECT wo.*, uo.kod AS oncelik_kod, idur.kod AS is_emri_durum_kod
         FROM is_emirleri wo
         INNER JOIN oncelik_seviyeleri uo ON uo.id = wo.oncelik_id
         INNER JOIN is_emri_durumlari idur ON idur.id = wo.is_emri_durum_id
         WHERE wo.id=?'
    );
    $st->execute([$editId]);
    $editRow = $st->fetch();
}

require __DIR__ . '/parcalar/ust.php';

$oncelikSecenekleri = [
    'dusuk' => 'Düşük',
    'normal' => 'Normal',
    'yuksek' => 'Yüksek',
];
$isEmriDurumSecenekleri = [
    'acik' => 'Açık',
    'devam_ediyor' => 'Devam ediyor',
    'bitti' => 'Tamamlandı',
    'iptal' => 'İptal',
];
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h1 class="h4 mb-0">İş emirleri</h1>
    <?php if ($canManage): ?>
        <button class="btn btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#woForm">
            <?= $editRow ? 'Düzenleme' : 'Yeni iş emri' ?>
        </button>
    <?php endif; ?>
</div>

<?php if ($canManage): ?>
<div class="collapse <?= $editRow ? 'show' : '' ?> mb-4" id="woForm">
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <?php if ($editRow): ?>
                <form method="post" class="row g-3">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" value="<?= (int) $editRow['id'] ?>">
                    <div class="col-md-4">
                        <label class="form-label">Atanan personel</label>
                        <select name="atanan_kullanici_id" class="form-select">
                            <option value="">— Atanmadı —</option>
                            <?php foreach ($personel as $usr): ?>
                                <option value="<?= (int) $usr['id'] ?>"
                                    <?= ((int) ($editRow['atanan_kullanici_id'] ?? 0) === (int) $usr['id']) ? 'selected' : '' ?>>
                                    <?= e($usr['ad_soyad']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Öncelik</label>
                        <select name="oncelik_kod" class="form-select">
                            <?php foreach ($oncelikSecenekleri as $k => $lab): ?>
                                <option value="<?= e($k) ?>" <?= ($editRow['oncelik_kod'] ?? '') === $k ? 'selected' : '' ?>><?= e($lab) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">İş emri durumu</label>
                        <select name="is_emri_durum_kod" class="form-select">
                            <?php foreach ($isEmriDurumSecenekleri as $k => $lab): ?>
                                <option value="<?= e($k) ?>" <?= ($editRow['is_emri_durum_kod'] ?? '') === $k ? 'selected' : '' ?>><?= e($lab) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Notlar</label>
                        <textarea name="notlar" class="form-control" rows="2"><?= e($editRow['notlar'] ?? '') ?></textarea>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-success">Kaydet</button>
                        <a class="btn btn-outline-secondary" href="is_emirleri.php">İptal</a>
                    </div>
                </form>
            <?php else: ?>
                <form method="post" class="row g-3">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="create">
                    <div class="col-md-6">
                        <label class="form-label">Sipariş</label>
                        <select name="siparis_id" class="form-select" required>
                            <?php foreach ($siparisler as $o): ?>
                                <?php if ($o['siparis_durum_kod'] === 'teslim_edildi') {
                                    continue;
                                } ?>
                                <option value="<?= (int) $o['id'] ?>">
                                    #<?= (int) $o['id'] ?> · <?= e($o['firma_adi']) ?>
                                    (<?= e(order_status_label($o['siparis_durum_kod'])) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Atanan personel</label>
                        <select name="atanan_kullanici_id" class="form-select">
                            <option value="">—</option>
                            <?php foreach ($personel as $usr): ?>
                                <option value="<?= (int) $usr['id'] ?>"><?= e($usr['ad_soyad']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Öncelik</label>
                        <select name="oncelik_kod" class="form-select">
                            <option value="normal" selected>Normal</option>
                            <option value="dusuk">Düşük</option>
                            <option value="yuksek">Yüksek</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Üretim notları</label>
                        <textarea name="notlar" class="form-control" rows="2" placeholder="Hat, malzeme vb."></textarea>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-success">İş emrini oluştur</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
            <tr>
                <th>Kod</th>
                <th>Müşteri</th>
                <th>Termin</th>
                <th>Öncelik</th>
                <th>Durum</th>
                <th>Atanan</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td class="fw-semibold"><?= e($r['kod']) ?></td>
                    <td><?= e($r['firma_adi']) ?></td>
                    <td><?= e($r['teslim_tarihi']) ?></td>
                    <td><span class="badge text-bg-info"><?= e(priority_label($r['oncelik_kod'])) ?></span></td>
                    <td><?= e(work_order_status_label($r['is_emri_durum_kod'])) ?></td>
                    <td><?= e($r['atanan_ad'] ?? '—') ?></td>
                    <td class="text-end text-nowrap">
                        <a class="btn btn-sm btn-outline-secondary" href="uretim_takip.php?wo=<?= (int) $r['id'] ?>">Adımlar</a>
                        <a class="btn btn-sm btn-outline-dark" target="_blank" href="is_emri_yazdir.php?id=<?= (int) $r['id'] ?>">QR</a>
                        <?php if ($canManage): ?>
                            <a class="btn btn-sm btn-outline-primary" href="is_emirleri.php?edit=<?= (int) $r['id'] ?>">Düzenle</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?>
                <tr><td colspan="7" class="text-muted text-center py-4">
                    <?= $u['role'] === 'personel' ? 'Size atanmış iş emri yok.' : 'Henüz iş emri yok.' ?>
                </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/parcalar/alt.php'; ?>
