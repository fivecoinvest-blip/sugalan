<?php

namespace App\Services;

class SlotEncryptionService
{
    /**
     * Encrypt data using AES-256-ECB
     *
     * @param array $data Data to encrypt
     * @param string $key AES key
     * @return string Base64 encoded encrypted data
     */
    public function encrypt(array $data, string $key): string
    {
        $jsonData = json_encode($data);
        
        $encrypted = openssl_encrypt(
            $jsonData,
            'AES-256-ECB',
            $key,
            OPENSSL_RAW_DATA
        );
        
        return base64_encode($encrypted);
    }

    /**
     * Decrypt data using AES-256-ECB
     *
     * @param string $encryptedData Base64 encoded encrypted data
     * @param string $key AES key
     * @return array Decrypted data
     */
    public function decrypt(string $encryptedData, string $key): array
    {
        $encrypted = base64_decode($encryptedData);
        
        $decrypted = openssl_decrypt(
            $encrypted,
            'AES-256-ECB',
            $key,
            OPENSSL_RAW_DATA
        );
        
        if ($decrypted === false) {
            throw new \Exception('Decryption failed');
        }
        
        return json_decode($decrypted, true);
    }

    /**
     * Generate HMAC signature for callback authentication
     *
     * @param string $data Data to sign
     * @param string $key Secret key
     * @param int|null $timestamp Optional timestamp
     * @return string HMAC signature
     */
    public function generateSignature(string $data, string $key, ?int $timestamp = null): string
    {
        $message = $data;
        
        if ($timestamp !== null) {
            $message .= $timestamp;
        }
        
        return hash_hmac('sha256', $message, $key);
    }

    /**
     * Verify HMAC signature
     *
     * @param string $data Data that was signed
     * @param string $signature Signature to verify
     * @param string $key Secret key
     * @param int|null $timestamp Optional timestamp
     * @return bool True if signature is valid
     */
    public function verifySignature(string $data, string $signature, string $key, ?int $timestamp = null): bool
    {
        $expectedSignature = $this->generateSignature($data, $key, $timestamp);
        
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Generate a secure random token
     *
     * @param int $length Token length
     * @return string Random token
     */
    public function generateToken(int $length = 64): string
    {
        return bin2hex(random_bytes($length / 2));
    }

    /**
     * Validate timestamp is within acceptable window (5 minutes)
     *
     * @param int $timestamp Timestamp to validate
     * @param int $windowSeconds Acceptable time window in seconds
     * @return bool True if timestamp is valid
     */
    public function validateTimestamp(int $timestamp, int $windowSeconds = 300): bool
    {
        $now = time();
        $diff = abs($now - $timestamp);
        
        return $diff <= $windowSeconds;
    }
}
