<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\WalletService;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;

class WalletServiceTest extends TestCase
{
    use RefreshDatabase;

    protected WalletService $service;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(WalletService::class);
        
        $this->user = User::factory()->create();
        $this->user->wallet()->create([
            'real_balance' => 1000.00,
            'bonus_balance' => 500.00,
            'locked_balance' => 0.00,
        ]);
    }

    /** @test */
    public function it_gets_user_balance()
    {
        $balance = $this->service->getBalance($this->user);

        $this->assertEquals(1000.00, $balance['real_balance']);
        $this->assertEquals(500.00, $balance['bonus_balance']);
        $this->assertEquals(0.00, $balance['locked_balance']);
        $this->assertEquals(1500.00, $balance['total_balance']);
    }

    /** @test */
    public function it_credits_real_balance()
    {
        $this->service->credit($this->user, 250.00, 'deposit', 'Test deposit');

        $wallet = $this->user->wallet->fresh();
        $this->assertEquals(1250.00, $wallet->real_balance);
        
        // Check transaction created
        $transaction = Transaction::where('user_id', $this->user->id)
            ->where('type', 'deposit')
            ->first();
            
        $this->assertNotNull($transaction);
        $this->assertEquals(250.00, $transaction->amount);
    }

    /** @test */
    public function it_credits_bonus_balance()
    {
        $this->service->creditBonus($this->user, 100.00, 'bonus', 'Test bonus');

        $wallet = $this->user->wallet->fresh();
        $this->assertEquals(600.00, $wallet->bonus_balance);
    }

    /** @test */
    public function it_deducts_real_balance()
    {
        $this->service->deduct($this->user, 300.00, 'withdrawal', 'Test withdrawal');

        $wallet = $this->user->wallet->fresh();
        $this->assertEquals(700.00, $wallet->real_balance);
    }

    /** @test */
    public function it_throws_exception_when_insufficient_real_balance()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Insufficient balance');

        $this->service->deduct($this->user, 1500.00, 'withdrawal', 'Test');
    }

    /** @test */
    public function it_deducts_bet_from_bonus_first_then_real()
    {
        $balanceUsed = $this->service->deductBet($this->user, 700.00);

        $wallet = $this->user->wallet->fresh();
        
        // Should use 500 from bonus, 200 from real
        $this->assertEquals(800.00, $wallet->real_balance);
        $this->assertEquals(0.00, $wallet->bonus_balance);
        
        $this->assertEquals([
            'real' => 200.00,
            'bonus' => 500.00
        ], $balanceUsed);
    }

    /** @test */
    public function it_deducts_bet_from_real_when_no_bonus()
    {
        // Set bonus to 0
        $this->user->wallet->update(['bonus_balance' => 0]);

        $balanceUsed = $this->service->deductBet($this->user, 100.00);

        $wallet = $this->user->wallet->fresh();
        $this->assertEquals(900.00, $wallet->real_balance);
        $this->assertEquals(0.00, $wallet->bonus_balance);
        
        $this->assertEquals([
            'real' => 100.00,
            'bonus' => 0.00
        ], $balanceUsed);
    }

    /** @test */
    public function it_credits_win_payout()
    {
        $this->service->creditWin($this->user, 500.00, 'dice', 'Game win');

        $wallet = $this->user->wallet->fresh();
        $this->assertEquals(1500.00, $wallet->real_balance);
    }

    /** @test */
    public function it_locks_balance_for_pending_withdrawal()
    {
        $this->service->lockBalance($this->user, 200.00);

        $wallet = $this->user->wallet->fresh();
        $this->assertEquals(800.00, $wallet->real_balance);
        $this->assertEquals(200.00, $wallet->locked_balance);
    }

    /** @test */
    public function it_unlocks_balance_after_withdrawal_rejection()
    {
        // First lock
        $this->service->lockBalance($this->user, 200.00);
        
        // Then unlock
        $this->service->unlockBalance($this->user, 200.00);

        $wallet = $this->user->wallet->fresh();
        $this->assertEquals(1000.00, $wallet->real_balance);
        $this->assertEquals(0.00, $wallet->locked_balance);
    }

    /** @test */
    public function it_releases_locked_balance_after_withdrawal_approval()
    {
        // Lock balance
        $this->service->lockBalance($this->user, 200.00);
        
        // Release (deduct from locked)
        $this->service->releaseLockedBalance($this->user, 200.00);

        $wallet = $this->user->wallet->fresh();
        $this->assertEquals(800.00, $wallet->real_balance);
        $this->assertEquals(0.00, $wallet->locked_balance);
    }

    /** @test */
    public function it_transfers_between_users()
    {
        $user2 = User::factory()->create();
        $user2->wallet()->create([
            'real_balance' => 500.00,
            'bonus_balance' => 0.00,
            'locked_balance' => 0.00,
        ]);

        $this->service->transfer($this->user, $user2, 100.00, 'Test transfer');

        $wallet1 = $this->user->wallet->fresh();
        $wallet2 = $user2->wallet->fresh();
        
        $this->assertEquals(900.00, $wallet1->real_balance);
        $this->assertEquals(600.00, $wallet2->real_balance);
    }

    /** @test */
    public function it_creates_transaction_records()
    {
        $this->service->credit($this->user, 100.00, 'deposit', 'Test deposit');

        $transaction = Transaction::where('user_id', $this->user->id)->latest()->first();
        
        $this->assertNotNull($transaction);
        $this->assertEquals('deposit', $transaction->type);
        $this->assertEquals(100.00, $transaction->amount);
        $this->assertEquals('completed', $transaction->status);
        $this->assertNotNull($transaction->uuid);
    }

    /** @test */
    public function wallet_operations_are_atomic()
    {
        try {
            \DB::transaction(function () {
                $this->service->deduct($this->user, 500.00, 'test', 'Test');
                throw new \Exception('Simulated error');
            });
        } catch (\Exception $e) {
            // Expected exception
        }

        // Balance should remain unchanged
        $wallet = $this->user->wallet->fresh();
        $this->assertEquals(1000.00, $wallet->real_balance);
    }

    /** @test */
    public function it_handles_concurrent_balance_updates()
    {
        // This tests database locking
        $wallet = $this->user->wallet;
        
        // First transaction
        \DB::transaction(function () use ($wallet) {
            $wallet->lockForUpdate()->first();
            $wallet->decrement('real_balance', 100);
        });

        $wallet->refresh();
        $this->assertEquals(900.00, $wallet->real_balance);
    }
}
