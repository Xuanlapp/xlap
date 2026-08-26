<?php

namespace Tests\Feature;

use App\Models\FinancialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_without_a_financial_account_cannot_open_financial_management(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $response = $this->actingAs($user)->get(route('offorest.financial-management'));

        $this->assertSame(403, $response->status());
    }

    public function test_user_with_view_permission_can_open_only_assigned_financial_accounts(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $assigned = FinancialAccount::query()->create([
            'name' => 'Etsy US 01', 'platform' => 'etsy', 'code' => 'ETSY-US-01', 'currency' => 'USD', 'created_by' => $user->id,
        ]);
        FinancialAccount::query()->create([
            'name' => 'Amazon US 01', 'platform' => 'amazon', 'code' => 'AMZ-US-01', 'currency' => 'USD', 'created_by' => $user->id,
        ]);
        $assigned->users()->attach($user->id, ['can_view' => true, 'can_add' => false, 'can_edit' => false, 'can_delete' => false]);

        $response = $this->actingAs($user)->get(route('offorest.financial-management'));

        $this->assertSame(200, $response->status());
        $this->assertStringContainsString('Etsy US 01', $response->getContent());
        $this->assertStringNotContainsString('Amazon US 01', $response->getContent());
    }
}
