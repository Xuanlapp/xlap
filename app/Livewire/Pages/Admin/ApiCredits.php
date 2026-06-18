<?php

namespace App\Livewire\Pages\Admin;

use App\Models\ApiCreditTracker;
use App\Models\UserApiCredential;
use App\Models\VertexApiCredential;
use App\Services\Vertex\VertexImageGenerator;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Http;
use Livewire\Attributes\On;
use Livewire\Attributes\Session;
use Livewire\Component;
use Livewire\WithPagination;
use Throwable;

class ApiCredits extends Component
{
    use WithPagination;

    #[Session(key: 'admin.api_credits.search')]
    public string $search = '';

    #[Session(key: 'admin.api_credits.status')]
    public string $status = '';

    /**
     * @var array<string, array{ok: bool, message: string}>
     */
    public array $apiTestResults = [];

    /**
     * Refresh the table after add/edit/delete actions.
     */
    #[On('api-credits-updated')]
    public function refreshCredits(): void
    {
        //
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'status']);
        $this->resetPage();
    }

    /**
     * Soft delete one API credit tracker.
     */
    public function deleteCredit(int $creditId): void
    {
        ApiCreditTracker::query()->findOrFail($creditId)->delete();

        $this->dispatch('api-credits-updated');
        $this->dispatch('toast', type: 'success', title: 'Successfully saved!', message: 'Da xoa API credit.');
    }

    /**
     * Test one active API key credential already saved in user_api_credentials.
     */
    public function testUserApiCredential(int $credentialId): void
    {
        $resultKey = "user_api:{$credentialId}";
        $this->apiTestResults[$resultKey] = ['ok' => false, 'message' => 'Dang test...'];

        $credential = UserApiCredential::query()
            ->with('user:id,name,email')
            ->where('is_active', true)
            ->findOrFail($credentialId);

        try {
            $this->apiTestResults[$resultKey] = match ($credential->provider_key) {
                'v98store' => $this->testV98StoreCredential($credential),
                default => $this->testOpenAiCompatibleCredential($credential),
            };
        } catch (Throwable $exception) {
            $this->apiTestResults[$resultKey] = [
                'ok' => false,
                'message' => $exception->getMessage(),
            ];
        }
    }

    /**
     * Test one active Vertex credential that supports a lightweight text request.
     */
    public function testVertexCredential(int $credentialId, VertexImageGenerator $generator): void
    {
        $resultKey = "vertex:{$credentialId}";
        $this->apiTestResults[$resultKey] = ['ok' => false, 'message' => 'Dang test...'];

        $credential = VertexApiCredential::query()
            ->where('is_active', true)
            ->findOrFail($credentialId);

        if ($credential->function_key !== 'marketplace_listing') {
            $this->apiTestResults[$resultKey] = [
                'ok' => false,
                'message' => 'Key Vertex nay dung tao anh, panel nay khong test tao anh de tranh ton credit.',
            ];

            return;
        }

        try {
            $text = $generator->generateText(auth()->user(), <<<'PROMPT'
Return ONLY valid JSON. Do not include markdown.
{"status":"ok","title":"Vertex listing test title","tags":"vertex test, listing test"}
PROMPT);

            $payload = json_decode(trim($text), true);

            $this->apiTestResults[$resultKey] = is_array($payload) && ($payload['status'] ?? null) === 'ok'
                ? ['ok' => true, 'message' => 'Vertex title/listing OK. JSON hop le.']
                : ['ok' => false, 'message' => 'Vertex co tra ve text nhung khong dung JSON test mong doi.'];
        } catch (Throwable $exception) {
            $this->apiTestResults[$resultKey] = [
                'ok' => false,
                'message' => $exception->getMessage(),
            ];
        }
    }

    public function render(): View
    {
        $search = trim($this->search);

        $credits = ApiCreditTracker::query()
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $like = '%'.$search.'%';
                    $query->where('name', 'like', $like)
                        ->orWhere('provider', 'like', $like)
                        ->orWhere('account_email', 'like', $like)
                        ->orWhere('credit_code', 'like', $like);
                });
            })
            ->orderByRaw('expires_at IS NULL')
            ->orderBy('expires_at')
            ->latest('id')
            ->paginate(20);

        return view('livewire.pages.admin.api-credits', [
            'credits' => $credits,
            'statuses' => ApiCreditTracker::STATUSES,
            'userApiCredentials' => UserApiCredential::query()
                ->with('user:id,name,email')
                ->where('is_active', true)
                ->latest('id')
                ->get(['id', 'user_id', 'provider_key', 'name', 'is_active', 'created_at', 'updated_at']),
            'vertexApiCredentials' => VertexApiCredential::query()
                ->with('user:id,name,email')
                ->where('is_active', true)
                ->latest('id')
                ->get(['id', 'user_id', 'function_key', 'project_id', 'location', 'client_email', 'is_active', 'created_at', 'updated_at']),
        ])->layout('layouts.app');
    }

    /**
     * @return array{ok: bool, message: string}
     */
    private function testV98StoreCredential(UserApiCredential $credential): array
    {
        $endpoint = config('services.api_key_providers.v98store.balance_endpoint', 'https://v98store.com/check-balance');

        if (! is_string($endpoint) || trim($endpoint) === '') {
            return ['ok' => false, 'message' => 'Chua cau hinh balance endpoint cho v98Store.'];
        }

        $response = Http::timeout(15)->get(trim($endpoint), [
            'key' => $this->plainApiKey($credential),
        ]);

        if ($response->failed()) {
            return ['ok' => false, 'message' => $this->httpErrorMessage('v98Store balance loi', $response->status(), $response->body())];
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            return ['ok' => false, 'message' => 'v98Store khong tra ve JSON balance hop le.'];
        }

        $remain = is_numeric($payload['remain_quota'] ?? null) ? $payload['remain_quota'] + 0 : null;
        $used = is_numeric($payload['used_quota'] ?? null) ? $payload['used_quota'] + 0 : null;
        $name = is_string($payload['name'] ?? null) ? $payload['name'] : 'v98Store';

        return [
            'ok' => true,
            'message' => "{$name} OK. Remain: $".($remain ?? '0').($used !== null ? " | Used: {$used}" : ''),
        ];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    private function testOpenAiCompatibleCredential(UserApiCredential $credential): array
    {
        $providerKey = $credential->provider_key;
        $endpoint = config("services.api_key_providers.{$providerKey}.text_endpoint");

        if (! is_string($endpoint) || trim($endpoint) === '') {
            return ['ok' => false, 'message' => "Provider {$this->providerLabel($providerKey)} chua co text endpoint de test."];
        }

        $response = Http::withToken($this->plainApiKey($credential))
            ->timeout(45)
            ->asJson()
            ->post(trim($endpoint), [
                'model' => config("services.api_key_providers.{$providerKey}.text_model", 'gpt-4.1-mini'),
                'messages' => [
                    ['role' => 'user', 'content' => 'Return exactly: API_OK'],
                ],
                'temperature' => 0,
            ]);

        if ($response->failed()) {
            return ['ok' => false, 'message' => $this->httpErrorMessage($this->providerLabel($providerKey).' text loi', $response->status(), $response->body())];
        }

        $content = $response->json('choices.0.message.content');

        return is_string($content) && trim($content) !== ''
            ? ['ok' => true, 'message' => $this->providerLabel($providerKey).' OK. Text endpoint tra ve hop le.']
            : ['ok' => false, 'message' => $this->providerLabel($providerKey).' khong tra ve text hop le.'];
    }

    private function plainApiKey(UserApiCredential $credential): string
    {
        try {
            $apiKey = $credential->key_api;
        } catch (Throwable) {
            throw new \RuntimeException('API key khong giai ma duoc tren server nay. Hay nhap lai key bang APP_KEY hien tai.');
        }

        if (! is_string($apiKey) || trim($apiKey) === '') {
            throw new \RuntimeException('API key dang rong.');
        }

        return trim($apiKey);
    }

    private function providerLabel(string $providerKey): string
    {
        return config("ai_providers.providers.{$providerKey}.label", $providerKey);
    }

    private function httpErrorMessage(string $prefix, int $status, string $body): string
    {
        $body = trim(strip_tags($body));
        $body = preg_replace('/\s+/', ' ', $body) ?: $body;

        return "{$prefix}. HTTP {$status}: ".mb_substr($body, 0, 300);
    }
}
