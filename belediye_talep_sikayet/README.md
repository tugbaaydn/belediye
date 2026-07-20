# Belediye Talep ve Akıllı Şikâyet Yönetim Sistemi

PHP, MySQL ve Bootstrap ile geliştirilmiş web tabanlı belediye başvuru yönetim uygulamasıdır.

## Özellikler

- Vatandaşların talep, öneri ve şikâyet oluşturabilmesi
- Otomatik takip numarası üretimi
- Telefon + takip numarasıyla başvuru sorgulama
- Anahtar kelime tabanlı akıllı müdürlük önerisi
- Akıllı öncelik belirleme: Düşük, Normal, Yüksek, Acil
- Önceliğe göre hedef çözüm tarihi
- Admin tarafından müdürlüğe yönlendirme ve personele atama
- Personelin işlem notu eklemesi ve durum güncellemesi
- Vatandaşa açık notlar ve kurum içi notlar
- Başvuru süreç zaman çizelgesi
- Müdürlük ve kullanıcı yönetimi
- Responsive yönetim paneli
- Fotoğraf/PDF ekleme desteği

## Kurulum

1. ZIP dosyasını `C:\laragon\www\` klasörüne çıkarın.
2. Klasör adını `belediye_talep_sikayet` olarak bırakın.
3. Laragon içinden Apache ve MySQL servislerini başlatın.
4. HeidiSQL veya phpMyAdmin açın.
5. `database.sql` dosyasını çalıştırın.
6. Tarayıcıda aşağıdaki adresi açın:

```text
http://localhost/belediye_talep_sikayet/
```

## Veritabanı Ayarları

Varsayılan Laragon ayarları:

```php
$host = 'localhost';
$db   = 'belediye_sikayet';
$user = 'root';
$pass = '';
```

Farklı ayar kullanıyorsanız `config/db.php` dosyasını düzenleyin.

## Demo Giriş Bilgileri

### Yönetici
- E-posta: `admin@belediye.local`
- Şifre: `Admin123!`

### Personel
- E-posta: `personel@belediye.local`
- Şifre: `Personel123!`

## Örnek Başvuru Takibi

- Takip numarası: `BLD-2026-DEMO001`
- Telefon: `05550000000`

## Önemli Notlar

- Yüklenen belgeler `uploads/` klasörüne kaydedilir. Klasörün yazma izni olmalıdır.
- Canlı ortamda demo kullanıcılarının şifrelerini değiştirin.
- E-posta/SMS gönderimi bu sürümde gerçek servis bağlantısı içermez; vatandaş bilgilendirmesi takip ekranı üzerinden yapılır.
