<?php
declare(strict_types=1);

namespace Orixo\Middleware;

/**
 * Validates synchronizer CSRF token on state-changing requests.
 */
final class CsrfMiddleware
{
    public function handle(array $params = []): void
    {
        $sessionToken = (string) ($_SESSION['_csrf_token'] ?? '');
        $headerToken = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        $postToken = (string) ($_POST['_csrf_token'] ?? '');

        $provided = $headerToken !== '' ? $headerToken : $postToken;

        if ($provided === '' && str_contains((string) ($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json')) {
            $body = request_json();
            $provided = (string) ($body['_csrf_token'] ?? '');
        }

        if ($sessionToken === '' || $provided === '' || !hash_equals($sessionToken, $provided)) {
            if (str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/')) {
                json_response(false, 'Security token expired or invalid. Please refresh the page.', null, [], 419);
            }
            http_response_code(419);
            exit('Security token expired or invalid.');
        }
    }
}
