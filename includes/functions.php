<?php

/** Veritabanı bağlantısı — PDO ile basit bağlantı */
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $ayar = require __DIR__ . '/../config/database.php';
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

/** @return array{type: string, message: string}|null */
function flash_pull(): ?array
{
    if (empty($_SESSION['flash'])) {
        return null;
    }
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $f;
}

/** @return array{id: int, name: string, email: string, role: string}|null — role = roller.kod */
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
        redirect('login.php');
    }
}

/** Müşteri rolündeki kullanıcıları personel panelinden uzak tutar. */
function redirect_if_customer(string $to = 'portal_siparisler.php'): void
{
    $u = current_user();
    if ($u !== null && $u['role'] === 'musteri') {
        redirect($to);
    }
}

/**
 * Oturum bilgisini veritabanı satırından yükler (rol_kod anahtarı gerekli).
 *
 * @param array{id: int, ad_soyad: string, e_posta: string, rol_kod: string} $row
 */
function login_user_from_row(array $row): void
{
    // Güvenlik için oturum kimliğini yenileme (ders konusunun hemen üstü)
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

/** @return positive-int|null */
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

/** @param list<string> $roles roller.kod değerleri */
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

/** @param string $role roller.kod */
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

/** @param string $s siparis_durumlari.kod */
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

/** @param string $s is_emri_durumlari.kod */
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

/** @param string $p oncelik_seviyeleri.kod */
function priority_label(string $p): string
{
    return match ($p) {
        'dusuk' => 'Düşük',
        'normal' => 'Normal',
        'yuksek' => 'Yüksek',
        default => $p,
    };
}

/** @param string $s uretim_asama_durumlari.kod */
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
