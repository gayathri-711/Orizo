<?php
declare(strict_types=1);

namespace Orixo\Helpers;

/**
 * Fluent server-side input validator.
 */
final class Validator
{
    private array $errors = [];

    public function validate(array $data, array $rules): bool
    {
        $this->errors = [];

        foreach ($rules as $field => $ruleList) {
            $value = $data[$field] ?? null;
            $strVal = is_string($value) ? trim($value) : $value;
            $checks = is_string($ruleList) ? explode('|', $ruleList) : $ruleList;

            $isNullable = in_array('nullable', $checks, true);
            if ($isNullable && ($strVal === null || $strVal === '')) {
                continue;
            }

            foreach ($checks as $rule) {
                if ($rule === 'nullable') {
                    continue;
                }
                [$name, $param] = array_pad(explode(':', $rule, 2), 2, null);

                match ($name) {
                    'required' => ($strVal === null || $strVal === '' || (is_array($strVal) && count($strVal) === 0))
                        ? $this->addError($field, ucfirst(str_replace('_', ' ', $field)) . ' is required.') : null,
                    'email' => (!filter_var((string) $strVal, FILTER_VALIDATE_EMAIL))
                        ? $this->addError($field, 'Please enter a valid email address.') : null,
                    'min' => (mb_strlen((string) $strVal) < (int) $param)
                        ? $this->addError($field, ucfirst(str_replace('_', ' ', $field)) . " must be at least {$param} characters.") : null,
                    'max' => (mb_strlen((string) $strVal) > (int) $param)
                        ? $this->addError($field, ucfirst(str_replace('_', ' ', $field)) . " must not exceed {$param} characters.") : null,
                    'numeric' => (!is_numeric($strVal))
                        ? $this->addError($field, ucfirst(str_replace('_', ' ', $field)) . ' must be a valid number.') : null,
                    'positive' => (!is_numeric($strVal) || (float) $strVal <= 0)
                        ? $this->addError($field, ucfirst(str_replace('_', ' ', $field)) . ' must be greater than zero.') : null,
                    'in' => (!in_array((string) $strVal, explode(',', (string) $param), true))
                        ? $this->addError($field, 'Invalid selection for ' . str_replace('_', ' ', $field) . '.') : null,
                    'date' => (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $strVal) || !strtotime((string) $strVal))
                        ? $this->addError($field, 'Please provide a valid date (YYYY-MM-DD).') : null,
                    'url' => (!filter_var((string) $strVal, FILTER_VALIDATE_URL))
                        ? $this->addError($field, 'Please provide a valid URL.') : null,
                    'strong_password' => (!self::isStrongPassword((string) $strVal))
                        ? $this->addError($field, 'Password must be at least 10 characters and include uppercase, lowercase, number, and symbol.') : null,
                    default => null,
                };
            }
        }

        return empty($this->errors);
    }

    public static function isStrongPassword(string $password): bool
    {
        return strlen($password) >= 10
            && preg_match('/[A-Z]/', $password)
            && preg_match('/[a-z]/', $password)
            && preg_match('/[0-9]/', $password)
            && preg_match('/[^A-Za-z0-9]/', $password);
    }

    private function addError(string $field, string $message): void
    {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = $message;
        }
    }

    public function errors(): array
    {
        return $this->errors;
    }
}
