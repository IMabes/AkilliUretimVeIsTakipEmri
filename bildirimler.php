<?php
// Bildirim linkine tıklayınca okundu işaretliyorum
require_once __DIR__ . '/parcalar/baslangic.php';
require_login();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id > 0) {
    $st = db()->prepare('UPDATE bildirimler SET okundu = 1 WHERE id = ? AND kullanici_id = ?');
    $st->execute([$id, current_user()['id']]);
}
$u = current_user();
redirect(($u && $u['role'] === 'musteri') ? 'musteri_portal.php' : 'dashboard.php');
