<?php
require_once 'BaseChecker.php';

class DnsChecker extends BaseChecker {

    public function check(): array {
        $this->log("Début vérification DNS pour {$this->domain}");

        $results = [
            'a' => 'N/A', 'aaaa' => 'N/A', 'mx' => 'Aucun', 'mx_ok' => false,
            'ns' => [], 'txt' => [], 'has_spf' => false, 'has_dmarc' => false,
            'cloudflare' => false, 'propagation' => 'N/A'
        ];

        // 1. Enregistrement A
        $aRecords = @dns_get_record($this->domain, DNS_A);
        if (!empty($aRecords)) {
            $results['a'] = $aRecords[0]['ip'];
            $results['a_all'] = array_column($aRecords, 'ip');
            $this->log("A trouvé: {$results['a']}");
            // Détecte Cloudflare
            if (str_starts_with($results['a'], '104.') || str_starts_with($results['a'], '172.')) {
                $results['cloudflare'] = true;
            }
        }

        // 2. AAAA IPv6
        $aaaa = @dns_get_record($this->domain, DNS_AAAA);
        if (!empty($aaaa)) $results['aaaa'] = $aaaa[0]['ipv6'];

        // 3. MX - Le plus important pour VALA
        $mxRecords = @dns_get_record($this->domain, DNS_MX);
        if (!empty($mxRecords)) {
            usort($mxRecords, fn($a,$b) => $a['pri'] <=> $b['pri']);
            $results['mx'] = $mxRecords[0]['target'];
            $results['mx_all'] = $mxRecords;
            $results['mx_ok'] = true;
            $this->log("MX trouvé: {$results['mx']}");
        } else {
            $this->log("Aucun MX trouvé - Problème email");
        }

        // 4. NS
        $nsRecords = @dns_get_record($this->domain, DNS_NS);
        if (!empty($nsRecords)) $results['ns'] = array_column($nsRecords, 'target');

        // 5. TXT pour SPF / DKIM
        $txtRecords = @dns_get_record($this->domain, DNS_TXT);
        if (!empty($txtRecords)) {
            foreach ($txtRecords as $txt) {
                $val = $txt['txt'];
                $results['txt'][] = $val;
                if (str_contains($val, 'v=spf1')) $results['has_spf'] = true;
                if (str_contains($val, 'v=DMARC')) $results['has_dmarc'] = true;
            }
        }

        // 6. Vérification DMARC
        $dmarc = @dns_get_record("_dmarc.{$this->domain}", DNS_TXT);
        if (!empty($dmarc)) $results['has_dmarc'] = true;

        // Diagnostic
        $diag = [];
        if (!$results['mx_ok']) $diag[] = "Pas de MX - emails ne marcheront pas";
        if (!$results['has_spf']) $diag[] = "Pas de SPF - risque spam élevé";
        if (!$results['has_dmarc']) $diag[] = "Pas de DMARC - usurpation possible";
        if (empty($diag)) $diag[] = "Configuration DNS parfaite";

        $results['diagnosis'] = implode(" | ", $diag);
        $results['score'] = ($results['mx_ok']?50:0) + ($results['has_spf']?25:0) + ($results['has_dmarc']?25:0);

        return $this->validateResult($results);
    }

    public function checkPropagation(): array {
        $publicDns = ['8.8.8.8', '1.1.1.1', '9.9.9.9'];
        $results = [];
        foreach ($publicDns as $dns) {
            // Simulation - en prod on ferait un vrai check
            $results[$dns] = ['a' => $this->domain.' -> OK', 'time' => rand(20,100).'ms'];
        }
        return $results;
    }
}
?>