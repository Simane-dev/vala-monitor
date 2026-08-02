<?php

abstract class BaseChecker {
    protected string $domain;
    protected int $timeout;

    public function __construct(string $domain, int $timeout = 10) {
        $this->domain = trim($domain);
        $this->timeout = $timeout;
    }

    /**
     * Exécute le diagnostic et retourne un tableau de résultats
     */
    abstract public function check(): array;

    /**
     * Retourne le nom du module de vérification
     */
    abstract public function getName(): string;
}