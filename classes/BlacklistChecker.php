<?php
require_once 'BaseChecker.php';

class BlacklistChecker extends BaseChecker {
    private $rbls = [
        'zen.spamhaus.org',
        'bl.spamcop.net'
    ];

    public function check($domain) {
        $result = [
            'status' => 'ok',
            'blacklisted' => false,
            'details' => []
        ];

        foreach ($this->rbls as $rbl) {
            $check = $domain . '.' . $rbl;
            if (checkdnsrr($check, 'A')) {
                $result['blacklisted'] = true;
                $result['status'] = 'listed';
                $result['details'][] = $rbl;
            }
        }
        
        return $result;
    }
}
?>
