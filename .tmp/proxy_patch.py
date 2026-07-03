from pathlib import Path

def replace(path, old, new):
    p = Path(path)
    text = p.read_text(encoding='utf-8-sig')
    if old not in text:
        raise SystemExit(f'Missing pattern in {path}: {old[:80]!r}')
    p.write_text(text.replace(old, new, 1), encoding='utf-8')

# User model relation
replace('app/Models/User.php', "    public function products(): BelongsToMany\n    {\n        return $this->belongsToMany(Product::class)->withTimestamps();\n    }\n", "    public function products(): BelongsToMany\n    {\n        return $this->belongsToMany(Product::class)->withTimestamps();\n    }\n\n    /**\n     * Proxy sources this user can view.\n     */\n    public function dataHubProxies(): BelongsToMany\n    {\n        return $this->belongsToMany(DataHubProxy::class, 'data_hub_proxy_user')->withTimestamps();\n    }\n")

# User repository eager load proxies
replace('app/Repositories/User/UserRepository.php', "                'products' => fn ($query) => $query->where('is_active', true),\n", "                'products' => fn ($query) => $query->where('is_active', true),\n                'dataHubProxies' => fn ($query) => $query->where('is_active', true),\n")

# Product registry import and entry
replace('app/Support/ProductRegistry.php', "use App\\Livewire\\Pages\\OrnamentEtsy\\ListOrnamentEtsy;\n", "use App\\Livewire\\Pages\\OrnamentEtsy\\ListOrnamentEtsy;\nuse App\\Livewire\\Pages\\Proxy\\Index as ProxyPage;\n")
replace('app/Support/ProductRegistry.php', "            [\n                'name' => 'YTrends',\n", "            [\n                'name' => 'Proxy',\n                'slug' => 'proxy',\n                'description' => 'Monitor proxy sources and changes.',\n                'route_name' => 'offorest.products.proxy',\n                'path' => 'proxy',\n                'component' => ProxyPage::class,\n                'sort_order' => 45,\n                'is_active' => true,\n            ],\n            [\n                'name' => 'YTrends',\n")

# Navigation include proxy in page group
replace('resources/views/livewire/layout/navigation.blade.php', "$pageProducts = $products->whereIn('slug', ['ornament', 'ornament-etsy', 'ornament-amazon-2', 'sticker']);", "$pageProducts = $products->whereIn('slug', ['ornament', 'ornament-etsy', 'ornament-amazon-2', 'sticker', 'proxy']);")

# Scheduler
replace('routes/console.php', "    Schedule::command('offorest:generate-listing-metadata')\n        ->everyFiveMinutes()\n        ->withoutOverlapping();\n", "    Schedule::command('offorest:generate-listing-metadata')\n        ->everyFiveMinutes()\n        ->withoutOverlapping();\n\n    Schedule::command('offorest:refresh-proxy-data')\n        ->cron('*/'.max(1, (int) env('OFFOREST_PROXY_REFRESH_EVERY_MINUTES', 5)).' * * * *')\n        ->withoutOverlapping();\n")

# UserAccessService imports/method/sync
replace('app/Services/User/UserAccessService.php', "use App\\Repositories\\Product\\ProductRepository;\n", "use App\\Repositories\\Product\\ProductRepository;\nuse App\\Repositories\\Proxy\\DataHubProxyRepository;\n")
replace('app/Services/User/UserAccessService.php', "        private readonly ProductRepository $products,\n        private readonly UserRepository $users,\n", "        private readonly ProductRepository $products,\n        private readonly UserRepository $users,\n        private readonly DataHubProxyRepository $proxies,\n")
replace('app/Services/User/UserAccessService.php', "    public function users(): Collection\n    {\n        return $this->users->allWithActiveProductsOrderedByName();\n    }\n", "    public function users(): Collection\n    {\n        return $this->users->allWithActiveProductsOrderedByName();\n    }\n\n    public function activeProxies(): Collection\n    {\n        return $this->proxies->activeOrdered();\n    }\n")
replace('app/Services/User/UserAccessService.php', "        $this->syncAiProviders(\n            user: $user,\n            providerKeys: $data['selectedAiProviders'] ?? [],\n            preferredProviderKey: $data['preferredAiProvider'] ?? null,\n        );\n", "        $this->syncAiProviders(\n            user: $user,\n            providerKeys: $data['selectedAiProviders'] ?? [],\n            preferredProviderKey: $data['preferredAiProvider'] ?? null,\n        );\n\n        $this->syncProxyAccess($user, $data['selectedProxies'] ?? []);\n", )
replace('app/Services/User/UserAccessService.php', "        $this->syncAiProviders(\n            user: $targetUser,\n            providerKeys: $data['selectedAiProviders'] ?? [],\n            preferredProviderKey: $data['preferredAiProvider'] ?? null,\n        );\n", "        $this->syncAiProviders(\n            user: $targetUser,\n            providerKeys: $data['selectedAiProviders'] ?? [],\n            preferredProviderKey: $data['preferredAiProvider'] ?? null,\n        );\n\n        $this->syncProxyAccess($targetUser, $data['selectedProxies'] ?? []);\n")
replace('app/Services/User/UserAccessService.php', "    /**\n     * @return array<string, array{label: string, description?: string, model?: string}>\n     */\n", "    public function syncProxyAccess(User $user, array $proxyIds): void\n    {\n        $validProxyIds = $this->activeProxies()->pluck('id')->map(fn ($id) => (int) $id)->all();\n        $selectedProxyIds = collect($proxyIds)\n            ->map(fn ($id) => (int) $id)\n            ->filter(fn (int $id): bool => in_array($id, $validProxyIds, true))\n            ->unique()\n            ->all();\n\n        $user->dataHubProxies()->sync($selectedProxyIds);\n    }\n\n    /**\n     * @return array<string, array{label: string, description?: string, model?: string}>\n     */\n")
