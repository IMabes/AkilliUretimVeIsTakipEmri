<?php
// Ana ekran — sayılar ve grafikler burada toplanıyor
require_once __DIR__ . '/parcalar/baslangic.php';
require_login();
redirect_if_customer();

$pageTitle = 'Panel';
$u = current_user();
$pdo = db();
$role = $u['role'];
$uid = $u['id'];

$staffWhere = '';
$staffParams = [];
if ($role === 'personel') {
    $staffWhere = ' AND wo.atanan_kullanici_id = ? ';
    $staffParams[] = $uid;
}

$st = $pdo->prepare(
    "SELECT COUNT(*) FROM is_emirleri wo
     INNER JOIN is_emri_durumlari d ON d.id = wo.is_emri_durum_id
     WHERE d.kod IN ('acik','devam_ediyor') $staffWhere"
);
$st->execute($staffParams);
$activeWo = (int) $st->fetchColumn();

$st = $pdo->prepare(
    "SELECT COUNT(*) FROM uretim_asamalari ua
     INNER JOIN uretim_asama_durumlari uad ON uad.id = ua.uretim_asama_durum_id
     INNER JOIN is_emirleri wo ON wo.id = ua.is_emri_id
     WHERE uad.kod = 'tamamlandi' AND DATE(ua.bitis_zamani) = CURDATE() $staffWhere"
);
$st->execute($staffParams);
$dailyDone = (int) $st->fetchColumn();

if ($role === 'personel') {
    $st = $pdo->prepare(
        "SELECT COUNT(DISTINCT s.id) FROM siparisler s
         INNER JOIN siparis_durumlari sd ON sd.id = s.siparis_durum_id
         INNER JOIN is_emirleri wo ON wo.siparis_id = s.id
         WHERE s.teslim_tarihi < CURDATE()
           AND sd.kod NOT IN ('teslim_edildi','tamamlandi')
           AND wo.atanan_kullanici_id = ?"
    );
    $st->execute([$uid]);
} else {
    $st = $pdo->query(
        "SELECT COUNT(*) FROM siparisler s
         INNER JOIN siparis_durumlari sd ON sd.id = s.siparis_durum_id
         WHERE s.teslim_tarihi < CURDATE()
           AND sd.kod NOT IN ('teslim_edildi','tamamlandi')"
    );
}
$delayedOrders = (int) $st->fetchColumn();

if ($role === 'personel') {
    $st = $pdo->prepare(
        "SELECT COUNT(*) FROM siparisler s
         INNER JOIN siparis_durumlari sd ON sd.id = s.siparis_durum_id
         INNER JOIN is_emirleri wo ON wo.siparis_id = s.id
         WHERE sd.kod IN ('teslim_edildi','tamamlandi')
           AND MONTH(s.siparis_tarihi) = MONTH(CURDATE())
           AND YEAR(s.siparis_tarihi) = YEAR(CURDATE())
           AND wo.atanan_kullanici_id = ?"
    );
    $st->execute([$uid]);
} else {
    $st = $pdo->query(
        "SELECT COUNT(*) FROM siparisler s
         INNER JOIN siparis_durumlari sd ON sd.id = s.siparis_durum_id
         WHERE sd.kod IN ('teslim_edildi','tamamlandi')
           AND MONTH(s.siparis_tarihi) = MONTH(CURDATE())
           AND YEAR(s.siparis_tarihi) = YEAR(CURDATE())"
    );
}
$completedMonth = (int) $st->fetchColumn();

$days = [];
for ($i = 6; $i >= 0; $i--) {
    $days[date('Y-m-d', strtotime("-$i days"))] = 0;
}
$sqlWeekly =
    "SELECT DATE(ua.bitis_zamani) AS d, COUNT(*) AS c
     FROM uretim_asamalari ua
     INNER JOIN uretim_asama_durumlari uad ON uad.id = ua.uretim_asama_durum_id
     INNER JOIN is_emirleri wo ON wo.id = ua.is_emri_id
     WHERE uad.kod = 'tamamlandi'
       AND ua.bitis_zamani IS NOT NULL
       AND ua.bitis_zamani >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
       $staffWhere
     GROUP BY DATE(ua.bitis_zamani)";
$st = $pdo->prepare($sqlWeekly);
$st->execute($staffParams);
while ($row = $st->fetch()) {
    if (isset($days[$row['d']])) {
        $days[$row['d']] = (int) $row['c'];
    }
}
$chartWeeklyLabels = array_keys($days);
$chartWeeklyData = array_values($days);

$perfLabels = [];
$perfData = [];
$sqlPerf =
    "SELECT k.ad_soyad AS n, COUNT(*) AS c
     FROM uretim_asamalari ua
     INNER JOIN uretim_asama_durumlari uad ON uad.id = ua.uretim_asama_durum_id
     INNER JOIN is_emirleri wo ON wo.id = ua.is_emri_id
     INNER JOIN kullanicilar k ON k.id = wo.atanan_kullanici_id
     WHERE uad.kod = 'tamamlandi'
       AND ua.bitis_zamani >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
     GROUP BY k.id, k.ad_soyad
     ORDER BY c DESC
     LIMIT 8";
