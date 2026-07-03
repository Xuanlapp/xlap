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

    public ?int $assignedUserId = null;

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
        $this->assignedUserId = null;

        abort_unless(auth()->user()?->is_admin, 403);

        $item = DataHubProxyItem::query()->findOrFail($itemId);

        $this->publicIp = (string) ($item->public_ip ?? '');
        $this->ppp = (string) ($item->ppp ?? '');
        $this->port = $item->port;
        $this->note = (string) ($item->note ?? '');
        $this->assignedUserId = $item->assigned_user_id;
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
        ]);

        abort_unless(auth()->user()?->is_admin, 403);

        $item = DataHubProxyItem::query()->findOrFail($this->itemId);

        $item->update([
            'note' => $validated['note'] ?? null,
            'port' => $validated['port'] ?? null,
            'assigned_user_id' => $validated['assignedUserId'] ?? null,
        ]);

        $this->dispatch('proxy-item-updated');
        $this->dispatch('toast', type: 'success', title: 'Successfully saved!', message: 'Da luu note proxy.');
        $this->close();
    }

    public function render(): View
    {
        return view('livewire.modals.proxy.edit-proxy-item', [
            'users' => User::query()->where('is_admin', false)->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
