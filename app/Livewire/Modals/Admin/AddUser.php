<?php

namespace App\Livewire\Modals\Admin;

use App\Mail\Admin\UserLoginCredentialsMail;
use App\Models\User;
use App\Models\UserApiCredential;
use App\Services\Logging\ActivityLogService;
use App\Services\User\UserAccessService;
use App\Support\Traits\BuildsVertexCredentialPayload;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Component;
use Throwable;

class AddUser extends Component
{
    use BuildsVertexCredentialPayload;

    public bool $isOpen = false;

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


    public function updatedCanGenerateAmazonListing(bool $value): void
    {
        if ($value) {
            $this->can_generate_etsy_listing = false;
        }
    }

    public function updatedCanGenerateEtsyListing(bool $value): void
    {
        if ($value) {
            $this->can_generate_amazon_listing = false;
        }
    }


    public function generatePassword(): void
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
        $password = '';

        for ($index = 0; $index < 8; $index++) {
            $password .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        $this->password = $password;
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
            'username' => ['required', 'string', 'alpha_dash', 'max:255', 'unique:users,username'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
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
        $validated['is_admin'] = ((($validated['role'] ?? 'user') === 'admin') || (bool) ($validated['is_admin'] ?? false));

        $createdUser = DB::transaction(function () use ($validated, $vertexCredentialPayload, $v98StoreCredentialPayload): User {
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

            return $user->refresh();
        });

        $mailSent = $this->sendLoginCredentialsMail($createdUser, $validated['password']);

        app(ActivityLogService::class)->record(
            event: 'admin.user_created',
            description: 'Admin created a user account.',
            properties: [
                'email' => $validated['email'],
                'username' => $validated['username'],
                'is_admin' => (bool) ($validated['is_admin'] ?? false),
                'can_generate_amazon_listing' => (bool) ($validated['can_generate_amazon_listing'] ?? false),
                'can_generate_etsy_listing' => (bool) ($validated['can_generate_etsy_listing'] ?? false),
                'can_access_wali' => (bool) ($validated['can_access_wali'] ?? false),
                'selected_products' => $validated['selectedProducts'] ?? [],
                'selected_ai_providers' => $validated['selectedAiProviders'] ?? [],
                'preferred_ai_provider' => $validated['preferredAiProvider'] ?? null,
                'vertex_mode' => $validated['vertexMode'],
                'vertex_configured' => $vertexCredentialPayload !== null,
                'v98store_mode' => $validated['v98StoreMode'],
                'v98store_configured' => $v98StoreCredentialPayload !== null,
                'login_credentials_mail_sent' => $mailSent,
            ],
            actor: auth()->user(),
            actorType: 'admin',
        );

        $this->isOpen = false;
        $this->resetForm();
        $this->dispatch('users-updated');
        $this->dispatch(
            'toast',
            type: $mailSent ? 'success' : 'warning',
            title: $mailSent ? 'Successfully saved!' : 'Da tao user, mail chua gui duoc',
            message: $mailSent ? 'Da tao user moi va gui mail thong tin dang nhap.' : 'User da tao xong nhung mail dang nhap gui that bai. Hay kiem tra cau hinh SMTP.',
        );
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
            'username',
            'email',
            'password',
            'is_admin',
            'can_generate_amazon_listing',
            'can_generate_etsy_listing',
            'can_access_wali',
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
     * Send the initial login credentials email to the newly created user.
     */
    private function sendLoginCredentialsMail(User $user, string $plainPassword): bool
    {
        $appUrl = $this->accountMailAppUrl();

        try {
            Mail::to($user->email)->send(new UserLoginCredentialsMail(
                user: $user,
                plainPassword: $plainPassword,
                appUrl: $appUrl,
                loginUrl: rtrim($appUrl, '/').'/login',
            ));

            return true;
        } catch (Throwable $exception) {
            if (app()->runningUnitTests()) {
                throw $exception;
            }

            Log::warning('Failed to send new user login credentials mail.', [
                'user_id' => $user->id,
                'email' => $user->email,
                'message' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Resolve the public app URL shown in the account email.
     */
    private function accountMailAppUrl(): string
    {
        $appUrl = rtrim((string) config('app.url'), '/');

        if ($appUrl === '' || str_contains($appUrl, 'localhost') || str_contains($appUrl, '127.0.0.1')) {
            return 'https://xlap.tech';
        }

        return $appUrl;
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
