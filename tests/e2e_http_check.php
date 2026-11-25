<?php
declare(strict_types=1);

$base = 'http://127.0.0.1';
$cookieFile = sys_get_temp_dir() . '/orixo_e2e_cookies.txt';

function http_call(string $url, string $method = 'GET', ?array $body = null, ?string $csrf = null, string $cookieFile = ''): array
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    $headers = [];
    if ($csrf !== null) {
        $headers[] = 'X-CSRF-Token: ' . $csrf;
    }
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        $headers[] = 'Content-Type: application/json';
    }
    if ($headers) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    $res = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$code, $res];
}

function get_csrf(string $base, string $cookieFile): string
{
    @unlink($cookieFile);
    [, $html] = http_call($base . '/login', 'GET', null, null, $cookieFile);
    preg_match('/name="csrf-token" content="([^"]+)"/', $html, $m);
    return $m[1] ?? '';
}

$passed = 0;
$failed = 0;
$check = static function (bool $cond, string $label) use (&$passed, &$failed): void {
    if ($cond) {
        $passed++;
        echo "  [PASS] {$label}\n";
    } else {
        $failed++;
        echo "  [FAIL] {$label}\n";
    }
};

echo "=== Orixo Live HTTP & RBAC E2E Check ===\n";

// 1. Client flow
$csrf = get_csrf($base, $cookieFile);
[$cLogin, $rLogin] = http_call($base . '/api/v1/auth/login', 'POST', ['email' => 'rohan@nexafin.in', 'password' => 'OrixoClient@2026!'], $csrf, $cookieFile);
$check($cLogin === 200 && (json_decode($rLogin, true)['data']['redirect'] ?? '') === '/portal', 'Client login succeeds & redirects to /portal');

[$cDash, $rDash] = http_call($base . '/api/v1/client/dashboard', 'GET', null, null, $cookieFile);
$check($cDash === 200 && (json_decode($rDash, true)['success'] ?? false) === true, 'Client dashboard API returns 200');

[$cDenyAdm,] = http_call($base . '/api/v1/admin/dashboard', 'GET', null, null, $cookieFile);
$check($cDenyAdm === 403, 'Client blocked from Admin API (403 Forbidden)');

[$cPdf, $pdfBytes] = http_call($base . '/api/v1/client/invoices/1/pdf', 'GET', null, null, $cookieFile);
$check($cPdf === 200 && str_starts_with($pdfBytes, '%PDF'), 'Client downloads Dompdf-generated INR Invoice PDF');

// 2. Team Member flow
$csrf = get_csrf($base, $cookieFile);
[$tLogin, $rtLogin] = http_call($base . '/api/v1/auth/login', 'POST', ['email' => 'arjun@orixo.studio', 'password' => 'OrixoTeam@2026!'], $csrf, $cookieFile);
$check($tLogin === 200 && (json_decode($rtLogin, true)['data']['redirect'] ?? '') === '/admin/workspace', 'Team Member login redirects to /admin/workspace');

[$wDash, $rwDash] = http_call($base . '/api/v1/workspace/dashboard', 'GET', null, null, $cookieFile);
$check($wDash === 200 && (json_decode($rwDash, true)['success'] ?? false) === true, 'Team workspace dashboard API returns 200');

[$tDenyAdm,] = http_call($base . '/api/v1/admin/dashboard', 'GET', null, null, $cookieFile);
$check($tDenyAdm === 403, 'Team Member blocked from Admin API (403 Forbidden)');

[$tDenyPub,] = http_call($base . '/api/v1/admin/deliverables/1/publish', 'PATCH', [], $csrf, $cookieFile);
$check($tDenyPub === 403, 'Team Member blocked from publishing deliverables (403 Forbidden)');

// 3. Admin flow
$csrf = get_csrf($base, $cookieFile);
[$aLogin, $raLogin] = http_call($base . '/api/v1/auth/login', 'POST', ['email' => 'admin@orixo.studio', 'password' => 'OrixoAdmin@2026!'], $csrf, $cookieFile);
$check($aLogin === 200 && (json_decode($raLogin, true)['data']['redirect'] ?? '') === '/admin', 'Admin login redirects to /admin');

[$aDash, $raDash] = http_call($base . '/api/v1/admin/dashboard', 'GET', null, null, $cookieFile);
$check($aDash === 200 && (json_decode($raDash, true)['success'] ?? false) === true, 'Admin dashboard API returns 200');

@unlink($cookieFile);
echo "========================================\n";
echo "Passed: {$passed}, Failed: {$failed}\n";
exit($failed > 0 ? 1 : 0);
