<?php
class ValaScorer {
    private array $results;
    private int $baseScore = 100;
    private array $penalties = [];
    private array $weights = [
        'http'      => 0.40, // 40% من النقطة
        'ssl'       => 0.30, // 30% من النقطة
        'blacklist' => 0.20, // 20% من النقطة
        'dns'       => 0.10  // 10% من النقطة
    ];

    public function __construct(array $results) {
        $this->results = $results;
    }

    public function calculate(): array {
        $score = $this->baseScore;

        // 1. تقييم الـ HTTP
        foreach ($this->results as $checkerName => $data) {
            if (strpos($checkerName, 'Http') !== false) {
                if (isset($data['status_code']) && $data['status_code'] !== 200) {
                    $score -= (40 * $this->weights['http']);
                    $this->penalties[] = "Code HTTP non valide (" . $data['status_code'] . ")";
                }
            }
            
            // 2. تقييم الـ SSL
            if (strpos($checkerName, 'Ssl') !== false) {
                if (isset($data['valid']) && !$data['valid']) {
                    $score -= (100 * $this->weights['ssl']);
                    $this->penalties[] = "Certificat SSL invalide ou expiré";
                }
            }

            // 3. تقييم Blacklist
            if (strpos($checkerName, 'Blacklist') !== false) {
                if (isset($data['blacklisted']) && $data['blacklisted']) {
                    $score -= (100 * $this->weights['blacklist']);
                    $this->penalties[] = "Présence sur liste noire (RBL)";
                }
            }
        }

        $finalScore = (int) max(0, round($score));

        return [
            'score'     => $finalScore,
            'status'    => $finalScore >= 80 ? 'EXCELLENT' : ($finalScore >= 50 ? 'MOYEN' : 'CRITIQUE'),
            'penalties' => $this->penalties,
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }
}
?>