<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wallet;
use App\Models\Withdrawal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WithdrawalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Wallet $wallet;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->wallet = Wallet::factory()->create([
            'user_id' => $this->user->id,
            'real_balance' => 10000,
            'bonus_balance' => 0,
        ]);
        
        $this->actingAs($this->user, 'api');
    }

    /** @test */
    public function user_can_request_withdrawal()
    {
        $response = $this->postJson('/api/withdrawals', [
            'amount' => 5000,
            'gcash_number' => '09171234567',
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'data' => [
                'id',
                'amount',
                'status',
            ],
        ]);
        
        $this->assertDatabaseHas('withdrawals', [
            'user_id' => $this->user->id,
            'amount' => 5000,
            'status' => 'pending',
        ]);
    }

    /** @test */
    public function withdrawal_locks_balance()
    {
        $this->postJson('/api/withdrawals', [
            'amount' => 5000,
            'gcash_number' => '09171234567',
        ]);

        $this->wallet->refresh();
        
        $this->assertEquals(5000, $this->wallet->real_balance);
        $this->assertEquals(5000, $this->wallet->locked_balance);
    }

    /** @test */
    public function user_cannot_withdraw_more_than_balance()
    {
        $response = $this->postJson('/api/withdrawals', [
            'amount' => 15000, // More than available
            'gcash_number' => '09171234567',
        ]);

        $response->assertStatus(400);
        $response->assertJson([
            'message' => 'Insufficient balance',
        ]);
    }

    /** @test */
    public function withdrawal_validates_minimum_amount()
    {
        $response = $this->postJson('/api/withdrawals', [
            'amount' => 50, // Below minimum
            'gcash_number' => '09171234567',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['amount']);
    }

    /** @test */
    public function withdrawal_requires_valid_gcash_number()
    {
        $response = $this->postJson('/api/withdrawals', [
            'amount' => 5000,
            'gcash_number' => 'invalid',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['gcash_number']);
    }

    /** @test */
    public function user_can_view_withdrawal_history()
    {
        Withdrawal::factory()->count(3)->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->getJson('/api/withdrawals');

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');
    }

    /** @test */
    public function user_can_cancel_pending_withdrawal()
    {
        $withdrawal = Withdrawal::factory()->create([
            'user_id' => $this->user->id,
            'amount' => 5000,
            'status' => 'pending',
        ]);
        
        $this->wallet->update([
            'locked_balance' => 5000,
        ]);

        $response = $this->deleteJson("/api/withdrawals/{$withdrawal->id}");

        $response->assertStatus(200);
        
        $this->assertDatabaseHas('withdrawals', [
            'id' => $withdrawal->id,
            'status' => 'cancelled',
        ]);
        
        // Balance should be unlocked
        $this->wallet->refresh();
        $this->assertEquals(0, $this->wallet->locked_balance);
    }

    /** @test */
    public function user_cannot_cancel_processing_withdrawal()
    {
        $withdrawal = Withdrawal::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'processing',
        ]);

        $response = $this->deleteJson("/api/withdrawals/{$withdrawal->id}");

        $response->assertStatus(400);
    }

    /** @test */
    public function user_cannot_withdraw_with_active_bonus()
    {
        $this->wallet->update([
            'bonus_balance' => 1000,
        ]);

        $response = $this->postJson('/api/withdrawals', [
            'amount' => 5000,
            'gcash_number' => '09171234567',
        ]);

        $response->assertStatus(400);
        $response->assertJson([
            'message' => 'Cannot withdraw with active bonus balance',
        ]);
    }

    /** @test */
    public function withdrawal_creates_audit_log()
    {
        $this->postJson('/api/withdrawals', [
            'amount' => 5000,
            'gcash_number' => '09171234567',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->user->id,
            'action' => 'withdrawal_requested',
        ]);
    }

    /** @test */
    public function withdrawal_enforces_daily_limit()
    {
        // Create existing withdrawal today
        Withdrawal::factory()->create([
            'user_id' => $this->user->id,
            'amount' => 40000,
            'status' => 'approved',
            'created_at' => now(),
        ]);

        $response = $this->postJson('/api/withdrawals', [
            'amount' => 20000, // Would exceed daily limit of 50000
            'gcash_number' => '09171234567',
        ]);

        $response->assertStatus(400);
        $response->assertJson([
            'message' => 'Daily withdrawal limit exceeded',
        ]);
    }

    /** @test */
    public function user_cannot_have_multiple_pending_withdrawals()
    {
        Withdrawal::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'pending',
        ]);

        $response = $this->postJson('/api/withdrawals', [
            'amount' => 2000,
            'gcash_number' => '09171234567',
        ]);

        $response->assertStatus(400);
        $response->assertJson([
            'message' => 'You have a pending withdrawal request',
        ]);
    }
}
