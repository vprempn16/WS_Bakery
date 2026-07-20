<?php

namespace App\Modules\Api\V1\User\Support;

/**
 * Shared password strength rules matching FE FieldValidationHandler:
 * min 8 chars, at least one uppercase letter, at least one number.
 */
class PasswordRules
{
    /** @return list<string> */
    public static function strengthRules(bool $required = true): array
    {
        $rules = ['string', 'min:8', 'regex:/^(?=.*[A-Z])(?=.*[0-9]).+$/'];

        array_unshift($rules, $required ? 'required' : 'nullable');

        return $rules;
    }

    /** @return array<string, string> */
    public static function messages(string $passwordKey = 'data.values.password'): array
    {
        return [
            "{$passwordKey}.required" => 'Password is required.',
            "{$passwordKey}.min" => 'Password must be at least 8 characters long.',
            "{$passwordKey}.regex" => 'Password must include at least one uppercase letter and one number.',
        ];
    }

    public static function isStrong(string $password): bool
    {
        return strlen($password) >= 8
            && preg_match('/[A-Z]/', $password)
            && preg_match('/[0-9]/', $password);
    }

    public static function strengthErrorMessage(string $password): ?string
    {
        if ($password === '') {
            return 'This field is required.';
        }
        if (strlen($password) < 8) {
            return 'Password must be at least 8 characters long.';
        }
        if (! preg_match('/[A-Z]/', $password) || ! preg_match('/[0-9]/', $password)) {
            return 'Password must include at least one uppercase letter and one number.';
        }

        return null;
    }
}
