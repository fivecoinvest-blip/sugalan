<?php

namespace Tests\Unit;

use App\Services\EncryptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EncryptionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected EncryptionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EncryptionService::class);
    }

    /** @test */
    public function it_encrypts_and_decrypts_generic_values()
    {
        $value = 'sensitive data';
        
        $encrypted = $this->service->encrypt($value);
        $this->assertNotEquals($value, $encrypted);
        
        $decrypted = $this->service->decrypt($encrypted);
        $this->assertEquals($value, $decrypted);
    }

    /** @test */
    public function it_encrypts_and_decrypts_phone_numbers()
    {
        $phone = '+639171234567';
        
        $encrypted = $this->service->encryptPhone($phone);
        $this->assertNotEquals($phone, $encrypted);
        
        $decrypted = $this->service->decryptPhone($encrypted);
        $this->assertEquals($phone, $decrypted);
    }

    /** @test */
    public function it_encrypts_and_decrypts_emails()
    {
        $email = 'user@example.com';
        
        $encrypted = $this->service->encryptEmail($email);
        $this->assertNotEquals($email, $encrypted);
        
        $decrypted = $this->service->decryptEmail($encrypted);
        $this->assertEquals($email, $decrypted);
    }

    /** @test */
    public function it_encrypts_and_decrypts_ip_addresses()
    {
        $ip = '192.168.1.100';
        
        $encrypted = $this->service->encryptIp($ip);
        $this->assertNotEquals($ip, $encrypted);
        
        $decrypted = $this->service->decryptIp($encrypted);
        $this->assertEquals($ip, $decrypted);
    }

    /** @test */
    public function it_handles_null_values()
    {
        $this->assertNull($this->service->encrypt(null));
        $this->assertNull($this->service->decrypt(null));
        $this->assertNull($this->service->encryptPhone(null));
        $this->assertNull($this->service->decryptPhone(null));
    }

    /** @test */
    public function it_handles_empty_strings()
    {
        $this->assertNull($this->service->encrypt(''));
        $this->assertNull($this->service->encryptPhone(''));
        $this->assertNull($this->service->encryptEmail(''));
    }

    /** @test */
    public function it_masks_generic_values()
    {
        $value = '1234567890';
        $masked = $this->service->mask($value, 4);
        
        $this->assertEquals('******7890', $masked);
    }

    /** @test */
    public function it_masks_phone_numbers()
    {
        $phone = '+639171234567';
        $masked = $this->service->maskPhone($phone);
        
        $this->assertStringContainsString('*', $masked);
        $this->assertStringEndsWith('4567', $masked);
    }

    /** @test */
    public function it_masks_emails()
    {
        $email = 'john.doe@example.com';
        $masked = $this->service->maskEmail($email);
        
        $this->assertStringContainsString('*', $masked);
        $this->assertStringContainsString('@example.com', $masked);
        $this->assertStringStartsWith('j', $masked);
    }

    /** @test */
    public function it_masks_card_numbers()
    {
        $card = '4242424242424242';
        $masked = $this->service->maskCardNumber($card);
        
        $this->assertStringContainsString('*', $masked);
        $this->assertStringEndsWith('4242', $masked);
    }

    /** @test */
    public function it_creates_consistent_hashes()
    {
        $value = 'test value';
        
        $hash1 = $this->service->hash($value);
        $hash2 = $this->service->hash($value);
        
        $this->assertEquals($hash1, $hash2);
    }

    /** @test */
    public function it_verifies_hashes_correctly()
    {
        $value = 'test value';
        $hash = $this->service->hash($value);
        
        $this->assertTrue($this->service->verifyHash($value, $hash));
        $this->assertFalse($this->service->verifyHash('wrong value', $hash));
    }

    /** @test */
    public function it_creates_different_hashes_for_different_values()
    {
        $hash1 = $this->service->hash('value1');
        $hash2 = $this->service->hash('value2');
        
        $this->assertNotEquals($hash1, $hash2);
    }

    /** @test */
    public function encrypted_values_are_different_each_time()
    {
        $value = 'test';
        
        $encrypted1 = $this->service->encrypt($value);
        $encrypted2 = $this->service->encrypt($value);
        
        // Due to Laravel's encryption with random IV, each encryption is unique
        $this->assertNotEquals($encrypted1, $encrypted2);
        
        // But both decrypt to the same value
        $this->assertEquals($value, $this->service->decrypt($encrypted1));
        $this->assertEquals($value, $this->service->decrypt($encrypted2));
    }

    /** @test */
    public function it_handles_special_characters_in_encryption()
    {
        $value = "Special!@#$%^&*()_+-=[]{}|;':,.<>?/~`";
        
        $encrypted = $this->service->encrypt($value);
        $decrypted = $this->service->decrypt($encrypted);
        
        $this->assertEquals($value, $decrypted);
    }

    /** @test */
    public function it_handles_unicode_in_encryption()
    {
        $value = '你好世界 مرحبا العالم';
        
        $encrypted = $this->service->encrypt($value);
        $decrypted = $this->service->decrypt($encrypted);
        
        $this->assertEquals($value, $decrypted);
    }

    /** @test */
    public function it_handles_long_values()
    {
        $value = str_repeat('a', 10000);
        
        $encrypted = $this->service->encrypt($value);
        $decrypted = $this->service->decrypt($encrypted);
        
        $this->assertEquals($value, $decrypted);
    }

    /** @test */
    public function it_returns_null_for_invalid_encrypted_data()
    {
        $invalidData = 'invalid_encrypted_data';
        
        $result = $this->service->decrypt($invalidData);
        
        $this->assertNull($result);
    }

    /** @test */
    public function masked_values_preserve_length_indication()
    {
        $short = '123';
        $long = '1234567890123456';
        
        $maskedShort = $this->service->mask($short, 2);
        $maskedLong = $this->service->mask($long, 4);
        
        $this->assertLessThan(strlen($maskedLong), strlen($maskedShort));
    }
}
