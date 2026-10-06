<?php

namespace Tests\Feature\DataExport;

use App\Models\Account;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DataExportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Household $household;
    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->household = Household::create(['name' => 'Export Household', 'description' => null]);
        HouseholdMember::create([
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
            'role' => 'household_owner',
        ]);

        $this->account = Account::create([
            'household_id' => $this->household->id,
            'name' => 'Kas Mandiri',
            'type' => 'bank',
            'current_balance' => 2000000,
            'is_active' => true,
        ]);
    }

    public function test_user_can_export_transactions_as_json_and_csv(): void
    {
        Sanctum::actingAs($this->user);

        Transaction::create([
            'household_id' => $this->household->id,
            'account_id' => $this->account->id,
            'type' => 'income',
            'amount' => 500000,
            'description' => 'Gaji Tambahan',
            'transaction_date' => now()->toDateString(),
        ]);

        // JSON export
        $responseJson = $this->getJson("/api/v1/households/{$this->household->id}/export/transactions?format=json");
        $responseJson->assertStatus(200)
            ->assertJsonPath('total_records', 1);

        // CSV export
        $responseCsv = $this->get("/api/v1/households/{$this->household->id}/export/transactions?format=csv");
        $responseCsv->assertStatus(200)
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    public function test_user_can_export_financial_summary_as_json_and_csv(): void
    {
        Sanctum::actingAs($this->user);

        $responseJson = $this->getJson("/api/v1/households/{$this->household->id}/export/summary?format=json");
        $responseJson->assertStatus(200)
            ->assertJsonPath('household_name', 'Export Household');

        $responseCsv = $this->get("/api/v1/households/{$this->household->id}/export/summary?format=csv");
        $responseCsv->assertStatus(200)
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    public function test_cross_household_isolation(): void
    {
        $otherUser = User::factory()->create();
        $otherHousehold = Household::create(['name' => 'Other Household', 'description' => null]);
        HouseholdMember::create([
            'user_id' => $otherUser->id,
            'household_id' => $otherHousehold->id,
            'role' => 'household_owner',
        ]);

        Sanctum::actingAs($otherUser);

        $response = $this->getJson("/api/v1/households/{$this->household->id}/export/transactions");
        $response->assertStatus(403);
    }
}
