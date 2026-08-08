<?php

class HttpChecker {
    private string $domain;

    public function __construct(string $domain) {
        $clean = preg_replace('/^https?:\/\//i', '', $domain);
        $this->domain = explode('/', $clean)[0];
    }

    public function check(): array {
        if (empty($this->domain)) {
            return [
                'status'        => 'offline',
                'status_code'   => 0,
                'response_time' => 0
            ];
        }

        $url = "https://" . $this->domain;
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_NOBODY         => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_TIMEOUT        => 4,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT      => 'VALA-Monitor/1.0'
        ]);

        $response = curl_exec($ch);

        if ($response === false) {
            curl_close($ch);
            return [
                'status'        => 'offline',
                'status_code'   => 0,
                'response_time' => 0
            ];
        }

        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $timeInSeconds = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
        $responseTimeMs = (int)round($timeInSeconds * 1000);

        curl_close($ch);

        $isOnline = ($httpCode >= 200 && $httpCode < 400);

        return [
            'status'        => $isOnline ? 'online' : 'offline',
            'status_code'   => $httpCode,
            'response_time' => $responseTimeMs
        ];
    }
}
?>