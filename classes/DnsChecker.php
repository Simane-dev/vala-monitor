<?php

class DnsChecker {
    private $domain;

    public function __construct($domain) {
        $clean = preg_replace('/^https?:\/\//i', '', $domain);
        $this->domain = explode('/', $clean)[0];
    }

    public function check(): array {
        if (empty($this->domain)) {
            return ['resolved' => false, 'mx_valid' => false];
        }

        // 1. التحقق من وجود IP (A or AAAA Records)
        $hasIp = checkdnsrr($this->domain, "A") || checkdnsrr($this->domain, "AAAA");

        // 2. التحقق من الـ MX Records بـ الطريقة العادية
        $hasMx = checkdnsrr($this->domain, "MX");

        // 3. إذا فشل النظام المحلي فـ إيجاد MX (مثلاً مع google.com فـ XAMPP)، نستخدم Google DNS عبر UDP
        if (!$hasMx && function_exists('dns_get_record')) {
            $records = @dns_get_record($this->domain, DNS_MX);
            if (!empty($records)) {
                $hasMx = true;
            }
        }

        // 4. حيل إضافية لضمان الدومينات الكبرى (Fallback Check)
        if (!$hasMx) {
            $hasMx = $this->queryGoogleDnsMx($this->domain);
        }

        return [
            'resolved' => $hasIp,
            'mx_valid' => $hasMx
        ];
    }

    /**
     * الاستعلام المباشر عبر Google DNS API للقطع مع مشاكل DNS المحلي فـ Windows
     */
    private function queryGoogleDnsMx($domain): bool {
        $url = "https://dns.google/resolve?name=" . urlencode($domain) . "&type=MX";
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 3,
            CURLOPT_SSL_VERIFYPEER => false
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        if ($response) {
            $data = json_decode($response, true);
            if (isset($data['Answer']) && count($data['Answer']) > 0) {
                return true;
            }
        }

        return false;
    }
}