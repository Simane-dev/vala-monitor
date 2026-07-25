<?php
/**
 * VALA Monitor Pro - API REST FIXED
 * Fix: Session, Headers, Offline mode, JSON errors
 */

// 1. Headers أول حاجة قبل أي echo
ob_start(); // مهم باش ما يوقعش header already sent
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('X-Powered-By: VALA-Monitor-Pro/1.0');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    ob_end_clean();
    exit;
}

// 2. Includes - بمسار آمن
$baseDir = __DIR__. '/..';
require $baseDir. '/config/database.php';
require $baseDir. '/classes/HttpChecker.php';
require $baseDir. '/classes/SslChecker.php';
require $baseDir. '/classes/DnsChecker.php';
require $baseDir. '/classes/ValaScorer.php';

// 3. دالة باش نتأكدو واش كاين إنترنت بلا fsockopen اللي كيدير مشاكل
function isInternetAvailable(): bool {
    // نجربو ب curl أسرع وما كيديرش warning
    $ch = @curl_init("https://1.1.1.1");
    if (!$ch) return false;
    curl_setopt_array($ch, [
        CURLOPT_TIMEOUT => 2,
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_NOBODY => true,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    $result = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    // إلا رجع أي كود حتى 403 راه كاين إنترنت
    return $result!== false || $code > 0;
}

// 4. Validation
$domainInput = $_GET['domain']?? $_POST['domain']?? '';
$domainInput = trim($domainInput);

if ($domainInput === '') {
    ob_end_clean();
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Paramètre domain manquant',
        'usage' => 'api/check.php?domain=vala.ma',
        'example' => 'api/check.php?domain=google.com&pretty=1',
        'method' => 'GET or POST'
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// 5. Nettoyage الدومين
$originalDomain = $domainInput;
$domain = strtolower($domainInput);
$domain = str_replace(['https://','http://','www.'], '', $domain);
$domain = explode('/', $domain)[0];
$domain = explode('?', $domain)[0];
$domain = explode(':', $domain)[0];
$domain = trim($domain, '. ');

if (strlen($domain) < 3 ||!str_contains($domain, '.')) {
    ob_end_clean();
    http_response_code(422);
    echo json_encode(['success'=>false,'error'=>"Domaine invalide: $originalDomain",'cleaned'=>$domain], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// 6. Execution
try {
    $startTotal = microtime(true);
    $isOnline = isInternetAvailable();

    if (!$isOnline) {
        // MODE OFFLINE - كنقراو demo_data.json
        $demoPath = $baseDir. '/data/demo_data.json';
        if (!file_exists($demoPath)) {
            throw new Exception("Mode offline et fichier data/demo_data.json introuvable à $demoPath");
        }
        $jsonContent = file_get_contents($demoPath);
        $report = json_decode($jsonContent, true);

        if (json_last_error()!== JSON_ERROR_NONE) {
            throw new Exception("demo_data.json فيه خطأ JSON: ". json_last_error_msg());
        }

        $report['domain'] = $domain;
        $report['original_query'] = $originalDomain;
        $report['mode'] = 'offline_demo';
        $report['message'] = 'Mode hors-ligne - Données de démonstration (Internet non détecté)';
        $report['success'] = true;
        $report['timestamp'] = date('c');
    } else {
        // MODE LIVE - فحص حقيقي
        $httpChecker = new HttpChecker($domain);
        $sslChecker = new SslChecker($domain);
        $dnsChecker = new DnsChecker($domain);

        $http = $httpChecker->check();
        $ssl = $sslChecker->check();
        $dns = $dnsChecker->check();
        $analysis = ValaScorer::analyze($http, $ssl, $dns);

        $report = array_merge(
            [
                'success' => true,
                'domain' => $domain,
                'original_query' => $originalDomain,
                'mode' => 'live',
                'timestamp' => date('c'),
                'checked_at' => date('Y-m-d H:i:s')
            ],
            compact('http','ssl','dns'),
            $analysis
        );

        // 7. Sauvegarde فالباز ب Try/Catch باش إلا فشلات ما تطيحش API
        try {
            if (isset($pdo) && $pdo instanceof PDO) {
                $stmt = $pdo->prepare("INSERT INTO diagnostics (domain,http_code,response_time,ssl_days,mx_ok,vala_score,grade,full_report) VALUES (?,?,?,?,?,?,?,?)");
                $stmt->execute([
                    $domain,
                    $http['code']?? 0,
                    $http['time']?? 0,
                    $ssl['days']?? 0,
                    ($dns['mx_ok']?? false)? 1 : 0,
                    $analysis['score']?? 0,
                    $analysis['grade']?? 'F',
                    json_encode($report, JSON_UNESCAPED_UNICODE)
                ]);
                $report['saved_id'] = $pdo->lastInsertId();
            }
        } catch (Exception $dbEx) {
            $report['save_warning'] = "Non sauvegardé en BDD: ". $dbEx->getMessage();
        }
    }

    $report['execution_time_ms'] = (int) round((microtime(true) - $startTotal) * 1000);
    $report['api_version'] = '2.0 Pro Fixed';
    $report['server'] = $_SERVER['SERVER_SOFTWARE']?? 'PHP';

    // 8. Output
    ob_end_clean();

    // إلا بغا pretty فالبراوزر
    if (isset($_GET['pretty'])) {
        header('Content-Type: text/html; charset=utf-8');
        echo "<!DOCTYPE html><body style='background:#0f172a;color:#e2e8f0;padding:20px;font-family:monospace'><h3>VALA API - Pretty Mode</h3><pre style='background:#1e293b;padding:20px;border-radius:12px;overflow:auto'>". htmlspecialchars(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))."</pre></body>";
    } else {
        $json = json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new Exception("Erreur encodage JSON: ". json_last_error_msg());
        }
        echo $json;
    }

} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    // Log فالملف
    error_log("VALA API Error: ".$e->getMessage()." in ".$e->getFile().":".$e->getLine());

    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'domain' => $domain?? $originalDomain?? 'unknown',
        'file' => basename($e->getFile()),
        'line' => $e->getLine(),
        'help' => 'Vérifiez que classes/*.php existent et que data/demo_data.json est valide'
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
?>