from pathlib import Path

def replace(path, old, new):
    p=Path(path); text=p.read_text(encoding='utf-8-sig')
    if old not in text: raise SystemExit(f'Missing {old[:80]!r} in {path}')
    p.write_text(text.replace(old,new,1), encoding='utf-8')

for path in ['app/Livewire/Modals/Admin/AddUser.php','app/Livewire/Modals/Admin/EditUser.php']:
    replace(path, "    /** @var array<int, string> */\n    public array $selectedAiProviders = [];\n", "    /** @var array<int, int|string> */\n    public array $selectedProxies = [];\n\n    /** @var array<int, string> */\n    public array $selectedAiProviders = [];\n")
    replace(path, "            'selectedProducts' => ['array'],\n            'selectedProducts.*' => ['integer', 'exists:products,id'],\n", "            'selectedProducts' => ['array'],\n            'selectedProducts.*' => ['integer', 'exists:products,id'],\n            'selectedProxies' => ['array'],\n            'selectedProxies.*' => ['integer', 'exists:data_hub_proxy,id'],\n")
    replace(path, "                'selected_products' => $validated['selectedProducts'] ?? [],\n", "                'selected_products' => $validated['selectedProducts'] ?? [],\n                'selected_proxies' => $validated['selectedProxies'] ?? [],\n")
    replace(path, "            'products' => $service->activeProducts(),\n", "            'products' => $service->activeProducts(),\n            'proxies' => $service->activeProxies(),\n")
    replace(path, "            'selectedProducts',\n", "            'selectedProducts',\n            'selectedProxies',\n")

replace('app/Livewire/Modals/Admin/AddUser.php', "                'selectedProducts' => $validated['selectedProducts'] ?? [],\n", "                'selectedProducts' => $validated['selectedProducts'] ?? [],\n                'selectedProxies' => $validated['selectedProxies'] ?? [],\n")
replace('app/Livewire/Modals/Admin/EditUser.php', "        $this->selectedProducts = $user->products->pluck('id')->map(fn ($id) => (int) $id)->all();\n", "        $this->selectedProducts = $user->products->pluck('id')->map(fn ($id) => (int) $id)->all();\n        $this->selectedProxies = $user->dataHubProxies->pluck('id')->map(fn ($id) => (int) $id)->all();\n")
replace('app/Livewire/Modals/Admin/EditUser.php', "                'selectedProducts' => $validated['selectedProducts'] ?? [],\n", "                'selectedProducts' => $validated['selectedProducts'] ?? [],\n                'selectedProxies' => $validated['selectedProxies'] ?? [],\n")
