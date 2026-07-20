<nav class="navbar navbar-expand-lg navbar-dark municipal-nav">
    <div class="container">
        <a class="navbar-brand fw-bold" href="<?= e($basePath) ?>index.php">
            <span class="brand-mark"><i class="fa-solid fa-building-columns"></i></span>
            Akıllı Belediye
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-2">
                <li class="nav-item"><a class="nav-link" href="<?= e($basePath) ?>index.php">Ana Sayfa</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= e($basePath) ?>talep-olustur.php">Başvuru Oluştur</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= e($basePath) ?>talep-takip.php">Başvuru Takip</a></li>
                <li class="nav-item"><a class="btn btn-light btn-sm px-3" href="<?= e($basePath) ?>login.php">Personel Girişi</a></li>
            </ul>
        </div>
    </div>
</nav>
