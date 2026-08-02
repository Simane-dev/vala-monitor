<?php
// Exécution automatique via CLI ou CRON
if (php_sapi_name() !== 'cli' && !isset($_GET['key'])) {
    die("Accès refusé.");
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/classes/BaseChecker.php';
require_once __DIR__ . '/classes/HttpChecker.php';
require_once __DIR__ . '/classes/SslChecker.php';
require_once __DIR__ . '/classes/DnsChecker.php';
require_once __DIR__ . '/classes/BlacklistChecker.php';
require_once __DIR__ . '/classes/ValaScorer.php';

try {
    $db = Database::getInstance();
    $stmt = $db->query("SELECT domain FROM monitored_domains WHERE active = 1");
    $domains = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo "[" . date('Y-m-d H:i:s') . "] Début du Cron Job...\n";

    foreach ($domains as $domain) {
        $httpData      = (new HttpChecker($domain))->check();
        $sslData       = (new SslChecker($domain))->check();
        $dnsData       = (new DnsChecker($domain))->check();
        $blacklistData = (new BlacklistChecker($domain))->check();

        $scoreData = ValaScorer::analyze($httpData, $sslData, $dnsData, $blacklistData);

        $saveStmt = $db->prepare("INSERT INTO checks (domain, score, status, penalties_json, created_at) VALUES (?, ?, ?, ?, NOW())");
        $saveStmt->execute([
            $domain,
            $scoreData['score'],
            $scoreData['status'],
            json_encode($scoreData['penalties'])
        ]);

        echo " - Domaine $domain vérifié. Score: {$scoreData['score']}\n";
    }

    echo "[" . date('Y-m-d H:i:s') . "] Cron Job terminé avec succès.\n";

} catch (Exception $e) {
    echo "Erreur Cron: " . $e->getMessage() . "\n";
}