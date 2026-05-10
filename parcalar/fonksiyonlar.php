<?php
// PDO ile veritabanı bağlantısı (aynı bağlantıyı tekrar açmamak için static kullandım)
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $ayar = require __DIR__ . '/../ayarlar/veritabani_baglanti.php';
        $dsn = 'mysql:host=' . $ayar['host'] . ';port=' . $ayar['port'] . ';dbname=' . $ayar['database'] . ';charset=utf8mb4';
        $pdo = new PDO($dsn, $ayar['username'], $ayar['password']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }
    return $pdo;
}

function e(?string $s): string
{
    return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_verify(?string $token): bool
{
    return isset($_SESSION['csrf']) && is_string($token) && hash_equals($_SESSION['csrf'], $token);
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function flash_pull(): ?array
{
    if (empty($_SESSION['flash'])) {
        return null;
    }
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $f;
}

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    return [
        'id' => (int) $_SESSION['user_id'],
        'name' => (string) ($_SESSION['user_name'] ?? ''),
        'email' => (string) ($_SESSION['user_email'] ?? ''),
        'role' => (string) ($_SESSION['user_role'] ?? ''),
    ];
}

function require_login(): void
{
    if (!current_user()) {
        redirect('giris.php');
    }
}

// Müşteri kullanıcısı personel sayfalarına girmesin diye
function redirect_if_customer(string $to = 'musteri_portal.php'): void
{
    $u = current_user();
    if ($u !== null && $u['role'] === 'musteri') {
        redirect($to);
    }
}

function login_user_from_row(array $row): void
{
    // giriş yapınca session id yenileniyormuş, ödevde okumuştum
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }

    $_SESSION['user_id'] = (int) $row['id'];
    $_SESSION['user_name'] = (string) $row['ad_soyad'];
    $_SESSION['user_email'] = (string) $row['e_posta'];
    $_SESSION['user_role'] = (string) $row['rol_kod'];
    unset($_SESSION['musteri_id']);

    if ($_SESSION['user_role'] === 'musteri') {
        $pdo = db();
        $stmt = $pdo->prepare('SELECT id FROM musteriler WHERE kullanici_id = ? LIMIT 1');
        $stmt->execute([(int) $row['id']]);
        $mid = (int) $stmt->fetchColumn();
        if ($mid > 0) {
            $_SESSION['musteri_id'] = $mid;
        }
    }
}

function ensure_musteri_id_for_session(): ?int
{
    $u = current_user();
    if ($u === null || $u['role'] !== 'musteri') {
        return null;
    }
    if (!empty($_SESSION['musteri_id'])) {
        return (int) $_SESSION['musteri_id'];
    }
    $pdo = db();
    $stmt = $pdo->prepare('SELECT id FROM musteriler WHERE kullanici_id = ? LIMIT 1');
    $stmt->execute([$u['id']]);
    $mid = (int) $stmt->fetchColumn();
    if ($mid > 0) {
        $_SESSION['musteri_id'] = $mid;
        return $mid;
    }
    return null;
}

function require_roles(array $roles): void
{
    require_login();
    $u = current_user();
    if ($u === null || !in_array($u['role'], $roles, true)) {
        http_response_code(403);
        echo 'Bu sayfaya erişim yetkiniz yok.';
        exit;
    }
}

function role_label(string $role): string
{
    return match ($role) {
        'yonetici' => 'Yönetici',
        'uretim_muduru' => 'Üretim Müdürü',
        'personel' => 'Personel',
        'musteri' => 'Müşteri',
        default => $role,
    };
}

function order_status_label(string $s): string
{
    return match ($s) {
        'beklemede' => 'Bekliyor',
        'uretimde' => 'Üretimde',
        'tamamlandi' => 'Tamamlandı',
        'teslim_edildi' => 'Teslim Edildi',
        default => $s,
    };
}

function work_order_status_label(string $s): string
{
    return match ($s) {
        'acik' => 'Açık',
        'devam_ediyor' => 'Devam ediyor',
        'bitti' => 'Tamamlandı',
        'iptal' => 'İptal',
        default => $s,
    };
}

function priority_label(string $p): string
{
    return match ($p) {
        'dusuk' => 'Düşük',
        'normal' => 'Normal',
        'yuksek' => 'Yüksek',
        default => $p,
    };
}

function step_status_label(string $s): string
{
    return match ($s) {
        'beklemede' => 'Bekliyor',
        'uretimde' => 'Üretimde',
        'tamamlandi' => 'Tamamlandı',
        default => $s,
    };
}

function app_base_url(): string
{
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $dir = str_replace('\\', '/', dirname($script));
    if ($dir === '/' || $dir === '\\') {
        $dir = '';
    }
    return rtrim($scheme . '://' . $host . $dir, '/');
}

function notify_message(string $message, ?int $alsoUserId = null): void
{
    $pdo = db();
    $stmt = $pdo->prepare(
        'INSERT INTO bildirimler (kullanici_id, mesaj)
         SELECT k.id, ? FROM kullanicilar k
         INNER JOIN roller r ON r.id = k.rol_id
         WHERE r.kod IN (\'yonetici\',\'uretim_muduru\')'
    );
    $stmt->execute([$message]);
    if ($alsoUserId !== null && $alsoUserId > 0) {
        $stmt = $pdo->prepare('INSERT INTO bildirimler (kullanici_id, mesaj) VALUES (?, ?)');
        $stmt->execute([$alsoUserId, $message]);
    }
}
