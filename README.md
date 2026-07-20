# Belediye Talep ve Akıllı Şikâyet Yönetim Sistemi

Belediye Talep ve Akıllı Şikâyet Yönetim Sistemi; vatandaşların belediye hizmetleriyle ilgili talep, öneri ve şikâyetlerini çevrim içi olarak iletebildiği, belediye yöneticilerinin başvuruları ilgili müdürlüklere yönlendirebildiği ve belediye personelinin çözüm sürecini takip edebildiği web tabanlı bir yönetim sistemidir.

Sistem yalnızca kayıt ekleme ve listeleme işlemlerinden oluşmaz. Başvurunun oluşturulması, ilgili birime aktarılması, personel atanması, görev açıklamasının yazılması, son işlem tarihinin belirlenmesi, işlem yapılması ve başvurunun sonuçlandırılması gibi bütün aşamalar kayıt altına alınır.

## Projenin Amacı

Bu projenin amacı vatandaş ile belediye arasındaki iletişimi daha hızlı, düzenli, şeffaf ve takip edilebilir hâle getirmektir.

Sistem sayesinde vatandaş başvuruları kayıt altına alınır, ilgili birimlere yönlendirilir, sorumlu personel atanır ve çözüm süreci takip edilir.

## Kullanılan Teknolojiler

- PHP
- MySQL / MariaDB
- HTML5
- CSS3
- JavaScript
- Bootstrap 5
- Font Awesome
- PDO
- Laragon
- HeidiSQL

## Kullanıcı Rolleri

### Vatandaş

Vatandaşlar sisteme giriş yapmadan başvuru oluşturabilir.

Vatandaşın yapabileceği işlemler:

- Talep, öneri veya şikâyet oluşturma
- İlgili müdürlük veya birimi seçme
- Mahalle ve açık adres bilgisi girme
- Cihaz konumu ekleme
- Başvuru fotoğrafı yükleme
- Başvuru takip numarası alma
- Telefon ve takip numarasıyla başvuru sorgulama
- Başvurunun güncel durumunu görüntüleme

Vatandaş, belediye personelinin kurum içi işlem açıklamalarını ve işlem fotoğraflarını göremez.

### Sistem Yöneticisi

Yönetici bütün başvuruları ve sistem kullanıcılarını yönetebilir.

Yönetici işlemleri:

- Dashboard ekranını görüntüleme
- Bütün başvuruları listeleme
- Başvuru detaylarını görüntüleme
- Vatandaş tarafından yüklenen fotoğrafı görüntüleme
- Başvuru konumunu harita üzerinde açma
- Başvuruyu ilgili müdürlüğe yönlendirme
- Seçilen müdürlük içerisinden personel atama
- Görev açıklaması yazma
- Öncelik seviyesi belirleme
- Son işlem tarihi belirleme
- Personele sistem bildirimi gönderme
- Atamayı güncelleme
- Müdürlük yönetimi
- Kullanıcı yönetimi
- Süresi geçen başvuruları takip etme

### Belediye Personeli

Personel yalnızca kendisine atanan başvuruları görüntüleyebilir.

Personelin yapabileceği işlemler:

- Kendisine atanan görevleri listeleme
- Görev açıklamasını görüntüleme
- Son işlem tarihini görüntüleme
- Vatandaşın yüklediği fotoğrafı görüntüleme
- Konumu Google Maps üzerinde açma
- Başvurunun durumunu güncelleme
- İşlem açıklaması ekleme
- Yapılan işleme ait fotoğraf yükleme
- İşlem geçmişini görüntüleme
- Sağ üst bildirim alanından yeni görevleri açma

Vatandaşın telefon numarası personel ekranında gösterilmez.

## Başvuru Durumları

Sistemde kullanılan başvuru durumları:

- Yeni
- İnceleniyor
- Yönlendirildi
- İşlemde
- Çözüldü
- Reddedildi

## Öncelik Seviyeleri

- Düşük
- Normal
- Yüksek
- Acil

