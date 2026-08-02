<?php
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../classes/BaseChecker.php';
require_once __DIR__ . '/../classes/HttpChecker.php';
require_once __DIR__ . '/../classes/SslChecker.php';
require_once __DIR__ . '/../classes/DnsChecker.php';
require_once __DIR__ . '/../classes/BlacklistChecker.php';
require_once __DIR__ . '/../classes/ValaScorer.php';

$domain = $_GET['domain'] ?? $_POST['domain'] ?? null;

if (!$domain) {
    http_response_code(400);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Le paramètre domain est obligatoire.'
    ], JSON_PRETTY_PRINT);
    exit;
}

$domain = preg_replace('#^https?://#', '', trim($domain));
$domain = rtrim($domain, '/');

try {
    $httpChecker      = new HttpChecker($domain);
    $sslChecker       = new SslChecker($domain);
    $dnsChecker       = new DnsChecker($domain);
    $blacklistChecker = new BlacklistChecker($domain);

    $httpData      = $httpChecker->check();
    $sslData       = $sslChecker->check();
    $dnsData       = $dnsChecker->check();
    $blacklistData = $blacklistChecker->check();

    $scoreData = ValaScorer::analyze($httpData, $sslData, $dnsData, $blacklistData);

    echo json_encode([
        'status'    => 'success',
        'domain'    => $domain,
        'timestamp' => date('c'),
        'score'     => $scoreData,
        'details'   => [
            'http'      => $httpData,
            'ssl'       => $sslData,
            'dns'       => $dnsData,
            'blacklist' => $blacklistData
        ]
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}