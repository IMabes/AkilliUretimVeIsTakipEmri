<?php
// Çıkış yapınca session komple sıfırlanıyor
require_once __DIR__ . '/parcalar/baslangic.php';
$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $ayar = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $ayar['path'], $ayar['domain'], $ayar['secure'], $ayar['httponly']);
}

session_destroy();

redirect('index.php');
