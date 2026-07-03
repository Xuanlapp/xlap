from pathlib import Path

# Update Proxy page class
p = Path('app/Livewire/Pages/Proxy/Index.php')
text = p.read_text(encoding='utf-8')
if "use Livewire\\Attributes\\On;" not in text:
    text = text.replace("use App\\Services\\Proxy\\ProxyMonitorService;\nuse Illuminate\\Contracts\\View\\View;\nuse Livewire\\Component;", "use App\\Services\\Proxy\\ProxyMonitorService;\nuse Illuminate\\Contracts\\View\\View;\nuse Livewire\\Attributes\\On;\nuse Livewire\\Component;")
insert = "\n    #[On('proxy-item-updated')]\n    public function refreshItems(): void\n    {\n        // Re-render after modal save.\n    }\n"
text = text.replace("    public ?string $refreshError = null;\n", "    public ?string $refreshError = null;\n" + insert)
p.write_text(text, encoding='utf-8')

# Update proxy view
p = Path('resources/views/livewire/pages/proxy/index.blade.php')
text = p.read_text(encoding='utf-8')
text = text.replace('<th class="px-4 py-3 text-left font-semibold">Public IP Change</th>\n                                            <th class="px-4 py-3 text-left font-semibold">Port</th>', '<th class="px-4 py-3 text-left font-semibold">Public IP Change</th>\n                                            <th class="px-4 py-3 text-left font-semibold">Note</th>\n                                            <th class="px-4 py-3 text-left font-semibold">Port</th>')
text = text.replace('<tr class="{{ $publicIpChanged ? \'bg-red-50\' : \'\' }}">', '<tr wire:click="$dispatch(\'openModal\', { component: \'modals.proxy.edit-proxy-item\', arguments: { itemId: {{ $item->id }} } })" class="cursor-pointer transition hover:bg-cyan-50 {{ $publicIpChanged ? \'bg-red-50\' : \'\' }}">')
text = text.replace('<td class="px-4 py-3 text-slate-700 {{ $publicIpChanged ? \'font-semibold text-red-700\' : \'\' }}">{{ $item->public_ip_change ?: \'-\' }}</td>\n                                                <td class="px-4 py-3 text-slate-700">{{ 9800 + $loop->index }}</td>', '<td class="px-4 py-3 text-slate-700 {{ $publicIpChanged ? \'font-semibold text-red-700\' : \'\' }}">{{ $item->public_ip_change ?: \'-\' }}</td>\n                                                <td class="px-4 py-3 text-slate-700">{{ $item->note ?: \'-\' }}</td>\n                                                <td class="px-4 py-3 text-slate-700">{{ 9800 + $loop->index }}</td>')
text = text.replace('                                                <td colspan="7" class="px-4 py-10 text-center text-sm text-slate-400">', '                                                <td colspan="8" class="px-4 py-10 text-center text-sm text-slate-400">')
if '<livewire:modals.proxy.edit-proxy-item />' not in text:
    text = text.replace('</div>\n', '    <livewire:modals.proxy.edit-proxy-item />\n</div>\n', 1)
p.write_text(text, encoding='utf-8')
