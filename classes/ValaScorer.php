<?php
class ValaScorer {
    private static array $weights = ['http'=>40,'ssl'=>30,'dns'=>20,'security'=>10];

    public static function analyze(array $http, array $ssl, array $dns): array {
        $score = 0; $issues = []; $solutions = []; $warnings = []; $details = [];

        // 1. HTTP - 40 points
        if ($http['ok']) {
            $timeScore = max(0, 40 - ($http['time'] / 50));
            $score += $timeScore;
            $details['http'] = "HTTP OK +".round($timeScore,1)."pts";
            if ($http['time'] > 800) {
                $warnings[] = "Temps de réponse lent: {$http['time']}ms";
                $solutions[] = "Optimisez les images et activez le cache";
            }
        } else {
            $issues[] = "🔴 Site inaccessible (HTTP {$http['code']})";
            $solutions[] = "Vérifiez Apache/Nginx, redémarrez le serveur, vérifiez le DNS";
            $details['http'] = "HTTP FAIL 0pts";
        }

        // 2. SSL - 30 points
        if ($ssl['valid']) {
            if ($ssl['days'] > 60) { $score+=30; $details['ssl']="SSL Excellent +30pts"; }
            elseif ($ssl['days'] > 30) { $score+=25; $details['ssl']="SSL OK +25pts"; }
            elseif ($ssl['days'] > 7) { $score+=15; $warnings[]="SSL expire dans {$ssl['days']} jours"; $solutions[]="Renouvelez le certificat SSL rapidement"; $details['ssl']="SSL Warning +15pts"; }
            else { $score+=5; $issues[]="🚨 SSL expire dans {$ssl['days']} jours - URGENT"; $solutions[]="Renouvellement SSL immédiat via cPanel > Let's Encrypt"; $details['ssl']="SSL Critique +5pts"; }
        } else {
            $issues[]="🔴 Certificat SSL invalide ou manquant"; $solutions[]="Installez un certificat SSL Let's Encrypt gratuit"; $details['ssl']="SSL FAIL 0pts";
        }

        // 3. DNS/MX - 20 points
        if ($dns['mx_ok']) { $score+=20; $details['dns']="MX OK +20pts"; }
        else { $issues[]="🟡 Aucun enregistrement MX - Les emails ne fonctionneront pas"; $solutions[]="Ajoutez un enregistrement MX dans Cloudflare: @ MX 10 mail.vala.ma"; $details['dns']="MX FAIL 0pts"; }

        // 4. Sécurité - 10 points
        $secScore = $http['security_headers']['score']?? 0;
        $score += ($secScore/100)*10;
        $details['security'] = "Sécurité +".round($secScore/10,1)."pts";

        $grade = match(true) {
            $score >= 90 => 'A', $score >= 80 => 'B', $score >= 65 => 'C', $score >= 50 => 'D', default => 'F'
        };

        $gradeLabel = match($grade) {
            'A'=>'Excellent - Prêt pour production','B'=>'Bon - Quelques optimisations','C'=>'Moyen - Corrections nécessaires','D'=>'Faible - Intervention requise','F'=>'Critique - Site en danger'
        };

        return [
            'score'=> (int) round($score),
            'grade'=> $grade,
            'grade_label'=> $gradeLabel,
            'issues'=> $issues,
            'warnings'=> $warnings,
            'sols'=> $solutions,
            'details'=> $details,
            'breakdown'=> ['http'=> $http['ok']?40:0, 'ssl'=> $ssl['valid']?30:0, 'dns'=> $dns['mx_ok']?20:0]
        ];
    }

    public static function getRecommendations(int $score): array {
        if ($score >= 90) return ["Félicitations! Maintenez ce niveau", "Pensez à activer la surveillance automatique"];
        if ($score >= 70) return ["Optimisez le temps de chargement", "Vérifiez les headers de sécurité"];
        return ["Intervention urgente requise", "Contactez le support VALA: support@vala.ma"];
    }
}
?>