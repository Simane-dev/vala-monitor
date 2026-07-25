<?php
/**
 * Classe abstraite de base - Fondation POO Pro
 * Tous les checkers doivent hériter de cette classe
 */

abstract class BaseChecker {
    protected string $domain;
    protected string $originalDomain;
    protected float $startTime;
    protected array $logs = [];
    protected int $timeout = 5;

    public function __construct(string $domain) {
        $this->originalDomain = $domain;
        $this->domain = $this->sanitizeDomain($domain);
        $this->startTime = microtime(true);
    }

    // Nettoie le domaine entré par l'utilisateur
    protected function sanitizeDomain(string $domain): string {
        $domain = strtolower(trim($domain));
        $domain = str_replace(['https://', 'http://', 'www.', '/'], '', $domain);
        $domain = explode('/', $domain)[0];
        $domain = explode('?', $domain)[0];
        $domain = explode(':', $domain)[0];
        // Validation basique
        if (!filter_var('http://'. $domain, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException("Domaine invalide: $domain");
        }
        return $domain;
    }

    protected function log(string $message): void {
        $this->logs[] = "[". date('H:i:s'). "] $message";
    }

    protected function getElapsedTime(): int {
        return (int) round((microtime(true) - $this->startTime) * 1000);
    }

    // Méthode abstraite que chaque enfant doit implémenter
    abstract public function check(): array;

    // Méthode commune pour valider le résultat
    protected function validateResult(array $result): array {
        if (!isset($result['ok']) &&!isset($result['valid'])) {
            $result['ok'] = false;
        }
        $result['checker'] = static::class;
        $result['domain'] = $this->domain;
        $result['execution_time'] = $this->getElapsedTime();
        $result['logs'] = $this->logs;
        return $result;
    }

    public function getDomain(): string { return $this->domain; }
    public function getOriginalDomain(): string { return $this->originalDomain; }
}
?>