Öncelik seviyesi yönetici tarafından personel atama sırasında belirlenebilir.

## Akıllı Yönlendirme Sistemi

Başvuru oluşturulurken konu ve açıklama alanları anahtar kelime tabanlı olarak analiz edilir.

Sistem başvurunun aşağıdaki müdürlüklerden hangisiyle ilgili olabileceğini tahmin eder:

- Fen İşleri Müdürlüğü
- Temizlik İşleri Müdürlüğü
- Su ve Kanalizasyon Müdürlüğü
- Park ve Bahçeler Müdürlüğü
- Zabıta Müdürlüğü
- Ulaşım Hizmetleri Müdürlüğü
- Veteriner İşleri Müdürlüğü
- Sosyal Yardım İşleri Müdürlüğü

Vatandaş ilgili birimi kendisi seçebilir. Yönetici gerekli görürse başvuruyu farklı bir müdürlüğe yönlendirebilir.

## Personel Atama Modülü

Personel atama modülünde yönetici aşağıdaki bilgileri belirler:

- İlgili müdürlük
- Görevlendirilecek personel
- Görev açıklaması
- Öncelik seviyesi
- Son işlem tarihi

Müdürlük seçildiğinde yalnızca seçilen müdürlüğe bağlı personeller listelenir.

Atama tamamlandığında:

1. Başvuru ilgili müdürlüğe aktarılır.
2. Seçilen personele görev atanır.
3. Başvurunun durumu `Yönlendirildi` olarak güncellenir.
4. Atama bilgisi işlem geçmişine kaydedilir.
5. Personele sistem içi bildirim gönderilir.

## Bildirim Sistemi

Yönetici ve personel panelinin sağ üst köşesinde bildirim alanı bulunur.

Yönetici bildirimleri:

- Yeni oluşturulan başvurular
- Son işlem tarihi geçen başvurular
- Güncel başvuru hareketleri

Personel bildirimleri:

- Yeni atanan görevler
- İşlemde olan başvurular
- Son işlem tarihi geçen görevler

Personel bildirime tıkladığında bildirim okundu olarak işaretlenir ve ilgili başvurunun detay sayfası açılır.

## Fotoğraf ve Konum Sistemi

Vatandaş başvuru oluştururken:

- İlgili birimi seçmek zorundadır.
- Mahalle bilgisini girmek zorundadır.
- Açık adres bilgisini girmek zorundadır.
- Cihaz konumunu eklemek zorundadır.
- Başvuru fotoğrafı yüklemek zorundadır.

Desteklenen fotoğraf biçimleri:

- JPG
- PNG
- WEBP

En yüksek fotoğraf boyutu:

```text
5 MB
```

Personel yaptığı işleme ait fotoğraf yükleyebilir. Personelin işlem açıklaması ve fotoğrafı kurum içi olarak saklanır ve vatandaş takip ekranında gösterilmez.

## Proje Klasör Yapısı

```text
belediye_talep_sikayet/
│
├── admin/
│   ├── dashboard.php
│   ├── basvurular.php
│   ├── basvuru_detay.php
│   ├── personel_atama.php
│   ├── atamalar.php
│   ├── departmanlar.php
│   └── kullanicilar.php
│
├── personel/
│   ├── dashboard.php
│   ├── basvurularim.php
│   ├── basvuru_detay.php
│   └── bildirim_ac.php
│
├── config/
│   └── db.php
│
├── includes/
│   ├── auth.php
│   ├── functions.php
│   ├── header.php
│   ├── footer.php
│   ├── public_nav.php
│   ├── panel_header.php
│   └── panel_footer.php
│
├── assets/
│   ├── css/
│   │   └── style.css
│   └── js/
│       └── app.js
│
├── uploads/
│   └── operations/
│
├── index.php
├── talep-olustur.php
├── talep-takip.php
├── basvuru-basarili.php
├── login.php
├── logout.php
├── database.sql
└── README.md
```

## Veritabanı Tabloları

### departments

Belediye müdürlüklerini saklar.