$st = $pdo->query($sqlPerf);
while ($row = $st->fetch()) {
    $perfLabels[] = $row['n'];
    $perfData[] = (int) $row['c'];
}
if ($role === 'personel') {
    $perfLabels = [$u['name']];
    $st = $pdo->prepare(
        "SELECT COUNT(*) FROM uretim_asamalari ua
         INNER JOIN uretim_asama_durumlari uad ON uad.id = ua.uretim_asama_durum_id
         INNER JOIN is_emirleri wo ON wo.id = ua.is_emri_id
         WHERE uad.kod = 'tamamlandi'
           AND ua.bitis_zamani >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
           AND wo.atanan_kullanici_id = ?"
    );
    $st->execute([$uid]);
    $perfData = [(int) $st->fetchColumn()];
}

$statusLabels = [];
$statusData = [];
$st = $pdo->query(
    'SELECT sd.kod, COUNT(*) AS c FROM siparisler s
     INNER JOIN siparis_durumlari sd ON sd.id = s.siparis_durum_id
     GROUP BY sd.kod'
);
while ($row = $st->fetch()) {
    $statusLabels[] = order_status_label($row['kod']);
    $statusData[] = (int) $row['c'];
}

require __DIR__ . '/parcalar/ust.php';
?>

<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100 card-stat card-stat-primary">
            <div class="card-body">
                <div class="text-white-50 small">Aktif iş emri</div>
                <div class="display-6 fw-bold text-white"><?= $activeWo ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100 card-stat card-stat-success">
            <div class="card-body">
                <div class="text-white-50 small">Bugün tamamlanan adım</div>
                <div class="display-6 fw-bold text-white"><?= $dailyDone ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100 card-stat card-stat-warning">
            <div class="card-body">
                <div class="text-white-50 small">Geciken sipariş</div>
                <div class="display-6 fw-bold text-white"><?= $delayedOrders ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100 card-stat card-stat-dark">
            <div class="card-body">
                <div class="text-white-50 small">Bu ay tamamlanan sipariş</div>
                <div class="display-6 fw-bold text-white"><?= $completedMonth ?></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white fw-semibold">Haftalık üretim (tamamlanan adım)</div>
            <div class="card-body">
                <canvas id="chartWeekly" height="120"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white fw-semibold">Sipariş durumu dağılımı</div>
            <div class="card-body">
                <canvas id="chartOrders" height="200"></canvas>
            </div>
        </div>
    </div>
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white fw-semibold">Personel performansı (son 30 gün · tamamlanan üretim adımı)</div>
            <div class="card-body">
                <canvas id="chartPerf" height="80"></canvas>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {
    const weeklyLabels = <?= json_encode($chartWeeklyLabels, JSON_UNESCAPED_UNICODE) ?>;
    const weeklyData = <?= json_encode($chartWeeklyData) ?>;
    const orderLabels = <?= json_encode($statusLabels, JSON_UNESCAPED_UNICODE) ?>;
    const orderData = <?= json_encode($statusData) ?>;
    const perfLabels = <?= json_encode($perfLabels, JSON_UNESCAPED_UNICODE) ?>;
    const perfData = <?= json_encode($perfData) ?>;

    const grid = getComputedStyle(document.documentElement).getPropertyValue('--chart-grid') || '#dee2e6';

    new Chart(document.getElementById('chartWeekly'), {
        type: 'line',
        data: {
            labels: weeklyLabels,
            datasets: [{
                label: 'Adım',
                data: weeklyData,
                borderColor: '#0d6efd',
                backgroundColor: 'rgba(13, 110, 253, 0.15)',
                fill: true,
                tension: 0.35
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                x: { ticks: { font: { size: 11 } }, grid: { color: grid } },
                y: { beginAtZero: true, ticks: { stepSize: 1 }, grid: { color: grid } }
            }
        }
    });

    new Chart(document.getElementById('chartOrders'), {
        type: 'doughnut',
        data: {
            labels: orderLabels,
            datasets: [{
                data: orderData,
                backgroundColor: ['#6c757d', '#0dcaf0', '#198754', '#0d6efd']
            }]
        },
        options: {
            plugins: { legend: { position: 'bottom', labels: { font: { size: 11 } } } }
        }
    });

    new Chart(document.getElementById('chartPerf'), {
        type: 'bar',
        data: {
            labels: perfLabels,
            datasets: [{
                label: 'Tamamlanan adım',
                data: perfData,
                backgroundColor: '#6610f2'
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                x: { ticks: { font: { size: 11 } }, grid: { display: false } },
                y: { beginAtZero: true, ticks: { stepSize: 1 }, grid: { color: grid } }
            }
        }
    });
})();
</script>

<?php require __DIR__ . '/parcalar/alt.php'; ?>
