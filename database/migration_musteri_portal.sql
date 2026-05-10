-- Mevcut veritabanına müşteri portalı desteği ekler.
-- Tek seferlik çalıştırın. "Duplicate column" hatası alırsanız sütun zaten eklenmiştir.
--
-- mysql -u root -p uretim_takip < database/migration_musteri_portal.sql

USE uretim_takip;

INSERT INTO roller (kod, ad)
SELECT 'musteri', 'Müşteri Portalı'
WHERE NOT EXISTS (SELECT 1 FROM roller WHERE kod = 'musteri');

ALTER TABLE musteriler
  ADD COLUMN kullanici_id INT UNSIGNED DEFAULT NULL COMMENT 'Portal müşteri hesabı' AFTER e_posta;

ALTER TABLE musteriler
  ADD UNIQUE KEY uq_musteri_kullanici (kullanici_id);

ALTER TABLE musteriler
  ADD CONSTRAINT fk_musteri_kullanici FOREIGN KEY (kullanici_id) REFERENCES kullanicilar (id)
    ON DELETE SET NULL ON UPDATE CASCADE;
