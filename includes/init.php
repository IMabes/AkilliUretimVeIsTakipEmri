<?php

/*
 * İnternet Programcılığı — Oturum (ör. session_start ile oturum başlatma)
 * Her sayfa önce bu dosyayı çağırmalı: require_once 'includes/init.php';
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/functions.php';
