-- Akıllı Üretim ve İş Emri Takip Sistemi
-- MySQL 8.x / MariaDB 10.5+
--
-- Tablo ve sütun adları Türkçedir (kod / id / FK adları tutarlı snake_case).
--
-- Normalizasyon (3NF özet):
--   • Her tablo tek bir varlığı temsil eder (müşteri, sipariş, iş emri, üretim adımı).
--   • Tekrarlayan sabit listeler (rol, sipariş durumu, öncelik, aşama durumu) ayrı
--     arama tablolarında tutulur; iş tablolarında yalnızca yabancı anahtar (FK) bulunur.
--   • Standart üretim aşamaları (Kesim, Montaj, …) tek yerde tanımlıdır; her iş emrindeki
--     ilerleme satırı standart_asama_id ile oraya bağlanır (aynı metnin kopyalanması önlenir).
--   • Sipariş–iş emri–üretim adımı zinciri FK ile bağlıdır; müşteri silinemezken bağlı
--     sipariş varken (RESTRICT) tutarlılık korunur.
--
-- Kurulum:
--   mysql -u root -p < database/schema.sql
--
-- Demo giriş (şifre: password):
--   admin@demo.local, mudur@demo.local, personel@demo.local

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP DATABASE IF EXISTS uretim_takip;
CREATE DATABASE uretim_takip
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE uretim_takip;

-- ---------------------------------------------------------------------------
-- Arama tabloları (normalizasyon: sabit değerler tek yerde, FK ile bağlanır)
-- ---------------------------------------------------------------------------

