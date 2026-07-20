<?php
$pageTitle = 'Akıllı Belediye Talep ve Şikâyet Yönetimi';
$basePath = '';
require 'includes/header.php';
require 'includes/public_nav.php';
?>
<section class="hero-section">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-7">
                <span class="eyebrow"><i class="fa-solid fa-bolt"></i> Hızlı, şeffaf ve takip edilebilir</span>
                <h1>Şehriniz için talebinizi iletin, çözüm sürecini anlık takip edin.</h1>
                <p>Talep, öneri ve şikâyetlerinizi belediyeye çevrim içi gönderin. Akıllı yönlendirme sistemi başvurunuzu uygun müdürlüğe önerir, belediye personeli süreci uçtan uca yönetir.</p>
                <div class="d-flex flex-wrap gap-3">
                    <a href="talep-olustur.php" class="btn btn-light btn-lg px-4"><i class="fa-solid fa-plus me-2"></i>Başvuru Oluştur</a>
                    <a href="talep-takip.php" class="btn btn-outline-light btn-lg px-4"><i class="fa-solid fa-magnifying-glass me-2"></i>Başvurumu Takip Et</a>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="hero-card">
                    <div class="hero-card-icon"><i class="fa-solid fa-route"></i></div>
                    <h3>Başvurunuzun yolculuğu</h3>
                    <div class="mini-step"><span>1</span><div><strong>Kayıt oluşturulur</strong><small>Takip numarası anında üretilir.</small></div></div>
                    <div class="mini-step"><span>2</span><div><strong>Akıllı analiz yapılır</strong><small>Konuya göre müdürlük ve öncelik önerilir.</small></div></div>
                    <div class="mini-step"><span>3</span><div><strong>Personel görevlendirilir</strong><small>İşlem adımları kayıt altına alınır.</small></div></div>
                    <div class="mini-step"><span>4</span><div><strong>Vatandaş bilgilendirilir</strong><small>Sonuç ve açıklamalar takip ekranında görünür.</small></div></div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="feature-section">
    <div class="container">
        <div class="section-heading text-center">
            <span>Nasıl çalışır?</span>
            <h2>Tek ekranda bütün süreç</h2>
        </div>
        <div class="row g-4">
            <div class="col-md-4">
                <div class="feature-card">
                    <i class="fa-solid fa-pen-to-square"></i>
                    <h4>Kolay Başvuru</h4>
                    <p>İletişim bilgilerinizi, konu ve açıklamanızı girerek birkaç adımda başvuru oluşturun.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-card">
                    <i class="fa-solid fa-wand-magic-sparkles"></i>
                    <h4>Akıllı Yönlendirme</h4>
                    <p>Anahtar kelime analiziyle ilgili müdürlük ve öncelik seviyesi otomatik olarak önerilir.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-card">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                    <h4>Süreç Geçmişi</h4>
                    <p>Başvurunun yönlendirme, atama, işlem ve sonuçlandırma adımlarını zaman çizelgesinde izleyin.</p>
                </div>
            </div>
        </div>
    </div>
</section>
<?php require 'includes/footer.php'; ?>
