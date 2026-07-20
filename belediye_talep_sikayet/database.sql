CREATE DATABASE IF NOT EXISTS belediye_sikayet CHARACTER SET utf8mb4 COLLATE utf8mb4_turkish_ci;
USE belediye_sikayet;

SET FOREIGN_KEY_CHECKS=0;
DROP TABLE IF EXISTS complaint_history;
DROP TABLE IF EXISTS complaints;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS departments;
SET FOREIGN_KEY_CHECKS=1;

CREATE TABLE departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','staff') NOT NULL DEFAULT 'staff',
    department_id INT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE complaints (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tracking_code VARCHAR(30) NOT NULL UNIQUE,
    citizen_name VARCHAR(120) NOT NULL,
    phone VARCHAR(30) NOT NULL,
    email VARCHAR(150) NULL,
    type ENUM('Talep','Öneri','Şikâyet') NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    address VARCHAR(255) NULL,
    district VARCHAR(120) NULL,
    attachment VARCHAR(255) NULL,
    department_id INT NULL,
    suggested_department_id INT NULL,
    assigned_user_id INT NULL,
    priority ENUM('Düşük','Normal','Yüksek','Acil') NOT NULL DEFAULT 'Normal',
    status ENUM('Yeni','İnceleniyor','Yönlendirildi','İşlemde','Çözüldü','Reddedildi') NOT NULL DEFAULT 'Yeni',
    due_date DATE NULL,
    resolution_note TEXT NULL,
    resolved_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_priority (priority),
    INDEX idx_department (department_id),
    INDEX idx_assigned (assigned_user_id),
    CONSTRAINT fk_complaint_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    CONSTRAINT fk_complaint_suggested FOREIGN KEY (suggested_department_id) REFERENCES departments(id) ON DELETE SET NULL,
    CONSTRAINT fk_complaint_user FOREIGN KEY (assigned_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE complaint_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    complaint_id INT NOT NULL,
    user_id INT NULL,
    status VARCHAR(50) NOT NULL,
    note TEXT NOT NULL,
    is_public TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_history_complaint FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE,
    CONSTRAINT fk_history_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT INTO departments (name, description) VALUES
('Fen İşleri Müdürlüğü','Yol, kaldırım, asfalt ve altyapı çalışmaları'),
('Temizlik İşleri Müdürlüğü','Çöp, atık ve çevre temizliği hizmetleri'),
('Su ve Kanalizasyon Müdürlüğü','Su arızaları, kanalizasyon ve rögar hizmetleri'),
('Park ve Bahçeler Müdürlüğü','Parklar, yeşil alanlar ve ağaçlandırma'),
('Zabıta Müdürlüğü','Denetim, işgal, ruhsat ve kamu düzeni'),
('Ulaşım Hizmetleri Müdürlüğü','Toplu taşıma, durak ve trafik düzenlemeleri'),
('Veteriner İşleri Müdürlüğü','Sokak hayvanları ve veteriner hizmetleri'),
('Sosyal Yardım İşleri Müdürlüğü','Sosyal destek ve yardım hizmetleri');

INSERT INTO users (full_name, email, password, role, department_id) VALUES
('Sistem Yöneticisi','admin@belediye.local','$2y$12$J1ZJszt9qGUgHb0iOZpWueHXfUdwKJx7dcF3cYoGZbP7ptKtyRoq2','admin',NULL),
('Ahmet Yılmaz','personel@belediye.local','$2y$12$wO9P8iT6qK/PLGIfWK1tl.f4rjlfZVAnjwN9du4tyZd0PhVeqanSC','staff',1),
('Ayşe Demir','temizlik@belediye.local','$2y$12$wO9P8iT6qK/PLGIfWK1tl.f4rjlfZVAnjwN9du4tyZd0PhVeqanSC','staff',2),
('Mehmet Kaya','su@belediye.local','$2y$12$wO9P8iT6qK/PLGIfWK1tl.f4rjlfZVAnjwN9du4tyZd0PhVeqanSC','staff',3);

INSERT INTO complaints
(tracking_code, citizen_name, phone, email, type, title, description, address, district, department_id, suggested_department_id, assigned_user_id, priority, status, due_date)
VALUES
('BLD-2026-DEMO001','Örnek Vatandaş','05550000000','ornek@example.com','Şikâyet','Mahalle yolunda büyük çukur var','Ana caddede araçlara zarar verebilecek büyüklükte bir yol çukuru oluştu. Kontrol edilmesini rica ediyorum.','Atatürk Caddesi No: 12','Merkez',1,1,2,'Yüksek','İşlemde',DATE_ADD(CURDATE(), INTERVAL 3 DAY));

INSERT INTO complaint_history (complaint_id, user_id, status, note, is_public) VALUES
(1,NULL,'Yeni','Başvuru vatandaş tarafından oluşturuldu. Akıllı sistem önerisi: Fen İşleri Müdürlüğü.',1),
(1,1,'Yönlendirildi','Başvuru Fen İşleri Müdürlüğüne yönlendirildi ve personele atandı.',1),
(1,2,'İşlemde','Ekipler konum incelemesi için yönlendirildi.',1);
