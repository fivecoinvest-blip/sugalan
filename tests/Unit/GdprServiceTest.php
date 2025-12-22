<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\Wallet;
use App\Models\Transaction;
use App\Models\Bet;
use App\Models\Bonus;
use App\Services\GdprService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class GdprServiceTest extends TestCase
{
    use RefreshDatabase;

    protected GdprService $gdprService;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        Storage::fake('local');
        $this->gdprService = app(GdprService::class);
        
        // Create test user with related data
        $this->user = User::factory()->create([
            'email' => 'test@example.com',
            'phone_number' => '+639171234567',
        ]);
        
        // Create wallet
        Wallet::factory()->create(['user_id' => $this->user->id]);
        
        // Create transactions
        Transaction::factory()->count(3)->create(['user_id' => $this->user->id]);
        
        // Create bets
        Bet::factory()->count(5)->create(['user_id' => $this->user->id]);
        
        // Create bonuses
        Bonus::factory()->count(2)->create(['user_id' => $this->user->id]);
    }

    /** @test */
    public function it_exports_user_data_to_zip()
    {
        $zipPath = $this->gdprService->exportUserData($this->user->id);

        $this->assertNotNull($zipPath);
        $this->assertTrue(Storage::exists($zipPath));
        
        // Verify it's a valid ZIP file
        $fullPath = Storage::path($zipPath);
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($fullPath) === true);
        
        // Verify ZIP contains required files
        $this->assertNotFalse($zip->locateName('data.json'));
        $this->assertNotFalse($zip->locateName('export.html'));
        $this->assertNotFalse($zip->locateName('README.txt'));
        
        $zip->close();
    }

    /** @test */
    public function it_includes_user_profile_in_export()
    {
        $data = $this->gdprService->getUserData($this->user->id);

        $this->assertArrayHasKey('profile', $data);
        $this->assertEquals($this->user->email, $data['profile']['email']);
        $this->assertEquals($this->user->phone_number, $data['profile']['phone_number']);
    }

    /** @test */
    public function it_includes_wallet_data_in_export()
    {
        $data = $this->gdprService->getUserData($this->user->id);

        $this->assertArrayHasKey('wallet', $data);
        $this->assertArrayHasKey('real_balance', $data['wallet']);
        $this->assertArrayHasKey('bonus_balance', $data['wallet']);
    }

    /** @test */
    public function it_includes_transactions_in_export()
    {
        $data = $this->gdprService->getUserData($this->user->id);

        $this->assertArrayHasKey('transactions', $data);
        $this->assertCount(3, $data['transactions']);
    }

    /** @test */
    public function it_includes_bets_in_export()
    {
        $data = $this->gdprService->getUserData($this->user->id);

        $this->assertArrayHasKey('bets', $data);
        $this->assertCount(5, $data['bets']);
    }

    /** @test */
    public function it_includes_bonuses_in_export()
    {
        $data = $this->gdprService->getUserData($this->user->id);

        $this->assertArrayHasKey('bonuses', $data);
        $this->assertCount(2, $data['bonuses']);
    }

    /** @test */
    public function it_deletes_user_data()
    {
        $result = $this->gdprService->deleteUserData($this->user->id);

        $this->assertTrue($result);
        
        // Verify user is marked for deletion
        $user = User::find($this->user->id);
        $this->assertNotNull($user->deleted_at);
    }

    /** @test */
    public function it_anonymizes_user_when_configured()
    {
        config(['gdpr.anonymize_instead_of_delete' => true]);
        
        $result = $this->gdprService->deleteUserData($this->user->id);

        $this->assertTrue($result);
        
        $user = User::find($this->user->id);
        $this->assertStringContainsString('deleted_user_', $user->email);
    }

    /** @test */
    public function it_preserves_financial_records()
    {
        config(['gdpr.preserve_financial_records' => true]);
        
        $this->gdprService->deleteUserData($this->user->id);

        // Verify transactions are preserved
        $this->assertEquals(3, Transaction::where('user_id', $this->user->id)->count());
    }

    /** @test */
    public function it_generates_valid_json_export()
    {
        $data = $this->gdprService->getUserData($this->user->id);
        $json = json_encode($data);

        $this->assertJson($json);
        $decoded = json_decode($json, true);
        $this->assertEquals($data, $decoded);
    }

    /** @test */
    public function it_handles_user_without_data()
    {
        $newUser = User::factory()->create();
        
        $data = $this->gdprService->getUserData($newUser->id);

        $this->assertArrayHasKey('profile', $data);
        $this->assertEmpty($data['transactions'] ?? []);
        $this->assertEmpty($data['bets'] ?? []);
    }

    /** @test */
    public function it_returns_null_for_non_existent_user()
    {
        $zipPath = $this->gdprService->exportUserData(99999);

        $this->assertNull($zipPath);
    }

    /** @test */
    public function it_calculates_data_summary_correctly()
    {
        $summary = $this->gdprService->getDataSummary($this->user->id);

        $this->assertArrayHasKey('total_transactions', $summary);
        $this->assertArrayHasKey('total_bets', $summary);
        $this->assertArrayHasKey('total_bonuses', $summary);
        $this->assertEquals(3, $summary['total_transactions']);
        $this->assertEquals(5, $summary['total_bets']);
        $this->assertEquals(2, $summary['total_bonuses']);
    }
}
