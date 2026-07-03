from pathlib import Path

# Modal backend: admin only
p = Path('app/Livewire/Modals/Proxy/EditProxyItem.php')
text = p.read_text(encoding='utf-8')
text = text.replace("        $item = DataHubProxyItem::query()->findOrFail($itemId);\n\n        abort_unless(auth()->user()?->is_admin || auth()->user()?->dataHubProxies()->whereKey($item->data_hub_proxy_id)->exists(), 403);", "        abort_unless(auth()->user()?->is_admin, 403);\n\n        $item = DataHubProxyItem::query()->findOrFail($itemId);")
text = text.replace("        $item = DataHubProxyItem::query()->findOrFail($this->itemId);\n\n        abort_unless(auth()->user()?->is_admin || auth()->user()?->dataHubProxies()->whereKey($item->data_hub_proxy_id)->exists(), 403);", "        abort_unless(auth()->user()?->is_admin, 403);\n\n        $item = DataHubProxyItem::query()->findOrFail($this->itemId);")
p.write_text(text, encoding='utf-8')

# View: admin-only row click/modal/loading
p = Path('resources/views/livewire/pages/proxy/index.blade.php')
text = p.read_text(encoding='utf-8')
text = text.replace("<div x-data=\"{ openingProxyModal: false }\" x-on:open-modal.window=\"if ($event.detail.component === 'modals.proxy.edit-proxy-item') { openingProxyModal = true; setTimeout(() => openingProxyModal = false, 900) }\" class=\"min-h-screen bg-slate-100 px-4 py-6 text-slate-950 sm:px-6 lg:px-8\">", "<div x-data=\"{ openingProxyModal: false }\" @if (auth()->user()?->is_admin) x-on:open-modal.window=\"if ($event.detail.component === 'modals.proxy.edit-proxy-item') { openingProxyModal = true; setTimeout(() => openingProxyModal = false, 900) }\" @endif class=\"min-h-screen bg-slate-100 px-4 py-6 text-slate-950 sm:px-6 lg:px-8\">")
old = "<tr x-on:click=\"openingProxyModal = true; setTimeout(() => openingProxyModal = false, 900)\" wire:click=\"$dispatch('openModal', { component: 'modals.proxy.edit-proxy-item', arguments: { itemId: {{ $item->id }} } })\" class=\"cursor-pointer transition hover:bg-cyan-50 {{ $publicIpChanged ? 'bg-red-50' : '' }}\">"
new = "<tr @if (auth()->user()?->is_admin) x-on:click=\"openingProxyModal = true; setTimeout(() => openingProxyModal = false, 900)\" wire:click=\"$dispatch('openModal', { component: 'modals.proxy.edit-proxy-item', arguments: { itemId: {{ $item->id }} } })\" @endif class=\"{{ auth()->user()?->is_admin ? 'cursor-pointer hover:bg-cyan-50' : '' }} transition {{ $publicIpChanged ? 'bg-red-50' : '' }}\">"
text = text.replace(old, new)
text = text.replace("\n    <livewire:modals.proxy.edit-proxy-item />\n</div>", "\n    @if (auth()->user()?->is_admin)\n        <livewire:modals.proxy.edit-proxy-item />\n    @endif\n</div>")
p.write_text(text, encoding='utf-8')
