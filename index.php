<?php
require 'config/database.php';
require 'classes/HttpChecker.php';
require 'classes/SslChecker.php';
require 'classes/DnsChecker.php';
require 'classes/ValaScorer.php';

$report = null; $error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['domain'])) {
    try {
        $domain = trim(str_replace(['https://','http://','www.','/'], '', $_POST['domain']));
        if (strlen($domain) < 3) throw new Exception("Domaine trop court");

        // Mode offline?
        $isOnline = @fsockopen("1.1.1.1", 53, $a, $b, 1);
        if (!$isOnline) {
            $demo = json_decode(file_get_contents('data/demo_data.json'), true);
            $report = array_merge($demo, ['domain' => $domain.' (DEMO Offline)','mode'=>'offline']);
        } else {
            $http = (new HttpChecker($domain))->check();
            $ssl = (new SslChecker($domain))->check();
            $dns = (new DnsChecker($domain))->check();
            $analysis = ValaScorer::analyze($http, $ssl, $dns);
            $report = array_merge(compact('domain','http','ssl','dns'), $analysis, ['mode'=>'live','checked_at'=>date('Y-m-d H:i:s')]);

            // Sauvegarde en BDD
            $stmt = $pdo->prepare("INSERT INTO diagnostics (domain,http_code,response_time,ssl_days,mx_ok,vala_score,grade,full_report) VALUES (?,?,?,?,?,?,?,?)");
            $stmt->execute([$domain,$http['code'],$http['time'],$ssl['days'],$dns['mx_ok']?1:0,$analysis['score'],$analysis['grade'],json_encode($report, JSON_UNESCAPED_UNICODE)]);

            // Ping pour uptime
            $pdo->prepare("INSERT INTO pings (domain,status,response_time) VALUES (?,?,?)")->execute([$domain,$http['ok']?1:0,$http['time']]);
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="fr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>VALA Monitor Pro</title><link rel="stylesheet" href="assets/style.css"></head><body>
<div class="container">
<div class="header"><h1>🛡️ VALA Monitor <span style="color:#6366f1">PRO</span></h1><div style="display:flex;gap:10px"><a href="history.php">📊 Historique</a><a href="api/check.php?domain=vala.ma" target="_blank">🔌 API</a></div></div>

<div class="search-box">
<form method="POST" style="display:flex;gap:12px;width:100%">
<input name="domain" placeholder="Entrez un domaine: ex: vala.ma, google.com" required value="<?= htmlspecialchars($_POST['domain']??'')?>">
<button type="submit">🔍 Diagnostiquer</button>
</form>
</div>

<?php if($error):?><div class="issue">❌ Erreur: <?= htmlspecialchars($error)?></div><?php endif;?>

<?php if($report):?>
<div class="score-card">
<div class="score" style="color:<?= match($report['grade']){'A'=>'#10b981','B'=>'#22c55e','C'=>'#f59e0b','D'=>'#f97316',default=>'#ef4444'}?>"><?= $report['score']?>/100</div>
<div style="font-size:20px;font-weight:700">Grade <?= $report['grade']?> - <?= $report['grade_label']??''?></div>
<div style="color:var(--muted);margin-top:8px"><?= $report['domain']?> • Vérifié: <?= $report['checked_at']?? date('H:i:s')?> • Mode: <?= $report['mode']?></div>
</div>

<div class="grid">
<div class="card"><h3>🌐 HTTP / Performance</h3><div class="value"><?= $report['http']['code']?> - <?= $report['http']['time']?>ms</div><div style="margin-top:8px;color:var(--muted)">IP: <?= $report['http']['ip']?> • <?= $report['http']['performance_label']??''?></div><div style="margin-top:10px;font-size:14px"><?= $report['http']['diagnosis']?></div></div>
<div class="card"><h3>🔒 SSL / Sécurité</h3><div class="value"><?= $report['ssl']['days']?> jours restants</div><div style="margin-top:8px;color:var(--muted)">Expire: <?= $report['ssl']['expire']?> • <?= $report['ssl']['issuer']??''?></div><div style="margin-top:10px;font-size:14px"><?= $report['ssl']['diagnosis']??''?></div></div>
</div>

<div class="card"><h3>📧 DNS & Email</h3><div style="display:flex;gap:20px;flex-wrap:wrap"><span>A: <strong><?= $report['dns']['a']?? $report['dns']['dns_a']??'N/A'?></strong></span><span>MX: <strong><?= $report['dns']['mx']?? $report['dns']['dns_mx']??'N/A'?></strong> <?= ($report['dns']['mx_ok']??false)?'<span class="badge-ok">OK</span>':'<span class="badge-bad">MANQUE</span>'?></span><span>SPF: <?= ($report['dns']['has_spf']??false)?'✅':'❌'?></span><span>DMARC: <?= ($report['dns']['has_dmarc']??false)?'✅':'❌'?></span></div><div style="margin-top:10px;font-size:14px;color:var(--muted)"><?= $report['dns']['diagnosis']??''?></div></div>

<div style="margin-top:20px"><h3 style="margin-bottom:12px">🔍 Diagnostic Détaillé</h3>
<?php foreach($report['issues']??[] as $issue):?><div class="issue">⚠️ <?= htmlspecialchars($issue)?></div><?php endforeach;?>
<?php foreach($report['warnings']??[] as $warn):?><div class="issue" style="border-color:#f59e0b;background:rgba(245,158,11,0.1)">⚡ <?= htmlspecialchars($warn)?></div><?php endforeach;?>
<?php foreach($report['sols']??[] as $sol):?><div class="solution">💡 <?= htmlspecialchars($sol)?></div><?php endforeach;?>
<?php if(empty($report['issues']) && empty($report['warnings'])):?><div class="solution">✅ Aucun problème détecté - Configuration parfaite!</div><?php endif;?>
</div>

<details style="margin-top:20px" class="card"><summary style="cursor:pointer;font-weight:600">Voir le rapport JSON complet</summary><pre style="background:#0f172a;padding:16px;border-radius:10px;overflow:auto;margin-top:12px;font-size:12px"><?= json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)?></pre></details>

<?php endif;?>
</div></body></html>