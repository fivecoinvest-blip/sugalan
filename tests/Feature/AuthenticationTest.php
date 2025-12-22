<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\VipLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\VipLevelsSeeder::class);
    }

    /** @test */
    public function user_can_register_with_phone_and_password()
    {
        $response = $this->postJson('/api/auth/register/phone', [
            'phone' => '+639171234567',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'referral_code' => null,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'user' => ['id', 'phone', 'vip_level_id'],
                'token',
            ]);

        $this->assertDatabaseHas('users', [
            'phone' => '+639171234567',
            'auth_method' => 'phone',
        ]);
    }

    /** @test */
    public function user_cannot_register_with_existing_phone()
    {
        User::factory()->create(['phone' => '+639171234567']);

        $response = $this->postJson('/api/auth/register/phone', [
            'phone' => '+639171234567',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
    }

    /** @test */
    public function user_can_login_with_phone_and_password()
    {
        $user = User::factory()->create([
            'phone' => '+639171234567',
            'password' => bcrypt('password123'),
            'auth_method' => 'phone',
        ]);

        $response = $this->postJson('/api/auth/login/phone', [
            'phone' => '+639171234567',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'user',
                'token',
            ]);
    }

    /** @test */
    public function user_cannot_login_with_wrong_password()
    {
        $user = User::factory()->create([
            'phone' => '+639171234567',
            'password' => bcrypt('password123'),
            'auth_method' => 'phone',
        ]);

        $response = $this->postJson('/api/auth/login/phone', [
            'phone' => '+639171234567',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Invalid credentials',
            ]);
    }

    /** @test */
    public function guest_user_can_be_created()
    {
        $response = $this->postJson('/api/auth/guest');

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'user' => ['id', 'guest_id'],
                'token',
            ]);

        $this->assertDatabaseHas('users', [
            'auth_method' => 'guest',
            'is_guest' => true,
        ]);
    }

    /** @test */
    public function authenticated_user_can_get_profile()
    {
        $user = User::factory()->create();
        $token = auth()->tokenById($user->id);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/auth/me');

        $response->assertStatus(200)
            ->assertJson([
                'id' => $user->id,
            ]);
    }

    /** @test */
    public function unauthenticated_user_cannot_access_protected_routes()
    {
        $response = $this->getJson('/api/auth/me');

        $response->assertStatus(401);
    }

    /** @test */
    public function user_can_logout()
    {
        $user = User::factory()->create();
        $token = auth()->tokenById($user->id);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/auth/logout');

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Successfully logged out',
            ]);
    }

    /** @test */
    public function registration_creates_wallet_automatically()
    {
        $response = $this->postJson('/api/auth/register/phone', [
            'phone' => '+639171234567',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $user = User::where('phone', '+639171234567')->first();
        
        $this->assertNotNull($user->wallet);
        $this->assertEquals(0, $user->wallet->real_balance);
        $this->assertEquals(0, $user->wallet->bonus_balance);
    }

    /** @test */
    public function new_user_gets_bronze_vip_level()
    {
        $response = $this->postJson('/api/auth/register/phone', [
            'phone' => '+639171234567',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $user = User::where('phone', '+639171234567')->first();
        $bronzeLevel = VipLevel::where('name', 'Bronze')->first();
        
        $this->assertEquals($bronzeLevel->id, $user->vip_level_id);
    }

    /** @test */
    public function password_must_be_confirmed()
    {
        $response = $this->postJson('/api/auth/register/phone', [
            'phone' => '+639171234567',
            'password' => 'password123',
            'password_confirmation' => 'different',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    /** @test */
    public function phone_format_is_validated()
    {
        $response = $this->postJson('/api/auth/register/phone', [
            'phone' => 'invalid-phone',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
    }

    /** @test */
    public function user_can_register_with_valid_referral_code()
    {
        $referrer = User::factory()->create([
            'referral_code' => 'ABC12345',
        ]);

        $response = $this->postJson('/api/auth/register/phone', [
            'phone' => '+639171234567',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'referral_code' => 'ABC12345',
        ]);

        $response->assertStatus(201);
        
        $newUser = User::where('phone', '+639171234567')->first();
        $this->assertEquals($referrer->id, $newUser->referred_by);
    }
}
