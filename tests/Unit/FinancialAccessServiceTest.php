<?php

namespace Tests\Unit;

use App\Models\FinancialTransaction;
use App\Services\Financial\FinancialAccessService;
use Illuminate\Support\Collection;
use Tests\TestCase;

class FinancialAccessServiceTest extends TestCase
{
    public function test_totals_calculate_balance_from_transaction_types(): void
    {
        $transactions = new Collection([
            new FinancialTransaction(['type' => 'revenue', 'amount' => '25000.00']),
            new FinancialTransaction(['type' => 'fulfillment', 'amount' => '13000.00']),
            new FinancialTransaction(['type' => 'expense', 'amount' => '2000.00']),
        ]);

        $totals = app(FinancialAccessService::class)->totals($transactions);

        $this->assertSame(25000.0, $totals['received']);
        $this->assertSame(13000.0, $totals['fulfillment']);
        $this->assertSame(2000.0, $totals['expenses']);
        $this->assertSame(10000.0, $totals['balance']);
    }
}