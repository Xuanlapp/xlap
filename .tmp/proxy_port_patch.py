from pathlib import Path
p = Path('resources/views/livewire/pages/proxy/index.blade.php')
text = p.read_text(encoding='utf-8')
text = text.replace('<th class="px-4 py-3 text-left font-semibold">Proxy Port</th>\n                                            <th class="px-4 py-3 text-left font-semibold">Proxy Port V6</th>', '<th class="px-4 py-3 text-left font-semibold">Port</th>')
text = text.replace('<td class="px-4 py-3 text-slate-700">{{ $item->proxy_port ?: \'-\' }}</td>\n                                                <td class="px-4 py-3 text-slate-700">{{ $item->proxy_port_v6 ?: \'-\' }}</td>', '<td class="px-4 py-3 text-slate-700">{{ 9800 + $loop->index }}</td>')
text = text.replace('                                                <td colspan="8" class="px-4 py-10 text-center text-sm text-slate-400">', '                                                <td colspan="7" class="px-4 py-10 text-center text-sm text-slate-400">')
p.write_text(text, encoding='utf-8')