CREATE TABLE roller (
  id TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  kod VARCHAR(32) NOT NULL COMMENT 'Uygulama içi sabit anahtar',
  ad VARCHAR(80) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_roller_kod (kod)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO roller (kod, ad) VALUES
('yonetici', 'Yönetici'),
('uretim_muduru', 'Üretim Müdürü'),
('personel', 'Personel'),
('musteri', 'Müşteri Portalı');

CREATE TABLE siparis_durumlari (
  id TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  kod VARCHAR(32) NOT NULL,
  ad VARCHAR(80) NOT NULL,
  sira TINYINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_siparis_durum_kod (kod)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO siparis_durumlari (kod, ad, sira) VALUES
('beklemede', 'Bekliyor', 1),
('uretimde', 'Üretimde', 2),
('tamamlandi', 'Tamamlandı', 3),
('teslim_edildi', 'Teslim Edildi', 4);

CREATE TABLE is_emri_durumlari (
  id TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  kod VARCHAR(32) NOT NULL,
  ad VARCHAR(80) NOT NULL,
  sira TINYINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_is_emri_durum_kod (kod)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO is_emri_durumlari (kod, ad, sira) VALUES
('acik', 'Açık', 1),
('devam_ediyor', 'Devam ediyor', 2),
('bitti', 'Tamamlandı', 3),
('iptal', 'İptal', 4);

CREATE TABLE oncelik_seviyeleri (
  id TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  kod VARCHAR(32) NOT NULL,
  ad VARCHAR(80) NOT NULL,
  sira TINYINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_oncelik_kod (kod)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO oncelik_seviyeleri (kod, ad, sira) VALUES
('dusuk', 'Düşük', 1),
('normal', 'Normal', 2),
('yuksek', 'Yüksek', 3);

CREATE TABLE uretim_asama_durumlari (
  id TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  kod VARCHAR(32) NOT NULL,
  ad VARCHAR(80) NOT NULL,
  sira TINYINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ua_durum_kod (kod)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO uretim_asama_durumlari (kod, ad, sira) VALUES
('beklemede', 'Bekliyor', 1),
('uretimde', 'Üretimde', 2),
('tamamlandi', 'Tamamlandı', 3);

-- Standart hat sırası (aynı adın her iş emrinde tekrar yazılmasını önler — 3NF)
CREATE TABLE standart_uretim_asamalari (
  id TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  sira TINYINT UNSIGNED NOT NULL,
  ad VARCHAR(64) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_standart_sira (sira)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO standart_uretim_asamalari (sira, ad) VALUES
(1, 'Kesim'),
(2, 'Montaj'),
(3, 'Boya'),
(4, 'Paketleme');

-- ---------------------------------------------------------------------------
-- Ana varlıklar
-- ---------------------------------------------------------------------------

CREATE TABLE kullanicilar (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  ad_soyad VARCHAR(120) NOT NULL,
  e_posta VARCHAR(190) NOT NULL,
  sifre_hash VARCHAR(255) NOT NULL,
  rol_id TINYINT UNSIGNED NOT NULL,
  kayit_tarihi TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_kullanici_eposta (e_posta),
  KEY idx_kullanici_rol (rol_id),
  CONSTRAINT fk_kullanici_rol
    FOREIGN KEY (rol_id) REFERENCES roller (id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Şifre: password (bcrypt)
INSERT INTO kullanicilar (ad_soyad, e_posta, sifre_hash, rol_id) VALUES
('Sistem Yöneticisi', 'admin@demo.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1),
('Ayşe Yılmaz', 'mudur@demo.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 2),
('Mehmet Kaya', 'personel@demo.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3);

CREATE TABLE musteriler (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  firma_adi VARCHAR(200) NOT NULL,
  telefon VARCHAR(40) DEFAULT NULL,
  e_posta VARCHAR(190) DEFAULT NULL,
  kullanici_id INT UNSIGNED DEFAULT NULL COMMENT 'Portal müşteri hesabı',
  kayit_tarihi TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_musteri_kullanici (kullanici_id),
  KEY idx_musteri_firma (firma_adi),
  CONSTRAINT fk_musteri_kullanici
    FOREIGN KEY (kullanici_id) REFERENCES kullanicilar (id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO musteriler (firma_adi, telefon, e_posta) VALUES
('Anadolu Mobilya A.Ş.', '0312 555 0101', 'siparis@anadolumobilya.test'),
('İzmir Metal İşleri Ltd.', '0232 444 9090', 'uretim@izmirmetal.test');

CREATE TABLE siparisler (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  musteri_id INT UNSIGNED NOT NULL,
  siparis_tarihi DATE NOT NULL,
  teslim_tarihi DATE NOT NULL,
  siparis_durum_id TINYINT UNSIGNED NOT NULL,
  notlar TEXT,
  kayit_tarihi TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_siparis_musteri (musteri_id),
  KEY idx_siparis_teslim (teslim_tarihi),
  KEY idx_siparis_durum (siparis_durum_id),
  CONSTRAINT fk_siparis_musteri
    FOREIGN KEY (musteri_id) REFERENCES musteriler (id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_siparis_durum
    FOREIGN KEY (siparis_durum_id) REFERENCES siparis_durumlari (id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO siparisler (musteri_id, siparis_tarihi, teslim_tarihi, siparis_durum_id, notlar) VALUES
(1, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 14 DAY), 2, 'Özel ölçü dolap siparişi'),
(2, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 7 DAY), 1, 'Paslanmaz korkuluk seti');

CREATE TABLE is_emirleri (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  siparis_id INT UNSIGNED NOT NULL,
  kod VARCHAR(32) NOT NULL,
  atanan_kullanici_id INT UNSIGNED DEFAULT NULL,
  oncelik_id TINYINT UNSIGNED NOT NULL,
  is_emri_durum_id TINYINT UNSIGNED NOT NULL,
  notlar TEXT,
  kayit_tarihi TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_is_emri_kod (kod),
  KEY idx_is_emri_siparis (siparis_id),
  KEY idx_is_emri_atanan (atanan_kullanici_id),
  KEY idx_is_emri_oncelik (oncelik_id),
  KEY idx_is_emri_durum (is_emri_durum_id),
  CONSTRAINT fk_is_emri_siparis
    FOREIGN KEY (siparis_id) REFERENCES siparisler (id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_is_emri_kullanici
    FOREIGN KEY (atanan_kullanici_id) REFERENCES kullanicilar (id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_is_emri_oncelik
    FOREIGN KEY (oncelik_id) REFERENCES oncelik_seviyeleri (id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_is_emri_durum
    FOREIGN KEY (is_emri_durum_id) REFERENCES is_emri_durumlari (id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO is_emirleri (siparis_id, kod, atanan_kullanici_id, oncelik_id, is_emri_durum_id, notlar) VALUES
(1, 'WO-2026-00001', 3, 3, 2, 'Öncelikli hat');

CREATE TABLE uretim_asamalari (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  is_emri_id INT UNSIGNED NOT NULL,
  standart_asama_id TINYINT UNSIGNED NOT NULL,
  uretim_asama_durum_id TINYINT UNSIGNED NOT NULL,
  baslama_zamani DATETIME DEFAULT NULL,
  bitis_zamani DATETIME DEFAULT NULL,
  notlar VARCHAR(500) DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_emir_standart_asama (is_emri_id, standart_asama_id),
  KEY idx_uretim_asama_emir (is_emri_id),
  KEY idx_uretim_asama_durum (uretim_asama_durum_id),
  CONSTRAINT fk_ua_is_emri
    FOREIGN KEY (is_emri_id) REFERENCES is_emirleri (id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_ua_standart
    FOREIGN KEY (standart_asama_id) REFERENCES standart_uretim_asamalari (id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_ua_durum
    FOREIGN KEY (uretim_asama_durum_id) REFERENCES uretim_asama_durumlari (id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO uretim_asamalari (is_emri_id, standart_asama_id, uretim_asama_durum_id, baslama_zamani, bitis_zamani) VALUES
(1, 1, 3, DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY)),
(1, 2, 3, DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY)),
(1, 3, 2, NOW(), NULL),
(1, 4, 1, NULL, NULL);

CREATE TABLE bildirimler (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  kullanici_id INT UNSIGNED NOT NULL,
  mesaj VARCHAR(500) NOT NULL,
  okundu TINYINT(1) NOT NULL DEFAULT 0,
  olusturulma_tarihi TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_bildirim_kullanici (kullanici_id),
  CONSTRAINT fk_bildirim_kullanici
    FOREIGN KEY (kullanici_id) REFERENCES kullanicilar (id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO bildirimler (kullanici_id, mesaj, okundu) VALUES
(2, 'Yeni iş emri oluşturuldu: WO-2026-00001', 0),
(3, 'Size yeni görev atandı: WO-2026-00001', 0);

SET FOREIGN_KEY_CHECKS = 1;
