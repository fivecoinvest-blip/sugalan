<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Contracts\Encryption\DecryptException;

class EncryptionService
{
    /**
     * Encrypt sensitive data
     */
    public function encrypt(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Crypt::encryptString($value);
        } catch (\Exception $e) {
            \Log::error('Encryption failed', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Decrypt sensitive data
     */
    public function decrypt(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException $e) {
            \Log::error('Decryption failed', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Encrypt phone number
     */
    public function encryptPhone(?string $phone): ?string
    {
        return $this->encrypt($phone);
    }

    /**
     * Decrypt phone number
     */
    public function decryptPhone(?string $encryptedPhone): ?string
    {
        return $this->decrypt($encryptedPhone);
    }

    /**
     * Encrypt email address
     */
    public function encryptEmail(?string $email): ?string
    {
        return $this->encrypt($email);
    }

    /**
     * Decrypt email address
     */
    public function decryptEmail(?string $encryptedEmail): ?string
    {
        return $this->decrypt($encryptedEmail);
    }

    /**
     * Encrypt IP address
     */
    public function encryptIp(?string $ip): ?string
    {
        return $this->encrypt($ip);
    }

    /**
     * Decrypt IP address
     */
    public function decryptIp(?string $encryptedIp): ?string
    {
        return $this->decrypt($encryptedIp);
    }

    /**
     * Mask sensitive data for display
     */
    public function mask(string $value, int $visibleChars = 4, string $maskChar = '*'): string
    {
        $length = strlen($value);
        
        if ($length <= $visibleChars) {
            return str_repeat($maskChar, $length);
        }

        $masked = str_repeat($maskChar, $length - $visibleChars);
        return $masked . substr($value, -$visibleChars);
    }

    /**
     * Mask phone number (show last 4 digits)
     */
    public function maskPhone(string $phone): string
    {
        return $this->mask($phone, 4);
    }

    /**
     * Mask email (show first char and domain)
     */
    public function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        
        if (count($parts) !== 2) {
            return $this->mask($email, 3);
        }

        $local = $parts[0];
        $domain = $parts[1];

        $maskedLocal = substr($local, 0, 1) . str_repeat('*', max(0, strlen($local) - 1));
        
        return $maskedLocal . '@' . $domain;
    }

    /**
     * Mask card number (show last 4 digits)
     */
    public function maskCardNumber(string $cardNumber): string
    {
        return $this->mask($cardNumber, 4);
    }

    /**
     * Hash sensitive data (one-way)
     */
    public function hash(string $value): string
    {
        return hash('sha256', $value);
    }

    /**
     * Verify hashed value
     */
    public function verifyHash(string $value, string $hash): bool
    {
        return hash('sha256', $value) === $hash;
    }
}
