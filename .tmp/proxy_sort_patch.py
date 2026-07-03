from pathlib import Path

repo = Path('app/Repositories/Proxy/DataHubProxyRepository.php')
text = repo.read_text(encoding='utf-8')
text = text.replace("                    'items' => fn ($query) => $query->orderBy('proxy_port')->orderBy('ppp'),", "                    'items' => fn ($query) => $query->orderByRaw(\"CASE WHEN ppp REGEXP '^mvlan[0-9]+$' THEN CAST(SUBSTRING(ppp, 6) AS UNSIGNED) ELSE 999999 END\")->orderBy('ppp'),")
text = text.replace("                'items' => fn ($query) => $query->orderBy('proxy_port')->orderBy('ppp'),", "                'items' => fn ($query) => $query->orderByRaw(\"CASE WHEN ppp REGEXP '^mvlan[0-9]+$' THEN CAST(SUBSTRING(ppp, 6) AS UNSIGNED) ELSE 999999 END\")->orderBy('ppp'),")
repo.write_text(text, encoding='utf-8')

view = Path('resources/views/livewire/pages/proxy/index.blade.php')
text = view.read_text(encoding='utf-8')
text = text.replace('<th class="px-4 py-3 text-left font-semibold">Public IP Change</th>\n                                            <th class="px-4 py-3 text-left font-semibold">Proxy Port</th>', '<th class="px-4 py-3 text-left font-semibold">PPP</th>\n                                            <th class="px-4 py-3 text-left font-semibold">Public IP Change</th>\n                                            <th class="px-4 py-3 text-left font-semibold">Proxy Port</th>')
text = text.replace('<td class="px-4 py-3 text-slate-700 {{ $publicIpChanged ? \'font-semibold text-red-700\' : \'\' }}">{{ $item->public_ip_change ?: \'-\' }}</td>\n                                                <td class="px-4 py-3 text-slate-700">{{ $item->proxy_port ?: \'-\' }}</td>', '<td class="px-4 py-3 text-slate-700">{{ $item->ppp ?: \'-\' }}</td>\n                                                <td class="px-4 py-3 text-slate-700 {{ $publicIpChanged ? \'font-semibold text-red-700\' : \'\' }}">{{ $item->public_ip_change ?: \'-\' }}</td>\n                                                <td class="px-4 py-3 text-slate-700">{{ $item->proxy_port ?: \'-\' }}</td>')
text = text.replace('                                                <td colspan="7" class="px-4 py-10 text-center text-sm text-slate-400">', '                                                <td colspan="8" class="px-4 py-10 text-center text-sm text-slate-400">')
view.write_text(text, encoding='utf-8')
