<?php
declare(strict_types=1);

/**
 * CLI Database Migration & Seed Runner
 * Usage:
 *   php bin/migrate.php
 *   php bin/migrate.php --seed
 */

require_once dirname(__DIR__) . '/app/Bootstrap/App.php';

use Orixo\Bootstrap\App;
use Orixo\Bootstrap\Database;

App::bootCli();

$pdo = Database::connection();

echo "[Orixo] Checking schema_migrations table...\n";
$pdo->exec("
    CREATE TABLE IF NOT EXISTS schema_migrations (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        migration VARCHAR(180) NOT NULL UNIQUE,
        applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$applied = $pdo->query("SELECT migration FROM schema_migrations")->fetchAll(\PDO::FETCH_COLUMN);
$appliedSet = array_flip($applied);

$migrationFiles = glob(dirname(__DIR__) . '/database/migrations/*.sql');
sort($migrationFiles);

foreach ($migrationFiles as $file) {
    $name = basename($file);
    if (isset($appliedSet[$name])) {
        echo "  [skip] {$name}\n";
        continue;
    }

    echo "  [apply] {$name}...\n";
    $sql = file_get_contents($file);
    if ($sql === false) {
        throw new RuntimeException("Unable to read migration {$name}");
    }

    // Strip full-line SQL comments before splitting statements on semicolons
    $cleanSql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
    $statements = array_filter(array_map('trim', preg_split('/;\s*(\r?\n|$)/', $cleanSql)));
    foreach ($statements as $stmt) {
        if ($stmt === '') {
            continue;
        }
        $pdo->exec($stmt);
    }

    $ins = $pdo->prepare("INSERT INTO schema_migrations (migration) VALUES (:name)");
    $ins->execute(['name' => $name]);
    echo "  [done] {$name}\n";
}

if (in_array('--seed', $argv, true)) {
    echo "[Orixo] Running database seed...\n";
    require_once dirname(__DIR__) . '/database/seeds/SeedRunner.php';
    \Orixo\Database\Seeds\SeedRunner::run($pdo);
    echo "[Orixo] Seed completed successfully.\n";
}

echo "[Orixo] Migration process finished.\n";
