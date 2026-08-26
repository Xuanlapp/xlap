<?php

namespace App\Services\Financial;

use App\Models\FinancialAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class FinancialAccessService
{
    public function isAdmin(User $user): bool
    {
        return (bool) $user->is_admin || $user->role === 'admin';
    }

    public function can(User $user, FinancialAccount $account, string $permission = 'can_view'): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }

        $relation = $account->users()->whereKey($user->id)->first();
        $level = (string) ($relation?->pivot?->access_level ?? '');

        if ($level === 'read_write') {
            return in_array($permission, ['can_view', 'can_add', 'can_edit', 'can_delete'], true);
        }

        if ($level === 'read_only') {
            return $permission === 'can_view';
        }

        return (bool) ($relation?->pivot?->{$permission} ?? false);
    }

    public function visibleAccountsQuery(User $user): Builder
    {
        if ($this->isAdmin($user)) {
            return FinancialAccount::query();
        }

        return FinancialAccount::query()->whereHas('users', function (Builder $query) use ($user): void {
            $query->where('users.id', $user->id)
                ->where(function (Builder $query): void {
                    $query->whereIn('financial_account_user.access_level', ['read_only', 'read_write'])
                        ->orWhere('financial_account_user.can_view', true);
                });
        });
    }

    /**
     * @return array{received: float, fulfillment: float, expenses: float, balance: float}
     */
    public function totals(Collection $transactions): array
    {
        $received = (float) $transactions->where('type', 'revenue')->sum('amount');
        $fulfillment = (float) $transactions->where('type', 'fulfillment')->sum('amount');
        $expenses = (float) $transactions->where('type', 'expense')->sum('amount');

        return [
            'received' => $received,
            'fulfillment' => $fulfillment,
            'expenses' => $expenses,
            'balance' => $received - $fulfillment - $expenses,
        ];
    }
}
