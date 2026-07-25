public function check($domain): array {<?php
require_once 'BaseChecker.php';

class BlacklistChecker extends BaseChecker {
    // زدناك ليست كبيرة
    private $rbls = [
        'zen.spamhaus.org',
        'bl.spamcop.net',
        'b.barracudacentral.org',
        'dnsbl.sorbs.net',
        'psbl.surriel.com',
        'cbl.abuseat.org'
    ];

    public function check(string $domain): array {
        $result = [
            'status' => 'ok',
            'blacklisted' => false,
            'details' => [],
            'checked_at' => date('Y-m-d H:i:s')
        ];

        // حول الدومين ل IP الى كان دومين
        $ip = $domain;
        if (!filter_var($domain, FILTER_VALIDATE_IP)) {
            $ip = gethostbyname($domain);
        }

        // الى ما لقيناش IP
        if ($ip === $domain && !filter_var($ip, FILTER_VALIDATE_IP)) {
            $result['status'] = 'error';
            $result['error'] = 'Cannot resolve domain';
            return $result;
        }

        // قلب IP باش يولي صالح لـ RBL
        $reverseIp = implode('.', array_reverse(explode('.', $ip)));

        foreach ($this->rbls as $rbl) {
            $check = $reverseIp . '.' . $rbl;
            // checkdnsrr خدام دابا حيت قلبنا IP
            if (checkdnsrr($check, 'A')) {
                $result['blacklisted'] = true;
                $result['status'] = 'listed';
                $result['details'][] = [
                    'rbl' => $rbl,
                    'listed' => true
                ];
            }
        }
        
        return $result;
    }

    public function getName() {
        return "Blacklist Checker";
    }
}
?>