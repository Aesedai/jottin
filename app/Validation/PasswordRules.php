<?php

namespace App\Validation;

class PasswordRules
{
    /**
     * bcrypt only uses the first 72 bytes, so longer passwords are refused rather
     * than silently cut. Bytes, not characters: multibyte text fills up sooner.
     */
    public function fitsBcrypt(?string $value): bool
    {
        return strlen((string) $value) <= 72;
    }
}
