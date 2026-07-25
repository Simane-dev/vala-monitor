<?php
require 'config/database.php';

// Récupération des filtres
$filterDomain = $_GET['domain']?? '';
$filterGrade = $_GET['grade']?? '';
$limit = (int)($_GET['limit']?? 50);

// Construction requête avec filtres
$sql = "SELECT * FROM diagnostics WHERE 1=1";
$params = [];
if ($filterDomain) { $sql .= " AND domain LIKE ?"; $params[] = "%$filterDomain%"; }
if ($filterGrade) { $sql .= " AND grade = ?"; $params[] = $filterGrade; }
$sql .= " ORDER BY created_at DESC LIMIT $limit";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Stats
$stats = $pdo->query("SELECT COUNT(*) as total, AVG(vala_score) as avg_score, COUNT(CASE WHEN grade='A' THEN 1 END) as count_a, COUNT(CASE WHEN grade='F' THEN 1 END) as count_f FROM diagnostics")->fetch();
$avg = round($stats['avg_score']??0);

// Derniers pings pour uptime
$uptimeData = [];
try {
    $uptimeData = $pdo->query("SELECT domain, AVG(status)*100 as uptime FROM pings GROUP BY domain ORDER BY uptime DESC LIMIT 10")->fetchAll();
} catch(Exception $e) {}
?>
<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Historique - VALA Monitor</title><link rel="stylesheet" href="assets/style.css"><script src="https://cdn.jsdelivr.net/npm/chart.js"></script></head><body>
<div class="container">
<div class="header"><h1>📊 Historique & Analytics</h1><div style="display:flex;gap:10px"><a href="index.php">🏠 Accueil</a><a href="?limit=100">Voir 100</a></div></div>

<div class="grid" style="grid-template-columns:1fr 1fr 1fr 1fr">
<div class="card" style="text-align:center"><h3>Total Scans</h3><div class="value" style="font-size:32px"><?= $stats['total']??0?></div></div>
<div class="card" style="text-align:center"><h3>Score Moyen</h3><div class="value" style="font-size:32px;color:<?= $avg>=80?'#10b981':'#ef4444'?>"><?= $avg?>/100</div></div>
<div class="card" style="text-align:center"><h3>Grade A</h3><div class="value" style="font-size:32px;color:#10b981"><?= $stats['count_a']??0?></div></div>
<div class="card" style="text-align:center"><h3>Grade F</h3><div class="value" style="font-size:32px;color:#ef4444"><?= $stats['count_f']??0?></div></div>
</div>

<div class="card" style="margin:20px 0"><h3>📈 Évolution des Scores (30 derniers)</h3><canvas id="scoreChart" height="80"></canvas></div>

<div class="card" style="margin-bottom:20px">
<form method="GET" style="display:flex;gap:10px;flex-wrap:wrap">
<input name="domain" placeholder="Filtrer domaine" value="<?= htmlspecialchars($filterDomain)?>" style="flex:1;min-width:200px;padding:10px;border-radius:10px;border:1px solid #334155;background:#0f172a;color:white">
<select name="grade" style="padding:10px;border-radius:10px;border:1px solid #334155;background:#0f172a;color:white"><option value="">Tous grades</option><option value="A" <?= $filterGrade=='A'?'selected':''?>>Grade A</option><option value="B" <?= $filterGrade=='B'?'selected':''?>>B</option><option value="C" <?= $filterGrade=='C'?'selected':''?>>C</option><option value="F" <?= $filterGrade=='F'?'selected':''?>>F</option></select>
<button style="padding:10px 20px;background:#6366f1;border:none;border-radius:10px;color:white;font-weight:700;cursor:pointer">Filtrer</button>
<a href="history.php" style="padding:10px 20px;background:#334155;border-radius:10px;color:white;text-decoration:none">Reset</a>
</form>
</div>

<div class="card">
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px"><h3>📋 Derniers Diagnostics (<?= count($rows)?>)</h3><span style="color:var(--muted);font-size:13px">Trié par date récente</span></div>
<table><tr><th>Domaine</th><th>HTTP</th><th>SSL</th><th>MX</th><th>Score</th><th>Grade</th><th>Date</th><th>Action</th></tr>
<?php foreach($rows as $r):
    $rep = json_decode($r['full_report']??'{}', true);
    $httpCode = $r['http_code']?? $rep['http']['code']?? 0;
    $sslDays = $r['ssl_days']?? 0;
?>
<tr><td><strong><?= htmlspecialchars($r['domain'])?></strong><br><span style="font-size:11px;color:var(--muted)"><?= htmlspecialchars($rep['http']['ip']??'')?></span></td><td><?= $httpCode?><br><span style="font-size:11px"><?= $r['response_time']??''?>ms</span></td><td><?= $sslDays?>j</td><td><?= $r['mx_ok']?'✅':'❌'?></td><td><strong><?= $r['vala_score']?></strong></td><td><span style="background:<?= match($r['grade']){'A'=>'#10b981','B'=>'#22c55e','C'=>'#f59e0b',default=>'#ef4444'}?>;color:white;padding:4px 12px;border-radius:20px;font-weight:800;font-size:12px"><?= $r['grade']?></span></td><td style="font-size:12px"><?= date('d/m H:i', strtotime($r['created_at']))?></td><td><a href="index.php?domain=<?= urlencode($r['domain'])?>" style="color:#6366f1;text-decoration:none;font-weight:600">Re-tester</a></td></tr>
<?php endforeach;?>
</table>
<?php if(empty($rows)):?><div style="text-align:center;padding:40px;color:var(--muted)">Aucun diagnostic trouvé. <a href="index.php" style="color:#6366f1">Lancez votre premier scan</a></div><?php endif;?>
</div>

<?php if(!empty($uptimeData)):?>
<div class="card" style="margin-top:20px"><h3>⏱️ Uptime par Domaine</h3><table><tr><th>Domaine</th><th>Uptime</th><th>Status</th></tr>
<?php foreach($uptimeData as $u):?><tr><td><?= htmlspecialchars($u['domain'])?></td><td><strong><?= round($u['uptime'],2)?>%</strong></td><td><?= $u['uptime']>=99?'🟢 Excellent':($u['uptime']>=95?'🟡 Moyen':'🔴 Critique')?></td></tr><?php endforeach;?>
</table></div>
<?php endif;?>

</div>
<script>
const ctx=document.getElementById('scoreChart');
new Chart(ctx,{type:'line',data:{labels:[<?php $rev=array_reverse($rows); foreach($rev as $r) echo "'".substr($r['created_at'],5,5)."',";?>],datasets:[{label:'Score',data:[<?php foreach($rev as $r) echo $r['vala_score'].",";?>],borderColor:'#6366f1',backgroundColor:'rgba(99,102,241,0.1)',fill:true,tension:0.4,pointRadius:3},{label:'Temps ms',data:[<?php foreach($rev as $r) echo ($r['response_time']??0).",";?>],borderColor:'#10b981',tension:0.4,hidden:true}]},options:{responsive:true,plugins:{legend:{display:true}},scales:{y:{beginAtZero:true,max:100}}}});
</script>
</body></html>