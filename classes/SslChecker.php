<?php

class SslChecker {
    private $domain;

    public function __construct($domain) {
        // تنظيف الدومين من أي مسارات أو بروتوكولات
        $clean = preg_replace('/^https?:\/\//i', '', $domain);
        $this->domain = explode('/', $clean)[0];
    }

    public function check() {
        if (empty($this->domain)) {
            return [
                'valid' => false,
                'days_left' => 0,
                'issuer' => 'Inconnu'
            ];
        }

        // تحضير الـ SSL Context مع تفعيل SNI
        $gcontext = stream_context_create([
            "ssl" => [
                "capture_peer_cert" => true,
                "verify_peer"       => false,
                "verify_peer_name"  => false,
                "SNI_enabled"        => true,
                "peer_name"         => $this->domain
            ]
        ]);

        // استعمال @ لإخفاء أي PHP Warnings أثناء الاتصال
        $client = @stream_socket_client(
            "ssl://" . $this->domain . ":443",
            $errno,
            $errstr,
            5,
            STREAM_CLIENT_CONNECT,
            $gcontext
        );

        if (!$client) {
            return [
                'valid' => false,
                'days_left' => 0,
                'issuer' => 'Inconnu'
            ];
        }

        $cont = stream_context_get_params($client);
        $cert = $cont["options"]["ssl"]["peer_certificate"] ?? null;

        if (!$cert) {
            @fclose($client);
            return [
                'valid' => false,
                'days_left' => 0,
                'issuer' => 'Inconnu'
            ];
        }

        $certData = openssl_x509_parse($cert);
        @fclose($client);

        if (!$certData || !isset($certData['validTo_time_t'])) {
            return [
                'valid' => false,
                'days_left' => 0,
                'issuer' => 'Inconnu'
            ];
        }

        $validTo = $certData['validTo_time_t'];
        $daysLeft = max(0, (int)floor(($validTo - time()) / 86400));

        return [
            'valid' => $daysLeft > 0,
            'days_left' => $daysLeft,
            'issuer' => $certData['issuer']['O'] ?? ($certData['issuer']['CN'] ?? 'Inconnu')
        ];
    }
}