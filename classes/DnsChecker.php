<?php
require_once __DIR__ . '/BaseChecker.php';

class DnsChecker extends BaseChecker {
    public function getName(): string {
        return 'DNS Records Checker';
    }

    public function check(): array {
        $aRecords     = @dns_get_record($this->domain, DNS_A);
        $aaaaRecords  = @dns_get_record($this->domain, DNS_AAAA);
        $mxRecords    = @dns_get_record($this->domain, DNS_MX);
        $txtRecords   = @dns_get_record($this->domain, DNS_TXT);
        $nsRecords    = @dns_get_record($this->domain, DNS_NS);

        $hasSpf = false;
        if (!empty($txtRecords)) {
            foreach ($txtRecords as $txt) {
                if (isset($txt['entries'])) {
                    foreach ($txt['entries'] as $entry) {
                        if (strpos($entry, 'v=spf1') !== false) {
                            $hasSpf = true;
                            break 2;
                        }
                    }
                }
            }
        }

        return [
            'has_a'       => !empty($aRecords),
            'has_aaaa'    => !empty($aaaaRecords),
            'has_mx'      => !empty($mxRecords),
            'has_txt'     => !empty($txtRecords),
            'has_spf'     => $hasSpf,
            'ip_v4'       => $aRecords[0]['ip'] ?? 'N/A',
            'ip_v6'       => $aaaaRecords[0]['ipv6'] ?? 'N/A',
            'mx_servers'  => array_column($mxRecords ?? [], 'target'),
            'nameservers' => array_column($nsRecords ?? [], 'target')
        ];
    }
}