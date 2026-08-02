<?php
require_once __DIR__ . '/BaseChecker.php';

class SslChecker extends BaseChecker {
    public function getName(): string {
        return 'SSL Certificate Checker';
    }

    public function check(): array {
        $gcontext = stream_context_create([
            "ssl" => [
                "capture_peer_cert" => true,
                "verify_peer"      => false,
                "verify_peer_name" => false,
            ]
        ]);

        $client = @stream_socket_client(
            "ssl://" . $this->domain . ":443",
            $errno,
            $errstr,
            $this->timeout,
            STREAM_CLIENT_CONNECT,
            $gcontext
        );

        if (!$client) {
            return [
                'valid'          => false,
                'days_remaining' => 0,
                'issuer'         => 'N/A',
                'valid_from'     => 'N/A',
                'valid_to'       => 'N/A',
                'signature_algo' => 'N/A',
                'error'          => "Échec de connexion SSL ($errno): $errstr"
            ];
        }

        $cont = stream_context_get_params($client);
        $cert = openssl_x509_parse($cont["options"]["ssl"]["peer_certificate"]);
        fclose($client);

        if (!$cert) {
            return [
                'valid'          => false,
                'days_remaining' => 0,
                'issuer'         => 'N/A',
                'error'          => 'Impossible de lire le certificat SSL.'
            ];
        }

        $validFrom = date('Y-m-d H:i:s', $cert['validFrom_time_t'] ?? 0);
        $validTo   = date('Y-m-d H:i:s', $cert['validTo_time_t'] ?? 0);
        $daysRemaining = round(($cert['validTo_time_t'] - time()) / 86400);

        $issuer = $cert['issuer']['O'] ?? ($cert['issuer']['CN'] ?? 'Inconnu');
        $subject = $cert['subject']['CN'] ?? $this->domain;

        return [
            'valid'          => ($daysRemaining > 0),
            'days_remaining' => (int)$daysRemaining,
            'issuer'         => $issuer,
            'domain_name'    => $subject,
            'valid_from'     => $validFrom,
            'valid_to'       => $validTo,
            'signature_algo' => $cert['signatureTypeSN'] ?? 'Inconnu'
        ];
    }
}