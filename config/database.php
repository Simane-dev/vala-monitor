<?php
/**
 * VALA Monitor - Database Singleton Pro
 * Gère la connexion PDO unique, le.env, et les logs
 */

class Database {
    private static?PDO $pdo = null;
    private static array $config = [];
    private static int $queryCount = 0;

    // Charge la config depuis.env si existe
    private static function loadConfig(): void {
        if (!empty(self::$config)) return;

        // Config par défaut
        self::$config = [
            'host' => 'localhost',
            'dbname' => 'vala_monitor',
            'user' => 'root',
            'pass' => '',
            'charset' => 'utf8mb4'
        ];

        // Essaie de lire.env
        $envFile = __DIR__. '/../.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (str_starts_with(trim($line), '#')) continue;
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key); $value = trim($value);
                if ($key === 'DB_HOST') self::$config['host'] = $value;
                if ($key === 'DB_NAME') self::$config['dbname'] = $value;
                if ($key === 'DB_USER') self::$config['user'] = $value;
                if ($key === 'DB_PASS') self::$config['pass'] = $value;
            }
        }
    }

    public static function connect(): PDO {
        if (self::$pdo!== null) {
            return self::$pdo;
        }

        self::loadConfig();
        $c = self::$config;

        $dsn = "mysql:host={$c['host']};dbname={$c['dbname']};charset={$c['charset']}";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_PERSISTENT => false,
        ];

        try {
            self::$pdo = new PDO($dsn, $c['user'], $c['pass'], $options);
            // Optimisations MySQL
            self::$pdo->exec("SET time_zone = '+01:00'");
            self::$pdo->exec("SET NAMES utf8mb4");
            return self::$pdo;
        } catch (PDOException $e) {
            error_log("DB Error: ". $e->getMessage());
            die("<div style='background:#ef4444;color:white;padding:20px;border-radius:10px;font-family:monospace'>
                <h3>❌ Erreur Base de Données</h3>
                <p>{$e->getMessage()}</p>
                <p>Vérifiez que MySQL est démarré et que la base vala_monitor existe.</p>
                <p>Importez database/schema.sql dans phpMyAdmin</p>
                </div>");
        }
    }

    public static function query(string $sql, array $params = []): array {
        self::$queryCount++;
        $stmt = self::connect()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function getStats(): array {
        return ['queries' => self::$queryCount, 'connected' => self::$pdo!== null];
    }
}

// Connexion globale pour compatibilité
$pdo = Database::connect();
?>