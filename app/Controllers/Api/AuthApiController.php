<?php
declare(strict_types=1);

namespace Orixo\Controllers\Api;

use Orixo\Bootstrap\App;
use Orixo\Helpers\Validator;
use Orixo\Middleware\RateLimitMiddleware;
use Orixo\Services\AuthService;

/**
 * Versioned authentication JSON API controller (/api/v1/auth/*).
 */
final class AuthApiController
{
    public function login(): void
    {
        $data = request_json();
        $v = new Validator();
        if (!$v->validate($data, [
            'email' => 'required|email',
            'password' => 'required|min:6',
        ])) {
            json_response(false, 'Please provide a valid email and password.', null, [], 422, $v->errors());
        }

        $res = AuthService::attemptLogin((string) $data['email'], (string) $data['password']);
        if (!$res['ok']) {
            json_response(false, $res['message'], null, [], $res['code']);
        }

        json_response(true, 'Authenticated successfully.', [
            'redirect' => $res['redirect'],
            'role' => $res['role'],
        ]);
    }

    public function logout(): void
    {
        App::destroySession();
        json_response(true, 'Logged out successfully.', ['redirect' => '/login']);
    }

    public function activate(): void
    {
        $data = request_json();
        $v = new Validator();
        if (!$v->validate($data, [
            'token' => 'required|min:16',
            'password' => 'required|strong_password',
            'password_confirmation' => 'required',
        ])) {
            json_response(false, 'Validation failed.', null, [], 422, $v->errors());
        }

        if (($data['password'] ?? '') !== ($data['password_confirmation'] ?? '')) {
            json_response(false, 'Password confirmation does not match.', null, [], 422, ['password_confirmation' => 'Passwords do not match.']);
        }

        $res = AuthService::activateAccount((string) $data['token'], (string) $data['password']);
        if (!$res['ok']) {
            json_response(false, $res['message'], null, [], $res['code']);
        }

        json_response(true, $res['message'], ['redirect' => '/login']);
    }

    public function forgotPassword(): void
    {
        RateLimitMiddleware::checkPublicAction('forgot_password', 5, 600);
        $data = request_json();
        $v = new Validator();
        if (!$v->validate($data, ['email' => 'required|email'])) {
            json_response(false, 'Please enter a valid email address.', null, [], 422, $v->errors());
        }

        AuthService::requestPasswordReset((string) $data['email']);
        json_response(true, 'If an active account matches that email, password reset instructions have been sent.');
    }

    public function resetPassword(): void
    {
        $data = request_json();
        $v = new Validator();
        if (!$v->validate($data, [
            'token' => 'required|min:16',
            'password' => 'required|strong_password',
            'password_confirmation' => 'required',
        ])) {
            json_response(false, 'Validation failed.', null, [], 422, $v->errors());
        }

        if (($data['password'] ?? '') !== ($data['password_confirmation'] ?? '')) {
            json_response(false, 'Password confirmation does not match.', null, [], 422, ['password_confirmation' => 'Passwords do not match.']);
        }

        $res = AuthService::resetPassword((string) $data['token'], (string) $data['password']);
        if (!$res['ok']) {
            json_response(false, $res['message'], null, [], $res['code']);
        }

        json_response(true, $res['message'], ['redirect' => '/login']);
    }

    public function me(): void
    {
        $user = current_user();
        json_response(true, 'Current user context.', [
            'id' => (int) $user['id'],
            'full_name' => $user['full_name'],
            'email' => $user['email'],
            'role' => $user['role_slug'],
            'csrf_token' => csrf_token(),
        ]);
    }
}
