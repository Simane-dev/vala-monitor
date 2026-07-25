<?php
require_once 'BaseChecker.php';

class HttpChecker extends BaseChecker {
    private int $maxRedirects = 3;
    private string $userAgent = 'VALA-Monitor-Pro/1.0 (+https://vala.ma)';

    public function check(): array {
        $this->log("Début vérification HTTP pour {$this->domain}");

        // Test 1: HTTPS d'abord
        $httpsResult = $this->checkUrl("https://{$this->domain}");

        // Si HTTPS échoue, essaie HTTP
        if (!$httpsResult['ok'] && $httpsResult['code'] >= 400) {
            $this->log("HTTPS échoué, tentative HTTP");
            $httpResult = $this->checkUrl("http://{$this->domain}");
            // Si HTTP marche mais pas HTTPS -> problème SSL/Redirection
            if ($httpResult['ok']) {
                $httpsResult['diagnosis'] = "Site accessible en HTTP mais pas HTTPS - Problème SSL ou redirection manquante";
                $httpsResult['recommendation'] = "Configurez une redirection HTTP vers HTTPS dans.htaccess";
            }
        }

        // Analyse du temps de réponse
        $time = $httpsResult['time'];
        if ($time < 200) $perf = "Excellent";
        elseif ($time < 500) $perf = "Bon";
        elseif ($time < 1000) $perf = "Moyen - à optimiser";
        else $perf = "Lent - optimisation urgente";

        $httpsResult['performance_label'] = $perf;
        $httpsResult['performance_score'] = max(0, 100 - ($time / 20));

        // Vérifie les headers de sécurité
        $httpsResult['security_headers'] = $this->analyzeSecurityHeaders($httpsResult['headers']?? []);

        return $this->validateResult($httpsResult);
    }

    private function checkUrl(string $url): array {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => $this->maxRedirects,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT => $this->userAgent,
            CURLOPT_HEADER => true,
            CURLOPT_NOBODY => false,
            CURLOPT_ENCODING => '',
        ]);

        $start = microtime(true);
        $response = curl_exec($ch);
        $elapsed = round((microtime(true) - $start) * 1000);

        $info = curl_getinfo($ch);
        $error = curl_error($ch);
        $headerSize = $info['header_size']?? 0;
        $headersRaw = substr($response, 0, $headerSize);

        curl_close($ch);

        if ($error) {
            $this->log("Erreur cURL: $error");
            return [
                'code' => 0, 'time' => $elapsed, 'ok' => false,
                'ip' => 'N/A', 'error' => $error,
                'diagnosis' => "Impossible de joindre le serveur: $error",
                'recommendation' => "Vérifiez que le domaine existe et que le serveur est allumé",
                'headers' => []
            ];
        }

        $code = $info['http_code'];
        $ok = $code >= 200 && $code < 400;

        $diag = match(true) {
            $code == 200 => "Site opérationnel - Réponse OK",
            $code >= 300 && $code < 400 => "Redirection détectée vers {$info['redirect_url']}",
            $code == 403 => "Accès refusé - Vérifiez les permissions",
            $code == 404 => "Page non trouvée - Vérifiez le fichier index",
            $code >= 500 => "Erreur serveur interne - Contactez l'hébergeur",
            default => "Code HTTP $code"
        };

        return [
            'code' => $code, 'time' => $elapsed, 'ok' => $ok,
            'ip' => $info['primary_ip']?? 'N/A',
            'size' => $info['size_download']?? 0,
            'redirect_url' => $info['redirect_url']?? '',
            'diagnosis' => $diag,
            'headers' => $this->parseHeaders($headersRaw),
            'error' => null
        ];
    }

    private function parseHeaders(string $raw): array {
        $headers = []; $lines = explode("\n", $raw);
        foreach ($lines as $line) {
            if (str_contains($line, ':')) {
                [$k, $v] = explode(':', $line, 2);
                $headers[trim($k)] = trim($v);
            }
        }
        return $headers;
    }

    private function analyzeSecurityHeaders(array $headers): array {
        $required = ['Strict-Transport-Security','X-Frame-Options','X-Content-Type-Options'];
        $found = []; $missing = [];
        foreach ($required as $h) {
            if (isset($headers[$h])) $found[] = $h; else $missing[] = $h;
        }
        return ['found'=>$found,'missing'=>$missing,'score'=>count($found)/count($required)*100];
    }
}
?>