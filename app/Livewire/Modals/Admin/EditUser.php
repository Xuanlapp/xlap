<?php

namespace App\Livewire\Modals\Admin;

use App\Models\User;
use App\Models\UserApiCredential;
use App\Services\Logging\ActivityLogService;
use App\Services\User\UserAccessService;
use App\Support\Traits\BuildsVertexCredentialPayload;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Component;

class EditUser extends Component
{
    use BuildsVertexCredentialPayload;

    public bool $isOpen = false;

    public ?int $userId = null;

    public string $name = '';

    public string $username = '';

    public string $email = '';

    public string $password = '';

    public string $status = 'active';

    public string $role = 'user';

    public bool $is_admin = false;

    public bool $can_generate_amazon_listing = false;

    public bool $can_generate_etsy_listing = false;

    public bool $can_access_wali = false;

    /** @var array<int, int|string> */
    public array $selectedProducts = [];

    /** @var array<int, string> */
    public array $selectedAiProviders = [];

    public ?string $preferredAiProvider = null;

    public string $vertexMode = 'keep';

    public string $vertexJson = '';

    public string $vertexLocation = 'global';

    public ?int $vertexCopyUserId = null;

    public ?string $currentVertexLabel = null;

    public string $v98StoreMode = 'keep';

    public string $v98StoreApiKey = '';

    public ?int $v98StoreCopyUserId = null;

    public ?string $currentV98StoreLabel = null;

    /**
     * Open this modal through the shared openModal event pattern.
     *
     * @param  array<string, mixed>  $arguments
     */
    #[On('openModal')]
    public function openModal(string $component, array $arguments = []): void
    {
        if ($component !== 'modals.admin.edit-user') {
            return;
        }

        $this->open((int) ($arguments['userId'] ?? 0));
    }

    public function open(int $userId): void
    {
        $user = User::query()
            ->with(['products', 'vertexApiCredential'])
            ->with('aiProviders')
            ->findOrFail($userId);

        $this->resetValidation();
        $this->userId = $user->id;
        $this->name = $user->name;
        $this->username = $user->username ?: Str::of($user->name)->ascii()->lower()->replaceMatches('/[^a-z0-9_-]+/', '_')->trim('_')->toString();
        $this->email = $user->email;
        $this->password = '';
        $this->status = $user->status ?: 'active';
        $this->role = $user->role ?: ((bool) $user->is_admin ? 'admin' : 'user');
        $this->is_admin = (bool) $user->is_admin;
        $this->can_generate_amazon_listing = (bool) $user->can_generate_amazon_listing;
        $this->can_generate_etsy_listing = (bool) $user->can_generate_etsy_listing;
        $this->can_access_wali = (bool) $user->can_access_wali;
        $this->selectedProducts = $user->products->pluck('id')->map(fn ($id) => (int) $id)->all();
        $this->selectedAiProviders = $user->aiProviders
            ->where('is_enabled', true)
            ->pluck('provider_key')
            ->values()
            ->all();
        $this->preferredAiProvider = $user->activeAiProviderKey();
        $this->vertexMode = 'keep';
        $this->vertexJson = '';
        $this->vertexLocation = $user->vertexApiCredential?->location ?: 'global';
        $this->vertexCopyUserId = null;
        $this->currentVertexLabel = $user->vertexApiCredential
            ? $user->vertexApiCredential->client_email.' | '.($user->vertexApiCredential->project_id ?: 'no project_id')
            : null;
        $this->v98StoreMode = 'keep';
        $this->v98StoreApiKey = '';
        $this->v98StoreCopyUserId = null;
        $this->currentV98StoreLabel = $this->v98StoreCredentialLabel($this->activeV98StoreCredentialForUser($user->id));
        $this->isOpen = true;
    }

    public function close(): void
    {
        $this->isOpen = false;
    }

