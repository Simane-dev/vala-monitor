<?php
require_once __DIR__ . '/BaseChecker.php';

class BlacklistChecker extends BaseChecker {
    private array $rbls = [
        'zen.spamhaus.org',
        'bl.spamcop.net',
        'cbl.abuseat.org',
        'dnsbl.sorbs.net'
    ];

    public function getName(): string {
        return 'Blacklist RBL Checker';
    }

    public function check(): array {
        $ip = gethostbyname($this->domain);
        
        if ($ip === $this->domain) {
            return [
                'blacklisted' => false,
                'ip'          => 'N/A',
                'listed_on'   => [],
                'total_rbls'  => count($this->rbls),
                'error'       => 'Impossible de résoudre l\'adresse IP du domaine.'
            ];
        }

        $reverseIp = implode('.', array_reverse(explode('.', $ip)));
        $listedOn = [];

        foreach ($this->rbls as $rbl) {
            $lookup = $reverseIp . '.' . $rbl;
            if (checkdnsrr($lookup, 'A')) {
                $listedOn[] = $rbl;
            }
        }

        return [
            'blacklisted' => !empty($listedOn),
            'ip'          => $ip,
            'listed_on'   => $listedOn,
            'total_rbls'  => count($this->rbls),
            'checked_count' => count($this->rbls) - count($listedOn)
        ];
    }
}