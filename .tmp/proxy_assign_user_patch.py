from pathlib import Path

# UserAccessService: remove proxy repo/deps/sync
p = Path('app/Services/User/UserAccessService.php')
text = p.read_text(encoding='utf-8')
text = text.replace("use App\\Repositories\\Proxy\\DataHubProxyRepository;\n", "")
text = text.replace("        private readonly UserRepository $users,\n        private readonly DataHubProxyRepository $proxies,\n", "        private readonly UserRepository $users,\n")
text = text.replace("\n    public function activeProxies(): Collection\n    {\n        return $this->proxies->activeOrdered();\n    }\n", "\n")
text = text.replace("\n        $this->syncProxyAccess($user, $data['selectedProxies'] ?? []);\n", "\n")
text = text.replace("\n        $this->syncProxyAccess($targetUser, $data['selectedProxies'] ?? []);\n", "\n")
start = text.find("    public function syncProxyAccess(User $user, array $proxyIds): void")
if start != -1:
    end = text.find("    /**\n     * @return array<string, array{label: string, description?: string, model?: string}>\n     */", start)
    text = text[:start] + text[end:]
p.write_text(text, encoding='utf-8')

# AddUser remove selectedProxies
for path in ['app/Livewire/Modals/Admin/AddUser.php','app/Livewire/Modals/Admin/EditUser.php']:
    p = Path(path)
    text = p.read_text(encoding='utf-8')
    text = text.replace("\n    /** @var array<int, int|string> */\n    public array $selectedProxies = [];\n", "")
    text = text.replace("            'selectedProxies' => ['array'],\n            'selectedProxies.*' => ['integer', 'exists:data_hub_proxy,id'],\n", "")
    text = text.replace("                'selected_proxies' => $validated['selectedProxies'] ?? [],\n", "")
    text = text.replace("            'proxies' => $service->activeProxies(),\n", "")
    text = text.replace("            'selectedProxies',\n", "")
    p.write_text(text, encoding='utf-8')

# EditUser remove selectedProxies state assignment
p = Path('app/Livewire/Modals/Admin/EditUser.php')
text = p.read_text(encoding='utf-8')
text = text.replace("            ->with(['products', 'dataHubProxies', 'vertexApiCredential'])\n", "            ->with(['products', 'vertexApiCredential'])\n")
text = text.replace("        $this->selectedProxies = $user->dataHubProxies->pluck('id')->map(fn ($id) => (int) $id)->all();\n", "")
p.write_text(text, encoding='utf-8')

# Remove Proxy access sections from admin views
for path in ['resources/views/livewire/modals/admin/add-user.blade.php','resources/views/livewire/modals/admin/edit-user.blade.php']:
    p = Path(path)
    text = p.read_text(encoding='utf-8')
    start = text.find('<section class="rounded-xl border border-slate-200 bg-slate-50 p-4">\n                            <h3 class="text-sm font-bold text-slate-900">Proxy access</h3>')
    if start != -1:
        end = text.find('                        </section>', start)
        end = text.find('\n', end) + 1
        text = text[:start] + text[end:]
    p.write_text(text, encoding='utf-8')

# Proxy modal add assigned user field
p = Path('app/Livewire/Modals/Proxy/EditProxyItem.php')
text = p.read_text(encoding='utf-8')
text = text.replace("use App\\Models\\DataHubProxyItem;\n", "use App\\Models\\DataHubProxyItem;\nuse App\\Models\\User;\n")
text = text.replace("    public string $note = '';\n", "    public string $note = '';\n\n    public ?int $assignedUserId = null;\n")
text = text.replace("        $this->note = '';\n", "        $this->note = '';\n        $this->assignedUserId = null;\n")
text = text.replace("        $this->note = (string) ($item->note ?? '');\n", "        $this->note = (string) ($item->note ?? '');\n        $this->assignedUserId = $item->assigned_user_id;\n")
text = text.replace("        $validated = $this->validate([\n            'note' => ['nullable', 'string', 'max:5000'],\n        ]);", "        $validated = $this->validate([\n            'note' => ['nullable', 'string', 'max:5000'],\n            'assignedUserId' => ['nullable', 'integer', 'exists:users,id'],\n        ]);")
text = text.replace("        $item->update([\n            'note' => $validated['note'] ?? null,\n        ]);", "        $item->update([\n            'note' => $validated['note'] ?? null,\n            'assigned_user_id' => $validated['assignedUserId'] ?? null,\n        ]);")
text = text.replace("    public function render(): View\n    {\n        return view('livewire.modals.proxy.edit-proxy-item');\n    }\n}", "    public function render(): View\n    {\n        return view('livewire.modals.proxy.edit-proxy-item', [\n            'users' => User::query()->orderBy('name')->get(['id', 'name', 'email']),\n        ]);\n    }\n}")
p.write_text(text, encoding='utf-8')