    /**
     * Update user account, access, and optional API credentials.
     */
    public function save(): void
    {
        if (! $this->userId) {
            return;
        }

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'alpha_dash', 'max:255', Rule::unique('users', 'username')->ignore($this->userId)],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->userId)],
            'password' => ['nullable', 'string', 'min:8'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'role' => ['required', Rule::in(['user', 'manager', 'admin'])],
            'is_admin' => ['boolean'],
            'can_generate_amazon_listing' => ['boolean'],
            'can_generate_etsy_listing' => ['boolean'],
            'can_access_wali' => ['boolean'],
            'selectedProducts' => ['array'],
            'selectedProducts.*' => ['integer', 'exists:products,id'],
            'selectedAiProviders' => ['array'],
            'selectedAiProviders.*' => ['string', Rule::in(array_keys($this->aiProviderOptions()))],
            'preferredAiProvider' => ['nullable', 'string', Rule::in(array_keys($this->aiProviderOptions()))],
            'vertexMode' => ['required', Rule::in(['keep', 'new', 'copy', 'remove'])],
            'vertexJson' => ['nullable', 'string', 'max:30000', 'required_if:vertexMode,new'],
            'vertexLocation' => ['nullable', 'string', 'max:100'],
            'vertexCopyUserId' => ['nullable', 'integer', 'exists:users,id', 'required_if:vertexMode,copy'],
            'v98StoreMode' => ['required', Rule::in(['keep', 'new', 'copy', 'remove'])],
            'v98StoreApiKey' => ['nullable', 'string', 'max:500', 'required_if:v98StoreMode,new'],
            'v98StoreCopyUserId' => ['nullable', 'integer', 'exists:users,id', 'required_if:v98StoreMode,copy'],
        ]);

        $this->ensureSingleMarketplace();

        $user = User::findOrFail($this->userId);
        $vertexCredentialPayload = $this->validatedVertexCredentialPayload();
        $v98StoreCredentialPayload = $this->validatedV98StoreCredentialPayload();
        $hasCurrentV98StoreCredential = $this->activeV98StoreCredentialForUser($user->id) !== null;
        $validated = $this->normalizedAiProviderPayload(
            validated: $validated,
            hasVertexCredential: $vertexCredentialPayload !== null || ($this->vertexMode === 'keep' && $user->vertexApiCredential !== null),
            removedVertexCredential: $this->vertexMode === 'remove',
            hasV98StoreCredential: $v98StoreCredentialPayload !== null || ($this->v98StoreMode === 'keep' && $hasCurrentV98StoreCredential),
            removedV98StoreCredential: $this->v98StoreMode === 'remove',
        );

        DB::transaction(function () use ($user, $validated, $vertexCredentialPayload, $v98StoreCredentialPayload): void {
            app(UserAccessService::class)->updateUser($user, $validated);

            if ($this->vertexMode === 'remove') {
                $user->vertexApiCredential()->update(['is_active' => false]);
            }

            if ($vertexCredentialPayload !== null) {
                $user->vertexApiCredential()->update(['is_active' => false]);
                $user->vertexApiCredential()->create($vertexCredentialPayload);
            }

            if ($this->v98StoreMode === 'remove') {
                UserApiCredential::query()
                    ->where('provider_key', 'v98store')
                    ->where('user_id', $user->id)
                    ->where('is_active', true)
                    ->update(['is_active' => false]);
            }

            if ($v98StoreCredentialPayload !== null) {
                UserApiCredential::query()
                    ->where('provider_key', 'v98store')
                    ->where('user_id', $user->id)
                    ->where('is_active', true)
                    ->update(['is_active' => false]);

                UserApiCredential::query()->create([
                    ...$v98StoreCredentialPayload,
                    'user_id' => $user->id,
                ]);
            }
        });

        app(ActivityLogService::class)->record(
            event: 'admin.user_updated',
            description: 'Admin updated a user account.',
            properties: [
                'target_user_id' => $user->id,
                'email' => $validated['email'],
                'username' => $validated['username'],
                'status' => $validated['status'],
                'role' => $validated['role'],
                'is_admin' => (bool) ($validated['is_admin'] ?? false) || $validated['role'] === 'admin',
                'can_generate_amazon_listing' => (bool) ($validated['can_generate_amazon_listing'] ?? false),
                'can_generate_etsy_listing' => (bool) ($validated['can_generate_etsy_listing'] ?? false),
                'can_access_wali' => (bool) ($validated['can_access_wali'] ?? false),
                'selected_products' => $validated['selectedProducts'] ?? [],
                'selected_ai_providers' => $validated['selectedAiProviders'] ?? [],
                'preferred_ai_provider' => $validated['preferredAiProvider'] ?? null,
                'vertex_mode' => $validated['vertexMode'],
                'v98store_mode' => $validated['v98StoreMode'],
            ],
            actor: auth()->user(),
            actorType: 'admin',
        );

        $this->isOpen = false;
        $this->dispatch('users-updated');
        $this->dispatch('toast', type: 'success', title: 'Successfully saved!', message: 'Da cap nhat user.');
    }

    public function render(): View
    {
        $service = app(UserAccessService::class);

        return view('livewire.modals.admin.edit-user', [
            'products' => $service->activeProducts(),
            'aiProviderOptions' => $this->aiProviderOptions(),
            'vertexCredentialUsers' => User::query()
                ->whereHas('vertexApiCredential')
                ->when($this->userId, fn ($query) => $query->whereKeyNot($this->userId))
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
            'v98StoreCredentialUsers' => User::query()
                ->whereIn('id', UserApiCredential::query()
                    ->select('user_id')
                    ->where('provider_key', 'v98store')
                    ->where('is_active', true)
                    ->whereNotNull('user_id')
                    ->when($this->userId, fn ($query) => $query->where('user_id', '!=', $this->userId)))
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
        ]);
    }

    private function ensureSingleMarketplace(): void
    {
        if ($this->can_generate_amazon_listing && $this->can_generate_etsy_listing) {
            throw ValidationException::withMessages([
                'can_generate_amazon_listing' => 'Moi user chi duoc chon Amazon hoac Etsy, khong duoc chon ca hai.',
                'can_generate_etsy_listing' => 'Moi user chi duoc chon Amazon hoac Etsy, khong duoc chon ca hai.',
            ]);
        }
    }

    /**
     * @return array<string, array{label: string, description?: string, model?: string}>
     */
    private function aiProviderOptions(): array
    {
        return app(UserAccessService::class)->aiProviderOptions();
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function normalizedAiProviderPayload(
        array $validated,
        bool $hasVertexCredential,
        bool $removedVertexCredential,
        bool $hasV98StoreCredential,
        bool $removedV98StoreCredential,
    ): array
    {
        $providers = collect($validated['selectedAiProviders'] ?? []);

        if ($hasVertexCredential && ! $removedVertexCredential) {
            $providers->push('vertex');
        }

        if ($hasV98StoreCredential && ! $removedV98StoreCredential) {
            $providers->push('v98store');
        }

        if (! $hasVertexCredential && ! $removedVertexCredential && $providers->contains('vertex')) {
            throw ValidationException::withMessages([
                'selectedAiProviders' => 'Muon cap Vertex provider thi user phai co Vertex key active.',
            ]);
        }

        if ($removedVertexCredential) {
            $providers = $providers->reject(fn (string $providerKey): bool => $providerKey === 'vertex');
        }

        if ($removedV98StoreCredential) {
            $providers = $providers->reject(fn (string $providerKey): bool => $providerKey === 'v98store');
        }

        $validated['selectedAiProviders'] = $providers
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (! in_array($validated['preferredAiProvider'] ?? null, $validated['selectedAiProviders'], true)) {
            $validated['preferredAiProvider'] = $validated['selectedAiProviders'][0] ?? null;
        }

        return $validated;
    }

    /**
     * @return array{function_key: string, project_id: string|null, location: string, client_email: string|null, private_key: string|null, credentials_json: array<string, mixed>|null, is_active: bool}|null
     */
    private function validatedVertexCredentialPayload(): ?array
    {
        if ($this->vertexMode === 'keep' || $this->vertexMode === 'remove') {
            return null;
        }

        if ($this->vertexMode === 'copy') {
            return $this->copiedImageVertexCredentialPayload($this->vertexCopyUserId, $this->vertexLocation);
        }

        return [
            'function_key' => 'image_generation',
            ...$this->vertexCredentialPayloadFromJson(
                json: $this->vertexJson,
                location: $this->normalizedLocation($this->vertexLocation),
            ),
            'is_active' => true,
        ];
    }

    /**
     * @return array{provider_key: string, name: string, key_api: string, is_active: bool}|null
     */
    private function validatedV98StoreCredentialPayload(): ?array
    {
        if ($this->v98StoreMode === 'keep' || $this->v98StoreMode === 'remove') {
            return null;
        }

        if ($this->v98StoreMode === 'copy') {
            $sourceCredential = UserApiCredential::query()
                ->where('provider_key', 'v98store')
                ->where('is_active', true)
                ->where('user_id', $this->v98StoreCopyUserId)
                ->latest('id')
                ->first();

            if (! $sourceCredential) {
                throw ValidationException::withMessages([
                    'v98StoreCopyUserId' => 'User nay chua co v98Store API key active.',
                ]);
            }

            return [
                'provider_key' => 'v98store',
                'name' => $sourceCredential->name ?: 'v98Store',
                'key_api' => $sourceCredential->key_api,
                'is_active' => true,
            ];
        }

        $apiKey = trim($this->v98StoreApiKey);

        if ($apiKey === '') {
            throw ValidationException::withMessages([
                'v98StoreApiKey' => 'Vui long nhap v98Store API key.',
            ]);
        }

        return [
            'provider_key' => 'v98store',
            'name' => 'v98Store',
            'key_api' => $apiKey,
            'is_active' => true,
        ];
    }

    private function activeV98StoreCredentialForUser(int $userId): ?UserApiCredential
    {
        return UserApiCredential::query()
            ->where('provider_key', 'v98store')
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->latest('id')
            ->first();
    }

    private function v98StoreCredentialLabel(?UserApiCredential $credential): ?string
    {
        if (! $credential) {
            return null;
        }

        try {
            $keyTail = substr($credential->key_api, -4);
        } catch (\Throwable) {
            $keyTail = 'error';
        }

        return ($credential->name ?: 'v98Store').' | ...'.$keyTail;
    }
}
