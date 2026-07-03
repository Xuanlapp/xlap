from pathlib import Path

# Repository: non-admin sources come from assigned items, not old pivot
p = Path('app/Repositories/Proxy/DataHubProxyRepository.php')
text = p.read_text(encoding='utf-8')
old = """        return $user->dataHubProxies()
            ->with([
                'snapshots' => fn ($query) => $query->latest('checked_at')->limit(10),
                'items' => fn ($query) => $this->orderedItems($query)->where('assigned_user_id', $user->id)->with('assignedUser'),
            ])
            ->where('is_active', true)
            ->orderBy('name')
            ->get();"""
new = """        return DataHubProxy::query()
            ->with([
                'snapshots' => fn ($query) => $query->latest('checked_at')->limit(10),
                'items' => fn ($query) => $this->orderedItems($query)->where('assigned_user_id', $user->id)->with('assignedUser'),
            ])
            ->where('is_active', true)
            ->whereHas('items', fn ($query) => $query->where('assigned_user_id', $user->id))
            ->orderBy('name')
            ->get();"""
text = text.replace(old, new)
p.write_text(text, encoding='utf-8')

# Admin users page: remove Proxy column header/cell/colspan if present
p = Path('resources/views/livewire/pages/admin/list-user.blade.php')
text = p.read_text(encoding='utf-8')
text = text.replace('                            <th class="px-5 py-4 text-center font-medium">Proxy</th>\n', '')
import re
text = re.sub(r'\n\s*<td class="px-5 py-5 text-center">\n\s*@if \(\$user->dataHubProxies->isNotEmpty\(\)\).*?\n\s*</td>', '', text, flags=re.S)
text = text.replace('{{ 8 + $products->count() }}', '{{ 7 + $products->count() }}')
p.write_text(text, encoding='utf-8')
