<?php
declare(strict_types=1);

/**
 * Orixo MVP Automated Verification & Integration Test Suite
 * Run after configuring .env, running composer install, and executing `php bin/migrate.php --seed`.
 * Usage: php tests/smoke_and_security_tests.php
 */

require_once dirname(__DIR__) . '/app/Bootstrap/App.php';

use Orixo\Bootstrap\App;
use Orixo\Bootstrap\Database;
use Orixo\Services\AuthService;
use Orixo\Services\InquiryService;
use Orixo\Services\InvoiceService;
use Orixo\Services\ProgressService;

App::bootCli();
$pdo = Database::connection();

$passed = 0;
$failed = 0;

function assertTrue(bool $cond, string $label): void
{
    global $passed, $failed;
    if ($cond) {
        $passed++;
        echo "  [PASS] {$label}\n";
    } else {
        $failed++;
        echo "  [FAIL] {$label}\n";
    }
}

echo "=== Orixo Automated Verification Suite ===\n";

// 1. Verify Indian INR currency formatting & multi-currency preservation
assertTrue(format_currency(150000, 'INR') === '₹1,50,000.00', 'INR Indian numbering format (₹1,50,000.00)');
assertTrue(format_currency(150000, 'USD') === '$150,000.00', 'USD international numbering format ($150,000.00)');

// 2. Verify no public self-registration route exists in public/index.php
$indexSrc = file_get_contents(App::rootPath() . '/public/index.php') ?: '';
assertTrue(!str_contains($indexSrc, "'/register'"), 'Public self-registration route is disabled');

// 3. Verify inquiry submission & staff email conflict protection
$adminEmail = (string) $pdo->query("SELECT u.email FROM users u INNER JOIN roles r ON r.id = u.role_id WHERE r.slug = 'admin' LIMIT 1")->fetchColumn();
$adminId = (int) $pdo->query("SELECT u.id FROM users u INNER JOIN roles r ON r.id = u.role_id WHERE r.slug = 'admin' LIMIT 1")->fetchColumn();

$inq = InquiryService::submit([
    'inquiry_type' => 'project_inquiry',
    'full_name' => 'QA Test Contact',
    'email' => $adminEmail,
    'project_title' => 'Staff Conflict Test',
    'message_or_description' => 'Automated QA check for staff email conflict.',
]);
assertTrue(str_starts_with($inq['reference_code'], 'INQ-'), 'Inquiry submission generates INQ reference code');

$conflict = InquiryService::convertToClientAndProject($inq['id'], $adminId, []);
assertTrue($conflict['ok'] === false && $conflict['code'] === 409, 'Admin email conflict rejected with HTTP 409 on conversion');
$pdo->prepare("DELETE FROM inquiries WHERE id = ?")->execute([$inq['id']]);

// 4. Verify ProgressService calculation (excludes archived tasks)
$proj = $pdo->query("SELECT id FROM projects WHERE project_code = 'PRJ-2026-0001' LIMIT 1")->fetch();
if ($proj) {
    $res = ProgressService::recalculate((int) $proj['id']);
    assertTrue($res['progress_percentage'] === 40, 'Automatic progress calculates ROUND(2/5 * 100) = 40%');
}

// 5. Verify Invoice project-client ownership guard
$clientId = (int) $pdo->query("SELECT id FROM clients LIMIT 1")->fetchColumn();
$badInv = InvoiceService::createDraft($adminId, [
    'client_id' => $clientId + 99999,
    'project_id' => (int) ($proj['id'] ?? 1),
    'items' => [['description' => 'Invalid mismatch test', 'quantity' => 1, 'unit_price' => 1000]],
]);
assertTrue($badInv['ok'] === false && $badInv['code'] === 422, 'Invoice creation rejects project belonging to another client');

// 6. Verify Password Policy & Session Invalidation token_version bump
$beforeVer = (int) $pdo->query("SELECT token_version FROM users WHERE id = {$adminId}")->fetchColumn();
AuthService::invalidateSessions($adminId);
$afterVer = (int) $pdo->query("SELECT token_version FROM users WHERE id = {$adminId}")->fetchColumn();
assertTrue($afterVer === $beforeVer + 1, 'Session invalidation increments users.token_version');

echo "==========================================\n";
echo "Passed: {$passed}, Failed: {$failed}\n";
exit($failed > 0 ? 1 : 0);
