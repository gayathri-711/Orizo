<?php
declare(strict_types=1);

namespace Orixo\Bootstrap;

use PDO;
use PDOException;

/**
 * PDO database connection manager with strict prepared statements and transaction support.
 */
final class Database
{
    private static ?PDO $instance = null;

    public static function connection(): PDO
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $cfg = App::config('database');
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $cfg['host'],
            $cfg['port'],
            $cfg['database'],
            $cfg['charset']
        );

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        $sslCa = trim((string) ($cfg['ssl_ca'] ?? ''));
        if ($sslCa === 'system') {
            $sslCa = '/etc/ssl/certs/ca-certificates.crt';
        }
        if ($sslCa !== '') {
            $options[PDO::MYSQL_ATTR_SSL_CA] = $sslCa;
            if (defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')) {
                $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = (bool) ($cfg['ssl_verify'] ?? true);
            }
        }

        try {
            self::$instance = new PDO($dsn, $cfg['username'], $cfg['password'], $options);
        } catch (PDOException $e) {
            App::logError('Database connection failed: ' . $e->getMessage(), 'app');
            throw new \RuntimeException('Database connection error.');
        }

        return self::$instance;
    }

    /**
     * Execute a callback inside an ACID transaction.
     */
    public static function transaction(callable $callback): mixed
    {
        $pdo = self::connection();
        $started = !$pdo->inTransaction();
        if ($started) {
            $pdo->beginTransaction();
        }
        try {
            $result = $callback($pdo);
            if ($started && $pdo->inTransaction()) {
                $pdo->commit();
            }
            return $result;
        } catch (\Throwable $e) {
            if ($started && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