# Proxy modal view add user select
p = Path('resources/views/livewire/modals/proxy/edit-proxy-item.blade.php')
text = p.read_text(encoding='utf-8')
text = text.replace("                        <label for=\"proxyNote\" class=\"text-sm font-bold text-slate-900\">Note</label>\n                        <textarea id=\"proxyNote\" wire:model=\"note\" rows=\"7\" class=\"mt-2 w-full rounded-xl border-slate-200 text-sm text-slate-950 shadow-sm focus:border-cyan-500 focus:ring-cyan-500\" placeholder=\"Nhap note cho proxy nay...\"></textarea>\n                        @error('note') <p class=\"mt-2 text-sm text-red-600\">{{ $message }}</p> @enderror", "                        <label for=\"proxyAssignedUser\" class=\"text-sm font-bold text-slate-900\">User</label>\n                        <select id=\"proxyAssignedUser\" wire:model=\"assignedUserId\" class=\"mt-2 w-full rounded-xl border-slate-200 text-sm text-slate-950 shadow-sm focus:border-cyan-500 focus:ring-cyan-500\">\n                            <option value=\"\">-- Chua gan user --</option>\n                            @foreach ($users as $user)\n                                <option value=\"{{ $user->id }}\">{{ $user->name }} @if($user->email) ({{ $user->email }}) @endif</option>\n                            @endforeach\n                        </select>\n                        @error('assignedUserId') <p class=\"mt-2 text-sm text-red-600\">{{ $message }}</p> @enderror\n\n                        <label for=\"proxyNote\" class=\"mt-4 block text-sm font-bold text-slate-900\">Note</label>\n                        <textarea id=\"proxyNote\" wire:model=\"note\" rows=\"7\" class=\"mt-2 w-full rounded-xl border-slate-200 text-sm text-slate-950 shadow-sm focus:border-cyan-500 focus:ring-cyan-500\" placeholder=\"Nhap note cho proxy nay...\"></textarea>\n                        @error('note') <p class=\"mt-2 text-sm text-red-600\">{{ $message }}</p> @enderror")
p.write_text(text, encoding='utf-8')

# Proxy page show assigned user column
p = Path('resources/views/livewire/pages/proxy/index.blade.php')
text = p.read_text(encoding='utf-8')
text = text.replace('<th class="px-4 py-3 text-left font-semibold">Port</th>\n                                            <th class="px-4 py-3 text-left font-semibold">Note</th>', '<th class="px-4 py-3 text-left font-semibold">Port</th>\n                                            <th class="px-4 py-3 text-left font-semibold">User</th>\n                                            <th class="px-4 py-3 text-left font-semibold">Note</th>')
text = text.replace('<td class="px-4 py-3 text-slate-700">{{ 9801 + $loop->index }}</td>\n                                                <td class="px-4 py-3 text-slate-700">{{ $item->note ?: \'-\' }}</td>', '<td class="px-4 py-3 text-slate-700">{{ 9801 + $loop->index }}</td>\n                                                <td class="px-4 py-3 text-slate-700">{{ $item->assignedUser?->name ?: \'-\' }}</td>\n                                                <td class="px-4 py-3 text-slate-700">{{ $item->note ?: \'-\' }}</td>')
text = text.replace('                                                <td colspan="8" class="px-4 py-10 text-center text-sm text-slate-400">', '                                                <td colspan="9" class="px-4 py-10 text-center text-sm text-slate-400">')
p.write_text(text, encoding='utf-8')
