<?php

class ValaScorer {
    private $results;

    public function __construct(array $results) {
        $this->results = $results;
    }

    public function calculate(): array {
        $http = $this->results['HttpChecker'] ?? [];
        $ssl  = $this->results['SslChecker'] ?? [];
        $dns  = $this->results['DnsChecker'] ?? [];

        $statusCode  = (int)($http['status_code'] ?? $http['http_code'] ?? 0);
        $responseTime = (int)($http['response_time'] ?? 0);
        $sslValid    = (bool)($ssl['valid'] ?? false);
        $sslDays     = (int)($ssl['days_left'] ?? $ssl['ssl_days'] ?? 0);
        $dnsResolved = (bool)($dns['resolved'] ?? true);
        $mxValid     = (bool)($dns['mx_valid'] ?? $dns['mx_ok'] ?? false);

        // 1. الشرط الوحيد اللي كيعطي 0 هو إلا كان الموقع طايح تماماً (Offline/No DNS)
        if ($statusCode === 0 || !$dnsResolved) {
            return [
                'score'     => 0,
                'status'    => 'CRITICAL',
                'penalties' => ['SITE_OFFLINE_OR_DNS_FAILED']
            ];
        }

        // يبدأ بـ 100 نقطة ونبدأ الخصم المنطقي
        $score = 100;
        $penalties = [];

        // أ. خصم الـ HTTP Code
        if ($statusCode !== 200) {
            $score -= 30;
            $penalties[] = "HTTP_NOT_200";
        }

        // ب. خصم زمن الاستجابة (Temps de réponse) - خصم تدريجي وليس قاتل
        if ($responseTime > 2000) {
            $score -= 25; // نقص 25 نقطة فقط حيت بطيء (أكثر من 2 ثواني)
            $penalties[] = "VERY_SLOW_RESPONSE";
        } elseif ($responseTime > 1000) {
            $score -= 15;
            $penalties[] = "SLOW_RESPONSE";
        } elseif ($responseTime > 500) {
            $score -= 5;
            $penalties[] = "MODERATE_RESPONSE";
        }

        // ج. خصم الـ SSL
        if (!$sslValid || $sslDays <= 0) {
            $score -= 30;
            $penalties[] = "NO_SSL_OR_EXPIRED";
        } elseif ($sslDays < 30) {
            $score -= 10;
            $penalties[] = "SSL_EXPIRING_SOON";
        }

        // د. خصم سيرفر البريد MX
        if (!$mxValid) {
            $score -= 10;
            $penalties[] = "NO_MX_RECORD";
        }

        // ضمان أن النتيجة محصورة بين 0 و 100
        $finalScore = max(0, min(100, $score));

        $status = 'EXCELLENT';
        if ($finalScore < 50) {
            $status = 'CRITICAL';
        } elseif ($finalScore < 75) {
            $status = 'WARNING';
        }

        return [
            'score'     => $finalScore,
            'status'    => $status,
            'penalties' => $penalties
        ];
    }
}
?>