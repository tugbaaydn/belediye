<?php
declare(strict_types=1);

function e(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function start_session(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function csrf_token(): string {
    start_session();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(): void {
    start_session();
    $token = $_POST['csrf_token'] ?? '';
    if (!$token || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(419);
        exit('Oturum doğrulaması başarısız. Sayfayı yenileyip tekrar deneyin.');
    }
}

function flash(string $type, string $message): void {
    start_session();
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash(): ?array {
    start_session();
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

function redirect(string $url): never {
    header("Location: {$url}");
    exit;
}

function generate_tracking_code(PDO $pdo): string {
    do {
        $code = 'BLD-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(4)));
        $stmt = $pdo->prepare('SELECT id FROM complaints WHERE tracking_code = ?');
        $stmt->execute([$code]);
    } while ($stmt->fetch());
    return $code;
}

function slug_text(string $text): string {
    $map = ['Ç'=>'C','ç'=>'c','Ğ'=>'G','ğ'=>'g','İ'=>'I','ı'=>'i','Ö'=>'O','ö'=>'o','Ş'=>'S','ş'=>'s','Ü'=>'U','ü'=>'u'];
    $text = strtr($text, $map);
    return strtolower($text);
}

function smart_analysis(string $title, string $description, array $departments): array {
    $text = slug_text($title . ' ' . $description);

    $rules = [
        'Fen İşleri Müdürlüğü' => ['yol','asfalt','kaldirim','cukur','bordur','kopru','altyapi','ustyapi'],
        'Temizlik İşleri Müdürlüğü' => ['cop','atik','konteyner','temizlik','moloz','koku','sokak temizligi'],
        'Su ve Kanalizasyon Müdürlüğü' => ['su','kanal','kanalizasyon','logar','patlak','sizinti','musluk','gider'],
        'Park ve Bahçeler Müdürlüğü' => ['park','bahce','agac','cim','bank','oyun grubu','budama','yesil alan'],
        'Zabıta Müdürlüğü' => ['zabita','isgal','seyyar','gurultu','ruhsat','pazar','dilenci','kaldirim isgali'],
        'Ulaşım Hizmetleri Müdürlüğü' => ['otobus','durak','trafik','ulasim','sinyalizasyon','servis','yaya gecidi'],
        'Veteriner İşleri Müdürlüğü' => ['hayvan','kopek','kedi','veteriner','sokak hayvani','yarali hayvan'],
        'Sosyal Yardım İşleri Müdürlüğü' => ['yardim','gida','engelli','yasli','sosyal destek','ihtiyac sahibi']
    ];

    $scores = [];
    foreach ($rules as $deptName => $keywords) {
        $scores[$deptName] = 0;
        foreach ($keywords as $keyword) {
            if (str_contains($text, $keyword)) {
                $scores[$deptName] += 2;
            }
        }
    }

    arsort($scores);
    $bestName = array_key_first($scores);
    $bestScore = $bestName ? $scores[$bestName] : 0;
    $departmentId = null;

    if ($bestScore > 0) {
        foreach ($departments as $department) {
            if ($department['name'] === $bestName) {
                $departmentId = (int)$department['id'];
                break;
            }
        }
    }

    $urgentWords = ['acil','tehlike','yangin','can guvenligi','elektrik','patlama','sel','tasmak','cok ciddi','yarali'];
    $highWords = ['patlak','kaza','cokuk','kopmus','tikanmis','karanlik','hijyen'];

    $priority = 'Normal';
    foreach ($urgentWords as $word) {
        if (str_contains($text, $word)) {
            $priority = 'Acil';
            break;
        }
    }
    if ($priority === 'Normal') {
        foreach ($highWords as $word) {
            if (str_contains($text, $word)) {
                $priority = 'Yüksek';
                break;
            }
        }
    }

    return [
        'department_id' => $departmentId,
        'department_name' => $bestScore > 0 ? $bestName : null,
        'priority' => $priority,
        'score' => $bestScore
    ];
}

function status_badge(string $status): string {
    return match ($status) {
        'Yeni' => 'secondary',
        'İnceleniyor' => 'info',
        'Yönlendirildi' => 'primary',
        'İşlemde' => 'warning',
        'Çözüldü' => 'success',
        'Reddedildi' => 'danger',
        default => 'dark'
    };
}

function priority_badge(string $priority): string {
    return match ($priority) {
        'Acil' => 'danger',
        'Yüksek' => 'warning',
        'Düşük' => 'secondary',
        default => 'primary'
    };
}

function status_label(string $status): string {
    return $status;
}

function due_date_for_priority(string $priority): string {
    $days = match ($priority) {
        'Acil' => 1,
        'Yüksek' => 3,
        'Düşük' => 10,
        default => 7
    };
    return date('Y-m-d', strtotime("+{$days} days"));
}
