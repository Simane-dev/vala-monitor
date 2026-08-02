<?php
require_once __DIR__ . '/BaseChecker.php';

class HttpChecker extends BaseChecker {
    public function getName(): string {
        return 'HTTP / Performance Checker';
    }

    public function check(): array {
        $url = "http://" . $this->domain;
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_NOBODY         => false,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_USERAGENT      => 'VALAMonitorPro/1.0 (+https://vala.ma)'
        ]);

        $startTime = microtime(true);
        $response = curl_exec($ch);
        $endTime = microtime(true);

        $responseTime = round(($endTime - $startTime) * 1000);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $error = curl_error($ch);
        $errno = curl_errno($ch);
        curl_close($ch);

        if ($response === false || $httpCode === 0) {
            return [
                'status'        => 'offline',
                'status_code'   => 0,
                'response_time' => 0,
                'content_type'  => 'N/A',
                'error_code'    => $errno,
                'error'         => $error ?: 'Impossible de contacter le serveur HTTP.'
            ];
        }

        $headersRaw = substr($response, 0, $headerSize);
        $isHttps = (strpos($url, 'https://') === 0);

        return [
            'status'        => ($httpCode >= 200 && $httpCode < 400) ? 'online' : 'degraded',
            'status_code'   => $httpCode,
            'response_time' => $responseTime,
            'content_type'  => $contentType ?: 'Inconnu',
            'is_https'      => $isHttps,
            'headers_count' => count(explode("\r\n", trim($headersRaw)))
        ];
    }
}