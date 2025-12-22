<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wallet;
use App\Models\Deposit;
use App\Models\GcashAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepositWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected GcashAccount $gcashAccount;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        Wallet::factory()->create(['user_id' => $this->user->id]);
        
        $this->gcashAccount = GcashAccount::factory()->create([
            'is_active' => true,
            'daily_limit' => 100000,
            'current_daily_amount' => 0,
        ]);
        
        $this->actingAs($this->user, 'api');
    }

    /** @test */
    public function user_can_request_deposit()
    {
        $response = $this->postJson('/api/deposits', [
            'amount' => 1000,
            'gcash_account_id' => $this->gcashAccount->id,
            'reference_number' => 'REF' . time(),
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'data' => [
                'id',
                'amount',
                'status',
                'reference_number',
            ],
        ]);
        
        $this->assertDatabaseHas('deposits', [
            'user_id' => $this->user->id,
            'amount' => 1000,
            'status' => 'pending',
        ]);
    }

    /** @test */
    public function deposit_request_validates_minimum_amount()
    {
        $response = $this->postJson('/api/deposits', [
            'amount' => 50, // Below minimum
            'gcash_account_id' => $this->gcashAccount->id,
            'reference_number' => 'REF' . time(),
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['amount']);
    }

    /** @test */
    public function deposit_request_validates_maximum_amount()
    {
        $response = $this->postJson('/api/deposits', [
            'amount' => 1000000, // Above maximum
            'gcash_account_id' => $this->gcashAccount->id,
            'reference_number' => 'REF' . time(),
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['amount']);
    }

    /** @test */
    public function deposit_request_requires_reference_number()
    {
        $response = $this->postJson('/api/deposits', [
            'amount' => 1000,
            'gcash_account_id' => $this->gcashAccount->id,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['reference_number']);
    }

    /** @test */
    public function deposit_request_requires_valid_gcash_account()
    {
        $response = $this->postJson('/api/deposits', [
            'amount' => 1000,
            'gcash_account_id' => 99999,
            'reference_number' => 'REF' . time(),
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['gcash_account_id']);
    }

    /** @test */
    public function user_can_view_pending_deposits()
    {
        Deposit::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'status' => 'pending',
        ]);

        $response = $this->getJson('/api/deposits?status=pending');

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');
    }

    /** @test */
    public function user_can_view_deposit_history()
    {
        Deposit::factory()->count(5)->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->getJson('/api/deposits');

        $response->assertStatus(200);
        $response->assertJsonCount(5, 'data');
    }

    /** @test */
    public function user_can_cancel_pending_deposit()
    {
        $deposit = Deposit::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'pending',
        ]);

        $response = $this->deleteJson("/api/deposits/{$deposit->id}");

        $response->assertStatus(200);
        
        $this->assertDatabaseHas('deposits', [
            'id' => $deposit->id,
            'status' => 'cancelled',
        ]);
    }

    /** @test */
    public function user_cannot_cancel_approved_deposit()
    {
        $deposit = Deposit::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'approved',
        ]);

        $response = $this->deleteJson("/api/deposits/{$deposit->id}");

        $response->assertStatus(400);
        
        $this->assertDatabaseHas('deposits', [
            'id' => $deposit->id,
            'status' => 'approved',
        ]);
    }

    /** @test */
    public function user_cannot_view_other_users_deposits()
    {
        $otherUser = User::factory()->create();
        $deposit = Deposit::factory()->create([
            'user_id' => $otherUser->id,
        ]);

        $response = $this->getJson("/api/deposits/{$deposit->id}");

        $response->assertStatus(403);
    }

    /** @test */
    public function deposit_creates_audit_log()
    {
        $this->postJson('/api/deposits', [
            'amount' => 1000,
            'gcash_account_id' => $this->gcashAccount->id,
            'reference_number' => 'REF' . time(),
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->user->id,
            'action' => 'deposit_requested',
        ]);
    }

    /** @test */
    public function deposit_respects_gcash_daily_limit()
    {
        $this->gcashAccount->update([
            'current_daily_amount' => 95000,
        ]);

        $response = $this->postJson('/api/deposits', [
            'amount' => 10000, // Would exceed daily limit
            'gcash_account_id' => $this->gcashAccount->id,
            'reference_number' => 'REF' . time(),
        ]);

        $response->assertStatus(400);
    }

    /** @test */
    public function duplicate_reference_numbers_are_rejected()
    {
        $refNumber = 'REF' . time();
        
        Deposit::factory()->create([
            'user_id' => $this->user->id,
            'reference_number' => $refNumber,
        ]);

        $response = $this->postJson('/api/deposits', [
            'amount' => 1000,
            'gcash_account_id' => $this->gcashAccount->id,
            'reference_number' => $refNumber,
        ]);

        $response->assertStatus(422);
    }
}
