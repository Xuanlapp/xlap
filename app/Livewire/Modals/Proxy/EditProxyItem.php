<?php

namespace App\Livewire\Modals\Proxy;

use App\Models\DataHubProxyItem;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class EditProxyItem extends Component
{
    public bool $isOpen = false;

    public bool $isLoading = false;

    public ?int $itemId = null;

    public string $publicIp = '';

    public string $ppp = '';

    public ?int $port = null;

    public string $note = '';

    /** @var array<int, int|string> */
    public array $sharedManagerIds = [];

    /** @var array<int, int|string> */
    public array $fullAccessManagerIds = [];

    public ?int $assignedUserId = null;

    public bool $hasChangedAt = false;

    public function updatedFullAccessManagerIds(): void
    {
        $fullIds = array_map('intval', $this->fullAccessManagerIds);
        $this->sharedManagerIds = array_values(array_diff(array_map('intval', $this->sharedManagerIds), $fullIds));
    }

    public function updatedSharedManagerIds(): void
    {
        $sharedIds = array_map('intval', $this->sharedManagerIds);
        $this->fullAccessManagerIds = array_values(array_diff(array_map('intval', $this->fullAccessManagerIds), $sharedIds));
    }

    #[On('openModal')]
    public function openModal(string $component, array $arguments = []): void
    {
        if ($component !== 'modals.proxy.edit-proxy-item') {
            return;
        }

        $this->open((int) ($arguments['itemId'] ?? 0));
    }

    public function open(int $itemId): void
    {
        $this->resetValidation();
        $this->isOpen = true;
        $this->isLoading = true;
        $this->itemId = $itemId;
        $this->publicIp = '';
        $this->ppp = '';
        $this->port = null;
        $this->note = '';
        $this->sharedManagerIds = [];
        $this->fullAccessManagerIds = [];
        $this->assignedUserId = null;
        $this->hasChangedAt = false;

        abort_unless(auth()->user()?->is_admin, 403);

        $item = DataHubProxyItem::query()->findOrFail($itemId);

        $this->publicIp = (string) ($item->public_ip ?? '');
        $this->ppp = (string) ($item->ppp ?? '');
        $this->port = $item->port;
        $this->note = (string) ($item->note ?? '');
        $this->assignedUserId = $item->assigned_user_id;
        $this->hasChangedAt = filled($item->changed_at);
        $this->sharedManagerIds = $item->managerAccesses()->wherePivot('access_type', 'shared')->pluck('users.id')->map(fn ($id) => (int) $id)->all();
        $this->fullAccessManagerIds = User::query()
            ->where('is_admin', false)
            ->where('role', 'manager')
            ->where('can_view_all_proxy', true)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $this->isLoading = false;
    }

    public function close(): void
    {
        $this->isOpen = false;
        $this->isLoading = false;
    }

    public function save(): void
    {
        if (! $this->itemId) {
            return;
        }

        $validated = $this->validate([
            'note' => ['nullable', 'string', 'max:5000'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'assignedUserId' => ['nullable', 'integer', 'exists:users,id'],
            'sharedManagerIds' => ['array'],
            'sharedManagerIds.*' => ['integer', 'exists:users,id'],
            'fullAccessManagerIds' => ['array'],
            'fullAccessManagerIds.*' => ['integer', 'exists:users,id'],
        ]);

        abort_unless(auth()->user()?->is_admin, 403);

        $item = DataHubProxyItem::query()->findOrFail($this->itemId);

        $item->update([
            'note' => $validated['note'] ?? null,
            'port' => $validated['port'] ?? null,
            'assigned_user_id' => $validated['assignedUserId'] ?? null,
        ]);

        $sharedManagerIds = array_values(array_diff(array_unique(array_map('intval', $validated['sharedManagerIds'] ?? [])), array_unique(array_map('intval', $validated['fullAccessManagerIds'] ?? []))));
        $fullAccessManagerIds = array_values(array_unique(array_map('intval', $validated['fullAccessManagerIds'] ?? [])));

        $item->managerAccesses()->sync([]);
        foreach ($sharedManagerIds as $managerId) {
            $item->managerAccesses()->syncWithoutDetaching([$managerId => ['access_type' => 'shared']]);
        }
        foreach ($fullAccessManagerIds as $managerId) {
            $item->managerAccesses()->syncWithoutDetaching([$managerId => ['access_type' => 'full']]);
        }

        $this->dispatch('proxy-item-updated');
        $this->dispatch('toast', type: 'success', title: 'Successfully saved!', message: 'Da luu note proxy.');
        $this->close();
    }

    /**
     * Xac nhan proxy da quay ve binh thuong va xoa moc thay doi hien tai.
     */
    public function resetChangedAt(): void
    {
        if (! $this->itemId) {
            return;
        }

        abort_unless(auth()->user()?->is_admin, 403);

        $item = DataHubProxyItem::query()->findOrFail($this->itemId);
        $item->forceFill([
            'changed_at' => null,
        ])->save();

        $this->dispatch('proxy-item-updated');
        $this->dispatch('toast', type: 'success', title: 'Da xac nhan!', message: 'Changed At da duoc xoa va proxy quay ve mau xanh.');
        $this->close();
    }

    public function render(): View
    {
        return view('livewire.modals.proxy.edit-proxy-item', [
            'users' => User::query()->where('is_admin', false)->where('role', 'user')->orderBy('name')->get(['id', 'name']),
            'managers' => User::query()->where('is_admin', false)->where('role', 'manager')->orderBy('name')->get(['id', 'name']),
        ]);
    }
}

