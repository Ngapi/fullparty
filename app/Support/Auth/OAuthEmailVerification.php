<?php

namespace App\Support\Auth;

final class OAuthEmailVerification
{
    public static function isVerified(object $providerUser, string $provider): bool
    {
        if (! self::hasEmail($providerUser)) {
            return false;
        }

        $raw = self::rawAttributes($providerUser);

        return match ($provider) {
            'discord' => self::isTruthy(data_get($raw, 'verified')),
            'google' => self::isTruthy(data_get($raw, 'email_verified'))
                || self::isTruthy(data_get($raw, 'verified_email')),
            'xivauth' => self::isTruthy($providerUser->email_verified ?? null)
                || self::isTruthy(data_get($raw, 'user.email_verified'))
                || self::isTruthy(data_get($raw, 'email_verified')),
            default => false,
        };
    }

    private static function hasEmail(object $providerUser): bool
    {
        if (! method_exists($providerUser, 'getEmail')) {
            return false;
        }

        $email = $providerUser->getEmail();

        return is_string($email) && trim($email) !== '';
    }

    /**
     * @return array<string, mixed>
     */
    private static function rawAttributes(object $providerUser): array
    {
        if (method_exists($providerUser, 'getRaw')) {
            $raw = $providerUser->getRaw();

            return is_array($raw) ? $raw : [];
        }

        return [];
    }

    private static function isTruthy(mixed $value): bool
    {
        return $value === true
            || $value === 1
            || $value === '1'
            || $value === 'true';
    }
}
