<?php
require_once '../config/database.php';
require_once '../classes/BaseChecker.php';
require_once '../classes/HttpChecker.php';
require_once '../classes/SslChecker.php';
require_once '../classes/DnsChecker.php';
require_once '../classes/BlacklistChecker.php';
require_once '../classes/ValaScorer.php';

header('Content-Type: application/json');

$domain = $_GET['domain'] ?? $_POST['domain'] ?? '';

if (empty($domain)) {
    echo json_encode(['status' => 'error', 'message' => 'Domaine manquant']);
    exit;
}

$domain = str_replace(['http://', 'https://', 'www.'], '', $domain);
$domain = trim($domain, '/');

function checkLocalInternet() {
    // 8.8.8.8 هو Google DNS, Timeout 2 ثواني
    $connected = @fsockopen("8.8.8.8", 53, $errno, $errstr, 2);
    if ($connected) {
        fclose($connected);
        return true;
    }
    return false;
}

if (!checkLocalInternet()) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Pas de connexion Internet sur le serveur.'
    ]);
    exit;
}

try {
    $httpChecker = new HttpChecker($domain);
    $sslChecker = new SslChecker($domain);
    $dnsChecker = new DnsChecker($domain);
    $blacklistChecker = new BlacklistChecker($domain);

    $httpData = $httpChecker->check();
    $sslData = $sslChecker->check();
    $dnsData = $dnsChecker->check();
    $blacklistData = $blacklistChecker->check();

    $allResults = [
        'HttpChecker' => $httpData,
        'SslChecker' => $sslData,
        'DnsChecker' => $dnsData,
        'BlacklistChecker' => $blacklistData
    ];

    $scorer = new ValaScorer($allResults);
    $scoreData = $scorer->calculate();

    echo json_encode([
        'status' => 'success',
        'domain' => $domain,
        'timestamp' => date('c'),
        'score' => $scoreData,
        'details' => [
            'http' => $httpData,
            'ssl' => $sslData,
            'dns' => $dnsData,
            'blacklist' => $blacklistData
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>