### users

Yönetici ve belediye personeli hesaplarını saklar.

### complaints

Vatandaş başvurularını, konum bilgilerini, fotoğrafı ve personel atama bilgilerini saklar.

### complaint_history

Başvuru üzerinde gerçekleştirilen bütün işlemleri ve işlem fotoğraflarını saklar.

### notifications

Kullanıcılara gönderilen sistem içi bildirimleri saklar.

## Kurulum

### 1. Projeyi Laragon klasörüne taşıma

Proje klasörünü aşağıdaki dizine yerleştirin:

```text
C:\laragon\www\belediye_talep_sikayet
```

### 2. Laragon servislerini başlatma

Laragon uygulamasını açın ve şu servisleri başlatın:

- Apache
- MySQL

### 3. Veritabanını oluşturma

HeidiSQL veya phpMyAdmin üzerinden proje içerisindeki `database.sql` dosyasını çalıştırın.

Veritabanı adı:

```text
belediye_sikayet
```

### 4. Veritabanı bağlantısını kontrol etme

`config/db.php` dosyası:

```php
<?php

$host = 'localhost';
$db = 'belediye_sikayet';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false
];

$pdo = new PDO($dsn, $user, $pass, $options);
```

### 5. Fotoğraf klasörlerini kontrol etme

Şu klasörlerin bulunduğundan emin olun:

```text
uploads/
uploads/operations/
```

### 6. Projeyi tarayıcıda açma

```text
http://localhost/belediye_talep_sikayet/
```

Personel ve yönetici giriş ekranı:

```text
http://localhost/belediye_talep_sikayet/login.php
```

## Demo Giriş Bilgileri

### Yönetici

```text
E-posta: admin@belediye.local
Şifre: Admin123!
```

### Personel

```text
E-posta: personel@belediye.local
Şifre: Personel123!
```

### Test Personelleri

```text
test.personel.1@belediye.local
test.personel.2@belediye.local
test.personel.3@belediye.local
```

Test personeli şifresi:

```text
Personel123!
```

## Örnek Başvuru Takibi

```text
Takip numarası: BLD-2026-DEMO001
Telefon: 05550000000
```

## Güvenlik Özellikleri

- PDO ve hazırlanmış SQL sorguları
- Şifrelerin `password_hash()` ile saklanması
- `password_verify()` ile giriş kontrolü
- Rol tabanlı yetkilendirme
- CSRF token kontrolü
- Dosya türü ve boyutu kontrolü
- Benzersiz fotoğraf adı oluşturma
- Personelin yalnızca kendisine atanan görevleri açabilmesi
- Vatandaş telefon numarasının personel ekranında gizlenmesi
- Kurum içi işlem notlarının vatandaş ekranından gizlenmesi

## GitHub’a Yükleme

Proje klasöründe Git Bash açın:

```bash
git init
git add .
git commit -m "Belediye talep ve şikayet sistemi eklendi"
git branch -M main
git remote add origin GITHUB_REPO_ADRESI
git push -u origin main
```

Daha önce uzak bağlantı eklendiyse:

```bash
git remote set-url origin GITHUB_REPO_ADRESI
git push -u origin main
```

## Geliştirilebilecek Özellikler

- SMS bildirim sistemi
- E-posta bildirimi
- Başvuruların harita üzerinde toplu gösterimi
- Müdürlük yöneticisi rolü
- PDF raporu oluşturma
- Excel raporu dışa aktarma
- Grafik tabanlı yönetim raporları
- Ortalama çözüm süresi hesaplama
- Mobil uygulama
- Vatandaş üyelik sistemi
- Yapay zekâ destekli başvuru sınıflandırması

## Proje Notu

Bu proje eğitim ve geliştirme amacıyla hazırlanmıştır. Gerçek bir belediye sisteminde kullanılmadan önce güvenlik testleri yapılmalı, KVKK süreçleri değerlendirilmelidir.

## Geliştirici

**Tuğba Aydın**

Yönetim Bilişim Sistemleri
