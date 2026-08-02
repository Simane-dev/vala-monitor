<?php

class ValaScorer {
    private array $results;
    private int $score = 100;
    private array $penalties = [];

    /** 
     * Constructeur
     */
    public function __construct(array $results = []) {
        $this->results = $results;
    }

    /**
     * Calcul du score global de santé
     */
    public function calculate(): array {
        $this->score = 100;
        $this->penalties = [];

        $http      = $this->results['http']      ?? [];
        $ssl       = $this->results['ssl']       ?? [];
        $dns       = $this->results['dns']       ?? [];
        $blacklist = $this->results['blacklist'] ?? [];

        // 1. Analyse HTTP
        if (isset($http['status']) && $http['status'] === 'offline') {
            $this->score -= 50;
            $this->penalties[] = "Site web inaccessible (HTTP Offline).";
        } elseif (isset($http['response_time']) && $http['response_time'] > 1500) {
            $this->score -= 15;
            $this->penalties[] = "Temps de réponse très lent (> 1.5s).";
        }

        // 2. Analyse SSL
        if (isset($ssl['valid']) && !$ssl['valid']) {
            $this->score -= 30;
            $this->penalties[] = "Certificat SSL invalide ou expiré.";
        } elseif (isset($ssl['days_remaining']) && $ssl['days_remaining'] < 14) {
            $this->score -= 10;
            $this->penalties[] = "Certificat SSL expire dans moins de 14 jours.";
        }

        // 3. Analyse DNS
        if (isset($dns['has_a']) && !$dns['has_a']) {
            $this->score -= 20;
            $this->penalties[] = "Enregistrement DNS A manquant.";
        }

        // 4. Analyse Blacklist
        if (isset($blacklist['blacklisted']) && $blacklist['blacklisted']) {
            $this->score -= 40;
            $this->penalties[] = "Domaine présent sur au moins une liste noire (RBL).";
        }

        // Sécuriser le score entre 0 et 100
        $this->score = max(0, min(100, $this->score));

        // Détermination du statut
        $status = 'EXCELLENT';
        if ($this->score < 50) {
            $status = 'CRITICAL';
        } elseif ($this->score < 80) {
            $status = 'WARNING';
        }

        return [
            'score'     => $this->score,
            'status'    => $status,
            'penalties' => $this->penalties
        ];
    }

    /**
     * Méthode Statique d'analyse (Compatibilité directe avec index.php)
     */
    public static function analyze(array $http = [], array $ssl = [], array $dns = [], array $blacklist = []): array {
        $combinedResults = [
            'http'      => $http,
            'ssl'       => $ssl,
            'dns'       => $dns,
            'blacklist' => $blacklist
        ];

        $scorer = new self($combinedResults);
        return $scorer->calculate();
    }
}