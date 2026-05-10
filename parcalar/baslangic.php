<?php
// Burada oturum açılıyor — Internet Programcılığında gördüğümüz session_start mantığı
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/fonksiyonlar.php';
