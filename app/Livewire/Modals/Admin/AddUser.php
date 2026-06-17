<?php

namespace App\Livewire\Modals\Admin;

use App\Models\User;
use App\Models\UserApiCredential;
use App\Services\Logging\ActivityLogService;
use App\Services\User\UserAccessService;
use App\Support\Traits\BuildsVertexCredentialPayload;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Component;

class AddUser extends Component
{
    use BuildsVertexCredentialPayload;

    public bool $isOpen = false;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $status = 'active';

    public bool $is_admin = false;

    public bool $can_generate_amazon_listing = false;

    public bool $can_generate_etsy_listing = false;

    /** @var array<int, int|string> */
    public array $selectedProducts = [];

    /** @var array<int, string> */
    public array $selectedAiProviders = [];

    public ?string $preferredAiProvider = null;

    public string $vertexMode = 'none';

    public string $vertexJson = '';

    public string $vertexLocation = 'global';

    public ?int $vertexCopyUserId = null;

    public string $v98StoreMode = 'none';

    public string $v98StoreApiKey = '';

    public ?int $v98StoreCopyUserId = null;

    /**
     * Open this modal through the shared openModal event pattern.
     *
     * @param  array<string, mixed>  $arguments
     */
    #[On('openModal')]
    public function openModal(string $component, array $arguments = []): void
    {
        if ($component !== 'modals.admin.add-user') {
            return;
        }

        $this->resetForm();
        $this->isOpen = true;
    }

    public function close(): void
    {
        $this->isOpen = false;
    }

    /**
     * Create a user account and optional API credentials.
     */
    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::min(12)->mixedCase()->numbers()],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'is_admin' => ['boolean'],
            'can_generate_amazon_listing' => ['boolean'],
            'can_generate_etsy_listing' => ['boolean'],
            'selectedProducts' => ['array'],
            'selectedProducts.*' => ['integer', 'exists:products,id'],
            'selectedAiProviders' => ['array'],
            'selectedAiProviders.*' => ['string', Rule::in(array_keys($this->aiProviderOptions()))],
            'preferredAiProvider' => ['nullable', 'string', Rule::in(array_keys($this->aiProviderOptions()))],
            'vertexMode' => ['required', Rule::in(['none', 'new', 'copy'])],
            'vertexJson' => ['nullable', 'string', 'max:30000', 'required_if:vertexMode,new'],
            'vertexLocation' => ['nullable', 'string', 'max:100'],
            'vertexCopyUserId' => ['nullable', 'integer', 'exists:users,id', 'required_if:vertexMode,copy'],
            'v98StoreMode' => ['required', Rule::in(['none', 'new', 'copy'])],
            'v98StoreApiKey' => ['nullable', 'string', 'max:500', 'required_if:v98StoreMode,new'],
            'v98StoreCopyUserId' => ['nullable', 'integer', 'exists:users,id', 'required_if:v98StoreMode,copy'],
        ]);

        $this->ensureSingleMarketplace();

        $vertexCredentialPayload = $this->validatedVertexCredentialPayload();
        $v98StoreCredentialPayload = $this->validatedV98StoreCredentialPayload();
        $validated = $this->normalizedAiProviderPayload(
            validated: $validated,
            hasVertexCredential: $vertexCredentialPayload !== null,
            hasV98StoreCredential: $v98StoreCredentialPayload !== null,
        );

        DB::transaction(function () use ($validated, $vertexCredentialPayload, $v98StoreCredentialPayload): void {
            $user = app(UserAccessService::class)->createUser($validated);

            if ($vertexCredentialPayload !== null) {
                $user->vertexApiCredential()->create($vertexCredentialPayload);
            }

            if ($v98StoreCredentialPayload !== null) {
                UserApiCredential::query()->create([
                    ...$v98StoreCredentialPayload,
                    'user_id' => $user->id,
                ]);
            }
        });

        app(ActivityLogService::class)->record(
            event: 'admin.user_created',
            description: 'Admin created a user account.',
            properties: [
                'email' => $validated['email'],
                'is_admin' => (bool) ($validated['is_admin'] ?? false),
                'can_generate_amazon_listing' => (bool) ($validated['can_generate_amazon_listing'] ?? false),
                'can_generate_etsy_listing' => (bool) ($validated['can_generate_etsy_listing'] ?? false),
                'selected_products' => $validated['selectedProducts'] ?? [],
                'selected_ai_providers' => $validated['selectedAiProviders'] ?? [],
                'preferred_ai_provider' => $validated['preferredAiProvider'] ?? null,
                'vertex_mode' => $validated['vertexMode'],
                'vertex_configured' => $vertexCredentialPayload !== null,
                'v98store_mode' => $validated['v98StoreMode'],
                'v98store_configured' => $v98StoreCredentialPayload !== null,
            ],
            actor: auth()->user(),
            actorType: 'admin',
        );

        $this->isOpen = false;
        $this->resetForm();
        $this->dispatch('users-updated');
        $this->dispatch('toast', type: 'success', title: 'Successfully saved!', message: 'Da tao user moi.');
    }

    public function render(): View
    {
        $service = app(UserAccessService::class);

        return view('livewire.modals.admin.add-user', [
            'products' => $service->activeProducts(),
            'aiProviderOptions' => $this->aiProviderOptions(),
            'vertexCredentialUsers' => User::query()
                ->whereHas('vertexApiCredential')
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
            'v98StoreCredentialUsers' => User::query()
                ->whereIn('id', UserApiCredential::query()
                    ->select('user_id')
                    ->where('provider_key', 'v98store')
                    ->where('is_active', true)
                    ->whereNotNull('user_id'))
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
        ]);
    }

    private function resetForm(): void
    {
        $this->reset([
            'name',
            'email',
            'password',
            'is_admin',
            'can_generate_amazon_listing',
            'can_generate_etsy_listing',
            'selectedProducts',
            'selectedAiProviders',
            'preferredAiProvider',
            'vertexMode',
            'vertexJson',
            'vertexLocation',
            'vertexCopyUserId',
            'v98StoreMode',
            'v98StoreApiKey',
            'v98StoreCopyUserId',
        ]);

        $this->status = 'active';
        $this->vertexMode = 'none';
        $this->vertexLocation = 'global';
        $this->v98StoreMode = 'none';
        $this->selectedAiProviders = [];
        $this->preferredAiProvider = null;
        $this->resetValidation();
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
    private function normalizedAiProviderPayload(array $validated, bool $hasVertexCredential, bool $hasV98StoreCredential): array
    {
        $providers = collect($validated['selectedAiProviders'] ?? []);

        if ($hasVertexCredential) {
            $providers->push('vertex');
        }

        if ($hasV98StoreCredential) {
            $providers->push('v98store');
        }

        if (! $hasVertexCredential && $providers->contains('vertex')) {
            throw ValidationException::withMessages([
                'selectedAiProviders' => 'Muon cap Vertex provider thi can add new key hoac copy key Vertex cho user.',
            ]);
        }

        $validated['selectedAiProviders'] = $providers
            ->filter()
            ->unique()
            ->values()
            ->all();

        $validated['preferredAiProvider'] = $validated['preferredAiProvider'] ?: ($validated['selectedAiProviders'][0] ?? null);

        return $validated;
    }

    /**
     * @return array{function_key: string, project_id: string|null, location: string, client_email: string|null, private_key: string|null, credentials_json: array<string, mixed>|null, is_active: bool}|null
     */
    private function validatedVertexCredentialPayload(): ?array
    {
        if ($this->vertexMode === 'none') {
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
        if ($this->v98StoreMode === 'none') {
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
}
